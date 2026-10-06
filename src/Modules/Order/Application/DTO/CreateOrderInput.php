<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\DTO;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateOrderInput
{
    #[Assert\NotBlank(message: 'El correo del cliente es obligatorio.')]
    #[Assert\Email(message: 'El formato de correo no es válido.')]
    #[Groups(['order:write'])]
    public string $customerEmail;

    #[Assert\NotBlank(message: 'El monto total es obligatorio.')]
    #[Assert\Positive(message: 'El monto total debe ser mayor a 0.')]
    #[Groups(['order:write'])]
    public float $totalAmount;
}
