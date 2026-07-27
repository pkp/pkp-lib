# U26 Review stage & rounds — OMP Playwright suite (RUNBOOK steps 6–7)

Author: Opus 5, 2026-07-27. Fleet: OMP on 127.0.0.1:8100/8101, Postgres `omp_test`,
shared Mailpit. Derivation reference: the OJS suite + `tests-ojs.md`; nothing was
transplanted literally (RUNBOOK multi-app rules 2–3).

## Result

**12 tests — the 11 COMMON canonical scenarios in OMP's own context, plus
scenario 12 (the two doors into External Review). Green twice, two fresh runs.**

| Run | Command | Result |
|---|---|---|
| A | `npx playwright test --config=playwright/playwright.config.js --project=omp --reporter=list --output=<scratch>/pw-run-a` | 13 passed (12 feature + setup), **1.1 min** |
| B | same, `--output=<scratch>/pw-run-b` | 13 passed, **1.1 min** |

Both runs used 2 workers (two PHP servers, 8100/8101). No retries, no flakes:
the suite also passed on its first authoring run, three green runs total.
Scenario 13 (OPS) is not implemented here — it is that app's suite.

## Files created / changed

### App repo (`omp-main`) — the only repo touched

| Path | Change |
|---|---|
| `playwright/tests/review-stage-and-rounds.spec.js` | **new** — the OMP suite, 12 tests. |
| `api/v1/_test/SubmissionScenarioController.php` | **widened the `reviewRounds` schema overlay** so OMP accepts the U26 per-reviewer keys. See "Shared-code gaps" — this is the app-local work-around for a shared-layer sharp edge, in the test-only API namespace. |

`omp-main/lib/pkp` was **not** modified (`git status` clean inside the submodule);
no app code outside `playwright/` and the test-only `api/v1/_test` namespace was
touched, so there is nothing for `app-changes.md`.

## Scenario → test map

