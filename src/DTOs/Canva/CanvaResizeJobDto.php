<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO für Canva Resize Job Ergebnisse
 *
 * Repräsentiert einen asynchronen Resize-Job in Canva.
 * @see https://www.canva.dev/docs/connect/api-reference/autofills/
 */
class CanvaResizeJobDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $status,
        public readonly ?string $designId,
        public readonly ?array $errors,
    ) {}

    public static function fromApiResult(array $data): self
    {
        // Handle nested job structure (job.id, job.status)
        $jobData = $data['job'] ?? $data;

        return new self(
            id: (string) ($jobData['id'] ?? $data['id'] ?? ''),
            status: isset($jobData['status']) ? (string) $jobData['status'] : (isset($data['status']) ? (string) $data['status'] : null),
            designId: isset($data['design']['id']) ? (string) $data['design']['id'] : (isset($jobData['design']['id']) ? (string) $jobData['design']['id'] : (isset($data['design_id']) ? (string) $data['design_id'] : null)),
            errors: $jobData['errors'] ?? $data['errors'] ?? null,
        );
    }

    /**
     * @param array $results Array von API-Ergebnissen
     * @return self[]
     */
    public static function fromApiResults(array $results): array
    {
        return array_map(fn(array $item) => self::fromApiResult($item), $results);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'design_id' => $this->designId,
            'errors' => $this->errors,
        ];
    }
}
