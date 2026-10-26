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
 * Aktive Alarme (ausgelöste Bedingungen) abrufen.
 */
class ListAlertsTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.ninja.alerts.GET';
    }

    public function getDescription(): string
    {
        return 'Listet die aktiven Alarme aus NinjaOne (ausgelöste Bedingungen). Ohne Angaben flottenweit, '
            . 'mit device_id nur die eines Geräts. Eingrenzen über "df" (Gerätefilter), z.B. "org = 5". '
            . 'Zum Quittieren eines Alarms dient integrations.ninja.call mit DELETE /v2/alert/{uid} '
            . 'beziehungsweise POST /v2/alert/{uid}/reset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'device_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Alarme dieses Geräts (GET /v2/device/{id}/alerts).',
                ],
                'df' => ['type' => 'string', 'description' => 'Gerätefilter, z.B. "org = 5" oder "status eq APPROVED".'],
                'lang' => ['type' => 'string', 'description' => 'Sprache der Meldungstexte, z.B. "de".'],
                'tz' => ['type' => 'string', 'description' => 'Zeitzone für Zeitangaben, z.B. "Europe/Berlin".'],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Nur diese Felder zurückgeben.',
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

        $deviceId = $arguments['device_id'] ?? null;

        $path = $deviceId ? '/v2/device/{id}/alerts' : '/v2/alerts';
        $pathParams = $deviceId ? ['id' => (int) $deviceId] : [];

        $query = [];
        foreach (['df', 'lang', 'tz'] as $key) {
            if (!empty($arguments[$key])) {
                $query[$key] = $arguments[$key];
            }
        }

        // Der geräte-bezogene Endpunkt kennt keinen Gerätefilter.
        if ($deviceId) {
            unset($query['df']);
        }

        try {
            $result = app(NinjaApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->call($context->user, 'GET', $path, $pathParams, $query);

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
            'tags' => ['ninja', 'ninjaone', 'rmm', 'alerts', 'monitoring'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
