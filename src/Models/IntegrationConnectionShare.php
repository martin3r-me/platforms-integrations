<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;

/**
 * Connection Sharing mit Wildcard-Konzept.
 *
 * Wildcard-Logik:
 * - team_id: null  → gilt für ALLE Teams
 * - user_id: null  → gilt für ALLE User
 * - Beides null    → vollständig öffentlich innerhalb des Parent-Kontexts
 *
 * Ressourcen-Scope (nur bei has_resources=true):
 * - resource_id: null  → alle Ressourcen der Connection
 * - resource_id: 123 + resource_type: instagram_account → nur dieser Account
 * - Bei has_resources=false wird resource_id ignoriert
 *
 * Beispiele:
 * - team_id=5, user_id=null   → Alle User in Team 5 dürfen die Connection nutzen
 * - team_id=null, user_id=42  → User 42 darf die Connection in allen Teams nutzen
 * - team_id=5, user_id=42     → Nur User 42 in Team 5 darf die Connection nutzen
 * - team_id=null, user_id=null → Alle User in allen Teams dürfen die Connection nutzen
 */
class IntegrationConnectionShare extends Model
{
    protected $table = 'integration_connection_shares';

    protected $fillable = [
        'connection_id',
        'team_id',
        'user_id',
        'resource_id',
        'resource_type',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Prüft ob dieser Share ein Wildcard für alle Teams ist.
     */
    public function isAllTeams(): bool
    {
        return $this->team_id === null;
    }

    /**
     * Prüft ob dieser Share ein Wildcard für alle User ist.
     */
    public function isAllUsers(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Prüft ob dieser Share vollständig öffentlich ist (beide Wildcards).
     */
    public function isPublic(): bool
    {
        return $this->team_id === null && $this->user_id === null;
    }

    /**
     * Prüft ob dieser Share ein Wildcard für alle Ressourcen ist.
     * resource_id=null bedeutet: Freigabe gilt für alle Ressourcen der Connection.
     */
    public function isAllResources(): bool
    {
        return $this->resource_id === null;
    }
}
