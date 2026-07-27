# U26 probe report — Group IV (items 11–14, lifecycle mutations)

Agent: probe, RUNBOOK step 3. Date: 2026-07-27. Fleets driven live at OJS
`http://127.0.0.1:8000` and OMP `http://127.0.0.1:8100` (the `localhost` host
name 400s "Server host not allowed" on both — `allowed_hosts` in each
`config.test.inc.php` lists `127.0.0.1` only; incidental harness context, same
observation as Group I).

All state was seeded through `POST /api/v1/_test/scenarios/submission` on each
fleet's seeded `publicknowledge` context, one scratch submission per sub-item;
no seeded submission or account was modified and Mailpit was never cleared
(every mail assertion is scoped by the submission's unique tag via
`GET :8025/api/v1/search?query=<tag>`). Every affordance claim below was
observed on the live screen by driving Chromium (Playwright `chromium` from
`ojs-main/node_modules`, real UI login per user).

Marker convention: **CLAIM** = observation the item asked for, promotable to
the spec; **context** = incidental behaviour noted in passing (often another
group's item).

Common locators used throughout:

- workflow modal: `page.locator('[data-cy="active-modal"]')`
- stage side menu: `<modal>.locator('nav').first()` (`innerText`)
- decision / action buttons: `<modal>.getByRole('button')` filtered to visible,
  plus `page.getByRole('button', {name: '<label>', exact: true}).count()` for
  presence/absence
- round status: `<modal>` innerText matched on `/Round \d Status\n+([^\n]+)/`
- stacked sub-modal (file selection, add reviewer, assign): `page.locator('[role="dialog"]').last()`
- editorial workflow URL: `/publicknowledge/en/dashboard/editorial?workflowSubmissionId={id}`
- author workflow URL: `/publicknowledge/en/dashboard/mySubmissions?workflowSubmissionId={id}`
- decision wizard: navigates to `/publicknowledge/en/decision/record/{id}?decision={n}&…&reviewRoundId={r}`;
  headings read from `page.locator('h1')`, e-mail subject from
  `input[name="subject"]` `inputValue()`

---

## Item 11 — Cancel round (Rule 15, scenario 9, OMP1) — SETTLED

### What was driven

| # | App | Role / account | Seeded state |
|---|---|---|---|
| 11a | OJS | Journal editor (`editor.diana`) | tag `c11amnx8e8`, submission 25: `sendExternalReview → requestRevisions → newExternalReviewRound`; round 1 `reviewer.paul` accepted, round 2 `reviewer.julia` **invited** |
| 11a | OMP | Press editor (`editor.diana`) | tag `a11d5210`, submission 37: `skipInternalReview → requestRevisions → newExternalReviewRound`; external round 1 `reviewer.paul` accepted, external round 2 `reviewer.julia` invited |
| 11b | OJS | `editor.diana` | tag `c11b5002`, submission 27: same chain, round 2 `reviewer.julia` **accepted** |
| 11b | OMP | `editor.diana` | tag `b11d5212`, submission 38: `skipInternalReview`, external round 1 `reviewer.julia` **accepted** |
| 11c | OJS | `editor.diana` | tag `c11c5002`, submission 28: `sendExternalReview`, round 1 `reviewer.julia` invited |
| 11d | OMP | `editor.diana` | tag `d11d5084`, submission 33: internal round 1 (`reviewer.amara` accepted) **and** external round 1 (`reviewer.julia` invited) |
| 11d | OMP | `editor.diana` | tag `e11d5085`, submission 34: `skipInternalReview`, external round 1 only, `reviewer.julia` invited |
| extra | OJS | `editor.diana` | tag `g12o5300`, submission 37: round 2 with a carried review file and **no reviewers** |

The decision-type name is `cancelReviewRound` on both apps (`newExternalRound`
from `scenarios.md` is stale — the live OJS roster answers
`newExternalReviewRound`; OMP additionally offers `cancelInternalReviewRound`
and `newInternalReviewRound`). Both rosters were read back verbatim from the
scenario endpoint's 400 on an unknown decision name — harness context.

### Observations

**11.1 CLAIM — "Cancel Review Round" is offered while the round's only reviewer
has not accepted.** Locator: `page.getByRole('button', {name: 'Cancel Review
Round', exact: true}).count()` on the workflow modal. OJS submission 25 (round 2
selected by default) → 1. OMP submission 33 (External Review Round 1) → 1. The
full visible action list at that moment, in DOM order, is
`Request Revisions // Accept Submission // Create New Review Round // Cancel
Review Round // Decline Submission` on both apps.

**11.2 CLAIM — after a reviewer accepts, the button is not offered.** Same
locator → 0 on OJS submission 27 (round 2, `reviewer.julia` accepted) and on OMP
submission 38 (External Review Round 1, accepted). Every other action stays:
`Request Revisions // Accept Submission // Create New Review Round // Decline
Submission`. The removal is specific to the cancel action, not a collapse of the
list.

**11.3 CLAIM — the cancel decision runs through the U34 wizard with two steps
and these exact strings.** OJS and OMP alike, from
`decision/record/{id}?decision=31&…&reviewRoundId={r}`:

- page heading step 1: `Cancel Review Round: Notify Authors`
- page heading step 2: `Cancel Review Round: Notify Reviewers`
- step rail: `1 Notify Authors` / `2 Notify Reviewers`
- wizard blurb (both steps): "Cancel the current round of review and send the
  submission back to the last round of review. If this is the first review
  round, it will be moved to the submission stage."
- step 1 template `Review Round Cancelled`, recipient `Alex Author`, subject
  field value **"A review round for your submission has been cancelled"**
- step 2 template `Reviewer Unassign`, recipient `Julia Reviewer`, subject field
  value **"Request for Review Cancelled"**
- commit button: `Record Decision`; success panel: "Cancelled the latest round
  of review." / "The review round for the submission, `<title>`, has been
  cancelled. All notifications have been sent, except any you chose to skip."

**11.4 CLAIM — on a round with no reviewers the wizard collapses to one step.**
OJS submission 37, round 2 (carried file, no reviewers assigned): the
`Continue` button is absent (`getByRole('button', {name: 'Continue', exact:
true}).count()` → 0) and step 1 commits directly with `Record Decision`. Only
the author e-mail is composed. The cancel still succeeds.

**11.5 CLAIM — cancelling round 2 removes it and makes round 1 current.** Side
menu (`<modal> nav` innerText) before / after on OJS submission 25:
`… Review | Review Round 1 | Review Round 2 | Copyediting …` →
`… Review | Review Round 1 | Copyediting …`; the workflow heading reads
`WORKFLOW: REVIEW (ROUND 1)` and the URL settles on
`…&workflowMenuKey=workflow_3_26` (round 1's id). Round 1's own reviewer
(`Paul Reviewer`, `Request Accepted`) and files are intact. Identical on OMP
submission 37 (`workflow_3_34`, heading `WORKFLOW: EXTERNAL REVIEW (ROUND 1)`).

**11.6 CLAIM — cancelling Round 1 on OJS returns the submission to the
Submission stage.** OJS submission 28 after the cancel: side menu
`Workflow | Submission | Review | Copyediting | Production | Publication | …`
(the `Review` stage entry remains but has no round children), heading
`WORKFLOW: SUBMISSION`, URL `…&workflowMenuKey=workflow_1`, and the modal's
stage label under the title reads `Submission`.

**11.7 CLAIM (OMP1) — cancelling External Round 1 lands on Internal Review when
internal rounds exist, on Submission when they do not.** OMP submission 33
(internal round 1 present): after the cancel the side menu is
`… Internal Review | Review Round 1 | External Review | Copyediting …` — the
External Review entry loses its round child, the Internal Review round survives
— heading `WORKFLOW: INTERNAL REVIEW (ROUND 1)`, URL
`…&workflowMenuKey=workflow_2_29`. OMP submission 34 (no internal rounds): side
menu `… Internal Review | External Review | Copyediting …` with no round
children anywhere, heading `WORKFLOW: SUBMISSION`, URL
`…&workflowMenuKey=workflow_1`. OMP1 reproduces exactly as registered.

**11.8 CLAIM — both e-mails reach Mailpit, one per addressee group.** Scoped
search by tag. OJS `c11amnx8e8`, OMP `d11d5084`, OMP `e11d5085`, OJS `c11c5002`
all return the same pair:

| To | Subject |
|---|---|
| `author.alex@example.org` | `A review round for your submission has been cancelled` |
| `reviewer.julia@example.org` | `Request for Review Cancelled` |

**11.9 CLAIM — the cancelled round's reviewer loses the assignment and its
access.** OJS submission 25 after the cancel, as `reviewer.julia`: the reviewer
dashboard (`/publicknowledge/en/dashboard/reviewAssignments`) no longer lists
the tag (`body.innerText().includes('c11amnx8e8')` → false) while five other
assignments for the same account still render — the positive control for a live
session. A signed-in `context.request.get('/publicknowledge/en/reviewer/submission/25')`
ends on `/user/authorizationDenied?message=user.authorization.submissionReviewer`.
Direction is *less* access, as intended.

**11.10 CLAIM — files uploaded for the cancelled round are not deleted.** OJS
submission 37: before the cancel, round 2's "Files for Review" held file 51.
After the cancel, `GET /publicknowledge/api/v1/submissions/37/files` as
`editor.diana` still returns three files including
`51 | fileStage 4 | assocId 45 | revision.pdf` — round 45 being the cancelled
round. The file survives, but it is no longer listed on any screen: the round-1
"Upload/Select Files" modal with "Show files from all accessible workflow
stages" ticked lists only 42 and 48. **Screen-vs-data note for the spec**: "not
deleted" is true of the record, not of any surface the editor can reach.

**11.11 context** — after the OMP external-round cancel of submission 33, the
surviving Internal Review Round 1 status line reads `Submission accepted.`, not
Rule 5's "advanced to the next round" wording. Rule 7/Rule 5 territory (Group
II item 7), noted in passing.

**11.12 context** — OJS submission 25's Round 1 status after the cancel reads
`Awaiting responses from reviewers.` even though its only reviewer had accepted.
Group II/III status-wording territory.

---

## Item 12 — New round carry-over (Rule 16, scenario 4) — SETTLED

### What was driven

| App | Role / account | Seeded state |
|---|---|---|
| OJS | `editor.diana` (editor), `author.alex` (author) | tag `g12o5300`, submission 37: `sendExternalReview → resubmit`, round 1 `reviewer.paul` accepted |
| OMP | `editor.diana`, `author.alex` | tag `g12m5633`, submission 39: `skipInternalReview → resubmit`, external round 1 `reviewer.paul` accepted |

In both cases the author then uploaded a revision through the round's own
"Upload revisions" button (a real file, `revision.pdf`), and the editor recorded
"Create New Review Round".

### Observations

**12.1 CLAIM — the author's revision-upload path is a 3-step side modal titled
"Upload Review File".** Steps rail: `1. Upload File` / `2. Review Details` /
`3. Confirm`; a required `Article Component*` select (`select#genreId`) precedes
the drop zone; step 3 reads `File Added` with `Add Another File` and a
`Complete` button. The genre roster differs per app — OJS offers
`Article Text, Research Instrument, …`; OMP offers a book-manuscript roster
whose first selectable entry is `Appendix`. Locator: `[role="dialog"]` filtered
by text `Upload Review File`, file input `input[type=file]` (`setInputFiles`).

**12.2 CLAIM — before the new round, the editor's round status is "Revisions
submitted. A new review round needs to be created."** Observed identically on
OJS submission 37 and OMP submission 39 after the author's upload (the author's
own view shows the same string — see 14.4).

