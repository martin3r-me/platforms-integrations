<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\DTOs\DataForSeo\KeywordVolumeResult;
use Platform\Integrations\DTOs\DataForSeo\RelatedKeywordResult;
use Platform\Integrations\Exceptions\DataForSeoApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der DataForSEO Keywords Data API v3
 *
 * Stellt authentifizierte HTTP-Requests an die DataForSEO API bereit.
 * Credentials (Login/Password für Basic Auth) werden aus der IntegrationConnection gelesen.
 *
 * Endpoints:
 * - POST /v3/keywords_data/google_ads/search_volume/live – Suchvolumen
 * - POST /v3/keywords_data/google_ads/keywords_for_keywords/live – Verwandte Keywords
 * - POST /v3/keywords_data/google_ads/keyword_suggestions/live – Keyword-Vorschläge
 *
 * @see https://docs.dataforseo.com/v3/keywords_data/google_ads/
 */
class DataForSeoApiService
{
    protected const BASE_URL = 'https://api.dataforseo.com';

    /** Default Location: Germany */
    protected const DEFAULT_LOCATION_CODE = 2276;

    /** Default Language: German */
    protected const DEFAULT_LANGUAGE_CODE = 1001;

    protected DataForSeoIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(DataForSeoIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    /**
     * Gibt eine Kopie dieses Services zurück, die eine spezifische Connection verwendet.
     */
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
     * Löst die IntegrationConnection für den User auf.
     */
    protected function resolveConnection(User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $resolver = app(IntegrationConnectionResolver::class);
            $connection = $resolver->resolveById($this->connectionIdOverride, $user);
        } else {
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('DataForSEO API: Keine Connection für User', ['user_id' => $user->id]);
            throw DataForSeoApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Suchvolumen für eine Liste von Keywords abrufen
     *
     * @param User $user
     * @param string[] $keywords Liste der Keywords (max. 700 pro Request)
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param int|null $languageCode Language Code (Default: 1001 = German)
     * @return KeywordVolumeResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getSearchVolume(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?int $languageCode = null,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageCode = $languageCode ?? config('integrations.dataforseo.default_language_code', self::DEFAULT_LANGUAGE_CODE);

        $payload = [
            [
                'keywords' => array_values($keywords),
                'location_code' => $locationCode,
                'language_code' => $languageCode,
            ],
        ];

        $response = $this->post($user, '/v3/keywords_data/google_ads/search_volume/live', $payload);

        return $this->extractKeywordVolumeResults($response);
    }

    /**
     * Verwandte Keywords für gegebene Keywords abrufen
     *
     * @param User $user
     * @param string[] $keywords Seed-Keywords
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param int|null $languageCode Language Code (Default: 1001 = German)
     * @return RelatedKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getRelatedKeywords(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?int $languageCode = null,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageCode = $languageCode ?? config('integrations.dataforseo.default_language_code', self::DEFAULT_LANGUAGE_CODE);

        $payload = [
            [
                'keywords' => array_values($keywords),
                'location_code' => $locationCode,
                'language_code' => $languageCode,
            ],
        ];

        $response = $this->post($user, '/v3/keywords_data/google_ads/keywords_for_keywords/live', $payload);

        return $this->extractRelatedKeywordResults($response);
    }

    /**
     * Keyword-Vorschläge für gegebene Keywords abrufen
     *
     * @param User $user
     * @param string[] $keywords Seed-Keywords
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param int|null $languageCode Language Code (Default: 1001 = German)
     * @return RelatedKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getKeywordSuggestions(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?int $languageCode = null,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageCode = $languageCode ?? config('integrations.dataforseo.default_language_code', self::DEFAULT_LANGUAGE_CODE);

        $payload = [
            [
                'keywords' => array_values($keywords),
                'location_code' => $locationCode,
                'language_code' => $languageCode,
            ],
        ];

        $response = $this->post($user, '/v3/keywords_data/google_ads/keyword_suggestions/live', $payload);

        return $this->extractRelatedKeywordResults($response);
    }

    // =========================================================================
    // RESULT EXTRACTION
    // =========================================================================

    /**
     * Extrahiert KeywordVolumeResult-Objekte aus der API-Response
     *
     * @return KeywordVolumeResult[]
     */
    protected function extractKeywordVolumeResults(array $response): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $result) {
                $results[] = KeywordVolumeResult::fromApiResult($result);
            }
        }

        return $results;
    }

    /**
     * Extrahiert RelatedKeywordResult-Objekte aus der API-Response
     *
     * @return RelatedKeywordResult[]
     */
    protected function extractRelatedKeywordResults(array $response): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $result) {
                $results[] = RelatedKeywordResult::fromApiResult($result);
            }
        }

        return $results;
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * POST Request an die DataForSEO API
     *
     * @throws DataForSeoApiException
     */
    protected function post(User $user, string $endpoint, array $data = []): array
    {
        return $this->request($user, $endpoint, $data);
    }

    /**
     * Führt einen HTTP Request an die DataForSEO API aus
     *
     * DataForSEO verwendet Basic Auth (Login + Password).
     * Alle Endpoints nutzen POST.
     *
     * @throws DataForSeoApiException
     */
    protected function request(User $user, string $endpoint, array $data = []): array
    {
        $connection = $this->resolveConnection($user);

        $credentials = $this->integrationService->getCredentials($connection);

        if (!$credentials) {
            Log::warning('DataForSEO API: Keine Credentials für User', ['user_id' => $user->id]);
            throw DataForSeoApiException::unauthorized();
        }

        $baseUrl = config('integrations.dataforseo.api_base_url', self::BASE_URL);
        $url = $baseUrl . $endpoint;
        $timeout = config('integrations.dataforseo.timeout.default', 30);
        $connectTimeout = config('integrations.dataforseo.timeout.connect', 10);

        try {
            $response = Http::withBasicAuth($credentials['login'], $credentials['password'])
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $data);

            return $this->handleResponse($response, $connection);
        } catch (DataForSeoApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('DataForSEO API: Verbindungsfehler', [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw DataForSeoApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP Response und behandelt Fehler
     *
     * @throws DataForSeoApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        // Auth-Fehler
        if ($statusCode === 401) {
            $this->updateConnectionStatus($connection, 'error', 'Ungültige Credentials');
            throw DataForSeoApiException::unauthorized();
        }

        // Rate-Limit
        if ($statusCode === 429) {
            throw DataForSeoApiException::rateLimitExceeded();
        }

        // Erfolgreiche Response (2xx)
        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');

            // DataForSEO gibt status_code im Body zurück – auch bei HTTP 200 können Task-Fehler auftreten
            $apiStatusCode = $data['status_code'] ?? 20000;
            if ($apiStatusCode >= 40000) {
                throw DataForSeoApiException::fromResponse($statusCode, $data);
            }

            return $data;
        }

        // Sonstige Fehler
        $this->updateConnectionStatus(
            $connection,
            'active',
            $data['status_message'] ?? null
        );

        Log::warning('DataForSEO API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw DataForSeoApiException::fromResponse($statusCode, $data);
    }

    /**
     * Aktualisiert den Status der IntegrationConnection
     */
    protected function updateConnectionStatus(
        IntegrationConnection $connection,
        string $status,
        ?string $error = null
    ): void {
        $connection->status = $status;
        $connection->last_error = $error;
        $connection->last_tested_at = now();
        $connection->save();
    }
}
