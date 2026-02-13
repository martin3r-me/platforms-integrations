<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateCreditNoteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.credit_notes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /credit-notes - Erstellt eine neue Lexware-Gutschrift. data (object) - Gutschriftdaten gemäß Lexware API. finalize (bool, optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Gutschriftdaten gemäß Lexware API. Felder: voucherDate, address, lineItems, totalPrice, taxConditions, introduction, remark.',
                ],
                'finalize' => ['type' => 'boolean', 'description' => 'Direkt finalisieren (default: false)'],
            ],
            'required' => ['data'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['data'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Gutschriftdaten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->createCreditNote($context->user, $arguments['data'], $arguments['finalize'] ?? false);
            return ToolResult::success($result);
        } catch (LexwareApiException $e) {
            return ToolResult::error($e->getLexwareErrorCode() ?? 'LEXWARE_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['lexware', 'credit_notes', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
