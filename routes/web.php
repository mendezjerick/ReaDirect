<?php

use App\Http\Controllers\Admin\AdminAgentController;
use App\Http\Controllers\Admin\AdminAIEnvironmentGuideController;
use App\Http\Controllers\Admin\AdminAssessmentContentController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLearnerController;
use App\Http\Controllers\Admin\AdminModuleContentController;
use App\Http\Controllers\Admin\AdminPromptTemplateController;
use App\Http\Controllers\Admin\AdminRuleController;
use App\Http\Controllers\Admin\AdminSchoolController;
use App\Http\Controllers\Admin\AdminSystemMonitoringController;
use App\Http\Controllers\Admin\AdminTeacherController;
use App\Http\Controllers\Admin\AdminTestingController;
use App\Http\Controllers\Admin\DeveloperReinforcementModeController;
use App\Http\Controllers\AgentVoiceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FinalAssessmentController;
use App\Http\Controllers\Learner\AudioUploadController;
use App\Http\Controllers\Learner\DiagnosticAssessmentController;
use App\Http\Controllers\Learner\LearnerAccessController;
use App\Http\Controllers\Learner\LearnerDashboardController;
use App\Http\Controllers\Learner\ModuleActivityController;
use App\Http\Controllers\Learner\ModuleController;
use App\Http\Controllers\Learner\ModuleMasteryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Teacher\AudioPlaybackController;
use App\Http\Controllers\Teacher\AudioTranscriptController;
use App\Http\Controllers\Teacher\TeacherAnalyticsController;
use App\Http\Controllers\Teacher\TeacherAssessmentReviewController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\Teacher\TeacherLearnerController;
use App\Http\Controllers\Teacher\TeacherModuleProgressController;
use App\Http\Controllers\Teacher\TeacherReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('welcome');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/agent-voice/{cacheKey}', [AgentVoiceController::class, 'show'])
    ->where('cacheKey', '[a-f0-9]{64}')
    ->name('agent-voice.show');
Route::post('/agent-voice/synthesize', [AgentVoiceController::class, 'synthesize'])
    ->middleware('throttle:assessment-submit')
    ->name('agent-voice.synthesize');

Route::get('/learner/access', [LearnerAccessController::class, 'create'])->name('learner.access');
Route::post('/learner/access', [LearnerAccessController::class, 'store'])->middleware('throttle:learner-access')->name('learner.access.store');
Route::get('/learner/dashboard', LearnerDashboardController::class)->name('learner.dashboard');
Route::post('/learner/audio/upload', [AudioUploadController::class, 'store'])->middleware('throttle:assessment-submit')->name('learner.audio.upload');

Route::prefix('learner/diagnostic')->name('learner.diagnostic.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('learner.diagnostic.start'))->name('intro');
    Route::get('/start', [DiagnosticAssessmentController::class, 'start'])->name('start');
    Route::post('/start', [DiagnosticAssessmentController::class, 'storeStart'])->middleware('throttle:assessment-submit')->name('start.store');
    Route::post('/developer-retest', [DiagnosticAssessmentController::class, 'developerRetest'])->middleware('throttle:assessment-submit')->name('developer-retest');
    Route::get('/task-1', [DiagnosticAssessmentController::class, 'taskOne'])->name('task-1');
    Route::post('/task-1', [DiagnosticAssessmentController::class, 'storeTaskOne'])->middleware('throttle:assessment-submit')->name('task-1.store');
    Route::get('/task-routing', [DiagnosticAssessmentController::class, 'taskRouting'])->name('task-routing');
    Route::get('/task-2a', [DiagnosticAssessmentController::class, 'taskTwoA'])->name('task-2a');
    Route::post('/task-2a', [DiagnosticAssessmentController::class, 'storeTaskTwoA'])->middleware('throttle:assessment-submit')->name('task-2a.store');
    Route::get('/task-2b', [DiagnosticAssessmentController::class, 'taskTwoB'])->name('task-2b');
    Route::post('/task-2b', [DiagnosticAssessmentController::class, 'storeTaskTwoB'])->middleware('throttle:assessment-submit')->name('task-2b.store');
    Route::get('/crla-summary', [DiagnosticAssessmentController::class, 'crlaSummary'])->name('crla-summary');
    Route::get('/reading-intro', [DiagnosticAssessmentController::class, 'readingIntro'])->name('reading-intro');
    Route::get('/passage', [DiagnosticAssessmentController::class, 'passage'])->name('passage');
    Route::post('/passage', [DiagnosticAssessmentController::class, 'storePassage'])->middleware('throttle:assessment-submit')->name('passage.store');
    Route::get('/comprehension', [DiagnosticAssessmentController::class, 'comprehension'])->name('comprehension');
    Route::post('/comprehension', [DiagnosticAssessmentController::class, 'storeComprehension'])->middleware('throttle:assessment-submit')->name('comprehension.store');
    Route::get('/reading-summary', [DiagnosticAssessmentController::class, 'readingSummary'])->name('reading-summary');
    Route::get('/module-placement', [DiagnosticAssessmentController::class, 'modulePlacement'])->name('module-placement');

    Route::get('/routing-result', [DiagnosticAssessmentController::class, 'taskRouting'])->name('routing-result');
    Route::get('/task-one', [DiagnosticAssessmentController::class, 'taskOne'])->name('task-one');
});

