<div class="h-full overflow-y-auto p-6">
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Integrationen</h1>
                <p class="text-gray-600">Verwalten Sie Anbindungen (OAuth oder manuell) und deren Freigaben</p>
            </div>
            <div class="d-flex items-center gap-2">
                <x-ui-button variant="primary" wire:click="openCreateModal">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Neue Connection</span>
                    </div>
                </x-ui-button>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4">
            <x-ui-alert variant="success">
                {{ session('status') }}
            </x-ui-alert>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if($connections->count() > 0)
            <x-ui-table>
                <x-ui-table-header>
                    <x-ui-table-header-cell>Integration</x-ui-table-header-cell>
                    <x-ui-table-header-cell>Owner</x-ui-table-header-cell>
                    <x-ui-table-header-cell>Auth</x-ui-table-header-cell>
                    <x-ui-table-header-cell>Status</x-ui-table-header-cell>
                    <x-ui-table-header-cell align="right">Aktionen</x-ui-table-header-cell>
                </x-ui-table-header>

                <x-ui-table-body>
                    @foreach($connections as $conn)
                        <x-ui-table-row>
                            <x-ui-table-cell class="font-medium">
                                {{ $conn->integration->name ?? $conn->integration->key ?? '—' }}
                            </x-ui-table-cell>
                            <x-ui-table-cell>
                                @if($conn->owner_user_id)
                                    User: {{ $conn->ownerUser->name ?? $conn->owner_user_id }}
                                @elseif($conn->owner_team_id)
                                    Team: {{ $conn->ownerTeam->name ?? $conn->owner_team_id }}
                                @else
                                    —
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell>{{ $conn->auth_scheme }}</x-ui-table-cell>
                            <x-ui-table-cell>
                                <x-ui-badge
                                    size="sm"
                                    variant="{{ $conn->status === 'active' ? 'success' : ($conn->status === 'error' ? 'danger' : 'secondary') }}"
                                >
                                    {{ $conn->status }}
                                </x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell align="right">
                                <div class="d-flex items-center gap-2 justify-end">
                                    <x-ui-button size="sm" variant="secondary" wire:click="openEditModal({{ $conn->id }})">
                                        Bearbeiten
                                    </x-ui-button>
                                    <x-ui-button size="sm" variant="secondary" :href="route('integrations.connections.grants', ['connection' => $conn->id])">
                                        Freigaben
                                    </x-ui-button>
                                    @if($conn->auth_scheme === 'oauth2')
                                        <x-ui-button size="sm" variant="secondary" wire:click="startOAuth({{ $conn->id }})">
                                            OAuth verbinden
                                        </x-ui-button>
                                    @endif
                                    <x-ui-button size="sm" variant="danger" wire:click="deleteConnection({{ $conn->id }})">
                                        Löschen
                                    </x-ui-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforeach
                </x-ui-table-body>
            </x-ui-table>
        @else
            <div class="text-center py-12 text-gray-600">
                <x-heroicon-o-link class="w-12 h-12 text-gray-400 mx-auto mb-3"/>
                <div class="text-lg font-medium">Keine Connections gefunden</div>
                <div>Erstellen Sie die erste Connection, um zu starten.</div>
            </div>
        @endif
    </div>

    <div class="mt-4">{{ $connections->links() }}</div>

    {{-- Create Modal --}}
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">Connection anlegen</x-slot>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="integrationKey"
                    label="Integration"
                    :options="$integrations->map(fn($i) => ['value' => $i->key, 'label' => $i->name . ' (' . $i->key . ')'])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="integrationKey"
                    :errorKey="'integrationKey'"
                />

                <x-ui-input-select
                    name="ownerType"
                    label="Owner"
                    :options="collect([['value' => 'team','label' => 'Team'],['value' => 'user','label' => 'User']])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="ownerType"
                    :errorKey="'ownerType'"
                />
            </div>

            @if($ownerType === 'team')
                <div class="grid grid-cols-1 gap-4">
                    <x-ui-input-select
                        name="ownerTeamId"
                        label="Team"
                        :options="$teams->map(fn($t) => ['value' => $t->id, 'label' => $t->name])"
                        optionValue="value"
                        optionLabel="label"
                        :nullable="false"
                        wire:model.live="ownerTeamId"
                        :errorKey="'ownerTeamId'"
                    />
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="authScheme"
                    label="Auth Methode"
                    :options="collect([['value'=>'oauth2','label'=>'OAuth2'],['value'=>'api_key','label'=>'API Key'],['value'=>'basic','label'=>'Basic (User/Pass)'],['value'=>'bearer','label'=>'Bearer Token'],['value'=>'custom','label'=>'Custom']])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="authScheme"
                    :errorKey="'authScheme'"
                />

                <x-ui-input-select
                    name="status"
                    label="Status"
                    :options="collect([['value'=>'draft','label'=>'draft'],['value'=>'active','label'=>'active'],['value'=>'disabled','label'=>'disabled'],['value'=>'error','label'=>'error']])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="status"
                    :errorKey="'status'"
                />
            </div>

            <x-ui-input-textarea
                name="credentialsJson"
                label="Credentials (JSON, verschlüsselt gespeichert)"
                wire:model.live="credentialsJson"
                rows="10"
                :errorKey="'credentialsJson'"
            />

            <div class="text-xs text-gray-500">
                Hinweis: Bei OAuth2 speichert der Callback Tokens unter <code>credentials.oauth.*</code>. Für manuelle Methoden kannst du beliebige Keys speichern.
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" @click="$wire.closeCreateModal()">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="save">
                    Speichern
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Edit Modal --}}
    <x-ui-modal wire:model="editModalShow" size="lg">
        <x-slot name="header">Connection bearbeiten</x-slot>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="integrationKey" wire:model.live="integrationKey" label="Integration Key" :errorKey="'integrationKey'" />
                <x-ui-input-select
                    name="ownerType"
                    label="Owner"
                    :options="collect([['value' => 'team','label' => 'Team'],['value' => 'user','label' => 'User']])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="ownerType"
                    :errorKey="'ownerType'"
                />
            </div>

            @if($ownerType === 'team')
                <x-ui-input-select
                    name="ownerTeamId"
                    label="Team"
                    :options="$teams->map(fn($t) => ['value' => $t->id, 'label' => $t->name])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="ownerTeamId"
                    :errorKey="'ownerTeamId'"
                />
            @endif

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="authScheme"
                    label="Auth Methode"
                    :options="collect([['value'=>'oauth2','label'=>'OAuth2'],['value'=>'api_key','label'=>'API Key'],['value'=>'basic','label'=>'Basic (User/Pass)'],['value'=>'bearer','label'=>'Bearer Token'],['value'=>'custom','label'=>'Custom']])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="authScheme"
                    :errorKey="'authScheme'"
                />
                <x-ui-input-select
                    name="status"
                    label="Status"
                    :options="collect([['value'=>'draft','label'=>'draft'],['value'=>'active','label'=>'active'],['value'=>'disabled','label'=>'disabled'],['value'=>'error','label'=>'error']])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="status"
                    :errorKey="'status'"
                />
            </div>

            <x-ui-input-textarea
                name="credentialsJson"
                label="Credentials (JSON, verschlüsselt gespeichert)"
                wire:model.live="credentialsJson"
                rows="10"
                :errorKey="'credentialsJson'"
            />

            @if($lastError)
                <x-ui-alert variant="danger">
                    <div class="text-sm">
                        <div class="font-medium">Letzter Fehler</div>
                        <div class="mt-1 whitespace-pre-wrap">{{ $lastError }}</div>
                    </div>
                </x-ui-alert>
            @endif
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" @click="$wire.closeEditModal()">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="save">
                    Speichern
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</div>

