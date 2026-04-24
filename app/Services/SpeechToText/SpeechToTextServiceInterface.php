<?php

namespace App\Services\SpeechToText;

use App\Models\AudioFile;

interface SpeechToTextServiceInterface
{
    public function transcribe(AudioFile $audioFile): SpeechToTextResult;
}