**12.3 CLAIM — the "Create New Review Round" wizard has two steps with these
exact strings.** Both apps, `decision/record/{id}?decision=14&…`:

- step 1 heading `New Review Round: Notify Authors`, step rail
  `1 Notify Authors` / `2 Select Files`
- blurb "Open another round of review for this submission."
- template `New Review Round Initiated`, recipient `Alex Author`, subject field
  value **"Your submission has been sent for another round of review"**
- step 2 heading `New Review Round: Select Files`, section label
  `Select Files` with the instruction "Select files that should be sent for
  review."
- commit button `Record Decision`; success panel "Review Round Created" / "A new
  round of review has been created for the submission, `<title>`. The author has
  been notified, unless you chose to skip that email."

**12.4 CLAIM — step 2 lists the previous round's uploaded revision under a
"Revisions" group, preselected.** Locator: `input[type=checkbox]` inside the
wizard. OJS: exactly one checkbox, labelled `48 revision.pdf` / "Uploaded by
author.alex on 2026-07-27" / `Article Text`, `isChecked()` → **true**. OMP:
exactly one, `50 revision.pdf` / `Appendix`, `isChecked()` → **true**. A
`Download` link accompanies the row.

**12.5 CLAIM — after confirming, Round 2 is selected, waiting for reviewers,
with the carried file in "Files for Review" as a copy.** OJS submission 37:
side menu gains `Review Round 2`, URL `…&workflowMenuKey=workflow_3_45`,
heading `WORKFLOW: REVIEW (ROUND 2)`, status **"Waiting for reviewers to be
assigned."**, "Revisions Uploaded" empty, "Files for Review" listing
**`51 revision.pdf / Article Text`** — a new file id, not the original 48. OMP
submission 39: heading `WORKFLOW: EXTERNAL REVIEW (ROUND 2)`, same status,
"Files for Review" listing `51 revision.pdf / Appendix` against the original 50.

