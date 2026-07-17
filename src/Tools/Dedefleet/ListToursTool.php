<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;
use Platform\Integrations\Support\FieldProjection;

/**
 * POST /Tour/List — Touren eines Zeitraums inkl. Fahrer, Aufträge & Status.
 * Das zentrale Dispo-Read-Tool: "Wer fährt heute?" und "Ist alles gelaufen?".
 */
class ListToursTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.tours.GET';
    }

    public function getDescription(): string
    {
        return <<<TXT
        POST /Tour/List — liefert alle Touren eines Zeitraums (start/end) mit Fahrer, zugewiesenen Aufträgen und Status.
        DAS Dispo-Read-Tool für operative Fragen:

        - "WER FÄHRT HEUTE?" → start/end auf heute setzen; je Tour: driverName, driver (Personalnr.), vehicleApiID,
          departure {date, time}, return.calculatedReturnTime (voraussichtliche Rückkehr).
        - "IST ALLES GELAUFEN?" → je Tour status (0=Planning, 1=Released, 2=Completed) und je Auftrag orders[].orderStatus:
          0=Open, 1=Read, 2=Active, 3=Done, 4=Deleted, 5=In Navigation. Alles orderStatus=3 ⇒ Tour erledigt.
          metrics: distancePlanned vs distanceDriven, actualDuration, fuel. Pro Stopp: tourArrival, eta, waitingTime.

        Parameter start/end (Abfragezeitraum) als ISO 8601 (yyyy-MM-dd bzw. yyyy-MM-ddTHH:mm) — wird automatisch
        ins DedeFleet-Format konvertiert. Für "heute" Tagesgrenzen setzen. Zusätzliche Felder via `params`.

        Ergänzend: order.list-status.GET (Statuswechsel + Rückmeldungen/Nachweise als formdata), tracking.GET (Live-GPS).
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'start' => [
                    'type' => 'string',
                    'description' => 'Beginn des Abfragezeitraums als ISO 8601, z.B. "2026-07-17" oder "2026-07-17T00:00". Für "heute" den heutigen Tag.',
                ],
                'end' => [
                    'type' => 'string',
                    'description' => 'Ende des Abfragezeitraums als ISO 8601, z.B. "2026-07-17T23:59".',
                ],
                'params' => [
                    'type' => 'object',
                    'description' => 'Optionale zusätzliche Filter (siehe Swagger /swagger/data/api/2). Wird mit start/end gemerged.',
                ],
                'fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte, z.B. "customer.customerNumber"). Reduziert die Antwortgröße drastisch.'],
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

        $body = is_array($arguments['params'] ?? null) ? $arguments['params'] : [];
        if (!empty($arguments['start'])) {
            $body['start'] = $arguments['start'];
        }
        if (!empty($arguments['end'])) {
            $body['end'] = $arguments['end'];
        }

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->listTours($context->user, $body);

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = FieldProjection::apply($result, $arguments['fields']);
            }

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
            'tags' => ['dedefleet', 'tours', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
