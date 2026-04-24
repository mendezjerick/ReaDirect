<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Learner;
use App\Services\TeacherAccessService;
use App\Services\TeacherReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherReportController extends Controller
{
    public function index(Request $request, TeacherAccessService $access, TeacherReportService $reports): Response
    {
        $access->ensureTeacherArea($request->user());

        return Inertia::render('Teacher/ReportsIndex', [
            'reports' => $reports->reportsIndex($request->user()),
            'learners' => $access->learnersFor($request->user())->get()->map(fn (Learner $learner) => [
                'public_id' => $learner->public_id,
                'learner_code' => $learner->learner_code,
                'name' => trim($learner->first_name.' '.$learner->last_name),
            ])->values(),
        ]);
    }

    public function learnerDiagnostic(Request $request, Learner $learner, TeacherReportService $reports): StreamedResponse
    {
        $this->audit($request, 'teacher.downloaded_learner_diagnostic_csv', $learner);

        return $reports->learnerDiagnosticCsv($request->user(), $learner);
    }

    public function learnerModuleProgress(Request $request, Learner $learner, TeacherReportService $reports): StreamedResponse
    {
        $this->audit($request, 'teacher.downloaded_learner_module_csv', $learner);

        return $reports->learnerModuleProgressCsv($request->user(), $learner);
    }

    public function learnerFullProgress(Request $request, Learner $learner, TeacherReportService $reports): StreamedResponse
    {
        $this->audit($request, 'teacher.downloaded_learner_full_csv', $learner);

        return $reports->learnerFullProgressCsv($request->user(), $learner);
    }

    public function classSummary(Request $request, TeacherAccessService $access, TeacherReportService $reports): StreamedResponse
    {
        $access->ensureTeacherArea($request->user());
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'teacher.downloaded_class_summary_csv',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $reports->classSummaryCsv($request->user());
    }

    public function pdfPlaceholder(Request $request, TeacherAccessService $access)
    {
        $access->ensureTeacherArea($request->user());

        return response('PDF export will be added in a later phase.', 501);
    }

    private function audit(Request $request, string $action, Learner $learner): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => Learner::class,
            'auditable_id' => $learner->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
