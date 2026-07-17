<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/orders
 * Alle Bestellungen laden
 */
class OrdersGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['page', 'pageSize', 'designationOrNumber', 'customerId', 'contactNameOrNumber', 'deliveryAddressId', 'costCenterId', 'targetCostCenterId', 'dateFrom', 'dateUntil', 'deliveryTimeFrom', 'deliveryTimeUntil', 'isDeliveryDate', 'state', 'sortByChangeDate', 'productionLineId', 'providerCostCenterId', 'type', 'tourId', 'isCreditNote', 'isStationOrder', 'isExtraInvoice', 'isNoBktRelevant', 'productDesignationOrNumber', 'changedSince'];

    public function getName(): string
    {
        return 'integrations.necta.v1.orders.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Bestellungen laden
Bei Datumsfilter muss dateFrom ECHT KLEINER als dateUntil sein (necta: Validation.LessThan).
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- page: integer — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- designationOrNumber: string — Suche nach Bestellbezeichnung, Referenznummer oder Kundenreferenznummer
- customerId: integer — Filter nach Kunden-ID
- contactNameOrNumber: string — Suche nach Kontaktname oder Kontaktnummer
- deliveryAddressId: integer — Filter nach Lieferadressen-ID
- costCenterId: integer — Filter nach Kostenstellen-ID
- targetCostCenterId: integer — Filter nach Ziel-Kostenstellen-ID
- dateFrom: string — Bestellungen ab diesem Datum (Format: yyyy-MM-dd). Bezieht sich auf Lieferdatum wenn IsDeliveryDate=true, sonst auf Bestelldatum
- dateUntil: string — Bestellungen bis zu diesem Datum (Format: yyyy-MM-dd). Bezieht sich auf Lieferdatum wenn IsDeliveryDate=true, sonst auf Bestelldatum
- deliveryTimeFrom: string — Lieferzeit ab (Format: HH:mm). Erforderlich um Zeitfilterung zu aktivieren
- deliveryTimeUntil: string — Lieferzeit bis (Format: HH:mm). Standard: 23:59:59 wenn nicht angegeben
- isDeliveryDate: boolean — Wenn true (Standard), beziehen sich Datumsfilter auf das Lieferdatum. Wenn false, auf das Bestelldatum
- state: integer enum[0|1|2|3|4|5|6|7] — Filter nach Bestellstatus
- sortByChangeDate: boolean — Wenn true, nach Änderungsdatum absteigend sortieren. Wenn false oder null, Standardsortierung
- productionLineId: integer — Filter nach Produktionslinie-ID
- providerCostCenterId: integer — Filter nach Anbieter-Kostenstellen-ID
- type: integer enum[0|1] — Filter nach Bestelltyp
- tourId: integer — Filter nach Tour-ID (prüft Kunden-Tour-Zuordnung)
- isCreditNote: boolean — Filter nach Gutschrift-Status. True=nur Gutschriften, False=ohne Gutschriften, null=alle
- isStationOrder: boolean — Filter nach Stationsbestellungen. True=nur Stationsbestellungen, False=ohne Stationsbestellungen, null=alle
- isExtraInvoice: boolean — Filter nach Zusatzrechnungen. True=nur Zusatzrechnungen, False=ohne Zusatzrechnungen, null=alle
- isNoBktRelevant: boolean — Filter nach BKT-Relevanz. True=nur BKT-relevant, False=nur nicht BKT-relevant, null=alle
- productDesignationOrNumber: string — Suche nach Produktbezeichnung oder Artikelnummer 
… (gekürzt, siehe Spec)';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Seitennummer für die Paginierung (1-basiert)'],
                'pageSize' => ['type' => 'integer', 'description' => 'Anzahl der Ergebnisse pro Seite (Standard: 100)'],
                'designationOrNumber' => ['type' => 'string', 'description' => 'Suche nach Bestellbezeichnung, Referenznummer oder Kundenreferenznummer'],
                'customerId' => ['type' => 'integer', 'description' => 'Filter nach Kunden-ID'],
                'contactNameOrNumber' => ['type' => 'string', 'description' => 'Suche nach Kontaktname oder Kontaktnummer'],
                'deliveryAddressId' => ['type' => 'integer', 'description' => 'Filter nach Lieferadressen-ID'],
                'costCenterId' => ['type' => 'integer', 'description' => 'Filter nach Kostenstellen-ID'],
                'targetCostCenterId' => ['type' => 'integer', 'description' => 'Filter nach Ziel-Kostenstellen-ID'],
                'dateFrom' => ['type' => 'string', 'description' => 'Bestellungen ab diesem Datum (Format: yyyy-MM-dd). Bezieht sich auf Lieferdatum wenn IsDeliveryDate=true, sonst auf Bestelldatum'],
                'dateUntil' => ['type' => 'string', 'description' => 'Bestellungen bis zu diesem Datum (Format: yyyy-MM-dd). Bezieht sich auf Lieferdatum wenn IsDeliveryDate=true, sonst auf Bestelldatum'],
                'deliveryTimeFrom' => ['type' => 'string', 'description' => 'Lieferzeit ab (Format: HH:mm). Erforderlich um Zeitfilterung zu aktivieren'],
                'deliveryTimeUntil' => ['type' => 'string', 'description' => 'Lieferzeit bis (Format: HH:mm). Standard: 23:59:59 wenn nicht angegeben'],
                'isDeliveryDate' => ['type' => 'boolean', 'description' => 'Wenn true (Standard), beziehen sich Datumsfilter auf das Lieferdatum. Wenn false, auf das Bestelldatum'],
                'state' => ['type' => 'integer', 'enum' => [0, 1, 2, 3, 4, 5, 6, 7], 'description' => 'Filter nach Bestellstatus'],
                'sortByChangeDate' => ['type' => 'boolean', 'description' => 'Wenn true, nach Änderungsdatum absteigend sortieren. Wenn false oder null, Standardsortierung'],
                'productionLineId' => ['type' => 'integer', 'description' => 'Filter nach Produktionslinie-ID'],
                'providerCostCenterId' => ['type' => 'integer', 'description' => 'Filter nach Anbieter-Kostenstellen-ID'],
                'type' => ['type' => 'integer', 'enum' => [0, 1], 'description' => 'Filter nach Bestelltyp'],
                'tourId' => ['type' => 'integer', 'description' => 'Filter nach Tour-ID (prüft Kunden-Tour-Zuordnung)'],
                'isCreditNote' => ['type' => 'boolean', 'description' => 'Filter nach Gutschrift-Status. True=nur Gutschriften, False=ohne Gutschriften, null=alle'],
                'isStationOrder' => ['type' => 'boolean', 'description' => 'Filter nach Stationsbestellungen. True=nur Stationsbestellungen, False=ohne Stationsbestellungen, null=alle'],
                'isExtraInvoice' => ['type' => 'boolean', 'description' => 'Filter nach Zusatzrechnungen. True=nur Zusatzrechnungen, False=ohne Zusatzrechnungen, null=alle'],
                'isNoBktRelevant' => ['type' => 'boolean', 'description' => 'Filter nach BKT-Relevanz. True=nur BKT-relevant, False=nur nicht BKT-relevant, null=alle'],
                'productDesignationOrNumber' => ['type' => 'string', 'description' => 'Suche nach Produktbezeichnung oder Artikelnummer in Bestellpositionen'],
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


        $path = '/api/v1/{tenantId}/orders';

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
            'tags' => ['necta', 'v1', 'orders'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
