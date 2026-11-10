<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * A chain whose rows are not on the prune's own connection cannot have its
 * deletes, its anchor and its prune record commit in one transaction, so the
 * prune refuses to run.
 */
final class ChainOnAnotherConnection extends RuntimeException
{
    public function __construct(string $chain, ?string $chainConnection, ?string $pruneConnection)
    {
        parent::__construct(sprintf(
            '%s is on connection %s, the prune on %s: its deletes could not share the transaction that moves its anchor.',
            $chain,
            $chainConnection ?? 'an unnamed connection',
            $pruneConnection ?? 'an unnamed connection',
        ));
    }
}
