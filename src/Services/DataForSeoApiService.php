<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\DTOs\DataForSeo\CompetitorDomainResult;
use Platform\Integrations\DTOs\DataForSeo\KeywordVolumeResult;
use Platform\Integrations\DTOs\DataForSeo\LabsKeywordResult;
use Platform\Integrations\DTOs\DataForSeo\OnPageResult;
use Platform\Integrations\DTOs\DataForSeo\RankedKeywordResult;
use Platform\Integrations\DTOs\DataForSeo\RelatedKeywordResult;
use Platform\Integrations\DTOs\DataForSeo\SerpOrganicResult;
use Platform\Integrations\Exceptions\DataForSeoApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der DataForSEO API v3
 *
 * Stellt authentifizierte HTTP-Requests an die DataForSEO API bereit.
 * Credentials (Login/Password für Basic Auth) werden aus der IntegrationConnection gelesen.
 *
 * Endpoints:
 * - POST /v3/keywords_data/google_ads/search_volume/live – Suchvolumen
 * - POST /v3/keywords_data/google_ads/keywords_for_keywords/live – Verwandte Keywords
 * - POST /v3/keywords_data/google_ads/keyword_suggestions/live – Keyword-Vorschläge
 * - POST /v3/serp/google/organic/live/regular – SERP Organic
 * - POST /v3/dataforseo_labs/google/keyword_suggestions/live – Labs Keyword Suggestions
 * - POST /v3/dataforseo_labs/google/related_keywords/live – Labs Related Keywords
 * - POST /v3/dataforseo_labs/google/ranked_keywords/live – Labs Ranked Keywords
 * - POST /v3/dataforseo_labs/google/competitors_domain/live – Labs Competitors Domain
 * - POST /v3/on_page/instant_pages – On-Page Instant
 *
 * @see https://docs.dataforseo.com/v3/
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

    /**
     * SERP Organic Ergebnisse für ein Keyword abrufen
     *
     * @param User $user
     * @param string $keyword Keyword
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param int|null $languageCode Language Code (Default: 1001 = German)
     * @param string $device Device type ('desktop' oder 'mobile')
     * @return SerpOrganicResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getSerpOrganic(
        User $user,
        string $keyword,
        ?int $locationCode = null,
        ?int $languageCode = null,
        string $device = 'desktop',
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageCode = $languageCode ?? config('integrations.dataforseo.default_language_code', self::DEFAULT_LANGUAGE_CODE);

        $payload = [
            [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'device' => $device,
                'depth' => 100,
            ],
        ];

        $response = $this->post($user, '/v3/serp/google/organic/live/regular', $payload);

        return $this->extractSerpOrganicResults($response, $keyword);
    }

    /**
     * Labs Keyword Suggestions abrufen
     *
     * @param User $user
     * @param string[] $keywords Seed-Keywords
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param int|null $languageCode Language Code (Default: 1001 = German)
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return LabsKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getLabsKeywordSuggestions(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?int $languageCode = null,
        int $limit = 100,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageCode = $languageCode ?? config('integrations.dataforseo.default_language_code', self::DEFAULT_LANGUAGE_CODE);

        $payload = [
            [
                'keywords' => array_values($keywords),
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'limit' => $limit,
                'include_seed_keyword' => true,
            ],
        ];

        $response = $this->post($user, '/v3/dataforseo_labs/google/keyword_suggestions/live', $payload);

        return $this->extractLabsKeywordResults($response);
    }

    /**
     * Labs Related Keywords abrufen
     *
     * @param User $user
     * @param string[] $keywords Seed-Keywords
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param int|null $languageCode Language Code (Default: 1001 = German)
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return LabsKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getLabsRelatedKeywords(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?int $languageCode = null,
        int $limit = 100,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageCode = $languageCode ?? config('integrations.dataforseo.default_language_code', self::DEFAULT_LANGUAGE_CODE);

        $payload = [
            [
                'keywords' => array_values($keywords),
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'limit' => $limit,
                'include_seed_keyword' => true,
            ],
        ];

        $response = $this->post($user, '/v3/dataforseo_labs/google/related_keywords/live', $payload);

        return $this->extractLabsKeywordResults($response);
    }

    /**
     * Ranked Keywords für eine Domain/URL abrufen
     *
     * @param User $user
     * @param string $target Domain oder URL
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param int|null $languageCode Language Code (Default: 1001 = German)
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return RankedKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getRankedKeywords(
        User $user,
        string $target,
        ?int $locationCode = null,
        ?int $languageCode = null,
        int $limit = 100,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageCode = $languageCode ?? config('integrations.dataforseo.default_language_code', self::DEFAULT_LANGUAGE_CODE);

        $payload = [
            [
                'target' => $target,
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'limit' => $limit,
            ],
        ];

        $response = $this->post($user, '/v3/dataforseo_labs/google/ranked_keywords/live', $payload);

        return $this->extractRankedKeywordResults($response);
    }

    /**
     * Competitor Domains für eine Domain abrufen
     *
     * @param User $user
     * @param string $target Domain
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param int|null $languageCode Language Code (Default: 1001 = German)
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return CompetitorDomainResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getCompetitorsDomain(
        User $user,
        string $target,
        ?int $locationCode = null,
        ?int $languageCode = null,
        int $limit = 20,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageCode = $languageCode ?? config('integrations.dataforseo.default_language_code', self::DEFAULT_LANGUAGE_CODE);

        $payload = [
            [
                'target' => $target,
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'limit' => $limit,
            ],
        ];

        $response = $this->post($user, '/v3/dataforseo_labs/google/competitors_domain/live', $payload);

        return $this->extractCompetitorDomainResults($response);
    }

    /**
     * On-Page Instant Analyse einer URL
     *
     * @param User $user
     * @param string $url URL die analysiert werden soll
     * @return OnPageResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getOnPageInstant(
        User $user,
        string $url,
    ): array {
        $payload = [
            [
                'url' => $url,
                'enable_javascript' => true,
            ],
        ];

        $response = $this->post($user, '/v3/on_page/instant_pages', $payload);

        return $this->extractOnPageResults($response);
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

    /**
     * Extrahiert SerpOrganicResult-Objekte aus der API-Response
     *
     * @return SerpOrganicResult[]
     */
    protected function extractSerpOrganicResults(array $response, string $keyword): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $resultSet) {
                $items = $resultSet['items'] ?? [];
                foreach ($items as $item) {
                    if (($item['type'] ?? '') === 'organic') {
                        $results[] = SerpOrganicResult::fromApiResult($item, $keyword);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Extrahiert LabsKeywordResult-Objekte aus der API-Response
     *
     * @return LabsKeywordResult[]
     */
    protected function extractLabsKeywordResults(array $response): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $resultSet) {
                $items = $resultSet['items'] ?? [];
                foreach ($items as $item) {
                    $results[] = LabsKeywordResult::fromApiResult($item);
                }
            }
        }

        return $results;
    }

    /**
     * Extrahiert RankedKeywordResult-Objekte aus der API-Response
     *
     * @return RankedKeywordResult[]
     */
    protected function extractRankedKeywordResults(array $response): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $resultSet) {
                $items = $resultSet['items'] ?? [];
                foreach ($items as $item) {
                    $results[] = RankedKeywordResult::fromApiResult($item);
                }
            }
        }

        return $results;
    }

    /**
     * Extrahiert CompetitorDomainResult-Objekte aus der API-Response
     *
     * @return CompetitorDomainResult[]
     */
    protected function extractCompetitorDomainResults(array $response): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $resultSet) {
                $items = $resultSet['items'] ?? [];
                foreach ($items as $item) {
                    $results[] = CompetitorDomainResult::fromApiResult($item);
                }
            }
        }

        return $results;
    }

    /**
     * Extrahiert OnPageResult-Objekte aus der API-Response
     *
     * @return OnPageResult[]
     */
    protected function extractOnPageResults(array $response): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $resultSet) {
                $items = $resultSet['items'] ?? [];
                foreach ($items as $item) {
                    $results[] = OnPageResult::fromApiResult($item);
                }
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
