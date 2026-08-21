# U26 probe report — Group I (items 1–2)

Agent: probe, RUNBOOK step 3. Date: 2026-07-27. Fleets driven live at
OJS `http://127.0.0.1:8000`, OMP `http://127.0.0.1:8100`, OPS
`http://127.0.0.1:8200` (the `localhost` host name 400s on all three — the
running servers answer on `127.0.0.1` only; incidental, harness context).

All state was seeded through `POST /api/v1/_test/scenarios/submission` on each
fleet's seeded `publicknowledge` context with unique tags; no seeded submission
or account was modified. Every claim below was observed on the live screen by
driving Chromium (Playwright `chromium` from `ojs-main/node_modules`, real UI
login per user). Marker convention: **CLAIM** = observation the item asked for,
promotable to the spec; **context** = incidental DOM/behaviour noted in passing.

Common locators used throughout:

- workflow modal: `page.locator('[role="dialog"]').last()`
- stage side menu: `<dialog>.locator('nav')` (`innerText`)
- selected-stage heading: `<dialog>.locator('h2').first()`
- decision buttons: `<dialog>.locator('[data-cy="workflow-action-items"]')`
- round panels: `<dialog>.locator('[data-cy="workflow-primary-items"]')`
- participants: `<dialog>.locator('[data-cy="workflow-secondary-items"]')`
- editorial workflow URL: `/{context}/en/dashboard/editorial?workflowSubmissionId={id}`
- author workflow URL: `/{context}/en/dashboard/mySubmissions?workflowSubmissionId={id}`

---

## Item 1 — OPS absence (spec absence paragraph + scenario 13) — SETTLED

### What was driven

| App | Role / account | State |
|---|---|---|
| OPS | Preprint Server Manager (`manager.maya`) | scratch preprint tag `u26g1ops1`, submission 3, submitter `author.alex`, section `PRE`, no decisions (server put it at `stageId: 5`, Production) |
| OPS | Author (`author.alex`, the submitter) | same preprint, author dashboard view |
| OJS | Editor (`editor.diana`) | positive control: scratch submission tag `u26g1ojs1`, submission 15, `decisions: [sendExternalReview]` |

### Observations

**1.1 CLAIM — OPS manager side menu offers Production only.**
Locator: `[role="dialog"] nav` innerText on
`/publicknowledge/en/dashboard/editorial?workflowSubmissionId=3`.
Observed, verbatim and in order:

```
Workflow
Production
Preprint
Unassigned version (2026-07-27)
Title & Abstract
Contributors
Metadata
References
Galleys
Media
Permissions & Disclosure
Preprint entry
Create New Version
```

The `Workflow` group holds exactly one item, `Production`. No `Review`, no
`Internal Review`, no `Review Round N` item anywhere in the menu. Selected-stage
heading (`h2`): **`WORKFLOW: PRODUCTION`**.

**1.2 CLAIM — no round panels, no reviewer panel on the OPS manager screen.**
Locator: `[data-cy="workflow-primary-items"]`. Observed content is exactly one
panel, `Production Tasks & Discussions` (plus the `Current Submission Language:
English` line). No `Round N Status`, no `Files for Review`, no `Revisions
Uploaded`, no `Reviewers` panel.

**1.3 CLAIM — no review decision anywhere on the OPS manager screen.**
Locator: `[data-cy="workflow-action-items"]`. Observed, verbatim:
`Post the preprint`, `Decline Submission` — nothing else. A case-insensitive
scan of the whole dialog's `innerText` for `review` returned a single hit, and
it is the substring inside the header button **`Preview`** — no standalone
review vocabulary on the screen.

**1.4 CLAIM — the OPS author screen likewise has no review surface.**
Locator: `[role="dialog"] nav` on
`/publicknowledge/en/dashboard/mySubmissions?workflowSubmissionId=3` as
`author.alex`. Observed:

```
Preprint
Unassigned version (2026-07-27)
Title & Abstract
Contributors
Metadata
References
Galleys
Media
Production Tasks & Discussions
```

The author's menu has **no `Workflow` group at all** (context: the author view
lists the publication items plus one discussions item; it opens on
`h2` = `PREPRINT: TITLE & ABSTRACT`, i.e. a metadata panel, not a stage).
`[data-cy="workflow-action-items"]` is absent; `workflow-controls-left` shows
`Status: Unposted` + `Relations`. A `review` scan of the author dialog returned
**0** hits.