Vocabulary throughout: **press**, **monograph**, **External Review**, **Series
Editor** (`APP-GLOSSARY.md`). Every monograph is filed under the `textbooks`
series, whose seeded series editors are `editor.diana` and `sectioneditor.ravi` —
the OMP analogue of the OJS suite's section REV, chosen for the same reason: it
leaves `sectioneditor.omar` unassigned and therefore free to carry scenario 8's
"Recommend only" flag. External reviewers are `reviewer.julia` / `reviewer.paul`
(OMP's split reviewer groups: `amara`/`adam` reach Internal Review only).

| Spec scenario | Test | What it drives / asserts in OMP's context |
|---|---|---|
| 1 Round 1 opens with the chosen files | `scenario 1 — round 1 opens with the chosen files` | Press Editor records **Send to External Review** from the Submission stage through the real wizard; its Select Files step lists exactly one file, already ticked (counted, so the check cannot pass vacuously). Then: "Review Round 1" appears under **External Review**; heading "Workflow: External Review (Round 1)"; "Round 1 Status" / "Waiting for reviewers to be assigned."; `article.pdf` in "Files for Review"; "Revisions Uploaded" empty. |
| 2 Status follows reviewer activity | `scenario 2 — the round status follows reviewer activity` | Three monographs seeded at the three external-reviewer states (accepted / review submitted / review confirmed); each round's status line read from the editor's screen: "Awaiting responses from reviewers." → "New reviews have been submitted." → "All reviews are confirmed and a decision is needed." |
| 3 Revisions round-trip inside the round | `scenario 3 — revisions round-trip inside the round` | Seeded `requestRevisions`; editor and author both read "Revisions have been requested."; the author's "Upload revisions" wizard completes (component **Book Manuscript**, OMP's manuscript genre); both views flip to "Revisions have been submitted and a decision is needed." with the file in "Revisions Uploaded"; **one** "Revised Version Uploaded" mail whose recipient list carries `editor.diana` AND `sectioneditor.ravi` (the "addressed to all of them together" claim). |
| 4 Resubmit leads to a new round | `scenario 4 — a resubmit request leads to a new review round` | Seeded `resubmit` → "Revisions requested from the author to be taken to a new review round."; author uploads → "Revisions submitted. A new review round needs to be created."; editor records **Create New Review Round**, whose file step offers the revision preselected; "Review Round 2" appears under External Review, selected, at "Waiting for reviewers to be assigned.", carrying the revision in its "Files for Review". |
| 5 Earlier rounds are read-only | `scenario 5 — an earlier round is read-only` | Two external rounds. Round 2 (control) offers Request Revisions / Accept Submission / Create New Review Round. Round 1: heading plain "Status", text "The submission has been advanced to the next round of review", **zero** buttons in the action column, round-1 reviewer listed and round-2's not, and the panel-level "Upload/Select Files" / "Add Reviewer" still offered. |
| 6 Author reads an open review | `scenario 6 — the author reads an open review` | Open review seeded complete with an author comment, an editor-only comment and an attachment — **no `recommendation`**, see [OMP4] below. The author's "Reviewers" panel lists the reviewer; "Read Review" opens name / "Completed:" / the shared comment / `reviewer-attachment.pdf`, and does **not** contain the editor-only comment. Second monograph, same reviewer double-anonymous: the author's round renders (status + "Revisions Uploaded" present) but has no "Reviewers" panel at all. |
| 7 Author follows the editor's messages | `scenario 7 — the author follows the editor's messages` | Before any decision mail the "Notifications" panel is absent (bounded by the round's own status heading). Editor records **Request Revisions** (stay-in-round) with the author email sent; the author's panel then lists exactly one row carrying a subject and this year's date, and clicking the subject opens the message. |
| 8 Recommendations reach the deciding editor | `scenario 8 — recommendations reach the deciding editor` | `sectioneditor.omar` (Series Editor of the *other* series) seeded as a recommend-only participant: he sees Recommend Revisions / Recommend Accept / Recommend Decline, no "Accept Submission", and **no** side-column "Recommendation" box (his side column is there — "Participants" is the control). He records a recommendation; his action area then heads "Recommendation" with "Change decision", still with no side-column box. `manager.maya` (unassigned Press Manager) sees the side-column "Recommendation" listing and status "All recommendations are in and a decision is needed." |
| 9 Cancel a fresh round | `scenario 9 — a fresh round can be cancelled` | Three monographs. (a) External round 2 with an unanswered invitation: "Cancel Review Round" offered, recorded through its wizard; round 2 vanishes from External Review's menu, round 1 is current again. (b) A monograph whose reviewer accepted: the decision is not offered, with "Accept Submission" as the positive control. (c) **OMP's own landing** [OMP1]: cancelling External Review's Round 1 on a monograph that came through Internal Review removes the external round and lands on "Workflow: Internal Review (Round 1)". |
| 10 Decline and revert in review | `scenario 10 — decline and revert in review` | `sectioneditor.ravi` (Series Editor) records **Decline Submission** → "Submission declined.", action list collapses to "Revert Decline" (Accept / Request Revisions gone), and **no "Delete"** for him. `manager.maya` sees "Delete" beside "Revert Decline". Reverting restores the computed status ("Waiting for reviewers to be assigned.") and "Accept Submission". |
| 11 Curating Files for Review mid-round | `scenario 11 — curating Files for Review mid-round` | Round opened through the real Send-to-External-Review wizard so it has a review file. "Current Review Files For Round 1" lists that file only; ticking "Show files from all accessible workflow stages." reveals the submission-stage file; ticking it and saving puts a **copy** (a new file id) on the panel; the copy is unticked on reopen (Rule 17's "not yet released"); ticking it releases it; unticking it again withdraws it while both files stay listed on the panel and in the modal. |
| **12 {OMP} Two doors into External Review** | `scenario 12 — the two doors into External Review` | Door 1 (Submission stage): "Send to External Review" stands beside the separate "Send to Internal Review", and its Select Files step offers the monograph's file pre-ticked; recording it opens External Review Round 1 at "Waiting for reviewers to be assigned." with `article.pdf` in "Files for Review". Door 2 (Internal Review stage): an action with the **byte-identical label** "Send to External Review" [OMP2] — the two are told apart only by the stage they are offered on; recording it opens External Review Round 1 at the same heading and the same starting status, with the same panel roster ("Files for Review", "Revisions Uploaded", "Reviewers") and the same five decisions on offer, i.e. every common scenario runs from here unchanged. What this door does about *files* is [OMP3] and is deliberately not asserted. |

### Coverage the suite declares it does not have

Written into the spec file's header comment: scenario 13 (OPS); every 🐞 register
finding (A1, A5, A6, A7, A8) — asserted nowhere, because a test would freeze the
defect as contract; the open ❓ items (A2, A3, A4, **OMP3**, **OMP4**); Internal
Review as a stage in its own right (out of campaign scope — it appears only as
scenario 12's second door and scenario 9's cancel landing); rules with no
canonical scenario (Rule 9's minimum-reviews prompt, Rule 3's
reviewer-suggestions panel, Rule 18's return from Copyediting, the status rows no
scenario walks); and the neighbouring features' own mechanics (U24, U27, U28,
U30, U34, U35, U36, U37 — including the "Author Response" panel OMP does not
mount at all, [OMP5]).

Scenarios 4, 11 and 12 each walk *through* a state a register entry describes
(A1's vanished upload button; A8's indistinguishable release state; OMP3's
file-less internal door). Each test asserts the rule around it — the status text
and the new round in 4, the modal's checkbox state in 11, the round's opening
state and decision roster in 12 — and says nothing about the finding either way.

**One deliberate divergence from the OJS author's handover note.** `tests-ojs.md`
suggested an OMP scenario 6 should "omit the key and assert the absent
recommendation line instead". This suite omits the key but does **not** assert
the absent line: [OMP4] is an OPEN ❓ ("are presses meant to run review without
recommendations by default?", lean: defect). Asserting the absence would freeze
the unsettled answer as contract exactly the way a 🐞 assertion would, and the
standing constraint says a claim parked on an open ❓ is not a coverage gap. If
the team settles OMP4 as ✅ "presses ship none by design", the assertion becomes
correct to add and is a one-line change in scenario 6.

## Contradictions with the spec

**None.** Every rule the twelve scenarios exercise behaved as the spec says once
read through `APP-GLOSSARY.md` — the exact status strings (identical to OJS's,
verbatim), the past-round heading and wording, the decision-button rosters per
role and state, the panel rosters for editor and author, the anonymous-review
exclusion, the mail recipients, and both entry doors' shared label.

Four observations that are *not* contradictions:

1. **[OMP5] is visible in the panel roster, exactly as recorded.** The editorial
   round shows "Round N Status", "Revisions Uploaded", "Files for Review",
   "Reviewers", "Review Tasks & Discussions" + side-column "Participants"; the
   author's shows "Round N Status", "Revisions Uploaded", "Review Tasks &
   Discussions" and no side column. No "Author Response" anywhere, as [OMP5] (✅)
   says. The suite asserts these rosters positively in scenarios 5, 6 and 12.
2. **[OMP1]'s landing is confirmed live** (scenario 9c): with an internal round
   present, cancelling external Round 1 lands on Internal Review. Note for the
   spec reader: scenario 9's parenthetical is written OJS-first ("cancelling
   Round 1 lands on the Submission stage instead [OMP1]"), which reads
   backwards on a press — on OMP the Submission landing is only the *no internal
   rounds* case. The register entry states it correctly; only the scenario's
   parenthetical is compressed. Not a behaviour contradiction, a wording nit.
3. **Rule 11 / scenario 8 — the side-column listing shows the DECISION label** on
   OMP too ("Request Revisions", not reviewer-recommendation vocabulary). Same
   observation the OJS author filed; both apps behave alike, and the spec names
   no wording, so nothing is wrong.
4. **The legacy "Current Review Files For Round N" modal renders
   `##common.help##`** on OMP — an untranslated locale key in that modal's help
   link (OJS renders it fine). Cosmetic, outside U26's rules, and not asserted
   anywhere; recorded here because it was seen live and belongs to whoever owns
   the legacy grid modals.

