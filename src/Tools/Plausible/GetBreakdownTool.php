<?php

namespace Platform\Integrations\Tools\Plausible;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\PlausibleApiService;
use Platform\Integrations\Exceptions\PlausibleApiException;

class GetBreakdownTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.plausible.stats_breakdown.GET';
    }

    public function getDescription(): string
    {
        return 'GET /stats/breakdown - Ruft eine Aufschlüsselung der Statistiken nach einer Dimension ab (z.B. nach Source, Page, Country, Device, Browser, OS).';
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
                'property' => [
                    'type' => 'string',
                    'description' => 'Dimension für die Aufschlüsselung: visit:source, visit:referrer, visit:utm_medium, visit:utm_source, visit:utm_campaign, visit:utm_content, visit:utm_term, visit:device, visit:browser, visit:browser_version, visit:os, visit:os_version, visit:country, visit:region, visit:city, event:page, event:name.',
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
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximale Anzahl Ergebnisse (Standard: 100).',
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Seitennummer für Paginierung.',
                ],
                'filters' => [
                    'type' => 'string',
                    'description' => 'Filter-Ausdruck (z.B. visit:source==Google).',
                ],
            ],
            'required' => ['site_id', 'property'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(PlausibleApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'site_id' => $arguments['site_id'],
                'property' => $arguments['property'],
            ];

            foreach (['period', 'date', 'metrics', 'limit', 'page', 'filters'] as $key) {
                if (!empty($arguments[$key])) {
                    $params[$key] = $arguments[$key];
                }
            }

            $result = $service->getBreakdown($context->user, $params);

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
            'tags' => ['plausible', 'analytics', 'breakdown', 'dimensions'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
