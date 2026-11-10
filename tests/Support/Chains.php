<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use ScriptDevelopment\LaravelRetention\Audit\ChainStartResolver;
use ScriptDevelopment\LaravelRetention\Tests\TestCase;

use function is_string;

/**
 * A writer and a verifier over the demo chains, both starting where
 * ChainStartResolver says, as a territory's own writer and verifier do.
 */
final class Chains
{
    /**
     * @param class-string<Model> $modelClass
     *
     * @return list<int> the new rows' ids
     */
    public static function append(string $modelClass, string ...$timestamps): array
    {
        $ids = [];

        foreach ($timestamps as $index => $timestamp) {
            $model = new $modelClass;
            $newest = $model->newQuery()->orderByDesc('id')->value('hash');
            $previousHash = is_string($newest) ? $newest : new ChainStartResolver()->resolve($model->getTable(), TestCase::SEED)->hash;
            $createdAt = CarbonImmutable::parse($timestamp);
            $payload = 'row ' . $index . ' at ' . $timestamp;

            $row = $model->newInstance();
            $row->setAttribute('payload', $payload);
            $row->setAttribute('hash', DemoHashChains::hash($previousHash, $payload, $createdAt));
            $row->setAttribute('created_at', $createdAt);
            $row->save();

            $ids[] = (int) $row->getKey();
        }

        return $ids;
    }

    /**
     * @param class-string<Model> $modelClass
     *
     * @return int|null the first row that does not verify, or null when the chain verifies
     */
    public static function firstBreak(string $modelClass): ?int
    {
        $model = new $modelClass;
        $chainStart = new ChainStartResolver()->resolve($model->getTable(), TestCase::SEED);

        if (!$chainStart->anchorValid) {
            return $chainStart->throughId();
        }

        $previousHash = $chainStart->hash;
        $demo = new DemoHashChains;

        foreach ($model->newQuery()->orderBy('id')->get() as $row) {
            if (!hash_equals($demo->recompute($row, $previousHash), (string) $row->getAttribute('hash'))) {
                return (int) $row->getKey();
            }

            $previousHash = (string) $row->getAttribute('hash');
        }

        return null;
    }

    /**
     * @param class-string<Model> $modelClass
     *
     * @return list<int>
     */
    public static function ids(string $modelClass): array
    {
        return array_map(intval(...), new $modelClass()->newQuery()->orderBy('id')->pluck('id')->all());
    }
}