No permission-behaviour contradiction arose: every role-gated assertion
(recommend-only vs deciding editor, Series Editor vs Press Manager on "Delete",
author vs editor panels, anonymous-review invisibility) matched the spec, so
nothing routes to step 8 from this suite.

## Shared-code gaps (for the OJS-side checkout)

Both are in `lib/pkp` and were worked around app-locally here, per the brief.

### 1. An app overlay silently NARROWS a shared schema property — blocking

`omp-main/api/v1/_test/SubmissionScenarioController.php` redeclares the whole
`reviewRounds` property to add OMP's `stage` key, because `SpecValidator::
withOverlay()` merges overlay properties **over** the core schema (replace, not
deep-merge). When U26 grew the shared `reviewRounds[].reviewers[]` shape
(`method`, the `completed`/`confirmed` statuses, `commentsForAuthor`,
`commentsForEditor`, `recommendation`, `attachment`), OMP's copy kept the old
shape — so every new key was live in the shared **processor** and rejected by
OMP's **validator** with a 400. Every OMP test needing an open or completed
review was blocked.

*Worked around app-locally*: the overlay now mirrors the full shared reviewer
shape (with press-worded descriptions and an explicit note that a key added to
the shared schema must be mirrored here). OPS is unaffected — it rejects
`reviewRounds` outright.

*For the OJS-side checkout*, one of:

- make `withOverlay()` merge nested `properties` recursively so an overlay adds
  keys instead of replacing the object (the fix that removes the class of bug);
  **or**
- have the app subclass declare only the DELTA (`reviewRounds.items.properties.
  stage`) through a path-addressed overlay API; **or**
- at minimum, a schema-parity guard: fail loudly when an overlay redeclares a
  core property and drops keys the core schema declares.

Whichever is chosen, `scenario-processor-audit.md` should carry the note that
the OMP overlay is a full copy until then, since the next shared reviewer key
will break OMP the same way.

### 2. `WorkflowPage.openRound()` / `menuItem()` are stage-blind

The shared POM addresses a round by its side-menu label alone. OMP has **two**
review stages, so a monograph that has been through internal review carries a
"Review Round 1" treeitem under Internal Review *and* under External Review —
`getByRole('treeitem', {name: 'Review Round 1', exact: true})` resolves to 2
elements (probed: `countRound1 = 2`). The heading wait is equally ambiguous,
since `(Round 1)` matches both stages' headings.

*Worked around app-locally*: a small `PressWorkflowPage extends WorkflowPage` in
the OMP spec adds `stageItem(stage)` / `roundItem(round, stage)` and overrides
`openRound(round, stage = 'External Review')`, waiting on
`/<stage> \(Round N\)/`. It relies on two DOM facts worth carrying into the
shared POM: the stage treeitem's accessible name is the **stage name alone**
(`aria-label`) even though the node contains its rounds, and the round treeitem
is a DOM **descendant** of it (`aria-level` 2).

*For the OJS-side checkout*: give the shared POM an optional stage scope —
`openRound(round, {stage})` / `roundItem(round, {stage})` — defaulting to the
unscoped behaviour so the OJS and OPS suites are unaffected. That is a strictly
additive change and would let the OMP suite drop its local subclass.

### Two shared facts confirmed unchanged on OMP

Recorded so the next app author does not re-derive them: the panel tables carry
their title via `aria-labelledby` (so `panelTable(title)` works on OMP even
though the tables have no `aria-label`), and a scenario-seeded round has **no**
review files — the seeding decision is recorded without the wizard's file-promotion
step — which is why scenarios 1, 11 and 12 open their round through the real
decision wizard rather than a seeded one.

## Proposed `app-changes.md` entries

**None.** No app defect blocked green, no work-around touched app code: the one
file changed outside `playwright/` is `api/v1/_test/SubmissionScenarioController.php`,
which lives in the test-only API namespace the OJS author's report already
treats as out of the ledger's scope. The `##common.help##` locale gap in the
legacy select-files modal is a real OMP UI defect but did not block anything and
is not a build blocker; it is recorded under "Contradictions" observation 4 for
whoever owns that screen.

## Suite conventions

- Tags are `u26omp<scenario>w<parallelIndex><random>` — per app, per worker, per
  run, one hyphenless alphanumeric token; every Mailpit read is
  `find({to, contains: tag, subject})` and the inbox is never cleared.
- `publicknowledge` (the press) and the seeded accounts are read-only throughout:
  the suite creates only its own monographs and their files and mutates no press
  setting, so it needs no scratch press.
- Series `textbooks` is the suite's filing series precisely because its seeded
  editor roster (`editor.diana`, `sectioneditor.ravi`) excludes the account
  scenario 8 needs for the recommend-only assignment.
- The manuscript genre in every file wizard is **Book Manuscript** (OMP's
  counterpart to OJS's "Article Text"), passed explicitly to
  `completeFileWizard()`.
