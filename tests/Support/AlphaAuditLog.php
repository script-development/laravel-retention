<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $payload
 * @property string $hash
 */
class AlphaAuditLog extends Model
{
    public const null UPDATED_AT = null;

    /** @var string */
    protected $table = 'alpha_audit_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
