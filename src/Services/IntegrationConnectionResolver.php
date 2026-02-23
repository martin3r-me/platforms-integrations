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
     * User-zentriert: Nur User-owned Connections.
     * Bevorzugt is_default = true, Fallback auf erste aktive.
     */
    public function resolveForUser(string $integrationKey, User $user): ?IntegrationConnection
    {
        $integration = Integration::query()->where('key', $integrationKey)->first();

        if (!$integration || !$integration->is_enabled) {
            return null;
        }

        // Bevorzugt Default-Connection
        $userConn = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->first();

        if ($userConn && $this->access->canUse($user, $userConn)) {
            return $userConn;
        }

        return null;
    }

    /**
     * Resolve eine spezifische Connection by ID.
     * Prüft, dass der User Zugriff hat.
     */
    public function resolveById(int $connectionId, User $user): ?IntegrationConnection
    {
        $connection = IntegrationConnection::query()
            ->with('integration')
            ->find($connectionId);

        if (!$connection) {
            return null;
        }

        if ($connection->owner_user_id !== $user->id) {
            return null;
        }

        if ($this->access->canUse($user, $connection)) {
            return $connection;
        }

        return null;
    }

    /**
     * Alle Connections eines Typs für einen User.
     */
    public function resolveAllForUser(string $integrationKey, User $user): Collection
    {
        $integration = Integration::query()->where('key', $integrationKey)->first();

        if (!$integration || !$integration->is_enabled) {
            return collect();
        }

        return IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->filter(fn (IntegrationConnection $conn) => $this->access->canUse($user, $conn))
            ->values();
    }
}
