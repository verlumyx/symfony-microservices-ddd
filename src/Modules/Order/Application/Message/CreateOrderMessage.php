<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Message;

final readonly class CreateOrderMessage
{
    public function __construct(
        public string $orderId,
        public string $customerEmail,
        public float $totalAmount,
    ) {
    }
}
