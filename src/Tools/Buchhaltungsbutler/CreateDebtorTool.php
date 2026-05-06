<?php

namespace Platform\Integrations\Tools\Buchhaltungsbutler;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Exceptions\BuchhaltungsbutlerApiException;
use Platform\Integrations\Services\BuchhaltungsbutlerApiService;

class CreateDebtorTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.buchhaltungsbutler.debtors.POST';
    }

    public function getDescription(): string
    {
        return 'POST /settings/add/debtor — Legt ein neues Debitorenkonto (Kunde) in BuchhaltungsButler an. Pflicht: name. Wenn postingaccount_number leer bleibt, vergibt BuchhaltungsButler automatisch die nächste freie Nummer.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen BuchhaltungsButler-Connection.'],
                'name' => ['type' => 'string', 'description' => 'PFLICHT. Name des Debitorenkontos (Firmenname).'],
                'postingaccount_number' => ['type' => 'string', 'description' => 'Optional. Wenn leer, wird automatisch die nächste freie Nummer vergeben.'],
                'contact_person_name' => ['type' => 'string', 'description' => 'Ansprechpartner.'],
                'street' => ['type' => 'string', 'description' => 'Straße + Hausnummer.'],
                'additional_address_line' => ['type' => 'string', 'description' => 'Zusatzzeile zur Adresse.'],
                'customer_number' => ['type' => 'string', 'description' => 'Kundennummer.'],
                'zip' => ['type' => 'string', 'description' => 'PLZ.'],
                'city' => ['type' => 'string', 'description' => 'Ort.'],
                'country' => ['type' => 'string', 'description' => 'Land. ISO-Code (z.B. "DE") oder deutscher Ländername.'],
                'sales_tax_id' => ['type' => 'string', 'description' => 'USt-IdNr.'],
                'email' => ['type' => 'string', 'description' => 'E-Mail-Adresse.'],
                'iban' => ['type' => 'string', 'description' => 'IBAN.'],
                'bic' => ['type' => 'string', 'description' => 'BIC.'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['name'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Feld "name" ist erforderlich.');
        }

        $payload = $arguments;
        unset($payload['connection_id']);

        try {
            $service = app(BuchhaltungsbutlerApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result  = $service->addDebtor($context->user, $payload);
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
            'category' => 'action',
            'tags' => ['buchhaltungsbutler', 'debtors', 'customers', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
