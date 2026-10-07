<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Application\DTO\CreateOrderCommand;
use App\Modules\Order\Application\DTO\CreateOrderInput;
use App\Modules\Order\Application\UseCase\CreateOrderUseCase;
use App\Modules\Order\Domain\Exception\InvalidOrderException;
use App\Modules\Order\Domain\Exception\OrderLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateOrderController
{
    #[Route('/api/orders', name: 'api_orders_create', methods: ['POST'])]
    public function __invoke(
        Request $request,
        ValidatorInterface $validator,
        CreateOrderUseCase $useCase,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];

        $input = new CreateOrderInput();
        $input->customerEmail = (string) ($payload['customerEmail'] ?? '');
        $input->totalAmount = isset($payload['totalAmount']) ? (float) $payload['totalAmount'] : 0.0;

        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $idempotencyKey = $request->headers->get('X-Idempotency-Key');

        $command = new CreateOrderCommand(
            customerEmail: $input->customerEmail,
            totalAmount: $input->totalAmount,
            idempotencyKey: $idempotencyKey,
        );

        try {
            $orderDto = $useCase->execute($command);

            return new JsonResponse($orderDto->toArray(), Response::HTTP_ACCEPTED);
        } catch (OrderLockException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (InvalidOrderException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
