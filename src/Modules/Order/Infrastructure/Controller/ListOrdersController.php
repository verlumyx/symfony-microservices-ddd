<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Infrastructure\Repository\OrderRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListOrdersController
{
    #[Route('/api/orders', name: 'api_orders_list', methods: ['GET'])]
    public function __invoke(OrderRepository $orderRepository): JsonResponse
    {
        $orders = $orderRepository->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(fn($order) => $order->toArray(), $orders);

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
