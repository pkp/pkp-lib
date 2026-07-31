# U26 probe P5 — Review stage & rounds (items 11–12)

Probe date: 2026-07-31. OJS fleet at `http://127.0.0.1:8000`, Playwright
(chromium, headless), all screens driven as signed-in users. Workflow renders
inside `[data-cy="active-modal"]`; observations scoped to it unless noted. Text
quotes are verbatim `innerText`; headings/buttons read via
`getByRole('heading')` / `getByRole('button')`.

## Seeded state

Scratch journal `u26p5` ("U26 P5 Probe Journal", contextId 66) via
`POST /api/v1/_test/scenarios/context`, throwaway users `u26p5ed` (role key
`editor` = Journal editor group), `u26p5au` (author), `u26p5rev` /
`u26p5rev2` (`externalReviewer`). Two submissions via
`POST /api/v1/_test/scenarios/submission`, submitter `u26p5au`,
`decisions: ["sendExternalReview"]`, stage 3, round 1:

- **SubA** id 15 "U26P5 SubA open review case" — item 11 main + item 12.
- **SubB** id 16 "U26P5 SubB anonymous control" — item 11 control; round 1
  seeded with `u26p5rev2` status `accepted`.

The step-2 scenario core seeds no submission files or reviewer attachments;
everything below (reviewer accept/complete, attachments, comments, editor
confirm, the decision, the share step) entered through the screens. The
submit-time editor auto-assignment DID assign the editor on this scratch
journal — `u26p5ed` opened both workflows without the "does not have access"
error a sibling probe (P2) hit, so no manual Assign-Participant step was needed
(fixture context; differs from P2/P3's experience on their scratch journals).

Mailpit was not cleared.

**Setup driven through the screens (context for both items):**

1. As `u26p5ed`, SubA: `Add Reviewer` → selected `u26p5rev` → review-type radios
   `reviewMethod` (2 = `Anonymous Reviewer/Disclosed Author`, DEFAULT checked;
   1 = `Anonymous Reviewer/Anonymous Author`; 3 = `Open`) — selected **Open**
   (radio `reviewMethod=3`), submitted. Reviewer row then `u26p5rev / Request
   Sent / Open`.
2. As `u26p5rev`, `/index.php/u26p5/reviewer/submission/15`: accepted (privacy
   consent `Yes, I agree to have my data collected and stored according to the
   privacy statement.` + `Accept Review, Continue to Step #2`), through
   Guidelines, on step 3 typed the "For author and editor" TinyMCE comment
   ("...please expand the methods section. (probe u26p5 shared text)") and the
   "For editor" comment ("...mild concerns about the citations. (probe u26p5
   private text)"), uploaded **two** attachments via the Reviewer Files grid
   `Upload File` link (legacy 3-step wizard): `p5-attach-A.txt`
   (submissionFileId 6) and `p5-attach-B.txt` (submissionFileId 7); picked
   recommendation `Revisions Required` (select `#reviewerRecommendationId`);
   `Submit Review` → confirm dialog "Are you sure you want to submit this
   review?" → `OK` → step 4 "Review Submitted".
3. As `u26p5ed`, SubA reviewer row `Read Review` → editor modal (title
   "Review: U26P5 SubA open review case", reviewer `u26p5rev`,
   `Completed: 2026-07-31 03:42 PM`, `Recommendation: Revisions Required`,
   Reviewer Files grid lists both files, editable Recommendation select +
   Reviewer rating, `Download Review Form`) → `Confirm`. Row became
   `Complete / Revisions Required / Open / Thank Reviewer / Revert Decision`.
4. As `u26p5ed`, SubA: `Request Revisions` → entry modal "Require New Review
   Round" (default radio "Revisions will not be subject to a new round of peer
   reviews.") → `Next` → decision wizard `decision=4`. In "Request Revisions:
   Notify Authors" opened `Attach Files` (panel buttons `Upload File` /
   `Attach Review Files` / `Attach Submission Files` / `Attach Library Files`)
   → `Attach Review Files`: the list offered BOTH `u26p5rev — p5-attach-B.txt`
   and `u26p5rev — p5-attach-A.txt` (each with `Download`); checked ONLY
   `p5-attach-A.txt`, `Attach Selected`. Composer then showed "Attached Files:
   p5-attach-A.txt / Remove p5-attach-A.txt". `Continue` → step "2 Notify
   Reviewers" (template "Notify Reviewers of Decision", To: `u26p5rev`) →
   `Record Decision` → confirmation "Revisions Requested / Revisions for the
   submission, U26P5 SubA open review case, have been requested. All
   notifications have been sent, except any you chose to skip. / View
   Submission Summary". So exactly one of the two reviewer attachments
   (`p5-attach-A.txt`) was shared with the author.

