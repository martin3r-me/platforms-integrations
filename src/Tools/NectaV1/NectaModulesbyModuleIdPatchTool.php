<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — PATCH /api/v1/{tenantId}/necta-modules/{moduleId}
 * Necta Modul aktualisieren (Name + EntryPoint)
 */
class NectaModulesbyModuleIdPatchTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.necta-modules.by-module-id.PATCH';
    }

    public function getDescription(): string
    {
        return 'Necta Modul aktualisieren (Name + EntryPoint)

Body (`data`):
- name: string [REQ] — Anzeigename des Moduls.
- entryPoint: string [REQ] — Einstiegspunkt des Moduls.
- icon: string — Optionales Symbol des Moduls.
- isDisabled: boolean — Gibt an, ob das Modul deaktiviert ist.

Spec: https://docu.necta.one/necta.one-api (spec/necta-one.json).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'moduleId' => ['type' => 'string', 'description' => 'Pfad-Parameter moduleId (Pflicht).'],
                'data' => ['type' => 'object', 'description' => 'Request-Body. Felder siehe Tool-Description.', 'additionalProperties' => true],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => ['moduleId', 'data'],
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
        if (!isset($arguments['data']) || $arguments['data'] === '' || $arguments['data'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "data" fehlt.');
        }

        $path = '/api/v1/{tenantId}/necta-modules/{moduleId}';
        $path = str_replace('{moduleId}', rawurlencode((string) $arguments['moduleId']), $path);

        $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];

        $data = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->callSpec($context->user, 'PATCH', $path, $query, $data);

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
            'tags' => ['necta', 'v1', 'necta-modules'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
