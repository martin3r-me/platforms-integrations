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
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['dateFrom', 'dateTo', 'costCenterIds', 'includeChildren'];

    public function getName(): string
    {
        return 'integrations.necta.v1.companyearnings.turnover.by-costcenter.GET';
    }

    public function getDescription(): string
    {
        return 'Unternehmensumsatz nach Kostenstellen abrufen
Auch der schnellste Weg an die Kostenstellen-Stammdaten (id + name/[code]) — es gibt keinen separaten cost-centers-Endpunkt. dateFrom muss < dateTo sein.
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- dateFrom: string [REQUIRED] — Datumsbereich von
- dateTo: string [REQUIRED] — Datumsbereich bis
- costCenterIds: string — (Optional) Gewünschte Kostenstellen-IDs (kommagetrennt)
- includeChildren: boolean [REQUIRED] — Wenn gesetzt, werden auch die Unterkostenstellen mitgeladen und übergeben true = Unterkostenstellen als Baum mitliefern.
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dateFrom' => ['type' => 'string', 'description' => 'Datumsbereich von'],
                'dateTo' => ['type' => 'string', 'description' => 'Datumsbereich bis'],
                'costCenterIds' => ['type' => 'string', 'description' => '(Optional) Gewünschte Kostenstellen-IDs (kommagetrennt)'],
                'includeChildren' => ['type' => 'boolean', 'description' => 'Wenn gesetzt, werden auch die Unterkostenstellen mitgeladen und übergeben true = Unterkostenstellen als Baum mitliefern.'],
                'fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte, z.B. "customer.customerNumber"). Reduziert die Antwortgröße drastisch.'],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => ['dateFrom', 'dateTo', 'includeChildren'],
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
        if (!isset($arguments['includeChildren']) || $arguments['includeChildren'] === '' || $arguments['includeChildren'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "includeChildren" fehlt.');
        }

        $path = '/api/v1/{tenantId}/companyearnings/turnover/by-costcenter';

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
            'tags' => ['necta', 'v1', 'companyearnings'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
