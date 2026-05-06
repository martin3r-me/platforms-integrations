<?php

namespace Platform\Integrations\Tools\Buchhaltungsbutler;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Exceptions\BuchhaltungsbutlerApiException;
use Platform\Integrations\Services\BuchhaltungsbutlerApiService;

class ListDebtorsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.buchhaltungsbutler.debtors.GET';
    }

    public function getDescription(): string
    {
        return 'POST /settings/get/debtors — Listet die Debitorenkonten (Kunden) des verbundenen BuchhaltungsButler-Accounts. Paginiert (limit/offset).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen BuchhaltungsButler-Connection.'],
                'limit' => ['type' => 'integer', 'description' => 'Maximale Anzahl Ergebnisse (Default: 25).'],
                'offset' => ['type' => 'integer', 'description' => 'Offset für Pagination (Default: 0).'],
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
            $service = app(BuchhaltungsbutlerApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result  = $service->getDebtors(
                $context->user,
                (int) ($arguments['limit'] ?? 25),
                (int) ($arguments['offset'] ?? 0)
            );
            return ToolResult::success($result);
        } catch (BuchhaltungsbutlerApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'BUCHHALTUNGSBUTLER_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['buchhaltungsbutler', 'debtors', 'customers', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
