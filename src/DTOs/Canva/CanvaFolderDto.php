<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO for Canva Folder API results
 *
 * @see https://www.canva.dev/docs/connect/api-reference/folders/
 */
class CanvaFolderDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromApiResult(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: isset($data['name']) ? (string) $data['name'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
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
            'name' => $this->name,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
