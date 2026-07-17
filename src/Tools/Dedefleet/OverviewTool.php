<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;

/**
 * Übersichts-/Referenz-Tool für die DedeFleet-Integration.
 * Dokumentiert Base-URL, Auth, RPC-Muster und die verfügbaren Ressourcen/Aktionen —
 * Einstieg, um das richtige Tool bzw. den richtigen call()-Endpunkt zu wählen.
 */
class OverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.overview';
    }

    public function getDescription(): string
    {
        return 'Übersicht der DedeFleet-Integration (Ortung & Tourenplanung): Base-URL, Auth, RPC-Muster '
            . 'und alle Ressourcen/Aktionen. Einstieg, um das passende Tool oder den call()-Endpunkt zu finden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::success([
            'base_url' => 'https://ortung.dedefleet.de/data/api/2',
            'auth' => 'Bearer-Dauertoken (Authorization: Bearer <api_key>). Token im Portal unter '
                . 'Systemeinstellungen → Benutzer (Typ "Api Vollzugriff", "Permanent").',
            'style' => 'RPC — /{Resource}/{Action}. List-Endpunkte teils GET, teils POST (Filter im Body); '
                . 'schreibende Aktionen (Create/Update/Delete/Assign/…) sind POST.',
            'generic_tool' => 'integrations.dedefleet.call — method + endpoint (z.B. "Tour/Optimize") + payload für '
                . 'jede Ressource/Aktion, die kein dediziertes Tool hat.',
            'convenience_tools' => [
                'integrations.dedefleet.customers.GET',
                'integrations.dedefleet.locations.GET',
                'integrations.dedefleet.employees.GET',
                'integrations.dedefleet.vehicle-profiles.GET',
                'integrations.dedefleet.orders-unassigned.GET',
                'integrations.dedefleet.tours.GET',
                'integrations.dedefleet.tracking.GET',
            ],
            'tourenplanung_workflow' => [
                'step_1_auftrag_anlegen' => 'integrations.dedefleet.order.POST',
                'step_2_tour_anlegen' => 'integrations.dedefleet.tour.POST (oder .tour.from-template.POST)',
                'step_3_zuweisen' => 'integrations.dedefleet.order.assign.POST (einzeln) / .order.assign-bulk.POST (mehrere)',
                'step_4_tour_bearbeiten_freigeben' => 'integrations.dedefleet.tour.reorder.POST, .tour.optimize.POST, '
                    . '.tour.change-status.POST (0=Planning,1=Released,2=Completed), .tour.lock.POST',
                'step_5_auftrag_aendern' => 'integrations.dedefleet.order.PUT (Update), .order.unassign.POST, .order.DELETE',
                'hinweis' => 'GUIDs verketten: tour.POST liefert Tour-GUID → order.POST/assign nutzt sie; '
                    . 'order.POST liefert Order-GUID → update/assign/unassign/delete nutzen sie.',
            ],
            'resources' => [
                'Customer' => ['GET List', 'GET DeleteAll', 'POST Create', 'POST Delete', 'POST AddDocument', 'POST DeleteDocument'],
                'Order' => ['GET ListUnassigned', 'POST Get', 'POST Create', 'POST Update', 'POST Delete', 'POST Assign', 'POST AssignBulk', 'POST Unassign', 'POST FindSlot', 'POST GetStatus', 'POST GetStatusBulk', 'POST ListStatus'],
                'Tour' => ['GET ListTemplates', 'POST List', 'POST Get', 'POST GetBulk', 'POST Create', 'POST CreateFromTemplate', 'POST Delete', 'POST Reorder', 'POST Optimize', 'POST OptimizeMany', 'POST OptimizeTourDepartureTime', 'POST Calculate', 'POST CalculateToll', 'POST ChangeStatus', 'POST SetLockState'],
                'Employee' => ['GET List', 'GET ListDARP', 'POST Create', 'POST AssignToken', 'POST WorkTimeStart', 'POST WorkTimeStop'],
                'Location' => ['GET List', 'POST Create', 'POST Update', 'POST Delete'],
                'VehicleProfile' => ['GET List', 'POST Create', 'POST Update', 'POST Delete'],
                'TrackingObject' => ['GET List', 'GET ListCurrentData', 'POST Create', 'POST SendMsg', 'POST SetMileage'],
                'TrailerObject' => ['GET ListCurrentData'],
                'Event' => ['GET List', 'POST Create', 'POST Update', 'POST Delete'],
                'Item' => ['POST GetStatus', 'POST ListStatus'],
                'DriveBook' => ['POST List', 'POST Modify'],
                'Address' => ['POST Search'],
                'Calculate' => ['POST DistanceAndTime'],
                'Archive' => ['POST Import'],
                'Monitor' => ['POST SetData'],
                'User' => ['POST Login', 'GET Logout', 'POST Notify'],
            ],
            'note' => 'Es gibt API v1 (55 Endpunkte) und v2 (68 Endpunkte). Diese Integration nutzt v2. '
                . 'Vollständige Feld-/Schemadetails: https://ortung.dedefleet.de/swagger '
                . '(Spec: /swagger/data/api/2). Doku/Workflows: https://wiki.dedefleet.de/books/tourenplanung.',
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['dedefleet', 'overview', 'reference'],
            'read_only' => true,
            'requires_auth' => false,
            'risk_level' => 'safe',
        ];
    }
}
