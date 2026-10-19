<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformPaymentHistoryRequest;
use App\Services\PlatformPaymentHistoryService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentHistoryController extends Controller
{
    public function __construct(private readonly PlatformPaymentHistoryService $history) {}

    /** Display central payment reporting without reconciling or changing financial state. */
    public function index(PlatformPaymentHistoryRequest $request): View
    {
        $data = $this->history->listing($request->validated());

        return view('super-admin.payment-history.index', $data);
    }

    /** Show only allowlisted payment and identity information. */
    public function show(PlatformPaymentHistoryRequest $request, int $payment): View
    {
        $data = $this->history->details($payment);

        return view('super-admin.payment-history.show', $data);
    }

    /** Download the authorized, filtered ledger as an Excel workbook. */
    public function export(PlatformPaymentHistoryRequest $request): BinaryFileResponse
    {
        return $this->history->export($request->validated());
    }
}
