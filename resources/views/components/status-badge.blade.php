@props(['status'])
@php
    $tone = match ($status) {
        'active', 'paid', 'captured', 'delivered', 'completed', 'qc_passed', 'inspection_passed', 'issued' => 'success',
        'pending', 'requested', 'unpaid', 'partially_paid', 'refund_pending', 'out_of_stock', 'scheduled' => 'warning',
        'failed', 'qc_failed', 'inspection_failed', 'refund_failed', 'rejected', 'cancelled' => 'danger',
        'approved',
        'authorized',
        'confirmed',
        'processing',
        'replacement_processing',
        'refund_processing',
        'shipped',
        'in_transit',
        'picked_up',
        'received',
        'refunded',
        'converted_to_refund'
            => 'info',
        default => 'secondary',
    };
@endphp
<span
    {{ $attributes->class(['badge', 'text-bg-' . $tone]) }}>{{ $slot->isEmpty() ? \Illuminate\Support\Str::headline($status) : $slot }}</span>
