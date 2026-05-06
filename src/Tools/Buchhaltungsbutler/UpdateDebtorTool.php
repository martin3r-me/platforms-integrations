<?php

namespace Platform\Integrations\Tools\Buchhaltungsbutler;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Exceptions\BuchhaltungsbutlerApiException;
use Platform\Integrations\Services\BuchhaltungsbutlerApiService;

class UpdateDebtorTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.buchhaltungsbutler.debtors.PUT';
    }

    public function getDescription(): string
    {
        return 'POST /settings/update/debtor — Aktualisiert ein bestehendes Debitorenkonto. Pflicht: postingaccount_number (Identifikation des Kontos).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen BuchhaltungsButler-Connection.'],
                'postingaccount_number' => ['type' => 'string', 'description' => 'PFLICHT. Buchungskonto-Nummer des zu aktualisierenden Debitors.'],
                'name' => ['type' => 'string', 'description' => 'Neuer Name.'],
                'contact_person_name' => ['type' => 'string', 'description' => 'Neuer Ansprechpartner.'],
                'street' => ['type' => 'string', 'description' => 'Neue Straße.'],
                'additional_address_line' => ['type' => 'string', 'description' => 'Neue Zusatzzeile zur Adresse.'],
                'customer_number' => ['type' => 'string', 'description' => 'Neue Kundennummer.'],
                'zip' => ['type' => 'string', 'description' => 'Neue PLZ.'],
                'city' => ['type' => 'string', 'description' => 'Neuer Ort.'],
                'country' => ['type' => 'string', 'description' => 'Neues Land. ISO-Code oder deutscher Ländername.'],
                'sales_tax_id' => ['type' => 'string', 'description' => 'Neue USt-IdNr.'],
                'email' => ['type' => 'string', 'description' => 'Neue E-Mail.'],
                'iban' => ['type' => 'string', 'description' => 'Neue IBAN.'],
                'bic' => ['type' => 'string', 'description' => 'Neue BIC.'],
            ],
            'required' => ['postingaccount_number'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['postingaccount_number'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Feld "postingaccount_number" ist erforderlich.');
        }

        $payload = $arguments;
        unset($payload['connection_id']);

        try {
            $service = app(BuchhaltungsbutlerApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result  = $service->updateDebtor($context->user, $payload);
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
            'tags' => ['buchhaltungsbutler', 'debtors', 'customers', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['updates'],
        ];
    }
}
