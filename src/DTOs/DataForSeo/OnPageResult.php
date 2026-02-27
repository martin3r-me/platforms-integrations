<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO On-Page Instant Ergebnisse
 *
 * @see https://docs.dataforseo.com/v3/on_page/instant_pages/
 */
class OnPageResult
{
    public function __construct(
        public readonly string $url,
        public readonly ?int $statusCode,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly array $h1,
        public readonly array $h2,
        public readonly array $h3,
        public readonly ?int $wordCount,
        public readonly ?int $contentLength,
        public readonly ?int $internalLinks,
        public readonly ?int $externalLinks,
        public readonly ?int $images,
        public readonly ?float $loadTime,
        public readonly ?float $onpageScore,
        public readonly array $checks,
    ) {}

    public static function fromApiResult(array $data): self
    {
        $meta = $data['meta'] ?? [];
        $pageMetrics = $data['page_metrics'] ?? [];
        $onPageChecks = $data['checks'] ?? [];

        return new self(
            url: $data['url'] ?? '',
            statusCode: $data['status_code'] ?? $data['resource_errors']['status_code'] ?? null,
            title: $meta['title'] ?? $data['title'] ?? null,
            description: $meta['description'] ?? $data['description'] ?? null,
            h1: $meta['htags']['h1'] ?? $data['htags']['h1'] ?? [],
            h2: $meta['htags']['h2'] ?? $data['htags']['h2'] ?? [],
            h3: $meta['htags']['h3'] ?? $data['htags']['h3'] ?? [],
            wordCount: $data['page_metrics']['words_count'] ?? $data['words_count'] ?? null,
            contentLength: $data['content']['plain_text_size'] ?? $data['content_length'] ?? null,
            internalLinks: $data['internal_links_count'] ?? $pageMetrics['links_internal'] ?? null,
            externalLinks: $data['external_links_count'] ?? $pageMetrics['links_external'] ?? null,
            images: $data['images_count'] ?? $pageMetrics['images'] ?? null,
            loadTime: $data['page_timing']['time_to_interactive'] ?? $data['load_time'] ?? null,
            onpageScore: $data['onpage_score'] ?? null,
            checks: $onPageChecks,
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
            'url' => $this->url,
            'status_code' => $this->statusCode,
            'title' => $this->title,
            'description' => $this->description,
            'h1' => $this->h1,
            'h2' => $this->h2,
            'h3' => $this->h3,
            'word_count' => $this->wordCount,
            'content_length' => $this->contentLength,
            'internal_links' => $this->internalLinks,
            'external_links' => $this->externalLinks,
            'images' => $this->images,
            'load_time' => $this->loadTime,
            'onpage_score' => $this->onpageScore,
            'checks' => $this->checks,
        ];
    }
}
