<?php

namespace App\Services;

use App\Mail\TenantUserCredentials;
use App\Models\Tenant\User;
use App\Repositories\TenantUserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class TenantUserCreationService
{
    public function __construct(private readonly TenantUserRepository $tenantUserRepository) {}

    /**
     * @param  array{first_name: string, last_name: string, email: string, mobile_number: string, status: string, role_id: int|string}  $data
     */
    public function create(array $data, string $domain): User
    {

        $temporaryPassword = Str::ucfirst(Str::lower(Str::password(5, letters: true, numbers: false, symbols: false))).'@'.random_int(100, 999);
        $attributes = [];
        $attributes['first_name'] = trim($data['first_name']);
        $attributes['last_name'] = trim($data['last_name']);
        $attributes['email'] = Str::lower(trim($data['email']));
        $attributes['mobile_number'] = trim($data['mobile_number']);
        $attributes['password'] = Hash::make($temporaryPassword);
        $attributes['status'] = $data['status'];
        $attributes['is_first_login'] = true;

        $user = $this->tenantUserRepository->create($attributes, [$data['role_id']]);

        try {
            Mail::to($user->email)->send(new TenantUserCredentials($user, $temporaryPassword, $domain));
        } catch (Throwable $exception) {
            Log::error('Tenant user credentials email failed', [
                'tenant_user_id' => $user->id,
                'tenant_domain' => $domain,
                'exception' => $exception,
            ]);
        }

        return $user;
    }
}
