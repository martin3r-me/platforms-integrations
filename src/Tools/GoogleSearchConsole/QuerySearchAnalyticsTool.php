<?php

namespace Platform\Integrations\Tools\GoogleSearchConsole;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\GoogleSearchConsoleApiService;
use Platform\Integrations\Exceptions\GoogleSearchConsoleApiException;

class QuerySearchAnalyticsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.google_search_console.search_analytics.POST';
    }

    public function getDescription(): string
    {
        return 'POST /sites/{siteUrl}/searchAnalytics/query - Search Analytics abfragen. Liefert Clicks, Impressions, CTR und Position. Pflicht: startDate, endDate (YYYY-MM-DD). Optional: dimensions (query, page, country, device, date), dimensionFilterGroups, rowLimit, startRow, type, dataState, aggregationType.';
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
                'start_date' => [
                    'type' => 'string',
                    'description' => 'Startdatum (YYYY-MM-DD).',
                ],
                'end_date' => [
                    'type' => 'string',
                    'description' => 'Enddatum (YYYY-MM-DD).',
                ],
                'dimensions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Dimensionen: query, page, country, device, date, searchAppearance.',
                ],
                'dimension_filter_groups' => [
                    'type' => 'array',
                    'items' => ['type' => 'object'],
                    'description' => 'Filter-Gruppen zum Einschränken der Ergebnisse.',
                ],
                'row_limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Anzahl Zeilen (Standard: 1000, Max: 25000).',
                ],
                'start_row' => [
                    'type' => 'integer',
                    'description' => 'Start-Zeile für Paginierung (0-basiert).',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Search-Typ: web, image, video, news, discover, googleNews.',
                ],
                'data_state' => [
                    'type' => 'string',
                    'description' => 'Datenstatus: final, all (inkl. vorläufige Daten).',
                ],
                'aggregation_type' => [
                    'type' => 'string',
                    'description' => 'Aggregations-Typ: auto, byPage, byProperty.',
                ],
            ],
            'required' => ['site_url', 'start_date', 'end_date'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(GoogleSearchConsoleApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'startDate' => $arguments['start_date'],
                'endDate' => $arguments['end_date'],
            ];

            if (!empty($arguments['dimensions'])) {
                $params['dimensions'] = $arguments['dimensions'];
            }
            if (!empty($arguments['dimension_filter_groups'])) {
                $params['dimensionFilterGroups'] = $arguments['dimension_filter_groups'];
            }
            if (isset($arguments['row_limit'])) {
                $params['rowLimit'] = $arguments['row_limit'];
            }
            if (isset($arguments['start_row'])) {
                $params['startRow'] = $arguments['start_row'];
            }
            if (!empty($arguments['type'])) {
                $params['type'] = $arguments['type'];
            }
            if (!empty($arguments['data_state'])) {
                $params['dataState'] = $arguments['data_state'];
            }
            if (!empty($arguments['aggregation_type'])) {
                $params['aggregationType'] = $arguments['aggregation_type'];
            }

            $result = $service->querySearchAnalytics($context->user, $arguments['site_url'], $params);

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
            'tags' => ['google-search-console', 'seo', 'search-analytics', 'clicks', 'impressions', 'ctr', 'position'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
