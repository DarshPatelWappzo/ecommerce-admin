<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminPackageStoreRequest;
use App\Http\Requests\SuperAdminPackageUpdateRequest;
use App\Models\Package;
use App\Repositories\PackageRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /**
     * Create a package controller with its persistence repository.
     *
     * @param  PackageRepository  $packageRepository  The injected package repository.
     */
    public function __construct(private readonly PackageRepository $packageRepository) {}

    /**
     * Display a paginated list of packages.
     *
     * @param  Request  $request  The incoming request.
     * @return View The response for this action.
     */
    public function index(Request $request): View
    {
        $packages = $this->packageRepository->paginate(
            10,
            $request->string('search')->trim()->toString(),
        );

        $data = [];
        $data['packages'] = $packages;

        return view('super-admin.package.index', $data);
    }

    /**
     * Show the form for creating a package.
     *
     * @return View The response for this action.
     */
    public function create(): View
    {
        return view('super-admin.package.add');
    }

    /**
     * Validate and store a new package.
     *
     * @param  SuperAdminPackageStoreRequest  $request  The incoming request.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function store(SuperAdminPackageStoreRequest $request): RedirectResponse|JsonResponse
    {
        $this->packageRepository->create($request->validated());

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'Package created successfully.';
            $data['redirect'] = route('super-admin.package.index');

            return response()->json($data, 201);
        }

        return redirect()->route('super-admin.package.index')->with('success', 'Package created successfully.');
    }

    /**
     * Placeholder for the show action; no behavior is implemented yet.
     *
     * @param  string  $id  The id used by this action.
     * @return void No return value.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing a package.
     *
     * @param  Package  $package  The package used by this action.
     * @return View The response for this action.
     */
    public function edit(Package $package): View
    {
        $data = [];
        $data['package'] = $package;

        return view('super-admin.package.edit', $data);
    }

    /**
     * Validate and update an existing package.
     *
     * @param  SuperAdminPackageUpdateRequest  $request  The incoming request.
     * @param  Package  $package  The package used by this action.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function update(SuperAdminPackageUpdateRequest $request, Package $package): RedirectResponse|JsonResponse
    {
        $this->packageRepository->update($package, $request->validated());

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'Package updated successfully.';
            $data['redirect'] = route('super-admin.package.index');

            return response()->json($data);
        }

        return redirect()->route('super-admin.package.index')->with('success', 'Package updated successfully.');
    }

    /**
     * Placeholder for the destroy action; no behavior is implemented yet.
     *
     * @param  string  $id  The id used by this action.
     * @return void No return value.
     */
    public function destroy(string $id)
    {
        //
    }
}
