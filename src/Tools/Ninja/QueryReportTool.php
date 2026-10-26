<?php

namespace Platform\Integrations\Tools\Ninja;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\NinjaApiException;
use Platform\Integrations\Services\NinjaApiService;
use Platform\Integrations\Services\NinjaResource;
use Platform\Integrations\Tools\Ninja\Concerns\GuardsArguments;

/**
 * Flottenweite Auswertungen über die /v2/queries/*-Endpunkte.
 *
 * Diese Endpunkte liefern eine Kennzahl über alle Geräte hinweg, statt sie
 * Gerät für Gerät abfragen zu müssen — für Inventar- und Patch-Auswertungen
 * der übliche Weg.
 */
class QueryReportTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /** Report-Name (= Pfadsegment hinter /v2/queries/) ohne führenden Slash. */
    private const REPORTS = [
        'antivirus-status', 'antivirus-threats', 'backup/usage', 'computer-systems',
        'custom-fields', 'custom-fields-detailed', 'device-health', 'disks',
        'logged-on-users', 'network-interfaces', 'operating-systems', 'os-patch-installs',
        'os-patches', 'policy-overrides', 'processors', 'raid-controllers', 'raid-drives',
        'scoped-custom-fields', 'scoped-custom-fields-detailed', 'software',
        'software-patch-installs', 'software-patches', 'volumes', 'windows-services',
    ];

    public function getName(): string
    {
        return 'integrations.ninja.query.GET';
    }

    public function getDescription(): string
    {
        return 'Flottenweite Auswertungen aus NinjaOne (GET /v2/queries/*) — eine Kennzahl über alle '
            . 'Geräte hinweg statt Gerät für Gerät. Verfügbar: ' . implode(', ', self::REPORTS) . '. '
            . 'Eingrenzen mit "df" (Gerätefilter) und den reportspezifischen Parametern, die '
            . 'integrations.ninja.overview mit tag="queries" je Report nennt. '
            . 'Beispiele: report="device-health" für den Gesundheitsstatus, report="os-patches" mit '
            . 'status/severity für offene Betriebssystem-Updates, report="software" für das Inventar.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'report' => [
                    'type' => 'string',
                    'enum' => self::REPORTS,
                    'description' => 'Welche Auswertung abgerufen wird.',
                ],
                'df' => [
                    'type' => 'string',
                    'description' => 'Gerätefilter, z.B. "org = 5" oder "class in (WINDOWS_SERVER)".',
                ],
                'params' => [
                    'type' => 'object',
                    'description' => 'Weitere Query-Parameter dieses Reports, z.B. {"status":"FAILED"} oder '
                        . '{"installedAfter":"2026-01-01"}. Unbekannte Namen werden abgelehnt statt ignoriert.',
                ],
                'pageSize' => ['type' => 'integer', 'description' => 'Einträge pro Seite (max. 1000).', 'minimum' => 1],
                'cursor' => ['type' => 'string', 'description' => 'Cursor der vorherigen Seite.'],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Nur diese Felder zurückgeben.',
                ],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: bestimmte NinjaOne-Verbindung.'],
            ],
            'required' => ['report'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['report'])) {
            return $error;
        }

        $report = trim((string) $arguments['report'], '/ ');

        if (!in_array($report, self::REPORTS, true)) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf('Unbekannter report "%s". Verfügbar: %s.', $report, implode(', ', self::REPORTS))
            );
        }

        $path = '/v2/queries/' . $report;
        $operation = NinjaResource::operation('GET', $path);

        $query = $this->listQuery($arguments);
        foreach ((array) ($arguments['params'] ?? []) as $key => $value) {
            if ($value !== null && $value !== '') {
                $query[$key] = $value;
            }
        }

        // Gegen den Katalog prüfen — NinjaOne ignoriert unbekannte Parameter
        // kommentarlos und lieferte sonst ein ungefiltertes Ergebnis zurück.
        $unknown = array_diff(array_keys($query), $operation['query'] ?? []);
        if ($unknown !== []) {
            return ToolResult::error(
                'UNKNOWN_QUERY_PARAMETER',
                sprintf(
                    'Unbekannte(r) Parameter für report "%s": %s. Erlaubt sind: %s.',
                    $report,
                    implode(', ', $unknown),
                    implode(', ', $operation['query'] ?? [])
                )
            );
        }

        try {
            $result = app(NinjaApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->call($context->user, 'GET', $path, [], $query);

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = NinjaApiService::projectFields($result, $arguments['fields']);
            }

            return ToolResult::success($result);
        } catch (NinjaApiException $e) {
            return ToolResult::error($e->getNinjaErrorCode() ?? 'NINJA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['ninja', 'ninjaone', 'rmm', 'queries', 'report', 'inventory'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
