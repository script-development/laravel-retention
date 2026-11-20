<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Support;

/**
 * A chain whose model lives on a second connection, outside the prune's.
 */
final class ElsewhereAuditLog extends AlphaAuditLog
{
    /** @var string|null */
    protected $connection = 'elsewhere';

    /** @var string */
    protected $table = 'elsewhere_audit_logs';
}
