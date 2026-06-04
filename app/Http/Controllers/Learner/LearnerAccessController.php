<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnerAccessController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Learner/Access');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['learner_code' => ['required', 'string', 'exists:learners,learner_code']]);
        $learner = Learner::where('learner_code', $validated['learner_code'])->firstOrFail();

        if ((bool) ($learner->metadata['normal_learner_access_disabled'] ?? false)) {
            throw ValidationException::withMessages([
                'learner_code' => 'This learner code is reserved for admin testing.',
            ]);
        }

        $request->session()->put('learner_id', $learner->id);
        $request->session()->forget([
            'assessment_attempt_id',
            'final_assessment_attempt_id',
            'module_attempt_id',
            'task_one_route',
        ]);

        return redirect()->route('learner.diagnostic.start');
    }
}
