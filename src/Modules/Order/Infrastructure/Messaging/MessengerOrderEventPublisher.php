<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Messaging;

use App\Modules\Order\Application\Message\CreateOrderMessage;
use App\Modules\Order\Application\Message\SendNotificationMessage;
use App\Modules\Order\Application\Port\OrderEventPublisherInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerOrderEventPublisher implements OrderEventPublisherInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function publishOrderCreated(string $orderId, string $customerEmail, float $totalAmount): void
    {
        $this->messageBus->dispatch(
            new CreateOrderMessage(
                orderId: $orderId,
                customerEmail: $customerEmail,
                totalAmount: $totalAmount,
            ),
            [new AmqpStamp('order.created')]
        );
    }

    public function publishNotification(string $orderId, string $recipientEmail, string $message): void
    {
        $this->messageBus->dispatch(
            new SendNotificationMessage(
                orderId: $orderId,
                recipientEmail: $recipientEmail,
                message: $message,
            ),
            [new AmqpStamp('order.notification')]
        );
    }
}
