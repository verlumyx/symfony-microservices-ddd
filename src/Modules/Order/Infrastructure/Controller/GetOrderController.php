<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Infrastructure\Repository\OrderRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class GetOrderController
{
    #[Route('/api/orders/{id}', name: 'api_orders_get', methods: ['GET'])]
    public function __invoke(string $id, OrderRepository $orderRepository): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['error' => 'Identificador UUID inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $order = $orderRepository->find(Uuid::fromString($id));

        if (!$order) {
            return new JsonResponse(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($order->toArray(), Response::HTTP_OK);
    }
}
