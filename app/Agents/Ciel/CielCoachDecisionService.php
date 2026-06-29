<?php

namespace App\Agents\Ciel;

class CielCoachDecisionService
{
    public function __construct(private readonly CielDialogueCatalog $dialogue) {}

    public function decide(array|CielCoachEvent $input): ?array
    {
        $event = $input instanceof CielCoachEvent ? $input : CielCoachEvent::fromArray($input);

        if ($event->isAssessmentContext()) {
            return null;
        }

        if ($event->isListeningGame()) {
            return $this->listeningGameDecision($event)->toArray();
        }

        if (! in_array($event->context, ['module_practice', 'mastery_practice'], true)) {
            return null;
        }

        return $this->moduleDecision($event)->toArray();
    }

    private function moduleDecision(CielCoachEvent $event): CielCoachDecision
    {
        if (in_array($event->instructionMode, ['processing', 'checking', 'uploading'], true)) {
            return $this->decision($event, 'thinking', 'ciel.module.processing', ['PROCESSING']);
        }

        if (
            $event->retryRequired === true
            || $event->uncertain === true
            || in_array($event->errorType, ['unclear_audio', 'unclear_asr', 'audio_too_unclear'], true)
            || $event->similarityLabel === 'unclear'
        ) {
            return $this->decision($event, 'confused', 'ciel.module.audio_unclear', ['AUDIO_UNCLEAR']);
        }

        if ($event->isCorrect === false && ($event->remainingAttempts ?? 0) > 0) {
            [$dialogueKey, $errorReason] = $this->correctiveHint($event->errorType);
            $reasons = array_values(array_filter(['CORRECTIVE_HINT', $errorReason]));

            return $this->decision($event, 'advise', $dialogueKey, $reasons);
        }

        if ($event->isCorrect === true && ($event->correctStreak ?? 0) >= 3) {
            return $this->decision($event, 'clap', 'ciel.module.strong_success', ['STRONG_PROGRESS']);
        }

        if ($event->isCorrect === true && $event->sectionCompleted === true) {
            return $this->decision($event, 'clap', 'ciel.module.section_complete', ['SECTION_COMPLETE']);
        }

        if ($event->isCorrect === true) {
            return $this->decision($event, 'happy', 'ciel.module.success', ['CORRECT_RESPONSE']);
        }

        if ($event->finalCompletion === true && $event->congratsAllowed) {
            return $this->decision($event, 'congrats', 'ciel.module.final_complete', ['FINAL_COMPLETION']);
        }

        return $this->decision($event, 'talk', 'ciel.module.generic', ['GENERAL_GUIDANCE']);
    }

    private function listeningGameDecision(CielCoachEvent $event): CielCoachDecision
    {
        return match ($event->instructionMode) {
            'model_pronunciation' => $this->decision(
                $event,
                'talk',
                'ciel.game.model_pronunciation',
                ['PRONUNCIATION_MODEL'],
            ),
            'repeat_after_me' => $this->decision(
                $event,
                'talk',
                'ciel.game.repeat_after_me',
                ['LISTEN_AND_REPEAT'],
            ),
            'listen_and_choose' => $this->decision(
                $event,
                'advise',
                'ciel.game.listen_and_choose',
                ['LISTENING_CHOICE'],
            ),
            'sound_focus' => $this->decision(
                $event,
                'advise',
                'ciel.game.sound_focus',
                ['SOUND_FOCUS'],
            ),
            default => $this->decision(
                $event,
                'talk',
                'ciel.game.generic_listen',
                ['LISTENING_GUIDE'],
            ),
        };
    }

    private function correctiveHint(?string $errorType): array
    {
        return match ($errorType) {
            'vowel_confusion', 'vowel_error', 'middle_sound_error' => [
                'ciel.module.close_retry.middle_sound',
                $errorType === 'middle_sound_error' ? 'MIDDLE_SOUND_ERROR' : 'VOWEL_CONFUSION',
            ],
            'initial_sound_error' => [
                'ciel.module.close_retry.initial_sound',
                'INITIAL_SOUND_ERROR',
            ],
            'final_sound_error' => [
                'ciel.module.close_retry.final_sound',
                'FINAL_SOUND_ERROR',
            ],
            'omission', 'skipped_word' => [
                'ciel.module.close_retry.omission',
                'OMISSION',
            ],
            'insertion' => [
                'ciel.module.close_retry.insertion',
                'INSERTION',
            ],
            'word_boundary_error' => [
                'ciel.module.close_retry.word_boundary',
                'WORD_BOUNDARY_ERROR',
            ],
            'pace_too_fast' => [
                'ciel.module.pace.too_fast',
                'PACE_TOO_FAST',
            ],
            'pace_too_slow' => [
                'ciel.module.pace.too_slow',
                'PACE_TOO_SLOW',
            ],
            'pace_unknown' => [
                'ciel.module.pace.unknown',
                'PACE_UNKNOWN',
            ],
            default => ['ciel.module.close_retry.generic', null],
        };
    }

    private function decision(
        CielCoachEvent $event,
        string $action,
        string $dialogueKey,
        array $reasonCodes,
    ): CielCoachDecision {
        return new CielCoachDecision(
            action: $action,
            dialogueKey: $dialogueKey,
            message: $this->dialogue->message($dialogueKey, $event),
            reasonCodes: $reasonCodes,
            context: $event->context,
            sourceType: $event->sourceType,
        );
    }
}
