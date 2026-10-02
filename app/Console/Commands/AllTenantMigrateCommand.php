<?php

namespace App\Console\Commands;

use App\Models\TenantDatabase;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

#[Signature('tenant:migrate-all')]
#[Description('Run pending tenant migrations for every tenant database')]
class AllTenantMigrateCommand extends Command
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
        $processedTenantCount = 0;
        $hasFailures = false;

        foreach (TenantDatabase::query()->lazyById() as $tenantDatabase) {
            $processedTenantCount++;

            try {
                $this->connectionManager->connect($tenantDatabase);
                $exitCode = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);

                if ($exitCode !== 0) {
                    $hasFailures = true;
                    $this->error($tenantDatabase->database_name.' migration failed.');

                    continue;
                }

                $tenantDatabase->update(['migrated_at' => now()]);
                $this->info($tenantDatabase->database_name.' migrated.');
            } catch (Throwable $exception) {
                $hasFailures = true;
                $this->error($tenantDatabase->database_name.' migration failed: '.$exception->getMessage());
            } finally {
                $this->connectionManager->disconnect();
            }
        }

        if ($processedTenantCount === 0) {
            $this->info('No tenant databases found.');
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
