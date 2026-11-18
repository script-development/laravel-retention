<?php

declare(strict_types = 1);

namespace ScriptDevelopment\LaravelRetention;

use Illuminate\Support\ServiceProvider;

/**
 * Publishes the anchor migration and never loads it: a territory that already
 * has `audit_chain_anchors` must not have it created again.
 */
final class RetentionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'retention-audit-migrations');
    }
}
