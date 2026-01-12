<?php

namespace Platform\Integrations\Livewire\Connections;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\IntegrationAccessService;

class Index extends Component
{
    use WithPagination;

    public bool $modalShow = false;
    public bool $editModalShow = false;

    public ?int $editingId = null;

    public string $integrationKey = '';
    public string $ownerType = 'team'; // team|user
    public ?int $ownerTeamId = null;
    public bool $isEnabled = true;

    public string $authScheme = 'oauth2'; // oauth2|api_key|basic|bearer|custom
    public string $status = 'draft';

    // UI-Form: wir speichern credentials als JSON-String und parsen beim Save
    public string $credentialsJson = "{}";

    public ?string $lastError = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->ownerTeamId = (int) ($user?->currentTeam?->id ?? 0) ?: null;
    }

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        $teamIds = $user->teams()->pluck('teams.id')->all();

        $connections = IntegrationConnection::query()
            ->with(['integration', 'ownerTeam', 'ownerUser'])
            ->where(function ($q) use ($user, $teamIds) {
                $q->where('owner_user_id', $user->id)
                  ->orWhereIn('owner_team_id', $teamIds);
            })
            ->orderByDesc('updated_at')
            ->paginate(15);

        $integrations = Integration::query()
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get();

        $teams = $user->teams()->orderBy('name')->get();

        return view('integrations::livewire.connections.index', [
            'connections' => $connections,
            'integrations' => $integrations,
            'teams' => $teams,
        ])->layout('platform::layouts.app');
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->integrationKey = '';
        $this->ownerType = 'team';
        $this->authScheme = 'oauth2';
        $this->status = 'draft';
        $this->credentialsJson = "{}";
        $this->lastError = null;
        $this->modalShow = true;
    }

    public function closeCreateModal(): void
    {
        $this->modalShow = false;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();

        $conn = IntegrationConnection::query()->with('integration')->findOrFail($id);
        $this->assertCanManage($conn);

        $this->editingId = $conn->id;
        $this->integrationKey = $conn->integration?->key ?? '';
        $this->ownerType = $conn->owner_user_id ? 'user' : 'team';
        $this->ownerTeamId = $conn->owner_team_id;
        $this->authScheme = $conn->auth_scheme;
        $this->status = $conn->status;
        $this->credentialsJson = json_encode($conn->credentials ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: "{}";
        $this->lastError = $conn->last_error;

        $this->editModalShow = true;
    }

    public function closeEditModal(): void
    {
        $this->editModalShow = false;
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $integration = Integration::query()->where('key', $this->integrationKey)->first();
        if (!$integration) {
            $this->addError('integrationKey', 'Integration nicht gefunden.');
            return;
        }

        $ownerTeamId = $this->ownerType === 'team' ? (int) ($this->ownerTeamId ?? 0) : null;
        $ownerUserId = $this->ownerType === 'user' ? (int) auth()->id() : null;

        if ($this->ownerType === 'team' && (!$ownerTeamId || $ownerTeamId <= 0)) {
            $this->addError('ownerTeamId', 'Bitte ein Team wählen.');
            return;
        }

        $credentials = $this->decodeCredentialsJson();
        if ($credentials === null) {
            return; // error already set
        }

        $query = IntegrationConnection::query()
            ->where('integration_id', $integration->id);

        if ($ownerTeamId) {
            $query->where('owner_team_id', $ownerTeamId);
        }
        if ($ownerUserId) {
            $query->where('owner_user_id', $ownerUserId);
        }

        $connection = $this->editingId
            ? IntegrationConnection::findOrFail($this->editingId)
            : ($query->first() ?? new IntegrationConnection());

        if ($connection->exists) {
            $this->assertCanManage($connection);
        }

        $connection->integration_id = $integration->id;
        $connection->owner_team_id = $ownerTeamId;
        $connection->owner_user_id = $ownerUserId;
        $connection->auth_scheme = $this->authScheme;
        $connection->status = $this->status;
        $connection->credentials = $credentials;
        $connection->last_error = null;
        $connection->save();

        $this->modalShow = false;
        $this->editModalShow = false;

        session()->flash('status', 'Connection gespeichert.');
    }

    public function deleteConnection(int $id): void
    {
        $conn = IntegrationConnection::findOrFail($id);
        $this->assertCanManage($conn);
        $conn->delete();
        session()->flash('status', 'Connection gelöscht.');
    }

    public function startOAuth(int $connectionId): void
    {
        $conn = IntegrationConnection::query()->with('integration')->findOrFail($connectionId);
        $this->assertCanManage($conn);

        $integrationKey = $conn->integration?->key;
        if (!$integrationKey) {
            session()->flash('status', 'Integration-Key fehlt.');
            return;
        }

        // Für user-owned immer owner_type=user (owner_id wird serverseitig auf current user gesetzt)
        $ownerType = $conn->owner_user_id ? 'user' : 'team';
        $ownerId = $conn->owner_team_id ?: null;

        $params = [
            'owner_type' => $ownerType,
        ];

        if ($ownerType === 'team' && $ownerId) {
            $params['owner_id'] = $ownerId;
        }
        $url = route('integrations.oauth2.start', ['integrationKey' => $integrationKey]) . '?' . http_build_query($params);
        $this->redirect($url);
    }

    protected function rules(): array
    {
        return [
            'integrationKey' => ['required', 'string'],
            'ownerType' => ['required', Rule::in(['team', 'user'])],
            'ownerTeamId' => ['nullable', 'integer'],
            'authScheme' => ['required', Rule::in(['oauth2', 'api_key', 'basic', 'bearer', 'custom'])],
            'status' => ['required', Rule::in(['draft', 'active', 'disabled', 'error'])],
            'credentialsJson' => ['required', 'string'],
        ];
    }

    protected function decodeCredentialsJson(): ?array
    {
        try {
            $decoded = json_decode($this->credentialsJson, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            $this->addError('credentialsJson', 'Ungültiges JSON: ' . $e->getMessage());
            return null;
        }
    }

    protected function assertCanManage(IntegrationConnection $connection): void
    {
        /** @var User $user */
        $user = auth()->user();
        $access = app(IntegrationAccessService::class);

        if (!$access->canManage($user, $connection)) {
            abort(403, 'Keine Berechtigung (nur Owner darf verwalten).');
        }
    }
}

