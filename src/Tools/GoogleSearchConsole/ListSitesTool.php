<?php

namespace Platform\Integrations\Tools\GoogleSearchConsole;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\GoogleSearchConsoleApiService;
use Platform\Integrations\Exceptions\GoogleSearchConsoleApiException;

class ListSitesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.google_search_console.sites.GET';
    }

    public function getDescription(): string
    {
        return 'GET /sites - Listet alle verifizierten Google Search Console Sites auf. Gibt siteUrl, permissionLevel und weitere Metadaten zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Google Search Console-Verbindung.',
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
            $service = app(GoogleSearchConsoleApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = $service->getSites($context->user);

            return ToolResult::success($result);
        } catch (GoogleSearchConsoleApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'GSC_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['google-search-console', 'seo', 'sites', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