**12.6 CLAIM — the original stays in round 1's "Revisions Uploaded".** Selecting
`Review Round 1` in the side menu (`<modal>.getByRole('link', {name: 'Review
Round 1', exact: true})`): OJS round 1 shows `48 revision.pdf` under "Revisions
Uploaded" and an empty "Files for Review"; OMP round 1 shows `50 revision.pdf`.
Status on both: **"The submission has been advanced to the next round of
review"** under a bare `Status` heading (no round number — the heading is
`Status`, not `Round 1 Status`).

**12.7 context (Group II item 7)** — that read-only past round still renders
`Upload`, `Upload/Select Files`, `Add Reviewer`, `Request Response` and `Add`
buttons for the editor; only the primary decision buttons are withheld.

**12.8 context (Group II item 6 / A1)** — in the resubmit variant, after the
author's first upload the workflow's `Upload revisions` button is gone from the
author's action list, while the "Revisions Uploaded" panel still offers its own
`Upload` control. In the stay-in-round variant (item 14's submissions) the
`Upload revisions` button is still present after the upload.

---

## Item 13 — Files-for-review curation (Rule 17, scenario 11) — SETTLED

### What was driven

| App | Role / account | Seeded state |
|---|---|---|
| OJS | `editor.diana` | tag `f13o5824`, submission 43: `sendExternalReview`, round 1 `reviewer.julia` invited; submission file 54 `article.pdf` (`Article Text`) |
| OMP | `editor.diana` | tag `f13m5824`, submission 40: `skipInternalReview`, external round 1 `reviewer.julia` invited; submission file 52 `article.pdf` (`Book Manuscript`) |

### Observations

**13.1 CLAIM — the modal title is exactly "Current Review Files For Round 1" on
both apps.** Locator: `page.locator('[role="dialog"]').last().locator('h1,h2,h3')`
after clicking `Upload/Select Files`. The modal body carries the section label
`Review Files`, an `Upload Review File` button, a `Show files from all
accessible workflow stages.` checkbox, a three-column grid `Select | Name |
Component`, and `Cancel` / `OK`.

**13.2 CLAIM — by default the modal lists only the current review stage's
files, which on a fresh round is empty.** OJS: one category row `Review` → `No
Items`. OMP: `External Review` → `No Items`. Ticking "Show files from all
accessible workflow stages." expands it to every stage — OJS
`Submission / Review / Copyediting / Production`, OMP `Submission / Internal
Review / External Review / Copyediting / Production` — and only then is the
submission's own file reachable. Rule 17's "listing the submission's files" is
true only behind that toggle.

**13.3 CLAIM — ticking a file from another stage copies it into the round.**
OJS: tick `54 article.pdf`, `OK` → "Files for Review" gains **`56
article.pdf / Article Text`** (a new id; 54 is untouched). OMP: tick `52`, `OK`
→ "Files for Review" gains `56 article.pdf / Book Manuscript`. Panel locator:
`<modal>` innerText matched on `/Files for Review[\s\S]*?(?=Reviewers\n)/`.

**13.4 CLAIM — a file added this way arrives UNTICKED.** Reopening the modal
immediately after the add shows the new review file listed under `Review` /
`External Review` with `input#select-56` `isChecked()` → **false**, on both
apps. The DOM row is
`<input type="checkbox" id="select-56" name="selectedFiles[]" value="56">`
inside `tr#component-grid-files-review-managereviewfilesgrid-row-56`. Ticking it
and pressing `OK` makes the reopened modal show it checked, so the checkbox is a
persistent per-file flag, not a transient selection.

**13.5 CLAIM — unticking withdraws the file without removing it, and the
editor's panel does not change.** OJS submission 43 and OMP submission 40: with
`56` ticked, untick → `OK`. The "Files for Review" panel still lists `56
article.pdf` identically; reopening the modal lists `56` unticked. Pressing `OK`
with a file already unticked is a no-op — the file is not withdrawn from the
panel and nothing is deleted. **Screen gap for the spec**: the editor's "Files
for Review" panel gives no visible difference between a ticked (reviewer-facing)
and an unticked (withdrawn) review file; the only surface that distinguishes
them is the modal's checkbox.

**13.6 context** — the reviewer-facing half of Rule 17 could not be settled from
this surface. `reviewer.julia`'s own review page
(`/publicknowledge/en/reviewer/submission/43`, step 1 "Review Files" grid) shows
`No Files` both while file 56 is ticked and while it is unticked, because a
scenario-seeded reviewer carries no `review_files` grant — the grant is written
by the Add Reviewer modal's file selection (U27 territory; matches the
`patterns.md` "review files are grant-based" note). Whether unticking changes
what a *granted* reviewer sees belongs to a U27/U36 probe.

