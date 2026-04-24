<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase as LaravelRefreshDatabase;

/**
 * Runs db:wipe, migrate:install, then migrate. Keeps the migration repository on the same
 * connection as wipe/migrate and avoids migrate:fresh skipping db:wipe when the repository
 * table is missing.
 */
trait RefreshDatabase
{
    use LaravelRefreshDatabase {
        migrateDatabases as private laravelMigrateDatabasesNotUsed;
    }

    protected function migrateDatabases(): void
    {
        $db = (string) config('database.default');

        $wipe = ['--force' => true, '--database' => $db];
        if ($this->shouldDropViews()) {
            $wipe['--drop-views'] = true;
        }
        if ($this->shouldDropTypes()) {
            $wipe['--drop-types'] = true;
        }
        $this->artisan('db:wipe', $wipe);

        $this->artisan('migrate:install', ['--database' => $db]);

        $migrate = ['--force' => true, '--database' => $db];
        if ($s = $this->seeder()) {
            $migrate['--seeder'] = $s;
        } elseif ($this->shouldSeed()) {
            $migrate['--seed'] = true;
        }
        $this->artisan('migrate', $migrate);
    }
}
