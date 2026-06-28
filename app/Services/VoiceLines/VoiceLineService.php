<?php

namespace App\Services\VoiceLines;

use App\Models\GeneratedVoiceLine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VoiceLineService
{
    public const REFERENCE_STYLE = 'reference_style';
    public const KOKORO_IDENTITY = 'kokoro_identity';

    private const DEFENSE_VALUES = [
        'cat',
        'dog',
        'sun',
        'map',
        'pen',
        'red',
        'log',
        'cup',
        'fish',
        'leaf',
        'kite',
        'seed',
        'mat',
        'sit',
        'run',
        'read',
        'rosa',
        'lena',
    ];

    private const TEXT_ALIASES = [
        'vivian' => [
            'we will do a short reading check together i will guide each step just try your best' => 'vivian.intro.assessment',
            'great work on the word check now pick a story read it out loud and then ill ask you five questions about it' => 'vivian.assessment.story_choice',
            'which story do you want to read pick the one that sounds most interesting to you' => 'vivian.assessment.story_choice',
            'choose one story for your final reading passage' => 'vivian.assessment.story_choice',
            'choose the best answer based on the story you read' => 'vivian.assessment.comprehension_choice',
            'this is your final reading check do your best one step at a time' => 'vivian.assessment.final_start',
            'this is your final reading check read the passage aloud and try your best' => 'vivian.assessment.final_start',
            'say this letter clearly for your final check' => 'vivian.instruction.listen_then_say_sound',
            'read the passage aloud try your best and speak clearly' => 'vivian.instruction.listen_choose_or_say',
            'task 2a is saved task 2b and passage reading are not administered for this path' => 'vivian.friendly.keep_going_each_item',
            'listen to your answer if you are happy with your answer click submit' => 'vivian.instruction.listen_choose_or_say',
            'say the letter out loud record your answer when you are ready' => 'vivian.task1.normal_start',
            'read the word in the sentence speak clearly when you record' => 'vivian.task2b.word_sentence_start',
            'listen to both words carefully then choose yes if they rhyme or no if they do not rhyme' => 'vivian.task2a.rhyme_prompt_intro',
            'checking your recording' => 'vivian.processing.checking_recording',
            'checking your answer' => 'vivian.processing.checking_answer',
            'checking your reading' => 'vivian.processing.checking_reading',
            'thank you let us continue' => 'vivian.continue.thank_you',
            'good effort let us go to the next one' => 'vivian.continue.good_effort',
            'i heard your answer let us keep going' => 'vivian.asr.unknown_transcript',
            'hold the orange button to record your answer first' => 'vivian.task1.normal_start',
            'hold the orange button to record the highlighted word first' => 'vivian.instruction.listen_then_say_sound',
            'hold the orange button to record the passage first' => 'vivian.no_recording.passage_first',
            'click submit first so i can check your answer' => 'vivian.instruction.listen_choose_or_say',
            'click submit first so i can check your reading' => 'vivian.processing.checking_reading',
            'let us answer this first' => 'vivian.instruction.listen_choose_or_say',
            'manual recording mode is ready' => 'vivian.friendly.stay_focused_ready',
            'something went wrong while checking your recording thats okay please try again with a clear voice' => 'vivian.error.recording_check_failed',
        ],
        'ciel' => [
            'your practice path is ready we will work one step at a time' => 'ciel.friendly.read_slowly_together',
            'read the prompt then record your voice i will help you practice' => 'ciel.instruction.look_listen_read',
            'listen to your answer if you are happy with your answer click submit' => 'ciel.instruction.listen_then_say_word',
            'checking your recording' => 'ciel.module.processing.checking_reading',
            'checking your reading' => 'ciel.module.processing.checking_reading',
            'checking your answer' => 'ciel.module.processing.checking_reading',
            'we could not use that recording please try again' => 'ciel.module.audio_unclear.try_clear_voice',
            'hold the orange button to record your answer first' => 'ciel.instruction.say_sound_clearly',
            'click submit first so i can check your answer' => 'ciel.instruction.look_listen_read',
            'let us answer this first' => 'ciel.instruction.look_listen_read',
            'that is correct go to the next one' => 'ciel.praise.got_that_one',
            'try this same item again' => 'ciel.reassurance.try_one_more_time',
            'good try go to the next one' => 'ciel.reassurance.slow_down_together',
            'see you next time' => 'ciel.module.goodbye',
            'this is your mini mastery check do your best one item at a time' => 'ciel.mastery.start',
            'this is your mini mastery check do your best one item at a time and ill stay with you' => 'ciel.mastery.start',
            'manual recording mode is ready' => 'ciel.friendly.ready_read_together',
            'ciel stopped listening safely you can use manual recording mode' => 'ciel.automatic.stopped',
            'ciel stopped listening safely you can use manual recording mode and keep practicing at your own pace' => 'ciel.automatic.stopped',
            'let us slow down and choose one lesson at a time' => 'ciel.playful.go_slowly_try',
            'are you ready to choose one lesson without rushing' => 'ciel.playful.try_together_smile',
        ],
        'estelle' => [
            'great job finishing your final assessment here is how your reading changed' => 'estelle.completion.final_check_complete',
            'great job finishing your final reading check your effort shows what you practiced and what you can keep building next' => 'estelle.completion.final_check_complete',
            'you made progress from your first reading check to your final reading check and that progress can guide your next practice' => 'estelle.summary.progress_made',
            'you finished the first reading task your score helps us decide which reading activity should come next' => 'estelle.result.task1.routing',
            'task 2a is now saved based on this path the next reading parts will not be given for now' => 'estelle.result.task2a.saved',
            'the crla tasks are complete review your scores first then you will continue with a short reading passage' => 'estelle.result.crla.summary_with_passage',
            'the crla tasks are complete passage reading is not needed for this result so we can move forward' => 'estelle.result.crla.summary_no_passage',
            'i used your final reading score to find your reading level tap continue when you are ready to see your path' => 'estelle.result.reading_summary',
            'great job your reading path is ready and it will guide the next activities on your dashboard' => 'estelle.result.module_placement',
            'wonderful work you are reading at grade level so you can continue to your dashboard' => 'estelle.result.grade_level_placement',
            'your mastery result is ready this helps us see what you learned and what you can practice next' => 'estelle.result.mastery_ready',
        ],
    ];

    private const DEFAULT_LINES = [
        'vivian' => [
            'intro' => 'vivian.intro.assessment',
            'focused_instruction' => 'vivian.instruction.look_item_answer',
            'friendly_encouragement' => 'vivian.friendly.stay_focused_ready',
            'gentle_reassurance' => 'vivian.reassurance.continue_calmly',
            'happy_praise' => 'vivian.praise.answered_clearly',
            'default' => 'vivian.instruction.look_item_answer',
        ],
        'ciel' => [
            'intro' => 'ciel.intro.read_together',
            'focused_instruction' => 'ciel.instruction.look_listen_read',
            'friendly_encouragement' => 'ciel.friendly.read_slowly_together',
            'gentle_reassurance' => 'ciel.reassurance.try_one_more_time',
            'happy_praise' => 'ciel.praise.got_that_one',
            'playful_friend' => 'ciel.playful.go_slowly_try',
            'default' => 'ciel.friendly.read_slowly_together',
        ],
        'estelle' => [
            'intro' => 'estelle.intro.results',
            'calm_evaluation' => 'estelle.evaluation.look_result_together',
            'focused_instruction' => 'estelle.instruction.look_result_carefully',
            'gentle_reassurance' => 'estelle.reassurance.result_not_failed',
            'happy_praise' => 'estelle.praise.effort_progress',
            'default' => 'estelle.evaluation.look_result_together',
        ],
    ];

    public function resolve(string $agent, string $text, ?string $lineKey = null, ?string $intent = null): ?array
    {
        if (! (bool) config('readirect.voice_database.enabled', false)) {
            return null;
        }

        try {
            $line = $this->findLine($agent, $text, $lineKey, $intent);
        } catch (QueryException $exception) {
            $this->logMissing('voice_line_table_unavailable', ['error' => $exception->getMessage()]);

            return null;
        } catch (Throwable $exception) {
            $this->logMissing('voice_line_lookup_failed', ['error' => $exception->getMessage()]);

            return null;
        }

        if (! $line) {
            $this->logMissing('voice_line_not_found', [
                'agent' => $this->canonicalAgent($agent),
                'line_key' => $lineKey,
                'intent' => $intent,
                'text_hash' => $this->textHash($text),
            ]);

            return null;
        }

        $selection = $this->selectAudio($line);
        if (! $selection) {
            $this->logMissing('voice_line_audio_missing', [
                'line_key' => $line->line_key,
                'active_stage' => $this->activeStage(),
            ]);

            return null;
        }

        return [
            'agent' => $line->agent,
            'line_key' => $line->line_key,
            'text' => $line->text,
            'voice_enabled' => true,
            'tts_provider' => 'database',
            'audio_url' => route('agent-voice.generated', [
                'line' => $line->id,
                'stage' => $selection['type'],
            ], false),
            'fallback' => $selection['fallback'],
            'fallback_used' => $selection['fallback'],
            'text_fallback_allowed' => true,
            'status' => 'database_hit',
            'active_stage' => $this->activeStage(),
            'active_audio_type' => $selection['type'],
            'engine_used' => $selection['engine'],
            'duration_seconds' => $selection['duration'],
            'generated_voice_line_id' => $line->id,
        ];
    }

    public function activeStage(): string
    {
        $stage = (string) config('readirect.voice_database.active_stage', self::REFERENCE_STYLE);

        return in_array($stage, [self::REFERENCE_STYLE, self::KOKORO_IDENTITY], true)
            ? $stage
            : self::REFERENCE_STYLE;
    }

    public function refreshActiveFields(GeneratedVoiceLine $line): GeneratedVoiceLine
    {
        $stage = $this->activeStage();
        $line->defense_audio_path = $line->reference_style_audio_path;
        $line->stage2_demo_audio_path = $line->kokoro_identity_audio_path;
        $line->active_audio_type = $stage;
        $line->active_audio_path = $stage === self::KOKORO_IDENTITY
            ? $line->kokoro_identity_audio_path
            : $line->reference_style_audio_path;
        $line->save();

        return $line;
    }

    public function voiceIdForAgent(string $agent): string
    {
        return match ($this->canonicalAgent($agent)) {
            'ciel' => 'af_heart',
            'estelle' => str_contains(strtolower((string) config('readirect.tts.voices.miss_estelle', 'bf_isabella')), 'isabella')
                ? (string) config('readirect.tts.voices.miss_estelle', 'bf_isabella')
                : 'bf_isabella',
            default => 'af_bella',
        };
    }

    public function canonicalAgent(string $agent): string
    {
        return match ($agent) {
            'coach_feedback', 'miss_ciel', 'ciel' => 'ciel',
            'evaluator', 'evaluator_recommendation', 'miss_estelle', 'estelle' => 'estelle',
            default => 'vivian',
        };
    }

    public function textHash(string $text): string
    {
        return hash('sha256', $this->normalizeText($text));
    }

    private function findLine(string $agent, string $text, ?string $lineKey, ?string $intent): ?GeneratedVoiceLine
    {
        $canonicalAgent = $this->canonicalAgent($agent);
        $cleanLineKey = $this->cleanKey($lineKey);

        if ($cleanLineKey) {
            $query = GeneratedVoiceLine::query()
                ->where('line_key', $cleanLineKey)
                ->where('agent', $canonicalAgent);

            return $query->first();
        }

        $fixtureLineKey = $this->dynamicFixtureLineKey($canonicalAgent, $text);
        if ($fixtureLineKey) {
            $fixture = $this->lineByKey($canonicalAgent, $fixtureLineKey);
            if ($fixture) {
                return $fixture;
            }
        }

        $aliasLineKey = $this->aliasLineKey($canonicalAgent, $text);
        if ($aliasLineKey) {
            $alias = $this->lineByKey($canonicalAgent, $aliasLineKey);
            if ($alias) {
                return $alias;
            }
        }

        $query = GeneratedVoiceLine::query()
            ->where('agent', $canonicalAgent)
            ->where('text_hash', $this->textHash($text))
            ->where('is_dynamic_template', false);

        $cleanIntent = $this->cleanKey($intent);
        if ($cleanIntent) {
            $query->where('intent', $cleanIntent);
        }

        $line = $query->orderByDesc('is_static')->first();
        if ($line) {
            return $line;
        }

        if ((bool) config('readirect.voice_database.strict', false)) {
            return null;
        }

        return $this->defaultLine($canonicalAgent, $intent);
    }

    private function lineByKey(string $agent, string $lineKey): ?GeneratedVoiceLine
    {
        return GeneratedVoiceLine::query()
            ->where('agent', $agent)
            ->where('line_key', $lineKey)
            ->first();
    }

    private function aliasLineKey(string $agent, string $text): ?string
    {
        return self::TEXT_ALIASES[$agent][$this->matchKey($text)] ?? null;
    }

    private function defaultLine(string $agent, ?string $intent): ?GeneratedVoiceLine
    {
        $cleanIntent = $this->cleanKey($intent) ?: 'default';
        $lineKey = self::DEFAULT_LINES[$agent][$cleanIntent]
            ?? self::DEFAULT_LINES[$agent]['default']
            ?? null;

        return $lineKey ? $this->lineByKey($agent, $lineKey) : null;
    }

    private function dynamicFixtureLineKey(string $agent, string $text): ?string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
        $patterns = [
            'vivian' => [
                '/\AI heard:\s*(.+?)\.?\z/i' => 'asr_echo.generic.',
                '/\AYou said:\s*(.+?)\.?\z/i' => 'learner_echo.generic.',
            ],
            'ciel' => [
                '/\AYou said:\s*(.+?)\.?\z/i' => 'ciel_asr_echo.',
                '/\AThe word is\s+(.+?)\.?\z/i' => 'target_word_echo.generic.',
                '/\AThat\'s okay,\s*let\'s try\s+(.+?)\s+one more time\.?\z/i' => 'try_again_with_target.generic.',
                '/\AThe correct word is\s+(.+?)\.\s*Let\'s say it slowly together\.?\z/i' => 'correct_word_support.generic.',
            ],
        ];

        foreach ($patterns[$agent] ?? [] as $pattern => $prefix) {
            if (! preg_match($pattern, $clean, $matches)) {
                continue;
            }

            $value = Str::lower(trim($matches[1]));
            if (in_array($value, self::DEFENSE_VALUES, true)) {
                if ($prefix === 'ciel_asr_echo.') {
                    return 'ciel.asr.success_generic';
                }

                return $prefix.$value;
            }

            if ($agent === 'vivian' && str_contains($prefix, 'generic.')) {
                return 'vivian.asr.unknown_transcript';
            }

            if ($agent === 'ciel' && $prefix === 'ciel_asr_echo.') {
                return 'ciel.asr.transcript_unknown';
            }
        }

        return null;
    }

    private function selectAudio(GeneratedVoiceLine $line): ?array
    {
        $stage = $this->activeStage();
        $ordered = $stage === self::KOKORO_IDENTITY
            ? [self::KOKORO_IDENTITY, self::REFERENCE_STYLE]
            : [self::REFERENCE_STYLE, self::KOKORO_IDENTITY];

        if (! (bool) config('readirect.voice_database.fallback_to_other_stage', true)) {
            $ordered = [$stage];
        }

        foreach ($ordered as $type) {
            $path = $type === self::KOKORO_IDENTITY
                ? $line->kokoro_identity_audio_path
                : $line->reference_style_audio_path;

            if (! $path || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            return [
                'type' => $type,
                'path' => $path,
                'fallback' => $type !== $stage,
                'engine' => $type === self::KOKORO_IDENTITY ? $line->kokoro_identity_engine : $line->reference_style_engine,
                'duration' => $type === self::KOKORO_IDENTITY ? $line->kokoro_identity_duration_seconds : $line->reference_style_duration_seconds,
            ];
        }

        return null;
    }

    private function normalizeText(string $text): string
    {
        $cleaned = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));

        return Str::lower($cleaned);
    }

    private function matchKey(string $text): string
    {
        $cleaned = Str::ascii($this->normalizeText($text));
        $cleaned = str_replace(["'", "`"], '', $cleaned);
        $cleaned = preg_replace('/[^a-z0-9]+/', ' ', $cleaned) ?: '';

        return trim($cleaned);
    }

    private function cleanKey(?string $value): ?string
    {
        $cleaned = trim((string) $value);
        if ($cleaned === '') {
            return null;
        }

        return preg_replace('/[^A-Za-z0-9_.-]/', '', $cleaned) ?: null;
    }

    private function logMissing(string $reason, array $context = []): void
    {
        if (! (bool) config('readirect.tts.debug', false)) {
            return;
        }

        Log::info('Generated voice line lookup missed.', ['reason' => $reason] + $context);
    }
}
