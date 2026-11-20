<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * A chunk's delete removed a different number of rows than the chunk verified,
 * so its transaction rolls back and the chunk is refused at $rowId.
 */
final class PrunedRowCountMismatch extends RuntimeException
{
    public function __construct(
        public readonly int $rowId,
        int $verified,
    ) {
        parent::__construct(sprintf('Verified %d rows from #%d; the delete removed a different number.', $verified, $rowId));
    }
}
