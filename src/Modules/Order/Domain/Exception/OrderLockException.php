<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Exception;

use DomainException;

final class OrderLockException extends DomainException
{
    public static function forIdempotencyKey(string $key): self
    {
        return new self('Ya existe una solicitud en proceso con esta clave de idempotencia.');
    }
}
