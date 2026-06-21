<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\CrlaExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CrlaExcelExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_preserves_required_sheet_names_and_writes_visible_scoresheet_columns(): void
    {
        [$learner] = $this->learnerInClass();
        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'module_placement_completed',
            'task_1_score' => 5,
            'task_2a_score' => 8,
            'task_2b_score' => 0,
            'crla_total_score' => 13,
            'crla_classification' => 'Moderate Refresher',
            'incorrect_words' => 0,
            'reading_accuracy' => 0,
            'comprehension_correct_count' => 0,
            'comprehension_percentage' => 0,
            'final_reading_score' => 0,
            'reading_classification' => 'Low Emerging Reader',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        $report = app(CrlaExcelExportService::class)->refreshForAttempt($attempt);
        $path = storage_path('app/'.$report->file_path);

        $this->assertFileExists($path);

        $workbook = IOFactory::load($path);
        $this->assertSame([
            'G1 MT Reading Scoresheet',
            'Class Record',
            'Class Summary',
            'Scoring Reference',
            'List',
        ], $workbook->getSheetNames());

        $sheet = $workbook->getSheetByName('G1 MT Reading Scoresheet');
        $this->assertSame('LRN-1001', $sheet->getCell('B11')->getValue());
        $this->assertSame('Ana Cruz', $sheet->getCell('C11')->getValue());
        $this->assertSame('F', $sheet->getCell('D11')->getValue());
        $this->assertSame(5, $sheet->getCell('F11')->getValue());
        $this->assertSame(8, $sheet->getCell('G11')->getValue());
        $this->assertSame(0, $sheet->getCell('H11')->getValue());
        $this->assertSame(13, $sheet->getCell('I11')->getValue());
        $this->assertSame('Moderate Refresher', $sheet->getCell('J11')->getValue());
        $this->assertSame(0, $sheet->getCell('L11')->getValue());
        $this->assertSame(0, $sheet->getCell('M11')->getValue());
        $this->assertSame(0.0, (float) $sheet->getCell('Q11')->getValue());
        $this->assertSame('ReaDirect: Task 1A 0-6; Task 2B and passage not administered.', $sheet->getCell('V11')->getValue());
    }

    public function test_diagnostic_and_final_exports_use_separate_report_files(): void
    {
        [$learner] = $this->learnerInClass();
        $diagnostic = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'module_placement_completed',
            'task_1_score' => 5,
            'task_2a_score' => 8,
            'task_2b_score' => 0,
            'crla_total_score' => 13,
            'crla_classification' => 'Moderate Refresher',
            'reading_accuracy' => 0,
            'comprehension_percentage' => 0,
            'final_reading_score' => 0,
            'reading_classification' => 'Low Emerging Reader',
            'completed_at' => now(),
        ]);
        $final = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'final_reassessment_completed',
            'task_1_score' => 8,
            'task_2a_score' => 10,
            'task_2b_score' => 7,
            'crla_total_score' => 25,
            'crla_classification' => 'Light Refresher',
            'incorrect_words' => 5,
            'reading_accuracy' => 90,
            'comprehension_correct_count' => 4,
            'comprehension_percentage' => 80,
            'final_reading_score' => 84,
            'reading_classification' => 'Transitioning Reader',
            'completed_at' => now(),
        ]);

        $diagnosticReport = app(CrlaExcelExportService::class)->refreshForAttempt($diagnostic);
        $finalReport = app(CrlaExcelExportService::class)->refreshForAttempt($final);

        $this->assertNotSame($diagnosticReport->file_path, $finalReport->file_path);
        $this->assertFileExists(storage_path('app/'.$diagnosticReport->file_path));
        $this->assertFileExists(storage_path('app/'.$finalReport->file_path));
        $this->assertSame(1, Report::where('report_type', 'crla_diagnostic_scoresheet')->count());
        $this->assertSame(1, Report::where('report_type', 'crla_final_reassessment_scoresheet')->count());
    }

    private function learnerInClass(): array
    {
        $school = School::create(['name' => 'Export Test School']);
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'name' => 'Grade 1 - Export',
            'grade_level' => 'Grade 1',
            'school_year' => '2026',
        ]);
        $learner = Learner::create([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'learner_code' => 'RD-EXPORT-1',
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'grade_level' => 'Grade 1',
            'metadata' => ['lrn' => 'LRN-1001', 'sex' => 'F'],
        ]);

        return [$learner, $class];
    }
}
