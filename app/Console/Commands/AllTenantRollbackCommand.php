<?php

namespace App\Console\Commands;

use App\Models\TenantDatabase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

#[Signature('tenant:rollback-all {--step=1 : Number of completed migrations to undo per tenant} {--pretend : Show SQL without changing databases} {--force : Run without confirmation in production}')]
#[Description('Roll back completed migrations for every tenant database')]
class AllTenantRollbackCommand extends Command
{
    use ConfirmableTrait;

    public function handle(): int
    {
        $step = filter_var($this->option('step'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($step === false) {
            $this->error('The --step option must be a positive integer.');

            return self::INVALID;
        }
        $databases = TenantDatabase::orderBy('id')->pluck('database_name');
        if ($databases->isEmpty()) {
            $this->info('No tenant databases found.');

            return self::SUCCESS;
        }
        if (! $this->option('pretend') && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $hasFailures = false;
        foreach ($databases as $database) {
            $exitCode = $this->call('tenant:rollback', [
                'database' => $database,
                '--step' => $step,
                '--pretend' => (bool) $this->option('pretend'),
                '--force' => true,
            ]);
            if ($exitCode !== self::SUCCESS) {
                $hasFailures = true;
            }
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
