<?php

namespace Platform\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Core\Models\User;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\IntegrationConnectionShareService;

class ConnectionShareController extends Controller
{
    public function __construct(
        protected IntegrationConnectionShareService $shareService,
    ) {}

    /**
     * GET /api/integrations/connections/{connection}/shares
     * Listet alle Freigaben einer Connection auf. Nur für Owner.
     */
    public function index(IntegrationConnection $connection): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $shares = $this->shareService->listShares($user, $connection);

        return response()->json([
            'data' => $shares,
            'connection_id' => $connection->id,
            'total' => $shares->count(),
        ]);
    }

    /**
     * POST /api/integrations/connections/{connection}/shares
     * Erstellt eine neue Freigabe. Nur für Owner.
     *
     * Body: { "team_id": int|null, "user_id": int|null }
     * Wildcard: null = alle (z.B. team_id=null → gilt für alle Teams)
     */
    public function store(Request $request, IntegrationConnection $connection): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $validated = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $share = $this->shareService->createShare(
                $user,
                $connection,
                $validated['team_id'] ?? null,
                $validated['user_id'] ?? null,
            );

            return response()->json([
                'data' => $this->shareService->formatShare($share),
                'message' => 'Freigabe erstellt.',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /api/integrations/connections/{connection}/shares/{share}
     * Entfernt eine Freigabe. Nur für Owner.
     */
    public function destroy(IntegrationConnection $connection, int $share): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->shareService->deleteShare($user, $connection, $share);

        return response()->json([
            'message' => 'Freigabe entfernt.',
        ]);
    }
}
