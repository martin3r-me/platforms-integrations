<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO Labs Competitor Domain Ergebnisse
 *
 * @see https://docs.dataforseo.com/v3/dataforseo_labs/google/competitors_domain/live/
 */
class CompetitorDomainResult
{
    public function __construct(
        public readonly string $domain,
        public readonly ?float $avgPosition,
        public readonly ?int $serpCount,
        public readonly ?int $intersections,
        public readonly ?int $fullDomainKeywords,
        public readonly ?float $fullDomainTraffic,
        public readonly ?float $fullDomainCost,
    ) {}

    public static function fromApiResult(array $data): self
    {
        $metrics = $data['full_domain_metrics'] ?? [];
        $organic = $metrics['organic'] ?? [];

        return new self(
            domain: $data['domain'] ?? '',
            avgPosition: $data['avg_position'] ?? null,
            serpCount: $data['se_type'] ?? $data['serp_count'] ?? null,
            intersections: $data['intersections'] ?? null,
            fullDomainKeywords: $organic['count'] ?? $organic['keywords'] ?? null,
            fullDomainTraffic: $organic['estimated_paid_traffic_cost'] ?? $organic['traffic'] ?? null,
            fullDomainCost: $organic['estimated_paid_traffic_cost'] ?? $organic['cost'] ?? null,
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
            'domain' => $this->domain,
            'avg_position' => $this->avgPosition,
            'serp_count' => $this->serpCount,
            'intersections' => $this->intersections,
            'full_domain_keywords' => $this->fullDomainKeywords,
            'full_domain_traffic' => $this->fullDomainTraffic,
            'full_domain_cost' => $this->fullDomainCost,
        ];
    }
}
