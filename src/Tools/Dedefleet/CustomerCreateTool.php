<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet POST /Customer/Create — Creates or updates one or more customers.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class CustomerCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.customer.create.POST';
    }

    public function getDescription(): string
    {
        return 'Creates or updates one or more customers.

REQUEST-Felder (`data`):
- customers: array<Api.v2.Customer.Customer> — Array of customers
    - customerNumber: string — Primary key and unique customer number.
    - name: string — Name of the customer or company.
    - location: Api.v2.Location — Customer address and geographic location.
    - radius: integer:int32 — Optional search radius used for events or reports.
    - salesVolume: number:double — Optional information about the customer\'s sales volume.
    - contact: string — Name of the contact person.
    - phoneNumber: string — Phone number of the customer.
    - fax: string — Fax number of the customer.
    - email: string — Email address of the customer.
    - website: string — Website URL of the customer.
    - customerClass: string — Customer class used for grouping.
    - notes: string — Internal notes stored with the customer.
    - visitTimeWindows: array<Api.v2.VisitTimeWindow> — Visit time windows defined for the customer.
    - shipmentTracking: boolean — Indicates whether shipment tracking is enabled for the customer.
    - archived: boolean — Indicates whether the customer record is archived.

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
            $result = $svc->call($context->user, 'POST', '/Customer/Create', $payload);

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
            'tags' => ['dedefleet', 'customer'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
