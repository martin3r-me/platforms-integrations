<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/out-invoices
 * Alle Ausgangsrechnungen laden
 */
class OutInvoicesGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['page', 'pageSize', 'designationOrNumber', 'customerIds', 'contactNameOrNumber', 'targetCostCenterIds', 'dateFrom', 'dateUntil', 'state', 'isSent', 'isChecked', 'isLocked', 'productDesignationOrNumber', 'classificationIds', 'changedSince'];

    public function getName(): string
    {
        return 'integrations.necta.v1.out-invoices.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Ausgangsrechnungen laden
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- page: integer — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- designationOrNumber: string — Suche nach Ausgangsrechnungs-Bezeichnung oder Referenznummer
- customerIds: string — Filter nach Kunden-IDs (kommagetrennt, z.B. \'1,2,3\')
- contactNameOrNumber: string — Suche nach Kontaktname oder Kontaktnummer
- targetCostCenterIds: string — Filter nach Ziel-Kostenstellen-IDs (kommagetrennt, z.B. \'1,2,3\')
- dateFrom: string — Ausgangsrechnungen ab diesem Datum filtern (inklusiv)
- dateUntil: string — Ausgangsrechnungen bis zu diesem Datum filtern (inklusiv)
- state: integer enum[0|1|2|3|4|5|6|7|8] — Filter nach Status der Ausgangsrechnung
- isSent: boolean — Filter nach Versandstatus (true/false)
- isChecked: boolean — Filter nach Geprüft-Status (true/false)
- isLocked: boolean — Filter nach Gesperrt-Status (true/false)
- productDesignationOrNumber: string — Suche nach Produktbezeichnung oder Artikelnummer in Rechnungspositionen
- classificationIds: string — Filter nach Klassifizierungs-IDs (kommagetrennt, z.B. \'1,2,3\')
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
                'designationOrNumber' => ['type' => 'string', 'description' => 'Suche nach Ausgangsrechnungs-Bezeichnung oder Referenznummer'],
                'customerIds' => ['type' => 'string', 'description' => 'Filter nach Kunden-IDs (kommagetrennt, z.B. \'1,2,3\')'],
                'contactNameOrNumber' => ['type' => 'string', 'description' => 'Suche nach Kontaktname oder Kontaktnummer'],
                'targetCostCenterIds' => ['type' => 'string', 'description' => 'Filter nach Ziel-Kostenstellen-IDs (kommagetrennt, z.B. \'1,2,3\')'],
                'dateFrom' => ['type' => 'string', 'description' => 'Ausgangsrechnungen ab diesem Datum filtern (inklusiv)'],
                'dateUntil' => ['type' => 'string', 'description' => 'Ausgangsrechnungen bis zu diesem Datum filtern (inklusiv)'],
                'state' => ['type' => 'integer', 'enum' => [0, 1, 2, 3, 4, 5, 6, 7, 8], 'description' => 'Filter nach Status der Ausgangsrechnung'],
                'isSent' => ['type' => 'boolean', 'description' => 'Filter nach Versandstatus (true/false)'],
                'isChecked' => ['type' => 'boolean', 'description' => 'Filter nach Geprüft-Status (true/false)'],
                'isLocked' => ['type' => 'boolean', 'description' => 'Filter nach Gesperrt-Status (true/false)'],
                'productDesignationOrNumber' => ['type' => 'string', 'description' => 'Suche nach Produktbezeichnung oder Artikelnummer in Rechnungspositionen'],
                'classificationIds' => ['type' => 'string', 'description' => 'Filter nach Klassifizierungs-IDs (kommagetrennt, z.B. \'1,2,3\')'],
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


        $path = '/api/v1/{tenantId}/out-invoices';

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
            'tags' => ['necta', 'v1', 'out-invoices'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
