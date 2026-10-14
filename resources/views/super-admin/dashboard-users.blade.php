<svg class="commerce-users-timeline" viewBox="0 0 480 {{ $chart['year'] ? 185 : 170 }}" role="img"
    aria-labelledby="users-timeline-title users-timeline-description">
    <title id="users-timeline-title">{{ $chart['title'] }}</title>
    <desc id="users-timeline-description">{{ $chart['description'] }}</desc>
    <defs>
        <linearGradient id="commerce-users-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#59adff" stop-opacity="0.22" />
            <stop offset="100%" stop-color="#59adff" stop-opacity="0.04" />
        </linearGradient>
    </defs>
    <g class="commerce-timeline-grid" aria-hidden="true">
        <path
            d="M40 15H460 M40 45H460 M40 75H460 M40 105H460 M40 135H460 M40 15V135 M110 15V135 M180 15V135 M250 15V135 M320 15V135 M390 15V135 M460 15V135" />
    </g>
    <path d="{{ $chart['area'] }}" fill="url(#commerce-users-fill)" />
    <path d="{{ $chart['path'] }}" fill="none" stroke="#0866ff" stroke-width="2" />
    <g fill="#0866ff">
        @foreach ($chart['points'] as $point)
            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="{{ count($chart['points']) > 7 ? 2 : 4 }}">
                <title>{{ $point['label'] }}: {{ $point['value'] }} users</title>
            </circle>
        @endforeach
    </g>
    <g class="commerce-timeline-labels" aria-hidden="true">
        <g text-anchor="end">
            @foreach ($chart['axis'] as $value)
                <text x="25" y="{{ 19 + $loop->index * 30 }}">{{ $value }}</text>
            @endforeach
        </g>
        <g text-anchor="middle">
            @foreach ($chart['points'] as $point)
                @if ($point['show_label'])
                    <text x="{{ $point['x'] }}" y="160">
                        <tspan x="{{ $point['x'] }}">{{ $point['axis_label'] }}</tspan>
                        @if ($chart['year'])
                            <tspan x="{{ $point['x'] }}" dy="14">{{ $chart['year'] }}</tspan>
                        @endif
                    </text>
                @endif
            @endforeach
        </g>
    </g>
</svg>
