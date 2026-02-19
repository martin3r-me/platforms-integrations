<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class UpdateContactTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.contacts.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /contacts/{id} - Aktualisiert einen Lexware-Kontakt. WICHTIG: Zuerst den Kontakt per GET abrufen, um die aktuelle version zu erhalten. Alle Felder, die NICHT im Request enthalten sind, werden auf Standardwerte zurückgesetzt! Daher immer den kompletten Kontakt senden. Beispiel: {"version":1,"roles":{"customer":{"number":10001}},"company":{"name":"Muster GmbH - Aktualisiert"},"addresses":{"billing":[{"street":"Neue Str. 2","zip":"12345","city":"Berlin","countryCode":"DE"}]}}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'PFLICHT. UUID des Kontakts (aus vorherigem GET).'],
                'data' => [
                    'type' => 'object',
                    'description' => 'Vollständige Kontakt-Daten. WICHTIG: Felder die fehlen werden auf Standardwerte zurückgesetzt! Immer alle relevanten Felder mitsenden.',
                    'properties' => [
                        'version' => [
                            'type' => 'integer',
                            'description' => 'PFLICHT. Aktuelle Version des Kontakts (aus vorherigem GET). Wird für Optimistic Locking verwendet.',
                        ],
                        'roles' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Rollen: {"customer":{}} und/oder {"vendor":{}}. Optional mit number: {"customer":{"number":10001}}.',
                        ],
                        'company' => [
                            'type' => 'object',
                            'description' => 'Firmendaten (wenn Firma). Felder: name (string, PFLICHT), taxNumber (string), allowTaxFreeInvoices (bool), contactPersons (array mit salutation, firstName, lastName, primary, emailAddress, phoneNumber).',
                        ],
                        'person' => [
                            'type' => 'object',
                            'description' => 'Personendaten (wenn Person). Felder: salutation (string), firstName (string, PFLICHT), lastName (string, PFLICHT).',
                        ],
                        'addresses' => [
                            'type' => 'object',
                            'description' => 'Adressen. Felder: billing (array), shipping (array). Jede Adresse: {street, zip, city, countryCode, supplement}.',
                        ],
                        'emailAddresses' => [
                            'type' => 'object',
                            'description' => 'E-Mails. Felder: business, office, private, other (jeweils Array von Strings).',
                        ],
                        'phoneNumbers' => [
                            'type' => 'object',
                            'description' => 'Telefonnummern. Felder: business, office, mobile, private, fax, other (jeweils Array von Strings).',
                        ],
                        'note' => ['type' => 'string', 'description' => 'Freitext-Notiz.'],
                    ],
                    'required' => ['version', 'roles'],
                ],
            ],
            'required' => ['id', 'data'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Kontakt-ID ist erforderlich.');
        }

        if (empty($arguments['data'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Aktualisierte Daten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->updateContact($context->user, $arguments['id'], $arguments['data']);
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
            'tags' => ['lexware', 'contacts', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['updates'],
        ];
    }
}
