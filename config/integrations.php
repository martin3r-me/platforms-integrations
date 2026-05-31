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

            /**
             * RingCentral OAuth2 Konfiguration
             *
             * RingCentral OAuth2 Authorization Code Flow.
             * Read-Only-Zugriff auf Call Logs, Extensions und Account-Informationen.
             *
             * Benötigte ENV-Variablen:
             * - RINGCENTRAL_CLIENT_ID: OAuth2 Client ID
             * - RINGCENTRAL_CLIENT_SECRET: OAuth2 Client Secret
             * - RINGCENTRAL_OAUTH_REDIRECT_DOMAIN: Optional - Domain für Redirect URI
             *
             * @see https://developers.ringcentral.com/api-reference
             */
            'ringcentral' => [
                'authorize_url' => 'https://platform.ringcentral.com/restapi/oauth/authorize',
                'token_url' => 'https://platform.ringcentral.com/restapi/oauth/token',
                'client_id' => env('RINGCENTRAL_CLIENT_ID'),
                'client_secret' => env('RINGCENTRAL_CLIENT_SECRET'),
                'redirect_domain' => env('RINGCENTRAL_OAUTH_REDIRECT_DOMAIN'),
                'scopes' => [
                    'ReadCallLog',
                    'ReadAccounts',
                ],
            ],

            /**
             * Google Search Console OAuth2 Konfiguration
             *
             * Google OAuth2 Authorization Code Flow mit offline access.
             * Read-Only-Zugriff auf Sites, Search Analytics, Sitemaps und URL Inspection.
             *
             * Benötigte ENV-Variablen:
             * - GOOGLE_CLIENT_ID: Google OAuth2 Client ID (geteilt mit anderen Google-Integrationen)
             * - GOOGLE_CLIENT_SECRET: Google OAuth2 Client Secret
             * - GOOGLE_OAUTH_REDIRECT_DOMAIN: Optional - Domain für Redirect URI
             *
             * @see https://developers.google.com/webmaster-tools/v3/how-tos/authorizing
             */
            'google_search_console' => [
                'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                'redirect_domain' => env('GOOGLE_OAUTH_REDIRECT_DOMAIN'),
                'scopes' => [
                    'https://www.googleapis.com/auth/webmasters.readonly',
                ],
                // Google-spezifisch: access_type=offline für Refresh-Token, prompt=consent erzwingt Consent-Screen
                'extra_params' => [
                    'access_type' => 'offline',
                    'prompt' => 'consent',
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
     * BuchhaltungsButler API Konfiguration
     *
     * BuchhaltungsButler Buchhaltungs-Software.
     * Alle Endpoints sind POST mit JSON-Body (auch Read-Operationen).
     * HTTP Basic Auth (api_client + api_secret) + api_key im Body.
     *
     * API-Credentials erhält man unter:
     * BuchhaltungsButler → Einstellungen → Schnittstellen und API-Zugang
     *
     * Rate-Limit: 100 Requests pro Kunde pro Minute.
     *
     * @see https://app.buchhaltungsbutler.de/docs/api/v1/
     */
    'buchhaltungsbutler' => [
        'api_base_url' => env('BUCHHALTUNGSBUTLER_API_BASE_URL', 'https://webapp.buchhaltungsbutler.de/api/v1'),

        // Timeout-Konfiguration
        'timeout' => [
            'default' => (int) env('BUCHHALTUNGSBUTLER_DEFAULT_TIMEOUT', 30),
            'connect' => (int) env('BUCHHALTUNGSBUTLER_CONNECT_TIMEOUT', 10),
        ],
    ],

    /**
     * Moss API Konfiguration
     *
     * Moss Spend-Management Platform.
     * Verwendet OAuth2 Client Credentials (client_id + client_secret → Bearer Token).
     * Auth-Scheme in DB ist 'basic' (client_id = login, client_secret = password).
     *
     * API-Credentials erhält man unter:
     * https://getmoss.com → API Settings
     *
     * ENV-Variablen:
     * - MOSS_API_BASE_URL: Base URL für API-Aufrufe (Standard: https://public-api.getmoss.com)
     *
     * Dokumentation:
     * @see https://public-api.getmoss.com
     */
    'moss' => [
        'api_base_url' => env('MOSS_API_BASE_URL', 'https://public-api.getmoss.com'),
        'token_url' => '/oauth2/token',

        // Timeout-Konfiguration
        'timeout' => [
            'default' => (int) env('MOSS_DEFAULT_TIMEOUT', 30),
            'connect' => (int) env('MOSS_CONNECT_TIMEOUT', 10),
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
    /**
     * Google Search Console API Konfiguration
     *
     * Zwei Base-URLs:
     * - webmasters/v3: Sites, Search Analytics, Sitemaps
     * - searchconsole/v1: URL Inspection
     *
     * ENV-Variablen:
     * - GOOGLE_CLIENT_ID: OAuth2 Client ID (gemeinsam für alle Google-Integrationen)
     * - GOOGLE_CLIENT_SECRET: OAuth2 Client Secret (gemeinsam für alle Google-Integrationen)
     * - GOOGLE_OAUTH_REDIRECT_DOMAIN: Optional - Domain für OAuth Callback
     * - GOOGLE_SEARCH_CONSOLE_API_BASE_URL: Base URL für Sites/Analytics/Sitemaps
     * - GOOGLE_SEARCH_CONSOLE_INSPECTION_BASE_URL: Base URL für URL Inspection
     *
     * @see https://developers.google.com/webmaster-tools/v3/
     */
    'google_search_console' => [
        'api_base_url' => env('GOOGLE_SEARCH_CONSOLE_API_BASE_URL', 'https://www.googleapis.com/webmasters/v3'),
        'inspection_base_url' => env('GOOGLE_SEARCH_CONSOLE_INSPECTION_BASE_URL', 'https://searchconsole.googleapis.com/v1'),

        // Timeout-Konfiguration
        'timeout' => [
            'default' => 30,
            'connect' => 10,
        ],
    ],

    /**
     * RingCentral API Konfiguration
     *
     * RingCentral Cloud-Telefonie Platform.
     * Verwendet OAuth2 Authorization Code Flow.
     *
     * ENV-Variablen:
     * - RINGCENTRAL_CLIENT_ID: OAuth2 Client ID (siehe oauth2.providers.ringcentral)
     * - RINGCENTRAL_CLIENT_SECRET: OAuth2 Client Secret
     * - RINGCENTRAL_OAUTH_REDIRECT_DOMAIN: Domain für OAuth Callback
     * - RINGCENTRAL_API_BASE_URL: Base URL für API-Aufrufe
     *
     * @see https://developers.ringcentral.com/api-reference
     */
    'ringcentral' => [
        'api_base_url' => env('RINGCENTRAL_API_BASE_URL', 'https://platform.ringcentral.com/restapi/v1.0'),

        // Timeout-Konfiguration
        'timeout' => [
            'default' => (int) env('RINGCENTRAL_DEFAULT_TIMEOUT', 30),
            'connect' => (int) env('RINGCENTRAL_CONNECT_TIMEOUT', 10),
        ],
    ],

    /**
     * Plausible Analytics API Konfiguration
     *
     * Plausible Analytics (Self-Hosted oder Cloud).
     * Verwendet API-Key (Bearer Token) Authentifizierung.
     * Base-URL ist pro Connection konfigurierbar für Self-Hosted-Instanzen.
     *
     * ENV-Variablen:
     * - PLAUSIBLE_API_BASE_URL: Standard-Base-URL (kann pro Connection überschrieben werden)
     * - PLAUSIBLE_DEFAULT_TIMEOUT: Request-Timeout in Sekunden
     * - PLAUSIBLE_CONNECT_TIMEOUT: Connect-Timeout in Sekunden
     *
     * @see https://plausible.io/docs/stats-api
     */
    'plausible' => [
        'api_base_url' => env('PLAUSIBLE_API_BASE_URL', 'https://plausible.io'),

        // Timeout-Konfiguration
        'timeout' => [
            'default' => (int) env('PLAUSIBLE_DEFAULT_TIMEOUT', 30),
            'connect' => (int) env('PLAUSIBLE_CONNECT_TIMEOUT', 10),
        ],
    ],

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

