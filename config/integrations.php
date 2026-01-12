<?php

return [
    'name' => 'Integrations',
    'description' => 'External integrations (OAuth + manual credentials) with grants per team/user',
    'version' => '1.0.0',

    'routing' => [
        'prefix' => 'integrations',
        'middleware' => ['web', 'auth'],
    ],

    'guard' => 'web',

    'navigation' => [
        'integrations' => [
            'title' => 'Integrationen',
            'icon' => 'heroicon-o-link',
            'route' => 'integrations.connections.index',
            'order' => 35,
        ],
    ],

    'sidebar' => [
        'integrations' => [
            'title' => 'Integrationen',
            'icon' => 'heroicon-o-link',
            'route' => 'integrations.connections.index',
            'order' => 35,
        ],
    ],

    /**
     * OAuth2 Provider-Konfiguration (global pro Integration-Key; Tokens sind pro Connection).
     *
     * Beispiel:
     * 'providers' => [
     *   'lexoffice' => [
     *     'authorize_url' => 'https://app.lexoffice.de/oauth2/authorize',
     *     'token_url' => 'https://app.lexoffice.de/oauth2/token',
     *     'client_id' => env('INTEGRATIONS_LEXOFFICE_CLIENT_ID'),
     *     'client_secret' => env('INTEGRATIONS_LEXOFFICE_CLIENT_SECRET'),
     *     'scopes' => ['profile', 'contacts', 'invoices'],
     *   ],
     * ],
     */
    'oauth2' => [
        'providers' => [
            // 'lexoffice' => [ ... ]
        ],
    ],
];

