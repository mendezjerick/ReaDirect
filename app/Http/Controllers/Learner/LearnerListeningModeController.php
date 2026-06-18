<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Services\LearnerListeningModeService;
use App\Support\CurrentLearner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LearnerListeningModeController extends Controller
{
    public function update(Request $request, LearnerListeningModeService $listeningMode): RedirectResponse
    {
        $learner = CurrentLearner::require($request);

        $validated = $request->validate([
            'listening_mode' => ['required', 'string', Rule::in(LearnerListeningModeService::ALLOWED)],
        ]);

        $listeningMode->setForLearner($learner, $validated['listening_mode']);

        return back()->with('success', 'Recording mode saved.');
    }
}
