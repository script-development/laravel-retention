<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use ScriptDevelopment\LaravelRetention\RetentionServiceProvider;

abstract class TestCase extends Orchestra
{
    public const string SEED = 'package-test-seed';

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [RetentionServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('audit.hash_seed', self::SEED);
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        (require __DIR__ . '/../database/migrations/2026_11_05_000002_create_audit_chain_anchors_table.php')->up();
    }
}
