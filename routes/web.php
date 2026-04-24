<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Learner\DiagnosticController;
use App\Http\Controllers\Learner\LearnerAccessController;
use App\Http\Controllers\Learner\LearnerDashboardController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
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

Route::prefix('learner/diagnostic')->name('learner.diagnostic.')->group(function (): void {
    Route::get('/', [DiagnosticController::class, 'intro'])->name('intro');
    Route::get('/task-1', [DiagnosticController::class, 'taskOne'])->name('task-one');
    Route::post('/task-1', [DiagnosticController::class, 'submitTaskOne'])->middleware('throttle:assessment-submit')->name('task-one.submit');
    Route::get('/routing-result', [DiagnosticController::class, 'routingResult'])->name('routing-result');
    Route::get('/module-placement', [DiagnosticController::class, 'placementResult'])->name('module-placement');
});

Route::get('/teacher/dashboard', TeacherDashboardController::class)->middleware('auth')->name('teacher.dashboard');
