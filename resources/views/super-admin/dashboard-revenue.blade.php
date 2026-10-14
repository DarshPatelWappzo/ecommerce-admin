<div class="commerce-revenue" role="img" aria-label="{{ $chart['description'] }}">
    <div class="commerce-axis" aria-hidden="true">
        @foreach ($chart['axis'] as $amount)
            <span>{{ $amount }}</span>
        @endforeach
    </div>
    <div class="commerce-bars" aria-hidden="true">
        @foreach ($chart['points'] as $point)
            <div class="commerce-bar-column">
                <div class="commerce-bar" style="height: {{ $point['height'] }}%"
                    title="{{ $point['label'] }}: {{ $point['amount'] }}"></div>
                <span>{{ $point['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>
