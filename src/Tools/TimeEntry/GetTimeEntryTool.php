<?php

namespace Platform\Integrations\Tools\TimeEntry;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\TimeEntryService;

class GetTimeEntryTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.time-entry.GET';
    }

    public function getDescription(): string
    {
        return 'GET /time-entries/{id} - Ruft einen einzelnen Zeiteintrag ab. id (integer) - Zeiteintrag-ID.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des Zeiteintrags'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Zeiteintrag-ID ist erforderlich.');
        }

        try {
            $service = app(TimeEntryService::class);
            $entry = $service->get($context->user, (int) $arguments['id']);

            if (!$entry) {
                return ToolResult::error('NOT_FOUND', 'Zeiteintrag nicht gefunden.');
            }

            return ToolResult::success($entry->toArray());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['time-entries', 'detail', 'zeiterfassung'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
