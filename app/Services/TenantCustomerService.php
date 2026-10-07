<?php

namespace App\Services;

use App\Models\Tenant\Customer;
use App\Repositories\TenantCustomerRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantCustomerService
{
    public function __construct(private readonly TenantCustomerRepository $customers, private readonly TenantCustomerAddressService $addresses, private readonly TenantAuditLogService $audit) {}

    public function save(array $data, ?int $id = null): Customer
    {
        try {
            return DB::connection('tenant')->transaction(function () use ($data, $id): Customer {
                $customer = $id ? $this->customers->find($id, true) : new Customer;
                $before = $id ? $customer->only(['customer_code', 'customer_type', 'status']) : null;
                $address = $data['address'] ?? null;
                unset($data['address']);
                if (! $id) {
                    $customer->customer_code = 'CUS-'.Str::ulid();
                }
                if (($data['customer_type'] ?? $customer->customer_type) === 'individual') {
                    $data['company_name'] = null;
                    $data['gstin'] = null;
                }
                $customer->fill($data);
                $changedFields = array_keys($customer->getDirty());
                if (! $customer->email && ! $customer->phone) {
                    throw ValidationException::withMessages(['email' => 'Provide an email address or mobile number.']);
                }
                if ($customer->phone && ! $customer->phone_country_code) {
                    throw ValidationException::withMessages(['phone_country_code' => 'A calling code is required with a mobile number.']);
                }
                if ($customer->customer_type === 'business' && ! $customer->company_name) {
                    throw ValidationException::withMessages(['company_name' => 'Registered business name is required for Business customers.']);
                }
                if ($customer->isDirty('email')) {
                    $customer->email_verified_at = null;
                }
                if ($customer->isDirty(['phone', 'phone_country_code'])) {
                    $customer->phone_verified_at = null;
                }
                $this->customers->save($customer, $data);
                if (! $id && $address !== null) {
                    $this->addresses->save($customer->id, $address);
                }
                $this->audit->recordSnapshot($customer, $id ? 'updated' : 'created', $before, [
                    ...$customer->only(['customer_code', 'customer_type', 'status']),
                    ...($changedFields !== [] ? ['changed_fields' => $changedFields] : []),
                ]);

                return $this->customers->details($customer->id);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'The email or generated customer code is already in use. Archived customers also reserve their email. Please retry or use another email.']);
        }
    }

    public function delete(int $id): void
    {
        DB::connection('tenant')->transaction(function () use ($id): void {
            $customer = $this->customers->find($id, true);
            $this->customers->delete($customer);
            $this->audit->recordSnapshot($customer, 'deleted', $customer->only(['customer_code', 'status']), null);
        });
    }
}
