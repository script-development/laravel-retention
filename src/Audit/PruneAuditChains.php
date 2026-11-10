<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use ScriptDevelopment\LaravelRetention\Audit\Contracts\HashChains;
use UnexpectedValueException;

use function is_numeric;
use function is_string;

/**
 * Deletes audit rows past the retention window (ADR-0033 Amendment 2). Only an
 * unbroken id prefix can leave a chain, so a row older than the cutoff waits
 * behind any newer row before it. Each chunk decides under its row locks: it
 * deletes the leading rows that are expired and verify from the chain's start,
 * then moves the anchor and records the prune in one transaction. A prefix that
 * does not verify stays as evidence, and a verifier's verdict on the kept rows
 * is unchanged.
 */
final readonly class PruneAuditChains
{
    public const int CHUNK_SIZE = 500;

    public function __construct(
        private ConnectionInterface $connection,
        private HashChains $hashChains,
        private ChainStartResolver $chainStartResolver,
        #[Config('audit.hash_seed')]
        private string $hashSeed,
        #[Config('audit.retention_months')]
        private int $retentionMonths,
    ) {}

    public function execute(): AuditChainPruneResultDto
    {
        if ($this->retentionMonths < 1) {
            throw new RuntimeException('audit.retention_months must be at least 1.');
        }

        // A row exactly at the cutoff is not older than the window, so it stays. Stored timestamps hold whole seconds.
        $cutoff = CarbonImmutable::now()->startOfSecond()->subMonthsNoOverflow($this->retentionMonths);
        $rowsPruned = [];
        $refusedAtRowId = [];

        foreach ($this->hashChains->chains() as $modelClass) {
            $model = new $modelClass;
            $table = $model->getTable();
            $throughId = $this->lastExpiredId($model, $cutoff);

            while ($throughId !== null) {
                [$deleted, $refusedAt] = $this->pruneChunk($model, $throughId, $cutoff);

                if ($deleted > 0) {
                    $rowsPruned[$table] = ($rowsPruned[$table] ?? 0) + $deleted;
                }

                if ($refusedAt !== null) {
                    $refusedAtRowId[$table] = $refusedAt;
                }

                if ($deleted < self::CHUNK_SIZE) {
                    break;
                }
            }
        }

        return new AuditChainPruneResultDto($rowsPruned, $refusedAtRowId);
    }

    /**
     * How far a chunk may lock, so a prune stays off live rows. Read without a
     * lock, it only limits: pruneChunk() rechecks each row under its lock.
     */
    private function lastExpiredId(Model $model, CarbonImmutable $cutoff): ?int
    {
        $firstKept = $model->newQuery()->where($model->qualifyColumn('created_at'), '>=', $cutoff)->orderBy($model->getKeyName())->first();

        if ($firstKept instanceof Model) {
            return $this->rowId($firstKept) - 1;
        }

        $newestId = $model->newQuery()->max($model->getKeyName());

        return is_numeric($newestId) ? (int) $newestId : null;
    }

    /**
     * Locks in the writer's order, chain lock, rows, then anchor, so an append that
     * finds the table emptied reads the anchor this chunk committed.
     *
     * @return array{int, int|null} rows deleted, and the row id the chunk was refused at
     */
    private function pruneChunk(Model $model, int $throughId, CarbonImmutable $cutoff): array
    {
        // @audit-snapshot-retry-safety: fresh-fetch shape. The rows and the anchor are read under lockForUpdate() inside the closure and every result is returned, so a retry starts from committed state.
        return $this->connection->transaction(function() use ($model, $throughId, $cutoff): array {
            $table = $model->getTable();

            $this->hashChains->lockChain($model);

            $rows = $model->newQuery()->where($model->getKeyName(), '<=', $throughId)->orderBy($model->getKeyName())->limit(self::CHUNK_SIZE)->lockForUpdate()->get();

            $expired = $rows->takeWhile(function(Model $row) use ($cutoff): bool {
                $createdAt = $row->getAttribute('created_at');

                return $createdAt instanceof CarbonInterface && $createdAt->lt($cutoff);
            });
            $lastRow = $expired->last();

            if (!$lastRow instanceof Model) {
                return [0, null];
            }

            $chainStart = $this->chainStartResolver->resolve($table, $this->hashSeed, lockForUpdate: true);

            if (!$chainStart->anchorValid) {
                return [0, $chainStart->throughId()];
            }

            $previousHash = $chainStart->hash;

            foreach ($expired as $row) {
                $storedHash = $row->getAttribute('hash');

                try {
                    $recomputed = $this->hashChains->recompute($row, $previousHash);
                } catch (UnexpectedValueException) {
                    return [0, $this->rowId($row)];
                }

                if (!is_string($storedHash) || !hash_equals($recomputed, $storedHash)) {
                    return [0, $this->rowId($row)];
                }

                $previousHash = $storedHash;
            }

            $lastRowId = $this->rowId($lastRow);
            $model->newQuery()->whereKey($expired->modelKeys())->delete();

            $anchor = $chainStart->anchor ?? new AuditChainAnchor;
            $anchor->chain = $table;
            $anchor->through_id = $lastRowId;
            $anchor->anchor_hash = $previousHash;
            $anchor->mac = AnchorMac::compute($this->hashSeed, $table, $lastRowId, $previousHash);
            $anchor->save();

            $this->hashChains->recordPrune($table, $expired->count(), $lastRowId, $cutoff);

            return [$expired->count(), null];
        }, 3);
    }

    private function rowId(Model $row): int
    {
        $rowId = $row->getKey();

        return is_numeric($rowId) ? (int) $rowId : 0;
    }
}
