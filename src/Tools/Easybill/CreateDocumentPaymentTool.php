<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class CreateDocumentPaymentTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.document-payment.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
POST /document-payments — Zahlungseingang/-ausgang zu einem Beleg erfassen.

PFLICHT: data.document_id (ID des Belegs), data.amount (Integer-Cent), data.date (YYYY-MM-DD).
WICHTIG: amount ist Integer-Cent (2499000 = 24.990,00 €), niemals Euro/Float.
Negative amount = Rückzahlung/Erstattung.

Häufige data-Felder:
- Bezug: document_id (Pflicht), reference (Verwendungszweck/Referenz)
- Betrag: amount (Cent, Pflicht), date (Pflicht, YYYY-MM-DD)
- Zahlungsart: payment_type (z.B. BANK_TRANSFER, CASH, CREDIT_CARD, PAYPAL, DIRECT_DEBIT, CHECK, OTHER), type (PAYMENT|REFUND|…)
- Fremdwährung: foreign_amount (Cent), foreign_currency (z.B. USD), foreign_exchange_rate
- Custom: notes

Hinweis: easybill aktualisiert paid_amount und ggf. den Status des verknüpften Belegs automatisch.

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
              'description' => 'Zahlungs-Daten. Siehe Tool-Description. Beispiel: {"document_id": 123456, "amount": 249900, "date": "2026-06-17", "payment_type": "BANK_TRANSFER", "reference": "Rechnung 2026-001"}.',
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
            $result = $svc->createDocumentPayment($context->user, $arguments['data']);
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
              1 => 'document-payments',
              2 => 'create',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}