<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTaskOneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['nullable', 'integer', 'exists:learners,id'],
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'responses' => ['nullable', 'array'],
            'responses.*.prompt' => ['nullable', 'string', 'max:10'],
            'responses.*.is_correct' => ['nullable', 'boolean'],
        ];
    }
}
