<?php

use Illuminate\Support\Facades\Route;
use Platform\Integrations\Livewire\Connections\Index as ConnectionsIndex;

// Diese Routes werden über ModuleRouter geladen (wenn Modul aktiv ist)
// OAuth-Routes werden direkt im ServiceProvider registriert (immer verfügbar)
Route::get('/', ConnectionsIndex::class)->name('integrations.connections.index');
