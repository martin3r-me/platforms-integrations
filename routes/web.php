<?php

use Illuminate\Support\Facades\Route;
use Platform\Integrations\Livewire\Connections\Index as ConnectionsIndex;
use Platform\Integrations\Livewire\SyncProfiles\Index as SyncProfilesIndex;
use Platform\Integrations\Livewire\SyncProfiles\Mappings as SyncProfilesMappings;

// Diese Routes werden über ModuleRouter geladen (wenn Modul aktiv ist)
// OAuth-Routes werden direkt im ServiceProvider registriert (immer verfügbar)
Route::get('/', ConnectionsIndex::class)->name('integrations.connections.index');

// Lexoffice ↔ DATEV Sync-Profile (Bridges) + Konten-Mappings
Route::get('/sync-profiles', SyncProfilesIndex::class)->name('integrations.sync-profiles.index');
Route::get('/sync-profiles/{bridge}/mappings', SyncProfilesMappings::class)
    ->name('integrations.sync-profiles.mappings');
