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
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['productTerm', 'productTermStrict', 'pageSize', 'page', 'costCenterId', 'stockId', 'productAreaId', 'groupProductClassId', 'productClassId', 'categoryProductClassId', 'dateFrom', 'dateUntil', 'batchNumber', 'classificationTerm1', 'classificationTerm2', 'classificationAndOperator', 'type', 'reducedList'];

    public function getName(): string
    {
        return 'integrations.necta.v1.inventory-movements.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Lagerbewegungen
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
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
- type: integer enum[1|2|3|4|5|10|11|12|13|14|15|16|17|18|19|20] — Bewegungstyp (Correction, ManuallyEntry, etc.)
- reducedList: boolean — Reduzierte Liste (true) oder vollständige Daten (false)
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'productTerm' => ['type' => 'string', 'description' => 'Produktname oder Artikelnummer'],
                'productTermStrict' => ['type' => 'boolean', 'description' => 'Exakte Suche (true) oder Teilzeichensuche (false, Standard)'],
                'pageSize' => ['type' => 'integer', 'description' => 'Anzahl der Ergebnisse pro Seite (Standard: 100)'],
                'page' => ['type' => 'integer', 'description' => 'Seitennummer für die Paginierung (1-basiert, Standard: 1)'],
                'costCenterId' => ['type' => 'integer', 'description' => 'Filter nach Kostenstellen-ID'],
                'stockId' => ['type' => 'integer', 'description' => 'Filter nach Lager-ID'],
                'productAreaId' => ['type' => 'integer', 'description' => 'Filter nach Produktbereich-ID'],
                'groupProductClassId' => ['type' => 'integer', 'description' => 'Filter nach Warengruppen-Klassifizierungs-ID'],
                'productClassId' => ['type' => 'integer', 'description' => 'Filter nach Produktklassen-ID'],
                'categoryProductClassId' => ['type' => 'integer', 'description' => 'Filter nach Kategorie-Klassifizierungs-ID'],
                'dateFrom' => ['type' => 'string', 'description' => 'Lagerbewegungen ab diesem Datum (Format: yyyy-MM-dd)'],
                'dateUntil' => ['type' => 'string', 'description' => 'Lagerbewegungen bis zu diesem Datum (Format: yyyy-MM-dd)'],
                'batchNumber' => ['type' => 'string', 'description' => 'Filter nach Chargennummer'],
                'classificationTerm1' => ['type' => 'string', 'description' => 'Suchbegriff für erste Klassifizierung (mehrere Begriffe durch Leerzeichen getrennt)'],
                'classificationTerm2' => ['type' => 'string', 'description' => 'Suchbegriff für zweite Klassifizierung (mehrere Begriffe durch Leerzeichen getrennt)'],
                'classificationAndOperator' => ['type' => 'boolean', 'description' => 'UND-Verknüpfung (true) oder ODER-Verknüpfung (false) für Klassifizierungsbegriffe'],
                'type' => ['type' => 'integer', 'enum' => [1, 2, 3, 4, 5, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20], 'description' => 'Bewegungstyp (Correction, ManuallyEntry, etc.)'],
                'reducedList' => ['type' => 'boolean', 'description' => 'Reduzierte Liste (true) oder vollständige Daten (false)'],
                'fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte, z.B. "customer.customerNumber"). Reduziert die Antwortgröße drastisch.'],
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

        $query = [];
        foreach (self::QUERY_KEYS as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $query[$k] = $arguments[$k];
            }
        }
        if (!array_key_exists('pageSize', $query)) { $query['pageSize'] = 100; }
        if (!array_key_exists('page', $query)) { $query['page'] = 1; }

        $data = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->callSpec($context->user, 'GET', $path, $query, $data);
            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = NectaApiV1Service::projectFields($result, $arguments['fields']);
            }
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
