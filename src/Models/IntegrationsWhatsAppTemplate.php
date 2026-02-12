<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Models\User;
use Platform\Core\Contracts\HasDisplayName;

class IntegrationsWhatsAppTemplate extends Model implements HasDisplayName
{
    protected $table = 'integrations_whatsapp_templates';

    protected $fillable = [
        'uuid', 'external_id', 'name', 'language', 'status',
        'category', 'components', 'metadata',
        'whatsapp_account_id', 'user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'components' => 'array',
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
     * Der WhatsApp Business Account, zu dem dieses Template gehört
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationsWhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->name;
    }
}
