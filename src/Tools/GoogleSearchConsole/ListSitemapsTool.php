<?php

namespace Platform\Integrations\Tools\GoogleSearchConsole;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\GoogleSearchConsoleApiService;
use Platform\Integrations\Exceptions\GoogleSearchConsoleApiException;

class ListSitemapsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.google_search_console.sitemaps.GET';
    }

    public function getDescription(): string
    {
        return 'GET /sites/{siteUrl}/sitemaps - Listet alle Sitemaps einer Google Search Console Site auf. Gibt path, lastSubmitted, isPending, isSitemapsIndex und weitere Metadaten zurück.';
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
                'site_url' => [
                    'type' => 'string',
                    'description' => 'Die Site-URL (z.B. "https://example.com/" oder "sc-domain:example.com").',
                ],
            ],
            'required' => ['site_url'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(GoogleSearchConsoleApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = $service->getSitemaps($context->user, $arguments['site_url']);

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
            'tags' => ['google-search-console', 'seo', 'sitemaps', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
