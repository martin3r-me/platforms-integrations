<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationsWhatsAppAccount;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        // Versuche zuerst direkten Zugriff über /me (falls verfügbar)
        // Falls das nicht funktioniert, verwenden wir den Business-Account-Weg
        $directWabaUrl = "https://graph.facebook.com/{$apiVersion}/me/owned_whatsapp_business_accounts";
        $directWabaParams = [
            'access_token' => $accessToken,
            'fields' => 'id,name,account_review_status,message_template_namespace,primary_funding_id,timezone_id,currency,payment_model,is_enabled,is_permanently_closed,on_behalf_of_business_info,owner_business_info',
            'limit' => 100,
        ];

        $directWabaResponse = Http::get($directWabaUrl, $directWabaParams);
        
        if ($directWabaResponse->successful()) {
            $directWabaData = $directWabaResponse->json();
            $directWabaAccounts = $directWabaData['data'] ?? [];
            
            if (!empty($directWabaAccounts)) {
                Log::info('Found WhatsApp Business Accounts via direct /me endpoint', [
                    'user_id' => $userId,
                    'count' => count($directWabaAccounts),
                ]);
                
                // Verarbeite direkte WhatsApp Accounts
                return $this->processWhatsAppAccounts($directWabaAccounts, $accessToken, $userId, $connection);
            }
        } else {
            Log::info('Direct /me/owned_whatsapp_business_accounts endpoint not available, using Business Account approach', [
                'user_id' => $userId,
                'error' => $directWabaResponse->json()['error'] ?? null,
            ]);
        }

        // Fallback: Business Accounts mit Pagination holen
        // WICHTIG: Im glowkit-master wird {facebook_user_id}/businesses verwendet, nicht /me/businesses
        // Das ist der entscheidende Unterschied!
        $businessUrl = null;
        if ($metaUserId) {
            // Verwende Meta User ID direkt (wie im glowkit-master)
            $businessUrl = "https://graph.facebook.com/{$apiVersion}/{$metaUserId}/businesses";
            Log::info('Using Meta User ID for Business Accounts (like glowkit-master)', [
                'meta_user_id' => $metaUserId,
                'user_id' => $userId,
            ]);
        } else {
            // Falls keine Meta User ID vorhanden, versuche /me
            $businessUrl = "https://graph.facebook.com/{$apiVersion}/me/businesses";
            Log::warning('No Meta User ID found, using /me endpoint (may not work for WhatsApp)', [
                'user_id' => $userId,
                'note' => 'Meta User ID should be stored in credentials.oauth.meta_user_id after OAuth flow',
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
            $businessParams = []; // Bei next-URL sind alle Parameter bereits enthalten
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

        // Für jede Business Account die WhatsApp Business Accounts holen (mit Pagination)
        foreach ($allBusinessAccounts as $businessAccount) {
            $businessId = $businessAccount['id'];
            $businessName = $businessAccount['name'] ?? 'Unknown';
            
            Log::info('Checking WhatsApp Business Accounts for business', [
                'business_id' => $businessId,
                'business_name' => $businessName,
                'user_id' => $userId,
            ]);
            
            $wabaUrl = "https://graph.facebook.com/{$apiVersion}/{$businessId}/owned_whatsapp_business_accounts";
            $wabaParams = [
                'access_token' => $accessToken,
                'fields' => 'id,name,account_review_status,message_template_namespace,primary_funding_id,timezone_id,currency,payment_model,is_enabled,is_permanently_closed,on_behalf_of_business_info,owner_business_info',
                'limit' => 100,
            ];

            $wabaAccountsFound = 0;

            // Pagination für WhatsApp Business Accounts pro Business Account
            do {
                $wabaResponse = Http::get($wabaUrl, $wabaParams);

                if ($wabaResponse->failed()) {
                    $error = $wabaResponse->json()['error'] ?? [];
                    $errorCode = $error['code'] ?? null;
                    $errorMessage = $error['message'] ?? null;
                    
                    // Fehler 10: App ist nicht als Business Solution Provider registriert
                    // Das ist eine Meta-App-Konfiguration, kein OAuth-Problem
                    // Wir überspringen dieses Business Account und versuchen die nächsten
                    if ($errorCode === 10 && str_contains($errorMessage ?? '', 'Business Solution Provider')) {
                        Log::warning('App is not registered as Business Solution Provider for WhatsApp', [
                            'business_id' => $businessId,
                            'business_name' => $businessName,
                            'note' => 'This requires Meta App configuration, not OAuth scopes. Skipping this business account.',
                        ]);
                        break; // Nächstes Business Account versuchen
                    }
                    
                    // Andere Fehler (z.B. fehlende Berechtigungen) loggen
                    Log::error('Failed to fetch WhatsApp Business Accounts for business', [
                        'business_id' => $businessId,
                        'business_name' => $businessName,
                        'error' => $error,
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'error_type' => $error['type'] ?? null,
                    ]);
                    break;
                }

                $wabaData = $wabaResponse->json();
                $wabaAccounts = $wabaData['data'] ?? [];

                Log::info('WhatsApp Business Accounts response', [
                    'business_id' => $businessId,
                    'accounts_count' => count($wabaAccounts),
                    'has_paging' => isset($wabaData['paging']),
                    'response_keys' => array_keys($wabaData),
                ]);

                foreach ($wabaAccounts as $wabaAccountData) {
                    $wabaId = $wabaAccountData['id'] ?? null;
                    
                    if (!$wabaId) {
                        Log::warning('WhatsApp Business Account without ID', [
                            'business_id' => $businessId,
                            'account_data' => $wabaAccountData,
                        ]);
                        continue;
                    }
                    
                    $wabaName = $wabaAccountData['name'] ?? 'WhatsApp Business Account';
                    $wabaAccountsFound++;
                    
                    Log::info('Processing WhatsApp Business Account', [
                        'waba_id' => $wabaId,
                        'waba_name' => $wabaName,
                        'business_id' => $businessId,
                        'user_id' => $userId,
                    ]);

                    // Phone Numbers für diesen WABA holen (mit Pagination)
                    $phoneNumbersUrl = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/phone_numbers";
                    $phoneNumbersParams = [
                        'access_token' => $accessToken,
                        'fields' => 'id,display_phone_number,verified_name,code_verification_status,quality_rating,throughput,last_onboarded_time',
                        'limit' => 100,
                    ];

                    $allPhoneNumbers = [];

                    // Pagination für Phone Numbers
                    do {
                        $phoneNumbersResponse = Http::get($phoneNumbersUrl, $phoneNumbersParams);

                        if ($phoneNumbersResponse->successful()) {
                            $phoneNumbersData = $phoneNumbersResponse->json();
                            $phoneNumbers = $phoneNumbersData['data'] ?? [];

                            if (!empty($phoneNumbers)) {
                                $allPhoneNumbers = array_merge($allPhoneNumbers, $phoneNumbers);
                            }

                            $phoneNumbersUrl = $phoneNumbersData['paging']['next'] ?? null;
                            $phoneNumbersParams = []; // Bei next-URL sind alle Parameter bereits enthalten
                        } else {
                            break;
                        }
                    } while ($phoneNumbersUrl);

                    // Erste Phone Number als primäre verwenden
                    $primaryPhoneNumber = $allPhoneNumbers[0] ?? null;
                    $phoneNumber = $primaryPhoneNumber['display_phone_number'] ?? null;
                    $phoneNumberId = $primaryPhoneNumber['id'] ?? null;

                    // Versuche WABA-spezifischen Access Token zu holen (falls verfügbar)
                    // Falls nicht, verwende den User-Token (wie im glowkit-master)
                    $wabaAccessToken = $wabaAccountData['access_token'] ?? $accessToken;
                    
                    // WhatsApp Account auf User-Ebene erstellen oder aktualisieren
                    try {
                        $whatsappAccount = IntegrationsWhatsAppAccount::updateOrCreate(
                            [
                                'external_id' => $wabaId,
                                'user_id' => $userId,
                            ],
                            [
                                'title' => $wabaName,
                                'description' => $wabaAccountData['account_review_status'] ?? null,
                                'phone_number' => $phoneNumber,
                                'phone_number_id' => $phoneNumberId,
                                'active' => $wabaAccountData['is_enabled'] ?? true,
                                'access_token' => $wabaAccessToken, // WABA-spezifischer Token oder User-Token als Fallback
                                'verified_at' => isset($primaryPhoneNumber['verified_name']) ? now() : null,
                            ]
                        );

                        $syncedAccounts[] = $whatsappAccount;

                        Log::info('WhatsApp Business Account synced successfully', [
                            'account_id' => $whatsappAccount->id,
                            'external_id' => $wabaId,
                            'user_id' => $userId,
                            'phone_numbers_count' => count($allPhoneNumbers),
                            'phone_number' => $phoneNumber,
                            'phone_number_id' => $phoneNumberId,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to save WhatsApp Business Account', [
                            'waba_id' => $wabaId,
                            'user_id' => $userId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
                
                if ($wabaAccountsFound === 0 && empty($wabaAccounts)) {
                    Log::info('No WhatsApp Business Accounts found for business', [
                        'business_id' => $businessId,
                        'business_name' => $businessName,
                    ]);
                }

                // Nächste Seite holen
                $wabaUrl = $wabaData['paging']['next'] ?? null;
                $wabaParams = []; // Bei next-URL sind alle Parameter bereits enthalten
            } while ($wabaUrl);
        }

        Log::info('WhatsApp sync completed', [
            'user_id' => $userId,
            'business_accounts_checked' => count($allBusinessAccounts),
            'whatsapp_accounts_synced' => count($syncedAccounts),
        ]);

        return $syncedAccounts;
    }

    /**
     * Verarbeitet WhatsApp Business Accounts und speichert sie
     */
    protected function processWhatsAppAccounts(array $wabaAccounts, string $accessToken, int $userId, IntegrationConnection $connection): array
    {
        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $syncedAccounts = [];

        foreach ($wabaAccounts as $wabaAccountData) {
            $wabaId = $wabaAccountData['id'] ?? null;
            
            if (!$wabaId) {
                Log::warning('WhatsApp Business Account without ID', [
                    'account_data' => $wabaAccountData,
                ]);
                continue;
            }
            
            $wabaName = $wabaAccountData['name'] ?? 'WhatsApp Business Account';
            
            Log::info('Processing WhatsApp Business Account', [
                'waba_id' => $wabaId,
                'waba_name' => $wabaName,
                'user_id' => $userId,
            ]);

            // Phone Numbers für diesen WABA holen (mit Pagination)
            $phoneNumbersUrl = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/phone_numbers";
            $phoneNumbersParams = [
                'access_token' => $accessToken,
                'fields' => 'id,display_phone_number,verified_name,code_verification_status,quality_rating,throughput,last_onboarded_time',
                'limit' => 100,
            ];

            $allPhoneNumbers = [];

            // Pagination für Phone Numbers
            do {
                $phoneNumbersResponse = Http::get($phoneNumbersUrl, $phoneNumbersParams);

                if ($phoneNumbersResponse->successful()) {
                    $phoneNumbersData = $phoneNumbersResponse->json();
                    $phoneNumbers = $phoneNumbersData['data'] ?? [];

                    if (!empty($phoneNumbers)) {
                        $allPhoneNumbers = array_merge($allPhoneNumbers, $phoneNumbers);
                    }

                    $phoneNumbersUrl = $phoneNumbersData['paging']['next'] ?? null;
                    $phoneNumbersParams = []; // Bei next-URL sind alle Parameter bereits enthalten
                } else {
                    break;
                }
            } while ($phoneNumbersUrl);

            // Erste Phone Number als primäre verwenden
            $primaryPhoneNumber = $allPhoneNumbers[0] ?? null;
            $phoneNumber = $primaryPhoneNumber['display_phone_number'] ?? null;
            $phoneNumberId = $primaryPhoneNumber['id'] ?? null;

            // Versuche WABA-spezifischen Access Token zu holen (falls verfügbar)
            // Falls nicht, verwende den User-Token (wie im glowkit-master)
            $wabaAccessToken = $wabaAccountData['access_token'] ?? $accessToken;
            
            // WhatsApp Account auf User-Ebene erstellen oder aktualisieren
            try {
                $whatsappAccount = IntegrationsWhatsAppAccount::updateOrCreate(
                    [
                        'external_id' => $wabaId,
                        'user_id' => $userId,
                    ],
                    [
                        'title' => $wabaName,
                        'description' => $wabaAccountData['account_review_status'] ?? null,
                        'phone_number' => $phoneNumber,
                        'phone_number_id' => $phoneNumberId,
                        'active' => $wabaAccountData['is_enabled'] ?? true,
                        'access_token' => $wabaAccessToken, // WABA-spezifischer Token oder User-Token als Fallback
                        'verified_at' => isset($primaryPhoneNumber['verified_name']) ? now() : null,
                    ]
                );

                $syncedAccounts[] = $whatsappAccount;

                Log::info('WhatsApp Business Account synced successfully', [
                    'account_id' => $whatsappAccount->id,
                    'external_id' => $wabaId,
                    'user_id' => $userId,
                    'phone_numbers_count' => count($allPhoneNumbers),
                    'phone_number' => $phoneNumber,
                    'phone_number_id' => $phoneNumberId,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to save WhatsApp Business Account', [
                    'waba_id' => $wabaId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $syncedAccounts;
    }
}
