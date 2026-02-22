<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\IntegrationConnection;

class IntegrationAccessService
{
    /**
     * Prüft, ob $user diese Connection nutzen darf.
     *
     * Regeln (Reihenfolge = Priorität):
     * 1. Owner darf immer
     * 2. Direkter User-Grant (legacy: integration_connection_grants)
     * 3. Connection Share mit Wildcard-Logik (integration_connection_shares)
     *
     * Share Wildcard-Logik:
     * - team_id NULL  = gilt für alle Teams
     * - user_id NULL  = gilt für alle User
     * - Beides NULL   = vollständig öffentlich innerhalb des Parent-Kontexts
     */
    public function canUse(User $user, IntegrationConnection $connection, ?int $teamId = null): bool
    {
        if ($connection->isOwner($user)) {
            return true;
        }

        // direkter User-Grant (legacy)
        $hasUserGrant = $connection->grants()
            ->where('grantee_user_id', $user->id)
            ->exists();

        if ($hasUserGrant) {
            return true;
        }

        // Share-basierte Prüfung mit Wildcard-Logik
        return $this->hasShareAccess($user, $connection, $teamId);
    }

    public function canManage(User $user, IntegrationConnection $connection): bool
    {
        // nur Owner darf verwalten (Credentials + Grants + Shares)
        return $connection->isOwner($user);
    }

    /**
     * Prüft ob der User Credentials/Token dieser Connection sehen darf.
     * Nur der Owner darf Credentials einsehen.
     */
    public function canViewCredentials(User $user, IntegrationConnection $connection): bool
    {
        return $connection->isOwner($user);
    }

    /**
     * Gibt Connection-Daten zurück, bei denen Credentials je nach Berechtigung
     * sichtbar oder ausgeblendet sind.
     *
     * Owner: sieht Credentials, Token, auth_scheme Details
     * Andere: sehen readonly Metadaten (Integration-Name, Status), keine Credentials
     */
    public function formatConnectionForUser(User $user, IntegrationConnection $connection): array
    {
        $isOwner = $connection->isOwner($user);

        $data = [
            'id' => $connection->id,
            'integration_id' => $connection->integration_id,
            'integration_name' => $connection->integration?->name,
            'integration_key' => $connection->integration?->key,
            'owner_user_id' => $connection->owner_user_id,
            'is_owner' => $isOwner,
            'auth_scheme' => $connection->auth_scheme,
            'status' => $connection->status,
            'last_tested_at' => $connection->last_tested_at?->toIso8601String(),
            'created_at' => $connection->created_at?->toIso8601String(),
            'updated_at' => $connection->updated_at?->toIso8601String(),
        ];

        if ($isOwner) {
            // Owner sieht alles
            $data['credentials'] = $connection->credentials;
            $data['last_error'] = $connection->last_error;
        } else {
            // Freigegebene User sehen keine Credentials
            $data['credentials'] = null;
            $data['credentials_hint'] = 'Nur der Owner kann Credentials einsehen.';
            $data['last_error'] = null;
        }

        return $data;
    }

    /**
     * Prüft ob der User über einen Share Zugriff hat.
     *
     * Ein Share matcht, wenn:
     * - share.user_id IS NULL (Wildcard: alle User) ODER share.user_id = $user->id
     * - UND share.team_id IS NULL (Wildcard: alle Teams) ODER share.team_id = $teamId
     *
     * Wenn kein $teamId übergeben wird, matchen nur Shares mit team_id IS NULL.
     */
    protected function hasShareAccess(User $user, IntegrationConnection $connection, ?int $teamId = null): bool
    {
        return $connection->shares()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->where(function ($query) use ($teamId) {
                $query->whereNull('team_id');
                if ($teamId !== null) {
                    $query->orWhere('team_id', $teamId);
                }
            })
            ->exists();
    }
}
