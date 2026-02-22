<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Collection;
use Platform\Core\Models\User;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationConnectionShare;

class IntegrationConnectionShareService
{
    public function __construct(
        protected IntegrationAccessService $accessService,
    ) {}

    /**
     * Alle Shares einer Connection abrufen.
     * Nur der Owner darf Shares sehen.
     */
    public function listShares(User $user, IntegrationConnection $connection): Collection
    {
        $this->assertCanManage($user, $connection);

        return $connection->shares()
            ->with(['team', 'user'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (IntegrationConnectionShare $share) => $this->formatShare($share));
    }

    /**
     * Neue Freigabe erstellen.
     *
     * Wildcard-Logik:
     * - team_id=null, user_id=null   -> alle User in allen Teams
     * - team_id=5,    user_id=null   -> alle User in Team 5
     * - team_id=null, user_id=42     -> User 42 in allen Teams
     * - team_id=5,    user_id=42     -> User 42 in Team 5
     *
     * Ressourcen-Scope (nur bei has_resources=true):
     * - resource_id=null             -> alle Ressourcen der Connection
     * - resource_id=123 + resource_type=instagram_account -> nur diese Ressource
     * - Bei has_resources=false wird resource_id/resource_type ignoriert
     */
    public function createShare(
        User $user,
        IntegrationConnection $connection,
        ?int $teamId,
        ?int $userId,
        ?int $resourceId = null,
        ?string $resourceType = null,
    ): IntegrationConnectionShare {
        $this->assertCanManage($user, $connection);

        // Owner braucht keinen Share auf sich selbst
        if ($userId !== null && $userId === $connection->owner_user_id) {
            throw new \InvalidArgumentException('Der Owner benötigt keine Freigabe – er hat bereits vollen Zugriff.');
        }

        // Bei has_resources=false wird resource_id/resource_type ignoriert
        $hasResources = $connection->integration?->has_resources ?? false;
        if (!$hasResources) {
            $resourceId = null;
            $resourceType = null;
        }

        // Validierung: resource_id erfordert resource_type und umgekehrt
        if ($resourceId !== null && empty($resourceType)) {
            throw new \InvalidArgumentException('resource_type ist erforderlich, wenn resource_id gesetzt ist.');
        }
        if ($resourceId === null && !empty($resourceType)) {
            throw new \InvalidArgumentException('resource_id ist erforderlich, wenn resource_type gesetzt ist.');
        }

        $share = IntegrationConnectionShare::query()->updateOrCreate(
            [
                'connection_id' => $connection->id,
                'team_id' => $teamId,
                'user_id' => $userId,
                'resource_id' => $resourceId,
                'resource_type' => $resourceType,
            ],
        );

        return $share->load(['team', 'user']);
    }

    /**
     * Freigabe entfernen.
     */
    public function deleteShare(User $user, IntegrationConnection $connection, int $shareId): void
    {
        $this->assertCanManage($user, $connection);

        $share = $connection->shares()
            ->where('id', $shareId)
            ->firstOrFail();

        $share->delete();
    }

    /**
     * Formatiert einen Share für API/Tool-Ausgabe.
     */
    public function formatShare(IntegrationConnectionShare $share): array
    {
        return [
            'id' => $share->id,
            'connection_id' => $share->connection_id,
            'team_id' => $share->team_id,
            'team_name' => $share->team?->name,
            'user_id' => $share->user_id,
            'user_name' => $share->user?->name,
            'user_email' => $share->user?->email,
            'resource_id' => $share->resource_id,
            'resource_type' => $share->resource_type,
            'is_all_teams' => $share->isAllTeams(),
            'is_all_users' => $share->isAllUsers(),
            'is_all_resources' => $share->isAllResources(),
            'is_public' => $share->isPublic(),
            'wildcard_description' => $this->describeWildcard($share),
            'created_at' => $share->created_at?->toIso8601String(),
        ];
    }

    /**
     * Menschenlesbare Beschreibung der Wildcard-Kombination.
     */
    protected function describeWildcard(IntegrationConnectionShare $share): string
    {
        // Basis-Beschreibung: Wer hat Zugriff?
        if ($share->isPublic()) {
            $who = 'Alle User in allen Teams';
        } elseif ($share->isAllTeams() && !$share->isAllUsers()) {
            $name = $share->user?->name ?? "User #{$share->user_id}";
            $who = "{$name} in allen Teams";
        } elseif (!$share->isAllTeams() && $share->isAllUsers()) {
            $name = $share->team?->name ?? "Team #{$share->team_id}";
            $who = "Alle User in {$name}";
        } else {
            $userName = $share->user?->name ?? "User #{$share->user_id}";
            $teamName = $share->team?->name ?? "Team #{$share->team_id}";
            $who = "{$userName} in {$teamName}";
        }

        // Ressourcen-Scope anhängen (nur wenn gesetzt)
        if (!$share->isAllResources()) {
            $resourceLabel = $share->resource_type
                ? str_replace('_', ' ', $share->resource_type) . " #{$share->resource_id}"
                : "Ressource #{$share->resource_id}";
            return "{$who} – nur {$resourceLabel}";
        }

        return $who;
    }

    protected function assertCanManage(User $user, IntegrationConnection $connection): void
    {
        if (!$this->accessService->canManage($user, $connection)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Keine Berechtigung – nur der Owner darf Freigaben verwalten.'
            );
        }
    }
}
