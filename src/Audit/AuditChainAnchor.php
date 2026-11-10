<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Where a pruned chain now starts: the hash of the last row PruneAuditChains
 * deleted, keyed by AnchorMac so it cannot be moved without the hash seed. One
 * row per chain, moved forward in the transaction that deletes the rows.
 *
 * @property int    $id
 * @property string $chain
 * @property int    $through_id
 * @property string $anchor_hash
 * @property string $mac
 */
final class AuditChainAnchor extends Model
{
    public const null UPDATED_AT = null;

    /** @var bool */
    public $timestamps = false;

    /** @var string */
    protected $table = 'audit_chain_anchors';

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'through_id' => 'integer',
        ];
    }
}
