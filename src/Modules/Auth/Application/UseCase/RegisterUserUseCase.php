<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\DTO\RegisterUserCommand;
use App\Modules\Auth\Application\DTO\UserResponseDto;
use App\Modules\Auth\Application\Message\UserRegisteredMessage;
use App\Modules\Auth\Domain\Entity\User;
use App\Modules\Auth\Domain\Exception\UserAlreadyExistsException;
use App\Modules\Auth\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function execute(RegisterUserCommand $command): UserResponseDto
    {
        if ($this->userRepository->findByEmail($command->email) !== null) {
            throw UserAlreadyExistsException::withEmail($command->email);
        }

        $dummyUser = new User();
        $dummyUser->setEmail($command->email);
        $hashedPassword = $this->passwordHasher->hashPassword($dummyUser, $command->plainPassword);

        $user = User::create(
            email: $command->email,
            hashedPassword: $hashedPassword,
        );

        $this->userRepository->save($user, flush: true);

        // Publicar evento asíncrono
        $this->messageBus->dispatch(new UserRegisteredMessage(
            userId: (string) $user->getId(),
            email: (string) $user->getEmail(),
        ));

        return UserResponseDto::fromEntity($user);
    }
}
