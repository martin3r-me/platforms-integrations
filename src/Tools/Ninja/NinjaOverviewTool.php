<?php

namespace Platform\Integrations\Tools\Ninja;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\NinjaIntegrationService;
use Platform\Integrations\Services\NinjaResource;

/**
 * Referenz zur NinjaOne Public API 2.0 — Einstiegspunkt vor dem Call-Tool.
 */
class NinjaOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.ninja.overview';
    }

    public function getDescription(): string
    {
        return 'Referenz zur NinjaOne Public API 2.0: Regionen, Authentifizierung, Scopes, Paginierung '
            . 'und der vollständige Endpunkt-Katalog (315 Operationen in 27 Themenbereichen). '
            . 'Ohne Argumente kommt die Übersicht mit allen Themenbereichen; mit "tag" die Endpunkte '
            . 'eines Bereichs, mit "search" eine Freitextsuche über Pfade und Beschreibungen. '
            . 'Read-only und ohne API-Aufruf — die Daten stammen aus der mitgelieferten Spezifikation.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tag' => [
                    'type' => 'string',
                    'description' => 'Themenbereich, z.B. "devices", "system", "queries", "management", "ticketing". '
                        . 'Ohne Angabe wird nur die Bereichsübersicht geliefert.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Freitextsuche über Pfad, Zusammenfassung und operationId, z.B. "reboot", "patch", "alert".',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximale Trefferzahl bei "search" (Standard: 40).',
                    'minimum' => 1,
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $tag = trim((string) ($arguments['tag'] ?? ''));
        $search = trim((string) ($arguments['search'] ?? ''));
        $limit = max(1, (int) ($arguments['limit'] ?? 40));

        if ($search !== '') {
            return ToolResult::success([
                'suche' => $search,
                'treffer' => $this->describe(NinjaResource::search($search, $limit)),
            ]);
        }

        if ($tag !== '') {
            $keys = NinjaResource::byTag($tag);

            if ($keys === []) {
                return ToolResult::error(
                    'UNKNOWN_TAG',
                    sprintf(
                        'Unbekannter Themenbereich "%s". Verfügbar: %s.',
                        $tag,
                        implode(', ', array_keys(NinjaResource::tagCounts()))
                    )
                );
            }

            return ToolResult::success([
                'themenbereich' => $tag,
                'beschreibung' => NinjaResource::TAGS[$tag] ?? null,
                'endpunkte' => $this->describe($keys),
            ]);
        }

        return ToolResult::success([
            'api' => 'NinjaOne Public API 2.0',
            'dokumentation' => 'https://app.ninjarmm.com/apidocs/',
            'spezifikation' => 'https://app.ninjarmm.com/apidocs-beta/NinjaRMM-API-v2.yaml',
            'regionen' => NinjaIntegrationService::REGIONS,
            'authentifizierung' => [
                'verfahren' => 'OAuth2 Bearer-Token',
                'client_credentials' => 'App-Typ "API Services (machine-to-machine)" — Token wird automatisch geholt und erneuert.',
                'authorization_code' => 'App-Typ "Web" — Browser-Autorisierung, benötigt registrierte Redirect-URI.',
                'token_endpunkt' => '{region}/oauth/token',
                'scopes' => [
                    'monitoring' => 'lesender Zugriff',
                    'management' => 'verändernder Zugriff',
                    'control' => 'Fernsteuerung',
                ],
            ],
            'paginierung' => [
                'parameter' => 'pageSize (max. 1000) zusammen mit after (Cursor auf der letzten ID); einzelne Endpunkte nutzen cursor.',
                'hinweis' => 'Nicht alle Listen sind paginiert — der Katalog nennt je Operation die erlaubten Query-Parameter.',
            ],
            'geraetefilter' => 'Viele Endpunkte akzeptieren "df" (device filter), z.B. "status eq APPROVED" oder "org = 5".',
            'themenbereiche' => array_map(
                fn (string $tag, int $count) => [
                    'tag' => $tag,
                    'operationen' => $count,
                    'beschreibung' => NinjaResource::TAGS[$tag] ?? null,
                ],
                array_keys(NinjaResource::tagCounts()),
                array_values(NinjaResource::tagCounts())
            ),
            'naechster_schritt' => 'integrations.ninja.overview mit "tag" für die Endpunkte eines Bereichs, '
                . 'dann integrations.ninja.call für den Aufruf.',
        ]);
    }

    /**
     * @param  array<int, string> $keys
     * @return array<int, array<string, mixed>>
     */
    protected function describe(array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            [$method, $path] = explode(' ', $key, 2);
            $op = NinjaResource::operation($method, $path);

            if (!$op) {
                continue;
            }

            $entry = [
                'operation' => $key,
                'beschreibung' => $op['summary'],
            ];

            foreach (['path' => 'pfad_parameter', 'query' => 'query_parameter', 'required' => 'pflicht_parameter'] as $src => $label) {
                if (!empty($op[$src])) {
                    $entry[$label] = $op[$src];
                }
            }

            if ($op['body']) {
                $entry['body'] = 'erwartet einen Request-Body';
            }

            if ($op['write']) {
                $entry['verändernd'] = 'ja — verlangt "confirm": true';
            }

            $out[] = $entry;
        }

        return $out;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'reference',
            'tags' => ['ninja', 'ninjaone', 'rmm', 'overview', 'reference'],
            'read_only' => true,
            'requires_auth' => false,
            'risk_level' => 'safe',
        ];
    }
}
