<?php

namespace Platform\Integrations\Tools\ConnectionShares;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\IntegrationConnectionShareService;

/**
 * LLM-Tool: Freigabe für eine Connection erstellen.
 *
 * Erteilt Zugriff auf eine Integration-Connection an bestimmte Teams/User.
 * Nur der Owner der Connection darf Freigaben erteilen.
 *
 * Wildcard-Support (null = alle):
 * - team_id=5, user_id=null   → Alle User in Team 5 dürfen die Connection nutzen
 * - team_id=null, user_id=42  → User 42 darf die Connection in allen Teams nutzen
 * - team_id=5, user_id=42     → Nur User 42 in Team 5 darf die Connection nutzen
 * - team_id=null, user_id=null → Alle User in allen Teams (vollständig öffentlich)
 */
class CreateShareTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.connections.shares.POST';
    }

    public function getDescription(): string
    {
        return 'POST /connections/{connection_id}/shares - Erstellt eine Freigabe (Share) für eine Integration-Connection. '
            . 'Nur der Owner kann Freigaben erteilen. '
            . 'Wildcard-Support: team_id und/oder user_id können null sein. '
            . 'null bedeutet "alle" – z.B. team_id=null, user_id=null ergibt eine vollständig öffentliche Freigabe. '
            . 'team_id=5, user_id=null bedeutet "alle User in Team 5". '
            . 'Duplikate (gleiche connection_id + team_id + user_id) werden ignoriert (idempotent).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der IntegrationConnection, für die eine Freigabe erstellt werden soll.',
                ],
                'team_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Ziel-Team-ID. null = Wildcard (alle Teams). Wenn gesetzt, gilt die Freigabe nur für dieses Team.',
                ],
                'user_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Ziel-User-ID. null = Wildcard (alle User). Wenn gesetzt, gilt die Freigabe nur für diesen User.',
                ],
            ],
            'required' => ['connection_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $connectionId = $arguments['connection_id'] ?? null;
        if (!$connectionId) {
            return ToolResult::error('VALIDATION_ERROR', 'connection_id ist erforderlich.');
        }

        try {
            $connection = IntegrationConnection::find($connectionId);
            if (!$connection) {
                return ToolResult::error('NOT_FOUND', "Connection #{$connectionId} nicht gefunden.");
            }

            $teamId = array_key_exists('team_id', $arguments) ? $arguments['team_id'] : null;
            $userId = array_key_exists('user_id', $arguments) ? $arguments['user_id'] : null;

            // Integer-Cast für nicht-null Werte
            $teamId = $teamId !== null ? (int) $teamId : null;
            $userId = $userId !== null ? (int) $userId : null;

            $service = app(IntegrationConnectionShareService::class);
            $share = $service->createShare($context->user, $connection, $teamId, $userId);

            return ToolResult::success([
                'message' => 'Freigabe erstellt.',
                'share' => $service->formatShare($share),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('FORBIDDEN', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['integrations', 'connections', 'shares', 'sharing', 'access-control', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
