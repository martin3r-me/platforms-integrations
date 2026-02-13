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
        return 'lexware.overview.GET';
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
                'invoice_creation' => '1. Kontakt anlegen/finden (lexware.contacts.GET/POST) → 2. Rechnung erstellen (lexware.invoices.POST) → 3. Finalisieren (lexware.invoices.finalize.POST) → 4. PDF rendern (lexware.invoices.pdf.GET)',
                'quotation_to_invoice' => '1. Angebot erstellen (lexware.quotations.POST) → 2. Bei Annahme: Rechnung erstellen mit gleichen Daten',
                'dunning_process' => '1. Überfällige Rechnungen finden (lexware.voucherlist.GET mit Status-Filter) → 2. Mahnung erstellen (lexware.dunnings.POST)',
            ],
            'pagination' => 'Listen-Endpunkte unterstützen page (0-basiert) und size (max 250, default 25) Parameter.',
            'tools' => [
                'overview' => 'lexware.overview.GET',
                'profile' => 'lexware.profile.GET',
                'voucherlist' => 'lexware.voucherlist.GET',
                'contacts' => ['lexware.contacts.GET', 'lexware.contact.GET', 'lexware.contacts.POST', 'lexware.contacts.PUT', 'lexware.contacts.DELETE'],
                'invoices' => ['lexware.invoices.GET', 'lexware.invoice.GET', 'lexware.invoices.POST', 'lexware.invoices.finalize.POST', 'lexware.invoices.pdf.GET'],
                'quotations' => ['lexware.quotations.GET', 'lexware.quotation.GET', 'lexware.quotations.POST'],
                'order_confirmations' => ['lexware.order_confirmations.GET', 'lexware.order_confirmation.GET', 'lexware.order_confirmations.POST'],
                'credit_notes' => ['lexware.credit_notes.GET', 'lexware.credit_note.GET', 'lexware.credit_notes.POST'],
                'delivery_notes' => ['lexware.delivery_notes.GET', 'lexware.delivery_note.GET', 'lexware.delivery_notes.POST'],
                'dunnings' => ['lexware.dunnings.GET', 'lexware.dunning.GET', 'lexware.dunnings.POST'],
                'articles' => ['lexware.articles.GET', 'lexware.article.GET', 'lexware.articles.POST', 'lexware.articles.PUT'],
                'payments' => 'lexware.payments.GET',
                'reference_data' => ['lexware.countries.GET', 'lexware.posting_categories.GET', 'lexware.payment_conditions.GET'],
                'down_payment_invoices' => ['lexware.down_payment_invoices.GET', 'lexware.down_payment_invoice.GET'],
                'recurring_templates' => ['lexware.recurring_templates.GET', 'lexware.recurring_template.GET'],
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
