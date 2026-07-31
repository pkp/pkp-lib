# U26 Review stage & rounds — probe P3 (items 6–8)

Probe date: 2026-07-31. Fleet: OJS at `http://127.0.0.1:8000`, scratch journal `u26p3`
(contextId 64), throwaway users `u26p3editor` (role key `editor` = Journal editor group),
`u26p3author`, `u26p3rev1..3` (`externalReviewer`). All observations from a signed-in
Chromium session (Playwright); locators quoted per observation. Seeds via
`POST /api/v1/_test/scenarios/{context,submission}`:

- SubA id=8 `u26p3suba`: `decisions: [sendExternalReview, resubmit]`, round 1 with
  `u26p3rev1` status `accepted`.
- SubB id=9 `u26p3subb`: `decisions: [sendExternalReview]`, round 1 with `u26p3rev3`
  status `accepted`.
- SubC id=10 `u26p3subc`: `decisions: [sendExternalReview]`, round 1, no reviewers.

The step-2 scenario core seeds no files; every file below entered through the screens.
No observation in this probe looked like a security weakness; nothing was routed to the
private security file.

---

## Item 6 — Create New Review Round after resubmit + author revision

### Setup states observed (context)

SubA round 1 as editor (`dashboard/editorial?workflowSubmissionId=8`), before any probe
action — heading `Workflow: Review (Round 1)` (`getByRole('heading')`):

- `Round 1 Status` (h3) / paragraph: **"Revisions requested from the author to be taken
  to a new review round."**
- Decision buttons rendered (bottom of stage view, in order): `Request Revisions`,
  `Accept Submission`, `Create New Review Round`, `Decline Submission`. **No
  `Cancel Review Round` button on this round in this state** (round with an accepted
  reviewer + resubmit decision recorded). Context observation.

Author upload (context — needed to produce the revised file): as `u26p3author` on the
same submission, the round 1 view shows panel `Revisions Uploaded` (h3, paragraph
"These files have been submitted by the author after revisions were requested") with an
`Upload` button (`getByRole('button', {name: 'Upload', exact: true})`) and a bottom
button `Upload revisions`. `Upload` opens the legacy 3-step wizard, dialog title
**"Upload Review File"** (`getByRole('dialog', {name: 'Upload Review File'})`), tabs
`1. Upload File` / `2. Review Details` / `3. Confirm`; the upload form posts to
`wizard/file-upload/...&fileStage=15&reviewRoundId=8`. After completing it, the author's
`Revisions Uploaded` table gains row `3 revised-manuscript.pdf 2026-07-31 Article Text`
(submissionFileId=3).

### Probe action: Create New Review Round

As `u26p3editor`, clicked `getByRole('button', {name: 'Create New Review Round'})`.
Navigates (full page) to `/u26p3/decision/record/8?decision=14&...&reviewRoundId=8`.

- Wizard heading: **"New Review Round: Notify Authors"** (h1); intro paragraph "Open
  another round of review for this submission."; step list "1 Notify Authors",
  "2 Select Files".
- Step 1 is an email composer, template button **"New Review Round Initiated"**, subject
  prefilled "Your submission has been sent for another round of review", buttons
  `Skip this email` / `Cancel` / `Continue`.
- Step 2 heading **"New Review Round: Select Files"**, section heading `Select Files`,
  paragraph "Select files that should be sent for review.", group heading **"Revisions"**
  with checkbox **"3 revised-manuscript.pdf"** — **already checked on arrival**
  (`getByRole('checkbox', {name: /revised-manuscript\.pdf/}).isChecked()` → `true`; the
  probe item says "tick the revised file" — no ticking was needed). Buttons `Cancel` /
  `Previous` / `Record Decision`.
- After `Record Decision`: confirmation dialog **"Review Round Created"** — paragraph
  "A new round of review has been created for the submission, U26P3 SubA resubmit round
  case. The author has been notified, unless you chose to skip that email." with link
  `View Submission Summary`.

### Result: Round 2 view (claim)

