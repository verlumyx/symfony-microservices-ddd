<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Application\UseCase\GetOrderStatusUseCase;
use App\Modules\Order\Domain\Exception\InvalidOrderException;
use App\Modules\Order\Domain\Exception\OrderNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetOrderStatusController
{
    #[Route('/api/orders/{id}/status', name: 'api_orders_get_status', methods: ['GET'])]
    public function __invoke(string $id, GetOrderStatusUseCase $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute($id);

            return new JsonResponse(
                data: $result->status->toArray(),
                status: Response::HTTP_OK,
                headers: ['X-Cache' => $result->isFromCache ? 'HIT' : 'MISS'],
            );
        } catch (InvalidOrderException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (OrderNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
