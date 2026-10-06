<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Application\DTO\CreateOrderInput;
use App\Modules\Order\Application\Message\CreateOrderMessage;
use App\Modules\Order\Domain\Entity\Order;
use App\Modules\Order\Domain\Enum\OrderStatus;
use App\Modules\Order\Infrastructure\Repository\OrderRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateOrderController
{
    #[Route('/api/orders', name: 'api_orders_create', methods: ['POST'])]
    public function __invoke(
        Request $request,
        ValidatorInterface $validator,
        OrderRepository $orderRepository,
        MessageBusInterface $messageBus,
        LockFactory $lockFactory,
        CacheItemPoolInterface $cache,
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

        // 1. Manejo de idempotencia con Redis si se proporciona la cabecera
        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $cacheKey = 'idempotency_order_' . md5(trim($idempotencyKey));
            $cachedItem = $cache->getItem($cacheKey);

            if ($cachedItem->isHit()) {
                $existingOrderId = $cachedItem->get();
                $existingOrder = $orderRepository->find(Uuid::fromString($existingOrderId));
                if ($existingOrder !== null) {
                    return new JsonResponse($existingOrder->toArray(), Response::HTTP_ACCEPTED);
                }
            }

            // Lock distribuido en Redis para evitar concurrencia en la misma clave
            $lock = $lockFactory->createLock('lock_' . $cacheKey, 10.0);
            if (!$lock->acquire()) {
                return new JsonResponse([
                    'error' => 'Ya existe una solicitud en proceso con esta clave de idempotencia.'
                ], Response::HTTP_CONFLICT);
            }

            try {
                // Doble chequeo tras adquirir el lock
                $cachedItem = $cache->getItem($cacheKey);
                if ($cachedItem->isHit()) {
                    $existingOrderId = $cachedItem->get();
                    $existingOrder = $orderRepository->find(Uuid::fromString($existingOrderId));
                    if ($existingOrder !== null) {
                        return new JsonResponse($existingOrder->toArray(), Response::HTTP_ACCEPTED);
                    }
                }

                $order = $this->createAndDispatchOrder($input, $orderRepository, $messageBus, $cache);

                // Guardar en Redis el mapeo de idempotencia (TTL 24 horas)
                $cachedItem->set($order->getId()->toRfc4122());
                $cachedItem->expiresAfter(86400);
                $cache->save($cachedItem);

                return new JsonResponse($order->toArray(), Response::HTTP_ACCEPTED);
            } finally {
                $lock->release();
            }
        }

        // 2. Procesamiento estándar sin cabecera de idempotencia
        $order = $this->createAndDispatchOrder($input, $orderRepository, $messageBus, $cache);

        return new JsonResponse($order->toArray(), Response::HTTP_ACCEPTED);
    }

    private function createAndDispatchOrder(
        CreateOrderInput $input,
        OrderRepository $orderRepository,
        MessageBusInterface $messageBus,
        CacheItemPoolInterface $cache,
    ): Order {
        $order = new Order();
        $order->setCustomerEmail($input->customerEmail);
        $order->setTotalAmount($input->totalAmount);
        $order->setStatus(OrderStatus::PENDING);

        $orderRepository->save($order, flush: true);

        // Precalentar estado en Redis para consultas inmediatas ultrarrápidas
        $statusItem = $cache->getItem('order_status_' . $order->getId()->toRfc4122());
        $statusItem->set([
            'orderId' => $order->getId()->toRfc4122(),
            'status' => OrderStatus::PENDING->value,
            'customerEmail' => $order->getCustomerEmail(),
            'totalAmount' => (float) $order->getTotalAmount(),
            'updatedAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
        $statusItem->expiresAfter(3600);
        $cache->save($statusItem);

        $messageBus->dispatch(
            new CreateOrderMessage(
                orderId: $order->getId()->toRfc4122(),
                customerEmail: $order->getCustomerEmail(),
                totalAmount: (float) $order->getTotalAmount(),
            ),
            [new AmqpStamp('order.created')]
        );

        return $order;
    }
}
