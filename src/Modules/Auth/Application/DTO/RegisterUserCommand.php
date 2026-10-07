<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTO;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $plainPassword,
    ) {
    }
}
