<?php

namespace Platform\Integrations\Tools\Meta;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationMetaFacebookPage;

/**
 * LLM-Tool: Facebook Pages einer Meta-Connection abrufen.
 *
 * Listet alle Facebook Pages auf, die einer Meta-IntegrationConnection
 * als Ressourcen zugeordnet sind. Nur der Owner der Connection darf abrufen.
 */
class ListFacebookPagesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.meta.facebook_pages.GET';
    }

    public function getDescription(): string
    {
        return 'GET /connections/{connection_id}/meta/facebook-pages - Listet alle Facebook Pages einer Meta-Connection auf. '
            . 'Zeigt pro Page: page_id, name und is_active. '
            . 'Nur der Owner der Connection kann Facebook Pages abrufen. '
            . 'Optional: is_active Filter um nur aktive/inaktive Pages zu zeigen. '
            . 'Der page-spezifische access_token wird aus Sicherheitsgründen nicht angezeigt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Meta-IntegrationConnection, deren Facebook Pages abgerufen werden sollen.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur aktive (true) oder inaktive (false) Pages anzeigen. Ohne Filter werden alle angezeigt.',
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
                return ToolResult::error('FORBIDDEN', 'Nur der Owner der Connection kann Facebook Pages abrufen.');
            }

            $query = IntegrationMetaFacebookPage::where('connection_id', $connectionId);

            if (isset($arguments['is_active'])) {
                $query->where('is_active', $arguments['is_active']);
            }

            $pages = $query->get();

            return ToolResult::success([
                'connection_id' => $connection->id,
                'facebook_pages' => $pages->map(fn ($page) => [
                    'id' => $page->id,
                    'page_id' => $page->page_id,
                    'name' => $page->name,
                    'is_active' => $page->is_active,
                    'created_at' => $page->created_at?->toIso8601String(),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                ])->toArray(),
                'total' => $pages->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['integrations', 'meta', 'facebook', 'pages', 'resources'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
