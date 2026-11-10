# laravel-retention

Retention and erasure kernel for Script Development and Back to code Laravel territories.

- **Audit retention (landed, unreleased):** a prune for append-only, hash-chained audit tables that keeps the remaining chain verifiable. Extracted from entreezuil (EZ-0037, WR-2926).
- **Erasure on a retention clock (not here yet):** anonymisation in place, with coverage gates that refuse a table or column the erasure route does not place. To be extracted from lokalekeuze.

Status: no release yet. Nothing here is new behaviour: every line came from a production territory.

## Audit retention

The package owns the algorithm; the territory keeps its recipes.

| Package | Territory |
|---|---|
| `PruneAuditChains`: prefix-only, chunked, verify-before-delete, anchor move and prune record in one transaction, three attempts | implements `Audit\Contracts\HashChains`: its chains, `recompute()`, its writer's chain lock, where a prune is recorded |
| `ChainStartResolver`: where a chain starts (anchor or seed), with the anchor's MAC checked and reported | its writer and verifier read the chain start through it |
| `AuditChainAnchor` (table `audit_chain_anchors`) and `AnchorMac` | its artisan command, schedule and config |

Config read: `audit.hash_seed` and `audit.retention_months` (at least 1).

### Adopting

1. `composer require script-development/laravel-retention`.
2. A territory without `audit_chain_anchors`: `php artisan vendor:publish --tag=retention-audit-migrations`, then migrate. The package never loads the migration itself, so a territory that already has the table is untouched.
3. Bind your implementation: `$this->app->bind(HashChains::class, YourChains::class);`.
4. Point your writer's empty-table path and your verifier's walk at `ChainStartResolver`, so all three agree where a chain starts.
5. Call `PruneAuditChains::execute()` from a scheduled command; exit with the result's `exitCode()`.

## License

MIT
