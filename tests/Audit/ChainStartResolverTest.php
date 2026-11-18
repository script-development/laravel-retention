<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Audit;

use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ScriptDevelopment\LaravelRetention\Audit\AnchorMac;
use ScriptDevelopment\LaravelRetention\Audit\AuditChainAnchor;
use ScriptDevelopment\LaravelRetention\Audit\ChainStartResolver;
use ScriptDevelopment\LaravelRetention\Tests\TestCase;

final class ChainStartResolverTest extends TestCase
{
    #[Test]
    public function a_chain_never_pruned_starts_from_the_seed(): void
    {
        $chainStart = new ChainStartResolver()->resolve('alpha_audit_logs', self::SEED);

        self::assertSame(self::SEED, $chainStart->hash);
        self::assertFalse($chainStart->isAnchored());
        self::assertTrue($chainStart->anchorValid);
        self::assertNull($chainStart->anchor);
        self::assertNull($chainStart->throughId());
    }

    #[Test]
    public function a_pruned_chain_starts_from_its_anchor(): void
    {
        $this->anchor('alpha_audit_logs', 7, str_repeat('a', 64), AnchorMac::compute(self::SEED, 'alpha_audit_logs', 7, str_repeat('a', 64)));

        $chainStart = new ChainStartResolver()->resolve('alpha_audit_logs', self::SEED);

        self::assertSame(str_repeat('a', 64), $chainStart->hash);
        self::assertTrue($chainStart->isAnchored());
        self::assertTrue($chainStart->anchorValid);
        self::assertSame(7, $chainStart->throughId());
    }

    #[Test]
    public function an_anchor_copied_from_another_chain_is_reported_and_still_returned(): void
    {
        $this->anchor('beta_audit_logs', 7, str_repeat('a', 64), AnchorMac::compute(self::SEED, 'alpha_audit_logs', 7, str_repeat('a', 64)));

        $chainStart = new ChainStartResolver()->resolve('beta_audit_logs', self::SEED);

        self::assertFalse($chainStart->anchorValid);
        self::assertSame(str_repeat('a', 64), $chainStart->hash, 'a caller that chains regardless still gets the stored hash');
        self::assertSame(7, $chainStart->throughId());
    }

    #[Test]
    public function an_anchor_moved_past_its_rows_is_reported(): void
    {
        $this->anchor('alpha_audit_logs', 8, str_repeat('a', 64), AnchorMac::compute(self::SEED, 'alpha_audit_logs', 7, str_repeat('a', 64)));

        self::assertFalse(new ChainStartResolver()->resolve('alpha_audit_logs', self::SEED)->anchorValid);
    }

    #[Test]
    public function it_reads_only_the_named_chain(): void
    {
        $this->anchor('beta_audit_logs', 7, str_repeat('b', 64), AnchorMac::compute(self::SEED, 'beta_audit_logs', 7, str_repeat('b', 64)));

        self::assertFalse(new ChainStartResolver()->resolve('alpha_audit_logs', self::SEED)->isAnchored());
    }

    #[Test]
    public function it_reads_with_a_lock_only_when_asked(): void
    {
        $connection = DB::connection();
        $connection->setQueryGrammar(new MySqlGrammar($connection));
        $resolver = new ChainStartResolver;

        $locked = $connection->pretend(fn() => $resolver->resolve('alpha_audit_logs', self::SEED, lockForUpdate: true));
        $plain = $connection->pretend(fn() => $resolver->resolve('alpha_audit_logs', self::SEED));

        self::assertSame(['select * from `audit_chain_anchors` where `chain` = \'alpha_audit_logs\' limit 1 for update'], array_column($locked, 'query'));
        self::assertSame(['select * from `audit_chain_anchors` where `chain` = \'alpha_audit_logs\' limit 1'], array_column($plain, 'query'));
    }

    #[Test]
    public function the_anchor_reads_its_through_id_as_an_integer_whatever_the_driver_returns(): void
    {
        $anchor = new AuditChainAnchor;
        $anchor->setRawAttributes(['through_id' => '7']);

        self::assertSame(7, $anchor->through_id);
    }

    private function anchor(string $chain, int $throughId, string $anchorHash, string $mac): void
    {
        DB::table('audit_chain_anchors')->insert(['chain' => $chain, 'through_id' => $throughId, 'anchor_hash' => $anchorHash, 'mac' => $mac]);
    }
}
