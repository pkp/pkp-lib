# Parity spot-check: UI wizard submission vs scenario-seeded twin (OJS)

Step-1 harness acceptance (PROGRESS restart step 1; PRINCIPLES "Rebuild acceptance").
Question: does `POST /api/v1/_test/scenarios/submission` leave the same database
state as a user completing the submission wizard through the UI?

Date: 2026-07-31

## Environment

- App: OJS at `/Users/jarda/git/pkp/pkp-main/ojs-main`, served at `http://127.0.0.1:8000` (pre-existing `php -S`, `config.test.inc.php`).
- DB: PostgreSQL `ojs_test` (empty of submissions before this check — the two submissions below are ids 1 and 2).
- Journal: `publicknowledge` (context_id 1). Submitter: `author.alex` (user_id 15).
- UI run: real Chromium via Playwright (script in session scratchpad, not committed), full wizard: start page (title, section "Articles", both checklist/privacy boxes) → Upload Files (one `.txt`, genre "Article Text") → Details (abstract; keywords left empty — wizard marks them optional) → Contributors (pre-filled Alex Author) → For the Editors (empty, optional) → Review → Submit → confirm dialog → "Submission complete".
- Scenario run: `curl POST .../_test/scenarios/submission` with `{"tag":"parw0ui1scn","context":"publicknowledge","submitter":"author.alex","section":"ART","title":"Parity scenario twin parw0ui1scn"}` → `{"submissionId":2,"publicationId":2,"stageId":1,"status":1,"submissionProgress":""}`.

## Submission ids

| | id | title |
|---|---|---|
| UI wizard | **1** | Parity UI submission parw0ui1 |
| Scenario | **2** | Parity scenario twin parw0ui1scn |

Both left in the DB (they carry the tags).

## Seed-data note (not a parity issue, but fragile)

The journal has **two active sections both named "Articles" with abbrev `ART`**
(section_id 1 and 2; section 2 also carries fr_CA settings). The wizard shows two
identical "Articles" radios (values 1 and 2); the UI run picked the first (1) and the
scenario's `"section":"ART"` lookup also resolved to section_id 1, so parity holds —
but only by both defaulting to the lower id. The bootstrap seed should not create two
same-abbrev sections, or the scenario key should be documented as "first match".

## Comparison table

| Field | UI (sub 1) | Scenario (sub 2) | Match |
|---|---|---|---|
| submissions.stage_id | 1 (Submission) | 1 | yes |
| submissions.status | 1 (STATUS_QUEUED) | 1 | yes |
| submissions.submission_progress | '' (empty = complete) | '' | yes |
| submissions.date_submitted IS NOT NULL | true | true | yes |
| submissions.locale | en | en | yes |
| submissions.current_publication_id | 1 | 2 | yes (own pub) |
| publications.section_id | 1 | 1 | yes |
| publications.status | 1 | 1 | yes |
| publications.primary_contact_id IS NOT NULL | **true (author 1)** | **false (NULL)** | **NO** |
| publication_settings.title (en) | set | set | yes |
| publication_settings.abstract (en) | **set** | **absent** | **NO** |
| authors count / email | 1 / author.alex@mail.test | 1 / author.alex@mail.test | yes |
| authors seq / include_in_browse / settings | 0 / 1 / givenName+familyName en | same | yes |
| stage_assignments | 4 rows: editor.diana (Journal editor, grp 3, ccm=1), sectioneditor.ana + sectioneditor.omar (Section editor, grp 5, ccm=1), author.alex (Author, grp 14, ccm=0); recommend_only=0 all | identical 4 rows | yes |
| event_log | metadataUpdated ×3, fileRevised ×1, submissionSubmitted ×1 (all user 15) | metadataUpdated ×2, submissionSubmitted ×1 | **no — expected** |
| notifications | SUBMISSION_SUBMITTED (16777217) ×3 to users 3, 4, 6; **plus APPROVE_SUBMISSION (16777243) ×1 and FORMAT_NEEDS_APPROVED_SUBMISSION (16777245) ×1** (user_id NULL, context 1) | SUBMISSION_SUBMITTED ×3 to users 3, 4, 6 only | **NO** |
| edit_tasks (discussions; no `queries` table on this schema) | 0 | 0 | yes |
| review_rounds count | 0 | 0 | yes |
| submission_files count | **1** | **0** | **no — expected** |

