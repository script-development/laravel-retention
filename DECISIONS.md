# Decisions

Review findings declined on purpose, with the reason. A finding listed here is not a defect to re-raise; a change to the reason belongs in this file.

## 43855355bfe7 — coverage checker reads the Clover file unbounded

**Finding** (PR #1, `bin/coverage-check.php`): "The coverage checker parses the entire supplied Clover XML file without bounding its size."

**Declined.** Its only input is the Clover report the same CI job's test step has just written. No untrusted input reaches it, and a size budget would be a gate that can only fail falsely.

## 0d00ac2611e7 — resolver returns the anchor hash when its MAC is invalid

**Finding** (PR #1, `src/Audit/ChainStartResolver.php`): "The resolver returns the stored anchor hash even when its MAC is invalid, so callers can use an unauthenticated chain root."

**Declined, by design.** Binding invariant 5 in `CLAUDE.md`: a MAC fault is reported, never repaired. The resolver reports `anchorValid` and each caller decides: a verifier reports the chain as broken, and the prune refuses it. entreezuil's writer never checked the MAC at extraction (`cfec4687`), so refusing in the resolver would be new behaviour. Follow-up: WR-2932 (whether writers should refuse).

## ab009b376222 — the published anchor migration creates its table unconditionally

**Finding** (PR #1, `database/migrations/2026_11_05_000002_create_audit_chain_anchors_table.php`): "Publishing the anchor migration can block deployment when the table already exists."

**Declined.** A territory publishes this migration only when its schema has no `audit_chain_anchors` table; `README.md` says so. A territory that publishes it over an existing table runs both creates on every fresh database, its test database included, so its own CI fails before anything deploys. A `hasTable` guard would turn that loud failure into a silent skip over a table whose columns may differ from the ones the resolver and the prune read. Follow-up: WR-2926.
