# AGENTS.md

## Purpose

This repository is a 90-minute Laravel take-home task for an investor CSV import API.

The goal is not to build a full production platform. The goal is to demonstrate clear judgement, Laravel maintainability, safe CSV ingestion, scalable processing direction, and documented progress.

Agents must work issue-by-issue and keep the implementation understandable for another Laravel developer.

---

## Non-negotiable workflow

Every task must follow this flow:

1. Start from the GitHub issue.
2. Create a dedicated branch for that issue.
3. Implement only the scope described by that issue.
4. Add progress comments to the GitHub issue.
5. Raise a pull request when the task is complete.
6. Link the pull request to the issue.
7. Add comments on the pull request where useful.
8. Do not merge unless explicitly instructed.

No silent work.

No orphan branches.

No unlinked pull requests.

---

## Branch rules

Each issue must have its own branch.

Branch naming format:

```
issue-<issue-number>-<short-description>
```

Examples:

```
issue-2-csv-upload-ingress
issue-3-streamed-csv-import
issue-5-api-responses
```

Create branches from `main` only:

```
git checkout main
git pull
git checkout -b issue-<issue-number>-<short-description>
```

Do not combine unrelated issues into one branch.

---

## Pull request rules

A pull request must be raised after the task is completed.

PR title format:

```
Issue #<number>: <short task title>
```

PR body must include:

```
## Summary

- What changed
- Why it changed

## Issue

Closes #<issue-number>

## Notes

- Any trade-offs
- Any incomplete work
- Any follow-up needed

## Verification

- Commands run
- Tests run
- Anything not run and why
```

Use `Closes #<issue-number>` to link the PR to the issue.

Do not create a PR for unfinished work unless explicitly asked.

---

## Issue comment rules

Every task issue must receive comments.

Minimum comments:

1. Start comment when work begins.
2. Progress comment if a decision or scope trade-off is made.
3. Completion comment when the PR is raised.

Start comment format:

```
Starting work on this issue.

Branch: issue-<issue-number>-<short-description>

Planned scope:
- <item>
- <item>

Out of scope:
- <item>
```

Completion comment format:

```
Completed implementation for this issue.

Branch: issue-<issue-number>-<short-description>
PR: #<pr-number>

Verification:
- <command/result>
- <command/result>

Notes:
- <any limitations or follow-up>
```

---

## PR comment rules

Add PR comments where useful.

Use PR comments for:

- explaining trade-offs
- calling out intentionally incomplete work
- documenting why a Laravel convention was chosen
- noting validation or storage safety decisions
- noting anything reviewers should inspect first

Do not flood the PR with noise.

---

## Scope boundaries

Stay inside the task issues.

Do not add:

- authentication
- authorization
- user management
- frontend UI
- queue workers
- import status dashboard
- CSV export
- Docker production hardening
- observability stack
- OpenAPI generation
- custom architecture framework
- non-Laravel abstractions

Unless an issue explicitly asks for it.

Authentication is considered but intentionally out of scope for this MVP.

Workers are considered but intentionally out of scope for the immediate MVP. They should be documented as the production evolution path.

---

## Laravel maintainability boundary

Prefer Laravel conventions.

Use conventional Laravel locations:

```
app/Models
app/Http/Controllers/Api
app/Http/Requests
app/Http/Resources
app/Services
database/migrations
tests/Feature
tests/Unit
routes/api.php
```

Keep controllers thin.

Business logic belongs in services.

Validation belongs in Form Requests.

API response shaping belongs in resources/controllers.

Do not create unnecessary custom framework layers.

A Laravel developer should be able to continue the work without reading extensive architecture notes.

---

## CSV upload boundary

Raw uploaded CSV files are private application input.

They must not be public assets.

Use Laravel private local storage:

```
$request->file('file')->store('uploads/csv', 'local');
```

Expected private path:

```
storage/app/private/uploads/csv
```

Do not store raw CSV uploads in:

```
public/
storage/app/public
public/storage
```

Do not rely on:

```
php artisan storage:link
```

for raw CSV uploads.

Validate before storing.

Pass the private stored path to the import service.

---

