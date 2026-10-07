<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\UseCase;

use App\Modules\Order\Application\DTO\CreateOrderCommand;
use App\Modules\Order\Application\DTO\OrderResponseDto;
use App\Modules\Order\Application\DTO\OrderStatusDto;
use App\Modules\Order\Application\Port\IdempotencyManagerInterface;
use App\Modules\Order\Application\Port\OrderEventPublisherInterface;
use App\Modules\Order\Application\Port\OrderStatusCacheInterface;
use App\Modules\Order\Domain\Entity\Order;
use App\Modules\Order\Domain\Exception\OrderLockException;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CreateOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private OrderEventPublisherInterface $eventPublisher,
        private OrderStatusCacheInterface $statusCache,
        private IdempotencyManagerInterface $idempotencyManager,
    ) {
    }

    public function execute(CreateOrderCommand $command): OrderResponseDto
    {
        $idempotencyKey = $command->idempotencyKey;

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            return $this->handleWithIdempotency($command, trim($idempotencyKey));
        }

        return $this->createOrder($command);
    }

    private function handleWithIdempotency(CreateOrderCommand $command, string $key): OrderResponseDto
    {
        // 1. Verificar si ya fue procesada anteriormente
        $existingOrderId = $this->idempotencyManager->findExistingOrderId($key);
        if ($existingOrderId !== null) {
            $existingOrder = $this->orderRepository->findById(Uuid::fromString($existingOrderId));
            if ($existingOrder !== null) {
                return OrderResponseDto::fromEntity($existingOrder);
            }
        }

        // 2. Adquirir lock distribuido para evitar condiciones de carrera concurrentes
        if (!$this->idempotencyManager->acquireLock($key)) {
            throw OrderLockException::forIdempotencyKey($key);
        }

        try {
            // Doble chequeo tras la adquisición del lock
            $existingOrderId = $this->idempotencyManager->findExistingOrderId($key);
            if ($existingOrderId !== null) {
                $existingOrder = $this->orderRepository->findById(Uuid::fromString($existingOrderId));
                if ($existingOrder !== null) {
                    return OrderResponseDto::fromEntity($existingOrder);
                }
            }

            $responseDto = $this->createOrder($command);

            // Registrar clave de idempotencia
            $this->idempotencyManager->saveIdempotency($key, $responseDto->id);

            return $responseDto;
        } finally {
            $this->idempotencyManager->releaseLock($key);
        }
    }

    private function createOrder(CreateOrderCommand $command): OrderResponseDto
    {
        // Creación mediante método factory de dominio
        $order = Order::create(
            customerEmail: $command->customerEmail,
            totalAmount: $command->totalAmount,
        );

        $this->orderRepository->save($order, flush: true);

        $responseDto = OrderResponseDto::fromEntity($order);

        // Precalentar caché en Redis con estado inicial PENDING
        $this->statusCache->save(OrderStatusDto::fromEntity($order));

        // Publicar evento en el bus asíncrono
        $this->eventPublisher->publishOrderCreated(
            orderId: $responseDto->id,
            customerEmail: $responseDto->customerEmail,
            totalAmount: $responseDto->totalAmount,
        );

        return $responseDto;
    }
}
