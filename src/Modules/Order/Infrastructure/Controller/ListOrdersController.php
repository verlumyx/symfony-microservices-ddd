<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Application\UseCase\ListOrdersUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListOrdersController
{
    #[Route('/api/orders', name: 'api_orders_list', methods: ['GET'])]
    public function __invoke(ListOrdersUseCase $useCase): JsonResponse
    {
        $orders = $useCase->execute();

        $data = array_map(fn($orderDto) => $orderDto->toArray(), $orders);

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
