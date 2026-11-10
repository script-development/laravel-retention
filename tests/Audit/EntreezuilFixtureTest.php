<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Audit;

use const JSON_THROW_ON_ERROR;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ScriptDevelopment\LaravelRetention\Audit\AnchorMac;
use ScriptDevelopment\LaravelRetention\Audit\ChainStartResolver;
use ScriptDevelopment\LaravelRetention\Tests\TestCase;

/**
 * Pins the arithmetic to the code it was extracted from (WR-2926). The fixture
 * was written by entreezuil's own AuditChainHash::anchorMac() and
 * PruneAuditChains at the commit named in its `produced_by` key, before the
 * extraction: a prune over real chains, the anchors it left, and MAC vectors.
 * An anchor production holds must verify under this package unchanged.
 */
final class EntreezuilFixtureTest extends TestCase
{
    private const string PRODUCED_BY = 'cfec46874e48d6f52f139938230cd69360c5637b';

    #[Test]
    public function the_fixture_names_the_commit_that_wrote_it(): void
    {
        self::assertSame(self::PRODUCED_BY, self::fixture()['produced_by']);
    }

    #[Test]
    public function every_anchor_mac_vector_computes_to_the_bytes_entreezuil_wrote(): void
    {
        $vectors = self::fixture()['anchor_mac_vectors'];

        self::assertNotEmpty($vectors, 'the fixture holds no MAC vectors, so this test would compare nothing');

        foreach ($vectors as $vector) {
            self::assertSame($vector['mac'], AnchorMac::compute($vector['seed'], $vector['chain'], $vector['through_id'], $vector['anchor_hash']));
        }
    }

    #[Test]
    public function every_anchor_entreezuils_prune_left_verifies_under_the_package(): void
    {
        $fixture = self::fixture();
        $anchors = $fixture['tables']['audit_chain_anchors'];

        self::assertCount(3, $anchors);
        DB::table('audit_chain_anchors')->insert($anchors);

        foreach ($anchors as $anchor) {
            $chainStart = new ChainStartResolver()->resolve($anchor['chain'], $fixture['seed']);

            self::assertTrue($chainStart->anchorValid, $anchor['chain']);
            self::assertSame($anchor['anchor_hash'], $chainStart->hash);
            self::assertSame($anchor['through_id'], $chainStart->throughId());
        }
    }

    #[Test]
    public function the_same_anchors_do_not_verify_under_another_seed(): void
    {
        $anchors = self::fixture()['tables']['audit_chain_anchors'];
        DB::table('audit_chain_anchors')->insert($anchors);

        foreach ($anchors as $anchor) {
            self::assertFalse(new ChainStartResolver()->resolve($anchor['chain'], 'not-the-seed')->anchorValid, $anchor['chain']);
        }
    }

    /**
     * @return array{seed: string, produced_by: string, anchor_mac_vectors: list<array{seed: string, chain: string, through_id: int, anchor_hash: string, mac: string}>, tables: array{audit_chain_anchors: list<array{id: int, chain: string, through_id: int, anchor_hash: string, mac: string}>}}
     */
    private static function fixture(): array
    {
        /** @var array{seed: string, produced_by: string, anchor_mac_vectors: list<array{seed: string, chain: string, through_id: int, anchor_hash: string, mac: string}>, tables: array{audit_chain_anchors: list<array{id: int, chain: string, through_id: int, anchor_hash: string, mac: string}>}} */
        return json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/entreezuil-cfec4687-prune.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
