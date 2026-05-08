<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use Illuminate\Http\Request;

class AssessmentModeService
{
    public function isDeveloperQaMode(Request $request, ?AssessmentAttempt $attempt = null, ?Learner $learner = null): bool
    {
        if ((bool) $request->session()->get('admin_testing_mode')) {
            return true;
        }

        if ($attempt?->is_sandbox) {
            return true;
        }

        return (bool) (
            $request->user()?->hasRole('system_admin')
            && config('readirect_ai.debug.enable_developer_assessment_reset')
        );
    }

    public function canShowManualFallback(Request $request, ?AssessmentAttempt $attempt = null, ?Learner $learner = null): bool
    {
        return $this->isDeveloperQaMode($request, $attempt, $learner);
    }

    public function canShowAssessmentDebug(Request $request, ?AssessmentAttempt $attempt = null, ?Learner $learner = null): bool
    {
        return $this->isDeveloperQaMode($request, $attempt, $learner)
            && (bool) config('readirect_ai.debug.show_admin_debug');
    }

    public function canShowJumpControls(Request $request, ?AssessmentAttempt $attempt = null, ?Learner $learner = null): bool
    {
        return $this->isDeveloperQaMode($request, $attempt, $learner);
    }

    public function props(Request $request, ?AssessmentAttempt $attempt = null, ?Learner $learner = null): array
    {
        $isDeveloperQaMode = $this->isDeveloperQaMode($request, $attempt, $learner);

        return [
            'isDeveloperQaMode' => $isDeveloperQaMode,
            'canUseManualFallback' => $this->canShowManualFallback($request, $attempt, $learner),
            'canShowAssessmentDebug' => $this->canShowAssessmentDebug($request, $attempt, $learner),
            'canUseDeveloperJumpControls' => $this->canShowJumpControls($request, $attempt, $learner),
            'assessmentMode' => $isDeveloperQaMode ? 'developer_qa' : 'learner',
            'requireReviewBeforeSubmit' => ! $isDeveloperQaMode,
            'autoTranscribeOnStop' => $isDeveloperQaMode,
        ];
    }
}
