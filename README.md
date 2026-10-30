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

**Antwortformat:** Forge v2 antwortet im JSON:API-Stil. Die Nutzdaten liegen unter
`attributes`, nicht auf der obersten Ebene. Eine Feldauswahl lautet deshalb
`attributes.name`, nicht `name`.

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

## NinjaOne (Public API 2.0)

RMM-Verwaltung: Geräte, Monitoring, Alarme, Patch-Management und Automatisierung.

**Verbinden:** `/integrations` → „NinjaOne verbinden". In NinjaOne wird die Anwendung unter
Administration → Apps → API angelegt.

**Region wählen.** NinjaOne betreibt getrennte Instanzen, und die Region bestimmt sowohl die
API- als auch die OAuth-Endpunkte:

| Region | Host |
|---|---|
| `us` | `https://app.ninjarmm.com` |
| `eu` | `https://eu.ninjarmm.com` |
| `oc` | `https://oc.ninjarmm.com` |
| `ca` | `https://ca.ninjarmm.com` |

**Zwei Anmeldeverfahren**, je Verbindung wählbar:

- **Client Credentials** (empfohlen) — App-Typ *API Services (machine-to-machine)*. Client-ID
  und Secret liegen in der Verbindung, das Token wird selbstständig geholt und vor Ablauf
  erneuert. Kein Browser, keine Redirect-URI, und damit regionsunabhängig.
- **Browser-Autorisierung** — App-Typ *Web*. Erfordert die Redirect-URI
  `{APP_URL}/integrations/oauth2/ninja/callback` in der NinjaOne-App. Weil der `OAuth2Service`
  seine URLs global auflöst, gilt dieser Weg nur für die Region aus `NINJA_REGION`; bei
  gemischten Regionen ist Client Credentials der richtige Weg.

**Scopes:** `monitoring` (lesen), `management` (verändern), `control` (Fernsteuerung). Sie
müssen in der NinjaOne-App freigegeben *und* in der Verbindung ausgewählt sein — fehlt ein
Scope, antwortet die API mit HTTP 403.

**Paginierung:** `pageSize` (max. 1000) zusammen mit `after`, einem Cursor auf der letzten
gelieferten ID; einzelne Endpunkte nutzen stattdessen `cursor`. Nicht jede Liste ist
paginiert — der Katalog nennt je Operation die erlaubten Parameter.

**Gerätefilter:** Viele Endpunkte akzeptieren `df`, zum Beispiel `status eq APPROVED`,
`org = 5` oder `class in (WINDOWS_WORKSTATION,WINDOWS_SERVER)`.

**Tools:**

| Tool | Zweck |
|---|---|
| `integrations.ninja.overview` | Endpunkt-Katalog, Scopes und Nutzungsregeln, ohne API-Aufruf |
| `integrations.ninja.test-connection` | Token beziehen, Verbindung prüfen, Region und Scopes melden |
| `integrations.ninja.call` | Generischer Zugriff auf alle 315 Endpunkte |
| `integrations.ninja.devices.GET` | Geräte auflisten — global, detailliert oder je Organisation |
| `integrations.ninja.device.GET` | Ein Gerät mit 20 wählbaren Unterressourcen |
| `integrations.ninja.organizations.GET` | Organisationen auflisten oder einzeln abrufen |
| `integrations.ninja.alerts.GET` | Aktive Alarme, flottenweit oder je Gerät |
| `integrations.ninja.query.GET` | 24 flottenweite Auswertungen (`/v2/queries/*`) |
| `integrations.ninja.device.action` | **Schreibend:** Neustart, Wartung, Patches, Skripte |

Alle verändernden Aufrufe verlangen `"confirm": true` — auch über
`integrations.ninja.call`. Sie wirken sofort auf echte Endgeräte.

**Unbekannte Parameter werden abgelehnt,** nicht stillschweigend verworfen: NinjaOne ignoriert
sie kommentarlos und lieferte sonst ein ungefiltertes Ergebnis, das wie ein gefiltertes aussieht.

