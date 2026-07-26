<?php

namespace Platform\Integrations\Tools\Moss;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\MossApiException;
use Platform\Integrations\Services\MossApiService;

class SearchFilesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.moss.files.search';
    }

    public function getDescription(): string
    {
        return 'POST /v1/files/search-query - Findet die Belege (Files) zu Moss-Expenses. Filtert nach expense_ids (bis zu 100). Liefert Datei-Metadaten (id, name, size). Zum Herunterladen der Datei: integrations.moss.file.content mit file_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Moss-Verbindung.',
                ],
                'expense_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Expense-UUIDs, deren Belege gesucht werden (max. 100).',
                ],
            ],
            'required' => ['expense_ids'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $expenseIds = $arguments['expense_ids'] ?? [];
        if (empty($expenseIds)) {
            return ToolResult::error('VALIDATION_ERROR', 'expense_ids ist erforderlich.');
        }

        try {
            $service = app(MossApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->searchFilesByExpenses($context->user, $expenseIds);

            return ToolResult::success($result);
        } catch (MossApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'MOSS_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['moss', 'spend-management', 'files', 'receipts', 'belege'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