## Differences and verdicts

1. **publications.primary_contact_id — PARITY DEFECT (must fix).**
   UI sets the submitting author as primary contact (pub 1 → author_id 1); the
   scenario leaves it NULL. Visible downstream: the Contributors list's "Primary
   Contact" badge, primary-contact email flows, and pre-publish validation. Any spec
   asserting primary-contact behaviour on a scenario-seeded submission diverges from
   a real one. Fix in the scenario processor: set `primary_contact_id` to the seeded
   author row, as the wizard does.

2. **Missing APPROVE_SUBMISSION + FORMAT_NEEDS_APPROVED_SUBMISSION notifications — PARITY DEFECT (must fix).**
   The real submit endpoint ends by calling
   `NotificationManager::updateNotification(..., [NOTIFICATION_TYPE_APPROVE_SUBMISSION], ...)`
   (`lib/pkp/api/v1/submissions/PKPSubmissionController.php` ~line 921), whose
   delegate creates both unpublished-state notifications (types 16777243 / 16777245,
   user_id NULL, visible to all workflow users). The scenario path skips this, so
   scenario-seeded submissions reach the workflow without the "approve submission"
   notices that drive the publication-approval UI state. Fix: invoke the same
   NotificationManager call (or the delegate) after scenario submit.

3. **publication_settings.abstract absent on the scenario twin — PARITY DEFECT (minor).**
   The wizard marks Abstract "* Required" for this section, so every real submission
   has one; the scenario's step-2 core seeds title only. A scenario-seeded submission
   is therefore in a metadata state no UI user could produce, and abstract-dependent
   validation/display (e.g. publish-time checks, workflow metadata panels) differs.
   Fix options: seed a default abstract in the core, or accept an `abstract` key and
   have fixture builders always pass one.

4. **Extra event_log rows on the UI submission — ACCEPTABLE DEVIATION (expected, confirmed).**
   UI: `metadataUpdated ×3 + fileRevised ×1 + submissionSubmitted`; scenario:
   `metadataUpdated ×2 + submissionSubmitted`. The extra metadataUpdated and the
   fileRevised row come from wizard step autosaves and the file upload. Log noise
   only; no state machine depends on the count. (No `copyrightAgreed` row on either —
   that branch of the submit endpoint did not trigger on this journal.)

5. **submission_files: 1 (UI) vs 0 (scenario) — ACCEPTABLE DEVIATION (expected, confirmed; documented limitation).**
   The wizard requires a file upload; the scenario's step-2 core seeds none. Known
   and pre-declared. Specs that need files on a scenario submission must seed them
   explicitly; the limitation must stay documented in the scenario schema docs.

Everything else tabulated above is identical, including the full stage-assignment set
(AssignEditors ran for both: editor.diana + both section editors assigned, author
assignment with can_change_metadata=0) and the three SUBMISSION_SUBMITTED
notifications to editor.diana, sectioneditor.ana, sectioneditor.omar.

Nothing observed suggested a security weakness; nothing was routed to the private
security file for this check.

## Proposed parity-ledger rows (for lib/pkp/docs/e2e/scenario-processor-audit.md)

| Date | App | Scenario | Field | UI | Scenario | Verdict | Status |
|---|---|---|---|---|---|---|---|
| 2026-07-31 | OJS | submission (submitted) | publications.primary_contact_id | seeded author | NULL | parity-defect | open |
| 2026-07-31 | OJS | submission (submitted) | notifications APPROVE_SUBMISSION (0x100001B) + FORMAT_NEEDS_APPROVED_SUBMISSION (0x100001D) | created (user NULL, per context) | missing | parity-defect | open |
| 2026-07-31 | OJS | submission (submitted) | publication_settings.abstract | set (wizard-required) | absent | parity-defect (minor) | open |
| 2026-07-31 | OJS | submission (submitted) | event_log wizard autosaves (metadataUpdated ×1 extra, fileRevised ×1) | present | absent | acceptable-deviation | closed |
| 2026-07-31 | OJS | submission (submitted) | submission_files | 1 (wizard requires upload) | 0 (core seeds none) | acceptable-deviation (documented) | closed |
| 2026-07-31 | OJS | submission (submitted) | all other audited fields (submissions, publications core, authors, stage_assignments, SUBMISSION_SUBMITTED notifications, edit_tasks, review_rounds) | — | — | identical | closed |
