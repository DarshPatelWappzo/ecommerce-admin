<?php

namespace App\Repositories;

use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use Illuminate\Database\Eloquent\Collection;

class TenantCustomerAddressRepository
{
    public function all(Customer $customer): Collection
    {
        return $customer->addresses()->get();
    }

    public function find(Customer $customer, int $id): CustomerAddress
    {
        return $customer->addresses()->findOrFail($id);
    }

    public function save(Customer $customer, array $data, ?CustomerAddress $address = null): CustomerAddress
    {
        $address ??= $customer->addresses()->make();
        $address->fill($data)->save();

        return $address;
    }

    public function clearDefault(Customer $customer, string $field): void
    {
        $customer->addresses()->where($field, true)->update([$field => false]);
    }

    public function delete(CustomerAddress $address): void
    {
        $address->delete();
    }
}
