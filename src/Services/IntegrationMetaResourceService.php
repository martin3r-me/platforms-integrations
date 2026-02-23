<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationMetaFacebookPage;
use Platform\Integrations\Models\IntegrationMetaInstagramAccount;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service für Meta-Ressourcen (Facebook Pages + Instagram Accounts) als teilbare Ressourcen.
 *
 * Synchronisiert Ressourcen aus der Meta API und markiert entfernte als inaktiv.
 * Verwendet die neuen Resource-Models (integration_meta_facebook_pages, integration_meta_instagram_accounts).
 */
class IntegrationMetaResourceService
{
    protected MetaIntegrationService $metaService;

    public function __construct(MetaIntegrationService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Synchronisiert alle Meta-Ressourcen (Facebook Pages + Instagram Accounts) für eine Connection.
     *
     * @return array{facebook_pages: array{synced: int, deactivated: int}, instagram_accounts: array{synced: int, deactivated: int}}
     */
    public function syncResources(IntegrationConnection $connection): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);

        if (!$accessToken) {
            $this->handleTokenError($connection);
            throw new \Exception('Access Token konnte nicht abgerufen werden. Der Token ist möglicherweise abgelaufen.');
        }

        $pagesResult = $this->syncFacebookPages($connection, $accessToken);
        $accountsResult = $this->syncInstagramAccounts($connection, $accessToken);

        Log::info('Meta resources synced', [
            'connection_id' => $connection->id,
            'user_id' => $connection->owner_user_id,
            'facebook_pages' => $pagesResult,
            'instagram_accounts' => $accountsResult,
        ]);

