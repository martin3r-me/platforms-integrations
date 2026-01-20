<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Models\User;
use Platform\Core\Contracts\HasDisplayName;

class IntegrationsWhatsAppAccount extends Model implements HasDisplayName
{
    protected $table = 'integrations_whatsapp_accounts';
    
    protected $fillable = [
        'uuid', 'external_id', 'title', 'phone_number', 'phone_number_id',
        'description', 'active', 'access_token', 'last_used_at', 'verified_at',
        'user_id', 'integration_connection_id', 'meta_business_account_id',
    ];
    
    protected $casts = [
        'uuid' => 'string',
        'active' => 'boolean',
        'last_used_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
    
    protected $hidden = [
        'access_token',
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
     * Die Meta-IntegrationConnection, über die dieser WhatsApp Account synchronisiert wurde
     */
    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Der Meta Business Account, zu dem dieser WhatsApp Account gehört (optional)
     */
    public function metaBusinessAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationsMetaBusinessAccount::class, 'meta_business_account_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    /**
     * Services, die diesen WhatsApp Account verwenden
     * TODO: Verknüpfung implementieren, wenn benötigt
     */
    public function services()
    {
        // TODO: Verknüpfung implementieren
        return collect();
    }
}
