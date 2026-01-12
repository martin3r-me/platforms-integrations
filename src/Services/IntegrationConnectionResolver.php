<?php

namespace Platform\Integrations\Services;

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
     * Priorität: User > Team (nur wenn Zugriff via Grants/Owner gegeben ist).
     */
    public function resolveForUser(string $integrationKey, User $user): ?IntegrationConnection
    {
        $team = $user->currentTeam;
        $integration = Integration::query()->where('key', $integrationKey)->first();

        if (!$integration || !$integration->is_enabled) {
            return null;
        }

        // 1) User-owned Connection
        $userConn = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->first();

        if ($userConn && $this->access->canUse($user, $team, $userConn)) {
            return $userConn;
        }

        // 2) Team-owned Connection (aktuelles Team)
        if ($team) {
            $teamConn = IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->where('owner_team_id', $team->id)
                ->first();

            if ($teamConn && $this->access->canUse($user, $team, $teamConn)) {
                return $teamConn;
            }
        }

        return null;
    }
}

