<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationsGithubRepository;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service für GitHub Repositories Management
 */
class IntegrationsGithubRepositoryService
{
    protected GithubIntegrationService $githubService;

    public function __construct(GithubIntegrationService $githubService)
    {
        $this->githubService = $githubService;
    }

    /**
     * Ruft alle GitHub Repositories für einen User ab und speichert sie
     * 
     * @param IntegrationConnection $connection
     * @return array
     */
    public function syncGithubRepositoriesForUser(IntegrationConnection $connection): array
    {
        $accessToken = $this->githubService->getValidAccessToken($connection);
        
        if (!$accessToken) {
            throw new \Exception('Access Token konnte nicht abgerufen werden.');
        }

        $userId = $connection->owner_user_id;

        // GitHub API: /user/repos holt alle Repositories des authentifizierten Users
        $url = 'https://api.github.com/user/repos';
        $params = [
            'per_page' => 100,
            'sort' => 'updated',
            'direction' => 'desc',
        ];

        $syncedRepos = [];
        $allRepos = [];

        // Pagination durchlaufen
        $page = 1;
        do {
            $params['page'] = $page;
            
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($url, $params);

            if ($response->failed()) {
                $error = $response->json() ?? [];
                Log::error('Failed to fetch GitHub repositories', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'error' => $error,
                ]);
                throw new \Exception('Fehler beim Abrufen der GitHub Repositories: ' . ($error['message'] ?? 'Unbekannter Fehler'));
            }

            $repos = $response->json();

            if (!empty($repos) && is_array($repos)) {
                $allRepos = array_merge($allRepos, $repos);
            }

            // Prüfe ob es eine nächste Seite gibt
            $linkHeader = $response->header('Link');
            $hasNextPage = $linkHeader && strpos($linkHeader, 'rel="next"') !== false;
            $page++;
        } while ($hasNextPage && !empty($repos));

        if (empty($allRepos)) {
            Log::warning('No GitHub repositories found', ['user_id' => $userId]);
            return [];
        }

        Log::info('Found GitHub repositories', [
            'user_id' => $userId,
            'count' => count($allRepos),
        ]);

        // Alle Repositories speichern
        foreach ($allRepos as $repoData) {
            $repoId = (string) $repoData['id'];
            $fullName = $repoData['full_name'] ?? '';
            $name = $repoData['name'] ?? '';
            $owner = $repoData['owner']['login'] ?? '';

            $githubRepo = IntegrationsGithubRepository::updateOrCreate(
                [
                    'external_id' => $repoId,
                    'user_id' => $userId,
                ],
                [
                    'full_name' => $fullName,
                    'name' => $name,
                    'owner' => $owner,
                    'description' => $repoData['description'] ?? null,
                    'url' => $repoData['html_url'] ?? '',
                    'clone_url' => $repoData['clone_url'] ?? null,
                    'default_branch' => $repoData['default_branch'] ?? 'main',
                    'is_private' => $repoData['private'] ?? false,
                    'is_fork' => $repoData['fork'] ?? false,
                    'is_archived' => $repoData['archived'] ?? false,
                    'language' => $repoData['language'] ?? null,
                    'stars_count' => $repoData['stargazers_count'] ?? 0,
                    'forks_count' => $repoData['forks_count'] ?? 0,
                    'open_issues_count' => $repoData['open_issues_count'] ?? 0,
                    'github_created_at' => isset($repoData['created_at']) 
                        ? Carbon::parse($repoData['created_at']) 
                        : null,
                    'github_updated_at' => isset($repoData['updated_at']) 
                        ? Carbon::parse($repoData['updated_at']) 
                        : null,
                    'github_pushed_at' => isset($repoData['pushed_at']) 
                        ? Carbon::parse($repoData['pushed_at']) 
                        : null,
                    'metadata' => [
                        'node_id' => $repoData['node_id'] ?? null,
                        'size' => $repoData['size'] ?? null,
                        'has_issues' => $repoData['has_issues'] ?? false,
                        'has_projects' => $repoData['has_projects'] ?? false,
                        'has_wiki' => $repoData['has_wiki'] ?? false,
                        'has_pages' => $repoData['has_pages'] ?? false,
                        'has_downloads' => $repoData['has_downloads'] ?? false,
                        'topics' => $repoData['topics'] ?? [],
                    ],
                    'integration_connection_id' => $connection->id,
                ]
            );

            $syncedRepos[] = $githubRepo;

            Log::info('GitHub Repository synced', [
                'repo_id' => $githubRepo->id,
                'external_id' => $repoId,
                'full_name' => $fullName,
                'user_id' => $userId,
            ]);
        }

        return $syncedRepos;
    }
}