Route::prefix('learner/modules')->name('learner.modules.')->group(function (): void {
    Route::get('/', [ModuleController::class, 'index'])->name('index');
    Route::get('/{module:key}/start', [ModuleController::class, 'start'])->name('start');
    Route::post('/{module:key}/start', [ModuleController::class, 'start'])->middleware('throttle:assessment-submit')->name('start.store');
    Route::get('/{module:key}/overview', [ModuleController::class, 'overview'])->name('overview');
    Route::get('/{module:key}/activity/{activityType}', [ModuleActivityController::class, 'show'])->name('activity');
    Route::post('/{module:key}/activity/{activityType}', [ModuleActivityController::class, 'store'])->middleware('throttle:assessment-submit')->name('activity.store');
    Route::get('/{module:key}/mastery-check', [ModuleMasteryController::class, 'show'])->name('mastery-check');
    Route::post('/{module:key}/mastery-check', [ModuleMasteryController::class, 'store'])->middleware('throttle:assessment-submit')->name('mastery-check.store');
    Route::get('/{module:key}/mastery-result', [ModuleMasteryController::class, 'result'])->name('mastery-result');
    Route::get('/{module:key}/extra-drills', [ModuleController::class, 'extraDrills'])->name('extra-drills');
});

Route::prefix('final-assessment')->name('final-assessment.')->group(function (): void {
    Route::get('/start', [FinalAssessmentController::class, 'start'])->name('start');
    Route::post('/start', [FinalAssessmentController::class, 'storeStart'])->middleware('throttle:assessment-submit')->name('start.store');
    Route::get('/summary', [FinalAssessmentController::class, 'summary'])->name('summary');
    Route::get('/{taskKey}', [FinalAssessmentController::class, 'showTask'])->name('task');
    Route::post('/{taskKey}/submit', [FinalAssessmentController::class, 'submitTask'])->middleware('throttle:assessment-submit')->name('task.submit');
});

