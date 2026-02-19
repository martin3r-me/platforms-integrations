<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateContactTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.contacts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /contacts - Erstellt einen neuen Lexware-Kontakt (Kunde/Lieferant). PFLICHT: version (immer 0 bei Neuanlage), roles ({customer:{}} und/oder {vendor:{}}), und entweder company.name ODER person (firstName+lastName). Beispiel Firma: {"version":0,"roles":{"customer":{}},"company":{"name":"Muster GmbH"},"addresses":{"billing":[{"street":"Musterstr. 1","zip":"12345","city":"Berlin","countryCode":"DE"}]},"emailAddresses":{"business":["info@muster.de"]}} — Beispiel Person: {"version":0,"roles":{"customer":{}},"person":{"salutation":"Herr","firstName":"Max","lastName":"Mustermann"},"addresses":{"billing":[{"street":"Privatweg 5","zip":"11111","city":"Hamburg","countryCode":"DE"}]}}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Kontakt-Daten für die Lexware API. ALLE Felder werden direkt in diesem Objekt übergeben (NICHT nochmals in ein data-Objekt verschachteln).',
                    'properties' => [
                        'version' => [
                            'type' => 'integer',
                            'description' => 'PFLICHT. Bei Neuanlage immer 0.',
                        ],
                        'roles' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Rollen des Kontakts. Mindestens eine Rolle setzen: {"customer":{}} für Kunde, {"vendor":{}} für Lieferant, oder beides.',
                            'properties' => [
                                'customer' => [
                                    'type' => 'object',
                                    'description' => 'Kunde-Rolle. Leeres Objekt {} zum Aktivieren. Optional: {"number": 10001} für eigene Kundennummer.',
                                ],
                                'vendor' => [
                                    'type' => 'object',
                                    'description' => 'Lieferanten-Rolle. Leeres Objekt {} zum Aktivieren. Optional: {"number": 70001} für eigene Lieferantennummer.',
                                ],
                            ],
                        ],
                        'company' => [
                            'type' => 'object',
                            'description' => 'Firmendaten. PFLICHT wenn keine person angegeben. Entweder company ODER person muss gesetzt sein.',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'PFLICHT. Firmenname, z.B. "Muster GmbH".'],
                                'taxNumber' => ['type' => 'string', 'description' => 'Steuernummer, z.B. "DE123456789".'],
                                'allowTaxFreeInvoices' => ['type' => 'boolean', 'description' => 'Steuerfreie Rechnungen erlaubt? Default: false.'],
                                'contactPersons' => [
                                    'type' => 'array',
                                    'description' => 'Ansprechpartner der Firma.',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'salutation' => ['type' => 'string', 'description' => 'Anrede: "Herr", "Frau" oder leer.'],
                                            'firstName' => ['type' => 'string', 'description' => 'Vorname.'],
                                            'lastName' => ['type' => 'string', 'description' => 'Nachname.'],
                                            'primary' => ['type' => 'boolean', 'description' => 'Haupt-Ansprechpartner? Default: false.'],
                                            'emailAddress' => ['type' => 'string', 'description' => 'E-Mail des Ansprechpartners.'],
                                            'phoneNumber' => ['type' => 'string', 'description' => 'Telefon des Ansprechpartners.'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'person' => [
                            'type' => 'object',
                            'description' => 'Personendaten. PFLICHT wenn keine company angegeben. Entweder company ODER person muss gesetzt sein.',
                            'properties' => [
                                'salutation' => ['type' => 'string', 'description' => 'Anrede: "Herr", "Frau" oder leer.'],
                                'firstName' => ['type' => 'string', 'description' => 'PFLICHT. Vorname.'],
                                'lastName' => ['type' => 'string', 'description' => 'PFLICHT. Nachname.'],
                            ],
                        ],
                        'addresses' => [
                            'type' => 'object',
                            'description' => 'Adressen des Kontakts.',
                            'properties' => [
                                'billing' => [
                                    'type' => 'array',
                                    'description' => 'Rechnungsadressen (Array von Adress-Objekten).',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'street' => ['type' => 'string', 'description' => 'Straße und Hausnummer.'],
                                            'zip' => ['type' => 'string', 'description' => 'PLZ.'],
                                            'city' => ['type' => 'string', 'description' => 'Stadt.'],
                                            'countryCode' => ['type' => 'string', 'description' => 'ISO 3166-1 alpha-2 Ländercode, z.B. "DE", "AT", "CH".'],
                                            'supplement' => ['type' => 'string', 'description' => 'Adresszusatz.'],
                                        ],
                                    ],
                                ],
                                'shipping' => [
                                    'type' => 'array',
                                    'description' => 'Lieferadressen (Array von Adress-Objekten, gleiche Struktur wie billing).',
                                ],
                            ],
                        ],
                        'emailAddresses' => [
                            'type' => 'object',
                            'description' => 'E-Mail-Adressen. Jedes Feld ist ein Array von Strings.',
                            'properties' => [
                                'business' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Geschäftliche E-Mails, z.B. ["info@firma.de"].'],
                                'office' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Büro-E-Mails.'],
                                'private' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Private E-Mails.'],
                                'other' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Sonstige E-Mails.'],
                            ],
                        ],
                        'phoneNumbers' => [
                            'type' => 'object',
                            'description' => 'Telefonnummern. Jedes Feld ist ein Array von Strings.',
                            'properties' => [
                                'business' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Geschäftliche Nummern.'],
                                'office' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Büro-Nummern.'],
                                'mobile' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Mobilnummern.'],
                                'private' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Private Nummern.'],
                                'fax' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Faxnummern.'],
                                'other' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Sonstige Nummern.'],
                            ],
                        ],
                        'note' => [
                            'type' => 'string',
                            'description' => 'Freitext-Notiz zum Kontakt.',
                        ],
                    ],
                    'required' => ['version', 'roles'],
                ],
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
            return ToolResult::error('VALIDATION_ERROR', 'Kontakt-Daten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->createContact($context->user, $arguments['data']);
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
            'tags' => ['lexware', 'contacts', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