## CSV validation boundary

CSV rows should be validated pragmatically for the MVP.

Required CSV headers:

```
investor_id,name,age,investment_amount,investment_date
```

Field rules:

```
investor_id          required, non-empty string
name                 required, non-empty string
age                  required, integer, minimum 0
investment_amount    required, numeric/decimal, minimum 0
investment_date      required, valid date
```

Recommended documented date format:

```
YYYY-MM-DD
```

Invalid rows may be skipped and counted in the MVP if row-level error reporting is not fully implemented.

Do not silently fail the whole import without returning a useful error or skipped-row count.

---

## CSV processing boundary

Do not load the full CSV into memory.

Use a streamed/lazy approach.

Acceptable approaches:

```
SplFileObject
fgetcsv
LazyCollection
chunked processing
```

Do not use:

```
file()
collect(file(...))
reading all rows before processing
```

The task mentions 10k+ records. The implementation should show that larger files have been considered.

---

## Data model boundary

Use the task language directly.

CSV fields:

```
investor_id
name
age
investment_amount
investment_date
```

Database model:

```
investors
- id
- external_id
- name
- age
- timestamps

investments
- id
- investor_id
- amount
- investment_date
- timestamps
```

One investor can have many dated investments.

There should be only one investment amount per date per investor.

Use a unique index for:

```
investor_id + investment_date
```

---

## CSV field mapping boundary

The CSV field names do not map one-to-one to all database column names.

Explicit mapping:

```
CSV investor_id          -> investors.external_id
CSV name                 -> investors.name
CSV age                  -> investors.age
CSV investment_amount    -> investments.amount
CSV investment_date      -> investments.investment_date
```

Do not create an `investor_id` column on `investors`.

In the database:

- `investors.external_id` stores the source/system investor identifier from the CSV.
- `investments.investor_id` stores the internal database reference to `investors.id`.

This distinction must remain clear in migrations, models, services, tests, resources, and README wording.

---

## API boundary

Required endpoints:

```
POST /api/imports/investors
GET  /api/investors
GET  /api/investors/average-age
GET  /api/investments/average-amount
GET  /api/investments/count
```

Responses must be JSON.

The investors endpoint must be paginated.

Do not return unbounded large result sets.

---

## Aggregate response formatting boundary

Aggregate endpoints must return stable JSON keys.

Use numeric values, rounded to 2 decimal places for averages.

```
GET /api/investors/average-age

{
  "average_age": 47.04
}
```

```
GET /api/investments/average-amount

{
  "average_investment_amount": 517396.36
}
```

```
GET /api/investments/count

{
  "total_investments": 10000
}
```

Empty database behaviour should be consistent and documented.

Preferred MVP behaviour:

```
average_age: 0
average_investment_amount: 0
total_investments: 0
```

---

## Testing boundary

Prioritise critical logic.

Highest priority:

- CSV upload validation
- private upload storage decision
- streamed import service behaviour
- investor creation/update
- investment creation/update
- duplicate investor/date investment prevention
- aggregate calculations

Lower priority:

- exhaustive malformed CSV handling
- worker tests
- export tests
- frontend tests

Use:

```
php artisan test
```

Document any tests not run and why.

---

## README boundary

README must explain:

- what was built
- how to run locally
- database settings
- CSV input format
- API endpoints
- architecture approach
- safe upload storage decision
- synchronous MVP import decision
- worker/queue production evolution
- authentication out of scope
- incomplete work
- tests

README should be honest and concise.

Do not pretend incomplete production features exist.

---

## Time-box judgement

This is a time-boxed task.

Prefer:

- clear core path
- safe upload handling
- Laravel conventions
- thin controllers
- service-oriented business logic
- documented trade-offs
- small meaningful tests

Avoid:

- polishing non-core features
- speculative architecture
- large abstractions
- hidden complexity
- broad refactors
- scope creep

---

## Completion standard

A task is complete when:

- its issue scope is implemented
- relevant tests are added or the reason is documented
- commands run are documented
- issue has progress/completion comments
- PR is raised
- PR links to the issue
- PR body explains verification and limitations

No undocumented trade-offs.
