<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO Labs Keyword-Ergebnisse (Suggestions, Related)
 *
 * @see https://docs.dataforseo.com/v3/dataforseo_labs/google/keyword_suggestions/live/
 * @see https://docs.dataforseo.com/v3/dataforseo_labs/google/related_keywords/live/
 */
class LabsKeywordResult
{
    public function __construct(
        public readonly string $keyword,
        public readonly ?int $searchVolume,
        public readonly ?float $cpc,
        public readonly ?float $competition,
        public readonly ?string $competitionLevel,
        public readonly ?int $keywordDifficulty,
        public readonly ?array $monthlySearches,
        public readonly ?int $locationCode,
        public readonly ?string $languageCode,
    ) {}

    public static function fromApiResult(array $data): self
    {
        $kd = $data['keyword_data'] ?? $data;
        $ki = $kd['keyword_info'] ?? $kd;

        return new self(
            keyword: (string) ($kd['keyword'] ?? $ki['keyword'] ?? ''),
            searchVolume: isset($ki['search_volume']) ? (int) $ki['search_volume'] : null,
            cpc: isset($ki['cpc']) ? (float) $ki['cpc'] : null,
            competition: isset($ki['competition']) ? (float) $ki['competition'] : null,
            competitionLevel: isset($ki['competition_level']) ? (string) $ki['competition_level'] : null,
            keywordDifficulty: isset($kd['keyword_properties']['keyword_difficulty'])
                ? (int) $kd['keyword_properties']['keyword_difficulty']
                : (isset($ki['keyword_difficulty']) ? (int) $ki['keyword_difficulty'] : null),
            monthlySearches: $ki['monthly_searches'] ?? null,
            locationCode: isset($kd['location_code']) ? (int) $kd['location_code'] : null,
            languageCode: isset($kd['language_code']) ? (string) $kd['language_code'] : null,
        );
    }

    /**
     * @param array $results Array von API-Ergebnissen
     * @return self[]
     */
    public static function fromApiResults(array $results): array
    {
        return array_map(fn(array $item) => self::fromApiResult($item), $results);
    }

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'search_volume' => $this->searchVolume,
            'cpc' => $this->cpc,
            'competition' => $this->competition,
            'competition_level' => $this->competitionLevel,
            'keyword_difficulty' => $this->keywordDifficulty,
            'monthly_searches' => $this->monthlySearches,
            'location_code' => $this->locationCode,
            'language_code' => $this->languageCode,
        ];
    }
}
