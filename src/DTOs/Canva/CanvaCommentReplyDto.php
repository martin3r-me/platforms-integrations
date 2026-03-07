<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO für Canva Comment Reply Ergebnisse
 *
 * Repräsentiert eine Antwort auf einen Kommentar-Thread in einem Canva Design.
 * @see https://www.canva.dev/docs/connect/api-reference/comments/
 */
class CanvaCommentReplyDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $threadId,
        public readonly ?string $message,
        public readonly ?array $author,
        public readonly ?string $createdAt,
    ) {}

    public static function fromApiResult(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            threadId: isset($data['thread_id']) ? (string) $data['thread_id'] : null,
            message: isset($data['message']) ? (string) $data['message'] : null,
            author: $data['author'] ?? null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
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
            'thread_id' => $this->threadId,
            'message' => $this->message,
            'author' => $this->author,
            'created_at' => $this->createdAt,
        ];
    }
}
