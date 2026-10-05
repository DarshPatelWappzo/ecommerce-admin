<div class="invoice-document">
    <h1 class="invoice-heading">{{ $invoice->status === 'issued' ? 'INVOICE' : 'DRAFT INVOICE' }}</h1>

    <table class="invoice-meta" role="presentation">
        <tr>
            <td><strong>Invoice ID:</strong> {{ $invoice->number ?? '#' . $invoice->id }}</td>
            <td><strong>Order ID:</strong> {{ $invoice->customer['order_number'] }}</td>
        </tr>
        <tr>
            <td><strong>Invoice Date:</strong> {{ $invoice->invoice_date->format('d-m-Y') }}</td>
            <td><strong>Order Date:</strong> {{ $invoice->order?->order_date?->format('d-m-Y') ?? 'Not recorded' }}</td>
        </tr>
    </table>

    @if ($invoice->order?->status === 'cancelled')
        <p>Order cancelled. This invoice is retained; a cancellation or credit-note workflow is required.</p>
    @endif
    @if (!empty($invoice->seller['name']))
        <p class="invoice-seller">{{ $invoice->seller['name'] }}@if (!empty($invoice->seller['address']))
                — {{ $invoice->seller['address'] }}
            @endif
            @if (!empty($invoice->seller['gstin']))
                | GSTIN: {{ $invoice->seller['gstin'] }}
            @endif
        </p>
    @endif

    <table class="invoice-addresses" role="presentation">
        <thead>
            <tr>
                <th>Bill to</th>
                <th>Ship to</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach (['billing', 'shipping'] as $field)
                    <td>
                        <p>{{ $invoice->$field['name'] }}</p>
                        <p>{{ $invoice->$field['address_line_1'] }}</p>
                        @if (!empty($invoice->$field['address_line_2']))
                            <p>{{ $invoice->$field['address_line_2'] }}</p>
                        @endif
                        <p>{{ $invoice->$field['postal_code'] }} {{ $invoice->$field['city'] }}</p>
                        <p>{{ $invoice->$field['state_name'] }}, {{ $invoice->$field['country_code'] }}</p>
                        <p>Contact: {{ $invoice->$field['phone'] }}</p>
                        @if ($field === 'billing' && !empty($invoice->customer['gstin']))
                            <p>GSTIN: {{ $invoice->customer['gstin'] }}</p>
                        @endif
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <table class="invoice-methods" role="presentation">
        <thead>
            <tr>
                <th>Payment Method</th>
                <th>Shipping Method</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ collect($payment['records'])->pluck('method')->unique()->map(fn($method) => $method === 'cod' ? 'Cash On Delivery' : ucwords(str_replace('_', ' ', $method)))->implode(', ') ?: 'Not recorded' }}
                </td>
                <td>Not recorded</td>
            </tr>
        </tbody>
    </table>

    <table class="invoice-products">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product Name</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $line)
                @php($item = $line->snapshot)
                <tr>
                    <td>{{ $item['sku'] ?? '—' }}</td>
                    <td>
                        <p>{{ $item['product_name'] }}</p>
                        @if (!empty($item['variant_name']))
                            <p>{{ $item['variant_name'] }}</p>
                        @endif
                        <p class="invoice-line-detail">
                            @if (!empty($item['hsn_code']))
                                HSN: {{ $item['hsn_code'] }} ·
                            @endif
                            {{ $item['tax_name'] }} {{ $item['tax_rate'] }}%: {{ $item['tax_amount'] }}
                        </p>
                        @if ($item['discount_amount'] !== '0.00')
                            <p class="invoice-line-detail">Discount: {{ $item['discount_amount'] }} (coupon included:
                                {{ $item['coupon_discount'] }})</p>
                        @endif
                        @foreach (['cgst_amount' => 'CGST', 'sgst_amount' => 'SGST', 'igst_amount' => 'IGST'] as $field => $label)
                            @if (($item[$field] ?? null) !== null)
                                <p class="invoice-line-detail">{{ $label }}: {{ $item[$field] }}</p>
                            @endif
                        @endforeach
                    </td>
                    <td>{{ $invoice->currency === 'INR' ? '₹' : $invoice->currency . ' ' }}{{ $item['unit_price'] }}
                    </td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>{{ $invoice->currency === 'INR' ? '₹' : $invoice->currency . ' ' }}{{ $item['subtotal'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="invoice-summary">
        <tbody>
            @foreach (['subtotal' => 'Subtotal', 'shipping_amount' => 'Shipping Handling', 'tax_total' => 'Tax', 'discount_total' => 'Discount', 'rounding_adjustment' => 'Rounding', 'grand_total' => 'Grand Total'] as $field => $label)
                @if ($field !== 'rounding_adjustment' || $invoice->financials[$field] !== '0.00')
                    <tr class="{{ $field === 'grand_total' ? 'invoice-grand-total' : '' }}">
                        <th>{{ $label }}</th>
                        <td class="invoice-summary-separator">-</td>
                        <td>{{ $invoice->currency === 'INR' ? '₹' : $invoice->currency . ' ' }}{{ $invoice->financials[$field] }}
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if ($invoice->notes)
        <div class="invoice-note">
            <h2>Notes</h2>
            <p class="preserve-lines">{{ $invoice->notes }}</p>
        </div>
    @endif
    @if ($invoice->terms)
        <div class="invoice-note">
            <h2>Terms</h2>
            <p class="preserve-lines">{{ $invoice->terms }}</p>
        </div>
    @endif
    <p class="invoice-payment-status">Current payment: {{ ucwords(str_replace('_', ' ', $payment['status'])) }} ·
        Received: {{ $invoice->currency }} {{ $payment['received_amount'] }} ·
        Outstanding: {{ $invoice->currency }} {{ $payment['outstanding_amount'] }}<br>
        As of {{ $payment['as_of'] }}. Payment information may change; issued invoice totals remain fixed.
    </p>
</div>
