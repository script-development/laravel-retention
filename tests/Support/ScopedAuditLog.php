<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Support;

use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * A chain whose model hides rows behind a global scope, so its scoped query
 * does not see the whole table.
 */
final class ScopedAuditLog extends AlphaAuditLog
{
    /** @var string */
    protected $table = 'scoped_audit_logs';

    #[Override]
    protected static function booted(): void
    {
        self::addGlobalScope('visible', function(Builder $builder): void {
            $builder->where('hidden', false);
        });
    }
}
