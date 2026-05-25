<?php

namespace Platform\Integrations\Tools\GoogleSearchConsole;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\GoogleSearchConsoleApiService;
use Platform\Integrations\Exceptions\GoogleSearchConsoleApiException;

class GetSitemapTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.google_search_console.sitemaps.detail.GET';
    }

    public function getDescription(): string
    {
        return 'GET /sites/{siteUrl}/sitemaps/{feedpath} - Details einer spezifischen Sitemap abrufen. Gibt path, lastSubmitted, warnings, errors und contents zurück.';
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
                'feedpath' => [
                    'type' => 'string',
                    'description' => 'Die URL der Sitemap (z.B. "https://example.com/sitemap.xml").',
                ],
            ],
            'required' => ['site_url', 'feedpath'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(GoogleSearchConsoleApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = $service->getSitemap($context->user, $arguments['site_url'], $arguments['feedpath']);

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
            'tags' => ['google-search-console', 'seo', 'sitemaps', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
