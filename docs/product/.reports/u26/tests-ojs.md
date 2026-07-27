# U26 Review stage & rounds — OJS Playwright suite (RUNBOOK steps 6–7)

Author: Opus 5, 2026-07-27. Fleet: OJS on 127.0.0.1:8000/8001, Postgres `ojs_test`,
shared Mailpit.

## Result

**11 tests, one per COMMON canonical scenario. Green twice, two fresh runs.**

| Run | Command | Result |
|---|---|---|
| A | `npx playwright test --config=playwright/playwright.config.js --project=ojs --reporter=list --output=<scratch>/pw-run-a` | 12 passed (11 feature + setup), **58.7 s** |
| B | same, `--output=<scratch>/pw-run-b` | 12 passed, **57.4 s** |

Both runs used 2 workers (two PHP servers, 8000/8001). `--project=shared` was re-run
afterwards (4 passed, 6.0 s) to confirm the `lib/pkp` changes did not disturb the
login smoke. No retries, no flakes across the six runs made during authoring.

Scenarios 12 (OMP) and 13 (OPS) are not implemented here — they are those apps'
suites, per multi-app rule 3.

## Files created / changed

### `lib/pkp` (shared — propagates to OMP and OPS)

| Path | Change |
|---|---|
| `classes/testing/scenario/schema/submission.json` | **new keys**: `participants[]`; reviewer `method`, `commentsForAuthor`, `commentsForEditor`, `recommendation`, `attachment`; reviewer `status` enum extended with `completed` and `confirmed`. Additive only — every existing spec still validates. |
| `api/v1/_test/PKPSubmissionScenarioController.php` | processors for the above: `applyParticipants()` / `resolveParticipantGroup()`, `resolveReviewMethod()`, `submitReview()` + `reviewStep3Form()`, `confirmReview()`, `addReviewAttachment()`, `resolveRecommendationId()`. |
| `playwright/pages/WorkflowPage.js` | **new POM** for the per-submission workflow screen (app-agnostic: modal scoping, side-menu round selection, status heading + lines, panels, action buttons, decision-wizard walk, legacy file wizard). |
| `docs/e2e/scenario-processor-audit.md` | five parity rows for the new builder paths (below). |

### App repo (`ojs-main`)

| Path | Change |
|---|---|
| `playwright/tests/review-stage-and-rounds.spec.js` | **new** — the OJS suite, 11 tests. |

