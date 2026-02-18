<?php

namespace Platform\Integrations\Tools\TimeEntry;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\DTOs\TimeEntry\TimeEntryRequest;
use Platform\Integrations\Services\TimeEntryService;

class CreateTimeEntryTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.time-entries.POST';
    }

    public function getDescription(): string
    {
        return 'POST /time-entries - Erstellt einen einzelnen Zeiteintrag (Stempeln). Pflichtfelder: date (YYYY-MM-DD), start_time (HH:MM), end_time (HH:MM). Optional: project_id, project_name, context, description, type (work/break/travel/meeting/other), tags (array), team_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => 'Datum (YYYY-MM-DD)'],
                'start_time' => ['type' => 'string', 'description' => 'Startzeit (HH:MM)'],
                'end_time' => ['type' => 'string', 'description' => 'Endzeit (HH:MM)'],
                'project_id' => ['type' => 'integer', 'description' => 'Projekt-ID (optional)'],
                'project_name' => ['type' => 'string', 'description' => 'Projektname (optional)'],
                'context' => ['type' => 'string', 'description' => 'Kontext/Bereich (optional)'],
                'description' => ['type' => 'string', 'description' => 'Beschreibung (optional)'],
                'type' => ['type' => 'string', 'description' => 'Typ: work, break, travel, meeting, other (default: work)'],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tags (optional)'],
                'team_id' => ['type' => 'integer', 'description' => 'Team-ID (optional)'],
            ],
            'required' => ['date', 'start_time', 'end_time'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $dto = TimeEntryRequest::fromRequest($arguments);
            $service = app(TimeEntryService::class);
            $teamId = isset($arguments['team_id']) ? (int) $arguments['team_id'] : null;
            $entry = $service->create($context->user, $dto, $teamId, 'tool');

            return ToolResult::success($entry->toArray());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ToolResult::error('VALIDATION_ERROR', 'Validierungsfehler: ' . json_encode($e->errors(), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['time-entries', 'create', 'stempeln', 'zeiterfassung'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
