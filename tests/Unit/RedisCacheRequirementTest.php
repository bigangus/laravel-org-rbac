<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RedisStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zhanghongfei\OrgRbac\Support\RedisCacheRequirement;

class RedisCacheRequirementTest extends TestCase
{
    #[Test]
    public function it_rejects_non_redis_store(): void
    {
        $this->expectException(RuntimeException::class);
        RedisCacheRequirement::assertDefaultStore(new ArrayStore);
    }

    #[Test]
    public function it_accepts_redis_store_instance(): void
    {
        $store = $this->createStub(RedisStore::class);
        $this->assertInstanceOf(RedisStore::class, $store);

        RedisCacheRequirement::assertDefaultStore($store);
    }
}
