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

        // Business Accounts mit Pagination holen
        $businessUrl = "https://graph.facebook.com/{$apiVersion}/me/businesses";
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
            
            $wabaUrl = "https://graph.facebook.com/{$apiVersion}/{$businessId}/owned_whatsapp_business_accounts";
            $wabaParams = [
                'access_token' => $accessToken,
                'fields' => 'id,name,account_review_status,message_template_namespace,primary_funding_id,timezone_id,currency,payment_model,is_enabled,is_permanently_closed,on_behalf_of_business_info,owner_business_info',
                'limit' => 100,
            ];

            // Pagination für WhatsApp Business Accounts pro Business Account
            do {
                $wabaResponse = Http::get($wabaUrl, $wabaParams);

                if ($wabaResponse->failed()) {
                    Log::error('Failed to fetch WhatsApp Business Accounts for business', [
                        'business_id' => $businessId,
                        'error' => $wabaResponse->json()['error'] ?? [],
                    ]);
                    break;
                }

                $wabaData = $wabaResponse->json();
                $wabaAccounts = $wabaData['data'] ?? [];

                foreach ($wabaAccounts as $wabaAccountData) {
                    $wabaId = $wabaAccountData['id'];
                    $wabaName = $wabaAccountData['name'] ?? 'WhatsApp Business Account';

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

                    // WhatsApp Account auf User-Ebene erstellen oder aktualisieren
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
                            'access_token' => $accessToken, // WABA-spezifischer Token könnte später separat geholt werden
                            'verified_at' => isset($primaryPhoneNumber['verified_name']) ? now() : null,
                        ]
                    );

                    $syncedAccounts[] = $whatsappAccount;

                    Log::info('WhatsApp Business Account synced', [
                        'account_id' => $whatsappAccount->id,
                        'external_id' => $wabaId,
                        'user_id' => $userId,
                        'phone_numbers_count' => count($allPhoneNumbers),
                    ]);
                }

                // Nächste Seite holen
                $wabaUrl = $wabaData['paging']['next'] ?? null;
                $wabaParams = []; // Bei next-URL sind alle Parameter bereits enthalten
            } while ($wabaUrl);
        }

        return $syncedAccounts;
    }
}
