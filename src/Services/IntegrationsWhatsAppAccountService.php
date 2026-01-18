<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationsWhatsAppAccount;
use Platform\Integrations\Models\IntegrationsMetaToken;
use Platform\Integrations\Services\IntegrationsMetaTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service für WhatsApp Business Accounts Management (generische Integrations-Logik)
 */
class IntegrationsWhatsAppAccountService
{
    protected IntegrationsMetaTokenService $tokenService;

    public function __construct(IntegrationsMetaTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Ruft alle WhatsApp Business Accounts für einen User ab und speichert sie (generisch)
     * 
     * @param IntegrationsMetaToken $metaToken
     * @return array
     */
    public function syncWhatsAppAccountsForUser(IntegrationsMetaToken $metaToken): array
    {
        $accessToken = $this->tokenService->getValidAccessToken($metaToken);
        
        if (!$accessToken) {
            throw new \Exception('Access Token konnte nicht abgerufen werden.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $userId = $metaToken->user_id;

        // Business Accounts holen
        $businessResponse = Http::get("https://graph.facebook.com/{$apiVersion}/me/businesses", [
            'access_token' => $accessToken,
        ]);

        if ($businessResponse->failed()) {
            $error = $businessResponse->json()['error'] ?? [];
            throw new \Exception('Fehler beim Abrufen der Business Accounts: ' . ($error['message'] ?? 'Unbekannter Fehler'));
        }

        $businessData = $businessResponse->json();
        $businessAccounts = $businessData['data'] ?? [];

        if (empty($businessAccounts)) {
            Log::warning('No business accounts found', ['user_id' => $userId]);
            return [];
        }

        $syncedAccounts = [];

        // Für jede Business Account die WhatsApp Business Accounts holen
        foreach ($businessAccounts as $businessAccount) {
            $businessId = $businessAccount['id'];
            
            $wabaResponse = Http::get("https://graph.facebook.com/{$apiVersion}/{$businessId}/owned_whatsapp_business_accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,account_review_status,message_template_namespace,primary_funding_id,timezone_id,currency,payment_model,is_enabled,is_permanently_closed,on_behalf_of_business_info,owner_business_info',
            ]);

            if ($wabaResponse->failed()) {
                Log::error('Failed to fetch WhatsApp Business Accounts for business', [
                    'business_id' => $businessId,
                    'error' => $wabaResponse->json()['error'] ?? [],
                ]);
                continue;
            }

            $wabaData = $wabaResponse->json();
            $wabaAccounts = $wabaData['data'] ?? [];

            foreach ($wabaAccounts as $wabaAccountData) {
                $wabaId = $wabaAccountData['id'];
                $wabaName = $wabaAccountData['name'] ?? 'WhatsApp Business Account';

                // Phone Numbers für diesen WABA holen
                $phoneNumbersResponse = Http::get("https://graph.facebook.com/{$apiVersion}/{$wabaId}/phone_numbers", [
                    'access_token' => $accessToken,
                    'fields' => 'id,display_phone_number,verified_name,code_verification_status,quality_rating,throughput,last_onboarded_time',
                ]);

                $phoneNumbers = [];
                if ($phoneNumbersResponse->successful()) {
                    $phoneNumbersData = $phoneNumbersResponse->json();
                    $phoneNumbers = $phoneNumbersData['data'] ?? [];
                }

                // Erste Phone Number als primäre verwenden
                $primaryPhoneNumber = $phoneNumbers[0] ?? null;
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
                ]);
            }
        }

        return $syncedAccounts;
    }
}
