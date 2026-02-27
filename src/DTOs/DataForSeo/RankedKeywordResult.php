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
            keyword: (string) ($kd['keyword'] ?? ''),
            position: isset($serpItem['rank_absolute']) ? (int) $serpItem['rank_absolute'] : (isset($serp['position']) ? (int) $serp['position'] : null),
            url: $serpItem['url'] ?? $serp['url'] ?? null,
            searchVolume: isset($ki['search_volume']) ? (int) $ki['search_volume'] : null,
            cpc: isset($ki['cpc']) ? (float) $ki['cpc'] : null,
            competition: isset($ki['competition']) ? (float) $ki['competition'] : null,
            keywordDifficulty: isset($kd['keyword_properties']['keyword_difficulty']) ? (int) $kd['keyword_properties']['keyword_difficulty'] : null,
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
