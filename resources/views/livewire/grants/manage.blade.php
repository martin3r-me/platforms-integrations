<div class="h-full overflow-y-auto p-6">
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Freigaben</h1>
                <p class="text-gray-600">
                    {{ $connection->integration->name ?? $connection->integration->key ?? 'Integration' }}
                    – Connection #{{ $connection->id }}
                </p>
            </div>
            <div class="d-flex items-center gap-2">
                <x-ui-button variant="secondary" :href="route('integrations.connections.index')">
                    Zurück
                </x-ui-button>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-check-circle', 'w-5 h-5 text-green-600')
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-2 gap-4 items-end">
            <x-ui-input-select
                name="grantUserId"
                label="User"
                :options="$users->map(fn($u) => ['value' => $u->id, 'label' => ($u->name ?? $u->email ?? ('#'.$u->id))])"
                optionValue="value"
                optionLabel="label"
                :nullable="false"
                wire:model.live="grantUserId"
                :errorKey="'grantUserId'"
            />

            <div class="d-flex justify-end">
                <x-ui-button variant="primary" wire:click="addGrant">
                    Freigabe hinzufügen
                </x-ui-button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if($grants->count() > 0)
            <x-ui-table>
                <x-ui-table-header>
                    <x-ui-table-header-cell>User</x-ui-table-header-cell>
                    <x-ui-table-header-cell align="right">Aktionen</x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @foreach($grants as $grant)
                        <x-ui-table-row>
                            <x-ui-table-cell class="font-medium">
                                {{ $grant->granteeUser->name ?? $grant->granteeUser->email ?? 'User #' . $grant->grantee_user_id }}
                            </x-ui-table-cell>
                            <x-ui-table-cell align="right">
                                <x-ui-button size="sm" variant="danger" wire:click="removeGrant({{ $grant->id }})">
                                    Entfernen
                                </x-ui-button>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforeach
                </x-ui-table-body>
            </x-ui-table>
        @else
            <div class="text-center py-12 text-gray-600">
                <x-heroicon-o-lock-closed class="w-12 h-12 text-gray-400 mx-auto mb-3"/>
                <div class="text-lg font-medium">Keine Freigaben</div>
                <div>Füge Grants hinzu, um anderen Usern Zugriff zu erlauben.</div>
            </div>
        @endif
    </div>
</div>
