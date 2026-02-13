<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;

class LexwareOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /overview - Beschreibt alle verfügbaren Lexware/LexOffice Tools, Konzepte und Workflows. Immer zuerst aufrufen, um einen Überblick zu bekommen.';
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
            'module' => 'Lexware / LexOffice Integration',
            'description' => 'Anbindung an die Lexware/LexOffice Buchhaltungs-API. Alle Daten werden live von der externen Lexware API abgerufen. Der Benutzer muss eine aktive Lexware-Verbindung (IntegrationConnection) konfiguriert haben.',
            'concepts' => [
                'contacts' => 'Kunden und Lieferanten. Basis für Rechnungen, Angebote etc.',
                'invoices' => 'Rechnungen. Können erstellt, finalisiert und als PDF gerendert werden.',
                'quotations' => 'Angebote an Kunden.',
                'order_confirmations' => 'Auftragsbestätigungen.',
                'credit_notes' => 'Gutschriften.',
                'delivery_notes' => 'Lieferscheine.',
                'dunnings' => 'Mahnungen für überfällige Rechnungen.',
                'articles' => 'Artikel/Produkte im Lexware-System.',
                'payments' => 'Zahlungseingänge und -ausgänge.',
                'voucherlist' => 'Zentrale Belegliste über alle Belegarten hinweg.',
                'down_payment_invoices' => 'Anzahlungsrechnungen.',
                'recurring_templates' => 'Wiederkehrende Vorlagen für automatische Belegerstellung.',
                'reference_data' => 'Stammdaten: Länder, Buchungskategorien, Zahlungsbedingungen.',
            ],
            'workflows' => [
                'invoice_creation' => '1. Kontakt anlegen/finden (integrations.lexware.contacts.GET/POST) → 2. Rechnung erstellen (integrations.lexware.invoices.POST) → 3. Finalisieren (integrations.lexware.invoices.finalize.POST) → 4. PDF rendern (integrations.lexware.invoices.pdf.GET)',
                'quotation_to_invoice' => '1. Angebot erstellen (integrations.lexware.quotations.POST) → 2. Bei Annahme: Rechnung erstellen mit gleichen Daten',
                'dunning_process' => '1. Überfällige Rechnungen finden (integrations.lexware.voucherlist.GET mit Status-Filter) → 2. Mahnung erstellen (integrations.lexware.dunnings.POST)',
            ],
            'pagination' => 'Listen-Endpunkte unterstützen page (0-basiert) und size (max 250, default 25) Parameter.',
            'tools' => [
                'overview' => 'integrations.lexware.overview.GET',
                'profile' => 'integrations.lexware.profile.GET',
                'voucherlist' => 'integrations.lexware.voucherlist.GET',
                'contacts' => ['integrations.lexware.contacts.GET', 'integrations.lexware.contact.GET', 'integrations.lexware.contacts.POST', 'integrations.lexware.contacts.PUT', 'integrations.lexware.contacts.DELETE'],
                'invoices' => ['integrations.lexware.invoices.GET', 'integrations.lexware.invoice.GET', 'integrations.lexware.invoices.POST', 'integrations.lexware.invoices.finalize.POST', 'integrations.lexware.invoices.pdf.GET'],
                'quotations' => ['integrations.lexware.quotations.GET', 'integrations.lexware.quotation.GET', 'integrations.lexware.quotations.POST'],
                'order_confirmations' => ['integrations.lexware.order_confirmations.GET', 'integrations.lexware.order_confirmation.GET', 'integrations.lexware.order_confirmations.POST'],
                'credit_notes' => ['integrations.lexware.credit_notes.GET', 'integrations.lexware.credit_note.GET', 'integrations.lexware.credit_notes.POST'],
                'delivery_notes' => ['integrations.lexware.delivery_notes.GET', 'integrations.lexware.delivery_note.GET', 'integrations.lexware.delivery_notes.POST'],
                'dunnings' => ['integrations.lexware.dunnings.GET', 'integrations.lexware.dunning.GET', 'integrations.lexware.dunnings.POST'],
                'articles' => ['integrations.lexware.articles.GET', 'integrations.lexware.article.GET', 'integrations.lexware.articles.POST', 'integrations.lexware.articles.PUT'],
                'payments' => 'integrations.lexware.payments.GET',
                'reference_data' => ['integrations.lexware.countries.GET', 'integrations.lexware.posting_categories.GET', 'integrations.lexware.payment_conditions.GET'],
                'down_payment_invoices' => ['integrations.lexware.down_payment_invoices.GET', 'integrations.lexware.down_payment_invoice.GET'],
                'recurring_templates' => ['integrations.lexware.recurring_templates.GET', 'integrations.lexware.recurring_template.GET'],
            ],
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['lexware', 'overview', 'help'],
            'read_only' => true,
            'requires_auth' => false,
            'risk_level' => 'safe',
        ];
    }
}
