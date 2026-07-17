<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet POST /VehicleProfile/Create — Creates a new vehicle profile.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class VehicleProfileCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.vehicle-profile.create.POST';
    }

    public function getDescription(): string
    {
        return 'Creates a new vehicle profile.

REQUEST-Felder (`data`):
- vehicleProfile: Api.v2.VehicleProfile.VehicleProfileEntry
    - guid: string:uuid — Unique identifier (GUID) of this vehicleprofile.
    - name: string
    - speedProfile: integer:int32
    - euroEmissionClass: integer:int32 — 0 = Others
    - axisWeightClass: integer:int32
    - height: number:double — In meters, max. 5
    - width: number:double — In meters, max. 5
    - length: number:double — In meters, max 20
    - skills: array<string>
    - payload: number:double — In kg
    - capacities: array<Api.v2.VehicleProfile.VehicleProfileCapacity>
    - optCostsPerKM: number:double — Max. 100
    - optCostsPerMin: number:double — Max. 100
    - optWaitingCostsPerMin: number:double — Max. 100
    - optCostsPerVehicle: number:double — Max. 100
    - totalWeight: number:double — Maximum total weight in kg
    - maxSpeed: number:double — Maximum speed in km/h

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
            $result = $svc->call($context->user, 'POST', '/VehicleProfile/Create', $payload);

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
            'tags' => ['dedefleet', 'vehicle-profile'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
