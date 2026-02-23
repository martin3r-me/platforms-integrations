<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'name',
        'is_default',
        'auth_scheme',
        'status',
        'credentials',
        'credentials_hash',
        'last_tested_at',
        'last_error',
    ];

    protected $casts = [
        'last_tested_at' => 'datetime',
        'is_default' => 'boolean',
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

    public function shares(): HasMany
    {
        return $this->hasMany(IntegrationConnectionShare::class, 'connection_id');
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

    /**
     * Meta-Ressourcen: Instagram Accounts (für Connection-Sharing mit Ressourcen-Scope)
     */
    public function metaInstagramAccounts(): HasMany
    {
        return $this->hasMany(IntegrationMetaInstagramAccount::class, 'connection_id');
    }

    /**
     * Meta-Ressourcen: Facebook Pages (für Connection-Sharing mit Ressourcen-Scope)
     */
    public function metaFacebookPages(): HasMany
    {
        return $this->hasMany(IntegrationMetaFacebookPage::class, 'connection_id');
    }

    /**
     * GitHub-Ressourcen: Repos (für Connection-Sharing mit Ressourcen-Scope)
     */
    public function githubRepos(): HasMany
    {
        return $this->hasMany(IntegrationGithubRepo::class, 'connection_id');
    }

    /**
     * Scope: nur Default-Connections
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Setzt diese Connection als Default und entfernt Default bei allen anderen
     * Connections desselben Typs und Users.
     */
    public function makeDefault(): void
    {
        // Alle anderen gleichen Typs + User auf non-default setzen
        static::query()
            ->where('integration_id', $this->integration_id)
            ->where('owner_user_id', $this->owner_user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }

    /**
     * Generiert einen eindeutigen Namen für eine neue Connection.
     * Beispiel: "Meta", "Meta (2)", "Meta (3)"
     */
    public static function generateName(int $integrationId, int $userId, string $baseName): string
    {
        $existing = static::query()
            ->where('integration_id', $integrationId)
            ->where('owner_user_id', $userId)
            ->pluck('name')
            ->toArray();

        if (!in_array($baseName, $existing)) {
            return $baseName;
        }

        $counter = 2;
        while (in_array("{$baseName} ({$counter})", $existing)) {
            $counter++;
        }

        return "{$baseName} ({$counter})";
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_user_id === $user->id;
    }
}
