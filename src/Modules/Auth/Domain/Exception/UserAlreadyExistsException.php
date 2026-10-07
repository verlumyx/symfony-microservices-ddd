<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exception;

use DomainException;

final class UserAlreadyExistsException extends DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('Ya existe un usuario con el correo electrónico "%s".', $email));
    }
}
