<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminFilterOptionsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureAdmin($request->user());
        $filters = $this->filters($request);
        $logs = $this->filteredLogs($filters)
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'filterOptions' => [
                'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action')->map(fn ($action) => ['label' => $action, 'value' => $action])->values(),
                'entityTypes' => AuditLog::query()->whereNotNull('auditable_type')->select('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type')->map(fn ($type) => ['label' => class_basename($type), 'value' => $type])->values(),
                'roles' => array_merge([['label' => 'All roles', 'value' => 'all']], $options->roleOptions()),
            ],
        ]);
    }

    public function export(Request $request, AdminAccessService $access): StreamedResponse
    {
        $access->ensureAdmin($request->user());
        $logs = $this->filteredLogs($this->filters($request))->latest()->limit(5000)->get();

        return response()->streamDownload(function () use ($logs): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'User', 'Action', 'Entity', 'Entity ID', 'IP']);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at?->toDateTimeString(),
                    $log->user?->email,
                    $log->action,
                    $log->auditable_type,
                    $log->auditable_id,
                    $log->ip_address,
                ]);
            }
            fclose($handle);
        }, 'admin-audit-logs.csv', ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        return [
            'search' => trim($request->string('search')->toString()),
            'action' => trim($request->string('action')->toString()),
            'entity_type' => trim($request->string('entity_type')->toString()),
            'user_id' => trim($request->string('user_id')->toString()),
            'role' => trim($request->string('role', 'all')->toString()) ?: 'all',
            'date_from' => trim($request->string('date_from')->toString()),
            'date_to' => trim($request->string('date_to')->toString()),
        ];
    }

    private function filteredLogs(array $filters)
    {
        return AuditLog::with('user')
            ->when($filters['action'], fn ($query) => $query->where('action', $filters['action']))
            ->when($filters['entity_type'], fn ($query) => $query->where('auditable_type', $filters['entity_type']))
            ->when($filters['user_id'], fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['role'] !== 'all', fn ($query) => $query->whereHas('user.roles', fn ($role) => $role->where('name', $filters['role'])))
            ->when($filters['date_from'], fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner
                ->where('action', 'like', "%{$filters['search']}%")
                ->orWhere('auditable_type', 'like', "%{$filters['search']}%")
                ->orWhere('ip_address', 'like', "%{$filters['search']}%")
                ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$filters['search']}%")->orWhere('name', 'like', "%{$filters['search']}%"))));
    }
}
