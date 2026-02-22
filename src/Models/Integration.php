<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine registrierte Integration (z.B. LexOffice, Meta, GitHub).
 *
 * @property int $id
 * @property string $key              Eindeutiger Schlüssel (z.B. 'meta', 'github', 'lexoffice')
 * @property string $name             Anzeigename
 * @property bool $is_enabled         Ob die Integration aktiviert ist
 * @property bool $has_resources      Ob die Integration teilbare Kinder-Ressourcen besitzt.
 *                                    true  = Ressourcen vorhanden (z.B. Meta → Facebook Pages, Instagram Accounts / GitHub → Repos)
 *                                    false = Connection selbst ist die Ressource (z.B. LexOffice, DataForSEO)
 *                                    Grundlage für das granulare Ressourcen-Sharing.
 * @property array|null $supported_auth_schemes  Unterstützte Auth-Verfahren (oauth2, api_key, basic, bearer)
 * @property array|null $meta         Zusätzliche Metadaten (description, icon, etc.)
 */
class Integration extends Model
{
    protected $table = 'integrations';

    protected $fillable = [
        'key',
        'name',
        'is_enabled',
        'has_resources',
        'supported_auth_schemes',
        'meta',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'has_resources' => 'boolean',
        'supported_auth_schemes' => 'array',
        'meta' => 'array',
    ];

    public function connections(): HasMany
    {
        return $this->hasMany(IntegrationConnection::class, 'integration_id');
    }
}

