<?php

namespace Platform\Integrations\Tools\Cisbox;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\CisboxApiException;
use Platform\Integrations\Services\CisboxApiService;
use Platform\Integrations\Tools\Cisbox\Concerns\GuardsArguments;

/**
 * Lieferungen auflisten, einzeln abrufen oder ihre Positionen laden.
 */
class ListDeliveriesTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.cisbox.deliveries.GET';
    }

    public function getDescription(): string
    {
        return 'Lieferungen (Lieferscheine) aus cisbox. Ohne Angabe die Liste (GET /deliveries) mit '
            . 'Liefer- und Bestellnummer, Beträgen, Abweichung zur Rechnung und zugeordneter '
            . 'Rechnungs-Barcodenummer; mit "deliveryId" die einzelne Lieferung, auf Wunsch samt '
            . 'Positionen. Der Weg, um Rechnungen gegen den tatsächlichen Wareneingang zu prüfen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge([
                'deliveryId' => [
                    'type' => 'integer',
                    'description' => 'ID der Lieferung. Ohne Angabe wird die Liste geliefert.',
                ],
                'include_positions' => [
                    'type' => 'boolean',
                    'description' => 'Nur zusammen mit "deliveryId": zusätzlich die Positionen laden '
                        . '(GET /deliveries/{deliveryId}/positions) — Artikel, Mengen, Preise und Kontierung.',
                ],
                'updatedSince' => [
                    'type' => 'string',
                    'description' => 'Nur seit diesem Zeitpunkt geänderte Lieferungen (ISO-8601). '
                        . 'Gilt nur für die Liste.',
                ],
            ], $this->paginationSchema()),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardSearch($arguments)) {
            return $error;
        }

        $deliveryId = $arguments['deliveryId'] ?? null;

        if (($arguments['include_positions'] ?? false) && $deliveryId === null) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'Positionen gibt es nur je Lieferung — bitte zusätzlich "deliveryId" angeben.'
            );
        }

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);

            if ($deliveryId === null) {
                $query = $this->listQuery($arguments, ['updatedSince']);

                return ToolResult::success(
                    $this->runList($service, $context->user, '/deliveries', [], $query, $arguments)
                );
            }

            $pathParams = ['deliveryId' => (int) $deliveryId];
            $response = $service->call($context->user, 'GET', '/deliveries/{deliveryId}', $pathParams);
            $delivery = $service->single($response);

            if ($delivery === null) {
                return ToolResult::error('NOT_FOUND', "Keine Lieferung mit der ID {$deliveryId} gefunden.");
            }

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $projected = CisboxApiService::projectFields($delivery, $arguments['fields']);
                $delivery = is_array($projected) ? $projected : $delivery;
            }

            $result = [
                'data' => $delivery,
                'request_id' => $response['request_id'] ?? null,
            ];

            if ($arguments['include_positions'] ?? false) {
                $result['positions'] = $service->items($service->call(
                    $context->user,
                    'GET',
                    '/deliveries/{deliveryId}/positions',
                    $pathParams
                ));
            }

            return ToolResult::success($result);
        } catch (CisboxApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CISBOX_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['cisbox', 'lieferungen', 'lieferschein', 'wareneingang', 'positionen'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