**1.5 CLAIM — server side agrees with the screen (OPS).**
As `manager.maya`, `GET /publicknowledge/api/v1/submissions/3` returns
`availableEditorialDecisions: [{"stageId":5,"id":8,"label":"Decline Submission"}]`
and empty `reviewAssignments` / `reviewRounds`. Posting review decisions to
`POST /publicknowledge/api/v1/submissions/3/decisions` with a live authenticated
session (CSRF token from `window.pkp.currentUser.csrfToken`; positive control:
the same context's `GET .../submissions/3` returned 200) was refused for both
`decision: 3` (EXTERNAL_REVIEW) and `decision: 14` (NEW_EXTERNAL_ROUND):

```
{"error":"editor.submission.workflowDecision.typeInvalid",
 "errorMessage":"This decision could not be found. Please provide a recognized decision type."}
```

The screen offers no review decision and the server refuses one — agreement, no
gap. **context**: the refusal is returned with HTTP status **401**, not 400/403,
for what is a "no such decision type in this application" condition. Harmless
here but it is a misleading status for an unrecognized-input error, and any
future test asserting on it should expect 401.

**1.6 context — the scenario endpoint refuses review seeding on OPS.**
`POST /index/api/v1/_test/scenarios/submission` with `reviewRounds: [{...}]`
returns HTTP 400,
`{"error":"This application has no review stage, so reviewRounds cannot be seeded.","specKey":"reviewRounds"}`.
Harness-side, not product behaviour — recorded because it is the cheapest
regression guard for the absence claim.

**1.7 CLAIM — positive control on OJS.**
Same walk as `editor.diana` on submission 15 after `sendExternalReview`:
`[role="dialog"] nav` shows

```
Workflow
Submission
Review
Review Round 1
Copyediting
Production
Publication
…
```

`h2` = **`WORKFLOW: REVIEW (ROUND 1)`**; `[data-cy="workflow-primary-items"]`
carries `Round 1 Status` → "Waiting for reviewers to be assigned.",
`Revisions Uploaded`, `Files for Review`, `Reviewers`, `Author Response`,
`Review Tasks & Discussions`; `[data-cy="workflow-action-items"]` =
`Request Revisions`, `Accept Submission`, `Create New Review Round`,
`Cancel Review Round`, `Decline Submission`. The absence on OPS is therefore a
real difference, not a screen that failed to render.

### Verdict

Scenario 13 and the spec's OPS-absence paragraph hold exactly as written, for
both the Preprint Server Manager and the Author, on screen and at the API.
Footnote **w**'s "Live absence verification: probe item 1" can be marked done.

**context** — noticed while reading the OPS manager dialog: the page header
renders the literal string `##common.help##` (an unresolved locale key) on both
OPS and OMP dialogs. Outside U26's scope; flagged for whoever owns the header.

---

## Item 2 — OMP entry doors (scenario 12, register OMP2) — SETTLED, with one deviation

### What was driven

| Sub | Tag | Path driven | Actor |
|---|---|---|---|
| 17 | `u26g1ompa` | Submission stage → skip-internal decision (UI) | `editor.diana` (Press editor) |
| 18 | `u26g1ompb` | seeded `sendInternalReview`, then Internal Review → Send to External Review (UI) | `editor.diana` |
| 35 | `u26g1ompc` | **fully UI-driven** Submission → Send to Internal Review → Send to External Review | `editor.diana` |
| 36 | `u26g1ompd` | Submission stage, read-only inventory | `manager.maya` (Press manager, unassigned) |

All four are scratch monographs in the seeded press, submitters `author.alex` /
`author.bea`, series `monographs` / `textbooks`.

### 2a — The Submission-stage doors and their exact labels

**2a.1 CLAIM — the Submission stage offers four decisions; the skip-internal one
is labelled `Send to External Review`.**
Locator: `[data-cy="workflow-action-items"] button` innerTexts on
`?workflowSubmissionId=17`, `h2` = `WORKFLOW: SUBMISSION`. Observed, verbatim
and in DOM order:

```
Send to External Review
Accept and Skip Review
Decline Submission
Send to Internal Review
```

Server-side inventory for the same submission
(`GET /api/v1/submissions/17` → `availableEditorialDecisions`) matches the
screen one-for-one and pins the identities:

| id | constant | label |
|---|---|---|
| 18 | `SKIP_INTERNAL_REVIEW` | `Send to External Review` |
| 17 | `SKIP_EXTERNAL_REVIEW` | `Accept and Skip Review` |
| 8 | `INITIAL_DECLINE` | `Decline Submission` |
| 1 | `INTERNAL_REVIEW` | `Send to Internal Review` |

**Vocabulary point for the spec/glossary (CLAIM):** OMP shows **the same label,
`Send to External Review`, for two different decisions** — the Submission-stage
skip (id 18) and the Internal-Review-stage promotion (id 3). The screen never
says "skip"; the two doors are distinguished only by which stage you are
standing on. The confirmed URL for the skip door is
`/publicknowledge/en/decision/record/17?decision=18&…`.

**2a.2 CLAIM — a Press Manager without a stage assignment sees the same four
doors.** `manager.maya` on submission 36:
`[data-cy="workflow-action-items"]` = `Send to External Review`,
`Accept and Skip Review`, `Decline Submission`, `Send to Internal Review`;
participants list holds only `Diana Editor (Press editor)`,
`Ravi Sectioneditor (Series editor)`, `Bea Author (Author)` — she is not among
them.

**2a.3 CLAIM — the skip door's wizard is two steps, and it promotes the
submission files.** Driving the button as `editor.diana`:

- step 1 `h1` = **`Send to External Review: Notify Authors`**; step rail reads
  `1 Notify Authors` / `2 Select Files`; email template preloaded is
  **`Sent to Review`**, To: `Alex Author`; buttons `Skip this email`, `Cancel`,
  `Continue`.
- step 2 `h1` = **`Send to External Review: Select Files`**, body text
  "Select files that should be sent to the review stage.", file list heading
  **`Submission Files`** with `article.pdf` (`Uploaded by author.alex …`,
  genre `Book Manuscript`) and its checkbox `input[name="promoteFile21"]`
  **checked by default**.
- final button label: **`Record Decision`**.

**2a.4 CLAIM — outcome of the skip door.** Re-opened workflow after recording:
`nav` = `Workflow / Submission / Internal Review / External Review / Review
Round 1 / Copyediting / Production / …`; `h2` = **`WORKFLOW: EXTERNAL REVIEW
(ROUND 1)`**; `Round 1 Status` = **"Waiting for reviewers to be assigned."**;
`Files for Review` lists the promoted copy (`article.pdf`, new file id 29, type
`Book Manuscript`); `Reviewers` empty; decision buttons are the common review
set (`Request Revisions`, `Accept Submission`, `Create New Review Round`,
`Cancel Review Round`, `Decline Submission`) — identical to OJS's round-1 set
observed in item 1.7.
**context**: `Internal Review` stays listed in the side menu after the skip, but
carries no `Review Round` child item.

### 2b — The Internal-Review door

**2b.1 CLAIM — `Send to External Review` is offered on the Internal Review
stage.** `h2` = `WORKFLOW: INTERNAL REVIEW (ROUND 1)`;
`[data-cy="workflow-action-items"]` = `Request Revisions`,
`Send to External Review`, `Accept Submission`, `Create New Review Round`,
`Cancel Review Round`, `Decline Submission`. Server inventory for the same
state: ids 20 / **3** / 19 / 28 / 32 / 22.
**context** — label drift worth one line in the spec: for id 28 the API's
`availableEditorialDecisions` label is **`New Review Round`** while the button
on screen reads **`Create New Review Round`**. The button label is what a reader
sees; anything asserting on the API label will read differently.

**2b.2 CLAIM — the door lands the monograph in External Review Round 1.**
After `Record Decision` (URL `…/decision/record/35?decision=3&…&reviewRoundId=32`):
`nav` = `… Internal Review / Review Round 1 / External Review / Review Round 1 /
…` (both stages now carry a round item); `h2` = **`WORKFLOW: EXTERNAL REVIEW
(ROUND 1)`**; `Round 1 Status` = **"Waiting for reviewers to be assigned."**;
decision buttons = the same common review set. Same wizard shape as the skip
door: step 1 `Send to External Review: Notify Authors` (template `Sent to
Review`), step 2 `Send to External Review: Select Files`, finish
`Record Decision`.

**2b.3 CLAIM — DEVIATION from scenario 12: the Internal-Review door arrives with
an EMPTY "Files for Review".**
On submission 35 the internal round was entered through the UI, and its
`Files for Review` panel demonstrably held `article.pdf` (file id 41, `Book
Manuscript`) — verified on the round-1 screen immediately before the decision.
Nevertheless:

- `Send to External Review: Select Files` showed one list, headed
  **`Revisions`**, reading **"No items found."**; zero
  `input[type="checkbox"]` on the step (the skip door had one, pre-checked).
- After recording, External Review Round 1's `Files for Review` panel reads
  **`No Items`**.

Reproduced identically on submission 18 (internal round entered by seeding).
So the two doors are **not** equivalent on files: the skip door promotes the
submission files, the internal→external door offers only files the author
uploaded as revisions *during* internal review, and promotes nothing when there
are none — the internal round's own review files do not carry over. An editor
taking this door lands on an external round with no files for reviewers and must
use `Upload/Select Files` to re-add them.
**context (mechanism, code-read after the observation, for the spec's footnote
only)**: `omp-main/classes/decision/types/SendExternalReview.php::withFilePromotionLists()`
builds the single list from `SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION`
under the heading `editor.submission.revisions`.

Direction of the deviation: the screen delivers **less** than the spec
documents (a round that opens empty), not more access than intended.

### Verdict

Scenario 12's two doors exist and both land on External Review Round 1 at
"Waiting for reviewers to be assigned.", so **OMP2 is confirmed as intended
divergence (✅)**. The scenario's "Either way … opens with the promoted files"
clause is **true only of the Submission-stage skip door**; the Internal-Review
door opens with an empty Files for Review unless the author uploaded revisions
during internal review.

---

## Proposed content for files this agent must not write

### For `specs/review-stage-and-rounds.md`

1. **Footnote w** — replace the trailing "Live absence verification: probe item
   1." with the settled result, e.g.: *"Live verification 2026-07-27 (OPS,
   Preprint Server Manager and Author): the workflow side menu's Workflow group
   holds only `Production`; no round panels, no `Reviewers` panel; the only
   actions offered are `Post the preprint` and `Decline Submission`; the API
   offers `Decline Submission` alone and refuses review decision types."*
2. **Scenario 13** — no wording change needed; it matched verbatim.
3. **Scenario 12** — split the file clause by door: the Submission-stage door
   opens External Review Round 1 with the submission files promoted (its Select
   Files step pre-checks them); the Internal Review door's Select Files step
   offers only internal-review *revision* files, so a round entered that way can
   open with an empty `Files for Review`.
4. **Decision vocabulary** (spec body or APP-GLOSSARY): OMP labels the
   Submission-stage skip-internal decision **`Send to External Review`** — the
   same words as the Internal-Review-stage decision; the Submission stage also
   offers `Send to Internal Review`, `Accept and Skip Review`, `Decline
   Submission`. The word "skip" appears on screen only in
   `Accept and Skip Review`.
5. **New findings-register candidate** (author's call whether it rises to an
   entry): *"External Review entered from Internal Review starts with no files
   for reviewers"* — 🐞/❓, user-visible, OMP-only; symptom and repro in item
   2b.3 above.
6. Round-1 label/status vocabulary confirmed live and reusable by later items:
   stage heading `WORKFLOW: EXTERNAL REVIEW (ROUND 1)`, status line
   "Waiting for reviewers to be assigned.", decision set `Request Revisions` /
   `Accept Submission` / `Create New Review Round` / `Cancel Review Round` /
   `Decline Submission`, panels `Round 1 Status` / `Revisions Uploaded` /
   `Files for Review` / `Reviewers` / `Review Tasks & Discussions` (+ `Author
   Response` on OJS, absent on OMP — for Group II item 3 to settle).

### For PROGRESS.md

*U26 probe Group I (items 1–2) complete 2026-07-27 — item 1 settled (OPS
absence confirmed on screen and at the API, both roles; footnote w discharged);
item 2 settled (both OMP doors confirmed, exact labels recorded) with one
documented deviation in scenario 12's file-promotion clause.*

### For docs/e2e/app-changes.md

Nothing to add: no harness or app change was needed. Two facts a future test
author will want are already in this report — the fleets answer on `127.0.0.1`
(not `localhost`), and the OPS scenario endpoint rejects `reviewRounds` with a
400 + `specKey`.

---

## Artefacts

Scratch scripts, JSON dumps and full-page screenshots live in the session
scratchpad (`item1b.json`, `item1c.json`, `item2a-inventory.json`,
`item2-walk-17.json`, `item2-walk-18.json`, `item2-walk2-35.json`,
`ops-manager.png`, `ops-author.png`, `omp-*.png`). Nothing was left behind in
the repos; the only durable state is the scratch submissions listed above.