---

## Item 11 — Author "Read Review" on a completed OPEN review (two attachments, one shared)

### Author workflow view before opening the review (context)

As `u26p5au` at
`/index.php/u26p5/dashboard/mySubmissions?workflowSubmissionId=15`. Primary-
column headings, in order: `WORKFLOW: REVIEW (ROUND 1)`, `Round 1 Status`
(body "Revisions have been requested."), **`Notifications`** (see item 12),
`Reviewers`, `Revisions Uploaded`, `Review Tasks & Discussions`,
`Author Response` (body "Respond to Reviews", button `Submit Response`);
bottom action button `Upload revisions`.

The **`Reviewers`** panel (author view) has columns `REVIEWER / TYPE / ACTIONS`
(no status column, no `Add Reviewer` button); one row:
`u26p5rev / Open / Read Review` (locator: `tr` containing `u26p5rev`, action
`getByRole('button', {name: /Read Review/i})`). [claim — the author DOES see
the open reviewer's name `u26p5rev` in this row]

### "Read Review" window contents (claim)

`Read Review` opens a side window (`[role="dialog"]`). Full contents:

- Title: `Review: U26P5 SubA open review case`
- Reviewer name line: `u26p5rev`
- `Completed: 2026-07-31 03:42 PM`
- `Recommendation: Revisions Required`
- `Reviewer Files` grid (columns `Name / Date / Component`, a `Search` box; no
  `Upload File` link on the author side) — see the attachment listing note
  below.
- **No `Download Review Form` button** (the editor's window at setup-step 3
  had one). [context]

**Review TEXT in the window (claim):** the window renders **NO reviewer comment
text at all** — neither the shared "For author and editor" comment
("...please expand the methods section...") nor the private "For editor"
comment appears. Verified programmatically inside the dialog: `iframes: 0`;
`innerText` does not contain the shared marker "shared text", the private
marker "private text", or the label "For author"; no element with a
`comment`/`review`-classed body renders the text. So the review text is
**absent from the author's Read Review window** (recorded here as the observed
state; relevant to draft register A3, which expected the TEXT to be filtered —
on OJS it does not render at all, whereas the OMP sibling P7 reported the
shared comment DID show in its window). [claim — divergence for the digest]

**Which attachments are listed (the point of this sub-item):** ROUTED to the
maintainer's private security file per the Frame. This report intentionally
does not record the attachment listing, nor whether an unshared file is
retrievable. Routing is hereby declared; the public report stays silent on the
content.

### Control — completed ANONYMOUS review, author's review list shows no reviewer name (claim)

SubB (id 16) carried a completed review by `u26p5rev2`, review type
`Anonymous Reviewer/Disclosed Author` (the scratch journal's DEFAULT review
mode — `reviewMethod=2`; confirmed on the editor side where the row read
`u26p5rev2 / Complete / Accept Submission / Anonymous Reviewer/Disclosed
Author`, editor confirmed via `Read Review` → `Confirm`).

As `u26p5au` at
`/index.php/u26p5/dashboard/mySubmissions?workflowSubmissionId=16`, the author
workflow view headings, in order, are: `WORKFLOW: REVIEW (ROUND 1)`,
`Round 1 Status` (body "All reviews are confirmed and a decision is needed."),
`Revisions Uploaded`, `Review Tasks & Discussions`. **There is NO `Reviewers`
panel** on the author view for this anonymous review — no reviewer row, no
`Read Review` button, no reviewer name. Programmatic check: the modal
`innerText` contains no "Reviewers" heading and does not contain the string
`u26p5rev2` anywhere. [claim — the reviewer's name is not disclosed to the
author; in fact no review-list surface is presented to the author at all for a
non-open review]

