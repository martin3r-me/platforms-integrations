<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Models\User;
use Platform\Core\Contracts\HasDisplayName;

/**
 * Model für Instagram Accounts (user-zentriert)
 * 
 * Ein User kann Instagram Accounts haben, die dann mit Services verknüpft werden können
 */
class IntegrationsInstagramAccount extends Model implements HasDisplayName
{
    protected $table = 'integrations_instagram_accounts';

    protected $fillable = [
        'uuid',
        'facebook_page_id',
        'external_id',
        'username',
        'description',
        'access_token',
        'refresh_token',
        'expires_at',
        'token_type',
        'scopes',
        'user_id',
        'integration_connection_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'expires_at' => 'datetime',
        'scopes' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
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

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(IntegrationsFacebookPage::class, 'facebook_page_id');
    }

    /**
     * Die Meta-IntegrationConnection, über die dieser Instagram Account synchronisiert wurde
     */
    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Services, die diesen Instagram Account verwenden
     * TODO: Verknüpfung implementieren, wenn benötigt
     */
    public function services()
    {
        // TODO: Verknüpfung implementieren
        return collect();
    }

    /**
     * Instagram Media dieses Accounts (Brands-spezifisch)
     */
    public function media(): HasMany
    {
        return $this->hasMany(\Platform\Brands\Models\BrandsInstagramMedia::class, 'instagram_account_id');
    }

    /**
     * Instagram Account Insights (Brands-spezifisch)
     */
    public function insights(): HasMany
    {
        return $this->hasMany(\Platform\Brands\Models\BrandsInstagramAccountInsight::class, 'instagram_account_id');
    }

    /**
     * Neueste Insight
     */
    public function latestInsight()
    {
        return $this->hasOne(\Platform\Brands\Models\BrandsInstagramAccountInsight::class, 'instagram_account_id')->latestOfMany('insight_date');
    }

    public function getDisplayName(): ?string
    {
        return $this->username;
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

    /**
     * Verschlüsselt den Refresh Token beim Speichern
     */
    public function setRefreshTokenAttribute($value)
    {
        if ($value) {
            $this->attributes['refresh_token'] = Crypt::encryptString($value);
        } else {
            $this->attributes['refresh_token'] = null;
        }
    }

    /**
     * Entschlüsselt den Refresh Token beim Abrufen
     */
    public function getRefreshTokenAttribute($value)
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

    /**
     * Prüft ob Token abgelaufen ist
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isPast();
    }

    /**
     * Prüft ob Token bald abläuft (innerhalb der nächsten 5 Minuten)
     */
    public function isExpiringSoon(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isBefore(now()->addMinutes(5));
    }
}
