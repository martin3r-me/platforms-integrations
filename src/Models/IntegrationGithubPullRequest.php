<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationGithubPullRequest extends Model
{
    protected $table = 'integration_github_pull_requests';

    protected $fillable = [
        'repo_id',
        'github_pr_id',
        'number',
        'title',
        'body',
        'state',
        'author_login',
        'head_ref',
        'base_ref',
        'merge_commit_sha',
        'is_merged',
        'is_draft',
        'additions',
        'deletions',
        'changed_files',
        'comments_count',
        'url',
        'github_created_at',
        'github_updated_at',
        'merged_at',
        'closed_at',
    ];

    protected $casts = [
        'github_pr_id' => 'integer',
        'number' => 'integer',
        'is_merged' => 'boolean',
        'is_draft' => 'boolean',
        'additions' => 'integer',
        'deletions' => 'integer',
        'changed_files' => 'integer',
        'comments_count' => 'integer',
        'github_created_at' => 'datetime',
        'github_updated_at' => 'datetime',
        'merged_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function repo(): BelongsTo
    {
        return $this->belongsTo(IntegrationGithubRepo::class, 'repo_id');
    }
}
