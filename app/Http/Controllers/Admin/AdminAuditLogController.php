<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Admin\AdminAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    public function index(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());
        $action = $request->string('action')->toString();
        $logs = AuditLog::with('user')
            ->when($action, fn ($query) => $query->where('action', 'like', "%{$action}%"))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/AuditLogs/Index', ['logs' => $logs, 'filters' => ['action' => $action]]);
    }

    public function export(Request $request, AdminAccessService $access): StreamedResponse
    {
        $access->ensureAdmin($request->user());
        $logs = AuditLog::with('user')->latest()->limit(5000)->get();

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
}
