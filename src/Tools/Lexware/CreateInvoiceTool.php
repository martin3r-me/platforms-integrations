<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateInvoiceTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'lexware.invoices.POST';
    }

    public function getDescription(): string
    {
        return 'POST /invoices - Erstellt eine neue Lexware-Rechnung. data (object) - Rechnungsdaten gemäß Lexware API. finalize (bool, optional) - Rechnung direkt finalisieren (default: false). Wichtige Felder: voucherDate, address (contactId oder manuell), lineItems (array mit type, name, unitPrice, quantity etc.), totalPrice, taxConditions.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Rechnungsdaten gemäß Lexware API. Wichtige Felder: voucherDate (string, YYYY-MM-DD), address (object mit contactId oder name/street/city/zip/countryCode), lineItems (array), totalPrice (object mit currency), taxConditions (object mit taxType), paymentConditions (object), introduction (string), remark (string).',
                ],
                'finalize' => ['type' => 'boolean', 'description' => 'Rechnung direkt finalisieren (default: false). Finalisierte Rechnungen können nicht mehr bearbeitet werden.'],
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
            return ToolResult::error('VALIDATION_ERROR', 'Rechnungsdaten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->createInvoice($context->user, $arguments['data'], $arguments['finalize'] ?? false);
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
            'tags' => ['lexware', 'invoices', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
