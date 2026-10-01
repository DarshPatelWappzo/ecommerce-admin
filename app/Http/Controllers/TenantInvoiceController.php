<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantInvoiceRequest;
use App\Http\Requests\TenantInvoiceSaveRequest;
use App\Models\Tenant\Invoice;
use App\Repositories\TenantInvoiceRepository;
use App\Repositories\TenantOrderRepository;
use App\Services\TenantInvoicePdfService;
use App\Services\TenantInvoiceService;
use App\Services\TenantInvoiceSnapshotBuilder;
use App\Services\TenantOrderCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Throwable;

class TenantInvoiceController extends Controller
{
    /**
     * Initialize the shared admin and staff API invoice workflow.
     *
     * @param  TenantInvoiceRepository  $invoices  Tenant-scoped invoice queries.
     * @param  TenantInvoiceService  $service  Shared draft and issuance workflow.
     * @param  TenantOrderRepository  $orders  Saved order access.
     * @param  TenantInvoiceSnapshotBuilder  $snapshots  Snapshot validation and preparation.
     * @param  TenantInvoicePdfService  $pdfs  PDF rendering boundary.
     */
    public function __construct(private readonly TenantInvoiceRepository $invoices, private readonly TenantInvoiceService $service, private readonly TenantOrderRepository $orders, private readonly TenantInvoiceSnapshotBuilder $snapshots, private readonly TenantInvoicePdfService $pdfs) {}

    /**
     * List authorized tenant invoices with filters and pagination.
     *
     * @param  TenantInvoiceRequest  $request  Authorized filter request.
     * @return View|JsonResponse Paginated invoices.
     */
    public function index(TenantInvoiceRequest $request): View|JsonResponse
    {
        $invoices = $this->invoices->paginate($request->validated());
        if ($request->is('api/*')) {
            $data = $invoices;

            return response()->json($data);
        }
        $data = $this->viewData($request);
        $data['invoices'] = $invoices;

        return view('tenant.invoices.index', $data);
    }

    /**
     * Select an order or prefill its immutable financial snapshot.
     *
     * @param  TenantInvoiceRequest  $request  Authorized order selection.
     * @return View Manual preparation form.
     */
    public function create(TenantInvoiceRequest $request): View
    {
        $data = $this->viewData($request);
        $data['candidates'] = $this->invoices->candidates($request->validated('search'));
        $data['invoice'] = $request->filled('order_id') ? $this->service->prepare((int) $request->validated('order_id')) : null;
        $data['items'] = $data['invoice'] ? $this->snapshots->build($this->orders->find($data['invoice']->order_id))['items'] : [];

        return view('tenant.invoices.form', $data);
    }

    /**
     * Show an editable draft; issued documents cannot be edited.
     *
     * @param  TenantInvoiceRequest  $request  Authorized edit request.
     * @param  int  $invoice  Tenant invoice identifier.
     * @return View Draft edit form.
     */
    public function edit(TenantInvoiceRequest $request, int $invoice): View
    {
        $data = $this->viewData($request);
        $data['invoice'] = $this->invoices->find($invoice);
        abort_unless($data['invoice']->status === 'draft', 422, 'Issued invoices cannot be edited.');
        $data['items'] = $data['invoice']->items->pluck('snapshot')->all();

        return view('tenant.invoices.form', $data);
    }

    /**
     * Save a manual draft without assigning a final number.
     *
     * @param  TenantInvoiceSaveRequest  $request  Validated draft details.
     * @return JsonResponse|RedirectResponse Saved draft response.
     */
    public function store(TenantInvoiceSaveRequest $request): JsonResponse|RedirectResponse
    {
        $invoice = $this->service->save((int) $request->validated('order_id'), $request->validated(), $request->actor());

        return $this->respond($request, $invoice, 201);
    }

    /**
     * Apply only permitted nonfinancial changes to a draft.
     *
     * @param  TenantInvoiceSaveRequest  $request  Validated draft details.
     * @param  int  $invoice  Tenant invoice identifier.
     * @return JsonResponse|RedirectResponse Updated draft response.
     */
    public function update(TenantInvoiceSaveRequest $request, int $invoice): JsonResponse|RedirectResponse
    {
        $record = $this->invoices->find($invoice);
        abort_unless($record->order_id === (int) $request->validated('order_id'), 422, 'An invoice cannot be moved to another order.');
        $record = $this->service->save($record->order_id, $request->validated(), $request->actor(), $invoice);

        return $this->respond($request, $record);
    }

