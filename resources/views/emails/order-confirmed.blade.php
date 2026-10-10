<x-mail::message>
# Order confirmed

Hello {{ $customerName }},

Your order **{{ $orderNumber }}** has been confirmed.

**Order total:** {{ $currency }} {{ $grandTotal }}

Thank you for your order.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
