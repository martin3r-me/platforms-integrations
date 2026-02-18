<?php

namespace Platform\Integrations\DTOs\TimeEntry;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

/**
 * DTO für einen einzelnen TimeEntry-Request (Zeitstempel)
 *
 * Validiert und kapselt die Daten für einen einzelnen Zeiteintrag.
 */
class TimeEntryRequest
{
    public function __construct(
        public readonly string $date,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly ?int $projectId = null,
        public readonly ?string $projectName = null,
        public readonly ?string $context = null,
        public readonly ?string $description = null,
        public readonly ?string $type = 'work',
        public readonly ?array $tags = null,
    ) {
    }

    /**
     * Erstellt eine Instanz aus Request-Daten mit Validierung
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function fromRequest(array $data): self
    {
        $validator = self::validator($data);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return new self(
            date: $data['date'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            projectId: $data['project_id'] ?? null,
            projectName: $data['project_name'] ?? null,
            context: $data['context'] ?? null,
            description: $data['description'] ?? null,
            type: $data['type'] ?? 'work',
            tags: $data['tags'] ?? null,
        );
    }

    /**
     * Erstellt den Validator für die Request-Daten
     */
    public static function validator(array $data): Validator
    {
        return ValidatorFacade::make($data, [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'in:work,break,travel,meeting,other'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ], [
            'date.required' => 'Das Datum ist erforderlich.',
            'date.date_format' => 'Das Datum muss im Format YYYY-MM-DD sein.',
            'start_time.required' => 'Die Startzeit ist erforderlich.',
            'start_time.date_format' => 'Die Startzeit muss im Format HH:MM sein.',
            'end_time.required' => 'Die Endzeit ist erforderlich.',
            'end_time.date_format' => 'Die Endzeit muss im Format HH:MM sein.',
            'end_time.after' => 'Die Endzeit muss nach der Startzeit liegen.',
            'project_id.integer' => 'Die Projekt-ID muss eine Ganzzahl sein.',
            'project_id.min' => 'Die Projekt-ID muss mindestens 1 sein.',
            'type.in' => 'Der Typ muss einer der folgenden sein: work, break, travel, meeting, other.',
            'tags.array' => 'Tags müssen als Array übergeben werden.',
            'tags.*.max' => 'Ein Tag darf maximal 100 Zeichen lang sein.',
        ]);
    }

    /**
     * Konvertiert zu Array für die Speicherung
     */
    public function toArray(): array
    {
        return array_filter([
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'context' => $this->context,
            'description' => $this->description,
            'type' => $this->type,
            'tags' => $this->tags,
        ], fn ($value) => $value !== null);
    }

    /**
     * Berechnet die Dauer in Minuten
     */
    public function getDurationMinutes(): int
    {
        $start = \Carbon\Carbon::createFromFormat('H:i', $this->startTime);
        $end = \Carbon\Carbon::createFromFormat('H:i', $this->endTime);

        return (int) $start->diffInMinutes($end);
    }
}
