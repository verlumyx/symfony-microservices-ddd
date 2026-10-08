<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\EventSubscriber;

use App\Modules\Auth\Application\Message\UserLoggedInMessage;
use App\Modules\Auth\Domain\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();

        $this->messageBus->dispatch(new UserLoggedInMessage(
            userId: (string) $user->getId(),
            email: (string) $user->getEmail(),
            ipAddress: $ip,
        ));
    }
}
