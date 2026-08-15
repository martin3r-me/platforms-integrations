<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO Labs Domain Intersection — Keywords im Verhältnis zweier
 * Domains. Mit intersections=false: Keywords, für die target1 rankt, target2
 * aber NICHT (= die Content-/Keyword-Lücke).
 *
 * @see https://docs.dataforseo.com/v3/dataforseo_labs/google/domain_intersection/live/
 */
class DomainIntersectionResult
{
    public function __construct(
        public readonly string $keyword,
        public readonly ?int $searchVolume,
        public readonly ?float $cpc,
        public readonly ?float $competition,
        public readonly ?int $keywordDifficulty,
        public readonly ?int $firstPosition,   // Position target1 (Wettbewerber)
        public readonly ?string $firstUrl,
        public readonly ?int $secondPosition,  // Position target2 (eigene Domain) — null = Lücke
        public readonly ?string $secondUrl,
    ) {}

    public static function fromApiResult(array $item): self
    {
        $kd = $item['keyword_data'] ?? [];
        $ki = $kd['keyword_info'] ?? [];
        $first = $item['first_domain_serp_element'] ?? [];
        $second = $item['second_domain_serp_element'] ?? [];

        return new self(
            keyword: (string) ($kd['keyword'] ?? ''),
            searchVolume: isset($ki['search_volume']) ? (int) $ki['search_volume'] : null,
            cpc: isset($ki['cpc']) ? (float) $ki['cpc'] : null,
            competition: isset($ki['competition']) ? (float) $ki['competition'] : null,
            keywordDifficulty: isset($kd['keyword_properties']['keyword_difficulty'])
                ? (int) $kd['keyword_properties']['keyword_difficulty'] : null,
            firstPosition: isset($first['rank_absolute']) ? (int) $first['rank_absolute'] : null,
            firstUrl: $first['url'] ?? null,
            secondPosition: isset($second['rank_absolute']) ? (int) $second['rank_absolute'] : null,
            secondUrl: $second['url'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'search_volume' => $this->searchVolume,
            'cpc' => $this->cpc,
            'competition' => $this->competition,
            'keyword_difficulty' => $this->keywordDifficulty,
            'first_position' => $this->firstPosition,
            'first_url' => $this->firstUrl,
            'second_position' => $this->secondPosition,
            'second_url' => $this->secondUrl,
        ];
    }
}
