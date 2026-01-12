<?php

use Illuminate\Support\Facades\Route;
use Platform\Integrations\Livewire\Connections\Index as ConnectionsIndex;
use Platform\Integrations\Livewire\Grants\Manage as GrantsManage;
use Platform\Integrations\Http\Controllers\OAuth2Controller;

Route::get('/', ConnectionsIndex::class)->name('integrations.connections.index');
Route::get('/connections/{connection}/grants', GrantsManage::class)->name('integrations.connections.grants');

// OAuth2 (generic)
Route::get('/oauth2/{integrationKey}/start', [OAuth2Controller::class, 'start'])
    ->name('integrations.oauth2.start');
Route::get('/oauth2/{integrationKey}/callback', [OAuth2Controller::class, 'callback'])
    ->name('integrations.oauth2.callback');

