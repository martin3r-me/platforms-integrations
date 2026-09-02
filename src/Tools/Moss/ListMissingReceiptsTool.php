<?php

namespace Platform\Integrations\Tools\Moss;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\MossApiService;
use Platform\Integrations\Exceptions\MossApiException;

class ListMissingReceiptsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.moss.expenses.missing-receipts';
    }

    public function getDescription(): string
    {
        return 'Komfort: Findet Moss-Expenses mit fehlendem Beleg ("offene Belege") — belegpflichtig (receiptRequirement=REQUIRED), aber kein Beleg angehängt (receiptStatus=NOT_CREATED). Paginiert automatisch über alle Expenses und liefert eine kompakte Liste (Betrag, Händler/Supplier, Datum, User, Expense-ID) plus Summenblock (Anzahl & Gesamtbetrag je Währung). Optionaler Zeitraum-Filter. Read-only.';
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
                    'description' => 'Optionaler Filter nach Expense-Typ: invoice, card_transaction, reimbursement.',
                ],
                'date_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Startdatum (YYYY-MM-DD).',
                ],
                'date_to' => [
                    'type' => 'string',
                    'description' => 'Optional: Enddatum (YYYY-MM-DD).',
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
                'date_from' => $arguments['date_from'] ?? null,
                'date_to' => $arguments['date_to'] ?? null,
            ], fn ($v) => $v !== null);

            $result = $service->getExpensesMissingReceipts($context->user, $filters);

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
            'tags' => ['moss', 'spend-management', 'expenses', 'receipts', 'belege', 'missing'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
