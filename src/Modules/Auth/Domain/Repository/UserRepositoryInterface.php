<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Repository;

use App\Modules\Auth\Domain\Entity\User;

interface UserRepositoryInterface
{
    public function save(User $user, bool $flush = true): void;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;
}
