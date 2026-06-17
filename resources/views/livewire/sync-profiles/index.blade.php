<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Integrationen', 'icon' => 'link', 'route' => 'integrations.connections.index'],
            ['label' => 'Lexoffice ↔ DATEV'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Bridge</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        @if($flashSuccess)
            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-check-circle', 'w-5 h-5 text-green-600')
                    <p class="text-sm font-medium text-green-800">{{ $flashSuccess }}</p>
                </div>
            </div>
        @endif

        @if($flashError)
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-600')
                    <p class="text-sm font-medium text-red-800">{{ $flashError }}</p>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500/10 to-indigo-600/5 flex items-center justify-center">
                            @svg('heroicon-o-arrows-right-left', 'w-6 h-6 text-indigo-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">Sync-Profile</h2>
                            <p class="text-sm text-[var(--ui-muted)]">
                                Paare eine Lexoffice-Verbindung mit einer DATEV-Verbindung + Mandant.
                                Pro Bridge werden danach die Konten-Mappings gepflegt.
                            </p>
                        </div>
                    </div>
                </div>

                @if($lexofficeConnections->isEmpty() || $datevConnections->isEmpty())
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                        <div class="flex items-start gap-3">
                            @svg('heroicon-o-information-circle', 'w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5')
                            <div class="text-sm text-amber-900">
                                <p class="font-medium">Aktive Verbindungen fehlen.</p>
                                <p class="mt-1">
                                    Es werden eine aktive <strong>Lexoffice</strong>- und eine aktive <strong>DATEV</strong>-Verbindung benötigt.
                                    Status:
                                    Lexoffice = {{ $lexofficeConnections->count() }},
                                    DATEV = {{ $datevConnections->count() }}.
                                    <a href="{{ route('integrations.connections.index') }}" class="underline">Zu den Verbindungen</a>.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($bridges->isEmpty())
                    <div class="text-center py-12 text-[var(--ui-muted)]">
                        <p class="text-sm">Noch keine Bridges angelegt.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($bridges as $bridge)
                            <div class="p-4 border {{ $bridge->is_active ? 'border-[var(--ui-border)]/60 bg-white' : 'border-gray-200 bg-gray-50' }} rounded-xl">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="text-base font-semibold text-[var(--ui-secondary)] truncate">
                                                {{ $bridge->name }}
                                            </h3>
                                            <x-ui-badge size="sm" variant="{{ $bridge->is_active ? 'success' : 'neutral' }}">
                                                {{ $bridge->is_active ? 'aktiv' : 'inaktiv' }}
                                            </x-ui-badge>
                                        </div>
                                        <div class="flex items-center gap-2 text-sm text-[var(--ui-muted)] flex-wrap">
                                            <span class="font-medium text-gray-700">
                                                {{ $bridge->lexofficeConnection?->name ?? 'Lexoffice #' . $bridge->lexoffice_connection_id }}
                                            </span>
                                            @svg('heroicon-o-arrows-right-left', 'w-4 h-4 text-gray-400')
                                            <span class="font-medium text-gray-700">
                                                {{ $bridge->datevConnection?->name ?? 'DATEV #' . $bridge->datev_connection_id }}
                                            </span>
                                            <span class="text-gray-400">·</span>
                                            <span>Mandant <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs">{{ $bridge->datev_client_id }}</code></span>
                                        </div>
                                        @if($bridge->notes)
                                            <p class="text-xs text-gray-500 mt-2">{{ $bridge->notes }}</p>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <x-ui-button
                                            variant="primary"
                                            size="sm"
                                            :href="route('integrations.sync-profiles.mappings', $bridge->id)"
                                        >
                                            @svg('heroicon-o-table-cells', 'w-4 h-4')
                                            <span>Mappings</span>
                                        </x-ui-button>

                                        <x-ui-button
                                            variant="secondary-outline"
                                            size="sm"
                                            wire:click="openEditModal({{ $bridge->id }})"
                                        >
                                            @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                        </x-ui-button>

                                        <x-ui-button
                                            variant="secondary-outline"
                                            size="sm"
                                            wire:click="toggleActive({{ $bridge->id }})"
                                            title="{{ $bridge->is_active ? 'Deaktivieren' : 'Aktivieren' }}"
                                        >
                                            @svg($bridge->is_active ? 'heroicon-o-pause' : 'heroicon-o-play', 'w-4 h-4')
                                        </x-ui-button>

                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="delete({{ $bridge->id }})"
                                            wire:confirm="Bridge '{{ $bridge->name }}' wirklich löschen? Alle Mappings darin werden mit gelöscht."
                                        >
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </x-ui-button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>

    {{-- Create / Edit Modal --}}
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">{{ $editingId ? 'Bridge bearbeiten' : 'Neue Bridge anlegen' }}</x-slot>

        <div class="space-y-4">
            <x-ui-input-text
                name="name"
                label="Name"
                wire:model.live="name"
                placeholder="z. B. BHG.DIGITAL Hauptbuch 2026"
                :errorKey="'name'"
            />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-select
                    name="lexofficeConnectionId"
                    label="Lexoffice-Verbindung"
                    :options="$lexofficeConnections->map(fn ($c) => ['value' => $c->id, 'label' => $c->name ?: 'Lexoffice #' . $c->id])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="true"
                    wire:model.live="lexofficeConnectionId"
                    :errorKey="'lexofficeConnectionId'"
                />

                <x-ui-input-select
                    name="datevConnectionId"
                    label="DATEV-Verbindung"
                    :options="$datevConnections->map(fn ($c) => ['value' => $c->id, 'label' => $c->name ?: 'DATEV #' . $c->id])"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="true"
                    wire:model.live="datevConnectionId"
                    :errorKey="'datevConnectionId'"
                />
            </div>

            <x-ui-input-text
                name="datevClientId"
                label="DATEV-Mandanten-ID"
                wire:model.live="datevClientId"
                placeholder="z. B. 12345"
                :errorKey="'datevClientId'"
            />

            <x-ui-input-textarea
                name="notes"
                label="Notizen (optional)"
                wire:model.live="notes"
                rows="3"
                :errorKey="'notes'"
            />

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="isActive" class="rounded">
                <span>Aktiv</span>
            </label>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="save">
                    Speichern
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
