<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;
use Platform\Integrations\Tools\Dedefleet\Concerns\GuardsArguments;

/**
 * Tourenplanung Step 5 — POST /Order/Update: bestehenden Auftrag ändern.
 */
class UpdateOrderTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.dedefleet.order.PUT';
    }

    public function getDescription(): string
    {
        return <<<TXT
        Tourenplanung Step 5 — POST /Order/Update: aktualisiert einen bestehenden Auftrag.

        PFLICHT: data.orderGuid (GUID des Auftrags, aus Create-Response oder order-Liste).
        Übrige Felder identisch zu order.POST (type, order, location{…}, plannedDeliveryDate, workTime, weight,
        priority, items[], skills[], capacities[], visitTimeWindows[], documents[], tourGuid, contact, phone,
        notes, fixed, limit1/limit2 …). Sende die zu ändernden Felder — Feldsemantik wie bei Create.

        Für reines Verschieben zwischen Touren gibt es dedizierte Tools: order.assign.POST / order.unassign.POST.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Auftrags-Daten inkl. Pflichtfeld orderGuid. Beispiel: '
                        . '{"orderGuid":"<uuid>","priority":1,"weight":150}. Feldliste siehe Tool-Description / order.POST.',
                    'additionalProperties' => true,
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen DedeFleet-Connection.',
                ],
            ],
            'required' => ['data'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['data'])) {
            return $guard;
        }

        $data = $arguments['data'];
        if (!is_array($data) || empty($data['orderGuid'])) {
            return ToolResult::error('VALIDATION_ERROR', 'data.orderGuid (GUID des Auftrags) ist erforderlich.');
        }

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->updateOrder($context->user, $data);

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
            'tags' => ['dedefleet', 'order', 'update', 'tourenplanung', 'step-5'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