---

## Item 14 — Revision-upload side effects (Side effects; scenario 3; A6) — SETTLED

### What was driven

| App | Role / account | Seeded state |
|---|---|---|
| OJS | `editor.diana`, `sectioneditor.ravi` (assigned **recommend-only** through the UI), `author.alex` | tag `h14o6428`, submission 47: `sendExternalReview → requestRevisions` (stay-in-round), round 1 `reviewer.paul` accepted |
| OMP | same cast | tag `h14m6772`, submission 41: `skipInternalReview → requestRevisions`, external round 1 `reviewer.paul` accepted |
| OJS | `author.alex` | tags `thr1009` (submission 49) and `thr2009` (submission 50): two throttle-control submissions at `requestRevisions`, no reviewers |

`sectioneditor.ravi` was made recommend-only through the workflow's
`Assign` → **"Assign Participant"** modal: role select `Section editor`
(OMP: `Series editor`), user search, then the `Assignment privileges` checkbox
labelled "This participant is only allowed to recommend an editorial decision
and will require an authorised editor to record editorial decisions." The
Participants column then reads `RS | Ravi Sectioneditor | Section editor | Only
allowed to recommend an editorial decision`. Auto-assigned stage editors on the
seeded journal are `editor.diana`, `sectioneditor.ana`, `sectioneditor.omar`.

