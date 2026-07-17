<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet POST /Order/FindSlot — Determines fitting tour slots for an order proposal.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class OrderFindSlotTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.order.find-slot.POST';
    }

    public function getDescription(): string
    {
        return 'Determines fitting tour slots for an order proposal.

REQUEST-Felder (`data`):
- date: string — Date for which available slots should be searched.
- tours: Api.v2.Order.OrderFindSlotTour — Filters that limit which tours may be considered for the slot search.
    - specificTourGuids: array<string> — Optional list of specific tour GUIDs that should be considered.
    - bookableByCustomer: boolean — Restricts the result to tours that are marked as bookable by customers.
- order: Api.v2.Order.OrderFindSlotOrder — Order data used for slot calculation.
    - guid: string — GUID of an existing order.
    - location: Api.v2.Location — See "Structure Location"
    - workTime: number:double — Time in minutes a person needs to fulfill this order.
    - weight: number:double — Weight of goods in kg.
    - skills: array<string> — A list of skills which are necessary for transportation. The skills have to be created in DeDeFleet first.
    - capacities: array<Api.v2.Order.OrderCapacity> — A list of individual defined capacities.
    - visitTimeWindows: array<Api.v2.VisitTimeWindow> — Optional visit time windows that must be respected during slot calculation.

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
            $result = $svc->call($context->user, 'POST', '/Order/FindSlot', $payload);

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
            'category' => 'query',
            'tags' => ['dedefleet', 'order'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
