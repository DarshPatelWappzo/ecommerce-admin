<?php

namespace App\Services;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\Order;
use App\Models\Tenant\User;
use App\Repositories\TenantOrderRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class TenantInvoiceService
{
    public function __construct(
        private readonly TenantOrderRepository $orders,
        private readonly TenantInvoiceSnapshotBuilder $snapshots,
        private readonly TenantInvoiceNumberService $numbers,
        private readonly TenantAuditLogService $audit,
    ) {}

    public function prepare(int $orderId): Invoice
    {
        $order = $this->orders->find($orderId);
        $this->eligible($order, false);

        return $this->newDraft($order, null, 'manual');
    }

    /** All writes acquire the order lock first, including edits and retries. */
    public function save(int $orderId, array $input, User $actor, ?int $invoiceId = null): Invoice
    {
        return DB::connection('tenant')->transaction(function () use ($orderId, $input, $actor, $invoiceId): Invoice {
            $order = $this->orders->find($orderId, true);
            $this->eligible($order, false);
            $invoice = Invoice::where('order_id', $orderId)->first();
            if ($invoiceId !== null) {
                abort_unless($invoice?->id === $invoiceId, 404);
            } elseif ($invoice) {
                return $invoice->load('items');
            }
            if ($invoice?->status === 'issued') {
                throw ValidationException::withMessages(['invoice' => 'Issued invoices cannot be edited.']);
            }
            $invoice ??= $this->newDraft($order, $actor, 'manual');
            $before = $invoice->exists ? $invoice->only(['invoice_date', 'notes', 'terms', 'customer_name', 'billing']) : null;
            $snapshot = $this->snapshots->build($order);
            if ($invoice->order_fingerprint !== $snapshot['fingerprint']) {
                throw ValidationException::withMessages(['order' => 'The order changed after this draft was prepared. Review and correct the order before invoicing.']);
            }
            $this->applyDetails($invoice, $input, $order);
            $invoice->updated_by = $actor->id;
            $invoice->seller = $this->snapshots->seller();
            $new = ! $invoice->exists;
            $invoice->save();
            if ($new) {
                $this->saveItems($invoice, $snapshot);
            }
            $this->audit->recordSnapshot($invoice, $new ? 'draft_created' : 'draft_updated', $before, $invoice->only(['invoice_date', 'notes', 'terms', 'customer_name', 'billing']), $actor);

            return $invoice->load('items');
        }, 3);
    }

    public function issue(int $orderId, User $actor, string $mode = 'manual'): Invoice
    {
        return DB::connection('tenant')->transaction(function () use ($orderId, $actor, $mode): Invoice {
            $order = $this->orders->find($orderId, true);
            $invoice = Invoice::where('order_id', $orderId)->first();
            if ($invoice?->status === 'issued') {
                return $invoice->load('items');
            }
            $this->eligible($order, true);
            if (($this->snapshots->settings()['require_reviewed_draft'] ?? config('invoices.require_reviewed_draft')) && ! $invoice) {
                throw ValidationException::withMessages(['invoice' => 'Prepare and save a reviewed invoice draft before dispatch approval.']);
            }
            $invoice ??= $this->newDraft($order, $actor, $mode);
            $snapshot = $this->snapshots->build($order);
            if ($invoice->order_fingerprint !== $snapshot['fingerprint']) {
                throw ValidationException::withMessages(['order' => 'The order changed after invoice preparation. Resolve the order snapshot before issuing.']);
            }
            $this->applyDetails($invoice, [], $order);
            if (! $invoice->exists) {
                $invoice->save();
                $this->saveItems($invoice, $snapshot);
            }
            $invoice->fill([...$this->numbers->next($invoice->invoice_date), 'status' => 'issued', 'issued_at' => now(), 'issued_by' => $actor->id, 'updated_by' => $actor->id])->save();
            $order->forceFill(['invoice_error' => null])->save();
            $this->audit->recordSnapshot($invoice, 'issued', null, ['order_id' => $orderId, 'number' => $invoice->number, 'mode' => $mode, 'status' => $invoice->status], $actor);

            return $invoice->load('items');
        }, 3);
    }

    /** Persist approval before attempting issuance, retaining a durable retry marker. */
    public function approveDispatch(int $orderId, User $actor): Order
    {
        DB::connection('tenant')->transaction(function () use ($orderId, $actor): void {
            $order = $this->orders->find($orderId, true);
            $this->eligible($order, true, false);
            if (! $order->dispatch_approved_at) {
                $order->forceFill(['dispatch_approved_at' => now(), 'dispatch_approved_by' => $actor->id, 'invoice_error' => 'Invoice processing pending. Retry dispatch approval if processing is interrupted.'])->save();
                $this->audit->recordSnapshot($order, 'dispatch_approved', null, ['actor_id' => $actor->id], $actor);
            }
            DB::connection('tenant')->afterCommit(fn () => $this->processAutomatic($orderId, $actor));
        }, 3);

        return $this->orders->details($orderId);
    }

    /** Runs synchronously while request middleware still owns the tenant connection. */
    private function processAutomatic(int $orderId, User $actor): void
    {
        try {
            $this->issue($orderId, $actor, 'automatic');
        } catch (Throwable $exception) {
            $message = $exception instanceof ValidationException
                ? implode(' ', Arr::flatten($exception->errors()))
                : 'Invoice processing failed. Retry dispatch approval or issue the draft. Contact an administrator if it persists.';
            if (! $exception instanceof ValidationException) {
                report($exception);
            }
            DB::connection('tenant')->transaction(function () use ($orderId, $message): void {
                $order = $this->orders->find($orderId, true);
                if (! $order->invoice()->where('status', 'issued')->exists()) {
                    $order->forceFill(['invoice_error' => $message])->save();
                    $this->audit->recordSnapshot($order, 'invoice_failed', null, ['error' => $message]);
                }
            });
        }
    }

    public function eligible(Order $order, bool $issuing, bool $requireApproval = true): void
    {
        $statuses = $issuing ? ['confirmed', 'processing', 'shipped', 'delivered'] : ['pending', 'confirmed', 'processing'];
        if (! in_array($order->status, $statuses, true)) {
            throw ValidationException::withMessages(['order' => 'This order is not eligible for invoicing. Confirm it first; draft and cancelled orders cannot be invoiced.']);
        }
        if (! $issuing) {
            return;
        }
        if ($requireApproval && ! $order->dispatch_approved_at) {
            throw ValidationException::withMessages(['order' => 'Explicit dispatch approval is required before issuing an invoice.']);
        }
        if ($order->paymentCheckout()->where('status', 'review')->exists()) {
            throw ValidationException::withMessages(['payment' => 'Resolve the payment review before invoicing.']);
        }
        $captured = TenantOrderCalculationService::money('0');
        foreach ($order->payments()->where('status', 'captured')->get() as $payment) {
            if ($payment->currency !== $order->currency) {
                throw ValidationException::withMessages(['payment' => 'Payment currency does not match the order.']);
            }
            $captured = $captured->plus($payment->amount);
        }
        $method = $order->payments()->orderBy('id')->value('method');
        if ($captured->isGreaterThan($order->grand_total) || ($method !== 'cod' && ($order->payment_status !== 'paid' || ! $captured->isEqualTo($order->grand_total)))) {
            throw ValidationException::withMessages(['payment' => 'Prepaid orders require confirmed full payment; excess captures require review.']);
        }
    }

    private function newDraft(Order $order, ?User $actor, string $mode): Invoice
    {
        $snapshot = $this->snapshots->build($order);

        return new Invoice([
            'order_id' => $order->id,
            'status' => 'draft',
            'mode' => $mode,
            'invoice_date' => today()->toDateString(),
            'seller' => $this->snapshots->seller(),
            'customer' => [...$snapshot['customer'], 'order_number' => $snapshot['order_number']],
            'customer_name' => $order->customer_name,
            'billing' => $snapshot['billing'],
            'shipping' => $snapshot['shipping'],
            'financials' => $snapshot['financials'],
            'order_fingerprint' => $snapshot['fingerprint'],
            'currency' => $order->currency,
            'grand_total' => $order->grand_total,
            'terms' => $this->snapshots->settings()['terms'] ?? null,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    private function saveItems(Invoice $invoice, array $snapshot): void
    {
        foreach ($snapshot['items'] as $item) {
            $invoice->items()->create(['order_item_id' => $item['id'], 'snapshot' => Arr::except($item, ['id'])]);
        }
    }

    private function applyDetails(Invoice $invoice, array $input, Order $order): void
    {
        $date = $input['invoice_date'] ?? $invoice->invoice_date->toDateString();
        Validator::make(['invoice_date' => $date], ['invoice_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$order->order_date->toDateString(), 'before_or_equal:today']])->validate();
        $invoice->invoice_date = $date;
        $billing = $input['billing'] ?? [];
        foreach (['country_code', 'state_code', 'city', 'postal_code'] as $field) {
            if (array_key_exists($field, $billing) && $billing[$field] !== $invoice->billing[$field]) {
                throw ValidationException::withMessages(['billing.'.$field => 'A place-of-supply change requires correction of the order, not the invoice.']);
            }
        }
        if (array_key_exists('gstin', $input) && $input['gstin'] !== ($invoice->customer['gstin'] ?? null)) {
            throw ValidationException::withMessages(['gstin' => 'GSTIN changes require correction of the order before invoicing.']);
        }
        $invoice->billing = array_replace($invoice->billing, Arr::only($billing, ['name', 'phone', 'address_line_1', 'address_line_2']));
        $invoice->fill(Arr::only($input, ['notes', 'terms', 'customer_name']));
        $invoice->customer = array_replace($invoice->customer, ['customer_name' => $invoice->customer_name]);
    }
}
