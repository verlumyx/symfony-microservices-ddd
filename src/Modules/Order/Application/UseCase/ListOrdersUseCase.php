<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\UseCase;

use App\Modules\Order\Application\DTO\OrderResponseDto;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;

final readonly class ListOrdersUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * @return OrderResponseDto[]
     */
    public function execute(): array
    {
        $orders = $this->orderRepository->findAllSortedByDate();

        return array_map(fn($order) => OrderResponseDto::fromEntity($order), $orders);
    }
}
