<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Application\UseCase\GetOrderUseCase;
use App\Modules\Order\Domain\Exception\InvalidOrderException;
use App\Modules\Order\Domain\Exception\OrderNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetOrderController
{
    #[Route('/api/orders/{id}', name: 'api_orders_get', methods: ['GET'])]
    public function __invoke(string $id, GetOrderUseCase $useCase): JsonResponse
    {
        try {
            $orderDto = $useCase->execute($id);

            return new JsonResponse($orderDto->toArray(), Response::HTTP_OK);
        } catch (InvalidOrderException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (OrderNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
