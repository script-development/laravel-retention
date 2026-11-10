<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use ScriptDevelopment\LaravelRetention\Audit\Contracts\HashChains;
use ScriptDevelopment\LaravelRetention\RetentionServiceProvider;
use ScriptDevelopment\LaravelRetention\Tests\Support\DemoHashChains;

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
        $app['config']->set('audit.retention_months', 24);
        $app->singleton(DemoHashChains::class);
        $app->bind(HashChains::class, DemoHashChains::class);
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        (require __DIR__ . '/../database/migrations/2026_11_05_000002_create_audit_chain_anchors_table.php')->up();

        foreach (['alpha_audit_logs', 'beta_audit_logs', 'soft_audit_logs', 'scoped_audit_logs'] as $name) {
            $schema->create($name, function(Blueprint $table): void {
                $table->id();
                $table->string('payload');
                $table->char('hash', 64);
                $table->timestamp('created_at');
                $table->timestamp('deleted_at')->nullable();
                $table->boolean('hidden')->default(false);
            });
        }
    }
}