### Observations

**14.1 CLAIM — the revised-version e-mail's on-screen subject is "Revised
Version Uploaded".** The spec's "Revised Version Notify" is the mailable /
template key (`lib/pkp/classes/mail/mailables/RevisedVersionNotify.php`,
`$emailTemplateKey = 'REVISED_VERSION_NOTIFY'`); the delivered subject differs.
Worth aligning the spec's wording.

**14.2 CLAIM — one e-mail goes to every stage-assigned editor, recommend-only
included.** Mailpit search scoped by tag. OJS `h14o6428`:

```
To: editor.diana@example.org, sectioneditor.ravi@example.org,
    sectioneditor.ana@example.org, sectioneditor.omar@example.org
Subject: Revised Version Uploaded
```

OMP `h14m6772`: byte-identical recipient set and subject. Note the shape — a
**single message addressed to all of them**, not one message per editor.
`sectioneditor.ravi` is the recommend-only participant, so the "recommend-only
included" claim holds on both apps.

**14.3 CLAIM — the round status flips in every view.** Status locator
`/Round \d Status\n+([^\n]+)/` on each role's workflow modal, before → after the
author's upload, identical on OJS submission 47 and OMP submission 41:

| Role | Before | After |
|---|---|---|
| deciding editor (`editor.diana`) | `Revisions have been requested.` | `Revisions have been submitted and a decision is needed.` |
| recommend-only editor (`sectioneditor.ravi`) | `Revisions have been requested.` | `Revisions have been submitted and a decision is needed.` |
| author (`author.alex`) | `Revisions have been requested.` | `Revisions have been submitted and a decision is needed.` |

