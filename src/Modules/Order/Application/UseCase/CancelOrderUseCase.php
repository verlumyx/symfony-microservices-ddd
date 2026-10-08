<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\UseCase;

use App\Modules\Order\Application\DTO\OrderResponseDto;
use App\Modules\Order\Application\DTO\OrderStatusDto;
use App\Modules\Order\Application\Port\OrderEventPublisherInterface;
use App\Modules\Order\Application\Port\OrderStatusCacheInterface;
use App\Modules\Order\Domain\Exception\InvalidOrderException;
use App\Modules\Order\Domain\Exception\OrderNotFoundException;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CancelOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private OrderEventPublisherInterface $eventPublisher,
        private OrderStatusCacheInterface $statusCache,
    ) {
    }

    public function execute(string $orderId, ?string $reason = null): OrderResponseDto
    {
        if (!Uuid::isValid($orderId)) {
            throw InvalidOrderException::invalidUuid($orderId);
        }

        $order = $this->orderRepository->findById(Uuid::fromString($orderId));
        if ($order === null) {
            throw OrderNotFoundException::withId($orderId);
        }

        if (!$order->canBeCancelled()) {
            throw InvalidOrderException::alreadyCancelled($orderId);
        }

        // 1. Cambiar estado a CANCELLED en dominio
        $order->markAsCancelled();
        $this->orderRepository->save($order, flush: true);

        // 2. Actualizar caché de Redis
        $this->statusCache->save(OrderStatusDto::fromEntity($order));

        // 3. Publicar evento en RabbitMQ
        $this->eventPublisher->publishOrderCancelled(
            orderId: (string) $order->getId()?->toRfc4122(),
            customerEmail: (string) $order->getCustomerEmail(),
            reason: $reason,
        );

        return OrderResponseDto::fromEntity($order);
    }
}
