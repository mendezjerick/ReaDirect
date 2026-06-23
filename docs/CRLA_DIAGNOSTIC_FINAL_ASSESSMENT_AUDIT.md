# CRLA Diagnostic and Final Assessment Audit

Date: 2026-06-21

This audit records the existing ReaDirect assessment/export state before the CRLA diagnostic/final assessment update.

## Repository Structure

- Laravel/Inertia app: `ReaDirect/`
- ASR/FastAPI service: `ReaDirect-AI-ASR/`
- Game app: `ReaDirect-Game/`
- Intelligent agent app: `ReaDirect-IA/`
- TTS service: `ReaDirect-TTS/`
- Uploaded DepEd workbook sample: `../CRLA3_Grade1Scoresheet_v3.xlsx`

The Laravel app is the official scoring, routing, learner progression, dashboard, storage, and reporting surface. The ASR service is separate and is used through Laravel services for speech/audio processing only.

## Existing Assessment Routes and Controllers

Routes are defined in `routes/web.php`.

Diagnostic flow:

- `GET /learner/diagnostic/start`
- `POST /learner/diagnostic/start`
- `GET /learner/diagnostic/task-1`
- `POST /learner/diagnostic/task-1`
- `GET /learner/diagnostic/task-routing`
- `GET /learner/diagnostic/task-2a`
- `POST /learner/diagnostic/task-2a`
- `GET /learner/diagnostic/task-2a-summary`
- `GET /learner/diagnostic/task-2b`
- `POST /learner/diagnostic/task-2b`
- `GET /learner/diagnostic/crla-summary`
- `GET /learner/diagnostic/reading-intro`
- `GET /learner/diagnostic/passage`
- `POST /learner/diagnostic/passage`
- `GET /learner/diagnostic/comprehension`
- `POST /learner/diagnostic/comprehension`
- `GET /learner/diagnostic/reading-summary`
- `GET /learner/diagnostic/module-placement`

Final reassessment flow:

- `GET /final-assessment/start`
- `POST /final-assessment/start`
- `GET /final-assessment/{taskKey}`
- `POST /final-assessment/{taskKey}/submit`
- `GET /final-assessment/summary`

Diagnostic logic is in `app/Http/Controllers/Learner/DiagnosticAssessmentController.php`. Final logic is in `app/Http/Controllers/FinalAssessmentController.php`.

## Existing Task Behavior

Task 1:

- Uses `AssessmentItemSelectionService::TASK_1_LETTER`.
- Locks 10 `letter` / `crla_task_1_letter` content rows.
- Existing scoring passes text/audio through `AIAnalysisResolver` and `AnswerMatchingService`.
- Score range is effectively 0-10.

Task 2A:

- Uses `AssessmentItemSelectionService::TASK_2A_RHYME`.
- Existing selector locks 10 `rhyme_prompt` / `crla_task_2a_rhyme` content rows.
- Existing learner pages are `resources/js/Pages/Learner/Task2ARhymingWords.vue` and `resources/js/Pages/Learner/FinalAssessment/Task2ARhymingWords.vue`.
- Existing UI asks the learner to say/read the second rhyming word.
- Existing UI records audio, uploads to `/learner/audio/upload`, and uses ASR transcript data.
- Existing backend `storeTaskTwoA()` and final `submitTaskTwoA()` call `scoreTextResponses()`, which can call `AIAnalysisResolver`.
- Existing content CSV is spoken rhyme prompt data, not Yes/No decision data.

Task 2B:

- Uses `AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE`.
- Locks 10 `word_sentence` / `crla_task_2b_word_sentence` content rows.
- Existing scoring uses `SentenceReadingScoringService` after resolving text/audio through `AIAnalysisResolver`.

Passage reading and comprehension:

- Passage route uses one `reading_passage` selected item.
- Passage reading stores `incorrect_words` and `reading_accuracy`.
- Comprehension stores five multiple-choice `comprehension_question` responses and computes final reading score.
- Passage reading still uses audio/STT when not in developer/manual fallback mode.

## Existing Scoring Services

`app/Services/CrlaScoringService.php`:

- Routes Task 1 scores 0-6 to Task 2A and 7-10 to Task 2B.
- Credits Task 2A as 10 when Task 1 is 7-10.
- Computes CRLA total as Task 1 + Task 2A + Task 2B.
- Classifies totals as Full Refresher, Moderate Refresher, Light Refresher, or Grade Ready.
- Did not expose passage eligibility or early-end completion helpers before this change.

`app/Services/ReadingComprehensionScoringService.php`:

- Reading accuracy formula is `100 - incorrect_words * 2`, floored at 0.
- Final reading score formula is `(comprehension * 0.60) + (accuracy * 0.40)`.
- Reading classifications are Low Emerging Reader, High Emerging Reader, Developing Reader, Transitioning Reader, and Reading at Grade Level.

`app/Services/ModulePlacementService.php`:

- Full/Moderate/Light Refresher all assign Module 1.
- Grade Ready + Low/High Emerging assigns Module 2.
- Grade Ready + Developing/Transitioning assigns Module 3.
- Grade Ready + Reading at Grade Level assigns no module.

`app/Services/DiagnosticPlacementService.php`:

- Completes placement after reading classification is present.
- Writes `assigned_module_id`, `placement_decision`, `status = module_placement_completed`, `completed_at`, recommendation row, and learner stage.

## Existing Flow Gating

