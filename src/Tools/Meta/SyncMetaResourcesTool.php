<?php

namespace Platform\Integrations\Tools\Meta;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\IntegrationMetaResourceService;

/**
 * LLM-Tool: Manuellen Sync der Meta-Ressourcen triggern.
 *
 * Synchronisiert Facebook Pages und Instagram Accounts einer Meta-Connection
 * mit der Meta API. Nur der Owner der Connection darf den Sync triggern.
 */
class SyncMetaResourcesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.meta.resources.SYNC';
    }

    public function getDescription(): string
    {
        return 'POST /connections/{connection_id}/meta/resources/sync - Triggert einen manuellen Sync der Meta-Ressourcen (Facebook Pages + Instagram Accounts). '
            . 'Lädt alle Facebook Pages via GET /me/accounts und Instagram Accounts via GET /{page-id}?fields=instagram_business_account aus der Meta API. '
            . 'Neue Ressourcen werden hinzugefügt, entfernte werden als inaktiv markiert. '
            . 'Nur der Owner der Connection kann den Sync triggern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Meta-IntegrationConnection, deren Ressourcen synchronisiert werden sollen.',
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

            $service = resolve(IntegrationMetaResourceService::class);
            $result = $service->syncResources($connection);

            $pages = $result['facebook_pages'];
            $accounts = $result['instagram_accounts'];

            $totalSynced = $pages['synced'] + $accounts['synced'];
            $totalDeactivated = $pages['deactivated'] + $accounts['deactivated'];

            return ToolResult::success([
                'connection_id' => $connection->id,
                'facebook_pages' => $pages,
                'instagram_accounts' => $accounts,
                'message' => "{$pages['synced']} Facebook Page(s) und {$accounts['synced']} Instagram Account(s) synchronisiert, "
                    . "{$pages['deactivated']} Page(s) und {$accounts['deactivated']} Account(s) deaktiviert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Sync: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['integrations', 'meta', 'sync', 'resources', 'facebook', 'instagram'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
