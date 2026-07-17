<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet POST /Order/ListStatus — Returns order status changes within a given time range.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class OrderListStatusTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.order.list-status.POST';
    }

    public function getDescription(): string
    {
        return 'POST /Order/ListStatus — Auftrags-Statuswechsel + Rückmeldungen in einem Zeitraum ("Was ist gelaufen?").

REQUEST-Felder (`data`):
- start: string [REQUIRED] — Beginn des Intervalls (z.B. "17.07.2026 00:00").
- end: string [REQUIRED] — Ende des Intervalls (z.B. "17.07.2026 23:59").

RESPONSE: statusList[] je Auftrag mit tourGuid, orderGuid, orderState
(0=Open, 1=Read, 2=Active, 3=Done, 4=Deleted, 5=In Navigation), tourArrival, eta (aktuelle Ankunftsprognose)
sowie formdata[] = im Fahrer-App erfasste Rückmeldungen/Nachweise (Werte, Unterschrift/Foto via file_Data).

Für den Tages-Gesamtblick pro Tour ist tours.GET (start/end) meist praktischer; dieses Tool ist ideal für
Statusverläufe/Rückmeldungen über alle Aufträge hinweg. Vollständige Details: https://ortung.dedefleet.de/swagger.';
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
            $result = $svc->call($context->user, 'POST', '/Order/ListStatus', $payload);

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
