<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/supplier-items
 * Lieferantenartikel laden
 */
class SupplierItemsGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['page', 'pageSize', 'costCenterId', 'supplierId', 'itemName', 'itemNumber', 'itemProducer', 'fixedPriceExpiryFrom', 'fixedPriceExpiryTo', 'itemIsBio', 'itemLabelIds'];

    public function getName(): string
    {
        return 'integrations.necta.v1.supplier-items.GET';
    }

    public function getDescription(): string
    {
        return 'Lieferantenartikel laden
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- page: integer [REQUIRED] — Seitennummer für die Paginierung (1-basiert)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- costCenterId: integer — Filter nach Kostenstellen-ID
- supplierId: integer — Filter nach Lieferanten-ID
- itemName: string — Filter nach Artikelbezeichnung
- itemNumber: string — Filter nach Artikelnummer
- itemProducer: string — Filter nach Hersteller
- fixedPriceExpiryFrom: string — Fixpreis-Ablaufdatum von (Format: YYYY-MM-DD)
- fixedPriceExpiryTo: string — Fixpreis-Ablaufdatum bis (Format: YYYY-MM-DD)
- itemIsBio: boolean — Filter nach Bio-Kennzeichen (true = nur Bio-Artikel)
- itemLabelIds: array
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
                'supplierId' => ['type' => 'integer', 'description' => 'Filter nach Lieferanten-ID'],
                'itemName' => ['type' => 'string', 'description' => 'Filter nach Artikelbezeichnung'],
                'itemNumber' => ['type' => 'string', 'description' => 'Filter nach Artikelnummer'],
                'itemProducer' => ['type' => 'string', 'description' => 'Filter nach Hersteller'],
                'fixedPriceExpiryFrom' => ['type' => 'string', 'description' => 'Fixpreis-Ablaufdatum von (Format: YYYY-MM-DD)'],
                'fixedPriceExpiryTo' => ['type' => 'string', 'description' => 'Fixpreis-Ablaufdatum bis (Format: YYYY-MM-DD)'],
                'itemIsBio' => ['type' => 'boolean', 'description' => 'Filter nach Bio-Kennzeichen (true = nur Bio-Artikel)'],
                'itemLabelIds' => ['type' => 'array', 'description' => 'Query-Parameter itemLabelIds'],
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


        $path = '/api/v1/{tenantId}/supplier-items';

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
            'tags' => ['necta', 'v1', 'supplier-items'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
