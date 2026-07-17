<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/necta-modules/{moduleId}/details
 * Necta Modul laden (Details + Dateien)
 */
class NectaModulesbyModuleIddetailsGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = [];

    public function getName(): string
    {
        return 'integrations.necta.v1.necta-modules.by-module-id.details.GET';
    }

    public function getDescription(): string
    {
        return 'Necta Modul laden (Details + Dateien)
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Pfad-Parameter:
- moduleId [REQUIRED]
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'moduleId' => ['type' => 'string', 'description' => 'Pfad-Parameter moduleId (Pflicht).'],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => ['moduleId'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (!isset($arguments['moduleId']) || $arguments['moduleId'] === '' || $arguments['moduleId'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "moduleId" fehlt.');
        }

        $path = '/api/v1/{tenantId}/necta-modules/{moduleId}/details';
        $path = str_replace('{moduleId}', rawurlencode((string) $arguments['moduleId']), $path);

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
            'tags' => ['necta', 'v1', 'necta-modules'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
