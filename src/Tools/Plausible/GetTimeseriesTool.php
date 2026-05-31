<?php

namespace Platform\Integrations\Tools\Plausible;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\PlausibleApiService;
use Platform\Integrations\Exceptions\PlausibleApiException;

class GetTimeseriesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.plausible.stats_timeseries.GET';
    }

    public function getDescription(): string
    {
        return 'GET /stats/timeseries - Ruft Zeitreihen-Daten für eine Site ab (Trend-Daten pro Tag/Stunde). Ideal für Charts und Trend-Analysen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Plausible-Verbindung.',
                ],
                'site_id' => [
                    'type' => 'string',
                    'description' => 'Domain der Site (z.B. example.com).',
                ],
                'period' => [
                    'type' => 'string',
                    'description' => 'Zeitraum: 12mo, 6mo, month, 30d, 7d, day, custom.',
                    'enum' => ['12mo', '6mo', 'month', '30d', '7d', 'day', 'custom'],
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Datum (YYYY-MM-DD). Bei custom: Start,End (z.B. 2026-01-01,2026-01-31).',
                ],
                'metrics' => [
                    'type' => 'string',
                    'description' => 'Komma-getrennte Metriken: visitors, visits, pageviews, views_per_visit, bounce_rate, visit_duration, events.',
                ],
                'interval' => [
                    'type' => 'string',
                    'description' => 'Intervall: date, month.',
                    'enum' => ['date', 'month'],
                ],
                'filters' => [
                    'type' => 'string',
                    'description' => 'Filter-Ausdruck (z.B. visit:source==Google).',
                ],
            ],
            'required' => ['site_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(PlausibleApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = ['site_id' => $arguments['site_id']];

            foreach (['period', 'date', 'metrics', 'interval', 'filters'] as $key) {
                if (!empty($arguments[$key])) {
                    $params[$key] = $arguments[$key];
                }
            }

            $result = $service->getTimeseries($context->user, $params);

            return ToolResult::success($result);
        } catch (PlausibleApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'PLAUSIBLE_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['plausible', 'analytics', 'timeseries', 'trends'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
