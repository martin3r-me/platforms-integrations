<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Collection;
use Platform\Core\Models\Team;
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
     * Resolve Connection für Integration-Key über ein Team.
     *
     * 1. Connections von Team-Mitgliedern (Owner ist Mitglied des Teams)
     * 2. Geteilte Connections, die explizit für dieses Team freigegeben sind
     *
     * Nützlich für Commands und Kontexte ohne User (z.B. SEO-Pipeline).
     */
    public function resolveForTeam(string $integrationKey, Team $team): ?IntegrationConnection
    {
        $integration = Integration::query()->where('key', $integrationKey)->first();

        if (!$integration || !$integration->is_enabled) {
            return null;
        }

        $teamMemberIds = $team->users()->pluck('users.id')->toArray();

        // Connection eines Team-Mitglieds (bevorzugt Default)
        if (!empty($teamMemberIds)) {
            $memberConn = IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->whereIn('owner_user_id', $teamMemberIds)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->first();

            if ($memberConn) {
                return $memberConn;
            }
        }

        // Fallback: Connections, die explizit für dieses Team geteilt sind
        $sharedConn = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('status', 'active')
            ->whereHas('shares', function ($query) use ($team) {
                $query->where('team_id', $team->id);
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
