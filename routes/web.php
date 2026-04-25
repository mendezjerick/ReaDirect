<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Learner\DiagnosticAssessmentController;
use App\Http\Controllers\Learner\LearnerAccessController;
use App\Http\Controllers\Learner\LearnerDashboardController;
use App\Http\Controllers\Learner\AudioUploadController;
use App\Http\Controllers\Learner\ModuleActivityController;
use App\Http\Controllers\Learner\ModuleController;
use App\Http\Controllers\Learner\ModuleMasteryController;
use App\Http\Controllers\Teacher\TeacherAnalyticsController;
use App\Http\Controllers\Teacher\AudioPlaybackController;
use App\Http\Controllers\Teacher\AudioTranscriptController;
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

Route::get('/learner/access', [LearnerAccessController::class, 'create'])->name('learner.access');
Route::post('/learner/access', [LearnerAccessController::class, 'store'])->middleware('throttle:learner-access')->name('learner.access.store');
Route::get('/learner/dashboard', LearnerDashboardController::class)->name('learner.dashboard');
Route::post('/learner/audio/upload', [AudioUploadController::class, 'store'])->middleware('throttle:assessment-submit')->name('learner.audio.upload');

Route::prefix('learner/diagnostic')->name('learner.diagnostic.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('learner.diagnostic.start'))->name('intro');
    Route::get('/start', [DiagnosticAssessmentController::class, 'start'])->name('start');
    Route::post('/start', [DiagnosticAssessmentController::class, 'storeStart'])->middleware('throttle:assessment-submit')->name('start.store');
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

Route::middleware('auth')->prefix('teacher')->name('teacher.')->group(function (): void {
    Route::get('/dashboard', TeacherDashboardController::class)->name('dashboard');
    Route::get('/learners', [TeacherLearnerController::class, 'index'])->name('learners.index');
    Route::get('/learners/{learner}', [TeacherLearnerController::class, 'show'])->name('learners.show');
    Route::get('/learners/{learner}/assessments/{assessmentAttempt}', [TeacherAssessmentReviewController::class, 'show'])->name('learners.assessments.show');
    Route::get('/learners/{learner}/modules', [TeacherModuleProgressController::class, 'index'])->name('learners.modules.index');
    Route::get('/reports', [TeacherReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/learner/{learner}/diagnostic', [TeacherReportController::class, 'learnerDiagnostic'])->name('reports.learner.diagnostic');
    Route::get('/reports/learner/{learner}/module-progress', [TeacherReportController::class, 'learnerModuleProgress'])->name('reports.learner.module-progress');
    Route::get('/reports/learner/{learner}/full-progress', [TeacherReportController::class, 'learnerFullProgress'])->name('reports.learner.full-progress');
    Route::get('/reports/class-summary', [TeacherReportController::class, 'classSummary'])->name('reports.class-summary');
    Route::get('/reports/pdf-placeholder', [TeacherReportController::class, 'pdfPlaceholder'])->name('reports.pdf-placeholder');
    Route::get('/analytics', TeacherAnalyticsController::class)->name('analytics');
    Route::get('/audio/{audioFile}/play', AudioPlaybackController::class)->name('audio.play');
    Route::put('/audio/{audioFile}/transcript', [AudioTranscriptController::class, 'update'])->name('audio.transcript.update');
});
