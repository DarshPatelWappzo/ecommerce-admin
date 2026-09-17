<?php

namespace App\Console\Commands;

use App\Models\TenantDatabase;
use App\Services\TenantConnectionManager;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

#[Signature('tenant:seed {user_id}')]
#[Description('Seed tenant roles and permissions for all domains owned by a central user')]
class TenantSeedCommand extends Command
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
        foreach (TenantDatabase::where('user_id', $this->argument('user_id'))->get() as $tenantDatabase) {
            try {
                $this->connectionManager->connect($tenantDatabase);
                Artisan::call('db:seed', ['--class' => TenantDatabaseSeeder::class, '--database' => 'tenant', '--force' => true]);
                $tenantDatabase->update(['status' => 'seeding']);
                $this->info($tenantDatabase->database_name.' seeded.');
            } finally {
                $this->connectionManager->disconnect();
            }
        }

        return self::SUCCESS;
    }
}
