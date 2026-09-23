<?php

namespace App\Services;

use App\Models\Tenant\CustomerAddress;
use App\Repositories\TenantCustomerAddressRepository;
use App\Repositories\TenantCustomerRepository;
use Illuminate\Support\Facades\DB;

class TenantCustomerAddressService
{
    public function __construct(private readonly TenantCustomerRepository $customers, private readonly TenantCustomerAddressRepository $addresses, private readonly AuditLogService $audit) {}

    public function save(int $customerId, array $data, ?int $id = null): CustomerAddress
    {
        return DB::connection('tenant')->transaction(function () use ($customerId, $data, $id): CustomerAddress {
            $customer = $this->customers->find($customerId, true);
            $address = $id ? $this->addresses->find($customer, $id) : null;
            $before = $this->addresses->all($customer)->map->only(['id', 'is_default_shipping', 'is_default_billing'])->all();
            if ($before === []) {
                $data['is_default_shipping'] = true;
                $data['is_default_billing'] = true;
            }
            foreach (['is_default_shipping', 'is_default_billing'] as $field) {
                if (! empty($data[$field])) {
                    $this->addresses->clearDefault($customer, $field);
                    if ($address) {
                        $address->refresh();
                    }
                }
            }
            $saved = $this->addresses->save($customer, $data, $address);
            $this->audit->recordSnapshot($saved, $id ? 'updated' : 'created', ['defaults' => $before], [
                'customer_id' => $customerId,
                'changed_fields' => array_keys($data),
                'defaults' => $this->addresses->all($customer)->map->only(['id', 'is_default_shipping', 'is_default_billing'])->all(),
            ]);

            return $saved;
        });
    }

    public function delete(int $customerId, int $id): void
    {
        DB::connection('tenant')->transaction(function () use ($customerId, $id): void {
            $customer = $this->customers->find($customerId, true);
            $address = $this->addresses->find($customer, $id);
            $before = $address->only(['customer_id', 'is_default_shipping', 'is_default_billing']);
            $this->addresses->delete($address);
            $replacement = $this->addresses->all($customer)->first();
            $defaults = [];
            foreach (['is_default_shipping', 'is_default_billing'] as $field) {
                if ($before[$field]) {
                    $defaults[$field] = true;
                }
            }
            if ($replacement && $defaults !== []) {
                $this->addresses->save($customer, $defaults, $replacement);
            }
            $this->audit->recordSnapshot($address, 'deleted', $before, ['replacement_id' => $replacement?->id]);
        });
    }
}
