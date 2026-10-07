<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Port;

interface IdempotencyManagerInterface
{
    public function findExistingOrderId(string $key): ?string;

    public function acquireLock(string $key, float $ttl = 10.0): bool;

    public function saveIdempotency(string $key, string $orderId, int $ttl = 86400): void;

    public function releaseLock(string $key): void;
}
