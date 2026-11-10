<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Support;

final class BetaAuditLog extends AlphaAuditLog
{
    /** @var string */
    protected $table = 'beta_audit_logs';
}
