<?php

namespace App\Shared\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatusController
{
    #[Route('/status', name: 'app_status', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'code' => Response::HTTP_OK,
            'php_version' => PHP_VERSION,
        ], Response::HTTP_OK);
    }
}