Back on `dashboard/editorial?workflowSubmissionId=8` (lands on the new round):

- Stage menu (navigation tree, `treeitem`s): `Review` now expands to **"Review Round 1"**
  and **"Review Round 2"**; header badge text `Review (Round 2)`.
- Heading `Workflow: Review (Round 2)`; **`Round 2 Status` paragraph: "Waiting for
  reviewers to be assigned."**
- **`Files for Review`** table (aria-label `Files for Review`, paragraph "These files
  will be sent to the reviewers to review"): one row —
  **`5 revised-manuscript.pdf 2026-07-31 Article Text`** (a new submissionFileId=5, i.e.
  a copy; the author's original stays id=3 in round 1's `Revisions Uploaded`).
- `Revisions Uploaded` on round 2: `No Items`. `Reviewers` table: `No Items`.
- Decision buttons on round 2: `Request Revisions`, `Accept Submission`,
  `Create New Review Round`, **`Cancel Review Round`**, `Decline Submission`.

### Result: Round 1 view after advancement (claim)

Clicked `getByRole('link', {name: 'Review Round 1'})` in the stage menu:

- Heading `Workflow: Review (Round 1)`; status heading is plain **"Status"** (not
  "Round 1 Status"); paragraph: **"The submission has been advanced to the next round of
  review"** (no trailing period in the DOM text).
- **All five decision buttons are absent** on this view (no Request Revisions / Accept
  Submission / Create New Review Round / Cancel Review Round / Decline Submission).
- The expectation "no buttons" holds only for decision buttons: panel-level buttons still
  render — `Upload` (Revisions Uploaded), `Upload/Select Files` (Files for Review),
  `Add Reviewer`, `Request Response` (disabled), `Add` (Tasks & Discussions), `Assign`
  (Participants). Context observation.
- Round 1 panels still show their content: `Revisions Uploaded` row
  `3 revised-manuscript.pdf ...`, `Reviewers` row
  `u26p3rev1 Request Accepted Review due: 2026-09-25 Anonymous Reviewer/Disclosed Author`.

---

## Item 7 — Cancel Review Round (three cases)

### (a) Round with a completed review — button absent (claim)

Setup (context): as `u26p3rev3` on SubB (`/u26p3/reviewer/submission/9`) the reviewer
wizard rendered tabs `1. Request` / `2. Guidelines` / `3. Download & Review` /
`4. Completion`; drove `Save and continue` ×2, typed comments into the first TinyMCE
iframe, picked recommendation `Accept Submission` (combobox options: "Choose One",
"Accept Submission", "Revisions Required", "Resubmit for Review", …), clicked
`Submit Review`; confirm dialog text "Confirm / Are you sure you want to submit this
review?" → `OK`; landing tab `4. Completion` shows heading **"Review Submitted"**.

Editor check on SubB round 1 (`dashboard/editorial?workflowSubmissionId=9`):

- `Round 1 Status` paragraph: **"New reviews have been submitted."**
- Reviewers row: `u26p3rev3 Review Submitted Accept Submission Anonymous
  Reviewer/Disclosed Author Read Review More Actions`.
- Decision buttons rendered: `Request Revisions`, `Accept Submission`,
  `Create New Review Round`, `Decline Submission`. **`Cancel Review Round` is absent**
  (`getByRole('button', {name: 'Cancel Review Round'})` matches nothing in the stage
  view; verified against the full aria snapshot of the dialog).
- Positive control for button presence: same session, SubA round 2 with only an
  unanswered invitation (case b below) and SubC round 1 with no reviewers (case c) both
  DO render `Cancel Review Round`.

### (b) Round 2 with only an unanswered invitation — cancel (claim)

Setup (context): on SubA round 2, `Add Reviewer` (button) opened dialog
**"Add Reviewer"** (`getByRole('dialog', {name: 'Add Reviewer'})`) — headings
"Submission Author List", "Locate a Reviewer", reviewer list with
`Select u26p3rev1/2/3` buttons, links `Create New Reviewer` / `Enroll Existing User`.
Selected `u26p3rev2`; the form shows an email body composer, checkbox "Do not send email
to Reviewer.", `Response Due Date` 2026-08-28, `Review Due Date` 2026-08-28, heading
"Files To Be Reviewed" with one pre-checked file checkbox, and submit button
`Add Reviewer`. After submit: Round 2 `Reviewers` row **`u26p3rev2 Request Sent
Anonymous Reviewer/Anonymous Author`**; `Round 2 Status` paragraph now "Awaiting
responses from reviewers."

Reviewer's own list BEFORE cancel — as `u26p3rev2`,
`/u26p3/dashboard/reviewAssignments` (view "My Assignments as Reviewer"): heading
**"Action Required by me (1)"**, views sidebar `1 Action Required by me`,
`1 All assignments`, `0 Completed`, `0 Declined`, `0 Published`, `0 Archived`; row
`8 U26P3 SubA resubmit round case Please accept or decline this request by 2026-08-28
Respond to request`.

Cancel: editor clicked `getByRole('button', {name: 'Cancel Review Round'})` on Round 2 →
full-page wizard `/u26p3/decision/record/8?decision=31&...&reviewRoundId=11`:

- Heading **"Cancel Review Round: Notify Authors"**; intro paragraph: **"Cancel the
  current round of review and send the submission back to the last round of review. If
  this is the first review round, it will be moved to the submission stage."**
- Steps: "1 Notify Authors" (email template button "Review Round Cancelled"), then
  "2 Notify Reviewers" (heading "Cancel Review Round: Notify Reviewers", composer with
  `To: u26p3rev2`), then `Record Decision`.
- Confirmation dialog title **"Cancelled the latest round of review."** — paragraph "The
  review round for the submission, U26P3 SubA resubmit round case, has been cancelled.
  All notifications have been sent, except any you chose to skip." + link
  `View Submission Summary`.

After cancel (editor, submission 8 workflow):

- **Stage menu**: `Review` treeitem now contains only **"Review Round 1"** — the
  "Review Round 2" entry is gone.
- **Landing round**: heading `Workflow: Review (Round 1)`, header badge
  `Review (Round 1)`.
- Round 1 status paragraph now reads **"Awaiting responses from reviewers."** (it read
  "Revisions requested from the author to be taken to a new review round." before the
  round was advanced, and "The submission has been advanced to the next round of review"
  while round 2 existed). Context observation.
- Decision buttons on the restored round 1: `Request Revisions`, `Accept Submission`,
  `Create New Review Round`, `Decline Submission` (no `Cancel Review Round`).

Reviewer's own list AFTER cancel — same URL as `u26p3rev2`: **"Action Required by me
(0)"** with `No Items`; sidebar counts now `0 Action Required by me`,
**`0 All assignments`**, `0 Declined`; "All assignments (0)" view also `No Items`. **The
assignment disappeared from every reviewer-side list** (it is not shown as declined or
cancelled — it is simply no longer listed).

### (c) Cancel a Round 1 — lands on Submission stage (claim)

SubC (id 10), round 1, no reviewers. Before cancel: `Round 1 Status` present,
`Cancel Review Round` button present (count 1).

- Wizard for a round with no reviewers has **only one step**: heading is plain
  **"Cancel Review Round"** (h1), step list only "1 Notify Authors" — there is no
  "Notify Reviewers" step — and the primary button is `Record Decision` directly (no
  `Continue`). Same intro paragraph as in (b).
- Confirmation dialog: **"Cancelled the latest round of review."** / "The review round
  for the submission, U26P3 SubC cancel round1 case, has been cancelled. ...".
- After `View Submission Summary`: URL
  `dashboard/editorial?workflowSubmissionId=10&...&workflowMenuKey=workflow_1`, heading
  **`Workflow: Submission`**, header badge `Submission`; the `Review` treeitem has no
  round children. Submission-stage decision buttons rendered: `Schedule For Publication`,
  `Send for Review`, `Accept and Skip Review`, `Decline Submission`. (Presence of
  `Schedule For Publication` here: context observation — actor is in the Journal editor
  group, which is manager-level on scratch journals.)

---

## Item 8 — Review Files selection dialog (untick a file)

Performed on SubA Round 2 while it existed (before the item-7b cancel), i.e. with
`Files for Review` containing `5 revised-manuscript.pdf`.

### Dialog identity (claim)

`getByRole('button', {name: 'Upload/Select Files'})` on the `Files for Review` panel
opens a legacy grid dialog titled **"Current Review Files For Round 2"**
(`getByRole('dialog', {name: 'Current Review Files For Round 2'})`, h1 with the same
text) — matches the expected "Current Review Files For Round {N}". Contents: h4
**"Review Files"**, link `Upload Review File` (wizard URL with `fileStage=4`),
checkbox "Show files from all accessible workflow stages.", a table with columns
`Select / Name / Component`, category row **"Review"**, one file row
` 5 revised-manuscript.pdf` / `Article Text` with checkbox
`input[name="selectedFiles[]"][value="5"]`, buttons `Cancel` (link) and `OK`. The form
posts to `$$$call$$$/grid/files/review/manage-review-files-grid/update-review-files`.

### Checkbox pre-state (claim — disagrees with the panel)

On first open — before this probe submitted anything from this dialog — the checkbox for
file 5, the file currently listed in the round's `Files for Review` panel, renders
**unchecked** (`isChecked()` → `false`; served HTML has no `checked` attribute). So the
file promoted into round 2 by the New Review Round decision (where its wizard checkbox
WAS pre-ticked, see item 6) arrives in the manage dialog in the unselected state, while
the panel behind the dialog lists it as a review file.

### Tick / untick round-trip (claim)

To make the untick meaningful the probe first ticked and saved, then unticked:

1. Tick `selectedFiles[]` → `OK` → POST `update-review-files` returned 200
   `{"status":true,...,"events":[{"name":"dataChanged"}]}`. Full page reload: panel
   unchanged (still the one row); reopened dialog now shows the checkbox **checked**.
2. Untick → `OK` → same 200 response. Full page reload:
   - **The file did NOT leave the round's `Files for Review` panel** — the table still
     lists `5 revised-manuscript.pdf 2026-07-31 Article Text More Actions` (locator:
     `table[aria-label="Files for Review"]`, editor session, fresh `page.goto`).
   - Reopened dialog: file row still listed under "Review", checkbox unchecked again.
   - The file still exists in the submission's files as far as the screens show: it stays
     listed (unchecked) in this dialog's inventory, and the author's original revision
     (id 3) is still in round 1's `Revisions Uploaded`. The Submission stage
     `Submission Files` panel shows `No Items` (that panel was always empty for this
     seeded submission — no stage-1 files existed).

Net observed behaviour: the untick+OK round-trips a saved state change (the checkbox
state persists across reloads) but the editor-facing `Files for Review` table renders the
file identically whether ticked or unticked. The probe expectation "the file left the
round" did not render anywhere the editor screens show; the only visible difference is
the checkbox state inside the dialog itself. (Reviewer-side visibility of a ticked vs
unticked file was not probed — out of this item's scope.)

---

## Proposed app-changes / PROGRESS content (returned, not written)

- Round advanced to next round: round 1 keeps `Upload`, `Upload/Select Files`,
  `Add Reviewer`, `Add`, `Assign` panel buttons while all decision buttons disappear
  (item 6). Status heading changes from "Round 1 Status" to plain "Status".
- Cancelling round 2 rewrites round 1's status line to "Awaiting responses from
  reviewers." even though round 1 previously carried a resubmit decision (item 7b).
- Reviewer-side: a cancelled round's unanswered invitation vanishes from all reviewer
  list views including "All assignments" (item 7b).
- "Current Review Files For Round {N}" dialog: checkbox state (viewable flag) does not
  affect what the round's `Files for Review` panel lists, and a decision-promoted file
  arrives unselected in that dialog (item 8) — screen-vs-screen disagreement worth a
  Findings-register entry.