Route::middleware('auth')->prefix('teacher')->name('teacher.')->group(function (): void {
    Route::get('/dashboard', TeacherDashboardController::class)->name('dashboard');
    Route::get('/learners', [TeacherLearnerController::class, 'index'])->name('learners.index');
    Route::get('/learners/create', [TeacherLearnerController::class, 'create'])->name('learners.create');
    Route::post('/learners', [TeacherLearnerController::class, 'store'])->name('learners.store');
    Route::get('/learners/{learner}', [TeacherLearnerController::class, 'show'])->name('learners.show');
    Route::get('/learners/{learner}/assessments/{assessmentAttempt}', [TeacherAssessmentReviewController::class, 'show'])->name('learners.assessments.show');
    Route::get('/learners/{learner}/modules', [TeacherModuleProgressController::class, 'index'])->name('learners.modules.index');
    Route::get('/reports', [TeacherReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/learner/{learner}/diagnostic', [TeacherReportController::class, 'learnerDiagnostic'])->name('reports.learner.diagnostic');
    Route::get('/reports/learner/{learner}/module-progress', [TeacherReportController::class, 'learnerModuleProgress'])->name('reports.learner.module-progress');
    Route::get('/reports/learner/{learner}/full-progress', [TeacherReportController::class, 'learnerFullProgress'])->name('reports.learner.full-progress');
    Route::get('/reports/learner/{learner}/final-comparison', [TeacherReportController::class, 'learnerFinalComparison'])->name('reports.learner.final-comparison');
    Route::get('/reports/class-summary', [TeacherReportController::class, 'classSummary'])->name('reports.class-summary');
    Route::get('/reports/pdf-placeholder', [TeacherReportController::class, 'pdfPlaceholder'])->name('reports.pdf-placeholder');
    Route::get('/analytics', TeacherAnalyticsController::class)->name('analytics');
    Route::get('/audio/{audioFile}/play', AudioPlaybackController::class)->name('audio.play');
    Route::put('/audio/{audioFile}/transcript', [AudioTranscriptController::class, 'update'])->name('audio.transcript.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit')->defaults('layout', 'teacher');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    Route::post('/developer-reinforcement-mode', [DeveloperReinforcementModeController::class, 'update'])->name('developer-reinforcement-mode.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit')->defaults('layout', 'admin');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::resource('schools', AdminSchoolController::class)->except(['destroy']);
    Route::post('/schools/{school}/deactivate', [AdminSchoolController::class, 'deactivate'])->name('schools.deactivate');
    Route::post('/schools/{school}/reactivate', [AdminSchoolController::class, 'reactivate'])->name('schools.reactivate');

    Route::resource('teachers', AdminTeacherController::class)->parameters(['teachers' => 'teacher'])->except(['destroy']);
    Route::post('/teachers/{teacher}/deactivate', [AdminTeacherController::class, 'deactivate'])->name('teachers.deactivate');
    Route::post('/teachers/{teacher}/reactivate', [AdminTeacherController::class, 'reactivate'])->name('teachers.reactivate');

    Route::resource('learners', AdminLearnerController::class)->except(['destroy']);
    Route::post('/learners/{learner}/deactivate', [AdminLearnerController::class, 'deactivate'])->name('learners.deactivate');
    Route::post('/learners/{learner}/reactivate', [AdminLearnerController::class, 'reactivate'])->name('learners.reactivate');

    Route::resource('assessment-content', AdminAssessmentContentController::class)->parameters(['assessment-content' => 'assessmentContent'])->except(['destroy']);
    Route::post('/assessment-content/{assessmentContent}/deactivate', [AdminAssessmentContentController::class, 'deactivate'])->name('assessment-content.deactivate');
    Route::post('/assessment-content/{assessmentContent}/reactivate', [AdminAssessmentContentController::class, 'reactivate'])->name('assessment-content.reactivate');

    Route::resource('module-content', AdminModuleContentController::class)->parameters(['module-content' => 'moduleContent'])->except(['destroy']);
    Route::post('/module-content/{moduleContent}/deactivate', [AdminModuleContentController::class, 'deactivate'])->name('module-content.deactivate');
    Route::post('/module-content/{moduleContent}/reactivate', [AdminModuleContentController::class, 'reactivate'])->name('module-content.reactivate');

    Route::get('/rules/history', [AdminRuleController::class, 'history'])->name('rules.history');
    Route::resource('rules', AdminRuleController::class)->parameters(['rules' => 'rule'])->only(['index', 'show', 'edit', 'update']);

    Route::resource('agents', AdminAgentController::class)->only(['index', 'show', 'edit', 'update']);
    Route::get('/prompts/history', [AdminPromptTemplateController::class, 'history'])->name('prompts.history');
    Route::resource('prompts', AdminPromptTemplateController::class)->parameters(['prompts' => 'prompt'])->except(['destroy']);

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->name('audit-logs.export');
    Route::get('/system-monitoring', AdminSystemMonitoringController::class)->name('system-monitoring.index');
    Route::get('/ai-env-guide', AdminAIEnvironmentGuideController::class)->name('ai-env-guide');

    Route::prefix('testing')->name('testing.')->group(function (): void {
        Route::get('/', [AdminTestingController::class, 'index'])->name('index');
        Route::get('/learners', [AdminTestingController::class, 'learners'])->name('learners');
        Route::get('/flow-jump', [AdminTestingController::class, 'flowJump'])->name('flow-jump');
        Route::get('/jump/{target}', [AdminTestingController::class, 'jump'])->name('jump');
        Route::post('/start-sandbox', [AdminTestingController::class, 'startSandbox'])->name('start-sandbox');
        Route::post('/exit', [AdminTestingController::class, 'exit'])->name('exit');
        Route::get('/learner/{learner}/jump', [AdminTestingController::class, 'learnerJump'])->name('learner-jump');
        Route::get('/assessment/{assessmentAttempt}/debug', [AdminTestingController::class, 'assessmentDebug'])->name('assessment.debug');
        Route::get('/module/{moduleAttempt}/debug', [AdminTestingController::class, 'moduleDebug'])->name('module.debug');
        Route::get('/stt/{audioFile}/debug', [AdminTestingController::class, 'sttDebug'])->name('stt.debug');
        Route::get('/llm/{interaction}/debug', [AdminTestingController::class, 'llmDebug'])->name('llm.debug');
    });
});
