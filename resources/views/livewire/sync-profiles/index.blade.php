<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Sync-Profile" icon="heroicon-o-arrows-right-left" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Integrationen', 'icon' => 'link', 'route' => 'integrations.connections.index'],
            ['label' => 'Sync-Profile (Lexoffice ↔ DATEV)'],
        ]">
            <x-nx-button variant="primary" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Neue Bridge
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        @if ($flashSuccess)
            <x-nx-callout variant="success">{{ $flashSuccess }}</x-nx-callout>
        @endif
        @if ($flashError)
            <x-nx-callout variant="danger">{{ $flashError }}</x-nx-callout>
        @endif

        <p class="m-0 max-w-2xl text-sm text-[color:var(--nx-muted)]">
            Eine Bridge paart eine Lexoffice-Verbindung mit einer DATEV-Verbindung und einem Mandanten. Pro Bridge werden danach die Konten-Mappings gepflegt.
        </p>

        @if ($lexofficeConnections->isEmpty() || $datevConnections->isEmpty())
            <x-nx-callout variant="warning" title="Aktive Verbindungen fehlen">
                Benötigt werden je eine aktive Lexoffice- und DATEV-Verbindung
                (aktuell: Lexoffice {{ $lexofficeConnections->count() }}, DATEV {{ $datevConnections->count() }}).
                <x-slot name="action">
                    <x-nx-button size="sm" :href="route('integrations.connections.index')">Zu den Verbindungen</x-nx-button>
                </x-slot>
            </x-nx-callout>
        @endif

        @if ($bridges->isEmpty())
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-arrows-right-left">
                    Noch keine Bridges angelegt.
                    <x-slot name="action">
                        <x-nx-button size="sm" wire:click="openCreateModal">Bridge anlegen</x-nx-button>
                    </x-slot>
                </x-nx-empty>
            </x-nx-card>
        @else
            <x-nx-card flush>
                <ul class="divide-y divide-[color:var(--nx-line)]">
                    @foreach ($bridges as $bridge)
                        <li wire:key="bridge-{{ $bridge->id }}" class="flex items-center gap-3 px-4 py-3 {{ $bridge->is_active ? '' : 'opacity-60' }}">
                            <a href="{{ route('integrations.sync-profiles.mappings', $bridge->id) }}" class="min-w-0 flex-1 group">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-medium text-[color:var(--nx-text)] group-hover:underline">{{ $bridge->name }}</span>
                                    <x-nx-badge :variant="$bridge->is_active ? 'success' : 'neutral'" dot>{{ $bridge->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-[color:var(--nx-faint)]">
                                    <span class="text-[color:var(--nx-muted)]">{{ $bridge->lexofficeConnection?->name ?? 'Lexoffice #' . $bridge->lexoffice_connection_id }}</span>
                                    @svg('heroicon-o-arrows-right-left', 'w-3 h-3')
                                    <span class="text-[color:var(--nx-muted)]">{{ $bridge->datevConnection?->name ?? 'DATEV #' . $bridge->datev_connection_id }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>Mandant <span class="font-mono">{{ $bridge->datev_client_id }}</span></span>
                                </div>
                                @if ($bridge->notes)
                                    <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">{{ $bridge->notes }}</p>
                                @endif
                            </a>

                            <x-nx-button size="sm" :href="route('integrations.sync-profiles.mappings', $bridge->id)">
                                @svg('heroicon-o-table-cells', 'w-4 h-4')
                                <span class="hidden sm:inline">Mappings</span>
                            </x-nx-button>
                            <x-nx-dropdown>
                                <x-nx-dropdown-item wire:click="openEditModal({{ $bridge->id }})">
                                    @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    Bearbeiten
                                </x-nx-dropdown-item>
                                <x-nx-dropdown-item wire:click="toggleActive({{ $bridge->id }})">
                                    @svg($bridge->is_active ? 'heroicon-o-pause' : 'heroicon-o-play', 'w-4 h-4')
                                    {{ $bridge->is_active ? 'Deaktivieren' : 'Aktivieren' }}
                                </x-nx-dropdown-item>
                                <x-nx-dropdown-divider />
                                <x-nx-dropdown-item variant="danger" wire:click="delete({{ $bridge->id }})"
                                    wire:confirm="Bridge „{{ $bridge->name }}“ wirklich löschen? Alle Mappings darin werden mit gelöscht.">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                    Löschen
                                </x-nx-dropdown-item>
                            </x-nx-dropdown>
                        </li>
                    @endforeach
                </ul>
            </x-nx-card>
        @endif
    </x-ui-page-container>

    <x-nx-modal wire:model="modalShow" size="lg">
        <x-slot name="header">{{ $editingId ? 'Bridge bearbeiten' : 'Neue Bridge' }}</x-slot>

        <div class="space-y-4">
            <x-nx-input-text name="name" label="Name" placeholder="z. B. BHG.DIGITAL Hauptbuch 2026"
                wire:model.live="name" errorKey="name" />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-nx-input-select name="lexofficeConnectionId" label="Lexoffice-Verbindung" nullable
                    :options="$lexofficeConnections->map(fn ($c) => ['value' => $c->id, 'label' => $c->name ?: 'Lexoffice #' . $c->id])"
                    wire:model.live="lexofficeConnectionId" errorKey="lexofficeConnectionId" />
                <x-nx-input-select name="datevConnectionId" label="DATEV-Verbindung" nullable
                    :options="$datevConnections->map(fn ($c) => ['value' => $c->id, 'label' => $c->name ?: 'DATEV #' . $c->id])"
                    wire:model.live="datevConnectionId" errorKey="datevConnectionId" />
            </div>

            <x-nx-input-text name="datevClientId" label="DATEV-Mandanten-ID" placeholder="z. B. 12345"
                wire:model.live="datevClientId" errorKey="datevClientId" />

            <x-nx-input-textarea name="notes" label="Notizen (optional)" rows="3"
                wire:model.live="notes" errorKey="notes" />

            <label class="inline-flex items-center gap-2 text-sm text-[color:var(--nx-text)]">
                <input type="checkbox" wire:model.live="isActive" class="h-4 w-4 rounded border-[color:var(--nx-line-strong)] accent-[color:var(--nx-accent)]">
                Aktiv
            </label>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
