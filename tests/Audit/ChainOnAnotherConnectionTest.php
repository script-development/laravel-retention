<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Audit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ScriptDevelopment\LaravelRetention\Audit\Exceptions\ChainOnAnotherConnection;

final class ChainOnAnotherConnectionTest extends TestCase
{
    #[Test]
    public function it_names_the_chain_and_both_connections(): void
    {
        self::assertSame(
            'logs is on connection an unnamed connection, the prune on mysql: its deletes could not share the transaction that moves its anchor.',
            new ChainOnAnotherConnection('logs', null, 'mysql')->getMessage(),
        );
    }
}
