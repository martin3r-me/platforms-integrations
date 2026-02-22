<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Meta-Ressource: Facebook Page
 *
 * Abgebildet als teilbare Ressource einer Meta-IntegrationConnection.
 * Wird für Connection-Sharing mit Ressourcen-Scope verwendet (has_resources=true).
 * Der access_token ist page-spezifisch und wird verschlüsselt gespeichert.
 */
class IntegrationMetaFacebookPage extends Model
{
    protected $table = 'integration_meta_facebook_pages';

    protected $fillable = [
        'connection_id',
        'page_id',
        'name',
        'access_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    /**
     * Verschlüsselt den Access Token beim Speichern
     */
    public function setAccessTokenAttribute($value)
    {
        if ($value) {
            $this->attributes['access_token'] = Crypt::encryptString($value);
        } else {
            $this->attributes['access_token'] = null;
        }
    }

    /**
     * Entschlüsselt den Access Token beim Abrufen
     */
    public function getAccessTokenAttribute($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
