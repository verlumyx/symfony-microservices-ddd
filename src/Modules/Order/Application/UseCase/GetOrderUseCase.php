<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\UseCase;

use App\Modules\Order\Application\DTO\OrderResponseDto;
use App\Modules\Order\Domain\Exception\InvalidOrderException;
use App\Modules\Order\Domain\Exception\OrderNotFoundException;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function execute(string $id): OrderResponseDto
    {
        if (!Uuid::isValid($id)) {
            throw InvalidOrderException::invalidUuid($id);
        }

        $order = $this->orderRepository->findById(Uuid::fromString($id));

        if ($order === null) {
            throw OrderNotFoundException::withId($id);
        }

        return OrderResponseDto::fromEntity($order);
    }
}
