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

    /**
     * Kontakte abrufen
     *
     * GET /api/integrations/lexware/contacts
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
     * GET /api/integrations/lexware/contacts/{id}
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
     * Rechnungen abrufen
     *
     * GET /api/integrations/lexware/invoices
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
     * GET /api/integrations/lexware/invoices/{id}
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
     * Angebote abrufen
     *
     * GET /api/integrations/lexware/quotations
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
     * GET /api/integrations/lexware/quotations/{id}
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
     * Gutschriften abrufen
     *
     * GET /api/integrations/lexware/credit-notes
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
     * Profil abrufen
     *
     * GET /api/integrations/lexware/profile
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
