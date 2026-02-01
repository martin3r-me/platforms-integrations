<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\LexwareApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der Lexware API
 *
 * Dieser Service bietet einen zentralen Zugriffspunkt für alle Lexware API Endpunkte.
 * Der API-Token wird aus der IntegrationConnection Tabelle geholt (nicht aus ENV/CONFIG).
 *
 * HTTP Status Codes gemäß Lexware API Dokumentation:
 * @see https://developers.lexware.io/docs/#http-status-codes
 *
 * 2xx - Erfolg:
 * - 200 OK: Anfrage erfolgreich
 * - 201 Created: Ressource erstellt
 * - 202 Accepted: Verarbeitung läuft
 * - 204 No Content: Keine Daten
 *
 * 4xx - Client-Fehler:
 * - 400 Bad Request: Ungültige Parameter
 * - 401 Unauthorized: Token ungültig
 * - 402 Payment Required: Kostenpflichtig
 * - 403 Forbidden: Keine Berechtigung
 * - 404 Not Found: Ressource nicht gefunden
 * - 405 Method Not Allowed
 * - 406 Not Acceptable
 * - 409 Conflict
 * - 415 Unsupported Media Type
 * - 429 Too Many Requests: Rate-Limit
 *
 * 5xx - Server-Fehler:
 * - 500 Internal Server Error
 * - 503 Service Unavailable
 */
class LexwareApiService
{
    protected const BASE_URL = 'https://api.lexoffice.io/v1';

    protected LexwareIntegrationService $integrationService;

    public function __construct(LexwareIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    // =========================================================================
    // KONTAKTE (CONTACTS)
    // =========================================================================

    /**
     * Kontakte abrufen (paginiert)
     *
     * Ruft eine Liste von Kontakten aus der Lexware API ab.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     * Kontakte können Kunden (customer), Lieferanten (vendor) oder beides sein.
     *
     * @see https://developers.lexoffice.io/docs/#contacts-endpoint-retrieve-a-list-of-contacts
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *       "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "version": 1,
     *       "roles": {
     *         "customer": {
     *           "number": 10001
     *         }
     *       },
     *       "company": {
     *         "name": "Muster GmbH",
     *         "taxNumber": "DE123456789",
     *         "allowTaxFreeInvoices": false
     *       },
     *       "addresses": {
     *         "billing": [
     *           {
     *             "street": "Musterstraße 1",
     *             "zip": "12345",
     *             "city": "Musterstadt",
     *             "countryCode": "DE"
     *           }
     *         ]
     *       },
     *       "emailAddresses": {
     *         "business": ["kontakt@muster.de"]
     *       },
     *       "phoneNumbers": {
     *         "business": ["+49 123 456789"]
     *       },
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 5,
     *   "totalElements": 120,
     *   "numberOfElements": 25,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Kontakten
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getContacts(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/contacts', [
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelnen Kontakt abrufen
     *
     * Ruft einen einzelnen Kontakt anhand seiner ID aus der Lexware API ab.
     * Gibt alle Details des Kontakts zurück, inklusive Adressen, E-Mails und Telefonnummern.
     *
     * @see https://developers.lexoffice.io/docs/#contacts-endpoint-retrieve-a-contact
     *
     * Beispiel-Response:
     * {
     *   "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "version": 1,
     *   "roles": {
     *     "customer": {
     *       "number": 10001
     *     },
     *     "vendor": {
     *       "number": 70001
     *     }
     *   },
     *   "company": {
     *     "name": "Muster GmbH",
     *     "taxNumber": "DE123456789",
     *     "vatRegistrationId": "DE123456789",
     *     "allowTaxFreeInvoices": false,
     *     "contactPersons": [
     *       {
     *         "salutation": "Herr",
     *         "firstName": "Max",
     *         "lastName": "Mustermann",
     *         "primary": true,
     *         "emailAddress": "max.mustermann@muster.de",
     *         "phoneNumber": "+49 123 456789"
     *       }
     *     ]
     *   },
     *   "addresses": {
     *     "billing": [
     *       {
     *         "supplement": "Gebäude A",
     *         "street": "Musterstraße 1",
     *         "zip": "12345",
     *         "city": "Musterstadt",
     *         "countryCode": "DE"
     *       }
     *     ],
     *     "shipping": [
     *       {
     *         "street": "Lieferstraße 5",
     *         "zip": "12345",
     *         "city": "Musterstadt",
     *         "countryCode": "DE"
     *       }
     *     ]
     *   },
     *   "xpiHeadquarter": null,
     *   "emailAddresses": {
     *     "business": ["kontakt@muster.de"],
     *     "office": ["buero@muster.de"],
     *     "private": [],
     *     "other": []
     *   },
     *   "phoneNumbers": {
     *     "business": ["+49 123 456789"],
     *     "office": [],
     *     "mobile": ["+49 170 1234567"],
     *     "private": [],
     *     "fax": ["+49 123 456780"],
     *     "other": []
     *   },
     *   "note": "Wichtiger Kunde seit 2020",
     *   "archived": false
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $contactId Die UUID des Kontakts
     * @return array Kontaktdaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getContact(User $user, string $contactId): array
    {
        return $this->get($user, "/contacts/{$contactId}");
    }

    /**
     * Kontakt erstellen
     *
     * Erstellt einen neuen Kontakt in der Lexware API.
     * Ein Kontakt kann entweder eine Person oder ein Unternehmen sein.
     * Die Rolle (customer/vendor) bestimmt, ob der Kontakt als Kunde oder Lieferant geführt wird.
     *
     * @see https://developers.lexoffice.io/docs/#contacts-endpoint-create-a-contact
     *
     * Beispiel-Request (Unternehmen als Kunde):
     * {
     *   "version": 0,
     *   "roles": {
     *     "customer": {}
     *   },
     *   "company": {
     *     "name": "Neue Firma GmbH",
     *     "taxNumber": "DE987654321",
     *     "allowTaxFreeInvoices": false,
     *     "contactPersons": [
     *       {
     *         "salutation": "Frau",
     *         "firstName": "Anna",
     *         "lastName": "Schmidt",
     *         "primary": true,
     *         "emailAddress": "anna.schmidt@neuefirma.de",
     *         "phoneNumber": "+49 987 654321"
     *       }
     *     ]
     *   },
     *   "addresses": {
     *     "billing": [
     *       {
     *         "street": "Neuestraße 10",
     *         "zip": "54321",
     *         "city": "Neustadt",
     *         "countryCode": "DE"
     *       }
     *     ]
     *   },
     *   "emailAddresses": {
     *     "business": ["info@neuefirma.de"]
     *   },
     *   "phoneNumbers": {
     *     "business": ["+49 987 654321"]
     *   },
     *   "note": "Neuer Kunde, gewonnen über Messe"
     * }
     *
     * Beispiel-Request (Person als Kunde):
     * {
     *   "version": 0,
     *   "roles": {
     *     "customer": {}
     *   },
     *   "person": {
     *     "salutation": "Herr",
     *     "firstName": "Peter",
     *     "lastName": "Müller"
     *   },
     *   "addresses": {
     *     "billing": [
     *       {
     *         "street": "Privatweg 5",
     *         "zip": "11111",
     *         "city": "Heimatstadt",
     *         "countryCode": "DE"
     *       }
     *     ]
     *   },
     *   "emailAddresses": {
     *     "private": ["peter.mueller@email.de"]
     *   }
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *   "resourceUri": "https://api.lexoffice.io/v1/contacts/66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *   "createdDate": "2024-01-17T09:00:00.000+01:00",
     *   "updatedDate": "2024-01-17T09:00:00.000+01:00",
     *   "version": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Kontaktdaten (roles erforderlich, entweder person oder company)
     * @return array Erstellte Kontakt-Metadaten mit ID
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten)
     */
    public function createContact(User $user, array $data): array
    {
        return $this->post($user, '/contacts', $data);
    }

    /**
     * Kontakt aktualisieren
     *
     * Aktualisiert einen bestehenden Kontakt in der Lexware API.
     * Die Version muss im Request-Body mitgegeben werden (Optimistic Locking).
     * Alle Felder, die nicht im Request enthalten sind, werden auf ihre Standardwerte zurückgesetzt.
     *
     * @see https://developers.lexoffice.io/docs/#contacts-endpoint-update-a-contact
     *
     * Beispiel-Request:
     * {
     *   "version": 1,
     *   "roles": {
     *     "customer": {
     *       "number": 10001
     *     }
     *   },
     *   "company": {
     *     "name": "Muster GmbH - Aktualisiert",
     *     "taxNumber": "DE123456789",
     *     "allowTaxFreeInvoices": false,
     *     "contactPersons": [
     *       {
     *         "salutation": "Herr",
     *         "firstName": "Max",
     *         "lastName": "Mustermann",
     *         "primary": true,
     *         "emailAddress": "max.mustermann@muster.de",
     *         "phoneNumber": "+49 123 456789"
     *       }
     *     ]
     *   },
     *   "addresses": {
     *     "billing": [
     *       {
     *         "street": "Neue Musterstraße 2",
     *         "zip": "12345",
     *         "city": "Musterstadt",
     *         "countryCode": "DE"
     *       }
     *     ]
     *   },
     *   "emailAddresses": {
     *     "business": ["neu@muster.de"]
     *   },
     *   "phoneNumbers": {
     *     "business": ["+49 123 999888"]
     *   },
     *   "note": "Adresse aktualisiert am 15.01.2024"
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "resourceUri": "https://api.lexoffice.io/v1/contacts/e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "createdDate": "2024-01-10T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-18T11:45:00.000+01:00",
     *   "version": 2
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $contactId Die UUID des zu aktualisierenden Kontakts
     * @param array $data Aktualisierte Kontaktdaten (version erforderlich)
     * @return array Aktualisierte Kontakt-Metadaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 409 bei Versionskonflikt)
     */
    public function updateContact(User $user, string $contactId, array $data): array
    {
        return $this->put($user, "/contacts/{$contactId}", $data);
    }

    /**
     * Kontakt löschen
     *
     * Löscht einen Kontakt aus der Lexware API.
     * Hinweis: Kontakte können nur gelöscht werden, wenn sie nicht in Belegen
     * (Rechnungen, Angebote, etc.) verwendet werden.
     *
     * ACHTUNG: Die Lexware API unterstützt möglicherweise kein direktes Löschen
     * von Kontakten. In diesem Fall wird ein 405 Method Not Allowed zurückgegeben.
     * Als Alternative kann der Kontakt über updateContact archiviert werden
     * (archived: true setzen).
     *
     * @see https://developers.lexoffice.io/docs/#contacts-endpoint
     *
     * Beispiel-Response bei Erfolg:
     * HTTP 204 No Content (leerer Response-Body)
     *
     * Alternative (Kontakt archivieren statt löschen):
     * PUT /contacts/{id} mit { "archived": true, "version": X }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $contactId Die UUID des zu löschenden Kontakts
     * @return array Leeres Array bei Erfolg
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden, 405 nicht erlaubt, 409 in Verwendung)
     */
    public function deleteContact(User $user, string $contactId): array
    {
        return $this->delete($user, "/contacts/{$contactId}");
    }

    // =========================================================================
    // RECHNUNGEN (INVOICES)
    // =========================================================================

    /**
     * Rechnungen abrufen (paginiert)
     *
     * Ruft eine Liste von Rechnungen aus der Lexware API ab.
     * Die Ergebnisse werden über den Voucherlist-Endpunkt abgerufen, gefiltert nach Typ 'invoice'.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     *
     * @see https://developers.lexoffice.io/docs/#voucherlist-endpoint-retrieve-a-voucherlist
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *       "voucherType": "invoice",
     *       "voucherStatus": "open",
     *       "voucherNumber": "RE-2024-001",
     *       "voucherDate": "2024-01-15",
     *       "createdDate": "2024-01-15T10:30:00.000+01:00",
     *       "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *       "dueDate": "2024-02-14",
     *       "contactId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "contactName": "Muster GmbH",
     *       "totalAmount": 1190.00,
     *       "openAmount": 1190.00,
     *       "currency": "EUR",
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 5,
     *   "totalElements": 120,
     *   "numberOfElements": 25,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Rechnungen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getInvoices(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/voucherlist', [
            'voucherType' => 'invoice',
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelne Rechnung abrufen
     *
     * Ruft eine einzelne Rechnung anhand ihrer ID aus der Lexware API ab.
     * Gibt alle Details der Rechnung zurück, inklusive Positionen, Adressen und Summen.
     *
     * @see https://developers.lexoffice.io/docs/#invoices-endpoint-retrieve-an-invoice
     *
     * Beispiel-Response:
     * {
     *   "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *   "version": 1,
     *   "language": "de",
     *   "archived": false,
     *   "voucherStatus": "open",
     *   "voucherNumber": "RE-2024-001",
     *   "voucherDate": "2024-01-15",
     *   "dueDate": "2024-02-14",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH",
     *     "street": "Musterstraße 1",
     *     "zip": "12345",
     *     "city": "Musterstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "id": "97b98491-e953-4dc9-97a9-ae437a8052b4",
     *       "type": "custom",
     *       "name": "Beratungsleistung",
     *       "description": "Projektberatung Januar 2024",
     *       "quantity": 10,
     *       "unitName": "Stunden",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "grossAmount": 119.00,
     *         "taxRatePercentage": 19
     *       },
     *       "lineItemAmount": 1190.00
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR",
     *     "totalNetAmount": 1000.00,
     *     "totalGrossAmount": 1190.00,
     *     "totalTaxAmount": 190.00
     *   },
     *   "taxAmounts": [
     *     {
     *       "taxRatePercentage": 19,
     *       "taxAmount": 190.00,
     *       "netAmount": 1000.00
     *     }
     *   ],
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "paymentConditions": {
     *     "paymentTermLabel": "Zahlbar innerhalb von 30 Tagen",
     *     "paymentTermDuration": 30
     *   },
     *   "shippingConditions": {
     *     "shippingDate": "2024-01-15",
     *     "shippingType": "delivery"
     *   },
     *   "title": "Rechnung",
     *   "introduction": "Vielen Dank für Ihren Auftrag.",
     *   "remark": "Bei Fragen stehen wir Ihnen gerne zur Verfügung."
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $invoiceId Die UUID der Rechnung
     * @return array Rechnungsdaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getInvoice(User $user, string $invoiceId): array
    {
        return $this->get($user, "/invoices/{$invoiceId}");
    }

    /**
     * Rechnung erstellen
     *
     * Erstellt eine neue Rechnung in der Lexware API.
     * Die Rechnung kann entweder als Entwurf oder direkt als finalisierte Rechnung erstellt werden.
     * Finalisierte Rechnungen erhalten eine Rechnungsnummer und können nicht mehr bearbeitet werden.
     *
     * @see https://developers.lexoffice.io/docs/#invoices-endpoint-create-an-invoice
     *
     * Beispiel-Request (Rechnung an bestehenden Kontakt):
     * {
     *   "voucherDate": "2024-01-15",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Beratungsleistung",
     *       "description": "Projektberatung Januar 2024",
     *       "quantity": 10,
     *       "unitName": "Stunden",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "paymentConditions": {
     *     "paymentTermLabel": "Zahlbar innerhalb von 30 Tagen",
     *     "paymentTermDuration": 30
     *   },
     *   "shippingConditions": {
     *     "shippingDate": "2024-01-15",
     *     "shippingType": "delivery"
     *   },
     *   "title": "Rechnung",
     *   "introduction": "Vielen Dank für Ihren Auftrag.",
     *   "remark": "Bei Fragen stehen wir Ihnen gerne zur Verfügung."
     * }
     *
     * Beispiel-Request (Rechnung mit neuer Adresse ohne Kontakt):
     * {
     *   "voucherDate": "2024-01-15",
     *   "address": {
     *     "name": "Neue Firma GmbH",
     *     "street": "Beispielstraße 123",
     *     "zip": "54321",
     *     "city": "Beispielstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Produkt A",
     *       "quantity": 5,
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 50.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   }
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "resourceUri": "https://api.lexoffice.io/v1/invoices/e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *   "version": 0
     * }
     *
     * Hinweise:
     * - Entwürfe (finalize=false) können nachträglich bearbeitet werden
     * - Finalisierte Rechnungen (finalize=true) erhalten eine Rechnungsnummer
     * - Die address kann entweder eine contactId oder manuelle Adressdaten enthalten
     * - lineItems können vom Typ 'custom' (freier Text) oder 'material' (Artikel) sein
     * - taxType kann 'net', 'gross' oder 'vatfree' sein
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Rechnungsdaten (address, lineItems, taxConditions erforderlich)
     * @param bool $finalize Wenn true, wird die Rechnung direkt finalisiert (Standard: false)
     * @return array Erstellte Rechnungs-Metadaten mit ID
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten)
     */
    public function createInvoice(User $user, array $data, bool $finalize = false): array
    {
        $query = $finalize ? ['finalize' => 'true'] : [];
        return $this->post($user, '/invoices', $data, $query);
    }

    /**
     * Rechnung finalisieren (abschließen)
     *
     * Finalisiert einen Rechnungsentwurf und macht ihn zu einer echten Rechnung.
     * Nach der Finalisierung erhält die Rechnung eine Rechnungsnummer und kann nicht mehr bearbeitet werden.
     * Der voucherStatus wechselt von 'draft' zu 'open'.
     *
     * WICHTIG: Diese Operation ist unwiderruflich! Finalisierte Rechnungen können nur noch
     * storniert werden, aber nicht mehr bearbeitet.
     *
     * @see https://developers.lexoffice.io/docs/#invoices-endpoint-finalize-an-invoice
     *
     * Beispiel-Request:
     * POST /invoices/{id}/finalize
     *
     * Beispiel-Response:
     * HTTP 200 OK (leerer Response-Body)
     *
     * Voraussetzungen:
     * - Die Rechnung muss im Status 'draft' (Entwurf) sein
     * - Alle Pflichtfelder müssen ausgefüllt sein
     * - Die Rechnungsdaten müssen valide sein
     *
     * Hinweise:
     * - Nach der Finalisierung wird automatisch eine Rechnungsnummer vergeben
     * - Der PDF-Download ist erst nach der Finalisierung möglich
     * - Finalisierte Rechnungen erscheinen in der Buchhaltung
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $invoiceId Die UUID der zu finalisierenden Rechnung
     * @return array Leeres Array bei Erfolg
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 wenn bereits finalisiert, 404 nicht gefunden)
     */
    public function finalizeInvoice(User $user, string $invoiceId): array
    {
        return $this->post($user, "/invoices/{$invoiceId}/finalize");
    }

    /**
     * Rechnung als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für eine finalisierte Rechnung.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * WICHTIG: Diese Methode erstellt das PDF, lädt es aber nicht herunter.
     * Für den Download verwende getInvoiceDownload() mit der documentFileId.
     *
     * @see https://developers.lexoffice.io/docs/#invoices-endpoint-render-a-document
     *
     * Beispiel-Request:
     * GET /invoices/{id}/document
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Die Rechnung muss finalisiert sein (voucherStatus != 'draft')
     * - Bei Entwürfen wird ein Fehler zurückgegeben
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann ablaufen
     * - Das PDF wird bei jedem Aufruf neu generiert
     * - Für den Download muss getInvoiceDownload() separat aufgerufen werden
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $invoiceId Die UUID der Rechnung
     * @return array Array mit documentFileId
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden, 406 wenn Entwurf)
     */
    public function renderInvoicePdf(User $user, string $invoiceId): array
    {
        return $this->get($user, "/invoices/{$invoiceId}/document");
    }

    /**
     * PDF-Dokument herunterladen
     *
     * Lädt das PDF-Dokument einer Rechnung herunter.
     * Verwendet die documentFileId aus renderInvoicePdf().
     *
     * WICHTIG: Diese Methode gibt den binären PDF-Inhalt zurück, nicht JSON!
     * Der Content-Type der Response ist 'application/pdf'.
     *
     * @see https://developers.lexoffice.io/docs/#files-endpoint-download-a-file
     *
     * Beispiel-Request:
     * GET /files/{documentFileId}
     *
     * Beispiel-Response:
     * Binäre PDF-Daten (Content-Type: application/pdf)
     *
     * Hinweise:
     * - Die documentFileId erhält man über renderInvoicePdf()
     * - Die documentFileId ist temporär und kann nach einiger Zeit ablaufen
     * - Das heruntergeladene PDF enthält das vollständige Rechnungsdokument
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $documentFileId Die Document-File-ID aus renderInvoicePdf()
     * @return string Binärer PDF-Inhalt
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden)
     */
    public function downloadFile(User $user, string $documentFileId): string
    {
        // Token aus der IntegrationConnection Tabelle holen
        $connection = $this->integrationService->getConnectionForUser($user);

        if (!$connection) {
            Log::warning('Lexware API: Keine Connection für User', ['user_id' => $user->id]);
            throw LexwareApiException::noConnection();
        }

        $apiToken = $this->integrationService->getApiToken($connection);

        if (!$apiToken) {
            Log::warning('Lexware API: Kein Token für User', ['user_id' => $user->id]);
            throw LexwareApiException::unauthorized();
        }

        $url = self::BASE_URL . "/files/{$documentFileId}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/pdf',
            ])->get($url);

