<?php

namespace Platform\Integrations\Livewire\SyncProfiles;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsLexofficeDatevBridge;

/**
 * Verwaltung der Lexoffice ↔ DATEV-Pairings (Bridges / Sync-Profile).
 *
 * @see IntegrationsLexofficeDatevBridge
 */
class Index extends Component
{
    public bool $modalShow = false;

    public ?int $editingId = null;

    public string $name = '';
    public ?int $lexofficeConnectionId = null;
    public ?int $datevConnectionId = null;
    public string $datevClientId = '';
    public string $notes = '';
    public bool $isActive = true;

    public ?string $flashSuccess = null;
    public ?string $flashError = null;

    public function render()
    {
        $user = auth()->user();

        $bridges = IntegrationsLexofficeDatevBridge::query()
            ->forUser($user)
            ->with(['lexofficeConnection.integration', 'datevConnection.integration'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $lexofficeConnections = IntegrationConnection::query()
            ->where('owner_user_id', $user->id)
            ->whereHas('integration', fn ($q) => $q->where('key', 'lexware'))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $datevConnections = IntegrationConnection::query()
            ->where('owner_user_id', $user->id)
            ->whereHas('integration', fn ($q) => $q->where('key', 'datev'))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('integrations::livewire.sync-profiles.index', [
            'bridges' => $bridges,
            'lexofficeConnections' => $lexofficeConnections,
            'datevConnections' => $datevConnections,
        ]);
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingId', 'name', 'lexofficeConnectionId', 'datevConnectionId', 'datevClientId', 'notes']);
        $this->isActive = true;
        $this->modalShow = true;
    }

    public function openEditModal(int $id): void
    {
        $bridge = IntegrationsLexofficeDatevBridge::query()
            ->forUser(auth()->user())
            ->findOrFail($id);

        $this->editingId = $bridge->id;
        $this->name = $bridge->name;
        $this->lexofficeConnectionId = $bridge->lexoffice_connection_id;
        $this->datevConnectionId = $bridge->datev_connection_id;
        $this->datevClientId = $bridge->datev_client_id;
        $this->notes = $bridge->notes ?? '';
        $this->isActive = (bool) $bridge->is_active;
        $this->modalShow = true;
    }

    public function closeModal(): void
    {
        $this->modalShow = false;
    }

    public function save(): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'lexofficeConnectionId' => ['required', 'integer', Rule::exists('integration_connections', 'id')->where('owner_user_id', $user->id)],
            'datevConnectionId' => ['required', 'integer', Rule::exists('integration_connections', 'id')->where('owner_user_id', $user->id)],
            'datevClientId' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['boolean'],
        ]);

        $payload = [
            'name' => $data['name'],
            'lexoffice_connection_id' => $data['lexofficeConnectionId'],
            'datev_connection_id' => $data['datevConnectionId'],
            'datev_client_id' => $data['datevClientId'],
            'notes' => $data['notes'] ?: null,
            'is_active' => (bool) ($data['isActive'] ?? true),
            'owner_user_id' => $user->id,
        ];

        try {
            if ($this->editingId) {
                $bridge = IntegrationsLexofficeDatevBridge::query()
                    ->forUser($user)
                    ->findOrFail($this->editingId);
                $bridge->fill($payload)->save();
                $this->flashSuccess = 'Bridge aktualisiert.';
            } else {
                IntegrationsLexofficeDatevBridge::create($payload);
                $this->flashSuccess = 'Bridge angelegt.';
            }

            $this->modalShow = false;
            $this->reset(['editingId', 'name', 'lexofficeConnectionId', 'datevConnectionId', 'datevClientId', 'notes']);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->flashError = 'Diese Paarung existiert bereits (Lexoffice + DATEV + Mandant).';
        }
    }

    public function delete(int $id): void
    {
        IntegrationsLexofficeDatevBridge::query()
            ->forUser(auth()->user())
            ->whereKey($id)
            ->delete();

        $this->flashSuccess = 'Bridge gelöscht.';
    }

    public function toggleActive(int $id): void
    {
        $bridge = IntegrationsLexofficeDatevBridge::query()
            ->forUser(auth()->user())
            ->findOrFail($id);

        $bridge->is_active = !$bridge->is_active;
        $bridge->save();
    }
}
