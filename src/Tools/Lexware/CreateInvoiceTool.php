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
        return 'integrations.lexware.invoices.POST';
    }

    public function getDescription(): string
    {
        return 'POST /invoices - Erstellt eine neue Lexware-Rechnung. Beispiel: {"voucherDate":"2024-06-15","address":{"contactId":"UUID-DES-KONTAKTS"},"lineItems":[{"type":"custom","name":"Beratung","quantity":10,"unitName":"Stunden","unitPrice":{"currency":"EUR","netAmount":100.00,"taxRatePercentage":19}}],"totalPrice":{"currency":"EUR"},"taxConditions":{"taxType":"net"}}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Rechnungsdaten für die Lexware API.',
                    'properties' => [
                        'voucherDate' => [
                            'type' => 'string',
                            'description' => 'Rechnungsdatum im Format YYYY-MM-DD, z.B. "2024-06-15".',
                        ],
                        'address' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Empfängeradresse. ENTWEDER contactId (UUID eines bestehenden Kontakts) ODER manuelle Adresse (name, street, zip, city, countryCode). Nicht beides mischen!',
                            'properties' => [
                                'contactId' => ['type' => 'string', 'description' => 'UUID eines bestehenden Lexware-Kontakts. Wenn gesetzt, werden name/street/etc. ignoriert.'],
                                'name' => ['type' => 'string', 'description' => 'Empfängername (nur bei manueller Adresse ohne contactId).'],
                                'street' => ['type' => 'string', 'description' => 'Straße und Hausnummer.'],
                                'zip' => ['type' => 'string', 'description' => 'PLZ.'],
                                'city' => ['type' => 'string', 'description' => 'Stadt.'],
                                'countryCode' => ['type' => 'string', 'description' => 'ISO 3166-1 alpha-2, z.B. "DE".'],
                            ],
                        ],
                        'lineItems' => [
                            'type' => 'array',
                            'description' => 'PFLICHT. Rechnungspositionen (mindestens eine).',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'description' => 'PFLICHT. Positionstyp: "custom" (Freitext) oder "material" (Artikel aus Lexware). Meistens "custom".',
                                    ],
                                    'name' => ['type' => 'string', 'description' => 'PFLICHT. Bezeichnung der Position, z.B. "Beratungsleistung".'],
                                    'description' => ['type' => 'string', 'description' => 'Beschreibung/Details zur Position.'],
                                    'quantity' => ['type' => 'number', 'description' => 'PFLICHT. Menge, z.B. 10.'],
                                    'unitName' => ['type' => 'string', 'description' => 'Einheit, z.B. "Stunden", "Stück", "Pauschal".'],
                                    'unitPrice' => [
                                        'type' => 'object',
                                        'description' => 'PFLICHT. Einzelpreis.',
                                        'properties' => [
                                            'currency' => ['type' => 'string', 'description' => 'PFLICHT. Währung, z.B. "EUR".'],
                                            'netAmount' => ['type' => 'number', 'description' => 'Nettobetrag pro Einheit, z.B. 100.00. Verwende netAmount bei taxType "net".'],
                                            'grossAmount' => ['type' => 'number', 'description' => 'Bruttobetrag pro Einheit. Verwende grossAmount bei taxType "gross".'],
                                            'taxRatePercentage' => ['type' => 'number', 'description' => 'PFLICHT. Steuersatz in Prozent: 0, 7 oder 19.'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'totalPrice' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Gesamtpreis-Objekt.',
                            'properties' => [
                                'currency' => ['type' => 'string', 'description' => 'PFLICHT. Währung, z.B. "EUR". Muss mit lineItems übereinstimmen.'],
                            ],
                        ],
                        'taxConditions' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Steuereinstellungen.',
                            'properties' => [
                                'taxType' => [
                                    'type' => 'string',
                                    'description' => 'PFLICHT. Steuertyp: "net" (Netto-Rechnung, am häufigsten), "gross" (Brutto-Rechnung) oder "vatfree" (steuerfrei). Bei "net" netAmount in unitPrice verwenden, bei "gross" grossAmount.',
                                ],
                            ],
                        ],
                        'paymentConditions' => [
                            'type' => 'object',
                            'description' => 'Zahlungsbedingungen (optional).',
                            'properties' => [
                                'paymentTermLabel' => ['type' => 'string', 'description' => 'Text, z.B. "Zahlbar innerhalb von 30 Tagen".'],
                                'paymentTermDuration' => ['type' => 'integer', 'description' => 'Zahlungsfrist in Tagen, z.B. 30.'],
                            ],
                        ],
                        'shippingConditions' => [
                            'type' => 'object',
                            'description' => 'Lieferbedingungen (optional).',
                            'properties' => [
                                'shippingDate' => ['type' => 'string', 'description' => 'Lieferdatum YYYY-MM-DD.'],
                                'shippingType' => ['type' => 'string', 'description' => '"delivery", "pickup" etc.'],
                            ],
                        ],
                        'title' => ['type' => 'string', 'description' => 'Titel der Rechnung, z.B. "Rechnung".'],
                        'introduction' => ['type' => 'string', 'description' => 'Einleitungstext, z.B. "Vielen Dank für Ihren Auftrag.".'],
                        'remark' => ['type' => 'string', 'description' => 'Schlussbemerkung, z.B. "Bei Fragen stehen wir Ihnen gerne zur Verfügung.".'],
                    ],
                    'required' => ['address', 'lineItems', 'totalPrice', 'taxConditions'],
                ],
                'finalize' => ['type' => 'boolean', 'description' => 'Rechnung direkt finalisieren (default: false). ACHTUNG: Finalisierte Rechnungen können NICHT mehr bearbeitet werden und erhalten eine Rechnungsnummer.'],
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
