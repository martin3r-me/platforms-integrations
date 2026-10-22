@php
    $typeLabels = collect($typeOptions)->pluck('label', 'value');
    $kindLabels = collect($kindOptions)->pluck('label', 'value');
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$bridge->name" icon="heroicon-o-arrows-right-left" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Integrationen', 'icon' => 'link', 'route' => 'integrations.connections.index'],
            ['label' => 'Sync-Profile', 'route' => 'integrations.sync-profiles.index'],
            ['label' => $bridge->name],
        ]">
            <x-slot name="left">
                <div class="w-48">
                    <x-nx-input-select name="filterType" size="sm" wire:model.live="filterType"
                        :options="$typeOptions" nullable nullLabel="Alle Typen" />
                </div>
            </x-slot>
            <x-nx-button variant="primary" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Neues Mapping
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

        {{-- Bridge-Kopf --}}
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[color:var(--nx-muted)]">
            <span class="text-[color:var(--nx-text)]">{{ $bridge->lexofficeConnection?->name ?? 'Lexoffice #' . $bridge->lexoffice_connection_id }}</span>
            @svg('heroicon-o-arrows-right-left', 'w-4 h-4 text-[color:var(--nx-faint)]')
            <span class="text-[color:var(--nx-text)]">{{ $bridge->datevConnection?->name ?? 'DATEV #' . $bridge->datev_connection_id }}</span>
            <span class="text-[color:var(--nx-faint)]" aria-hidden="true">·</span>
            <span>Mandant <span class="font-mono">{{ $bridge->datev_client_id }}</span></span>
            <x-nx-badge :variant="$bridge->is_active ? 'success' : 'neutral'" dot>{{ $bridge->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
        </div>

        <x-nx-panel title="Mappings" :subtitle="$mappings->count() . ($filterType ? ' im Filter' : ' insgesamt')" flush>
            @if ($mappings->isEmpty())
                <x-nx-empty icon="heroicon-o-table-cells">
                    Keine Mappings{{ $filterType ? ' für diesen Filter' : '' }}.
                    <x-slot name="action">
                        <x-nx-button size="sm" wire:click="openCreateModal">Mapping anlegen</x-nx-button>
                    </x-slot>
                </x-nx-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[color:var(--nx-line)] text-left text-xs font-medium text-[color:var(--nx-faint)]">
                                <th class="px-4 py-2 font-medium">Typ</th>
                                <th class="px-4 py-2 font-medium">Quelle (Lexoffice)</th>
                                <th class="px-4 py-2 font-medium">DATEV-Konto</th>
                                <th class="px-4 py-2 font-medium">Art</th>
                                <th class="px-4 py-2 font-medium">KSt / Steuer</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="w-10 px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[color:var(--nx-line)]">
                            @foreach ($mappings as $mapping)
                                <tr wire:key="mapping-{{ $mapping->id }}" class="hover:bg-[color:var(--nx-hover)] {{ $mapping->is_active ? '' : 'opacity-60' }}">
                                    <td class="px-4 py-2.5">
                                        <x-nx-badge>{{ $typeLabels[$mapping->mapping_type] ?? $mapping->mapping_type }}</x-nx-badge>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="text-[color:var(--nx-text)]">{{ $mapping->source_label ?? '—' }}</div>
                                        <div class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $mapping->source_key }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 font-mono font-medium tabular-nums text-[color:var(--nx-text)]">{{ $mapping->datev_account_number }}</td>
                                    <td class="px-4 py-2.5 text-[color:var(--nx-muted)]">{{ $kindLabels[$mapping->account_kind] ?? $mapping->account_kind }}</td>
                                    <td class="px-4 py-2.5 text-xs text-[color:var(--nx-muted)]">
                                        @if ($mapping->cost_center_1 || $mapping->cost_center_2)
                                            <div>KSt {{ $mapping->cost_center_1 }}{{ $mapping->cost_center_2 ? ' / ' . $mapping->cost_center_2 : '' }}</div>
                                        @endif
                                        @if ($mapping->tax_key)
                                            <div>BU {{ $mapping->tax_key }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <x-nx-badge :variant="$mapping->is_active ? 'success' : 'neutral'" dot>{{ $mapping->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                                    </td>
                                    <td class="px-2 py-2.5 text-right">
                                        <x-nx-dropdown>
                                            <x-nx-dropdown-item wire:click="openEditModal({{ $mapping->id }})">
                                                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                                Bearbeiten
                                            </x-nx-dropdown-item>
                                            <x-nx-dropdown-divider />
                                            <x-nx-dropdown-item variant="danger" wire:click="delete({{ $mapping->id }})" wire:confirm="Mapping wirklich löschen?">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                Löschen
                                            </x-nx-dropdown-item>
                                        </x-nx-dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-nx-panel>
    </x-ui-page-container>

    <x-nx-modal wire:model="modalShow" size="lg">
        <x-slot name="header">{{ $editingId ? 'Mapping bearbeiten' : 'Neues Mapping' }}</x-slot>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-nx-input-select name="mappingType" label="Mapping-Typ" :options="$typeOptions"
                    wire:model.live="mappingType" errorKey="mappingType" />
                <x-nx-input-select name="accountKind" label="Konto-Art (DATEV)" :options="$kindOptions"
                    wire:model.live="accountKind" errorKey="accountKind" />
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-nx-input-text name="sourceKey" label="Quell-ID (Lexoffice)" placeholder="z. B. Kontakt-UUID oder Category-Slug"
                    wire:model.live="sourceKey" errorKey="sourceKey" />
                <x-nx-input-text name="sourceLabel" label="Bezeichnung (optional)" placeholder="z. B. Firma Mustermann GmbH"
                    wire:model.live="sourceLabel" errorKey="sourceLabel" />
            </div>

            <x-nx-input-text name="datevAccountNumber" label="DATEV-Kontonummer" placeholder="z. B. 10234 oder 4400"
                wire:model.live="datevAccountNumber" errorKey="datevAccountNumber" />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-nx-input-text name="costCenter1" label="Kostenstelle 1 (optional)" wire:model.live="costCenter1" errorKey="costCenter1" />
                <x-nx-input-text name="costCenter2" label="Kostenstelle 2 (optional)" wire:model.live="costCenter2" errorKey="costCenter2" />
                <x-nx-input-text name="taxKey" label="Steuerschlüssel BU (optional)" wire:model.live="taxKey" errorKey="taxKey" />
            </div>

            <x-nx-input-textarea name="notes" label="Notizen (optional)" rows="2" wire:model.live="notes" errorKey="notes" />

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
