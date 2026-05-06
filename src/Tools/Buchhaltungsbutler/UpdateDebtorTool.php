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
        return 'POST /settings/update/debtor — Aktualisiert ein bestehendes Debitorenkonto. Pflicht: postingaccount_number. '
             . 'WICHTIG: BuchhaltungsButler ersetzt beim Update den kompletten Datensatz — Felder, die NICHT mitgeschickt werden, werden auf NULL gesetzt (z. B. Adresse, IBAN, E-Mail). '
             . 'Empfohlener Ablauf: Erst per debtors.GET den aktuellen Datensatz holen, alle bestehenden Werte mit den gewünschten Änderungen mergen und vollständig im Update-Body übergeben. Niemals nur einzelne Felder schicken, wenn die anderen erhalten bleiben sollen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen BuchhaltungsButler-Connection.'],
                'postingaccount_number' => ['type' => 'string', 'description' => 'PFLICHT. Buchungskonto-Nummer des zu aktualisierenden Debitors.'],
                'name' => ['type' => 'string', 'description' => 'Name. Bestehenden Wert mitschicken, sonst wird er ggf. genullt.'],
                'contact_person_name' => ['type' => 'string', 'description' => 'Ansprechpartner. Bestehenden Wert mitschicken, sonst NULL.'],
                'street' => ['type' => 'string', 'description' => 'Straße. Bestehenden Wert mitschicken, sonst NULL.'],
                'additional_address_line' => ['type' => 'string', 'description' => 'Zusatzzeile zur Adresse. Bestehenden Wert mitschicken, sonst NULL.'],
                'customer_number' => ['type' => 'string', 'description' => 'Kundennummer. Bestehenden Wert mitschicken, sonst NULL.'],
                'zip' => ['type' => 'string', 'description' => 'PLZ. Bestehenden Wert mitschicken, sonst NULL.'],
                'city' => ['type' => 'string', 'description' => 'Ort. Bestehenden Wert mitschicken, sonst NULL.'],
                'country' => ['type' => 'string', 'description' => 'Land (ISO-Code oder deutscher Ländername). Bestehenden Wert mitschicken, sonst NULL.'],
                'sales_tax_id' => ['type' => 'string', 'description' => 'USt-IdNr. Bestehenden Wert mitschicken, sonst NULL.'],
                'email' => ['type' => 'string', 'description' => 'E-Mail. Bestehenden Wert mitschicken, sonst NULL.'],
                'iban' => ['type' => 'string', 'description' => 'IBAN. Bestehenden Wert mitschicken, sonst NULL.'],
                'bic' => ['type' => 'string', 'description' => 'BIC. Bestehenden Wert mitschicken, sonst NULL.'],
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
