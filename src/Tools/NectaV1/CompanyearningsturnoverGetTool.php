<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/companyearnings/turnover
 * Unternehmensumsatz abrufen
 */
class CompanyearningsturnoverGetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.companyearnings.turnover.GET';
    }

    public function getDescription(): string
    {
        return 'Unternehmensumsatz (Zeitreihe: Ist vs. Vorperiode) abrufen. Parameter unter `query`. dateFrom muss < dateTo sein (ISO yyyy-MM-dd).

Query-Parameter (`query`):
- dateFrom: string [REQUIRED] — ISO yyyy-MM-dd, Bereich von (muss < dateTo)
- dateTo: string [REQUIRED] — ISO yyyy-MM-dd, Bereich bis
- aggregateBy: integer [REQUIRED] — DateAggregation-Enum: 1=Tag, 2=Woche, 3=Monat, 4=Quartal, 5=Jahr
- costCenterIds: string — (Optional) Kostenstellen-IDs, kommagetrennt

Spec: https://docu.necta.one/necta.one-api (spec/necta-one.json).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'object', 'description' => 'Query-Parameter. Erforderlich: dateFrom, dateTo, aggregateBy. Siehe Tool-Description.'],
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


        $path = '/api/v1/{tenantId}/companyearnings/turnover';

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
            'tags' => ['necta', 'v1', 'companyearnings'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
