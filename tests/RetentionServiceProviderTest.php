<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention\Tests;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use ScriptDevelopment\LaravelRetention\RetentionServiceProvider;

final class RetentionServiceProviderTest extends TestCase
{
    #[Test]
    public function it_never_loads_the_anchor_migration(): void
    {
        $paths = $this->app->make('migrator')->paths();
        $packageMigrations = realpath(__DIR__ . '/../database/migrations');

        self::assertIsString($packageMigrations);
        self::assertNotContains($packageMigrations, array_map(fn(string $path): string => (string) realpath($path), $paths));
        self::assertNotContains(__DIR__ . '/../src/../database/migrations', $paths);
    }

    #[Test]
    public function it_publishes_the_anchor_migration_under_its_tag(): void
    {
        $published = ServiceProvider::pathsToPublish(RetentionServiceProvider::class, 'retention-audit-migrations');

        self::assertCount(1, $published);
        self::assertSame(realpath(__DIR__ . '/../database/migrations'), realpath((string) array_key_first($published)));
        self::assertSame($this->app->databasePath('migrations'), array_values($published)[0]);
        self::assertContains(realpath(__DIR__ . '/../database/migrations'), array_map(fn(string $path): string => (string) realpath($path), ServiceProvider::publishableMigrationPaths()));
    }

    #[Test]
    public function the_anchor_migration_creates_and_drops_the_table_entreezuil_has_in_production(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();
        $migration = require __DIR__ . '/../database/migrations/2026_11_05_000002_create_audit_chain_anchors_table.php';

        self::assertSame(['id', 'chain', 'through_id', 'anchor_hash', 'mac'], $schema->getColumnListing('audit_chain_anchors'));

        $migration->down();
        self::assertFalse($schema->hasTable('audit_chain_anchors'));

        $migration->up();
        self::assertTrue($schema->hasTable('audit_chain_anchors'));
        self::assertSame(['audit_chain_anchors_chain_unique'], array_values(array_filter(array_column($schema->getIndexes('audit_chain_anchors'), 'name'), fn(string $name): bool => str_contains($name, 'unique'))));
    }
}
