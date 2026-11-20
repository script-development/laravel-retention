<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit;

final readonly class AuditChainPruneResultDto
{
    /**
     * @param array<string, int> $rowsPruned     rows deleted, by chain table; a chain with none is absent
     * @param array<string, int> $refusedAtRowId the row the chain's prune stopped at, by chain table: one that does not verify, or one with no creation time
     */
    public function __construct(
        public array $rowsPruned,
        public array $refusedAtRowId,
    ) {}

    public function exitCode(): int
    {
        return $this->refusedAtRowId === [] ? 0 : 1;
    }
}
