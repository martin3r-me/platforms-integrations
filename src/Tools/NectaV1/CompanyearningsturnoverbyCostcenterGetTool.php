<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/companyearnings/turnover/by-costcenter
 * Unternehmensumsatz nach Kostenstellen abrufen
 */
class CompanyearningsturnoverbyCostcenterGetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.companyearnings.turnover.by-costcenter.GET';
    }

    public function getDescription(): string
    {
        return 'Umsatz je Kostenstelle (Baum mit costCenterId + name/[code]). Parameter unter `query`. dateFrom muss < dateTo sein (ISO yyyy-MM-dd).
TIPP: Auch der schnellste Weg an die Kostenstellen-Stammdaten (id, Name inkl. [code]) — es gibt keinen separaten cost-centers-Endpunkt.

Query-Parameter (`query`):
- dateFrom: string [REQUIRED] — ISO yyyy-MM-dd, Bereich von (muss < dateTo)
- dateTo: string [REQUIRED] — ISO yyyy-MM-dd, Bereich bis
- includeChildren: boolean [REQUIRED] — true = Unterkostenstellen als Baum mitliefern
- costCenterIds: string — (Optional) Kostenstellen-IDs, kommagetrennt

Spec: https://docu.necta.one/necta.one-api (spec/necta-one.json).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'object', 'description' => 'Query-Parameter. Erforderlich: dateFrom, dateTo, includeChildren. Siehe Tool-Description.'],
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


        $path = '/api/v1/{tenantId}/companyearnings/turnover/by-costcenter';

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
