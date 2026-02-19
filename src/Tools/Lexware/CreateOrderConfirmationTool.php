<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateOrderConfirmationTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.order_confirmations.POST';
    }

    public function getDescription(): string
    {
        return 'POST /order-confirmations - Erstellt eine neue Lexware-Auftragsbestätigung. Beispiel: {"voucherDate":"2024-06-15","address":{"contactId":"UUID"},"lineItems":[{"type":"custom","name":"Dienstleistung","quantity":1,"unitName":"Pauschal","unitPrice":{"currency":"EUR","netAmount":500.00,"taxRatePercentage":19}}],"totalPrice":{"currency":"EUR"},"taxConditions":{"taxType":"net"}}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Auftragsbestätigungsdaten für die Lexware API.',
                    'properties' => [
                        'voucherDate' => ['type' => 'string', 'description' => 'Datum im Format YYYY-MM-DD.'],
                        'address' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Empfänger. ENTWEDER {"contactId":"UUID"} ODER {"name":"...","street":"...","zip":"...","city":"...","countryCode":"DE"}.',
                        ],
                        'lineItems' => [
                            'type' => 'array',
                            'description' => 'PFLICHT. Positionen. Jede Position: {"type":"custom","name":"...","quantity":1,"unitName":"Stück","unitPrice":{"currency":"EUR","netAmount":100.00,"taxRatePercentage":19}}.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['type' => 'string', 'description' => 'PFLICHT. "custom" oder "material".'],
                                    'name' => ['type' => 'string', 'description' => 'PFLICHT. Bezeichnung.'],
                                    'description' => ['type' => 'string', 'description' => 'Beschreibung.'],
                                    'quantity' => ['type' => 'number', 'description' => 'PFLICHT. Menge.'],
                                    'unitName' => ['type' => 'string', 'description' => 'Einheit.'],
                                    'unitPrice' => [
                                        'type' => 'object',
                                        'description' => 'PFLICHT. {currency:"EUR", netAmount:100.00, taxRatePercentage:19}.',
                                    ],
                                ],
                            ],
                        ],
                        'totalPrice' => ['type' => 'object', 'description' => 'PFLICHT. {"currency":"EUR"}.'],
                        'taxConditions' => ['type' => 'object', 'description' => 'PFLICHT. {"taxType":"net"}, "gross" oder "vatfree".'],
                        'title' => ['type' => 'string', 'description' => 'Titel.'],
                        'introduction' => ['type' => 'string', 'description' => 'Einleitungstext.'],
                        'remark' => ['type' => 'string', 'description' => 'Schlussbemerkung.'],
                    ],
                    'required' => ['address', 'lineItems', 'totalPrice', 'taxConditions'],
                ],
                'finalize' => ['type' => 'boolean', 'description' => 'Direkt finalisieren (default: false). Finalisierte Auftragsbestätigungen können nicht mehr bearbeitet werden.'],
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
            return ToolResult::error('VALIDATION_ERROR', 'Auftragsbestätigungsdaten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->createOrderConfirmation($context->user, $arguments['data'], $arguments['finalize'] ?? false);
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
            'tags' => ['lexware', 'order_confirmations', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
