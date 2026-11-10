# Decisions

Review findings declined on purpose, with the reason. A finding listed here is not a defect to re-raise; a change to the reason belongs in this file.

## 43855355bfe7 — coverage checker reads the Clover file unbounded

**Finding** (PR #1, `bin/coverage-check.php`): "The coverage checker parses the entire supplied Clover XML file without bounding its size."

**Declined.** Its only input is the Clover report the same CI job's test step has just written. No untrusted input reaches it, and a size budget would be a gate that can only fail falsely.

## 0d00ac2611e7 — resolver returns the anchor hash when its MAC is invalid

**Finding** (PR #1, `src/Audit/ChainStartResolver.php`): "The resolver returns the stored anchor hash even when its MAC is invalid, so callers can use an unauthenticated chain root."

**Declined, by design.** Binding invariant 5 in `CLAUDE.md`: a MAC fault is reported, never repaired. The resolver reports `anchorValid` and each caller decides: a verifier reports the chain as broken, and the prune refuses it. entreezuil's writer never checked the MAC at extraction (`cfec4687`), so refusing in the resolver would be new behaviour. Follow-up: WR-2932 (whether writers should refuse).