**Der Endpunkt-Katalog** in `NinjaResource` ist aus der offiziellen Spezifikation generiert
(`https://app.ninjarmm.com/apidocs-beta/NinjaRMM-API-v2.yaml`, Version 2.0.9-draft). Ändert
sich die API, wird er daraus neu erzeugt.

**ENV-Variablen** (optional, überschreiben nur die Defaults):

```env
NINJA_REGION=eu
NINJA_SCOPES=monitoring,management,control
NINJA_DEFAULT_TIMEOUT=30
NINJA_CONNECT_TIMEOUT=10
NINJA_RETRY_TIMES=2
NINJA_RETRY_SLEEP_MS=500

# Nur für die Browser-Autorisierung:
NINJA_CLIENT_ID=
NINJA_CLIENT_SECRET=
NINJA_OAUTH_REDIRECT_DOMAIN=
NINJA_AUTHORIZE_URL=https://eu.ninjarmm.com/oauth/authorize
NINJA_TOKEN_URL=https://eu.ninjarmm.com/oauth/token
```

## cisbox (Invoice API v1)

Eingangsrechnungen aus cisbox: offene Posten, Kreditoren, Lieferungen, Zahlungen und die
Buchhaltungs-Stammdaten dahinter.

**Die API ist vollständig lesend.** Alle 27 Endpunkte sind GET; es gibt keinen Aufruf, der in
cisbox etwas verändert. Deshalb kennt diese Integration auch keine Bestätigungspflicht.

**Verbinden:** `/integrations` → „cisbox verbinden". Den API-Key stellt der
cisbox-Ansprechpartner aus.

**Der Key ist privilegiert.** Er ist ein technisches Token: Er hängt an keinem Benutzerkonto
und läuft nicht durch die Rechteverwaltung von cisbox, sieht also den gesamten Datenbestand
seiner Installation. Er gilt für genau eine Installation — für mehrere wird je eine eigene
Verbindung angelegt und über `connection_id` angesprochen.

**Antwort-Hülle:** Jede Antwort ist gleich aufgebaut:

```json
{ "status": "...", "data": [ … ], "meta": { "totalItems": 0, "totalPages": 0, "currentPage": 1, "itemsPerPage": 200 } }
```

`data` ist **immer** ein Array — auch bei Einzelabrufen wie `GET /invoices/{barcodeNo}`, die
dann genau einen Eintrag enthalten. Die Tools packen das aus; im Katalog ist über `single`
vermerkt, wo das zutrifft.

**X-Request-Id:** Jede Antwort trägt diesen Header. Er wird als `request_id` durchgereicht und
steht in jeder Fehlermeldung — ohne ihn lässt sich ein Vorfall im cisbox-Support nicht in den
Server-Logs wiederfinden.

**Paginierung:** `page` (ab 1) und `itemsPerPage` (Standard 200, Maximum 2000). Die Listen-Tools
kennen `all_pages`; sie laufen dann bis zu 50 Seiten durch und melden über `meta.truncated`,
wenn dabei gekürzt wurde.

**Mehrfach-Parameter:** `status` wird bei offenen Posten und Zahlungen wiederholt gesetzt
(`status=APPROVED&status=PAID`). Der Service baut den Query-String deshalb selbst — Guzzle
würde ein Array als `status[0]=…` kodieren, was die API ignoriert. Das Ergebnis sähe dann
gefiltert aus, obwohl der Filter nie gegriffen hat.

**Tools:**

