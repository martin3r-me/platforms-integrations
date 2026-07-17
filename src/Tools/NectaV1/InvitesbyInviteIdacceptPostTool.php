<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — POST /api/v1/{tenantId}/invites/{inviteId}/accept
 * necta.one Einladung akzeptieren
 */
class InvitesbyInviteIdacceptPostTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.invites.by-invite-id.accept.POST';
    }

    public function getDescription(): string
    {
        return 'necta.one Einladung akzeptieren

Spec: https://docu.necta.one/necta.one-api (spec/necta-one.json).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'inviteId' => ['type' => 'string', 'description' => 'Pfad-Parameter inviteId (Pflicht).'],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => ['inviteId'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (!isset($arguments['inviteId']) || $arguments['inviteId'] === '' || $arguments['inviteId'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "inviteId" fehlt.');
        }

        $path = '/api/v1/{tenantId}/invites/{inviteId}/accept';
        $path = str_replace('{inviteId}', rawurlencode((string) $arguments['inviteId']), $path);

        $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];

        $data = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->callSpec($context->user, 'POST', $path, $query, $data);

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
            'tags' => ['necta', 'v1', 'invites'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
