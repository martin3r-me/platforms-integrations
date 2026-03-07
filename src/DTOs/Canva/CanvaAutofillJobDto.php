<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO für Canva Autofill Job Ergebnisse
 *
 * Repräsentiert einen asynchronen Autofill-Job in Canva.
 * @see https://www.canva.dev/docs/connect/api-reference/autofills/
 */
class CanvaAutofillJobDto
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

        // Extract design ID from nested result.design.id
        $designId = isset($data['result']['design']['id'])
            ? (string) $data['result']['design']['id']
            : (isset($jobData['result']['design']['id'])
                ? (string) $jobData['result']['design']['id']
                : (isset($data['design_id'])
                    ? (string) $data['design_id']
                    : null));

        return new self(
            id: (string) ($jobData['id'] ?? $data['id'] ?? ''),
            status: isset($jobData['status']) ? (string) $jobData['status'] : (isset($data['status']) ? (string) $data['status'] : null),
            designId: $designId,
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
