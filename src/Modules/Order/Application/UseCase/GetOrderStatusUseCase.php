<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\UseCase;

use App\Modules\Order\Application\DTO\OrderStatusDto;
use App\Modules\Order\Application\DTO\OrderStatusResult;
use App\Modules\Order\Application\Port\OrderStatusCacheInterface;
use App\Modules\Order\Domain\Exception\InvalidOrderException;
use App\Modules\Order\Domain\Exception\OrderNotFoundException;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetOrderStatusUseCase
{
    public function __construct(
        private OrderStatusCacheInterface $statusCache,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function execute(string $id): OrderStatusResult
    {
        if (!Uuid::isValid($id)) {
            throw InvalidOrderException::invalidUuid($id);
        }

        // 1. Intentar recuperación ultrarrápida desde caché Redis
        $cachedDto = $this->statusCache->get($id);
        if ($cachedDto !== null) {
            return new OrderStatusResult(status: $cachedDto, isFromCache: true);
        }

        // 2. Fallback a la base de datos relacional
        $order = $this->orderRepository->findById(Uuid::fromString($id));
        if ($order === null) {
            throw OrderNotFoundException::withId($id);
        }

        $statusDto = OrderStatusDto::fromEntity($order);

        // Repoblar la caché en segundo plano/inmediatamente
        $this->statusCache->save($statusDto);

        return new OrderStatusResult(status: $statusDto, isFromCache: false);
    }
}
