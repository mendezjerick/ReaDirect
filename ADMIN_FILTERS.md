# Admin Filters

Admin list filters follow one rule: a visible filter must send a stable query-string key that the backend applies to the real database query.

## Filter Pipeline

1. Vue form control uses a stable `value`, not a display label.
2. Inertia sends the value as a URL query parameter.
3. The admin controller validates or normalizes the value.
4. The Eloquent query applies the filter.
5. Paginated results call `withQueryString()` so filters survive pagination.
6. The controller returns the active `filters` object so the UI keeps selected values.
7. Reset links return to the base page URL.

## Stable Keys

Module filters use module keys:

- `module_1` = Letter and Sound Learning
- `module_2` = Word Reading
- `module_3` = Sentence Reading and Fluency

Assessment content filters use canonical admin keys that map to historical database values:

- `task1_letter` maps to `letter`, `task_1_letter`, `task1_letter`, `crla_task_1_letter`
- `task2a_rhyme` maps to `rhyme_prompt`, `task_2a_rhyme`, `task2a_rhyme`, `crla_task_2a_rhyme`
- `task2b_word_sentence` maps to `word_sentence`, `task_2b_word_sentence`, `task2b_word_sentence`, `crla_task_2b_word_sentence`
- `reading_passage`
- `comprehension_question`

## Current Admin Filter Coverage

- Schools: search, status, district, division
- Teachers: search, school, role, status
- Learners: search, school, class, status, current module, CRLA level, reading classification, diagnostic status, final reassessment status
- Assessment Content: search, canonical content type, difficulty, status
- Module Content: search, module, activity type, practice/mastery, status
- Rules & Thresholds: search, rule type
- Agents: search, agent type, status
- Prompt Templates: search, prompt type, agent type, status
- Audit Logs: search, action, entity type, role, date range
- Testing / QA Mode: search learner, attempt type, sandbox/live, module, status, date range
- Testing learner select: search, school, status

Pages without visible filters, such as System Monitoring and individual debug detail pages, do not expose inactive filter controls.

## Adding a New Filter

1. Add an allowed option in `App\Services\Admin\AdminFilterOptionsService` if the filter uses a known set of values.
2. Add the field to the controller `filters` array.
3. Validate unknown values back to a safe default.
4. Apply the filter with Eloquent before pagination.
5. Return `filters` and `filterOptions` to the page.
6. Add the Vue form control with the exact query parameter name.
7. Add or update a feature test that proves unrelated records are excluded.

## Common Causes of Broken Filters

- Sending display labels such as `Word Reading` while the database stores `module_2`.
- Rendering dropdowns without applying the query parameter in the controller.
- Filtering on the wrong table, such as `learning_contents.content_type` when the page lists `module_activities.activity_type`.
- Forgetting `withQueryString()` on paginated results.
- Showing placeholder/static data instead of querying real records.
- Treating active/inactive status as one database column when some module activity state lives in `configuration`.
