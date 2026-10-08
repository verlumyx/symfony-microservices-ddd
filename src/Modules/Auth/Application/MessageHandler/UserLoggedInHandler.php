<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\MessageHandler;

use App\Modules\Auth\Application\Message\UserLoggedInMessage;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UserLoggedInHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserLoggedInMessage $message): void
    {
        $this->logger->info('Iniciando simulación de notificación de seguridad por inicio de sesión', [
            'userId' => $message->userId,
            'email' => $message->email,
            'ipAddress' => $message->ipAddress ?? 'desconocida',
            'loggedInAt' => $message->loggedInAt->format(DateTimeInterface::ATOM),
        ]);

        // Simulación: Envío de alerta de seguridad o registro de auditoría en sistema antifraude
        $this->logger->info('Alerta de seguridad enviada al usuario por inicio de sesión exitoso', [
            'email' => $message->email,
            'ipAddress' => $message->ipAddress,
        ]);
    }
}
