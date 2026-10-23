<?php

namespace Platform\Integrations\Tools\Github;

use Carbon\Carbon;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationGithubCommit;

/**
 * LLM-Tool: synchronisierte GitHub-Commits einer Connection nach Autor und Zeitraum abrufen.
 *
 * Liest aus den per Sync abgelegten Commits (integration_github_commits), nutzt also
 * die bestehende Anbindung — kein eigenes Token noetig. Nur der Owner der Connection darf abrufen.
 */
class ListGithubCommitsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.github.commits.GET';
    }

    public function getDescription(): string
    {
        return 'GET /connections/{connection_id}/github/commits - Listet synchronisierte GitHub-Commits einer GitHub-Connection '
            . 'mit Uhrzeit (committed_at). Filter: repo (full_name, z.B. "org/repo"), author (GitHub-Login, E-Mail oder Name), '
            . 'since/until (ISO-Datum oder Datum+Zeit; ein reines until-Datum gilt bis Tagesende). '
            . 'Rueckgabe je Commit: repo, sha, message, author, committed_at, url. Paginierung ueber limit/offset. '
            . 'Hinweis: Datenbasis ist der Sync (integrations.github.resources.SYNC) — nur Standard-Branch, ggf. nicht tagesaktuell. '
            . 'Nur der Owner der Connection kann Commits abrufen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der GitHub-IntegrationConnection.',
                ],
                'repo' => [
                    'type' => 'string',
                    'description' => 'Optional: full_name des Repos (owner/name). Ohne Angabe: alle Repos der Connection.',
                ],
                'author' => [
                    'type' => 'string',
                    'description' => 'Optional: GitHub-Login, E-Mail oder Name des Autors (exakter Treffer, Gross-/Kleinschreibung egal).',
                ],
                'since' => [
                    'type' => 'string',
                    'description' => 'Optional: ab diesem Zeitpunkt (ISO, z.B. 2026-10-23 oder 2026-10-23T08:00:00+02:00).',
                ],
                'until' => [
                    'type' => 'string',
                    'description' => 'Optional: bis zu diesem Zeitpunkt (ISO; reines Datum = bis Tagesende).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: max. Anzahl (Standard 100, max 500).',
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Optional: Offset fuer Paginierung (Standard 0).',
                ],
            ],
            'required' => ['connection_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $connectionId = $arguments['connection_id'] ?? null;
        if (!$connectionId) {
            return ToolResult::error('VALIDATION_ERROR', 'connection_id ist erforderlich.');
        }

        try {
            $connection = IntegrationConnection::find($connectionId);
            if (!$connection) {
                return ToolResult::error('NOT_FOUND', "Connection #{$connectionId} nicht gefunden.");
            }

            if (!$connection->isOwner($context->user)) {
                return ToolResult::error('FORBIDDEN', 'Nur der Owner der Connection kann GitHub Commits abrufen.');
            }

            try {
                $since = !empty($arguments['since']) ? Carbon::parse($arguments['since']) : null;
                $until = null;
                if (!empty($arguments['until'])) {
                    $until = Carbon::parse($arguments['until']);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($arguments['until']))) {
                        $until = $until->endOfDay();
                    }
                }
            } catch (\Throwable $e) {
                return ToolResult::error('VALIDATION_ERROR', 'since/until muessen ein gueltiges ISO-Datum sein.');
            }

            $limit = max(1, min((int) ($arguments['limit'] ?? 100), 500));
            $offset = max(0, (int) ($arguments['offset'] ?? 0));

            $query = IntegrationGithubCommit::query()
                ->with('repo:id,full_name')
                ->whereHas('repo', function ($q) use ($connectionId, $arguments) {
                    $q->where('connection_id', $connectionId);
                    if (!empty($arguments['repo'])) {
                        $q->where('full_name', $arguments['repo']);
                    }
                });

            if (!empty($arguments['author'])) {
                $author = mb_strtolower(trim($arguments['author']));
                $query->where(function ($q) use ($author) {
                    $q->whereRaw('LOWER(author_login) = ?', [$author])
                        ->orWhereRaw('LOWER(author_email) = ?', [$author])
                        ->orWhereRaw('LOWER(author_name) = ?', [$author]);
                });
            }

            if ($since) {
                $query->where('committed_at', '>=', $since);
            }
            if ($until) {
                $query->where('committed_at', '<=', $until);
            }

            $total = (clone $query)->count();
            $commits = $query->orderByDesc('committed_at')->offset($offset)->limit($limit)->get();

            return ToolResult::success([
                'connection_id' => $connection->id,
                'commits' => $commits->map(fn ($c) => [
                    'repo' => $c->repo?->full_name,
                    'sha' => $c->sha,
                    'message' => $c->message,
                    'author_login' => $c->author_login,
                    'author_name' => $c->author_name,
                    'author_email' => $c->author_email,
                    'committed_at' => $c->committed_at?->toIso8601String(),
                    'url' => $c->url,
                ])->toArray(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $commits->count()) < $total,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['integrations', 'github', 'commits', 'activity'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
