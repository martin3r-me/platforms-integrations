<?php

/**
 * Google Search Console – Service-Account-Konfiguration
 *
 * Diese Config gehört zum Service-Account-basierten GoogleSearchConsoleService
 * (services/integrations/src/Services/GoogleSearchConsoleService.php), der die
 * offizielle google/apiclient-Bibliothek verwendet.
 *
 * Sie ist bewusst getrennt von der OAuth2-basierten `integrations.google_search_console`-
 * Config (die den User-OAuth-Flow der GoogleSearchConsoleApiService abdeckt).
 *
 * Authentifizierung:
 * Ein Google-Service-Account (JSON-Key) mit Lesezugriff auf die Property. Der Key
 * wird NICHT im Repo abgelegt, sondern verschlüsselt in der DB gespeichert
 * (IntegrationConnection.credentials via EncryptedJson-Cast bzw. Team-Setting) und
 * dem Service zur Laufzeit über withCredentials() injiziert. Die hier hinterlegten
 * Fallbacks (ENV-Pfad / ENV-JSON) dienen nur lokaler Entwicklung.
 *
 * @see https://developers.google.com/webmaster-tools/v1/prereqs
 * @see https://developers.google.com/webmaster-tools/v1/searchanalytics/query
 * @see https://developers.google.com/webmaster-tools/v1/urlInspection.index/inspect
 */

return [

    // Application-Name, der bei API-Calls mitgesendet wird
    'application_name' => env('GSC_APPLICATION_NAME', 'Platform Integrations'),

    /**
     * Fallback-Quelle für den Service-Account-JSON-Key (nur lokale Entwicklung).
     *
     * Produktiv werden die Credentials verschlüsselt in der DB gehalten und via
     * GoogleSearchConsoleService::withCredentials() injiziert. Ist beides gesetzt,
     * gewinnt withCredentials().
     *
     * - credentials_path: absoluter Pfad zu einer service-account.json
     * - credentials_json: kompletter JSON-Key als String (z.B. Base64-frei in ENV)
     */
    'credentials_path' => env('GSC_SERVICE_ACCOUNT_PATH'),
    'credentials_json' => env('GSC_SERVICE_ACCOUNT_JSON'),

    /**
     * Optionale Default-Property (z.B. "sc-domain:example.com" oder "https://example.com/").
     * Produktiv kommt die Property-URL als Team-Setting pro Team – dieser Wert ist nur Fallback.
     */
    'default_property' => env('GSC_DEFAULT_PROPERTY'),

    // OAuth-Scopes des Service-Accounts (Read-Only genügt für Analytics + Inspection)
    'scopes' => [
        'https://www.googleapis.com/auth/webmasters.readonly',
    ],

    // Timeouts (Sekunden) für die zugrundeliegenden HTTP-Requests
    'timeout' => [
        'default' => (int) env('GSC_DEFAULT_TIMEOUT', 30),
        'connect' => (int) env('GSC_CONNECT_TIMEOUT', 10),
    ],

    /**
     * Search-Analytics-Pagination.
     *
     * Die API liefert max. 25.000 Rows pro Request. row_limit ist die Batch-Größe
     * pro Seite; der Service paginiert automatisch über startRow, bis alle Rows
     * geladen sind oder max_rows erreicht ist (max_rows = 0 → unbegrenzt).
     */
    'pagination' => [
        'row_limit' => (int) env('GSC_ROW_LIMIT', 25000),
        'max_rows'  => (int) env('GSC_MAX_ROWS', 0),
    ],

    /**
     * Rate-Limits laut Google-Doku. Der Service drosselt Requests so, dass diese
     * Grenzen eingehalten werden (min. Abstand zwischen Queries) und macht bei
     * 429/5xx zusätzlich Retry mit exponentiellem Backoff.
     *
     * @see https://developers.google.com/webmaster-tools/limits
     */
    'rate_limits' => [
        // Search Console (Search Analytics, Sites): 1.200 Queries/Minute
        'queries_per_minute' => (int) env('GSC_QUERIES_PER_MINUTE', 1200),

        // URL Inspection: 2.000/Tag, 600/Minute
        'inspection_per_minute' => (int) env('GSC_INSPECTION_PER_MINUTE', 600),
        'inspection_per_day'    => (int) env('GSC_INSPECTION_PER_DAY', 2000),
    ],

    /**
     * Retry-Verhalten (exponentielles Backoff mit Jitter).
     *
     * Wird angewendet auf Quota-/Rate-Limit-Fehler (429) und transiente Server-/
     * Netzwerkfehler (5xx, Verbindungsabbrüche). Auth-Fehler (401/403) werden NICHT
     * wiederholt, sondern sofort als Re-Auth-nötig gemeldet.
     */
    'retry' => [
        'max_attempts'   => (int) env('GSC_RETRY_MAX_ATTEMPTS', 3),
        'base_delay_ms'  => (int) env('GSC_RETRY_BASE_DELAY_MS', 1000),
        'multiplier'     => (float) env('GSC_RETRY_MULTIPLIER', 2.0),
        'max_delay_ms'   => (int) env('GSC_RETRY_MAX_DELAY_MS', 32000),
    ],

];
