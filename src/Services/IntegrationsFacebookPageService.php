<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service für Facebook Pages Management (generische Integrations-Logik)
 */
class IntegrationsFacebookPageService
{
    protected MetaIntegrationService $metaService;

    public function __construct(MetaIntegrationService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Ruft alle Facebook Pages für einen User ab und speichert sie (generisch)
     * 
     * @param IntegrationConnection $connection
     * @return array
     */
    public function syncFacebookPagesForUser(IntegrationConnection $connection): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);
        
        if (!$accessToken) {
            throw new \Exception('Access Token konnte nicht abgerufen werden.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $userId = $connection->owner_user_id;

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

        $syncedPages = [];

        // Für jede Business Account die Pages holen
        foreach ($businessAccounts as $businessAccount) {
            $businessId = $businessAccount['id'];
            
            $pagesResponse = Http::get("https://graph.facebook.com/{$apiVersion}/{$businessId}/owned_pages", [
                'access_token' => $accessToken,
            ]);

            if ($pagesResponse->failed()) {
                Log::error('Failed to fetch pages for business', [
                    'business_id' => $businessId,
                    'error' => $pagesResponse->json()['error'] ?? [],
                ]);
                continue;
            }

            $pagesData = $pagesResponse->json();
            $pages = $pagesData['data'] ?? [];

            foreach ($pages as $pageData) {
                $pageId = $pageData['id'];
                $pageName = $pageData['name'] ?? 'Facebook Page';
                $pageAccessToken = $pageData['access_token'] ?? $accessToken;

                // Page auf User-Ebene erstellen oder aktualisieren
                $credentials = $connection->credentials ?? [];
                $oauth = $credentials['oauth'] ?? [];
                
                $facebookPage = IntegrationsFacebookPage::updateOrCreate(
                    [
                        'external_id' => $pageId,
                        'user_id' => $userId,
                    ],
                    [
                        'name' => $pageName,
                        'description' => $pageData['about'] ?? null,
                        'access_token' => $pageAccessToken,
                        'refresh_token' => $oauth['refresh_token'] ?? null,
                        'expires_at' => isset($oauth['expires_at']) ? \Carbon\Carbon::createFromTimestamp($oauth['expires_at']) : null,
                        'token_type' => $oauth['token_type'] ?? 'Bearer',
                        'scopes' => $oauth['scope'] ? explode(' ', $oauth['scope']) : [],
                    ]
                );

                $syncedPages[] = $facebookPage;

                Log::info('Facebook Page synced', [
                    'page_id' => $facebookPage->id,
                    'external_id' => $pageId,
                    'user_id' => $userId,
                ]);
            }
        }

        return $syncedPages;
    }

    /**
     * Ruft alle Facebook Posts für eine Facebook Page ab (generisch)
     * 
     * @param IntegrationsFacebookPage $facebookPage
     * @param int $limit
     * @return array Array mit Post-Daten (nicht Models, da Brands-spezifisch)
     */
    public function fetchFacebookPosts(IntegrationsFacebookPage $facebookPage, int $limit = 100): array
    {
        $accessToken = $facebookPage->access_token;
        
        if (!$accessToken) {
            throw new \Exception('Kein Access Token für diese Facebook Page gefunden.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');

        $params = [
            'fields' => 'id,message,story,created_time,permalink_url,attachments,type,status_type',
            'access_token' => $accessToken,
            'limit' => $limit,
        ];

        $url = "https://graph.facebook.com/{$apiVersion}/{$facebookPage->external_id}/posts";
        $allPosts = [];

        do {
            $response = Http::get($url, $params);

            if ($response->failed()) {
                $error = $response->json()['error'] ?? [];
                Log::error('Failed to fetch Facebook posts', [
                    'facebook_page_id' => $facebookPage->id,
                    'error' => $error,
                ]);
                break;
            }

            $data = $response->json();

            if (!isset($data['data']) || empty($data['data'])) {
                break;
            }

            foreach ($data['data'] as $postData) {
                $postId = $postData['id'];
                $createdTime = isset($postData['created_time']) 
                    ? Carbon::parse($postData['created_time'])->format('Y-m-d H:i:s')
                    : null;

                // Media URL aus Attachments extrahieren
                $mediaUrl = null;
                if (isset($postData['attachments']['data'][0]['media'])) {
                    $mediaUrl = $postData['attachments']['data'][0]['media']['image']['src'] ?? null;
                }

                $allPosts[] = [
                    'external_id' => $postId,
                    'message' => $postData['message'] ?? null,
                    'story' => $postData['story'] ?? null,
                    'type' => $postData['type'] ?? $postData['status_type'] ?? null,
                    'media_url' => $mediaUrl,
                    'permalink_url' => $postData['permalink_url'] ?? null,
                    'published_at' => $createdTime,
                ];
            }

            $url = $data['paging']['next'] ?? null;
        } while ($url);

        return $allPosts;
    }
}
