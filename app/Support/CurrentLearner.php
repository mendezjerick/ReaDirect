<?php

namespace App\Support;

use App\Models\Learner;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

final class CurrentLearner
{
    public static function resolve(Request $request, bool $withCurrentModule = false): ?Learner
    {
        $learnerId = $request->session()->get('learner_id');

        if (! $learnerId) {
            return null;
        }

        $query = $withCurrentModule ? Learner::with('currentModule') : Learner::query();

        return $query->find($learnerId);
    }

    public static function require(Request $request, bool $withCurrentModule = false): Learner
    {
        $learner = self::resolve($request, $withCurrentModule);

        if ($learner) {
            return $learner;
        }

        throw new HttpResponseException(
            redirect()->route('learner.access')
                ->with('info', 'Enter your learner code to continue your reading journey.')
        );
    }
}
