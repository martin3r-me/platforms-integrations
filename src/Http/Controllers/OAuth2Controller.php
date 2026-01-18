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

        try {
            $authorizeUrl = $this->oauth2->buildAuthorizeUrl($integrationKey, $state);
            
            \Log::info('OAuth2 Start', [
                'integration_key' => $integrationKey,
                'user_id' => $ownerUserId,
                'authorize_url' => $authorizeUrl,
                'redirect_uri' => $this->oauth2->redirectUri($integrationKey),
            ]);

            return redirect()->away($authorizeUrl);
        } catch (\Exception $e) {
            \Log::error('OAuth2 Start Error', [
                'integration_key' => $integrationKey,
                'user_id' => $ownerUserId,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('integrations.connections.index')
                ->with('error', 'Fehler beim Starten des OAuth-Flows: ' . $e->getMessage());
        }
    }

    public function callback(Request $request, string $integrationKey)
    {
        $connection = $this->oauth2->handleCallback($request, $integrationKey);

        return redirect()
            ->route('integrations.connections.index')
            ->with('status', "OAuth Verbindung für '{$integrationKey}' gespeichert (Connection #{$connection->id}).");
    }
}
