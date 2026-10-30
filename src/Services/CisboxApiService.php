<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\CisboxApiException;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Support\HttpTransientFailure;

/**
 * Service für die cisbox Invoice API v1.
 *
 * Auth:     API-Key im Header X-API-Key, siehe {@see CisboxIntegrationService}.
 * Base-URL: https://api.cisbox.com/invoice/v1
 * Umfang:   27 Operationen in 11 Themenbereichen, katalogisiert in
 *           {@see CisboxResource}. Jeder Aufruf wird gegen den Katalog geprüft,
 *           damit Tippfehler sofort auffallen statt in einem 404 zu enden.
 *
 * ANTWORT-HÜLLE
 * Jede Antwort ist gleich aufgebaut:
 *   { "status": "...", "data": [ … ], "meta": { totalItems, totalPages, currentPage, itemsPerPage } }
 * "data" ist IMMER ein Array — auch bei Einzelabrufen wie GET /invoices/{barcodeNo}.
 * {@see single()} holt daraus den einen Datensatz; der Katalog markiert über
 * "single", wo das zutrifft.
 *
 * PAGINIERUNG
 * Seitenbasiert über page (ab 1) und itemsPerPage (max. 2000, Standard 200).
 * meta.totalPages nennt die Gesamtzahl — {@see fetchAll()} läuft sie durch.
 *
 * WIEDERHOLTE QUERY-PARAMETER
 * "status" bei GET /invoices/pending wird mehrfach gesetzt (status=A&status=B).
 * Deshalb baut {@see buildQueryString()} die Query selbst: Guzzle würde ein
 * Array als status[0]=A kodieren, was die API ignoriert — das Ergebnis sähe
 * gefiltert aus, obwohl der Filter nie gegriffen hat.
 *
 * @see https://api.cisbox.com/docs/ui/invoice-api-v1
 */
class CisboxApiService
{
    /** Obergrenze der API für itemsPerPage. */
    public const MAX_ITEMS_PER_PAGE = 2000;

    /** Mindestlänge des Volltext-Parameters "q" — kürzer antwortet die API mit 422. */
    public const MIN_SEARCH_LENGTH = 4;

    protected CisboxIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    /** @var callable|null */
    protected $observer = null;

