<?php

namespace Platform\Integrations\Tools\Moss;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\MossApiService;
use Platform\Integrations\Exceptions\MossApiException;

class ListExpensesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.moss.expenses.GET';
    }

    public function getDescription(): string
    {
        return 'GET /v1/expenses - Listet Moss Expenses auf. Filter: type (invoice, card_transaction, reimbursement), status (pending, approved, exported), date_from, date_to, page, per_page.';
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
                'type' => [
                    'type' => 'string',
                    'description' => 'Filter nach Expense-Typ: invoice, card_transaction, reimbursement.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter nach Status: pending, approved, exported.',
                ],
                'date_from' => [
                    'type' => 'string',
                    'description' => 'Startdatum (YYYY-MM-DD).',
                ],
                'date_to' => [
                    'type' => 'string',
                    'description' => 'Enddatum (YYYY-MM-DD).',
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Seitennummer für Paginierung.',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Anzahl Ergebnisse pro Seite.',
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

        try {
            $service = app(MossApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $filters = array_filter([
                'type' => $arguments['type'] ?? null,
                'status' => $arguments['status'] ?? null,
                'date_from' => $arguments['date_from'] ?? null,
                'date_to' => $arguments['date_to'] ?? null,
                'page' => $arguments['page'] ?? null,
                'per_page' => $arguments['per_page'] ?? null,
            ], fn ($v) => $v !== null);

            $result = $service->getExpenses($context->user, $filters);

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
            'tags' => ['moss', 'spend-management', 'expenses', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
