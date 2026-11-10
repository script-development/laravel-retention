<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit;

/**
 * The hash a chain's first stored row is written after: the anchor's once the
 * chain was pruned, the seed before. $anchorValid is false only for an anchor
 * whose MAC does not verify; a never-pruned chain is valid.
 */
final readonly class ChainStart
{
    public function __construct(
        public string $hash,
        public ?AuditChainAnchor $anchor,
        public bool $anchorValid,
    ) {}

    public function isAnchored(): bool
    {
        return $this->anchor instanceof AuditChainAnchor;
    }

    /**
     * The id of the last row pruned, or null on a chain never pruned.
     */
    public function throughId(): ?int
    {
        return $this->anchor?->through_id;
    }
}
