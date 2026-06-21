# CRLA Rules Update Notes

Date: 2026-06-21

## Changed Rules

- Task 2A is now a 10-item Yes/No rhyming decision task.
- Task 2A content contains exactly 6 rhyming pairs and 4 non-rhyming pairs.
- Task 2A stores the selected Yes/No answer, correctness, and a deterministic score in Laravel.
- Task 2A does not record audio, upload audio, or call ASR.
- Task 1A scores 0-6 route only to Task 2A, then the CRLA path ends with Task 2B and passage fields recorded as 0.
- Task 1A scores 7-10 skip Task 2A, credit Task 2A as 10, and proceed to Task 2B.
- Passage reading is administered only when Task 1A is 7-10 and CRLA total is 17-30.
- Passage duration is capped at 60 seconds. Reading accuracy remains `100 - (incorrect_words * 2)`.
- Final reading score remains `(comprehension * 0.60) + (reading_accuracy * 0.40)` and is only applied to passage-eligible learners.

## Export Notes

- The uploaded workbook is stored at `storage/app/templates/crla/CRLA3_Grade1Scoresheet_v3_readirect.xlsx`.
- `CrlaExcelExportService` loads the template, writes ReaDirect values into the visible scoresheet/class-record cells, and saves generated copies under `storage/app/reports/crla/{assessment_type}/`.
- Diagnostic and final reassessment exports use separate `reports.report_type` values and separate output directories.
- One workbook is generated per class when the learner belongs to a class; unassigned learners get a learner-scoped workbook.
- Existing rows are refreshed by regenerating the workbook from current results instead of appending duplicate report rows.

## Data Assumptions

- LRN is read from `learners.metadata.lrn`, falling back to `learner_code`.
- Sex is read from `learners.metadata.sex`, falling back to `learners.metadata.gender`.
- Story number defaults to `1` for eligible passage results.
- Words read is derived as `50 - incorrect_words` and reading duration is read from the latest passage audio record, capped at 60 seconds.
- For passage-ineligible learners, passage fields are written as 0 or blank in the template and remarks state why the passage was not administered.

## Local Dependency Note

PhpSpreadsheet was added for template-based Excel generation. The local Composer install was run with `--ignore-platform-req=ext-gd` because this workstation's PHP CLI is missing `ext-gd`; normal environments should enable the GD extension before running a plain `composer install`.
