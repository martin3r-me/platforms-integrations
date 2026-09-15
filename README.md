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

## Laravel Forge (API v2)

Server-, Site- und Deployment-Verwaltung über die Laravel Forge API v2.

**Verbinden:** `/integrations` → „Laravel Forge verbinden“. Benötigt ein persönliches
API-Token aus dem Forge-Profil (https://forge.laravel.com/profile/api). Das Token wird
verschlüsselt in `credentials.api_key` abgelegt.

**Organisationen:** Die v2-API ist organisationsbezogen — nahezu alle Ressourcen liegen
unter `/orgs/{organization}/…`. Im Verbindungsdialog kann ein Standard-Slug hinterlegt
werden (`credentials.organization`); er muss dann nicht bei jedem Tool-Aufruf mitgegeben
werden. Der Verbindungstest listet alle erreichbaren Organisationen auf.

**Paginierung:** cursorbasiert über `page[size]` und `page[cursor]`. Die Antwort liefert
`meta.next_cursor`, der als `cursor` in den Folgeaufruf geht.

**Tools:**

| Tool | Zweck |
|---|---|
| `integrations.forge.overview` | Endpunkt-Katalog und Nutzungsregeln, ohne API-Aufruf |
| `integrations.forge.test-connection` | Verbindung prüfen, Organisationen auflisten |
| `integrations.forge.call` | Generischer Zugriff auf alle 160 Endpunkte |
| `integrations.forge.organizations.GET` | Organisationen auflisten |
| `integrations.forge.servers.GET` | Server einer Organisation auflisten |
| `integrations.forge.server.GET` | Einzelnen Server abrufen |
| `integrations.forge.sites.GET` | Sites organisationsweit oder je Server |
| `integrations.forge.site.GET` | Einzelne Site abrufen |
| `integrations.forge.deployments.GET` | Deployment-Historie einer Site |
| `integrations.forge.deployment-log.GET` | Deployment-Log bzw. aktueller Status |
| `integrations.forge.events.GET` | Events als Audit-Trail |
| `integrations.forge.site.deploy` | **Schreibend:** Deployment auslösen |
| `integrations.forge.server.action` | **Schreibend:** `reboot` / `power-cycle` |
| `integrations.forge.service.action` | **Schreibend:** Dienst neu starten oder stoppen |

Alle schreibenden Tools verlangen `"confirm": true`.

**ENV-Variablen** (optional, überschreiben nur die Defaults):

```env
FORGE_API_BASE_URL=https://forge.laravel.com/api
FORGE_DEFAULT_TIMEOUT=30
FORGE_CONNECT_TIMEOUT=10
```

## Hetzner Cloud (API v1)

Verwaltung von Servern, Volumes, Netzwerken, Load Balancern, Firewalls und DNS-Zonen.

**Verbinden:** `/integrations` → „Hetzner Cloud verbinden“. Das Token wird in der Cloud
Console unter Projekt → Security → API Tokens erzeugt.

**Ein Token = ein Projekt.** Die API kennt keinen Projekt-Parameter. Für mehrere Projekte
wird je Projekt eine eigene Verbindung angelegt und über `connection_id` angesprochen. Ein
Token mit reinen Leserechten beantwortet schreibende Aufrufe mit HTTP 403.

**Asynchrone Aktionen:** Jeder verändernde Aufruf liefert ein `action`-Objekt mit Status
`running`, `success` oder `error`. Die Antwort bedeutet also noch nicht, dass die Aktion
abgeschlossen ist. Den Fortschritt liefert `integrations.hetzner.actions.GET`, wahlweise
mit `"wait": true`.

**Paginierung:** seitenbasiert über `page` und `per_page` (Maximum 50).

**Tools:**

| Tool | Zweck |
|---|---|
| `integrations.hetzner.overview` | Endpunkt-Katalog und Nutzungsregeln, ohne API-Aufruf |
| `integrations.hetzner.test-connection` | Verbindung prüfen, Server-Anzahl ermitteln |
| `integrations.hetzner.call` | Generischer Zugriff auf alle 152 Endpunkte |
| `integrations.hetzner.servers.GET` | Server des Projekts auflisten |
| `integrations.hetzner.server.GET` | Einzelnen Server abrufen |
| `integrations.hetzner.server.metrics.GET` | CPU-, Disk- und Netzwerk-Metriken |
| `integrations.hetzner.list.GET` | Sammel-Tool für 16 weitere Ressourcen |
| `integrations.hetzner.zone.rrsets.GET` | DNS-Einträge einer Zone |
| `integrations.hetzner.pricing.GET` | Preisliste des Projekts |
| `integrations.hetzner.actions.GET` | Status asynchroner Aktionen, optional mit Warten |
| `integrations.hetzner.server.action` | **Schreibend:** Server-Aktionen ausführen |

Die zerstörenden Aktionen `poweroff`, `reset` und `rebuild` sowie jedes `DELETE` über
`integrations.hetzner.call` verlangen `"confirm": true`.

**ENV-Variablen** (optional, überschreiben nur die Defaults):

```env
HETZNER_API_BASE_URL=https://api.hetzner.cloud/v1
HETZNER_DEFAULT_TIMEOUT=30
HETZNER_CONNECT_TIMEOUT=10
HETZNER_ACTION_MAX_WAIT=60
HETZNER_ACTION_POLL_INTERVAL=2
```
