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
        return 'POST /credit-notes - Erstellt eine neue Lexware-Gutschrift. Beispiel: {"voucherDate":"2024-06-20","address":{"contactId":"UUID"},"lineItems":[{"type":"custom","name":"Gutschrift für Reklamation","quantity":1,"unitName":"Pauschal","unitPrice":{"currency":"EUR","netAmount":50.00,"taxRatePercentage":19}}],"totalPrice":{"currency":"EUR"},"taxConditions":{"taxType":"net"}}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Lexware-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'data' => [
                    'type' => 'object',
                    'description' => 'Gutschriftdaten für die Lexware API.',
                    'properties' => [
                        'voucherDate' => ['type' => 'string', 'description' => 'Datum im Format YYYY-MM-DD.'],
                        'address' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Empfänger. ENTWEDER {"contactId":"UUID"} ODER {"name":"...","street":"...","zip":"...","city":"...","countryCode":"DE"}.',
                        ],
                        'lineItems' => [
                            'type' => 'array',
                            'description' => 'PFLICHT. Positionen. Jede Position: {"type":"custom","name":"...","quantity":1,"unitName":"Stück","unitPrice":{"currency":"EUR","netAmount":50.00,"taxRatePercentage":19}}.',
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
                                        'description' => 'PFLICHT. {currency:"EUR", netAmount:50.00, taxRatePercentage:19}.',
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
                'finalize' => ['type' => 'boolean', 'description' => 'Direkt finalisieren (default: false).'],
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
            $service = app(LexwareApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->createCreditNote($context->user, $arguments['data'], $arguments['finalize'] ?? false);
            return ToolResult::success($result);
        } catch (LexwareApiException $e) {
            $errorMsg = $e->getMessage();
            $responseData = $e->getResponseData();
            if ($responseData) {
                $errorMsg .= ' | API-Response: ' . json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            return ToolResult::error($e->getLexwareErrorCode() ?? 'LEXWARE_ERROR', $errorMsg);
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
