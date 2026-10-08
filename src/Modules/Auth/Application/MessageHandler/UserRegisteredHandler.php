<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\MessageHandler;

use App\Modules\Auth\Application\Message\UserRegisteredMessage;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UserRegisteredHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserRegisteredMessage $message): void
    {
        $this->logger->info('Iniciando simulación de envío de correo de bienvenida tras registro', [
            'userId' => $message->userId,
            'email' => $message->email,
            'registeredAt' => $message->registeredAt->format(DateTimeInterface::ATOM),
        ]);

        // Simulación: Envío de correo mediante SMTP o proveedor externo (SendGrid, Mailgun, etc.)
        usleep(150000); // 150 ms de latencia simulada

        $this->logger->info('Correo de bienvenida enviado exitosamente al usuario', [
            'email' => $message->email,
        ]);
    }
}
