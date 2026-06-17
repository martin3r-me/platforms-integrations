<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class CreateCustomerGroupTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.customer-group.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
POST /customer-groups — Kundengruppe anlegen.

Kundengruppen bündeln gemeinsame Defaults (Zahlungsziel, Rabatt, Preisstufe, Textvorlagen). Ein Kunde kann genau eine Hauptgruppe (group_id) und zusätzliche Gruppen (additional_groups_ids) haben.

PFLICHT: name.

WICHTIG: discount bei discount_type=AMOUNT ist Integer-Cent; bei PERCENT Prozentwert (1–100).

Häufige data-Felder:
- Stamm: name (Pflicht), archived (false)
- Zahlung: due_in_days (Zahlungsziel in Tagen), cash_allowance, cash_allowance_days, payment_options
- Preise/Rabatt: sale_price_level (1-5 — verweist auf Preisstufe der Positionen), discount, discount_type (AMOUNT|PERCENT)
- Verknüpfung: text_template_ids[] (Standard-Textvorlagen für Belege), additional_groups_ids[] (weitere Gruppen, die mit dieser kombiniert werden)
- Custom: buyer_reference, info_1, info_2

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
              'description' => 'Gruppen-Daten. Siehe Tool-Description. Beispiel: {"name": "Premium-Kunden", "due_in_days": 7, "sale_price_level": 2, "discount": 5, "discount_type": "PERCENT"}.',
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

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->createCustomerGroup($context->user, $arguments['data']);
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
              1 => 'customer-groups',
              2 => 'create',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}