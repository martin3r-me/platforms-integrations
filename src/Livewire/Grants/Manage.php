<?php

namespace Platform\Integrations\Livewire\Grants;

use Livewire\Component;
use Platform\Core\Models\User;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationConnectionGrant;
use Platform\Integrations\Services\IntegrationAccessService;

class Manage extends Component
{
    public IntegrationConnection $connection;

    public ?int $grantUserId = null;

    public function mount(IntegrationConnection $connection): void
    {
        $this->connection = $connection->load(['integration', 'ownerUser', 'grants.granteeUser']);
        $this->assertCanManage();
    }

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        // User-Auswahl: Alle User außer dem Owner
        $users = User::query()
            ->where('id', '!=', $this->connection->owner_user_id)
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        $grants = $this->connection->grants()->with('granteeUser')->orderBy('created_at')->get();

        return view('integrations::livewire.grants.manage', [
            'users' => $users,
            'grants' => $grants,
        ])->layout('platform::layouts.app');
    }

    public function addGrant(): void
    {
        $this->assertCanManage();

        $userId = (int) ($this->grantUserId ?? 0);
        if ($userId <= 0) {
            $this->addError('grantUserId', 'Bitte User auswählen.');
            return;
        }

        if ($userId === $this->connection->owner_user_id) {
            $this->addError('grantUserId', 'Der Owner benötigt keinen Grant.');
            return;
        }

        IntegrationConnectionGrant::query()->updateOrCreate(
            [
                'connection_id' => $this->connection->id,
                'grantee_user_id' => $userId,
            ],
            ['permissions' => null]
        );

        $this->grantUserId = null;
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
