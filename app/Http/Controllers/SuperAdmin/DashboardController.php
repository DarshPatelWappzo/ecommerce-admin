<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminDashboardRequest;
use App\Services\SuperAdminDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly SuperAdminDashboardService $dashboard) {}

    /** Render the existing dashboard using read-only central reports.
     * @return View The dashboard view.
     */
    public function index(): View
    {
        $data = $this->dashboard->overview();

        return view('super-admin.dashboard', $data);
    }

    /** Refresh one validated chart without loading the rest of the dashboard.
     * @param  SuperAdminDashboardRequest  $request  The authorized chart filters.
     * @return JsonResponse Only chart data and escaped rendered markup.
     */
    public function chart(SuperAdminDashboardRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $data = $this->dashboard->chart($filters['section'], $filters['period'], $filters['currency'] ?? config('subscriptions.currency'));
        $data['html'] = view('super-admin.dashboard-'.$filters['section'], ['chart' => $data])->render();

        return response()->json($data)->header('Cache-Control', 'no-store');
    }
}
