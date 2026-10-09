<?php

namespace App\Services;

use App\Mail\CustomerLoginCode;
use App\Models\Tenant\Customer;
use App\Models\TenantDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CustomerOtpService
{
    public function request(string $email, int $tenantId): void
    {
        $key = 'customer-otp:' . $tenantId . ':' . hash('sha256', $email);
        if (! RateLimiter::attempt($key, 3, fn() => true, 600)) {
            throw ValidationException::withMessages(['email' => 'Please wait before requesting another sign-in code.']);
        }
        $customer = Customer::where('email', $email)->where('status', 'active')->first();
        if (! $customer) {
            return;
        }
        $code = (string) random_int(100000, 999999);
        DB::connection('tenant')->transaction(function () use ($customer, $code): void {
            Customer::lockForUpdate()->findOrFail($customer->id);
            DB::connection('tenant')
                ->table('customer_login_codes')
                ->updateOrInsert(
                    ['customer_id' => $customer->id],
                    [
                        'code_hash' => Hash::make($code),
                        'attempts' => 0,
                        'expires_at' => now()->addMinutes(10),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
        }, 3);
        Mail::to($email)->send(new CustomerLoginCode($code));
    }

    public function verify(string $email, string $code, TenantDatabase $tenant): string
    {
        $customer = DB::connection('tenant')->transaction(function () use ($email, $code): ?Customer {
            $customer = Customer::where('email', $email)->where('status', 'active')->lockForUpdate()->first();
            if (! $customer) {
                return null;
            }
            $row = DB::connection('tenant')->table('customer_login_codes')->where('customer_id', $customer->id)->lockForUpdate()->first();
            if (! $row || $row->attempts >= 5 || now()->gte(Carbon::parse($row->expires_at))) {
                return null;
            }
            DB::connection('tenant')->table('customer_login_codes')->where('id', $row->id)->increment('attempts');
            if (! Hash::check($code, $row->code_hash)) {
                return null;
            }
            DB::connection('tenant')->table('customer_login_codes')->where('id', $row->id)->delete();

            return $customer;
        }, 3);
        if (! $customer) {
            throw ValidationException::withMessages(['code' => 'The sign-in code is invalid or expired.']);
        }

        return $customer->createCustomerToken($tenant)->plainTextToken;
    }
}