            if (!$response->successful()) {
                $this->updateConnectionStatus(
                    $connection,
                    $response->status() === 401 ? 'error' : 'active',
                    $response->json()['message'] ?? null
                );

                throw LexwareApiException::fromResponse($response->status(), $response->json() ?? []);
            }

            $this->updateConnectionStatus($connection, 'active');
            return $response->body();
        } catch (LexwareApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Lexware API: Verbindungsfehler beim Download', [
                'user_id' => $user->id,
                'document_file_id' => $documentFileId,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());
            throw LexwareApiException::connectionError($e->getMessage());
        }
    }

    // =========================================================================
    // DATEIEN (FILES)
    // =========================================================================

    /**
     * Datei hochladen
     *
     * Lädt eine Datei in die Lexware/Lexoffice API hoch.
     * Die hochgeladene Datei kann dann mit Belegen (Vouchers) verknüpft werden.
     *
     * WICHTIG: Diese Methode sendet die Datei als multipart/form-data.
     * Die maximale Dateigröße beträgt 5 MB (Lexware API Limit).
     *
     * Unterstützte Content-Types:
     * - application/pdf (PDF-Dokumente)
     * - image/png (PNG-Bilder)
     * - image/jpeg (JPEG-Bilder)
     *
     * @see https://developers.lexoffice.io/docs/#files-endpoint-upload-a-file
     *
     * Beispiel-Request:
     * POST /files
     * Content-Type: multipart/form-data
     * - file: Die hochzuladende Datei (max. 5 MB)
     * - type: Der Dateityp ("voucher" für Belegbilder)
     *
     * Beispiel-Response:
     * {
     *   "id": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Hinweise:
     * - Die zurückgegebene File-ID kann verwendet werden, um die Datei mit einem Beleg zu verknüpfen
     * - Hochgeladene Dateien sind nur temporär verfügbar bis sie mit einem Beleg verknüpft werden
     * - Bei Überschreitung der Dateigröße wird ein 400 Bad Request zurückgegeben
     * - Bei nicht unterstütztem Content-Type wird ein 415 Unsupported Media Type zurückgegeben
     *
     * Content-Type Handling:
     * - Der Content-Type wird automatisch aus der Datei-Extension erkannt
     * - Alternativ kann der Content-Type explizit übergeben werden
     * - Bei unbekannter Extension wird 'application/octet-stream' verwendet
     *
     * Large File Handling:
     * - Dateien größer als 5 MB werden von der Lexware API abgelehnt
     * - Diese Methode prüft die Dateigröße NICHT vorab - die Validierung sollte im Controller erfolgen
     * - Für große Dateien empfiehlt sich eine Vorverarbeitung (z.B. Bildkomprimierung)
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $filePath Der vollständige Pfad zur Datei auf dem Server
     * @param string $fileName Der Dateiname für die hochgeladene Datei
     * @param string $type Der Dateityp (Standard: "voucher" für Belegbilder)
     * @param string|null $contentType Der Content-Type der Datei (optional, wird automatisch erkannt)
     * @return array Array mit der File-ID der hochgeladenen Datei
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 Dateigröße, 415 falscher Content-Type)
     */
    public function uploadFile(
        User $user,
        string $filePath,
        string $fileName,
        string $type = 'voucher',
        ?string $contentType = null
    ): array {
        // Token aus der IntegrationConnection Tabelle holen
        $connection = $this->integrationService->getConnectionForUser($user);

        if (!$connection) {
            Log::warning('Lexware API: Keine Connection für User', ['user_id' => $user->id]);
            throw LexwareApiException::noConnection();
        }

        $apiToken = $this->integrationService->getApiToken($connection);

        if (!$apiToken) {
            Log::warning('Lexware API: Kein Token für User', ['user_id' => $user->id]);
            throw LexwareApiException::unauthorized();
        }

        // Prüfen ob Datei existiert
        if (!file_exists($filePath)) {
            Log::warning('Lexware API: Datei nicht gefunden', [
                'user_id' => $user->id,
                'file_path' => $filePath,
            ]);
            throw LexwareApiException::fromResponse(400, [
                'message' => 'Die angegebene Datei wurde nicht gefunden.',
            ]);
        }

        // Content-Type automatisch erkennen wenn nicht angegeben
        if ($contentType === null) {
            $contentType = $this->detectContentType($filePath, $fileName);
        }

        $url = self::BASE_URL . '/files';

        try {
            // Multipart-Request für Datei-Upload erstellen
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
            ])->attach(
                'file',
                file_get_contents($filePath),
                $fileName,
                ['Content-Type' => $contentType]
            )->post($url, [
                'type' => $type,
            ]);

            if (!$response->successful()) {
                $this->updateConnectionStatus(
                    $connection,
                    $response->status() === 401 ? 'error' : 'active',
                    $response->json()['message'] ?? null
                );

                Log::warning('Lexware API: Fehler beim Datei-Upload', [
                    'user_id' => $user->id,
                    'file_name' => $fileName,
                    'status_code' => $response->status(),
                    'response' => $response->json(),
                ]);

                throw LexwareApiException::fromResponse($response->status(), $response->json() ?? []);
            }

            $this->updateConnectionStatus($connection, 'active');

            Log::info('Lexware API: Datei erfolgreich hochgeladen', [
                'user_id' => $user->id,
                'file_name' => $fileName,
                'file_id' => $response->json()['id'] ?? null,
            ]);

            return $response->json();
        } catch (LexwareApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Lexware API: Verbindungsfehler beim Upload', [
                'user_id' => $user->id,
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());
            throw LexwareApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Datei herunterladen
     *
     * Lädt eine Datei anhand ihrer ID aus der Lexware API herunter.
     * Diese Methode ist ein Alias für downloadFile() und dient der Konsistenz
     * mit dem neuen /files Endpoint.
     *
     * WICHTIG: Diese Methode gibt den binären Datei-Inhalt zurück, nicht JSON!
     * Der Content-Type der Response entspricht dem Typ der ursprünglich hochgeladenen Datei.
     *
     * @see https://developers.lexoffice.io/docs/#files-endpoint-download-a-file
     *
     * Beispiel-Request:
     * GET /files/{id}
     *
     * Beispiel-Response:
     * Binäre Datei-Daten (Content-Type entspricht Dateiformat: application/pdf, image/png, etc.)
     *
     * Hinweise:
     * - Die File-ID erhält man beim Upload oder über renderPdf()-Methoden der Dokumente
     * - Die File-ID kann temporär sein und nach einiger Zeit ablaufen
     * - Diese Methode unterstützt verschiedene Content-Types (nicht nur PDF)
     *
     * Content-Type Handling:
     * - Der Response-Content-Type entspricht dem der hochgeladenen Datei
     * - Für PDF-Dokumente: application/pdf
     * - Für Bilder: image/png, image/jpeg
     * - Der Accept-Header wird automatisch auf den erwarteten Content-Type gesetzt
     *
     * Large File Handling:
     * - Die Methode liest die gesamte Datei in den Speicher
     * - Für sehr große Dateien sollte Streaming in Betracht gezogen werden
     * - Die maximale Dateigröße beträgt 5 MB (Lexware API Limit)
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $fileId Die UUID der Datei
     * @param string $acceptHeader Der erwartete Content-Type (Standard: application/pdf)
     * @return string Binärer Datei-Inhalt
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden)
     */
    public function getFile(User $user, string $fileId, string $acceptHeader = 'application/pdf'): string
    {
        // Token aus der IntegrationConnection Tabelle holen
        $connection = $this->integrationService->getConnectionForUser($user);

        if (!$connection) {
            Log::warning('Lexware API: Keine Connection für User', ['user_id' => $user->id]);
            throw LexwareApiException::noConnection();
        }

        $apiToken = $this->integrationService->getApiToken($connection);

        if (!$apiToken) {
            Log::warning('Lexware API: Kein Token für User', ['user_id' => $user->id]);
            throw LexwareApiException::unauthorized();
        }

        $url = self::BASE_URL . "/files/{$fileId}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => $acceptHeader,
            ])->get($url);

            if (!$response->successful()) {
                $this->updateConnectionStatus(
                    $connection,
                    $response->status() === 401 ? 'error' : 'active',
                    $response->json()['message'] ?? null
                );

                throw LexwareApiException::fromResponse($response->status(), $response->json() ?? []);
            }

            $this->updateConnectionStatus($connection, 'active');
            return $response->body();
        } catch (LexwareApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Lexware API: Verbindungsfehler beim Download', [
                'user_id' => $user->id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());
            throw LexwareApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Content-Type einer Datei automatisch erkennen
     *
     * Ermittelt den MIME-Type einer Datei basierend auf:
     * 1. Der Datei-Extension
     * 2. Der PHP-Funktion mime_content_type() als Fallback
     *
     * Unterstützte Dateitypen für Lexware:
     * - PDF (.pdf) -> application/pdf
     * - PNG (.png) -> image/png
     * - JPEG (.jpg, .jpeg) -> image/jpeg
     *
     * @param string $filePath Der vollständige Pfad zur Datei
     * @param string $fileName Der Dateiname (für Extension-Erkennung)
     * @return string Der erkannte Content-Type
     */
    protected function detectContentType(string $filePath, string $fileName): string
    {
        // Unterstützte Content-Types für Lexware
        $extensionMap = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];

        // Extension aus Dateinamen extrahieren
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (isset($extensionMap[$extension])) {
            return $extensionMap[$extension];
        }

        // Fallback: PHP MIME-Type Erkennung
        $mimeType = mime_content_type($filePath);

        if ($mimeType && $mimeType !== 'application/octet-stream') {
            return $mimeType;
        }

        // Standard-Fallback
        return 'application/octet-stream';
    }

    /**
     * Deeplink zu einer hochgeladenen Datei generieren
     *
     * Generiert einen Deep-Link, der direkt zur Datei-Ansicht in der Lexoffice
     * Web-Oberfläche führt. Dieser Link kann verwendet werden, um Benutzer
     * direkt zur Datei in Lexoffice weiterzuleiten.
     *
     * HINWEIS: Lexoffice bietet keinen direkten öffentlichen Deeplink für einzelne Dateien.
     * Dateien sind immer mit einem Beleg (Voucher) verknüpft und werden über den
     * Beleg-Deeplink aufgerufen. Diese Methode gibt daher eine Empfehlung zurück,
     * wie man zur verknüpften Ressource navigiert.
     *
     * @param string $fileId Die UUID der Datei
     * @return array Array mit Deeplink-Information und Hinweis
     *
     * Beispiel-Response:
     * {
     *   "info": "Dateien in Lexoffice sind mit Belegen verknüpft.",
     *   "hint": "Verwenden Sie den Deeplink des verknüpften Belegs, um die Datei anzuzeigen.",
     *   "fileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein
     * - Die Datei muss mit einem Beleg verknüpft sein, um sie anzuzeigen
     * - Einzelne Dateien ohne Beleg-Verknüpfung sind nicht direkt abrufbar
     */
    public function getFileDeeplink(string $fileId): array
    {
        return [
            'info' => 'Dateien in Lexoffice sind mit Belegen verknüpft.',
            'hint' => 'Verwenden Sie den Deeplink des verknüpften Belegs, um die Datei anzuzeigen.',
            'fileId' => $fileId,
        ];
    }

    /**
     * Deeplink zur Rechnung in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zur Rechnung in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zur Rechnung in Lexoffice weiterzuleiten.
     *
     * HINWEIS: Dies ist ein konstruierter Link basierend auf der Lexoffice-URL-Struktur.
     * Die Lexware API bietet keinen direkten Deeplink-Endpunkt, daher wird der Link
     * anhand der bekannten URL-Struktur von Lexoffice konstruiert.
     *
     * @param string $invoiceId Die UUID der Rechnung
     * @return array Array mit dem Deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/invoice/e9066f04-8cc7-4616-93f8-ac9c10e55bc9"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn die Rechnung existiert und der Benutzer Zugriff hat
     */
    public function getInvoiceDeeplink(string $invoiceId): array
    {
        return [
            'deeplink' => "https://app.lexoffice.de/vouchers#!/view/invoice/{$invoiceId}",
        ];
    }

    // =========================================================================
    // ANGEBOTE (QUOTATIONS)
    // =========================================================================

    /**
     * Angebote abrufen (paginiert)
     *
     * Ruft eine Liste von Angeboten aus der Lexware API ab.
     * Die Ergebnisse werden über den Voucherlist-Endpunkt abgerufen, gefiltert nach Typ 'quotation'.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     *
     * @see https://developers.lexoffice.io/docs/#voucherlist-endpoint-retrieve-a-voucherlist
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *       "voucherType": "quotation",
     *       "voucherStatus": "open",
     *       "voucherNumber": "AG-2024-001",
     *       "voucherDate": "2024-01-15",
     *       "createdDate": "2024-01-15T10:30:00.000+01:00",
     *       "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *       "expirationDate": "2024-02-14",
     *       "contactId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "contactName": "Muster GmbH",
     *       "totalAmount": 1190.00,
     *       "currency": "EUR",
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 5,
     *   "totalElements": 120,
     *   "numberOfElements": 25,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Angeboten
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getQuotations(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/voucherlist', [
            'voucherType' => 'quotation',
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelnes Angebot abrufen
     *
     * Ruft ein einzelnes Angebot anhand seiner ID aus der Lexware API ab.
     * Gibt alle Details des Angebots zurück, inklusive Positionen, Adressen und Summen.
     *
     * @see https://developers.lexoffice.io/docs/#quotations-endpoint-retrieve-a-quotation
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *   "version": 1,
     *   "language": "de",
     *   "archived": false,
     *   "voucherStatus": "open",
     *   "voucherNumber": "AG-2024-001",
     *   "voucherDate": "2024-01-15",
     *   "expirationDate": "2024-02-14",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH",
     *     "street": "Musterstraße 1",
     *     "zip": "12345",
     *     "city": "Musterstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "id": "97b98491-e953-4dc9-97a9-ae437a8052b4",
     *       "type": "custom",
     *       "name": "Beratungsleistung",
     *       "description": "Projektberatung Januar 2024",
     *       "quantity": 10,
     *       "unitName": "Stunden",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "grossAmount": 119.00,
     *         "taxRatePercentage": 19
     *       },
     *       "lineItemAmount": 1190.00
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR",
     *     "totalNetAmount": 1000.00,
     *     "totalGrossAmount": 1190.00,
     *     "totalTaxAmount": 190.00
     *   },
     *   "taxAmounts": [
     *     {
     *       "taxRatePercentage": 19,
     *       "taxAmount": 190.00,
     *       "netAmount": 1000.00
     *     }
     *   ],
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "title": "Angebot",
     *   "introduction": "Gerne unterbreiten wir Ihnen folgendes Angebot.",
     *   "remark": "Dieses Angebot ist 30 Tage gültig."
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $quotationId Die UUID des Angebots
     * @return array Angebotsdaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getQuotation(User $user, string $quotationId): array
    {
        return $this->get($user, "/quotations/{$quotationId}");
    }

    /**
     * Angebot erstellen
     *
     * Erstellt ein neues Angebot in der Lexware API.
     * Das Angebot kann entweder als Entwurf oder direkt als finalisiertes Angebot erstellt werden.
     * Finalisierte Angebote erhalten eine Angebotsnummer und können nicht mehr bearbeitet werden.
     *
     * @see https://developers.lexoffice.io/docs/#quotations-endpoint-create-a-quotation
     *
     * Beispiel-Request (Angebot an bestehenden Kontakt):
     * {
     *   "voucherDate": "2024-01-15",
     *   "expirationDate": "2024-02-14",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Beratungsleistung",
     *       "description": "Projektberatung Januar 2024",
     *       "quantity": 10,
     *       "unitName": "Stunden",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "title": "Angebot",
     *   "introduction": "Gerne unterbreiten wir Ihnen folgendes Angebot.",
     *   "remark": "Dieses Angebot ist 30 Tage gültig."
     * }
     *
     * Beispiel-Request (Angebot mit neuer Adresse ohne Kontakt):
     * {
     *   "voucherDate": "2024-01-15",
     *   "expirationDate": "2024-02-14",
     *   "address": {
     *     "name": "Neue Firma GmbH",
     *     "street": "Beispielstraße 123",
     *     "zip": "54321",
     *     "city": "Beispielstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Produkt A",
     *       "quantity": 5,
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 50.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   }
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "resourceUri": "https://api.lexoffice.io/v1/quotations/a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *   "version": 0
     * }
     *
     * Hinweise:
     * - Entwürfe (finalize=false) können nachträglich bearbeitet werden
     * - Finalisierte Angebote (finalize=true) erhalten eine Angebotsnummer
     * - Die address kann entweder eine contactId oder manuelle Adressdaten enthalten
     * - lineItems können vom Typ 'custom' (freier Text) oder 'material' (Artikel) sein
     * - taxType kann 'net', 'gross' oder 'vatfree' sein
     * - expirationDate gibt das Gültigkeitsdatum des Angebots an
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Angebotsdaten (address, lineItems, taxConditions erforderlich)
     * @param bool $finalize Wenn true, wird das Angebot direkt finalisiert (Standard: false)
     * @return array Erstellte Angebots-Metadaten mit ID
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten)
     */
    public function createQuotation(User $user, array $data, bool $finalize = false): array
    {
        $query = $finalize ? ['finalize' => 'true'] : [];
        return $this->post($user, '/quotations', $data, $query);
    }

    /**
     * Angebot als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für ein finalisiertes Angebot.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * WICHTIG: Diese Methode erstellt das PDF, lädt es aber nicht herunter.
     * Für den Download verwende downloadFile() mit der documentFileId.
     *
     * @see https://developers.lexoffice.io/docs/#quotations-endpoint-render-a-document
     *
     * Beispiel-Request:
     * GET /quotations/{id}/document
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Das Angebot muss finalisiert sein (voucherStatus != 'draft')
     * - Bei Entwürfen wird ein Fehler zurückgegeben
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann ablaufen
     * - Das PDF wird bei jedem Aufruf neu generiert
     * - Für den Download muss downloadFile() separat aufgerufen werden
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $quotationId Die UUID des Angebots
     * @return array Array mit documentFileId
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden, 406 wenn Entwurf)
     */
    public function renderQuotationPdf(User $user, string $quotationId): array
    {
        return $this->get($user, "/quotations/{$quotationId}/document");
    }

    /**
     * Deeplink zum Angebot in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zum Angebot in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zum Angebot in Lexoffice weiterzuleiten.
     *
     * HINWEIS: Dies ist ein konstruierter Link basierend auf der Lexoffice-URL-Struktur.
     * Die Lexware API bietet keinen direkten Deeplink-Endpunkt, daher wird der Link
     * anhand der bekannten URL-Struktur von Lexoffice konstruiert.
     *
     * @param string $quotationId Die UUID des Angebots
     * @return array Array mit dem Deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/quotation/a1b2c3d4-e5f6-7890-abcd-ef1234567890"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn das Angebot existiert und der Benutzer Zugriff hat
     */
    public function getQuotationDeeplink(string $quotationId): array
    {
        return [
            'deeplink' => "https://app.lexoffice.de/vouchers#!/view/quotation/{$quotationId}",
        ];
    }

    // =========================================================================
    // AUFTRAGSBESTÄTIGUNGEN (ORDER CONFIRMATIONS)
    // =========================================================================

    /**
     * Auftragsbestätigungen abrufen (paginiert)
     *
     * Ruft eine Liste von Auftragsbestätigungen aus der Lexware API ab.
     * Die Ergebnisse werden über den Voucherlist-Endpunkt abgerufen, gefiltert nach Typ 'orderconfirmation'.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     *
     * @see https://developers.lexoffice.io/docs/#voucherlist-endpoint-retrieve-a-voucherlist
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "a1b2c3d4-e5f6-7890-abcd-123456789xyz",
     *       "voucherType": "orderconfirmation",
     *       "voucherStatus": "open",
     *       "voucherNumber": "AB-2024-001",
     *       "voucherDate": "2024-01-15",
     *       "createdDate": "2024-01-15T10:30:00.000+01:00",
     *       "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *       "contactId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "contactName": "Muster GmbH",
     *       "totalAmount": 1190.00,
     *       "currency": "EUR",
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 2,
     *   "totalElements": 30,
     *   "numberOfElements": 25,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Auftragsbestätigungen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getOrderConfirmations(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/voucherlist', [
            'voucherType' => 'orderconfirmation',
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelne Auftragsbestätigung abrufen
     *
     * Ruft eine einzelne Auftragsbestätigung anhand ihrer ID aus der Lexware API ab.
     * Gibt alle Details der Auftragsbestätigung zurück, inklusive Positionen, Adressen und Summen.
     *
     * @see https://developers.lexoffice.io/docs/#order-confirmations-endpoint-retrieve-an-order-confirmation
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-123456789xyz",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *   "version": 1,
     *   "language": "de",
     *   "archived": false,
     *   "voucherStatus": "open",
     *   "voucherNumber": "AB-2024-001",
     *   "voucherDate": "2024-01-15",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH",
     *     "street": "Musterstraße 1",
     *     "zip": "12345",
     *     "city": "Musterstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "id": "97b98491-e953-4dc9-97a9-ae437a8052b4",
     *       "type": "custom",
     *       "name": "Beratungsleistung",
     *       "description": "IT-Beratung Januar 2024",
     *       "quantity": 10,
     *       "unitName": "Stunden",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "grossAmount": 119.00,
     *         "taxRatePercentage": 19
     *       },
     *       "lineItemAmount": 1190.00
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR",
     *     "totalNetAmount": 1000.00,
     *     "totalGrossAmount": 1190.00,
     *     "totalTaxAmount": 190.00
     *   },
     *   "taxAmounts": [
     *     {
     *       "taxRatePercentage": 19,
     *       "taxAmount": 190.00,
     *       "netAmount": 1000.00
     *     }
     *   ],
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "title": "Auftragsbestätigung",
     *   "introduction": "Vielen Dank für Ihren Auftrag.",
     *   "remark": "Bei Fragen stehen wir Ihnen gerne zur Verfügung.",
     *   "deliveryTerms": "Lieferung innerhalb von 2 Wochen"
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $orderId Die UUID der Auftragsbestätigung
     * @return array Auftragsbestätigungsdaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getOrderConfirmation(User $user, string $orderId): array
    {
        return $this->get($user, "/order-confirmations/{$orderId}");
    }

    /**
     * Auftragsbestätigung erstellen
     *
     * Erstellt eine neue Auftragsbestätigung in der Lexware API.
     * Die Auftragsbestätigung kann entweder als Entwurf oder direkt als finalisierte Bestätigung erstellt werden.
     * Finalisierte Auftragsbestätigungen erhalten eine Auftragsnummer und können nicht mehr bearbeitet werden.
     *
     * @see https://developers.lexoffice.io/docs/#order-confirmations-endpoint-create-an-order-confirmation
     *
     * Beispiel-Request (Auftragsbestätigung an bestehenden Kontakt):
     * {
     *   "voucherDate": "2024-01-15",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Beratungsleistung",
     *       "description": "IT-Beratung Januar 2024",
     *       "quantity": 10,
     *       "unitName": "Stunden",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "title": "Auftragsbestätigung",
     *   "introduction": "Vielen Dank für Ihren Auftrag.",
     *   "remark": "Bei Fragen stehen wir Ihnen gerne zur Verfügung.",
     *   "deliveryTerms": "Lieferung innerhalb von 2 Wochen"
     * }
     *
     * Beispiel-Request (Auftragsbestätigung mit neuer Adresse ohne Kontakt):
     * {
     *   "voucherDate": "2024-01-15",
     *   "address": {
     *     "name": "Neue Firma GmbH",
     *     "street": "Beispielstraße 123",
     *     "zip": "54321",
     *     "city": "Beispielstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Softwarelizenz",
     *       "quantity": 5,
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 200.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   }
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-123456789xyz",
     *   "resourceUri": "https://api.lexoffice.io/v1/order-confirmations/a1b2c3d4-e5f6-7890-abcd-123456789xyz",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *   "version": 0
     * }
     *
     * Hinweise:
     * - Entwürfe (finalize=false) können nachträglich bearbeitet werden
     * - Finalisierte Auftragsbestätigungen (finalize=true) erhalten eine Auftragsnummer
     * - Die address kann entweder eine contactId oder manuelle Adressdaten enthalten
     * - lineItems können vom Typ 'custom' (freier Text) oder 'material' (Artikel) sein
     * - taxType kann 'net', 'gross' oder 'vatfree' sein
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Auftragsbestätigungsdaten (address, lineItems, taxConditions erforderlich)
     * @param bool $finalize Wenn true, wird die Auftragsbestätigung direkt finalisiert (Standard: false)
     * @return array Erstellte Auftragsbestätigung-Metadaten mit ID
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten)
     */
    public function createOrderConfirmation(User $user, array $data, bool $finalize = false): array
    {
        $query = $finalize ? ['finalize' => 'true'] : [];
        return $this->post($user, '/order-confirmations', $data, $query);
    }

    /**
     * Auftragsbestätigung als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für eine finalisierte Auftragsbestätigung.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * WICHTIG: Diese Methode erstellt das PDF, lädt es aber nicht herunter.
     * Für den Download verwende downloadFile() mit der documentFileId.
     *
     * @see https://developers.lexoffice.io/docs/#order-confirmations-endpoint-render-a-document
     *
     * Beispiel-Request:
     * GET /order-confirmations/{id}/document
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Die Auftragsbestätigung muss finalisiert sein (voucherStatus != 'draft')
     * - Bei Entwürfen wird ein Fehler zurückgegeben
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann ablaufen
     * - Das PDF wird bei jedem Aufruf neu generiert
     * - Für den Download muss downloadFile() separat aufgerufen werden
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $orderId Die UUID der Auftragsbestätigung
     * @return array Array mit documentFileId
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden, 406 wenn Entwurf)
     */
    public function renderOrderConfirmationPdf(User $user, string $orderId): array
    {
        return $this->get($user, "/order-confirmations/{$orderId}/document");
    }

    /**
     * Deeplink zur Auftragsbestätigung in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zur Auftragsbestätigung in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zur Auftragsbestätigung in Lexoffice weiterzuleiten.
     *
     * HINWEIS: Dies ist ein konstruierter Link basierend auf der Lexoffice-URL-Struktur.
     * Die Lexware API bietet keinen direkten Deeplink-Endpunkt, daher wird der Link
     * anhand der bekannten URL-Struktur von Lexoffice konstruiert.
     *
     * @param string $orderId Die UUID der Auftragsbestätigung
     * @return array Array mit dem Deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/orderconfirmation/a1b2c3d4-e5f6-7890-abcd-123456789xyz"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn die Auftragsbestätigung existiert und der Benutzer Zugriff hat
     */
    public function getOrderConfirmationDeeplink(string $orderId): array
    {
        return [
            'deeplink' => "https://app.lexoffice.de/vouchers#!/view/orderconfirmation/{$orderId}",
        ];
    }

    // =========================================================================
    // GUTSCHRIFTEN (CREDIT NOTES)
    // =========================================================================

    /**
     * Gutschriften abrufen (paginiert)
     *
     * Ruft eine Liste von Gutschriften aus der Lexware API ab.
     * Die Ergebnisse werden über den Voucherlist-Endpunkt abgerufen, gefiltert nach Typ 'creditnote'.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     *
     * @see https://developers.lexoffice.io/docs/#voucherlist-endpoint-retrieve-a-voucherlist
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "c1d2e3f4-a5b6-7890-cdef-123456789abc",
     *       "voucherType": "creditnote",
     *       "voucherStatus": "open",
     *       "voucherNumber": "GS-2024-001",
     *       "voucherDate": "2024-01-20",
     *       "createdDate": "2024-01-20T10:30:00.000+01:00",
     *       "updatedDate": "2024-01-20T10:30:00.000+01:00",
     *       "contactId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "contactName": "Muster GmbH",
     *       "totalAmount": 119.00,
     *       "currency": "EUR",
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 2,
     *   "totalElements": 30,
     *   "numberOfElements": 25,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Gutschriften
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getCreditNotes(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/voucherlist', [
            'voucherType' => 'creditnote',
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelne Gutschrift abrufen
     *
     * Ruft eine einzelne Gutschrift anhand ihrer ID aus der Lexware API ab.
     * Gibt alle Details der Gutschrift zurück, inklusive Positionen, Adressen und Summen.
     *
     * @see https://developers.lexoffice.io/docs/#credit-notes-endpoint-retrieve-a-credit-note
     *
     * Beispiel-Response:
     * {
     *   "id": "c1d2e3f4-a5b6-7890-cdef-123456789abc",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-20T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-20T10:30:00.000+01:00",
     *   "version": 1,
     *   "language": "de",
     *   "archived": false,
     *   "voucherStatus": "open",
     *   "voucherNumber": "GS-2024-001",
     *   "voucherDate": "2024-01-20",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH",
     *     "street": "Musterstraße 1",
     *     "zip": "12345",
     *     "city": "Musterstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "id": "97b98491-e953-4dc9-97a9-ae437a8052b4",
     *       "type": "custom",
     *       "name": "Rückerstattung Beratungsleistung",
     *       "description": "Gutschrift für Januar 2024",
     *       "quantity": 1,
     *       "unitName": "Stück",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "grossAmount": 119.00,
     *         "taxRatePercentage": 19
     *       },
     *       "lineItemAmount": 119.00
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR",
     *     "totalNetAmount": 100.00,
     *     "totalGrossAmount": 119.00,
     *     "totalTaxAmount": 19.00
     *   },
     *   "taxAmounts": [
     *     {
     *       "taxRatePercentage": 19,
     *       "taxAmount": 19.00,
     *       "netAmount": 100.00
     *     }
     *   ],
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "title": "Gutschrift",
     *   "introduction": "Hiermit erhalten Sie folgende Gutschrift.",
     *   "remark": "Bei Fragen stehen wir Ihnen gerne zur Verfügung."
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $creditNoteId Die UUID der Gutschrift
     * @return array Gutschriftdaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getCreditNote(User $user, string $creditNoteId): array
    {
        return $this->get($user, "/credit-notes/{$creditNoteId}");
    }

    /**
     * Gutschrift erstellen
     *
     * Erstellt eine neue Gutschrift in der Lexware API.
     * Die Gutschrift kann entweder als Entwurf oder direkt als finalisierte Gutschrift erstellt werden.
     * Finalisierte Gutschriften erhalten eine Gutschriftsnummer und können nicht mehr bearbeitet werden.
     *
     * @see https://developers.lexoffice.io/docs/#credit-notes-endpoint-create-a-credit-note
     *
     * Beispiel-Request (Gutschrift an bestehenden Kontakt):
     * {
     *   "voucherDate": "2024-01-20",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Rückerstattung Beratungsleistung",
     *       "description": "Gutschrift für Januar 2024",
     *       "quantity": 1,
     *       "unitName": "Stück",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "title": "Gutschrift",
     *   "introduction": "Hiermit erhalten Sie folgende Gutschrift.",
     *   "remark": "Bei Fragen stehen wir Ihnen gerne zur Verfügung."
     * }
     *
     * Beispiel-Request (Gutschrift mit neuer Adresse ohne Kontakt):
     * {
     *   "voucherDate": "2024-01-20",
     *   "address": {
     *     "name": "Neue Firma GmbH",
     *     "street": "Beispielstraße 123",
     *     "zip": "54321",
     *     "city": "Beispielstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Rückerstattung Produkt A",
     *       "quantity": 2,
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 50.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   }
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "c1d2e3f4-a5b6-7890-cdef-123456789abc",
     *   "resourceUri": "https://api.lexoffice.io/v1/credit-notes/c1d2e3f4-a5b6-7890-cdef-123456789abc",
     *   "createdDate": "2024-01-20T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-20T10:30:00.000+01:00",
     *   "version": 0
     * }
     *
     * Hinweise:
     * - Entwürfe (finalize=false) können nachträglich bearbeitet werden
     * - Finalisierte Gutschriften (finalize=true) erhalten eine Gutschriftsnummer
     * - Die address kann entweder eine contactId oder manuelle Adressdaten enthalten
     * - lineItems können vom Typ 'custom' (freier Text) oder 'material' (Artikel) sein
     * - taxType kann 'net', 'gross' oder 'vatfree' sein
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Gutschriftdaten (address, lineItems, taxConditions erforderlich)
     * @param bool $finalize Wenn true, wird die Gutschrift direkt finalisiert (Standard: false)
     * @return array Erstellte Gutschrift-Metadaten mit ID
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten)
     */
    public function createCreditNote(User $user, array $data, bool $finalize = false): array
    {
        $query = $finalize ? ['finalize' => 'true'] : [];
        return $this->post($user, '/credit-notes', $data, $query);
    }

    /**
     * Gutschrift als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für eine finalisierte Gutschrift.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * WICHTIG: Diese Methode erstellt das PDF, lädt es aber nicht herunter.
     * Für den Download verwende downloadFile() mit der documentFileId.
     *
     * @see https://developers.lexoffice.io/docs/#credit-notes-endpoint-render-a-document
     *
     * Beispiel-Request:
     * GET /credit-notes/{id}/document
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Die Gutschrift muss finalisiert sein (voucherStatus != 'draft')
     * - Bei Entwürfen wird ein Fehler zurückgegeben
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann ablaufen
     * - Das PDF wird bei jedem Aufruf neu generiert
     * - Für den Download muss downloadFile() separat aufgerufen werden
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $creditNoteId Die UUID der Gutschrift
     * @return array Array mit documentFileId
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden, 406 wenn Entwurf)
     */
    public function renderCreditNotePdf(User $user, string $creditNoteId): array
    {
        return $this->get($user, "/credit-notes/{$creditNoteId}/document");
    }

    /**
     * Deeplink zur Gutschrift in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zur Gutschrift in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zur Gutschrift in Lexoffice weiterzuleiten.
     *
     * HINWEIS: Dies ist ein konstruierter Link basierend auf der Lexoffice-URL-Struktur.
     * Die Lexware API bietet keinen direkten Deeplink-Endpunkt, daher wird der Link
     * anhand der bekannten URL-Struktur von Lexoffice konstruiert.
     *
     * @param string $creditNoteId Die UUID der Gutschrift
     * @return array Array mit dem Deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/creditnote/c1d2e3f4-a5b6-7890-cdef-123456789abc"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn die Gutschrift existiert und der Benutzer Zugriff hat
     */
    public function getCreditNoteDeeplink(string $creditNoteId): array
    {
        return [
            'deeplink' => "https://app.lexoffice.de/vouchers#!/view/creditnote/{$creditNoteId}",
        ];
    }

    // =========================================================================
    // LIEFERSCHEINE (DELIVERY NOTES)
    // =========================================================================

    /**
     * Lieferscheine abrufen (paginiert)
     *
     * Ruft eine Liste von Lieferscheinen aus der Lexware API ab.
     * Die Ergebnisse werden über den Voucherlist-Endpunkt abgerufen, gefiltert nach Typ 'deliverynote'.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     *
     * @see https://developers.lexoffice.io/docs/#voucherlist-endpoint-retrieve-a-voucherlist
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "d1e2f3a4-b5c6-7890-defg-123456789hij",
     *       "voucherType": "deliverynote",
     *       "voucherStatus": "open",
     *       "voucherNumber": "LS-2024-001",
     *       "voucherDate": "2024-01-25",
     *       "createdDate": "2024-01-25T10:30:00.000+01:00",
     *       "updatedDate": "2024-01-25T10:30:00.000+01:00",
     *       "contactId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "contactName": "Muster GmbH",
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 2,
     *   "totalElements": 30,
     *   "numberOfElements": 25,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Lieferscheinen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getDeliveryNotes(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/voucherlist', [
            'voucherType' => 'deliverynote',
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelnen Lieferschein abrufen
     *
     * Ruft einen einzelnen Lieferschein anhand seiner ID aus der Lexware API ab.
     * Gibt alle Details des Lieferscheins zurück, inklusive Positionen, Adressen und Lieferinformationen.
     *
     * @see https://developers.lexoffice.io/docs/#delivery-notes-endpoint-retrieve-a-delivery-note
     *
     * Beispiel-Response:
     * {
     *   "id": "d1e2f3a4-b5c6-7890-defg-123456789hij",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-25T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-25T10:30:00.000+01:00",
     *   "version": 1,
     *   "language": "de",
     *   "archived": false,
     *   "voucherStatus": "open",
     *   "voucherNumber": "LS-2024-001",
     *   "voucherDate": "2024-01-25",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH",
     *     "street": "Musterstraße 1",
     *     "zip": "12345",
     *     "city": "Musterstadt",
     *     "countryCode": "DE"
     *   },
     *   "shippingConditions": {
     *     "shippingDate": "2024-01-26",
     *     "shippingType": "delivery"
     *   },
     *   "lineItems": [
     *     {
     *       "id": "97b98491-e953-4dc9-97a9-ae437a8052b4",
     *       "type": "custom",
     *       "name": "Produkt A",
     *       "description": "Beschreibung des Produkts",
     *       "quantity": 5,
     *       "unitName": "Stück"
     *     }
     *   ],
     *   "title": "Lieferschein",
     *   "introduction": "Hiermit liefern wir Ihnen folgende Artikel.",
     *   "remark": "Bitte prüfen Sie die Lieferung auf Vollständigkeit."
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $deliveryNoteId Die UUID des Lieferscheins
     * @return array Lieferscheindaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getDeliveryNote(User $user, string $deliveryNoteId): array
    {
        return $this->get($user, "/delivery-notes/{$deliveryNoteId}");
    }

    /**
     * Lieferschein erstellen
     *
     * Erstellt einen neuen Lieferschein in der Lexware API.
     * Lieferscheine dokumentieren die Lieferung von Waren an einen Kunden.
     * Im Gegensatz zu Rechnungen enthalten Lieferscheine keine Preisangaben.
     *
     * @see https://developers.lexoffice.io/docs/#delivery-notes-endpoint-create-a-delivery-note
     *
     * Beispiel-Request (Lieferschein an bestehenden Kontakt):
     * {
     *   "voucherDate": "2024-01-25",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a"
     *   },
     *   "shippingConditions": {
     *     "shippingDate": "2024-01-26",
     *     "shippingType": "delivery"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Produkt A",
     *       "description": "Beschreibung des Produkts",
     *       "quantity": 5,
     *       "unitName": "Stück"
     *     }
     *   ],
     *   "title": "Lieferschein",
     *   "introduction": "Hiermit liefern wir Ihnen folgende Artikel.",
     *   "remark": "Bitte prüfen Sie die Lieferung auf Vollständigkeit."
     * }
     *
     * Beispiel-Request (Lieferschein mit neuer Adresse ohne Kontakt):
     * {
     *   "voucherDate": "2024-01-25",
     *   "address": {
     *     "name": "Neue Firma GmbH",
     *     "street": "Beispielstraße 123",
     *     "zip": "54321",
     *     "city": "Beispielstadt",
     *     "countryCode": "DE"
     *   },
     *   "shippingConditions": {
     *     "shippingDate": "2024-01-26",
     *     "shippingType": "pickup"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Produkt B",
     *       "quantity": 10,
     *       "unitName": "Karton"
     *     }
     *   ]
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "d1e2f3a4-b5c6-7890-defg-123456789hij",
     *   "resourceUri": "https://api.lexoffice.io/v1/delivery-notes/d1e2f3a4-b5c6-7890-defg-123456789hij",
     *   "createdDate": "2024-01-25T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-25T10:30:00.000+01:00",
     *   "version": 0
     * }
     *
     * Hinweise:
     * - Die address kann entweder eine contactId oder manuelle Adressdaten enthalten
     * - lineItems enthalten nur Mengenangaben, keine Preise
     * - shippingType kann 'delivery', 'pickup', 'express' oder ähnlich sein
     * - shippingDate gibt das geplante Lieferdatum an
     * - Lieferscheine haben keinen finalize-Parameter, da sie keine Preise enthalten
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Lieferscheindaten (address, lineItems erforderlich)
     * @param bool $finalize Wenn true, wird der Lieferschein direkt finalisiert (Standard: false)
     * @return array Erstellte Lieferschein-Metadaten mit ID
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten)
     */
    public function createDeliveryNote(User $user, array $data, bool $finalize = false): array
    {
        $query = $finalize ? ['finalize' => 'true'] : [];
        return $this->post($user, '/delivery-notes', $data, $query);
    }

    /**
     * Lieferschein als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für einen finalisierten Lieferschein.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * WICHTIG: Diese Methode erstellt das PDF, lädt es aber nicht herunter.
     * Für den Download verwende downloadFile() mit der documentFileId.
     *
     * @see https://developers.lexoffice.io/docs/#delivery-notes-endpoint-render-a-document
     *
     * Beispiel-Request:
     * GET /delivery-notes/{id}/document
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Der Lieferschein muss finalisiert sein (voucherStatus != 'draft')
     * - Bei Entwürfen wird ein Fehler zurückgegeben
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann ablaufen
     * - Das PDF wird bei jedem Aufruf neu generiert
     * - Für den Download muss downloadFile() separat aufgerufen werden
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $deliveryNoteId Die UUID des Lieferscheins
     * @return array Array mit documentFileId
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden, 406 wenn Entwurf)
     */
    public function renderDeliveryNotePdf(User $user, string $deliveryNoteId): array
    {
        return $this->get($user, "/delivery-notes/{$deliveryNoteId}/document");
    }

    /**
     * Deeplink zum Lieferschein in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zum Lieferschein in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zum Lieferschein in Lexoffice weiterzuleiten.
     *
     * HINWEIS: Dies ist ein konstruierter Link basierend auf der Lexoffice-URL-Struktur.
     * Die Lexware API bietet keinen direkten Deeplink-Endpunkt, daher wird der Link
     * anhand der bekannten URL-Struktur von Lexoffice konstruiert.
     *
     * @param string $deliveryNoteId Die UUID des Lieferscheins
     * @return array Array mit dem Deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/delivery-note/d1e2f3a4-b5c6-7890-defg-123456789hij"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn der Lieferschein existiert und der Benutzer Zugriff hat
     */
    public function getDeliveryNoteDeeplink(string $deliveryNoteId): array
    {
        return [
            'deeplink' => "https://app.lexoffice.de/vouchers#!/view/delivery-note/{$deliveryNoteId}",
        ];
    }

    // =========================================================================
    // MAHNUNGEN (DUNNINGS)
    // =========================================================================

    /**
     * Mahnungen abrufen (paginiert)
     *
     * Ruft eine Liste von Mahnungen aus der Lexware API ab.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     * Mahnungen werden erstellt, um offene Forderungen einzutreiben.
     *
     * @see https://developers.lexoffice.io/docs/#dunnings-endpoint
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *       "voucherType": "dunning",
     *       "voucherStatus": "open",
     *       "voucherNumber": "MA-2024-001",
     *       "voucherDate": "2024-01-25",
     *       "createdDate": "2024-01-25T10:30:00.000+01:00",
     *       "updatedDate": "2024-01-25T10:30:00.000+01:00",
     *       "contactName": "Muster GmbH",
     *       "totalAmount": 1190.00,
     *       "currency": "EUR",
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 3,
     *   "totalElements": 75,
     *   "size": 25,
     *   "number": 0,
     *   "numberOfElements": 25
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Mahnungen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getDunnings(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/voucherlist', [
            'voucherType' => 'dunning',
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelne Mahnung abrufen
     *
     * Ruft eine einzelne Mahnung anhand ihrer ID aus der Lexware API ab.
     * Gibt alle Details der Mahnung zurück, inklusive Positionen, Adressen und Zahlungsinformationen.
     *
     * @see https://developers.lexoffice.io/docs/#dunnings-endpoint-retrieve-a-dunning
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-25T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-25T10:30:00.000+01:00",
     *   "version": 1,
     *   "language": "de",
     *   "archived": false,
     *   "voucherStatus": "open",
     *   "voucherNumber": "MA-2024-001",
     *   "voucherDate": "2024-01-25",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH",
     *     "street": "Musterstraße 1",
     *     "zip": "12345",
     *     "city": "Musterstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "id": "97b98491-e953-4dc9-97a9-ae437a8052b4",
     *       "type": "custom",
     *       "name": "Offener Rechnungsbetrag RE-2024-001",
     *       "description": "Zahlungserinnerung für Rechnung vom 15.12.2023",
     *       "quantity": 1,
     *       "unitName": "Stück",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 1000.00,
     *         "grossAmount": 1190.00,
     *         "taxRatePercentage": 19
     *       },
     *       "lineItemAmount": 1190.00
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR",
     *     "totalNetAmount": 1000.00,
     *     "totalGrossAmount": 1190.00,
     *     "totalTaxAmount": 190.00
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "paymentConditions": {
     *     "paymentTermLabel": "Sofort fällig",
     *     "paymentTermDuration": 0
     *   },
     *   "relatedVouchers": [
     *     {
     *       "id": "b2c3d4e5-f6a7-8901-bcde-f23456789012",
     *       "voucherNumber": "RE-2024-001",
     *       "voucherType": "invoice"
     *     }
     *   ],
     *   "title": "1. Mahnung",
     *   "introduction": "Leider konnten wir für folgende Rechnung noch keinen Zahlungseingang feststellen.",
     *   "remark": "Bitte überweisen Sie den offenen Betrag innerhalb von 7 Tagen."
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $dunningId Die UUID der Mahnung
     * @return array Mahnungsdaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getDunning(User $user, string $dunningId): array
    {
        return $this->get($user, "/dunnings/{$dunningId}");
    }

    /**
     * Mahnung erstellen
     *
     * Erstellt eine neue Mahnung in der Lexware API.
     * Mahnungen werden verwendet, um Kunden an offene Forderungen zu erinnern.
     * Eine Mahnung bezieht sich typischerweise auf eine oder mehrere unbezahlte Rechnungen.
     *
     * @see https://developers.lexoffice.io/docs/#dunnings-endpoint-create-a-dunning
     *
     * Beispiel-Request (Mahnung an bestehenden Kontakt mit Bezug auf Rechnung):
     * {
     *   "voucherDate": "2024-01-25",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Offener Rechnungsbetrag RE-2024-001",
     *       "description": "Zahlungserinnerung für Rechnung vom 15.12.2023",
     *       "quantity": 1,
     *       "unitName": "Stück",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 1000.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "paymentConditions": {
     *     "paymentTermLabel": "Sofort fällig",
     *     "paymentTermDuration": 0
     *   },
     *   "title": "1. Mahnung",
     *   "introduction": "Leider konnten wir für folgende Rechnung noch keinen Zahlungseingang feststellen.",
     *   "remark": "Bitte überweisen Sie den offenen Betrag innerhalb von 7 Tagen."
     * }
     *
     * Beispiel-Request (Mahnung mit Mahngebühren):
     * {
     *   "voucherDate": "2024-02-05",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a"
     *   },
     *   "lineItems": [
     *     {
     *       "type": "custom",
     *       "name": "Offener Rechnungsbetrag RE-2024-001",
     *       "quantity": 1,
     *       "unitName": "Stück",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 1000.00,
     *         "taxRatePercentage": 19
     *       }
     *     },
     *     {
     *       "type": "custom",
     *       "name": "Mahngebühr",
     *       "quantity": 1,
     *       "unitName": "pauschal",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 5.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR"
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "title": "2. Mahnung",
     *   "introduction": "Trotz unserer Zahlungserinnerung ist der Betrag noch offen.",
     *   "remark": "Bei weiterem Zahlungsverzug behalten wir uns rechtliche Schritte vor."
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "resourceUri": "https://api.lexoffice.io/v1/dunnings/a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "createdDate": "2024-01-25T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-25T10:30:00.000+01:00",
     *   "version": 0
     * }
     *
     * Hinweise:
     * - address kann entweder eine contactId oder manuelle Adressdaten enthalten
     * - lineItems müssen Preisangaben enthalten (im Gegensatz zu Lieferscheinen)
     * - taxType kann 'net' (Netto) oder 'gross' (Brutto) sein
     * - paymentConditions definieren die Zahlungsfrist
     * - relatedVouchers können verknüpfte Rechnungen referenzieren
     * - finalize=true finalisiert die Mahnung direkt (dann nicht mehr bearbeitbar)
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Mahnungsdaten (address, lineItems, totalPrice erforderlich)
     * @param bool $finalize Wenn true, wird die Mahnung direkt finalisiert (Standard: false)
     * @return array Erstellte Mahnungs-Metadaten mit ID
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten)
     */
    public function createDunning(User $user, array $data, bool $finalize = false): array
    {
        $query = $finalize ? ['finalize' => 'true'] : [];
        return $this->post($user, '/dunnings', $data, $query);
    }

    /**
     * Mahnung als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für eine finalisierte Mahnung.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * WICHTIG: Diese Methode erstellt das PDF, lädt es aber nicht herunter.
     * Für den Download verwende downloadFile() mit der documentFileId.
     *
     * @see https://developers.lexoffice.io/docs/#dunnings-endpoint-render-a-document
     *
     * Beispiel-Request:
     * GET /dunnings/{id}/document
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Die Mahnung muss finalisiert sein (voucherStatus != 'draft')
     * - Bei Entwürfen wird ein Fehler zurückgegeben
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann ablaufen
     * - Das PDF wird bei jedem Aufruf neu generiert
     * - Für den Download muss downloadFile() separat aufgerufen werden
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $dunningId Die UUID der Mahnung
     * @return array Array mit documentFileId
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden, 406 wenn Entwurf)
     */
    public function renderDunningPdf(User $user, string $dunningId): array
    {
        return $this->get($user, "/dunnings/{$dunningId}/document");
    }

    /**
     * Deeplink zur Mahnung in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zur Mahnung in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zur Mahnung in Lexoffice weiterzuleiten.
     *
     * HINWEIS: Dies ist ein konstruierter Link basierend auf der Lexoffice-URL-Struktur.
     * Die Lexware API bietet keinen direkten Deeplink-Endpunkt, daher wird der Link
     * anhand der bekannten URL-Struktur von Lexoffice konstruiert.
     *
     * @param string $dunningId Die UUID der Mahnung
     * @return array Array mit dem Deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/dunning/a1b2c3d4-e5f6-7890-abcd-ef1234567890"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn die Mahnung existiert und der Benutzer Zugriff hat
     */
    public function getDunningDeeplink(string $dunningId): array
    {
        return [
            'deeplink' => "https://app.lexoffice.de/vouchers#!/view/dunning/{$dunningId}",
        ];
    }

    // =========================================================================
    // ANZAHLUNGSRECHNUNGEN (DOWN PAYMENT INVOICES)
    // =========================================================================

    /**
     * Alle Anzahlungsrechnungen abrufen (Listenansicht)
     *
     * Ruft eine Liste von Anzahlungsrechnungen aus der Lexware API ab.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     * Anzahlungsrechnungen werden verwendet, um Teilzahlungen vor der Leistungserbringung abzurechnen.
     *
     * @see https://developers.lexoffice.io/docs/#down-payment-invoices-endpoint
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *       "voucherType": "downpaymentinvoice",
     *       "voucherStatus": "open",
     *       "voucherNumber": "AR-2024-001",
     *       "voucherDate": "2024-01-15",
     *       "createdDate": "2024-01-15T08:30:00.000+01:00",
     *       "updatedDate": "2024-01-15T08:30:00.000+01:00",
     *       "contactName": "Muster GmbH",
     *       "totalAmount": 1190.00,
     *       "currency": "EUR",
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 3,
     *   "totalElements": 75,
     *   "size": 25,
     *   "number": 0,
     *   "numberOfElements": 25
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Anzahlungsrechnungen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getDownPaymentInvoices(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/voucherlist', [
            'voucherType' => 'downpaymentinvoice',
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelne Anzahlungsrechnung abrufen
     *
     * Ruft eine einzelne Anzahlungsrechnung anhand ihrer ID aus der Lexware API ab.
     * Gibt alle Details der Anzahlungsrechnung zurück, inklusive Positionen, Adressen und Zahlungsinformationen.
     *
     * @see https://developers.lexoffice.io/docs/#down-payment-invoices-endpoint-retrieve-a-down-payment-invoice
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-15T08:30:00.000+01:00",
     *   "updatedDate": "2024-01-15T08:30:00.000+01:00",
     *   "version": 1,
     *   "language": "de",
     *   "archived": false,
     *   "voucherStatus": "open",
     *   "voucherNumber": "AR-2024-001",
     *   "voucherDate": "2024-01-15",
     *   "dueDate": "2024-01-29",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH",
     *     "street": "Musterstraße 1",
     *     "zip": "12345",
     *     "city": "Musterstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "id": "97b98491-e953-4dc9-97a9-ae437a8052b4",
     *       "type": "custom",
     *       "name": "Anzahlung für Projekt X",
     *       "description": "Erste Anzahlung (30%)",
     *       "quantity": 1,
     *       "unitName": "Stück",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 1000.00,
     *         "grossAmount": 1190.00,
     *         "taxRatePercentage": 19
     *       },
     *       "lineItemAmount": 1190.00
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR",
     *     "totalNetAmount": 1000.00,
     *     "totalGrossAmount": 1190.00,
     *     "totalTaxAmount": 190.00
     *   },
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "paymentConditions": {
     *     "paymentTermLabel": "Zahlbar innerhalb von 14 Tagen",
     *     "paymentTermDuration": 14
     *   },
     *   "title": "Anzahlungsrechnung",
     *   "introduction": "Wie vereinbart stellen wir Ihnen folgende Anzahlung in Rechnung.",
     *   "remark": "Vielen Dank für Ihren Auftrag!"
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $downPaymentInvoiceId Die UUID der Anzahlungsrechnung
     * @return array Anzahlungsrechnungsdaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getDownPaymentInvoice(User $user, string $downPaymentInvoiceId): array
    {
        return $this->get($user, "/down-payment-invoices/{$downPaymentInvoiceId}");
    }

    /**
     * Anzahlungsrechnung als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für eine finalisierte Anzahlungsrechnung.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * WICHTIG: Diese Methode erstellt das PDF, lädt es aber nicht herunter.
     * Für den Download verwende downloadFile() mit der documentFileId.
     *
     * @see https://developers.lexoffice.io/docs/#down-payment-invoices-endpoint-render-a-document
     *
     * Beispiel-Request:
     * GET /down-payment-invoices/{id}/document
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Die Anzahlungsrechnung muss finalisiert sein (voucherStatus != 'draft')
     * - Bei Entwürfen wird ein Fehler zurückgegeben
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann ablaufen
     * - Das PDF wird bei jedem Aufruf neu generiert
     * - Für den Download muss downloadFile() separat aufgerufen werden
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $downPaymentInvoiceId Die UUID der Anzahlungsrechnung
     * @return array Array mit documentFileId
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 nicht gefunden, 406 wenn Entwurf)
     */
    public function renderDownPaymentInvoicePdf(User $user, string $downPaymentInvoiceId): array
    {
        return $this->get($user, "/down-payment-invoices/{$downPaymentInvoiceId}/document");
    }

    /**
     * Deeplink zur Anzahlungsrechnung in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zur Anzahlungsrechnung in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zur Anzahlungsrechnung in Lexoffice weiterzuleiten.
     *
     * HINWEIS: Dies ist ein konstruierter Link basierend auf der Lexoffice-URL-Struktur.
     * Die Lexware API bietet keinen direkten Deeplink-Endpunkt, daher wird der Link
     * anhand der bekannten URL-Struktur von Lexoffice konstruiert.
     *
     * @param string $downPaymentInvoiceId Die UUID der Anzahlungsrechnung
     * @return array Array mit dem Deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/downpaymentinvoice/a1b2c3d4-e5f6-7890-abcd-ef1234567890"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn die Anzahlungsrechnung existiert und der Benutzer Zugriff hat
     */
    public function getDownPaymentInvoiceDeeplink(string $downPaymentInvoiceId): array
    {
        return [
            'deeplink' => "https://app.lexoffice.de/vouchers#!/view/downpaymentinvoice/{$downPaymentInvoiceId}",
        ];
    }

    // =========================================================================
    // PROFIL & VERBINDUNG
    // =========================================================================

    /**
     * Profil abrufen
     *
     * Ruft das Profil des verbundenen Lexoffice-Kontos aus der Lexware API ab.
     * Der Profile-Endpunkt gibt Informationen über die Organisation zurück,
     * die mit dem verwendeten API-Token verknüpft ist.
     *
     * @see https://developers.lexoffice.io/docs/#profile-endpoint
     *
     * Beispiel-Request:
     * GET /profile
     *
     * Beispiel-Response:
     * {
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "companyName": "Muster GmbH",
     *   "created": {
     *     "date": "2020-06-15",
     *     "userId": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *     "userName": "Max Mustermann"
     *   },
     *   "connectionId": "a2691815-4f13-48e8-a7e9-3990be5b5f1d",
     *   "taxType": "net",
     *   "smallBusiness": false,
     *   "subscriptionStatus": "active"
     * }
     *
     * Response-Felder:
     * - organizationId (string): Die eindeutige UUID der Organisation in Lexoffice
     * - companyName (string): Der Name des Unternehmens/der Organisation
     * - created (object): Informationen zur Erstellung des Kontos
     *   - date (string): Datum der Kontoerstellung im Format YYYY-MM-DD
     *   - userId (string): UUID des Benutzers, der das Konto erstellt hat
     *   - userName (string): Name des Benutzers
     * - connectionId (string): UUID der API-Verbindung
     * - taxType (string): Standard-Steuertyp ("net" oder "gross")
     * - smallBusiness (bool): true wenn Kleinunternehmerregelung nach §19 UStG gilt
     * - subscriptionStatus (string): Status des Lexoffice-Abonnements
     *
     * Hinweise:
     * - Dieser Endpunkt erfordert keine speziellen Berechtigungen
     * - Kann verwendet werden, um die Gültigkeit des API-Tokens zu prüfen
     * - Gibt Informationen über das verbundene Konto zurück, nicht über den API-Benutzer
     * - Die subscriptionStatus kann sein: "active", "trial", "cancelled", etc.
     * - smallBusiness=true bedeutet, dass keine Umsatzsteuer ausgewiesen wird
     *
     * @param User $user Der authentifizierte Benutzer
     * @return array Profildaten der verbundenen Organisation
     * @throws LexwareApiException Bei API-Fehlern (z.B. 401 bei ungültigem Token)
     */
    public function getProfile(User $user): array
    {
        return $this->get($user, '/profile');
    }

    /**
     * Verbindung testen
     *
     * @throws LexwareApiException
     */
    public function testConnection(User $user): array
    {
        return $this->getProfile($user);
    }

    // =========================================================================
    // ARTIKEL (ARTICLES)
    // =========================================================================

    /**
     * Artikel abrufen (paginiert)
     *
     * Ruft eine Liste von Artikeln aus der Lexware API ab.
     * Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250.
     *
     * @see https://developers.lexoffice.io/docs/#articles-endpoint-retrieve-a-list-of-articles
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "eb46d328-e1dc-11ee-8e52-2fadfc15a567",
     *       "articleNumber": "ART-001",
     *       "title": "Beispielartikel",
     *       "description": "Beschreibung des Artikels",
     *       "type": "PRODUCT",
     *       "unitName": "Stück",
     *       "price": {
     *         "netPrice": 100.00,
     *         "grossPrice": 119.00,
     *         "taxRate": 19.0
     *       },
     *       "version": 1
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 5,
     *   "totalElements": 100,
     *   "numberOfElements": 25,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Artikeln
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getArticles(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/articles', [
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelnen Artikel abrufen
     *
     * Ruft einen einzelnen Artikel anhand seiner ID aus der Lexware API ab.
     *
     * @see https://developers.lexoffice.io/docs/#articles-endpoint-retrieve-an-article
     *
     * Beispiel-Response:
     * {
     *   "id": "eb46d328-e1dc-11ee-8e52-2fadfc15a567",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-16T14:20:00.000+01:00",
     *   "archived": false,
     *   "articleNumber": "ART-001",
     *   "title": "Beispielartikel",
     *   "description": "Detaillierte Beschreibung des Artikels",
     *   "type": "PRODUCT",
     *   "unitName": "Stück",
     *   "price": {
     *     "netPrice": 100.00,
     *     "grossPrice": 119.00,
     *     "leadingPrice": "NET",
     *     "taxRate": 19.0
     *   },
     *   "version": 1
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $articleId Die UUID des Artikels
     * @return array Artikeldaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getArticle(User $user, string $articleId): array
    {
        return $this->get($user, "/articles/{$articleId}");
    }

    /**
     * Artikel erstellen
     *
     * Erstellt einen neuen Artikel in der Lexware API.
     * Der Typ (type) bestimmt, ob es sich um ein Produkt oder eine Dienstleistung handelt.
     *
     * @see https://developers.lexoffice.io/docs/#articles-endpoint-create-an-article
     *
     * Beispiel-Request:
     * {
     *   "title": "Neuer Artikel",
     *   "description": "Beschreibung des neuen Artikels",
     *   "type": "PRODUCT",
     *   "articleNumber": "ART-002",
     *   "unitName": "Stück",
     *   "price": {
     *     "netPrice": 50.00,
     *     "grossPrice": 59.50,
     *     "leadingPrice": "NET",
     *     "taxRate": 19.0
     *   }
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *   "resourceUri": "https://api.lexoffice.io/v1/articles/66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *   "createdDate": "2024-01-17T09:00:00.000+01:00",
     *   "updatedDate": "2024-01-17T09:00:00.000+01:00",
     *   "version": 0
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Artikeldaten (title, type erforderlich)
     * @return array Erstellte Artikel-Metadaten mit ID
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten)
     */
    public function createArticle(User $user, array $data): array
    {
        return $this->post($user, '/articles', $data);
    }

    /**
     * Artikel aktualisieren
     *
     * Aktualisiert einen bestehenden Artikel in der Lexware API.
     * Die Version muss im Request-Body mitgegeben werden (Optimistic Locking).
     *
     * @see https://developers.lexoffice.io/docs/#articles-endpoint-update-an-article
     *
     * Beispiel-Request:
     * {
     *   "title": "Aktualisierter Artikel",
     *   "description": "Neue Beschreibung",
     *   "type": "PRODUCT",
     *   "articleNumber": "ART-001",
     *   "unitName": "Stück",
     *   "price": {
     *     "netPrice": 120.00,
     *     "grossPrice": 142.80,
     *     "leadingPrice": "NET",
     *     "taxRate": 19.0
     *   },
     *   "version": 1
     * }
     *
     * Beispiel-Response:
     * {
     *   "id": "eb46d328-e1dc-11ee-8e52-2fadfc15a567",
     *   "resourceUri": "https://api.lexoffice.io/v1/articles/eb46d328-e1dc-11ee-8e52-2fadfc15a567",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-18T11:45:00.000+01:00",
     *   "version": 2
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $articleId Die UUID des zu aktualisierenden Artikels
     * @param array $data Aktualisierte Artikeldaten (version erforderlich)
     * @return array Aktualisierte Artikel-Metadaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 409 bei Versionskonflikt)
     */
    public function updateArticle(User $user, string $articleId, array $data): array
    {
        return $this->put($user, "/articles/{$articleId}", $data);
    }

    /**
     * Artikel löschen
     *
     * Löscht einen Artikel aus der Lexware API.
     * Hinweis: Artikel können nur gelöscht werden, wenn sie nicht in Belegen verwendet werden.
     *
     * @see https://developers.lexoffice.io/docs/#articles-endpoint-delete-an-article
     *
     * Beispiel-Response bei Erfolg:
     * HTTP 204 No Content (leerer Response-Body)
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $articleId Die UUID des zu löschenden Artikels
     * @return array Leeres Array bei Erfolg
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden, 409 wenn in Verwendung)
     */
    public function deleteArticle(User $user, string $articleId): array
    {
        return $this->delete($user, "/articles/{$articleId}");
    }

    // =========================================================================
    // LÄNDER (COUNTRIES)
    // =========================================================================

    /**
     * Länder abrufen
     *
     * Ruft die Liste aller verfügbaren Länder aus der Lexware API ab.
     * Die Länder können in Adressen (billing, shipping) verwendet werden.
     * Der Ländercode (countryCode) entspricht dem ISO 3166-1 alpha-2 Standard.
     *
     * @see https://developers.lexoffice.io/docs/#countries-endpoint
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/countries
     *
     * Beispiel-Response:
     * [
     *   {
     *     "countryCode": "DE",
     *     "countryNameDE": "Deutschland",
     *     "countryNameEN": "Germany",
     *     "taxClassification": "intraCommunity"
     *   },
     *   {
     *     "countryCode": "AT",
     *     "countryNameDE": "Österreich",
     *     "countryNameEN": "Austria",
     *     "taxClassification": "intraCommunity"
     *   },
     *   {
     *     "countryCode": "CH",
     *     "countryNameDE": "Schweiz",
     *     "countryNameEN": "Switzerland",
     *     "taxClassification": "thirdPartyCountry"
     *   },
     *   {
     *     "countryCode": "US",
     *     "countryNameDE": "Vereinigte Staaten von Amerika",
     *     "countryNameEN": "United States of America",
     *     "taxClassification": "thirdPartyCountry"
     *   }
     * ]
     *
     * Hinweise:
     * - Die Liste enthält alle für Lexware verfügbaren Länder
     * - taxClassification kann sein:
     *   - "de" (Deutschland)
     *   - "intraCommunity" (EU-Mitgliedsstaaten)
     *   - "thirdPartyCountry" (Drittländer außerhalb der EU)
     * - Der countryCode entspricht ISO 3166-1 alpha-2 (z.B. "DE", "AT", "CH")
     *
     * @param User $user Der authentifizierte Benutzer
     * @return array Liste aller verfügbaren Länder
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getCountries(User $user): array
    {
        return $this->get($user, '/countries');
    }

    // =========================================================================
    // BUCHUNGSKATEGORIEN (POSTING CATEGORIES)
    // =========================================================================

    /**
     * Buchungskategorien abrufen
     *
     * Ruft die Liste aller verfügbaren Buchungskategorien aus der Lexware API ab.
     * Buchungskategorien werden verwendet, um Einnahmen und Ausgaben in der Buchhaltung
     * zu kategorisieren und den entsprechenden Konten zuzuordnen.
     *
     * @see https://developers.lexoffice.io/docs/#posting-categories-endpoint
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/posting-categories
     *
     * Beispiel-Response:
     * [
     *   {
     *     "id": "8f8664a1-fd86-11e1-a21f-0800200c9a66",
     *     "name": "Erlöse 19%",
     *     "type": "income",
     *     "contactRequired": false,
     *     "splitAllowed": true,
     *     "groupName": "Erlöse"
     *   },
     *   {
     *     "id": "9075a4e3-66de-4795-a016-3889feca0d20",
     *     "name": "Erlöse 7%",
     *     "type": "income",
     *     "contactRequired": false,
     *     "splitAllowed": true,
     *     "groupName": "Erlöse"
     *   },
     *   {
     *     "id": "7c112b66-0565-479c-bc18-5845e080880a",
     *     "name": "Wareneinkauf 19%",
     *     "type": "expense",
     *     "contactRequired": false,
     *     "splitAllowed": true,
     *     "groupName": "Wareneinkauf"
     *   },
     *   {
     *     "id": "cf0a3e33-4156-4e3f-8a3d-46c2a08f9a14",
     *     "name": "Bürobedarf",
     *     "type": "expense",
     *     "contactRequired": false,
     *     "splitAllowed": true,
     *     "groupName": "Sonstige Ausgaben"
     *   },
     *   {
     *     "id": "a1f9a8d5-e9c4-4b8a-8f2e-3d5c6b7a8e9f",
     *     "name": "Privateinlage",
     *     "type": "privatewithdrawal",
     *     "contactRequired": false,
     *     "splitAllowed": false,
     *     "groupName": "Privatbuchungen"
     *   }
     * ]
     *
     * Hinweise:
     * - Die Liste enthält alle für den Benutzer verfügbaren Buchungskategorien
     * - type kann sein:
     *   - "income" (Einnahmen/Erlöse)
     *   - "expense" (Ausgaben/Aufwendungen)
     *   - "depreciationexpense" (Abschreibungen)
     *   - "privatewithdrawal" (Privatentnahmen/-einlagen)
     * - contactRequired gibt an, ob ein Kontakt bei der Buchung erforderlich ist
     * - splitAllowed gibt an, ob die Kategorie für Split-Buchungen verwendet werden kann
     * - groupName gruppiert ähnliche Kategorien für die Anzeige in der UI
     * - Diese Kategorien werden bei der Erfassung von Belegen und Buchungen verwendet
     *
     * @param User $user Der authentifizierte Benutzer
     * @return array Liste aller verfügbaren Buchungskategorien
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getPostingCategories(User $user): array
    {
        return $this->get($user, '/posting-categories');
    }

    // =========================================================================
    // ZAHLUNGSBEDINGUNGEN (PAYMENT CONDITIONS)
    // =========================================================================

    /**
     * Zahlungsbedingungen abrufen
     *
     * Ruft die Liste aller Zahlungsbedingungen aus der Lexware API ab.
     * Zahlungsbedingungen definieren Zahlungsfristen und -konditionen, die auf Belegen
     * (Rechnungen, Angebote, Auftragsbestätigungen etc.) verwendet werden können.
     * Sie bestehen aus einem Label (Beschreibungstext) und einer Zahlungsfrist in Tagen.
     *
     * @see https://developers.lexoffice.io/docs/#payment-conditions-endpoint
     *
     * Beispiel-Request:
     * GET /payment-conditions
     *
     * Beispiel-Response:
     * [
     *   {
     *     "id": "e9066f04-8cc7-4616-93f8-ac9571ec5f71",
     *     "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *     "paymentTermLabelTemplate": "Zahlbar innerhalb von {paymentTermDuration} Tagen.",
     *     "paymentTermDuration": 30,
     *     "paymentDiscountConditions": null
     *   },
     *   {
     *     "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *     "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *     "paymentTermLabelTemplate": "Zahlbar sofort ohne Abzug.",
     *     "paymentTermDuration": 0,
     *     "paymentDiscountConditions": null
     *   },
     *   {
     *     "id": "b2c3d4e5-f6a7-8901-bcde-f23456789012",
     *     "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *     "paymentTermLabelTemplate": "Zahlbar innerhalb von {paymentTermDuration} Tagen. Bei Zahlung innerhalb von {paymentDiscountDuration} Tagen gewähren wir {paymentDiscountPercent}% Skonto.",
     *     "paymentTermDuration": 30,
     *     "paymentDiscountConditions": {
     *       "discountPercentage": 2.00,
     *       "discountRange": 14
     *     }
     *   },
     *   {
     *     "id": "c3d4e5f6-a7b8-9012-cdef-345678901234",
     *     "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *     "paymentTermLabelTemplate": "Zahlbar innerhalb von {paymentTermDuration} Tagen netto.",
     *     "paymentTermDuration": 14,
     *     "paymentDiscountConditions": null
     *   }
     * ]
     *
     * Hinweise:
     * - paymentTermLabelTemplate enthält Platzhalter, die bei der Belegstellung ersetzt werden:
     *   - {paymentTermDuration}: Zahlungsfrist in Tagen
     *   - {paymentDiscountDuration}: Skontofrist in Tagen (wenn Skonto definiert)
     *   - {paymentDiscountPercent}: Skontosatz in Prozent (wenn Skonto definiert)
     * - paymentTermDuration gibt die Zahlungsfrist in Tagen an (0 = sofort fällig)
     * - paymentDiscountConditions enthält optionale Skonto-Konditionen:
     *   - discountPercentage: Skontosatz in Prozent (z.B. 2.00 für 2%)
     *   - discountRange: Anzahl Tage für Skontogewährung
     * - Wenn paymentDiscountConditions null ist, gibt es keinen Skonto
     * - Die Liste enthält alle vom Benutzer angelegten Zahlungsbedingungen
     * - Diese Zahlungsbedingungen können bei Belegerstellung verwendet werden
     *
     * @param User $user Der authentifizierte Benutzer
     * @return array Liste aller verfügbaren Zahlungsbedingungen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getPaymentConditions(User $user): array
    {
        return $this->get($user, '/payment-conditions');
    }

    // =========================================================================
    // ZAHLUNGEN (PAYMENTS)
    // =========================================================================

    /**
     * Zahlungen abrufen (paginiert)
     *
     * Ruft eine Liste von Zahlungsinformationen aus der Lexware API ab.
     * Zahlungen werden automatisch mit Rechnungen, Gutschriften und anderen Belegen verknüpft.
     * Dieser Endpunkt gibt Informationen zu erfassten Zahlungseingängen und -ausgängen zurück.
     *
     * @see https://developers.lexoffice.io/docs/#payments-endpoint
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Request:
     * GET /payments?page=0&size=25
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "paymentId": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *       "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "voucherId": "b2c3d4e5-f6a7-8901-bcde-f23456789012",
     *       "voucherType": "invoice",
     *       "voucherNumber": "RE-2024-001",
     *       "paymentDate": "2024-01-20",
     *       "amount": 1190.00,
     *       "currency": "EUR",
     *       "paymentType": "incoming",
     *       "paymentMethod": "bankTransfer"
     *     },
     *     {
     *       "paymentId": "c3d4e5f6-a7b8-9012-cdef-345678901234",
     *       "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "voucherId": "d4e5f6a7-b8c9-0123-defg-456789012345",
     *       "voucherType": "invoice",
     *       "voucherNumber": "RE-2024-002",
     *       "paymentDate": "2024-01-22",
     *       "amount": 595.00,
     *       "currency": "EUR",
     *       "paymentType": "incoming",
     *       "paymentMethod": "cash"
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 5,
     *   "totalElements": 120,
     *   "size": 25,
     *   "number": 0,
     *   "numberOfElements": 25
     * }
     *
     * Hinweise:
     * - paymentType kann sein: "incoming" (Zahlungseingang) oder "outgoing" (Zahlungsausgang)
     * - paymentMethod kann sein: "bankTransfer", "cash", "creditCard", "debitCard", "paypal", "other"
     * - voucherType bezieht sich auf den verknüpften Beleg (invoice, creditnote, etc.)
     * - Die Zahlungen werden chronologisch nach paymentDate sortiert (neueste zuerst)
     * - Stornierte Zahlungen werden nicht in der Liste angezeigt
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von Zahlungen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getPayments(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/payments', [
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelne Zahlung abrufen
     *
     * Ruft eine einzelne Zahlung anhand ihrer ID aus der Lexware API ab.
     * Gibt alle Details zur Zahlung zurück, inklusive verknüpftem Beleg und Zahlungsinformationen.
     *
     * @see https://developers.lexoffice.io/docs/#payments-endpoint-retrieve-a-payment
     *
     * Beispiel-Request:
     * GET /payments/a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * Beispiel-Response:
     * {
     *   "paymentId": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-20T10:30:00.000+01:00",
     *   "voucherId": "b2c3d4e5-f6a7-8901-bcde-f23456789012",
     *   "voucherType": "invoice",
     *   "voucherNumber": "RE-2024-001",
     *   "voucherStatus": "paid",
     *   "paymentDate": "2024-01-20",
     *   "amount": 1190.00,
     *   "currency": "EUR",
     *   "paymentType": "incoming",
     *   "paymentMethod": "bankTransfer",
     *   "reference": "RE-2024-001 Muster GmbH",
     *   "contact": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH"
     *   },
     *   "remainingAmount": 0.00,
     *   "paidAmount": 1190.00
     * }
     *
     * Hinweise:
     * - remainingAmount zeigt den noch offenen Betrag des verknüpften Belegs
     * - paidAmount zeigt den gesamten bereits gezahlten Betrag des Belegs
     * - Der voucherStatus des verknüpften Belegs wird automatisch aktualisiert:
     *   - "open": Noch offener Betrag vorhanden
     *   - "paid": Vollständig bezahlt
     *   - "paidoff": Überbezahlt oder ausgeglichen
     * - reference enthält die Referenz/Verwendungszweck der Zahlung
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $paymentId Die UUID der Zahlung
     * @return array Zahlungsdaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getPayment(User $user, string $paymentId): array
    {
        return $this->get($user, "/payments/{$paymentId}");
    }

    // =========================================================================
    // DRUCKVORLAGEN (PRINT LAYOUTS)
    // =========================================================================

    /**
     * Druckvorlagen abrufen
     *
     * Ruft die Liste aller verfügbaren Druckvorlagen aus der Lexware API ab.
     * Druckvorlagen (Print Layouts) definieren das Layout und Design für den Druck
     * von Dokumenten wie Rechnungen, Angeboten, Auftragsbestätigungen, Lieferscheinen,
     * Gutschriften, Mahnungen und Anzahlungsrechnungen.
     *
     * @see https://developers.lexoffice.io/docs/#print-layouts-endpoint
     *
     * Beispiel-Request:
     * GET /print-layouts
     *
     * Beispiel-Response:
     * [
     *   {
     *     "id": "28c212c4-b6dd-11ee-b80a-dbc65f4fa848",
     *     "name": "Standard",
     *     "default": true,
     *     "color": "#2196f3"
     *   },
     *   {
     *     "id": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f",
     *     "name": "Modern",
     *     "default": false,
     *     "color": "#4caf50"
     *   },
     *   {
     *     "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *     "name": "Klassisch",
     *     "default": false,
     *     "color": "#607d8b"
     *   }
     * ]
     *
     * Hinweise:
     * - id: Eindeutige UUID der Druckvorlage
     * - name: Anzeigename der Druckvorlage (vom Benutzer definiert)
     * - default: Gibt an, ob dies die Standard-Druckvorlage ist (nur eine kann true sein)
     * - color: Akzentfarbe der Druckvorlage als Hex-Code
     * - Die Liste enthält alle vom Benutzer angelegten Druckvorlagen
     * - Diese Druckvorlagen können bei der PDF-Generierung von Belegen verwendet werden
     * - Die Standard-Vorlage wird automatisch verwendet, wenn keine spezifische Vorlage angegeben wird
     *
     * @param User $user Der authentifizierte Benutzer
     * @return array Liste aller verfügbaren Druckvorlagen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getPrintLayouts(User $user): array
    {
        return $this->get($user, '/print-layouts');
    }

    // =========================================================================
    // EVENT-SUBSCRIPTIONS (WEBHOOKS)
    // =========================================================================

    /**
     * Event-Subscription erstellen (Webhook registrieren)
     *
     * Erstellt eine neue Event-Subscription (Webhook) in der Lexware API.
     * Mit Event-Subscriptions können Sie über Änderungen an Ressourcen benachrichtigt werden.
     * Die Lexware API sendet HTTP POST Requests an die angegebene Callback-URL,
     * wenn ein abonniertes Event auftritt.
     *
     * @see https://developers.lexoffice.io/docs/#event-subscriptions-endpoint-create-an-event-subscription
     *
     * Verfügbare Event-Typen:
     * - contact.created: Neuer Kontakt erstellt
     * - contact.changed: Kontakt geändert
     * - contact.deleted: Kontakt gelöscht
     * - invoice.created: Neue Rechnung erstellt
     * - invoice.changed: Rechnung geändert
     * - invoice.deleted: Rechnung gelöscht
     * - invoice.status.changed: Rechnungsstatus geändert
     * - quotation.created: Neues Angebot erstellt
     * - quotation.changed: Angebot geändert
     * - quotation.deleted: Angebot gelöscht
     * - quotation.status.changed: Angebotsstatus geändert
     * - order-confirmation.created: Neue Auftragsbestätigung erstellt
     * - order-confirmation.changed: Auftragsbestätigung geändert
     * - order-confirmation.deleted: Auftragsbestätigung gelöscht
     * - order-confirmation.status.changed: Status Auftragsbestätigung geändert
     * - credit-note.created: Neue Gutschrift erstellt
     * - credit-note.changed: Gutschrift geändert
     * - credit-note.deleted: Gutschrift gelöscht
     * - credit-note.status.changed: Gutschriftstatus geändert
     * - delivery-note.created: Neuer Lieferschein erstellt
     * - delivery-note.changed: Lieferschein geändert
     * - delivery-note.deleted: Lieferschein gelöscht
     * - down-payment-invoice.created: Neue Anzahlungsrechnung erstellt
     * - down-payment-invoice.changed: Anzahlungsrechnung geändert
     * - down-payment-invoice.deleted: Anzahlungsrechnung gelöscht
     * - down-payment-invoice.status.changed: Status Anzahlungsrechnung geändert
     * - recurring-template.created: Neues wiederkehrendes Template erstellt
     * - recurring-template.changed: Wiederkehrendes Template geändert
     * - recurring-template.deleted: Wiederkehrendes Template gelöscht
     * - payment.changed: Zahlung geändert
     * - article.created: Neuer Artikel erstellt
     * - article.changed: Artikel geändert
     * - article.deleted: Artikel gelöscht
     * - dunning.created: Neue Mahnung erstellt
     * - dunning.changed: Mahnung geändert
     * - dunning.deleted: Mahnung gelöscht
     * - token.revoked: API-Token widerrufen
     *
     * Beispiel-Request:
     * {
     *   "eventType": "contact.changed",
     *   "callbackUrl": "https://example.com/webhooks/lexware/contacts"
     * }
     *
     * Beispiel-Response:
     * {
     *   "subscriptionId": "a2691815-4f13-48e8-a7e9-3990be5b5f1d",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-17T09:00:00.000+01:00",
     *   "eventType": "contact.changed",
     *   "callbackUrl": "https://example.com/webhooks/lexware/contacts"
     * }
     *
     * Hinweise:
     * - Die Callback-URL muss HTTPS verwenden (außer localhost für Entwicklung)
     * - Die URL muss erreichbar sein und einen 2xx Status zurückgeben
     * - Pro Event-Typ kann nur eine Subscription existieren
     * - Bei Token-Widerruf wird auch die Subscription deaktiviert
     *
     * Validierung:
     * - eventType: Erforderlich, muss ein gültiger Event-Typ sein
     * - callbackUrl: Erforderlich, muss eine gültige HTTPS-URL sein
     *
     * @param User $user Der authentifizierte Benutzer
     * @param array $data Event-Subscription-Daten (eventType, callbackUrl erforderlich)
     * @return array Erstellte Subscription-Daten mit subscriptionId
     * @throws LexwareApiException Bei API-Fehlern (z.B. 400 bei ungültigen Daten, 409 bei Duplikat)
     */
    public function createEventSubscription(User $user, array $data): array
    {
        return $this->post($user, '/event-subscriptions', $data);
    }

    /**
     * Einzelne Event-Subscription abrufen
     *
     * Ruft eine einzelne Event-Subscription anhand ihrer ID aus der Lexware API ab.
     * Gibt Details zur Subscription zurück, inklusive Event-Typ und Callback-URL.
     *
     * @see https://developers.lexoffice.io/docs/#event-subscriptions-endpoint-retrieve-an-event-subscription
     *
     * Beispiel-Request:
     * GET /event-subscriptions/a2691815-4f13-48e8-a7e9-3990be5b5f1d
     *
     * Beispiel-Response:
     * {
     *   "subscriptionId": "a2691815-4f13-48e8-a7e9-3990be5b5f1d",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-17T09:00:00.000+01:00",
     *   "eventType": "contact.changed",
     *   "callbackUrl": "https://example.com/webhooks/lexware/contacts"
     * }
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $subscriptionId Die UUID der Event-Subscription
     * @return array Subscription-Daten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getEventSubscription(User $user, string $subscriptionId): array
    {
        return $this->get($user, "/event-subscriptions/{$subscriptionId}");
    }

    /**
     * Alle Event-Subscriptions abrufen
     *
     * Ruft alle Event-Subscriptions des aktuellen Benutzers aus der Lexware API ab.
     * Im Gegensatz zu anderen Endpunkten ist diese Liste NICHT paginiert,
     * da typischerweise nur wenige Subscriptions existieren.
     *
     * @see https://developers.lexoffice.io/docs/#event-subscriptions-endpoint-retrieve-all-event-subscriptions
     *
     * Beispiel-Request:
     * GET /event-subscriptions
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "subscriptionId": "a2691815-4f13-48e8-a7e9-3990be5b5f1d",
     *       "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "createdDate": "2024-01-17T09:00:00.000+01:00",
     *       "eventType": "contact.changed",
     *       "callbackUrl": "https://example.com/webhooks/lexware/contacts"
     *     },
     *     {
     *       "subscriptionId": "b3702926-5g24-59f9-b8f0-4001cf6c6g2e",
     *       "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "createdDate": "2024-01-18T10:00:00.000+01:00",
     *       "eventType": "invoice.created",
     *       "callbackUrl": "https://example.com/webhooks/lexware/invoices"
     *     }
     *   ]
     * }
     *
     * Hinweise:
     * - Diese Liste enthält alle aktiven Subscriptions
     * - Es gibt kein Paginierung - alle Subscriptions werden zurückgegeben
     * - Gelöschte Subscriptions erscheinen nicht in der Liste
     *
     * @param User $user Der authentifizierte Benutzer
     * @return array Liste aller Event-Subscriptions
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getEventSubscriptions(User $user): array
    {
        return $this->get($user, '/event-subscriptions');
    }

    /**
     * Event-Subscription löschen (Webhook abmelden)
     *
     * Löscht eine Event-Subscription aus der Lexware API.
     * Nach dem Löschen werden keine weiteren Webhook-Benachrichtigungen
     * für diesen Event-Typ gesendet.
     *
     * @see https://developers.lexoffice.io/docs/#event-subscriptions-endpoint-delete-an-event-subscription
     *
     * Beispiel-Request:
     * DELETE /event-subscriptions/a2691815-4f13-48e8-a7e9-3990be5b5f1d
     *
     * Beispiel-Response bei Erfolg:
     * HTTP 204 No Content (leerer Response-Body)
     *
     * Hinweise:
     * - Nach dem Löschen werden keine weiteren Events für diesen Typ empfangen
     * - Die Subscription kann jederzeit neu erstellt werden
     * - Diese Operation ist unwiderruflich
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $subscriptionId Die UUID der zu löschenden Event-Subscription
     * @return array Leeres Array bei Erfolg
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function deleteEventSubscription(User $user, string $subscriptionId): array
    {
        return $this->delete($user, "/event-subscriptions/{$subscriptionId}");
    }

    /**
     * Webhook-Signatur verifizieren (HMAC-SHA256)
     *
     * Überprüft die Authentizität eines eingehenden Webhook-Requests.
     * Lexware signiert alle ausgehenden Webhook-Requests mit HMAC-SHA256.
     * Die Signatur wird im HTTP-Header 'X-Lxo-Signature' übermittelt.
     *
     * WICHTIG: Diese Methode sollte IMMER verwendet werden, um die Echtheit
     * von Webhook-Benachrichtigungen zu verifizieren, bevor die Daten verarbeitet werden.
     *
     * Sicherheitshinweise:
     * - Der API-Token wird als geheimer Schlüssel verwendet
     * - Requests ohne gültige Signatur sollten mit HTTP 401 abgelehnt werden
     * - Replay-Angriffe sollten durch Timestamp-Validierung verhindert werden
     *
     * Ablauf der Verifikation:
     * 1. Signatur aus Header 'X-Lxo-Signature' extrahieren
     * 2. HMAC-SHA256 Hash des Request-Body mit API-Token berechnen
     * 3. Berechneten Hash mit Signatur vergleichen (timing-safe)
     *
     * Beispiel-Header:
     * X-Lxo-Signature: sha256=a1b2c3d4e5f6...
     *
     * @param string $payload Der rohe Request-Body (JSON-String)
     * @param string $signature Die Signatur aus dem Header 'X-Lxo-Signature'
     * @param string $apiToken Der API-Token des Benutzers (geheimer Schlüssel)
     * @return bool true wenn Signatur gültig, false wenn ungültig
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $apiToken): bool
    {
        // Signatur-Prefix entfernen (falls vorhanden)
        // Format: "sha256=<hex_digest>" oder nur "<hex_digest>"
        $signatureHash = $signature;
        if (str_starts_with($signature, 'sha256=')) {
            $signatureHash = substr($signature, 7);
        }

        // HMAC-SHA256 Hash des Payloads mit API-Token berechnen
        $expectedHash = hash_hmac('sha256', $payload, $apiToken);

        // Timing-safe Vergleich um Timing-Angriffe zu verhindern
        return hash_equals($expectedHash, $signatureHash);
    }

    /**
     * Webhook-Request validieren und verarbeiten
     *
     * Kombiniert Signaturverifikation mit Payload-Extraktion.
     * Diese Methode sollte als Einstiegspunkt für die Webhook-Verarbeitung verwendet werden.
     *
     * Webhook-Payload Struktur:
     * {
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "eventType": "contact.changed",
     *   "resourceId": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "eventDate": "2024-01-17T10:30:00.000+01:00"
     * }
     *
     * Hinweise:
     * - resourceId enthält die UUID der betroffenen Ressource
     * - eventDate gibt den Zeitpunkt des Events an
     * - Der Payload enthält NICHT die vollständigen Ressourcendaten
     * - Vollständige Daten müssen separat über GET /contacts/{id} etc. abgerufen werden
     *
     * @param string $payload Der rohe Request-Body (JSON-String)
     * @param string $signature Die Signatur aus dem Header 'X-Lxo-Signature'
     * @param string $apiToken Der API-Token des Benutzers
     * @return array{valid: bool, data: array|null, error: string|null} Validierungsergebnis
     */
    public function processWebhookRequest(string $payload, string $signature, string $apiToken): array
    {
        // Signatur verifizieren
        if (!$this->verifyWebhookSignature($payload, $signature, $apiToken)) {
            return [
                'valid' => false,
                'data' => null,
                'error' => 'Ungültige Webhook-Signatur. Request könnte manipuliert sein.',
            ];
        }

        // Payload parsen
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'data' => null,
                'error' => 'Ungültiges JSON im Webhook-Payload: ' . json_last_error_msg(),
            ];
        }

        // Erforderliche Felder prüfen
        $requiredFields = ['organizationId', 'eventType', 'resourceId', 'eventDate'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return [
                    'valid' => false,
                    'data' => null,
                    'error' => "Erforderliches Feld '{$field}' fehlt im Webhook-Payload.",
                ];
            }
        }

        return [
            'valid' => true,
            'data' => $data,
            'error' => null,
        ];
    }

    // =========================================================================
    // WIEDERKEHRENDE VORLAGEN (RECURRING TEMPLATES)
    // =========================================================================

    /**
     * Wiederkehrende Vorlagen abrufen (paginiert)
     *
     * Ruft eine Liste von wiederkehrenden Vorlagen (Recurring Templates) aus der Lexware API ab.
     * Wiederkehrende Vorlagen sind Vorlagen für automatisch erstellte Rechnungen,
     * die in regelmäßigen Abständen generiert werden (z.B. monatliche Abonnements).
     *
     * @see https://developers.lexoffice.io/docs/#recurring-templates-endpoint-retrieve-recurring-templates
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/recurring-templates?page=0&size=25
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "f4b5e3d2-c1a0-9876-fedc-ba0987654321",
     *       "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *       "createdDate": "2024-01-15T10:30:00.000+01:00",
     *       "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *       "version": 1,
     *       "templateName": "Monatliches Hosting",
     *       "nextExecutionDate": "2024-02-01",
     *       "executionInterval": "MONTHLY",
     *       "executionStatus": "ACTIVE",
     *       "lastExecutionDate": "2024-01-01",
     *       "address": {
     *         "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *         "name": "Muster GmbH"
     *       },
     *       "totalPrice": {
     *         "currency": "EUR",
     *         "totalNetAmount": 100.00,
     *         "totalGrossAmount": 119.00,
     *         "totalTaxAmount": 19.00
     *       }
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 5,
     *   "totalElements": 120,
     *   "size": 25,
     *   "number": 0,
     *   "numberOfElements": 25
     * }
     *
     * Hinweise:
     * - Die Ergebnisse sind paginiert mit einer maximalen Seitengröße von 250
     * - executionInterval kann sein: WEEKLY, BIWEEKLY, MONTHLY, QUARTERLY, BIANNUALLY, ANNUALLY
     * - executionStatus kann sein: ACTIVE, PAUSED, ENDED
     *
     * @param User $user Der authentifizierte Benutzer
     * @param int $page Seitennummer (0-basiert)
     * @param int $size Anzahl Elemente pro Seite (max. 250)
     * @return array Paginierte Liste von wiederkehrenden Vorlagen
     * @throws LexwareApiException Bei API-Fehlern
     */
    public function getRecurringTemplates(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/recurring-templates', [
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    /**
     * Einzelne wiederkehrende Vorlage abrufen
     *
     * Ruft eine einzelne wiederkehrende Vorlage anhand ihrer ID aus der Lexware API ab.
     * Gibt alle Details der Vorlage zurück, inklusive Positionen, Adressen, Intervall und Summen.
     *
     * @see https://developers.lexoffice.io/docs/#recurring-templates-endpoint-retrieve-a-recurring-template
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/recurring-templates/f4b5e3d2-c1a0-9876-fedc-ba0987654321
     *
     * URL-Parameter:
     * - id (string): Die UUID der wiederkehrenden Vorlage
     *
     * Beispiel-Response:
     * {
     *   "id": "f4b5e3d2-c1a0-9876-fedc-ba0987654321",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "createdDate": "2024-01-15T10:30:00.000+01:00",
     *   "updatedDate": "2024-01-15T10:30:00.000+01:00",
     *   "version": 1,
     *   "templateName": "Monatliches Hosting",
     *   "nextExecutionDate": "2024-02-01",
     *   "executionInterval": "MONTHLY",
     *   "executionStatus": "ACTIVE",
     *   "lastExecutionDate": "2024-01-01",
     *   "lastCreatedInvoiceId": "a1b2c3d4-e5f6-7890-abcd-123456789xyz",
     *   "address": {
     *     "contactId": "66196c43-baf0-4c4a-8c7f-612ce856ad5a",
     *     "name": "Muster GmbH",
     *     "street": "Musterstraße 1",
     *     "zip": "12345",
     *     "city": "Musterstadt",
     *     "countryCode": "DE"
     *   },
     *   "lineItems": [
     *     {
     *       "id": "97b98491-e953-4dc9-97a9-ae437a8052b4",
     *       "type": "custom",
     *       "name": "Webhosting Premium",
     *       "description": "Monatliches Webhosting-Paket",
     *       "quantity": 1,
     *       "unitName": "Monat",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 100.00,
     *         "grossAmount": 119.00,
     *         "taxRatePercentage": 19
     *       },
     *       "lineItemAmount": 119.00
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR",
     *     "totalNetAmount": 100.00,
     *     "totalGrossAmount": 119.00,
     *     "totalTaxAmount": 19.00
     *   },
     *   "taxAmounts": [
     *     {
     *       "taxRatePercentage": 19,
     *       "taxAmount": 19.00,
     *       "netAmount": 100.00
     *     }
     *   ],
     *   "taxConditions": {
     *     "taxType": "net"
     *   },
     *   "paymentConditions": {
     *     "paymentTermLabel": "Zahlbar innerhalb von 14 Tagen",
     *     "paymentTermDuration": 14
     *   },
     *   "shippingConditions": {
     *     "shippingDate": "2024-02-01",
     *     "shippingType": "delivery"
     *   },
     *   "title": "Rechnung",
     *   "introduction": "Vielen Dank für Ihre Treue.",
     *   "remark": "Bei Fragen stehen wir Ihnen gerne zur Verfügung."
     * }
     *
     * Hinweise:
     * - lastCreatedInvoiceId enthält die ID der zuletzt aus dieser Vorlage erstellten Rechnung
     * - Die Vorlage enthält alle Daten, die für die automatische Rechnungserstellung benötigt werden
     * - executionStatus zeigt den aktuellen Status: ACTIVE, PAUSED oder ENDED
     *
     * @param User $user Der authentifizierte Benutzer
     * @param string $templateId Die UUID der wiederkehrenden Vorlage
     * @return array Detaillierte Vorlagendaten
     * @throws LexwareApiException Bei API-Fehlern (z.B. 404 wenn nicht gefunden)
     */
    public function getRecurringTemplate(User $user, string $templateId): array
    {
        return $this->get($user, "/recurring-templates/{$templateId}");
    }

    /**
     * Deeplink zu einer wiederkehrenden Vorlage abrufen
     *
     * Generiert einen Deeplink, der direkt zur wiederkehrenden Vorlage in der Lexoffice Web-App führt.
     * Der Deeplink öffnet die Detailansicht der Vorlage in Lexoffice, wo sie bearbeitet werden kann.
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/recurring-templates/f4b5e3d2-c1a0-9876-fedc-ba0987654321/deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/recurring-templates#!/view/f4b5e3d2-c1a0-9876-fedc-ba0987654321"
     * }
     *
     * Hinweise:
     * - Der Deeplink ist ein direkter Link zur Lexoffice Web-App
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um die Vorlage zu sehen
     * - Dieser Endpunkt macht keinen API-Aufruf, sondern generiert nur die URL
     *
     * @param string $templateId Die UUID der wiederkehrenden Vorlage
     * @return array Array mit dem Deeplink zur Vorlage
     */
    public function getRecurringTemplateDeeplink(string $templateId): array
    {
        return [
            'deeplink' => "https://app.lexoffice.de/recurring-templates#!/view/{$templateId}",
        ];
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * GET Request an die Lexware API
     *
     * @throws LexwareApiException
     */
    protected function get(User $user, string $endpoint, array $query = []): array
    {
        return $this->request($user, 'GET', $endpoint, $query);
    }

    /**
     * POST Request an die Lexware API
     *
     * @throws LexwareApiException
     */
    protected function post(User $user, string $endpoint, array $data = [], array $query = []): array
    {
        return $this->request($user, 'POST', $endpoint, $query, $data);
    }

    /**
     * PUT Request an die Lexware API
     *
     * @throws LexwareApiException
     */
    protected function put(User $user, string $endpoint, array $data = [], array $query = []): array
    {
        return $this->request($user, 'PUT', $endpoint, $query, $data);
    }

    /**
     * DELETE Request an die Lexware API
     *
     * @throws LexwareApiException
     */
    protected function delete(User $user, string $endpoint): array
    {
        return $this->request($user, 'DELETE', $endpoint);
    }

    /**
     * Führt einen HTTP Request an die Lexware API aus
     *
     * Der API-Token wird aus der IntegrationConnection Tabelle geholt.
     * Es werden KEINE ENV oder CONFIG Variablen verwendet.
     *
     * @throws LexwareApiException
     */
    protected function request(
        User $user,
        string $method,
        string $endpoint,
        array $query = [],
        array $data = []
    ): array {
        // Token aus der IntegrationConnection Tabelle holen
        $connection = $this->integrationService->getConnectionForUser($user);

        if (!$connection) {
            Log::warning('Lexware API: Keine Connection für User', ['user_id' => $user->id]);
            throw LexwareApiException::noConnection();
        }

        $apiToken = $this->integrationService->getApiToken($connection);

        if (!$apiToken) {
            Log::warning('Lexware API: Kein Token für User', ['user_id' => $user->id]);
            throw LexwareApiException::unauthorized();
        }

        $url = self::BASE_URL . $endpoint;

        try {
            $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            // Request ausführen
            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'POST' => $request->post($url . $this->buildQueryString($query), $data),
                'PUT' => $request->put($url . $this->buildQueryString($query), $data),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            return $this->handleResponse($response, $connection);
        } catch (LexwareApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Lexware API: Verbindungsfehler', [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw LexwareApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP Response und behandelt Fehler
     *
     * @throws LexwareApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        // Erfolgreiche Responses (2xx)
        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');
            return $data;
        }

        // Fehlerbehandlung basierend auf HTTP Status Code
        $this->updateConnectionStatus(
            $connection,
            $statusCode === 401 ? 'error' : 'active',
            $data['message'] ?? null
        );

        Log::warning('Lexware API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw LexwareApiException::fromResponse($statusCode, $data);
    }

    /**
     * Aktualisiert den Status der IntegrationConnection
     */
    protected function updateConnectionStatus(
        IntegrationConnection $connection,
        string $status,
        ?string $error = null
    ): void {
        $connection->status = $status;
        $connection->last_error = $error;
        $connection->last_tested_at = now();
        $connection->save();
    }

    /**
     * Erstellt einen Query-String aus einem Array
     */
    protected function buildQueryString(array $query): string
    {
        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }
}
