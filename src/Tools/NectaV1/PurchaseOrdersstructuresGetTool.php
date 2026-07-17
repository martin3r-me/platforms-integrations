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
    public function getName(): string
    {
        return 'integrations.necta.v1.purchase-orders.structures.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Bestellpositionen laden

Query-Parameter (`query`):
- page: integer — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- designationOrNumber: string — Suche nach Bestellbezeichnung oder Referenznummer
- isOrderDate: boolean — Wenn true, beziehen sich Datumsfilter auf das Bestelldatum. Wenn false, auf das Lieferdatum (Standard)
- dateFrom: string — Bestellungen ab diesem Datum (Format: yyyy-MM-dd)
- dateUntil: string — Bestellungen bis zu diesem Datum (Format: yyyy-MM-dd)
- isDeliveryPeriodExceeded: boolean — Wenn true, nur Bestellungen mit überschrittenem Lieferzeitraum. Wenn false, nur innerhalb. Wenn null, alle
- state: string — Filter nach Bestellstatus (OrderOpen, OrderClosed, Requirement, etc.)
- referenceState: string — Filter nach Referenzstatus (NotSent, Sent, ConfirmationOfReceiptRequested, etc.)
- supplierId: integer — Filter nach Lieferanten-ID
- productDesignationOrNumber: string — Suche nach Produktbezeichnung oder Artikelnummer in Bestellpositionen
- costCenterId: integer — Filter nach Kostenstellen-ID
- assortmentIdentifier: string — Filter nach Sortimentstyp (Mixed, Regular, FreshFood, DryFood, NonFood)
- changedSince: string — Gibt nur Datensätze zurück, deren ChangeDate größer oder gleich dem angegebenen Zeitpunkt ist (inklusiv). Format: ISO 8601, z. B. 2024-01-15T08:30:00Z. Optional.

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


        $path = '/api/v1/{tenantId}/purchase-orders/structures';

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
            'tags' => ['necta', 'v1', 'purchase-orders'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
