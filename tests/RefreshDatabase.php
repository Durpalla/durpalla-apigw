<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase as LaravelRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fast, reliable test DB reset for apigw (loads ~280 Durpalla migrations).
 *
 * MySQL: DROP/CREATE database (seconds) instead of multi-table DROP (slow + fragile).
 * Other drivers: drop tables one-by-one with IF EXISTS.
 *
 * If migrate fails, leave RefreshDatabaseState::$migrated = false so the next
 * class can retry — but prefer succeeding once per PHPUnit process.
 */
trait RefreshDatabase
{
    use LaravelRefreshDatabase {
        migrateDatabases as private laravelMigrateDatabasesNotUsed;
    }

    /** @var bool Prevent re-running ~280 Durpalla migrations after a failed migrate. */
    private static bool $migrateFailed = false;

    protected function migrateDatabases(): void
    {
        if (self::$migrateFailed) {
            $this->fail('RefreshDatabase migrate previously failed in this process; fix the migration error instead of remigrating (~280 files) per test.');
        }

        $db = (string) config('database.default');

        try {
            $this->wipeDatabaseSafely($db);
            $this->artisan('migrate:install', ['--database' => $db]);

            $migrate = ['--force' => true, '--database' => $db];
            if ($s = $this->seeder()) {
                $migrate['--seeder'] = $s;
            } elseif ($this->shouldSeed()) {
                $migrate['--seed'] = true;
            }
            $this->artisan('migrate', $migrate);
        } catch (Throwable $e) {
            self::$migrateFailed = true;
            RefreshDatabaseState::$migrated = false;
            throw $e;
        }
    }

    protected function wipeDatabaseSafely(string $connection): void
    {
        $conn = DB::connection($connection);

        if ($conn->getDriverName() === 'mysql') {
            $this->recreateMysqlDatabase($connection);

            return;
        }

        $schema = Schema::connection($connection);
        foreach ($this->listTables($connection) as $table) {
            $schema->dropIfExists($table);
        }

        if ($this->shouldDropViews()) {
            $grammar = $conn->getQueryGrammar();
            foreach ($this->listViews($connection) as $view) {
                $conn->statement('DROP VIEW IF EXISTS '.$grammar->wrap($view));
            }
        }
    }

    /**
     * Fastest MySQL reset: drop and recreate the schema.
     */
    protected function recreateMysqlDatabase(string $connection): void
    {
        $conn = DB::connection($connection);
        $database = (string) $conn->getDatabaseName();
        if ($database === '') {
            throw new \RuntimeException('MySQL test connection has no database name.');
        }

        $charset = $conn->getConfig('charset') ?: 'utf8mb4';
        $collation = $conn->getConfig('collation') ?: 'utf8mb4_unicode_ci';
        $quoted = str_replace('`', '``', $database);

        // Connect to server without selecting apigw_test (use system schema).
        $hostConfig = $conn->getConfig();
        $hostConfig['database'] = 'mysql';
        config(["database.connections.{$connection}_wipe" => $hostConfig]);
        DB::purge("{$connection}_wipe");

        $wipe = DB::connection("{$connection}_wipe");
        $wipe->statement("DROP DATABASE IF EXISTS `{$quoted}`");
        $wipe->statement("CREATE DATABASE `{$quoted}` CHARACTER SET {$charset} COLLATE {$collation}");
        $wipe->disconnect();
        DB::purge("{$connection}_wipe");

        DB::purge($connection);
        DB::reconnect($connection);
    }

    /**
     * @return list<string>
     */
    protected function listTables(string $connection): array
    {
        $schema = Schema::connection($connection);

        if (method_exists($schema, 'getTableListing')) {
            return array_values($schema->getTableListing());
        }

        return array_values(array_map(
            static fn ($table) => is_object($table) ? ($table->name ?? (string) $table) : (string) $table,
            $schema->getAllTables()
        ));
    }

    /**
     * @return list<string>
     */
    protected function listViews(string $connection): array
    {
        $schema = Schema::connection($connection);
        if (! method_exists($schema, 'getViews')) {
            return [];
        }

        $views = [];
        foreach ($schema->getViews() as $view) {
            $name = is_object($view) ? ($view->name ?? null) : ($view['name'] ?? null);
            if (is_string($name) && $name !== '') {
                $views[] = $name;
            }
        }

        return $views;
    }
}
