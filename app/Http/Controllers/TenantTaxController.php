<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantTaxRequest;
use App\Http\Requests\TenantTaxSaveRequest;
use App\Models\Tenant\Tax;
use App\Repositories\TenantRoleRepository;
use App\Repositories\TenantTaxRepository;
use App\Services\TenantTaxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantTaxController extends Controller
{
    /**
     * Initialize the tax module dependencies.
     *
     * @param  TenantTaxRepository  $taxes  The tenant tax repository.
     * @param  TenantTaxService  $service  The tax persistence service.
     * @param  TenantRoleRepository  $roles  The tenant permission repository.
     */
    public function __construct(
        private readonly TenantTaxRepository $taxes,
        private readonly TenantTaxService $service,
        private readonly TenantRoleRepository $roles,
    ) {}

    /**
     * List taxes with search and status filters.
     *
     * @param  TenantTaxRequest  $request  The authorized filter request.
     * @return View The tax list.
     */
    public function index(TenantTaxRequest $request): View
    {
        $data = $this->viewData($request);
        $data['taxes'] = $this->taxes->paginate($request->validated());
        $data['canCreate'] = $this->roles->userHasPermission($request->user('tenant'), 'taxes.create');
        $data['canEdit'] = $this->roles->userHasPermission($request->user('tenant'), 'taxes.update');

        return view('tenant.taxes.index', $data);
    }

    /**
     * Show a new tax form.
     *
     * @param  TenantTaxRequest  $request  The authorized request.
     * @return View The creation page.
     */
    public function create(TenantTaxRequest $request): View
    {
        $data = $this->viewData($request);
        $data['tax'] = new Tax;

        return view('tenant.taxes.create', $data);
    }

    /**
     * Show the current tenant's tax for editing.
     *
     * @param  TenantTaxRequest  $request  The authorized request.
     * @param  Tax  $tax  The tenant-bound tax.
     * @return View The edit page.
     */
    public function edit(TenantTaxRequest $request, Tax $tax): View
    {
        $data = $this->viewData($request);
        $data['tax'] = $tax;

        return view('tenant.taxes.edit', $data);
    }

    /**
     * Save a validated new tax.
     *
     * @param  TenantTaxSaveRequest  $request  The validated input.
     * @return RedirectResponse|JsonResponse The success notification.
     */
    public function store(TenantTaxSaveRequest $request): RedirectResponse|JsonResponse
    {
        $this->service->save($request->validated());

        return $this->saved($request, 'Tax created successfully.', 201);
    }

    /**
     * Update only the selected tax master record.
     *
     * @param  TenantTaxSaveRequest  $request  The validated input.
     * @param  Tax  $tax  The tenant-bound tax.
     * @return RedirectResponse|JsonResponse The success notification.
     */
    public function update(TenantTaxSaveRequest $request, Tax $tax): RedirectResponse|JsonResponse
    {
        $this->service->save($request->validated(), $tax);

        return $this->saved($request, 'Tax updated successfully.');
    }

    /**
     * Provide the shared tenant page context.
     *
     * @param  TenantTaxRequest  $request  The authorized tenant request.
     * @return array<string, mixed> The layout data.
     */
    private function viewData(TenantTaxRequest $request): array
    {
        $data = [];
        $data['tenantUser'] = $request->user('tenant');
        $data['tenantDomain'] = session('tenant_domain');

        return $data;
    }

    /**
     * Return the same notification for AJAX and standard forms.
     *
     * @param  TenantTaxSaveRequest  $request  The submitted request.
     * @param  string  $message  The success message.
     * @param  int  $status  The JSON response status.
     * @return RedirectResponse|JsonResponse The response for the submission.
     */
    private function saved(TenantTaxSaveRequest $request, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            $request->session()->flash('success', $message);
            $data = [];
            $data['message'] = $message;
            $data['redirect'] = route('tenant.taxes.index');

            return response()->json($data, $status);
        }

        return redirect()->route('tenant.taxes.index')->with('success', $message);
    }
}
