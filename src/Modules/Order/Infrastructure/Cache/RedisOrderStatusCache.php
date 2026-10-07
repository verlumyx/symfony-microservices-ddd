<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Cache;

use App\Modules\Order\Application\DTO\OrderStatusDto;
use App\Modules\Order\Application\Port\OrderStatusCacheInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class RedisOrderStatusCache implements OrderStatusCacheInterface
{
    private const KEY_PREFIX = 'order_status_';

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function get(string $orderId): ?OrderStatusDto
    {
        $item = $this->cache->getItem(self::KEY_PREFIX . $orderId);

        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();
        if (!is_array($data)) {
            return null;
        }

        return OrderStatusDto::fromArray($data);
    }

    public function save(OrderStatusDto $dto, int $ttl = 3600): void
    {
        $item = $this->cache->getItem(self::KEY_PREFIX . $dto->orderId);
        $item->set($dto->toArray());
        $item->expiresAfter($ttl);

        $this->cache->save($item);
    }
}
