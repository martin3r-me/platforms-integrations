<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationGithubRepo;
use Platform\Integrations\Models\IntegrationGithubCommit;
use Platform\Integrations\Models\IntegrationGithubPullRequest;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Synchronisiert Commits und Pull Requests für GitHub Repos.
 */
class IntegrationGithubDataService
{
    protected GithubIntegrationService $githubService;

    public function __construct(GithubIntegrationService $githubService)
    {
        $this->githubService = $githubService;
    }

    /**
     * Synchronisiert Commits und PRs für alle aktiven Repos einer Connection.
     *
     * @return array{commits_synced: int, prs_synced: int, repos_processed: int}
     */
    public function syncAll(IntegrationConnection $connection): array
    {
        $accessToken = $this->githubService->getValidAccessToken($connection);

        if (!$accessToken) {
            throw new \Exception('Access Token konnte nicht abgerufen werden.');
        }

        $repos = IntegrationGithubRepo::where('connection_id', $connection->id)
            ->where('is_active', true)
            ->get();

        $totalCommits = 0;
        $totalPrs = 0;

        foreach ($repos as $repo) {
            try {
                $totalCommits += $this->syncCommits($repo, $accessToken);
                $totalPrs += $this->syncPullRequests($repo, $accessToken);
            } catch (\Exception $e) {
                Log::warning('GitHub data sync failed for repo', [
                    'repo_id' => $repo->id,
                    'full_name' => $repo->full_name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GitHub data synced', [
            'connection_id' => $connection->id,
            'repos_processed' => $repos->count(),
            'commits_synced' => $totalCommits,
            'prs_synced' => $totalPrs,
        ]);

        return [
            'commits_synced' => $totalCommits,
            'prs_synced' => $totalPrs,
            'repos_processed' => $repos->count(),
        ];
    }

    /**
     * Synchronisiert die letzten Commits eines Repos (max 100, seit letztem bekannten Commit).
     */
    public function syncCommits(IntegrationGithubRepo $repo, string $accessToken): int
    {
        $since = $repo->commits()->max('committed_at');

        $params = [
            'per_page' => 100,
        ];

        if ($since) {
            // Add 1 second to avoid re-fetching the last known commit
            $params['since'] = Carbon::parse($since)->addSecond()->toIso8601String();
        }

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get("https://api.github.com/repos/{$repo->full_name}/commits", $params);

        if ($response->status() === 409) {
            // Empty repository
            return 0;
        }

        if ($response->failed()) {
            throw new \Exception("Commits fetch failed for {$repo->full_name}: " . ($response->json()['message'] ?? $response->status()));
        }

        $commits = $response->json();
        $synced = 0;

        foreach ($commits as $commitData) {
            $sha = $commitData['sha'] ?? null;
            if (!$sha) {
                continue;
            }

            IntegrationGithubCommit::updateOrCreate(
                [
                    'repo_id' => $repo->id,
                    'sha' => $sha,
                ],
                [
                    'message' => $commitData['commit']['message'] ?? '',
                    'author_name' => $commitData['commit']['author']['name'] ?? null,
                    'author_email' => $commitData['commit']['author']['email'] ?? null,
                    'author_login' => $commitData['author']['login'] ?? null,
                    'committer_name' => $commitData['commit']['committer']['name'] ?? null,
                    'committer_login' => $commitData['committer']['login'] ?? null,
                    'committed_at' => isset($commitData['commit']['committer']['date'])
                        ? Carbon::parse($commitData['commit']['committer']['date'])
                        : null,
                    'url' => $commitData['html_url'] ?? null,
                ]
            );

            $synced++;
        }

        return $synced;
    }

    /**
     * Synchronisiert Pull Requests eines Repos (offen + kürzlich aktualisiert).
     */
    public function syncPullRequests(IntegrationGithubRepo $repo, string $accessToken): int
    {
        $synced = 0;

        // Fetch open PRs + recently updated (closed/merged)
        foreach (['open', 'closed'] as $state) {
            $params = [
                'state' => $state,
                'per_page' => 30,
                'sort' => 'updated',
                'direction' => 'desc',
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get("https://api.github.com/repos/{$repo->full_name}/pulls", $params);

            if ($response->failed()) {
                throw new \Exception("PRs fetch failed for {$repo->full_name}: " . ($response->json()['message'] ?? $response->status()));
            }

            $prs = $response->json();

            foreach ($prs as $prData) {
                $githubPrId = (int) ($prData['id'] ?? 0);
                if (!$githubPrId) {
                    continue;
                }

                IntegrationGithubPullRequest::updateOrCreate(
                    [
                        'repo_id' => $repo->id,
                        'github_pr_id' => $githubPrId,
                    ],
                    [
                        'number' => $prData['number'] ?? 0,
                        'title' => $prData['title'] ?? '',
                        'body' => $prData['body'] ?? null,
                        'state' => $prData['state'] ?? 'open',
                        'author_login' => $prData['user']['login'] ?? null,
                        'head_ref' => $prData['head']['ref'] ?? null,
                        'base_ref' => $prData['base']['ref'] ?? null,
                        'merge_commit_sha' => $prData['merge_commit_sha'] ?? null,
                        'is_merged' => !empty($prData['merged_at']),
                        'is_draft' => $prData['draft'] ?? false,
                        'additions' => $prData['additions'] ?? null,
                        'deletions' => $prData['deletions'] ?? null,
                        'changed_files' => $prData['changed_files'] ?? null,
                        'comments_count' => ($prData['comments'] ?? 0) + ($prData['review_comments'] ?? 0),
                        'url' => $prData['html_url'] ?? null,
                        'github_created_at' => isset($prData['created_at']) ? Carbon::parse($prData['created_at']) : null,
                        'github_updated_at' => isset($prData['updated_at']) ? Carbon::parse($prData['updated_at']) : null,
                        'merged_at' => isset($prData['merged_at']) ? Carbon::parse($prData['merged_at']) : null,
                        'closed_at' => isset($prData['closed_at']) ? Carbon::parse($prData['closed_at']) : null,
                    ]
                );

                $synced++;
            }
        }

        return $synced;
    }
}
