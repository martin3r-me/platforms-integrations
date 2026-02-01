<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\SipgateApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der Sipgate API
 *
 * Dieser Service bietet:
 * - Zentralen Zugriffspunkt für alle Sipgate API Endpunkte
 * - Automatische Token-Erneuerung bei 401
 * - Rate-Limit-Handling mit Backoff
 * - Retry-Logik mit exponentiellem Backoff
 * - Circuit-Breaker zum Schutz bei wiederholten Fehlern
 * - Request-Tracing
 *
 * API Dokumentation:
 * @see https://developer.sipgate.io
 */
class SipgateApiService
{
    protected const BASE_URL = 'https://api.sipgate.com/v2';

    // Circuit Breaker Konfiguration
    protected const CIRCUIT_FAILURE_THRESHOLD = 5;
    protected const CIRCUIT_RECOVERY_TIME = 60; // Sekunden
    protected const CIRCUIT_CACHE_KEY = 'sipgate_circuit_breaker';

    // Retry Konfiguration
    protected const MAX_RETRIES = 3;
    protected const INITIAL_RETRY_DELAY = 1000; // Millisekunden
    protected const MAX_RETRY_DELAY = 10000; // Millisekunden

    // Timeout Konfiguration
    protected const DEFAULT_TIMEOUT = 30;
    protected const CONNECT_TIMEOUT = 10;

    protected SipgateIntegrationService $integrationService;

