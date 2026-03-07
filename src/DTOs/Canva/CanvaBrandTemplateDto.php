<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO für Canva Brand Template Ergebnisse
 *
 * Repräsentiert ein Brand Template in Canva.
 * @see https://www.canva.dev/docs/connect/api-reference/brand-templates/
 */
class CanvaBrandTemplateDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?array $thumbnail,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromApiResult(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            title: isset($data['title']) ? (string) $data['title'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            thumbnail: $data['thumbnail'] ?? null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
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
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
