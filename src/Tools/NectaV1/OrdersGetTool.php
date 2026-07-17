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
    public function getName(): string
    {
        return 'integrations.necta.v1.orders.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Bestellungen laden

WICHTIG: Bei Datumsfilterung muss dateFrom ECHT KLEINER als dateUntil sein (necta: Validation.LessThan) — gleiche Werte werden abgelehnt. Für einen einzelnen Tag dateUntil = Folgetag setzen. Datumsangaben als ISO yyyy-MM-dd.

Query-Parameter (`query`):
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
- state: string — Filter nach Bestellstatus
- sortByChangeDate: boolean — Wenn true, nach Änderungsdatum absteigend sortieren. Wenn false oder null, Standardsortierung
- productionLineId: integer — Filter nach Produktionslinie-ID
- providerCostCenterId: integer — Filter nach Anbieter-Kostenstellen-ID
- type: string — Filter nach Bestelltyp
- tourId: integer — Filter nach Tour-ID (prüft Kunden-Tour-Zuordnung)
- isCreditNote: boolean — Filter nach Gutschrift-Status. True=nur Gutschriften, False=ohne Gutschriften, null=alle
- isStationOrder: boolean — Filter nach Stationsbestellungen. True=nur Stationsbestellungen, False=ohne Stationsbestellungen, null=alle
- isExtraInvoice: boolean — Filter nach Zusatzrechnungen. True=nur Zusatzrechnungen, False=ohne Zusatzrechnungen, null=alle
- isNoBktRelevant: boolean — Filter nach BKT-Relevanz. True=nur BKT-relevant, False=nur nicht BKT-relevant, null=alle
- productDesignationOrNumber: string — Suche nach Produktbezeichnung oder Artikelnummer in Bestellpositionen
- changedSince: string — Gibt nur Datensätze zurück, deren ChangeDate größer oder gleich dem ang
… (gekürzt, siehe Spec)';
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


        $path = '/api/v1/{tenantId}/orders';

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
            'tags' => ['necta', 'v1', 'orders'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
