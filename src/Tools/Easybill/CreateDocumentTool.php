<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class CreateDocumentTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.document.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
POST /documents — Beleg erstellen.

PFLICHT: data.type (INVOICE/OFFER/CREDIT/DELIVERY_NOTE/ORDER_CONFIRMATION/PAID/REMINDER/STORNO/CHARGE/CHARGE_CONFIRM/PROFORMA/LETTER).
EMPFOHLEN: data.customer_id (numerisch) ODER data.address (Inline-Adresse).
WICHTIG: Alle Geldbeträge sind Integer-Cent (185000 = 1.850,00 €), niemals Euro/Float.

Häufige data-Felder:
- Bezug: type, customer_id, contact_id, project_id, external_id, order_number, ref_id, buyer_reference
- Datum: document_date (YYYY-MM-DD), due_in_days, grace_period
- Text: title, text_prefix, text (Hauptbody), text_tax, item_notes
- Adresse: address {company_name, first_name, last_name, street, zip_code, city, country, state, salutation, title}; delivery_* und use_shipping_address für abweichende Lieferadresse
- Items: items[] mit type=POSITION|TEXT|SUBTOTAL, description, quantity, single_price_net (Cent), vat_percent, position_id (Verknüpfung zum Stamm-Artikel), unit, number, booking_account, cost_price_net (Cent), discount, discount_type, document_note
- Rabatt (Beleg): discount (Cent oder %), discount_type=AMOUNT|PERCENT
- Zahlung: cash_allowance, cash_allowance_days, cash_allowance_text, payment_options, payment_link_enabled, bank_debit_form
- Status: is_draft (false = direkt finalisiert), is_archive
- PDF: pdf_template (DE/EN/…), file_format_config, attachment_ids[]
- Steuer/Länder: currency (EUR), billing_country, shipping_country, fulfillment_country, vat_country, vat_option, is_oss, calc_vat_from
- Custom: advanced_data_fields, info_1, info_2

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
              'description' => 'Beleg-Daten. Siehe Tool-Description für alle Felder. Mindest: type. Beispiel: {"type": "OFFER", "customer_id": 12345, "items": [{"type": "POSITION", "description": "Beratung", "quantity": 1, "single_price_net": 150000, "vat_percent": 19}]}.',
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
            $result = $svc->createDocument($context->user, $arguments['data']);
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
              1 => 'documents',
              2 => 'create',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}