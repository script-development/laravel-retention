<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit;

/**
 * The one place a chain's start is read, so the writer, the verifier and the
 * prune cannot disagree about it. A MAC fault is reported on the result and
 * never repaired here; each caller decides what a faulty anchor means for it.
 */
final readonly class ChainStartResolver
{
    public function resolve(string $chain, string $hashSeed, bool $lockForUpdate = false): ChainStart
    {
        $query = AuditChainAnchor::query()->where('chain', $chain);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $anchor = $query->first();

        if (!$anchor instanceof AuditChainAnchor) {
            return new ChainStart($hashSeed, null, true);
        }

        return new ChainStart(
            $anchor->anchor_hash,
            $anchor,
            hash_equals(AnchorMac::compute($hashSeed, $chain, $anchor->through_id, $anchor->anchor_hash), $anchor->mac),
        );
    }
}
