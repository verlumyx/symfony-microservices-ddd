<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\DTO;

final readonly class CreateOrderCommand
{
    public function __construct(
        public string $customerEmail,
        public float $totalAmount,
        public ?string $idempotencyKey = null,
    ) {
    }
}
