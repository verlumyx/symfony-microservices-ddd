<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Controller;

use App\Modules\Order\Infrastructure\Repository\OrderRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class GetOrderStatusController
{
    #[Route('/api/orders/{id}/status', name: 'api_orders_get_status', methods: ['GET'])]
    public function __invoke(
        string $id,
        OrderRepository $orderRepository,
        CacheItemPoolInterface $cache,
    ): JsonResponse {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['error' => 'Identificador UUID inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $cacheKey = 'order_status_' . $id;
        $cachedItem = $cache->getItem($cacheKey);

        // 1. Lectura ultrarrápida desde caché Redis
        if ($cachedItem->isHit()) {
            $data = $cachedItem->get();
            return new JsonResponse($data, Response::HTTP_OK, ['X-Cache' => 'HIT']);
        }

        // 2. Fallback a PostgreSQL si hubo un Cache MISS
        $order = $orderRepository->find(Uuid::fromString($id));

        if (!$order) {
            return new JsonResponse(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'orderId' => $order->getId()->toRfc4122(),
            'status' => $order->getStatus()->value,
            'customerEmail' => $order->getCustomerEmail(),
            'totalAmount' => (float) $order->getTotalAmount(),
            'updatedAt' => ($order->getUpdatedAt() ?? $order->getCreatedAt())->format(\DateTimeInterface::ATOM),
        ];

        // Repoblar la caché en Redis para acelerar futuras consultas (TTL 1 hora)
        $cachedItem->set($data);
        $cachedItem->expiresAfter(3600);
        $cache->save($cachedItem);

        return new JsonResponse($data, Response::HTTP_OK, ['X-Cache' => 'MISS']);
    }
}
