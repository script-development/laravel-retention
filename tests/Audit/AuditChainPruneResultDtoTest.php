<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Audit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ScriptDevelopment\LaravelRetention\Audit\AuditChainPruneResultDto;

final class AuditChainPruneResultDtoTest extends TestCase
{
    #[Test]
    public function it_exits_one_only_when_a_chain_was_refused(): void
    {
        self::assertSame(0, new AuditChainPruneResultDto(['a' => 3], [])->exitCode());
        self::assertSame(1, new AuditChainPruneResultDto([], ['a' => 3])->exitCode());
    }
}
