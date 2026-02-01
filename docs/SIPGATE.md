# Sipgate Integration

## Übersicht

Die Sipgate Integration ermöglicht die Anbindung an die Sipgate VoIP-Plattform für:

- **Click-to-Call**: Anrufe direkt aus der Anwendung initiieren
- **SMS**: Nachrichten senden und empfangen
- **Fax**: Faxe digital senden und empfangen
- **Voicemail**: Voicemail-Nachrichten abrufen
- **Anrufhistorie**: Alle Anrufe, SMS und Faxe einsehen
- **Webhooks**: Echtzeit-Benachrichtigungen bei eingehenden Anrufen

## Voraussetzungen

- Sipgate Account (Basic, Team oder Trunk)
- Sipgate API-Zugang (OAuth2 App registrieren)
- PHP 8.1+
- Laravel 10+

## Installation

### 1. Umgebungsvariablen konfigurieren

Fügen Sie folgende Variablen zur `.env` Datei hinzu:

```env
# OAuth2 Credentials (erforderlich)
SIPGATE_CLIENT_ID=your-client-id
SIPGATE_CLIENT_SECRET=your-client-secret
SIPGATE_OAUTH_REDIRECT_DOMAIN=https://your-app.com

# Webhook-Konfiguration (optional)
SIPGATE_WEBHOOK_SECRET=your-webhook-secret
SIPGATE_WEBHOOK_ENABLED=true
SIPGATE_WEBHOOK_SIGNATURE_ENABLED=true

# API-Konfiguration (optional)
SIPGATE_API_BASE_URL=https://api.sipgate.com/v2
SIPGATE_DEFAULT_TIMEOUT=30
SIPGATE_CONNECT_TIMEOUT=10

# Circuit Breaker (optional)
SIPGATE_CIRCUIT_FAILURE_THRESHOLD=5
SIPGATE_CIRCUIT_RECOVERY_TIME=60

# Retry-Konfiguration (optional)
SIPGATE_MAX_RETRIES=3
SIPGATE_RETRY_INITIAL_DELAY=1000
SIPGATE_RETRY_MAX_DELAY=10000
```

### 2. Sipgate OAuth App erstellen

