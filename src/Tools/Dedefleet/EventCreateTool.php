<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet POST /Event/Create — Creates a new event.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class EventCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.event.create.POST';
    }

    public function getDescription(): string
    {
        return 'Creates a new event.

REQUEST-Felder (`data`):
- event: Api.v2.Event.Structures.EventEntry — See “Structure: event”
    - guid: string — Unique identifier (GUID) of this event.
    - name: string
    - active: boolean — If true, the event is activated, false it is deactivated and will not be checked.
    - receivers: array<string> — Those email addresses will be informed when this event is hit.
    - vehicles: array<string> — The vehicle API ID as set in DeDeFleet.
    - activeTime: Api.v2.Event.Structures.EventActiveTime — Zeiteinstellungen
    - eventType: integer:int32 — 0 – Position monitoring
    - positionMonitoring: Api.v2.Event.Structures.PositionMonitoring — This is ignored, if EventType is not 0.
    - vehicleApproachesCustomer: Api.v2.Event.Structures.VehicleApproachesCustomer — This is ignored, if EventType is not 1.
    - vehicleApproachesLocation: Api.v2.Event.Structures.VehicleApproachesLocation — This is ignored, if EventType is not 2.
    - signalChanges: Api.v2.Event.Structures.SignalChanges — This is ignored, if EventType is not 3.
    - temperatureControl: Api.v2.Event.Structures.TemperatureControl — This is ignored, if EventType is not 4.
    - fuelLoss: Api.v2.Event.Structures.FuelLoss — This is ignored, if EventType is not 5.
    - drivingSpeed: Api.v2.Event.Structures.DrivingSpeed — This is ignored, if EventType is not 6.
    - dataControl: Api.v2.Event.Structures.DataControl — This is ignored, if EventType is not 7.
    - notification: Api.v2.Event.Structures.Notification

Vollständige Feld-/Response-Details: https://ortung.dedefleet.de/swagger (Spec /swagger/data/api/2).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Request-Body (Felder siehe Tool-Description / Swagger).',
                    'additionalProperties' => true,
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen DedeFleet-Connection.',
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

        $payload = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->call($context->user, 'POST', '/Event/Create', $payload);

            return ToolResult::success($result);
        } catch (DedefleetApiException $e) {
            return ToolResult::error($e->getDedefleetErrorCode() ?? 'DEDEFLEET_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dedefleet', 'event'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
