<?php

namespace Tests\Unit;

use App\Support\SubmittedItemSet;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SubmittedItemSetTest extends TestCase
{
    public function test_string_and_integer_ids_match_after_normalization(): void
    {
        $items = new Collection([(object) ['id' => 3], (object) ['id' => 1], (object) ['id' => 2]]);
        $responses = [
            ['assessment_attempt_item_id' => '2'],
            ['assessment_attempt_item_id' => '1'],
            ['assessment_attempt_item_id' => '3'],
        ];

        $this->assertTrue(SubmittedItemSet::idsMatch($items, $responses, 'assessment_attempt_item_id'));
    }

    public function test_duplicate_or_missing_ids_are_rejected(): void
    {
        $items = new Collection([(object) ['id' => 1], (object) ['id' => 2], (object) ['id' => 3]]);

        $this->assertFalse(SubmittedItemSet::idsMatch($items, [
            ['assessment_attempt_item_id' => '1'],
            ['assessment_attempt_item_id' => '1'],
            ['assessment_attempt_item_id' => '3'],
        ], 'assessment_attempt_item_id'));

        $this->assertFalse(SubmittedItemSet::idsMatch($items, [
            ['assessment_attempt_item_id' => 1],
            ['assessment_attempt_item_id' => 2],
        ], 'assessment_attempt_item_id'));
    }
}
