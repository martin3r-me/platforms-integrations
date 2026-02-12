<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationsWhatsAppAccount;
use Platform\Integrations\Models\IntegrationsWhatsAppTemplate;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsMetaBusinessAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * Service für WhatsApp Business Accounts Management (generische Integrations-Logik)
 */
class IntegrationsWhatsAppAccountService
{
    protected MetaIntegrationService $metaService;

    public function __construct(MetaIntegrationService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Ruft alle WhatsApp Business Accounts für einen User ab und speichert sie (generisch)
     * 
     * @param IntegrationConnection $connection
     * @return array
     */
    public function syncWhatsAppAccountsForUser(IntegrationConnection $connection): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);
        
        if (!$accessToken) {
            throw new \Exception('Access Token konnte nicht abgerufen werden.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $userId = $connection->owner_user_id;

        // Versuche Meta User ID aus Credentials zu holen (wie im glowkit-master)
        $credentials = $connection->credentials ?? [];
        $metaUserId = $credentials['oauth']['meta_user_id'] ?? null;
        
        Log::info('WhatsApp sync - Meta User ID check', [
            'user_id' => $userId,
            'has_meta_user_id' => !empty($metaUserId),
            'meta_user_id' => $metaUserId,
            'credentials_keys' => array_keys($credentials),
            'oauth_keys' => array_keys($credentials['oauth'] ?? []),
        ]);

        // WICHTIG: Für eigene Konten (kein BSP) verwenden wir die normalen Cloud-API-Endpoints
        // Ansatz: Business Accounts holen und für jede Business Account prüfen,
        // ob WABA-IDs direkt in den Business Account Details enthalten sind
        // oder ob wir sie über einen alternativen Endpoint holen können
        
        // Business Accounts holen (wie im glowkit-master)
        $businessUrl = null;
        if ($metaUserId) {
            $businessUrl = "https://graph.facebook.com/{$apiVersion}/{$metaUserId}/businesses";
            Log::info('Using Meta User ID for Business Accounts', [
                'meta_user_id' => $metaUserId,
                'user_id' => $userId,
            ]);
        } else {
            $businessUrl = "https://graph.facebook.com/{$apiVersion}/me/businesses";
            Log::warning('No Meta User ID found, using /me endpoint', [
                'user_id' => $userId,
            ]);
        }
        
        $businessParams = [
            'access_token' => $accessToken,
            'limit' => 100,
        ];

        $allBusinessAccounts = [];

        // Pagination für Business Accounts
        do {
            $businessResponse = Http::get($businessUrl, $businessParams);

            if ($businessResponse->failed()) {
                $error = $businessResponse->json()['error'] ?? [];
                Log::error('Failed to fetch business accounts', [
                    'user_id' => $userId,
                    'error' => $error,
                ]);
                throw new \Exception('Fehler beim Abrufen der Business Accounts: ' . ($error['message'] ?? 'Unbekannter Fehler'));
            }

            $businessData = $businessResponse->json();
            $businessAccounts = $businessData['data'] ?? [];

            if (!empty($businessAccounts)) {
                $allBusinessAccounts = array_merge($allBusinessAccounts, $businessAccounts);
            }

            $businessUrl = $businessData['paging']['next'] ?? null;
            $businessParams = [];
        } while ($businessUrl);

        if (empty($allBusinessAccounts)) {
            Log::warning('No business accounts found', ['user_id' => $userId]);
            return [];
        }

        Log::info('Found business accounts', [
            'user_id' => $userId,
            'count' => count($allBusinessAccounts),
        ]);

        $syncedAccounts = [];
        
        // Optional: Business Accounts speichern, falls Tabelle existiert
        $businessAccountsTableExists = Schema::hasTable('integrations_meta_business_accounts');
        $savedBusinessAccounts = [];

        // Exakt wie im glowkit-master: Für jede Business Account die WhatsApp Business Accounts holen
        foreach ($allBusinessAccounts as $businessAccount) {
            $businessId = $businessAccount['id'];
            $businessName = $businessAccount['name'] ?? 'Unknown';
            
            // Optional: Business Account speichern
            $metaBusinessAccount = null;
            if ($businessAccountsTableExists) {
                try {
                    $metaBusinessAccount = IntegrationsMetaBusinessAccount::updateOrCreate(
                        [
                            'external_id' => $businessId,
                            'user_id' => $userId,
                        ],
                        [
                            'name' => $businessName,
                            'description' => $businessAccount['description'] ?? null,
                            'timezone' => $businessAccount['timezone'] ?? null,
                            'vertical' => $businessAccount['vertical'] ?? null,
                            'metadata' => $businessAccount, // Alle Meta-Daten speichern
                            'integration_connection_id' => $connection->id,
                        ]
                    );
                    $savedBusinessAccounts[$businessId] = $metaBusinessAccount;
                    
                    Log::info('Meta Business Account saved', [
                        'business_account_id' => $metaBusinessAccount->id,
                        'external_id' => $businessId,
                        'name' => $businessName,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to save Meta Business Account (continuing)', [
                        'business_id' => $businessId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Retrieve WhatsApp Business Accounts (exakt wie im glowkit-master)
            $waAccountsResponse = Http::get("https://graph.facebook.com/{$apiVersion}/{$businessId}/owned_whatsapp_business_accounts", [
                'access_token' => $accessToken,
            ]);

            $waAccountsData = $waAccountsResponse->json();

            // Bei Fehler einfach loggen und zum nächsten Business Account (wie im glowkit-master)
            if (isset($waAccountsData['error'])) {
                Log::warning('Failed to retrieve WhatsApp accounts for business (continuing to next)', [
                    'business_id' => $businessId,
                    'business_name' => $businessName,
                    'error' => $waAccountsData['error']['message'] ?? 'Unknown error',
                ]);
                continue;
            }

            // Verarbeite alle gefundenen WhatsApp Business Accounts
            foreach ($waAccountsData['data'] ?? [] as $waAccountData) {
                $wabaId = $waAccountData['id'] ?? null;
                
                if (!$wabaId) {
                    continue;
                }

                // Step 3: Retrieve phone numbers (exakt wie im glowkit-master)
                $phoneNumbersResponse = Http::get("https://graph.facebook.com/{$apiVersion}/{$wabaId}/phone_numbers", [
                    'access_token' => $accessToken,
                ]);

                $phoneNumbersData = $phoneNumbersResponse->json();
                $phoneNumber = $phoneNumbersData['data'][0]['display_phone_number'] ?? null;
                $phoneNumberId = $phoneNumbersData['data'][0]['id'] ?? null;

                // Step 4: Update or create WhatsAppAccount (exakt wie im glowkit-master)
                try {
                    $whatsappAccountData = [
                        'title' => $waAccountData['name'] ?? 'Unnamed Account',
                        'phone_number' => $phoneNumber,
                        'phone_number_id' => $phoneNumberId,
                        'description' => $waAccountData['description'] ?? null,
                        'active' => isset($waAccountData['status']) && $waAccountData['status'] === 'ACTIVE',
                        'access_token' => $waAccountData['access_token'] ?? $accessToken,
                        'verified_at' => isset($waAccountData['verified']) && $waAccountData['verified'] ? now() : null,
                        'integration_connection_id' => $connection->id,
                    ];
                    
                    // Optional: Business Account verknüpfen, falls vorhanden
                    if ($metaBusinessAccount) {
                        $whatsappAccountData['meta_business_account_id'] = $metaBusinessAccount->id;
                    }
                    
                    $whatsappAccount = IntegrationsWhatsAppAccount::updateOrCreate(
                        [
                            'external_id' => $wabaId,
                            'user_id' => $userId,
                        ],
                        $whatsappAccountData
                    );

                    $syncedAccounts[] = $whatsappAccount;

                    Log::info('WhatsApp Account synced successfully', [
                        'account_id' => $whatsappAccount->id,
                        'external_id' => $wabaId,
                        'title' => $whatsappAccount->title,
                        'phone_number' => $phoneNumber,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to save WhatsApp Account', [
                        'waba_id' => $wabaId,
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('WhatsApp sync completed', [
            'user_id' => $userId,
            'business_accounts_checked' => count($allBusinessAccounts),
            'whatsapp_accounts_synced' => count($syncedAccounts),
        ]);

        return $syncedAccounts;
    }

    /**
     * Synchronisiert alle WhatsApp Message Templates für einen WhatsApp Business Account
     *
     * Verwendet den Meta Graph API Endpoint:
     * GET /v{version}/{waba_id}/message_templates
     *
     * @param IntegrationsWhatsAppAccount $whatsappAccount
     * @param IntegrationConnection $connection
     * @return array
     */
    public function syncWhatsAppTemplatesForAccount(IntegrationsWhatsAppAccount $whatsappAccount, IntegrationConnection $connection): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);

        if (!$accessToken) {
            throw new \Exception('Access Token konnte nicht abgerufen werden.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $wabaId = $whatsappAccount->external_id;
        $userId = $whatsappAccount->user_id;

        if (!$wabaId) {
            throw new \Exception("WhatsApp Account hat keine external_id (WABA ID).");
        }

        Log::info('Starting WhatsApp template sync', [
            'whatsapp_account_id' => $whatsappAccount->id,
            'waba_id' => $wabaId,
            'user_id' => $userId,
        ]);

        // Prüfe Token-Scopes via debug_token
        try {
            $debugResponse = Http::get("https://graph.facebook.com/debug_token", [
                'input_token' => $accessToken,
                'access_token' => $accessToken,
            ]);

            if ($debugResponse->successful()) {
                $debugData = $debugResponse->json()['data'] ?? [];
                $grantedScopes = $debugData['scopes'] ?? [];

                Log::info('WhatsApp template sync - token scopes', [
                    'waba_id' => $wabaId,
                    'granted_scopes' => $grantedScopes,
                    'has_whatsapp_business_management' => in_array('whatsapp_business_management', $grantedScopes),
                    'app_id' => $debugData['app_id'] ?? null,
                    'is_valid' => $debugData['is_valid'] ?? false,
                ]);

                if (!in_array('whatsapp_business_management', $grantedScopes)) {
                    throw new \Exception(
                        'Token hat keine whatsapp_business_management Permission. ' .
                        'Bitte Meta-Verbindung trennen und neu verbinden, damit die aktuellen Scopes gewährt werden.'
                    );
                }
            }
        } catch (\Exception $e) {
            // Wenn es unsere eigene Permission-Exception ist, weiterwerfen
            if (str_contains($e->getMessage(), 'whatsapp_business_management')) {
                throw $e;
            }
            // debug_token-Fehler nur loggen, nicht blockierend
            Log::warning('Could not verify token scopes via debug_token', [
                'error' => $e->getMessage(),
            ]);
        }

        $url = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates";

        $syncedTemplates = [];

        // Pagination
        do {
            $response = Http::get($url, [
                'access_token' => $accessToken,
            ]);

            Log::info('WhatsApp templates API response', [
                'waba_id' => $wabaId,
                'http_status' => $response->status(),
                'data_count' => count($response->json()['data'] ?? []),
                'has_error' => isset($response->json()['error']),
                'response_keys' => array_keys($response->json() ?? []),
            ]);

            if ($response->failed()) {
                $error = $response->json()['error'] ?? [];
                Log::error('Failed to fetch WhatsApp templates', [
                    'waba_id' => $wabaId,
                    'user_id' => $userId,
                    'error' => $error,
                ]);
                throw new \Exception('Fehler beim Abrufen der WhatsApp Templates: ' . ($error['message'] ?? 'Unbekannter Fehler'));
            }

            $data = $response->json();
            $templates = $data['data'] ?? [];

            foreach ($templates as $templateData) {
                $templateId = $templateData['id'] ?? null;

                if (!$templateId) {
                    continue;
                }

                try {
                    $template = IntegrationsWhatsAppTemplate::updateOrCreate(
                        [
                            'external_id' => $templateId,
                            'language' => $templateData['language'] ?? 'de',
                            'whatsapp_account_id' => $whatsappAccount->id,
                        ],
                        [
                            'name' => $templateData['name'] ?? 'Unnamed Template',
                            'status' => $templateData['status'] ?? 'PENDING',
                            'category' => $templateData['category'] ?? null,
                            'components' => $templateData['components'] ?? null,
                            'metadata' => $templateData,
                            'user_id' => $userId,
                        ]
                    );

                    $syncedTemplates[] = $template;

                    Log::info('WhatsApp Template synced', [
                        'template_id' => $template->id,
                        'external_id' => $templateId,
                        'name' => $template->name,
                        'status' => $template->status,
                        'language' => $template->language,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to save WhatsApp Template', [
                        'template_external_id' => $templateId,
                        'waba_id' => $wabaId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Nächste Seite (URL enthält bereits alle Query-Parameter)
            $url = $data['paging']['next'] ?? null;
        } while ($url);

        Log::info('WhatsApp template sync completed', [
            'whatsapp_account_id' => $whatsappAccount->id,
            'waba_id' => $wabaId,
            'templates_synced' => count($syncedTemplates),
        ]);

        return $syncedTemplates;
    }

    /**
     * Synchronisiert Templates für alle WhatsApp Accounts einer Connection
     *
     * @param IntegrationConnection $connection
     * @return array
     */
    public function syncAllWhatsAppTemplates(IntegrationConnection $connection): array
    {
        $userId = $connection->owner_user_id;

        $whatsappAccounts = IntegrationsWhatsAppAccount::where('user_id', $userId)
            ->where('integration_connection_id', $connection->id)
            ->whereNotNull('external_id')
            ->get();

        Log::info('WhatsApp template sync - accounts query result', [
            'user_id' => $userId,
            'connection_id' => $connection->id,
            'accounts_found' => $whatsappAccounts->count(),
            'account_ids' => $whatsappAccounts->pluck('external_id')->toArray(),
        ]);

        if ($whatsappAccounts->isEmpty()) {
            Log::warning('No WhatsApp accounts found for template sync', [
                'user_id' => $userId,
                'connection_id' => $connection->id,
            ]);
            return [];
        }

        $allTemplates = [];

        foreach ($whatsappAccounts as $whatsappAccount) {
            try {
                $templates = $this->syncWhatsAppTemplatesForAccount($whatsappAccount, $connection);
                $allTemplates = array_merge($allTemplates, $templates);
            } catch (\Exception $e) {
                Log::error('Failed to sync templates for WhatsApp account (continuing to next)', [
                    'whatsapp_account_id' => $whatsappAccount->id,
                    'waba_id' => $whatsappAccount->external_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Permission-Fehler nicht verschlucken — nach oben werfen
                if (str_contains($e->getMessage(), 'whatsapp_business_management')) {
                    throw $e;
                }
            }
        }

        return $allTemplates;
    }

}
