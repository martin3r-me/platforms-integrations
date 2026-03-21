<?php

namespace Platform\Integrations\Tools\WhatsApp;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationsWhatsAppAccount;

class ListWhatsAppAccountsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.whatsapp.accounts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /integrations/whatsapp/accounts - Listet alle WhatsApp Business Accounts auf. '
            . 'Zeigt pro Account: id, title, phone_number, phone_number_id, active, templates_count. '
            . 'Optional filterbar nach connection_id oder active-Status.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Accounts dieser Meta-Connection anzeigen.',
                ],
                'active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur aktive (true) oder inaktive (false) Accounts anzeigen.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $query = IntegrationsWhatsAppAccount::query()
                ->withCount('templates');

            // Filter by connection
            if (isset($arguments['connection_id'])) {
                $query->where('integration_connection_id', (int) $arguments['connection_id']);
            }

            // Filter by active status
            if (isset($arguments['active'])) {
                $query->where('active', $arguments['active']);
            }

            $accounts = $query->get();

            return ToolResult::success([
                'whatsapp_accounts' => $accounts->map(fn ($account) => [
                    'id' => $account->id,
                    'title' => $account->title,
                    'phone_number' => $account->phone_number,
                    'phone_number_id' => $account->phone_number_id,
                    'external_id' => $account->external_id,
                    'active' => $account->active,
                    'description' => $account->description,
                    'connection_id' => $account->integration_connection_id,
                    'templates_count' => $account->templates_count,
                    'last_used_at' => $account->last_used_at?->toIso8601String(),
                    'verified_at' => $account->verified_at?->toIso8601String(),
                    'created_at' => $account->created_at?->toIso8601String(),
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
            'tags' => ['integrations', 'whatsapp', 'accounts'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
