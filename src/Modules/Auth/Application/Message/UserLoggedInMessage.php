<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Message;

use DateTimeImmutable;

final readonly class UserLoggedInMessage
{
    public function __construct(
        public string $userId,
        public string $email,
        public ?string $ipAddress = null,
        public DateTimeImmutable $loggedInAt = new DateTimeImmutable(),
    ) {
    }
}
