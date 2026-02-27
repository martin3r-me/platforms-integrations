<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO Labs Ranked Keywords Ergebnisse
 *
 * @see https://docs.dataforseo.com/v3/dataforseo_labs/google/ranked_keywords/live/
 */
class RankedKeywordResult
{
    public function __construct(
        public readonly string $keyword,
        public readonly ?int $position,
        public readonly ?string $url,
        public readonly ?int $searchVolume,
        public readonly ?float $cpc,
        public readonly ?float $competition,
        public readonly ?int $keywordDifficulty,
        public readonly ?array $serpFeatures,
        public readonly bool $isLocalPack = false,
    ) {}

    public static function fromApiResult(array $data): self
    {
        $kd = $data['keyword_data'] ?? [];
        $ki = $kd['keyword_info'] ?? [];
        $serp = $data['ranked_serp_element'] ?? [];
        $serpItem = $serp['serp_item'] ?? [];

        return new self(
            keyword: $kd['keyword'] ?? '',
            position: $serpItem['rank_absolute'] ?? $serp['position'] ?? null,
            url: $serpItem['url'] ?? $serp['url'] ?? null,
            searchVolume: $ki['search_volume'] ?? null,
            cpc: $ki['cpc'] ?? null,
            competition: $ki['competition'] ?? null,
            keywordDifficulty: $kd['keyword_properties']['keyword_difficulty'] ?? null,
            serpFeatures: $serpItem['serp_features'] ?? null,
            isLocalPack: ($serpItem['type'] ?? '') === 'local_pack',
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
            'position' => $this->position,
            'url' => $this->url,
            'search_volume' => $this->searchVolume,
            'cpc' => $this->cpc,
            'competition' => $this->competition,
            'keyword_difficulty' => $this->keywordDifficulty,
            'serp_features' => $this->serpFeatures,
            'is_local_pack' => $this->isLocalPack,
        ];
    }
}