    /**
     * Show stored invoice data and separately derived current payment information.
     *
     * @param  TenantInvoiceRequest  $request  Authorized view request.
     * @param  int  $invoice  Tenant invoice identifier.
     * @return View|JsonResponse Invoice detail and live payment summary.
     */
    public function show(TenantInvoiceRequest $request, int $invoice): View|JsonResponse
    {
        $record = $this->invoices->find($invoice);
        $data = $this->viewData($request);
        $data['invoice'] = $record;
        $data['payment'] = $this->payment($record);
        if ($request->is('api/*')) {
            $data = ['data' => $record, 'payment' => $data['payment']];

            return response()->json($data);
        }

        return view('tenant.invoices.show', $data);
    }

    /**
     * Issue or safely return the invoice already issued for this order.
     *
     * @param  TenantInvoiceRequest  $request  Authorized issuance request.
     * @param  int  $invoice  Tenant invoice identifier.
     * @return JsonResponse|RedirectResponse Issued document response.
     */
    public function issue(TenantInvoiceRequest $request, int $invoice): JsonResponse|RedirectResponse
    {
        $record = $this->invoices->find($invoice);

        return $this->respond($request, $this->service->issue($record->order_id, $request->actor()));
    }

    /**
     * Render a print document from saved snapshots.
     *
     * @param  TenantInvoiceRequest  $request  Authorized download request.
     * @param  int  $invoice  Tenant invoice identifier.
     * @return View Printable invoice document.
     */
    public function print(TenantInvoiceRequest $request, int $invoice): View
    {
        $record = $this->invoices->find($invoice);
        abort_unless($record->status === 'issued', 422, 'Only issued invoices can be printed.');
        $data = ['invoice' => $record, 'payment' => $this->payment($record), 'pdf' => false];

        return view('tenant.invoices.print', $data);
    }

    /**
     * Regenerate the PDF without changing issuance or numbering.
     *
     * @param  TenantInvoiceRequest  $request  Authorized download request.
     * @param  int  $invoice  Tenant invoice identifier.
     * @return Response PDF attachment or retryable error.
     */
    public function pdf(TenantInvoiceRequest $request, int $invoice): Response
    {
        $record = $this->invoices->find($invoice);
        abort_unless($record->status === 'issued', 422, 'Only issued invoices can be downloaded.');
        try {
            return $this->pdfs->download($record, $this->payment($record));
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'PDF generation is unavailable. The invoice is preserved; retry download or use the print view.');
        }
    }

    /**
     * Derive live collection information without maintaining a second ledger.
     *
     * @param  Invoice  $invoice  Invoice with loaded order payments.
     * @return array{status: string, received_amount: string, outstanding_amount: string, as_of: string, records: Collection} Current collection information.
     */
    private function payment(Invoice $invoice): array
    {
        $captured = TenantOrderCalculationService::money('0');
        foreach ($invoice->order->payments->where('status', 'captured') as $payment) {
            $captured = $captured->plus($payment->amount);
        }

        return ['status' => $invoice->order->payment_status, 'received_amount' => (string) $captured, 'outstanding_amount' => (string) TenantOrderCalculationService::money($invoice->grand_total)->minus($captured), 'as_of' => now()->toIso8601String(), 'records' => $invoice->order->payments->map(fn($payment) => $payment->only(['method', 'status', 'amount', 'currency', 'reference_number', 'paid_at']))];
    }

    /**
     * Prepare existing layout and per-action capability flags.
     *
     * @param  TenantInvoiceRequest  $request  Authorized staff request.
     * @return array Layout data and permission flags.
     */
    private function viewData(TenantInvoiceRequest $request): array
    {
        $data = ['tenantUser' => $request->actor(), 'tenantDomain' => session('tenant_domain')];
        foreach (['view', 'create', 'update', 'issue', 'download'] as $action) {
            $data['permissions'][$action] = $request->allowed($action);
        }

        return $data;
    }

    /**
     * Preserve JSON envelopes and browser success notifications.
     *
     * @param  TenantInvoiceRequest  $request  Authorized invoice request.
     * @param  Invoice  $invoice  Saved document.
     * @param  int  $status  HTTP response code.
     * @return JsonResponse|RedirectResponse API response or browser redirect.
     */
    private function respond(TenantInvoiceRequest $request, Invoice $invoice, int $status = 200): JsonResponse|RedirectResponse
    {
        $data = ['data' => $invoice, 'message' => $invoice->status === 'issued' ? 'Invoice issued.' : 'Invoice draft saved.'];
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json($data, $status);
        }

        return redirect()->route('tenant.invoices.show', $invoice)->with('success', $data['message']);
    }
}
