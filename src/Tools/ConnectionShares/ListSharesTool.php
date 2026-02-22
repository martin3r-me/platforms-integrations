<?php

namespace Platform\Integrations\Tools\ConnectionShares;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\IntegrationConnectionShareService;

/**
 * LLM-Tool: Freigaben einer Connection abrufen.
 *
 * Zeigt alle aktiven Freigaben (Shares) einer Integration-Connection an.
 * Nur der Owner der Connection darf Freigaben einsehen.
 *
 * Wildcard-Logik:
 * - team_id=null → gilt für ALLE Teams
 * - user_id=null → gilt für ALLE User
 * - Beides null  → vollständig öffentlich (alle User in allen Teams)
 */
class ListSharesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.connections.shares.GET';
    }

    public function getDescription(): string
    {
        return 'GET /connections/{connection_id}/shares - Listet alle Freigaben (Shares) einer Integration-Connection auf. '
            . 'Nur der Owner der Connection kann Freigaben einsehen. '
            . 'Zeigt pro Share: Ziel-Team, Ziel-User und die Wildcard-Auflösung (null = alle). '
            . 'Beispiel: team_id=5, user_id=null bedeutet "Alle User in Team 5 dürfen diese Connection nutzen".';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der IntegrationConnection, deren Freigaben abgerufen werden sollen.',
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

            $service = app(IntegrationConnectionShareService::class);
            $shares = $service->listShares($context->user, $connection);

            return ToolResult::success([
                'connection_id' => $connection->id,
                'owner_user_id' => $connection->owner_user_id,
                'shares' => $shares->toArray(),
                'total' => $shares->count(),
                'wildcard_info' => 'null-Werte sind Wildcards: team_id=null → alle Teams, user_id=null → alle User, beides null → vollständig öffentlich.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('FORBIDDEN', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['integrations', 'connections', 'shares', 'sharing', 'access-control'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