Contrast (claim, from the item-11 main case above): on SubA, where the review
type was **Open**, the author DID get a `Reviewers` panel with the reviewer's
name (`u26p5rev`) and a `Read Review` action. So the author-side reviewer
panel appears only for the open review; the anonymous review yields no author-
visible reviewer identity or review list.

---

## Item 12 — Author "Notifications" list on the review stage, and the read-only letter

Observed on SubA (id 15) as `u26p5au`, review-stage workflow view, after the
Request Revisions decision.

### The Notifications list (claim)

Panel heading: **`Notifications`** (a primary-column panel, positioned between
`Round 1 Status` and `Reviewers` in the author view). One entry, rendered as a
subject line with the date on the next line:

- Subject: `Your submission has been reviewed and we encourage you to submit revisions`
- Date: `2026-07-31 03:44 PM`

(Locator: `modal.getByText('Your submission has been reviewed and we encourage
you to submit revisions')`, count 1.)

### The read-only letter view (claim)

Clicking the entry opens a side window (`[role="dialog"]`) headed
`Notifications`, then the subject line and the date repeated, then the full
letter. No edit/reply/compose controls: the only buttons in the window are the
page-chrome `Tasks` / user-menu / `Close`; **editable-field count inside the
window is 0** (no `input`, `textarea`, or `[contenteditable="true"]`). Letter
body, verbatim:

> Dear u26p5au,
>
> Your submission "U26P5 SubA open review case" has been reviewed and we would
> like to encourage you to submit revisions that address the reviewers'
> comments. An editor will review these revisions and if they address the
> concerns adequately, your submission may be accepted for publication.
>
> The reviewers' comments are included at the bottom of this email. We invite
> you to review the comments and provide a response to the reviewers' feedback.
>
> You may include clarifications, address concerns, or indicate any planned
> revisions. Your response will help inform the editorial decision-making
> process.
>
> You can access the reviews and submit your response below
>
> Submit Author Response
>
> When you have completed your revisions, you can upload revised documents at
> your submission dashboard. If you have been logged out, you can login again
> with the username u26p5au.
>
> If you have any questions, please contact me from your submission dashboard.
>
> We look forward to receiving your revised submission.
>
> Kind regards,
>
> u26p5ed
>
> The following comments were received from reviewers.
>
> u26p5rev
> Recommendation: Revisions Required

Notes on the letter appendix (claim): the "The following comments were received
from reviewers." appendix names the reviewer (`u26p5rev` — the open review's
name) and prints `Recommendation: Revisions Required`, but **no reviewer
comment text** follows the recommendation (the shared "For author and editor"
comment does not appear in the letter either — consistent with its absence from
the Read Review window in item 11). `Submit Author Response` renders as a plain
line of the letter (not a working control in this read-only view).

---

## Proposed app-changes / Findings-register content (returned, not written)

- **Author open-review Read Review window (OJS) shows no reviewer comment
  text.** The window lists title / reviewer name / `Completed:` /
  `Recommendation:` / a Reviewer Files grid, but neither the shared "For author
  and editor" comment nor the private one renders anywhere in it — nor in the
  decision letter's reviewer appendix, which stops at `Recommendation:`. This
  diverges from the OMP sibling observation (P7) where the shared comment DID
  appear in the author's window. Screen-vs-expectation gap for draft register
  A3 (which anticipated the TEXT being *filtered*, i.e. shared-only shown; on
  OJS none shows).
- **Anonymous review → no author-side Reviewers panel.** For a review typed
  `Anonymous Reviewer/Disclosed Author`, the author workflow view renders no
  Reviewers panel, no reviewer name, no Read Review action (only the open
  review surfaces one). Confirms the anonymity control.
- **Notifications panel + read-only letter** behave as expected (item 12): a
  single subject+date row opens a no-controls letter view (0 editable fields).

## Routing note

One item-11 observation (which reviewer attachments the author's Read Review
window lists, and their retrievability) was appended to the maintainer's
private security file per the Frame. This report and this return say only that
the routing happened; they do not record the content.

Probe scripts and screenshots live only in the session scratchpad
(`p5-*.js`, `p5-*.png`); nothing was written into any repo tree except this
report and the private security file. Scratch context `u26p5` (submissions
15–16, users 133–136) remains in `ojs_test`; `publicknowledge` and the seeded
roster untouched; Mailpit not cleared.
