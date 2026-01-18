<?php

namespace Platform\Integrations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Integrations\Services\OAuth2Service;

class OAuth2Controller extends Controller
{
    public function __construct(
        protected OAuth2Service $oauth2,
    ) {}

    public function start(Request $request, string $integrationKey)
    {
        $user = $request->user();
        $ownerUserId = $user->id;

        $state = $this->oauth2->newState();
        $request->session()->put('integrations.oauth2.state', $state);
        $request->session()->put('integrations.oauth2.owner_user_id', $ownerUserId);

        return redirect()->away($this->oauth2->buildAuthorizeUrl($integrationKey, $state));
    }

    public function callback(Request $request, string $integrationKey)
    {
        $connection = $this->oauth2->handleCallback($request, $integrationKey);

        return redirect()
            ->route('integrations.connections.index')
            ->with('status', "OAuth Verbindung für '{$integrationKey}' gespeichert (Connection #{$connection->id}).");
    }
}
