<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO for Canva Design API results
 *
 * @see https://www.canva.dev/docs/connect/api-reference/designs/
 */
class CanvaDesignDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $title,
        public readonly ?string $docType,
        public readonly ?array $owner,
        public readonly ?array $thumbnail,
        public readonly ?array $urls,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromApiResult(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            title: isset($data['title']) ? (string) $data['title'] : null,
            docType: isset($data['document_type']) ? (string) $data['document_type'] : (isset($data['doc_type']) ? (string) $data['doc_type'] : null),
            owner: $data['owner'] ?? null,
            thumbnail: $data['thumbnail'] ?? null,
            urls: $data['urls'] ?? null,
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
            'title' => $this->title,
            'doc_type' => $this->docType,
            'owner' => $this->owner,
            'thumbnail' => $this->thumbnail,
            'urls' => $this->urls,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
