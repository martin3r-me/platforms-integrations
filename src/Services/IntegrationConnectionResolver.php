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
     * User-zentriert: Nur User-owned Connections
     */
    public function resolveForUser(string $integrationKey, User $user): ?IntegrationConnection
    {
        $integration = Integration::query()->where('key', $integrationKey)->first();

        if (!$integration || !$integration->is_enabled) {
            return null;
        }

        // User-owned Connection
        $userConn = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->first();

        if ($userConn && $this->access->canUse($user, $userConn)) {
            return $userConn;
        }

        return null;
    }
}
