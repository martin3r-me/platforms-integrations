<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/purchase-orders/structures
 * Alle Bestellpositionen laden
 */
class PurchaseOrdersstructuresGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['page', 'pageSize', 'designationOrNumber', 'isOrderDate', 'dateFrom', 'dateUntil', 'isDeliveryPeriodExceeded', 'state', 'referenceState', 'supplierId', 'productDesignationOrNumber', 'costCenterId', 'assortmentIdentifier', 'changedSince'];

    public function getName(): string
    {
        return 'integrations.necta.v1.purchase-orders.structures.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Bestellpositionen laden
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- page: integer — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- designationOrNumber: string — Suche nach Bestellbezeichnung oder Referenznummer
- isOrderDate: boolean — Wenn true, beziehen sich Datumsfilter auf das Bestelldatum. Wenn false, auf das Lieferdatum (Standard)
- dateFrom: string — Bestellungen ab diesem Datum (Format: yyyy-MM-dd)
- dateUntil: string — Bestellungen bis zu diesem Datum (Format: yyyy-MM-dd)
- isDeliveryPeriodExceeded: boolean — Wenn true, nur Bestellungen mit überschrittenem Lieferzeitraum. Wenn false, nur innerhalb. Wenn null, alle
- state: integer enum[1|2|3|4|5|6|7|8|9|10] — Filter nach Bestellstatus (OrderOpen, OrderClosed, Requirement, etc.)
- referenceState: integer enum[0|1|2|3|4|5|6|7|8] — Filter nach Referenzstatus (NotSent, Sent, ConfirmationOfReceiptRequested, etc.)
- supplierId: integer — Filter nach Lieferanten-ID
- productDesignationOrNumber: string — Suche nach Produktbezeichnung oder Artikelnummer in Bestellpositionen
- costCenterId: integer — Filter nach Kostenstellen-ID
- assortmentIdentifier: integer enum[0|10|20|30|40] — Filter nach Sortimentstyp (Mixed, Regular, FreshFood, DryFood, NonFood)
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
                'designationOrNumber' => ['type' => 'string', 'description' => 'Suche nach Bestellbezeichnung oder Referenznummer'],
                'isOrderDate' => ['type' => 'boolean', 'description' => 'Wenn true, beziehen sich Datumsfilter auf das Bestelldatum. Wenn false, auf das Lieferdatum (Standard)'],
                'dateFrom' => ['type' => 'string', 'description' => 'Bestellungen ab diesem Datum (Format: yyyy-MM-dd)'],
                'dateUntil' => ['type' => 'string', 'description' => 'Bestellungen bis zu diesem Datum (Format: yyyy-MM-dd)'],
                'isDeliveryPeriodExceeded' => ['type' => 'boolean', 'description' => 'Wenn true, nur Bestellungen mit überschrittenem Lieferzeitraum. Wenn false, nur innerhalb. Wenn null, alle'],
                'state' => ['type' => 'integer', 'enum' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 'description' => 'Filter nach Bestellstatus (OrderOpen, OrderClosed, Requirement, etc.)'],
                'referenceState' => ['type' => 'integer', 'enum' => [0, 1, 2, 3, 4, 5, 6, 7, 8], 'description' => 'Filter nach Referenzstatus (NotSent, Sent, ConfirmationOfReceiptRequested, etc.)'],
                'supplierId' => ['type' => 'integer', 'description' => 'Filter nach Lieferanten-ID'],
                'productDesignationOrNumber' => ['type' => 'string', 'description' => 'Suche nach Produktbezeichnung oder Artikelnummer in Bestellpositionen'],
                'costCenterId' => ['type' => 'integer', 'description' => 'Filter nach Kostenstellen-ID'],
                'assortmentIdentifier' => ['type' => 'integer', 'enum' => [0, 10, 20, 30, 40], 'description' => 'Filter nach Sortimentstyp (Mixed, Regular, FreshFood, DryFood, NonFood)'],
                'changedSince' => ['type' => 'string', 'description' => 'Gibt nur Datensätze zurück, deren ChangeDate größer oder gleich dem angegebenen Zeitpunkt ist (inklusiv). Format: ISO 8601, z. B. 2024-01-15T08:30:00Z. Optional.'],
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


        $path = '/api/v1/{tenantId}/purchase-orders/structures';

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
            'tags' => ['necta', 'v1', 'purchase-orders'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
