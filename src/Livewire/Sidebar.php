<?php

namespace Platform\Integrations\Livewire;

use Livewire\Component;
use Platform\Integrations\Models\IntegrationConnection;

class Sidebar extends Component
{
    /** Icons je Integration-Key — deckungsgleich mit dem Katalog der Verbindungsseite. */
    private const ICONS = [
        'meta' => 'globe-alt',
        'sipgate' => 'phone',
        'lexoffice' => 'calculator',
        'easybill' => 'document-text',
        'buchhaltungsbutler' => 'banknotes',
        'datev' => 'building-library',
        'moss' => 'credit-card',
        'necta' => 'cube',
        'dedefleet' => 'truck',
        'hubspot' => 'users',
        'github' => 'code-bracket',
        'forge' => 'server-stack',
        'hetzner' => 'cloud',
        'google_search_console' => 'magnifying-glass-circle',
        'dataforseo' => 'magnifying-glass',
    ];

    /**
     * Verbundene Anbieter des Users: je Integration-Key Anzahl Verbindungen
     * und wie viele davon im Fehlerzustand sind. Eine Query.
     */
    protected function connectedProviders(): array
    {
        $userId = auth()->id();
        if (! $userId) {
            return [];
        }

        return IntegrationConnection::query()
            ->with('integration:id,key,name')
            ->where('owner_user_id', $userId)
            ->get(['id', 'integration_id', 'status'])
            ->groupBy(fn ($c) => $c->integration?->key ?? '—')
            ->map(fn ($conns, $key) => [
                'key' => $key,
                'label' => $conns->first()->integration?->name ?? $key,
                'icon' => self::ICONS[$key] ?? 'link',
                'count' => $conns->count(),
                'errors' => $conns->where('status', 'error')->count(),
            ])
            ->sortBy([['errors', 'desc'], ['label', 'asc']])
            ->values()
            ->all();
    }

    protected function sharedWithMeCount(): int
    {
        $user = auth()->user();
        if (! $user) {
            return 0;
        }
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return IntegrationConnection::query()
            ->where('owner_user_id', '!=', $user->id)
            ->where('status', 'active')
            ->whereHas('shares', function ($q) use ($user, $teamIds) {
                $q->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
                    ->where(function ($q) use ($teamIds) {
                        $q->whereNull('team_id');
                        if (! empty($teamIds)) {
                            $q->orWhereIn('team_id', $teamIds);
                        }
                    });
            })
            ->count();
    }

    public function render()
    {
        $path = '/' . ltrim(request()->path(), '/');

        return view('integrations::livewire.sidebar', [
            'pages' => [
                ['route' => 'integrations.connections.index', 'label' => 'Verbindungen', 'icon' => 'link', 'active' => (bool) preg_match('#^/integrations/?$#', $path)],
                ['route' => 'integrations.sync-profiles.index', 'label' => 'Sync-Profile', 'icon' => 'arrows-right-left', 'active' => str_starts_with($path, '/integrations/sync-profiles')],
            ],
            'providers' => $this->connectedProviders(),
            'sharedCount' => $this->sharedWithMeCount(),
            'onCatalog' => (bool) preg_match('#^/integrations/?$#', $path),
        ]);
    }
}
