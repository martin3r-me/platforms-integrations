<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class CreateCustomerTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.customer.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
POST /customers — Neuen Kunden anlegen.

EMPFOHLEN: mindestens company_name ODER first_name+last_name; bei Privatperson personal=true.
Kundennummer (number) wird automatisch vergeben, wenn leer.

Häufige data-Felder:
- Identität: company_name, first_name, last_name, salutation (0/1/2 = unbestimmt/Herr/Frau), title, suffix_1, suffix_2, personal (true = Privatperson), birth_date (YYYY-MM-DD)
- Rechnungsadresse: street, zip_code, city, state, country (DE/AT/CH/…)
- Lieferadresse: delivery_company_name, delivery_first_name, delivery_last_name, delivery_street, delivery_zip_code, delivery_city, delivery_state, delivery_country, delivery_salutation, delivery_title, delivery_suffix_1/2, delivery_personal
- Postfach: postbox, postbox_zip_code, postbox_city, postbox_state, postbox_country
- Kontakt: emails[] (z.B. ["kunde@example.de"]), phone_1, phone_2, mobile, fax, internet (Website)
- Steuer: tax_number, vat_identifier (USt-IdNr), tax_options
- Bank: bank_account_owner, bank_iban, bank_bic, bank_name, bank_code, bank_account, sepa_agreement, sepa_agreement_date, sepa_mandate_reference
- Zahlung: due_in_days, grace_period, cash_discount, cash_discount_type (AMOUNT|PERCENT), cash_allowance, cash_allowance_days, sale_price_level (1-5), payment_options
- Handelsregister: court, court_registry_number, since_date (YYYY-MM-DD)
- Gruppierung: group_id (Hauptgruppe), additional_groups_ids[]
- Lieferant: supplier_number, foreign_supplier_number
- Custom: number (Kundennummer manuell), display_name, note (interne Notiz), info_1, info_2, buyer_reference, document_pdf_type, acquire_options, archived

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
            'data' => [
              'type' => 'object',
              'description' => 'Kunden-Daten. Siehe Tool-Description für alle Felder. Beispiel: {"company_name": "Muster GmbH", "street": "Hauptstr. 1", "zip_code": "40213", "city": "Düsseldorf", "country": "DE", "emails": ["info@muster.de"]}.',
              'additionalProperties' => true,
            ],
          ],
          'required' => [
            0 => 'data',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['data'])) {
            return $guard;
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->createCustomer($context->user, $arguments['data']);
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
              2 => 'create',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}