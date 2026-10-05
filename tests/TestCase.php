<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // O banco volta ao estado inicial a cada teste (transação); o cache também precisa.
        Redis::connection('cache')->flushdb();
        Redis::connection('default')->flushdb();
    }
}
