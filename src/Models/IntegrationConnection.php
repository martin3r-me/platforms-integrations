<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\User;
use Platform\Core\Traits\Encryptable;

class IntegrationConnection extends Model
{
    use SoftDeletes;
    use Encryptable;

    protected $table = 'integration_connections';

    protected $fillable = [
        'integration_id',
        'owner_user_id',
        'auth_scheme',
        'status',
        'credentials',
        'credentials_hash',
        'last_tested_at',
        'last_error',
    ];

    protected $casts = [
        'last_tested_at' => 'datetime',
        // EncryptedJson wird automatisch gesetzt via Encryptable, aber explizit ist besser (siehe planner)
        'credentials' => \Platform\Core\Casts\EncryptedJson::class,
    ];

    protected array $encryptable = [
        'credentials' => 'json',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(IntegrationConnectionGrant::class, 'connection_id');
    }

    /**
     * Facebook Pages, die über diese Meta-Connection synchronisiert wurden
     */
    public function facebookPages(): HasMany
    {
        return $this->hasMany(IntegrationsFacebookPage::class, 'integration_connection_id');
    }

    /**
     * Instagram Accounts, die über diese Meta-Connection synchronisiert wurden
     */
    public function instagramAccounts(): HasMany
    {
        return $this->hasMany(IntegrationsInstagramAccount::class, 'integration_connection_id');
    }

    /**
     * WhatsApp Accounts, die über diese Meta-Connection synchronisiert wurden
     */
    public function whatsappAccounts(): HasMany
    {
        return $this->hasMany(IntegrationsWhatsAppAccount::class, 'integration_connection_id');
    }

    /**
     * Meta Business Accounts, die über diese Connection synchronisiert wurden (optional)
     */
    public function metaBusinessAccounts(): HasMany
    {
        return $this->hasMany(IntegrationsMetaBusinessAccount::class, 'integration_connection_id');
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_user_id === $user->id;
    }
}
