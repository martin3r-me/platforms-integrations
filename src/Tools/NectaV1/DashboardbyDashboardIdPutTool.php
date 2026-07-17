<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — PUT /api/v1/{tenantId}/dashboard/{dashboardId}
 * Dashboard aktualisieren
 */
class DashboardbyDashboardIdPutTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = [];

    public function getName(): string
    {
        return 'integrations.necta.v1.dashboard.by-dashboard-id.PUT';
    }

    public function getDescription(): string
    {
        return 'Dashboard aktualisieren
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Pfad-Parameter:
- dashboardId [REQUIRED]

Body (`data`):
- id: integer:int32 — Eindeutige Kennung für das Dashboard
- tenantId: integer:int32 — Mandanten-ID
- name: string — Name des Dashboards
- icon: string — Symbol, das das Dashboard repräsentiert
- description: string — Beschreibung des Dashboards
- createdBy: string — Benutzer, der das Dashboard erstellt hat
- createdAt: string:date-time — Zeitstempel, wann das Dashboard erstellt wurde
- updatedBy: string — Benutzer, der das Dashboard zuletzt aktualisiert hat (nullable)
- updatedAt: string:date-time — Zeitstempel, wann das Dashboard zuletzt aktualisiert wurde (nullable)
- widgets: array<necta.application.DTOs.Dashboard.DashboardWidgetDto> — Liste der Widgets, die mit dem Dashboard verknüpft sind
    - id: integer:int32 — Ruft die eindeutige Kennung des Widgets ab oder legt sie fest.
    - widgetId: integer:int32 — Ruft die eindeutige Kennung des Widgets ab oder legt sie fest.
    - label: string — Ruft die Bezeichnung oder den Titel des Widgets ab oder legt sie fest.
    - componentType: necta.domain.Enums.WidgetComponentType
    - rowSpan: integer:int32 — Ruft die Anzahl der Zeilen ab, die das Widget überspannt, oder legt sie fest.
    - columnSpan: integer:int32 — Ruft die Anzahl der Spalten ab, die das Widget überspannt, oder legt sie fest.
    - rowPosition: integer:int32 — Zeilenposition des Widgets
    - columnPosition: integer:int32 — Spaltenposition des Widgets
    - backgroundColor: string — Ruft die Hintergrundfarbe des Widgets ab oder legt sie fest.  
    - color: string — Ruft die Textfarbe des Widgets ab oder legt sie fest.  
    - contentData: string — Ruft die Inhaltsdaten des Widgets ab, die als Zeichenfolge serialisiert sind, oder legt sie fest.  
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dashboardId' => ['type' => 'string', 'description' => 'Pfad-Parameter dashboardId (Pflicht).'],
                'data' => ['type' => 'object', 'description' => 'Request-Body. Felder siehe Tool-Description.', 'additionalProperties' => true],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => ['dashboardId', 'data'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (!isset($arguments['dashboardId']) || $arguments['dashboardId'] === '' || $arguments['dashboardId'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "dashboardId" fehlt.');
        }
        if (!isset($arguments['data']) || $arguments['data'] === '' || $arguments['data'] === null) {
            return ToolResult::error('VALIDATION_ERROR', 'Pflichtparameter "data" fehlt.');
        }

        $path = '/api/v1/{tenantId}/dashboard/{dashboardId}';
        $path = str_replace('{dashboardId}', rawurlencode((string) $arguments['dashboardId']), $path);

        $query = [];
        foreach (self::QUERY_KEYS as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $query[$k] = $arguments[$k];
            }
        }

        $data = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->callSpec($context->user, 'PUT', $path, $query, $data);

            return ToolResult::success($result);
        } catch (NectaApiException $e) {
            return ToolResult::error($e->getNectaErrorCode() ?? 'NECTA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['necta', 'v1', 'dashboard'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
