<?php

namespace Tests\Feature;

use App\Models\GeneratedVoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneratedVoiceLineSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_voice_line_seed_relinks_current_and_legacy_reference_audio_paths(): void
    {
        Storage::fake('public');

        $currentPath = 'tts/generated_voice_lines/reference_style/ciel/module_echo/correct/module_1/ciel_module_echo_correct_module_1_letter_a_21094bef.wav';
        $legacyPath = 'tts/generated_voice_lines/reference_style/ciel/module_echo/correct/module_1/ciel_module_echo_correct_module_1_m1_hr011_f026fe3d.wav';

        Storage::disk('public')->put($currentPath, 'RIFF-current-letter-a');
        Storage::disk('public')->put($legacyPath, 'RIFF-legacy-letter-k');

        $this->artisan('readirect:voice-lines:seed')
            ->assertExitCode(0);

        $current = GeneratedVoiceLine::where('line_key', 'ciel.module_echo.correct.module_1.letter.a')->firstOrFail();
        $legacy = GeneratedVoiceLine::where('line_key', 'ciel.module_echo.correct.module_1.letter.k')->firstOrFail();

        $this->assertSame($currentPath, $current->reference_style_audio_path);
        $this->assertSame($currentPath, $current->active_audio_path);
        $this->assertSame('generated', $current->reference_style_status);
        $this->assertSame('generated', $current->status);

        $this->assertSame($legacyPath, $legacy->reference_style_audio_path);
        $this->assertSame($legacyPath, $legacy->active_audio_path);
        $this->assertSame('generated', $legacy->reference_style_status);
        $this->assertSame('generated', $legacy->status);
    }
}
