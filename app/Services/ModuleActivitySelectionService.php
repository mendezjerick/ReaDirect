<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\LearnerModuleUsedTarget;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Models\ModuleLessonProgress;
use App\Support\IsolatedLetterSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ModuleActivitySelectionService
{
    private const LESSON_REQUIRED_CORRECT = 4;

    public function startOrResumeModuleAttempt(Learner $learner, Module $module): ModuleAttempt
    {
        $attempt = ModuleAttempt::where('learner_id', $learner->id)
            ->where('module_id', $module->id)
            ->whereIn('status', ['in_progress', 'practice_started', 'mastery_started'])
            ->latest()
            ->first();

        if ($attempt) {
            return $attempt;
        }

        return ModuleAttempt::create([
            'learner_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function selectPracticeItemsForAttempt(ModuleAttempt $moduleAttempt, string $activityType, int $count): Collection
    {
        if ($count <= 0) {
            return collect();
        }

        return DB::transaction(function () use ($moduleAttempt, $activityType, $count): Collection {
            $progress = $this->currentOrCreateLessonProgress($moduleAttempt, $activityType, $count);
            $existing = $this->itemsForLessonProgress($moduleAttempt, $activityType, $progress->lesson_attempt_number);

            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $selection = $this->selectFreshPracticeActivities($moduleAttempt, $activityType, $count);
            $this->guardSelectedCount($selection, $count, $activityType);
            $items = $this->lockItems($moduleAttempt, $selection, false, $progress);
            $this->recordUsedTargets($moduleAttempt, $activityType, $selection);

            return $items;
        });
    }

    public function selectMasteryItemsForAttempt(ModuleAttempt $moduleAttempt, int $count = 10): Collection
    {
        $existing = $this->getLockedItemsForAttempt($moduleAttempt)
            ->where('is_mastery_item', true)
            ->values();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $activities = ModuleActivity::query()
            ->with('learningContent')
            ->where('module_id', $moduleAttempt->module_id)
            ->where('activity_type', 'mastery_check')
            ->whereHas('learningContent', function ($query): void {
                $query->where('is_active', true);
            })
            ->get()
            ->filter(fn (ModuleActivity $activity) => (bool) ($activity->configuration['is_mastery_item'] ?? false))
            ->filter(fn (ModuleActivity $activity) => $this->allowedForNewSelection($activity))
            ->shuffle()
            ->take($count)
            ->values()
            ->map(fn (ModuleActivity $activity): array => ['activity' => $activity]);

        $this->guardSelectedCount($activities, $count, 'mastery_check');

        return $this->lockItems($moduleAttempt, $activities, true);
    }

    public function getLockedItemsForAttempt(ModuleAttempt $moduleAttempt, ?string $activityType = null): Collection
    {
        return $moduleAttempt->items()
            ->when($activityType, fn ($query) => $query->where('activity_type', $activityType))
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();
    }

    public function currentPracticeItemsForAttempt(ModuleAttempt $moduleAttempt, string $activityType): Collection
    {
        $progress = $this->activeLessonProgress($moduleAttempt, $activityType);

        if (! $progress) {
            return collect();
        }

        return $this->itemsForLessonProgress($moduleAttempt, $activityType, $progress->lesson_attempt_number);
    }

    public function latestLessonProgress(ModuleAttempt $moduleAttempt, string $activityType): ?ModuleLessonProgress
    {
        return ModuleLessonProgress::query()
            ->where('module_attempt_id', $moduleAttempt->id)
            ->where('activity_type', $activityType)
            ->latest('lesson_attempt_number')
            ->latest('id')
            ->first();
    }

    public function activeLessonProgress(ModuleAttempt $moduleAttempt, string $activityType): ?ModuleLessonProgress
    {
        return ModuleLessonProgress::query()
            ->where('module_attempt_id', $moduleAttempt->id)
            ->where('activity_type', $activityType)
            ->where('status', 'in_progress')
            ->latest('lesson_attempt_number')
            ->latest('id')
            ->first();
    }

    public function lessonMastered(ModuleAttempt $moduleAttempt, string $activityType): bool
    {
        return ModuleLessonProgress::query()
            ->where('module_attempt_id', $moduleAttempt->id)
            ->where('activity_type', $activityType)
            ->where('status', 'mastered')
            ->exists();
    }

    public function completePracticeLessonAttempt(ModuleAttempt $moduleAttempt, string $activityType, Collection $items): array
    {
        $progress = $this->activeLessonProgress($moduleAttempt, $activityType)
            ?? $this->latestLessonProgress($moduleAttempt, $activityType);
        $itemIds = $items->pluck('id')->all();
        $correctCount = ModuleActivityResponse::query()
            ->where('module_attempt_id', $moduleAttempt->id)
            ->whereIn('module_attempt_item_id', $itemIds)
            ->where('is_correct', true)
            ->count();
        $itemCount = $items->count();
        $requiredCorrect = min(self::LESSON_REQUIRED_CORRECT, max(1, $itemCount));
        $mastered = $itemCount > 0 && $correctCount >= $requiredCorrect;

        if ($progress) {
            $progress->update([
                'status' => $mastered ? 'mastered' : 'retry',
                'item_count' => $itemCount,
                'correct_count' => $correctCount,
                'required_correct' => $requiredCorrect,
                'completed_at' => now(),
                'mastered_at' => $mastered ? now() : null,
            ]);
        }

        return [
            'mastered' => $mastered,
            'correct_count' => $correctCount,
            'item_count' => $itemCount,
            'required_correct' => $requiredCorrect,
            'lesson_attempt_number' => $progress?->lesson_attempt_number,
        ];
    }

    public function getNextUnansweredItem(ModuleAttempt $moduleAttempt): ?ModuleAttemptItem
    {
        return $moduleAttempt->items()
            ->whereNull('answered_at')
            ->orderBy('sequence')
            ->orderBy('id')
            ->first();
    }

    public function markItemAnswered(ModuleAttemptItem $moduleAttemptItem): ModuleAttemptItem
    {
        $moduleAttemptItem->update(['answered_at' => now()]);

        return $moduleAttemptItem;
    }

    public function practiceActivityTypes(Module $module): array
    {
        return LearningContent::where('content_type', 'module_activity_selection_rule')
            ->where('is_active', true)
            ->get()
            ->filter(fn (LearningContent $content) => ($content->payload['module_key'] ?? null) === $module->key)
            ->filter(fn (LearningContent $content) => (int) ($content->payload['practice_item_count'] ?? 0) > 0)
            ->sortBy(fn (LearningContent $content) => $content->payload['source_csv_id'] ?? '')
            ->take(4)
            ->map(fn (LearningContent $content) => $content->payload['activity_type'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function practiceCountFor(Module $module, string $activityType): int
    {
        $rule = $this->ruleFor($module, $activityType);

        return (int) ($rule?->payload['practice_item_count'] ?? 5);
    }

    public function masteryCountFor(Module $module): int
    {
        $rule = $this->ruleFor($module, 'mastery_check');

        return (int) ($rule?->payload['mastery_item_count'] ?? 10);
    }

    private function currentOrCreateLessonProgress(ModuleAttempt $moduleAttempt, string $activityType, int $count): ModuleLessonProgress
    {
        $latest = $this->latestLessonProgress($moduleAttempt, $activityType);

        if ($latest?->status === 'in_progress') {
            return $latest;
        }

        if ($latest?->status === 'mastered') {
            return $latest;
        }

        $attemptNumber = ((int) ($latest?->lesson_attempt_number ?? 0)) + 1;

        return ModuleLessonProgress::create([
            'module_attempt_id' => $moduleAttempt->id,
            'module_key' => $moduleAttempt->module?->key ?? Module::find($moduleAttempt->module_id)?->key ?? '',
            'activity_type' => $activityType,
            'lesson_attempt_number' => $attemptNumber,
            'status' => 'in_progress',
            'item_count' => $count,
            'correct_count' => 0,
            'required_correct' => min(self::LESSON_REQUIRED_CORRECT, max(1, $count)),
            'started_at' => now(),
        ]);
    }

    private function itemsForLessonProgress(ModuleAttempt $moduleAttempt, string $activityType, int $lessonAttemptNumber): Collection
    {
        return $moduleAttempt->items()
            ->where('activity_type', $activityType)
            ->where('is_mastery_item', false)
            ->where('lesson_attempt_number', $lessonAttemptNumber)
            ->orderBy('lesson_item_number')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();
    }

    private function ruleFor(Module $module, string $activityType): ?LearningContent
    {
        return LearningContent::where('content_type', 'module_activity_selection_rule')
            ->where('is_active', true)
            ->get()
            ->first(fn (LearningContent $content) => ($content->payload['module_key'] ?? null) === $module->key
                && ($content->payload['activity_type'] ?? null) === $activityType);
    }

    private function selectFreshPracticeActivities(ModuleAttempt $moduleAttempt, string $activityType, int $count): Collection
    {
        $module = $moduleAttempt->module ?? Module::findOrFail($moduleAttempt->module_id);
        $moduleKey = $module->key;
        $targetType = $this->targetTypeForModule($moduleKey);
        $candidates = ModuleActivity::query()
            ->with('learningContent')
            ->where('module_id', $moduleAttempt->module_id)
            ->where('activity_type', $activityType)
            ->whereHas('learningContent', function ($query): void {
                $query->where('is_active', true);
            })
            ->get()
            ->filter(fn (ModuleActivity $activity) => ! (bool) ($activity->configuration['is_mastery_item'] ?? false))
            ->filter(fn (ModuleActivity $activity) => $this->allowedForNewSelection($activity))
            ->map(function (ModuleActivity $activity) use ($moduleKey, $targetType): ?array {
                $target = $this->canonicalTargetForActivity($activity, $moduleKey, $targetType);

                if ($target === null || $target === '') {
                    return null;
                }

                return [
                    'activity' => $activity,
                    'canonical_target' => $target,
                    'target_type' => $targetType,
                    'canonical_target_hash' => $this->targetHash($target),
                    'cycle_number' => null,
                ];
            })
            ->filter()
            ->values();

        $byTarget = $candidates->groupBy('canonical_target');
        $allTargets = $byTarget->keys()->values()->all();

        if ($allTargets === []) {
            return collect();
        }

        $currentCycle = $this->currentTargetCycle($moduleAttempt->learner_id, $moduleKey, $targetType);
        $usedHashes = $this->usedTargetHashesForCycle($moduleAttempt->learner_id, $moduleKey, $targetType, $currentCycle);
        $unusedTargets = collect($allTargets)
            ->reject(fn (string $target): bool => in_array($this->targetHash($target), $usedHashes, true))
            ->shuffle()
            ->values()
            ->all();
        $wrapCycle = count($unusedTargets) < $count;
        $selectionCycle = $wrapCycle ? $currentCycle + 1 : $currentCycle;
        $selectedTargets = collect($unusedTargets)
            ->take($count)
            ->values()
            ->all();

        if (count($selectedTargets) < $count) {
            $missing = $count - count($selectedTargets);
            $refreshedPool = collect($allTargets)
                ->reject(fn (string $target): bool => in_array($target, $selectedTargets, true))
                ->shuffle()
                ->values()
                ->all();

            if (count($refreshedPool) < $missing) {
                $refreshedPool = collect($allTargets)->shuffle()->values()->all();
            }

            $selectedTargets = array_merge($selectedTargets, array_slice($refreshedPool, 0, $missing));
        }

        return collect($selectedTargets)
            ->map(function (string $target) use ($byTarget, $targetType, $selectionCycle): array {
                $entry = $byTarget->get($target)->shuffle()->first();

                return [
                    'activity' => $entry['activity'],
                    'canonical_target' => $target,
                    'target_type' => $targetType,
                    'canonical_target_hash' => $this->targetHash($target),
                    'cycle_number' => $selectionCycle,
                ];
            })
            ->values();
    }

    private function lockItems(ModuleAttempt $moduleAttempt, Collection $selection, bool $isMastery, ?ModuleLessonProgress $lessonProgress = null): Collection
    {
        $startSequence = (int) $moduleAttempt->items()->max('sequence');
        $activityOffset = $isMastery || ! $lessonProgress
            ? 0
            : (int) $moduleAttempt->items()
                ->where('activity_type', $lessonProgress->activity_type)
                ->where('is_mastery_item', false)
                ->count();

        return $selection->map(function (array|ModuleActivity $entry, int $index) use ($moduleAttempt, $isMastery, $lessonProgress, $startSequence, $activityOffset): ModuleAttemptItem {
            $activity = $entry instanceof ModuleActivity ? $entry : $entry['activity'];
            $content = $activity->learningContent;
            $payload = $content?->payload ?? $activity->configuration ?? [];
            $lessonItemNumber = $index + 1;

            if ($lessonProgress) {
                $payload = array_merge($payload, [
                    'lesson_attempt_number' => $lessonProgress->lesson_attempt_number,
                    'lesson_item_number' => $lessonItemNumber,
                    'dialogue_cycle_position' => $activityOffset + $lessonItemNumber,
                    'canonical_target' => $entry['canonical_target'] ?? null,
                    'target_type' => $entry['target_type'] ?? null,
                    'used_target_cycle_number' => $entry['cycle_number'] ?? null,
                ]);
            }

            return ModuleAttemptItem::create([
                'module_attempt_id' => $moduleAttempt->id,
                'module_activity_id' => $activity->id,
                'source_csv_id' => $payload['source_csv_id'] ?? null,
                'activity_type' => $activity->activity_type,
                'sequence' => $startSequence + $index + 1,
                'lesson_attempt_number' => $lessonProgress?->lesson_attempt_number ?? 1,
                'lesson_item_number' => $lessonItemNumber,
                'prompt_snapshot' => [
                    'title' => $activity->title,
                    'prompt' => $content?->prompt ?? $activity->title,
                    'accepted_answers' => $content?->accepted_answers ?? [],
                    'payload' => $payload,
                    'points' => (int) ($payload['points'] ?? 1),
                ],
                'is_mastery_item' => $isMastery,
                'selected_at' => now(),
            ]);
        })->values();
    }

    private function recordUsedTargets(ModuleAttempt $moduleAttempt, string $activityType, Collection $selection): void
    {
        $moduleKey = $moduleAttempt->module?->key ?? Module::find($moduleAttempt->module_id)?->key ?? '';

        foreach ($selection as $entry) {
            $target = (string) ($entry['canonical_target'] ?? '');
            $targetType = (string) ($entry['target_type'] ?? '');
            $cycleNumber = (int) ($entry['cycle_number'] ?? 1);

            if ($target === '' || $targetType === '') {
                continue;
            }

            LearnerModuleUsedTarget::updateOrCreate(
                [
                    'learner_id' => $moduleAttempt->learner_id,
                    'module_key' => $moduleKey,
                    'target_type' => $targetType,
                    'canonical_target_hash' => $this->targetHash($target),
                    'cycle_number' => $cycleNumber,
                ],
                [
                    'lesson_key' => $activityType,
                    'canonical_target' => $target,
                    'used_at' => now(),
                ],
            );
        }
    }

    private function guardSelectedCount(Collection $selection, int $count, string $activityType): void
    {
        if ($selection->count() < $count) {
            throw new \RuntimeException("Not enough active module activities for {$activityType}.");
        }
    }

    private function allowedForNewSelection(ModuleActivity $activity): bool
    {
        $content = $activity->learningContent;
        $payload = $content?->payload ?? $activity->configuration ?? [];
        $acceptedAnswers = $content?->accepted_answers ?? [];
        $letter = IsolatedLetterSet::expectedLetter($payload, $content?->prompt ?? $activity->title, $acceptedAnswers);

        if ($letter === null) {
            return true;
        }

        if (($payload['module_key'] ?? null) === 'module_1' && IsolatedLetterSet::isExcluded($letter)) {
            return false;
        }

        $promptIsSingleLetter = IsolatedLetterSet::normalize($content?->prompt ?? $activity->title) === $letter;
        if (! $promptIsSingleLetter && ! IsolatedLetterSet::isIsolatedLetterActivity($activity->activity_type, $payload, $content?->content_type)) {
            return true;
        }

        return IsolatedLetterSet::isAllowed($letter);
    }

    private function targetTypeForModule(string $moduleKey): string
    {
        return match ($moduleKey) {
            'module_1' => 'letter',
            'module_3' => 'sentence',
            default => 'word',
        };
    }

    private function canonicalTargetForActivity(ModuleActivity $activity, string $moduleKey, string $targetType): ?string
    {
        $content = $activity->learningContent;
        $payload = $content?->payload ?? $activity->configuration ?? [];

        if ($moduleKey === 'module_1' || $targetType === 'letter') {
            return IsolatedLetterSet::expectedLetter($payload, $content?->prompt ?? $activity->title, $content?->accepted_answers ?? []);
        }

        if ($moduleKey === 'module_3' || $targetType === 'sentence') {
            $sentence = $payload['canonical_target']
                ?? $payload['target_sentence']
                ?? $payload['expected_answer']
                ?? $content?->prompt
                ?? $activity->title
                ?? null;

            return $this->normalizeSentenceTarget($sentence);
        }

        $word = $payload['canonical_target']
            ?? $payload['target_word']
            ?? $payload['expected_answer']
            ?? $content?->prompt
            ?? $activity->title
            ?? null;

        return $this->normalizeWordTarget($word);
    }

    private function normalizeWordTarget(mixed $value): ?string
    {
        $word = strtolower(trim((string) ($value ?? '')));

        return $word === '' ? null : $word;
    }

    private function normalizeSentenceTarget(mixed $value): ?string
    {
        $sentence = strtolower(preg_replace('/\s+/', ' ', trim((string) ($value ?? ''))));

        return $sentence === '' ? null : $sentence;
    }

    private function currentTargetCycle(int $learnerId, string $moduleKey, string $targetType): int
    {
        $cycle = LearnerModuleUsedTarget::query()
            ->where('learner_id', $learnerId)
            ->where('module_key', $moduleKey)
            ->where('target_type', $targetType)
            ->max('cycle_number');

        return max(1, (int) ($cycle ?? 1));
    }

    private function usedTargetHashesForCycle(int $learnerId, string $moduleKey, string $targetType, int $cycleNumber): array
    {
        return LearnerModuleUsedTarget::query()
            ->where('learner_id', $learnerId)
            ->where('module_key', $moduleKey)
            ->where('target_type', $targetType)
            ->where('cycle_number', $cycleNumber)
            ->pluck('canonical_target_hash')
            ->all();
    }

    private function targetHash(string $target): string
    {
        return hash('sha256', $target);
    }
}
