<?php

namespace Platform\Integrations\Tools\WhatsApp;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationsWhatsAppTemplate;

class ListWhatsAppTemplatesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.whatsapp.templates.GET';
    }

    public function getDescription(): string
    {
        return 'GET /integrations/whatsapp/templates - Listet WhatsApp Message Templates auf. '
            . 'Zeigt pro Template: id, name, language, status, category, components. '
            . 'Filterbar nach whatsapp_account_id, status (APPROVED, PENDING, REJECTED) oder Suchbegriff.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'whatsapp_account_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Templates dieses WhatsApp Accounts anzeigen.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Template-Status (z.B. "APPROVED", "PENDING", "REJECTED").',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Kategorie (z.B. "MARKETING", "UTILITY", "AUTHENTICATION").',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Suche nach Template-Name.',
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
            $query = IntegrationsWhatsAppTemplate::query()
                ->with('whatsappAccount:id,title,phone_number');

            // Filter by account
            if (isset($arguments['whatsapp_account_id'])) {
                $query->where('whatsapp_account_id', (int) $arguments['whatsapp_account_id']);
            }

            // Filter by status
            if (isset($arguments['status']) && $arguments['status'] !== '') {
                $query->where('status', strtoupper($arguments['status']));
            }

            // Filter by category
            if (isset($arguments['category']) && $arguments['category'] !== '') {
                $query->where('category', strtoupper($arguments['category']));
            }

            // Search by name
            if (isset($arguments['search']) && $arguments['search'] !== '') {
                $query->where('name', 'like', '%' . $arguments['search'] . '%');
            }

            $templates = $query->orderBy('name')->get();

            return ToolResult::success([
                'whatsapp_templates' => $templates->map(fn ($tpl) => [
                    'id' => $tpl->id,
                    'name' => $tpl->name,
                    'language' => $tpl->language,
                    'status' => $tpl->status,
                    'category' => $tpl->category,
                    'components' => $tpl->components,
                    'whatsapp_account_id' => $tpl->whatsapp_account_id,
                    'whatsapp_account' => $tpl->whatsappAccount ? [
                        'id' => $tpl->whatsappAccount->id,
                        'title' => $tpl->whatsappAccount->title,
                        'phone_number' => $tpl->whatsappAccount->phone_number,
                    ] : null,
                    'created_at' => $tpl->created_at?->toIso8601String(),
                    'updated_at' => $tpl->updated_at?->toIso8601String(),
                ])->toArray(),
                'total' => $templates->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['integrations', 'whatsapp', 'templates'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
