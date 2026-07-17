<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — DELETE /api/v1/{tenantId}/dashboard/{dashboardId}
 * Dashboard löschen
 */
class DashboardbyDashboardIdDeleteTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = [];

    public function getName(): string
    {
        return 'integrations.necta.v1.dashboard.by-dashboard-id.DELETE';
    }

    public function getDescription(): string
    {
        return 'Dashboard löschen
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Pfad-Parameter:
- dashboardId [REQUIRED]
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dashboardId' => ['type' => 'string', 'description' => 'Pfad-Parameter dashboardId (Pflicht).'],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => ['dashboardId'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (!isset($arguments['dashboardId']) || $arguments['dashboardId'] === '' || $arguments['dashboardId'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "dashboardId" fehlt.');
        }

        $path = '/api/v1/{tenantId}/dashboard/{dashboardId}';
        $path = str_replace('{dashboardId}', rawurlencode((string) $arguments['dashboardId']), $path);

        $query = [];
        foreach (self::QUERY_KEYS as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $query[$k] = $arguments[$k];
            }
        }

        $data = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->callSpec($context->user, 'DELETE', $path, $query, $data);

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
            'category' => 'action',
            'tags' => ['necta', 'v1', 'dashboard'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}
