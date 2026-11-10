<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use UnexpectedValueException;

/**
 * What a territory supplies so the package can prune its chains: which chains
 * exist, how a row's hash is recomputed, how its writer serialises a chain, and
 * where a prune is recorded. The recipes stay the territory's own.
 */
interface HashChains
{
    /**
     * @return list<class-string<Model>> one model per hash-chained table
     */
    public function chains(): array;

    /**
     * Recompute a stored row's hash as the territory's writer wrote it after $previousHash.
     *
     * @throws UnexpectedValueException when a hashed column holds a value the writer could not have written
     */
    public function recompute(Model $row, string $previousHash): string;

    /**
     * Take the lock the territory's writer takes before it appends, held until commit.
     */
    public function lockChain(Model $chain): void;

    /**
     * Record one pruned chunk in the territory's own audit trail. Called inside
     * the transaction that deleted the rows, so a failure here rolls the chunk back.
     */
    public function recordPrune(string $chain, int $rowsPruned, int $throughId, CarbonImmutable $cutoff): void;
}
