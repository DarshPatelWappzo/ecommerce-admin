@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])

@section('title', 'Payment Gateway Settings')

@section('content')
    <div class="container-fluid">
        <div class="listing-page-header mb-4">
            <div>
                <p class="text-primary fw-semibold mb-1">Settings</p>
                <h1 class="page-title mb-1">Payment Gateways</h1>
            </div>
        </div>
        <section class="dashboard-card">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-4">
                <h2 class="h5 mb-0">Razorpay Configuration</h2>
                <span
                    class="badge text-bg-{{ $settings['is_enabled'] ? 'success' : 'secondary' }}">{{ $settings['is_enabled'] ? 'Enabled' : 'Disabled' }}</span>
            </div>
            @include('tenant.partials.validation-errors')
            <form id="payment-gateway-form" action="{{ route('tenant.payment-gateways.update') }}" method="POST" novalidate
                autocomplete="off">
                @csrf
                @method('PUT')
                <fieldset @disabled(!$canEdit)>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <input type="hidden" name="is_enabled" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled"
                                    value="1" @checked(old('is_enabled', $settings['is_enabled']))>
                                <label class="form-check-label" for="is_enabled">Enable Razorpay for new payments</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mode">Active payment mode</label>
                            <input type="hidden" name="mode" value="{{ $settings['mode'] }}">
                            <select class="form-select" id="mode" name="mode" data-gateway-configuration
                                @disabled(!old('is_enabled', $settings['is_enabled']))>
                                @foreach (['test', 'live'] as $mode)
                                    <option value="{{ $mode }}" @selected(old('mode', $settings['mode']) === $mode)>{{ ucfirst($mode) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @foreach ($settings['environments'] as $mode => $environment)
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h6">{{ ucfirst($mode) }} Environment</h3>
                                    <p class="small text-muted">
                                        {{ $environment['key_id'] && $environment['key_secret_configured'] ? 'API credentials configured' : 'API credentials incomplete' }}
                                    </p>
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $mode }}_key_id">Key ID</label>
                                        <input class="form-control" id="{{ $mode }}_key_id"
                                            data-gateway-configuration @disabled(!old('is_enabled', $settings['is_enabled']))
                                            name="{{ $mode }}_key_id" value="{{ $environment['key_id'] }}"
                                            maxlength="50">
                                    </div>
                                    @foreach (['key_secret' => 'Key Secret', 'webhook_secret' => 'Webhook Secret'] as $field => $label)
                                        <div class="mb-3">
                                            <label class="form-label"
                                                for="{{ $mode }}_{{ $field }}">{{ $label }}</label>
                                            <input class="form-control" type="password"
                                                data-gateway-configuration @disabled(!old('is_enabled', $settings['is_enabled']))
                                                id="{{ $mode }}_{{ $field }}"
                                                name="{{ $mode }}_{{ $field }}" autocomplete="new-password"
                                                maxlength="255"
                                                placeholder="{{ $environment[$field . '_configured'] ? 'Configured — leave blank to retain' : 'Not configured' }}">
                                            <span class="field-error"
                                                data-error-for="{{ $mode }}_{{ $field }}"></span>
                                        </div>
                                    @endforeach
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            data-gateway-configuration @disabled(!old('is_enabled', $settings['is_enabled']))
                                            id="{{ $mode }}_remove_webhook_secret"
                                            name="{{ $mode }}_remove_webhook_secret" value="1">
                                        <label class="form-check-label"
                                            for="{{ $mode }}_remove_webhook_secret">Remove webhook secret for the
                                            new version</label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-muted small">Blank secret fields retain saved values. Changing credentials creates a new
                        version. Previous payments keep their original version; historical secrets are never displayed.</p>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="bind_legacy" name="bind_legacy" value="1"
                            data-gateway-configuration @disabled(!old('is_enabled', $settings['is_enabled']))>
                        <label class="form-check-label" for="bind_legacy">I confirm the active credentials are the original
                            merchant credentials. Link unassigned legacy payments with this exact saved Key ID.</label>
                    </div>
                    @if ($canEdit)
                        <div class="d-flex flex-wrap justify-content-end gap-2 border-top pt-4">
                            <button type="button" class="btn btn-outline-primary" id="test-gateway"
                                @disabled(!old('is_enabled', $settings['is_enabled']))
                                data-url="{{ route('tenant.payment-gateways.test') }}">Test Saved Connection</button>
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                        <p class="small text-muted text-end mt-2">Save changes before testing. The test checks the selected
                            environment and does not test webhooks.</p>
                    @endif
                </fieldset>
            </form>
        </section>
        <section class="dashboard-card mt-4">
            <h2 class="h5">Webhook URLs</h2>
            <p class="text-muted">Configure these URLs in the matching Razorpay account and environment. Keep historical
                URLs active while old payments or refunds still require events. Signatures remain required.</p>
            @forelse ($settings['webhooks'] as $webhook)
                <label class="form-label" for="webhook-{{ $webhook['version'] }}">{{ ucfirst($webhook['mode']) }} ·
                    Version {{ $webhook['version'] }} · {{ $webhook['current'] ? 'Current' : 'Historical' }}</label>
                <div class="input-group mb-3">
                    <input readonly class="form-control" id="webhook-{{ $webhook['version'] }}"
                        value="{{ $webhook['url'] }}">
                    <button class="btn btn-outline-secondary" type="button"
                        data-copy-webhook="webhook-{{ $webhook['version'] }}">Copy</button>
                </div>
                @if (!$webhook['current'] && $canEdit)
                    <form method="POST" action="{{ route('tenant.payment-gateways.retire', $webhook['version']) }}"
                        data-retire-gateway-version class="mb-4">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove unused version
                            {{ $webhook['version'] }}</button>
                        <span class="small text-muted">Removal is blocked while payments may require this version.</span>
                    </form>
                @endif
            @empty
                <p class="mb-0">Save credentials to generate tenant-specific webhook URLs.</p>
            @endforelse
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {{-- The server validates is_enabled; client required-checkbox validation would reject the valid unchecked state. --}}
    {!! JsValidator::make(
        Illuminate\Support\Arr::except((new \App\Http\Requests\SavePaymentGatewayRequest())->configurationRules(), ['is_enabled']),
        [],
        [],
        '#payment-gateway-form',
    ) 
    !!}
    <script src="{{ asset('js/payment-gateway-settings.js') }}" defer></script>
@endpush
