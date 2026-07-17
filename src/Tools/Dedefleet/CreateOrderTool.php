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
 * Tourenplanung Step 1 — POST /Order/Create: neuen Auftrag anlegen.
 */
class CreateOrderTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.dedefleet.order.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
        Tourenplanung Step 1 — POST /Order/Create: legt einen Auftrag an. Der Auftrag kann direkt einer
        Tour zugewiesen werden (data.tourGuid). Alle Felder unter `data`.

        PFLICHT/WICHTIG:
        - type (int): 0 = Delivery (Lieferung), 1 = Pickup (Abholung)
        - order (string): Auftragsnummer
        - location (object): Zielort — {
            type: 0=bekannte Standort-Nr, 1=Kunden-Nr, 2=Adressfelder, 3=lat/lng, 4=Mitarbeiter-Nr;
            id (je nach type), name, street, postal, city, country (ISO-3166 ALPHA-2), latitude, longitude }

        DATUM: ISO 8601 angeben (yyyy-MM-dd bzw. yyyy-MM-ddTHH:mm[:ss]) — wird automatisch ins DedeFleet-Format konvertiert. Reine Uhrzeiten (Zeitfenster) als HH:MM.

        HÄUFIGE data-Felder (gruppiert):
        - Zeit/Menge: plannedDeliveryDate (ISO, z.B. "2026-07-18T09:00"), workTime (Minuten), weight (kg), priority (1–9, 1=höchste), fixed (bool)
        - Anzeige/Kontakt: driverMessage (HTML erlaubt, kompakt), appTitle, notes (intern, nicht für Fahrer), contact, phone, deliveryConditions, deliveryType, delivery (Lieferschein-Nr)
        - items[]: { item, description, type (0=NoReturnable,1=UniqueReturnable,2=GroupedReturnable), status (0–3), quantity (bei type!=2 genau 1), group }
        - skills[] (string; müssen in DedeFleet existieren), capacities[]: { key, value }
        - visitTimeWindows[]: { day (0=Mo … 6=So), startTime "HH:MM", endTime "HH:MM" }
        - documents[]: { name, data (Base64) }
        - Direkt-Zuweisung: tourGuid (GUID einer zuvor erstellten Tour), startDateTime (ISO mit Sekunden, z.B. "2026-07-18T09:00:00", nur mit tourGuid; chronologisch)
        - Verspätung: limit1 (Min), limit2 (Min, E-Mail), limit2Receiver (";"-getrennt)
        - formdata[] (Formularfelder für die Fahrer-App)

        Reihenfolge im Workflow: erst Tour anlegen (tour.POST) → GUID → hier als tourGuid setzen, ODER Auftrag ohne Tour anlegen und später via order.assign.POST zuweisen.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Auftrags-Daten. Mind. type + location. Beispiel: '
                        . '{"type":0,"order":"A-1001","location":{"type":1,"id":"K-500"},'
                        . '"plannedDeliveryDate":"2026-07-18T09:00","weight":120,"workTime":10}. '
                        . 'Vollständige Feldliste siehe Tool-Description.',
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

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->createOrder($context->user, $arguments['data']);

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
            'tags' => ['dedefleet', 'order', 'create', 'tourenplanung', 'step-1'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