**14.4 CLAIM — the author's pending-revisions task flag HAS visible surfaces;
A6's "invisible" symptom does not reproduce on this build.** Before the upload,
as `author.alex`, three distinct surfaces show it:

1. **Tasks panel** — header `Tasks` button (badge `3`) opens a panel whose grid
   lists a row per task. OJS row text: `Revision required.` + journal acronym
   `JPK` + the submission title. OMP row text: **`Revisions to consider in
   External Review.`** + press acronym `PKP` + title. (A sibling row on another
   scratch submission reads `Resubmit for review.` for the resubmit variant.)
   The panel footer offers `Mark New` / `Mark Read` / `Delete`.
2. **Author dashboard row** — `/publicknowledge/en/dashboard/mySubmissions`, the
   `EDITORIAL ACTIVITY` column for the submission reads `Revision requested`
   with an action link `Submit revisions`.
3. **Author dashboard view counter** — the left rail carries a
   `Revisions requested` view with the count.

After the upload all three clear: the Tasks badge drops `3 → 2` and the row for
that submission is gone from the panel (the two remaining rows belong to other
submissions); the dashboard `EDITORIAL ACTIVITY` cell changes to
`Review update 0/1`. This confirms the Side-effects bullet "the author's
pending-revisions task flag is cleared" **and** contradicts A6's registered
symptom — the flag's display surface is the current Tasks panel, not the legacy
Tasks grid. Proposed register change is in the closing section.

**14.5 CLAIM — the "about one per day per editor" throttle does not hold across
submissions.** Controlled run: two scratch OJS submissions (49, 50) seeded at
`requestRevisions`, revisions uploaded back to back. Mailpit, scoped by tag:

```
thr1009  20:30:27  To: editor.diana, sectioneditor.ana, sectioneditor.omar
thr2009  20:30:59  To: editor.diana, sectioneditor.ana, sectioneditor.omar
```

