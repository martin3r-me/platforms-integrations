<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Models\User;
use Platform\Core\Contracts\HasDisplayName;

/**
 * Model für GitHub Repositories
 * 
 * Repositories werden pro User gespeichert und über eine GitHub-Connection synchronisiert.
 */
class IntegrationsGithubRepository extends Model implements HasDisplayName
{
    protected $table = 'integrations_github_repositories';

    protected $fillable = [
        'uuid',
        'external_id',
        'full_name',
        'name',
        'owner',
        'description',
        'url',
        'clone_url',
        'default_branch',
        'is_private',
        'is_fork',
        'is_archived',
        'language',
        'stars_count',
        'forks_count',
        'open_issues_count',
        'github_created_at',
        'github_updated_at',
        'github_pushed_at',
        'metadata',
        'integration_connection_id',
        'user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'is_private' => 'boolean',
        'is_fork' => 'boolean',
        'is_archived' => 'boolean',
        'stars_count' => 'integer',
        'forks_count' => 'integer',
        'open_issues_count' => 'integer',
        'github_created_at' => 'datetime',
        'github_updated_at' => 'datetime',
        'github_pushed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Die GitHub-IntegrationConnection, über die dieses Repository synchronisiert wurde
     */
    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->full_name;
    }
}
