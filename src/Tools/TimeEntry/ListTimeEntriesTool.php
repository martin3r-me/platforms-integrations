<?php

namespace Platform\Integrations\Tools\TimeEntry;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\TimeEntryService;

class ListTimeEntriesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.time-entries.GET';
    }

    public function getDescription(): string
    {
        return '[DEPRECATED – Bitte organization.time_entries.GET verwenden] GET /time-entries - Listet Zeiteinträge auf. Dieses Tool ist veraltet und wird in einer zukünftigen Version entfernt. Nutze stattdessen organization.time_entries.GET für erweiterte Filter, Cross-Team-Abfragen und Kontext-Kaskaden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Seite (1-basiert, default: 1)'],
                'per_page' => ['type' => 'integer', 'description' => 'Einträge pro Seite (max 100, default: 25)'],
                'date_from' => ['type' => 'string', 'description' => 'Startdatum (YYYY-MM-DD)'],
                'date_to' => ['type' => 'string', 'description' => 'Enddatum (YYYY-MM-DD)'],
                'project_id' => ['type' => 'integer', 'description' => 'Filter nach Projekt-ID'],
                'context' => ['type' => 'string', 'description' => 'Filter nach Kontext'],
                'type' => ['type' => 'string', 'description' => 'Filter nach Typ (work, break, travel, meeting, other)'],
                'team_id' => ['type' => 'integer', 'description' => 'Filter nach Team-ID'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(TimeEntryService::class);
            $filters = array_filter([
                'date_from' => $arguments['date_from'] ?? null,
                'date_to' => $arguments['date_to'] ?? null,
                'project_id' => $arguments['project_id'] ?? null,
                'context' => $arguments['context'] ?? null,
                'type' => $arguments['type'] ?? null,
                'team_id' => $arguments['team_id'] ?? null,
            ], fn ($v) => $v !== null);

            $result = $service->list(
                $context->user,
                $filters,
                $arguments['page'] ?? 1,
                min($arguments['per_page'] ?? 25, 100)
            );

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['time-entries', 'list', 'zeiterfassung', 'deprecated'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'deprecated' => true,
            'replacement' => 'organization.time_entries.GET',
        ];
    }
}
