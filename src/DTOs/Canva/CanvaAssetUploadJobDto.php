<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO for Canva Asset Upload Job API results
 *
 * @see https://www.canva.dev/docs/connect/api-reference/assets/
 */
class CanvaAssetUploadJobDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $status,
        public readonly ?string $assetId,
        public readonly ?array $errors,
    ) {}

    public static function fromApiResult(array $data): self
    {
        $job = $data['job'] ?? [];

        return new self(
            id: (string) ($job['id'] ?? $data['id'] ?? ''),
            status: isset($job['status']) ? (string) $job['status'] : (isset($data['status']) ? (string) $data['status'] : null),
            assetId: isset($job['asset']['id']) ? (string) $job['asset']['id'] : (isset($data['asset_id']) ? (string) $data['asset_id'] : null),
            errors: $job['errors'] ?? $data['errors'] ?? null,
        );
    }

    /**
     * @param array $results Array of API results
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
            'asset_id' => $this->assetId,
            'errors' => $this->errors,
        ];
    }
}
