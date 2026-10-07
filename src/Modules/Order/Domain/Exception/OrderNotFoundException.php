<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Exception;

use DomainException;

final class OrderNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('El pedido con identificador "%s" no fue encontrado.', $id));
    }
}
