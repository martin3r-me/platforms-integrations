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
 * Verändernde Aktionen auf einem Gerät.
 *
 * Jede Aktion wirkt sofort auf ein echtes Endgerät und verlangt deshalb
 * "confirm": true. Ein Neustart trennt laufende Sitzungen, ein Skriptlauf
 * führt Code auf dem Rechner aus — beides ist nicht rücknehmbar.
 */
class DeviceActionTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /** action => [Methode, Pfad, braucht Body] */
    private const ACTIONS = [
        'reboot' => ['POST', '/v2/device/{id}/reboot/{mode}', false],
        'schedule-maintenance' => ['PUT', '/v2/device/{id}/maintenance', true],
        'cancel-maintenance' => ['DELETE', '/v2/device/{id}/maintenance', false],
        'run-script' => ['POST', '/v2/device/{id}/script/run', true],
        'os-patch-scan' => ['POST', '/v2/device/{id}/patch/os/scan', false],
        'os-patch-apply' => ['POST', '/v2/device/{id}/patch/os/apply', false],
        'software-patch-scan' => ['POST', '/v2/device/{id}/patch/software/scan', false],
        'software-patch-apply' => ['POST', '/v2/device/{id}/patch/software/apply', false],
        'decommission' => ['POST', '/v2/device/{id}/decommission', false],
        'reset-policy-overrides' => ['DELETE', '/v2/device/{id}/policy/overrides', false],
    ];

    private const REBOOT_MODES = ['NORMAL', 'FORCED'];

    public function getName(): string
    {
        return 'integrations.ninja.device.action';
    }

    public function getDescription(): string
    {
        return 'SCHREIBEND: Führt eine Aktion auf einem NinjaOne-Gerät aus. '
            . 'Verfügbar: ' . implode(', ', array_keys(self::ACTIONS)) . '. '
            . 'Alle Aktionen verlangen "confirm": true und wirken sofort auf das echte Gerät. '
            . 'reboot benötigt zusätzlich mode (NORMAL oder FORCED); run-script und '
            . 'schedule-maintenance erwarten einen Body — dessen Felder nennt '
            . 'integrations.ninja.overview mit search="script" bzw. "maintenance". '
            . 'decommission entfernt das Gerät aus der Verwaltung und ist nicht umkehrbar.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Geräte-ID aus NinjaOne.'],
                'action' => [
                    'type' => 'string',
                    'enum' => array_keys(self::ACTIONS),
                    'description' => 'Welche Aktion ausgeführt wird.',
                ],
                'mode' => [
                    'type' => 'string',
                    'enum' => self::REBOOT_MODES,
                    'description' => 'Nur bei action="reboot": NORMAL (geordnet) oder FORCED (erzwungen).',
                ],
                'body' => [
                    'type' => 'object',
                    'description' => 'Request-Body für run-script (z.B. {"type":"SCRIPT","id":123,"parameters":"…"}) '
                        . 'oder schedule-maintenance (Start, Ende, pausierte Funktionen).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Pflicht. Ohne true wird die Aktion abgelehnt.',
                ],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: bestimmte NinjaOne-Verbindung.'],
            ],
            'required' => ['id', 'action', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['id', 'action'])) {
            return $error;
        }

        $action = (string) $arguments['action'];

        if (!array_key_exists($action, self::ACTIONS)) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf('Unbekannte Aktion "%s". Erlaubt: %s.', $action, implode(', ', array_keys(self::ACTIONS)))
            );
        }

        if ($error = $this->guardConfirmed($arguments, "Die Aktion \"{$action}\"")) {
            return $error;
        }

        [$method, $path, $needsBody] = self::ACTIONS[$action];
        $pathParams = ['id' => (int) $arguments['id']];

        if ($action === 'reboot') {
            $mode = mb_strtoupper(trim((string) ($arguments['mode'] ?? '')));

            if (!in_array($mode, self::REBOOT_MODES, true)) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    'action="reboot" benötigt mode="NORMAL" oder mode="FORCED".'
                );
            }

            $pathParams['mode'] = $mode;
        }

        $body = is_array($arguments['body'] ?? null) ? $arguments['body'] : [];

        if ($needsBody && $body === []) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf('Die Aktion "%s" erwartet einen "body". Felder siehe integrations.ninja.overview.', $action)
            );
        }

        try {
            $result = app(NinjaApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->call($context->user, $method, $path, $pathParams, [], $body);

            return ToolResult::success([
                'action' => $action,
                'device_id' => (int) $arguments['id'],
                'request' => trim("{$method} {$path}"),
                'result' => $result,
            ]);
        } catch (NinjaApiException $e) {
            return ToolResult::error($e->getNinjaErrorCode() ?? 'NINJA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['ninja', 'ninjaone', 'rmm', 'device', 'action', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}
