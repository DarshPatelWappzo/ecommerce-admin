<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantCategorySaveRequest;
use App\Models\Tenant\User;
use App\Repositories\TenantCategoryRepository;
use App\Repositories\TenantRoleRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantCategoryController extends Controller
{
    /**
     * Initialize the controller dependencies.
     *
     * @param  TenantCategoryRepository  $tenantCategoryRepository  The injected tenant category repository.
     * @param  TenantRoleRepository  $tenantRoleRepository  The injected tenant role repository.
     */
    public function __construct(
        private readonly TenantCategoryRepository $tenantCategoryRepository,
        private readonly TenantRoleRepository $tenantRoleRepository,
    ) {}

    /**
     * List the tenant category records.
     *
     * @return View The response for this action.
     */
    public function index(): View
    {
        $tenantUser = auth('tenant')->user();
        abort_unless($tenantUser instanceof User, 401);
        abort_unless($this->tenantRoleRepository->userHasPermission($tenantUser, 'categories.view'), 403);

        $data = [];
        $data['tenantUser'] = $tenantUser;
        $data['tenantDomain'] = session('tenant_domain');
        $data['categories'] = $this->tenantCategoryRepository->paginate();
        $data['canCreate'] = $this->tenantRoleRepository->userHasPermission($tenantUser, 'categories.create');
        $data['canEdit'] = $this->tenantRoleRepository->userHasPermission($tenantUser, 'categories.update');

        return view('tenant.categories.index', $data);
    }

    /**
     * Show the form for creating a tenant category.
     *
     * @return View The response for this action.
     */
    public function create(): View
    {
        return $this->form();
    }

    /**
     * Show the form for editing the specified tenant category.
     *
     * @param  int  $category  The category used by this action.
     * @return View The response for this action.
     */
    public function edit(int $category): View
    {
        return $this->form($category);
    }

    /**
     * Create a tenant category from the validated request.
     *
     * @param  TenantCategorySaveRequest  $request  The incoming request.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function store(TenantCategorySaveRequest $request): RedirectResponse|JsonResponse
    {
        $this->tenantCategoryRepository->save($request->validated());

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Category created successfully.');

            $data = [];
            $data['message'] = 'Category created successfully.';
            $data['redirect'] = route('tenant.categories.index');

            return response()->json($data, 201);
        }

        return redirect()->route('tenant.categories.index')->with('success', 'Category created successfully.');
    }

    /**
     * Update the specified tenant category from the validated request.
     *
     * @param  TenantCategorySaveRequest  $request  The incoming request.
     * @param  int  $category  The category used by this action.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function update(TenantCategorySaveRequest $request, int $category): RedirectResponse|JsonResponse
    {
        $this->tenantCategoryRepository->find($category);
        $this->tenantCategoryRepository->save($request->validated(), $category);

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Category updated successfully.');

            $data = [];
            $data['message'] = 'Category updated successfully.';
            $data['redirect'] = route('tenant.categories.index');

            return response()->json($data);
        }

        return redirect()->route('tenant.categories.index')->with('success', 'Category updated successfully.');
    }

    /**
     * Prepare the category and parent options for the add or edit form.
     *
     * @param  int|null  $categoryId  The category id used by this action.
     * @return View The response for this action.
     */
    private function form(?int $categoryId = null): View
    {
        $tenantUser = auth('tenant')->user();
        abort_unless($tenantUser instanceof User, 401);
        abort_unless($this->tenantRoleRepository->userHasPermission(
            $tenantUser,
            $categoryId === null ? 'categories.create' : 'categories.update',
        ), 403);

        $data = [];
        $data['tenantUser'] = $tenantUser;
        $data['tenantDomain'] = session('tenant_domain');
        $data['category'] = $categoryId === null ? null : $this->tenantCategoryRepository->find($categoryId);
        $data['parentCategories'] = $this->tenantCategoryRepository->parentOptions($categoryId);

        return view('tenant.categories.form', $data);
    }
}
