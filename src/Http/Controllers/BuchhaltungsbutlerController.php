<?php

namespace Platform\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Integrations\Exceptions\BuchhaltungsbutlerApiException;
use Platform\Integrations\Services\BuchhaltungsbutlerApiService;

/**
 * Controller für BuchhaltungsButler API-Endpunkte.
 *
 * Dünner HTTP-Wrapper um den BuchhaltungsbutlerApiService — die UI ruft hier
 * REST-Endpunkte auf, die intern in die BuchhaltungsButler-RPC-Calls übersetzt
 * werden (alle BB-Endpoints sind POST, auch Read-Operationen).
 */
class BuchhaltungsbutlerController extends Controller
{
    public function __construct(
        protected BuchhaltungsbutlerApiService $apiService,
    ) {}

    /**
     * GET /api/integrations/buchhaltungsbutler/test
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $service = $this->apiService->forConnection($request->get('connection_id'));
            $result  = $service->testConnection($request->user());

            return response()->json(['success' => true, 'data' => $result]);
        } catch (BuchhaltungsbutlerApiException $e) {
            return response()->json($e->toArray(), $e->getHttpStatusCode());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => ['message' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/integrations/buchhaltungsbutler/debtors?limit=25&offset=0
     */
    public function debtors(Request $request): JsonResponse
    {
        try {
            $limit  = (int) $request->get('limit', 25);
            $offset = (int) $request->get('offset', 0);

            $service = $this->apiService->forConnection($request->get('connection_id'));
            $result  = $service->getDebtors($request->user(), $limit, $offset);

            return response()->json($result);
        } catch (BuchhaltungsbutlerApiException $e) {
            return response()->json($e->toArray(), $e->getHttpStatusCode());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => ['message' => $e->getMessage()],
            ], 500);
        }
    }
}
