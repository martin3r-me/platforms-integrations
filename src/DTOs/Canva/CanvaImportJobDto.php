<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO für Canva Import Job Ergebnisse
 *
 * Repräsentiert einen asynchronen Import-Job in Canva.
 * @see https://www.canva.dev/docs/connect/api-reference/imports/
 */
class CanvaImportJobDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $status,
        public readonly ?array $designIds,
        public readonly ?array $errors,
    ) {}

    public static function fromApiResult(array $data): self
    {
        // Handle nested job structure (job.id, job.status)
        $jobData = $data['job'] ?? $data;

        // Extract design IDs from various possible structures
        $designIds = $data['design_ids']
            ?? $jobData['design_ids']
            ?? null;

        // Try nested result.designs array
        if ($designIds === null) {
            $designs = $data['result']['designs'] ?? $jobData['result']['designs'] ?? null;
            if (is_array($designs)) {
                $designIds = array_map(fn($d) => is_array($d) ? (string) ($d['id'] ?? '') : (string) $d, $designs);
            }
        }

        return new self(
            id: (string) ($jobData['id'] ?? $data['id'] ?? ''),
            status: isset($jobData['status']) ? (string) $jobData['status'] : (isset($data['status']) ? (string) $data['status'] : null),
            designIds: $designIds,
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
            'design_ids' => $this->designIds,
            'errors' => $this->errors,
        ];
    }
}
