<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Models\User;
use Platform\Core\Contracts\HasDisplayName;

/**
 * Model für Meta Business Accounts
 * 
 * Ein Meta Business Account kann mehrere WhatsApp Business Accounts enthalten.
 * Diese Tabelle speichert die Business Accounts, die über eine Meta-Connection
 * synchronisiert wurden.
 * 
 * Optional: Wird aktuell hauptsächlich für bessere Hierarchie-Abbildung verwendet.
 * Kann später für Business-spezifische Features erweitert werden.
 */
class IntegrationsMetaBusinessAccount extends Model implements HasDisplayName
{
    protected $table = 'integrations_meta_business_accounts';

    protected $fillable = [
        'uuid',
        'external_id',
        'name',
        'description',
        'timezone',
        'vertical',
        'metadata',
        'integration_connection_id',
        'user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
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
     * Die Meta-IntegrationConnection, über die dieser Business Account synchronisiert wurde
     */
    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * WhatsApp Business Accounts, die zu diesem Business Account gehören
     */
    public function whatsappAccounts(): HasMany
    {
        return $this->hasMany(IntegrationsWhatsAppAccount::class, 'meta_business_account_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->name;
    }
}