        return [
            'facebook_pages' => $pagesResult,
            'instagram_accounts' => $accountsResult,
        ];
    }

    /**
     * Synchronisiert Facebook Pages für eine Connection.
     *
     * - Lädt alle Pages via Meta API GET /me/accounts (paginiert)
     * - Erstellt/aktualisiert Einträge in integration_meta_facebook_pages
     * - Markiert nicht mehr vorhandene Pages als is_active=false
     *
     * @return array{synced: int, deactivated: int}
     */
    public function syncFacebookPages(IntegrationConnection $connection, ?string $accessToken = null): array
    {
        if (!$accessToken) {
            $accessToken = $this->metaService->getValidAccessToken($connection);
        }

        if (!$accessToken) {
            $this->handleTokenError($connection);
            throw new \Exception('Access Token konnte nicht abgerufen werden. Der Token ist möglicherweise abgelaufen.');
        }

        $allPages = $this->fetchAllFacebookPagesFromApi($accessToken, $connection);

        $syncedIds = [];

        foreach ($allPages as $pageData) {
            $pageId = (string) $pageData['id'];
            $pageAccessToken = $pageData['access_token'] ?? null;

            $page = IntegrationMetaFacebookPage::updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'page_id' => $pageId,
                ],
                [
                    'name' => $pageData['name'] ?? 'Facebook Page',
                    'access_token' => $pageAccessToken,
                    'is_active' => true,
                ]
            );

            $syncedIds[] = $page->id;
        }

        // Pages, die nicht mehr in der API sind, als inaktiv markieren
        $deactivated = IntegrationMetaFacebookPage::where('connection_id', $connection->id)
            ->where('is_active', true)
            ->when(!empty($syncedIds), fn ($q) => $q->whereNotIn('id', $syncedIds))
            ->update(['is_active' => false]);

        Log::info('Meta Facebook Pages synced (resources)', [
            'connection_id' => $connection->id,
            'user_id' => $connection->owner_user_id,
            'synced' => count($syncedIds),
            'deactivated' => $deactivated,
        ]);

        return [
            'synced' => count($syncedIds),
            'deactivated' => $deactivated,
        ];
    }

    /**
     * Synchronisiert Instagram Accounts für eine Connection.
     *
     * - Iteriert über alle Facebook Pages der Connection
     * - Fragt pro Page GET /{page-id}?fields=instagram_business_account ab
     * - Erstellt/aktualisiert Einträge in integration_meta_instagram_accounts
     * - Markiert nicht mehr vorhandene Accounts als is_active=false
     *
     * @return array{synced: int, deactivated: int}
     */
    public function syncInstagramAccounts(IntegrationConnection $connection, ?string $accessToken = null): array
    {
        if (!$accessToken) {
            $accessToken = $this->metaService->getValidAccessToken($connection);
        }

        if (!$accessToken) {
            $this->handleTokenError($connection);
            throw new \Exception('Access Token konnte nicht abgerufen werden. Der Token ist möglicherweise abgelaufen.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');

        // Facebook Pages aus der Resource-Tabelle holen (inklusive inaktiver, um alle IG-Accounts zu finden)
        $facebookPages = IntegrationMetaFacebookPage::where('connection_id', $connection->id)
            ->where('is_active', true)
            ->get();

        $syncedIds = [];

        foreach ($facebookPages as $facebookPage) {
            $pageAccessToken = $facebookPage->access_token ?? $accessToken;

            try {
                $response = Http::get("https://graph.facebook.com/{$apiVersion}/{$facebookPage->page_id}", [
                    'fields' => 'instagram_business_account',
                    'access_token' => $pageAccessToken,
                ]);

                if ($response->failed()) {
                    $error = $response->json()['error'] ?? [];
                    Log::warning('Failed to fetch Instagram business account for page', [
                        'connection_id' => $connection->id,
                        'page_id' => $facebookPage->page_id,
                        'error' => $error,
                    ]);
                    continue;
                }

                $data = $response->json();

                if (!isset($data['instagram_business_account']['id'])) {
                    continue;
                }

                $instagramId = (string) $data['instagram_business_account']['id'];

                // Instagram Account Details abrufen (username, name, profile_picture_url)
                $accountDetails = $this->fetchInstagramAccountDetails($instagramId, $pageAccessToken, $apiVersion);

                $account = IntegrationMetaInstagramAccount::updateOrCreate(
                    [
                        'connection_id' => $connection->id,
                        'instagram_account_id' => $instagramId,
                    ],
                    [
                        'name' => $accountDetails['name'] ?? $accountDetails['username'] ?? 'Instagram Account',
                        'username' => $accountDetails['username'] ?? 'instagram_account',
                        'profile_picture_url' => $accountDetails['profile_picture_url'] ?? null,
                        'is_active' => true,
                    ]
                );

                $syncedIds[] = $account->id;
            } catch (\Exception $e) {
                Log::warning('Error syncing Instagram account for page', [
                    'connection_id' => $connection->id,
                    'page_id' => $facebookPage->page_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Accounts, die nicht mehr in der API sind, als inaktiv markieren
        $deactivated = IntegrationMetaInstagramAccount::where('connection_id', $connection->id)
            ->where('is_active', true)
            ->when(!empty($syncedIds), fn ($q) => $q->whereNotIn('id', $syncedIds))
            ->update(['is_active' => false]);

        Log::info('Meta Instagram Accounts synced (resources)', [
            'connection_id' => $connection->id,
            'user_id' => $connection->owner_user_id,
            'synced' => count($syncedIds),
            'deactivated' => $deactivated,
        ]);

        return [
            'synced' => count($syncedIds),
            'deactivated' => $deactivated,
        ];
    }

    /**
     * Lädt alle Facebook Pages paginiert aus der Meta API.
     */
    protected function fetchAllFacebookPagesFromApi(string $accessToken, IntegrationConnection $connection): array
    {
        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $url = "https://graph.facebook.com/{$apiVersion}/me/accounts";
        $params = [
            'access_token' => $accessToken,
            'fields' => 'id,name,access_token',
            'limit' => 100,
        ];

        $allPages = [];

        do {
            $response = Http::get($url, $params);

            if ($response->failed()) {
                $error = $response->json()['error'] ?? [];
                $errorCode = $error['code'] ?? null;
                $errorSubcode = $error['error_subcode'] ?? null;

                // Token abgelaufen oder ungültig
                if ($errorCode === 190 || $errorSubcode === 463 || $errorSubcode === 467) {
                    $this->handleTokenError($connection);
                    throw new \Exception('Meta Access Token ist abgelaufen oder ungültig. Bitte erneut verbinden.');
                }

                Log::error('Failed to fetch Facebook pages for resources sync', [
                    'connection_id' => $connection->id,
                    'status' => $response->status(),
                    'error' => $error,
                ]);
                throw new \Exception('Fehler beim Abrufen der Facebook Pages: ' . ($error['message'] ?? 'Unbekannter Fehler'));
            }

            $data = $response->json();
            $pages = $data['data'] ?? [];

            if (!empty($pages)) {
                $allPages = array_merge($allPages, $pages);
            }

            // Nächste Seite holen (falls vorhanden)
            $url = $data['paging']['next'] ?? null;
            $params = []; // Bei next-URL sind alle Parameter bereits enthalten
        } while ($url);

        return $allPages;
    }

    /**
     * Ruft Instagram Account Details ab (username, name, profile_picture_url).
     */
    protected function fetchInstagramAccountDetails(string $instagramId, string $accessToken, string $apiVersion): array
    {
        try {
            $response = Http::get("https://graph.facebook.com/{$apiVersion}/{$instagramId}", [
                'fields' => 'username,name,profile_picture_url',
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Instagram account details', [
                'instagram_id' => $instagramId,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Behandelt Token-Fehler: Setzt Connection auf error-Status.
     */
    protected function handleTokenError(IntegrationConnection $connection): void
    {
        try {
            $connection->status = 'error';
            $connection->last_error = 'Meta Access Token ist abgelaufen oder ungültig. Bitte erneut verbinden.';
            $connection->save();

            Log::warning('Meta connection marked as error due to token issue', [
                'connection_id' => $connection->id,
                'user_id' => $connection->owner_user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update connection status', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
