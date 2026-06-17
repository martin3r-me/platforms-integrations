<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Integrationen', 'icon' => 'link', 'route' => 'integrations.connections.index'],
            ['label' => 'Sync-Profile', 'route' => 'integrations.sync-profiles.index'],
            ['label' => $bridge->name],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neues Mapping</span>
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

        {{-- Bridge Header --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500/10 to-indigo-600/5 flex items-center justify-center">
                        @svg('heroicon-o-arrows-right-left', 'w-6 h-6 text-indigo-600')
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-[var(--ui-secondary)] mb-1">{{ $bridge->name }}</h2>
                        <div class="text-sm text-[var(--ui-muted)] flex items-center gap-2 flex-wrap">
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
                    </div>
                </div>
                <x-ui-badge size="sm" variant="{{ $bridge->is_active ? 'success' : 'neutral' }}">
                    {{ $bridge->is_active ? 'aktiv' : 'inaktiv' }}
                </x-ui-badge>
            </div>
        </div>

        {{-- Mappings List --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-[var(--ui-border)]/40 flex items-center gap-3 flex-wrap">
                <h3 class="text-base font-semibold text-[var(--ui-secondary)]">Mappings ({{ $mappings->count() }})</h3>

                <div class="ml-auto flex items-center gap-2">
                    <span class="text-xs text-[var(--ui-muted)]">Filter:</span>
                    <select wire:model.live="filterType" class="text-sm border rounded-md px-2 py-1">
                        <option value="">Alle Typen</option>
                        @foreach($typeOptions as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($mappings->isEmpty())
                <div class="text-center py-12 text-[var(--ui-muted)]">
                    <p class="text-sm">Keine Mappings vorhanden{{ $filterType ? ' für diesen Filter' : '' }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Typ</th>
                                <th class="px-4 py-3">Quelle (Lexoffice)</th>
                                <th class="px-4 py-3">DATEV-Konto</th>
                                <th class="px-4 py-3">Art</th>
                                <th class="px-4 py-3">KSt / Steuer</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($mappings as $mapping)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700">
                                            {{ $mapping->mapping_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $mapping->source_label ?? '—' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $mapping->source_key }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-semibold text-gray-900">
                                        {{ $mapping->datev_account_number }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $mapping->account_kind }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-600">
                                        @if($mapping->cost_center_1 || $mapping->cost_center_2)
                                            KST: {{ $mapping->cost_center_1 }}{{ $mapping->cost_center_2 ? ' / ' . $mapping->cost_center_2 : '' }}<br>
                                        @endif
                                        @if($mapping->tax_key)
                                            Steuer: {{ $mapping->tax_key }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-ui-badge size="sm" variant="{{ $mapping->is_active ? 'success' : 'neutral' }}">
                                            {{ $mapping->is_active ? 'aktiv' : 'inaktiv' }}
                                        </x-ui-badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <x-ui-button variant="secondary-outline" size="sm" wire:click="openEditModal({{ $mapping->id }})">
                                                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                            </x-ui-button>
                                            <x-ui-button
                                                variant="danger-outline"
                                                size="sm"
                                                wire:click="delete({{ $mapping->id }})"
                                                wire:confirm="Mapping wirklich löschen?"
                                            >
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </x-ui-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Create / Edit Modal --}}
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">{{ $editingId ? 'Mapping bearbeiten' : 'Neues Mapping' }}</x-slot>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-select
                    name="mappingType"
                    label="Mapping-Typ"
                    :options="collect($typeOptions)"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="mappingType"
                    :errorKey="'mappingType'"
                />

                <x-ui-input-select
                    name="accountKind"
                    label="Konto-Art (DATEV)"
                    :options="collect($kindOptions)"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="accountKind"
                    :errorKey="'accountKind'"
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-text
                    name="sourceKey"
                    label="Quell-ID (Lexoffice)"
                    wire:model.live="sourceKey"
                    placeholder="z. B. Kontakt-UUID oder Category-Slug"
                    :errorKey="'sourceKey'"
                />
                <x-ui-input-text
                    name="sourceLabel"
                    label="Bezeichnung (optional)"
                    wire:model.live="sourceLabel"
                    placeholder="z. B. Firma Mustermann GmbH"
                    :errorKey="'sourceLabel'"
                />
            </div>

            <x-ui-input-text
                name="datevAccountNumber"
                label="DATEV-Kontonummer"
                wire:model.live="datevAccountNumber"
                placeholder="z. B. 10234 oder 4400"
                :errorKey="'datevAccountNumber'"
            />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-ui-input-text
                    name="costCenter1"
                    label="Kostenstelle 1 (optional)"
                    wire:model.live="costCenter1"
                    :errorKey="'costCenter1'"
                />
                <x-ui-input-text
                    name="costCenter2"
                    label="Kostenstelle 2 (optional)"
                    wire:model.live="costCenter2"
                    :errorKey="'costCenter2'"
                />
                <x-ui-input-text
                    name="taxKey"
                    label="Steuerschlüssel BU (optional)"
                    wire:model.live="taxKey"
                    :errorKey="'taxKey'"
                />
            </div>

            <x-ui-input-textarea
                name="notes"
                label="Notizen (optional)"
                wire:model.live="notes"
                rows="2"
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
