# Changelog

All notable changes to `script-development/laravel-retention` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Where an audit hash chain starts, extracted from entreezuil (WR-2926): `ChainStartResolver`, `ChainStart`, `AnchorMac`, the `AuditChainAnchor` model, and a publishable `audit_chain_anchors` migration that is never auto-loaded.
