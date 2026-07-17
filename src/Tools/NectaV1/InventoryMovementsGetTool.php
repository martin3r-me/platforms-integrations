<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/inventory-movements
 * Alle Lagerbewegungen
 */
class InventoryMovementsGetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.inventory-movements.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Lagerbewegungen

Query-Parameter (`query`):
- productTerm: string — Produktname oder Artikelnummer
- productTermStrict: boolean — Exakte Suche (true) oder Teilzeichensuche (false, Standard)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- page: integer — Seitennummer für die Paginierung (1-basiert, Standard: 1)
- costCenterId: integer — Filter nach Kostenstellen-ID
- stockId: integer — Filter nach Lager-ID
- productAreaId: integer — Filter nach Produktbereich-ID
- groupProductClassId: integer — Filter nach Warengruppen-Klassifizierungs-ID
- productClassId: integer — Filter nach Produktklassen-ID
- categoryProductClassId: integer — Filter nach Kategorie-Klassifizierungs-ID
- dateFrom: string — Lagerbewegungen ab diesem Datum (Format: yyyy-MM-dd)
- dateUntil: string — Lagerbewegungen bis zu diesem Datum (Format: yyyy-MM-dd)
- batchNumber: string — Filter nach Chargennummer
- classificationTerm1: string — Suchbegriff für erste Klassifizierung (mehrere Begriffe durch Leerzeichen getrennt)
- classificationTerm2: string — Suchbegriff für zweite Klassifizierung (mehrere Begriffe durch Leerzeichen getrennt)
- classificationAndOperator: boolean — UND-Verknüpfung (true) oder ODER-Verknüpfung (false) für Klassifizierungsbegriffe
- type: string — Bewegungstyp (Correction, ManuallyEntry, etc.)
- reducedList: boolean — Reduzierte Liste (true) oder vollständige Daten (false)

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


        $path = '/api/v1/{tenantId}/inventory-movements';

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
            'tags' => ['necta', 'v1', 'inventory-movements'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
