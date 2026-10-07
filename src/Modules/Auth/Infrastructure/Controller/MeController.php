<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Controller;

use App\Modules\Auth\Application\UseCase\GetUserProfileUseCase;
use App\Modules\Auth\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MeController
{
    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function __invoke(
        #[CurrentUser] ?User $user,
        GetUserProfileUseCase $useCase,
    ): JsonResponse {
        $userDto = $useCase->execute($user);

        if ($userDto === null) {
            return new JsonResponse([
                'error' => 'No autenticado.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse($userDto->toArray(), Response::HTTP_OK);
    }
}
