<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitTaskOneRequest;
use App\Models\AgentProfile;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Services\CrlaScoringService;
use App\Services\ModulePlacementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosticController extends Controller
{
    public function intro(): Response
    {
        return Inertia::render('Learner/DiagnosticIntro');
    }

    public function taskOne(): Response
    {
        return Inertia::render('Learner/TaskOneLetterPronunciation', [
            'letters' => LearningContent::where('content_type', 'crla_task_1_letter')
                ->orderBy('id')
                ->get(['id', 'prompt', 'title'])
                ->values(),
        ]);
    }

    public function submitTaskOne(SubmitTaskOneRequest $request, CrlaScoringService $scoring): RedirectResponse
    {
        $learner = Learner::find($request->integer('learner_id')) ?? Learner::find(session('learner_id')) ?? Learner::firstOrFail();
        $route = $scoring->routeTaskOne($request->integer('score'));
        $agent = AgentProfile::where('key', AgentProfile::ASSESSMENT)->first();

        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'agent_profile_id' => $agent?->id,
            'attempt_type' => 'diagnostic',
            'status' => 'in_progress',
            'task_1_score' => $request->integer('score'),
            'task_2a_score' => $route['assigned_task_2a_score'],
            'rule_applied' => $route['rule_applied'],
            'decision_reason' => $route['requires_task_2a']
                ? 'Task 1 score is 0-6, so Task 2A is required.'
                : 'Task 1 score is 7-10, so Task 2A receives an automatic score of 10.',
            'started_at' => now(),
        ]);

        foreach ($request->input('responses', []) as $index => $response) {
            AssessmentTaskResponse::create([
                'assessment_attempt_id' => $attempt->id,
                'task_key' => 'task_1_letter_pronunciation',
                'item_number' => $index + 1,
                'prompt' => $response['prompt'] ?? null,
                'is_correct' => $response['is_correct'] ?? null,
                'score' => ($response['is_correct'] ?? false) ? 1 : 0,
                'rule_applied' => 'CRLA_TASK_1_SCORING_V1',
            ]);
        }

        session(['assessment_attempt_id' => $attempt->id, 'task_one_route' => $route]);

        return redirect()->route('learner.diagnostic.routing-result');
    }

    public function routingResult(): Response
    {
        $attempt = AssessmentAttempt::find(session('assessment_attempt_id'));
        $route = session('task_one_route', []);

        return Inertia::render('Learner/TaskRoutingResult', [
            'attempt' => $attempt?->only('task_1_score', 'task_2a_score', 'decision_reason'),
            'route' => $route,
        ]);
    }

    public function placementResult(ModulePlacementService $placementService): Response
    {
        $decision = $placementService->place(
            session('demo_crla_classification', CrlaScoringService::GRADE_READY),
            session('demo_reading_classification', 'Developing Reader')
        );

        return Inertia::render('Learner/ModulePlacementResult', [
            'decision' => $decision,
        ]);
    }
}