`app/Services/LearnerFlowService.php` controls resume and step access.

Existing diagnostic resume logic:

- Task 1 if `task_1_score` is null.
- Task 2A if `task_2a_score` is null and Task 1 score <= 6.
- Task 2B if `task_2b_score` is null.
- Passage if `reading_accuracy` is null.
- Comprehension if `final_reading_score` is null.
- Module placement after reading completion.

Existing gap:

- A Task 1 score of 0-6 still resumed to Task 2B after Task 2A because `task_2b_score` remained null.
- Passage eligibility was not enforced through the new CRLA rules.

Existing final resume logic mirrored the diagnostic path and also sent Task 1 score 0-6 learners to Task 2B after Task 2A.

## Existing Data Models and Tables

`assessment_attempts`:

- Stores learner, agent, baseline final assessment link, attempt type, status, Task 1/2A/2B scores, CRLA total/classification, reading accuracy, comprehension percentage, final reading score, reading classification, incorrect word count, comprehension correct count, assigned module, placement decision, comparison summary, sandbox flag, timestamps.

`assessment_attempt_items`:

- Locks selected content by attempt, task type, sequence, source CSV id, prompt snapshot, selected/answered timestamps.

`assessment_task_responses`:

- Stores selected item responses, learner id, audio id, task key/type, item number, prompt, expected answer, transcript fields, selected answer, response text, correctness, score, rule, commentary, AI fields, metadata.

`learning_contents`:

- Stores content type, title, prompt, payload, accepted answers, enrichment metadata, difficulty, active flag.

`reports`:

- Stores learner/school/class/generated-by references, report type, status, file path, payload, timestamps.
- There was no existing dedicated CRLA Excel export model/table.

## Existing Content Bank and Seeders

Main seed data lives in `database/seed-data/readirect/`.

Existing Task 2A seed file:

- `database/seed-data/readirect/task2a_rhyming_words.csv`
- Columns: `id,sequence,content_type,prompt_text,target_word,expected_rhyme_family,accepted_answers,difficulty,points,is_active`
- Contained 20 spoken rhyming prompts.

Enriched/export copies also existed:

- `content-bank/export/assessment/task2a_rhyming_words.csv`
- `database/seed-data/readirect/enriched/assessment/task2a_rhyming_words_enriched.csv`

Seeder:

- `database/seeders/DiagnosticContentSeeder.php`
- `seedTaskTwoARhymes()` imported Task 2A as `rhyme_prompt`, with `target_word`, `expected_answer`, `expected_rhyme_family`, and accepted answers.

## Existing Dashboard and Result Display

Learner dashboard:

- `app/Http/Controllers/Learner/LearnerDashboardController.php`
- Uses latest diagnostic/final attempts and `LearnerFlowService`.

Teacher dashboard/review:

- `app/Services/TeacherDashboardService.php`
- `app/Http/Controllers/Teacher/TeacherAssessmentReviewController.php`
- `resources/js/Pages/Teacher/AssessmentReview.vue`

Learner result pages:

- `TaskRoutingResult.vue`
- `Task2ASummary.vue`
- `CrlaSummary.vue`
- `ReadingIntro.vue`
- `PassageReading.vue`
- `ComprehensionQuestions.vue`
- `ReadingSummary.vue`
- `ModulePlacementResult.vue`
- Final assessment pages under `resources/js/Pages/Learner/FinalAssessment/`.

## Existing Export and Report Code

`app/Services/TeacherReportService.php` generated CSV downloads only:

- Learner diagnostic CSV
- Module progress CSV
- Full progress CSV
- Final comparison CSV
- Class summary CSV

`app/Http/Controllers/Teacher/TeacherReportController.php` exposes these CSV routes. It has a PDF placeholder only. There was no existing PhpSpreadsheet/Laravel Excel package in `composer.json`.

## Uploaded Workbook Template

Workbook path before changes:

- `../CRLA3_Grade1Scoresheet_v3.xlsx`

Workbook structure found:

- `G1 MT Reading Scoresheet`
- `Class Record`
- `Class Summary`
- `Scoring Reference`
- `List` hidden

Visible scoresheet columns:

- `A`: S/N
- `B`: LRN
- `C`: Name of Learner
- `D`: Sex
- `E`: Date of Assessment
- `F`: Task 1
- `G`: Task 2L/Rhymes
- `H`: Task 2H/Sentences
- `I`: Total Score
- `J`: Assessment Part 1 Reading Level
- `K`: Story Number
- `L`: Number of Miscue
- `M`: Words Read
- `N:O`: Total mins/secs
- `P`: WPM
- `Q`: % Correct Words Read
- `R`: Total Correct Answer
- `S`: Learner Experience
- `T`: Observation Level
- `U`: Learner Reading Profile
- `V`: Remarks

The workbook contains merged cells, formulas, styles, hidden/helper columns, and a hidden `List` sheet.

## Implementation Assumptions

- The uploaded workbook would be copied into Laravel storage as the template base.
- Official scoring stays in Laravel services/controllers.
- Task 2A would become deterministic Yes/No response scoring and would not call ASR.
- For Task 1A 0-6, diagnostic and final paths would set Task 2B, passage, comprehension, and final passage score fields to 0 or an ineligible classification state as needed, then route to placement/completion instead of Task 2B/passage.
- Existing `reports` table can store generated class workbook metadata through `file_path` and `payload`; no dedicated export table existed before this change.
