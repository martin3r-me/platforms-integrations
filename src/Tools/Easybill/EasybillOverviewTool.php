<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;

class EasybillOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.overview';
    }

    public function getDescription(): string
    {
        return 'Übersicht aller verfügbaren easybill Tools mit Endpunkten und kurzen Beschreibungen. Nützlich als Einstieg, um zu entscheiden, welches Tool für einen Anwendungsfall geeignet ist.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::success([
            'base_url' => 'https://api.easybill.de/rest/v1',
            'auth' => 'Bearer Token (api_key in IntegrationConnection.credentials)',
            'rate_limits' => ['PLUS' => '10 req/min', 'BUSINESS' => '60 req/min'],
            'money_format' => 'Alle Geldbeträge sind Integer-Cent (185000 = 1.850,00 €). Gilt für single_price_net/single_price_gross/total_*/amount/sale_price/purchase_price/discount(AMOUNT). Niemals Euro/Float senden — easybill rechnet sonst 100× zu klein.',
            'resources' => [
                'customers' => ['list', 'get', 'create', 'update', 'delete'],
                'customer-contacts' => ['list', 'get', 'create', 'update', 'delete'],
                'customer-groups' => ['list', 'get', 'create', 'update', 'delete'],
                'documents' => ['list', 'get', 'create', 'update', 'delete', 'complete', 'cancel', 'send', 'convert', 'pdf', 'jpg', 'download'],
                'document-payments' => ['list', 'get', 'create', 'delete'],
                'incoming-documents' => ['list', 'get', 'files.list', 'file.download', '(read-only, Eingangsbelege/Lieferantenrechnungen)'],
                'positions' => ['list', 'get', 'create', 'update', 'delete'],
                'position-groups' => ['list', 'get', 'create', 'update', 'delete'],
                'discounts.position' => ['list', 'get', 'create', 'update', 'delete'],
                'discounts.position-group' => ['list', 'get', 'create', 'update', 'delete'],
                'projects' => ['list', 'get', 'create', 'update', 'delete'],
                'tasks' => ['list', 'get', 'create', 'update', 'delete'],
                'time-trackings' => ['list', 'get', 'create', 'update', 'delete'],
                'text-templates' => ['list', 'get', 'create', 'update', 'delete'],
                'attachments' => ['list', 'get', 'create', 'update', 'delete', 'content'],
                'post-boxes' => ['list', 'get', 'delete'],
                'sepa-payments' => ['list', 'get', 'create', 'update', 'delete'],
                'stocks' => ['list', 'get', 'create'],
                'serial-numbers' => ['list', 'get', 'create', 'delete'],
                'logins' => ['list', 'get'],
                'webhooks' => ['list', 'get', 'create', 'update', 'delete'],
            ],
            'document_types' => ['INVOICE', 'OFFER', 'CREDIT', 'DELIVERY_NOTE', 'ORDER_CONFIRMATION', 'PAID', 'REMINDER', 'STORNO', 'CHARGE', 'CHARGE_CONFIRM', 'PROFORMA', 'LETTER'],
            'send_types' => ['email', 'fax', 'post', 'sms', 'whatsapp'],
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['easybill', 'overview', 'reference'],
            'read_only' => true,
            'requires_auth' => false,
            'risk_level' => 'safe',
        ];
    }
}
