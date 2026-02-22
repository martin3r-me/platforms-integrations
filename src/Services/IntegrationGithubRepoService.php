<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationGithubRepo;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service für GitHub Repos als teilbare Ressourcen (integration_github_repos).
 *
 * Synchronisiert Repos aus der GitHub API und markiert entfernte Repos als inaktiv.
 */
class IntegrationGithubRepoService
{
    protected GithubIntegrationService $githubService;

    public function __construct(GithubIntegrationService $githubService)
    {
        $this->githubService = $githubService;
    }

    /**
     * Synchronisiert alle GitHub Repos für eine Connection.
     *
     * - Lädt alle Repos via GitHub API (paginiert)
     * - Erstellt/aktualisiert Einträge in integration_github_repos
     * - Markiert nicht mehr vorhandene Repos als is_active=false
     *
     * @return array{synced: int, deactivated: int}
     */
    public function syncRepos(IntegrationConnection $connection): array
    {
        $accessToken = $this->githubService->getValidAccessToken($connection);

        if (!$accessToken) {
            throw new \Exception('Access Token konnte nicht abgerufen werden.');
        }

        $allRepos = $this->fetchAllReposFromApi($accessToken, $connection);

        $syncedIds = [];

        foreach ($allRepos as $repoData) {
            $githubRepoId = (int) $repoData['id'];

            $repo = IntegrationGithubRepo::updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'github_repo_id' => $githubRepoId,
                ],
                [
                    'full_name' => $repoData['full_name'] ?? '',
                    'name' => $repoData['name'] ?? '',
                    'owner' => $repoData['owner']['login'] ?? '',
                    'is_private' => $repoData['private'] ?? false,
                    'is_active' => true,
                ]
            );

            $syncedIds[] = $repo->id;
        }

        // Repos, die nicht mehr in der API sind, als inaktiv markieren
        $deactivated = IntegrationGithubRepo::where('connection_id', $connection->id)
            ->where('is_active', true)
            ->when(!empty($syncedIds), fn ($q) => $q->whereNotIn('id', $syncedIds))
            ->update(['is_active' => false]);

        Log::info('GitHub Repos synced (resources)', [
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
     * Lädt alle Repos paginiert aus der GitHub API.
     */
    protected function fetchAllReposFromApi(string $accessToken, IntegrationConnection $connection): array
    {
        $url = 'https://api.github.com/user/repos';
        $params = [
            'per_page' => 100,
            'sort' => 'updated',
            'direction' => 'desc',
        ];

        $allRepos = [];
        $page = 1;

        do {
            $params['page'] = $page;

            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($url, $params);

            if ($response->failed()) {
                $error = $response->json() ?? [];
                Log::error('Failed to fetch GitHub repos for resources sync', [
                    'connection_id' => $connection->id,
                    'status' => $response->status(),
                    'error' => $error,
                ]);
                throw new \Exception('Fehler beim Abrufen der GitHub Repos: ' . ($error['message'] ?? 'Unbekannter Fehler'));
            }

            $repos = $response->json();

            if (!empty($repos) && is_array($repos)) {
                $allRepos = array_merge($allRepos, $repos);
            }

            $linkHeader = $response->header('Link');
            $hasNextPage = $linkHeader && strpos($linkHeader, 'rel="next"') !== false;
            $page++;
        } while ($hasNextPage && !empty($repos));

        return $allRepos;
    }
}
