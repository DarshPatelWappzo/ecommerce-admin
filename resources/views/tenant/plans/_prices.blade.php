<div aria-live="polite">
    @foreach (\App\Services\PackagePricingService::CYCLES as $cycle => $months)
        @php($price = $package->prices->firstWhere('billing_cycle', $cycle))
        <div data-cycle-price="{{ $cycle }}" @if ($cycle !== 'monthly') hidden @endif>
            @if ($price)
                <p class="h4 mb-1">{{ $price->currency }} {{ $price->price }}</p>
                <p class="text-muted">per {{ $months }} {{ $months === 1 ? 'month' : 'months' }}</p>
            @else
                <p class="text-muted">{{ ucfirst($cycle) }} pricing unavailable</p>
            @endif
        </div>
    @endforeach
</div>
