<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Message;

final readonly class SendNotificationMessage
{
    public function __construct(
        public string $orderId,
        public string $recipientEmail,
        public string $message,
    ) {
    }
}
