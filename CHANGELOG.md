# Changelog

All notable changes to `script-development/laravel-retention` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- The audit-retention half, extracted from entreezuil (WR-2926): `PruneAuditChains`, `ChainStartResolver`, `ChainStart`, `AnchorMac`, `AuditChainAnchor`, `AuditChainPruneResultDto`, the `HashChains` contract, and a publishable `audit_chain_anchors` migration that is never auto-loaded.
