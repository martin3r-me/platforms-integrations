<?php

namespace Platform\Integrations\Tools\TimeEntry;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\DTOs\TimeEntry\BulkTimeEntryRequest;
use Platform\Integrations\Services\TimeEntryService;

class BulkCreateTimeEntriesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.time-entries.bulk.POST';
    }

    public function getDescription(): string
    {
        return '[DEPRECATED – Bitte organization.time_entries.bulk.POST verwenden] POST /time-entries/bulk - Erstellt mehrere Zeiteinträge auf einmal. Dieses Tool ist veraltet und wird in einer zukünftigen Version entfernt. Nutze stattdessen organization.time_entries.bulk.POST für Kontext-Zuordnung, Kaskaden und erweiterte Funktionen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entries' => [
                    'type' => 'array',
                    'description' => 'Array von Zeiteinträgen. Jeder Eintrag: {date (YYYY-MM-DD), start_time (HH:MM), end_time (HH:MM), project_id?, project_name?, context?, description?, type? (work/break/travel/meeting/other), tags? (array)}',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'date' => ['type' => 'string', 'description' => 'Datum (YYYY-MM-DD)'],
                            'start_time' => ['type' => 'string', 'description' => 'Startzeit (HH:MM)'],
                            'end_time' => ['type' => 'string', 'description' => 'Endzeit (HH:MM)'],
                            'project_id' => ['type' => 'integer', 'description' => 'Projekt-ID (optional)'],
                            'project_name' => ['type' => 'string', 'description' => 'Projektname (optional)'],
                            'context' => ['type' => 'string', 'description' => 'Kontext/Bereich (optional)'],
                            'description' => ['type' => 'string', 'description' => 'Beschreibung (optional)'],
                            'type' => ['type' => 'string', 'description' => 'Typ (default: work)'],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tags (optional)'],
                        ],
                        'required' => ['date', 'start_time', 'end_time'],
                    ],
                ],
                'team_id' => ['type' => 'integer', 'description' => 'Team-ID für alle Einträge (optional)'],
            ],
            'required' => ['entries'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        // Schritt 1: Bulk-DTO erstellen und validieren
        $parsed = BulkTimeEntryRequest::fromRequest($arguments);

        if (!empty($parsed['errors']) && $parsed['dto'] === null) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'Validierungsfehler: ' . json_encode($parsed['errors'], JSON_UNESCAPED_UNICODE)
            );
        }

        try {
            $service = app(TimeEntryService::class);
            $teamId = isset($arguments['team_id']) ? (int) $arguments['team_id'] : null;
            $result = $service->createBulk($context->user, $parsed['dto'], $teamId);

            // Validierungsfehler einbeziehen
            if (!empty($parsed['errors'])) {
                $result['validation_errors'] = $parsed['errors'];
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['time-entries', 'bulk', 'create', 'stempeln', 'zeiterfassung', 'deprecated'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
            'deprecated' => true,
            'replacement' => 'organization.time_entries.bulk.POST',
        ];
    }
}
