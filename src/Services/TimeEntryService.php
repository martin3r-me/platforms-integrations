<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Platform\Core\Models\User;
use Platform\Integrations\DTOs\TimeEntry\TimeEntryRequest;
use Platform\Integrations\DTOs\TimeEntry\BulkTimeEntryRequest;
use Platform\Integrations\Models\TimeEntry;

/**
 * Service für die Verwaltung von Zeiteinträgen (TimeEntries)
 *
 * Bietet Methoden zum Erstellen, Auflisten und Verwalten von Zeiteinträgen.
 * Unterstützt sowohl Einzel- als auch Bulk-Operationen.
 */
class TimeEntryService
{
    // =========================================================================
    // EINZELNE ZEITEINTRÄGE
    // =========================================================================

    /**
     * Erstellt einen einzelnen Zeiteintrag
     */
    public function create(User $user, TimeEntryRequest $dto, ?int $teamId = null, string $source = 'manual'): TimeEntry
    {
        return TimeEntry::create([
            'user_id' => $user->id,
            'team_id' => $teamId,
            'date' => $dto->date,
            'start_time' => $dto->startTime,
            'end_time' => $dto->endTime,
            'duration_minutes' => $dto->getDurationMinutes(),
            'project_id' => $dto->projectId,
            'project_name' => $dto->projectName,
            'context' => $dto->context,
            'description' => $dto->description,
            'type' => $dto->type ?? 'work',
            'tags' => $dto->tags,
            'source' => $source,
        ]);
    }

    // =========================================================================
    // BULK-OPERATIONEN
    // =========================================================================

    /**
     * Erstellt mehrere Zeiteinträge in einem Vorgang (Bulk-Stempeln)
     *
     * Validiert jeden Eintrag einzeln und gibt detailliertes Feedback pro Eintrag.
     * Verwendet eine Datenbank-Transaktion für Konsistenz.
     *
     * @param User $user Der authentifizierte Benutzer
     * @param BulkTimeEntryRequest $bulkDto Die validierten Bulk-Daten
     * @param int|null $teamId Optionale Team-ID
     * @return array{bulk_id: string, results: array, summary: array}
     */
    public function createBulk(User $user, BulkTimeEntryRequest $bulkDto, ?int $teamId = null): array
    {
        $bulkId = (string) Str::uuid();
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        DB::beginTransaction();

        try {
            foreach ($bulkDto->entries as $index => $entryDto) {
                try {
                    $entry = TimeEntry::create([
                        'user_id' => $user->id,
                        'team_id' => $teamId,
                        'date' => $entryDto->date,
                        'start_time' => $entryDto->startTime,
                        'end_time' => $entryDto->endTime,
                        'duration_minutes' => $entryDto->getDurationMinutes(),
                        'project_id' => $entryDto->projectId,
                        'project_name' => $entryDto->projectName,
                        'context' => $entryDto->context,
                        'description' => $entryDto->description,
                        'type' => $entryDto->type ?? 'work',
                        'tags' => $entryDto->tags,
                        'source' => 'bulk',
                        'bulk_id' => $bulkId,
                    ]);

                    $results[$index] = [
                        'status' => 'success',
                        'entry' => $entry->toArray(),
                    ];
                    $successCount++;
                } catch (\Throwable $e) {
                    $results[$index] = [
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'input' => $entryDto->toArray(),
                    ];
                    $errorCount++;

                    Log::warning('TimeEntry Bulk: Eintrag fehlgeschlagen', [
                        'index' => $index,
                        'user_id' => $user->id,
                        'bulk_id' => $bulkId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Nur committen wenn mindestens ein Eintrag erfolgreich war
            if ($successCount > 0) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('TimeEntry Bulk: Transaktion fehlgeschlagen', [
                'user_id' => $user->id,
                'bulk_id' => $bulkId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return [
            'bulk_id' => $bulkId,
            'results' => $results,
            'summary' => [
                'total' => count($bulkDto->entries),
                'success' => $successCount,
                'errors' => $errorCount,
            ],
        ];
    }

    // =========================================================================
    // ABFRAGEN
    // =========================================================================

    /**
     * Listet Zeiteinträge eines Benutzers auf (paginiert)
     */
    public function list(User $user, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $query = TimeEntry::where('user_id', $user->id);

        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['context'])) {
            $query->where('context', $filters['context']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        $paginator = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Ruft einen einzelnen Zeiteintrag ab
     */
    public function get(User $user, int $id): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)
            ->where('id', $id)
            ->first();
    }
}
