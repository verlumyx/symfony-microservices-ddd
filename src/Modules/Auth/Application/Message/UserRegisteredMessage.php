<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Message;

use DateTimeImmutable;

final readonly class UserRegisteredMessage
{
    public function __construct(
        public string $userId,
        public string $email,
        public DateTimeImmutable $registeredAt = new DateTimeImmutable(),
    ) {
    }
}
