<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\DTO;

final readonly class OrderStatusResult
{
    public function __construct(
        public OrderStatusDto $status,
        public bool $isFromCache,
    ) {
    }
}