1. Gehen Sie zu [Sipgate Console](https://console.sipgate.com/applications)
2. Erstellen Sie eine neue OAuth2 Application
3. Konfigurieren Sie die Redirect URI: `https://your-app.com/integrations/oauth2/sipgate/callback`
4. Notieren Sie Client ID und Client Secret

### 3. Migrationen ausführen

```bash
php artisan migrate
```

### 4. Integration seeden (optional)

```bash
php artisan integrations:seed
```

## OAuth2 Scopes

### Empfohlener Scope (Vollzugriff)

```php
'scopes' => ['all']
```

Dieser Scope gewährt Zugriff auf alle Sipgate-Funktionen.

### Granulare Scopes (Alternative)

Wenn nur bestimmte Funktionen benötigt werden:

| Scope | Beschreibung |
|-------|--------------|
| `account:read` | Account-Informationen lesen |
| `balance:read` | Guthaben lesen |
| `users:read` | Benutzer lesen |
| `phonelines:read` | Telefonleitungen lesen |
| `calls:write` | Anrufe initiieren/beenden |
| `history:read` | Anrufhistorie lesen |
| `history:write` | Anrufhistorie bearbeiten |
| `sms:write` | SMS senden |
| `fax:write` | Fax senden |
| `contacts:read` | Kontakte lesen |
| `contacts:write` | Kontakte bearbeiten |
| `voicemails:read` | Voicemails lesen |
| `settings:read` | Einstellungen lesen |
| `settings:write` | Einstellungen bearbeiten |

## API-Endpunkte

### Connection Management

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/api/integrations/sipgate/test` | Verbindung testen |
| DELETE | `/api/integrations/sipgate/disconnect` | Verbindung trennen |

### Account & User

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/api/integrations/sipgate/userinfo` | Benutzerinformationen |
| GET | `/api/integrations/sipgate/account` | Account-Details |
| GET | `/api/integrations/sipgate/balance` | Guthaben abrufen |
| GET | `/api/integrations/sipgate/users` | Alle Users (Admin) |

### Telefonie

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/api/integrations/sipgate/numbers` | Telefonnummern abrufen |
| GET | `/api/integrations/sipgate/devices` | Geräte abrufen |
| POST | `/api/integrations/sipgate/calls` | Anruf initiieren (Click-to-Call) |
| DELETE | `/api/integrations/sipgate/calls/{id}` | Anruf beenden |

### Historie

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/api/integrations/sipgate/history` | Anrufhistorie |
| GET | `/api/integrations/sipgate/history/{id}` | Einzelner Eintrag |
| PUT | `/api/integrations/sipgate/history/{id}/archive` | Archivieren |
| DELETE | `/api/integrations/sipgate/history/{id}` | Löschen |

### SMS

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| POST | `/api/integrations/sipgate/sms` | SMS senden |
| GET | `/api/integrations/sipgate/sms/extensions` | SMS-Erweiterungen |

### Fax

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| POST | `/api/integrations/sipgate/fax` | Fax senden |
| GET | `/api/integrations/sipgate/faxlines` | Faxlines abrufen |

### Voicemail

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/api/integrations/sipgate/voicemails` | Voicemails abrufen |

### Kontakte

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/api/integrations/sipgate/contacts` | Kontakte auflisten |
| GET | `/api/integrations/sipgate/contacts/{id}` | Kontakt abrufen |
| POST | `/api/integrations/sipgate/contacts` | Kontakt erstellen |
| PUT | `/api/integrations/sipgate/contacts/{id}` | Kontakt aktualisieren |
| DELETE | `/api/integrations/sipgate/contacts/{id}` | Kontakt löschen |

### Webhooks

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/api/integrations/sipgate/webhooks` | Webhook-Settings abrufen |
| PUT | `/api/integrations/sipgate/webhooks` | Webhooks konfigurieren |
| DELETE | `/api/integrations/sipgate/webhooks` | Webhooks löschen |
| POST | `/api/integrations/sipgate/webhook` | Webhook-Empfänger (nicht auth.) |

### Health & Metrics

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/api/integrations/sipgate/health` | Health-Check |
| GET | `/api/integrations/sipgate/metrics/tokens` | Token-Metriken |
| GET | `/api/integrations/sipgate/tokens/history` | Token-History (Audit) |

## Beispiele

### Anruf initiieren (Click-to-Call)

```php
use Platform\Integrations\Services\SipgateApiService;

$sipgateService = app(SipgateApiService::class);

// Anruf starten
$result = $sipgateService->initiateCall(
    $user,
    'e0',  // Device-ID (z.B. 'e0' für erstes Telefon)
    '+49301234567',  // Zielnummer
    '+49301234568'   // Optional: Angezeigte Caller-ID
);
```

### SMS senden

```php
$result = $sipgateService->sendSms(
    $user,
    's0',  // SMS-Extension-ID
    '+49171234567',  // Empfänger
    'Hallo Welt!'    // Nachricht
);
```

### Fax senden

```php
$pdfContent = file_get_contents('/path/to/document.pdf');
$base64Pdf = base64_encode($pdfContent);

$result = $sipgateService->sendFax(
    $user,
    'f0',  // Faxline-ID
    '+49301234567',  // Empfänger
    $base64Pdf,
    'Dokument.pdf'   // Optional: Dateiname
);
```

### Anrufhistorie abrufen

```php
use Platform\Integrations\DTOs\Sipgate\SipgateHistoryFilter;

// Nur verpasste Anrufe der letzten Woche
$filter = SipgateHistoryFilter::missedCalls()->toArray();
$history = $sipgateService->getHistory($user, $filter);

// Mit Datumsfilter
$filter = SipgateHistoryFilter::forPeriod(
    now()->subDays(7),
    now()
)->toArray();
```

## Webhooks

### Webhook-Konfiguration

Sipgate kann bei Anrufereignissen HTTP-Callbacks senden:

```php
$sipgateService->setWebhooks($user, [
    'incomingUrl' => 'https://your-app.com/api/integrations/sipgate/webhook',
    'outgoingUrl' => 'https://your-app.com/api/integrations/sipgate/webhook',
    'log' => true,
]);
```

### Webhook-Events

| Event | Beschreibung |
|-------|--------------|
| `newCall` | Neuer eingehender/ausgehender Anruf |
| `onAnswer` | Anruf wurde angenommen |
| `onHangup` | Anruf wurde beendet |
| `dtmf` | DTMF-Tasten wurden gedrückt |

### Signatur-Verifizierung

Webhooks werden mit HMAC-SHA256 signiert. Die Signatur wird im Header `X-Sipgate-Signature` übertragen.

```env
SIPGATE_WEBHOOK_SECRET=your-secret
SIPGATE_WEBHOOK_SIGNATURE_ENABLED=true
```

### Idempotency

Webhooks werden automatisch dedupliziert. Duplikate werden erkannt und ignoriert.

## Scheduled Commands

### Token-Refresh

Erneuert ablaufende Tokens proaktiv:

```php
// In Kernel.php
$schedule->command('integrations:sipgate-refresh-tokens')
    ->everyThirtyMinutes();
```

### Account-Synchronisierung

Synchronisiert Account-Daten:

```php
$schedule->command('integrations:sync-sipgate-accounts')
    ->hourly();
```

### Webhook-Cleanup

Bereinigt alte Webhook-Events und verarbeitet fehlgeschlagene erneut:

```php
// Alte Events löschen (täglich)
$schedule->command('integrations:sipgate-webhook-cleanup --days=30')
    ->daily();

// Fehlgeschlagene Events retrien (alle 5 Minuten)
$schedule->command('integrations:sipgate-webhook-cleanup --retry-failed')
    ->everyFiveMinutes();
```

## Fehlerbehandlung

### SipgateApiException

Alle API-Fehler werden als `SipgateApiException` geworfen:

```php
use Platform\Integrations\Exceptions\SipgateApiException;

try {
    $result = $sipgateService->initiateCall($user, 'e0', '+4930123456');
} catch (SipgateApiException $e) {
    if ($e->isRateLimited()) {
        // Rate-Limit überschritten, warten
        sleep($e->getRetryAfter() ?? 60);
    } elseif ($e->shouldRefreshToken()) {
        // Token erneuern
    } elseif ($e->isServerError()) {
        // Später erneut versuchen
    }

    // Fehlermeldung für User
    $userMessage = $e->getUserMessage();
}
```

### Circuit Breaker

Bei wiederholten Fehlern wird der Circuit Breaker aktiviert:

```php
$status = $sipgateService->getCircuitBreakerStatus();
// ['status' => 'open', 'failures' => 5, 'recovery_at' => '...']

// Manuell zurücksetzen
$sipgateService->resetCircuitBreaker();
```

## Datenbank-Schema

### integrations_sipgate_accounts

Speichert synchronisierte Account-Informationen.

### integrations_sipgate_tokens

Audit-Log für Token-Events (erstellt, erneuert, widerrufen, Fehler).

### integrations_sipgate_webhooks

Registrierte Webhook-Konfigurationen.

### integrations_sipgate_webhook_events

Empfangene Webhook-Events mit Verarbeitungsstatus.

## Token-Audit

Alle Token-Events werden für Compliance und Debugging protokolliert:

```php
$integrationService = app(SipgateIntegrationService::class);

// Token-History abrufen
$history = $integrationService->getTokenHistory($connection, 50);

// Token-Metriken
$metrics = $integrationService->getTokenMetrics($connection);
// [
//     'total_events' => 42,
//     'refresh_count' => 35,
//     'error_count' => 2,
//     'last_refresh' => Carbon,
//     'current_token_age_hours' => 0.5,
//     'is_healthy' => true,
// ]
```

## Troubleshooting

### Token-Refresh schlägt fehl

1. Prüfen Sie ob `SIPGATE_CLIENT_SECRET` korrekt ist
2. Prüfen Sie ob der Refresh-Token noch gültig ist
3. Verbinden Sie erneut über OAuth

### Webhook kommt nicht an

1. Prüfen Sie ob die Webhook-URL öffentlich erreichbar ist
2. Prüfen Sie ob HTTPS verwendet wird (erforderlich)
3. Prüfen Sie die Firewall-Regeln
4. Prüfen Sie die Webhook-Logs

### Rate-Limit Fehler

1. Reduzieren Sie die Anfrage-Frequenz
2. Implementieren Sie Caching
3. Verwenden Sie Batch-Operationen wo möglich

## Sicherheitshinweise

- **Tokens werden verschlüsselt** in der Datenbank gespeichert (APP_KEY)
- **Webhook-Signatur** sollte immer verifiziert werden
- **Token-Revoke** wird beim Disconnect durchgeführt
- **Audit-Trail** für alle Token-Events

## API-Dokumentation

- [Sipgate API Dokumentation](https://developer.sipgate.io)
- [OAuth2 Guide](https://developer.sipgate.io/authentication/oauth2)
- [Push API (Webhooks)](https://developer.sipgate.io/push-api)
