<?php

namespace App\Services;

use App\Http\Resources\CustomerProductResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Wishlist;
use App\Repositories\CustomerShoppingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerWishlistService
{
    public function __construct(private readonly CustomerShoppingRepository $shopping, private readonly CustomerCartService $carts) {}

    public function list(Customer $customer, array $filters): LengthAwarePaginator
    {
        return $this->shopping->wishlist($customer)->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString()->through(fn($entry): array => [
            'id' => $entry->id,
            'product_id' => $entry->product_id,
            'saved_at' => $entry->created_at,
            'product' => $entry->product ? (new CustomerProductResource($entry->product))->resolve() : null,
        ]);
    }

    public function add(Customer $customer, int $productId): void
    {
        $this->shopping->transaction($customer, function () use ($customer, $productId): void {
            $this->shopping->products()->findOrFail($productId);
            Wishlist::firstOrCreate(['customer_id' => $customer->id, 'product_id' => $productId]);
        });
    }

    public function remove(Customer $customer, int $productId): void
    {
        $this->shopping->transaction($customer, fn() => $this->shopping->wishlist($customer)->where('product_id', $productId)->firstOrFail()->delete());
    }

    public function move(Customer $customer, int $productId, array $input): array
    {
        return $this->shopping->transaction($customer, function () use ($customer, $productId, $input): array {
            $entry = $this->shopping->wishlist($customer)->where('product_id', $productId)->firstOrFail();
            $this->carts->add($customer, [...$input, 'product_id' => $productId]);
            $entry->delete();

            return $this->carts->describe($this->carts->active($customer));
        });
    }
}
