<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\EventSubscriber;

use App\Modules\Order\Application\DTO\OrderStatusDto;
use App\Modules\Order\Application\Message\CreateOrderMessage;
use App\Modules\Order\Application\Port\OrderStatusCacheInterface;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

final readonly class OrderFailedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private OrderStatusCacheInterface $statusCache,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Prioridad -200: se ejecuta después de SendFailedMessageToFailureTransportListener (-100)
            WorkerMessageFailedEvent::class => ['onMessageFailed', -200],
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        // Si Symfony va a reintentar el mensaje, no marcamos aún como fallido
        if ($event->willRetry()) {
            $this->logger->warning('Reintento en curso para mensaje fallido en Messenger', [
                'receiver' => $event->getReceiverName(),
                'error' => $event->getThrowable()->getMessage(),
            ]);
            return;
        }

        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof CreateOrderMessage) {
            return;
        }

        $this->logger->critical('Todos los reintentos agotados. Pedido enviado a la Dead Letter Queue (DLQ)', [
            'orderId' => $message->orderId,
            'customerEmail' => $message->customerEmail,
            'error' => $event->getThrowable()->getMessage(),
        ]);

        $order = $this->orderRepository->findById(Uuid::fromString($message->orderId));

        if ($order === null) {
            return;
        }

        // 1. Transición de dominio a FAILED
        $order->markAsFailed();
        $this->orderRepository->save($order, flush: true);

        // 2. Actualizar estado en Redis para consultas ultrarrápidas
        $this->statusCache->save(OrderStatusDto::fromEntity($order));
    }
}
