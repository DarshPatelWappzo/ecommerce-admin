<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TenantDatabaseCreator
{
    public function databaseExists(string $databaseName): bool
    {
        $this->validateDatabaseName($databaseName);

        return DB::connection('mysql')->table('information_schema.schemata')
            ->where('schema_name', $databaseName)
            ->exists();
    }

    public function create(string $databaseName): void
    {
        $this->validateDatabaseName($databaseName);

        if ($this->databaseExists($databaseName)) {
            return;
        }

        DB::connection('mysql')->statement("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function validateDatabaseName(string $databaseName): void
    {
        if (! preg_match('/^tenant_[0-9]+_[0-9]+$/', $databaseName)) {
            throw new InvalidArgumentException('Invalid tenant database name.');
        }

        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('Tenant provisioning requires the central MySQL connection.');
        }
    }
}
