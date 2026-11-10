<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Audit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ScriptDevelopment\LaravelRetention\Audit\Exceptions\PrunedRowCountMismatch;

final class PrunedRowCountMismatchTest extends TestCase
{
    #[Test]
    public function it_names_the_row_and_the_count_it_verified(): void
    {
        $prunedRowCountMismatch = new PrunedRowCountMismatch(7, 3);

        self::assertSame(7, $prunedRowCountMismatch->rowId);
        self::assertSame('Verified 3 rows from #7; the delete removed a different number.', $prunedRowCountMismatch->getMessage());
    }
}