    public function __construct(CisboxIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function forConnection(?int $connectionId): static
    {
        if ($connectionId === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->connectionIdOverride = $connectionId;

        return $clone;
    }

    /**
     * Beobachter für jeden Aufruf: (method, path, status, durationMs, requestId).
     * Nötig, weil call() nur die dekodierten Daten zurückgibt und die
     * Korrelations-ID erfolgreicher Antworten sonst verloren ginge.
     */
    public function withObserver(callable $observer): static
    {
        $clone = clone $this;
        $clone->observer = $observer;

        return $clone;
    }

    /** @throws CisboxApiException */
    protected function resolveConnection(?User $user): IntegrationConnection
    {
        // resolveById prüft die Zugriffsrechte des Users und verlangt ihn
        // deshalb zwingend — ohne User gibt es keine auflösbare Verbindung.
        if (!$user) {
            throw CisboxApiException::noConnection();
        }

        $connection = $this->connectionIdOverride
            ? app(IntegrationConnectionResolver::class)->resolveById($this->connectionIdOverride, $user)
            : $this->integrationService->getConnectionForUser($user);

        if (!$connection) {
            Log::warning('cisbox API: Keine Connection', [
                'user_id' => $user->id,
                'connection_override' => $this->connectionIdOverride,
            ]);

            throw CisboxApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // GENERISCHER ZUGRIFF
    // =========================================================================

    /**
     * Führt eine katalogisierte Operation aus.
     *
     * Pfad-Platzhalter werden aus $pathParams ersetzt: call($u, 'GET',
     * '/invoices/{barcodeNo}', ['barcodeNo' => 4711]) wird zu /invoices/4711.
     *
     * @param array<string, mixed> $pathParams
     * @param array<string, mixed> $query
     * @return array<mixed> Die vollständige Hülle inkl. "data" und "meta"
     *
     * @throws CisboxApiException
     */
    public function call(?User $user, string $method, string $path, array $pathParams = [], array $query = []): array
    {
        $method = mb_strtoupper(trim($method));
        $path = '/' . ltrim(trim($path), '/');

        if (!CisboxResource::exists($method, $path)) {
            throw CisboxApiException::unknownOperation($method, $path);
        }

        if ($method !== 'GET') {
            // Die Spezifikation kennt ausschließlich GET. Ein anderer Verb käme
            // nur durch einen Fehler hierher.
            throw new CisboxApiException(
                "Die cisbox Invoice API ist lesend — \"{$method}\" gibt es nicht.",
                405,
                'METHOD_NOT_ALLOWED'
            );
        }

        return $this->request($user, $this->resolvePath($path, $pathParams), $query);
    }

    /**
     * Ersetzt {platzhalter} im Pfad.
     *
     * @param array<string, mixed> $params
     *
     * @throws CisboxApiException
     */
    protected function resolvePath(string $path, array $params): string
    {
        return preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $m) use ($params, $path) {
                $value = $params[$m[1]] ?? null;

                if ($value === null || $value === '') {
                    throw CisboxApiException::missingPathParameter($m[1], $path);
                }

                return rawurlencode((string) $value);
            },
            $path
        ) ?? $path;
    }

    /**
     * Läuft eine paginierte Liste über alle Seiten.
     *
     * meta.totalPages ist maßgeblich; maxPages deckelt zusätzlich, damit eine
     * unerwartet große Ressource nicht den ganzen Speicher zieht. Wurde
     * gedeckelt, sagt das Ergebnis es über "truncated" — stillschweigend eine
     * Teilmenge zurückzugeben wäre schlimmer als gar keine.
     *
     * @param array<string, mixed> $pathParams
     * @param array<string, mixed> $query
     * @return array{data: array<int, mixed>, meta: array<string, mixed>}
     *
     * @throws CisboxApiException
     */
    public function fetchAll(
        ?User $user,
        string $path,
        array $pathParams = [],
        array $query = [],
        int $itemsPerPage = 500,
        int $maxPages = 50
    ): array {
        $itemsPerPage = max(1, min($itemsPerPage, self::MAX_ITEMS_PER_PAGE));
        $maxPages = max(1, $maxPages);

        $items = [];
        $page = (int) ($query['page'] ?? 1);
        $page = max(1, $page);
        $firstPage = $page;
        $totalPages = null;
        $totalItems = null;
        $fetched = 0;

        do {
            $response = $this->call($user, 'GET', $path, $pathParams, array_merge($query, [
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
            ]));

            $chunk = $this->items($response);
            $items = array_merge($items, $chunk);
            $fetched++;

            $totalPages ??= (int) ($response['meta']['totalPages'] ?? 1);
            $totalItems ??= $response['meta']['totalItems'] ?? null;

            $page++;
        } while ($page <= $totalPages && $fetched < $maxPages && $chunk !== []);

        $lastFetched = $firstPage + $fetched - 1;

        return [
            'data' => $items,
            'meta' => [
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'fetchedPages' => $fetched,
                'itemsPerPage' => $itemsPerPage,
                'returnedItems' => count($items),
                'truncated' => $lastFetched < (int) $totalPages,
            ],
        ];
    }

    /**
     * Die Einträge aus "data".
     *
     * @param array<mixed> $response
     * @return array<int, mixed>
     */
    public function items(array $response): array
    {
        $data = $response['data'] ?? null;

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * Der eine Datensatz aus "data" — für Operationen, die der Katalog als
     * "single" führt. Ein leeres Array bedeutet: nicht gefunden.
     *
     * @param array<mixed> $response
     * @return array<string, mixed>|null
     */
    public function single(array $response): ?array
    {
        $first = $this->items($response)[0] ?? null;

        return is_array($first) ? $first : null;
    }

    // =========================================================================
    // KOMFORT-METHODEN
    // =========================================================================

    /** @return array<mixed> */
    public function listInvoices(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/invoices', [], $query);
    }

    /** @return array<string, mixed>|null */
    public function getInvoice(?User $user, int|string $barcodeNo): ?array
    {
        return $this->single($this->call($user, 'GET', '/invoices/{barcodeNo}', ['barcodeNo' => $barcodeNo]));
    }

    /** @return array<int, mixed> */
    public function getInvoiceItems(?User $user, int|string $barcodeNo): array
    {
        return $this->items($this->call($user, 'GET', '/invoices/{barcodeNo}/items', ['barcodeNo' => $barcodeNo]));
    }

    /** @return array<mixed> */
    public function listPendingInvoices(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/invoices/pending', [], $query);
    }

    /** @return array<mixed> */
    public function listCreditors(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/creditors', [], $query);
    }

    /** @return array<string, mixed>|null */
    public function getCreditor(?User $user, int|string $creditorNo): ?array
    {
        return $this->single($this->call($user, 'GET', '/creditors/{creditorNo}', ['creditorNo' => $creditorNo]));
    }

    /** @return array<mixed> */
    public function listPayments(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/payments', [], $query);
    }

    /** @return array<mixed> */
    public function listDeliveries(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/deliveries', [], $query);
    }

    /**
     * Das PDF einer Rechnung als Rohdaten.
     *
     * Die Spezifikation deklariert fileData als Array von base64-Teilen; in der
     * Praxis liefert Jackson dort einen einzelnen String. Beide Formen werden
     * zusammengeführt, damit der Aufrufer sich nicht darum kümmern muss.
     *
     * @return array{base64: string, size: int, mime: string, filename: string}
     *
     * @throws CisboxApiException
     */
    public function getInvoicePdf(?User $user, int|string $barcodeNo): array
    {
        $file = $this->single($this->call($user, 'GET', '/invoices/{barcodeNo}/pdf', ['barcodeNo' => $barcodeNo]));
        $raw = $file['fileData'] ?? null;

        $base64 = is_array($raw) ? implode('', array_map('strval', $raw)) : (string) ($raw ?? '');
        $base64 = preg_replace('/\s+/', '', $base64) ?? '';

        if ($base64 === '') {
            throw CisboxApiException::notFound("Zu Rechnung {$barcodeNo} liegt kein PDF vor.");
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw CisboxApiException::invalidResponse('Das PDF-Feld "fileData" ist kein gültiges Base64.');
        }

        return [
            'base64' => $base64,
            'size' => strlen($decoded),
            'mime' => 'application/pdf',
            'filename' => "rechnung-{$barcodeNo}.pdf",
        ];
    }

    // =========================================================================
    // HTTP
    // =========================================================================

    /**
     * Führt den Request aus.
     *
     * @param array<string, mixed> $query
     * @return array<mixed>
     *
     * @throws CisboxApiException
     */
    protected function request(?User $user, string $path, array $query = []): array
    {
        $connection = $this->resolveConnection($user);
        $apiKey = $this->integrationService->getApiKey($connection);

        if (!$apiKey) {
            Log::warning('cisbox API: Kein API-Key', ['connection_id' => $connection->id]);

            throw CisboxApiException::unauthorized();
        }

        $queryString = $this->buildQueryString($query);
        $url = $this->integrationService->getBaseUrl($connection) . $path
            . ($queryString !== '' ? '?' . $queryString : '');

        try {
            $startedAt = microtime(true);
            $response = $this->buildRequest($apiKey)->get($url);
            $this->notifyObserver($path, $response, $startedAt);
        } catch (\Throwable $e) {
            $timeout = (int) config('integrations.cisbox.timeout.default', 30);
            $message = HttpTransientFailure::describe($e, 'cisbox', $timeout);

            Log::error('cisbox API: Verbindungsfehler', [
                'connection_id' => $connection->id,
                'path' => $path,
                'error' => $e->getMessage(),
                'transient' => HttpTransientFailure::isTransient($e),
            ]);

            // Netzwerkfehler sagen nichts über den API-Key aus — der Status
            // bleibt stehen, damit geteilte Verbindungen nutzbar bleiben.
            if (HttpTransientFailure::isTransient($e)) {
                $connection->last_error = $message;
                $connection->last_tested_at = now();
                $connection->save();
            }

            throw CisboxApiException::connectionError($message);
        }

        return $this->handleResponse($response, $connection);
    }

    protected function buildRequest(string $apiKey): PendingRequest
    {
        return Http::withHeaders([
            'X-API-Key' => $apiKey,
            'Accept' => 'application/json',
        ])
            ->connectTimeout((int) config('integrations.cisbox.timeout.connect', 10))
            ->timeout((int) config('integrations.cisbox.timeout.default', 30))
            // Die API kennt nur GET, jeder Aufruf ist also idempotent. Wiederholt
            // wird dennoch nur bei sporadischen Verbindungsabbrüchen, nicht bei
            // Read-Timeouts — siehe HttpTransientFailure.
            ->retry(
                max(1, (int) config('integrations.cisbox.retry.times', 2)),
                max(0, (int) config('integrations.cisbox.retry.sleep_ms', 500)),
                static fn (\Throwable $e) => HttpTransientFailure::isRetryable($e),
                false
            );
    }

    /**
     * Baut den Query-String.
     *
     * Array-Werte werden als wiederholter Parameter geschrieben
     * (status=APPROVED&status=PAID), weil die API genau das erwartet.
     * Booleans werden zu "true"/"false", weil 1/0 dort nicht als Wahrheitswert
     * gelesen wird. Leere Werte fallen raus — nur echte Null und "false"
     * bleiben, denn cancelled=false ist ein sinnvoller Filter.
     *
     * @param array<string, mixed> $query
     */
    protected function buildQueryString(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $single) {
                if ($single === null || $single === '') {
                    continue;
                }

                if (is_bool($single)) {
                    $single = $single ? 'true' : 'false';
                }

                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $single);
            }
        }

        return implode('&', $parts);
    }

