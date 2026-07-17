<?php

namespace Platform\Integrations\Tools\Necta;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaResource;

/**
 * Discovery-Tool: listet alle verfügbaren necta.one Raw-API-Ressourcen samt
 * Label und dokumentierten Query-Filtern. Kein API-Call — reine Registry-Abfrage.
 */
class ResourcesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.resources.GET';
    }

    public function getDescription(): string
    {
        return 'Listet alle verfügbaren necta.one Raw-API-Ressourcen (417) mit Label und erlaubten '
            . 'Query-Filtern. Nutze das Ergebnis, um den passenden `resource`-Slug für '
            . 'integrations.necta.list.GET zu finden. Optional per `search` (Slug/Label) filterbar.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Filtert Ressourcen nach Teilstring in Slug oder Label '
                        . '(z.B. "product", "kunde", "invoice").',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $search = trim((string) ($arguments['search'] ?? ''));
        $needle = mb_strtolower($search);

        $resources = [];
        foreach (NectaResource::REGISTRY as $slug => $meta) {
            $label = $meta['label'] ?? '';
            if ($needle !== ''
                && !str_contains(mb_strtolower($slug), $needle)
                && !str_contains(mb_strtolower($label), $needle)) {
                continue;
            }

            $resources[] = [
                'resource' => $slug,
                'label' => $label,
                'filters' => $meta['filters'] ?? [],
            ];
        }

        return ToolResult::success([
            'total' => count($resources),
            'resources' => $resources,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['necta', 'resources', 'discovery'],
            'read_only' => true,
            'requires_auth' => false,
            'risk_level' => 'safe',
        ];
    }
}
