<x-mail::message>
# {{ $title }}

Hello {{ $customerName }},

{{ $eventMessage }}

**Order number:** {{ $orderNumber }}

@foreach ($details as $label => $value)
@if ($label === 'Tracking URL')
**{{ $label }}:** <a href="{{ $value }}">{{ $value }}</a>
@else
**{{ $label }}:** {{ $value }}
@endif

@endforeach

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
