<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service für Instagram Accounts Management (generische Integrations-Logik)
 */
class IntegrationsInstagramAccountService
{
    protected MetaIntegrationService $metaService;

    public function __construct(MetaIntegrationService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Ruft Instagram Accounts für einen User ab und speichert sie (generisch)
     * 
     * @param IntegrationConnection $connection
     * @return array
     */
    public function syncInstagramAccountsForUser(IntegrationConnection $connection): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);
        
        if (!$accessToken) {
            throw new \Exception('Access Token konnte nicht abgerufen werden.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $userId = $connection->owner_user_id;

        // Instagram Accounts über Facebook Pages holen (alle Pages des Users)
        $facebookPages = IntegrationsFacebookPage::where('user_id', $userId)->get();
        $syncedAccounts = [];

        foreach ($facebookPages as $facebookPage) {
            $pageAccessToken = $facebookPage->access_token ?? $accessToken;

            // Versuche Instagram Account direkt über die Facebook Page zu holen
            $instagramResponse = Http::get("https://graph.facebook.com/{$apiVersion}/{$facebookPage->external_id}", [
                'fields' => 'instagram_business_account',
                'access_token' => $pageAccessToken,
            ]);

            if ($instagramResponse->successful()) {
                $instagramData = $instagramResponse->json();
                
                if (isset($instagramData['instagram_business_account'])) {
                    $accountData = $instagramData['instagram_business_account'];
                    $instagramId = $accountData['id'] ?? null;

                    if ($instagramId) {
                        // Instagram Username separat abrufen
                        $username = $this->fetchInstagramUsername($instagramId, $pageAccessToken, $apiVersion);
                        
                        // Account auf User-Ebene erstellen oder aktualisieren
                        $credentials = $connection->credentials ?? [];
                        $oauth = $credentials['oauth'] ?? [];
                        
                        $instagramAccount = IntegrationsInstagramAccount::updateOrCreate(
                            [
                                'external_id' => $instagramId,
                                'user_id' => $userId,
                            ],
                            [
                                'username' => $username,
                                'description' => null,
                                'access_token' => $pageAccessToken,
                                'refresh_token' => $oauth['refresh_token'] ?? null,
                                'expires_at' => isset($oauth['expires_at']) ? \Carbon\Carbon::createFromTimestamp($oauth['expires_at']) : null,
                                'token_type' => $oauth['token_type'] ?? 'Bearer',
                                'scopes' => $oauth['scope'] ? explode(' ', $oauth['scope']) : [],
                                'facebook_page_id' => $facebookPage->id,
                            ]
                        );

                        $syncedAccounts[] = $instagramAccount;

                        Log::info('Instagram Account synced via Facebook Page', [
                            'instagram_account_id' => $instagramAccount->id,
                            'external_id' => $instagramId,
                            'facebook_page_id' => $facebookPage->id,
                            'user_id' => $userId,
                        ]);
                    }
                }
            }
        }

        // Fallback: Versuche über Business Account
        if (empty($syncedAccounts)) {
            $businessResponse = Http::get("https://graph.facebook.com/{$apiVersion}/me/businesses", [
                'access_token' => $accessToken,
            ]);

            if ($businessResponse->successful()) {
                $businessData = $businessResponse->json();
                $businessAccounts = $businessData['data'] ?? [];

                foreach ($businessAccounts as $businessAccount) {
                    $businessId = $businessAccount['id'];
                    
                    $instagramResponse = Http::get("https://graph.facebook.com/{$apiVersion}/{$businessId}/owned_instagram_accounts", [
                        'access_token' => $accessToken,
                    ]);

                    if ($instagramResponse->successful()) {
                        $instagramData = $instagramResponse->json();
                        $instagramAccounts = $instagramData['data'] ?? [];

                        foreach ($instagramAccounts as $accountData) {
                            $instagramId = $accountData['id'] ?? null;

                            if ($instagramId) {
                                // Verwende den accessToken für den Username-Abruf
                                $username = $this->fetchInstagramUsername($instagramId, $accessToken, $apiVersion);
                                
                                // Account auf User-Ebene erstellen oder aktualisieren
                                $credentials = $connection->credentials ?? [];
                                $oauth = $credentials['oauth'] ?? [];
                                
                                $instagramAccount = IntegrationsInstagramAccount::updateOrCreate(
                                    [
                                        'external_id' => $instagramId,
                                        'user_id' => $userId,
                                    ],
                                    [
                                        'username' => $username,
                                        'description' => null,
                                        'access_token' => $accessToken,
                                        'refresh_token' => $oauth['refresh_token'] ?? null,
                                        'expires_at' => isset($oauth['expires_at']) ? \Carbon\Carbon::createFromTimestamp($oauth['expires_at']) : null,
                                        'token_type' => $oauth['token_type'] ?? 'Bearer',
                                        'scopes' => $oauth['scope'] ? explode(' ', $oauth['scope']) : [],
                                        'facebook_page_id' => null,
                                    ]
                                );

                                $syncedAccounts[] = $instagramAccount;

                                Log::info('Instagram Account synced via Business Account', [
                                    'instagram_account_id' => $instagramAccount->id,
                                    'external_id' => $instagramId,
                                    'user_id' => $userId,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        return $syncedAccounts;
    }

    /**
     * Ruft den Instagram Username ab
     */
    protected function fetchInstagramUsername(string $instagramId, string $accessToken, string $apiVersion): string
    {
        try {
            $response = Http::get("https://graph.facebook.com/{$apiVersion}/{$instagramId}", [
                'fields' => 'username',
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['username'] ?? 'instagram_account';
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Instagram username', [
                'instagram_id' => $instagramId,
                'error' => $e->getMessage(),
            ]);
        }

        return 'instagram_account';
    }
}
