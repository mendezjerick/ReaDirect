<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Learner;
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

        $request->session()->put('learner_id', $learner->id);

        return redirect()->route('learner.dashboard');
    }
}
