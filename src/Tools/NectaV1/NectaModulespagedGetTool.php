<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/necta-modules/paged
 * Necta-Module laden (paginiert)
 */
class NectaModulespagedGetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.necta-modules.paged.GET';
    }

    public function getDescription(): string
    {
        return 'Necta-Module laden (paginiert)

Query-Parameter (`query`):
- page: integer [REQUIRED] — Seitennummer (0-basiert)
- size: integer [REQUIRED] — Anzahl der Einträge pro Seite
- sort: string — Name des Sortierfeldes (z. B. \'name\' oder \'createdAt\')
- sortDir: string
- search: string
- filters: string — Spaltenfilter als JSON-String (PrimeNG-Format) {"name": { "value": "Max", "matchMode": "contains" },"age":  { "value": 30,    "matchMode": "equals" }}

Spec: https://docu.necta.one/necta.one-api (spec/necta-one.json).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'object', 'description' => 'Query-Parameter. Erforderlich: page, size. Siehe Tool-Description.'],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }


        $path = '/api/v1/{tenantId}/necta-modules/paged';

        $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];
        if (!array_key_exists('page', $query)) { $query['page'] = 1; }
        if (!array_key_exists('size', $query)) { $query['size'] = 100; }

        $data = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->callSpec($context->user, 'GET', $path, $query, $data);

            return ToolResult::success($result);
        } catch (NectaApiException $e) {
            return ToolResult::error($e->getNectaErrorCode() ?? 'NECTA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['necta', 'v1', 'necta-modules'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
