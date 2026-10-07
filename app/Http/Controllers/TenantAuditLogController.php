<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantAuditLogRequest;
use App\Repositories\TenantAuditLogRepository;
use App\Services\TenantAuditValues;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class TenantAuditLogController extends Controller
{
    /**
     * Initialize tenant audit read dependencies.
     *
     * @param  TenantAuditLogRepository  $logs  Tenant queries.
     * @param  TenantAuditValues  $values  Sanitization for legacy snapshots.
     */
    public function __construct(private readonly TenantAuditLogRepository $logs, private readonly TenantAuditValues $values) {}

    /**
     * List authorized, filtered tenant audit history.
     *
     * @param  TenantAuditLogRequest  $request  Authorized filters.
     * @return View|JsonResponse Paginated history in the requested channel.
     */
    public function index(TenantAuditLogRequest $request): View|JsonResponse
    {
        $logs = $this->logs->paginate($request->validated());
        if ($request->is('api/*')) {
            $data = $logs;

            return response()->json($data);
        }
        $data = [];
        $data['tenantUser'] = $request->user('tenant');
        $data['tenantDomain'] = session('tenant_domain');
        $data['auditLogs'] = $logs;
        $data['choices'] = $this->logs->choices();

        return view('tenant.audit-logs.index', $data);
    }

    /**
     * Display one tenant record without relying on the original business record.
     *
     * @param  TenantAuditLogRequest  $request  Authorized tenant request.
     * @param  int  $auditLog  Tenant audit identifier.
     * @return View|JsonResponse Read-only comparison with sanitized snapshots.
     */
    public function show(TenantAuditLogRequest $request, int $auditLog): View|JsonResponse
    {
        $log = $this->logs->findById($auditLog);
        if ($request->is('api/*')) {
            $data = [];
            $data['data'] = $log->toArray();
            foreach (['old_values', 'new_values', 'metadata'] as $field) {
                $data['data'][$field] = $log->$field === null ? null : $this->values->sanitize($log->$field);
            }

            return response()->json($data);
        }
        $data = [];
        $data['tenantUser'] = $request->user('tenant');
        $data['tenantDomain'] = session('tenant_domain');
        $data['auditLog'] = $log;
        $data['oldValues'] = $this->values->sanitize($log->old_values ?? []);
        $data['newValues'] = $this->values->sanitize($log->new_values ?? []);
        $data['metadata'] = $this->values->sanitize($log->metadata ?? []);
        $data['fields'] = array_unique([...array_keys($data['oldValues']), ...array_keys($data['newValues'])]);

        return view('tenant.audit-logs.show', $data);
    }
}
