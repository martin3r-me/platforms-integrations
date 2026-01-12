<?php

namespace Platform\Integrations\Livewire\Grants;

use Livewire\Component;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationConnectionGrant;
use Platform\Integrations\Services\IntegrationAccessService;

class Manage extends Component
{
    public IntegrationConnection $connection;

    public string $grantType = 'user'; // user|team
    public ?int $grantUserId = null;
    public ?int $grantTeamId = null;

    public function mount(IntegrationConnection $connection): void
    {
        $this->connection = $connection->load(['integration', 'ownerTeam', 'ownerUser', 'grants']);
        $this->assertCanManage();
    }

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        $teams = $user->teams()->orderBy('name')->get();

        // User-Auswahl: standardmäßig Nutzer aus Owner-Team (falls team-owned), sonst nur self
        $users = collect();
        if ($this->connection->owner_team_id) {
            $ownerTeam = Team::find($this->connection->owner_team_id);
            $users = $ownerTeam ? $ownerTeam->users()->orderBy('name')->get() : collect();
        } else {
            $users = User::query()->where('id', $user->id)->get();
        }

        $grants = $this->connection->grants()->orderBy('created_at')->get();

        return view('integrations::livewire.grants.manage', [
            'teams' => $teams,
            'users' => $users,
            'grants' => $grants,
        ])->layout('platform::layouts.app');
    }

    public function addGrant(): void
    {
        $this->assertCanManage();

        if ($this->grantType === 'user') {
            $userId = (int) ($this->grantUserId ?? 0);
            if ($userId <= 0) {
                $this->addError('grantUserId', 'Bitte User auswählen.');
                return;
            }

            IntegrationConnectionGrant::query()->updateOrCreate(
                [
                    'connection_id' => $this->connection->id,
                    'grantee_type' => 'user',
                    'grantee_id' => $userId,
                ],
                ['permissions' => null]
            );
        } else {
            $teamId = (int) ($this->grantTeamId ?? 0);
            if ($teamId <= 0) {
                $this->addError('grantTeamId', 'Bitte Team auswählen.');
                return;
            }

            IntegrationConnectionGrant::query()->updateOrCreate(
                [
                    'connection_id' => $this->connection->id,
                    'grantee_type' => 'team',
                    'grantee_id' => $teamId,
                ],
                ['permissions' => null]
            );
        }

        $this->grantUserId = null;
        $this->grantTeamId = null;
        $this->resetValidation();
        session()->flash('status', 'Grant gespeichert.');
    }

    public function removeGrant(int $grantId): void
    {
        $this->assertCanManage();

        $grant = IntegrationConnectionGrant::query()
            ->where('connection_id', $this->connection->id)
            ->where('id', $grantId)
            ->firstOrFail();

        $grant->delete();
        session()->flash('status', 'Grant entfernt.');
    }

    protected function assertCanManage(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $access = app(IntegrationAccessService::class);

        if (!$access->canManage($user, $this->connection)) {
            abort(403, 'Keine Berechtigung (nur Owner darf verwalten).');
        }
    }
}

