<?php

namespace Tests\Unit;

use App\Services\VoiceLines\VoiceLineCatalog;
use Tests\TestCase;

class VoiceLineCatalogModuleEchoTest extends TestCase
{
    public function test_ciel_echoes_are_item_specific_and_lesson_specific_lines_exist(): void
    {
        $catalog = new VoiceLineCatalog();
        $lines = $catalog->allSeedLines();
        $lineKeys = array_column($lines, 'line_key');

        foreach ([
            'ciel.module1.letter_pair_identification.01',
            'ciel.module1.letter_pair_identification.02',
            'ciel.module1.letter_pair_identification.03',
            'ciel.module1.highlighted_first_letter.01',
            'ciel.module1.highlighted_first_letter.02',
            'ciel.module1.highlighted_first_letter.03',
            'ciel.module1.first_letter_identification.01',
            'ciel.module1.first_letter_identification.02',
            'ciel.module1.first_letter_identification.03',
            'ciel.module1.missing_first_letter.01',
            'ciel.module1.missing_first_letter.02',
            'ciel.module1.missing_first_letter.03',
            'ciel.module2.display_word_reading.01',
            'ciel.module2.display_word_reading.02',
            'ciel.module2.display_word_reading.03',
            'ciel.module2.split_word_reading.01',
            'ciel.module2.split_word_reading.02',
            'ciel.module2.split_word_reading.03',
            'ciel.module2.highlighted_rhyme_word.01',
            'ciel.module2.highlighted_rhyme_word.02',
            'ciel.module2.highlighted_rhyme_word.03',
            'ciel.module2.highlighted_sentence_word.01',
            'ciel.module2.highlighted_sentence_word.02',
            'ciel.module2.highlighted_sentence_word.03',
            'ciel.module3.simple_sentence_reading.01',
            'ciel.module3.simple_sentence_reading.02',
            'ciel.module3.simple_sentence_reading.03',
            'ciel.module3.comma_pause_reading.01',
            'ciel.module3.comma_pause_reading.02',
            'ciel.module3.comma_pause_reading.03',
            'ciel.module3.full_stop_pause_reading.01',
            'ciel.module3.full_stop_pause_reading.02',
            'ciel.module3.full_stop_pause_reading.03',
            'ciel.module3.mixed_punctuation_fluency.01',
            'ciel.module3.mixed_punctuation_fluency.02',
            'ciel.module3.mixed_punctuation_fluency.03',
            'ciel.module.after_recording.review_submit_01',
            'ciel.module.after_recording.review_submit_02',
            'ciel.module.after_recording.review_submit_03',
            'ciel.module1.validation.record_letter_first',
            'ciel.module2.validation.record_word_first',
            'ciel.module3.validation.record_sentence_first',
            'vivian.asr.received_01',
            'vivian.asr.received_02',
            'vivian.asr.received_03',
        ] as $lineKey) {
            $this->assertContains($lineKey, $lineKeys);
        }

        $this->assertNotContains('vivian.asr.unknown_transcript', $lineKeys);
        $this->assertNotContains('ciel.module.after_recording.listen_submit', $lineKeys);
        $this->assertNotContains('ciel.module1.before_recording.letter_01', $lineKeys);
        $this->assertNotContains('ciel.module2.before_recording.word_01', $lineKeys);
        $this->assertNotContains('ciel.module3.before_recording.sentence_01', $lineKeys);
        $this->assertNotContains('ciel.module1.instruction.look_letter_sound', $lineKeys);
        $this->assertNotContains('ciel.module2.instruction.look_display_word_reading', $lineKeys);
        $this->assertNotContains('ciel.module3.instruction.simple_sentence_reading', $lineKeys);
        $this->assertNotContains(
            'target_word_echo.generic.rosa',
            $lineKeys,
        );
        $this->assertNotContains(
            'target_word_echo.generic',
            $lineKeys,
        );

        $this->assertContains('ciel.focus.echo_intro', $lineKeys);
        $this->assertContains('ciel.focus.echo_repeat', $lineKeys);
        $this->assertNotContains('ciel.module_echo.initial.module_1.M1-SL011', $lineKeys);
        $this->assertNotContains('ciel.module_echo.correct.module_1.M1-SL011', $lineKeys);
        $this->assertContains('ciel.module_echo.correct.module_1.letter.k', $lineKeys);

        $byKey = collect($lines)->keyBy('line_key');
        $this->assertSame('focused_instruction', $byKey['ciel.focus.echo_intro']['intent']);
        $this->assertSame('focused_instruction', $byKey['ciel.focus.echo_repeat']['intent']);
        $this->assertSame('The letter is pronounced as K.', $byKey['ciel.module_echo.correct.module_1.letter.k']['text']);
        $this->assertSame('The letter is pronounced as kay.', $byKey['ciel.module_echo.correct.module_1.letter.k']['synthesis_text']);
        $this->assertNotSame('The letter is pronounced as Kk.', $byKey['ciel.module_echo.correct.module_1.letter.k']['text']);
        $this->assertSame('The letter is pronounced as aitch.', $byKey['ciel.module_echo.correct.module_1.letter.h']['synthesis_text']);
        $this->assertSame('The letter is pronounced as em.', $byKey['ciel.module_echo.correct.module_1.letter.m']['synthesis_text']);
        $this->assertSame('The letter is pronounced as double you.', $byKey['ciel.module_echo.correct.module_1.letter.w']['synthesis_text']);
        $this->assertSame(
            22,
            collect($lineKeys)->filter(fn (string $lineKey): bool => str_starts_with($lineKey, 'ciel.module_echo.correct.module_1.letter.'))->count(),
        );
        $this->assertNotContains('ciel.module_echo.correct.module_2.M2-RW001', $lineKeys);
        $this->assertContains('ciel.module_echo.correct.module_2.word.cat', $lineKeys);
        $this->assertSame('The word is pronounced as cat.', $byKey['ciel.module_echo.correct.module_2.word.cat']['text']);
        $this->assertSame('The word is pronounced as cat.', $byKey['ciel.module_echo.correct.module_2.word.cat']['synthesis_text']);
        $this->assertSame(
            55,
            collect($lineKeys)->filter(fn (string $lineKey): bool => str_starts_with($lineKey, 'ciel.module_echo.correct.module_2.word.'))->count(),
        );
        $this->assertSame(
            170,
            collect($lineKeys)->filter(fn (string $lineKey): bool => str_starts_with($lineKey, 'ciel.module_echo.correct.module_3.sentence.'))->count(),
        );
        $this->assertSame(
            0,
            collect($lineKeys)->filter(fn (string $lineKey): bool => str_starts_with($lineKey, 'ciel.module_echo.correct.module_3.M3-'))->count(),
        );
    }
}
