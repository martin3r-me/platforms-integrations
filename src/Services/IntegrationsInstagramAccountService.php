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

        // Fallback: Versuche über Business Accounts (mit Pagination)
        if (empty($syncedAccounts)) {
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
                    break;
                }

                $businessData = $businessResponse->json();
                $businessAccounts = $businessData['data'] ?? [];

                if (!empty($businessAccounts)) {
                    $allBusinessAccounts = array_merge($allBusinessAccounts, $businessAccounts);
                }

                $businessUrl = $businessData['paging']['next'] ?? null;
                $businessParams = []; // Bei next-URL sind alle Parameter bereits enthalten
            } while ($businessUrl);

            Log::info('Found business accounts', [
                'user_id' => $userId,
                'count' => count($allBusinessAccounts),
            ]);

            // Für jede Business Account die Instagram Accounts holen (mit Pagination)
            foreach ($allBusinessAccounts as $businessAccount) {
                $businessId = $businessAccount['id'];
                
                $instagramUrl = "https://graph.facebook.com/{$apiVersion}/{$businessId}/owned_instagram_accounts";
                $instagramParams = [
                    'access_token' => $accessToken,
                    'limit' => 100,
                ];

                // Pagination für Instagram Accounts pro Business Account
                do {
                    $instagramResponse = Http::get($instagramUrl, $instagramParams);

                    if ($instagramResponse->failed()) {
                        Log::error('Failed to fetch Instagram accounts for business', [
                            'business_id' => $businessId,
                            'error' => $instagramResponse->json()['error'] ?? [],
                        ]);
                        break;
                    }

                    $instagramData = $instagramResponse->json();
                    $instagramAccounts = $instagramData['data'] ?? [];

                    foreach ($instagramAccounts as $accountData) {
                        $instagramId = $accountData['id'] ?? null;

                        if ($instagramId) {
                            // Prüfe, ob Account bereits über Facebook Page synchronisiert wurde
                            $existingAccount = IntegrationsInstagramAccount::where('external_id', $instagramId)
                                ->where('user_id', $userId)
                                ->first();

                            if ($existingAccount) {
                                Log::info('Instagram Account already synced via Facebook Page', [
                                    'instagram_account_id' => $existingAccount->id,
                                    'external_id' => $instagramId,
                                ]);
                                continue;
                            }

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

                    // Nächste Seite holen
                    $instagramUrl = $instagramData['paging']['next'] ?? null;
                    $instagramParams = []; // Bei next-URL sind alle Parameter bereits enthalten
                } while ($instagramUrl);
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
