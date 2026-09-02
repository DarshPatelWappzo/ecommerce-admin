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
     */
    public function __construct(private readonly PackageRepository $packageRepository) {}

    /**
     * Display a paginated list of packages.
     */
    public function index(Request $request): View
    {
        $packages = $this->packageRepository->paginate(
            10,
            $request->string('search')->trim()->toString(),
        );

        return view('super-admin.package.index', compact('packages'));
    }

    /**
     * Show the form for creating a package.
     */
    public function create(): View
    {
        return view('super-admin.package.add');
    }

    /**
     * Validate and store a new package.
     */
    public function store(SuperAdminPackageStoreRequest $request): RedirectResponse|JsonResponse
    {
        $this->packageRepository->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Package created successfully.',
                'redirect' => route('super-admin.package.index'),
            ], 201);
        }

        return redirect()->route('super-admin.package.index')->with('success', 'Package created successfully.');
    }

    /**
     * Packages do not have a detail page.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing a package.
     */
    public function edit(Package $package): View
    {
        return view('super-admin.package.edit', compact('package'));
    }

    /**
     * Validate and update an existing package.
     */
    public function update(SuperAdminPackageUpdateRequest $request, Package $package): RedirectResponse|JsonResponse
    {
        $this->packageRepository->update($package, $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Package updated successfully.',
                'redirect' => route('super-admin.package.index'),
            ]);
        }

        return redirect()->route('super-admin.package.index')->with('success', 'Package updated successfully.');
    }

    /**
     * Packages are not deleted through this controller yet.
     */
    public function destroy(string $id)
    {
        //
    }
}
