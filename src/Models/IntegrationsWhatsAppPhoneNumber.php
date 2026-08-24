<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Contracts\HasDisplayName;

/**
 * Eine einzelne Rufnummer einer WABA (WhatsApp Business Account).
 *
 * Additive Kind-Tabelle: eine WABA kann mehrere Nummern haben. Die primäre
 * Nummer liegt weiterhin direkt auf IntegrationsWhatsAppAccount
 * (phone_number/phone_number_id) — dieses Modell hält zusätzlich alle Nummern.
 */
class IntegrationsWhatsAppPhoneNumber extends Model implements HasDisplayName
{
    protected $table = 'integrations_whatsapp_phone_numbers';

    protected $fillable = [
        'uuid', 'whatsapp_account_id', 'phone_number', 'phone_number_id',
        'display_name', 'status', 'quality_rating',
    ];

    protected $casts = [
        'uuid' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    /**
     * Der WhatsApp Business Account (WABA), zu dem diese Nummer gehört
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationsWhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->display_name ?: $this->phone_number;
    }
}
