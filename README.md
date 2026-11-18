# laravel-retention

Retention and erasure kernel for Script Development and Back to code Laravel territories.

- **Erasure on a retention clock:** anonymisation in place, with coverage gates that refuse a table or column the erasure route does not place.
- **Audit retention:** a prune for append-only, hash-chained audit tables that keeps the remaining chain verifiable.

The audit-retention half ships the `audit_chain_anchors` migration for publishing (`php artisan vendor:publish --tag=retention-audit-migrations`). A territory whose schema already has that table does not publish it.

Status: under construction, no release yet. Extracted from two production territories; nothing here is new behaviour.

## License

MIT
