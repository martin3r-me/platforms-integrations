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

        $ownerType = (string) $request->query('owner_type', 'team'); // team|user
        $ownerId = (int) $request->query('owner_id', 0);

        if (!in_array($ownerType, ['team', 'user'], true)) {
            abort(422, 'Ungültiger owner_type');
        }

        if ($ownerType === 'user') {
            $ownerId = $user->id;
        } else {
            // Default: aktuelles Team
            $ownerId = $ownerId > 0 ? $ownerId : (int) ($user->currentTeam?->id ?? 0);
        }

        if ($ownerId <= 0) {
            abort(422, 'Owner-Kontext fehlt');
        }

        $state = $this->oauth2->newState();
        $request->session()->put('integrations.oauth2.state', $state);
        $request->session()->put('integrations.oauth2.owner_type', $ownerType);
        $request->session()->put('integrations.oauth2.owner_id', $ownerId);

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

