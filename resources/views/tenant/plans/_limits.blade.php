<dl class="row mb-0">
    @foreach (\App\Services\PackagePricingService::LIMITS as $key => $label)
        @php($limit = $package->limits->firstWhere('limit_key', $key))
        <dt class="col-7 small">{{ $label }}</dt>
        <dd class="col-5 small">
            {{ $limit ? ($limit->limit_value === null ? 'Unlimited' : number_format($limit->limit_value)) : 'Not configured' }}
        </dd>
    @endforeach
</dl>
