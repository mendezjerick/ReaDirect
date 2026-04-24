<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use Illuminate\Support\Collection;

class ModuleActivitySelectionService
{
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
        $existing = $this->getLockedItemsForAttempt($moduleAttempt, $activityType)
            ->where('is_mastery_item', false)
            ->values();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        if ($count <= 0) {
            return collect();
        }

        $activities = ModuleActivity::query()
            ->with('learningContent')
            ->where('module_id', $moduleAttempt->module_id)
            ->where('activity_type', $activityType)
            ->whereHas('learningContent', function ($query): void {
                $query->where('is_active', true);
            })
            ->get()
            ->filter(fn (ModuleActivity $activity) => ! (bool) ($activity->configuration['is_mastery_item'] ?? false))
            ->shuffle()
            ->take($count)
            ->values();

        $this->guardSelectedCount($activities, $count, $activityType);

        return $this->lockItems($moduleAttempt, $activities, false);
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
            ->shuffle()
            ->take($count)
            ->values();

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

    private function ruleFor(Module $module, string $activityType): ?LearningContent
    {
        return LearningContent::where('content_type', 'module_activity_selection_rule')
            ->where('is_active', true)
            ->get()
            ->first(fn (LearningContent $content) => ($content->payload['module_key'] ?? null) === $module->key
                && ($content->payload['activity_type'] ?? null) === $activityType);
    }

    private function lockItems(ModuleAttempt $moduleAttempt, Collection $activities, bool $isMastery): Collection
    {
        $startSequence = (int) $moduleAttempt->items()->max('sequence');

        return $activities->map(function (ModuleActivity $activity, int $index) use ($moduleAttempt, $isMastery, $startSequence): ModuleAttemptItem {
            $content = $activity->learningContent;
            $payload = $content?->payload ?? $activity->configuration ?? [];

            return ModuleAttemptItem::create([
                'module_attempt_id' => $moduleAttempt->id,
                'module_activity_id' => $activity->id,
                'source_csv_id' => $payload['source_csv_id'] ?? null,
                'activity_type' => $activity->activity_type,
                'sequence' => $startSequence + $index + 1,
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

    private function guardSelectedCount(Collection $activities, int $count, string $activityType): void
    {
        if ($activities->count() < $count) {
            throw new \RuntimeException("Not enough active module activities for {$activityType}.");
        }
    }
}
