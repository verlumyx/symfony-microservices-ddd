<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Port;

use App\Modules\Order\Application\DTO\OrderStatusDto;

interface OrderStatusCacheInterface
{
    public function get(string $orderId): ?OrderStatusDto;

    public function save(OrderStatusDto $dto, int $ttl = 3600): void;
}
