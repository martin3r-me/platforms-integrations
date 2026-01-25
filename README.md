# Integrations Service

## ENV-Variablen für Meta OAuth

Folgende ENV-Variablen müssen in der `.env` Datei gesetzt sein:

```env
# Meta OAuth Configuration
META_CLIENT_ID=deine_meta_app_id
META_CLIENT_SECRET=dein_meta_app_secret
META_API_VERSION=21.0
META_OAUTH_REDIRECT_DOMAIN=https://deine-domain.de  # Optional: Falls die Domain von APP_URL abweicht
```

## Meta App Konfiguration

In der Meta App (https://developers.facebook.com/apps/) muss folgende Redirect URI eingetragen sein:

```
https://deine-domain.de/integrations/oauth2/meta/callback
```

## ENV-Variablen für GitHub OAuth

Folgende ENV-Variablen müssen in der `.env` Datei gesetzt sein:

```env
# GitHub OAuth Configuration
GITHUB_CLIENT_ID=deine_github_client_id
GITHUB_CLIENT_SECRET=dein_github_client_secret
GITHUB_OAUTH_REDIRECT_DOMAIN=https://deine-domain.de  # Optional: Falls die Domain von APP_URL abweicht
```

## GitHub App Konfiguration

In der GitHub OAuth App (https://github.com/settings/developers) muss folgende Authorization callback URL eingetragen sein:

```
https://deine-domain.de/integrations/oauth2/github/callback
```

## OAuth Flow

1. User klickt auf "Mit Meta/GitHub verbinden" auf `/integrations`
2. Weiterleitung zu `/integrations/oauth2/{integration}/start`
3. Controller generiert OAuth-URL und leitet zu Provider weiter
4. User autorisiert die App
5. Provider leitet zurück zu `/integrations/oauth2/{integration}/callback`
6. Token wird in `integration_connections` gespeichert
7. User wird zurück zu `/integrations` geleitet

## Commands

```bash
# Facebook Pages synchronisieren
php artisan integrations:sync-facebook-pages --user-id=1

# Instagram Accounts synchronisieren
php artisan integrations:sync-instagram-accounts --user-id=1

# WhatsApp Accounts synchronisieren
php artisan integrations:sync-whatsapp-accounts --user-id=1

# GitHub Repositories synchronisieren
php artisan integrations:sync-github-repositories --user-id=1
```

## Scripts

### Repository Ticket Checker

Das Script `/Users/martin3r/Platforms/opt/agent/check_repository_tickets.sh` prüft GitHub Repositories aus whitelabelten Ordnern auf offene Tickets.

**Verwendung:**

```bash
# Mit Umgebungsvariablen
export APP_URL="https://deine-platform.de"
export API_TOKEN="dein-api-token"
export PLATFORMS_DIR="/Users/martin3r/Platforms"  # Optional, Standard: /Users/martin3r/Platforms
/Users/martin3r/Platforms/opt/agent/check_repository_tickets.sh

# Oder direkt
APP_URL="https://deine-platform.de" API_TOKEN="dein-token" /Users/martin3r/Platforms/opt/agent/check_repository_tickets.sh
```

**API Token erstellen:**

```bash
php artisan api:token:create --email=your@email.com --name='Script Token' --show
```

**Konfiguration:**

- `APP_URL`: Base URL der Platform (Standard: `http://localhost:8000`)
- `API_TOKEN`: API Token für Authentifizierung
- `WHITELABEL_FOLDERS`: Liste der zu prüfenden Ordner (aktuell: `core`)

Das Script:
1. Liest GitHub Repository-Informationen aus den whitelabelten Ordnern
2. Fragt den API-Endpunkt `/api/helpdesk/tickets/github-repository/next-open` ab
3. Gibt gefundene Tickets aus: "JA TICKET: [Titel]"
