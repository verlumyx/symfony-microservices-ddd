<?php

namespace App\Modules\Auth\Infrastructure\Controller;

use App\Modules\Auth\Domain\Entity\User;
use App\Modules\Auth\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController
{
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function __invoke(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = $data['email'] ?? null;
        $plainPassword = $data['password'] ?? null;

        if (!$email || !$plainPassword) {
            return new JsonResponse([
                'error' => 'Los campos email y password son requeridos.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findByEmail($email)) {
            return new JsonResponse([
                'error' => 'Ya existe un usuario con este correo electrónico.'
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);

        // Hasheo seguro de la contraseña
        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Usuario registrado exitosamente.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ]
        ], Response::HTTP_CREATED);
    }
}
