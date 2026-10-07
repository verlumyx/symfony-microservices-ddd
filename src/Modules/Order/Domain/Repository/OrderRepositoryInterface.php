<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Repository;

use App\Modules\Order\Domain\Entity\Order;
use Symfony\Component\Uid\Uuid;

interface OrderRepositoryInterface
{
    public function save(Order $order, bool $flush = true): void;

    public function findById(Uuid $id): ?Order;

    public function refresh(Order $order): void;

    /**
     * @return Order[]
     */
    public function findAllSortedByDate(): array;
}
