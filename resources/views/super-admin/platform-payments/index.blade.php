@extends('layouts.app')
@section('title', 'Payment Configuration')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header mb-4">
            <h1 class="page-title">Payment Configuration</h1>
        </div>
        <section class="dashboard-card">
            <p>Platform package purchases use Razorpay. Credentials are encrypted centrally. Blank fields preserve the saved
                credentials for the selected environment.</p>
            <form id="platform-gateway-form" method="POST" action="{{ route('super-admin.platform-payments.update') }}"
                novalidate>
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Gateway</label>
                    <input class="form-control" value="Razorpay (razorpay)" disabled>
                </div>
                <div class="mb-3">
                    <label for="mode" class="form-label">Environment to configure and use</label>
                    <select id="mode" name="mode" class="form-select">
                        @foreach (['test', 'live'] as $mode)
                            <option value="{{ $mode }}" @selected(old('mode', $settings['mode']) === $mode)>{{ ucfirst($mode) }}</option>
                        @endforeach
                    </select>
                    <span class="field-error text-danger">
                        @error('mode')
                            {{ $message }}
                        @enderror
                    </span>
                </div>
                @foreach (['key_id' => 'Key ID', 'key_secret' => 'Key secret', 'webhook_secret' => 'Webhook secret (optional)'] as $field => $label)
                    <div class="mb-3">
                        <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="password" class="form-control"
                            autocomplete="new-password" value="">
                        <div class="small text-muted">
                            Test:
                            {{ $settings['environments']['test']['configured'][$field] ? 'Configured' : 'Not configured' }}.
                            Live:
                            {{ $settings['environments']['live']['configured'][$field] ? 'Configured' : 'Not configured' }}.
                        </div>
                        <span class="field-error text-danger">
                            @error($field)
                                {{ $message }}
                            @enderror
                        </span>
                    </div>
                @endforeach
                <input type="hidden" name="is_enabled" value="0">
                <div class="form-check mb-3">
                    <input class="form-check-input" id="is_enabled" name="is_enabled" value="1" type="checkbox"
                        @checked(old('is_enabled', $settings['is_enabled']))>
                    <label class="form-check-label" for="is_enabled">Enable selected environment</label>
                </div>
                <button class="btn btn-primary" type="submit">Save configuration</button>
            </form>
        </section>
        <section class="dashboard-card mt-4">
            <h2 class="h5">Payments requiring resolution</h2>
            <p>These captured payments have not granted a subscription because a newer payment or scheduled term conflicts. Review them manually; no refund is issued automatically.</p>
        <div class="table-responsive">
            <table class="table listing-table align-middle">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Reference</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>Mode</th>
                        <th>Provider payment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resolutionPayments as $payment)
                        <tr>
                            <td>{{ $payment->tenant_database_id }}</td>
                            <td>{{ $payment->reference }}</td>
                            <td>{{ $payment->quote['name'] }}</td>
                            <td>{{ $payment->currency }} {{ number_format($payment->amount / 100, 2) }}</td>
                            <td>{{ $payment->mode }}</td>
                            <td>{{ $payment->gateway_payment_id }}</td>
                        </tr>
                    @empty 
                        <tr>
                            <td colspan="6">No payments require resolution.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $resolutionPayments->links() }}
        <h2 class="h5 mt-4">Webhook endpoints</h2>
            <p>The webhook secret is optional for checkout. Until configured, payments are confirmed through checkout
                verification or reconciliation; webhook requests are not accepted.</p>
            <p>Configure payment.captured, order.paid and payment.failed in the matching Razorpay environment. Keep old
                endpoints and their original secrets for delayed events after credential rotation.</p>
            @forelse ($versions as $version)
                <p class="mb-2">
                    <strong>{{ ucfirst($version->mode) }}
                        version {{ $version->id }}
                    </strong>
                    <br>
                    <code>{{ route('api.platform-payments.webhook', $version->id) }}</code>
                </p>
            @empty
                <p>Save credentials to generate a webhook endpoint.</p>
            @endforelse
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {!! JsValidator::make(
        Illuminate\Support\Arr::except((new \App\Http\Requests\PlatformGatewayRequest())->configurationRules(), [
            'is_enabled',
        ]),
        [],
        [],
        '#platform-gateway-form',
    ) !!}
@endpush