    /**
     * @return array<mixed>
     *
     * @throws CisboxApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $requestId = $response->header('X-Request-Id') ?: null;
        $data = $response->json();

        if ($response->successful()) {
            $connection->status = 'active';
            $connection->last_error = null;
            $connection->last_tested_at = now();
            $connection->save();

            if (!is_array($data)) {
                throw CisboxApiException::invalidResponse(
                    'Erwartet wurde eine JSON-Hülle mit "data".'
                    . ($requestId ? " (X-Request-Id: {$requestId})" : '')
                );
            }

            if ($requestId) {
                $data['request_id'] = $requestId;
            }

            return $data;
        }

        $exception = CisboxApiException::fromResponse(
            $response->status(),
            is_array($data) ? $data : [],
            $response->body(),
            $requestId
        );

        // Nur ein Key-Fehler markiert die Verbindung als defekt — fachliche
        // Fehler (404, 422, 429) und Störungen lassen sie aktiv. Der 502 des
        // Gateways bei ungültigem Key zählt als Key-Fehler, siehe
        // CisboxApiException::indicatesInvalidKey().
        if ($exception->indicatesInvalidKey()) {
            $connection->status = 'error';
        }

        $connection->last_error = $exception->getMessage();
        $connection->last_tested_at = now();
        $connection->save();

        Log::warning('cisbox API: Fehler-Response', [
            'connection_id' => $connection->id,
            'status_code' => $response->status(),
            'request_id' => $requestId,
        ]);

        throw $exception;
    }

    protected function notifyObserver(string $path, Response $response, float $startedAt): void
    {
        if ($this->observer) {
            ($this->observer)(
                'GET',
                $path,
                $response->status(),
                (int) round((microtime(true) - $startedAt) * 1000),
                $response->header('X-Request-Id') ?: null
            );
        }
    }

    /**
     * Reduziert eine Antwort auf die gewünschten Felder (Dot-Notation für
     * verschachtelte). Delegiert an die gemeinsame Projektion, die "data" als
     * Liste und "meta"/"status" als Hülle bereits kennt.
     */
    public static function projectFields(mixed $response, array $fields): mixed
    {
        return \Platform\Integrations\Support\FieldProjection::apply($response, $fields);
    }
}
