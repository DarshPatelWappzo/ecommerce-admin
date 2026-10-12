<?php

namespace App\Console\Commands;

use App\Models\TenantDatabase;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Artisan;
use Throwable;

#[Signature('tenant:rollback {database : Exact tenant database name from tenant:list} {--step=1 : Number of completed migrations to undo} {--pretend : Show SQL without changing the database} {--force : Run without confirmation in production}')]
#[Description('Roll back completed migrations for one tenant database')]
class TenantRollbackCommand extends Command
{
    use ConfirmableTrait;

    public function __construct(private readonly TenantConnectionManager $connectionManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $step = filter_var($this->option('step'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($step === false) {
            $this->error('The --step option must be a positive integer.');

            return self::INVALID;
        }
        $tenant = TenantDatabase::where('database_name', $this->argument('database'))->first();
        if (! $tenant) {
            $this->error('Tenant database not found. Use tenant:list for registered database names.');

            return self::FAILURE;
        }
        if (! $this->option('pretend') && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            $this->connectionManager->connect($tenant);
            $this->info($tenant->database_name.($this->option('pretend') ? ' rollback preview:' : ' rollback:'));
            $exitCode = Artisan::call('migrate:rollback', [
                '--database' => 'tenant',
                '--path' => [database_path('migrations/tenant')],
                '--realpath' => true,
                '--step' => $step,
                '--pretend' => (bool) $this->option('pretend'),
                '--force' => true,
            ]);
            $this->output->write(Artisan::output());
            if ($exitCode !== self::SUCCESS) {
                $this->error($tenant->database_name.' rollback failed.');
            }

            return $exitCode;
        } catch (Throwable $exception) {
            $this->error($tenant->database_name.' rollback failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $this->connectionManager->disconnect();
        }
    }
}
