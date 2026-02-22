<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GitHub-Ressource: Repository
 *
 * Abgebildet als teilbare Ressource einer GitHub-IntegrationConnection.
 * Wird für Connection-Sharing mit Ressourcen-Scope verwendet (has_resources=true).
 */
class IntegrationGithubRepo extends Model
{
    protected $table = 'integration_github_repos';

    protected $fillable = [
        'connection_id',
        'github_repo_id',
        'full_name',
        'name',
        'owner',
        'is_private',
        'is_active',
    ];

    protected $casts = [
        'github_repo_id' => 'integer',
        'is_private' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }
}
