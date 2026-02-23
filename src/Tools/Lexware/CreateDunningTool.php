<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateDunningTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.dunnings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dunnings - Erstellt eine neue Lexware-Mahnung. Beispiel: {"voucherDate":"2024-07-01","address":{"contactId":"UUID"},"lineItems":[{"type":"custom","name":"Offener Rechnungsbetrag RE-2024-001","quantity":1,"unitName":"Pauschal","unitPrice":{"currency":"EUR","netAmount":1000.00,"taxRatePercentage":19}}],"totalPrice":{"currency":"EUR"},"taxConditions":{"taxType":"net"},"title":"1. Mahnung","introduction":"Leider konnten wir noch keinen Zahlungseingang feststellen.","remark":"Bitte überweisen Sie den Betrag innerhalb von 14 Tagen."}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Lexware-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'data' => [
                    'type' => 'object',
                    'description' => 'Mahnungsdaten für die Lexware API. Mahnungen enthalten Preise (anders als Lieferscheine).',
                    'properties' => [
                        'voucherDate' => ['type' => 'string', 'description' => 'Mahnungsdatum im Format YYYY-MM-DD.'],
                        'address' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Empfänger. ENTWEDER {"contactId":"UUID"} ODER {"name":"...","street":"...","zip":"...","city":"...","countryCode":"DE"}.',
                        ],
                        'lineItems' => [
                            'type' => 'array',
                            'description' => 'PFLICHT. Positionen (mit Preisen). Jede Position: {"type":"custom","name":"...","quantity":1,"unitPrice":{"currency":"EUR","netAmount":1000.00,"taxRatePercentage":19}}.',
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
                                        'description' => 'PFLICHT. {currency:"EUR", netAmount:1000.00, taxRatePercentage:19}.',
                                    ],
                                ],
                            ],
                        ],
                        'totalPrice' => ['type' => 'object', 'description' => 'PFLICHT. {"currency":"EUR"}.'],
                        'taxConditions' => ['type' => 'object', 'description' => 'PFLICHT. {"taxType":"net"} oder {"taxType":"gross"}.'],
                        'paymentConditions' => [
                            'type' => 'object',
                            'description' => 'Zahlungsbedingungen. {paymentTermLabel:"...", paymentTermDuration:14}.',
                        ],
                        'title' => ['type' => 'string', 'description' => 'Titel, z.B. "1. Mahnung", "2. Mahnung".'],
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
            return ToolResult::error('VALIDATION_ERROR', 'Mahnungsdaten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->createDunning($context->user, $arguments['data'], $arguments['finalize'] ?? false);
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
            'tags' => ['lexware', 'dunnings', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
