<h3 class="h6 mt-3">Payment timeline</h3>
@if ($checkout?->status === 'review')
    <div class="alert alert-danger">Payment needs manual review: an unexpected or duplicate capture was recorded.
        Fulfilment is blocked until this is resolved. No refund has been issued.</div>
@endif
<ul class="list-group list-group-flush">
    @foreach ($payments as $attempt)
        <li class="list-group-item px-0">
            <strong>Attempt #{{ $attempt->id }} · {{ ucfirst($attempt->method) }}</strong>
            <div>Created {{ $attempt->created_at->format('d M Y H:i:s') }}</div>
            @foreach (['authorized_at' => 'Authorized', 'failed_at' => 'Failed', 'paid_at' => 'Collected / captured', 'verified_at' => 'Last verified'] as $field => $label)
                @if ($attempt->{$field})
                    <div>{{ $label }} {{ $attempt->{$field}->format('d M Y H:i:s') }}</div>
                @endif
            @endforeach
            @if ($attempt->failure_message)
                <div class="text-danger">{{ $attempt->failure_message }}</div>
            @endif
        </li>
    @endforeach
    @if ($checkout)
        <li class="list-group-item px-0">Online checkout requested {{ $checkout->requested_at?->format('d M Y H:i:s') }}
            · {{ $checkout->gateway_order_id ?: 'Reconciliation pending' }}
            <div class="text-muted text-break">Checkout reference: {{ $checkout->reference }}</div>
        </li>
        @foreach ($checkout->events as $event)
            <li class="list-group-item px-0">{{ $event->processed_at->format('d M Y H:i:s') }} · {{ $event->type }} ·
                {{ $event->gateway_payment_id }}</li>
        @endforeach
    @endif
</ul>
