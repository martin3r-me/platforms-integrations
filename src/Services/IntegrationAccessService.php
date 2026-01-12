<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Models\IntegrationConnection;

class IntegrationAccessService
{
    /**
     * Prüft, ob $user diese Connection nutzen darf.
     *
     * Regeln:
     * - Owner (User oder Team-Owner) darf immer
     * - Grant an User -> darf
     * - Grant an Team -> darf, wenn User Teammitglied ist
     */
    public function canUse(User $user, ?Team $team, IntegrationConnection $connection): bool
    {
        if ($connection->isOwner($user)) {
            return true;
        }

        // direkter User-Grant
        $hasUserGrant = $connection->grants()
            ->where('grantee_type', 'user')
            ->where('grantee_id', $user->id)
            ->exists();

        if ($hasUserGrant) {
            return true;
        }

        // Team-Grant (nur, wenn User im Team ist)
        if ($team) {
            $hasTeamGrant = $connection->grants()
                ->where('grantee_type', 'team')
                ->where('grantee_id', $team->id)
                ->exists();

            if ($hasTeamGrant) {
                return $team->users()->where('users.id', $user->id)->exists();
            }
        }

        return false;
    }

    public function canManage(User $user, IntegrationConnection $connection): bool
    {
        // aktuell: nur Owner darf verwalten (Credentials + Grants)
        return $connection->isOwner($user);
    }
}

