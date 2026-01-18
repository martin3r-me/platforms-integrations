<?php

return [
    'name' => 'Integrations',
    'description' => 'External integrations (OAuth + manual credentials) with grants per user',
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
            'meta' => [
                'api_version' => env('META_API_VERSION', '21.0'),
                'authorize_url' => 'https://www.facebook.com/v' . env('META_API_VERSION', '21.0') . '/dialog/oauth',
                'token_url' => 'https://graph.facebook.com/v' . env('META_API_VERSION', '21.0') . '/oauth/access_token',
                'client_id' => env('META_CLIENT_ID'),
                'client_secret' => env('META_CLIENT_SECRET'),
                'scopes' => [
                    'pages_show_list',
                    'pages_read_engagement',
                    'pages_read_user_content',
                    'pages_manage_metadata',
                    'instagram_basic',
                    'instagram_manage_comments',
                    'instagram_manage_insights',
                    'whatsapp_business_management',
                    'whatsapp_business_messaging',
                ],
            ],
            // 'lexoffice' => [ ... ]
        ],
    ],
];

