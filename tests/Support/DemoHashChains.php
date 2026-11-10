<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use ScriptDevelopment\LaravelRetention\Audit\Contracts\HashChains;
use UnexpectedValueException;

use function is_string;

/**
 * A territory stand-in: its chains, one recipe, and a log of every call the
 * package makes, so tests can assert order and arguments.
 */
final class DemoHashChains implements HashChains
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array{string, int, int, string}> */
    public array $prunes = [];

    public ?Closure $onRecordPrune = null;

    /** @var list<class-string<Model>> */
    public array $chainModels = [AlphaAuditLog::class, BetaAuditLog::class];

    public function chains(): array
    {
        return $this->chainModels;
    }

    public function recompute(Model $row, string $previousHash): string
    {
        $payload = $row->getAttribute('payload');
        $createdAt = $row->getAttribute('created_at');

        if (!is_string($payload) || $payload === 'unreadable' || !$createdAt instanceof CarbonInterface) {
            throw new UnexpectedValueException('payload is unreadable.');
        }

        return self::hash($previousHash, $payload, $createdAt);
    }

    public function lockChain(Model $chain): void
    {
        $this->calls[] = 'lock:' . $chain->getTable();
    }

    public function recordPrune(string $chain, int $rowsPruned, int $throughId, CarbonImmutable $cutoff): void
    {
        $this->calls[] = 'record:' . $chain;
        $this->prunes[] = [$chain, $rowsPruned, $throughId, $cutoff->toIso8601String()];

        if ($this->onRecordPrune instanceof Closure) {
            ($this->onRecordPrune)();
        }
    }

    public static function hash(string $previousHash, string $payload, CarbonInterface $createdAt): string
    {
        return hash('sha256', $previousHash . '|' . $payload . '|' . $createdAt->toIso8601String());
    }
}
