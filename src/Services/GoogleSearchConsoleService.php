<?php

namespace Platform\Integrations\Services;

use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\SearchConsole as SearchConsoleService;
use Google\Service\SearchConsole\InspectUrlIndexRequest;
use Google\Service\Webmasters as WebmastersService;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Exceptions\GoogleSearchConsoleApiException;

/**
 * Zentrale Service-Klasse für die Google Search Console API (Service-Account-Auth).
 *
 * Authentifiziert sich per Service-Account-JSON-Key (kein OAuth2-Flow) über die
 * offizielle google/apiclient-Bibliothek. Der Service-Account benötigt Lesezugriff
 * auf die jeweilige Property (in der GSC-UI als Nutzer hinzugefügt).
 *
 * Credentials werden NICHT im Repo abgelegt, sondern verschlüsselt in der DB gehalten
 * (IntegrationConnection.credentials via EncryptedJson bzw. Team-Setting) und via
 * {@see withCredentials()} injiziert. {@see config('google_search_console')} liefert
 * nur lokale Entwicklungs-Fallbacks (ENV-Pfad/-JSON).
 *
 * Zwei API-Flächen unter der Haube:
 * - webmasters/v1  → Sites (getProperties) + Search Analytics (getSearchAnalytics)
 * - searchconsole/v1 → URL Inspection (inspectUrl)
 *
 * Nutzung:
 *   $rows = app(GoogleSearchConsoleService::class)
 *       ->withCredentials($serviceAccountJson)   // aus verschlüsselter DB-Quelle
 *       ->authenticate('sc-domain:example.com')
 *       ->getSearchAnalytics(['startDate' => '2026-01-01', 'endDate' => '2026-01-31']);
 *
 * @see https://developers.google.com/webmaster-tools/v1/searchanalytics/query
 * @see https://developers.google.com/webmaster-tools/limits
 */
class GoogleSearchConsoleService
{
    /**
     * Injizierte Service-Account-Credentials (decodiertes JSON-Array).
     * Hat Vorrang vor den Config-Fallbacks.
     */
    protected ?array $credentials = null;

    protected ?GoogleClient $client = null;

    protected ?WebmastersService $webmasters = null;

    protected ?SearchConsoleService $searchConsole = null;

    /** Aktive Property (siteUrl), z.B. "sc-domain:example.com" oder "https://example.com/" */
    protected ?string $propertyUrl = null;

    /** Zeitstempel (microtime) des letzten Requests je Rate-Limit-Bucket */
    protected array $lastRequestAt = [];

    /** In-Process-Zähler für das Tages-Kontingent der URL Inspection */
    protected int $inspectionsToday = 0;

    // =========================================================================
    // AUTHENTIFIZIERUNG
    // =========================================================================

    /**
     * Setzt die Service-Account-Credentials (decodiertes JSON-Array oder JSON-String).
     *
     * Wird typischerweise mit den entschlüsselten Credentials aus der DB aufgerufen,
     * bevor authenticate() läuft. Fluent.
     *
     * @param array|string $serviceAccount JSON-Key als Array oder String
     * @throws GoogleSearchConsoleApiException bei ungültigem JSON
     */
    public function withCredentials(array|string $serviceAccount): self
    {
        if (is_string($serviceAccount)) {
            $decoded = json_decode($serviceAccount, true);
            if (!is_array($decoded)) {
                throw GoogleSearchConsoleApiException::unauthorized(
                    'Service-Account-Key ist kein gültiges JSON.'
                );
            }
            $serviceAccount = $decoded;
        }

        $this->credentials = $serviceAccount;

        // Ein bereits initialisierter Client wird durch neue Credentials ungültig
        $this->client = null;
        $this->webmasters = null;
        $this->searchConsole = null;

        return $this;
    }

