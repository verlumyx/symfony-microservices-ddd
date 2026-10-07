<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\MessageHandler;

use App\Modules\Order\Application\DTO\OrderStatusDto;
use App\Modules\Order\Application\Message\CreateOrderMessage;
use App\Modules\Order\Application\Port\OrderEventPublisherInterface;
use App\Modules\Order\Application\Port\OrderStatusCacheInterface;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CreateOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private OrderStatusCacheInterface $statusCache,
        private OrderEventPublisherInterface $eventPublisher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateOrderMessage $message): void
    {
        $this->logger->info('Iniciando procesamiento asíncrono de orden', [
            'orderId' => $message->orderId,
            'customerEmail' => $message->customerEmail,
            'totalAmount' => $message->totalAmount,
        ]);

        $order = $this->orderRepository->findById(Uuid::fromString($message->orderId));

        if (!$order) {
            $this->logger->error('Orden no encontrada para procesar', ['orderId' => $message->orderId]);
            return;
        }

        // 1. Transición de dominio a PROCESSING y actualización de caché
        $order->markAsProcessing();
        $this->orderRepository->save($order, flush: true);
        $this->statusCache->save(OrderStatusDto::fromEntity($order));

        // Simulamos latencia de procesamiento
        usleep(250000); // 250 ms

        // 2. Transición de dominio a CONFIRMED y actualización de caché
        $order->markAsConfirmed();
        $this->orderRepository->save($order, flush: true);
        $this->statusCache->save(OrderStatusDto::fromEntity($order));

        $this->logger->info('Orden confirmada exitosamente y cacheada en Redis', [
            'orderId' => $message->orderId,
            'status' => $order->getStatus()->value,
        ]);

        // 3. Despachar notificación mediante el puerto de mensajería
        $this->eventPublisher->publishNotification(
            orderId: $message->orderId,
            recipientEmail: $message->customerEmail,
            message: sprintf('Tu pedido por un monto de %.2f ha sido confirmado exitosamente.', $message->totalAmount)
        );
    }
}
