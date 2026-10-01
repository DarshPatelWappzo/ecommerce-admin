<div class="table-responsive mt-4">
    <table class="table invoice-items">
        <thead>
            <tr>
                <th>Product / SKU / HSN</th>
                <th>Qty</th>
                <th>Unit price</th>
                <th>Discount</th>
                <th>Taxable</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['product_name'] }}<br>{{ $item['variant_name'] ?? '' }}<br>{{ $item['sku'] ?? '' }} /
                        {{ $item['hsn_code'] ?? 'HSN not recorded' }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>{{ $item['unit_price'] }}</td>
                    <td>{{ $item['discount_amount'] }}@if ($item['coupon_discount'] !== '0.00')
                            <br>Coupon: {{ $item['coupon_discount'] }} (included)
                        @endif
                    </td>
                    <td>{{ $item['taxable_amount'] }}</td>
                    <td>{{ $item['tax_name'] }} {{ $item['tax_rate'] }}%<br>{{ $item['tax_amount'] }}
                        @foreach (['cgst_amount' => 'CGST', 'sgst_amount' => 'SGST', 'igst_amount' => 'IGST'] as $field => $label)
                            @if (($item[$field] ?? null) !== null)
                                <br>{{ $label }} {{ $item[$field] }}
                            @endif
                        @endforeach
                    </td>
                    <td>{{ $item['total_amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<table class="table invoice-totals">
    @foreach (['subtotal' => 'Subtotal', 'discount_total' => 'Discounts (including coupons)', 'shipping_amount' => 'Shipping', 'taxable_amount' => 'Net amount before tax (including shipping)', 'shipping_tax_amount' => 'Shipping tax (included in tax total)', 'tax_total' => 'Tax total', 'rounding_adjustment' => 'Rounding', 'grand_total' => 'Grand total'] as $field => $label)
        <tr>
            <th>{{ $label }}</th>
            <td>{{ $invoice->currency }} {{ $financials[$field] }}</td>
        </tr>
    @endforeach
</table>
@if ($financials['shipping_tax_rate'] !== null)
    <p>Shipping tax: {{ $financials['shipping_tax_name'] }} {{ $financials['shipping_tax_rate'] }}%</p>
@endif
