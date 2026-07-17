<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class CreatePositionTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.position.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
POST /positions — Stamm-Artikel (Artikelstamm) anlegen.

ABGRENZUNG: Dies legt einen wiederverwendbaren Stamm-Artikel an — NICHT eine Position auf einem konkreten Beleg. Belegpositionen sind in easybill im Beleg eingebettet (data.items[]) und werden über integrations.easybill.document.POST/PUT gepflegt. Ein einzelnes Item eines bestehenden Belegs lässt sich API-seitig nicht separat anlegen/ändern — dazu den ganzen Beleg per PUT mit vollständigem items[] senden (items[] ersetzt die alte Liste komplett).

WICHTIG: sale_price und purchase_price sind Integer-Cent (185000 = 1.850,00 €), niemals Euro/Float.
EMPFOHLEN: mindestens description und sale_price.

Häufige data-Felder:
- Stammdaten: type (PRODUCT|SERVICE|TEXT), number (Artikelnummer, auto wenn leer), description, description_intern, group_id (Position-Group), bundle_id
- Preise: sale_price (Netto-VK in Cent), sale_price_gross (Brutto-VK in Cent), sale_price_brutto_or_net_type (NET|GROSS — welcher Preis maßgeblich ist), purchase_price (Einkaufspreis in Cent), vat_percent (z.B. 19 / 7 / 0)
- Einheit: unit (z.B. "Stück", "h", "kg"), unit_factor
- Lager: stock_initial, stock_min, stock_keeping (true = Bestandsführung), weight, ean
- Buchhaltung: booking_account (Sachkonto), tax_account, cost_center_1, cost_center_2, export_cost_1, export_cost_2
- Rabatt (default): discount, discount_type (AMOUNT|PERCENT)
- Partner: partner_id (Lieferant)
- Custom: info_1, info_2

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
              'description' => 'Artikel-Daten. Siehe Tool-Description. Beispiel: {"type": "SERVICE", "description": "Beratungsstunde", "sale_price": 12500, "vat_percent": 19, "unit": "h"}.',
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
            $result = $svc->createPosition($context->user, $arguments['data']);
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
              1 => 'positions',
              2 => 'create',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}