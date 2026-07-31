# U26 probe P2 — Request Revisions paths (items 4–5)

Probe agent P2, 2026-07-31, OJS fleet at `http://127.0.0.1:8000` (Playwright
chromium, headless). All times below are local (+02:00) as shown by
Mailpit/the UI.

## Fixture (scenario API, sanctioned setup)

- Scratch journal `u26p2` ("U26 P2 Probe Journal", contextId 63) via
  `POST /api/v1/_test/scenarios/context`, users:
  - `u26p2ed` — Petra Probeditor, role `sectionEditor`, sections `["ART"]`,
    email `u26p2ed-ojs@mail.test` (unique throwaway; scopes every Mailpit
    assertion below)
  - `u26p2au` — Aldo Probauthor, role `author`, email `u26p2au-ojs@mail.test`
- Two submissions via `POST /api/v1/_test/scenarios/submission`, submitter
  `u26p2au`, `decisions: ["sendExternalReview"]`, no reviewers:
  - id 6 "Probe u26p2a review revisions" (item 4), review round 1 (id 6)
  - id 7 "Probe u26p2b review revisions" (item 5), review round 1 (id 7)

**Setup deviation that the digest should know about.** Although `u26p2ed` was
seeded as a section editor of the section the submissions went to
(`subeditor_submission_group` row present: context 63, assoc 64 = the
submissions' section, user 119, group 1121), the submit-time auto-assignment
assigned NO editor to either submission — opening the workflow as `u26p2ed`
produced the dialog "Error / The current role does not have access to this
operation." (`u26p2-item4-editor-1-workflow.png`). Context, code-side pointer
only: `SubEditorsDAO::assignEditors()` filters candidate assignments with
`$userGroups->keys()` (collection indexes 0..n, not user_group_ids) at
`lib/pkp/classes/context/SubEditorsDAO.php:211-216`, which can only match when
a context's group ids happen to be small; scratch-journal group ids here are
~1121. Workaround used: signed in as `admin` (auto-enrolled Journal manager on
scratch contexts), workflow → PARTICIPANTS "Assign" button
(`getByRole('button', {name: /^Assign$/})`) → "Assign Participant" dialog →
"Locate a User" select `name="filterUserGroupId"` option "Section editor"
(value 1121) → "Search User By Name" = "Probeditor" → Search → user radio
`input[name="userId"]` → OK. Both submissions then listed "Petra Probeditor /
Section editor" under PARTICIPANTS. The assignment produced **no email** to
`u26p2ed-ojs@mail.test` (Mailpit search total remained 0), so the mailbox was
clean before item 4's flow.

Mailpit scoping used throughout:
`GET http://127.0.0.1:8025/api/v1/search?query=to:"u26p2ed-ojs@mail.test"`.

---

## Item 4 — Request Revisions, "not subject to a new round" (submission 6)

### Editor side (`u26p2ed`)

URL `/index.php/u26p2/dashboard/editorial?workflowSubmissionId=6`; the
workflow renders inside `[data-cy="active-modal"]`.

- Round status box BEFORE the decision (heading "Round 1 Status", observed via
  innerText of `[data-cy="active-modal"]`): "**Waiting for reviewers to be
  assigned.**"
- Right-rail decision buttons offered (in DOM order, all enabled): "Request
  Revisions", "Accept Submission", "Create New Review Round", "Cancel Review
  Round", "Decline Submission".
- Clicking `getByRole('button', {name: 'Request Revisions'})` opens a side
  modal (role=dialog) titled "**Request Revisions**" containing a form headed
  "**Require New Review Round**" with two radios
  (`input[type="radio"]`, labels read from the enclosing `<label>`):
  - value "4", **checked by default**: "Revisions will not be subject to a new
    round of peer reviews."
  - value "5", unchecked: "Revisions will be subject to a new round of peer
    reviews."
  - single form button: "**Next**".
- Kept the default (value 4) and clicked Next → full-page decision wizard (not
  a dialog), breadcrumb "Dashboard / Probauthor, Probe u26p2a review revisions
  / Request Revisions", heading "**Request Revisions**", subtitle "The author
  must provide revisions before this submission will be accepted for
  publication." One step: "**Notify Authors**" ("Send an email to the authors
  to let them know that revisions will be required before this submission will
  be accepted for publication. … This email will not be sent until the
  decision is recorded."). Email Templates list offers "Revisions Requested";
  composer pre-filled To: "Aldo Probauthor", Subject: "Your submission has
  been reviewed and we encourage you to submit revisions". Footer controls:
  link "Skip this email", buttons "Cancel", "**Record Decision**"
  (`u26p2-item4-e3-wizard.png`).
- Clicked "Record Decision" → confirmation dialog: heading "**Revisions
  Requested**", body "Revisions for the submission, Probe u26p2a review
  revisions, have been requested. All notifications have been sent, except any
  you chose to skip.", button "**View Submission Summary**".
- Round status box AFTER the decision (fresh visit as `u26p2ed`):
  "**Revisions have been requested.**"

### Author side (`u26p2au`)

URL `/index.php/u26p2/dashboard/mySubmissions` (list) and
`…?workflowSubmissionId=6` (workflow dialog).

- Dashboard list row for id 6 (columns SUBMISSIONS / STAGE / EDITORIAL
  ACTIVITY / ACTIONS): stage "Review (Round 1)", EDITORIAL ACTIVITY cell reads
  "**Revision requested**" with a button labeled "**Submit revisions**"
  (`getByRole('button', {name: 'Submit revisions'})`), plus the row's "View"
  action. Left sidebar view counts at this point: "Revisions requested 1",
  "Revisions submitted 0". Header Tasks bell: "Tasks 1".
- Tasks panel (header button `getByRole('button', {name: /^Tasks/})` → dialog
  "Tasks"): one entry — "**Revision required.**" followed by the submission
  title "Probe u26p2a review revisions"; grid controls "Mark New Mark Read
  Delete".
- Author workflow dialog before upload: Round 1 Status "**Revisions have been
  requested.**"; Notifications strip "Your submission has been reviewed and we
  encourage you to submit revisions — 2026-07-31 03:14 PM"; panel "Revisions
  Uploaded" ("These files have been submitted by the author after revisions
  were requested", header button "Upload", grid "No Items"); block "Author
  Response / Respond to Reviews" with button "Submit Response"; bottom action
  button "**Upload revisions**" (`getByRole('button', {name: 'Upload
  revisions'})`).
- "Upload revisions" opens the legacy wizard dialog "**Upload Review File**"
  with steps "1. Upload File / 2. Review Details / 3. Confirm". Step 1
  requires "Article Component*" (`select[name="genreId"]`; attaching a file
  first produces "Errors occurred processing this form / Missing or invalid
  component!"). With "Article Text" selected, `setInputFiles` on the hidden
  `input[type="file"]` uploads immediately ("Change File" replaces the
  dropzone) and "Continue" (`#continueButton`) advances to step 2 "Name the
  file (e.g., Manuscript; Table 1) *" + textarea "Summary of Changes
  (Amendment Notice)" ("Describe the key changes made in this version …").
- **Sequencing fact (claim):** the first wizard run was abandoned at step 2
  (Review Details; Confirm never reached, browser closed), yet by then the
  file row existed (`submission_files` id 1, file_stage 15), the round status
  had already flipped, and the "Revised Version Uploaded" email had already
  been sent (Mailpit timestamp 2026-07-31T17:18:08 — during that aborted run).
  Upload side-effects fire at the step-1 file transfer, not at wizard
  completion.
- A second, fully completed wizard run on the same submission (step 2 →
  Continue → step 3 "File Added / Add Another File" → "**Complete**") closed
  the wizard back to the workflow dialog and added a second grid row. This
  completed upload produced **no additional email** (Mailpit total for the
  recipient still 1 until item 5's upload).
- Status flip after upload (author view): Round 1 Status "**Revisions have
  been submitted and a decision is needed.**" Grid "Revisions Uploaded" lists
  "u26p2a-revision.txt / 2026-07-31 / Article Text". The bottom action button
  "**Upload revisions" is still offered** on this (no-new-round) path after
  uploading. The "Author Response / Submit Response" block, present before the
  upload, is no longer rendered after it (context).
- Task disappearance (claim): after the upload, the dashboard row's EDITORIAL
  ACTIVITY cell reads "**Review update 0/0**" with no button; sidebar counts
  flipped to "Revisions requested 0 / Revisions submitted 1"; the Tasks panel
  shows "No Items / 0 - 0 of 0 items" and the header bell lost its count
  ("Tasks", no numeral).
- Editor's view after upload: Round 1 Status "**Revisions have been submitted
  and a decision is needed.**"

### Mailbox (scoped to `u26p2ed-ojs@mail.test`)

One message at 2026-07-31T17:18:08: Subject "**Revised Version Uploaded**",
From "Aldo Probauthor" `<u26p2au-ojs@mail.test>` (the author's own address is
the From), To "Petra Probeditor" `<u26p2ed-ojs@mail.test>`. Body text:

> Dear Petra Probeditor,
>
> The author has uploaded revisions for the submission, *Probauthor — Probe
> u26p2a review revisions*.
>
> As an assigned editor, we ask that you login and view the revisions (
> http://127.0.0.1:8000/index.php/u26p2/dashboard/editorial?workflowSubmissionId=6
> ) and make a decision to accept, decline or send the submission for further
> review.
>
> —
> This is an automated message from U26 P2 Probe Journal (
> http://127.0.0.1:8000/index.php/u26p2 ).

---

## Item 5 — Request Revisions, "subject to a new round" / resubmit path (submission 7)

### Editor side (`u26p2ed`)

- Round 1 Status before: "**Waiting for reviewers to be assigned.**"
- Same "Request Revisions" entry modal; checked the second radio (value "5",
  label "Revisions will be subject to a new round of peer reviews."), clicked
  "Next" → full-page wizard, breadcrumb "… / Resubmit for Review", heading
  "**Resubmit for Review**", subtitle "The author must provide revisions that
  will be sent for another round of review before this submission will be
  accepted for publication." Single step "Notify Authors" (same explanatory
  text as item 4); template offered: "**Resubmit for Review**" ("Dear
  {$recipientName},After reviewing your submission, "{$submissionTi…").
- "Record Decision" → confirmation dialog: heading "**Revisions Requested**",
  body "Revisions for the submission, Probe u26p2b review revisions, have been
  requested. **A decision to send the revisions for another round of reviews
  was recorded.** All notifications have been sent, except any you chose to
  skip.", button "View Submission Summary".
- Round 1 Status after the decision (editor and author views identical):
  "**Revisions requested from the author to be taken to a new review
  round.**"

### Author side (`u26p2au`) — ONE completed upload

- Author notification strip: "Your submission has been reviewed - please
  revise and resubmit — 2026-07-31 03:23 PM". Dashboard row wording identical
  to item 4: "**Revision requested**" + button "**Submit revisions**". Tasks
  panel entry: "**Resubmit for review.** / Probe u26p2b review revisions".
  Bottom action button "**Upload revisions**" offered.
- Uploaded exactly one file (`u26p2b-revision.txt`) through the full 3-step
  wizard in a single run (Article Text → Continue → Continue → Complete; no
  aborted attempts on this submission).
- AFTER that single upload (fresh page loads, author view):
  - Status box: "**Revisions submitted. A new review round needs to be
    created.**"
  - The bottom "**Upload revisions" action button is GONE** —
    `getByRole('button', {name: 'Upload revisions'})` count 0 (matches the
    draft register A1 expectation for this path; contrast item 4 where the
    button remained after upload).
  - The "Revisions Uploaded" panel's own header "**Upload**" button
    (`getByRole('button', {name: 'Upload', exact: true})`) is still present
    and still opens the "Upload Review File" wizard (opened and cancelled
    without uploading). So the vanish affects only the bottom action button,
    not the panel upload entry point. (Context: author guard list at
    `lib/ui-library/src/pages/workflow/composables/useWorkflowConfig/workflowConfigAuthorOJS.js:222-227`
    lists REVISIONS_REQUESTED, RESUBMIT_FOR_REVIEW and REVISIONS_SUBMITTED
    twice — duplicated — and omits RESUBMIT_FOR_REVIEW_SUBMITTED, the status
    this round is now in; DB `review_rounds.status` = 15 for round 7 vs 11 for
    round 6.)
  - The Tasks panel entry "**Resubmit for review.**" is **still present**
    after the upload (bell "Tasks 1") — contrast item 4, where the "Revision
    required." task disappeared on upload.
  - Dashboard row: "**Review update 0/0**", no button. Sidebar counts:
    "Revisions requested 0", "Revisions submitted 1" — the 1 is submission 6;
    submission 7 (round status 15) is counted in neither revisions bucket
    (context).
- Editor's view after upload: Round 1 Status "**Revisions submitted. A new
  review round needs to be created.**"; grid lists "u26p2b-revision.txt /
  2026-07-31 / Article Text".
- Mailbox: a second message to `u26p2ed-ojs@mail.test` at
  2026-07-31T17:24:39, Subject "**Revised Version Uploaded**", snippet "Dear
  Petra Probeditor, The author has uploaded revisions for the submission,
  Probauthor — Probe u26p…" (same mailable as item 4).

---

## Incidental context observations (not promotable as-is)

1. Submit-time editor auto-assignment assigned nobody on this scratch journal
   despite a matching section-editor assignment (details + code pointer in the
   Fixture section above). Every later observation depended on the manual
   Assign Participant workaround.
2. Assigning a participant via the "Assign Participant" dialog with no
   predefined message selected sent no email.
3. Revision-upload side effects (file row, round-status flip, "Revised Version
   Uploaded" email) fire when the file lands in step 1 of the "Upload Review
   File" wizard, before Review Details/Confirm; a subsequent completed upload
   on the same round sent no further email.
4. On the resubmit path the round's stored status after upload is 15
   (`REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED`) even though
   `ReviewRound.php:59-61` describes 15 as a calculated status.
5. The author's "Author Response / Submit Response" block renders only while
   the round status is REVISIONS_REQUESTED (or accepted); it disappears after
   the pending-revisions upload.
6. On a second upload to the same round, wizard step 1 additionally offers a
   `select[name="revisedFileId"]` ("If you are uploading a revision of an
   existing file, please indicate which file." / "This is not a revision of an
   existing file").
7. The scratch-journal chrome shows a raw locale key "##common.help##" in the
   top bar on every page (visible in all dumps).

## Proposed app-changes note (returning here instead of editing docs)

- `docs/e2e/app-changes.md` candidate: none — no app behavior was changed by
  this probe; all mutations were confined to scratch context `u26p2`
  (submissions 6–7, users 119–120) plus two Mailpit messages scoped to
  `u26p2ed-ojs@mail.test`.

Probe artifacts (screenshots, scripts) live only in the session scratchpad
(`u26p2-*.png`, `u26p2-*.js`); nothing was written into any repo tree except
this report.
