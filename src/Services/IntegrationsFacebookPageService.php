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

        // Direkter Weg: /me/accounts holt alle Pages, die der User verwaltet
        // (inkl. Pagination)
        $url = "https://graph.facebook.com/{$apiVersion}/me/accounts";
        $params = [
            'access_token' => $accessToken,
            'fields' => 'id,name,about,access_token,category,tasks',
            'limit' => 100,
        ];

        $syncedPages = [];
        $allPages = [];

        // Pagination durchlaufen
        do {
            $response = Http::get($url, $params);

            if ($response->failed()) {
                $error = $response->json()['error'] ?? [];
                Log::error('Failed to fetch Facebook pages', [
                    'user_id' => $userId,
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

        if (empty($allPages)) {
            Log::warning('No Facebook pages found', ['user_id' => $userId]);
            return [];
        }

        Log::info('Found Facebook pages', [
            'user_id' => $userId,
            'count' => count($allPages),
        ]);

        // Alle Pages speichern
        foreach ($allPages as $pageData) {
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
                    'integration_connection_id' => $connection->id,
                ]
            );

            $syncedPages[] = $facebookPage;

            Log::info('Facebook Page synced', [
                'page_id' => $facebookPage->id,
                'external_id' => $pageId,
                'user_id' => $userId,
            ]);
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
        // Verwende zuerst den Page Token
        $accessToken = $facebookPage->access_token;
        
        // Falls Page Token fehlt oder abgelaufen, hole neuen Page Token vom Connection Token
        if (!$accessToken || $facebookPage->isExpired()) {
            $connection = $facebookPage->integrationConnection;
            if ($connection) {
                $connectionToken = $this->metaService->getValidAccessToken($connection);
                if ($connectionToken) {
                    // Hole neuen Page Token für diese spezifische Page
                    $accessToken = $this->getPageAccessToken($facebookPage->external_id, $connectionToken);
                    if ($accessToken) {
                        // Speichere den neuen Page Token
                        $facebookPage->access_token = $accessToken;
                        $facebookPage->save();
                    }
                }
            }
        }
        
        if (!$accessToken) {
            throw new \Exception('Kein Access Token für diese Facebook Page gefunden.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');

        // Minimale Felder verwenden, um deprecated fields zu vermeiden
        // Laut Facebook API Docs sind attachments, source, picture deprecated
        $params = [
            'fields' => 'id,message,created_time,permalink_url,full_picture',
            'access_token' => $accessToken,
            'limit' => $limit,
        ];

        // Verwende /feed statt /posts, da /posts deprecated fields verwendet
        $url = "https://graph.facebook.com/{$apiVersion}/{$facebookPage->external_id}/feed";
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

                // Media URL extrahieren - nur full_picture verwenden (andere Felder sind deprecated)
                $mediaUrl = $postData['full_picture'] ?? null;

                $allPosts[] = [
                    'external_id' => $postId,
                    'message' => $postData['message'] ?? null,
                    'story' => null, // Deprecated field entfernt
                    'type' => null, // Deprecated field entfernt
                    'media_url' => $mediaUrl,
                    'permalink_url' => $postData['permalink_url'] ?? null,
                    'published_at' => $createdTime,
                ];
            }

            $url = $data['paging']['next'] ?? null;
        } while ($url);

        return $allPosts;
    }

    /**
     * Holt einen Page Access Token für eine spezifische Page vom Connection Token
     */
    protected function getPageAccessToken(string $pageId, string $connectionToken): ?string
    {
        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        
        try {
            $response = Http::get("https://graph.facebook.com/{$apiVersion}/{$pageId}", [
                'fields' => 'access_token',
                'access_token' => $connectionToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get page access token', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
