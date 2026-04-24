<?php

namespace Database\Seeders;

use App\Models\Learner;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::firstOrCreate(['name' => 'ReaDirect Demonstration School'], ['district' => 'Local Development']);

        $teacher = User::firstOrCreate(
            ['email' => 'teacher@example.com'],
            ['name' => 'Demo Teacher', 'password' => Hash::make('password')]
        );
        $teacher->assignRole('teacher');

        $class = SchoolClass::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Grade 1 - Blue'],
            ['teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'school_year' => '2026']
        );

        Learner::firstOrCreate(
            ['learner_code' => 'RD-1001'],
            ['school_id' => $school->id, 'class_id' => $class->id, 'first_name' => 'Mika', 'last_name' => 'Reader']
        );
    }
}
