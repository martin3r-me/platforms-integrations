@php
    use Platform\Integrations\Models\IntegrationConnection;
    use Platform\Integrations\Models\IntegrationsGithubRepository;
    use Platform\Integrations\Models\IntegrationsHubspotCompany;
    use Platform\Integrations\Models\IntegrationsHubspotContact;
    use Platform\Integrations\Models\IntegrationsHubspotDeal;
    use Platform\Integrations\Models\IntegrationsHubspotEngagement;
    use Platform\Integrations\Models\IntegrationsLexwareContact;
    use Platform\Integrations\Models\IntegrationsWhatsAppTemplate;

    $uid = auth()->id();
    $oauth = fn (string $key, ?int $id = null) => route('integrations.oauth2.start', array_filter(['integrationKey' => $key, 'connection_id' => $id]));

    $statusVariant = fn (?string $s) => match ($s) { 'active' => 'success', 'error' => 'danger', 'draft', 'disabled' => 'neutral', default => 'warning' };
    $statusLabel = fn (?string $s) => match ($s) { 'active' => 'Aktiv', 'error' => 'Fehler', 'draft' => 'Entwurf', 'disabled' => 'Deaktiviert', 'pending' => 'Ausstehend', default => ucfirst((string) $s) };

    // Katalog: ein Eintrag je Anbieter. Aktionen sind entweder ['href' => …] (OAuth-Flow)
    // oder ['wire' => …] (Livewire-Methode, öffnet das Token-Modal o. ä.).
    $groups = [
        'social' => 'Kommunikation & Social',
        'finance' => 'Finanzen & Buchhaltung',
        'erp' => 'Warenwirtschaft & Logistik',
        'crm' => 'CRM',
        'dev' => 'Entwicklung & Infrastruktur',
        'seo' => 'SEO & Analytics',
    ];

    $providers = [
        [
            'key' => 'meta', 'group' => 'social', 'label' => 'Meta', 'icon' => 'heroicon-o-globe-alt',
            'desc' => 'Facebook Pages, Instagram Accounts und WhatsApp Business',
            'conns' => $metaConnections,
            'connect' => ['href' => $oauth('meta'), 'label' => 'Mit Meta verbinden'],
            'renew' => fn ($c) => ['href' => $oauth('meta', $c->id), 'label' => 'Neu verbinden'],
            'sync' => [
                ['syncAll', 'Alles synchronisieren'],
                ['syncFacebookPages', 'Facebook Pages'],
                ['syncInstagramAccounts', 'Instagram Accounts'],
                ['syncWhatsAppAccounts', 'WhatsApp Accounts'],
                ['syncWhatsAppTemplates', 'WhatsApp-Templates'],
            ],
            'stats' => fn ($c) => [
                'Pages' => $c->facebookPages()->count(),
                'Instagram' => $c->instagramAccounts()->count(),
                'WhatsApp' => $c->whatsappAccounts()->count(),
                'WA-Templates' => IntegrationsWhatsAppTemplate::where('user_id', $uid)->count(),
            ],
            'confirm' => 'Alle verknüpften Facebook Pages, Instagram Accounts und WhatsApp Accounts werden entfernt.',
        ],
        [
            'key' => 'sipgate', 'group' => 'social', 'label' => 'Sipgate', 'icon' => 'heroicon-o-phone',
            'desc' => 'Telefonie, SMS und Fax',
            'conns' => $sipgateConnections,
            'connect' => ['href' => $oauth('sipgate'), 'label' => 'Mit Sipgate verbinden'],
            'renew' => fn ($c) => ['href' => $oauth('sipgate', $c->id), 'label' => 'Neu verbinden'],
            'test' => 'testSipgateConnection',
        ],
        [
            'key' => 'lexoffice', 'group' => 'finance', 'label' => 'Lexware / Lexoffice', 'icon' => 'heroicon-o-calculator',
            'desc' => 'Buchhaltung und Kontakte — per API-Token',
            'conns' => $lexwareConnections,
            'connect' => ['wire' => 'openLexwareModal', 'label' => 'Lexware verbinden'],
            'renew' => fn ($c) => ['wire' => "openLexwareModalForEdit({$c->id})", 'label' => 'Token aktualisieren'],
            'test' => 'testLexwareConnection',
            'sync' => [['syncLexwareContacts', 'Kontakte synchronisieren']],
            'stats' => fn ($c) => [
                'Kontakte' => IntegrationsLexwareContact::where('user_id', $uid)->count(),
                'Kunden' => IntegrationsLexwareContact::where('user_id', $uid)->whereIn('contact_type', ['customer', 'both'])->count(),
                'Lieferanten' => IntegrationsLexwareContact::where('user_id', $uid)->whereIn('contact_type', ['vendor', 'both'])->count(),
            ],
            'confirm' => 'Alle verknüpften Kontakte werden entfernt.',
        ],
        [
            'key' => 'easybill', 'group' => 'finance', 'label' => 'easybill', 'icon' => 'heroicon-o-document-text',
            'desc' => 'Rechnungen, Belege, Kunden und Positionen — per API-Token',
            'conns' => $easybillConnections,
            'connect' => ['wire' => 'openEasybillModal', 'label' => 'easybill verbinden'],
            'renew' => fn ($c) => ['wire' => "openEasybillModalForEdit({$c->id})", 'label' => 'Token aktualisieren'],
            'test' => 'testEasybillConnection',
        ],
        [
            'key' => 'buchhaltungsbutler', 'group' => 'finance', 'label' => 'BuchhaltungsButler', 'icon' => 'heroicon-o-banknotes',
            'desc' => 'Rechnungen, Angebote und Gutschriften als Entwurf',
            'conns' => $buchhaltungsbutlerConnections,
            'connect' => ['wire' => 'openBuchhaltungsbutlerModal()', 'label' => 'BuchhaltungsButler verbinden'],
            'renew' => fn ($c) => ['wire' => "openBuchhaltungsbutlerModal({$c->id})", 'label' => 'Zugangsdaten aktualisieren'],
            'test' => 'testBuchhaltungsbutlerConnection', 'testAlways' => true,
        ],
        [
            'key' => 'datev', 'group' => 'finance', 'label' => 'DATEV', 'icon' => 'heroicon-o-building-library',
            'desc' => 'Buchhaltung, Rechnungswesen und Mandanten',
            'conns' => $datevConnections,
            'connect' => ['href' => $oauth('datev'), 'label' => 'Mit DATEV verbinden'],
            'renew' => fn ($c) => ['href' => $oauth('datev', $c->id), 'label' => 'Neu verbinden'],
            'test' => 'testDatevConnection',
            'links' => [['href' => route('integrations.sync-profiles.index'), 'label' => 'Sync-Profile (Lexoffice ↔ DATEV)', 'icon' => 'heroicon-o-arrows-right-left']],
        ],
        [
            'key' => 'moss', 'group' => 'finance', 'label' => 'Moss', 'icon' => 'heroicon-o-credit-card',
            'desc' => 'Spend-Management: Ausgaben, Lieferanten, Bankkonten, Dimensionen',
            'conns' => $mossConnections,
            'connect' => ['wire' => 'openMossModal', 'label' => 'Moss verbinden'],
            'renew' => fn ($c) => ['wire' => "openMossModalForEdit({$c->id})", 'label' => 'Zugangsdaten aktualisieren'],
            'test' => 'testMossConnection',
        ],
        [
            'key' => 'necta', 'group' => 'erp', 'label' => 'necta.one', 'icon' => 'heroicon-o-cube',
            'desc' => 'Warenwirtschaft: Produkte, Kunden, Bestellungen und Rechnungen',
            'conns' => $nectaConnections,
            'connect' => ['wire' => 'openNectaModal', 'label' => 'necta.one verbinden'],
            'renew' => fn ($c) => ['wire' => "openNectaModalForEdit({$c->id})", 'label' => 'Zugangsdaten aktualisieren'],
            // Auch im Fehlerzustand testbar: ein erfolgreicher Test setzt die Verbindung
            // wieder auf aktiv — die Zugangsdaten sind meist unverändert gültig.
            'test' => 'testNectaConnection', 'testStatuses' => ['active', 'error'],
            'meta' => fn ($c) => ($c->credentials['base_url'] ?? null) ? [$c->credentials['base_url'], 'mono'] : null,
        ],
        [
            'key' => 'dedefleet', 'group' => 'erp', 'label' => 'DedeFleet', 'icon' => 'heroicon-o-truck',
            'desc' => 'Ortung und Tourenplanung: Aufträge, Touren, Fahrzeuge, GPS-Positionen',
            'conns' => $dedefleetConnections,
            'connect' => ['wire' => 'openDedefleetModal', 'label' => 'DedeFleet verbinden'],
            'renew' => fn ($c) => ['wire' => "openDedefleetModalForEdit({$c->id})", 'label' => 'Token aktualisieren'],
            'test' => 'testDedefleetConnection',
        ],
        [
            'key' => 'hubspot', 'group' => 'crm', 'label' => 'HubSpot', 'icon' => 'heroicon-o-users',
            'desc' => 'Contacts, Companies, Deals und Engagements — per Private App Token',
            'conns' => $hubspotConnections,
            'connect' => ['wire' => 'openHubspotModal()', 'label' => 'HubSpot verbinden'],
            'renew' => fn ($c) => ['wire' => "openHubspotModal({$c->id})", 'label' => 'Token aktualisieren'],
            'test' => 'testHubspotConnection',
            'sync' => [['syncHubspotCrm', 'CRM synchronisieren']],
            'stats' => fn ($c) => [
                'Contacts' => IntegrationsHubspotContact::where('integration_connection_id', $c->id)->count(),
                'Companies' => IntegrationsHubspotCompany::where('integration_connection_id', $c->id)->count(),
                'Deals' => IntegrationsHubspotDeal::where('integration_connection_id', $c->id)->count(),
                'Engagements' => IntegrationsHubspotEngagement::where('integration_connection_id', $c->id)->count(),
            ],
            'confirm' => 'Alle verknüpften CRM-Daten werden entfernt.',
        ],
        [
            'key' => 'github', 'group' => 'dev', 'label' => 'GitHub', 'icon' => 'heroicon-o-code-bracket',
            'desc' => 'Repositories',
            'conns' => $githubConnections,
            'connect' => ['href' => $oauth('github'), 'label' => 'Mit GitHub verbinden'],
            'renew' => fn ($c) => ['href' => $oauth('github', $c->id), 'label' => 'Neu verbinden'],
            'sync' => [['syncGithubRepositories', 'Repositories synchronisieren']],
            'stats' => fn ($c) => [
                'Repositories' => IntegrationsGithubRepository::where('user_id', $uid)->count(),
                'privat' => IntegrationsGithubRepository::where('user_id', $uid)->where('is_private', true)->count(),
                'öffentlich' => IntegrationsGithubRepository::where('user_id', $uid)->where('is_private', false)->count(),
            ],
            'confirm' => 'Alle verknüpften Repositories werden entfernt.',
        ],
        [
            'key' => 'forge', 'group' => 'dev', 'label' => 'Laravel Forge', 'icon' => 'heroicon-o-server-stack',
            'desc' => 'Server, Sites und Deployments (API v2, organisationsbezogen)',
            'conns' => $forgeConnections,
            'connect' => ['wire' => 'openForgeModal', 'label' => 'Laravel Forge verbinden'],
            'renew' => fn ($c) => ['wire' => "openForgeModalForEdit({$c->id})", 'label' => 'Token aktualisieren'],
            'test' => 'testForgeConnection',
            'meta' => fn ($c) => ($c->credentials['organization'] ?? null)
                ? ['Organisation: ' . $c->credentials['organization'], 'mono']
                : ['Keine Standard-Organisation — „organization“ muss bei jedem Aufruf mitgegeben werden', 'warning'],
        ],
        [
            'key' => 'hetzner', 'group' => 'dev', 'label' => 'Hetzner Cloud', 'icon' => 'heroicon-o-cloud',
            'desc' => 'Server, Volumes, Netzwerke und DNS — ein Token je Projekt',
            'conns' => $hetznerConnections,
            'connect' => ['wire' => 'openHetznerModal', 'label' => 'Hetzner Cloud verbinden'],
            'renew' => fn ($c) => ['wire' => "openHetznerModalForEdit({$c->id})", 'label' => 'Token aktualisieren'],
            'test' => 'testHetznerConnection',
            'meta' => fn ($c) => ($c->credentials['project'] ?? null) ? ['Projekt: ' . $c->credentials['project'], 'mono'] : null,
        ],
        [
            'key' => 'google_search_console', 'group' => 'seo', 'label' => 'Google Search Console', 'icon' => 'heroicon-o-magnifying-glass-circle',
            'desc' => 'Search Analytics, Sitemaps und URL Inspection',
            'conns' => $googleSearchConsoleConnections,
            'connect' => ['href' => $oauth('google_search_console'), 'label' => 'Mit Google verbinden'],
            'connectAlt' => ['wire' => 'openGscServiceAccountModal', 'label' => 'Service-Account'],
            'renew' => fn ($c) => $c->auth_scheme === 'service_account'
                ? ['wire' => "openGscServiceAccountModalForEdit({$c->id})", 'label' => 'Key aktualisieren']
                : ['href' => $oauth('google_search_console', $c->id), 'label' => 'Neu verbinden'],
            'test' => 'testGoogleSearchConsoleConnection',
            'meta' => fn ($c) => [$c->auth_scheme === 'service_account' ? 'Service-Account' : 'OAuth', 'plain'],
        ],
        [
            'key' => 'dataforseo', 'group' => 'seo', 'label' => 'DataForSEO', 'icon' => 'heroicon-o-magnifying-glass',
            'desc' => 'Keyword-Daten: Suchvolumen, verwandte Keywords und Vorschläge',
            'conns' => $dataforseoConnections,
            'connect' => ['wire' => 'openDataforseoModal', 'label' => 'DataForSEO verbinden'],
            'renew' => fn ($c) => ['wire' => "openDataforseoModalForEdit({$c->id})", 'label' => 'Zugangsdaten aktualisieren'],
            'test' => 'testDataforseoConnection',
        ],
    ];

    // Innerhalb einer Gruppe: verbundene Anbieter zuerst, sonst Katalog-Reihenfolge.
    $byGroup = collect($providers)
        ->groupBy('group')
        ->map(fn ($items) => $items->sortBy(fn ($p) => $p['conns']->isEmpty() ? 1 : 0, SORT_REGULAR, false)->values());

    $connectedProviders = collect($providers)->filter(fn ($p) => $p['conns']->isNotEmpty())->count();

    $statusCounts = IntegrationConnection::query()
        ->where('owner_user_id', $uid)
        ->selectRaw('status, COUNT(*) AS n')
        ->groupBy('status')
        ->pluck('n', 'status');
    $totalOwned = (int) $statusCounts->sum();
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Integrationen" icon="heroicon-o-link" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Integrationen', 'icon' => 'link']]">
            <x-slot name="left">
                <div class="flex items-center gap-3" x-data="{ f: 'all' }">
                    <div class="inline-flex items-center gap-0.5 rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-0.5">
                        @foreach (['all' => ['Alle', count($providers)], 'connected' => ['Verbunden', $connectedProviders], 'open' => ['Nicht verbunden', count($providers) - $connectedProviders]] as $key => [$label, $n])
                            <button type="button" @click="f = '{{ $key }}'; $dispatch('integrations-filter', f)"
                                :class="f === '{{ $key }}' ? 'bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-sm' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]'"
                                class="inline-flex items-center gap-1.5 rounded-[5px] px-2.5 py-1 text-sm font-medium transition-colors">
                                {{ $label }}
                                <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $n }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="w-56">
                        <input type="search" placeholder="Anbieter suchen…"
                            @input.debounce.150ms="$dispatch('integrations-search', $event.target.value)"
                            class="h-8 w-full rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-2.5 text-sm text-[color:var(--nx-text)] placeholder:text-[color:var(--nx-faint)] focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]">
                    </div>
                </div>
            </x-slot>

            <x-nx-dropdown label="Aktionen" width="w-72">
                <x-nx-dropdown-item :href="route('integrations.sync-profiles.index')">
                    @svg('heroicon-o-arrows-right-left', 'w-4 h-4')
                    Sync-Profile (Lexoffice ↔ DATEV)
                </x-nx-dropdown-item>
                <x-nx-dropdown-divider />
                <x-nx-dropdown-item wire:click="openCreateModal">
                    @svg('heroicon-o-code-bracket-square', 'w-4 h-4')
                    Verbindung manuell anlegen (JSON)
                </x-nx-dropdown-item>
            </x-nx-dropdown>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        @if (session('status'))
            <x-nx-callout variant="success">{{ session('status') }}</x-nx-callout>
        @endif
        @if (session('error'))
            <x-nx-callout variant="danger">{{ session('error') }}</x-nx-callout>
        @endif

        <x-nx-stat-grid cols="4">
            <x-nx-stat label="Verbindungen" :value="$totalOwned" :hint="$connectedProviders . ' von ' . count($providers) . ' Anbietern'" icon="heroicon-o-link" />
            <x-nx-stat label="Aktiv" :value="(int) ($statusCounts['active'] ?? 0)" icon="heroicon-o-check-circle" accent="var(--nx-success)" />
            <x-nx-stat label="Mit Fehler" :value="(int) ($statusCounts['error'] ?? 0)" icon="heroicon-o-exclamation-triangle"
                :accent="($statusCounts['error'] ?? 0) > 0 ? 'var(--nx-danger)' : null" />
            <x-nx-stat label="Für mich freigegeben" :value="$sharedWithMe->count()" icon="heroicon-o-share" />
        </x-nx-stat-grid>

        {{-- Katalog: Gruppen → Anbieter → Verbindungen. Filter/Suche rein clientseitig. --}}
        <div class="space-y-6" x-data="{
                q: '',
                f: 'all',
                show(text, connected) {
                    if (this.f === 'connected' && ! connected) return false;
                    if (this.f === 'open' && connected) return false;
                    return this.q === '' || text.includes(this.q.toLowerCase());
                },
                any(items) { return items.some(([t, c]) => this.show(t, c)); },
            }"
            @integrations-search.window="q = $event.detail"
            @integrations-filter.window="f = $event.detail">

            @foreach ($groups as $groupKey => $groupLabel)
                @php
                    $items = $byGroup[$groupKey] ?? collect();
                    $index = $items->map(fn ($p) => [mb_strtolower($p['label'] . ' ' . $p['desc'] . ' ' . $p['key']), $p['conns']->isNotEmpty()])->values()->all();
                @endphp
                @continue($items->isEmpty())

                <section x-show="any(@js($index))">
                    <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $groupLabel }}</h2>
                    <x-nx-card flush>
                        <ul class="divide-y divide-[color:var(--nx-line)]">
                            @foreach ($items as $p)
                                @php
                                    $connected = $p['conns']->isNotEmpty();
                                    $searchText = mb_strtolower($p['label'] . ' ' . $p['desc'] . ' ' . $p['key']);
                                @endphp
                                <li id="provider-{{ $p['key'] }}" wire:key="provider-{{ $p['key'] }}" class="scroll-mt-4" x-show="show(@js($searchText), {{ $connected ? 'true' : 'false' }})">
                                    {{-- Anbieter-Kopf --}}
                                    <div class="flex items-center gap-3 px-4 py-3">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] {{ $connected ? 'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-text)]' : 'bg-[color:var(--nx-bg)] text-[color:var(--nx-faint)]' }}">
                                            @svg($p['icon'], 'w-4 h-4')
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $p['label'] }}</span>
                                                @if ($p['conns']->count() > 1)
                                                    <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $p['conns']->count() }} Verbindungen</span>
                                                @endif
                                            </div>
                                            <p class="m-0 truncate text-xs text-[color:var(--nx-muted)]">{{ $p['desc'] }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1.5">
                                            @foreach ($p['links'] ?? [] as $link)
                                                @if ($connected)
                                                    <x-nx-button variant="ghost" size="sm" :href="$link['href']">
                                                        @svg($link['icon'], 'w-4 h-4')
                                                        <span class="hidden sm:inline">{{ \Illuminate\Support\Str::before($link['label'], ' (') }}</span>
                                                    </x-nx-button>
                                                @endif
                                            @endforeach
                                            @foreach (array_filter([$p['connect'], $p['connectAlt'] ?? null]) as $i => $a)
                                                @if ($connected)
                                                    {{-- Weitere Verbindung: dezent, nur Icon + Kurzlabel --}}
                                                    @if (isset($a['href']))
                                                        <x-nx-button variant="ghost" size="sm" :href="$a['href']" :title="$a['label']">
                                                            @svg('heroicon-o-plus', 'w-4 h-4')
                                                            <span class="hidden sm:inline">{{ $i === 0 ? 'Weitere' : $a['label'] }}</span>
                                                        </x-nx-button>
                                                    @else
                                                        <x-nx-button variant="ghost" size="sm" wire:click="{{ $a['wire'] }}" :title="$a['label']">
                                                            @svg('heroicon-o-plus', 'w-4 h-4')
                                                            <span class="hidden sm:inline">{{ $i === 0 ? 'Weitere' : $a['label'] }}</span>
                                                        </x-nx-button>
                                                    @endif
                                                @else
                                                    @if (isset($a['href']))
                                                        <x-nx-button :variant="$i === 0 ? 'secondary' : 'ghost'" size="sm" :href="$a['href']">{{ $i === 0 ? 'Verbinden' : $a['label'] }}</x-nx-button>
                                                    @else
                                                        <x-nx-button :variant="$i === 0 ? 'secondary' : 'ghost'" size="sm" wire:click="{{ $a['wire'] }}">{{ $i === 0 ? 'Verbinden' : $a['label'] }}</x-nx-button>
                                                    @endif
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>

                                    {{-- Verbindungen des Anbieters --}}
                                    @foreach ($p['conns'] as $c)
                                        @php
                                            $isActive = $c->status === 'active';
                                            $testStatuses = $p['testStatuses'] ?? ['active'];
                                            $canTest = isset($p['test']) && (($p['testAlways'] ?? false) || in_array($c->status, $testStatuses, true));
                                            $stats = ($isActive && isset($p['stats'])) ? ($p['stats'])($c) : [];
                                            $meta = isset($p['meta']) ? ($p['meta'])($c) : null;
                                            $renew = ($p['renew'])($c);
                                            $sync = $isActive ? ($p['sync'] ?? []) : [];
                                        @endphp
                                        <div wire:key="conn-{{ $c->id }}" class="flex items-start gap-3 border-t border-dashed border-[color:var(--nx-line)] py-2.5 pl-[3.75rem] pr-4">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                    <span class="text-sm text-[color:var(--nx-text)]">{{ $c->name ?: $p['label'] }}</span>
                                                    <x-nx-badge :variant="$statusVariant($c->status)" dot>{{ $statusLabel($c->status) }}</x-nx-badge>
                                                    @if ($c->is_default && $p['conns']->count() > 1)
                                                        <x-nx-badge>Standard</x-nx-badge>
                                                    @endif
                                                </div>
                                                <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-[color:var(--nx-faint)]">
                                                    @if ($meta)
                                                        <span @class([
                                                            'font-mono' => $meta[1] === 'mono',
                                                            'text-[color:var(--nx-warning)]' => $meta[1] === 'warning',
                                                        ])>{{ $meta[0] }}</span>
                                                        <span aria-hidden="true">·</span>
                                                    @endif
                                                    <span title="{{ $c->updated_at->format('d.m.Y H:i') }}">seit {{ $c->updated_at->format('d.m.Y') }}</span>
                                                    @foreach ($stats as $statLabel => $statValue)
                                                        <span aria-hidden="true">·</span>
                                                        <span><span class="tabular-nums text-[color:var(--nx-muted)]">{{ number_format($statValue, 0, ',', '.') }}</span> {{ $statLabel }}</span>
                                                    @endforeach
                                                </div>
                                                @if ($c->last_error)
                                                    <p class="m-0 mt-1 text-xs text-[color:var(--nx-danger)]">{{ \Illuminate\Support\Str::limit($c->last_error, 240) }}</p>
                                                @endif
                                            </div>

                                            <div class="flex shrink-0 items-center gap-1">
                                                {{-- Sichtbar: die häufigste Aktion (Sync, sonst Test) --}}
                                                @if (! empty($sync))
                                                    <x-nx-button size="sm" wire:click="{{ $sync[0][0] }}({{ $c->id }})" :disabled="$isSyncing" wire:loading.attr="disabled" wire:target="{{ $sync[0][0] }}">
                                                        <span wire:loading.remove wire:target="{{ $sync[0][0] }}">@svg('heroicon-o-arrow-path', 'w-4 h-4')</span>
                                                        <span wire:loading wire:target="{{ $sync[0][0] }}">@svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')</span>
                                                        <span class="hidden sm:inline">Synchronisieren</span>
                                                    </x-nx-button>
                                                @elseif ($canTest)
                                                    <x-nx-button size="sm" wire:click="{{ $p['test'] }}({{ $c->id }})" wire:loading.attr="disabled" wire:target="{{ $p['test'] }}">
                                                        <span wire:loading.remove wire:target="{{ $p['test'] }}">@svg('heroicon-o-signal', 'w-4 h-4')</span>
                                                        <span wire:loading wire:target="{{ $p['test'] }}">@svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')</span>
                                                        <span class="hidden sm:inline">Testen</span>
                                                    </x-nx-button>
                                                @endif

                                                <x-nx-dropdown width="w-64">
                                                    @foreach ($sync as $idx => [$method, $label])
                                                        @continue($idx === 0)
                                                        <x-nx-dropdown-item wire:click="{{ $method }}({{ $c->id }})" :disabled="$isSyncing">
                                                            @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                            {{ $label }}
                                                        </x-nx-dropdown-item>
                                                    @endforeach
                                                    @if (! empty($sync) && $canTest)
                                                        <x-nx-dropdown-item wire:click="{{ $p['test'] }}({{ $c->id }})">
                                                            @svg('heroicon-o-signal', 'w-4 h-4')
                                                            Verbindung testen
                                                        </x-nx-dropdown-item>
                                                    @endif
                                                    @if (count($sync) > 1 || (! empty($sync) && $canTest))
                                                        <x-nx-dropdown-divider />
                                                    @endif

                                                    @if (isset($renew['href']))
                                                        <x-nx-dropdown-item :href="$renew['href']">
                                                            @svg('heroicon-o-key', 'w-4 h-4')
                                                            {{ $renew['label'] }}
                                                        </x-nx-dropdown-item>
                                                    @else
                                                        <x-nx-dropdown-item wire:click="{{ $renew['wire'] }}">
                                                            @svg('heroicon-o-key', 'w-4 h-4')
                                                            {{ $renew['label'] }}
                                                        </x-nx-dropdown-item>
                                                    @endif
                                                    <x-nx-dropdown-item wire:click="openShareModal({{ $c->id }})">
                                                        @svg('heroicon-o-user-group', 'w-4 h-4')
                                                        Freigaben
                                                    </x-nx-dropdown-item>
                                                    @if (! $c->is_default)
                                                        <x-nx-dropdown-item wire:click="setDefaultConnection({{ $c->id }})">
                                                            @svg('heroicon-o-star', 'w-4 h-4')
                                                            Als Standard setzen
                                                        </x-nx-dropdown-item>
                                                    @endif
                                                    <x-nx-dropdown-divider />
                                                    <x-nx-dropdown-item variant="danger" wire:click="deleteConnection({{ $c->id }})"
                                                        wire:confirm="{{ $p['label'] }}-Verbindung „{{ $c->name }}“ wirklich trennen? {{ $p['confirm'] ?? '' }}">
                                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                                        Trennen
                                                    </x-nx-dropdown-item>
                                                </x-nx-dropdown>
                                            </div>
                                        </div>
                                    @endforeach
                                </li>
                            @endforeach
                        </ul>
                    </x-nx-card>
                </section>
            @endforeach

            <div x-show="! any(@js(collect($providers)->map(fn ($p) => [mb_strtolower($p['label'] . ' ' . $p['desc'] . ' ' . $p['key']), $p['conns']->isNotEmpty()])->values()->all()))" x-cloak>
                <x-nx-empty icon="heroicon-o-magnifying-glass">Kein Anbieter passt zu Suche oder Filter.</x-nx-empty>
            </div>
        </div>

        {{-- Für mich freigegeben --}}
        @if ($sharedWithMe->isNotEmpty())
            <section id="shared" class="scroll-mt-4">
                <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)]">Für mich freigegeben</h2>
                <x-nx-card flush>
                    <ul class="divide-y divide-[color:var(--nx-line)]">
                        @foreach ($sharedWithMe as $sharedConn)
                            <li class="flex items-center gap-3 px-4 py-2.5">
                                @svg('heroicon-o-share', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm text-[color:var(--nx-text)]">{{ $sharedConn->name ?? $sharedConn->integration->name ?? '—' }}</div>
                                    <div class="text-xs text-[color:var(--nx-faint)]">
                                        {{ $sharedConn->integration->name ?? '—' }} · von {{ $sharedConn->ownerUser->name ?? $sharedConn->ownerUser->email ?? '—' }}
                                    </div>
                                </div>
                                <x-nx-badge :variant="$statusVariant($sharedConn->status)" dot>{{ $statusLabel($sharedConn->status) }}</x-nx-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-nx-card>
            </section>
        @endif

        {{-- Technische Gesamtliste: auch Verbindungen ohne eigene Katalog-Zeile, JSON-Bearbeitung --}}
        <section x-data="{ open: false }">
            <button type="button" @click="open = ! open"
                class="mb-2 flex items-center gap-1.5 px-1 text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] hover:text-[color:var(--nx-muted)]">
                <span class="inline-block transition-transform" :class="open ? 'rotate-90' : ''">@svg('heroicon-s-chevron-right', 'w-3 h-3')</span>
                Alle eigenen Verbindungen
                <span class="font-normal normal-case tracking-normal tabular-nums">{{ $connections->total() }}</span>
            </button>
            <div x-show="open" x-cloak>
                @if ($connections->count() > 0)
                    <x-nx-card flush>
                        <ul class="divide-y divide-[color:var(--nx-line)]">
                            @foreach ($connections as $conn)
                                <li wire:key="all-{{ $conn->id }}" class="flex items-center gap-3 px-4 py-2.5">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate text-sm text-[color:var(--nx-text)]">{{ $conn->name ?? $conn->integration->name ?? $conn->integration->key ?? '—' }}</span>
                                            @if ($conn->is_default)
                                                <x-nx-badge>Standard</x-nx-badge>
                                            @endif
                                        </div>
                                        <div class="text-xs text-[color:var(--nx-faint)]">
                                            {{ $conn->integration->name ?? $conn->integration->key ?? '—' }}
                                            · <span class="font-mono">{{ $conn->auth_scheme }}</span>
                                            · {{ $conn->updated_at->diffForHumans() }}
                                        </div>
                                    </div>
                                    <x-nx-badge :variant="$statusVariant($conn->status)" dot>{{ $statusLabel($conn->status) }}</x-nx-badge>
                                    <x-nx-dropdown>
                                        <x-nx-dropdown-item wire:click="openEditModal({{ $conn->id }})">
                                            @svg('heroicon-o-pencil', 'w-4 h-4')
                                            Bearbeiten (JSON)
                                        </x-nx-dropdown-item>
                                        @if ($conn->auth_scheme === 'oauth2')
                                            <x-nx-dropdown-item :href="$oauth($conn->integration->key ?? '', $conn->id)">
                                                @svg('heroicon-o-key', 'w-4 h-4')
                                                Neu verbinden
                                            </x-nx-dropdown-item>
                                        @endif
                                        <x-nx-dropdown-item wire:click="openShareModal({{ $conn->id }})">
                                            @svg('heroicon-o-user-group', 'w-4 h-4')
                                            Freigaben
                                        </x-nx-dropdown-item>
                                        @if (! $conn->is_default)
                                            <x-nx-dropdown-item wire:click="setDefaultConnection({{ $conn->id }})">
                                                @svg('heroicon-o-star', 'w-4 h-4')
                                                Als Standard setzen
                                            </x-nx-dropdown-item>
                                        @endif
                                        <x-nx-dropdown-divider />
                                        <x-nx-dropdown-item variant="danger" wire:click="deleteConnection({{ $conn->id }})" wire:confirm="Verbindung „{{ $conn->name }}“ wirklich löschen?">
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                            Löschen
                                        </x-nx-dropdown-item>
                                    </x-nx-dropdown>
                                </li>
                            @endforeach
                        </ul>
                    </x-nx-card>
                    @if ($connections->hasPages())
                        <div class="mt-3">{{ $connections->links() }}</div>
                    @endif
                @else
                    <x-nx-empty icon="heroicon-o-link">Noch keine Verbindungen.</x-nx-empty>
                @endif
            </div>
        </section>
    </x-ui-page-container>

    {{-- Ergebnis von Sync/Test: schwebend, damit es unabhängig von der Scroll-Position sichtbar ist --}}
    @if ($syncMessage || $syncError)
        <div class="fixed bottom-4 right-4 z-[90] w-[min(28rem,calc(100vw-2rem))] shadow-[var(--nx-shadow-pop)]">
            <x-nx-callout :variant="$syncError ? 'danger' : 'success'" class="border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)]">
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1 whitespace-pre-line break-words">{{ $syncError ?: $syncMessage }}</div>
                    <button type="button" title="Schließen" class="shrink-0 text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)]"
                        @click="$wire.$set('syncError', null); $wire.$set('syncMessage', null)">
                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                    </button>
                </div>
            </x-nx-callout>
        </div>
    @endif

    {{-- ===================== Modals ===================== --}}

    {{-- Manuell anlegen (JSON) --}}
    <x-nx-modal wire:model="modalShow" size="lg">
        <x-slot name="header">Verbindung manuell anlegen</x-slot>

        <div class="space-y-4">
            <x-nx-input-select name="integrationKey" label="Integration"
                :options="$integrations->map(fn ($i) => ['value' => $i->key, 'label' => $i->name . ' (' . $i->key . ')'])"
                wire:model.live="integrationKey" errorKey="integrationKey" />

            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-select name="authScheme" label="Auth-Methode"
                    :options="[['value' => 'oauth2', 'label' => 'OAuth2'], ['value' => 'api_key', 'label' => 'API Key'], ['value' => 'basic', 'label' => 'Basic (User/Pass)'], ['value' => 'bearer', 'label' => 'Bearer Token'], ['value' => 'custom', 'label' => 'Custom']]"
                    wire:model.live="authScheme" errorKey="authScheme" />
                <x-nx-input-select name="status" label="Status"
                    :options="[['value' => 'draft', 'label' => 'Entwurf'], ['value' => 'active', 'label' => 'Aktiv'], ['value' => 'disabled', 'label' => 'Deaktiviert'], ['value' => 'error', 'label' => 'Fehler']]"
                    wire:model.live="status" errorKey="status" />
            </div>

            <x-nx-input-textarea name="credentialsJson" label="Credentials (JSON, verschlüsselt gespeichert)"
                wire:model.live="credentialsJson" rows="10" errorKey="credentialsJson" class="font-mono" />

            <p class="m-0 text-xs text-[color:var(--nx-faint)]">
                Bei OAuth2 speichert der Callback die Tokens unter <code>credentials.oauth.*</code>. Für manuelle Methoden sind beliebige Keys möglich.
                Owner: {{ auth()->user()->name ?? auth()->user()->email }}
            </p>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeCreateModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Bearbeiten (JSON) --}}
    <x-nx-modal wire:model="editModalShow" size="lg">
        <x-slot name="header">Verbindung bearbeiten</x-slot>

        <div class="space-y-4">
            <x-nx-input-text name="integrationKey" label="Integration-Key" wire:model.live="integrationKey" errorKey="integrationKey" />

            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-select name="authScheme" label="Auth-Methode"
                    :options="[['value' => 'oauth2', 'label' => 'OAuth2'], ['value' => 'api_key', 'label' => 'API Key'], ['value' => 'basic', 'label' => 'Basic (User/Pass)'], ['value' => 'bearer', 'label' => 'Bearer Token'], ['value' => 'custom', 'label' => 'Custom']]"
                    wire:model.live="authScheme" errorKey="authScheme" />
                <x-nx-input-select name="status" label="Status"
                    :options="[['value' => 'draft', 'label' => 'Entwurf'], ['value' => 'active', 'label' => 'Aktiv'], ['value' => 'disabled', 'label' => 'Deaktiviert'], ['value' => 'error', 'label' => 'Fehler']]"
                    wire:model.live="status" errorKey="status" />
            </div>

            <x-nx-input-textarea name="credentialsJson" label="Credentials (JSON, verschlüsselt gespeichert)"
                wire:model.live="credentialsJson" rows="10" errorKey="credentialsJson" class="font-mono" />

            @if ($lastError)
                <x-nx-callout variant="danger" title="Letzter Fehler">
                    <span class="whitespace-pre-wrap">{{ $lastError }}</span>
                </x-nx-callout>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeEditModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Lexware --}}
    <x-nx-modal wire:model="lexwareModalShow">
        <x-slot name="header">Lexware verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="API-Token erforderlich">
                Lexware nutzt keinen OAuth-Flow. Den Token findest du in Lexoffice unter
                <span class="font-mono text-xs">Einstellungen → Erweiterungen → Public API</span>.
            </x-nx-callout>

            <x-nx-input-text name="lexwareApiToken" label="API-Token" type="password" placeholder="Dein Lexware API-Token…"
                wire:model.live="lexwareApiToken" errorKey="lexwareApiToken"
                hint="Wird verschlüsselt gespeichert und ist nur für dich sichtbar." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeLexwareModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveLexwareConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- easybill --}}
    <x-nx-modal wire:model="easybillModalShow">
        <x-slot name="header">easybill verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="API-Token erforderlich">
                easybill nutzt keinen OAuth-Flow. Den Token erzeugst du auf
                <a href="https://login.easybill.de" target="_blank" rel="noopener" class="underline">login.easybill.de</a> unter
                <span class="font-mono text-xs">Mein Konto → Einstellungen → API</span>.
            </x-nx-callout>

            <x-nx-input-text name="easybillApiToken" label="API-Token" type="password" placeholder="Dein easybill API-Token…"
                wire:model.live="easybillApiToken" errorKey="easybillApiToken"
                hint="Wird verschlüsselt gespeichert und ist nur für dich sichtbar." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeEasybillModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveEasybillConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- necta.one --}}
    <x-nx-modal wire:model="nectaModalShow">
        <x-slot name="header">necta.one verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="Host, API-Keys und (für v1) Tenant-ID">
                <p class="m-0">Eine Verbindung bedient <strong>beide</strong> necta-APIs (Header <span class="font-mono">X-Api-Key</span>):
                    die <strong>Raw-API</strong> (<span class="font-mono">/rawapi</span>, read-only) und die <strong>API v1</strong>
                    (<span class="font-mono">/api/v1/&#123;tenantId&#125;</span>, CRUD). necta stellt dafür <strong>getrennte Schlüssel</strong> aus.</p>
                <p class="m-0 mt-2"><strong>API-Key (v1)</strong>: dein User-Key aus dem necta-Profil.
                    <strong>Raw-API-Key</strong>: der Sysadmin-Key aus <span class="font-mono">Systemadmin → API-Schlüssel</span> — leer lassen,
                    wenn der v1-Key auch die Raw-API abdeckt.</p>
                <p class="m-0 mt-2"><strong>Host</strong>: nur der Root, z. B. <span class="font-mono">https://api.necta.one</span>, ohne
                    <span class="font-mono">/rawapi</span> oder <span class="font-mono">/api/v1/…</span> (wird automatisch bereinigt).</p>
            </x-nx-callout>

            <x-nx-input-text name="nectaBaseUrl" label="Host (Base-URL)" placeholder="https://api.necta.one"
                wire:model.live="nectaBaseUrl" errorKey="nectaBaseUrl" />
            <x-nx-input-text name="nectaApiKey" label="API-Key (necta.one API v1)" type="password" placeholder="Dein v1-API-Key…"
                wire:model.live="nectaApiKey" errorKey="nectaApiKey" />
            <x-nx-input-text name="nectaRawApiKey" label="Raw-API-Key (optional, Sysadmin-Key)" type="password" placeholder="Nur bei separatem Raw-API-Key…"
                wire:model.live="nectaRawApiKey" errorKey="nectaRawApiKey" />
            <x-nx-input-text name="nectaTenantId" label="Tenant-ID (optional, nur API v1)" placeholder="z. B. 42"
                wire:model.live="nectaTenantId" errorKey="nectaTenantId" />

            <p class="m-0 text-xs text-[color:var(--nx-faint)]">
                Die Keys werden verschlüsselt gespeichert und sind nur für dich sichtbar. Der Verbindungstest prüft die Raw-API (mit dem Raw-API-Key, sonst dem v1-Key).
            </p>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeNectaModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveNectaConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- DedeFleet --}}
    <x-nx-modal wire:model="dedefleetModalShow">
        <x-slot name="header">DedeFleet verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="Dauertoken erforderlich">
                Permanenter Bearer-Token aus dem DedeFleet-Portal:
                <span class="font-mono text-xs">Systemeinstellungen → Benutzer → Typ „Api Vollzugriff“ → Token „Permanent“</span>.
                Der Token wird nur <strong>einmal vollständig angezeigt</strong> — bei Verlust neu erzeugen.
            </x-nx-callout>

            <x-nx-input-text name="dedefleetApiToken" label="Dauertoken" type="password" placeholder="Dein DedeFleet Bearer-Token…"
                wire:model.live="dedefleetApiToken" errorKey="dedefleetApiToken"
                hint="Verschlüsselt gespeichert, nur für dich sichtbar. Base-URL: https://ortung.dedefleet.de/data/api/2" />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeDedefleetModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveDedefleetConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Laravel Forge --}}
    <x-nx-modal wire:model="forgeModalShow">
        <x-slot name="header">Laravel Forge verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="Persönliches API-Token erforderlich">
                Erzeugen unter <span class="font-mono text-xs">forge.laravel.com → Profil → API → Create Token</span>.
                Das Token wird nur <strong>einmal vollständig angezeigt</strong>.
            </x-nx-callout>

            <x-nx-input-text name="forgeApiToken" label="API-Token" type="password" placeholder="Dein Laravel-Forge API-Token…"
                wire:model.live="forgeApiToken" errorKey="forgeApiToken" />
            <x-nx-input-text name="forgeOrganization" label="Standard-Organisation (optional)" placeholder="z. B. meine-agentur"
                wire:model.live="forgeOrganization" errorKey="forgeOrganization"
                hint="API v2 ist organisationsbezogen (/orgs/{organisation}/…). Mit hinterlegtem Slug muss er nicht bei jedem Aufruf mitgegeben werden; der Verbindungstest zeigt alle erreichbaren Organisationen." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeForgeModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveForgeConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Hetzner Cloud --}}
    <x-nx-modal wire:model="hetznerModalShow">
        <x-slot name="header">Hetzner Cloud verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="Projekt-Token erforderlich">
                Erzeugen in der Cloud Console unter <span class="font-mono text-xs">Projekt → Security → API Tokens → Generate API token</span>.
                Ein Token gilt für <strong>genau ein Projekt</strong>. Mit <strong>Read</strong> sind nur Abfragen möglich; für Aktionen wie Neustarts braucht es <strong>Read &amp; Write</strong>.
            </x-nx-callout>

            <x-nx-input-text name="hetznerApiToken" label="API-Token" type="password" placeholder="64-stelliges Hetzner-Cloud-Token…"
                wire:model.live="hetznerApiToken" errorKey="hetznerApiToken" />
            <x-nx-input-text name="hetznerProjectLabel" label="Projektname (optional)" placeholder="z. B. Produktion"
                wire:model.live="hetznerProjectLabel" errorKey="hetznerProjectLabel"
                hint="Unterscheidet mehrere Verbindungen und wird als Name übernommen." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeHetznerModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveHetznerConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- HubSpot --}}
    <x-nx-modal wire:model="hubspotModalShow">
        <x-slot name="header">HubSpot verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="Private App Access Token erforderlich">
                <p class="m-0">Private App anlegen unter <span class="font-mono text-xs">Settings → Integrations → Private Apps</span> und das Access Token hier einfügen.</p>
                <p class="m-0 mt-2">Benötigte Scopes (jeweils <span class="font-mono text-xs">.read</span>):
                    <span class="font-mono text-xs">crm.objects.contacts, companies, deals, notes, calls, emails, meetings, tasks</span></p>
            </x-nx-callout>

            <x-nx-input-text name="hubspotApiToken" label="Private App Access Token" type="password" placeholder="pat-eu1-…"
                wire:model.live="hubspotApiToken" errorKey="hubspotApiToken"
                hint="Wird verschlüsselt gespeichert und ist nur für dich sichtbar." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeHubspotModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveHubspotConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- BuchhaltungsButler --}}
    <x-nx-modal wire:model="buchhaltungsbutlerModalShow">
        <x-slot name="header">BuchhaltungsButler verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="Drei Werte erforderlich">
                HTTP Basic Auth (API-Client + API-Secret) plus kundenspezifischer API-Key. Zu finden unter
                <span class="font-mono text-xs">Einstellungen → Schnittstellen und API-Zugang</span>: API aktivieren, dann erscheinen Client und Secret; nach Verifizierung der API-Key.
            </x-nx-callout>

            <x-nx-input-text name="buchhaltungsbutlerApiClient" label="API-Client" placeholder="z. B. ak_xxxxx"
                wire:model.live="buchhaltungsbutlerApiClient" errorKey="buchhaltungsbutlerApiClient" />
            <x-nx-input-text name="buchhaltungsbutlerApiSecret" label="API-Secret" type="password" placeholder="••••••••"
                wire:model.live="buchhaltungsbutlerApiSecret" errorKey="buchhaltungsbutlerApiSecret" />
            <x-nx-input-text name="buchhaltungsbutlerApiKey" label="API-Key (kundenspezifisch)" type="password" placeholder="••••••••"
                wire:model.live="buchhaltungsbutlerApiKey" errorKey="buchhaltungsbutlerApiKey"
                hint="Alle drei Werte werden verschlüsselt gespeichert und sind nur für dich sichtbar." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeBuchhaltungsbutlerModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveBuchhaltungsbutlerConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Moss --}}
    <x-nx-modal wire:model="mossModalShow">
        <x-slot name="header">Moss verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="Client Credentials erforderlich">
                Moss nutzt OAuth2 Client Credentials. Client ID und Secret findest du unter <span class="font-mono text-xs">getmoss.com → API Settings</span>.
            </x-nx-callout>

            <x-nx-input-text name="mossClientId" label="Client ID" placeholder="kid_…"
                wire:model.live="mossClientId" errorKey="mossClientId" />
            <x-nx-input-text name="mossClientSecret" label="Client Secret" type="password" placeholder="sk_…"
                wire:model.live="mossClientSecret" errorKey="mossClientSecret"
                hint="Wird verschlüsselt gespeichert und ist nur für dich sichtbar." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeMossModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveMossConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- DataForSEO --}}
    <x-nx-modal wire:model="dataforseoModalShow">
        <x-slot name="header">DataForSEO verbinden</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="API-Zugang erforderlich">
                DataForSEO nutzt Basic Auth. Login und Passwort findest du unter <span class="font-mono text-xs">app.dataforseo.com → API Access</span>.
            </x-nx-callout>

            <x-nx-input-text name="dataforseoLogin" label="API-Login (E-Mail)" placeholder="dein-login@example.com"
                wire:model.live="dataforseoLogin" errorKey="dataforseoLogin" />
            <x-nx-input-text name="dataforseoPassword" label="API-Passwort" type="password" placeholder="Dein DataForSEO API-Passwort…"
                wire:model.live="dataforseoPassword" errorKey="dataforseoPassword"
                hint="Wird verschlüsselt gespeichert und ist nur für dich sichtbar." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeDataforseoModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveDataforseoConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Google Search Console – Service-Account --}}
    <x-nx-modal wire:model="gscServiceAccountModalShow">
        <x-slot name="header">Google Search Console – Service-Account</x-slot>

        <div class="space-y-4">
            <x-nx-callout title="Service-Account-JSON-Key einfügen">
                Den vollständigen JSON-Key einfügen (<code>type</code> muss <code>service_account</code> sein).
                Die <code>client_email</code> muss in der Search-Console-Property als Nutzer eingetragen sein, sonst schlägt der Test mit HTTP 403 fehl.
            </x-nx-callout>

            <x-nx-input-textarea name="gscServiceAccountJson" label="Service-Account-Key (JSON)" rows="12" class="font-mono"
                placeholder='{ "type": "service_account", "project_id": "…", "private_key": "-----BEGIN PRIVATE KEY-----\n…", "client_email": "…@…iam.gserviceaccount.com" }'
                wire:model.live="gscServiceAccountJson" errorKey="gscServiceAccountJson"
                hint="Wird verschlüsselt gespeichert und ist nur für dich sichtbar." />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeGscServiceAccountModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveGscServiceAccountConnection">Verbinden</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Freigaben --}}
    <x-nx-modal wire:model="shareModalShow" size="lg">
        <x-slot name="header">Freigaben: {{ $shareConnectionName }}</x-slot>

        <div class="space-y-5">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-0.5 rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-0.5">
                    @foreach (['team' => ['Ganzes Team', 'heroicon-o-user-group'], 'user' => ['Einzelne Person', 'heroicon-o-user']] as $key => [$label, $ico])
                        <button type="button" wire:click="$set('shareType', '{{ $key }}')"
                            class="inline-flex items-center gap-1.5 rounded-[5px] px-2.5 py-1 text-sm font-medium transition-colors {{ $shareType === $key ? 'bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-sm' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                            @svg($ico, 'w-4 h-4')
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if ($shareType === 'team')
                    <x-nx-input-select name="shareTeamId" label="Team"
                        :options="$userTeams->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])"
                        nullable nullLabel="Alle Teams (Wildcard)" wire:model.live="shareTeamId" errorKey="shareType" />
                @else
                    <x-nx-input-select name="shareUserId" label="Person"
                        :options="$teamUsers->map(fn ($u) => ['value' => $u->id, 'label' => $u->name . ' (' . $u->email . ')'])"
                        wire:model.live="shareUserId" errorKey="shareType" />
                @endif

                @if ($shareConnectionHasResources && count($shareableResources) > 0)
                    <x-nx-input-select name="shareResourceId" label="Auf Ressource einschränken (optional)"
                        :options="collect($shareableResources)->map(fn ($r) => ['value' => $r['id'], 'label' => $r['label']])"
                        nullable nullLabel="Alle Ressourcen der Verbindung" wire:model.live="shareResourceId" />
                @endif

                <x-nx-button variant="primary" wire:click="addShare">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Freigabe hinzufügen
                </x-nx-button>
            </div>

            <div>
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)]">Aktive Freigaben</div>
                @if (count($sharesList) > 0)
                    <ul class="divide-y divide-[color:var(--nx-line)] rounded-[8px] border border-[color:var(--nx-line)]">
                        @foreach ($sharesList as $share)
                            @php
                                $shareIcon = match (true) {
                                    $share['team_id'] && ! $share['user_id'] => 'heroicon-o-user-group',
                                    (bool) $share['user_id'] => 'heroicon-o-user',
                                    default => 'heroicon-o-globe-alt',
                                };
                            @endphp
                            <li class="group flex items-center gap-2 px-3 py-2">
                                @svg($shareIcon, 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                <span class="min-w-0 flex-1 truncate text-sm text-[color:var(--nx-text)]">{{ $share['wildcard_description'] }}</span>
                                <button type="button" wire:click="removeShare({{ $share['id'] }})" wire:confirm="Freigabe wirklich entfernen?" title="Freigabe entfernen"
                                    class="text-[color:var(--nx-faint)] opacity-0 transition-opacity hover:text-[color:var(--nx-danger)] group-hover:opacity-100 focus:opacity-100">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-nx-empty icon="heroicon-o-user-group" class="py-6">Noch keine Freigaben.</x-nx-empty>
                @endif
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end">
                <x-nx-button variant="ghost" wire:click="closeShareModal">Schließen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
