<?php

namespace Platform\Integrations\Tools\ConnectionShares;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\IntegrationConnectionShareService;

/**
 * LLM-Tool: Freigabe einer Connection entfernen.
 *
 * Entfernt eine bestehende Freigabe (Share) von einer Integration-Connection.
 * Nur der Owner der Connection darf Freigaben entfernen.
 *
 * Nach dem Entfernen verlieren die betroffenen User/Teams sofort den Zugriff
 * über diesen Share (sofern kein anderer Share oder Legacy-Grant greift).
 */
class DeleteShareTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.connections.shares.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /connections/{connection_id}/shares/{share_id} - Entfernt eine Freigabe (Share) von einer Integration-Connection. '
            . 'Nur der Owner kann Freigaben entfernen. '
            . 'Nach dem Entfernen verlieren betroffene User/Teams sofort den Zugriff über diesen Share. '
            . 'Nutze zuerst integrations.connections.shares.GET um die Share-ID zu ermitteln.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der IntegrationConnection.',
                ],
                'share_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu entfernenden Shares. Ermittle diese über integrations.connections.shares.GET.',
                ],
            ],
            'required' => ['connection_id', 'share_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $connectionId = $arguments['connection_id'] ?? null;
        $shareId = $arguments['share_id'] ?? null;

        if (!$connectionId) {
            return ToolResult::error('VALIDATION_ERROR', 'connection_id ist erforderlich.');
        }
        if (!$shareId) {
            return ToolResult::error('VALIDATION_ERROR', 'share_id ist erforderlich.');
        }

        try {
            $connection = IntegrationConnection::find($connectionId);
            if (!$connection) {
                return ToolResult::error('NOT_FOUND', "Connection #{$connectionId} nicht gefunden.");
            }

            $service = app(IntegrationConnectionShareService::class);
            $service->deleteShare($context->user, $connection, (int) $shareId);

            return ToolResult::success([
                'message' => 'Freigabe entfernt.',
                'connection_id' => $connectionId,
                'share_id' => $shareId,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('FORBIDDEN', $e->getMessage());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ToolResult::error('NOT_FOUND', "Share #{$shareId} nicht gefunden für Connection #{$connectionId}.");
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['integrations', 'connections', 'shares', 'sharing', 'access-control', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
