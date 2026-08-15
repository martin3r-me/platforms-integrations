<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für die SERP-Features neben den organischen Treffern — aus demselben
 * /v3/serp/google/organic/live/regular-Call (depth 100). Bisher verworfen, obwohl
 * bezahlt: People-Also-Ask, Related Searches, Featured Snippet, AI-Overview.
 *
 * @see https://docs.dataforseo.com/v3/serp/google/organic/live/regular/
 */
class SerpFeaturesResult
{
    public function __construct(
        public readonly string $keyword,
        /** @var string[] Alle vorkommenden SERP-Element-Typen (organic, paa, ai_overview, …). */
        public readonly array $itemTypes,
        /** @var string[] People-Also-Ask-Fragen. */
        public readonly array $peopleAlsoAsk,
        /** @var string[] Related-Searches-Begriffe. */
        public readonly array $relatedSearches,
        /** @var array|null {domain, url, title} des Featured Snippets, falls vorhanden. */
        public readonly ?array $featuredSnippet,
        public readonly bool $hasAiOverview,
        /** @var string[] Im AI-Overview referenzierte Domains. */
        public readonly array $aiOverviewReferences,
    ) {}

    /**
     * Baut die Features aus den SERP-Items (ein result-Set der API-Response).
     *
     * @param array $items items[] eines result-Sets
     */
    public static function fromItems(array $items, string $keyword = ''): self
    {
        $itemTypes = [];
        $paa = [];
        $related = [];
        $featuredSnippet = null;
        $hasAiOverview = false;
        $aiRefs = [];

        foreach ($items as $item) {
            $type = $item['type'] ?? '';
            if ($type !== '') {
                $itemTypes[$type] = true;
            }

            switch ($type) {
                case 'people_also_ask':
                    foreach ($item['items'] ?? [] as $el) {
                        $q = $el['title'] ?? null;
                        if ($q) {
                            $paa[] = (string) $q;
                        }
                    }
                    break;

                case 'related_searches':
                    foreach ($item['items'] ?? [] as $rel) {
                        if (is_string($rel)) {
                            $related[] = $rel;
                        } elseif (is_array($rel) && isset($rel['title'])) {
                            $related[] = (string) $rel['title'];
                        }
                    }
                    break;

                case 'featured_snippet':
                    $featuredSnippet = array_filter([
                        'domain' => $item['domain'] ?? null,
                        'url' => $item['url'] ?? null,
                        'title' => $item['title'] ?? null,
                    ], fn ($v) => $v !== null);
                    if (empty($featuredSnippet)) {
                        $featuredSnippet = null;
                    }
                    break;

                case 'ai_overview':
                    $hasAiOverview = true;
                    // Referenzen liegen je nach Response in references[] oder items[].
                    $refs = $item['references'] ?? [];
                    foreach ($item['items'] ?? [] as $sub) {
                        foreach ($sub['references'] ?? [] as $r) {
                            $refs[] = $r;
                        }
                    }
                    foreach ($refs as $r) {
                        $domain = is_array($r) ? ($r['domain'] ?? null) : null;
                        if ($domain) {
                            $aiRefs[] = (string) $domain;
                        }
                    }
                    break;
            }
        }

        return new self(
            keyword: (string) $keyword,
            itemTypes: array_keys($itemTypes),
            peopleAlsoAsk: array_values(array_unique($paa)),
            relatedSearches: array_values(array_unique($related)),
            featuredSnippet: $featuredSnippet,
            hasAiOverview: $hasAiOverview,
            aiOverviewReferences: array_values(array_unique($aiRefs)),
        );
    }

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'item_types' => $this->itemTypes,
            'people_also_ask' => $this->peopleAlsoAsk,
            'related_searches' => $this->relatedSearches,
            'featured_snippet' => $this->featuredSnippet,
            'has_ai_overview' => $this->hasAiOverview,
            'ai_overview_references' => $this->aiOverviewReferences,
        ];
    }
}
