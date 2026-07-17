<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet POST /TrackingObject/Create — Creates or updates tracking objects such as vehicles.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class TrackingObjectCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.tracking-object.create.POST';
    }

    public function getDescription(): string
    {
        return 'Creates or updates tracking objects such as vehicles.

REQUEST-Felder (`data`):
- trackingObjects: array<Api.v2.TrackingObject.ApiTrackingObject> — Liste der Fahrzeuge, die angelegt/bearbeitet werden sollen
    - trackingObjectId: string — When updating a TrackingObject: Has to be filled with the ID of the existing TrackingObject.
    - imei: string — This is the key of the tracking unit.
    - name: string — Name of the tracking object, defined by user.
    - vehicleApiID: string — A custom-ID to identify this vehicle in other API calls
    - licenseNumber: string — License number / License plate of vehicle.
    - type: integer:int32 — Type of tracking object.
    - profileId: string — Sets the profile used by tour planning.
    - notes: string
    - vin: string — Sets the vehicle identification number (VIN)
    - machineId: string — Sets the machine number
    - machineCostCenter: string — Sets the machine cost centre
    - dailyChargeRate: number:double — Sets the daily charge rate
    - leasingRate: number:double — Sets the leasing rate
    - insuranceRate: number:double — Sets the insurance costs
    - taxes: number:double — Sets the taxes
    - vehicleCategory: string — Sets the vehicle category
    - firstRegistration: string — Sets the vehicles first registration date
    - warrantyPeriod: string — Sets the warranty period
    - numberSeats: integer:int32 — Sets the number of seats
    - state: string — Sets the state
    - parkingPermit: string — Sets the parking permit
    - replaceGearboxMileage: integer:int32 — Sets the mileage at which the gearbox got replaced
    - replaceEngineMileage: integer:int32 — Sets the mileage at which the engine got replaced
    - regularDriver: string — Sets the vehicles regular driver
    - wheelbase: integer:int32 — Sets the vehicles wheelbase
    - emissionGroup: integer:int32 — Sets the vehicles emission group
    - targetOperatingTime: number:double — Sets the target operating time (h/d).
    - carpool: boolean — Sets information about the carpool
    - hasTrailer: boolean — Sets “Has Trailer” checkbox
    - hasHandsFree: boolean — Sets “Has hands-free kit” checkbox
    - hasWinterTires: boolean — Sets “Has winter tires” checkbox
    - hasCompanyLogo: boolean — Sets “Has company logo” checkbox
    - hasTourPlanningLicense: boolean — Sets “Tour planning license active” checkbox
    - breakRegulation: integer:int32 — Valid values are:
    - depotId: string — Defines the default value where a tour starts and ends whe
… (gekürzt, siehe Swagger)';
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
            $result = $svc->call($context->user, 'POST', '/TrackingObject/Create', $payload);

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
            'tags' => ['dedefleet', 'tracking-object'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
