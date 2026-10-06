<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Entity;

use App\Modules\Order\Domain\Enum\OrderStatus;
use App\Modules\Order\Infrastructure\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $customerEmail = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalAmount = null;

    #[ORM\Column(length: 20, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v4();
        $this->status = OrderStatus::PENDING;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): static
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount !== null ? (float) $this->totalAmount : null;
    }

    public function setTotalAmount(float|string $totalAmount): static
    {
        $this->totalAmount = number_format((float) $totalAmount, 2, '.', '');

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->toRfc4122(),
            'customerEmail' => $this->customerEmail,
            'totalAmount' => $this->getTotalAmount(),
            'status' => $this->status->value,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
