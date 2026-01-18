<?php

use Illuminate\Support\Facades\Route;
use Platform\Integrations\Livewire\Connections\Index as ConnectionsIndex;
use Platform\Integrations\Livewire\Grants\Manage as GrantsManage;

// Diese Routes werden über ModuleRouter geladen (wenn Modul aktiv ist)
// OAuth-Routes werden direkt im ServiceProvider registriert (immer verfügbar)
Route::get('/', ConnectionsIndex::class)->name('integrations.connections.index');
Route::get('/connections/{connection}/grants', GrantsManage::class)->name('integrations.connections.grants');