    /**
     * Initialisiert den Google-API-Client mit den Service-Account-Credentials und
     * setzt die Property für alle folgenden Calls.
     *
     * @param string $propertyUrl z.B. "sc-domain:example.com" oder "https://example.com/"
     * @throws GoogleSearchConsoleApiException bei fehlenden/ungültigen Credentials
     */
    public function authenticate(string $propertyUrl): self
    {
        $credentials = $this->resolveCredentials();

        try {
            $client = new GoogleClient();
            $client->setApplicationName(
                (string) config('google_search_console.application_name', 'Platform Integrations')
            );
            $client->setAuthConfig($credentials);
            $client->setScopes(
                (array) config('google_search_console.scopes', [WebmastersService::WEBMASTERS_READONLY])
            );
            $client->setHttpClient(new GuzzleClient([
                'timeout'         => (int) config('google_search_console.timeout.default', 30),
                'connect_timeout' => (int) config('google_search_console.timeout.connect', 10),
            ]));

            $this->client = $client;
            $this->webmasters = new WebmastersService($client);
            $this->searchConsole = new SearchConsoleService($client);
            $this->propertyUrl = $propertyUrl;
        } catch (GoogleSearchConsoleApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Google Search Console: Authentifizierung fehlgeschlagen', [
                'property' => $propertyUrl,
                'error'    => $e->getMessage(),
            ]);
            throw GoogleSearchConsoleApiException::unauthorized(
                'Service-Account-Authentifizierung fehlgeschlagen: ' . $e->getMessage()
            );
        }

        return $this;
    }

    /**
     * Löst die Service-Account-Credentials auf: injizierte Credentials zuerst,
     * danach Config-Fallbacks (JSON-String, dann Pfad zur JSON-Datei).
     *
     * @throws GoogleSearchConsoleApiException wenn keine Credentials verfügbar sind
     */
    protected function resolveCredentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $json = config('google_search_console.credentials_json');
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $path = config('google_search_console.credentials_path');
        if (is_string($path) && $path !== '' && is_readable($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw GoogleSearchConsoleApiException::unauthorized(
            'Keine Service-Account-Credentials konfiguriert. Bitte Credentials via '
            . 'withCredentials() injizieren oder GSC_SERVICE_ACCOUNT_JSON/-PATH setzen.'
        );
    }

    /**
     * Stellt sicher, dass authenticate() gelaufen ist.
     *
     * @throws GoogleSearchConsoleApiException
     */
    protected function ensureAuthenticated(): void
    {
        if ($this->client === null || $this->propertyUrl === null) {
            throw GoogleSearchConsoleApiException::unauthorized(
                'Nicht authentifiziert. Bitte zuerst authenticate($propertyUrl) aufrufen.'
            );
        }
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Fragt Search Analytics für die aktive Property ab und liefert flache Rows.
     *
     * Paginiert automatisch über startRow, falls mehr als row_limit (max. 25.000)
     * Rows verfügbar sind.
     *
     * @param array $params {
     *     startDate:  string  (Pflicht, YYYY-MM-DD),
     *     endDate:    string  (Pflicht, YYYY-MM-DD),
     *     dimensions: array   (query|page|date|device|country|searchAppearance),
     *     rowLimit:   int     (Batch-Größe pro Seite, max. 25.000),
     *     dataState:  string  ('final'|'all'),
     *     type:       string  (web|image|video|news|discover|googleNews),
     *     dimensionFilterGroups: array,
     *     aggregationType: string,
     * }
     * @return array<int, array{keys: array, clicks: float, impressions: float, ctr: float, position: float}>
     * @throws GoogleSearchConsoleApiException
     */
    public function getSearchAnalytics(array $params): array
    {
        $this->ensureAuthenticated();

        if (empty($params['startDate']) || empty($params['endDate'])) {
            throw new GoogleSearchConsoleApiException(
                'getSearchAnalytics benötigt startDate und endDate (YYYY-MM-DD).',
                400,
                'INVALID_ARGUMENT'
            );
        }

        $configuredLimit = (int) config('google_search_console.pagination.row_limit', 25000);
        $pageSize = (int) ($params['rowLimit'] ?? $configuredLimit);
        // Google erlaubt maximal 25.000 Rows pro Request
        $pageSize = max(1, min($pageSize, 25000));

        $maxRows = (int) config('google_search_console.pagination.max_rows', 0);

        $dimensions = array_values((array) ($params['dimensions'] ?? []));

        $rows = [];
        $startRow = 0;

        do {
            $request = new SearchAnalyticsQueryRequest();
            $request->setStartDate($params['startDate']);
            $request->setEndDate($params['endDate']);
            $request->setDimensions($dimensions);
            $request->setRowLimit($pageSize);
            $request->setStartRow($startRow);

            if (!empty($params['dataState'])) {
                $request->setDataState($params['dataState']);
            }
            if (!empty($params['type'])) {
                $request->setType($params['type']);
            }
            if (!empty($params['aggregationType'])) {
                $request->setAggregationType($params['aggregationType']);
            }
            if (!empty($params['dimensionFilterGroups'])) {
                $request->setDimensionFilterGroups($params['dimensionFilterGroups']);
            }

            $response = $this->execute('query', fn () =>
                $this->webmasters->searchanalytics->query($this->propertyUrl, $request)
            );

            $batch = $response->getRows() ?? [];

            foreach ($batch as $row) {
                $rows[] = [
                    'keys'        => $row->getKeys() ?? [],
                    'clicks'      => (float) $row->getClicks(),
                    'impressions' => (float) $row->getImpressions(),
                    'ctr'         => (float) $row->getCtr(),
                    'position'    => (float) $row->getPosition(),
                ];

                if ($maxRows > 0 && count($rows) >= $maxRows) {
                    return array_slice($rows, 0, $maxRows);
                }
            }

            $startRow += $pageSize;
            // Weitere Seite nur, wenn die letzte Seite komplett gefüllt war
        } while (count($batch) === $pageSize);

        return $rows;
    }

    /**
     * Prüft den Indexierungsstatus einer URL (URL Inspection API).
     *
     * @param string $url Vollständige, zu prüfende URL (muss zur aktiven Property gehören)
     * @return array{
     *     verdict: ?string,
     *     coverageState: ?string,
     *     robotsTxtState: ?string,
     *     indexingState: ?string,
     *     lastCrawlTime: ?string,
     *     pageFetchState: ?string,
     *     googleCanonical: ?string,
     *     userCanonical: ?string,
     *     crawledAs: ?string,
     *     referringUrls: array,
     *     sitemap: array,
     * }
     * @throws GoogleSearchConsoleApiException
     */
    public function inspectUrl(string $url): array
    {
        $this->ensureAuthenticated();
        $this->guardInspectionDailyQuota();

        $request = new InspectUrlIndexRequest();
        $request->setInspectionUrl($url);
        $request->setSiteUrl($this->propertyUrl);

        $response = $this->execute('inspect', fn () =>
            $this->searchConsole->urlInspection_index->inspect($request)
        );

        $this->inspectionsToday++;

        $result = $response->getInspectionResult();
        $index = $result?->getIndexStatusResult();

        return [
            'verdict'         => $index?->getVerdict(),
            'coverageState'   => $index?->getCoverageState(),
            'robotsTxtState'  => $index?->getRobotsTxtState(),
            'indexingState'   => $index?->getIndexingState(),
            'lastCrawlTime'   => $index?->getLastCrawlTime(),
            'pageFetchState'  => $index?->getPageFetchState(),
            'googleCanonical' => $index?->getGoogleCanonical(),
            'userCanonical'   => $index?->getUserCanonical(),
            'crawledAs'       => $index?->getCrawledAs(),
            'referringUrls'   => $index?->getReferringUrls() ?? [],
            'sitemap'         => $index?->getSitemap() ?? [],
        ];
    }

    /**
     * Listet alle Properties (Sites) auf, auf die der Service-Account Zugriff hat.
     *
     * Erfordert nur einen initialisierten Client; die konkrete Property ist hier
     * irrelevant. Nützlich für Setup/Validierung.
     *
     * @return array<int, array{siteUrl: ?string, permissionLevel: ?string}>
     * @throws GoogleSearchConsoleApiException
     */
    public function getProperties(): array
    {
        if ($this->webmasters === null) {
            throw GoogleSearchConsoleApiException::unauthorized(
                'Nicht authentifiziert. Bitte zuerst authenticate($propertyUrl) aufrufen.'
            );
        }

        $response = $this->execute('query', fn () => $this->webmasters->sites->listSites());

        $properties = [];
        foreach ($response->getSiteEntry() ?? [] as $site) {
            $properties[] = [
                'siteUrl'         => $site->getSiteUrl(),
                'permissionLevel' => $site->getPermissionLevel(),
            ];
        }

        return $properties;
    }

    // =========================================================================
    // RATE-LIMITING, RETRY & FEHLERBEHANDLUNG
    // =========================================================================

    /**
     * Führt einen API-Call aus – mit Rate-Limit-Drosselung und exponentiellem
     * Backoff bei Quota-/Server-/Netzwerkfehlern.
     *
     * Auth-Fehler (401/403) werden nicht wiederholt, sondern sofort als
     * Re-Auth-nötig gemeldet.
     *
     * @param string   $bucket 'query' (GSC) oder 'inspect' (URL Inspection)
     * @param callable $call   Der eigentliche API-Aufruf
     * @throws GoogleSearchConsoleApiException
     */
    protected function execute(string $bucket, callable $call): mixed
    {
        $maxAttempts = max(1, (int) config('google_search_console.retry.max_attempts', 3));
        $attempt = 0;

        while (true) {
            $attempt++;
            $this->throttle($bucket);

            try {
                return $call();
            } catch (GoogleServiceException $e) {
                $status = $e->getCode();

                // Auth-Fehler: kein Retry, klare Re-Auth-Meldung
                if ($status === 401 || $status === 403) {
                    Log::warning('Google Search Console: Auth-Fehler', [
                        'status'   => $status,
                        'property' => $this->propertyUrl,
                        'message'  => $e->getMessage(),
                    ]);
                    throw GoogleSearchConsoleApiException::unauthorized(
                        'Zugriff verweigert (HTTP ' . $status . '). Service-Account-Berechtigung '
                        . 'für die Property prüfen / neu autorisieren. ' . $e->getMessage()
                    );
                }

                // Quota/Rate-Limit (429) oder transiente Server-Fehler (5xx): Retry
                $isRetryable = $status === 429 || $status >= 500;

                if ($isRetryable && $attempt < $maxAttempts) {
                    $this->backoff($attempt);
                    continue;
                }

                if ($status === 429) {
                    throw GoogleSearchConsoleApiException::rateLimited();
                }

                throw GoogleSearchConsoleApiException::fromResponse(
                    $status ?: 500,
                    ['error' => ['message' => $e->getMessage(), 'code' => $status]]
                );
            } catch (\Throwable $e) {
                // Netzwerk-/Verbindungsfehler: bis zu max_attempts wiederholen
                if ($attempt < $maxAttempts) {
                    Log::info('Google Search Console: Verbindungsfehler, Retry', [
                        'attempt' => $attempt,
                        'error'   => $e->getMessage(),
                    ]);
                    $this->backoff($attempt);
                    continue;
                }

                Log::error('Google Search Console: Verbindungsfehler', [
                    'property' => $this->propertyUrl,
                    'error'    => $e->getMessage(),
                ]);
                throw GoogleSearchConsoleApiException::connectionError($e->getMessage());
            }
        }
    }

    /**
     * Drosselt Requests, sodass das konfigurierte Rate-Limit je Bucket eingehalten
     * wird (Mindestabstand zwischen zwei Requests).
     */
    protected function throttle(string $bucket): void
    {
        $perMinute = $bucket === 'inspect'
            ? (int) config('google_search_console.rate_limits.inspection_per_minute', 600)
            : (int) config('google_search_console.rate_limits.queries_per_minute', 1200);

        if ($perMinute <= 0) {
            return;
        }

        $minIntervalMicros = (int) (60_000_000 / $perMinute);
        $last = $this->lastRequestAt[$bucket] ?? null;

        if ($last !== null) {
            $elapsed = (int) ((microtime(true) - $last) * 1_000_000);
            $wait = $minIntervalMicros - $elapsed;
            if ($wait > 0) {
                usleep($wait);
            }
        }

        $this->lastRequestAt[$bucket] = microtime(true);
    }

    /**
     * Exponentielles Backoff mit Jitter zwischen zwei Versuchen.
     */
    protected function backoff(int $attempt): void
    {
        $base = (int) config('google_search_console.retry.base_delay_ms', 1000);
        $multiplier = (float) config('google_search_console.retry.multiplier', 2.0);
        $maxDelay = (int) config('google_search_console.retry.max_delay_ms', 32000);

        $delay = (int) min($base * ($multiplier ** ($attempt - 1)), $maxDelay);
        // Jitter: bis zu 25 % des Delays aufschlagen, um Thundering-Herd zu vermeiden
        $jitter = $delay > 0 ? random_int(0, (int) ($delay * 0.25)) : 0;

        usleep(($delay + $jitter) * 1000);
    }

    /**
     * Schützt vor Überschreiten des Tages-Kontingents der URL Inspection
     * (in-process gezählt).
     *
     * @throws GoogleSearchConsoleApiException
     */
    protected function guardInspectionDailyQuota(): void
    {
        $perDay = (int) config('google_search_console.rate_limits.inspection_per_day', 2000);

        if ($perDay > 0 && $this->inspectionsToday >= $perDay) {
            throw GoogleSearchConsoleApiException::rateLimited();
        }
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getPropertyUrl(): ?string
    {
        return $this->propertyUrl;
    }

    public function getClient(): ?GoogleClient
    {
        return $this->client;
    }
}
