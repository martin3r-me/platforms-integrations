<?php

namespace Platform\Integrations\Tools\Github;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\IntegrationGithubRepoService;

/**
 * LLM-Tool: Manuellen Sync der GitHub-Ressourcen triggern.
 *
 * Synchronisiert Repos einer GitHub-Connection mit der GitHub API.
 * Nur der Owner der Connection darf den Sync triggern.
 */
class SyncGithubResourcesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.github.resources.SYNC';
    }

    public function getDescription(): string
    {
        return 'POST /connections/{connection_id}/github/resources/sync - Triggert einen manuellen Sync der GitHub-Ressourcen (Repos). '
            . 'Lädt alle Repos aus der GitHub API und aktualisiert die lokale Datenbank. '
            . 'Neue Repos werden hinzugefügt, entfernte Repos werden als inaktiv markiert. '
            . 'Nur der Owner der Connection kann den Sync triggern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der GitHub-IntegrationConnection, deren Ressourcen synchronisiert werden sollen.',
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

            if (!$connection->isOwner($context->user)) {
                return ToolResult::error('FORBIDDEN', 'Nur der Owner der Connection kann den Sync triggern.');
            }

            if ($connection->status !== 'active') {
                return ToolResult::error('INACTIVE', "Connection #{$connectionId} ist nicht aktiv (Status: {$connection->status}).");
            }

            $service = resolve(IntegrationGithubRepoService::class);
            $result = $service->syncRepos($connection);

            return ToolResult::success([
                'connection_id' => $connection->id,
                'synced' => $result['synced'],
                'deactivated' => $result['deactivated'],
                'message' => "{$result['synced']} Repo(s) synchronisiert, {$result['deactivated']} deaktiviert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Sync: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['integrations', 'github', 'sync', 'resources'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
