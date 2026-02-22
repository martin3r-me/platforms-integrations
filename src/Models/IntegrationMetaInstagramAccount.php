<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Meta-Ressource: Instagram Account
 *
 * Abgebildet als teilbare Ressource einer Meta-IntegrationConnection.
 * Wird für Connection-Sharing mit Ressourcen-Scope verwendet (has_resources=true).
 */
class IntegrationMetaInstagramAccount extends Model
{
    protected $table = 'integration_meta_instagram_accounts';

    protected $fillable = [
        'connection_id',
        'instagram_account_id',
        'name',
        'username',
        'profile_picture_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }
}
