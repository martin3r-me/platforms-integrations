<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/in-delivery-notes
 * Eingangslieferscheine laden
 */
class InDeliveryNotesGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['page', 'pageSize', 'costCenterId', 'description', 'deliveryNoteNumber', 'supplierId', 'stockId', 'accountingDateFrom', 'accountingDateTo', 'orderReferenceNumber', 'status', 'isChecked', 'isLocked', 'isCreditRequested', 'changedSince'];

    public function getName(): string
    {
        return 'integrations.necta.v1.in-delivery-notes.GET';
    }

    public function getDescription(): string
    {
        return 'Eingangslieferscheine laden
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- page: integer [REQUIRED] — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- costCenterId: integer — Filter nach Kostenstellen-ID
- description: string — Filter nach Bezeichnung
- deliveryNoteNumber: string — Filter nach Lieferscheinnummer
- supplierId: integer — Filter nach Lieferanten-ID
- stockId: integer — Filter nach Lager-ID
- accountingDateFrom: string — Buchungsdatum von (Format: YYYY-MM-DD)
- accountingDateTo: string — Buchungsdatum bis (Format: YYYY-MM-DD)
- orderReferenceNumber: string — Filter nach Bestellreferenznummer
- status: integer enum[1|2|3] — Filter nach Lieferscheinstatus (0-Importiert (\'imorted\'), 1-Gebucht (\'booked\'), 2-Geprüft (\'checked\'))
- isChecked: boolean — Filter nach Geprüft-Status (true/false)
- isLocked: boolean — Filter nach Gesperrt-Status (true/false)
- isCreditRequested: boolean — Filter nach Gutschrift-angefordert-Status (true/false)
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
                'costCenterId' => ['type' => 'integer', 'description' => 'Filter nach Kostenstellen-ID'],
                'description' => ['type' => 'string', 'description' => 'Filter nach Bezeichnung'],
                'deliveryNoteNumber' => ['type' => 'string', 'description' => 'Filter nach Lieferscheinnummer'],
                'supplierId' => ['type' => 'integer', 'description' => 'Filter nach Lieferanten-ID'],
                'stockId' => ['type' => 'integer', 'description' => 'Filter nach Lager-ID'],
                'accountingDateFrom' => ['type' => 'string', 'description' => 'Buchungsdatum von (Format: YYYY-MM-DD)'],
                'accountingDateTo' => ['type' => 'string', 'description' => 'Buchungsdatum bis (Format: YYYY-MM-DD)'],
                'orderReferenceNumber' => ['type' => 'string', 'description' => 'Filter nach Bestellreferenznummer'],
                'status' => ['type' => 'integer', 'enum' => [1, 2, 3], 'description' => 'Filter nach Lieferscheinstatus (0-Importiert (\'imorted\'), 1-Gebucht (\'booked\'), 2-Geprüft (\'checked\'))'],
                'isChecked' => ['type' => 'boolean', 'description' => 'Filter nach Geprüft-Status (true/false)'],
                'isLocked' => ['type' => 'boolean', 'description' => 'Filter nach Gesperrt-Status (true/false)'],
                'isCreditRequested' => ['type' => 'boolean', 'description' => 'Filter nach Gutschrift-angefordert-Status (true/false)'],
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


        $path = '/api/v1/{tenantId}/in-delivery-notes';

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
            'tags' => ['necta', 'v1', 'in-delivery-notes'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
