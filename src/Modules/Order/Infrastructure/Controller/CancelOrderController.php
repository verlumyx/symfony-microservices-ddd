<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Application\UseCase\CancelOrderUseCase;
use App\Modules\Order\Domain\Exception\InvalidOrderException;
use App\Modules\Order\Domain\Exception\OrderNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CancelOrderController
{
    #[Route('/api/orders/{id}/cancel', name: 'api_orders_cancel', methods: ['POST'])]
    public function __invoke(
        string $id,
        Request $request,
        CancelOrderUseCase $useCase,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];
        $reason = isset($payload['reason']) && is_string($payload['reason']) ? trim($payload['reason']) : null;

        try {
            $orderDto = $useCase->execute($id, $reason);

            return new JsonResponse($orderDto->toArray(), Response::HTTP_OK);
        } catch (InvalidOrderException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (OrderNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
