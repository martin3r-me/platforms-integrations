<?php

namespace Platform\Integrations\DTOs\Canva;

/**
 * DTO für Canva User Ergebnisse
 *
 * Repräsentiert einen Canva User / Team-Mitglied.
 * @see https://www.canva.dev/docs/connect/api-reference/users/
 */
class CanvaUserDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $displayName,
        public readonly ?string $email,
        public readonly ?string $teamId,
    ) {}

    public static function fromApiResult(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            displayName: isset($data['display_name']) ? (string) $data['display_name'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            teamId: isset($data['team_id']) ? (string) $data['team_id'] : null,
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
            'display_name' => $this->displayName,
            'email' => $this->email,
            'team_id' => $this->teamId,
        ];
    }
}
