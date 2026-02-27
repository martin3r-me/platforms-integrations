<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Collection;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

class IntegrationConnectionResolver
{
    public function __construct(
        protected IntegrationAccessService $access,
    ) {}

    /**
     * Resolve Connection für Integration-Key.
     *
     * 1. Bevorzugt eigene Default-Connection
     * 2. Fallback auf geteilte Connections
     */
    public function resolveForUser(string $integrationKey, User $user): ?IntegrationConnection
    {
        $integration = Integration::query()->where('key', $integrationKey)->first();

        if (!$integration || !$integration->is_enabled) {
            return null;
        }

        // Bevorzugt eigene Default-Connection
        $userConn = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->first();

        if ($userConn && $this->access->canUse($user, $userConn)) {
            return $userConn;
        }

        // Fallback: geteilte Connections anderer User
        $userTeamIds = $user->teams()->pluck('teams.id')->toArray();

        $sharedConn = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', '!=', $user->id)
            ->where('status', 'active')
            ->whereHas('shares', function ($query) use ($user, $userTeamIds) {
                $query->where(function ($q) use ($user) {
                    $q->whereNull('user_id')->orWhere('user_id', $user->id);
                })->where(function ($q) use ($userTeamIds) {
                    $q->whereNull('team_id');
                    if (!empty($userTeamIds)) {
                        $q->orWhereIn('team_id', $userTeamIds);
                    }
                });
            })
            ->first();

        return $sharedConn;
    }

    /**
     * Resolve eine spezifische Connection by ID.
     * Prüft, dass der User Zugriff hat (Owner oder Share).
     */
    public function resolveById(int $connectionId, User $user): ?IntegrationConnection
    {
        $connection = IntegrationConnection::query()
            ->with('integration')
            ->find($connectionId);

        if (!$connection) {
            return null;
        }

        if ($this->access->canUse($user, $connection)) {
            return $connection;
        }

        return null;
    }

    /**
     * Alle Connections eines Typs für einen User (eigene + geteilte).
     */
    public function resolveAllForUser(string $integrationKey, User $user): Collection
    {
        $integration = Integration::query()->where('key', $integrationKey)->first();

        if (!$integration || !$integration->is_enabled) {
            return collect();
        }

        // Eigene Connections
        $owned = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->filter(fn (IntegrationConnection $conn) => $this->access->canUse($user, $conn));

        // Geteilte Connections
        $userTeamIds = $user->teams()->pluck('teams.id')->toArray();

        $shared = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', '!=', $user->id)
            ->where('status', 'active')
            ->whereHas('shares', function ($query) use ($user, $userTeamIds) {
                $query->where(function ($q) use ($user) {
                    $q->whereNull('user_id')->orWhere('user_id', $user->id);
                })->where(function ($q) use ($userTeamIds) {
                    $q->whereNull('team_id');
                    if (!empty($userTeamIds)) {
                        $q->orWhereIn('team_id', $userTeamIds);
                    }
                });
            })
            ->orderBy('name')
            ->get();

        return $owned->merge($shared)->unique('id')->values();
    }
}
