<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/purchase-orders
 * Alle Bestellungen
 */
class PurchaseOrdersGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['page', 'pageSize', 'designationOrReferalNumber', 'dateFrom', 'dateUntil', 'isOrderDate', 'isDeliveryPeriodExceeded', 'state', 'referenceState', 'supplierId', 'itemNumberOrDesignation', 'costCenterIds', 'assortmentIdentifier', 'changedSince'];

    public function getName(): string
    {
        return 'integrations.necta.v1.purchase-orders.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Bestellungen
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- page: integer — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- designationOrReferalNumber: string — Filter für Bezeichnung oder Referenz der Bestellung
- dateFrom: string — Bestellungen ab diesem Datum. Gilt für das Lieferdatum, wenn IsDeliveryDate=true, ansonsten für das Bestelldatum
- dateUntil: string — Bestellungen bis zu diesem Datum. Gilt für das Lieferdatum, wenn IsDeliveryDate=true, ansonsten für das Bestelldatum
- isOrderDate: boolean — Wenn true (Standard), werden die Datumsfilter auf das Lieferdatum angewendet. Wenn false, werden die Filter auf das Bestelldatum angewendet
- isDeliveryPeriodExceeded: boolean — Kennzeichen, ob der Lieferzeitraum überschritten wurde (true = überschritten).
- state: integer enum[1|2|3|4|5|6|7|8|9|10] — Bestellstatus (gültige Werte: 1–10)
- referenceState: integer enum[0|1|2|3|4|5|6|7|8] — Bestellreferenz Status (gültige Werte: 0-8
- supplierId: integer — Lieferanten ID
- itemNumberOrDesignation: string — Artikelbezeichnung oder -nummer
- costCenterIds: string — Kostenstellen-IDs (kommagetrennt)
- assortmentIdentifier: integer enum[0|10|20|30|40] — Sortimentskennung (gülitge Werte: 0,10,20,30,40
- changedSince: string — Gibt nur Datensätze zurück, deren ChangeDate größer oder gleich dem angegebenen Zeitpunkt ist (inklusiv). Format: ISO 8601, z. B. 2024-01-15T08:30:00Z. Optional.
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Seitennummer für die Paginierung (1-basiert)'],
                'pageSize' => ['type' => 'integer', 'description' => 'Anzahl der Ergebnisse pro Seite (Standard: 100)'],
                'designationOrReferalNumber' => ['type' => 'string', 'description' => 'Filter für Bezeichnung oder Referenz der Bestellung'],
                'dateFrom' => ['type' => 'string', 'description' => 'Bestellungen ab diesem Datum. Gilt für das Lieferdatum, wenn IsDeliveryDate=true, ansonsten für das Bestelldatum'],
                'dateUntil' => ['type' => 'string', 'description' => 'Bestellungen bis zu diesem Datum. Gilt für das Lieferdatum, wenn IsDeliveryDate=true, ansonsten für das Bestelldatum'],
                'isOrderDate' => ['type' => 'boolean', 'description' => 'Wenn true (Standard), werden die Datumsfilter auf das Lieferdatum angewendet. Wenn false, werden die Filter auf das Bestelldatum angewendet'],
                'isDeliveryPeriodExceeded' => ['type' => 'boolean', 'description' => 'Kennzeichen, ob der Lieferzeitraum überschritten wurde (true = überschritten).'],
                'state' => ['type' => 'integer', 'enum' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 'description' => 'Bestellstatus (gültige Werte: 1–10)'],
                'referenceState' => ['type' => 'integer', 'enum' => [0, 1, 2, 3, 4, 5, 6, 7, 8], 'description' => 'Bestellreferenz Status (gültige Werte: 0-8'],
                'supplierId' => ['type' => 'integer', 'description' => 'Lieferanten ID'],
                'itemNumberOrDesignation' => ['type' => 'string', 'description' => 'Artikelbezeichnung oder -nummer'],
                'costCenterIds' => ['type' => 'string', 'description' => 'Kostenstellen-IDs (kommagetrennt)'],
                'assortmentIdentifier' => ['type' => 'integer', 'enum' => [0, 10, 20, 30, 40], 'description' => 'Sortimentskennung (gülitge Werte: 0,10,20,30,40'],
                'changedSince' => ['type' => 'string', 'description' => 'Gibt nur Datensätze zurück, deren ChangeDate größer oder gleich dem angegebenen Zeitpunkt ist (inklusiv). Format: ISO 8601, z. B. 2024-01-15T08:30:00Z. Optional.'],
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


        $path = '/api/v1/{tenantId}/purchase-orders';

        $query = [];
        foreach (self::QUERY_KEYS as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $query[$k] = $arguments[$k];
            }
        }
        if (!array_key_exists('page', $query)) { $query['page'] = 1; }
        if (!array_key_exists('pageSize', $query)) { $query['pageSize'] = 100; }

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
            'tags' => ['necta', 'v1', 'purchase-orders'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
