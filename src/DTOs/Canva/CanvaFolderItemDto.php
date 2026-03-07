<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO for Canva Folder Item API results
 *
 * @see https://www.canva.dev/docs/connect/api-reference/folders/
 */
class CanvaFolderItemDto
{
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $type,
        public readonly ?string $name,
        public readonly ?array $thumbnail,
    ) {}

    public static function fromApiResult(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            type: isset($data['type']) ? (string) $data['type'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            thumbnail: $data['thumbnail'] ?? null,
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
            'type' => $this->type,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail,
        ];
    }
}