No app code outside `playwright/`, `docs/` and the test-only `api/v1/_test` namespace
was touched, so there is **nothing for `app-changes.md`** (see "Proposed
app-changes entries" below).

## Scenario → test map

| Spec scenario | Test | What it drives / asserts |
|---|---|---|
| 1 Round 1 opens with the chosen files | `scenario 1 — round 1 opens with the chosen files` | Editor records **Send for Review** through the real wizard; its file step lists exactly one file, already ticked (counted, so the check cannot pass vacuously). Then: side menu gains "Review Round 1"; heading "Workflow: Review (Round 1)"; "Round 1 Status" / "Waiting for reviewers to be assigned."; `article.pdf` in "Files for Review"; "Revisions Uploaded" empty. |
| 2 Status follows reviewer activity | `scenario 2 — the round status follows reviewer activity` | Three rounds seeded at the three reviewer states (accepted / review submitted / review confirmed); each round's status line read from the editor's screen: "Awaiting responses from reviewers." → "New reviews have been submitted." → "All reviews are confirmed and a decision is needed." |
| 3 Revisions round-trip inside the round | `scenario 3 — revisions round-trip inside the round` | Seeded `requestRevisions`; editor and author both read "Revisions have been requested."; author's "Upload revisions" wizard completes; both views flip to "Revisions have been submitted and a decision is needed." with the file in "Revisions Uploaded"; **one** "Revised Version Uploaded" mail whose recipient list carries `editor.diana` AND `sectioneditor.ravi` (the "addressed to all of them together" claim). |
| 4 Resubmit leads to a new round | `scenario 4 — a resubmit request leads to a new review round` | Seeded `resubmit` → "Revisions requested from the author to be taken to a new review round."; author uploads → "Revisions submitted. A new review round needs to be created."; editor records **Create New Review Round**, whose file step offers the revision preselected; "Review Round 2" appears, selected, at "Waiting for reviewers to be assigned.", carrying the revision in its "Files for Review". |
| 5 Earlier rounds are read-only | `scenario 5 — an earlier round is read-only` | Two seeded rounds. Round 2 (control) offers Request Revisions / Accept Submission / Create New Review Round. Round 1: heading plain "Status", text "The submission has been advanced to the next round of review", **zero** buttons in the action column, round-1 reviewer listed and round-2's not, and the panel-level "Upload/Select Files" / "Add Reviewer" still offered. |
| 6 Author reads an open review | `scenario 6 — the author reads an open review` | Open review seeded complete with author comment, editor-only comment, recommendation and an attachment. Author's "Reviewers" panel lists the reviewer; "Read Review" opens name / "Completed:" / "Recommendation: Revisions Required" / the shared comment / `reviewer-attachment.pdf`, and does **not** contain the editor-only comment. Second submission, same reviewer double-anonymous: the author's round renders (status + "Revisions Uploaded" present) but has no "Reviewers" panel at all. |
| 7 Author follows the editor's messages | `scenario 7 — the author follows the editor's messages` | Before any decision mail the "Notifications" panel is absent (bounded by the round's own status heading). Editor records **Request Revisions** (stay-in-round) with the author email sent; the author's panel then lists exactly one row carrying a subject and this year's date, and clicking the subject opens the message. |
| 8 Recommendations reach the deciding editor | `scenario 8 — recommendations reach the deciding editor` | `sectioneditor.omar` seeded as a recommend-only participant: he sees Recommend Revisions / Recommend Accept / Recommend Decline, no "Accept Submission", and **no** side-column "Recommendation" box (his side column is there — "Participants" is the control). He records a recommendation; his action area then heads "Recommendation" with "Change decision", still with no side-column box. `manager.maya` (unassigned Journal Manager) sees the side-column "Recommendation" listing and status "All recommendations are in and a decision is needed." |
| 9 Cancel a fresh round | `scenario 9 — a fresh round can be cancelled` | Round 2 with an unanswered invitation: "Cancel Review Round" offered, recorded through its two-step wizard; round 2 vanishes from the side menu, round 1 is current again ("Workflow: Review (Round 1)", heading "Round 1 Status"). Second submission whose reviewer accepted: the decision is not offered, with "Accept Submission" as the positive control. |
| 10 Decline and revert in review | `scenario 10 — decline and revert in review` | `sectioneditor.ravi` (Section editor) records **Decline Submission** → "Submission declined.", action list collapses to "Revert Decline" (Accept / Request Revisions gone), and **no "Delete"** for him. `manager.maya` sees "Delete" beside "Revert Decline". Reverting restores the computed status ("Waiting for reviewers to be assigned.") and "Accept Submission". |
| 11 Curating Files for Review mid-round | `scenario 11 — curating Files for Review mid-round` | Round opened through the real Send-for-Review wizard so it has a review file. "Current Review Files For Round 1" lists that file only; ticking "Show files from all accessible workflow stages." reveals the submission-stage file; ticking it and saving puts a **copy** (a new file id) on the panel; the copy is unticked on reopen (Rule 17's "not yet released"); ticking it releases it; unticking it again withdraws it while both files stay listed on the panel and in the modal. |

### Coverage the suite declares it does not have

Written into the spec file's header comment: OMP/OPS scenarios; every 🐞 register
finding (A1, A5, A6, A7, A8) — asserted nowhere, because a test would freeze the
defect as contract; the open ❓ items (A2, A3, A4); rules with no canonical scenario
(Rule 9's minimum-reviews prompt, Rule 3's reviewer-suggestions panel, Rule 18's
return from Copyediting, the status rows no scenario walks); and the neighbouring
features' own mechanics (U24, U27, U28, U30, U34, U35, U36, U37).

Note on scenario 4 and scenario 11: both walk *through* a state a 🐞 describes
(A1's vanished upload button; A8's indistinguishable release state). The tests
assert the rule around each — the status text and the new round in 4, the modal's
checkbox state in 11 — and say nothing about the defect either way.

## Contradictions with the spec

**None.** Every rule the eleven scenarios exercise behaved as the spec says,
including the exact status strings, the past-round heading and wording, the
decision-button rosters per role and state, the panel rosters for editor and
author, the anonymous-review exclusion, and the mail recipients.

Two observations that are *not* contradictions but are worth a reviewer's eye:

1. **Rule 17 / scenario 11 — the round's OWN review file is also unreleased.**
   The spec says a file added through the select modal "arrives not yet released
   to reviewers". Live, the file promoted into the round by the **Send for Review
   decision** is equally unticked in the select modal — the whole round opened with
   nothing released. The spec does not claim otherwise (it is silent on the
   decision-promoted file), so this is not a contradiction; but if the team wants
   Rule 17 to describe the round's starting state, that sentence needs adding, and
   it plausibly widens A8's user impact (a round can open with review files listed
   and none of them actually reaching reviewers). The test was written to be
   correct either way: it identifies the copy by file id rather than assuming
   which boxes start ticked.

2. **Rule 11 / scenario 8 — the side-column listing shows the DECISION label.**
   The "Recommendation" box lists "Request Revisions" (the recommendation
   decision's label), not the reviewer-recommendation vocabulary
   ("Revisions Required"). The spec says the box "list[s] every recommendation
   recorded for the stage" without naming the wording, so nothing is wrong; noting
   it because the two vocabularies are one screen apart and a QA reader could
   reasonably expect the other one.

No permission-behaviour contradiction arose in this suite: every role-gated
assertion (recommend-only vs deciding editor, Section Editor vs Journal Manager on
"Delete", author vs editor panels, anonymous review invisibility) matched the spec.

## Proposed `app-changes.md` entries

**None.** No app defect blocked green, no workaround was needed in app code, and
nothing outside `playwright/`, `docs/` and the test-only `api/v1/_test` namespace
changed. Two harness facts that cost time but are ordinary UI behaviour, recorded
here rather than there:

- The decision wizard ends in a success dialog, not a redirect; the POM waits for
  that dialog's link and then re-opens the workflow from its own URL.
- The file-upload wizard validates the article component server-side as the file
  lands, so the component must be selected **before** `setInputFiles`.

## Scenario-endpoint keys added (for the OMP / OPS authors)

All additive; all in `lib/pkp`, so OMP and OPS get them by re-pinning the
submodule. Parity rows are already in `docs/e2e/scenario-processor-audit.md`.

### `submission.json` — new top-level key

```jsonc
"participants": [
  {
    "user": "sectioneditor.omar",   // required, username
    "role": "sectionEditor",        // optional role key; default = the user's first group in the context
    "recommendOnly": true           // optional; the Participants panel's "Recommend only" toggle
  }
]
```

Applied after submit and **before** the spec's decisions, so a recommend-only
participant is in place for every round the decisions open. Processor:
`applyParticipants()` → `Repo::stageAssignment()->build(...)`, plus a
`recommendOnly` update when the submission already carries an assignment for that
user (a real submit auto-assigns the section/series editors, and `build()` is
`firstOr` — without the update the flag would be silently dropped). A `role` the
user is not enrolled in **throws**: seeding never enrols a shared user in a new
role (PRINCIPLES 7).

*OMP/OPS note*: works unchanged — role keys resolve through
`resolveUserGroup()`'s `default.groups.name.*` lookup, so `seriesEditor`-style
groups an app ships resolve the same way. On OPS, `sectionEditor` is the Moderator
group.

### `submission.json` — new per-reviewer keys (`reviewRounds[].reviewers[]`)

```jsonc
{
  "user": "reviewer.julia",
  "status": "confirmed",            // invited | accepted | declined | completed | confirmed
  "method": "open",                 // open | anonymous | doubleAnonymous; default = context defaultReviewMode
  "commentsForAuthor": "…",         // shared with the author; needs a completed status
  "commentsForEditor": "…",         // editor-only; needs a completed status
  "recommendation": "Revisions Required",  // localized title of a context recommendation option
  "attachment": true                // a reviewer file on the review
}
```

- **`completed`** runs the application's own step-3 reviewer form
  (`APP\submission\reviewer\form\ReviewerReviewStep3Form` when the app ships one,
  else the PKP base), read and executed exactly as `PKPReviewerHandler::saveStep()`
  does after validation — so the comment rows, recommendation, completion stamp,
  editors' notification and event-log entry are the real ones.
- **`confirmed`** additionally reproduces `PKPReviewerGridHandler::reviewRead()`'s
  data changes (considered + `dateConsidered`, `reviewConfirmed` log entry,
  reviewer task cleared). The op itself cannot be invoked (CSRF-checked handler
  over an authorized context object). Deliberately omitted and recorded: the
  reviewer rating, and the ORCID deposit job (external service, egress firewalled).
- **`recommendation`** resolves against
  `Repo::reviewerRecommendation()->getRecommendationOptions($context)`. **OMP
  authors: this will throw on a press** — OMP ships no recommendation options
  (register entry OMP4). The exception names the available options, which is the
  honest signal; an OMP scenario-6 equivalent should simply omit the key and assert
  the absent recommendation line instead.
- **`attachment`** writes a `SUBMISSION_FILE_REVIEW_ATTACHMENT` associated with the
  assignment, uploaded as the reviewer before the review is submitted.
- **`method`** is per assignment, so a suite no longer needs a scratch context just
  to get an open review — which is why the whole OJS suite runs on
  `publicknowledge` with its own submissions and creates no scratch journal.

### Shared POM

`lib/pkp/playwright/pages/WorkflowPage.js` — `gotoEditorial` / `gotoAuthor`,
`openRound(n)`, `statusHeading(name)` / `statusLines(name)`, `panel(title)` /
`panelTable(title)`, `action(name)`, `primaryItems` / `actionItems` /
`secondaryItems`, `recordDecision(label, {revisions, onStep})` and
`completeFileWizard(path, {component})`. It names no app, stage id or app-specific
label; OMP/OPS suites pass their own context path and assert their own wording
(the round heading wait keys on `(Round N)`, which every app's heading carries).

Two facts it encodes that any app suite will otherwise rediscover:

1. `[data-cy="active-modal"]` marks only the **topmost** dialog — while a
   sub-dialog is open the workflow screen loses the attribute.
2. The round status is an `h3` plus its **sibling** paragraphs, so a
   minimum-reviews prompt line and the status line come back as an ordered list.

## Suite conventions worth carrying over

- Tags are `u26ojs<scenario>w<parallelIndex><random>` — per app, per worker, per
  run, one hyphenless alphanumeric token; every Mailpit read is
  `find({to, contains: tag, subject})` and the inbox is never cleared.
- Every submission is filed under section **REV** (Reviews), whose seeded editors
  are `editor.diana` and `sectioneditor.ravi` — that leaves `sectioneditor.omar`
  unassigned by the submit-time auto-assignment and therefore free to carry the
  recommend-only flag. The equivalent choice on OMP/OPS is a grouping whose seeded
  editor roster excludes the account the recommend-only test needs.
- `publicknowledge` and the seeded accounts are read-only throughout: the suite
  creates only its own submissions and their files, and mutates no context
  setting, so it needs no scratch context at all.
