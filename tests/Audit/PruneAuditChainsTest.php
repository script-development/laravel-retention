<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Audit;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use ScriptDevelopment\LaravelRetention\Audit\AnchorMac;
use ScriptDevelopment\LaravelRetention\Audit\AuditChainAnchor;
use ScriptDevelopment\LaravelRetention\Audit\AuditChainPruneResultDto;
use ScriptDevelopment\LaravelRetention\Audit\ChainStartResolver;
use ScriptDevelopment\LaravelRetention\Audit\PruneAuditChains;
use ScriptDevelopment\LaravelRetention\Tests\Support\AlphaAuditLog;
use ScriptDevelopment\LaravelRetention\Tests\Support\BetaAuditLog;
use ScriptDevelopment\LaravelRetention\Tests\Support\Chains;
use ScriptDevelopment\LaravelRetention\Tests\Support\DemoHashChains;
use ScriptDevelopment\LaravelRetention\Tests\Support\LockMarkingSQLiteGrammar;
use ScriptDevelopment\LaravelRetention\Tests\TestCase;

final class PruneAuditChainsTest extends TestCase
{
    private const string NOW = '2028-06-15 12:00:00';

    private const string CUTOFF = '2026-06-15T12:00:00+00:00';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse(self::NOW));
    }

    #[Override]
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function it_deletes_rows_older_than_the_window_and_keeps_the_row_exactly_at_the_cutoff(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2026-06-15 11:59:59', '2026-06-15 12:00:00', '2028-01-01 10:00:00');

        $result = $this->prune();

        self::assertSame(['alpha_audit_logs' => 2], $result->rowsPruned);
        self::assertSame([], $result->refusedAtRowId);
        self::assertSame(0, $result->exitCode());
        self::assertSame([$ids[2], $ids[3]], Chains::ids(AlphaAuditLog::class));
        self::assertNull(Chains::firstBreak(AlphaAuditLog::class));
    }

    #[Test]
    public function it_moves_the_anchor_to_the_last_pruned_row_with_a_mac_the_seed_verifies(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2021-01-01 10:00:00', '2028-01-01 10:00:00');
        $prunedHash = (string) AlphaAuditLog::query()->whereKey($ids[1])->value('hash');

        $this->prune();

        $anchor = AuditChainAnchor::query()->where('chain', 'alpha_audit_logs')->sole();
        self::assertSame($ids[1], $anchor->through_id);
        self::assertSame($prunedHash, $anchor->anchor_hash);
        self::assertSame(AnchorMac::compute(self::SEED, 'alpha_audit_logs', $ids[1], $prunedHash), $anchor->mac);
        self::assertSame(1, AuditChainAnchor::query()->count());
    }

    #[Test]
    public function it_records_each_pruned_chunk_once_with_its_chain_count_last_id_and_cutoff(): void
    {
        $alpha = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2020-02-01 10:00:00');
        $beta = Chains::append(BetaAuditLog::class, '2020-03-01 10:00:00');

        $this->prune();

        self::assertSame([
            ['alpha_audit_logs', 2, $alpha[1], self::CUTOFF],
            ['beta_audit_logs', 1, $beta[0], self::CUTOFF],
        ], $this->demo()->prunes);
    }

    #[Test]
    public function it_takes_the_chain_lock_then_locks_the_rows_then_the_anchor(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2020-02-01 10:00:00', '2028-01-01 10:00:00');
        $connection = DB::connection();
        $connection->setQueryGrammar(new LockMarkingSQLiteGrammar($connection));
        $demo = $this->demo();
        $chunkBindings = [];
        DB::listen(function($query) use ($demo, &$chunkBindings): void {
            $locked = str_ends_with($query->sql, '/* for update */') ? ' locked' : '';
            $demo->calls[] = match (true) {
                str_contains($query->sql, 'audit_chain_anchors') => 'anchor' . $locked,
                str_contains($query->sql, 'limit ' . PruneAuditChains::CHUNK_SIZE) => 'rows' . $locked,
                default => 'other' . $locked,
            };

            if (str_contains($query->sql, 'limit ' . PruneAuditChains::CHUNK_SIZE)) {
                $chunkBindings[] = $query->bindings;
            }
        });

        $this->prune();

        self::assertSame(
            ['lock:alpha_audit_logs', 'rows locked', 'anchor locked', 'anchor', 'record:alpha_audit_logs'],
            array_values(array_filter($demo->calls, fn(string $call): bool => !str_starts_with($call, 'other'))),
        );
        self::assertSame([[$ids[1]]], $chunkBindings, 'the chunk reaches the row before the first kept one, and no further');
    }

    #[Test]
    public function it_reaches_the_newest_row_when_every_row_has_expired(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2020-02-01 10:00:00');
        $chunkBindings = [];
        DB::listen(function($query) use (&$chunkBindings): void {
            if (str_contains($query->sql, 'limit ' . PruneAuditChains::CHUNK_SIZE)) {
                $chunkBindings[] = $query->bindings;
            }
        });

        $this->prune();

        self::assertSame([[$ids[1]]], $chunkBindings);
    }

    #[Test]
    public function it_rechecks_expiry_under_the_row_lock(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2028-01-01 10:00:00');
        $moved = false;
        DB::connection()->beforeExecuting(function(string $sql) use (&$moved, $ids): void {
            if (!$moved && str_contains($sql, 'limit ' . PruneAuditChains::CHUNK_SIZE)) {
                $moved = true;
                DB::table('alpha_audit_logs')->where('id', $ids[0])->update(['created_at' => '2027-01-01 10:00:00']);
            }
        });

        $result = $this->prune();

        self::assertTrue($moved);
        self::assertSame([], $result->rowsPruned);
        self::assertSame([], $result->refusedAtRowId, 'a row no longer expired is kept, not read as a broken prefix');
        self::assertSame($ids, Chains::ids(AlphaAuditLog::class));
    }

    #[Test]
    public function it_keeps_an_expired_row_that_waits_behind_a_newer_one(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2028-01-01 10:00:00', '2020-02-01 10:00:00');

        $result = $this->prune();

        self::assertSame(['alpha_audit_logs' => 1], $result->rowsPruned);
        self::assertSame([$ids[1], $ids[2]], Chains::ids(AlphaAuditLog::class));
        self::assertNull(Chains::firstBreak(AlphaAuditLog::class));
    }

    #[Test]
    public function it_stops_at_a_prefix_row_that_does_not_verify_and_keeps_it_as_evidence(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2020-02-01 10:00:00', '2020-03-01 10:00:00', '2028-01-01 10:00:00');
        AlphaAuditLog::query()->whereKey($ids[1])->toBase()->update(['payload' => 'tampered']);

        $result = $this->prune();

        self::assertSame([], $result->rowsPruned);
        self::assertSame(['alpha_audit_logs' => $ids[1]], $result->refusedAtRowId);
        self::assertSame(1, $result->exitCode());
        self::assertSame($ids, Chains::ids(AlphaAuditLog::class));
        self::assertFalse(AuditChainAnchor::query()->exists());
        self::assertSame([], $this->demo()->prunes);
    }

    #[Test]
    public function it_refuses_at_a_row_the_territory_cannot_read(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2020-02-01 10:00:00');
        AlphaAuditLog::query()->whereKey($ids[0])->toBase()->update(['payload' => 'unreadable']);

        $result = $this->prune();

        self::assertSame(['alpha_audit_logs' => $ids[0]], $result->refusedAtRowId);
        self::assertSame([], $result->rowsPruned);
        self::assertSame($ids, Chains::ids(AlphaAuditLog::class));
    }

    #[Test]
    public function it_refuses_a_forged_anchor_and_deletes_nothing(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2020-02-01 10:00:00', '2026-12-01 10:00:00', '2028-01-01 10:00:00');
        $this->prune();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2029-01-01 00:00:00'));
        AuditChainAnchor::query()->where('chain', 'alpha_audit_logs')->toBase()->update(['mac' => str_repeat('0', 64)]);
        $before = Chains::ids(AlphaAuditLog::class);

        $result = $this->prune();

        self::assertSame(['alpha_audit_logs' => $ids[1]], $result->refusedAtRowId);
        self::assertSame([], $result->rowsPruned);
        self::assertSame($before, Chains::ids(AlphaAuditLog::class));
        self::assertSame(str_repeat('0', 64), AuditChainAnchor::query()->sole()->mac, 'a MAC fault is reported, never repaired');
    }

    #[Test]
    public function it_continues_a_pruned_chain_from_its_anchor(): void
    {
        Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2020-02-01 10:00:00');
        $this->prune();
        self::assertSame([], Chains::ids(AlphaAuditLog::class));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2031-01-01 00:00:00'));
        Chains::append(AlphaAuditLog::class, '2026-01-01 10:00:00', '2030-01-01 10:00:00');
        $result = $this->prune();

        self::assertSame(['alpha_audit_logs' => 1], $result->rowsPruned);
        self::assertNull(Chains::firstBreak(AlphaAuditLog::class));
        self::assertCount(2, $this->demo()->prunes);
    }

    #[Test]
    public function a_tampered_row_inside_the_kept_window_still_fails_verification_after_a_prune(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2027-01-01 10:00:00', '2028-01-01 10:00:00');
        AlphaAuditLog::query()->whereKey($ids[2])->toBase()->update(['payload' => 'tampered']);

        $result = $this->prune();

        self::assertSame(['alpha_audit_logs' => 1], $result->rowsPruned);
        self::assertSame($ids[2], Chains::firstBreak(AlphaAuditLog::class));
    }

    #[Test]
    public function it_prunes_in_chunks_and_records_each_chunk(): void
    {
        $timestamps = array_map(fn(int $i): string => CarbonImmutable::parse('2020-01-01 00:00:00')->addMinutes($i)->toDateTimeString(), range(0, PruneAuditChains::CHUNK_SIZE));
        $ids = Chains::append(AlphaAuditLog::class, ...$timestamps, ...['2028-01-01 10:00:00']);

        $result = $this->prune();

        self::assertSame(['alpha_audit_logs' => PruneAuditChains::CHUNK_SIZE + 1], $result->rowsPruned);
        self::assertSame([
            ['alpha_audit_logs', PruneAuditChains::CHUNK_SIZE, $ids[PruneAuditChains::CHUNK_SIZE - 1], self::CUTOFF],
            ['alpha_audit_logs', 1, $ids[PruneAuditChains::CHUNK_SIZE], self::CUTOFF],
        ], $this->demo()->prunes);
        self::assertSame($ids[PruneAuditChains::CHUNK_SIZE], AuditChainAnchor::query()->sole()->through_id);
        self::assertNull(Chains::firstBreak(AlphaAuditLog::class));
    }

    #[Test]
    public function it_stops_after_a_full_chunk_that_reaches_the_last_expired_row(): void
    {
        $timestamps = array_map(fn(int $i): string => CarbonImmutable::parse('2020-01-01 00:00:00')->addMinutes($i)->toDateTimeString(), range(1, PruneAuditChains::CHUNK_SIZE));
        Chains::append(AlphaAuditLog::class, ...$timestamps);

        $result = $this->prune();

        self::assertSame(['alpha_audit_logs' => PruneAuditChains::CHUNK_SIZE], $result->rowsPruned);
        self::assertCount(1, $this->demo()->prunes);
    }

    #[Test]
    public function it_does_nothing_on_empty_chains_or_chains_with_nothing_expired(): void
    {
        Chains::append(BetaAuditLog::class, '2027-01-01 10:00:00');

        $result = $this->prune();

        self::assertSame([], $result->rowsPruned);
        self::assertSame([], $result->refusedAtRowId);
        self::assertFalse(AuditChainAnchor::query()->exists());
        self::assertSame(['lock:beta_audit_logs'], $this->demo()->calls, 'the empty chain is skipped; the kept chain locks, finds nothing expired and records nothing');
    }

    #[Test]
    public function it_rolls_the_chunk_back_when_recording_the_prune_fails(): void
    {
        $ids = Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00', '2028-01-01 10:00:00');
        $this->demo()->onRecordPrune = function(): never {
            throw new RuntimeException('crash before the event');
        };

        try {
            $this->prune();
            self::fail('the prune did not rethrow');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('crash before the event', $runtimeException->getMessage());
        }

        self::assertSame($ids, Chains::ids(AlphaAuditLog::class));
        self::assertFalse(AuditChainAnchor::query()->exists());
    }

    #[Test]
    public function it_retries_each_chunk_transaction_three_times(): void
    {
        Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00');
        $real = $this->app->make(ConnectionInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('transaction')->with(self::isInstanceOf(Closure::class), 3)
            ->willReturnCallback(fn(Closure $callback, int $attempts): mixed => $real->transaction($callback, $attempts));

        new PruneAuditChains($connection, $this->demo(), new ChainStartResolver, self::SEED, 24)->execute();

        self::assertSame([], Chains::ids(AlphaAuditLog::class));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function retentionsBelowOne(): iterable
    {
        yield 'zero' => [0];

        yield 'negative' => [-1];
    }

    #[DataProvider('retentionsBelowOne')]
    #[Test]
    public function it_refuses_a_retention_window_below_one_month(int $months): void
    {
        Chains::append(AlphaAuditLog::class, '2020-01-01 10:00:00');
        $this->app['config']->set('audit.retention_months', $months);

        try {
            $this->prune();
            self::fail('a retention window below one month was accepted');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('audit.retention_months must be at least 1.', $runtimeException->getMessage());
        }

        self::assertCount(1, Chains::ids(AlphaAuditLog::class));
    }

    #[Test]
    public function it_accepts_a_one_month_window(): void
    {
        Chains::append(AlphaAuditLog::class, '2028-05-15 11:59:59', '2028-05-15 12:00:00');
        $this->app['config']->set('audit.retention_months', 1);

        self::assertSame(['alpha_audit_logs' => 1], $this->prune()->rowsPruned);
    }

    #[Test]
    public function it_reads_the_window_from_config(): void
    {
        Chains::append(AlphaAuditLog::class, '2021-06-15 11:59:59', '2021-06-15 12:00:00');
        $this->app['config']->set('audit.retention_months', 84);

        self::assertSame(['alpha_audit_logs' => 1], $this->prune()->rowsPruned);
    }

    #[Test]
    public function it_cuts_off_on_whole_seconds(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2028-06-15 12:00:00.900'));
        $ids = Chains::append(AlphaAuditLog::class, '2026-06-15 11:59:59', '2026-06-15 12:00:00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2028-06-15 12:00:00.900'));

        $this->prune();

        self::assertSame([$ids[1]], Chains::ids(AlphaAuditLog::class));
    }

    #[Test]
    public function it_does_not_overflow_month_ends(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2028-02-29 12:00:00'));
        $ids = Chains::append(AlphaAuditLog::class, '2026-02-28 11:59:59', '2026-02-28 12:00:00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2028-02-29 12:00:00'));

        $this->prune();

        self::assertSame([$ids[1]], Chains::ids(AlphaAuditLog::class));
    }

    private function prune(): AuditChainPruneResultDto
    {
        return $this->app->make(PruneAuditChains::class)->execute();
    }

    private function demo(): DemoHashChains
    {
        return $this->app->make(DemoHashChains::class);
    }
}
