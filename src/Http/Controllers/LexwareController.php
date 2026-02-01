<?php

namespace Platform\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Integrations\Exceptions\LexwareApiException;
use Platform\Integrations\Services\LexwareApiService;

/**
 * Controller für Lexware API Endpunkte
 *
 * Dieser Controller dient als Rahmen für alle Lexware API Operationen.
 * Er verwendet den LexwareApiService für die eigentliche API-Kommunikation
 * und den LexwareIntegrationService für die Token-Verwaltung.
 */
class LexwareController extends Controller
{
    public function __construct(
        protected LexwareApiService $lexwareApiService,
    ) {}

    // =========================================================================
    // KONTAKTE (CONTACTS)
    // =========================================================================

    /**
     * Kontakte abrufen (paginiert)
     *
     * Ruft eine Liste von Kontakten aus der Lexware API ab.
     * Unterstützt Paginierung über die Query-Parameter 'page' und 'size'.
     * Kontakte können Kunden (customer), Lieferanten (vendor) oder beides sein.
     *
     * GET /api/integrations/lexware/contacts
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/contacts?page=0&size=25
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *       "version": 1,
     *       "roles": {
     *         "customer": { "number": 10001 }
     *       },
     *       "company": {
     *         "name": "Muster GmbH",
     *         "taxNumber": "DE123456789"
     *       },
     *       "addresses": {
     *         "billing": [{ "street": "Musterstraße 1", "zip": "12345", "city": "Musterstadt", "countryCode": "DE" }]
     *       },
     *       "emailAddresses": { "business": ["kontakt@muster.de"] },
     *       "phoneNumbers": { "business": ["+49 123 456789"] },
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 5,
     *   "totalElements": 120,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param Request $request HTTP-Request mit optionalen Paginierungsparametern
     * @return JsonResponse Liste der Kontakte oder Fehlermeldung
     */
    public function contacts(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = (int) $request->get('page', 0);
            $size = (int) $request->get('size', 25);

            $result = $this->lexwareApiService->getContacts($user, $page, $size);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Einzelnen Kontakt abrufen
     *
     * Ruft einen einzelnen Kontakt anhand seiner UUID aus der Lexware API ab.
     * Gibt alle Details des Kontakts zurück, inklusive Adressen, E-Mails und Telefonnummern.
     *
     * GET /api/integrations/lexware/contacts/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID des Kontakts
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/contacts/e9066f04-8cc7-4616-93f8-ac9c10e55bc9
     *
     * Beispiel-Response:
     * {
     *   "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "version": 1,
     *   "roles": {
     *     "customer": { "number": 10001 },
     *     "vendor": { "number": 70001 }
     *   },
     *   "company": {
     *     "name": "Muster GmbH",
     *     "taxNumber": "DE123456789",
     *     "contactPersons": [
     *       {
     *         "salutation": "Herr",
     *         "firstName": "Max",
     *         "lastName": "Mustermann",
     *         "primary": true,
     *         "emailAddress": "max.mustermann@muster.de"
     *       }
     *     ]
     *   },
     *   "addresses": {
     *     "billing": [{ "street": "Musterstraße 1", "zip": "12345", "city": "Musterstadt", "countryCode": "DE" }],
     *     "shipping": [{ "street": "Lieferstraße 5", "zip": "12345", "city": "Musterstadt", "countryCode": "DE" }]
     *   },
     *   "emailAddresses": { "business": ["kontakt@muster.de"], "office": ["buero@muster.de"] },
     *   "phoneNumbers": { "business": ["+49 123 456789"], "mobile": ["+49 170 1234567"] },
     *   "note": "Wichtiger Kunde seit 2020",
     *   "archived": false
     * }
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Kontakts
     * @return JsonResponse Kontaktdaten oder Fehlermeldung
     */
    public function contact(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getContact($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Kontakt erstellen
     *
     * Erstellt einen neuen Kontakt in der Lexware API.
     * Ein Kontakt kann entweder eine Person oder ein Unternehmen sein.
     * Die Rolle (customer/vendor) bestimmt, ob der Kontakt als Kunde oder Lieferant geführt wird.
     *
     * POST /api/integrations/lexware/contacts
     *
     * Request-Body (JSON) - Beispiel Unternehmen als Kunde:
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
     *         "emailAddress": "anna.schmidt@neuefirma.de"
     *       }
     *     ]
     *   },
     *   "addresses": {
     *     "billing": [{ "street": "Neuestraße 10", "zip": "54321", "city": "Neustadt", "countryCode": "DE" }]
     *   },
     *   "emailAddresses": { "business": ["info@neuefirma.de"] },
     *   "phoneNumbers": { "business": ["+49 987 654321"] },
     *   "note": "Neuer Kunde"
     * }
     *
     * Request-Body (JSON) - Beispiel Person als Kunde:
     * {
     *   "version": 0,
     *   "roles": { "customer": {} },
     *   "person": {
     *     "salutation": "Herr",
     *     "firstName": "Peter",
     *     "lastName": "Müller"
     *   },
     *   "addresses": {
     *     "billing": [{ "street": "Privatweg 5", "zip": "11111", "city": "Heimatstadt", "countryCode": "DE" }]
     *   },
     *   "emailAddresses": { "private": ["peter.mueller@email.de"] }
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
     * @param Request $request HTTP-Request mit Kontaktdaten im Body
     * @return JsonResponse Erstellte Kontakt-Metadaten oder Fehlermeldung
     */
    public function createContact(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();

            $result = $this->lexwareApiService->createContact($user, $data);

            return response()->json($result, 201);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Kontakt aktualisieren
     *
     * Aktualisiert einen bestehenden Kontakt in der Lexware API.
     * Die aktuelle 'version' muss im Request-Body mitgegeben werden (Optimistic Locking).
     * Alle Felder, die nicht im Request enthalten sind, werden auf ihre Standardwerte zurückgesetzt.
     *
     * PUT /api/integrations/lexware/contacts/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID des zu aktualisierenden Kontakts
     *
     * Request-Body (JSON):
     * {
     *   "version": 1,
     *   "roles": {
     *     "customer": { "number": 10001 }
     *   },
     *   "company": {
     *     "name": "Muster GmbH - Aktualisiert",
     *     "taxNumber": "DE123456789",
     *     "contactPersons": [
     *       {
     *         "salutation": "Herr",
     *         "firstName": "Max",
     *         "lastName": "Mustermann",
     *         "primary": true
     *       }
     *     ]
     *   },
     *   "addresses": {
     *     "billing": [{ "street": "Neue Musterstraße 2", "zip": "12345", "city": "Musterstadt", "countryCode": "DE" }]
     *   },
     *   "emailAddresses": { "business": ["neu@muster.de"] },
     *   "phoneNumbers": { "business": ["+49 123 999888"] },
     *   "note": "Adresse aktualisiert"
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
     * @param Request $request HTTP-Request mit aktualisierten Kontaktdaten
     * @param string $id Die UUID des Kontakts
     * @return JsonResponse Aktualisierte Kontakt-Metadaten oder Fehlermeldung
     */
    public function updateContact(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();

            $result = $this->lexwareApiService->updateContact($user, $id, $data);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Kontakt löschen
     *
     * Löscht einen Kontakt aus der Lexware API.
     * Hinweis: Kontakte können nur gelöscht werden, wenn sie nicht in Belegen verwendet werden.
     *
     * ACHTUNG: Die Lexware API unterstützt möglicherweise kein direktes Löschen von Kontakten.
     * In diesem Fall wird ein 405 Method Not Allowed zurückgegeben.
     * Als Alternative kann der Kontakt über PUT /contacts/{id} archiviert werden
     * (archived: true im Request-Body setzen).
     *
     * DELETE /api/integrations/lexware/contacts/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID des zu löschenden Kontakts
     *
     * Beispiel-Request:
     * DELETE /api/integrations/lexware/contacts/e9066f04-8cc7-4616-93f8-ac9c10e55bc9
     *
     * Beispiel-Response bei Erfolg:
     * HTTP 204 No Content
     *
     * Mögliche Fehler:
     * - 404: Kontakt nicht gefunden
     * - 405: Löschen nicht unterstützt (Alternative: archivieren)
     * - 409: Kontakt wird in Belegen verwendet
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des zu löschenden Kontakts
     * @return JsonResponse Leere Response (204) oder Fehlermeldung
     */
    public function deleteContact(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $this->lexwareApiService->deleteContact($user, $id);

            return response()->json(null, 204);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    // =========================================================================
    // RECHNUNGEN (INVOICES)
    // =========================================================================

    /**
     * Rechnungen abrufen (paginiert)
     *
     * Ruft eine Liste von Rechnungen aus der Lexware API ab.
     * Unterstützt Paginierung über die Query-Parameter 'page' und 'size'.
     *
     * GET /api/integrations/lexware/invoices
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/invoices?page=0&size=25
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
     *       "dueDate": "2024-02-14",
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
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param Request $request HTTP-Request mit optionalen Paginierungsparametern
     * @return JsonResponse Liste der Rechnungen oder Fehlermeldung
     */
    public function invoices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = (int) $request->get('page', 0);
            $size = (int) $request->get('size', 25);

            $result = $this->lexwareApiService->getInvoices($user, $page, $size);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Einzelne Rechnung abrufen
     *
     * Ruft eine einzelne Rechnung anhand ihrer UUID aus der Lexware API ab.
     * Gibt alle Details der Rechnung zurück, inklusive Positionen, Adressen und Summen.
     *
     * GET /api/integrations/lexware/invoices/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID der Rechnung
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/invoices/e9066f04-8cc7-4616-93f8-ac9c10e55bc9
     *
     * Beispiel-Response:
     * {
     *   "id": "e9066f04-8cc7-4616-93f8-ac9c10e55bc9",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "version": 1,
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
     *       "type": "custom",
     *       "name": "Beratungsleistung",
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
     *     "currency": "EUR",
     *     "totalNetAmount": 1000.00,
     *     "totalGrossAmount": 1190.00,
     *     "totalTaxAmount": 190.00
     *   }
     * }
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Rechnung
     * @return JsonResponse Rechnungsdaten oder Fehlermeldung
     */
    public function invoice(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getInvoice($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Rechnung erstellen
     *
     * Erstellt eine neue Rechnung in der Lexware API.
     * Die Rechnung kann entweder als Entwurf (Standard) oder direkt finalisiert erstellt werden.
     * Finalisierte Rechnungen erhalten sofort eine Rechnungsnummer.
     *
     * POST /api/integrations/lexware/invoices
     *
     * Query-Parameter:
     * - finalize (bool): Wenn true, wird die Rechnung direkt finalisiert (Standard: false)
     *
     * Request-Body (JSON) - Beispiel Rechnung an Kontakt:
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
     *   "title": "Rechnung",
     *   "introduction": "Vielen Dank für Ihren Auftrag.",
     *   "remark": "Bei Fragen stehen wir Ihnen gerne zur Verfügung."
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
     * - address kann entweder contactId (bestehender Kontakt) oder manuelle Adressdaten enthalten
     * - lineItems.type kann 'custom' (freier Text) oder 'material' (Artikel) sein
     * - taxConditions.taxType kann 'net', 'gross' oder 'vatfree' sein
     * - Bei finalize=true wird die Rechnung sofort abgeschlossen und erhält eine Nummer
     *
     * @param Request $request HTTP-Request mit Rechnungsdaten im Body
     * @return JsonResponse Erstellte Rechnungs-Metadaten oder Fehlermeldung
     */
    public function createInvoice(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();
            $finalize = filter_var($request->get('finalize', false), FILTER_VALIDATE_BOOLEAN);

            $result = $this->lexwareApiService->createInvoice($user, $data, $finalize);

            return response()->json($result, 201);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Rechnung finalisieren (abschließen)
     *
     * Finalisiert einen Rechnungsentwurf und macht ihn zu einer echten Rechnung.
     * Nach der Finalisierung erhält die Rechnung eine Rechnungsnummer und kann nicht mehr bearbeitet werden.
     *
     * WICHTIG: Diese Operation ist unwiderruflich!
     *
     * POST /api/integrations/lexware/invoices/{id}/finalize
     *
     * URL-Parameter:
     * - id (string): Die UUID der zu finalisierenden Rechnung
     *
     * Beispiel-Request:
     * POST /api/integrations/lexware/invoices/e9066f04-8cc7-4616-93f8-ac9c10e55bc9/finalize
     *
     * Beispiel-Response bei Erfolg:
     * {
     *   "success": true,
     *   "message": "Rechnung erfolgreich finalisiert."
     * }
     *
     * Voraussetzungen:
     * - Die Rechnung muss im Status 'draft' (Entwurf) sein
     * - Alle Pflichtfelder müssen ausgefüllt sein
     *
     * Mögliche Fehler:
     * - 400: Rechnung ist bereits finalisiert oder ungültige Daten
     * - 404: Rechnung nicht gefunden
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Rechnung
     * @return JsonResponse Erfolgsmeldung oder Fehlermeldung
     */
    public function finalizeInvoice(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $this->lexwareApiService->finalizeInvoice($user, $id);

            return response()->json([
                'success' => true,
                'message' => 'Rechnung erfolgreich finalisiert.',
            ]);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Rechnung als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für eine finalisierte Rechnung.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * GET /api/integrations/lexware/invoices/{id}/pdf
     *
     * URL-Parameter:
     * - id (string): Die UUID der Rechnung
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/invoices/e9066f04-8cc7-4616-93f8-ac9c10e55bc9/pdf
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Die Rechnung muss finalisiert sein (voucherStatus != 'draft')
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann nach einiger Zeit ablaufen
     * - Für den Download verwende GET /api/integrations/lexware/invoices/{id}/download
     *   oder GET /api/integrations/lexware/files/{documentFileId}
     *
     * Mögliche Fehler:
     * - 404: Rechnung nicht gefunden
     * - 406: Rechnung ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Rechnung
     * @return JsonResponse documentFileId oder Fehlermeldung
     */
    public function invoicePdf(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->renderInvoicePdf($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Rechnung als PDF herunterladen
     *
     * Rendert die Rechnung als PDF und gibt das Dokument direkt zum Download zurück.
     * Dies ist eine Kombination aus renderInvoicePdf() und downloadFile() in einem Request.
     *
     * GET /api/integrations/lexware/invoices/{id}/download
     *
     * URL-Parameter:
     * - id (string): Die UUID der Rechnung
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/invoices/e9066f04-8cc7-4616-93f8-ac9c10e55bc9/download
     *
     * Beispiel-Response:
     * Content-Type: application/pdf
     * Content-Disposition: attachment; filename="invoice-{id}.pdf"
     * (Binäre PDF-Daten)
     *
     * Voraussetzungen:
     * - Die Rechnung muss finalisiert sein (voucherStatus != 'draft')
     *
     * Mögliche Fehler:
     * - 404: Rechnung nicht gefunden
     * - 406: Rechnung ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Rechnung
     * @return \Illuminate\Http\Response PDF-Download oder JsonResponse bei Fehler
     */
    public function downloadInvoice(Request $request, string $id)
    {
        try {
            $user = $request->user();

            // Zuerst PDF rendern und documentFileId abrufen
            $renderResult = $this->lexwareApiService->renderInvoicePdf($user, $id);

            if (!isset($renderResult['documentFileId'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'pdf_render_failed',
                        'message' => 'PDF konnte nicht gerendert werden.',
                        'http_status' => 500,
                    ],
                ], 500);
            }

            // PDF herunterladen
            $pdfContent = $this->lexwareApiService->downloadFile($user, $renderResult['documentFileId']);

            // PDF als Download zurückgeben
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"invoice-{$id}.pdf\"")
                ->header('Content-Length', strlen($pdfContent));
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Deeplink zur Rechnung in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zur Rechnung in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zur Rechnung in Lexoffice weiterzuleiten.
     *
     * GET /api/integrations/lexware/invoices/{id}/deeplink
     *
     * URL-Parameter:
     * - id (string): Die UUID der Rechnung
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/invoices/e9066f04-8cc7-4616-93f8-ac9c10e55bc9/deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/invoice/e9066f04-8cc7-4616-93f8-ac9c10e55bc9"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn die Rechnung existiert und der Benutzer Zugriff hat
     * - Dieser Endpunkt validiert NICHT, ob die Rechnung existiert (für schnelle Response)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Rechnung
     * @return JsonResponse Array mit dem Deeplink
     */
    public function invoiceDeeplink(Request $request, string $id): JsonResponse
    {
        $result = $this->lexwareApiService->getInvoiceDeeplink($id);

        return response()->json($result);
    }

    // =========================================================================
    // ANGEBOTE (QUOTATIONS)
    // =========================================================================

    /**
     * Angebote abrufen (paginiert)
     *
     * Ruft eine Liste von Angeboten aus der Lexware API ab.
     * Unterstützt Paginierung über die Query-Parameter 'page' und 'size'.
     *
     * GET /api/integrations/lexware/quotations
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/quotations?page=0&size=25
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
     *       "expirationDate": "2024-02-14",
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
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param Request $request HTTP-Request mit optionalen Paginierungsparametern
     * @return JsonResponse Liste der Angebote oder Fehlermeldung
     */
    public function quotations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = (int) $request->get('page', 0);
            $size = (int) $request->get('size', 25);

            $result = $this->lexwareApiService->getQuotations($user, $page, $size);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Einzelnes Angebot abrufen
     *
     * Ruft ein einzelnes Angebot anhand seiner UUID aus der Lexware API ab.
     * Gibt alle Details des Angebots zurück, inklusive Positionen, Adressen und Summen.
     *
     * GET /api/integrations/lexware/quotations/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID des Angebots
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/quotations/a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "version": 1,
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
     *       "type": "custom",
     *       "name": "Beratungsleistung",
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
     *     "currency": "EUR",
     *     "totalNetAmount": 1000.00,
     *     "totalGrossAmount": 1190.00,
     *     "totalTaxAmount": 190.00
     *   },
     *   "title": "Angebot",
     *   "introduction": "Gerne unterbreiten wir Ihnen folgendes Angebot.",
     *   "remark": "Dieses Angebot ist 30 Tage gültig."
     * }
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Angebots
     * @return JsonResponse Angebotsdaten oder Fehlermeldung
     */
    public function quotation(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getQuotation($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Angebot erstellen
     *
     * Erstellt ein neues Angebot in der Lexware API.
     * Das Angebot kann entweder als Entwurf (Standard) oder direkt finalisiert erstellt werden.
     * Finalisierte Angebote erhalten sofort eine Angebotsnummer.
     *
     * POST /api/integrations/lexware/quotations
     *
     * Query-Parameter:
     * - finalize (bool): Wenn true, wird das Angebot direkt finalisiert (Standard: false)
     *
     * Request-Body (JSON) - Beispiel Angebot an Kontakt:
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
     * - address kann entweder contactId (bestehender Kontakt) oder manuelle Adressdaten enthalten
     * - lineItems.type kann 'custom' (freier Text) oder 'material' (Artikel) sein
     * - taxConditions.taxType kann 'net', 'gross' oder 'vatfree' sein
     * - Bei finalize=true wird das Angebot sofort abgeschlossen und erhält eine Nummer
     * - expirationDate gibt das Gültigkeitsdatum des Angebots an
     *
     * @param Request $request HTTP-Request mit Angebotsdaten im Body
     * @return JsonResponse Erstellte Angebots-Metadaten oder Fehlermeldung
     */
    public function createQuotation(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();
            $finalize = filter_var($request->get('finalize', false), FILTER_VALIDATE_BOOLEAN);

            $result = $this->lexwareApiService->createQuotation($user, $data, $finalize);

            return response()->json($result, 201);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Angebot als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für ein finalisiertes Angebot.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * GET /api/integrations/lexware/quotations/{id}/pdf
     *
     * URL-Parameter:
     * - id (string): Die UUID des Angebots
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/quotations/a1b2c3d4-e5f6-7890-abcd-ef1234567890/pdf
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Das Angebot muss finalisiert sein (voucherStatus != 'draft')
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann nach einiger Zeit ablaufen
     * - Für den Download verwende GET /api/integrations/lexware/quotations/{id}/download
     *   oder GET /api/integrations/lexware/files/{documentFileId}
     *
     * Mögliche Fehler:
     * - 404: Angebot nicht gefunden
     * - 406: Angebot ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Angebots
     * @return JsonResponse documentFileId oder Fehlermeldung
     */
    public function quotationPdf(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->renderQuotationPdf($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Angebot als PDF herunterladen
     *
     * Rendert das Angebot als PDF und gibt das Dokument direkt zum Download zurück.
     * Dies ist eine Kombination aus renderQuotationPdf() und downloadFile() in einem Request.
     *
     * GET /api/integrations/lexware/quotations/{id}/download
     *
     * URL-Parameter:
     * - id (string): Die UUID des Angebots
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/quotations/a1b2c3d4-e5f6-7890-abcd-ef1234567890/download
     *
     * Beispiel-Response:
     * Content-Type: application/pdf
     * Content-Disposition: attachment; filename="quotation-{id}.pdf"
     * (Binäre PDF-Daten)
     *
     * Voraussetzungen:
     * - Das Angebot muss finalisiert sein (voucherStatus != 'draft')
     *
     * Mögliche Fehler:
     * - 404: Angebot nicht gefunden
     * - 406: Angebot ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Angebots
     * @return \Illuminate\Http\Response PDF-Download oder JsonResponse bei Fehler
     */
    public function downloadQuotation(Request $request, string $id)
    {
        try {
            $user = $request->user();

            // Zuerst PDF rendern und documentFileId abrufen
            $renderResult = $this->lexwareApiService->renderQuotationPdf($user, $id);

            if (!isset($renderResult['documentFileId'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'pdf_render_failed',
                        'message' => 'PDF konnte nicht gerendert werden.',
                        'http_status' => 500,
                    ],
                ], 500);
            }

            // PDF herunterladen
            $pdfContent = $this->lexwareApiService->downloadFile($user, $renderResult['documentFileId']);

            // PDF als Download zurückgeben
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"quotation-{$id}.pdf\"")
                ->header('Content-Length', strlen($pdfContent));
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Deeplink zum Angebot in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zum Angebot in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zum Angebot in Lexoffice weiterzuleiten.
     *
     * GET /api/integrations/lexware/quotations/{id}/deeplink
     *
     * URL-Parameter:
     * - id (string): Die UUID des Angebots
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/quotations/a1b2c3d4-e5f6-7890-abcd-ef1234567890/deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/quotation/a1b2c3d4-e5f6-7890-abcd-ef1234567890"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn das Angebot existiert und der Benutzer Zugriff hat
     * - Dieser Endpunkt validiert NICHT, ob das Angebot existiert (für schnelle Response)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Angebots
     * @return JsonResponse Array mit dem Deeplink
     */
    public function quotationDeeplink(Request $request, string $id): JsonResponse
    {
        $result = $this->lexwareApiService->getQuotationDeeplink($id);

        return response()->json($result);
    }

    /**
     * Bestellungen abrufen
     *
     * GET /api/integrations/lexware/order-confirmations
     */
    public function orderConfirmations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = (int) $request->get('page', 0);
            $size = (int) $request->get('size', 25);

            $result = $this->lexwareApiService->getOrderConfirmations($user, $page, $size);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Gutschriften abrufen (paginiert)
     *
     * Ruft eine Liste von Gutschriften aus der Lexware API ab.
     * Unterstützt Paginierung über die Query-Parameter 'page' und 'size'.
     *
     * GET /api/integrations/lexware/credit-notes
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/credit-notes?page=0&size=25
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
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param Request $request HTTP-Request mit optionalen Paginierungsparametern
     * @return JsonResponse Liste der Gutschriften oder Fehlermeldung
     */
    public function creditNotes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = (int) $request->get('page', 0);
            $size = (int) $request->get('size', 25);

            $result = $this->lexwareApiService->getCreditNotes($user, $page, $size);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Einzelne Gutschrift abrufen
     *
     * Ruft eine einzelne Gutschrift anhand ihrer UUID aus der Lexware API ab.
     * Gibt alle Details der Gutschrift zurück, inklusive Positionen, Adressen und Summen.
     *
     * GET /api/integrations/lexware/credit-notes/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID der Gutschrift
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/credit-notes/c1d2e3f4-a5b6-7890-cdef-123456789abc
     *
     * Beispiel-Response:
     * {
     *   "id": "c1d2e3f4-a5b6-7890-cdef-123456789abc",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "version": 1,
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
     *       "type": "custom",
     *       "name": "Rückerstattung Beratungsleistung",
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
     *     "currency": "EUR",
     *     "totalNetAmount": 100.00,
     *     "totalGrossAmount": 119.00,
     *     "totalTaxAmount": 19.00
     *   }
     * }
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Gutschrift
     * @return JsonResponse Gutschriftdaten oder Fehlermeldung
     */
    public function creditNote(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getCreditNote($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Gutschrift erstellen
     *
     * Erstellt eine neue Gutschrift in der Lexware API.
     * Die Gutschrift kann entweder als Entwurf (Standard) oder direkt finalisiert erstellt werden.
     * Finalisierte Gutschriften erhalten sofort eine Gutschriftsnummer.
     *
     * POST /api/integrations/lexware/credit-notes
     *
     * Query-Parameter:
     * - finalize (bool): Wenn true, wird die Gutschrift direkt finalisiert (Standard: false)
     *
     * Request-Body (JSON) - Beispiel Gutschrift an Kontakt:
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
     * - address kann entweder contactId (bestehender Kontakt) oder manuelle Adressdaten enthalten
     * - lineItems.type kann 'custom' (freier Text) oder 'material' (Artikel) sein
     * - taxConditions.taxType kann 'net', 'gross' oder 'vatfree' sein
     * - Bei finalize=true wird die Gutschrift sofort abgeschlossen und erhält eine Nummer
     *
     * @param Request $request HTTP-Request mit Gutschriftdaten im Body
     * @return JsonResponse Erstellte Gutschrift-Metadaten oder Fehlermeldung
     */
    public function createCreditNote(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();
            $finalize = filter_var($request->get('finalize', false), FILTER_VALIDATE_BOOLEAN);

            $result = $this->lexwareApiService->createCreditNote($user, $data, $finalize);

            return response()->json($result, 201);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Gutschrift als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für eine finalisierte Gutschrift.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * GET /api/integrations/lexware/credit-notes/{id}/pdf
     *
     * URL-Parameter:
     * - id (string): Die UUID der Gutschrift
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/credit-notes/c1d2e3f4-a5b6-7890-cdef-123456789abc/pdf
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Die Gutschrift muss finalisiert sein (voucherStatus != 'draft')
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann nach einiger Zeit ablaufen
     * - Für den Download verwende GET /api/integrations/lexware/credit-notes/{id}/download
     *   oder GET /api/integrations/lexware/files/{documentFileId}
     *
     * Mögliche Fehler:
     * - 404: Gutschrift nicht gefunden
     * - 406: Gutschrift ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Gutschrift
     * @return JsonResponse documentFileId oder Fehlermeldung
     */
    public function creditNotePdf(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->renderCreditNotePdf($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Gutschrift als PDF herunterladen
     *
     * Rendert die Gutschrift als PDF und gibt das Dokument direkt zum Download zurück.
     * Dies ist eine Kombination aus renderCreditNotePdf() und downloadFile() in einem Request.
     *
     * GET /api/integrations/lexware/credit-notes/{id}/download
     *
     * URL-Parameter:
     * - id (string): Die UUID der Gutschrift
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/credit-notes/c1d2e3f4-a5b6-7890-cdef-123456789abc/download
     *
     * Beispiel-Response:
     * Content-Type: application/pdf
     * Content-Disposition: attachment; filename="credit-note-{id}.pdf"
     * (Binäre PDF-Daten)
     *
     * Voraussetzungen:
     * - Die Gutschrift muss finalisiert sein (voucherStatus != 'draft')
     *
     * Mögliche Fehler:
     * - 404: Gutschrift nicht gefunden
     * - 406: Gutschrift ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Gutschrift
     * @return \Illuminate\Http\Response PDF-Download oder JsonResponse bei Fehler
     */
    public function downloadCreditNote(Request $request, string $id)
    {
        try {
            $user = $request->user();

            // Zuerst PDF rendern und documentFileId abrufen
            $renderResult = $this->lexwareApiService->renderCreditNotePdf($user, $id);

            if (!isset($renderResult['documentFileId'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'pdf_render_failed',
                        'message' => 'PDF konnte nicht gerendert werden.',
                        'http_status' => 500,
                    ],
                ], 500);
            }

            // PDF herunterladen
            $pdfContent = $this->lexwareApiService->downloadFile($user, $renderResult['documentFileId']);

            // PDF als Download zurückgeben
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"credit-note-{$id}.pdf\"")
                ->header('Content-Length', strlen($pdfContent));
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Deeplink zur Gutschrift in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zur Gutschrift in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zur Gutschrift in Lexoffice weiterzuleiten.
     *
     * GET /api/integrations/lexware/credit-notes/{id}/deeplink
     *
     * URL-Parameter:
     * - id (string): Die UUID der Gutschrift
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/credit-notes/c1d2e3f4-a5b6-7890-cdef-123456789abc/deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/creditnote/c1d2e3f4-a5b6-7890-cdef-123456789abc"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn die Gutschrift existiert und der Benutzer Zugriff hat
     * - Dieser Endpunkt validiert NICHT, ob die Gutschrift existiert (für schnelle Response)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Gutschrift
     * @return JsonResponse Array mit dem Deeplink
     */
    public function creditNoteDeeplink(Request $request, string $id): JsonResponse
    {
        $result = $this->lexwareApiService->getCreditNoteDeeplink($id);

        return response()->json($result);
    }

    // =========================================================================
    // LIEFERSCHEINE (DELIVERY NOTES)
    // =========================================================================

    /**
     * Lieferscheine abrufen (paginiert)
     *
     * Ruft eine Liste von Lieferscheinen aus der Lexware API ab.
     * Unterstützt Paginierung über die Query-Parameter 'page' und 'size'.
     *
     * GET /api/integrations/lexware/delivery-notes
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/delivery-notes?page=0&size=25
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
     *       "contactName": "Muster GmbH",
     *       "archived": false
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 2,
     *   "totalElements": 30,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param Request $request HTTP-Request mit optionalen Paginierungsparametern
     * @return JsonResponse Liste der Lieferscheine oder Fehlermeldung
     */
    public function deliveryNotes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = (int) $request->get('page', 0);
            $size = (int) $request->get('size', 25);

            $result = $this->lexwareApiService->getDeliveryNotes($user, $page, $size);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Einzelnen Lieferschein abrufen
     *
     * Ruft einen einzelnen Lieferschein anhand seiner UUID aus der Lexware API ab.
     * Gibt alle Details des Lieferscheins zurück, inklusive Positionen und Lieferinformationen.
     *
     * GET /api/integrations/lexware/delivery-notes/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID des Lieferscheins
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/delivery-notes/d1e2f3a4-b5c6-7890-defg-123456789hij
     *
     * Beispiel-Response:
     * {
     *   "id": "d1e2f3a4-b5c6-7890-defg-123456789hij",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "version": 1,
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
     *       "type": "custom",
     *       "name": "Produkt A",
     *       "quantity": 5,
     *       "unitName": "Stück"
     *     }
     *   ],
     *   "title": "Lieferschein",
     *   "introduction": "Hiermit liefern wir Ihnen folgende Artikel.",
     *   "remark": "Bitte prüfen Sie die Lieferung auf Vollständigkeit."
     * }
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Lieferscheins
     * @return JsonResponse Lieferscheindaten oder Fehlermeldung
     */
    public function deliveryNote(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getDeliveryNote($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Lieferschein erstellen
     *
     * Erstellt einen neuen Lieferschein in der Lexware API.
     * Der Lieferschein kann entweder als Entwurf (Standard) oder direkt finalisiert erstellt werden.
     * Lieferscheine dokumentieren Warenlieferungen ohne Preisangaben.
     *
     * POST /api/integrations/lexware/delivery-notes
     *
     * Query-Parameter:
     * - finalize (bool): Wenn true, wird der Lieferschein direkt finalisiert (Standard: false)
     *
     * Request-Body (JSON) - Beispiel Lieferschein an Kontakt:
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
     * - address kann entweder contactId (bestehender Kontakt) oder manuelle Adressdaten enthalten
     * - lineItems enthalten Mengen, aber keine Preise (Lieferschein-typisch)
     * - shippingConditions.shippingType kann 'delivery', 'pickup', etc. sein
     * - Bei finalize=true wird der Lieferschein sofort abgeschlossen und erhält eine Nummer
     *
     * @param Request $request HTTP-Request mit Lieferscheindaten im Body
     * @return JsonResponse Erstellte Lieferschein-Metadaten oder Fehlermeldung
     */
    public function createDeliveryNote(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();
            $finalize = filter_var($request->get('finalize', false), FILTER_VALIDATE_BOOLEAN);

            $result = $this->lexwareApiService->createDeliveryNote($user, $data, $finalize);

            return response()->json($result, 201);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Lieferschein als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für einen finalisierten Lieferschein.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * GET /api/integrations/lexware/delivery-notes/{id}/pdf
     *
     * URL-Parameter:
     * - id (string): Die UUID des Lieferscheins
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/delivery-notes/d1e2f3a4-b5c6-7890-defg-123456789hij/pdf
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Der Lieferschein muss finalisiert sein (voucherStatus != 'draft')
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann nach einiger Zeit ablaufen
     * - Für den Download verwende GET /api/integrations/lexware/delivery-notes/{id}/download
     *   oder GET /api/integrations/lexware/files/{documentFileId}
     *
     * Mögliche Fehler:
     * - 404: Lieferschein nicht gefunden
     * - 406: Lieferschein ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Lieferscheins
     * @return JsonResponse documentFileId oder Fehlermeldung
     */
    public function deliveryNotePdf(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->renderDeliveryNotePdf($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Lieferschein als PDF herunterladen
     *
     * Rendert den Lieferschein als PDF und gibt das Dokument direkt zum Download zurück.
     * Dies ist eine Kombination aus renderDeliveryNotePdf() und downloadFile() in einem Request.
     *
     * GET /api/integrations/lexware/delivery-notes/{id}/download
     *
     * URL-Parameter:
     * - id (string): Die UUID des Lieferscheins
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/delivery-notes/d1e2f3a4-b5c6-7890-defg-123456789hij/download
     *
     * Beispiel-Response:
     * Content-Type: application/pdf
     * Content-Disposition: attachment; filename="delivery-note-{id}.pdf"
     * (Binäre PDF-Daten)
     *
     * Voraussetzungen:
     * - Der Lieferschein muss finalisiert sein (voucherStatus != 'draft')
     *
     * Mögliche Fehler:
     * - 404: Lieferschein nicht gefunden
     * - 406: Lieferschein ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Lieferscheins
     * @return \Illuminate\Http\Response PDF-Download oder JsonResponse bei Fehler
     */
    public function downloadDeliveryNote(Request $request, string $id)
    {
        try {
            $user = $request->user();

            // Zuerst PDF rendern und documentFileId abrufen
            $renderResult = $this->lexwareApiService->renderDeliveryNotePdf($user, $id);

            if (!isset($renderResult['documentFileId'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'pdf_render_failed',
                        'message' => 'PDF konnte nicht gerendert werden.',
                        'http_status' => 500,
                    ],
                ], 500);
            }

            // PDF herunterladen
            $pdfContent = $this->lexwareApiService->downloadFile($user, $renderResult['documentFileId']);

            // PDF als Download zurückgeben
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"delivery-note-{$id}.pdf\"")
                ->header('Content-Length', strlen($pdfContent));
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Deeplink zum Lieferschein in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zum Lieferschein in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zum Lieferschein in Lexoffice weiterzuleiten.
     *
     * GET /api/integrations/lexware/delivery-notes/{id}/deeplink
     *
     * URL-Parameter:
     * - id (string): Die UUID des Lieferscheins
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/delivery-notes/d1e2f3a4-b5c6-7890-defg-123456789hij/deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/delivery-note/d1e2f3a4-b5c6-7890-defg-123456789hij"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn der Lieferschein existiert und der Benutzer Zugriff hat
     * - Dieser Endpunkt validiert NICHT, ob der Lieferschein existiert (für schnelle Response)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Lieferscheins
     * @return JsonResponse Array mit dem Deeplink
     */
    public function deliveryNoteDeeplink(Request $request, string $id): JsonResponse
    {
        $result = $this->lexwareApiService->getDeliveryNoteDeeplink($id);

        return response()->json($result);
    }

    // =========================================================================
    // MAHNUNGEN (DUNNINGS)
    // =========================================================================

    /**
     * Mahnungen abrufen (paginiert)
     *
     * Ruft eine Liste von Mahnungen aus der Lexware API ab.
     * Unterstützt Paginierung über die Query-Parameter 'page' und 'size'.
     * Mahnungen werden erstellt, um Kunden an offene Forderungen zu erinnern.
     *
     * GET /api/integrations/lexware/dunnings
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/dunnings?page=0&size=25
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
     *   "number": 0
     * }
     *
     * @param Request $request HTTP-Request mit optionalen Paginierungsparametern
     * @return JsonResponse Liste der Mahnungen oder Fehlermeldung
     */
    public function dunnings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = (int) $request->get('page', 0);
            $size = (int) $request->get('size', 25);

            $result = $this->lexwareApiService->getDunnings($user, $page, $size);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Einzelne Mahnung abrufen
     *
     * Ruft eine einzelne Mahnung anhand ihrer UUID aus der Lexware API ab.
     * Gibt alle Details der Mahnung zurück, inklusive Positionen, Adressen und Zahlungsinformationen.
     *
     * GET /api/integrations/lexware/dunnings/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID der Mahnung
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/dunnings/a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * Beispiel-Response:
     * {
     *   "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *   "organizationId": "aa93e8a8-2aa3-470b-b914-caad8a255dd8",
     *   "version": 1,
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
     *       "type": "custom",
     *       "name": "Offener Rechnungsbetrag RE-2024-001",
     *       "quantity": 1,
     *       "unitName": "Stück",
     *       "unitPrice": {
     *         "currency": "EUR",
     *         "netAmount": 1000.00,
     *         "grossAmount": 1190.00,
     *         "taxRatePercentage": 19
     *       }
     *     }
     *   ],
     *   "totalPrice": {
     *     "currency": "EUR",
     *     "totalNetAmount": 1000.00,
     *     "totalGrossAmount": 1190.00,
     *     "totalTaxAmount": 190.00
     *   },
     *   "title": "1. Mahnung",
     *   "introduction": "Leider konnten wir für folgende Rechnung noch keinen Zahlungseingang feststellen.",
     *   "remark": "Bitte überweisen Sie den offenen Betrag innerhalb von 7 Tagen."
     * }
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Mahnung
     * @return JsonResponse Mahnungsdaten oder Fehlermeldung
     */
    public function dunning(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getDunning($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Mahnung erstellen
     *
     * Erstellt eine neue Mahnung in der Lexware API.
     * Die Mahnung kann entweder als Entwurf (Standard) oder direkt finalisiert erstellt werden.
     * Mahnungen werden verwendet, um Kunden an offene Forderungen zu erinnern.
     *
     * POST /api/integrations/lexware/dunnings
     *
     * Query-Parameter:
     * - finalize (bool): Wenn true, wird die Mahnung direkt finalisiert (Standard: false)
     *
     * Request-Body (JSON) - Beispiel Mahnung an Kontakt:
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
     * - address kann entweder contactId (bestehender Kontakt) oder manuelle Adressdaten enthalten
     * - lineItems müssen Preisangaben enthalten
     * - taxConditions.taxType kann 'net' (Netto) oder 'gross' (Brutto) sein
     * - Bei finalize=true wird die Mahnung sofort abgeschlossen und erhält eine Nummer
     *
     * @param Request $request HTTP-Request mit Mahnungsdaten im Body
     * @return JsonResponse Erstellte Mahnungs-Metadaten oder Fehlermeldung
     */
    public function createDunning(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();
            $finalize = filter_var($request->get('finalize', false), FILTER_VALIDATE_BOOLEAN);

            $result = $this->lexwareApiService->createDunning($user, $data, $finalize);

            return response()->json($result, 201);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Mahnung als PDF rendern (Document-ID abrufen)
     *
     * Triggert die Erstellung eines PDF-Dokuments für eine finalisierte Mahnung.
     * Gibt die documentFileId zurück, die für den Download verwendet werden kann.
     *
     * GET /api/integrations/lexware/dunnings/{id}/pdf
     *
     * URL-Parameter:
     * - id (string): Die UUID der Mahnung
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/dunnings/a1b2c3d4-e5f6-7890-abcd-ef1234567890/pdf
     *
     * Beispiel-Response:
     * {
     *   "documentFileId": "7f9b5e4a-3c8d-4e2a-9f6b-1d8c7a5e3b2f"
     * }
     *
     * Voraussetzungen:
     * - Die Mahnung muss finalisiert sein (voucherStatus != 'draft')
     *
     * Hinweise:
     * - Die documentFileId ist temporär und kann nach einiger Zeit ablaufen
     * - Für den Download verwende GET /api/integrations/lexware/dunnings/{id}/download
     *   oder GET /api/integrations/lexware/files/{documentFileId}
     *
     * Mögliche Fehler:
     * - 404: Mahnung nicht gefunden
     * - 406: Mahnung ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Mahnung
     * @return JsonResponse documentFileId oder Fehlermeldung
     */
    public function dunningPdf(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->renderDunningPdf($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Mahnung als PDF herunterladen
     *
     * Rendert die Mahnung als PDF und gibt das Dokument direkt zum Download zurück.
     * Dies ist eine Kombination aus renderDunningPdf() und downloadFile() in einem Request.
     *
     * GET /api/integrations/lexware/dunnings/{id}/download
     *
     * URL-Parameter:
     * - id (string): Die UUID der Mahnung
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/dunnings/a1b2c3d4-e5f6-7890-abcd-ef1234567890/download
     *
     * Beispiel-Response:
     * Content-Type: application/pdf
     * Content-Disposition: attachment; filename="dunning-{id}.pdf"
     * (Binäre PDF-Daten)
     *
     * Voraussetzungen:
     * - Die Mahnung muss finalisiert sein (voucherStatus != 'draft')
     *
     * Mögliche Fehler:
     * - 404: Mahnung nicht gefunden
     * - 406: Mahnung ist noch ein Entwurf (nicht finalisiert)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Mahnung
     * @return \Illuminate\Http\Response PDF-Download oder JsonResponse bei Fehler
     */
    public function downloadDunning(Request $request, string $id)
    {
        try {
            $user = $request->user();

            // Zuerst PDF rendern und documentFileId abrufen
            $renderResult = $this->lexwareApiService->renderDunningPdf($user, $id);

            if (!isset($renderResult['documentFileId'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'pdf_render_failed',
                        'message' => 'PDF konnte nicht gerendert werden.',
                        'http_status' => 500,
                    ],
                ], 500);
            }

            // PDF herunterladen
            $pdfContent = $this->lexwareApiService->downloadFile($user, $renderResult['documentFileId']);

            // PDF als Download zurückgeben
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"dunning-{$id}.pdf\"")
                ->header('Content-Length', strlen($pdfContent));
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Deeplink zur Mahnung in Lexoffice abrufen
     *
     * Gibt einen Deep-Link zurück, der direkt zur Mahnung in der Lexoffice Web-Oberfläche führt.
     * Dieser Link kann verwendet werden, um Benutzer direkt zur Mahnung in Lexoffice weiterzuleiten.
     *
     * GET /api/integrations/lexware/dunnings/{id}/deeplink
     *
     * URL-Parameter:
     * - id (string): Die UUID der Mahnung
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/dunnings/a1b2c3d4-e5f6-7890-abcd-ef1234567890/deeplink
     *
     * Beispiel-Response:
     * {
     *   "deeplink": "https://app.lexoffice.de/vouchers#!/view/dunning/a1b2c3d4-e5f6-7890-abcd-ef1234567890"
     * }
     *
     * Hinweise:
     * - Der Benutzer muss in Lexoffice eingeloggt sein, um den Link nutzen zu können
     * - Der Link funktioniert nur, wenn die Mahnung existiert und der Benutzer Zugriff hat
     * - Dieser Endpunkt validiert NICHT, ob die Mahnung existiert (für schnelle Response)
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Mahnung
     * @return JsonResponse Array mit dem Deeplink
     */
    public function dunningDeeplink(Request $request, string $id): JsonResponse
    {
        $result = $this->lexwareApiService->getDunningDeeplink($id);

        return response()->json($result);
    }

    /**
     * Profil abrufen
     *
     * Ruft das Profil des verbundenen Lexoffice-Kontos aus der Lexware API ab.
     * Gibt Informationen über die Organisation zurück, die mit dem API-Token verknüpft ist.
     * Dieser Endpunkt ist nützlich, um die Verbindung zu validieren und Kontoinformationen abzurufen.
     *
     * GET /api/integrations/lexware/profile
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/profile
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
     * - taxType (string): Standard-Steuertyp ("net" = Netto, "gross" = Brutto)
     * - smallBusiness (bool): true wenn Kleinunternehmerregelung nach §19 UStG gilt
     * - subscriptionStatus (string): Status des Lexoffice-Abonnements ("active", "trial", etc.)
     *
     * Hinweise:
     * - Dieser Endpunkt erfordert keine speziellen Berechtigungen
     * - Kann verwendet werden, um die Gültigkeit des API-Tokens zu prüfen
     * - Bei smallBusiness=true wird keine Umsatzsteuer auf Rechnungen ausgewiesen
     * - Die subscriptionStatus gibt Auskunft über den Abrechnungsstatus des Lexoffice-Kontos
     *
     * Mögliche Fehler:
     * - 401: Unauthorized - API-Token ungültig oder abgelaufen
     * - 500: Internal Server Error - Keine Verbindung zu Lexoffice konfiguriert
     *
     * @see https://developers.lexoffice.io/docs/#profile-endpoint
     *
     * @param Request $request HTTP-Request
     * @return JsonResponse Profildaten der verbundenen Organisation oder Fehlermeldung
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getProfile($user);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Verbindung testen
     *
     * GET /api/integrations/lexware/test
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->testConnection($user);

            return response()->json([
                'success' => true,
                'message' => 'Verbindung erfolgreich.',
                'data' => $result,
            ]);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    // =========================================================================
    // ARTIKEL (ARTICLES)
    // =========================================================================

    /**
     * Artikel abrufen (paginiert)
     *
     * Ruft eine Liste von Artikeln aus der Lexware API ab.
     * Unterstützt Paginierung über die Query-Parameter 'page' und 'size'.
     *
     * GET /api/integrations/lexware/articles
     *
     * Query-Parameter:
     * - page (int): Seitennummer, 0-basiert (Standard: 0)
     * - size (int): Anzahl Elemente pro Seite, max. 250 (Standard: 25)
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/articles?page=0&size=25
     *
     * Beispiel-Response:
     * {
     *   "content": [
     *     {
     *       "id": "eb46d328-e1dc-11ee-8e52-2fadfc15a567",
     *       "articleNumber": "ART-001",
     *       "title": "Beispielartikel",
     *       "type": "PRODUCT",
     *       "unitName": "Stück",
     *       "price": { "netPrice": 100.00, "grossPrice": 119.00, "taxRate": 19.0 }
     *     }
     *   ],
     *   "first": true,
     *   "last": false,
     *   "totalPages": 5,
     *   "totalElements": 100,
     *   "size": 25,
     *   "number": 0
     * }
     *
     * @param Request $request HTTP-Request mit optionalen Paginierungsparametern
     * @return JsonResponse Liste der Artikel oder Fehlermeldung
     */
    public function articles(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = (int) $request->get('page', 0);
            $size = (int) $request->get('size', 25);

            $result = $this->lexwareApiService->getArticles($user, $page, $size);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Einzelnen Artikel abrufen
     *
     * Ruft einen einzelnen Artikel anhand seiner UUID aus der Lexware API ab.
     *
     * GET /api/integrations/lexware/articles/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID des Artikels
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/articles/eb46d328-e1dc-11ee-8e52-2fadfc15a567
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
     *   "description": "Detaillierte Beschreibung",
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
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des Artikels
     * @return JsonResponse Artikeldaten oder Fehlermeldung
     */
    public function article(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getArticle($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Artikel erstellen
     *
     * Erstellt einen neuen Artikel in der Lexware API.
     * Mindestens 'title' und 'type' müssen im Request-Body angegeben werden.
     *
     * POST /api/integrations/lexware/articles
     *
     * Request-Body (JSON):
     * {
     *   "title": "Neuer Artikel",
     *   "description": "Beschreibung des Artikels",
     *   "type": "PRODUCT",           // PRODUCT oder SERVICE
     *   "articleNumber": "ART-002",  // Optional, wird sonst generiert
     *   "unitName": "Stück",         // Optional
     *   "price": {                   // Optional
     *     "netPrice": 50.00,
     *     "grossPrice": 59.50,
     *     "leadingPrice": "NET",     // NET oder GROSS
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
     * @param Request $request HTTP-Request mit Artikeldaten im Body
     * @return JsonResponse Erstellte Artikel-Metadaten oder Fehlermeldung
     */
    public function createArticle(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();

            $result = $this->lexwareApiService->createArticle($user, $data);

            return response()->json($result, 201);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Artikel aktualisieren
     *
     * Aktualisiert einen bestehenden Artikel in der Lexware API.
     * Die aktuelle 'version' muss im Request-Body mitgegeben werden (Optimistic Locking).
     *
     * PUT /api/integrations/lexware/articles/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID des zu aktualisierenden Artikels
     *
     * Request-Body (JSON):
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
     *   "version": 1  // Erforderlich für Optimistic Locking
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
     * @param Request $request HTTP-Request mit aktualisierten Artikeldaten
     * @param string $id Die UUID des Artikels
     * @return JsonResponse Aktualisierte Artikel-Metadaten oder Fehlermeldung
     */
    public function updateArticle(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();

            $result = $this->lexwareApiService->updateArticle($user, $id, $data);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Artikel löschen
     *
     * Löscht einen Artikel aus der Lexware API.
     * Hinweis: Artikel können nur gelöscht werden, wenn sie nicht in Belegen verwendet werden.
     *
     * DELETE /api/integrations/lexware/articles/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID des zu löschenden Artikels
     *
     * Beispiel-Request:
     * DELETE /api/integrations/lexware/articles/eb46d328-e1dc-11ee-8e52-2fadfc15a567
     *
     * Beispiel-Response bei Erfolg:
     * HTTP 204 No Content
     *
     * Mögliche Fehler:
     * - 404: Artikel nicht gefunden
     * - 409: Artikel wird in Belegen verwendet und kann nicht gelöscht werden
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID des zu löschenden Artikels
     * @return JsonResponse Leere Response (204) oder Fehlermeldung
     */
    public function deleteArticle(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $this->lexwareApiService->deleteArticle($user, $id);

            return response()->json(null, 204);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    // =========================================================================
    // LÄNDER (COUNTRIES)
    // =========================================================================

    /**
     * Länder abrufen
     *
     * Ruft die Liste aller verfügbaren Länder aus der Lexware API ab.
     * Diese Länder können in Adressen (billing, shipping) bei Kontakten verwendet werden.
     * Der Ländercode entspricht dem ISO 3166-1 alpha-2 Standard.
     *
     * GET /api/integrations/lexware/countries
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
     *     "taxClassification": "de"
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
     * - Diese Länder werden bei der Adressvalidierung in Kontakten verwendet
     *
     * @param Request $request HTTP-Request
     * @return JsonResponse Liste aller verfügbaren Länder oder Fehlermeldung
     */
    public function countries(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getCountries($user);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
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
     * GET /api/integrations/lexware/posting-categories
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
     *
     * @param Request $request HTTP-Request
     * @return JsonResponse Liste aller verfügbaren Buchungskategorien oder Fehlermeldung
     */
    public function postingCategories(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getPostingCategories($user);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    // =========================================================================
    // EVENT-SUBSCRIPTIONS (WEBHOOKS)
    // =========================================================================

    /**
     * Event-Subscriptions abrufen (alle Webhooks auflisten)
     *
     * Ruft alle registrierten Event-Subscriptions (Webhooks) aus der Lexware API ab.
     * Im Gegensatz zu anderen Listen-Endpunkten ist diese Liste NICHT paginiert.
     *
     * GET /api/integrations/lexware/event-subscriptions
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/event-subscriptions
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
     * - Alle aktiven Subscriptions werden zurückgegeben (keine Paginierung)
     * - Gelöschte Subscriptions erscheinen nicht in der Liste
     *
     * @param Request $request HTTP-Request
     * @return JsonResponse Liste aller Event-Subscriptions oder Fehlermeldung
     */
    public function eventSubscriptions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getEventSubscriptions($user);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Einzelne Event-Subscription abrufen
     *
     * Ruft eine einzelne Event-Subscription anhand ihrer UUID aus der Lexware API ab.
     * Gibt Details zur Subscription zurück, inklusive Event-Typ und Callback-URL.
     *
     * GET /api/integrations/lexware/event-subscriptions/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID der Event-Subscription
     *
     * Beispiel-Request:
     * GET /api/integrations/lexware/event-subscriptions/a2691815-4f13-48e8-a7e9-3990be5b5f1d
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
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der Event-Subscription
     * @return JsonResponse Subscription-Daten oder Fehlermeldung
     */
    public function eventSubscription(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->lexwareApiService->getEventSubscription($user, $id);

            return response()->json($result);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Event-Subscription erstellen (Webhook registrieren)
     *
     * Erstellt eine neue Event-Subscription (Webhook) in der Lexware API.
     * Mit Event-Subscriptions können Sie über Änderungen an Ressourcen benachrichtigt werden.
     *
     * POST /api/integrations/lexware/event-subscriptions
     *
     * Request-Body (JSON):
     * {
     *   "eventType": "contact.changed",
     *   "callbackUrl": "https://example.com/webhooks/lexware/contacts"
     * }
     *
     * Verfügbare Event-Typen:
     * - contact.created, contact.changed, contact.deleted
     * - invoice.created, invoice.changed, invoice.deleted, invoice.status.changed
     * - quotation.created, quotation.changed, quotation.deleted, quotation.status.changed
     * - order-confirmation.created, order-confirmation.changed, order-confirmation.deleted, order-confirmation.status.changed
     * - credit-note.created, credit-note.changed, credit-note.deleted, credit-note.status.changed
     * - delivery-note.created, delivery-note.changed, delivery-note.deleted
     * - down-payment-invoice.created, down-payment-invoice.changed, down-payment-invoice.deleted, down-payment-invoice.status.changed
     * - recurring-template.created, recurring-template.changed, recurring-template.deleted
     * - payment.changed
     * - article.created, article.changed, article.deleted
     * - dunning.created, dunning.changed, dunning.deleted
     * - token.revoked
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
     * Validierungshinweise:
     * - eventType: Erforderlich, muss ein gültiger Event-Typ sein
     * - callbackUrl: Erforderlich, muss eine gültige HTTPS-URL sein (localhost für Entwicklung erlaubt)
     * - Pro Event-Typ kann nur eine Subscription existieren (Duplikat = 409 Conflict)
     *
     * @param Request $request HTTP-Request mit eventType und callbackUrl im Body
     * @return JsonResponse Erstellte Subscription-Daten oder Fehlermeldung
     */
    public function createEventSubscription(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->all();

            $result = $this->lexwareApiService->createEventSubscription($user, $data);

            return response()->json($result, 201);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Event-Subscription löschen (Webhook abmelden)
     *
     * Löscht eine Event-Subscription aus der Lexware API.
     * Nach dem Löschen werden keine weiteren Webhook-Benachrichtigungen
     * für diesen Event-Typ an die registrierte Callback-URL gesendet.
     *
     * DELETE /api/integrations/lexware/event-subscriptions/{id}
     *
     * URL-Parameter:
     * - id (string): Die UUID der zu löschenden Event-Subscription
     *
     * Beispiel-Request:
     * DELETE /api/integrations/lexware/event-subscriptions/a2691815-4f13-48e8-a7e9-3990be5b5f1d
     *
     * Beispiel-Response bei Erfolg:
     * HTTP 204 No Content
     *
     * Mögliche Fehler:
     * - 404: Event-Subscription nicht gefunden
     * - 401: Nicht autorisiert (ungültiger oder abgelaufener Token)
     *
     * Hinweise:
     * - Die Subscription kann jederzeit neu erstellt werden
     * - Diese Operation ist unwiderruflich
     *
     * @param Request $request HTTP-Request
     * @param string $id Die UUID der zu löschenden Event-Subscription
     * @return JsonResponse Leere Response (204) oder Fehlermeldung
     */
    public function deleteEventSubscription(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $this->lexwareApiService->deleteEventSubscription($user, $id);

            return response()->json(null, 204);
        } catch (LexwareApiException $e) {
            return $this->handleLexwareException($e);
        }
    }

    /**
     * Behandelt Lexware API Exceptions und gibt passende HTTP-Responses zurück
     */
    protected function handleLexwareException(LexwareApiException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $e->getLexwareErrorCode(),
                'message' => $e->getMessage(),
                'http_status' => $e->getCode(),
            ],
        ], $e->getCode() ?: 500);
    }
}
