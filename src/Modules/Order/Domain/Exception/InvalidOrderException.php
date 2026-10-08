<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Exception;

use InvalidArgumentException;

final class InvalidOrderException extends InvalidArgumentException
{
    public static function invalidAmount(float $amount): self
    {
        return new self(sprintf('El importe del pedido debe ser mayor a cero. Valor recibido: %.2f', $amount));
    }

    public static function invalidEmail(string $email): self
    {
        return new self(sprintf('El correo electrónico del cliente "%s" no es válido.', $email));
    }

    public static function invalidUuid(string $uuid): self
    {
        return new self(sprintf('El identificador UUID "%s" no tiene un formato válido.', $uuid));
    }

    public static function alreadyCancelled(string $uuid): self
    {
        return new self(sprintf('La orden con ID "%s" ya se encuentra cancelada.', $uuid));
    }
}
