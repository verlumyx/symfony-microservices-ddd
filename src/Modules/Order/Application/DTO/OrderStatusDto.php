<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\DTO;

use App\Modules\Order\Domain\Entity\Order;
use DateTimeInterface;

final readonly class OrderStatusDto
{
    public function __construct(
        public string $orderId,
        public string $status,
        public string $customerEmail,
        public float $totalAmount,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        $updatedAt = $order->getUpdatedAt() ?? $order->getCreatedAt();

        return new self(
            orderId: (string) $order->getId()?->toRfc4122(),
            status: $order->getStatus()->value,
            customerEmail: (string) $order->getCustomerEmail(),
            totalAmount: (float) $order->getTotalAmount(),
            updatedAt: $updatedAt->format(DateTimeInterface::ATOM),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            orderId: (string) ($data['orderId'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            customerEmail: (string) ($data['customerEmail'] ?? ''),
            totalAmount: (float) ($data['totalAmount'] ?? 0.0),
            updatedAt: (string) ($data['updatedAt'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'status' => $this->status,
            'customerEmail' => $this->customerEmail,
            'totalAmount' => $this->totalAmount,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
