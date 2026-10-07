<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\DTO\UserResponseDto;
use App\Modules\Auth\Domain\Entity\User;

final readonly class GetUserProfileUseCase
{
    public function execute(?User $user): ?UserResponseDto
    {
        if ($user === null) {
            return null;
        }

        return UserResponseDto::fromEntity($user);
    }
}
