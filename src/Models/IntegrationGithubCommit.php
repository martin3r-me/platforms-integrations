<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationGithubCommit extends Model
{
    protected $table = 'integration_github_commits';

    protected $fillable = [
        'repo_id',
        'sha',
        'message',
        'author_name',
        'author_email',
        'author_login',
        'committer_name',
        'committer_login',
        'committed_at',
        'url',
        'additions',
        'deletions',
        'changed_files',
    ];

    protected $casts = [
        'committed_at' => 'datetime',
        'additions' => 'integer',
        'deletions' => 'integer',
        'changed_files' => 'integer',
    ];

    public function repo(): BelongsTo
    {
        return $this->belongsTo(IntegrationGithubRepo::class, 'repo_id');
    }
}
