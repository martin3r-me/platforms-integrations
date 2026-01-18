<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\IntegrationConnection;

class IntegrationAccessService
{
    /**
     * Prüft, ob $user diese Connection nutzen darf.
     *
     * Regeln:
     * - Owner (User) darf immer
     * - Grant an User -> darf
     */
    public function canUse(User $user, IntegrationConnection $connection): bool
    {
        if ($connection->isOwner($user)) {
            return true;
        }

        // direkter User-Grant
        $hasUserGrant = $connection->grants()
            ->where('grantee_user_id', $user->id)
            ->exists();

        return $hasUserGrant;
    }

    public function canManage(User $user, IntegrationConnection $connection): bool
    {
        // nur Owner darf verwalten (Credentials + Grants)
        return $connection->isOwner($user);
    }
}
