<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Audit;

use const JSON_THROW_ON_ERROR;

/**
 * Keys a prune anchor to the hash seed and to its chain, so one that was moved,
 * copied from another chain or written by hand does not verify.
 */
final readonly class AnchorMac
{
    public static function compute(string $hashSeed, string $chain, int $throughId, string $anchorHash): string
    {
        return hash_hmac('sha256', json_encode([$chain, $throughId, $anchorHash], JSON_THROW_ON_ERROR), $hashSeed);
    }
}
