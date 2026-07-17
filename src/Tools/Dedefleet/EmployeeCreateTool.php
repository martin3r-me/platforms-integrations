<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet POST /Employee/Create — Creates or updates one or more employees.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class EmployeeCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.employee.create.POST';
    }

    public function getDescription(): string
    {
        return 'Creates or updates one or more employees.

REQUEST-Felder (`data`):
- employees: array<Api.v2.Employee.ApiEmployee>
    - employeeNumber: string — (PK) The employees ID number
    - firstName: string — The employees first name
    - lastName: string — The employees last name
    - password: string — The password used for login to the driver app.
    - location: Api.v2.Location — See “Structure: Location”
    - email: string — The employees email address
    - phoneNumber: string — The employees phone number
    - workTimeStart: string — Usual working time start (HH:MM)
    - workTimeEnd: string — Usual working time end (HH:MM)
    - timeRecordingSource: integer:int32 — Sets the propriate source for time recording.
    - extraTimePercent: number:double — Defines how much extra time the employee is granted per order in the tour planning.
    - extraTimeMinutes: number:double — Adds extra minutes to a planned length of stay of an order (‘order.worktime’).
    - workingDays: array<integer:int32> — Defines on which days the employee gets listed as available for tour planning.
    - vehicleApiID: string — The vehicle API ID as set in DeDeFleet.
    - hasTourPlanningLicense: boolean — Sets “Display in Tour planning” checkbox

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
            $result = $svc->call($context->user, 'POST', '/Employee/Create', $payload);

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
            'tags' => ['dedefleet', 'employee'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
