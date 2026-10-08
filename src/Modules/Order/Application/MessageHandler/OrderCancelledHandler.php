<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\MessageHandler;

use App\Modules\Order\Application\Message\OrderCancelledMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OrderCancelledHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderCancelledMessage $message): void
    {
        $this->logger->info('Procesando evento asíncrono de orden cancelada', [
            'orderId' => $message->orderId,
            'customerEmail' => $message->customerEmail,
            'reason' => $message->reason,
            'cancelledAt' => $message->cancelledAt->format(\DateTimeInterface::ATOM),
        ]);

        // Simulación: Liberar stock reservado, emitir reembolso en pasarela y enviar email de cancelación
        $this->logger->info('Simulación completada: Reembolso solicitado y stock liberado para la orden cancelada', [
            'orderId' => $message->orderId,
        ]);
    }
}
