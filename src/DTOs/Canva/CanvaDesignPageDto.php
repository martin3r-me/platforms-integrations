<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO for Canva Design Page API results
 *
 * @see https://www.canva.dev/docs/connect/api-reference/designs/
 */
class CanvaDesignPageDto
{
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $title,
        public readonly ?array $thumbnail,
        public readonly ?int $width,
        public readonly ?int $height,
    ) {}

    public static function fromApiResult(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            thumbnail: $data['thumbnail'] ?? null,
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
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
            'thumbnail' => $this->thumbnail,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
