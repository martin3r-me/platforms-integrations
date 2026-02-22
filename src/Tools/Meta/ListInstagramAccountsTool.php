<?php

namespace Platform\Integrations\Tools\Meta;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationMetaInstagramAccount;

/**
 * LLM-Tool: Instagram Accounts einer Meta-Connection abrufen.
 *
 * Listet alle Instagram Accounts auf, die einer Meta-IntegrationConnection
 * als Ressourcen zugeordnet sind. Nur der Owner der Connection darf abrufen.
 */
class ListInstagramAccountsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.meta.instagram_accounts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /connections/{connection_id}/meta/instagram-accounts - Listet alle Instagram Accounts einer Meta-Connection auf. '
            . 'Zeigt pro Account: instagram_account_id, name, username, profile_picture_url und is_active. '
            . 'Nur der Owner der Connection kann Instagram Accounts abrufen. '
            . 'Optional: is_active Filter um nur aktive/inaktive Accounts zu zeigen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Meta-IntegrationConnection, deren Instagram Accounts abgerufen werden sollen.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur aktive (true) oder inaktive (false) Accounts anzeigen. Ohne Filter werden alle angezeigt.',
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
                return ToolResult::error('FORBIDDEN', 'Nur der Owner der Connection kann Instagram Accounts abrufen.');
            }

            $query = IntegrationMetaInstagramAccount::where('connection_id', $connectionId);

            if (isset($arguments['is_active'])) {
                $query->where('is_active', $arguments['is_active']);
            }

            $accounts = $query->get();

            return ToolResult::success([
                'connection_id' => $connection->id,
                'instagram_accounts' => $accounts->map(fn ($account) => [
                    'id' => $account->id,
                    'instagram_account_id' => $account->instagram_account_id,
                    'name' => $account->name,
                    'username' => $account->username,
                    'profile_picture_url' => $account->profile_picture_url,
                    'is_active' => $account->is_active,
                    'created_at' => $account->created_at?->toIso8601String(),
                    'updated_at' => $account->updated_at?->toIso8601String(),
                ])->toArray(),
                'total' => $accounts->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['integrations', 'meta', 'instagram', 'accounts', 'resources'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
