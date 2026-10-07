<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Idempotency;

use App\Modules\Order\Application\Port\IdempotencyManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

final class RedisIdempotencyManager implements IdempotencyManagerInterface
{
    private const KEY_PREFIX = 'idempotency_order_';
    private const LOCK_PREFIX = 'lock_idempotency_order_';

    /** @var array<string, LockInterface> */
    private array $activeLocks = [];

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function findExistingOrderId(string $key): ?string
    {
        $hash = md5(trim($key));
        $item = $this->cache->getItem(self::KEY_PREFIX . $hash);

        if (!$item->isHit()) {
            return null;
        }

        $orderId = $item->get();

        return is_string($orderId) ? $orderId : null;
    }

    public function acquireLock(string $key, float $ttl = 10.0): bool
    {
        $hash = md5(trim($key));
        $lockKey = self::LOCK_PREFIX . $hash;

        $lock = $this->lockFactory->createLock($lockKey, $ttl);
        if (!$lock->acquire()) {
            return false;
        }

        $this->activeLocks[$hash] = $lock;

        return true;
    }

    public function saveIdempotency(string $key, string $orderId, int $ttl = 86400): void
    {
        $hash = md5(trim($key));
        $item = $this->cache->getItem(self::KEY_PREFIX . $hash);
        $item->set($orderId);
        $item->expiresAfter($ttl);

        $this->cache->save($item);
    }

    public function releaseLock(string $key): void
    {
        $hash = md5(trim($key));
        if (isset($this->activeLocks[$hash])) {
            $this->activeLocks[$hash]->release();
            unset($this->activeLocks[$hash]);
        }
    }
}
