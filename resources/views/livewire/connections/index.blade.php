<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Integrationen" icon="heroicon-o-link">
            <x-slot name="actions">
                <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Neue Connection</span>
                    </span>
                </x-ui-button>
            </x-slot>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        @if (session('status'))
            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-check-circle', 'w-5 h-5 text-green-600')
                    <p class="text-sm font-medium text-green-800">{{ session('status') }}</p>
                </div>
            </div>
        @endif

        {{-- Meta Integration (Prominent) --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/10 to-blue-600/5 flex items-center justify-center">
                            @svg('heroicon-o-globe-alt', 'w-6 h-6 text-blue-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">Meta (Facebook, Instagram, WhatsApp)</h2>
                            <p class="text-sm text-[var(--ui-muted)]">Verbinde dein Meta-Konto für Facebook Pages, Instagram Accounts und WhatsApp Business</p>
                        </div>
                    </div>
                </div>

                @if($metaConnection && $metaConnection->status === 'active')
                    <div class="space-y-4">
                        <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-xl">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-green-900">Meta-Konto ist verbunden</p>
                                <p class="text-xs text-green-700 mt-1">
                                    Verbunden am {{ $metaConnection->updated_at->format('d.m.Y H:i') }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <x-ui-button 
                                    variant="secondary" 
                                    size="sm"
                                    :href="route('integrations.oauth2.start', ['integrationKey' => 'meta'])"
                                >
                                    <span class="inline-flex items-center gap-2">
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                        <span>Erneut verbinden</span>
                                    </span>
                                </x-ui-button>
                                <x-ui-button 
                                    variant="danger-outline" 
                                    size="sm"
                                    wire:click="deleteConnection({{ $metaConnection->id }})"
                                    wire:confirm="Meta-Verbindung wirklich löschen? Alle verknüpften Facebook Pages, Instagram Accounts und WhatsApp Accounts werden entfernt."
                                >
                                    <span class="inline-flex items-center gap-2">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                        <span>Trennen</span>
                                    </span>
                                </x-ui-button>
                            </div>
                        </div>

                        @if($metaToken)
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-xl">
                                    <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Facebook Pages</div>
                                    <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                        {{ \Platform\Integrations\Models\IntegrationsFacebookPage::where('user_id', auth()->id())->count() }}
                                    </div>
                                </div>
                                <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-xl">
                                    <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Instagram Accounts</div>
                                    <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                        {{ \Platform\Integrations\Models\IntegrationsInstagramAccount::where('user_id', auth()->id())->count() }}
                                    </div>
                                </div>
                                <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-xl">
                                    <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">WhatsApp Accounts</div>
                                    <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                        {{ \Platform\Integrations\Models\IntegrationsWhatsAppAccount::where('user_id', auth()->id())->count() }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-4">
                            @svg('heroicon-o-link', 'w-8 h-8 text-blue-600')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Meta-Konto noch nicht verbunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Verbinde dein Meta-Konto, um Facebook Pages, Instagram Accounts und WhatsApp Business zu verwalten</p>
                        <x-ui-button 
                            variant="primary" 
                            size="md"
                            :href="route('integrations.oauth2.start', ['integrationKey' => 'meta'])"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-link', 'w-5 h-5')
                                <span>Mit Meta verbinden</span>
                            </span>
                        </x-ui-button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Alle Connections --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">Alle Connections</h2>
                        <p class="text-sm text-[var(--ui-muted)]">Verwalte alle deine Integration-Verbindungen</p>
                    </div>
                </div>

                @if($connections->count() > 0)
                    <div class="space-y-3">
                        @foreach($connections as $conn)
                            <div class="group relative overflow-hidden rounded-xl border border-[var(--ui-border)]/60 shadow-sm hover:shadow-md transition-all duration-300 bg-white p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-4 flex-1 min-w-0">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[var(--ui-primary)]/10 to-[var(--ui-primary)]/5 flex items-center justify-center flex-shrink-0">
                                            @svg('heroicon-o-link', 'w-5 h-5 text-[var(--ui-primary)]')
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-sm font-bold text-[var(--ui-secondary)] mb-1 truncate">
                                                {{ $conn->integration->name ?? $conn->integration->key ?? '—' }}
                                            </h3>
                                            <div class="flex items-center gap-3 text-xs text-[var(--ui-muted)]">
                                                <span class="inline-flex items-center gap-1">
                                                    @svg('heroicon-o-key', 'w-3 h-3')
                                                    {{ $conn->auth_scheme }}
                                                </span>
                                                <span class="inline-flex items-center gap-1">
                                                    @svg('heroicon-o-clock', 'w-3 h-3')
                                                    {{ $conn->updated_at->diffForHumans() }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <x-ui-badge
                                            size="sm"
                                            variant="{{ $conn->status === 'active' ? 'success' : ($conn->status === 'error' ? 'danger' : 'secondary') }}"
                                        >
                                            {{ $conn->status }}
                                        </x-ui-badge>
                                        <x-ui-button 
                                            variant="secondary-outline" 
                                            size="xs" 
                                            wire:click="openEditModal({{ $conn->id }})"
                                        >
                                            @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                                        </x-ui-button>
                                        @if($conn->auth_scheme === 'oauth2')
                                            <x-ui-button 
                                                variant="secondary-outline" 
                                                size="xs"
                                                :href="route('integrations.oauth2.start', ['integrationKey' => $conn->integration->key ?? ''])"
                                            >
                                                @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button 
                                            variant="danger-outline" 
                                            size="xs"
                                            wire:click="deleteConnection({{ $conn->id }})"
                                            wire:confirm="Connection wirklich löschen?"
                                        >
                                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                        </x-ui-button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $connections->links() }}
                    </div>
                @else
                    <div class="text-center py-12 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[var(--ui-muted)] mb-4">
                            @svg('heroicon-o-link', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Noch keine Connections</p>
                        <p class="text-xs text-[var(--ui-muted)]">Erstelle deine erste Connection, um zu starten</p>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Navigation --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Navigation</h3>
                    <div class="flex flex-col gap-2">
                        <a href="{{ route('integrations.connections.index') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-[var(--ui-primary)] bg-[var(--ui-primary-10)] border border-[var(--ui-primary)]/20 rounded-lg">
                            @svg('heroicon-o-link', 'w-4 h-4')
                            <span>Connections</span>
                        </a>
                    </div>
                </div>

                {{-- Aktionen --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Aktionen</h3>
                    <div class="flex flex-col gap-2">
                        <x-ui-button variant="primary" size="sm" wire:click="openCreateModal" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Connection</span>
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center py-2 px-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Connections</span>
                            <span class="text-sm text-[var(--ui-secondary)] font-medium">
                                {{ $connections->total() }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2 px-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Aktiv</span>
                            <span class="text-sm text-[var(--ui-secondary)] font-medium">
                                {{ $connections->where('status', 'active')->count() }}
                            </span>
                        </div>
                        @if($metaConnection && $metaConnection->status === 'active')
                            <div class="flex justify-between items-center py-2 px-3 bg-green-50 border border-green-200 rounded-lg">
                                <span class="text-sm text-green-700">Meta verbunden</span>
                                <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                    ✓
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

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

                <div class="text-sm text-gray-600">
                    Owner: {{ auth()->user()->name ?? auth()->user()->email }}
                </div>
            </div>

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
            <div class="grid grid-cols-1 gap-4">
                <x-ui-input-text name="integrationKey" wire:model.live="integrationKey" label="Integration Key" :errorKey="'integrationKey'" />
                <div class="text-sm text-gray-600">
                    Owner: {{ auth()->user()->name ?? auth()->user()->email }}
                </div>
            </div>

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
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start gap-2">
                        @svg('heroicon-o-exclamation-circle', 'w-5 h-5 text-red-600 flex-shrink-0 mt-0.5')
                        <div class="text-sm">
                            <div class="font-medium text-red-800">Letzter Fehler</div>
                            <div class="mt-1 whitespace-pre-wrap text-red-700">{{ $lastError }}</div>
                        </div>
                    </div>
                </div>
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
</x-ui-page>
