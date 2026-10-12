<section class="mb-4">
    <h2 class="h5">Subscription Pricing</h2>
    <p class="text-muted">Customer Subscription Price is independent of Estimated Infrastructure Cost. Purchasing is not yet available.</p>
    <div class="mb-3">
        <label class="form-label" for="subscription_currency">Subscription currency</label>
        <select class="form-select" id="subscription_currency" name="subscription_currency">
            <option value="{{ config('subscriptions.currency') }}">{{ config('subscriptions.currency') }}</option>
        </select>
        <span class="field-error" data-error-for="subscription_currency">@error('subscription_currency') {{ $message }} @enderror</span>
    </div>
    <div class="row g-3">
        @foreach (\App\Services\PackagePricingService::CYCLES as $cycle => $months)
            @php($price = $package?->prices->first(fn ($price) => $price->billing_cycle === $cycle && $price->currency === config('subscriptions.currency')))
            <div class="col-md-4">
                <label class="form-label" for="price_{{ $cycle }}">{{ ucfirst($cycle) }} Price ({{ $months }} {{ $months === 1 ? 'month' : 'months' }})</label>
                <input class="form-control" id="price_{{ $cycle }}" name="subscription_prices[{{ $cycle }}][price]" inputmode="decimal" value="{{ old('subscription_prices.'.$cycle.'.price', $price?->price) }}">
                <span class="field-error" data-error-for="subscription_prices.{{ $cycle }}.price">@error('subscription_prices.'.$cycle.'.price') {{ $message }} @enderror</span>
                <input type="hidden" name="subscription_prices[{{ $cycle }}][is_active]" value="0">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="enabled_{{ $cycle }}" name="subscription_prices[{{ $cycle }}][is_active]" value="1" @checked(old('subscription_prices.'.$cycle.'.is_active', $price?->is_active ?? false))>
                    <label class="form-check-label" for="enabled_{{ $cycle }}">Enable {{ $cycle }}</label>
                </div>
                <span class="field-error" data-error-for="subscription_prices.{{ $cycle }}.is_active">@error('subscription_prices.'.$cycle.'.is_active') {{ $message }} @enderror</span>
            </div>
        @endforeach
    </div>
    <h2 class="h5 mt-4">Package Limits</h2>
    <p class="text-muted">Configuration only; these values are not enforced. Blank means unlimited. Products and customers remain unlimited.</p>
    <div class="row g-3">
        @foreach (\App\Services\PackagePricingService::LIMITS as $key => $label)
            <div class="col-md-4">
                <label class="form-label" for="limit_{{ $key }}">{{ $label }}</label>
                <input class="form-control" id="limit_{{ $key }}" name="package_limits[{{ $key }}]" inputmode="numeric" value="{{ old('package_limits.'.$key, $package?->limits->firstWhere('limit_key', $key)?->limit_value) }}">
                <span class="field-error" data-error-for="package_limits.{{ $key }}">@error('package_limits.'.$key) {{ $message }} @enderror</span>
            </div>
        @endforeach
    </div>
    @foreach (['is_tenant_visible' => 'Show this package in the tenant catalog', 'public_infrastructure' => 'Publish infrastructure specifications and summary as planning estimates'] as $key => $label)
        <input type="hidden" name="{{ $key }}" value="0">
        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" @checked(old($key, $package?->{$key} ?? ($key === 'is_tenant_visible')))>
            <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
        </div>
        <span class="field-error" data-error-for="{{ $key }}">@error($key) {{ $message }} @enderror</span>
    @endforeach
    <p class="small text-muted mt-2">Package name and description are customer-facing. Internal infrastructure costs and cost disclaimers are never published.</p>
</section>
