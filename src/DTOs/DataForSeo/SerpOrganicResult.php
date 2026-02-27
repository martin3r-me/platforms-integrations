<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO SERP Organic Ergebnisse
 *
 * @see https://docs.dataforseo.com/v3/serp/google/organic/live/regular/
 */
class SerpOrganicResult
{
    public function __construct(
        public readonly string $keyword,
        public readonly ?int $position,
        public readonly ?string $url,
        public readonly ?string $domain,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?array $serpFeatures,
        public readonly ?string $breadcrumb,
        public readonly bool $isFeaturedSnippet = false,
    ) {}

    public static function fromApiResult(array $data, string $keyword = ''): self
    {
        return new self(
            keyword: (string) $keyword,
            position: isset($data['rank_absolute']) ? (int) $data['rank_absolute'] : (isset($data['position']) ? (int) $data['position'] : null),
            url: isset($data['url']) ? (string) $data['url'] : null,
            domain: isset($data['domain']) ? (string) $data['domain'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            serpFeatures: $data['about_this_result']['languages'] ?? null,
            breadcrumb: isset($data['breadcrumb']) ? (string) $data['breadcrumb'] : null,
            isFeaturedSnippet: ($data['type'] ?? '') === 'featured_snippet',
        );
    }

    /**
     * @param array $results Array von API-Ergebnissen
     * @return self[]
     */
    public static function fromApiResults(array $results, string $keyword = ''): array
    {
        return array_map(fn(array $item) => self::fromApiResult($item, $keyword), $results);
    }

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'position' => $this->position,
            'url' => $this->url,
            'domain' => $this->domain,
            'title' => $this->title,
            'description' => $this->description,
            'serp_features' => $this->serpFeatures,
            'breadcrumb' => $this->breadcrumb,
            'is_featured_snippet' => $this->isFeaturedSnippet,
        ];
    }
}
