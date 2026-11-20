<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Override;

use function is_string;

/**
 * SQLite compiles a row lock to nothing, so a dropped lockForUpdate() would be
 * invisible. This grammar writes the lock as a trailing comment SQLite ignores,
 * so a test can see which statements asked for one.
 */
final class LockMarkingSQLiteGrammar extends SQLiteGrammar
{
    #[Override]
    protected function compileLock(Builder $query, $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value ? '/* for update */' : '/* shared */';
    }
}
