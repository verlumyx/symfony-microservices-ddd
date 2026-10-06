<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Enum;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case CONFIRMED = 'CONFIRMED';
    case FAILED = 'FAILED';
}
