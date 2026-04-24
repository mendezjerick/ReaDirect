<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Models\Module;
use Inertia\Inertia;
use Inertia\Response;

class LearnerDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $learner = Learner::find(session('learner_id')) ?? Learner::first();

        return Inertia::render('Learner/Dashboard', [
            'learner' => $learner?->only('id', 'public_id', 'first_name', 'learner_code'),
            'modules' => Module::query()->orderBy('sequence')->get(['key', 'sequence', 'title', 'description']),
        ]);
    }
}
