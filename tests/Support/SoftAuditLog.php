<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Support;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A chain whose model soft-deletes: a delete through its scoped query only
 * stamps deleted_at.
 */
final class SoftAuditLog extends AlphaAuditLog
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'soft_audit_logs';
}
