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
     * @throws LexwareApiException
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
     * @throws LexwareApiException
     */
    public function getInvoice(User $user, string $invoiceId): array
    {
        return $this->get($user, "/invoices/{$invoiceId}");
    }

    /**
     * Rechnung erstellen
     *
     * @throws LexwareApiException
     */
    public function createInvoice(User $user, array $data, bool $finalize = false): array
    {
        $query = $finalize ? ['finalize' => 'true'] : [];
        return $this->post($user, '/invoices', $data, $query);
    }

    // =========================================================================
    // ANGEBOTE (QUOTATIONS)
    // =========================================================================

    /**
     * Angebote abrufen (paginiert)
     *
     * @throws LexwareApiException
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
     * @throws LexwareApiException
     */
    public function getQuotation(User $user, string $quotationId): array
    {
        return $this->get($user, "/quotations/{$quotationId}");
    }

    /**
     * Angebot erstellen
     *
     * @throws LexwareApiException
     */
    public function createQuotation(User $user, array $data): array
    {
        return $this->post($user, '/quotations', $data);
    }

    // =========================================================================
    // AUFTRAGSBESTÄTIGUNGEN (ORDER CONFIRMATIONS)
    // =========================================================================

    /**
     * Auftragsbestätigungen abrufen (paginiert)
     *
     * @throws LexwareApiException
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
     * @throws LexwareApiException
     */
    public function getOrderConfirmation(User $user, string $orderId): array
    {
        return $this->get($user, "/order-confirmations/{$orderId}");
    }

    // =========================================================================
    // GUTSCHRIFTEN (CREDIT NOTES)
    // =========================================================================

    /**
     * Gutschriften abrufen (paginiert)
     *
     * @throws LexwareApiException
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
     * @throws LexwareApiException
     */
    public function getCreditNote(User $user, string $creditNoteId): array
    {
        return $this->get($user, "/credit-notes/{$creditNoteId}");
    }

    // =========================================================================
    // LIEFERSCHEINE (DELIVERY NOTES)
    // =========================================================================

    /**
     * Lieferscheine abrufen (paginiert)
     *
     * @throws LexwareApiException
     */
    public function getDeliveryNotes(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/voucherlist', [
            'voucherType' => 'deliverynote',
            'page' => $page,
            'size' => min($size, 250),
        ]);
    }

    // =========================================================================
    // PROFIL & VERBINDUNG
    // =========================================================================

    /**
     * Profil des aktuellen Benutzers abrufen
     *
     * @throws LexwareApiException
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
    // ZAHLUNGEN (PAYMENTS)
    // =========================================================================

    /**
     * Zahlungen abrufen (paginiert)
     *
     * @throws LexwareApiException
     */
    public function getPayments(User $user, int $page = 0, int $size = 25): array
    {
        return $this->get($user, '/payments', [
            'page' => $page,
            'size' => min($size, 250),
        ]);
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
