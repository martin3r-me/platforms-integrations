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
        
        Log::info('WhatsApp sync - Meta User ID check', [
            'user_id' => $userId,
            'has_meta_user_id' => !empty($metaUserId),
            'meta_user_id' => $metaUserId,
            'credentials_keys' => array_keys($credentials),
            'oauth_keys' => array_keys($credentials['oauth'] ?? []),
        ]);

        // WICHTIG: Für eigene Konten (kein BSP) verwenden wir die normalen Cloud-API-Endpoints
        // Statt über Business Accounts zu gehen, versuchen wir direkt über Facebook Pages
        // die mit WhatsApp verknüpft sind, oder über einen alternativen Weg
        
        // Ansatz 1: Versuche über /me/accounts die Facebook Pages zu holen
        // und prüfe, ob diese mit WhatsApp verknüpft sind
        $pagesUrl = "https://graph.facebook.com/{$apiVersion}/me/accounts";
        $pagesParams = [
            'access_token' => $accessToken,
            'fields' => 'id,name,whatsapp_business_account_id',
            'limit' => 100,
        ];

        $allPages = [];
        $wabaIdsFromPages = [];

        // Pagination für Facebook Pages
        do {
            $pagesResponse = Http::get($pagesUrl, $pagesParams);

            if ($pagesResponse->successful()) {
                $pagesData = $pagesResponse->json();
                $pages = $pagesData['data'] ?? [];

                foreach ($pages as $page) {
                    $allPages[] = $page;
                    // Wenn die Page eine WhatsApp Business Account ID hat, sammle sie
                    if (isset($page['whatsapp_business_account_id'])) {
                        $wabaIdsFromPages[] = $page['whatsapp_business_account_id'];
                    }
                }

                $pagesUrl = $pagesData['paging']['next'] ?? null;
                $pagesParams = []; // Bei next-URL sind alle Parameter bereits enthalten
            } else {
                break;
            }
        } while ($pagesUrl);

        Log::info('Found Facebook Pages with WhatsApp connections', [
            'user_id' => $userId,
            'pages_count' => count($allPages),
            'waba_ids_from_pages' => $wabaIdsFromPages,
        ]);

        $syncedAccounts = [];

        // Wenn wir WABA-IDs über Pages gefunden haben, verwende diese
        if (!empty($wabaIdsFromPages)) {
            foreach ($wabaIdsFromPages as $wabaId) {
                try {
                    // Hole WABA-Details direkt (normaler Cloud-API-Endpoint, kein BSP)
                    $wabaDetailsUrl = "https://graph.facebook.com/{$apiVersion}/{$wabaId}";
                    $wabaDetailsParams = [
                        'access_token' => $accessToken,
                        'fields' => 'id,name,account_review_status,timezone_id,currency,is_enabled',
                    ];

                    $wabaDetailsResponse = Http::get($wabaDetailsUrl, $wabaDetailsParams);

                    if ($wabaDetailsResponse->successful()) {
                        $wabaAccountData = $wabaDetailsResponse->json();
                        
                        // Phone Numbers für diesen WABA holen (normaler Cloud-API-Endpoint)
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
                                $phoneNumbersParams = [];
                            } else {
                                break;
                            }
                        } while ($phoneNumbersUrl);

                        // Erste Phone Number als primäre verwenden
                        $primaryPhoneNumber = $allPhoneNumbers[0] ?? null;
                        $phoneNumber = $primaryPhoneNumber['display_phone_number'] ?? null;
                        $phoneNumberId = $primaryPhoneNumber['id'] ?? null;

                        // WhatsApp Account auf User-Ebene erstellen oder aktualisieren
                        $whatsappAccount = IntegrationsWhatsAppAccount::updateOrCreate(
                            [
                                'external_id' => $wabaId,
                                'user_id' => $userId,
                            ],
                            [
                                'title' => $wabaAccountData['name'] ?? 'WhatsApp Business Account',
                                'description' => $wabaAccountData['account_review_status'] ?? null,
                                'phone_number' => $phoneNumber,
                                'phone_number_id' => $phoneNumberId,
                                'active' => $wabaAccountData['is_enabled'] ?? true,
                                'access_token' => $accessToken, // User-Token verwenden
                                'verified_at' => isset($primaryPhoneNumber['verified_name']) ? now() : null,
                            ]
                        );

                        $syncedAccounts[] = $whatsappAccount;

                        Log::info('WhatsApp Business Account synced via Facebook Pages', [
                            'account_id' => $whatsappAccount->id,
                            'external_id' => $wabaId,
                            'user_id' => $userId,
                            'phone_numbers_count' => count($allPhoneNumbers),
                            'phone_number' => $phoneNumber,
                            'phone_number_id' => $phoneNumberId,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to sync WhatsApp Business Account from Page', [
                        'waba_id' => $wabaId,
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Falls keine WABA-IDs über Pages gefunden wurden, versuche alternativen Ansatz
        // (z.B. wenn WhatsApp direkt mit dem User-Account verknüpft ist)
        if (empty($syncedAccounts)) {
            Log::info('No WhatsApp accounts found via Facebook Pages, trying alternative approach', [
                'user_id' => $userId,
            ]);
            
            // TODO: Hier könnte ein alternativer Ansatz implementiert werden
            // z.B. direkt über einen anderen Endpoint oder manuelle Eingabe
        }

        Log::info('WhatsApp sync completed', [
            'user_id' => $userId,
            'pages_checked' => count($allPages),
            'waba_ids_found' => count($wabaIdsFromPages),
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
