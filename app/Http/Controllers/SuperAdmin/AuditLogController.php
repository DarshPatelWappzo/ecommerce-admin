<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDomain;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    /**
     * List the resource records.
     *
     * @param  Request  $request  The incoming request.
     * @return View The response for this action.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'module' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
        ]);

        $auditLogs = AuditLog::query()
            ->with([
                'user:id,name,first_name,last_name,deleted_at',
                'auditable' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        UserDomain::class => ['user:id,email'],
                    ]);
                },
            ])
            ->when($filters['user_id'] ?? null, function ($query, int $userId): void {
                $query->where('user_id', $userId);
            })
            ->when($filters['action'] ?? null, function ($query, string $action): void {
                $query->where('action', $action);
            })
            ->when($filters['module'] ?? null, function ($query, string $module): void {
                $query->where('module', $module);
            })
            ->when($filters['date'] ?? null, function ($query, string $date): void {
                $query->whereDate('created_at', $date);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $users = User::query()
            ->select(['id', 'name', 'first_name', 'last_name'])
            ->orderBy('name')
            ->get();

        $data = [];
        $data['auditLogs'] = $auditLogs;
        $data['users'] = $users;
        $data['filters'] = $filters;

        return view('super-admin.audit-logs.index', $data);
    }
}
