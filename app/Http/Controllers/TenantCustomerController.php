<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantCustomerRequest;
use App\Http\Requests\TenantCustomerSaveRequest;
use App\Http\Resources\TenantCustomerResource;
use App\Models\Tenant\Customer;
use App\Repositories\TenantCustomerRepository;
use App\Repositories\TenantRoleRepository;
use App\Services\TenantCustomerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantCustomerController extends Controller
{
    /**
     * Initialize customer persistence and permission dependencies.
     *
     * @param  TenantCustomerRepository  $customers  The customers for this action.
     * @param  TenantCustomerService  $service  The service for this action.
     * @param  TenantRoleRepository  $roles  The roles for this action.
     */
    public function __construct(private readonly TenantCustomerRepository $customers, private readonly TenantCustomerService $service, private readonly TenantRoleRepository $roles) {}

    /**
     * List authorized tenant customers with server-side filters and pagination.
     *
     * @param  TenantCustomerRequest  $request  The request for this action.
     * @return View|JsonResponse The action result.
     */
    public function index(TenantCustomerRequest $request): View|JsonResponse
    {
        $records = $this->customers->paginate($request->validated());
        if ($request->is('api/*')) {
            $data = $records->through(fn(Customer $customer) => (new TenantCustomerResource($customer))->resolve($request));

            return response()->json($data);
        }
        $data = $this->viewData($request);
        $data['customers'] = $records;

        return view('tenant.customers.index', $data);
    }

    /**
     * Show the customer creation form.
     *
     * @param  TenantCustomerRequest  $request  The request for this action.
     * @return View The action result.
     */
    public function create(TenantCustomerRequest $request): View
    {
        $data = $this->viewData($request);
        $data['customer'] = new Customer;

        return view('tenant.customers.form', $data);
    }

    /**
     * Display the selected tenant customer and their addresses.
     *
     * @param  TenantCustomerRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return View|JsonResponse The action result.
     */
    public function show(TenantCustomerRequest $request, int $customer): View|JsonResponse
    {
        $record = $this->customers->details($customer);
        if ($request->is('api/*')) {
            $data = ['data' => new TenantCustomerResource($record)];

            return response()->json($data);
        }
        $data = $this->viewData($request);
        $data['customer'] = $record;

        return view('tenant.customers.show', $data);
    }

    /**
     * Show the customer edit form.
     *
     * @param  TenantCustomerRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return View The action result.
     */
    public function edit(TenantCustomerRequest $request, int $customer): View
    {
        $data = $this->viewData($request);
        $data['customer'] = $this->customers->find($customer);

        return view('tenant.customers.form', $data);
    }

    /**
     * Create a customer using only validated fields.
     *
     * @param  TenantCustomerSaveRequest  $request  The request for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    public function store(TenantCustomerSaveRequest $request): RedirectResponse|JsonResponse
    {
        $customer = $this->service->save($request->validated());

        return $this->saved($request, $customer, 'Customer created successfully.', 201);
    }

    /**
     * Preserve omitted values while updating a customer.
     *
     * @param  TenantCustomerSaveRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    public function update(TenantCustomerSaveRequest $request, int $customer): RedirectResponse|JsonResponse
    {
        $record = $this->service->save($request->validated(), $customer);

        return $this->saved($request, $record, 'Customer updated successfully.');
    }

    /**
     * Activate or deactivate an existing customer.
     *
     * @param  TenantCustomerRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    public function status(TenantCustomerRequest $request, int $customer): RedirectResponse|JsonResponse
    {
        $record = $this->service->save($request->validated(), $customer);

        return $this->saved($request, $record, 'Customer status updated.');
    }

    /**
     * Soft delete a customer while retaining addresses and related history.
     *
     * @param  TenantCustomerRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    public function destroy(TenantCustomerRequest $request, int $customer): RedirectResponse|JsonResponse
    {
        $this->service->delete($customer);
        $data = ['message' => 'Customer deleted successfully.'];

        return $request->is('api/*') ? response()->json($data) : redirect()->route('tenant.customers.index')->with('success', $data['message']);
    }

    /**
     * Build shared layout context and UI capabilities for an authorized admin.
     *
     * @param  TenantCustomerRequest  $request  The request for this action.
     * @return array The action result.
     */
    private function viewData(TenantCustomerRequest $request): array
    {
        $data = ['tenantUser' => $request->user('tenant'), 'tenantDomain' => session('tenant_domain')];
        foreach (['view', 'create', 'update', 'delete', 'addresses'] as $permission) {
            $data['can' . ucfirst($permission)] = $this->roles->userHasPermission($request->user('tenant'), 'customers.' . $permission);
        }

        return $data;
    }

    /**
     * Return an admin resource for APIs or the existing success notification for forms.
     *
     * @param  TenantCustomerRequest  $request  The request for this action.
     * @param  Customer  $customer  The customer for this action.
     * @param  string  $message  The message for this action.
     * @param  int  $status  The status for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    private function saved(TenantCustomerRequest $request, Customer $customer, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        $data = ['message' => $message, 'data' => new TenantCustomerResource($customer)];

        return $request->is('api/*') ? response()->json($data, $status) : redirect()->route('tenant.customers.index')->with('success', $message);
    }
}
