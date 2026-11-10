# laravel-retention — Retention Kernel

Composer package (`script-development/laravel-retention`, MIT) holding the fleet's retention kernel, in two halves. The **audit-retention** half is here: a prune for append-only, hash-chained audit tables that keeps the remaining chain verifiable (ADR-0033 Amendment 2). The **erasure** half (anonymisation in place, from lokalekeuze) is not extracted yet. Sister package: `script-development/phpstan-warroom-rules`.

## Stack

- PHP ^8.5, `illuminate/*` ^13.39 (the floor every consumer ran at extraction, 2026-11-10). `check-lowest` installs the floor and runs the suite on it.
- Tests: PHPUnit 12 on Orchestra Testbench, SQLite in memory. PHPStan level max with Larastan over `src/`. Pint (war-room canon). Infection, `--min-msi=90`. Line coverage gate 100 %.
- CI: `.github/workflows/ci.yml`; `main` requires the `ci-passed` rollup, which passes only when every lane reports `success` and refuses an empty set.

## Binding invariants

1. **Byte-identical chain arithmetic.** `AnchorMac` must produce the bytes entreezuil's `AuditChainHash::anchorMac()` produced at extraction. `tests/Fixtures/entreezuil-cfec4687-prune.json` was written by that code (its `produced_by` key names the commit); `EntreezuilFixtureTest` holds the package to it. An anchor already in production must verify unchanged. Never regenerate the fixture from this package.
2. **No new behaviour without a ruling.** The package carries what a production territory runs. A behaviour no consumer has is a Commander flag, not a feature.
3. **No migration collision.** `RetentionServiceProvider` publishes the anchor migration (`retention-audit-migrations`) and never loads it. `RetentionServiceProvider::it_never_loads_the_anchor_migration` pins this.
4. **The territory keeps its recipes.** Hashing a row, locking a chain and recording a prune go through `Audit\Contracts\HashChains`; nothing territory-specific lives in `src/`.
5. **A MAC fault is reported, never repaired.** `ChainStartResolver` returns `anchorValid: false` and the stored hash; the prune refuses, a verifier reports, a writer decides for itself.

Doctrine is the war room's: `final readonly` Actions with one `execute()`, constructor injection, no facades in Actions, every mutation in a transaction, no cascades. Comments follow the three tiers; the `@audit-snapshot-retry-safety` marker on the prune's transaction is Tier 1 and stays with that closure.

## Consumers

| Territory | State |
|---|---|
| entreezuil | the source; consumes it in place of its own prune (WR-2926) |
| ublgenie | owes a 7-year prune (WR-1901) |
| codebook | owes a 7-year prune (WR-2925 item 2) |
| emmie, kendo, isms | owe a prune (WR-2926) |

## Commands

`composer test`, `composer test:coverage && composer coverage:check`, `composer phpstan`, `composer format:check`, `composer mutation`.
