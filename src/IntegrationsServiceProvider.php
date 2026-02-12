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
                \Platform\Integrations\Console\Commands\SyncLexwareContacts::class,
                \Platform\Integrations\Console\Commands\SeedIntegrations::class,
                \Platform\Integrations\Console\Commands\SyncSipgateAccounts::class,
                \Platform\Integrations\Console\Commands\SipgateRefreshTokens::class,
                \Platform\Integrations\Console\Commands\SipgateWebhookCleanup::class,
            ]);
        }

        // Schritt 5: Config publish
        $this->publishes([
            __DIR__ . '/../config/integrations.php' => config_path('integrations.php'),
        ], 'integrations-config');
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

