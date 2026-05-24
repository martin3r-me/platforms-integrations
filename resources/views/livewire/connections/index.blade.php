<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Integrationen', 'icon' => 'link'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Connection</span>
            </x-ui-button>
        </x-ui-page-actionbar>
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
                    @if($metaConnections->isNotEmpty())
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            :href="route('integrations.oauth2.start', ['integrationKey' => 'meta'])"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Verbindung</span>
                            </span>
                        </x-ui-button>
                    @endif
                </div>

                @if($metaConnections->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($metaConnections as $metaConn)
                            <div class="p-4 {{ $metaConn->status === 'active' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($metaConn->status === 'active')
                                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                                        @else
                                            @svg('heroicon-o-exclamation-circle', 'w-6 h-6 text-yellow-600')
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium {{ $metaConn->status === 'active' ? 'text-green-900' : 'text-yellow-900' }}">
                                                {{ $metaConn->name ?? 'Meta' }}
                                            </p>
                                            @if($metaConn->is_default)
                                                <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                            @endif
                                            <x-ui-badge size="sm" variant="{{ $metaConn->status === 'active' ? 'success' : 'warning' }}">
                                                {{ $metaConn->status }}
                                            </x-ui-badge>
                                        </div>
                                        <p class="text-xs {{ $metaConn->status === 'active' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                                            Verbunden am {{ $metaConn->updated_at->format('d.m.Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        @if(!$metaConn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="setDefaultConnection({{ $metaConn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="openShareModal({{ $metaConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-user-group', 'w-4 h-4')
                                                <span>Freigaben</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            :href="route('integrations.oauth2.start', ['integrationKey' => 'meta', 'connection_id' => $metaConn->id])"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                <span>Reconnect</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="deleteConnection({{ $metaConn->id }})"
                                            wire:confirm="Meta-Verbindung '{{ $metaConn->name }}' wirklich löschen? Alle verknüpften Facebook Pages, Instagram Accounts und WhatsApp Accounts werden entfernt."
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                <span>Trennen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                </div>

                                @if($metaConn->status === 'active')
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Facebook Pages</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ $metaConn->facebookPages()->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Instagram Accounts</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ $metaConn->instagramAccounts()->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">WhatsApp Accounts</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ $metaConn->whatsappAccounts()->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">WA Templates</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsWhatsAppTemplate::where('user_id', auth()->id())->count() }}
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Sync-Buttons --}}
                                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/20">
                                        <div class="flex flex-col sm:flex-row gap-3">
                                            <x-ui-button
                                                variant="primary"
                                                size="sm"
                                                wire:click="syncAll({{ $metaConn->id }})"
                                                :disabled="$isSyncing"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    @if($isSyncing)
                                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                                    @else
                                                        @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                    @endif
                                                    <span>Alle synchronisieren</span>
                                                </span>
                                            </x-ui-button>

                                            <div class="flex gap-2 flex-wrap">
                                                <x-ui-button
                                                    variant="secondary-outline"
                                                    size="sm"
                                                    wire:click="syncFacebookPages({{ $metaConn->id }})"
                                                    :disabled="$isSyncing"
                                                >
                                                    <span class="inline-flex items-center gap-2">
                                                        @svg('heroicon-o-globe-alt', 'w-4 h-4')
                                                        <span>Facebook Pages</span>
                                                    </span>
                                                </x-ui-button>

                                                <x-ui-button
                                                    variant="secondary-outline"
                                                    size="sm"
                                                    wire:click="syncInstagramAccounts({{ $metaConn->id }})"
                                                    :disabled="$isSyncing"
                                                >
                                                    <span class="inline-flex items-center gap-2">
                                                        @svg('heroicon-o-camera', 'w-4 h-4')
                                                        <span>Instagram</span>
                                                    </span>
                                                </x-ui-button>

                                                <x-ui-button
                                                    variant="secondary-outline"
                                                    size="sm"
                                                    wire:click="syncWhatsAppAccounts({{ $metaConn->id }})"
                                                    :disabled="$isSyncing"
                                                >
                                                    <span class="inline-flex items-center gap-2">
                                                        @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4')
                                                        <span>WhatsApp</span>
                                                    </span>
                                                </x-ui-button>

                                                <x-ui-button
                                                    variant="secondary-outline"
                                                    size="sm"
                                                    wire:click="syncWhatsAppTemplates({{ $metaConn->id }})"
                                                    :disabled="$isSyncing"
                                                >
                                                    <span class="inline-flex items-center gap-2">
                                                        @svg('heroicon-o-document-text', 'w-4 h-4')
                                                        <span>WA Templates</span>
                                                    </span>
                                                </x-ui-button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if($syncMessage)
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800">{{ $syncMessage }}</p>
                            </div>
                        @endif

                        @if($syncError)
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    @svg('heroicon-o-exclamation-circle', 'w-5 h-5 text-red-600 flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-red-800">{{ $syncError }}</p>
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

        {{-- GitHub Integration (Prominent) --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-800/10 to-gray-900/5 flex items-center justify-center">
                            @svg('heroicon-o-code-bracket', 'w-6 h-6 text-gray-800')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">GitHub</h2>
                            <p class="text-sm text-[var(--ui-muted)]">Verbinde dein GitHub-Konto für Repository-Verwaltung</p>
                        </div>
                    </div>
                    @if($githubConnections->isNotEmpty())
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            :href="route('integrations.oauth2.start', ['integrationKey' => 'github'])"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Verbindung</span>
                            </span>
                        </x-ui-button>
                    @endif
                </div>

                @if($githubConnections->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($githubConnections as $ghConn)
                            <div class="p-4 {{ $ghConn->status === 'active' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($ghConn->status === 'active')
                                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                                        @else
                                            @svg('heroicon-o-exclamation-circle', 'w-6 h-6 text-yellow-600')
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium {{ $ghConn->status === 'active' ? 'text-green-900' : 'text-yellow-900' }}">
                                                {{ $ghConn->name ?? 'GitHub' }}
                                            </p>
                                            @if($ghConn->is_default)
                                                <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                            @endif
                                            <x-ui-badge size="sm" variant="{{ $ghConn->status === 'active' ? 'success' : 'warning' }}">
                                                {{ $ghConn->status }}
                                            </x-ui-badge>
                                        </div>
                                        <p class="text-xs {{ $ghConn->status === 'active' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                                            Verbunden am {{ $ghConn->updated_at->format('d.m.Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        @if(!$ghConn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="setDefaultConnection({{ $ghConn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            :href="route('integrations.oauth2.start', ['integrationKey' => 'github', 'connection_id' => $ghConn->id])"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                <span>Reconnect</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="deleteConnection({{ $ghConn->id }})"
                                            wire:confirm="GitHub-Verbindung '{{ $ghConn->name }}' wirklich löschen? Alle verknüpften Repositories werden entfernt."
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                <span>Trennen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                </div>

                                @if($ghConn->status === 'active')
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Repositories</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsGithubRepository::where('user_id', auth()->id())->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Private</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsGithubRepository::where('user_id', auth()->id())->where('is_private', true)->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Public</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsGithubRepository::where('user_id', auth()->id())->where('is_private', false)->count() }}
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Sync-Button --}}
                                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/20">
                                        <x-ui-button
                                            variant="primary"
                                            size="sm"
                                            wire:click="syncGithubRepositories({{ $ghConn->id }})"
                                            :disabled="$isSyncing"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @if($isSyncing)
                                                    @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                                @else
                                                    @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                @endif
                                                <span>Repositories synchronisieren</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if($syncMessage)
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800">{{ $syncMessage }}</p>
                            </div>
                        @endif

                        @if($syncError)
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    @svg('heroicon-o-exclamation-circle', 'w-5 h-5 text-red-600 flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-red-800">{{ $syncError }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                            @svg('heroicon-o-code-bracket', 'w-8 h-8 text-gray-600')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">GitHub-Konto noch nicht verbunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Verbinde dein GitHub-Konto, um deine Repositories zu verwalten</p>
                        <x-ui-button
                            variant="primary"
                            size="md"
                            :href="route('integrations.oauth2.start', ['integrationKey' => 'github'])"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-link', 'w-5 h-5')
                                <span>Mit GitHub verbinden</span>
                            </span>
                        </x-ui-button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Lexware Integration (Prominent) --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500/10 to-orange-600/5 flex items-center justify-center">
                            @svg('heroicon-o-calculator', 'w-6 h-6 text-orange-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">Lexware / Lexoffice</h2>
                            <p class="text-sm text-[var(--ui-muted)]">Verbinde dein Lexware-Konto für Buchhaltung und Kontaktverwaltung</p>
                        </div>
                    </div>
                    @if($lexwareConnections->isNotEmpty())
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            wire:click="openLexwareModal"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Verbindung</span>
                            </span>
                        </x-ui-button>
                    @endif
                </div>

                @if($lexwareConnections->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($lexwareConnections as $lexConn)
                            <div class="p-4 {{ $lexConn->status === 'active' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($lexConn->status === 'active')
                                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                                        @else
                                            @svg('heroicon-o-exclamation-circle', 'w-6 h-6 text-yellow-600')
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium {{ $lexConn->status === 'active' ? 'text-green-900' : 'text-yellow-900' }}">
                                                {{ $lexConn->name ?? 'Lexware' }}
                                            </p>
                                            @if($lexConn->is_default)
                                                <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                            @endif
                                            <x-ui-badge size="sm" variant="{{ $lexConn->status === 'active' ? 'success' : 'warning' }}">
                                                {{ $lexConn->status }}
                                            </x-ui-badge>
                                        </div>
                                        <p class="text-xs {{ $lexConn->status === 'active' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                                            Verbunden am {{ $lexConn->updated_at->format('d.m.Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        @if(!$lexConn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="setDefaultConnection({{ $lexConn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="openLexwareModalForEdit({{ $lexConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                <span>Token aktualisieren</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="deleteConnection({{ $lexConn->id }})"
                                            wire:confirm="Lexware-Verbindung '{{ $lexConn->name }}' wirklich loschen? Alle verknupften Kontakte werden entfernt."
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                <span>Trennen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                </div>

                                @if($lexConn->status === 'active')
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Kontakte</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsLexwareContact::where('user_id', auth()->id())->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Kunden</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsLexwareContact::where('user_id', auth()->id())->whereIn('contact_type', ['customer', 'both'])->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Lieferanten</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsLexwareContact::where('user_id', auth()->id())->whereIn('contact_type', ['vendor', 'both'])->count() }}
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Sync-Buttons --}}
                                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/20">
                                        <div class="flex flex-col sm:flex-row gap-3">
                                            <x-ui-button
                                                variant="primary"
                                                size="sm"
                                                wire:click="syncLexwareContacts({{ $lexConn->id }})"
                                                :disabled="$isSyncing"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    @if($isSyncing)
                                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                                    @else
                                                        @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                    @endif
                                                    <span>Kontakte synchronisieren</span>
                                                </span>
                                            </x-ui-button>

                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="testLexwareConnection({{ $lexConn->id }})"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    @svg('heroicon-o-signal', 'w-4 h-4')
                                                    <span>Verbindung testen</span>
                                                </span>
                                            </x-ui-button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if($syncMessage)
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800">{{ $syncMessage }}</p>
                            </div>
                        @endif

                        @if($syncError)
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    @svg('heroicon-o-exclamation-circle', 'w-5 h-5 text-red-600 flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-red-800">{{ $syncError }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-orange-100 mb-4">
                            @svg('heroicon-o-calculator', 'w-8 h-8 text-orange-600')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Lexware-Konto noch nicht verbunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Verbinde dein Lexware-Konto durch Eingabe deines API-Tokens</p>
                        <x-ui-button
                            variant="primary"
                            size="md"
                            wire:click="openLexwareModal"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-key', 'w-5 h-5')
                                <span>Lexware verbinden</span>
                            </span>
                        </x-ui-button>
                    </div>
                @endif
            </div>
        </div>

        {{-- HubSpot Integration (Prominent) --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500/10 to-orange-600/5 flex items-center justify-center">
                            @svg('heroicon-o-users', 'w-6 h-6 text-amber-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">HubSpot</h2>
                            <p class="text-sm text-[var(--ui-muted)]">Synchronisiere dein HubSpot CRM (Contacts, Companies, Deals, Engagements) via Private App Token</p>
                        </div>
                    </div>
                    @if($hubspotConnections->isNotEmpty())
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            wire:click="openHubspotModal()"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Verbindung</span>
                            </span>
                        </x-ui-button>
                    @endif
                </div>

                @if($hubspotConnections->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($hubspotConnections as $hubConn)
                            <div class="p-4 {{ $hubConn->status === 'active' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($hubConn->status === 'active')
                                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                                        @else
                                            @svg('heroicon-o-exclamation-circle', 'w-6 h-6 text-yellow-600')
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium {{ $hubConn->status === 'active' ? 'text-green-900' : 'text-yellow-900' }}">
                                                {{ $hubConn->name ?? 'HubSpot' }}
                                            </p>
                                            @if($hubConn->is_default)
                                                <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                            @endif
                                            <x-ui-badge size="sm" variant="{{ $hubConn->status === 'active' ? 'success' : 'warning' }}">
                                                {{ $hubConn->status }}
                                            </x-ui-badge>
                                        </div>
                                        <p class="text-xs {{ $hubConn->status === 'active' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                                            Verbunden am {{ $hubConn->updated_at->format('d.m.Y H:i') }}
                                        </p>
                                        @if($hubConn->last_error)
                                            <p class="text-xs text-red-700 mt-1">Letzter Fehler: {{ $hubConn->last_error }}</p>
                                        @endif
                                    </div>
                                    <div class="flex gap-2">
                                        @if(!$hubConn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="setDefaultConnection({{ $hubConn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="openHubspotModal({{ $hubConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                <span>Token aktualisieren</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="deleteConnection({{ $hubConn->id }})"
                                            wire:confirm="HubSpot-Verbindung '{{ $hubConn->name }}' wirklich loschen? Alle verknupften CRM-Daten werden entfernt."
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                <span>Trennen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                </div>

                                @if($hubConn->status === 'active')
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Contacts</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsHubspotContact::where('integration_connection_id', $hubConn->id)->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Companies</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsHubspotCompany::where('integration_connection_id', $hubConn->id)->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Deals</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsHubspotDeal::where('integration_connection_id', $hubConn->id)->count() }}
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Engagements</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                {{ \Platform\Integrations\Models\IntegrationsHubspotEngagement::where('integration_connection_id', $hubConn->id)->count() }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/20">
                                        <div class="flex flex-col sm:flex-row gap-3">
                                            <x-ui-button
                                                variant="primary"
                                                size="sm"
                                                wire:click="syncHubspotCrm({{ $hubConn->id }})"
                                                :disabled="$isSyncing"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    @if($isSyncing)
                                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                                    @else
                                                        @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                    @endif
                                                    <span>CRM synchronisieren</span>
                                                </span>
                                            </x-ui-button>

                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="testHubspotConnection({{ $hubConn->id }})"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    @svg('heroicon-o-signal', 'w-4 h-4')
                                                    <span>Verbindung testen</span>
                                                </span>
                                            </x-ui-button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-100 mb-4">
                            @svg('heroicon-o-users', 'w-8 h-8 text-amber-600')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">HubSpot-Konto noch nicht verbunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Verbinde dein HubSpot-Konto durch Eingabe eines Private App Access Tokens</p>
                        <x-ui-button
                            variant="primary"
                            size="md"
                            wire:click="openHubspotModal()"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-key', 'w-5 h-5')
                                <span>HubSpot verbinden</span>
                            </span>
                        </x-ui-button>
                    </div>
                @endif
            </div>
        </div>

        {{-- BuchhaltungsButler Integration (Prominent) --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-500/10 to-cyan-600/5 flex items-center justify-center">
                            @svg('heroicon-o-banknotes', 'w-6 h-6 text-cyan-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">BuchhaltungsButler</h2>
                            <p class="text-sm text-[var(--ui-muted)]">Erstelle Rechnungen, Angebote und Gutschriften als Entwurf via API.</p>
                        </div>
                    </div>
                    @if($buchhaltungsbutlerConnections->isNotEmpty())
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            wire:click="openBuchhaltungsbutlerModal()"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Verbindung</span>
                            </span>
                        </x-ui-button>
                    @endif
                </div>

                @if($buchhaltungsbutlerConnections->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($buchhaltungsbutlerConnections as $bbConn)
                            <div class="p-4 {{ $bbConn->status === 'active' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($bbConn->status === 'active')
                                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                                        @else
                                            @svg('heroicon-o-exclamation-circle', 'w-6 h-6 text-yellow-600')
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium {{ $bbConn->status === 'active' ? 'text-green-900' : 'text-yellow-900' }}">
                                                {{ $bbConn->name ?? 'BuchhaltungsButler' }}
                                            </p>
                                            @if($bbConn->is_default)
                                                <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                            @endif
                                            <x-ui-badge size="sm" variant="{{ $bbConn->status === 'active' ? 'success' : 'warning' }}">
                                                {{ $bbConn->status }}
                                            </x-ui-badge>
                                        </div>
                                        <p class="text-xs {{ $bbConn->status === 'active' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                                            Verbunden am {{ $bbConn->updated_at->format('d.m.Y H:i') }}
                                        </p>
                                        @if($bbConn->last_error)
                                            <p class="text-xs text-red-700 mt-1">Letzter Fehler: {{ $bbConn->last_error }}</p>
                                        @endif
                                    </div>
                                    <div class="flex gap-2">
                                        @if(!$bbConn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="setDefaultConnection({{ $bbConn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary-outline"
                                            size="sm"
                                            wire:click="testBuchhaltungsbutlerConnection({{ $bbConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-signal', 'w-4 h-4')
                                                <span>Testen</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="openBuchhaltungsbutlerModal({{ $bbConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                <span>Credentials aktualisieren</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="deleteConnection({{ $bbConn->id }})"
                                            wire:confirm="BuchhaltungsButler-Verbindung '{{ $bbConn->name }}' wirklich löschen?"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                <span>Trennen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-cyan-100 mb-4">
                            @svg('heroicon-o-banknotes', 'w-8 h-8 text-cyan-600')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">BuchhaltungsButler-Konto noch nicht verbunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Hinterlege API-Client, API-Secret und kundenspezifischen API-Key.</p>
                        <x-ui-button
                            variant="primary"
                            size="md"
                            wire:click="openBuchhaltungsbutlerModal()"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-key', 'w-5 h-5')
                                <span>BuchhaltungsButler verbinden</span>
                            </span>
                        </x-ui-button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sipgate Integration (Prominent) --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500/10 to-green-600/5 flex items-center justify-center">
                            @svg('heroicon-o-phone', 'w-6 h-6 text-green-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">Sipgate</h2>
                            <p class="text-sm text-[var(--ui-muted)]">Verbinde dein Sipgate-Konto für Telefonie, SMS und Fax</p>
                        </div>
                    </div>
                    @if($sipgateConnections->isNotEmpty())
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            :href="route('integrations.oauth2.start', ['integrationKey' => 'sipgate'])"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Verbindung</span>
                            </span>
                        </x-ui-button>
                    @endif
                </div>

                @if($sipgateConnections->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($sipgateConnections as $sipConn)
                            <div class="p-4 {{ $sipConn->status === 'active' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($sipConn->status === 'active')
                                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                                        @else
                                            @svg('heroicon-o-exclamation-circle', 'w-6 h-6 text-yellow-600')
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium {{ $sipConn->status === 'active' ? 'text-green-900' : 'text-yellow-900' }}">
                                                {{ $sipConn->name ?? 'Sipgate' }}
                                            </p>
                                            @if($sipConn->is_default)
                                                <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                            @endif
                                            <x-ui-badge size="sm" variant="{{ $sipConn->status === 'active' ? 'success' : 'warning' }}">
                                                {{ $sipConn->status }}
                                            </x-ui-badge>
                                        </div>
                                        <p class="text-xs {{ $sipConn->status === 'active' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                                            Verbunden am {{ $sipConn->updated_at->format('d.m.Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        @if(!$sipConn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="setDefaultConnection({{ $sipConn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            :href="route('integrations.oauth2.start', ['integrationKey' => 'sipgate', 'connection_id' => $sipConn->id])"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                <span>Reconnect</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="deleteConnection({{ $sipConn->id }})"
                                            wire:confirm="Sipgate-Verbindung '{{ $sipConn->name }}' wirklich löschen?"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                <span>Trennen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                </div>

                                @if($sipConn->status === 'active')
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Telefonie</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                @svg('heroicon-o-phone', 'w-6 h-6 text-green-600')
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">SMS</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                @svg('heroicon-o-chat-bubble-left', 'w-6 h-6 text-blue-600')
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white/60 border border-[var(--ui-border)]/40 rounded-xl">
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] mb-1 uppercase tracking-wide">Fax</div>
                                            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                                                @svg('heroicon-o-document', 'w-6 h-6 text-purple-600')
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Test-Button --}}
                                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/20">
                                        <x-ui-button
                                            variant="secondary-outline"
                                            size="sm"
                                            wire:click="testSipgateConnection({{ $sipConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-signal', 'w-4 h-4')
                                                <span>Verbindung testen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if($syncMessage)
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800">{{ $syncMessage }}</p>
                            </div>
                        @endif

                        @if($syncError)
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    @svg('heroicon-o-exclamation-circle', 'w-5 h-5 text-red-600 flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-red-800">{{ $syncError }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                            @svg('heroicon-o-phone', 'w-8 h-8 text-green-600')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Sipgate-Konto noch nicht verbunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Verbinde dein Sipgate-Konto für Telefonie, SMS und Fax</p>
                        <x-ui-button
                            variant="primary"
                            size="md"
                            :href="route('integrations.oauth2.start', ['integrationKey' => 'sipgate'])"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-link', 'w-5 h-5')
                                <span>Mit Sipgate verbinden</span>
                            </span>
                        </x-ui-button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Moss Integration --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500/10 to-emerald-600/5 flex items-center justify-center">
                            @svg('heroicon-o-banknotes', 'w-6 h-6 text-emerald-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">Moss</h2>
                            <p class="text-sm text-[var(--ui-muted)]">Spend-Management: Expenses, Suppliers, Bank Accounts, Dimensions und Payment Terms</p>
                        </div>
                    </div>
                    @if($mossConnections->isNotEmpty())
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            wire:click="openMossModal"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Verbindung</span>
                            </span>
                        </x-ui-button>
                    @endif
                </div>

                @if($mossConnections->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($mossConnections as $mossConn)
                            <div class="p-4 {{ $mossConn->status === 'active' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($mossConn->status === 'active')
                                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                                        @else
                                            @svg('heroicon-o-exclamation-circle', 'w-6 h-6 text-yellow-600')
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium {{ $mossConn->status === 'active' ? 'text-green-900' : 'text-yellow-900' }}">
                                                {{ $mossConn->name ?? 'Moss' }}
                                            </p>
                                            @if($mossConn->is_default)
                                                <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                            @endif
                                            <x-ui-badge size="sm" variant="{{ $mossConn->status === 'active' ? 'success' : 'warning' }}">
                                                {{ $mossConn->status }}
                                            </x-ui-badge>
                                        </div>
                                        <p class="text-xs {{ $mossConn->status === 'active' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                                            Verbunden am {{ $mossConn->updated_at->format('d.m.Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        @if(!$mossConn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="setDefaultConnection({{ $mossConn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="openMossModalForEdit({{ $mossConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                <span>Credentials aktualisieren</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="deleteConnection({{ $mossConn->id }})"
                                            wire:confirm="Moss-Verbindung '{{ $mossConn->name }}' wirklich löschen?"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                <span>Trennen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                </div>

                                @if($mossConn->status === 'active')
                                    {{-- Test-Button --}}
                                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/20">
                                        <x-ui-button
                                            variant="secondary-outline"
                                            size="sm"
                                            wire:click="testMossConnection({{ $mossConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-signal', 'w-4 h-4')
                                                <span>Verbindung testen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if($syncMessage)
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800">{{ $syncMessage }}</p>
                            </div>
                        @endif

                        @if($syncError)
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    @svg('heroicon-o-exclamation-circle', 'w-5 h-5 text-red-600 flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-red-800">{{ $syncError }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 mb-4">
                            @svg('heroicon-o-banknotes', 'w-8 h-8 text-emerald-600')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Moss noch nicht verbunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Verbinde dein Moss-Konto durch Eingabe deiner Client Credentials (Client ID/Client Secret)</p>
                        <x-ui-button
                            variant="primary"
                            size="md"
                            wire:click="openMossModal"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-key', 'w-5 h-5')
                                <span>Moss verbinden</span>
                            </span>
                        </x-ui-button>
                    </div>
                @endif
            </div>
        </div>

        {{-- DataForSEO Integration --}}
        <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500/10 to-indigo-600/5 flex items-center justify-center">
                            @svg('heroicon-o-magnifying-glass', 'w-6 h-6 text-indigo-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">DataForSEO</h2>
                            <p class="text-sm text-[var(--ui-muted)]">SEO-Keyword-Daten: Suchvolumen, verwandte Keywords und Keyword-Vorschläge</p>
                        </div>
                    </div>
                    @if($dataforseoConnections->isNotEmpty())
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            wire:click="openDataforseoModal"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Verbindung</span>
                            </span>
                        </x-ui-button>
                    @endif
                </div>

                @if($dataforseoConnections->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($dataforseoConnections as $dfsConn)
                            <div class="p-4 {{ $dfsConn->status === 'active' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($dfsConn->status === 'active')
                                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600')
                                        @else
                                            @svg('heroicon-o-exclamation-circle', 'w-6 h-6 text-yellow-600')
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium {{ $dfsConn->status === 'active' ? 'text-green-900' : 'text-yellow-900' }}">
                                                {{ $dfsConn->name ?? 'DataForSEO' }}
                                            </p>
                                            @if($dfsConn->is_default)
                                                <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                            @endif
                                            <x-ui-badge size="sm" variant="{{ $dfsConn->status === 'active' ? 'success' : 'warning' }}">
                                                {{ $dfsConn->status }}
                                            </x-ui-badge>
                                        </div>
                                        <p class="text-xs {{ $dfsConn->status === 'active' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                                            Verbunden am {{ $dfsConn->updated_at->format('d.m.Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        @if(!$dfsConn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="sm"
                                                wire:click="setDefaultConnection({{ $dfsConn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="openDataforseoModalForEdit({{ $dfsConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                <span>Credentials aktualisieren</span>
                                            </span>
                                        </x-ui-button>
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="sm"
                                            wire:click="deleteConnection({{ $dfsConn->id }})"
                                            wire:confirm="DataForSEO-Verbindung '{{ $dfsConn->name }}' wirklich löschen?"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                <span>Trennen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                </div>

                                @if($dfsConn->status === 'active')
                                    {{-- Test-Button --}}
                                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/20">
                                        <x-ui-button
                                            variant="secondary-outline"
                                            size="sm"
                                            wire:click="testDataforseoConnection({{ $dfsConn->id }})"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                @svg('heroicon-o-signal', 'w-4 h-4')
                                                <span>Verbindung testen</span>
                                            </span>
                                        </x-ui-button>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if($syncMessage)
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800">{{ $syncMessage }}</p>
                            </div>
                        @endif

                        @if($syncError)
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    @svg('heroicon-o-exclamation-circle', 'w-5 h-5 text-red-600 flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-red-800">{{ $syncError }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-4">
                            @svg('heroicon-o-magnifying-glass', 'w-8 h-8 text-indigo-600')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">DataForSEO noch nicht verbunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Verbinde dein DataForSEO-Konto durch Eingabe deiner API-Credentials (Login/Password)</p>
                        <x-ui-button
                            variant="primary"
                            size="md"
                            wire:click="openDataforseoModal"
                        >
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-key', 'w-5 h-5')
                                <span>DataForSEO verbinden</span>
                            </span>
                        </x-ui-button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Für mich freigegeben --}}
        @if($sharedWithMe->isNotEmpty())
            <div class="bg-white rounded-2xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
                <div class="p-6 lg:p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/10 to-purple-600/5 flex items-center justify-center">
                            @svg('heroicon-o-share', 'w-6 h-6 text-purple-600')
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--ui-secondary)] mb-1">Für mich freigegeben</h2>
                            <p class="text-sm text-[var(--ui-muted)]">Connections, die andere User mit dir geteilt haben</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($sharedWithMe as $sharedConn)
                            <div class="group relative overflow-hidden rounded-xl border border-purple-200/60 shadow-sm bg-purple-50/30 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-4 flex-1 min-w-0">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500/10 to-purple-600/5 flex items-center justify-center flex-shrink-0">
                                            @svg('heroicon-o-link', 'w-5 h-5 text-purple-600')
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <h3 class="text-sm font-bold text-[var(--ui-secondary)] truncate">
                                                    {{ $sharedConn->name ?? $sharedConn->integration->name ?? '—' }}
                                                </h3>
                                                <x-ui-badge size="sm" variant="{{ $sharedConn->status === 'active' ? 'success' : 'warning' }}">
                                                    {{ $sharedConn->status }}
                                                </x-ui-badge>
                                            </div>
                                            <div class="flex items-center gap-3 text-xs text-[var(--ui-muted)]">
                                                <span class="inline-flex items-center gap-1">
                                                    @svg('heroicon-o-squares-2x2', 'w-3 h-3')
                                                    {{ $sharedConn->integration->name ?? '—' }}
                                                </span>
                                                <span class="inline-flex items-center gap-1">
                                                    @svg('heroicon-o-user', 'w-3 h-3')
                                                    von {{ $sharedConn->ownerUser->name ?? $sharedConn->ownerUser->email ?? '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

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
                                            <div class="flex items-center gap-2 mb-1">
                                                <h3 class="text-sm font-bold text-[var(--ui-secondary)] truncate">
                                                    {{ $conn->name ?? $conn->integration->name ?? $conn->integration->key ?? '—' }}
                                                </h3>
                                                @if($conn->is_default)
                                                    <x-ui-badge size="sm" variant="primary">Standard</x-ui-badge>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 text-xs text-[var(--ui-muted)]">
                                                <span class="inline-flex items-center gap-1">
                                                    @svg('heroicon-o-squares-2x2', 'w-3 h-3')
                                                    {{ $conn->integration->name ?? $conn->integration->key ?? '—' }}
                                                </span>
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
                                        @if(!$conn->is_default)
                                            <x-ui-button
                                                variant="secondary-outline"
                                                size="xs"
                                                wire:click="setDefaultConnection({{ $conn->id }})"
                                                title="Als Standard setzen"
                                            >
                                                @svg('heroicon-o-star', 'w-3.5 h-3.5')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="secondary-outline"
                                            size="xs"
                                            wire:click="openShareModal({{ $conn->id }})"
                                            title="Freigaben verwalten"
                                        >
                                            @svg('heroicon-o-user-group', 'w-3.5 h-3.5')
                                        </x-ui-button>
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
                                                :href="route('integrations.oauth2.start', ['integrationKey' => $conn->integration->key ?? '', 'connection_id' => $conn->id])"
                                            >
                                                @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                            </x-ui-button>
                                        @endif
                                        <x-ui-button
                                            variant="danger-outline"
                                            size="xs"
                                            wire:click="deleteConnection({{ $conn->id }})"
                                            wire:confirm="Connection '{{ $conn->name }}' wirklich löschen?"
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
                        @if($metaConnections->where('status', 'active')->isNotEmpty())
                            <div class="flex justify-between items-center py-2 px-3 bg-green-50 border border-green-200 rounded-lg">
                                <span class="text-sm text-green-700">Meta verbunden</span>
                                <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                    {{ $metaConnections->where('status', 'active')->count() }}
                                </span>
                            </div>
                        @endif
                        @if($githubConnections->where('status', 'active')->isNotEmpty())
                            <div class="flex justify-between items-center py-2 px-3 bg-green-50 border border-green-200 rounded-lg">
                                <span class="text-sm text-green-700">GitHub verbunden</span>
                                <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                    {{ $githubConnections->where('status', 'active')->count() }}
                                </span>
                            </div>
                        @endif
                        @if($lexwareConnections->where('status', 'active')->isNotEmpty())
                            <div class="flex justify-between items-center py-2 px-3 bg-green-50 border border-green-200 rounded-lg">
                                <span class="text-sm text-green-700">Lexware verbunden</span>
                                <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                    {{ $lexwareConnections->where('status', 'active')->count() }}
                                </span>
                            </div>
                        @endif
                        @if($sipgateConnections->where('status', 'active')->isNotEmpty())
                            <div class="flex justify-between items-center py-2 px-3 bg-green-50 border border-green-200 rounded-lg">
                                <span class="text-sm text-green-700">Sipgate verbunden</span>
                                <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                    {{ $sipgateConnections->where('status', 'active')->count() }}
                                </span>
                            </div>
                        @endif
                        @if($dataforseoConnections->where('status', 'active')->isNotEmpty())
                            <div class="flex justify-between items-center py-2 px-3 bg-green-50 border border-green-200 rounded-lg">
                                <span class="text-sm text-green-700">DataForSEO verbunden</span>
                                <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                    {{ $dataforseoConnections->where('status', 'active')->count() }}
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

    {{-- Lexware Modal (API-Token Eingabe) --}}
    <x-ui-modal wire:model="lexwareModalShow" size="md">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-orange-500/10 to-orange-600/5 flex items-center justify-center">
                    @svg('heroicon-o-calculator', 'w-5 h-5 text-orange-600')
                </div>
                <span>Lexware verbinden</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5')
                    <div class="text-sm text-amber-800">
                        <p class="font-medium mb-1">API-Token erforderlich</p>
                        <p>Lexware verwendet keinen OAuth-Flow. Du musst deinen API-Token manuell eingeben.</p>
                        <p class="mt-2">Den API-Token findest du in deinem Lexoffice-Konto unter:</p>
                        <p class="font-mono text-xs mt-1 bg-amber-100 px-2 py-1 rounded">Einstellungen → Erweiterungen → Public API</p>
                    </div>
                </div>
            </div>

            <x-ui-input-text
                name="lexwareApiToken"
                label="API-Token"
                wire:model.live="lexwareApiToken"
                type="password"
                placeholder="Dein Lexware API-Token..."
                :errorKey="'lexwareApiToken'"
            />

            <div class="text-xs text-gray-500">
                Der Token wird verschlüsselt gespeichert und ist nur für dich sichtbar.
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeLexwareModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveLexwareConnection">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Verbinden</span>
                    </span>
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- HubSpot Modal (Private App Token Eingabe) --}}
    <x-ui-modal wire:model="hubspotModalShow" size="md">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500/10 to-orange-600/5 flex items-center justify-center">
                    @svg('heroicon-o-users', 'w-5 h-5 text-amber-600')
                </div>
                <span>HubSpot verbinden</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5')
                    <div class="text-sm text-amber-800">
                        <p class="font-medium mb-1">Private App Access Token erforderlich</p>
                        <p>Erstelle in HubSpot eine Private App und füge das generierte Access Token hier ein.</p>
                        <p class="mt-2 font-medium">Erforderliche Scopes:</p>
                        <ul class="mt-1 list-disc list-inside font-mono text-xs space-y-0.5">
                            <li>crm.objects.contacts.read</li>
                            <li>crm.objects.companies.read</li>
                            <li>crm.objects.deals.read</li>
                            <li>crm.objects.notes.read</li>
                            <li>crm.objects.calls.read</li>
                            <li>crm.objects.emails.read</li>
                            <li>crm.objects.meetings.read</li>
                            <li>crm.objects.tasks.read</li>
                        </ul>
                        <p class="mt-2">Private App findest du in HubSpot unter:</p>
                        <p class="font-mono text-xs mt-1 bg-amber-100 px-2 py-1 rounded">Settings &rarr; Integrations &rarr; Private Apps</p>
                    </div>
                </div>
            </div>

            <x-ui-input-text
                name="hubspotApiToken"
                label="Private App Access Token"
                wire:model.live="hubspotApiToken"
                type="password"
                placeholder="pat-eu1-..."
                :errorKey="'hubspotApiToken'"
            />

            <div class="text-xs text-gray-500">
                Der Token wird verschlüsselt gespeichert und ist nur für dich sichtbar.
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeHubspotModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveHubspotConnection">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Verbinden</span>
                    </span>
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- BuchhaltungsButler Modal (API Client + Secret + Key) --}}
    <x-ui-modal wire:model="buchhaltungsbutlerModalShow" size="md">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-cyan-500/10 to-cyan-600/5 flex items-center justify-center">
                    @svg('heroicon-o-banknotes', 'w-5 h-5 text-cyan-600')
                </div>
                <span>BuchhaltungsButler verbinden</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <div class="p-4 bg-cyan-50 border border-cyan-200 rounded-lg">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-cyan-600 flex-shrink-0 mt-0.5')
                    <div class="text-sm text-cyan-800">
                        <p class="font-medium mb-1">Drei Werte erforderlich</p>
                        <p>BuchhaltungsButler nutzt HTTP Basic Auth (API-Client + API-Secret) plus einen kundenspezifischen API-Key.</p>
                        <p class="mt-2 font-medium">Werte findest du in BuchhaltungsButler unter:</p>
                        <p class="font-mono text-xs mt-1 bg-cyan-100 px-2 py-1 rounded">Einstellungen &rarr; Schnittstellen und API-Zugang</p>
                        <p class="mt-2 text-xs">Dort API aktivieren, anschließend werden API-Client und API-Secret angezeigt; nach Verifizierung wird der API-Key sichtbar.</p>
                    </div>
                </div>
            </div>

            <x-ui-input-text
                name="buchhaltungsbutlerApiClient"
                label="API-Client"
                wire:model.live="buchhaltungsbutlerApiClient"
                type="text"
                placeholder="z.B. ak_xxxxx"
                :errorKey="'buchhaltungsbutlerApiClient'"
            />

            <x-ui-input-text
                name="buchhaltungsbutlerApiSecret"
                label="API-Secret"
                wire:model.live="buchhaltungsbutlerApiSecret"
                type="password"
                placeholder="••••••••"
                :errorKey="'buchhaltungsbutlerApiSecret'"
            />

            <x-ui-input-text
                name="buchhaltungsbutlerApiKey"
                label="API-Key (kundenspezifisch)"
                wire:model.live="buchhaltungsbutlerApiKey"
                type="password"
                placeholder="••••••••"
                :errorKey="'buchhaltungsbutlerApiKey'"
            />

            <div class="text-xs text-gray-500">
                Alle drei Werte werden verschlüsselt gespeichert und sind nur für dich sichtbar.
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeBuchhaltungsbutlerModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveBuchhaltungsbutlerConnection">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Verbinden</span>
                    </span>
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Moss Modal (Client ID / Client Secret Eingabe) --}}
    <x-ui-modal wire:model="mossModalShow" size="md">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-500/10 to-emerald-600/5 flex items-center justify-center">
                    @svg('heroicon-o-banknotes', 'w-5 h-5 text-emerald-600')
                </div>
                <span>Moss verbinden</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5')
                    <div class="text-sm text-emerald-800">
                        <p class="font-medium mb-1">API-Credentials erforderlich</p>
                        <p>Moss verwendet OAuth2 Client Credentials. Du benötigst deine Client ID und dein Client Secret.</p>
                        <p class="mt-2">Die Credentials findest du in deinem Moss-Konto unter:</p>
                        <p class="font-mono text-xs mt-1 bg-emerald-100 px-2 py-1 rounded">getmoss.com &rarr; API Settings</p>
                    </div>
                </div>
            </div>

            <x-ui-input-text
                name="mossClientId"
                label="Client ID"
                wire:model.live="mossClientId"
                type="text"
                placeholder="kid_..."
                :errorKey="'mossClientId'"
            />

            <x-ui-input-text
                name="mossClientSecret"
                label="Client Secret"
                wire:model.live="mossClientSecret"
                type="password"
                placeholder="sk_..."
                :errorKey="'mossClientSecret'"
            />

            <div class="text-xs text-gray-500">
                Die Credentials werden verschlüsselt gespeichert und sind nur für dich sichtbar.
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeMossModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveMossConnection">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Verbinden</span>
                    </span>
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- DataForSEO Modal (Login/Password Eingabe) --}}
    <x-ui-modal wire:model="dataforseoModalShow" size="md">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-500/10 to-indigo-600/5 flex items-center justify-center">
                    @svg('heroicon-o-magnifying-glass', 'w-5 h-5 text-indigo-600')
                </div>
                <span>DataForSEO verbinden</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5')
                    <div class="text-sm text-blue-800">
                        <p class="font-medium mb-1">API-Credentials erforderlich</p>
                        <p>DataForSEO verwendet Basic Auth. Du benötigst deinen API-Login und dein API-Password.</p>
                        <p class="mt-2">Die Credentials findest du in deinem DataForSEO-Konto unter:</p>
                        <p class="font-mono text-xs mt-1 bg-blue-100 px-2 py-1 rounded">app.dataforseo.com &rarr; API Access</p>
                    </div>
                </div>
            </div>

            <x-ui-input-text
                name="dataforseoLogin"
                label="API Login (E-Mail)"
                wire:model.live="dataforseoLogin"
                type="text"
                placeholder="dein-login@example.com"
                :errorKey="'dataforseoLogin'"
            />

            <x-ui-input-text
                name="dataforseoPassword"
                label="API Password"
                wire:model.live="dataforseoPassword"
                type="password"
                placeholder="Dein DataForSEO API-Password..."
                :errorKey="'dataforseoPassword'"
            />

            <div class="text-xs text-gray-500">
                Die Credentials werden verschlüsselt gespeichert und sind nur für dich sichtbar.
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeDataforseoModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveDataforseoConnection">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Verbinden</span>
                    </span>
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Share Modal --}}
    <x-ui-modal wire:model="shareModalShow" size="lg">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500/10 to-purple-600/5 flex items-center justify-center">
                    @svg('heroicon-o-user-group', 'w-5 h-5 text-purple-600')
                </div>
                <span>Freigaben: {{ $shareConnectionName }}</span>
            </div>
        </x-slot>

        <div class="space-y-6">
            {{-- Freigabe-Art Auswahl --}}
            <div>
                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Freigabe-Art</label>
                <div class="flex gap-2">
                    <button
                        type="button"
                        wire:click="$set('shareType', 'team')"
                        class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors {{ $shareType === 'team' ? 'bg-purple-100 border-purple-300 text-purple-800' : 'bg-white border-[var(--ui-border)] text-[var(--ui-muted)] hover:bg-gray-50' }}"
                    >
                        @svg('heroicon-o-user-group', 'w-4 h-4 inline mr-1')
                        Ganzes Team
                    </button>
                    <button
                        type="button"
                        wire:click="$set('shareType', 'user')"
                        class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors {{ $shareType === 'user' ? 'bg-purple-100 border-purple-300 text-purple-800' : 'bg-white border-[var(--ui-border)] text-[var(--ui-muted)] hover:bg-gray-50' }}"
                    >
                        @svg('heroicon-o-user', 'w-4 h-4 inline mr-1')
                        Einzelner User
                    </button>
                </div>
            </div>

            {{-- Team-Dropdown (wenn shareType='team') --}}
            @if($shareType === 'team')
                <div>
                    <x-ui-input-select
                        name="shareTeamId"
                        label="Team auswählen"
                        :options="$userTeams->map(fn($t) => ['value' => $t->id, 'label' => $t->name])"
                        optionValue="value"
                        optionLabel="label"
                        :nullable="true"
                        wire:model.live="shareTeamId"
                        :errorKey="'shareType'"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">
                        Leer lassen = Freigabe für alle Teams (Wildcard)
                    </p>
                </div>
            @endif

            {{-- User-Dropdown (wenn shareType='user') --}}
            @if($shareType === 'user')
                <div>
                    <x-ui-input-select
                        name="shareUserId"
                        label="User auswählen"
                        :options="$teamUsers->map(fn($u) => ['value' => $u->id, 'label' => $u->name . ' (' . $u->email . ')'])"
                        optionValue="value"
                        optionLabel="label"
                        :nullable="false"
                        wire:model.live="shareUserId"
                        :errorKey="'shareType'"
                    />
                </div>
            @endif

            {{-- Ressourcen-Dropdown (optional, nur bei has_resources=true) --}}
            @if($shareConnectionHasResources && count($shareableResources) > 0)
                <div>
                    <x-ui-input-select
                        name="shareResourceId"
                        label="Ressource einschränken (optional)"
                        :options="collect($shareableResources)->map(fn($r) => ['value' => $r['id'], 'label' => $r['label']])"
                        optionValue="value"
                        optionLabel="label"
                        :nullable="true"
                        wire:model.live="shareResourceId"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">
                        Leer lassen = Freigabe für alle Ressourcen der Connection
                    </p>
                </div>
            @endif

            {{-- Freigabe hinzufügen --}}
            <div>
                <x-ui-button type="button" variant="primary" size="sm" wire:click="addShare">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Freigabe hinzufügen</span>
                    </span>
                </x-ui-button>
            </div>

            {{-- Aktive Freigaben-Liste --}}
            @if(count($sharesList) > 0)
                <div>
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-3">Aktive Freigaben</h3>
                    <div class="space-y-2">
                        @foreach($sharesList as $share)
                            <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($share['team_id'] && !$share['user_id'])
                                        @svg('heroicon-o-user-group', 'w-4 h-4 text-purple-600 flex-shrink-0')
                                    @elseif($share['user_id'] && !$share['team_id'])
                                        @svg('heroicon-o-user', 'w-4 h-4 text-blue-600 flex-shrink-0')
                                    @elseif($share['team_id'] && $share['user_id'])
                                        @svg('heroicon-o-user', 'w-4 h-4 text-indigo-600 flex-shrink-0')
                                    @else
                                        @svg('heroicon-o-globe-alt', 'w-4 h-4 text-green-600 flex-shrink-0')
                                    @endif
                                    <span class="text-sm text-[var(--ui-secondary)] truncate">
                                        {{ $share['wildcard_description'] }}
                                    </span>
                                </div>
                                <x-ui-button
                                    type="button"
                                    variant="danger-outline"
                                    size="xs"
                                    wire:click="removeShare({{ $share['id'] }})"
                                    wire:confirm="Freigabe wirklich entfernen?"
                                >
                                    @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                </x-ui-button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center py-6 border-2 border-dashed border-[var(--ui-border)]/40 rounded-xl bg-[var(--ui-muted-5)]">
                    <p class="text-sm text-[var(--ui-muted)]">Noch keine Freigaben vorhanden</p>
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeShareModal">
                    Schließen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
