<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Controller;

use App\Modules\Auth\Application\DTO\RegisterUserCommand;
use App\Modules\Auth\Application\UseCase\RegisterUserUseCase;
use App\Modules\Auth\Domain\Exception\UserAlreadyExistsException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController
{
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function __invoke(
        Request $request,
        RegisterUserUseCase $useCase,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = $data['email'] ?? null;
        $plainPassword = $data['password'] ?? null;

        if (!$email || !$plainPassword) {
            return new JsonResponse([
                'error' => 'Los campos email y password son requeridos.'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $userDto = $useCase->execute(new RegisterUserCommand(
                email: (string) $email,
                plainPassword: (string) $plainPassword,
            ));

            return new JsonResponse([
                'message' => 'Usuario registrado exitosamente.',
                'user' => $userDto->toArray(),
            ], Response::HTTP_CREATED);
        } catch (UserAlreadyExistsException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_CONFLICT);
        }
    }
}
