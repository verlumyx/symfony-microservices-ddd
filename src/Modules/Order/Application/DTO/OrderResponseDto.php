<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\DTO;

use App\Modules\Order\Domain\Entity\Order;
use DateTimeInterface;

final readonly class OrderResponseDto
{
    public function __construct(
        public string $id,
        public string $customerEmail,
        public float $totalAmount,
        public string $status,
        public string $createdAt,
        public ?string $updatedAt = null,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: (string) $order->getId()?->toRfc4122(),
            customerEmail: (string) $order->getCustomerEmail(),
            totalAmount: (float) $order->getTotalAmount(),
            status: $order->getStatus()->value,
            createdAt: $order->getCreatedAt()->format(DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()?->format(DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customerEmail' => $this->customerEmail,
            'totalAmount' => $this->totalAmount,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
