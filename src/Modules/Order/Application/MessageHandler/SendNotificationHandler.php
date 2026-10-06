<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\MessageHandler;

use App\Modules\Order\Application\Message\SendNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendNotificationHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $this->logger->info('Notificación de pedido enviada al cliente', [
            'orderId' => $message->orderId,
            'recipientEmail' => $message->recipientEmail,
            'message' => $message->message,
        ]);
    }
}
