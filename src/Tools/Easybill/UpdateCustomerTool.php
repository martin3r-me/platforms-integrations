<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class UpdateCustomerTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.customer.PUT';
    }

    public function getDescription(): string
    {
        return <<<TXT
PUT /customers/{id} — Kunden aktualisieren.

Voll-Update: easybill ersetzt den Datensatz mit dem Payload — am sichersten zuerst GET /customers/{id}, gewünschte Felder anpassen, komplettes Objekt zurückschicken. Felder, die nicht im Payload stehen, werden auf Default zurückgesetzt.

Häufige data-Felder (selbe Struktur wie POST /customers):
- Identität: company_name, first_name, last_name, salutation, title, suffix_1, suffix_2, personal, birth_date
- Rechnungsadresse: street, zip_code, city, state, country
- Lieferadresse: delivery_company_name, delivery_first_name, delivery_last_name, delivery_street, delivery_zip_code, delivery_city, delivery_state, delivery_country, delivery_salutation, delivery_title, delivery_suffix_1/2, delivery_personal
- Postfach: postbox, postbox_zip_code, postbox_city, postbox_state, postbox_country
- Kontakt: emails[], phone_1, phone_2, mobile, fax, internet
- Steuer: tax_number, vat_identifier, tax_options
- Bank: bank_account_owner, bank_iban, bank_bic, bank_name, sepa_agreement, sepa_mandate_reference
- Zahlung: due_in_days, grace_period, cash_discount, cash_discount_type, cash_allowance, cash_allowance_days, sale_price_level, payment_options
- Handelsregister: court, court_registry_number, since_date
- Gruppierung: group_id, additional_groups_ids[]
- Lieferant: supplier_number, foreign_supplier_number
- Custom: number, display_name, note, info_1, info_2, buyer_reference, document_pdf_type, archived

Volle Feldliste: https://api.easybill.de/rest/v1/ (Swagger).
TXT;
    }

    public function getSchema(): array
    {
        return [
          'type' => 'object',
          'properties' => [
            'connection_id' => [
              'type' => 'integer',
              'description' => 'Optional: ID einer spezifischen easybill-Connection.',
            ],
            'customer_id' => [
              'type' => 'integer',
              'description' => 'ID des Kunden',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Kunden-Daten — vollständiger Stand, nicht diff. Siehe Tool-Description für alle Felder.',
              'additionalProperties' => true,
            ],
          ],
          'required' => [
            0 => 'customer_id',
            1 => 'data',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->updateCustomer($context->user, (int) $arguments['customer_id'], $arguments['data']);
            return ToolResult::success($result);
        } catch (EasybillApiException $e) {
            return ToolResult::error($e->getEasybillErrorCode() ?? 'EASYBILL_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'mutation',
            'tags' => [
              0 => 'easybill',
              1 => 'customers',
              2 => 'update',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}