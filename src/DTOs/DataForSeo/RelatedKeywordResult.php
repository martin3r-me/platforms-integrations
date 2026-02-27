<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO verwandte Keywords / Keyword-Vorschläge
 *
 * Wird sowohl für "keywords_for_keywords" als auch "keyword_suggestions" verwendet.
 * @see https://docs.dataforseo.com/v3/keywords_data/google_ads/keywords_for_keywords/live/
 * @see https://docs.dataforseo.com/v3/keywords_data/google_ads/keyword_suggestions/live/
 */
class RelatedKeywordResult
{
    public function __construct(
        public readonly string $keyword,
        public readonly ?int $searchVolume,
        public readonly ?float $competition,
        public readonly ?string $competitionLevel,
        public readonly ?int $competitionIndex,
        public readonly ?float $cpcLow,
        public readonly ?float $cpcHigh,
        public readonly ?array $monthlySearches,
        public readonly ?int $locationCode,
        public readonly ?string $languageCode,
    ) {}

    public static function fromApiResult(array $data): self
    {
        return new self(
            keyword: $data['keyword'] ?? '',
            searchVolume: $data['search_volume'] ?? null,
            competition: $data['competition'] ?? null,
            competitionLevel: $data['competition_level'] ?? null,
            competitionIndex: $data['competition_index'] ?? null,
            cpcLow: $data['low_top_of_page_bid'] ?? null,
            cpcHigh: $data['high_top_of_page_bid'] ?? null,
            monthlySearches: $data['monthly_searches'] ?? null,
            locationCode: $data['location_code'] ?? null,
            languageCode: $data['language_code'] ?? null,
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
            'competition' => $this->competition,
            'competition_level' => $this->competitionLevel,
            'competition_index' => $this->competitionIndex,
            'cpc_low' => $this->cpcLow,
            'cpc_high' => $this->cpcHigh,
            'monthly_searches' => $this->monthlySearches,
            'location_code' => $this->locationCode,
            'language_code' => $this->languageCode,
        ];
    }
}
