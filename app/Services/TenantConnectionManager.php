<?php

namespace App\Services;

use App\Models\TenantDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TenantConnectionManager
{
    public function connect(TenantDatabase $tenantDatabase): void
    {
        $this->connectByDatabaseName($tenantDatabase->database_name);
    }

    public function connectByDatabaseName(string $databaseName): void
    {
        if (! preg_match('/^tenant_[0-9]+_[0-9]+$/', $databaseName)) {
            throw new InvalidArgumentException('Invalid tenant database name.');
        }

        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant'); // previous connection is removed from the connection pool(complete removed from memory)
        DB::reconnect('tenant');
    }

    public function disconnect(): void
    {
        DB::disconnect('tenant');
        DB::purge('tenant');
        Config::set('database.connections.tenant.database', null);
    }

    public function currentDatabase(): ?string
    {
        return Config::get('database.connections.tenant.database');
    }
}
