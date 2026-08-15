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
use Platform\Integrations\DTOs\DataForSeo\GoogleBusinessInfoResult;
use Platform\Integrations\DTOs\DataForSeo\GoogleTrendsResult;
use Platform\Integrations\DTOs\DataForSeo\SerpFeaturesResult;
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
    protected const DEFAULT_LANGUAGE_NAME = 'German';

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
    protected function resolveConnection(?User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            if ($user) {
                $resolver = app(IntegrationConnectionResolver::class);
                $connection = $resolver->resolveById($this->connectionIdOverride, $user);
            } else {
                // Scheduler/CLI: direkt per ID laden ohne Access-Check
                $connection = IntegrationConnection::with('integration')->find($this->connectionIdOverride);
            }
        } else {
            if (!$user) {
                throw DataForSeoApiException::noConnection();
            }
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('DataForSEO API: Keine Connection', ['user_id' => $user?->id, 'connection_override' => $this->connectionIdOverride]);
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
     * @param string|null $languageName Language Name (Default: 'German')
     * @return KeywordVolumeResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getSearchVolume(
        ?User $user,
        array $keywords,
        ?int $locationCode = null,
        ?string $languageName = null,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'keywords' => array_values($keywords),
                'location_code' => $locationCode,
                'language_name' => $languageName,
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
     * @param string|null $languageName Language Name (Default: 'German')
     * @return RelatedKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getRelatedKeywords(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?string $languageName = null,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'keywords' => array_values($keywords),
                'location_code' => $locationCode,
                'language_name' => $languageName,
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
     * @param string|null $languageName Language Name (Default: 'German')
     * @return RelatedKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getKeywordSuggestions(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?string $languageName = null,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'keywords' => array_values($keywords),
                'location_code' => $locationCode,
                'language_name' => $languageName,
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
     * @param string|null $languageName Language Name (Default: 'German')
     * @param string $device Device type ('desktop' oder 'mobile')
     * @return SerpOrganicResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getSerpOrganic(
        ?User $user,
        string $keyword,
        ?int $locationCode = null,
        ?string $languageName = null,
        string $device = 'desktop',
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'language_name' => $languageName,
                'device' => $device,
                'depth' => 100,
            ],
        ];

        $response = $this->post($user, '/v3/serp/google/organic/live/regular', $payload);

        return $this->extractSerpOrganicResults($response, $keyword);
    }

    /**
     * Wie getSerpOrganic, liefert aber aus DEMSELBEN Call zusätzlich die SERP-Features
     * (People-Also-Ask, Related Searches, Featured Snippet, AI-Overview) — bisher
     * verworfen, obwohl bezahlt.
     *
     * @return array{organic: SerpOrganicResult[], features: SerpFeaturesResult}
     *
     * @throws DataForSeoApiException
     */
    public function getSerpWithFeatures(
        ?User $user,
        string $keyword,
        ?int $locationCode = null,
        ?string $languageName = null,
        string $device = 'desktop',
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'language_name' => $languageName,
                'device' => $device,
                'depth' => 100,
            ],
        ];

        // WICHTIG: /advanced (statt /regular) — nur dieser Endpoint liefert die
        // nicht-organischen SERP-Elemente (People-Also-Ask, AI-Overview, Featured
        // Snippet, Related Searches). /regular strippt sie. Kosten quasi identisch.
        $response = $this->post($user, '/v3/serp/google/organic/live/advanced', $payload);

        return [
            'organic' => $this->extractSerpOrganicResults($response, $keyword),
            'features' => $this->extractSerpFeatures($response, $keyword),
        ];
    }

    /**
     * Extrahiert die SERP-Features (nicht-organische Elemente) aus der Response.
     */
    protected function extractSerpFeatures(array $response, string $keyword): SerpFeaturesResult
    {
        foreach ($response['tasks'] ?? [] as $task) {
            foreach ($task['result'] ?? [] as $resultSet) {
                return SerpFeaturesResult::fromItems($resultSet['items'] ?? [], $keyword);
            }
        }

        return SerpFeaturesResult::fromItems([], $keyword);
    }

    /**
     * Labs Search Intent — klassifiziert Keywords in informational / navigational /
     * commercial / transactional. Bulk bis 1000 Keywords pro Call.
     *
     * @param string[] $keywords
     * @return array<string, array{label: string, probability: float|null}> keyword(lower) => intent
     *
     * @throws DataForSeoApiException
     */
    public function getSearchIntent(
        ?User $user,
        array $keywords,
        ?string $languageName = null,
    ): array {
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $keywords = array_values(array_filter(array_map('strval', $keywords)));
        if (empty($keywords)) {
            return [];
        }

        $payload = [
            [
                'keywords' => array_slice($keywords, 0, 1000),
                'language_name' => $languageName,
            ],
        ];

        $response = $this->post($user, '/v3/dataforseo_labs/google/search_intent/live', $payload);

        $map = [];
        foreach ($response['tasks'] ?? [] as $task) {
            foreach ($task['result'] ?? [] as $resultSet) {
                foreach ($resultSet['items'] ?? [] as $item) {
                    $kw = $item['keyword'] ?? null;
                    $intent = $item['keyword_intent'] ?? null;
                    if ($kw && is_array($intent) && !empty($intent['label'])) {
                        $map[mb_strtolower(trim($kw))] = [
                            'label' => (string) $intent['label'],
                            'probability' => isset($intent['probability']) ? (float) $intent['probability'] : null,
                        ];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Labs Keyword Suggestions abrufen
     *
     * @param User $user
     * @param string[] $keywords Seed-Keywords
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param string|null $languageName Language Name (Default: 'German')
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return LabsKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getLabsKeywordSuggestions(
        ?User $user,
        array $keywords,
        ?int $locationCode = null,
        ?string $languageName = null,
        int $limit = 100,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = array_map(fn(string $kw) => [
            'keyword' => $kw,
            'location_code' => $locationCode,
            'language_name' => $languageName,
            'limit' => $limit,
            'include_seed_keyword' => true,
        ], array_values($keywords));

        $response = $this->post($user, '/v3/dataforseo_labs/google/keyword_suggestions/live', $payload);

        return $this->extractLabsKeywordResults($response);
    }

    /**
     * Labs Related Keywords abrufen
     *
     * @param User $user
     * @param string[] $keywords Seed-Keywords
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param string|null $languageName Language Name (Default: 'German')
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return LabsKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getLabsRelatedKeywords(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?string $languageName = null,
        int $limit = 100,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = array_map(fn(string $kw) => [
            'keyword' => $kw,
            'location_code' => $locationCode,
            'language_name' => $languageName,
            'limit' => $limit,
            'include_seed_keyword' => true,
        ], array_values($keywords));

        $response = $this->post($user, '/v3/dataforseo_labs/google/related_keywords/live', $payload);

        return $this->extractLabsKeywordResults($response);
    }

    /**
     * Ranked Keywords für eine Domain/URL abrufen
     *
     * @param User $user
     * @param string $target Domain oder URL
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param string|null $languageName Language Name (Default: 'German')
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return RankedKeywordResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getRankedKeywords(
        ?User $user,
        string $target,
        ?int $locationCode = null,
        ?string $languageName = null,
        int $limit = 100,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'target' => $target,
                'location_code' => $locationCode,
                'language_name' => $languageName,
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
     * @param string|null $languageName Language Name (Default: 'German')
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return CompetitorDomainResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getCompetitorsDomain(
        User $user,
        string $target,
        ?int $locationCode = null,
        ?string $languageName = null,
        int $limit = 20,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'target' => $target,
                'location_code' => $locationCode,
                'language_name' => $languageName,
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
        ?User $user,
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

    /**
     * Backlinks für ein Ziel (Domain, Subdomain oder Seiten-URL) abrufen.
     *
     * @param string $target Ziel — Domain ohne Schema oder absolute Seiten-URL
     * @param int $limit Max. Anzahl Zeilen (Default 1000)
     * @param string $mode as_is | one_per_domain | one_per_anchor
     * @return array Erstes Result-Objekt (enthält total_count + items[])
     *
     * @throws DataForSeoApiException
     */
    public function getBacklinks(
        ?User $user,
        string $target,
        int $limit = 1000,
        string $mode = 'one_per_domain',
    ): array {
        $payload = [
            [
                'target' => $target,
                'limit' => $limit,
                'mode' => $mode,
                'backlinks_status_type' => 'live',
            ],
        ];

        $response = $this->post($user, '/v3/backlinks/backlinks/live', $payload);

        return $response['tasks'][0]['result'][0] ?? [];
    }

    /**
     * Domain-/Seiten-level Backlink-Summary für ein Ziel abrufen.
     *
     * Ein günstiger Aggregat-Call (/v3/backlinks/summary/live) liefert die
     * wichtigsten Autoritäts-Signale in einem Rutsch: referring_domains, rank
     * (0–1000), Spam-Score und broken_backlinks — im Gegensatz zu getBacklinks(),
     * das die einzelnen verweisenden Seiten zurückgibt.
     *
     * @param User|null $user
     * @param string $target Domain oder URL (z.B. "tm-foodsolutions.de" oder volle URL)
     * @return array Rohes result[0]-Objekt der DataForSEO-Antwort
     *
     * @throws DataForSeoApiException
     */
    public function getBacklinksSummary(?User $user, string $target): array
    {
        $payload = [
            [
                'target' => $target,
                'internal_list_limit' => 10,
                'backlinks_status_type' => 'live',
            ],
        ];

        $response = $this->post($user, '/v3/backlinks/summary/live', $payload);

        return $response['tasks'][0]['result'][0] ?? [];
    }

    /**
     * LLM-Mentions Target-Metrics für eine Domain (AI-Auffindbarkeit).
     *
     * Liefert aggregierte Sichtbarkeit einer Domain in LLM-Antworten
     * (ChatGPT + Google AI Overview): Gesamt-Mentions, AI-Suchvolumen und
     * Breakdown je Plattform. Ohne platform-Angabe werden beide berücksichtigt.
     *
     * @param User|null $user
     * @param string $target Domain (z.B. "tm-foodsolutions.de")
     * @param int|null $locationCode Default 2276 (Deutschland)
     * @param string|null $languageName Default 'German'
     * @return array Rohes result[0]-Objekt (u.a. aggregated_metrics)
     *
     * @throws DataForSeoApiException
     */
    public function getLlmMentionsTargetMetrics(
        ?User $user,
        string $target,
        ?int $locationCode = null,
        ?string $languageName = null,
    ): array {
        $payload = [
            [
                'target' => [
                    ['domain' => $target],
                ],
                'location_code' => $locationCode ?? self::DEFAULT_LOCATION_CODE,
                'language_name' => $languageName ?? self::DEFAULT_LANGUAGE_NAME,
            ],
        ];

        $response = $this->post($user, '/v3/ai_optimization/llm_mentions/target_metrics/live', $payload);

        return $response['tasks'][0]['result'][0] ?? [];
    }

    /**
     * Google Business Info für ein Keyword abrufen
     *
     * @param User $user
     * @param string $keyword Keyword oder place_id:ChIJ...
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param string|null $languageName Language Name (Default: 'German')
     * @return GoogleBusinessInfoResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getGoogleBusinessInfo(
        User $user,
        string $keyword,
        ?int $locationCode = null,
        ?string $languageName = null,
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'language_name' => $languageName,
            ],
        ];

        $response = $this->post($user, '/v3/business_data/google/my_business_info/live', $payload);

        return $this->extractGoogleBusinessInfoResults($response);
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

    /**
     * Extrahiert GoogleBusinessInfoResult-Objekte aus der API-Response
     *
     * @return GoogleBusinessInfoResult[]
     */
    protected function extractGoogleBusinessInfoResults(array $response): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $resultSet) {
                $items = $resultSet['items'] ?? [];
                foreach ($items as $item) {
                    $results[] = GoogleBusinessInfoResult::fromApiResult($item);
                }
            }
        }

        return $results;
    }

    /**
     * Google Trends Explore — Trend-Daten für Keywords abrufen
     *
     * @param User $user
     * @param string[] $keywords Keywords (max. 5 pro Request)
     * @param int|null $locationCode Location Code (Default: 2276 = Germany)
     * @param string|null $languageName Language Name (Default: 'German')
     * @param string $timeRange Zeitraum (z.B. 'past_12_months')
     * @return GoogleTrendsResult[]
     *
     * @throws DataForSeoApiException
     */
    public function getGoogleTrendsExplore(
        User $user,
        array $keywords,
        ?int $locationCode = null,
        ?string $languageName = null,
        string $timeRange = 'past_12_months',
    ): array {
        $locationCode = $locationCode ?? config('integrations.dataforseo.default_location_code', self::DEFAULT_LOCATION_CODE);
        $languageName = $languageName ?? config('integrations.dataforseo.default_language_name', self::DEFAULT_LANGUAGE_NAME);

        $payload = [
            [
                'keywords' => array_values(array_slice($keywords, 0, 5)),
                'location_code' => $locationCode,
                'language_name' => $languageName,
                'time_range' => $timeRange,
                'item_types' => ['google_trends_graph'],
            ],
        ];

        $response = $this->post($user, '/v3/keywords_data/google_trends/explore/live', $payload);

        return $this->extractGoogleTrendsResults($response, $keywords);
    }

    /**
     * Verfügbare SERP-Locations für Google abrufen (kostenlos, kein Credit-Verbrauch).
     *
     * @param User $user  Wird für Connection-Auth benötigt
     * @param string|null $country  ISO-2 Country Code Filter (z.B. 'DE', 'AT', 'CH')
     * @return array<array{location_code: int, location_name: string, country_iso_code: string, location_type: string}>
     *
     * @throws DataForSeoApiException
     */
    public function getLocations(User $user, ?string $country = null): array
    {
        $endpoint = '/v3/serp/google/locations';

        if ($country) {
            $endpoint .= '/' . strtoupper($country);
        }

        $response = $this->get($user, $endpoint);

        $locations = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $result) {
                $locations[] = [
                    'location_code' => $result['location_code'] ?? null,
                    'location_name' => $result['location_name'] ?? null,
                    'country_iso_code' => $result['country_iso_code'] ?? null,
                    'location_type' => $result['location_type'] ?? null,
                ];
            }
        }

        return $locations;
    }

    /**
     * Extrahiert GoogleTrendsResult-Objekte aus der API-Response
     *
     * @param string[] $keywords Die angefragten Keywords
     * @return GoogleTrendsResult[]
     */
    protected function extractGoogleTrendsResults(array $response, array $keywords): array
    {
        $results = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $taskResults = $task['result'] ?? [];
            foreach ($taskResults as $resultSet) {
                $items = $resultSet['items'] ?? [];
                foreach ($items as $item) {
                    if (($item['type'] ?? '') === 'google_trends_graph') {
                        $keyword = $item['keywords'][0] ?? ($keywords[0] ?? '');
                        $results[] = GoogleTrendsResult::fromApiResult($item, $keyword);
                    }
                }
            }
        }

        return $results;
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * GET Request an die DataForSEO API
     *
     * @throws DataForSeoApiException
     */
    protected function get(User $user, string $endpoint): array
    {
        return $this->request($user, $endpoint, [], 'GET');
    }

    /**
     * POST Request an die DataForSEO API
     *
     * @throws DataForSeoApiException
     */
    protected function post(?User $user, string $endpoint, array $data = []): array
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
    protected function request(?User $user, string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $connection = $this->resolveConnection($user);

        $credentials = $this->integrationService->getCredentials($connection);

        if (!$credentials) {
            Log::warning('DataForSEO API: Keine Credentials', ['user_id' => $user?->id]);
            throw DataForSeoApiException::unauthorized();
        }

        $baseUrl = config('integrations.dataforseo.api_base_url', self::BASE_URL);
        $url = $baseUrl . $endpoint;
        $timeout = config('integrations.dataforseo.timeout.default', 30);
        $connectTimeout = config('integrations.dataforseo.timeout.connect', 10);

        try {
            $http = Http::withBasicAuth($credentials['login'], $credentials['password'])
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ]);

            $response = ($method === 'GET')
                ? $http->get($url)
                : $http->post($url, $data);

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
