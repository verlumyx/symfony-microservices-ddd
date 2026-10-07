<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Exception;

use RuntimeException;

final class PaymentFailedException extends RuntimeException
{
    public static function forOrder(string $orderId, string $customerEmail, string $reason = 'Fondos insuficientes o rechazo bancario en la pasarela externa.'): self
    {
        return new self(sprintf(
            'Fallo en la pasarela de pagos para el pedido %s (%s). Motivo: %s',
            $orderId,
            $customerEmail,
            $reason
        ));
    }
}
