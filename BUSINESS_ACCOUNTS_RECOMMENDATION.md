# Empfehlung: Meta Business Accounts speichern?

## Aktuelle Situation

Die Meta Business Accounts werden aktuell nur als Zwischenschritt verwendet, um an die WhatsApp Business Accounts zu kommen. Sie werden nicht in der Datenbank gespeichert.

## Pro Business Accounts speichern

1. **Bessere Hierarchie-Abbildung**
   - User → Meta Connection → Business Account → WhatsApp Account
   - Klare Struktur und Beziehungen

2. **Zukünftige Features**
   - Business-spezifische Permissions/Rollen
   - Business-spezifische Einstellungen
   - Business-spezifische Reports/Analytics
   - Multi-Business-Management

3. **Bessere Übersicht**
   - User kann sehen, welche Businesses er verwaltet
   - Einfacher zu verstehen, welche WhatsApp Accounts zu welchem Business gehören

4. **API-Effizienz**
   - Business-Daten müssen nicht jedes Mal neu abgerufen werden
   - Caching möglich

## Contra Business Accounts speichern

1. **Aktuell nicht benötigt**
   - Werden nur als Zwischenschritt verwendet
   - Keine direkte Verwendung in der App

2. **Zusätzliche Komplexität**
   - Mehr Models, Migrations, Services
   - Mehr Wartungsaufwand

3. **Meta API liefert sie bereits**
   - Wenn man sie braucht, kann man sie über die API abrufen
   - Keine Notwendigkeit, sie zu cachen

## Empfehlung

**JA, aber optional implementieren:**

1. **Migration und Model erstellen** - für zukünftige Features
2. **Beim Sync speichern** - wenn Business Accounts abgerufen werden
3. **Foreign Key zu IntegrationConnection** - klare Beziehung
4. **Foreign Key von WhatsApp Accounts** - WhatsApp Accounts gehören zu einem Business Account

### Implementierung

- Migration: `create_integrations_meta_business_accounts_table.php`
- Model: `IntegrationsMetaBusinessAccount`
- Service: Business Accounts beim WhatsApp-Sync speichern
- Beziehungen:
  - `IntegrationConnection` → `hasMany(IntegrationsMetaBusinessAccount)`
  - `IntegrationsMetaBusinessAccount` → `hasMany(IntegrationsWhatsAppAccount)`
  - `IntegrationsWhatsAppAccount` → `belongsTo(IntegrationsMetaBusinessAccount)`

### Vorteile

- Vorbereitet für zukünftige Features
- Bessere Datenstruktur
- Einfacher zu erweitern
- Klare Hierarchie

### Nachteile

- Zusätzliche Tabelle
- Mehr Code zu warten
- Aktuell noch nicht direkt genutzt

## Entscheidung

**Empfehlung: Implementieren** - Die Vorteile überwiegen, besonders für zukünftige Features. Die Implementierung ist einfach und die Struktur wird klarer.
