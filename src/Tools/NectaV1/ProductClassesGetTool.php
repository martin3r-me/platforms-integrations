<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/product-classes
 * Alle Produktklassen laden
 */
class ProductClassesGetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.product-classes.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Produktklassen laden

Query-Parameter (`query`):
- page: integer — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- ids: string — Filter nach Produktklassen-IDs (kommagetrennt, z.B. \'1,2,3\')
- designation: string — Suche nach Produktklassen-Bezeichnung
- isInactive: boolean — Wenn true, nur inaktive Produktklassen. Wenn false, nur aktive Produktklassen (Standard: false)
- productMainClassIds: string — Filter nach Hauptproduktklassen-IDs (kommagetrennt, z.B. \'1,2,3\')
- productMainClassDesignation: string — Suche nach Hauptproduktklassen-Bezeichnung
- inclCostCenterDetails: boolean — Wenn true, Finanzkonfigurationen (Kontenpläne und Steuersätze) einbeziehen. Standard: false
- costCenterIds: string — Filter nach Kostenstellen-IDs (kommagetrennt, z.B. \'1,2,3\'). Gilt nur wenn InclCostCenterDetails=true

Spec: https://docu.necta.one/necta.one-api (spec/necta-one.json).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'object', 'description' => 'Query-Parameter. Erforderlich: keine. Siehe Tool-Description.'],
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


        $path = '/api/v1/{tenantId}/product-classes';

        $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];

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
            'tags' => ['necta', 'v1', 'product-classes'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
