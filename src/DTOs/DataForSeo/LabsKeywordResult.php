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
        public readonly ?int $languageCode,
    ) {}

    public static function fromApiResult(array $data): self
    {
        $kd = $data['keyword_data'] ?? $data;
        $ki = $kd['keyword_info'] ?? $kd;

        return new self(
            keyword: $kd['keyword'] ?? $ki['keyword'] ?? '',
            searchVolume: $ki['search_volume'] ?? null,
            cpc: $ki['cpc'] ?? null,
            competition: $ki['competition'] ?? null,
            competitionLevel: $ki['competition_level'] ?? null,
            keywordDifficulty: $kd['keyword_properties']['keyword_difficulty'] ?? $ki['keyword_difficulty'] ?? null,
            monthlySearches: $ki['monthly_searches'] ?? null,
            locationCode: $kd['location_code'] ?? null,
            languageCode: $kd['language_code'] ?? null,
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
