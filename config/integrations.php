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
                // authorize_url wird dynamisch im Service gebaut (mit api_version)
                'authorize_url_template' => 'https://www.facebook.com/v{version}/dialog/oauth',
                'token_url_template' => 'https://graph.facebook.com/v{version}/oauth/access_token',
                'client_id' => env('META_CLIENT_ID'),
                'client_secret' => env('META_CLIENT_SECRET'),
                'redirect_domain' => env('META_OAUTH_REDIRECT_DOMAIN'), // Optional: Nur Domain, URI wird automatisch generiert
                'scopes' => [
                    // Facebook Pages
                    'pages_show_list',
                    'pages_read_engagement',
                    'pages_read_user_content',
                    'pages_manage_metadata',
                    'pages_manage_posts', // Für Posts (wie im glowkit-master)
                    
                    // Instagram
                    'instagram_basic',
                    'instagram_manage_comments',
                    'instagram_manage_insights',
                    'instagram_manage_messages', // Für Instagram Messages (wie im glowkit-master)
                    'instagram_shopping_tag_products', // Für Instagram Shopping (wie im glowkit-master)
                    
                    // WhatsApp
                    'whatsapp_business_management',
                    'whatsapp_business_messaging',
                    
                    // Business Management
                    'business_management', // Benötigt für WhatsApp Business Accounts über Business Accounts
                    
                    // Optional: Ads (falls benötigt)
                    // 'ads_management', // Nur wenn Werbekonten benötigt werden
                ],
            ],
            'github' => [
                'authorize_url' => 'https://github.com/login/oauth/authorize',
                'token_url' => 'https://github.com/login/oauth/access_token',
                'client_id' => env('GITHUB_CLIENT_ID'),
                'client_secret' => env('GITHUB_CLIENT_SECRET'),
                'redirect_domain' => env('GITHUB_OAUTH_REDIRECT_DOMAIN'), // Optional: Nur Domain, URI wird automatisch generiert
                'scopes' => [
                    'repo', // Zugriff auf Repositories (read/write)
                    'read:user', // Benutzerinformationen lesen
                ],
            ],
            // 'lexoffice' => [ ... ]
        ],
    ],
];

