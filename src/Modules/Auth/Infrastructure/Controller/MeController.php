<?php

namespace App\Modules\Auth\Infrastructure\Controller;

use App\Modules\Auth\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class MeController
{
    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse([
                'error' => 'No autenticado.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_OK);
    }
}
