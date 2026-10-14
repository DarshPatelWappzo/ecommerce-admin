<?php

namespace App\Services;

use App\Repositories\PlatformPaymentHistoryRepository;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformPaymentHistoryService
{
    public function __construct(private readonly PlatformPaymentHistoryRepository $payments) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listing(array $filters): array
    {
        $payments = $this->payments->rows($filters)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
        $payments->through(fn(object $payment): array => $this->present($payment));

        return [
            'payments' => $payments,
            'earnings' => $this->payments->earnings($filters),
            'filters' => $filters,
            'mode' => $filters['mode'] ?? 'test' // instead of 'test' write 'live' on production
        ];
    }

    /** @return array<string, mixed> */
    public function details(int $id): array
    {
        $record = $this->payments->find($id);

        return ['payment' => $this->present($record), 'domains' => $this->payments->domains($record->owner_id)];
    }

    /** Explicit safe fields only; nested snapshots may never serialize arbitrary stored data.
     * @return array<string, mixed>
     */
    public function present(object $record): array
    {
        $decoded = json_decode($record->quote, true);
        $quote = is_array($decoded) ? $decoded : [];
        $snapshot = $this->safeScalars($quote, ['package_id', 'name', 'billing_cycle', 'duration_months', 'amount', 'currency', 'pricing_basis']);
        $snapshot['capacity'] = $this->safeScalars(is_array($quote['capacity'] ?? null) ? $quote['capacity'] : [], ['min_monthly_users', 'max_monthly_users']);
        $snapshot['limits'] = $this->safeScalars(is_array($quote['limits'] ?? null) ? $quote['limits'] : [], array_keys(PackagePricingService::LIMITS));
        $failure = match ($record->failure_code) {
            'resolution_required' => 'Payment received; resolution required',
            'order_rejected' => 'Gateway order creation rejected',
            'confirmation_pending' => 'Awaiting provider confirmation',
            null => 'None recorded',
            default => 'Payment needs review; additional failure information is not displayed',
        };

        return [
            'id' => $record->id,
            'reference' => $record->reference,
            'owner_id' => $record->owner_id,
            'owner_name' => $record->owner_name ?: (trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: 'Unavailable user'),
            'email' => $record->email ?? 'Unavailable',
            'owner_deleted' => $record->owner_deleted_at !== null,
            'tenant' => 'Tenant #' . $record->tenant_database_id . ($record->existing_tenant_id ? '' : ' (unavailable)'),
            'domain' => $record->domain_name ?? 'Unavailable',
            'domain_deleted' => $record->domain_deleted_at !== null,
            'package' => $snapshot['name'] ?? $record->current_package_name ?? 'Unavailable package',
            'package_source' => isset($snapshot['name']) ? 'Purchase snapshot' : 'Current catalog / unavailable snapshot',
            'purchase_type' => 'Not recorded',
            'billing_cycle' => $record->billing_cycle,
            'amount_minor' => (string) $record->amount,
            'currency' => $record->currency,
            'gateway' => $record->gateway,
            'mode' => $record->mode,
            'status' => $record->status,
            'fulfillment' => $record->fulfillment,
            'checkout_state' => $record->checkout_state,
            'checkout_expires_at' => $record->checkout_expires_at,
            'created_at' => $record->created_at,
            'paid_at' => $record->paid_at,
            'date' => $record->status === 'captured' ? $record->paid_at : $record->created_at,
            'date_label' => $record->status === 'captured' ? 'Captured' : 'Attempt created',
            'gateway_order_id' => $record->gateway_order_id,
            'gateway_payment_id' => $record->gateway_payment_id,
            'subscription_id' => $record->subscription_id,
            'starts_at' => $record->start_date,
            'expires_at' => $record->end_date,
            'grace_ends_at' => $record->grace_expires_at,
            'subscription_status' => $record->subscription_status,
            'subscription_deleted' => $record->subscription_deleted_at !== null,
            'failure' => $failure,
            'snapshot' => $snapshot,
        ];
    }

    /** @param array<string, mixed> $values
     * @param  list<string>  $keys
     * @return array<string, scalar|null>
     */
    private function safeScalars(array $values, array $keys): array
    {
        return array_filter(Arr::only($values, $keys), fn(mixed $value): bool => is_scalar($value) || $value === null);
    }

    /** @param array<string, mixed> $filters */
    public function export(array $filters): StreamedResponse
    {
        $query = $this->payments->rows($filters);
        $maxId = (clone $query)->reorder()->max('p.id');
        $query->where('p.id', '<=', $maxId ?? 0);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Reference',
                'Central owner ID',
                'Owner name (current)',
                'Owner email (current)',
                'Tenant',
                'Assigned domain (current)',
                'Package',
                'Package source',
                'Purchase type',
                'Billing cycle',
                'Amount (minor units)',
                'Currency',
                'Gateway',
                'Mode',
                'Financial status',
                'Fulfillment status',
                'Checkout state',
                'Date type',
                'Date',
                'Attempt created',
                'Captured at',
                'Gateway order ID',
                'Gateway payment ID',
                'Subscription ID',
                'Effective start',
                'Expiry',
                'Grace ends',
                'Safe failure information'
            ], ',', '"', '');
            foreach ($query->reorder()->lazyById(500, 'p.id', 'id') as $record) {
                $p = $this->present($record);
                $values = [];
                foreach (
                    [
                        'reference',
                        'owner_id',
                        'owner_name',
                        'email',
                        'tenant',
                        'domain',
                        'package',
                        'package_source',
                        'purchase_type',
                        'billing_cycle',
                        'amount_minor',
                        'currency',
                        'gateway',
                        'mode',
                        'status',
                        'fulfillment',
                        'checkout_state',
                        'date_label',
                        'date',
                        'created_at',
                        'paid_at',
                        'gateway_order_id',
                        'gateway_payment_id',
                        'subscription_id',
                        'starts_at',
                        'expires_at',
                        'grace_ends_at',
                        'failure'
                    ] as $key
                ) {
                    $value = (string) ($p[$key] ?? '');
                    $values[] = preg_match('/^[\\s\\x00-\\x1F]*[=+@-]|^[\\t\\r\\n]/u', $value) ? "'" . $value : $value;
                }
                fputcsv($output, $values, ',', '"', '');
            }
            fclose($output);
        }, 'platform-payments-' . ($filters['mode'] ?? 'test') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']); // instead of 'test' write 'live' on production
    }
}
