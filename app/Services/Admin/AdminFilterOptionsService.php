<?php

namespace App\Services\Admin;

use App\Models\AgentProfile;
use App\Models\Module;
use App\Models\School;
use App\Models\SchoolClass;
use Spatie\Permission\Models\Role;

class AdminFilterOptionsService
{
    public function statusOptions(): array
    {
        return [
            ['label' => 'All statuses', 'value' => 'all'],
            ['label' => 'Active', 'value' => 'active'],
            ['label' => 'Inactive', 'value' => 'inactive'],
        ];
    }

    public function moduleOptions(): array
    {
        return Module::query()
            ->orderBy('sequence')
            ->get(['id', 'key', 'title'])
            ->map(fn (Module $module): array => [
                'label' => $module->title,
                'value' => $module->key,
                'id' => $module->id,
            ])
            ->values()
            ->all();
    }

    public function moduleActivityTypeOptions(): array
    {
        return [
            ['label' => 'Display Letter Pair', 'value' => 'letter_pair_identification', 'module' => 'module_1'],
            ['label' => 'Highlighted First Letter', 'value' => 'highlighted_first_letter', 'module' => 'module_1'],
            ['label' => 'First Letter', 'value' => 'first_letter_identification', 'module' => 'module_1'],
            ['label' => 'Missing First Letter', 'value' => 'missing_first_letter', 'module' => 'module_1'],
            ['label' => 'Display Word', 'value' => 'display_word_reading', 'module' => 'module_2'],
            ['label' => 'Split Word', 'value' => 'split_word_reading', 'module' => 'module_2'],
            ['label' => 'Highlighted Rhyme Word', 'value' => 'highlighted_rhyme_word', 'module' => 'module_2'],
            ['label' => 'Highlighted Sentence Word', 'value' => 'highlighted_sentence_word', 'module' => 'module_2'],
            ['label' => 'Simple Sentence', 'value' => 'simple_sentence_reading', 'module' => 'module_3'],
            ['label' => 'Comma Pause', 'value' => 'comma_pause_reading', 'module' => 'advanced_module'],
            ['label' => 'Full-Stop Pause', 'value' => 'full_stop_pause_reading', 'module' => 'advanced_module'],
            ['label' => 'Mixed Punctuation Fluency', 'value' => 'mixed_punctuation_fluency', 'module' => 'advanced_module'],
            ['label' => 'Mastery Check', 'value' => 'mastery_check', 'module' => 'all'],
        ];
    }

    public function assessmentContentTypeOptions(): array
    {
        return [
            ['label' => 'Task 1 Letter Pronunciation', 'value' => 'task1_letter'],
            ['label' => 'Task 2A Rhyming Words', 'value' => 'task2a_rhyme'],
            ['label' => 'Task 2B Word-in-Sentence', 'value' => 'task2b_word_sentence'],
            ['label' => 'Reading Passage', 'value' => 'reading_passage'],
            ['label' => 'Comprehension Question', 'value' => 'comprehension_question'],
        ];
    }

    public function assessmentContentTypeMap(): array
    {
        return [
            'task1_letter' => ['task1_letter', 'task_1_letter', 'letter', 'crla_task_1_letter'],
            'task2a_rhyme' => ['task2a_rhyme', 'task_2a_rhyme', 'rhyme_prompt', 'crla_task_2a_rhyme'],
            'task2b_word_sentence' => ['task2b_word_sentence', 'task_2b_word_sentence', 'word_sentence', 'crla_task_2b_word_sentence'],
            'reading_passage' => ['reading_passage'],
            'comprehension_question' => ['comprehension_question'],
        ];
    }

    public function schoolOptions(): array
    {
        return School::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (School $school): array => ['label' => $school->name, 'value' => (string) $school->id])
            ->all();
    }

    public function classOptions(): array
    {
        return SchoolClass::query()
            ->with('school:id,name')
            ->orderBy('name')
            ->get(['id', 'school_id', 'name'])
            ->map(fn (SchoolClass $class): array => [
                'label' => trim(($class->school?->name ? $class->school->name.' / ' : '').$class->name),
                'value' => (string) $class->id,
                'school_id' => (string) $class->school_id,
            ])
            ->all();
    }

    public function roleOptions(): array
    {
        $roles = Role::query()->orderBy('name')->pluck('name')->all();

        return collect($roles ?: ['system_admin', 'school_admin', 'teacher', 'student'])
            ->map(fn (string $role): array => ['label' => str_replace('_', ' ', ucfirst($role)), 'value' => $role])
            ->values()
            ->all();
    }

    public function agentTypeOptions(): array
    {
        return [
            ['label' => 'Miss Vivian', 'value' => 'assessment'],
            ['label' => 'Miss Ciel', 'value' => 'coach_feedback'],
            ['label' => 'Miss Estelle', 'value' => 'evaluator'],
            ['label' => 'Miss Estelle', 'value' => AgentProfile::EVALUATOR_RECOMMENDATION],
        ];
    }

    public function promptStatusOptions(): array
    {
        return [
            ['label' => 'All statuses', 'value' => 'all'],
            ['label' => 'Draft', 'value' => 'draft'],
            ['label' => 'Active', 'value' => 'active'],
            ['label' => 'Inactive', 'value' => 'inactive'],
        ];
    }

    public function activeValue(?string $value): string
    {
        return in_array($value, ['active', 'inactive', 'all'], true) ? $value : 'all';
    }
}
