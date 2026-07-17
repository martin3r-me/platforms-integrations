<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/stocks
 * Alle Lager
 */
class StocksGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['page', 'pageSize', 'id', 'designation', 'costCenterId', 'code', 'searchCode', 'isBlocked', 'bookingAccountNumber', 'isNoNegativeStockAllowed'];

    public function getName(): string
    {
        return 'integrations.necta.v1.stocks.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Lager
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- page: integer — Seitennummer für die Paginierung (1-basiert, Standard: 1)
- pageSize: integer — Anzahl der Ergebnisse pro Seite (Standard: 100)
- id: integer — Filter nach Lager-ID
- designation: string — Filter nach Bezeichnung
- costCenterId: integer — Filter nach Kostenstellen-ID
- code: string — Filter nach Code
- searchCode: string — Filter nach Suchcode
- isBlocked: boolean — Filter nach Sperrlager
- bookingAccountNumber: string — Filter nach Buchungskontonummer
- isNoNegativeStockAllowed: boolean — Filter nach kein Negativlager erlaubt
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Seitennummer für die Paginierung (1-basiert, Standard: 1)'],
                'pageSize' => ['type' => 'integer', 'description' => 'Anzahl der Ergebnisse pro Seite (Standard: 100)'],
                'id' => ['type' => 'integer', 'description' => 'Filter nach Lager-ID'],
                'designation' => ['type' => 'string', 'description' => 'Filter nach Bezeichnung'],
                'costCenterId' => ['type' => 'integer', 'description' => 'Filter nach Kostenstellen-ID'],
                'code' => ['type' => 'string', 'description' => 'Filter nach Code'],
                'searchCode' => ['type' => 'string', 'description' => 'Filter nach Suchcode'],
                'isBlocked' => ['type' => 'boolean', 'description' => 'Filter nach Sperrlager'],
                'bookingAccountNumber' => ['type' => 'string', 'description' => 'Filter nach Buchungskontonummer'],
                'isNoNegativeStockAllowed' => ['type' => 'boolean', 'description' => 'Filter nach kein Negativlager erlaubt'],
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


        $path = '/api/v1/{tenantId}/stocks';

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
            'tags' => ['necta', 'v1', 'stocks'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
