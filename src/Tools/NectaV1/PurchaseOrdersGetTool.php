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
    public function getName(): string
    {
        return 'integrations.necta.v1.purchase-orders.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Bestellungen

Query-Parameter (`query`):
- page: integer — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- designationOrReferalNumber: string — Filter für Bezeichnung oder Referenz der Bestellung
- dateFrom: string — Bestellungen ab diesem Datum. Gilt für das Lieferdatum, wenn IsDeliveryDate=true, ansonsten für das Bestelldatum
- dateUntil: string — Bestellungen bis zu diesem Datum. Gilt für das Lieferdatum, wenn IsDeliveryDate=true, ansonsten für das Bestelldatum
- isOrderDate: boolean — Wenn true (Standard), werden die Datumsfilter auf das Lieferdatum angewendet. Wenn false, werden die Filter auf das Bestelldatum angewendet
- isDeliveryPeriodExceeded: boolean — Kennzeichen, ob der Lieferzeitraum überschritten wurde (true = überschritten).
- state: string — Bestellstatus (gültige Werte: 1–10)
- referenceState: string — Bestellreferenz Status (gültige Werte: 0-8
- supplierId: integer — Lieferanten ID
- itemNumberOrDesignation: string — Artikelbezeichnung oder -nummer
- costCenterIds: string — Kostenstellen-IDs (kommagetrennt)
- assortmentIdentifier: string — Sortimentskennung (gülitge Werte: 0,10,20,30,40
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


        $path = '/api/v1/{tenantId}/purchase-orders';

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
