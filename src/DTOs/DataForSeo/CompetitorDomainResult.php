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
            domain: (string) ($data['domain'] ?? ''),
            avgPosition: isset($data['avg_position']) ? (float) $data['avg_position'] : null,
            serpCount: isset($data['se_type']) ? (int) $data['se_type'] : (isset($data['serp_count']) ? (int) $data['serp_count'] : null),
            intersections: isset($data['intersections']) ? (int) $data['intersections'] : null,
            fullDomainKeywords: isset($organic['count']) ? (int) $organic['count'] : (isset($organic['keywords']) ? (int) $organic['keywords'] : null),
            fullDomainTraffic: isset($organic['estimated_paid_traffic_cost']) ? (float) $organic['estimated_paid_traffic_cost'] : (isset($organic['traffic']) ? (float) $organic['traffic'] : null),
            fullDomainCost: isset($organic['estimated_paid_traffic_cost']) ? (float) $organic['estimated_paid_traffic_cost'] : (isset($organic['cost']) ? (float) $organic['cost'] : null),
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
