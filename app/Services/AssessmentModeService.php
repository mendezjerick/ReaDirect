<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use Illuminate\Http\Request;

class AssessmentModeService
{
    public function isDeveloperQaMode(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        if ($this->isSandboxContext($request, $attempt)) {
            return true;
        }

        return $this->isConfiguredDeveloper($request);
    }

    public function canShowManualFallback(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->canUseManualFallback($request, $attempt, $learner);
    }

    public function canUseManualFallback(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->hasQaPermission($request, $attempt, $learner, 'manual_fallback');
    }

    public function canShowAssessmentDebug(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->hasQaPermission($request, $attempt, $learner, 'assessment_debug');
    }

    public function canShowJumpControls(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->canUseJumpControls($request, $attempt, $learner);
    }

    public function canUseJumpControls(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->hasQaPermission($request, $attempt, $learner, 'jump_controls');
    }

    public function canBypassLinearFlow(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->hasQaPermission($request, $attempt, $learner, 'flow_bypass');
    }

    public function canAutoTranscribeOnStop(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->hasQaPermission($request, $attempt, $learner, 'auto_transcribe_on_stop');
    }

    public function canForceLearnerStage(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->hasQaPermission($request, $attempt, $learner, 'force_learner_stage');
    }

    public function canResetLearnerFlow(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        if (! $this->isDeveloperQaMode($request, $attempt, $learner)) {
            return false;
        }

        return $this->isSandboxContext($request, $attempt)
            || (bool) config('readirect.developer_qa.reset_learner_flow')
            || (bool) config('readirect_ai.debug.enable_developer_assessment_reset');
    }

    public function canSeeRawAiPayload(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): bool
    {
        return $this->hasQaPermission($request, $attempt, $learner, 'show_ai_debug');
    }

    public function props(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null, ?Learner $learner = null): array
    {
        $isDeveloperQaMode = $this->isDeveloperQaMode($request, $attempt, $learner);
        $autoTranscribeOnStop = $this->canAutoTranscribeOnStop($request, $attempt, $learner);

        return [
            'isDeveloperQaMode' => $isDeveloperQaMode,
            'canUseManualFallback' => $this->canUseManualFallback($request, $attempt, $learner),
            'canShowAssessmentDebug' => $this->canShowAssessmentDebug($request, $attempt, $learner),
            'canUseDeveloperJumpControls' => $this->canUseJumpControls($request, $attempt, $learner),
            'canBypassLinearFlow' => $this->canBypassLinearFlow($request, $attempt, $learner),
            'canAutoTranscribeOnStop' => $autoTranscribeOnStop,
            'canForceLearnerStage' => $this->canForceLearnerStage($request, $attempt, $learner),
            'canResetLearnerFlow' => $this->canResetLearnerFlow($request, $attempt, $learner),
            'canSeeRawAiPayload' => $this->canSeeRawAiPayload($request, $attempt, $learner),
            'assessmentMode' => $isDeveloperQaMode ? 'developer_qa' : 'learner',
            'requireReviewBeforeSubmit' => ! $autoTranscribeOnStop,
            'autoTranscribeOnStop' => $autoTranscribeOnStop,
        ];
    }

    private function isSandboxContext(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt = null): bool
    {
        return (bool) $request->session()->get('admin_testing_mode')
            || (bool) $attempt?->is_sandbox;
    }

    private function isConfiguredDeveloper(Request $request): bool
    {
        return ((bool) config('readirect.developer_qa.enabled') || (bool) config('readirect_ai.debug.enable_developer_assessment_reset'))
            && (bool) $request->user()?->hasRole('system_admin');
    }

    private function hasQaPermission(Request $request, AssessmentAttempt|ModuleAttempt|null $attempt, ?Learner $learner, string $permission): bool
    {
        if (! $this->isDeveloperQaMode($request, $attempt, $learner)) {
            return false;
        }

        if ($this->isSandboxContext($request, $attempt)) {
            return true;
        }

        return (bool) config('readirect.developer_qa.'.$permission);
    }
}