    public function __construct(SipgateIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    // =========================================================================
    // ACCOUNT & USER INFO
    // =========================================================================

    /**
     * Ruft die Benutzerinformationen ab
     *
     * @see https://developer.sipgate.io/rest-api/users
     *
     * @throws SipgateApiException
     */
    public function getUserInfo(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/authorization/userinfo');
    }

    /**
     * Ruft das Benutzerprofil ab
     *
     * @throws SipgateApiException
     */
    public function getAccount(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/account');
    }

    /**
     * Ruft alle Users eines Accounts ab (nur für Admins)
     *
     * @throws SipgateApiException
     */
    public function getUsers(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/users');
    }

    /**
     * Ruft einen spezifischen User ab
     *
     * @throws SipgateApiException
     */
    public function getUser(User $user, string $userId): array
    {
        return $this->makeRequest($user, 'GET', "/users/{$userId}");
    }

    /**
     * Ruft das Guthaben ab
     *
     * @throws SipgateApiException
     */
    public function getBalance(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/balance');
    }

    // =========================================================================
    // TELEFONNUMMERN & GERÄTE
    // =========================================================================

    /**
     * Ruft alle Telefonnummern ab
     *
     * @throws SipgateApiException
     */
    public function getNumbers(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/numbers');
    }

    /**
     * Ruft alle Geräte/Devices ab
     *
     * @throws SipgateApiException
     */
    public function getDevices(User $user, ?string $userId = null): array
    {
        $endpoint = $userId ? "/{$userId}/devices" : '/devices';
        return $this->makeRequest($user, 'GET', $endpoint);
    }

    // =========================================================================
    // ANRUFE (CALLS)
    // =========================================================================

    /**
     * Initiiert einen Anruf (Click-to-Call)
     *
     * @param string $caller Die Absender-Nummer oder Device-ID
     * @param string $callee Die Zielnummer
     * @param string|null $callerId Anzuzeigende Caller-ID
     *
     * @throws SipgateApiException
     */
    public function initiateCall(User $user, string $caller, string $callee, ?string $callerId = null): array
    {
        $payload = [
            'caller' => $caller,
            'callee' => $callee,
        ];

        if ($callerId) {
            $payload['callerId'] = $callerId;
        }

        return $this->makeRequest($user, 'POST', '/sessions/calls', $payload);
    }

    /**
     * Hängt einen aktiven Anruf auf
     *
     * @throws SipgateApiException
     */
    public function hangupCall(User $user, string $sessionId): array
    {
        return $this->makeRequest($user, 'DELETE', "/sessions/calls/{$sessionId}");
    }

    // =========================================================================
    // ANRUFHISTORIE (HISTORY)
    // =========================================================================

    /**
     * Ruft die Anrufhistorie ab
     *
     * @param array $filters Optional: Filter für die Historie
     *  - types: array (CALL, SMS, FAX, VOICEMAIL)
     *  - directions: array (INCOMING, OUTGOING, MISSED)
     *  - archived: bool
     *  - starred: bool
     *  - from: string (ISO 8601 Datum)
     *  - to: string (ISO 8601 Datum)
     *  - phonenumber: string
     *  - limit: int (max 5000)
     *  - offset: int
     *
     * @throws SipgateApiException
     */
    public function getHistory(User $user, array $filters = []): array
    {
        $query = array_filter([
            'types' => isset($filters['types']) ? implode(',', $filters['types']) : null,
            'directions' => isset($filters['directions']) ? implode(',', $filters['directions']) : null,
            'archived' => isset($filters['archived']) ? ($filters['archived'] ? 'true' : 'false') : null,
            'starred' => isset($filters['starred']) ? ($filters['starred'] ? 'true' : 'false') : null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'phonenumber' => $filters['phonenumber'] ?? null,
            'limit' => $filters['limit'] ?? 100,
            'offset' => $filters['offset'] ?? 0,
        ], fn($v) => $v !== null);

        return $this->makeRequest($user, 'GET', '/history', null, $query);
    }

    /**
     * Ruft einen einzelnen History-Eintrag ab
     *
     * @throws SipgateApiException
     */
    public function getHistoryEntry(User $user, string $entryId): array
    {
        return $this->makeRequest($user, 'GET', "/history/{$entryId}");
    }

    /**
     * Archiviert einen History-Eintrag
     *
     * @throws SipgateApiException
     */
    public function archiveHistoryEntry(User $user, string $entryId, bool $archived = true): array
    {
        return $this->makeRequest($user, 'PUT', "/history/{$entryId}", [
            'archived' => $archived,
        ]);
    }

    /**
     * Markiert einen History-Eintrag als Favorit
     *
     * @throws SipgateApiException
     */
    public function starHistoryEntry(User $user, string $entryId, bool $starred = true): array
    {
        return $this->makeRequest($user, 'PUT', "/history/{$entryId}", [
            'starred' => $starred,
        ]);
    }

    /**
     * Löscht einen History-Eintrag
     *
     * @throws SipgateApiException
     */
    public function deleteHistoryEntry(User $user, string $entryId): void
    {
        $this->makeRequest($user, 'DELETE', "/history/{$entryId}");
    }

    // =========================================================================
    // SMS
    // =========================================================================

    /**
     * Sendet eine SMS
     *
     * @param string $smsId Die SMS-Extension-ID (z.B. 's0')
     * @param string $recipient Die Empfängernummer
     * @param string $message Der Nachrichtentext
     *
     * @throws SipgateApiException
     */
    public function sendSms(User $user, string $smsId, string $recipient, string $message): array
    {
        return $this->makeRequest($user, 'POST', '/sessions/sms', [
            'smsId' => $smsId,
            'recipient' => $recipient,
            'message' => $message,
        ]);
    }

    /**
     * Ruft alle SMS-Erweiterungen ab
     *
     * @throws SipgateApiException
     */
    public function getSmsExtensions(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/sms');
    }

    // =========================================================================
    // FAX
    // =========================================================================

    /**
     * Sendet ein Fax
     *
     * @param string $faxlineId Die Faxline-ID
     * @param string $recipient Die Empfängernummer
     * @param string $base64Pdf Das PDF als Base64-encodierter String
     * @param string|null $filename Optional: Dateiname
     *
     * @throws SipgateApiException
     */
    public function sendFax(User $user, string $faxlineId, string $recipient, string $base64Pdf, ?string $filename = null): array
    {
        $payload = [
            'faxlineId' => $faxlineId,
            'recipient' => $recipient,
            'base64Content' => $base64Pdf,
        ];

        if ($filename) {
            $payload['filename'] = $filename;
        }

        return $this->makeRequest($user, 'POST', '/sessions/fax', $payload);
    }

    /**
     * Ruft alle Faxlines ab
     *
     * @throws SipgateApiException
     */
    public function getFaxlines(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/faxlines');
    }

    // =========================================================================
    // VOICEMAIL
    // =========================================================================

    /**
     * Ruft alle Voicemails ab
     *
     * @throws SipgateApiException
     */
    public function getVoicemails(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/voicemails');
    }

    /**
     * Lädt eine Voicemail-Aufnahme herunter (als URL)
     *
     * @throws SipgateApiException
     */
    public function getVoicemailRecording(User $user, string visibleId): array
    {
        return $this->makeRequest($user, 'GET', "/voicemails/{$visibleId}/recording");
    }

    /**
     * Lädt eine Voicemail-Aufnahme als Audio-Datei herunter
     *
     * @throws SipgateApiException
     */
    public function downloadVoicemailRecording(User $user, string $visibleId): Response
    {
        $connection = $this->integrationService->getConnectionForUser($user);
        if (!$connection) {
            throw SipgateApiException::noConnection();
        }

        $accessToken = $this->integrationService->getAccessTokenForUser($user);
        if (!$accessToken) {
            throw SipgateApiException::unauthorized();
        }

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'audio/wav',
        ])->timeout(self::DEFAULT_TIMEOUT)->get(self::BASE_URL . "/voicemails/{$visibleId}/recording");
    }

    // =========================================================================
    // CONTACTS
    // =========================================================================

    /**
     * Ruft alle Kontakte ab
     *
     * @throws SipgateApiException
     */
    public function getContacts(User $user, int $limit = 100, int $offset = 0): array
    {
        return $this->makeRequest($user, 'GET', '/contacts', null, [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Ruft einen einzelnen Kontakt ab
     *
     * @throws SipgateApiException
     */
    public function getContact(User $user, string $contactId): array
    {
        return $this->makeRequest($user, 'GET', "/contacts/{$contactId}");
    }

    /**
     * Erstellt einen neuen Kontakt
     *
     * @throws SipgateApiException
     */
    public function createContact(User $user, array $contactData): array
    {
        return $this->makeRequest($user, 'POST', '/contacts', $contactData);
    }

    /**
     * Aktualisiert einen Kontakt
     *
     * @throws SipgateApiException
     */
    public function updateContact(User $user, string $contactId, array $contactData): array
    {
        return $this->makeRequest($user, 'PUT', "/contacts/{$contactId}", $contactData);
    }

    /**
     * Löscht einen Kontakt
     *
     * @throws SipgateApiException
     */
    public function deleteContact(User $user, string $contactId): void
    {
        $this->makeRequest($user, 'DELETE', "/contacts/{$contactId}");
    }

    // =========================================================================
    // WEBHOOKS
    // =========================================================================

    /**
     * Ruft alle Webhooks ab
     *
     * @throws SipgateApiException
     */
    public function getWebhooks(User $user): array
    {
        return $this->makeRequest($user, 'GET', '/settings/sipgateio');
    }

    /**
     * Erstellt oder aktualisiert Webhook-Einstellungen
     *
     * @param array $settings Webhook-Einstellungen
     *  - incomingUrl: string URL für eingehende Anrufe
     *  - outgoingUrl: string URL für ausgehende Anrufe
     *  - log: bool Logging aktivieren
     *  - whitelist: array Whitelist für Nummern
     *
     * @throws SipgateApiException
     */
    public function setWebhooks(User $user, array $settings): array
    {
        return $this->makeRequest($user, 'PUT', '/settings/sipgateio', $settings);
    }

    /**
     * Löscht alle Webhook-Einstellungen
     *
     * @throws SipgateApiException
     */
    public function deleteWebhooks(User $user): void
    {
        $this->makeRequest($user, 'DELETE', '/settings/sipgateio');
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    /**
     * Ruft Call-Forwarding-Einstellungen ab
     *
     * @throws SipgateApiException
     */
    public function getCallForwarding(User $user, string $userId): array
    {
        return $this->makeRequest($user, 'GET', "/{$userId}/forwarding");
    }

    /**
     * Setzt Call-Forwarding-Einstellungen
     *
     * @throws SipgateApiException
     */
    public function setCallForwarding(User $user, string $userId, array $settings): array
    {
        return $this->makeRequest($user, 'PUT', "/{$userId}/forwarding", $settings);
    }

    // =========================================================================
    // HEALTH CHECK
    // =========================================================================

    /**
     * Führt einen Health-Check durch
     *
     * @return array{healthy: bool, latency_ms: int, circuit_open: bool, error?: string}
     */
    public function healthCheck(User $user): array
    {
        $start = microtime(true);

        // Prüfe Circuit-Breaker-Status
        if ($this->isCircuitOpen()) {
            return [
                'healthy' => false,
                'latency_ms' => 0,
                'circuit_open' => true,
                'error' => 'Circuit breaker is open',
            ];
        }

        try {
            $this->getUserInfo($user);
            $latency = (int) ((microtime(true) - $start) * 1000);

            return [
                'healthy' => true,
                'latency_ms' => $latency,
                'circuit_open' => false,
            ];
        } catch (SipgateApiException $e) {
            $latency = (int) ((microtime(true) - $start) * 1000);

            return [
                'healthy' => false,
                'latency_ms' => $latency,
                'circuit_open' => $this->isCircuitOpen(),
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // INTERNE METHODEN
    // =========================================================================

    /**
     * Führt eine API-Anfrage mit allen Schutz-Mechanismen durch
     *
     * @throws SipgateApiException
     */
    protected function makeRequest(
        User $user,
        string $method,
        string $endpoint,
        ?array $payload = null,
        array $query = []
    ): array {
        // 1. Circuit-Breaker prüfen
        if ($this->isCircuitOpen()) {
            throw SipgateApiException::circuitOpen(self::CIRCUIT_RECOVERY_TIME);
        }

        // 2. Connection und Token abrufen
        $connection = $this->integrationService->getConnectionForUser($user);
        if (!$connection) {
            throw SipgateApiException::noConnection();
        }

        $requestId = $this->generateRequestId();
        $attempt = 0;
        $lastException = null;

        // 3. Retry-Loop mit exponentiellem Backoff
        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            try {
                return $this->executeRequest($connection, $method, $endpoint, $payload, $query, $requestId);
            } catch (SipgateApiException $e) {
                $lastException = $e;

                // Bei 401: Token-Refresh versuchen
                if ($e->shouldRefreshToken() && $attempt === 1) {
                    try {
                        $connection = $this->integrationService->refreshToken($connection);
                        continue; // Erneut versuchen mit neuem Token
                    } catch (SipgateApiException $refreshError) {
                        throw $refreshError; // Refresh fehlgeschlagen, abbrechen
                    }
                }

                // Bei Rate-Limit: Warten und erneut versuchen
                if ($e->isRateLimited() && $e->isRetryable()) {
                    $retryAfter = $e->getRetryAfter() ?? 60;
                    if ($retryAfter <= 120) { // Max 2 Minuten warten
                        $this->delay($retryAfter * 1000);
                        continue;
                    }
                }

                // Bei anderen retriebaren Fehlern: Backoff
                if ($e->isRetryable() && $attempt < self::MAX_RETRIES) {
                    $this->delay($this->calculateBackoff($attempt));
                    continue;
                }

                // Circuit-Breaker: Fehler zählen
                $this->recordFailure();

                throw $e;
            }
        }

        // Alle Retries erschöpft
        throw $lastException ?? SipgateApiException::connectionError('Maximum retries exceeded', $requestId);
    }

    /**
     * Führt die eigentliche HTTP-Anfrage durch
     *
     * @throws SipgateApiException
     */
    protected function executeRequest(
        IntegrationConnection $connection,
        string $method,
        string $endpoint,
        ?array $payload,
        array $query,
        string $requestId
    ): array {
        // Access Token abrufen
        $accessToken = $this->integrationService->getAccessToken($connection);
        if (!$accessToken) {
            throw SipgateApiException::unauthorized();
        }

        $url = self::BASE_URL . $endpoint;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        Log::debug('Sipgate API request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'request_id' => $requestId,
        ]);

        try {
            $request = $this->buildRequest($accessToken, $requestId);

            /** @var Response $response */
            $response = match ($method) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $payload ?? []),
                'PUT' => $request->put($url, $payload ?? []),
                'PATCH' => $request->patch($url, $payload ?? []),
                'DELETE' => $request->delete($url, $payload ?? []),
                default => throw new \InvalidArgumentException("Unknown HTTP method: {$method}"),
            };

            // Erfolg: Circuit-Breaker zurücksetzen
            $this->recordSuccess();

            // Response verarbeiten
            if (!$response->successful()) {
                throw SipgateApiException::fromResponse(
                    $response->status(),
                    $response->json(),
                    $requestId
                );
            }

            // 204 No Content
            if ($response->status() === 204) {
                return ['success' => true];
            }

            return $response->json() ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw SipgateApiException::connectionError($e->getMessage(), $requestId);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response) {
                throw SipgateApiException::fromResponse(
                    $e->response->status(),
                    $e->response->json(),
                    $requestId
                );
            }
            throw SipgateApiException::connectionError($e->getMessage(), $requestId);
        }
    }

    /**
     * Erstellt einen konfigurierten HTTP-Client
     */
    protected function buildRequest(string $accessToken, string $requestId): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Request-ID' => $requestId,
        ])
            ->timeout(self::DEFAULT_TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->retry(0); // Wir handhaben Retries selbst
    }

    /**
     * Generiert eine Request-ID für Tracing
     */
    protected function generateRequestId(): string
    {
        return 'sipgate-' . bin2hex(random_bytes(8)) . '-' . time();
    }

    /**
     * Berechnet die Backoff-Zeit für einen Retry
     */
    protected function calculateBackoff(int $attempt): int
    {
        $delay = self::INITIAL_RETRY_DELAY * (2 ** ($attempt - 1));
        $jitter = random_int(0, (int) ($delay * 0.1));
        return min($delay + $jitter, self::MAX_RETRY_DELAY);
    }

    /**
     * Wartet für die angegebene Zeit
     */
    protected function delay(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    // =========================================================================
    // CIRCUIT BREAKER
    // =========================================================================

    /**
     * Prüft, ob der Circuit-Breaker offen ist
     */
    protected function isCircuitOpen(): bool
    {
        $state = Cache::get(self::CIRCUIT_CACHE_KEY);

        if (!$state) {
            return false;
        }

        // Prüfen ob Recovery-Zeit abgelaufen
        if ($state['open_since'] && (time() - $state['open_since']) > self::CIRCUIT_RECOVERY_TIME) {
            // Half-Open: Nächster Request wird als Test behandelt
            Cache::put(self::CIRCUIT_CACHE_KEY, [
                'failures' => 0,
                'open_since' => null,
                'half_open' => true,
            ], 3600);
            return false;
        }

        return $state['open_since'] !== null;
    }

    /**
     * Registriert einen Fehler für den Circuit-Breaker
     */
    protected function recordFailure(): void
    {
        $state = Cache::get(self::CIRCUIT_CACHE_KEY, ['failures' => 0, 'open_since' => null, 'half_open' => false]);

        $state['failures']++;

        // Bei Half-Open sofort wieder öffnen
        if ($state['half_open']) {
            $state['open_since'] = time();
            $state['half_open'] = false;
        }
        // Threshold erreicht: Circuit öffnen
        elseif ($state['failures'] >= self::CIRCUIT_FAILURE_THRESHOLD) {
            $state['open_since'] = time();
            Log::warning('Sipgate circuit breaker opened', [
                'failures' => $state['failures'],
            ]);
        }

        Cache::put(self::CIRCUIT_CACHE_KEY, $state, 3600);
    }

    /**
     * Registriert einen Erfolg für den Circuit-Breaker
     */
    protected function recordSuccess(): void
    {
        $state = Cache::get(self::CIRCUIT_CACHE_KEY);

        if ($state && ($state['half_open'] || $state['failures'] > 0)) {
            // Reset bei Erfolg nach Half-Open oder Fehlern
            Cache::put(self::CIRCUIT_CACHE_KEY, [
                'failures' => 0,
                'open_since' => null,
                'half_open' => false,
            ], 3600);

            if ($state['half_open']) {
                Log::info('Sipgate circuit breaker closed after successful test');
            }
        }
    }

    /**
     * Manuelles Zurücksetzen des Circuit-Breakers
     */
    public function resetCircuitBreaker(): void
    {
        Cache::forget(self::CIRCUIT_CACHE_KEY);
        Log::info('Sipgate circuit breaker manually reset');
    }

    /**
     * Gibt den aktuellen Circuit-Breaker-Status zurück
     */
    public function getCircuitBreakerStatus(): array
    {
        $state = Cache::get(self::CIRCUIT_CACHE_KEY, ['failures' => 0, 'open_since' => null, 'half_open' => false]);

        return [
            'status' => $state['open_since'] ? 'open' : ($state['half_open'] ? 'half-open' : 'closed'),
            'failures' => $state['failures'],
            'open_since' => $state['open_since'] ? date('Y-m-d H:i:s', $state['open_since']) : null,
            'recovery_at' => $state['open_since']
                ? date('Y-m-d H:i:s', $state['open_since'] + self::CIRCUIT_RECOVERY_TIME)
                : null,
        ];
    }
}