32 seconds apart. `sectioneditor.ana` and `sectioneditor.omar` have never had a
session created in this test database (no storage state exists for either
account anywhere in the fleet), so the "unless they have signed in since the
last one" escape cannot explain it. Across the whole session those two accounts
received eight `Revised Version Uploaded` messages in half an hour.

**14.6 CLAIM — the suppression that does exist is per submission.** A second
revision upload on submission 50, minutes after the first, produced **no**
second e-mail (Mailpit still shows exactly one message for tag `thr2009`) while
the round genuinely gained a second file (`Revisions Uploaded` lists both `65`
and `66`). So the mechanism is "notify once per submission", not "once per day
per editor".

**14.7 context** — the recommend-only editor's action list on the round is
`Recommend Revisions // Recommend Accept // Recommend Decline` (no decision
buttons), unchanged before and after the upload. Group II item 5's territory.

**14.8 context** — in the stay-in-round variant the author keeps the
`Upload revisions` button after uploading (Rule 12's stay-in-round claim), and
a repeat upload is accepted.

---

## Proposed content for other files (NOT written by this agent)

For the orchestrator to place; nothing here was written to PROGRESS.md, atlas
files or `docs/e2e/app-changes.md`.

**Spec — Rule 15.** Add: the cancel decision is composed in a two-step wizard
(`Notify Authors` → `Notify Reviewers`) that collapses to one step when the
round has no reviewers. The delivered subjects are "A review round for your
submission has been cancelled" (author) and "Request for Review Cancelled"
(reviewer). "Files uploaded for the cancelled round are not deleted" is true of
the stored file only — it disappears from every editorial listing once its round
is gone.

**Spec — Rule 16.** Confirmed verbatim; the wizard's second step is titled
"Select Files" and the carried file appears in the new round with a new file id
(a copy), the original remaining under round 1's "Revisions Uploaded".

**Spec — Rule 17.** Two corrections. (a) The modal lists only the *current
review stage's* files until "Show files from all accessible workflow stages." is
ticked; the submission's own files are not visible by default. (b) A file added
through the modal arrives **unticked**, i.e. not yet released to reviewers, and
unticking an existing one leaves the editor's "Files for Review" panel visually
unchanged — the modal's checkbox is the only surface that shows which review
files are actually released.

**Spec — Side effects, revised-version bullet.** Replace the "throttled to at
most about one per day per editor unless they have signed in since the last one"
clause with: the notification fires once per submission — later revision uploads
on the same submission send nothing further, while a different submission sends
immediately regardless of elapsed time or sign-in. Also rename the e-mail to its
delivered subject, "Revised Version Uploaded" (mailable `RevisedVersionNotify`).

**Findings register — A6.** Proposed status change from 🐞 invisible to
resolved/not-reproducing: the author's pending-revisions task flag renders in
the current Tasks panel ("Revision required." on OJS, "Revisions to consider in
External Review." on OMP) and on the author dashboard row ("Revision requested"
+ "Submit revisions"), and clears on upload.

**Findings register — OMP1.** Reproduces exactly as registered; no change.

**Harness / `scenarios.md` correction.** The OJS decision name for a new round
is `newExternalReviewRound` (the file's `newExternalRound` 400s). Live rosters,
read back from the endpoint's error: OJS — `accept, backFromCopyediting,
backFromProduction, cancelReviewRound, decline, initialDecline, moveToDone,
newExternalReviewRound, recommendAccept, recommendDecline, recommendResubmit,
recommendRevisions, requestRevisions, resubmit, returnToDone, returnToWorkflow,
revertDecline, revertInitialDecline, sendExternalReview, sendToProduction,
skipExternalReview`; OMP adds the `*Internal*` family plus `acceptFromInternal`,
`recommendSendExternalReview`, `sendInternalReview`, `skipInternalReview`.
`resubmit` is a first-class decision, so the resubmit state is seedable in one
POST without driving the `WorkflowSelectRevisionFormModal`.
