<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateQuotationTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.quotations.POST';
    }

    public function getDescription(): string
    {
        return 'POST /quotations - Erstellt ein neues Lexware-Angebot. Beispiel: {"voucherDate":"2024-06-15","expirationDate":"2024-07-15","address":{"contactId":"UUID"},"lineItems":[{"type":"custom","name":"Beratung","quantity":10,"unitName":"Stunden","unitPrice":{"currency":"EUR","netAmount":100.00,"taxRatePercentage":19}}],"totalPrice":{"currency":"EUR"},"taxConditions":{"taxType":"net"},"introduction":"Gerne bieten wir Ihnen an:","remark":"Dieses Angebot ist 30 Tage gültig."}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Lexware-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'data' => [
                    'type' => 'object',
                    'description' => 'Angebotsdaten für die Lexware API.',
                    'properties' => [
                        'voucherDate' => ['type' => 'string', 'description' => 'Angebotsdatum im Format YYYY-MM-DD.'],
                        'expirationDate' => ['type' => 'string', 'description' => 'Gültig bis Datum im Format YYYY-MM-DD.'],
                        'address' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Empfänger. ENTWEDER {"contactId":"UUID"} ODER manuelle Adresse {"name":"...","street":"...","zip":"...","city":"...","countryCode":"DE"}.',
                        ],
                        'lineItems' => [
                            'type' => 'array',
                            'description' => 'PFLICHT. Positionen. Jede Position: {"type":"custom","name":"...","quantity":1,"unitName":"Stück","unitPrice":{"currency":"EUR","netAmount":100.00,"taxRatePercentage":19}}. type ist meistens "custom".',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['type' => 'string', 'description' => 'PFLICHT. "custom" (Freitext) oder "material" (Artikel).'],
                                    'name' => ['type' => 'string', 'description' => 'PFLICHT. Bezeichnung.'],
                                    'description' => ['type' => 'string', 'description' => 'Beschreibung.'],
                                    'quantity' => ['type' => 'number', 'description' => 'PFLICHT. Menge.'],
                                    'unitName' => ['type' => 'string', 'description' => 'Einheit, z.B. "Stunden", "Stück".'],
                                    'unitPrice' => [
                                        'type' => 'object',
                                        'description' => 'PFLICHT. Preis: {currency:"EUR", netAmount:100.00, taxRatePercentage:19}. Bei taxType "gross" verwende grossAmount statt netAmount.',
                                    ],
                                ],
                            ],
                        ],
                        'totalPrice' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. {"currency":"EUR"}.',
                        ],
                        'taxConditions' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. {"taxType":"net"} (Netto), {"taxType":"gross"} (Brutto) oder {"taxType":"vatfree"} (steuerfrei).',
                        ],
                        'title' => ['type' => 'string', 'description' => 'Titel, z.B. "Angebot".'],
                        'introduction' => ['type' => 'string', 'description' => 'Einleitungstext.'],
                        'remark' => ['type' => 'string', 'description' => 'Schlussbemerkung.'],
                    ],
                    'required' => ['address', 'lineItems', 'totalPrice', 'taxConditions'],
                ],
                'finalize' => ['type' => 'boolean', 'description' => 'Direkt finalisieren (default: false). Finalisierte Angebote erhalten eine Angebotsnummer.'],
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
            return ToolResult::error('VALIDATION_ERROR', 'Angebotsdaten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->createQuotation($context->user, $arguments['data'], $arguments['finalize'] ?? false);
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
            'tags' => ['lexware', 'quotations', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
