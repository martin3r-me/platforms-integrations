<div>
    <div x-show="!collapsed" class="p-3 text-sm italic text-[color:var(--nx-muted)] uppercase border-b border-[color:var(--nx-line)]">
        Integrationen
    </div>

    {{-- Ausgeklappt --}}
    <div x-show="!collapsed">
        <x-ui-sidebar-list>
            @foreach ($pages as $page)
                <x-ui-sidebar-item :href="route($page['route'])" :active="$page['active']">
                    @svg('heroicon-o-' . $page['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                    <span class="text-sm truncate">{{ $page['label'] }}</span>
                </x-ui-sidebar-item>
            @endforeach
        </x-ui-sidebar-list>

        @php
            $errorTotal = collect($providers)->sum('errors');
        @endphp
        @if (! empty($providers))
            <x-ui-sidebar-list :label="'Verbunden' . ($errorTotal > 0 ? ' · ' . $errorTotal . ' mit Fehler' : '')">
                @foreach ($providers as $p)
                    {{-- Sprung zur Anbieter-Zeile: auf der Katalogseite scrollen, sonst hinnavigieren --}}
                    <x-ui-sidebar-item type="button"
                        x-on:click="const el = document.getElementById('provider-{{ $p['key'] }}'); el ? el.scrollIntoView({ behavior: 'smooth', block: 'start' }) : (window.location.href = '{{ route('integrations.connections.index') }}#provider-{{ $p['key'] }}')">
                        @svg('heroicon-o-' . $p['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                        <span class="text-sm font-normal truncate">{{ $p['label'] }}</span>
                        <x-slot name="trailing">
                            @if ($p['errors'] > 0)
                                <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-danger)]" title="{{ $p['errors'] }} mit Fehler"></span>
                            @endif
                            @if ($p['count'] > 1)
                                <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $p['count'] }}</span>
                            @endif
                        </x-slot>
                    </x-ui-sidebar-item>
                @endforeach
            </x-ui-sidebar-list>
        @else
            <div class="px-3 py-3 text-xs text-[color:var(--nx-faint)] border-b border-[color:var(--nx-line)]">
                Noch keine Verbindungen.
            </div>
        @endif

        @if ($sharedCount > 0)
            <x-ui-sidebar-list>
                <x-ui-sidebar-item type="button"
                    x-on:click="const el = document.getElementById('shared'); el ? el.scrollIntoView({ behavior: 'smooth', block: 'start' }) : (window.location.href = '{{ route('integrations.connections.index') }}#shared')">
                    @svg('heroicon-o-share', 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                    <span class="text-sm font-normal truncate">Für mich freigegeben</span>
                    <x-slot name="trailing">
                        <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $sharedCount }}</span>
                    </x-slot>
                </x-ui-sidebar-item>
            </x-ui-sidebar-list>
        @endif
    </div>

    {{-- Eingeklappt: nur Icons --}}
    <div x-show="collapsed" x-cloak>
        <div class="px-2 py-2 border-b border-[color:var(--nx-line)] flex flex-col gap-1">
            @foreach ($pages as $page)
                <a href="{{ route($page['route']) }}" wire:navigate title="{{ $page['label'] }}"
                   @class([
                       'flex items-center justify-center p-2 rounded-md transition',
                       'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' => $page['active'],
                       'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' => ! $page['active'],
                   ])>
                    @svg('heroicon-o-' . $page['icon'], 'w-5 h-5')
                </a>
            @endforeach
        </div>
        @if (! empty($providers))
            <div class="px-2 py-2 border-b border-[color:var(--nx-line)] flex flex-col gap-1">
                @foreach ($providers as $p)
                    <a href="{{ route('integrations.connections.index') }}#provider-{{ $p['key'] }}" title="{{ $p['label'] }}"
                       class="relative flex items-center justify-center p-2 rounded-md transition text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                        @svg('heroicon-o-' . $p['icon'], 'w-5 h-5')
                        @if ($p['errors'] > 0)
                            <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-[color:var(--nx-danger)]"></span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
