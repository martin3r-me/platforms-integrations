<?php

return [
    'name' => 'Integrations',
    'description' => 'External integrations (OAuth + manual credentials) with grants per user',
    'version' => '1.0.0',

    'scope_type' => 'parent',

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
                    'instagram_content_publish',
                    'instagram_manage_comments',
                    'instagram_manage_insights',
                    'instagram_manage_messages',
                    'instagram_shopping_tag_products',
                    
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

            /**
             * Sipgate OAuth2 Konfiguration
             *
             * Sipgate verwendet OAuth2 für die Authentifizierung.
             * Die Credentials werden über das Sipgate Console Portal erstellt:
             * https://console.sipgate.com/applications
             *
             * Benötigte ENV-Variablen:
             * - SIPGATE_CLIENT_ID: OAuth2 Client ID
             * - SIPGATE_CLIENT_SECRET: OAuth2 Client Secret
             * - SIPGATE_OAUTH_REDIRECT_DOMAIN: Optional - Domain für Redirect URI
             * - SIPGATE_WEBHOOK_SECRET: Secret für Webhook-Signatur-Verifizierung
             *
             * Scopes:
             * - all: Vollzugriff auf alle Funktionen (empfohlen für Shop Full Access)
             *
             * Dokumentation:
             * @see https://developer.sipgate.io/authentication/oauth2
             */
            'sipgate' => [
                'authorize_url' => 'https://api.sipgate.com/login/oauth/authorize',
                'token_url' => 'https://api.sipgate.com/login/oauth/token',
                'revoke_url' => 'https://api.sipgate.com/login/oauth/revoke',
                'client_id' => env('SIPGATE_CLIENT_ID'),
                'client_secret' => env('SIPGATE_CLIENT_SECRET'),
                'redirect_domain' => env('SIPGATE_OAUTH_REDIRECT_DOMAIN'), // Optional: Nur Domain, URI wird automatisch generiert
                'scopes' => [
                    // Vollzugriff (empfohlen für Shop Full Access)
                    'all',

                    // Alternativ granulare Scopes:
                    // 'account:read',      // Account-Informationen lesen
                    // 'balance:read',      // Guthaben lesen
                    // 'users:read',        // Benutzer lesen
                    // 'phonelines:read',   // Telefonleitungen lesen
                    // 'calls:write',       // Anrufe initiieren/beenden
                    // 'history:read',      // Anrufhistorie lesen
                    // 'history:write',     // Anrufhistorie bearbeiten
                    // 'sms:write',         // SMS senden
                    // 'fax:write',         // Fax senden
                    // 'contacts:read',     // Kontakte lesen
                    // 'contacts:write',    // Kontakte bearbeiten
                    // 'voicemails:read',   // Voicemails lesen
                    // 'settings:read',     // Einstellungen lesen
                    // 'settings:write',    // Einstellungen bearbeiten
                ],
            ],

            // Lexware/Lexoffice hat KEIN OAuth! Verwendet API-Key Authentifizierung
            // Der API-Token wird manuell vom Benutzer eingegeben
        ],
    ],

    /**
     * Lexware/Lexoffice API Konfiguration
     *
     * WICHTIG: Lexware/Lexoffice verwendet KEIN OAuth-Verfahren!
     * Benutzer müssen ihren API-Token manuell eingeben.
     * Der Token wird in credentials.api_key gespeichert.
     *
     * API-Token erhält man unter:
     * Lexoffice → Einstellungen → Erweiterungen → Public API
     */
    'lexware' => [
        'api_base_url' => env('LEXWARE_API_BASE_URL', 'https://api.lexoffice.io/v1'),
    ],

    /**
     * DataForSEO API Konfiguration
     *
     * DataForSEO Keywords Data API v3 für SEO-Keyword-Daten.
     * Verwendet Basic Auth (Login + Password).
     *
     * API-Credentials erhält man unter:
     * https://app.dataforseo.com/api-access
     *
     * ENV-Variablen:
     * - DATAFORSEO_API_BASE_URL: Base URL für API-Aufrufe (Standard: https://api.dataforseo.com)
     * - DATAFORSEO_DEFAULT_LOCATION_CODE: Standard-Location (Standard: 2276 = Germany)
     * - DATAFORSEO_DEFAULT_LANGUAGE_CODE: Standard-Sprache (Standard: 1001 = German)
     *
     * Dokumentation:
     * @see https://docs.dataforseo.com/v3/keywords_data/google_ads/
     */
    'dataforseo' => [
        'api_base_url' => env('DATAFORSEO_API_BASE_URL', 'https://api.dataforseo.com'),

        // Default Location & Language
        'default_location_code' => (int) env('DATAFORSEO_DEFAULT_LOCATION_CODE', 2276), // Germany
        'default_language_code' => (int) env('DATAFORSEO_DEFAULT_LANGUAGE_CODE', 1001), // German

        // Timeout-Konfiguration
        'timeout' => [
            'default' => (int) env('DATAFORSEO_DEFAULT_TIMEOUT', 30),
            'connect' => (int) env('DATAFORSEO_CONNECT_TIMEOUT', 10),
        ],
    ],

    /**
     * Sipgate API Konfiguration
     *
     * Sipgate VoIP & Telefonie Integration für:
     * - Click-to-Call
     * - SMS senden/empfangen
     * - Fax senden/empfangen
     * - Anrufhistorie
     * - Webhooks für Echtzeit-Benachrichtigungen
     *
     * Dokumentation:
     * @see https://developer.sipgate.io
     *
     * ENV-Variablen:
     * - SIPGATE_CLIENT_ID: OAuth2 Client ID (siehe oauth2.providers.sipgate)
     * - SIPGATE_CLIENT_SECRET: OAuth2 Client Secret
     * - SIPGATE_OAUTH_REDIRECT_DOMAIN: Domain für OAuth Callback
     * - SIPGATE_WEBHOOK_SECRET: Secret für Webhook-Signatur-Verifizierung
     * - SIPGATE_API_BASE_URL: Base URL für API-Aufrufe (Standard: https://api.sipgate.com/v2)
     *
     * Webhook-Konfiguration:
     * - SIPGATE_WEBHOOK_ENABLED: Webhooks aktivieren (true/false)
     * - SIPGATE_WEBHOOK_SIGNATURE_ENABLED: Signatur-Verifizierung aktivieren
     *
     * Circuit Breaker:
     * - SIPGATE_CIRCUIT_FAILURE_THRESHOLD: Anzahl Fehler bis Circuit öffnet (Standard: 5)
     * - SIPGATE_CIRCUIT_RECOVERY_TIME: Sekunden bis Recovery (Standard: 60)
     *
     * Retry/Backoff:
     * - SIPGATE_MAX_RETRIES: Maximale Retry-Versuche (Standard: 3)
     * - SIPGATE_RETRY_INITIAL_DELAY: Initiale Wartezeit in ms (Standard: 1000)
     * - SIPGATE_RETRY_MAX_DELAY: Maximale Wartezeit in ms (Standard: 10000)
     */
    'sipgate' => [
        'api_base_url' => env('SIPGATE_API_BASE_URL', 'https://api.sipgate.com/v2'),

        // Webhook-Konfiguration
        'webhook' => [
            'enabled' => env('SIPGATE_WEBHOOK_ENABLED', true),
            'secret' => env('SIPGATE_WEBHOOK_SECRET'),
            'signature_enabled' => env('SIPGATE_WEBHOOK_SIGNATURE_ENABLED', true),
            // Callback-URL wird automatisch generiert: {APP_URL}/api/integrations/sipgate/webhook
        ],

        // Circuit Breaker Konfiguration
        'circuit_breaker' => [
            'failure_threshold' => (int) env('SIPGATE_CIRCUIT_FAILURE_THRESHOLD', 5),
            'recovery_time' => (int) env('SIPGATE_CIRCUIT_RECOVERY_TIME', 60),
        ],

        // Retry/Backoff Konfiguration
        'retry' => [
            'max_retries' => (int) env('SIPGATE_MAX_RETRIES', 3),
            'initial_delay' => (int) env('SIPGATE_RETRY_INITIAL_DELAY', 1000),
            'max_delay' => (int) env('SIPGATE_RETRY_MAX_DELAY', 10000),
        ],

        // Timeout-Konfiguration
        'timeout' => [
            'default' => (int) env('SIPGATE_DEFAULT_TIMEOUT', 30),
            'connect' => (int) env('SIPGATE_CONNECT_TIMEOUT', 10),
        ],
    ],
];

