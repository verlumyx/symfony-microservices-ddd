<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\MessageHandler;

use App\Modules\Order\Application\Message\CreateOrderMessage;
use App\Modules\Order\Application\Message\SendNotificationMessage;
use App\Modules\Order\Domain\Enum\OrderStatus;
use App\Modules\Order\Infrastructure\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CreateOrderHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private CacheItemPoolInterface $cache,
        private MessageBusInterface $messageBus,
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

        $order = $this->orderRepository->find(Uuid::fromString($message->orderId));

        if (!$order) {
            $this->logger->error('Orden no encontrada para procesar', ['orderId' => $message->orderId]);
            return;
        }

        // 1. Simulación de procesamiento (ej. validación con pasarela de pago, verificación de stock)
        $order->setStatus(OrderStatus::PROCESSING);
        $this->orderRepository->save($order, flush: true);

        // Reflejar estado PROCESSING en Redis
        $processingItem = $this->cache->getItem('order_status_' . $message->orderId);
        $processingItem->set([
            'orderId' => $message->orderId,
            'status' => OrderStatus::PROCESSING->value,
            'customerEmail' => $message->customerEmail,
            'totalAmount' => $message->totalAmount,
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
        $processingItem->expiresAfter(3600);
        $this->cache->save($processingItem);

        // Simulamos latencia de procesamiento
        usleep(250000); // 250 ms

        // 2. Confirmación exitosa del pedido
        $order->setStatus(OrderStatus::CONFIRMED);
        $this->orderRepository->save($order, flush: true);

        // 3. Cachear estado actualizado en Redis para lecturas ultrarrápidas
        $cacheItem = $this->cache->getItem('order_status_' . $message->orderId);
        $cacheItem->set([
            'orderId' => $message->orderId,
            'status' => OrderStatus::CONFIRMED->value,
            'customerEmail' => $message->customerEmail,
            'totalAmount' => $message->totalAmount,
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
        $cacheItem->expiresAfter(3600); // 1 hora de caché
        $this->cache->save($cacheItem);

        $this->logger->info('Orden confirmada exitosamente y cacheada en Redis', [
            'orderId' => $message->orderId,
            'status' => $order->getStatus()->value,
        ]);

        // 4. Despachar mensaje de notificación al cliente
        $this->messageBus->dispatch(
            new SendNotificationMessage(
                orderId: $message->orderId,
                recipientEmail: $message->customerEmail,
                message: sprintf('Tu pedido por un monto de %.2f ha sido confirmado exitosamente.', $message->totalAmount)
            ),
            [new AmqpStamp('order.notification')]
        );
    }
}
