<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTO;

use App\Modules\Auth\Domain\Entity\User;
use DateTimeInterface;

final readonly class UserResponseDto
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        public ?int $id,
        public string $email,
        public array $roles,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            email: (string) $user->getEmail(),
            roles: $user->getRoles(),
            createdAt: $user->getCreatedAt()->format(DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->roles,
            'created_at' => $this->createdAt,
        ];
    }
}