| Tool | Zweck |
|---|---|
| `integrations.cisbox.overview` | Endpunkt-Katalog, Felder der Datentypen und Nutzungsregeln, ohne API-Aufruf |
| `integrations.cisbox.test-connection` | Verbindung prüfen und Anzahl der Rechnungen melden |
| `integrations.cisbox.call` | Generischer Zugriff auf alle 27 Endpunkte |
| `integrations.cisbox.invoices.GET` | Eingangsrechnungen mit Datums-, Perioden- und Kreditorenfilter |
| `integrations.cisbox.invoice.GET` | Eine Rechnung, auf Wunsch mit Positionen und Kontierungs-Splits |
| `integrations.cisbox.invoices.pending.GET` | Offene Posten mit offenem Betrag, Fälligkeit und Skonto |
| `integrations.cisbox.invoice.pdf.GET` | Beleg-PDF — standardmäßig nur Größe, Inhalt auf Anforderung |
| `integrations.cisbox.creditors.GET` | Kreditoren, einzeln oder mit ihren Artikeln samt Preishistorie |
| `integrations.cisbox.deliveries.GET` | Lieferscheine, einzeln oder mit Positionen |
| `integrations.cisbox.payments.GET` | Zahlungen und Zahlungsvorschläge mit Status |
| `integrations.cisbox.masterdata.GET` | Sachkonten, Zuordnungen, Code 1–3, Zahlungsarten, Steuerschlüssel |

**Was vorab abgefangen wird,** statt im Fehler der API zu landen: Suchbegriffe unter vier
Zeichen (cisbox antwortet mit 422), unbekannte Enum-Werte, Parameter, die es an diesem
Endpunkt nicht gibt, und `all_pages` auf einer nicht paginierten Operation.

**Das PDF** kommt als Base64 in `data[0].fileData` — die Spezifikation deklariert dort ein
Array von Teilen, in der Praxis steht ein einzelner String. Beide Formen werden
zusammengeführt. Ausgeliefert wird der Inhalt nur mit `include_data` und nur bis 256 KB;
ein mehrere Megabyte großes Beleg-PDF gehört nicht in den Gesprächsverlauf.

**Der Endpunkt-Katalog** in `CisboxResource` ist aus der offiziellen Spezifikation generiert
(`https://cisbox-global.cisbox.com/resteasy/api-docs/invoice/v1`, Version 1.0). Neben den
Operationen enthält er die Felder aller Antwort-Typen — sie beantworten, was sich als `fields`
übergeben lässt. Ändert sich die API, wird er daraus neu erzeugt.

**ENV-Variablen** (optional, überschreiben nur die Defaults):

```env
CISBOX_API_BASE_URL=https://api.cisbox.com/invoice/v1
CISBOX_DEFAULT_TIMEOUT=30
CISBOX_CONNECT_TIMEOUT=10
CISBOX_RETRY_TIMES=2
CISBOX_RETRY_SLEEP_MS=500
```

Läuft eure Installation nicht über `api.cisbox.com`, gehört die Adresse nicht hierher, sondern
in die Verbindung: Das Feld „Abweichende Adresse" im Dialog legt sie in `credentials.base_url`
ab und gilt dann nur für diese Verbindung.

## Für aufrufenden Code

Die API-Services von Hetzner und NinjaOne bieten zwei Dinge, die längere Abläufe brauchen
(cisbox kennt davon `withObserver`, aber keinen Rate-Limit-Hinweis — die API nennt keinen):

- `withObserver(callable $observer)` liefert eine Kopie des Services, die jeden
  ausgeführten Aufruf meldet, mit Methode, Pfad, Status, Dauer und den
  Rate-Limit-Headern. Auch bei Erfolg. Ohne das käme ein Aufrufer an den
  Status-Code erfolgreicher Antworten nicht heran, weil nur die dekodierten Daten
  zurückgegeben werden. Ein Fehler im Beobachter bricht den Aufruf nie ab.
- `getRetryAfter()` auf beiden Exceptions liefert die Wartezeit einer
  Rate-Limit-Antwort als Zahl, statt sie nur in den Meldungstext zu schreiben.

Bei Hetzner ist der Header `RateLimit-Remaining` besonders nützlich: Er macht den
Budgetverbrauch sichtbar, bevor das Limit von 3600 Aufrufen je Stunde erreicht ist.

