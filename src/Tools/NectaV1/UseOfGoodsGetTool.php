<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/use-of-goods
 * Wareneinsatz abrufen
 */
class UseOfGoodsGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['dateFrom', 'dateTo', 'aggregateBy', 'groupType'];

    public function getName(): string
    {
        return 'integrations.necta.v1.use-of-goods.GET';
    }

    public function getDescription(): string
    {
        return 'Wareneinsatz abrufen
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- dateFrom: string [REQUIRED] — Datumsbereich von
- dateTo: string [REQUIRED] — Datumsbereich bis
- aggregateBy: integer [REQUIRED] enum[1|2|3|4|5] DateAggregation-Enum: 1=Tag, 2=Woche, 3=Monat, 4=Quartal, 5=Jahr.
- groupType: integer [REQUIRED] enum[1|2]
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dateFrom' => ['type' => 'string', 'description' => 'Datumsbereich von'],
                'dateTo' => ['type' => 'string', 'description' => 'Datumsbereich bis'],
                'aggregateBy' => ['type' => 'integer', 'enum' => [1, 2, 3, 4, 5], 'description' => 'DateAggregation-Enum: 1=Tag, 2=Woche, 3=Monat, 4=Quartal, 5=Jahr.'],
                'groupType' => ['type' => 'integer', 'enum' => [1, 2], 'description' => 'Query-Parameter groupType'],
                'fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte, z.B. "customer.customerNumber"). Reduziert die Antwortgröße drastisch.'],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => ['dateFrom', 'dateTo', 'aggregateBy', 'groupType'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (!isset($arguments['dateFrom']) || $arguments['dateFrom'] === '' || $arguments['dateFrom'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "dateFrom" fehlt.');
        }
        if (!isset($arguments['dateTo']) || $arguments['dateTo'] === '' || $arguments['dateTo'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "dateTo" fehlt.');
        }
        if (!isset($arguments['aggregateBy']) || $arguments['aggregateBy'] === '' || $arguments['aggregateBy'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "aggregateBy" fehlt.');
        }
        if (!isset($arguments['groupType']) || $arguments['groupType'] === '' || $arguments['groupType'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "groupType" fehlt.');
        }

        $path = '/api/v1/{tenantId}/use-of-goods';

        $query = [];
        foreach (self::QUERY_KEYS as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $query[$k] = $arguments[$k];
            }
        }

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
            'tags' => ['necta', 'v1', 'use-of-goods'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
