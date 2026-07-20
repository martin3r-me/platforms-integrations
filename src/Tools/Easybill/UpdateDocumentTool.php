<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class UpdateDocumentTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.document.PUT';
    }

    public function getDescription(): string
    {
        return <<<TXT
PUT /documents/{id} — Beleg aktualisieren.

Voll-Update: easybill ersetzt das Dokument mit dem übergebenen Payload — sende daher idealerweise zuerst per GET /documents/{id} den aktuellen Stand, ändere die gewünschten Felder, und schicke das komplette Objekt zurück. Bei Item-Listen werden vorhandene Positionen NICHT diff-gemergt; das übergebene items[] ersetzt die alten Positionen vollständig.

WICHTIG: Alle Geldbeträge sind Integer-Cent (185000 = 1.850,00 €), niemals Euro/Float.

Häufige data-Felder (selbe Struktur wie POST /documents):
- Bezug: type, customer_id, contact_id, project_id, external_id, order_number, ref_id, buyer_reference
- Datum: document_date, due_in_days, grace_period
- Text: title, text_prefix, text, text_tax, item_notes
- Adresse: address {…}; delivery_*, use_shipping_address
- Items: items[] mit type, description, quantity, single_price_net (Cent), vat_percent, position_id, unit, number, booking_account, cost_price_net, discount, discount_type, document_note. Hinweis: position_id kopiert Artikelwerte inkl. booking_account (Sachkonto); freie Positionen booking_account selbst setzen.
- Leistungszeitraum: service_date {type=DEFAULT|SERVICE|DELIVERY, date ODER date_from+date_to, text}.
- WIEDERKEHREND steuern: recurring_options.status = RUNNING|PAUSE|STOP|WAITING (Abo pausieren/stoppen), next_date, interval, frequency, end_date_or_count. target_type ist nach Anlage NICHT änderbar.
- Rabatt (Beleg): discount, discount_type=AMOUNT|PERCENT
- Zahlung: cash_allowance, cash_allowance_days, cash_allowance_text, payment_options, payment_link_enabled, bank_debit_form
- Status: is_draft ist READ-ONLY — finalisieren via integrations.easybill.document.done. is_archive setzbar.
- PDF: pdf_template, file_format_config, attachment_ids[]
- Steuer/Länder: currency, billing_country, shipping_country, fulfillment_country, vat_country, vat_option, is_oss
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
            'document_id' => [
              'type' => 'integer',
              'description' => 'ID des Belegs',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Beleg-Daten — vollständiger Stand des Belegs, nicht diff. Siehe Tool-Description für alle Felder.',
              'additionalProperties' => true,
            ],
          ],
          'required' => [
            0 => 'document_id',
            1 => 'data',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['document_id', 'data'])) {
            return $guard;
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->updateDocument($context->user, (int) $arguments['document_id'], $arguments['data']);
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
              2 => 'update',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}