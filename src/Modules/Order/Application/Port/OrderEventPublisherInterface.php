<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Port;

interface OrderEventPublisherInterface
{
    public function publishOrderCreated(string $orderId, string $customerEmail, float $totalAmount): void;

    public function publishNotification(string $orderId, string $recipientEmail, string $message): void;

    public function publishOrderCancelled(string $orderId, string $customerEmail, ?string $reason = null): void;
}

