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
