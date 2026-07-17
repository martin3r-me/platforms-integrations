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
 * Tourenplanung Step 2 — POST /Tour/Create: neue Tour anlegen.
 * Die Response enthält die Tour-GUID, die für Order-Zuweisung (Step 3) gebraucht wird.
 */
class CreateTourTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.dedefleet.tour.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
        Tourenplanung Step 2 — POST /Tour/Create: legt eine Tour an. Die Antwort enthält die Tour-GUID,
        die du in Step 3 (order.assign.POST bzw. order.POST mit tourGuid) verwendest.

        HÄUFIGE data-Felder:
        - tour (string): Name/Bezeichnung der Tour
        - vehicleApiID (string): Fahrzeug-API-ID (aus DedeFleet), driver (string): Mitarbeiter-Nr, trailer (string): Anhänger-ID
        - departure (object): { date (ISO yyyy-MM-dd, auto-konvertiert), time "HH:MM", location {…wie bei order.location…} }
        - return (object): { latestReturnTime "HH:MM", toDepot (bool; true = Depot statt expliziter Location), location {…} }
        - status (int): 0=Planning, 1=Released, 2=Completed
        - skills[] (string; müssen in DedeFleet existieren)
        - notes, forceVisibilityTourStartOrder (bool), forceVisibilityTourEndOrder (bool), appointmentBookingByCustomer (bool), tourEndQueries[] (string)

        location-Struktur (in departure/return): { type: 0=Standort-Nr,1=Kunden-Nr,2=Adresse,3=lat/lng,4=Mitarbeiter-Nr; id; name; street; postal; city; country (ISO2); latitude; longitude }.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Tour-Daten. Beispiel: {"tour":"Tour Nord","driver":"M-12","departure":'
                        . '{"date":"2026-07-18","time":"07:00","location":{"type":0,"id":"DEPOT-1"}},'
                        . '"return":{"toDepot":true},"status":0}. Feldliste siehe Tool-Description.',
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
            $result = $svc->createTour($context->user, $arguments['data']);

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
            'tags' => ['dedefleet', 'tour', 'create', 'tourenplanung', 'step-2'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
