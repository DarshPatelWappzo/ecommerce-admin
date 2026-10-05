<?php

namespace App\Services;

use App\Models\Tenant\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class TenantInvoicePdfService
{
    public function download(Invoice $invoice, array $payment): Response
    {
        abort_unless($invoice->status === 'issued', 422, 'Only issued invoices can be downloaded.');
        abort_unless(class_exists(Pdf::class), 503, 'PDF support is not installed. Install barryvdh/laravel-dompdf; the invoice remains safely issued.');

        return Pdf::loadView('tenant.invoices.print', ['invoice' => $invoice, 'payment' => $payment, 'pdf' => true])
            ->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->download(str_replace('/', '-', $invoice->number) . '.pdf');
    }
}
