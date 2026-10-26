<?php

namespace Platform\Integrations\Tools\Ninja;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\NinjaApiException;
use Platform\Integrations\Services\NinjaApiService;
use Platform\Integrations\Tools\Ninja\Concerns\GuardsArguments;

/**
 * Geräte auflisten — global, detailliert oder je Organisation.
 */
class ListDevicesTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.ninja.devices.GET';
    }

    public function getDescription(): string
    {
        return 'Listet Geräte aus NinjaOne. Ohne weitere Angaben die Kurzform (GET /v2/devices); '
            . 'mit detailed=true die ausführliche Variante inkl. Betriebssystem und letzter Kontaktzeit; '
            . 'mit organization_id nur die Geräte einer Organisation. '
            . 'Filtern über "df" (device filter), z.B. "status eq APPROVED", "org = 5", '
            . '"class in (WINDOWS_WORKSTATION,WINDOWS_SERVER)", "offline". '
            . 'Für große Bestände "fields" nutzen, sonst wird die Antwort sehr groß.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'detailed' => [
                    'type' => 'boolean',
                    'description' => 'true ruft /v2/devices-detailed auf — deutlich mehr Felder je Gerät.',
                ],
                'organization_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Geräte dieser Organisation (GET /v2/organization/{id}/devices).',
                ],
                'df' => [
                    'type' => 'string',
                    'description' => 'Gerätefilter von NinjaOne, z.B. "status eq APPROVED" oder "org = 5".',
                ],
                'pageSize' => [
                    'type' => 'integer',
                    'description' => 'Einträge pro Seite (max. 1000).',
                    'minimum' => 1,
                ],
                'after' => [
                    'type' => 'integer',
                    'description' => 'Cursor: letzte Geräte-ID der vorherigen Seite.',
                ],
                'all_pages' => [
                    'type' => 'boolean',
                    'description' => 'Alle Seiten zusammenführen (bis 50 Seiten). Bei großen Beständen mit "fields" kombinieren.',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Nur diese Felder zurückgeben, z.B. ["id","systemName","organizationId"].',
                ],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: bestimmte NinjaOne-Verbindung.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $orgId = $arguments['organization_id'] ?? null;
        $detailed = (bool) ($arguments['detailed'] ?? false);

        if ($orgId && $detailed) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'organization_id und detailed lassen sich nicht kombinieren — die API bietet für '
                . 'Organisationen keine Detail-Variante. Stattdessen df="org = ' . (int) $orgId . '" mit detailed=true nutzen.'
            );
        }

        [$path, $pathParams] = match (true) {
            (bool) $orgId => ['/v2/organization/{id}/devices', ['id' => (int) $orgId]],
            $detailed => ['/v2/devices-detailed', []],
            default => ['/v2/devices', []],
        };

        $query = $this->listQuery($arguments);

        try {
            $service = app(NinjaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = ($arguments['all_pages'] ?? false)
                ? $service->fetchAll(
                    $context->user,
                    $path,
                    $pathParams,
                    $query,
                    min((int) ($arguments['pageSize'] ?? 500), self::MAX_PAGE_SIZE)
                )
                : $service->call($context->user, 'GET', $path, $pathParams, $query);

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
            'tags' => ['ninja', 'ninjaone', 'rmm', 'devices', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
