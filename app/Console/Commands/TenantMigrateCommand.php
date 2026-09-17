<?php

namespace App\Console\Commands;

use App\Models\TenantDatabase;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

#[Signature('tenant:migrate {user_id}')]
#[Description('Run tenant migrations for all domains owned by a central user')]
class TenantMigrateCommand extends Command
{
    public function __construct(private readonly TenantConnectionManager $connectionManager)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantDatabases = TenantDatabase::where('user_id', $this->argument('user_id'))->get();
        foreach ($tenantDatabases as $tenantDatabase) {
            try {
                $this->connectionManager->connect($tenantDatabase);
                $exitCode = Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--realpath' => true, '--force' => true]);
                if ($exitCode !== 0) {
                    $this->error($tenantDatabase->database_name.' migration failed.');

                    return self::FAILURE;
                }
                $tenantDatabase->update(['status' => 'migrating', 'migrated_at' => now()]);
                $this->info($tenantDatabase->database_name.' migrated.');
            } finally {
                $this->connectionManager->disconnect();
            }
        }

        return self::SUCCESS;
    }
}
