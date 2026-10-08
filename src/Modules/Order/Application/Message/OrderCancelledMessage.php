<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Message;

use DateTimeImmutable;

final readonly class OrderCancelledMessage
{
    public function __construct(
        public string $orderId,
        public string $customerEmail,
        public ?string $reason = null,
        public DateTimeImmutable $cancelledAt = new DateTimeImmutable(),
    ) {
    }
}
