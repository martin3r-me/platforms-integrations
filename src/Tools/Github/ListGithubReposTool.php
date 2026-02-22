<?php

namespace Platform\Integrations\Tools\Github;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationGithubRepo;

/**
 * LLM-Tool: GitHub Repos einer GitHub-Connection abrufen.
 *
 * Listet alle GitHub Repos auf, die einer GitHub-IntegrationConnection
 * als Ressourcen zugeordnet sind. Nur der Owner der Connection darf abrufen.
 */
class ListGithubReposTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.github.repos.GET';
    }

    public function getDescription(): string
    {
        return 'GET /connections/{connection_id}/github/repos - Listet alle GitHub Repos einer GitHub-Connection auf. '
            . 'Zeigt pro Repo: github_repo_id, full_name, name, owner, is_private und is_active. '
            . 'Nur der Owner der Connection kann GitHub Repos abrufen. '
            . 'Optional: is_active Filter um nur aktive/inaktive Repos zu zeigen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der GitHub-IntegrationConnection, deren Repos abgerufen werden sollen.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur aktive (true) oder inaktive (false) Repos anzeigen. Ohne Filter werden alle angezeigt.',
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
                return ToolResult::error('FORBIDDEN', 'Nur der Owner der Connection kann GitHub Repos abrufen.');
            }

            $query = IntegrationGithubRepo::where('connection_id', $connectionId);

            if (isset($arguments['is_active'])) {
                $query->where('is_active', $arguments['is_active']);
            }

            $repos = $query->orderBy('full_name')->get();

            return ToolResult::success([
                'connection_id' => $connection->id,
                'github_repos' => $repos->map(fn ($repo) => [
                    'id' => $repo->id,
                    'github_repo_id' => $repo->github_repo_id,
                    'full_name' => $repo->full_name,
                    'name' => $repo->name,
                    'owner' => $repo->owner,
                    'is_private' => $repo->is_private,
                    'is_active' => $repo->is_active,
                    'created_at' => $repo->created_at?->toIso8601String(),
                    'updated_at' => $repo->updated_at?->toIso8601String(),
                ])->toArray(),
                'total' => $repos->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['integrations', 'github', 'repos', 'resources'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
