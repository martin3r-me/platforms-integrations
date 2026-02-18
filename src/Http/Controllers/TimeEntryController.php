<?php

namespace Platform\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\DTOs\TimeEntry\TimeEntryRequest;
use Platform\Integrations\DTOs\TimeEntry\BulkTimeEntryRequest;
use Platform\Integrations\Services\TimeEntryService;

/**
 * Controller für Zeiteinträge (TimeEntries)
 *
 * Bietet REST-Endpunkte für das Erstellen, Auflisten und Verwalten von Zeiteinträgen.
 * Unterstützt Einzel- und Bulk-Operationen.
 */
class TimeEntryController extends Controller
{
    protected TimeEntryService $timeEntryService;

    public function __construct(TimeEntryService $timeEntryService)
    {
        $this->timeEntryService = $timeEntryService;
    }

    // =========================================================================
    // EINZELNE ZEITEINTRÄGE
    // =========================================================================

    /**
     * Listet Zeiteinträge auf (paginiert, mit Filtern)
     *
     * GET /api/integrations/time-entries
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Benutzer nicht authentifiziert.',
            ], 401);
        }

        $filters = $request->only(['date_from', 'date_to', 'project_id', 'context', 'type', 'team_id']);
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 25), 100);

        try {
            $result = $this->timeEntryService->list($user, $filters, $page, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Zeiteinträge erfolgreich abgerufen.',
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
        } catch (\Throwable $e) {
            Log::error('TimeEntry Liste fehlgeschlagen', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Abrufen der Zeiteinträge.',
            ], 500);
        }
    }

    /**
     * Ruft einen einzelnen Zeiteintrag ab
     *
     * GET /api/integrations/time-entries/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Benutzer nicht authentifiziert.',
            ], 401);
        }

        try {
            $entry = $this->timeEntryService->get($user, $id);

            if (!$entry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zeiteintrag nicht gefunden.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Zeiteintrag erfolgreich abgerufen.',
                'data' => $entry,
            ]);
        } catch (\Throwable $e) {
            Log::error('TimeEntry Abfrage fehlgeschlagen', [
                'user_id' => $user->id,
                'entry_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Abrufen des Zeiteintrags.',
            ], 500);
        }
    }

    /**
     * Erstellt einen einzelnen Zeiteintrag (Stempeln)
     *
     * POST /api/integrations/time-entries
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Benutzer nicht authentifiziert.',
            ], 401);
        }

        try {
            $dto = TimeEntryRequest::fromRequest($request->all());
            $teamId = $request->input('team_id') ? (int) $request->input('team_id') : null;
            $entry = $this->timeEntryService->create($user, $dto, $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Zeiteintrag erfolgreich erstellt.',
                'data' => $entry,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('TimeEntry Erstellen fehlgeschlagen', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Erstellen des Zeiteintrags.',
            ], 500);
        }
    }

    // =========================================================================
    // BULK-OPERATIONEN
    // =========================================================================

    /**
     * Erstellt mehrere Zeiteinträge in einem Vorgang (Bulk-Stempeln)
     *
     * POST /api/integrations/time-entries/bulk
     *
     * Erwartet ein JSON-Body mit folgendem Format:
     * {
     *     "entries": [
     *         {
     *             "date": "2026-02-18",
     *             "start_time": "08:00",
     *             "end_time": "12:00",
     *             "project_id": 1,
     *             "project_name": "Projekt A",
     *             "context": "Entwicklung",
     *             "description": "Feature-Implementierung",
     *             "type": "work",
     *             "tags": ["feature", "backend"]
     *         },
     *         ...
     *     ]
     * }
     *
     * Response-Format (konsistent mit Plattform-Bulk-Endpoints):
     * {
     *     "success": true|false,
     *     "message": "...",
     *     "data": {
     *         "bulk_id": "uuid",
     *         "results": {
     *             "0": {"status": "success", "entry": {...}},
     *             "1": {"status": "error", "message": "...", "input": {...}}
     *         },
     *         "summary": {
     *             "total": 5,
     *             "success": 4,
     *             "errors": 1
     *         }
     *     }
     * }
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Benutzer nicht authentifiziert.',
            ], 401);
        }

        // Schritt 1: Bulk-DTO erstellen und validieren
        $parsed = BulkTimeEntryRequest::fromRequest($request->all());

        // Schritt 2: Strukturfehler prüfen
        if (!empty($parsed['errors']) && $parsed['dto'] === null) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler in der Bulk-Anfrage.',
                'errors' => $parsed['errors'],
            ], 422);
        }

        // Schritt 3: Validierungsfehler bei einzelnen Einträgen melden
        if (!empty($parsed['errors']) && $parsed['dto'] !== null) {
            // Es gibt sowohl gültige als auch ungültige Einträge
            // Wir informieren über die Fehler, verarbeiten aber die gültigen
            $validationErrors = $parsed['errors'];
        } else {
            $validationErrors = [];
        }

        try {
            $teamId = $request->input('team_id') ? (int) $request->input('team_id') : null;
            $result = $this->timeEntryService->createBulk($user, $parsed['dto'], $teamId);

            // Validierungsfehler in die Ergebnisse einbeziehen
            if (!empty($validationErrors)) {
                $result['validation_errors'] = $validationErrors;
                $result['summary']['total'] = ($result['summary']['total'] ?? 0) + ($validationErrors['summary']['invalid'] ?? 0);
                $result['summary']['validation_errors'] = $validationErrors['summary']['invalid'] ?? 0;
            }

            $allSuccessful = ($result['summary']['errors'] ?? 0) === 0 && empty($validationErrors);
            $statusCode = $allSuccessful ? 201 : 207; // 207 Multi-Status bei Teilerfolg

            return response()->json([
                'success' => $allSuccessful,
                'message' => $this->buildBulkMessage($result['summary']),
                'data' => $result,
            ], $statusCode);
        } catch (\Throwable $e) {
            Log::error('TimeEntry Bulk-Erstellen fehlgeschlagen', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Bulk-Erstellen der Zeiteinträge.',
            ], 500);
        }
    }

    /**
     * Erzeugt eine lesbare Nachricht für das Bulk-Ergebnis
     */
    protected function buildBulkMessage(array $summary): string
    {
        $total = $summary['total'] ?? 0;
        $success = $summary['success'] ?? 0;
        $errors = $summary['errors'] ?? 0;
        $validationErrors = $summary['validation_errors'] ?? 0;

        if ($errors === 0 && $validationErrors === 0) {
            return "{$success} von {$total} Zeiteinträgen erfolgreich erstellt.";
        }

        $failedTotal = $errors + $validationErrors;
        return "{$success} von {$total} Zeiteinträgen erstellt. {$failedTotal} fehlgeschlagen.";
    }
}
