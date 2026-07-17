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
    public function getName(): string
    {
        return 'integrations.necta.v1.in-delivery-notes.GET';
    }

    public function getDescription(): string
    {
        return 'Eingangslieferscheine laden

Query-Parameter (`query`):
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
- status: string — Filter nach Lieferscheinstatus (0-Importiert (\'imorted\'), 1-Gebucht (\'booked\'), 2-Geprüft (\'checked\'))
- isChecked: boolean — Filter nach Geprüft-Status (true/false)
- isLocked: boolean — Filter nach Gesperrt-Status (true/false)
- isCreditRequested: boolean — Filter nach Gutschrift-angefordert-Status (true/false)
- changedSince: string — Gibt nur Datensätze zurück, deren ChangeDate größer oder gleich dem angegebenen Zeitpunkt ist (inklusiv). Format: ISO 8601, z. B. 2024-01-15T08:30:00Z. Optional.

Spec: https://docu.necta.one/necta.one-api (spec/necta-one.json).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'object', 'description' => 'Query-Parameter. Erforderlich: page. Siehe Tool-Description.'],
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

        $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];
        if (!array_key_exists('page', $query)) { $query['page'] = 1; }

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
            'tags' => ['necta', 'v1', 'in-delivery-notes'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
