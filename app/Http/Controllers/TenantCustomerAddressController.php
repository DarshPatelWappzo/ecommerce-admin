<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantCustomerAddressRequest;
use App\Http\Requests\TenantCustomerAddressSaveRequest;
use App\Http\Resources\TenantCustomerAddressResource;
use App\Models\Tenant\CustomerAddress;
use App\Repositories\TenantCustomerAddressRepository;
use App\Repositories\TenantCustomerRepository;
use App\Services\TenantCustomerAddressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantCustomerAddressController extends Controller
{
    /**
     * Initialize address persistence dependencies.
     *
     * @param  TenantCustomerRepository  $customers  The customers for this action.
     * @param  TenantCustomerAddressRepository  $addresses  The addresses for this action.
     * @param  TenantCustomerAddressService  $service  The service for this action.
     */
    public function __construct(private readonly TenantCustomerRepository $customers, private readonly TenantCustomerAddressRepository $addresses, private readonly TenantCustomerAddressService $service) {}

    /**
     * Return addresses belonging only to the authorized tenant customer.
     *
     * @param  TenantCustomerAddressRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return JsonResponse The action result.
     */
    public function index(TenantCustomerAddressRequest $request, int $customer): JsonResponse
    {
        $data = ['data' => TenantCustomerAddressResource::collection($this->addresses->all($this->customers->find($customer)))];

        return response()->json($data);
    }

    /**
     * Show a new address form for the selected customer.
     *
     * @param  TenantCustomerAddressRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return View The action result.
     */
    public function create(TenantCustomerAddressRequest $request, int $customer): View
    {
        $data = $this->viewData($request, $customer);
        $data['address'] = $data['customer']->addresses()->make(['country_code' => 'IN', 'phone_country_code' => '+91']);

        return view('tenant.customers.address-form', $data);
    }

    /**
     * Show an address only after resolving it through its parent customer.
     *
     * @param  TenantCustomerAddressRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @param  int  $address  The address for this action.
     * @return View The action result.
     */
    public function edit(TenantCustomerAddressRequest $request, int $customer, int $address): View
    {
        $data = $this->viewData($request, $customer);
        $data['address'] = $this->addresses->find($data['customer'], $address);

        return view('tenant.customers.address-form', $data);
    }

    /**
     * Create an address and atomically establish defaults.
     *
     * @param  TenantCustomerAddressSaveRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    public function store(TenantCustomerAddressSaveRequest $request, int $customer): RedirectResponse|JsonResponse
    {
        $record = $this->service->save($customer, $request->validated());

        return $this->saved($request, $customer, 'Address added successfully.', $record, 201);
    }

    /**
     * Update the parent-scoped address and its defaults.
     *
     * @param  TenantCustomerAddressSaveRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @param  int  $address  The address for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    public function update(TenantCustomerAddressSaveRequest $request, int $customer, int $address): RedirectResponse|JsonResponse
    {
        $record = $this->service->save($customer, $request->validated(), $address);

        return $this->saved($request, $customer, 'Address updated successfully.', $record);
    }

    /**
     * Set shipping or billing defaults under the customer lock.
     *
     * @param  TenantCustomerAddressRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @param  int  $address  The address for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    public function defaults(TenantCustomerAddressRequest $request, int $customer, int $address): RedirectResponse|JsonResponse
    {
        $record = $this->service->save($customer, $request->validated(), $address);

        return $this->saved($request, $customer, 'Address defaults updated.', $record);
    }

    /**
     * Delete an address and replace its defaults consistently.
     *
     * @param  TenantCustomerAddressRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @param  int  $address  The address for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    public function destroy(TenantCustomerAddressRequest $request, int $customer, int $address): RedirectResponse|JsonResponse
    {
        $this->service->delete($customer, $address);

        return $this->saved($request, $customer, 'Address deleted successfully.');
    }

    /**
     * Build the existing tenant layout context.
     *
     * @param  TenantCustomerAddressRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @return array The action result.
     */
    private function viewData(TenantCustomerAddressRequest $request, int $customer): array
    {
        return ['tenantUser' => $request->user('tenant'), 'tenantDomain' => session('tenant_domain'), 'customer' => $this->customers->find($customer)];
    }

    /**
     * Return the existing API or browser notification format.
     *
     * @param  TenantCustomerAddressRequest  $request  The request for this action.
     * @param  int  $customer  The customer for this action.
     * @param  string  $message  The message for this action.
     * @param  ?CustomerAddress  $address  The address for this action.
     * @param  int  $status  The status for this action.
     * @return RedirectResponse|JsonResponse The action result.
     */
    private function saved(TenantCustomerAddressRequest $request, int $customer, string $message, ?CustomerAddress $address = null, int $status = 200): RedirectResponse|JsonResponse
    {
        $data = ['message' => $message];
        if ($address) {
            $data['data'] = new TenantCustomerAddressResource($address);
        }

        return $request->is('api/*') ? response()->json($data, $status) : redirect()->route('tenant.customers.show', $customer)->with('success', $message);
    }
}
