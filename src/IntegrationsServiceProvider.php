<?php

namespace Platform\Integrations;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/integrations.php', 'integrations');
    }

    public function boot(): void
    {
        // Schritt 1: Config laden (hier nochmals wie in media/printing, damit config()->has() sicher ist)
        $this->mergeConfigFrom(__DIR__ . '/../config/integrations.php', 'integrations');

        // Schritt 2: Modul registrieren (nur wenn Module-System verfügbar)
        if (
            config()->has('integrations.routing') &&
            config()->has('integrations.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'integrations',
                'title'      => 'Integrations',
                'group'      => 'admin',
                'routing'    => config('integrations.routing'),
                'guard'      => config('integrations.guard'),
                'navigation' => config('integrations.navigation'),
                'sidebar'    => config('integrations.sidebar'),
            ]);
        }

        // Schritt 3: Routes laden
        // OAuth-Routes müssen immer verfügbar sein
        // Callback benötigt auch Auth, da User eingeloggt sein sollte
        Route::prefix('integrations')
            ->middleware(['web', 'auth']) // Beide Routes benötigen Auth
            ->group(function () {
                Route::get('/oauth2/{integrationKey}/start', [\Platform\Integrations\Http\Controllers\OAuth2Controller::class, 'start'])
                    ->name('integrations.oauth2.start');
                Route::get('/oauth2/{integrationKey}/callback', [\Platform\Integrations\Http\Controllers\OAuth2Controller::class, 'callback'])
                    ->name('integrations.oauth2.callback');
            });

        // Lexware API Routes
        Route::prefix('api/integrations/lexware')
            ->middleware(['web', 'auth'])
            ->group(function () {
                Route::get('/profile', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'profile'])
                    ->name('integrations.lexware.profile');
                Route::get('/test', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'test'])
                    ->name('integrations.lexware.test');
                // Belegliste (Voucherlist) - Zentraler Endpunkt für alle Belege
                Route::get('/voucherlist', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'voucherlist'])
                    ->name('integrations.lexware.voucherlist');
                // Belege (Vouchers) - CRUD Endpunkte für Eingangs- und Ausgangsbelege
                Route::get('/vouchers', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'vouchers'])
                    ->name('integrations.lexware.vouchers');
                Route::get('/vouchers/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'voucher'])
                    ->name('integrations.lexware.voucher');
                Route::post('/vouchers', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createVoucher'])
                    ->name('integrations.lexware.vouchers.create');
                Route::put('/vouchers/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'updateVoucher'])
                    ->name('integrations.lexware.vouchers.update');
                Route::post('/vouchers/{id}/files', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'uploadFileToVoucher'])
                    ->name('integrations.lexware.vouchers.files.upload');
                // Kontakte (Contacts) - CRUD Endpunkte
                Route::get('/contacts', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'contacts'])
                    ->name('integrations.lexware.contacts');
                Route::get('/contacts/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'contact'])
                    ->name('integrations.lexware.contact');
                Route::post('/contacts', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createContact'])
                    ->name('integrations.lexware.contacts.create');
                Route::put('/contacts/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'updateContact'])
                    ->name('integrations.lexware.contacts.update');
                Route::delete('/contacts/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'deleteContact'])
                    ->name('integrations.lexware.contacts.delete');
                // Rechnungen (Invoices) - CRUD Endpunkte
                Route::get('/invoices', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'invoices'])
                    ->name('integrations.lexware.invoices');
                Route::post('/invoices', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createInvoice'])
                    ->name('integrations.lexware.invoices.create');
                Route::get('/invoices/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'invoice'])
                    ->name('integrations.lexware.invoice');
                Route::post('/invoices/{id}/finalize', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'finalizeInvoice'])
                    ->name('integrations.lexware.invoices.finalize');
                Route::get('/invoices/{id}/pdf', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'invoicePdf'])
                    ->name('integrations.lexware.invoices.pdf');
                Route::get('/invoices/{id}/download', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downloadInvoice'])
                    ->name('integrations.lexware.invoices.download');
                Route::get('/invoices/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'invoiceDeeplink'])
                    ->name('integrations.lexware.invoices.deeplink');
                // Angebote (Quotations) - CRUD Endpunkte
                Route::get('/quotations', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'quotations'])
                    ->name('integrations.lexware.quotations');
                Route::post('/quotations', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createQuotation'])
                    ->name('integrations.lexware.quotations.create');
                Route::get('/quotations/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'quotation'])
                    ->name('integrations.lexware.quotation');
                Route::get('/quotations/{id}/pdf', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'quotationPdf'])
                    ->name('integrations.lexware.quotations.pdf');
                Route::get('/quotations/{id}/download', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downloadQuotation'])
                    ->name('integrations.lexware.quotations.download');
                Route::get('/quotations/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'quotationDeeplink'])
                    ->name('integrations.lexware.quotations.deeplink');
                // Auftragsbestätigungen (Order Confirmations) - CRUD Endpunkte
                Route::get('/order-confirmations', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'orderConfirmations'])
                    ->name('integrations.lexware.order-confirmations');
                Route::post('/order-confirmations', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createOrderConfirmation'])
                    ->name('integrations.lexware.order-confirmations.create');
                Route::get('/order-confirmations/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'orderConfirmation'])
                    ->name('integrations.lexware.order-confirmation');
                Route::get('/order-confirmations/{id}/pdf', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'orderConfirmationPdf'])
                    ->name('integrations.lexware.order-confirmations.pdf');
                Route::get('/order-confirmations/{id}/download', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downloadOrderConfirmation'])
                    ->name('integrations.lexware.order-confirmations.download');
                Route::get('/order-confirmations/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'orderConfirmationDeeplink'])
                    ->name('integrations.lexware.order-confirmations.deeplink');

                // Gutschriften (Credit Notes) - CRUD Endpunkte
                Route::get('/credit-notes', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'creditNotes'])
                    ->name('integrations.lexware.credit-notes');
                Route::post('/credit-notes', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createCreditNote'])
                    ->name('integrations.lexware.credit-notes.create');
                Route::get('/credit-notes/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'creditNote'])
                    ->name('integrations.lexware.credit-note');
                Route::get('/credit-notes/{id}/pdf', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'creditNotePdf'])
                    ->name('integrations.lexware.credit-notes.pdf');
                Route::get('/credit-notes/{id}/download', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downloadCreditNote'])
                    ->name('integrations.lexware.credit-notes.download');
                Route::get('/credit-notes/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'creditNoteDeeplink'])
                    ->name('integrations.lexware.credit-notes.deeplink');

                // Lieferscheine (Delivery Notes) - CRUD Endpunkte
                Route::get('/delivery-notes', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'deliveryNotes'])
                    ->name('integrations.lexware.delivery-notes');
                Route::post('/delivery-notes', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createDeliveryNote'])
                    ->name('integrations.lexware.delivery-notes.create');
                Route::get('/delivery-notes/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'deliveryNote'])
                    ->name('integrations.lexware.delivery-note');
                Route::get('/delivery-notes/{id}/pdf', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'deliveryNotePdf'])
                    ->name('integrations.lexware.delivery-notes.pdf');
                Route::get('/delivery-notes/{id}/download', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downloadDeliveryNote'])
                    ->name('integrations.lexware.delivery-notes.download');
                Route::get('/delivery-notes/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'deliveryNoteDeeplink'])
                    ->name('integrations.lexware.delivery-notes.deeplink');

                // Mahnungen (Dunnings) - CRUD Endpunkte
                Route::get('/dunnings', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'dunnings'])
                    ->name('integrations.lexware.dunnings');
                Route::post('/dunnings', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createDunning'])
                    ->name('integrations.lexware.dunnings.create');
                Route::get('/dunnings/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'dunning'])
                    ->name('integrations.lexware.dunning');
                Route::get('/dunnings/{id}/pdf', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'dunningPdf'])
                    ->name('integrations.lexware.dunnings.pdf');
                Route::get('/dunnings/{id}/download', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downloadDunning'])
                    ->name('integrations.lexware.dunnings.download');
                Route::get('/dunnings/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'dunningDeeplink'])
                    ->name('integrations.lexware.dunnings.deeplink');

                // Anzahlungsrechnungen (Down Payment Invoices) - Endpunkte
                Route::get('/down-payment-invoices', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downPaymentInvoices'])
                    ->name('integrations.lexware.down-payment-invoices');
                Route::get('/down-payment-invoices/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downPaymentInvoice'])
                    ->name('integrations.lexware.down-payment-invoice');
                Route::get('/down-payment-invoices/{id}/pdf', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downPaymentInvoicePdf'])
                    ->name('integrations.lexware.down-payment-invoices.pdf');
                Route::get('/down-payment-invoices/{id}/download', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downloadDownPaymentInvoice'])
                    ->name('integrations.lexware.down-payment-invoices.download');
                Route::get('/down-payment-invoices/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downPaymentInvoiceDeeplink'])
                    ->name('integrations.lexware.down-payment-invoices.deeplink');

                // Zahlungen (Payments) - Endpunkte
                Route::get('/payments', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'payments'])
                    ->name('integrations.lexware.payments');
                Route::get('/payments/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'payment'])
                    ->name('integrations.lexware.payment');

                // Artikel (Articles) - CRUD Endpunkte
                Route::get('/articles', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'articles'])
                    ->name('integrations.lexware.articles');
                Route::get('/articles/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'article'])
                    ->name('integrations.lexware.article');
                Route::post('/articles', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createArticle'])
                    ->name('integrations.lexware.articles.create');
                Route::put('/articles/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'updateArticle'])
                    ->name('integrations.lexware.articles.update');
                Route::delete('/articles/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'deleteArticle'])
                    ->name('integrations.lexware.articles.delete');

                // Länder (Countries) - Liste aller verfügbaren Länder
                Route::get('/countries', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'countries'])
                    ->name('integrations.lexware.countries');

                // Buchungskategorien (Posting Categories) - Liste aller verfügbaren Kategorien
                Route::get('/posting-categories', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'postingCategories'])
                    ->name('integrations.lexware.posting-categories');

                // Zahlungsbedingungen (Payment Conditions) - Liste aller verfügbaren Zahlungsbedingungen
                Route::get('/payment-conditions', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'paymentConditions'])
                    ->name('integrations.lexware.payment-conditions');

                // Druckvorlagen (Print Layouts) - Liste aller verfügbaren Druckvorlagen
                Route::get('/print-layouts', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'printLayouts'])
                    ->name('integrations.lexware.print-layouts');

                // Event-Subscriptions (Webhooks) - CRUD Endpunkte
                Route::get('/event-subscriptions', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'eventSubscriptions'])
                    ->name('integrations.lexware.event-subscriptions');
                Route::get('/event-subscriptions/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'eventSubscription'])
                    ->name('integrations.lexware.event-subscription');
                Route::post('/event-subscriptions', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'createEventSubscription'])
                    ->name('integrations.lexware.event-subscriptions.create');
                Route::delete('/event-subscriptions/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'deleteEventSubscription'])
                    ->name('integrations.lexware.event-subscriptions.delete');

                // Dateien (Files) - Upload und Download Endpunkte
                Route::post('/files', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'uploadFile'])
                    ->name('integrations.lexware.files.upload');
                Route::get('/files/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'downloadFile'])
                    ->name('integrations.lexware.files.download');
                Route::get('/files/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'fileDeeplink'])
                    ->name('integrations.lexware.files.deeplink');

                // Wiederkehrende Vorlagen (Recurring Templates) - Endpunkte
                Route::get('/recurring-templates', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'recurringTemplates'])
                    ->name('integrations.lexware.recurring-templates');
                Route::get('/recurring-templates/{id}', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'recurringTemplate'])
                    ->name('integrations.lexware.recurring-template');
                Route::get('/recurring-templates/{id}/deeplink', [\Platform\Integrations\Http\Controllers\LexwareController::class, 'recurringTemplateDeeplink'])
                    ->name('integrations.lexware.recurring-templates.deeplink');
            });

        // BuchhaltungsButler API Routes
        Route::prefix('api/integrations/buchhaltungsbutler')
            ->middleware(['web', 'auth'])
            ->group(function () {
                Route::get('/test', [\Platform\Integrations\Http\Controllers\BuchhaltungsbutlerController::class, 'test'])
                    ->name('integrations.buchhaltungsbutler.test');
                Route::get('/debtors', [\Platform\Integrations\Http\Controllers\BuchhaltungsbutlerController::class, 'debtors'])
                    ->name('integrations.buchhaltungsbutler.debtors');
            });

        // Sipgate API Routes (authentifizierte Endpunkte)
        Route::prefix('api/integrations/sipgate')
            ->middleware(['web', 'auth'])
            ->group(function () {
                // Connection Management
                Route::get('/test', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'test'])
                    ->name('integrations.sipgate.test');
                Route::delete('/disconnect', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'disconnect'])
                    ->name('integrations.sipgate.disconnect');

                // Account & User Info
                Route::get('/userinfo', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'userinfo'])
                    ->name('integrations.sipgate.userinfo');
                Route::get('/account', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'account'])
                    ->name('integrations.sipgate.account');
                Route::get('/balance', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'balance'])
                    ->name('integrations.sipgate.balance');
                Route::get('/users', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'users'])
                    ->name('integrations.sipgate.users');

                // Telefonnummern & Geräte
                Route::get('/numbers', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'numbers'])
                    ->name('integrations.sipgate.numbers');
                Route::get('/devices', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'devices'])
                    ->name('integrations.sipgate.devices');

                // Anrufe (Calls)
                Route::post('/calls', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'initiateCall'])
                    ->name('integrations.sipgate.calls.initiate');
                Route::delete('/calls/{sessionId}', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'hangupCall'])
                    ->name('integrations.sipgate.calls.hangup');

                // Anrufhistorie (History)
                Route::get('/history', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'history'])
                    ->name('integrations.sipgate.history');
                Route::get('/history/{id}', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'historyEntry'])
                    ->name('integrations.sipgate.history.entry');
                Route::put('/history/{id}/archive', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'archiveHistoryEntry'])
                    ->name('integrations.sipgate.history.archive');
                Route::delete('/history/{id}', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'deleteHistoryEntry'])
                    ->name('integrations.sipgate.history.delete');

                // SMS
                Route::post('/sms', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'sendSms'])
                    ->name('integrations.sipgate.sms.send');
                Route::get('/sms/extensions', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'smsExtensions'])
                    ->name('integrations.sipgate.sms.extensions');

                // Fax
                Route::post('/fax', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'sendFax'])
                    ->name('integrations.sipgate.fax.send');
                Route::get('/faxlines', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'faxlines'])
                    ->name('integrations.sipgate.faxlines');

                // Voicemail
                Route::get('/voicemails', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'voicemails'])
                    ->name('integrations.sipgate.voicemails');

                // Kontakte (Contacts)
                Route::get('/contacts', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'contacts'])
                    ->name('integrations.sipgate.contacts');
                Route::get('/contacts/{id}', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'contact'])
                    ->name('integrations.sipgate.contact');
                Route::post('/contacts', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'createContact'])
                    ->name('integrations.sipgate.contacts.create');
                Route::put('/contacts/{id}', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'updateContact'])
                    ->name('integrations.sipgate.contacts.update');
                Route::delete('/contacts/{id}', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'deleteContact'])
                    ->name('integrations.sipgate.contacts.delete');

                // Webhook-Konfiguration
                Route::get('/webhooks', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'getWebhooks'])
                    ->name('integrations.sipgate.webhooks');
                Route::put('/webhooks', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'setWebhooks'])
                    ->name('integrations.sipgate.webhooks.set');
                Route::delete('/webhooks', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'deleteWebhooks'])
                    ->name('integrations.sipgate.webhooks.delete');

                // Health & Metrics
                Route::get('/health', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'health'])
                    ->name('integrations.sipgate.health');
                Route::get('/metrics/tokens', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'tokenMetrics'])
                    ->name('integrations.sipgate.metrics.tokens');
                Route::get('/tokens/history', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'tokenHistory'])
                    ->name('integrations.sipgate.tokens.history');
            });

        // Sipgate Webhook Endpoint (NICHT authentifiziert - empfängt Push von Sipgate)
        Route::prefix('api/integrations/sipgate')
            ->middleware(['web']) // Kein 'auth' - Webhooks kommen von Sipgate
            ->group(function () {
                Route::post('/webhook', [\Platform\Integrations\Http\Controllers\SipgateController::class, 'webhook'])
                    ->name('integrations.sipgate.webhook');
            });

        // Connection Shares API Routes (Freigaben)
        Route::prefix('api/integrations/connections/{connection}/shares')
            ->middleware(['web', 'auth'])
            ->group(function () {
                Route::get('/', [\Platform\Integrations\Http\Controllers\ConnectionShareController::class, 'index'])
                    ->name('integrations.connections.shares.index');
                Route::post('/', [\Platform\Integrations\Http\Controllers\ConnectionShareController::class, 'store'])
                    ->name('integrations.connections.shares.store');
                Route::delete('/{share}', [\Platform\Integrations\Http\Controllers\ConnectionShareController::class, 'destroy'])
                    ->name('integrations.connections.shares.destroy');
            });

        // TimeEntry API Routes (Zeiterfassung)
        Route::prefix('api/integrations/time-entries')
            ->middleware(['web', 'auth'])
            ->group(function () {
                // Einzelne Zeiteinträge
                Route::get('/', [\Platform\Integrations\Http\Controllers\TimeEntryController::class, 'index'])
                    ->name('integrations.time-entries.index');
                Route::post('/', [\Platform\Integrations\Http\Controllers\TimeEntryController::class, 'store'])
                    ->name('integrations.time-entries.store');
                Route::get('/{id}', [\Platform\Integrations\Http\Controllers\TimeEntryController::class, 'show'])
                    ->name('integrations.time-entries.show')
                    ->where('id', '[0-9]+');

                // Bulk-Operationen
                Route::post('/bulk', [\Platform\Integrations\Http\Controllers\TimeEntryController::class, 'bulkStore'])
                    ->name('integrations.time-entries.bulk.store');
            });

        // Andere Routes über ModuleRouter (wenn Modul aktiv ist)
        if (PlatformCore::getModule('integrations')) {
            $routesPath = __DIR__ . '/../routes/web.php';
            ModuleRouter::group('integrations', function () use ($routesPath) {
                // Routes aus web.php laden (ohne OAuth-Routes, die sind bereits oben registriert)
                require $routesPath;
            });
        }

        // Schritt 4: Migrationen + Views + Livewire + Commands
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'integrations');
        $this->registerLivewireComponents();
        
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Integrations\Console\Commands\SyncFacebookPages::class,
                \Platform\Integrations\Console\Commands\SyncInstagramAccounts::class,
                \Platform\Integrations\Console\Commands\SyncWhatsAppAccounts::class,
                \Platform\Integrations\Console\Commands\SyncWhatsAppTemplates::class,
                \Platform\Integrations\Console\Commands\SyncGithubRepositories::class,
                \Platform\Integrations\Console\Commands\SyncGithubRepos::class,
                \Platform\Integrations\Console\Commands\SyncGithubData::class,
                \Platform\Integrations\Console\Commands\SyncMetaResources::class,
                \Platform\Integrations\Console\Commands\SyncLexwareContacts::class,
                \Platform\Integrations\Console\Commands\SeedIntegrations::class,
                \Platform\Integrations\Console\Commands\SyncSipgateAccounts::class,
                \Platform\Integrations\Console\Commands\SipgateRefreshTokens::class,
                \Platform\Integrations\Console\Commands\SipgateWebhookCleanup::class,
            ]);
        }

        // Schritt 4b: Periodischen Sync registrieren (Scheduler)
        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            $schedule->command('integrations:sync-meta-resources')->daily();
            $schedule->command('integrations:sync-whatsapp-accounts')->daily();
            $schedule->command('integrations:sync-github-data')->hourly();
        });

        // Schritt 5: LLM Tools registrieren
        $this->registerTools();

        // Schritt 6: Config publish
        $this->publishes([
            __DIR__ . '/../config/integrations.php' => config_path('integrations.php'),
        ], 'integrations-config');
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Overview
            $registry->register(new \Platform\Integrations\Tools\Lexware\LexwareOverviewTool());

            // Profile
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetProfileTool());

            // Voucherlist
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListVoucherlistTool());

            // Contacts (CRUD)
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListContactsTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetContactTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\CreateContactTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\UpdateContactTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\DeleteContactTool());

            // Invoices
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListInvoicesTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetInvoiceTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\CreateInvoiceTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\FinalizeInvoiceTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\RenderInvoicePdfTool());

            // Quotations
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListQuotationsTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetQuotationTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\CreateQuotationTool());

            // Order Confirmations
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListOrderConfirmationsTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetOrderConfirmationTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\CreateOrderConfirmationTool());

            // Credit Notes
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListCreditNotesTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetCreditNoteTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\CreateCreditNoteTool());

            // Delivery Notes
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListDeliveryNotesTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetDeliveryNoteTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\CreateDeliveryNoteTool());

            // Dunnings
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListDunningsTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetDunningTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\CreateDunningTool());

            // Articles (CRUD)
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListArticlesTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetArticleTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\CreateArticleTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\UpdateArticleTool());

            // Payments (Read-Only)
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListPaymentsTool());

            // Reference Data (Read-Only)
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListCountriesTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListPostingCategoriesTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListPaymentConditionsTool());

            // Down Payment Invoices (Read-Only)
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListDownPaymentInvoicesTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetDownPaymentInvoiceTool());

            // Recurring Templates (Read-Only)
            $registry->register(new \Platform\Integrations\Tools\Lexware\ListRecurringTemplatesTool());
            $registry->register(new \Platform\Integrations\Tools\Lexware\GetRecurringTemplateTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: Lexware Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // BuchhaltungsButler Tools (Rechnungen/Angebote/Gutschriften als Entwurf + Debitoren)
        try {
            $registry->register(new \Platform\Integrations\Tools\Buchhaltungsbutler\CreateInvoiceDraftTool());
            $registry->register(new \Platform\Integrations\Tools\Buchhaltungsbutler\CreateOfferDraftTool());
            $registry->register(new \Platform\Integrations\Tools\Buchhaltungsbutler\CreateCreditNoteDraftTool());
            $registry->register(new \Platform\Integrations\Tools\Buchhaltungsbutler\ListDebtorsTool());
            $registry->register(new \Platform\Integrations\Tools\Buchhaltungsbutler\CreateDebtorTool());
            $registry->register(new \Platform\Integrations\Tools\Buchhaltungsbutler\UpdateDebtorTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: BuchhaltungsButler Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // DataForSEO Tools (SEO Keyword-Daten)
        try {
            $registry->register(new \Platform\Integrations\Tools\DataForSeo\DataForSeoTestConnectionTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: DataForSEO Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // Moss Tools (Spend-Management)
        try {
            $registry->register(new \Platform\Integrations\Tools\Moss\TestConnectionTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\ListExpensesTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\ListExpenseAccountsTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\ListSuppliersTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\GetSupplierTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\ListUsersTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\ListBankAccountsTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\ListDimensionsTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\ListDimensionItemsTool());
            $registry->register(new \Platform\Integrations\Tools\Moss\ListPaymentTermsTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: Moss Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // Connection Shares Tools (Freigaben)
        try {
            $registry->register(new \Platform\Integrations\Tools\ConnectionShares\ListSharesTool());
            $registry->register(new \Platform\Integrations\Tools\ConnectionShares\CreateShareTool());
            $registry->register(new \Platform\Integrations\Tools\ConnectionShares\DeleteShareTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: ConnectionShares Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // TimeEntry Tools (Zeiterfassung)
        try {
            $registry->register(new \Platform\Integrations\Tools\TimeEntry\ListTimeEntriesTool());
            $registry->register(new \Platform\Integrations\Tools\TimeEntry\GetTimeEntryTool());
            $registry->register(new \Platform\Integrations\Tools\TimeEntry\CreateTimeEntryTool());
            $registry->register(new \Platform\Integrations\Tools\TimeEntry\BulkCreateTimeEntriesTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: TimeEntry Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // Meta Tools (Instagram Accounts, Facebook Pages, Resources Sync)
        try {
            $registry->register(new \Platform\Integrations\Tools\Meta\ListInstagramAccountsTool());
            $registry->register(new \Platform\Integrations\Tools\Meta\ListFacebookPagesTool());
            $registry->register(new \Platform\Integrations\Tools\Meta\SyncMetaResourcesTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: Meta Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // GitHub Tools (Repos, Resources Sync)
        try {
            $registry->register(new \Platform\Integrations\Tools\Github\ListGithubReposTool());
            $registry->register(new \Platform\Integrations\Tools\Github\SyncGithubResourcesTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: GitHub Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // WhatsApp Tools (Accounts, Templates)
        try {
            $registry->register(new \Platform\Integrations\Tools\WhatsApp\ListWhatsAppAccountsTool());
            $registry->register(new \Platform\Integrations\Tools\WhatsApp\ListWhatsAppTemplatesTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: WhatsApp Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // RingCentral Tools (Call Logs, Extensions, Account Info)
        try {
            $registry->register(new \Platform\Integrations\Tools\RingCentral\TestConnectionTool());
            $registry->register(new \Platform\Integrations\Tools\RingCentral\GetUserInfoTool());
            $registry->register(new \Platform\Integrations\Tools\RingCentral\GetAccountInfoTool());
            $registry->register(new \Platform\Integrations\Tools\RingCentral\GetCallLogTool());
            $registry->register(new \Platform\Integrations\Tools\RingCentral\GetActiveCallsTool());
            $registry->register(new \Platform\Integrations\Tools\RingCentral\ListExtensionsTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: RingCentral Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // Plausible Analytics Tools (Sites, Realtime, Aggregate, Timeseries, Breakdown)
        try {
            $registry->register(new \Platform\Integrations\Tools\Plausible\TestConnectionTool());
            $registry->register(new \Platform\Integrations\Tools\Plausible\ListSitesTool());
            $registry->register(new \Platform\Integrations\Tools\Plausible\GetRealtimeVisitorsTool());
            $registry->register(new \Platform\Integrations\Tools\Plausible\GetAggregateStatsTool());
            $registry->register(new \Platform\Integrations\Tools\Plausible\GetTimeseriesTool());
            $registry->register(new \Platform\Integrations\Tools\Plausible\GetBreakdownTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: Plausible Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // Google Search Console Tools (Sites, Search Analytics, Sitemaps, URL Inspection)
        try {
            $registry->register(new \Platform\Integrations\Tools\GoogleSearchConsole\TestConnectionTool());
            $registry->register(new \Platform\Integrations\Tools\GoogleSearchConsole\ListSitesTool());
            $registry->register(new \Platform\Integrations\Tools\GoogleSearchConsole\GetSiteTool());
            $registry->register(new \Platform\Integrations\Tools\GoogleSearchConsole\QuerySearchAnalyticsTool());
            $registry->register(new \Platform\Integrations\Tools\GoogleSearchConsole\ListSitemapsTool());
            $registry->register(new \Platform\Integrations\Tools\GoogleSearchConsole\GetSitemapTool());
            $registry->register(new \Platform\Integrations\Tools\GoogleSearchConsole\InspectUrlTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: Google Search Console Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // DATEV Tools (Mandanten, Buchhaltung)
        try {
            $registry->register(new \Platform\Integrations\Tools\Datev\TestConnectionTool());
            $registry->register(new \Platform\Integrations\Tools\Datev\ListTenantsTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: DATEV Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // easybill Tools (Rechnungen, Belege, Kunden, Positionen, Projekte, Tasks, Time-Tracking, ...)
        try {
            $registry->register(new \Platform\Integrations\Tools\Easybill\CancelDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CompleteDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ConvertDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateAttachmentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateCustomerContactTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateCustomerGroupTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateCustomerTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateDocumentPaymentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateRecurringInvoiceTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreatePositionDiscountTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreatePositionGroupDiscountTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreatePositionGroupTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreatePositionTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateProjectTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateSepaPaymentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateSerialNumberTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateStockTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateTaskTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateTextTemplateTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateTimeTrackingTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\CreateWebhookTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteAttachmentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteCustomerContactTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteCustomerGroupTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteCustomerTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteDocumentPaymentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeletePositionDiscountTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeletePositionGroupDiscountTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeletePositionGroupTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeletePositionTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeletePostBoxTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteProjectTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteSepaPaymentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteSerialNumberTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteTaskTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteTextTemplateTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteTimeTrackingTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DeleteWebhookTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\DownloadDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\EasybillOverviewTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetAttachmentContentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetAttachmentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetCustomerContactTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetCustomerGroupTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetCustomerTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetDocumentJpgTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetDocumentPaymentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetDocumentPdfTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetLoginTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetPositionDiscountTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetPositionGroupDiscountTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetPositionGroupTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetPositionTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetPostBoxTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetProjectTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetSepaPaymentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetSerialNumberTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetStockTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetTaskTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetTextTemplateTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetTimeTrackingTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\GetWebhookTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListAttachmentsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListCustomerContactsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListCustomerGroupsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListCustomersTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListDocumentPaymentsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListDocumentsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListLoginsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListPositionDiscountsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListPositionGroupDiscountsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListPositionGroupsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListPositionsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListPostBoxesTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListProjectsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListSepaPaymentsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListSerialNumbersTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListStocksTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListTasksTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListTextTemplatesTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListTimeTrackingsTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\ListWebhooksTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\SendDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\TestConnectionTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateAttachmentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateCustomerContactTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateCustomerGroupTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateCustomerTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdatePositionDiscountTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdatePositionGroupDiscountTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdatePositionGroupTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdatePositionTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateProjectTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateSepaPaymentTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateTaskTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateTextTemplateTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateTimeTrackingTool());
            $registry->register(new \Platform\Integrations\Tools\Easybill\UpdateWebhookTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: easybill Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // ---------------------------------------------------------------------
        // necta.one Raw-API Tools (read-only, uniform GET /rawapi/{resource})
        // ---------------------------------------------------------------------
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Discovery + generischer Zugriff (deckt alle 417 Ressourcen ab)
            $registry->register(new \Platform\Integrations\Tools\Necta\ResourcesTool());
            $registry->register(new \Platform\Integrations\Tools\Necta\ListTool());

            // Komfort-Tools für Kernressourcen
            $registry->register(new \Platform\Integrations\Tools\Necta\ListProductsTool());
            $registry->register(new \Platform\Integrations\Tools\Necta\ListCustomersTool());
            $registry->register(new \Platform\Integrations\Tools\Necta\ListOrdersTool());
            $registry->register(new \Platform\Integrations\Tools\Necta\ListSuppliersTool());
            $registry->register(new \Platform\Integrations\Tools\Necta\ListInvoicesTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: necta.one Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // ---------------------------------------------------------------------
        // necta.one API v1 Tools (REST/CRUD, /api/v1/{tenantId}) — Test-Set
        // ---------------------------------------------------------------------
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            $registry->register(new \Platform\Integrations\Tools\NectaV1\CallTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\ApikeyPostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\ApikeysGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\ApikeyrevokePostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\ApikeyrotatePostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\CustomersGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\CustomerContactsbyIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\CustomerContactsGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\DashboardPostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\DashboardbyDashboardIdDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\DashboardbyDashboardIdPutTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\DashboardbyDashboardIdbyIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\DashboardbyTenantGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\DashboardbyUserGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\InvoicesbyIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\InvoicesGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\InDeliveryNotesGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\InDeliveryNotesbyIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\InventoryMovementsGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\InvitesbyInviteIdacceptPostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\InvitesPostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\InvitesGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\MealsGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulessessionsbyModuleIdDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulessessionsPostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModuletokenPostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesbyModuleIdDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesbyModuleIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesbyModuleIdPatchTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesbyModuleIdfilesDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesbyModuleIdfilesPostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesbyModuleIddetailsGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulespagedGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesbyModuleIdurlGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulesbyModuleIdfinalizePostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\NectaModulescleanuprunOncePostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\PurchaseOrdersbyIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\OrdersGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\OrdersbyIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\OutInvoicesGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\OutInvoicesbyIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\ProductMainClassesGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\ProductClassesGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\PurchaseOrdersGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\PurchaseOrdersstructuresGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\LoginPostTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\LogoutbySessionIdDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\UseOfGoodsGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\CompanyearningsturnoverGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\CompanyearningsturnoverbyCostcenterGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\StocksGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\SupplierItemsGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\SupplierItemsbyIdGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\UserspermissionsGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\UserprofileGetTool());
            $registry->register(new \Platform\Integrations\Tools\NectaV1\UserregisteredGetTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: necta.one API v1 Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        // ---------------------------------------------------------------------
        // DedeFleet Tools (Ortung & Tourenplanung, RPC /data/api/2)
        // ---------------------------------------------------------------------
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Referenz + generischer RPC-Zugriff (deckt alle Endpunkte ab)
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\OverviewTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CallTool());

            // Komfort-Read-Tools (Stammdaten / Listen)
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ListCustomersTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ListLocationsTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ListEmployeesTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ListVehicleProfilesTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ListUnassignedOrdersTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ListToursTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ListTrackingCurrentDataTool());

            // Tourenplanung Workflow (Wiki Steps 1–5) — schreibende Aktionen
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CreateOrderTool());          // Step 1
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CreateTourTool());           // Step 2
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CreateTourFromTemplateTool()); // Step 2 (Vorlage)
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\AssignOrderTool());          // Step 3
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\AssignOrdersBulkTool());     // Step 3
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ReorderTourTool());          // Step 4
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\OptimizeTourTool());         // Step 4
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ChangeTourStatusTool());     // Step 4
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\SetTourLockStateTool());     // Step 4
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\UpdateOrderTool());          // Step 5
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\UnassignOrderTool());        // Step 5
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\DeleteOrderTool());          // Step 5

            // Übrige v2-Endpunkte — vollständige Abdeckung (auto-generiert aus Swagger v2)
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\AddressSearchTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ArchiveImportTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CalculateDistanceAndTimeTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CustomerDeleteAllTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CustomerCreateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CustomerDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CustomerAddDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\CustomerDeleteDocumentTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\DriveBookListTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\DriveBookModifyTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EmployeeListDARPTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EmployeeCreateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EmployeeAssignTokenTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EmployeeWorkTimeStopTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EmployeeWorkTimeStartTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EventListTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EventCreateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EventDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\EventUpdateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ItemGetStatusTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\ItemListStatusTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\LocationCreateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\LocationDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\LocationUpdateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\MonitorSetDataTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\OrderGetTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\OrderFindSlotTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\OrderGetStatusTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\OrderListStatusTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\OrderGetStatusBulkTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TourListTemplatesTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TourGetTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TourDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TourGetBulkTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TourCalculateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TourOptimizeManyTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TourCalculateTollTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TourOptimizeTourDepartureTimeTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TrackingObjectListTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TrackingObjectCreateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TrackingObjectSendMsgTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TrackingObjectSetMileageTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\TrailerObjectListCurrentDataTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\UserLogoutTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\UserLoginTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\UserNotifyTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\VehicleProfileCreateTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\VehicleProfileDeleteTool());
            $registry->register(new \Platform\Integrations\Tools\Dedefleet\VehicleProfileUpdateTool());
        } catch (\Throwable $e) {
            \Log::warning('Integrations: DedeFleet Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }

    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Integrations\\Livewire';
        $prefix = 'integrations';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            // integrations.connections.index aus Connections/Index.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}

