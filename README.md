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

## OAuth Flow

1. User klickt auf "Mit Meta verbinden" auf `/integrations`
2. Weiterleitung zu `/integrations/oauth2/meta/start`
3. Controller generiert OAuth-URL und leitet zu Facebook weiter
4. User autorisiert die App
5. Facebook leitet zurück zu `/integrations/oauth2/meta/callback`
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
```
