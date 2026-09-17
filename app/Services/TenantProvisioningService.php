<?php

namespace App\Services;

use App\Models\Tenant\Role;
use App\Models\Tenant\User as TenantUser;
use App\Models\TenantDatabase;
use App\Models\User;
use App\Models\UserDomain;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        private readonly TenantDatabaseCreator $databaseCreator,
        private readonly TenantConnectionManager $connectionManager,
    ) {}

    public function provision(User $centralUser, UserDomain $domain, string $plainPassword): TenantDatabase
    {
        $databaseName = "tenant_{$centralUser->id}_{$domain->id}";
        $tenantDatabase = TenantDatabase::firstOrCreate([
            'domain_id' => $domain->id,
        ], [
            'user_id' => $centralUser->id,
            'database_name' => $databaseName,
            'status' => 'creating',
        ]);
        $isRetry = $tenantDatabase->status === 'failed';

        try {
            $tenantDatabase->update(['status' => 'creating', 'provisioning_error' => null]);
            $this->databaseCreator->create($tenantDatabase->database_name);
            $tenantDatabase->update(['status' => 'migrating']);
            $this->connectionManager->connect($tenantDatabase);
            $migrationExitCode = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => database_path('migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]);

            if ($migrationExitCode !== 0) {
                throw new \RuntimeException('Tenant migrations failed.');
            }
            $tenantDatabase->update(['status' => 'seeding', 'migrated_at' => now()]);
            Artisan::call('db:seed', ['--class' => TenantDatabaseSeeder::class, '--database' => 'tenant', '--force' => true]);

            $tenantAdmin = TenantUser::firstOrCreate(['email' => $centralUser->email], [
                'first_name' => $centralUser->first_name ?? $centralUser->name,
                'last_name' => $centralUser->last_name,
                'mobile_number' => $centralUser->mobile_number,
                'password' => Hash::make($plainPassword),
                'status' => 'active',
                'is_first_login' => true,
            ]);
            $adminRole = Role::where('slug', 'admin')->firstOrFail();
            $tenantAdmin->roles()->syncWithoutDetaching([$adminRole->id]);
            if ($isRetry && ! $tenantAdmin->wasRecentlyCreated) {
                $tenantAdmin->update([
                    'password' => Hash::make($plainPassword),
                    'is_first_login' => true,
                ]);
            }
            $tenantDatabase->update(['status' => 'active', 'provisioned_at' => now(), 'provisioning_error' => null]);
            if ($tenantAdmin->wasRecentlyCreated || $isRetry) {
                try {
                    Mail::send('emails.tenant-admin-credentials', [
                        'recipientName' => $centralUser->first_name ?? $centralUser->name,
                        'email' => $centralUser->email,
                        'temporaryPassword' => $plainPassword,
                        'domain' => $domain->domain_name,
                    ], function (Message $message) use ($centralUser): void {
                        $message
                            ->to($centralUser->email)
                            ->subject('Your tenant administrator credentials');
                    });
                } catch (Throwable $exception) {
                    Log::error('Tenant administrator credentials email failed', [
                        'central_user_id' => $centralUser->id,
                        'domain_id' => $domain->id,
                        'database_name' => $databaseName,
                        'exception' => $exception,
                    ]);
                }
            }
        } catch (Throwable $exception) {
            $tenantDatabase->update(['status' => 'failed', 'provisioning_error' => 'Tenant provisioning failed: '.class_basename($exception)]);
            Log::error('Tenant provisioning failed', ['central_user_id' => $centralUser->id, 'domain_id' => $domain->id, 'database_name' => $databaseName, 'exception' => $exception]);
        } finally {
            $this->connectionManager->disconnect();
        }

        return $tenantDatabase->refresh();
    }
}
