<?php

namespace Platform\Integrations\Tools\MeinConnect;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\MeinConnectApiException;
use Platform\Integrations\Services\MeinConnectApiService;

/**
 * Gerätestatus aus einer MeinConnect-Gastfreigabe (CoolCooking-Boxen), read-only.
 */
class ListDevicesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.meinconnect.devices.GET';
    }

    public function getDescription(): string
    {
        return 'GET MeinConnect-Geräte — Status aller in der Gastfreigabe sichtbaren CoolCooking-Boxen: '
            . 'Modus, Temperatur und Kerntemperatur (Ist/Soll), Tür, Verbindung, Fehler, letztes Update, '
            . 'heutige Heizzeit. Werte sind höchstens ~30 s alt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen MeinConnect-Connection.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $id = isset($arguments['connection_id']) ? (int) $arguments['connection_id'] : null;

            return ToolResult::success(app(MeinConnectApiService::class)->forConnection($id)->devices($context->user));
        } catch (MeinConnectApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'MEINCONNECT_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['meinconnect', 'coolcooking', 'devices', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
