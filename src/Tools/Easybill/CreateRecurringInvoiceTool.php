<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

/**
 * Wiederkehrende Rechnung (Abo) in easybill anlegen.
 *
 * easybill hat KEINEN separaten Recurring-Endpunkt: eine wiederkehrende Rechnung
 * ist ein Beleg (POST /documents) mit einem recurring_options-Objekt. easybill
 * erzeugt daraus automatisch die einzelnen Belege nach Plan.
 */
class CreateRecurringInvoiceTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.recurring-invoice.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
        Wiederkehrende Rechnung (Abo) anlegen — technisch ein Beleg (POST /documents) mit recurring_options.
        easybill erzeugt daraus automatisch die einzelnen Belege nach Plan. `data` enthält die normale
        Beleg-Struktur PLUS recurring_options.

        PFLICHT: data.recurring_options. EMPFOHLEN: data.customer_id, data.items[].
        Geldbeträge sind Integer-Cent (185000 = 1.850,00 €).

        recurring_options (Wiederkehr-Plan):
        - next_date [PFLICHT]: nächste/erste Ausführung, YYYY-MM-DD, MUSS in der Zukunft liegen
        - frequency [PFLICHT]: DAILY | WEEKLY | MONTHLY | YEARLY
        - interval: Vielfaches der frequency (z.B. frequency=MONTHLY + interval=3 → alle 3 Monate). Default 1
        - frequency_special: LASTDAYOFMONTH (immer letzter Tag des Monats)
        - end_date_or_count: Enddatum (YYYY-MM-DD) ODER Anzahl Ausführungen. Leer = unbegrenzt
        - status: RUNNING (aktiv) | PAUSE | STOP | WAITING. Default RUNNING
        - target_type: INVOICE | CREDIT | ORDER | OFFER — Belegtyp der erzeugten Belege (nach Anlage NICHT änderbar). Für Rechnungen: INVOICE
        - as_draft: true = erzeugte Belege als Entwurf (müssen manuell finalisiert werden); false = direkt fertig
        - is_notify + send_as (EMAIL|FAX|POST): automatischer Versand
        - is_paid + paid_date_option (created_date|due_date|next_valid_date): direkt als bezahlt markieren
        - is_sepa + sepa_local_instrument (CORE|B2B) + sepa_sequence_type (FRST|OOFF|FNAL|RCUR) + sepa_reference: SEPA-Lastschrift

        LEISTUNGSZEITRAUM: data.service_date {type:"SERVICE", date_from:"YYYY-MM-DD", date_to:"YYYY-MM-DD", text}.
        BUCHUNGSKONTO: an der Position (items[].booking_account). Mit items[].position_id wird das Sachkonto des
        Stamm-Artikels automatisch übernommen; freie Positionen booking_account selbst setzen.

        Beispiel data:
        {"type":"INVOICE","customer_id":12345,
         "items":[{"type":"POSITION","description":"Wartung monatlich","quantity":1,"single_price_net":9900,"vat_percent":19,"position_id":678}],
         "service_date":{"type":"SERVICE","date_from":"2026-08-01","date_to":"2026-08-31"},
         "recurring_options":{"next_date":"2026-08-01","frequency":"MONTHLY","interval":1,"target_type":"INVOICE","status":"RUNNING","as_draft":false}}
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
                    'description' => 'Beleg-Daten inkl. recurring_options (Pflicht). Feldliste + Beispiel siehe Tool-Description.',
                    'additionalProperties' => true,
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

        if ($guard = $this->guardRequired($arguments, ['data'])) {
            return $guard;
        }

        $data = $arguments['data'];
        if (!is_array($data) || empty($data['recurring_options'])) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'data.recurring_options fehlt — ohne Wiederkehr-Plan ist es keine wiederkehrende Rechnung. '
                . 'Mindestens next_date (Zukunft) + frequency setzen. Für einen einmaligen Beleg das Tool '
                . 'integrations.easybill.document.POST nutzen.'
            );
        }

        // Belegtyp der Vorlage: Standard INVOICE, wenn nicht gesetzt.
        if (empty($data['type'])) {
            $data['type'] = 'INVOICE';
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->createDocument($context->user, $data);

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
            'tags' => ['easybill', 'documents', 'recurring', 'abo', 'wiederkehrend', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
