# U26 adversarial verification — chunk 3 (Rules & state, rules 10–14)

Agent: adversarial verification chunk 3 (RUNBOOK per-feature loop step 8).
Date: 2026-07-27. Fleets driven live: OJS `http://127.0.0.1:8000`, OMP
`http://127.0.0.1:8100` (the `localhost` host name 400s; the servers answer on
`127.0.0.1`). Every state was seeded through
`POST /index.php/index/api/v1/_test/scenarios/submission` (and, where a real
email or a real decision record was needed, driven through the decision wizard
in Chromium). Scratch submissions only; no seeded `publicknowledge` account or
submission was modified; Mailpit untouched.

Scope: rules 10–14 of
`lib/pkp/docs/product/specs/review-stage-and-rounds.md`, their footnotes
(d, e, f, g, l, m, n, c) and the register entries A1, A2 (context only), A3
(context only), A6, A7, OMP4.

Marker convention: **CLAIM** = an observation that verifies or contradicts spec
text; **context** = incidental observation offered for the spec author's
judgment, not a verdict on a written claim.

Common locators used throughout:

- workflow modal: `page.locator('[role="dialog"]').last()`
- decision / recommendation area: `[data-cy="workflow-action-items"]` (button
  list = `[data-cy="workflow-action-items"] button` innerTexts)
- round panels: `[data-cy="workflow-primary-items"]`; panel titles =
  `[data-cy="workflow-primary-items"] h2, h3`
- side column: `[data-cy="workflow-secondary-items"]`
- round selection: the workflow modal's `nav`, `getByText('Review Round N', {exact: true})`
  (OMP: same labels)
- editorial view `/{context}/en/dashboard/editorial?workflowSubmissionId={id}`;
  author view `/{context}/en/dashboard/mySubmissions?workflowSubmissionId={id}`
- author revision upload: action button `Upload revisions`, or
  `[data-cy="workflow-primary-items"] >> getByRole('button', {name: 'Upload', exact: true})`

---

## Rule 10 — decision-button display — **AGREE** (all bullets, both apps)

### Cases driven

Scratch submissions on each fleet, one per state, entry decision
`sendExternalReview` (OJS) / `skipInternalReview` (OMP), viewed as
`editor.diana` unless noted.

| Case | OJS / OMP ids | Round state |
|---|---|---|
| no reviewer | 229 / 129 | "Waiting for reviewers to be assigned." |
| one invited reviewer | 224 / 124 | "Awaiting responses from reviewers." |
| one accepted reviewer | 225 / 125 | "Awaiting responses from reviewers." |
| one **declined invitation** | 226 / 126 | "All reviews are confirmed and a decision is needed." |
| one completed review | 227 / 127 | "New reviews have been submitted." |
| mixed: invited + accepted | 228 / 128 | "Awaiting responses from reviewers." |
| accepted submission (stage now Copyediting) | 230, 293 / 130, 168 | round selected from the side menu |
| declined submission | 231, 291 / 131 | "Submission declined." |
| declined → **Revert Decline** driven in the wizard | 231 / 131 | round-trip |
| round 2 **cancelled** in the wizard | 225 | previous round becomes current |

### Observations

**10.1 CLAIM — the five active-round buttons, in Rule 10's order.** On every
active latest round the area reads, in order: `Request Revisions`,
`Accept Submission`, `Create New Review Round`, `Cancel Review Round`,
`Decline Submission`. Byte-identical on OJS and OMP.

**10.2 CLAIM — Cancel Review Round drops exactly as the rule and footnote e
state.** Present with no reviewer and with an unanswered invitation; absent
once any reviewer has **accepted**, **declined the invitation**, or
**completed** a review, and absent in the mixed round (one invited + one
accepted). The declined-invitation nuance reproduces identically on both
apps.

**10.3 CLAIM — Revert Decline replaces the set; Delete is manager/admin-only.**
Declined submission, OJS 291: `sectioneditor.ana` (assigned sub-editor)
`["Revert Decline"]`; `manager.maya` (unassigned Journal Manager)
`["Revert Decline","Delete"]`; `assistant.rita` (assistant participant) no
decision area at all. OMP 131 as editor: `["Revert Decline","Delete"]`.

**10.4 CLAIM — the revert round-trip restores the full set.** Recording
`Revert Decline` (wizard: one step, "Notify Authors") returned the five active
buttons on both apps and the round status recomputed from activity —
"Waiting for reviewers to be assigned." on a reviewer-less round. The
"Submission declined." stamp does not survive the revert.

**10.5 CLAIM — post-accept: no decision buttons on the round.** After
`Accept Submission` the submission's active stage is Copyediting immediately
(no intermediate state exists); the side menu still carries "Review Round N",
the workflow column still heads "Workflow: Review (Round N)", and the decision
area is **empty**. Consistent with Rule 10's "only when the server currently
offers that decision for the submission's active stage".

**10.6 CLAIM — after a cancel, the previous round's own state governs.**
Cancelling round 2 of OJS 225 (wizard: "Notify Authors" only, no reviewer step
— the round had no reviewers) returned the workflow to Round 1, whose accepted
reviewer left `["Request Revisions","Accept Submission","Create New Review Round","Decline Submission"]`
— i.e. Cancel is re-evaluated against the round that becomes current.

**10.7 context — the per-bullet conditions were not observed to vary.** On
every active latest round driven — fresh, awaiting, overdue-free, reviews
submitted, revisions requested, revisions submitted, resubmit-submitted —
`Request Revisions`, `Accept Submission`, `Create New Review Round` and
`Decline Submission` were all present. Rule 10's qualifiers ("while the round
can still take revisions", "while the submission is active") read as if they
gate the button; nothing in the matrix distinguished them. Only
`Cancel Review Round` varies. The spec author may want to say so plainly:
*four of the five are offered on every active latest round; only Cancel is
conditional.*

**10.8 context — a round with the author's revisions already in can still be
cancelled.** OJS 290, status "Revisions submitted. A new review round needs to
be created.", no reviewers: `Cancel Review Round` offered. Consistent with the
written condition (reviewer-based only), but a reader may expect submitted
revisions to close the door.

---

## Rule 11 — recommend-only display — **AGREE** (both apps); one precision note

### Cases driven

Mixed roster on one scratch submission per fleet (OJS 282 / OMP 153): deciding
section editor `sectioneditor.ana`, **two** recommend-only participants
(`sectioneditor.omar`, `sectioneditor.ravi`), assistant participant
`assistant.rita`, author `author.alex`, one completed review. Recommendations
recorded through the real wizard (`Recommend Accept` by omar,
`Recommend Revisions` by ravi).

### Observations

**11.1 CLAIM — the recommend-only participant's three controls.** Before
recording, omar's decision area is exactly
`["Recommend Revisions","Recommend Accept","Recommend Decline"]` — no decision
buttons. Identical on OMP.

**11.2 CLAIM — mixed roster, simultaneously.** With one recommend-only and one
deciding editor active on the same round at the same time: omar sees only the
three Recommend controls; ana sees the ordinary decision buttons; neither
view alters the other. Same on OMP.

**11.3 CLAIM — post-recording restatement.** omar's area becomes
`RECOMMENDATION / Accept Submission / Change decision` (heading rendered
upper-case by the stylesheet; button label exactly "Change decision"). Both
apps.

**11.4 CLAIM — the side box is the deciding editor's alone.** After the first
recommendation: box present for `sectioneditor.ana` (assigned deciding editor)
**and** for `manager.maya` (unassigned Journal Manager); absent for the
recommender himself, for the *second* recommend-only participant (ravi, who
kept his own three buttons), for `assistant.rita`, and for the author (whose
view has no side column at all). Both apps.

**11.5 CLAIM / precision — two recommendations render as one comma-joined
line.** With both omar and ravi recorded, the box reads
`RECOMMENDATION / Accept Submission, Request Revisions` — one line of
recommendation labels, **no recommender names and no dates**. Rule 11's
"listing every recommendation recorded for the stage" is true but invites the
reader to expect per-editor rows. Suggested wording: *"a 'Recommendation' box
naming the recommendations recorded for the stage, as a single comma-separated
line; the recommending editors are not named there (the Participants list
flags who is recommend-only)."*

**11.6 context — participant flag verbatim.** Both recommend-only rows in
"Participants" carry "Only allowed to recommend an editorial decision" (OMP
labels the group "Series editor", OJS "Section editor").

**11.7 context — recommendation status texts.** One of two recommendations →
"New editorial recommendations have been submitted."; both → "All
recommendations are in and a decision is needed." (Rule 6 / A3 territory,
noted here because it fell out of the same runs.)

---

## Rule 12 — the author's revision-upload window — **AGREE**; A1 reproduces verbatim

### Cases driven

Author `author.alex` on the author view; upload driven through the real wizard
("Upload Review File" — `1. Upload File` / `2. Review Details` / `3. Confirm`,
component select, `input[type=file]`, Continue ×2, Complete) with a 193-byte
`revision.pdf`.

| Round status | action-area `Upload revisions` | driven on |
|---|---|---|
| Waiting for reviewers to be assigned. | absent | OJS 229, 292 (round 2) |
| Awaiting responses from reviewers. | absent | OJS 297 |
| Revisions have been requested. | **present** → upload completes | OJS 289 |
| Revisions have been submitted and a decision is needed. | **present** | OJS 289 after the upload |
| Revisions requested from the author to be taken to a new review round. | **present** → upload completes | OJS 290, OMP 165 |
| Revisions submitted. A new review round needs to be created. | **absent (A1)** | OJS 290, OMP 165 |
| Submission declined. | absent | OJS 291 |
| past round (round 1 of a 2-round submission) | absent | OJS 292 |
| latest round while the submission sits in Copyediting | absent | OJS 293, OMP 168 |

**12.1 CLAIM — the three named statuses, and only those, show the button.**
Exactly as Rule 12 states, both apps.

**12.2 CLAIM — uploaded files land in "Revisions Uploaded" on both views.**
OJS 290: the author's panel and `editor.diana`'s panel both list the uploaded
`revision.pdf` rows (file ids 375, 379) with today's date and component
"Article Text" (OMP: "Appendix").

**12.3 CLAIM — A1 reproduces exactly as written, both apps.** After the
resubmit request the button is present; the moment the first file lands the
status flips to "Revisions submitted. A new review round needs to be created."
and the decision area is empty. A1's second sentence also holds: the
"Revisions Uploaded" panel's own `Upload` still opens the full wizard in that
state (with the extra "If you are uploading a revision of an existing file,
please indicate which file." selector) and **completes** — a second file (379)
now sits beside the first (375).

---

## A6 — the revisions panel's Upload outside the window — **DISAGREE (one state)**

### Cases driven

The panel's own `Upload` button clicked as the author in every state above,
both apps.

**A6.1 CLAIM — the symptom holds in four of five out-of-window states.** The
button is rendered **and enabled** (`isEnabled() === true`, no `disabled`
attribute) in every state including past rounds. Clicking it opens a dialog
titled "Upload Review File" whose entire body is
"You are not allowed to add and edit these files." — no file input (0
`input[type=file]`), no wizard steps, only the modal's Close control. Observed
verbatim on: a fresh round with no decisions (OJS 229), a **newly created
round 2** (OJS 292, "Waiting for reviewers to be assigned."), a declined round
(OJS 291), and a past round (OJS 292 round 1).

**A6.2 CLAIM — but the state after Accept Submission is not a refusal: the
upload succeeds.** With the submission accepted and its active stage now
Copyediting, the author opening the review round's "Revisions Uploaded" panel
and pressing `Upload` gets the **full wizard**, and the upload **completes**:
on OJS 293 the file was written as a review-revision file on that round
(`submission_files`: id 380, `file_stage` 15
= `SUBMISSION_FILE_REVIEW_REVISION`, uploader = the author). OMP 168 opens the
same full wizard in the same state. A6 as written says "in any other state the
dialog opens with only a bare refusal notice", which a QA reader would test
here and find false.

Mechanism (for the footnote): the write gate is
`SubmissionFileStageAccessPolicy`, which grants an author participant the
review-revision file stage when the submission's **latest** review round
carries any of `ACCEPT`, `PENDING_REVISIONS`, `NEW_EXTERNAL_ROUND`,
`RESUBMIT` (internal variants for the internal stage) — the code comment says
so in as many words ("Authors may write to the revision files stage if an
accept or request revisions decision has been made in the latest round"). That
explains all five observations at once, including why a *new* round 2 refuses:
the new-round decision is recorded against round 1, not against the round now
current.

**Proposed A6 replacement text (facts only, product voice):**

> **A6 — The revisions panel offers "Upload" outside the working window** · 🐞 ·
> user-visible. The Author's "Revisions Uploaded" panel shows an enabled
> "Upload" button in every round state, past rounds included. It completes
> while the current round carries a revision request (either variant, before
> and after the author's upload) **and also after "Accept Submission", when the
> submission has already moved on to Copyediting**. In the remaining states —
> a round with no decision yet, a freshly created next round, a declined
> round, any past round — the dialog opens with only a bare refusal notice,
> "You are not allowed to add and edit these files.", and no way to proceed.
> The screen offers a control the server sometimes refuses, and in the
> post-accept case accepts a file the round's own status line says nothing
> about; expected: the button appears only when an upload can succeed, or the
> dialog explains the state in plain terms.

Rule 12 itself needs no change — the action-area button is absent in the
post-accept state, so only A6 (and footnote c, which cites
`SubmissionFileRequestedRevisionRequiredPolicy` — the *edit/delete* policy —
rather than `SubmissionFileStageAccessPolicy`, the *add* gate) is affected.

---

## Rule 13 — reading reviews — **AGREE** (both apps), including the narrowing

### Cases driven

One scratch submission per shape, author view:

| Case | OJS / OMP | Round contents |
|---|---|---|
| open review still under way | 297 / 172 | julia, open, **accepted** (not completed) |
| double-anonymous completed only | 299 / 174 | paul, double-anonymous, completed |
| mixed | 298 / 173 | julia open+completed (author comment, editor-only comment, attachment, recommendation "Revisions Required" on OJS), paul double-anonymous completed, **OJS also**: amara anonymous completed, adam open accepted |

**13.1 CLAIM — the panel appears only for a completed open review.** Author
panel titles for the in-flight open review: `["Round 1 Status","Revisions Uploaded","Review Tasks & Discussions"]`
— no "Reviewers". Same for the double-anonymous-only round. Both apps.

**13.2 CLAIM — mixed round lists the open completed review and nothing else.**
Panel titles gain "Reviewers"; the panel body is
`REVIEWER / TYPE / ACTIONS` + one row `Julia Reviewer | Open | Read Review`.
The completed double-anonymous review, the completed single-anonymous review
and the in-flight open review produce no row and no action (`Read Review`
count = 1). Both apps.

**13.3 CLAIM — the read sheet's contents, verbatim.** OJS 298:
`Review: Scenario submission [u26v3ojs-mixed-completed]` / `Julia Reviewer` /
`Completed: 2026-07-27 08:25 PM` / `Recommendation: Revisions Required` /
`Reviewer Comments` → `For author and editor` → the author-shared text /
`Reviewer Files` grid listing `reviewer-attachment.pdf` (July 27, 2026,
component "Article Text"). The reviewer's editor-only comment does **not**
appear anywhere in the delivered sheet. **Attachments are visible to the
author** and the file name is a link.

**13.4 CLAIM — OMP4 holds on the author's sheet.** OMP 173's sheet carries the
same fields minus any recommendation line, and the attachment is listed with
component "Book Manuscript".

**13.5 context — not exercised.** The review-form variant of Rule 13 ("or the
author-visible parts of the review form") was not driven: the live scenario
schema has no per-reviewer `reviewForm` key, so a form-based completed review
cannot be seeded without UI work. The claim is unverified rather than
disputed.

---

## Rule 14 — the "Notifications" list — **AGREE**; A7 reproduces exactly; two precision notes

### Cases driven

- OJS 224 and OMP 124: `Request Revisions` recorded through the real wizard as
  `editor.diana` (its "Notify Authors" step left at the default template, so
  the author email is really sent), then the author view.
- OJS 225: `Create New Review Round` recorded the same way, then the author
  view of round 1 (past) and round 2 (current), plus the Submission and
  Copyediting stages.
- Every scenario-seeded submission (decisions recorded with mail faked) as the
  negative control.

**14.1 CLAIM — hidden until an author email is really sent; then subject +
date.** Submissions whose decisions were recorded without a delivered author
email show no "Notifications" panel; after the wizard-recorded decision the
author's panel list becomes
`["Round 1 Status","Notifications","Revisions Uploaded","Review Tasks & Discussions"]`
and the row reads
`Your submission has been reviewed and we encourage you to submit revisions` +
`2026-07-27 08:28 PM`. Identical on OMP.

**14.2 CLAIM — only the Review stage shows it.** On OJS 225 the panel is
present on the Review rounds and absent on the Submission stage ("The
submission is currently in the Review stage.") and on Copyediting ("The
Copyediting stage has not yet been initiated.").

**14.3 CLAIM — A7's symptom is re-observable exactly as written.** The subject
row is `<a>` with `href = null`, `role = null`, `tabindex = null`, class
`text cursor-pointer text-base-normal hover:underline`. Tabbing 80 times from
the top of the author's workflow modal reaches only
`Library`, `Workflow`, `Publication`, `Upload`, `Add`, `Submit Response`,
`Upload revisions`, the help link, `Tasks`, the user menu and `Close` — never
the subject. A mouse click opens a side modal headed "Notifications" carrying
the subject, the timestamp and the full message body. Both apps. A7's wording
("respond to a pointer click only … keyboard and assistive-technology users
cannot open the message at all") matches the experience.

**14.4 CLAIM / precision — the panel is submission-wide, not round-scoped.**
On OJS 225 the same single entry is listed on round 1 (a past round) and on
round 2; the listing request carries no round id. Rule 4 places
"Notifications" in the *per-round* author roster and Rule 14 describes it
inside "The author's round", so a reader may expect round scoping. Suggested
addition to Rule 14: *"The list is per submission, not per round — every round
of the stage shows the same entries."*

**14.5 CLAIM / precision — only decision notifications appear.** The listing
is filtered to editor-notify-author decision emails; that event type is
written by the decision wizard's notify-authors step alone. Rule 14's "each
message the editorial team sent the author about this submission" reads
broader than the panel is: discussion messages and other editorial mail never
appear. Suggested wording: *"lists each decision notification the editorial
team sent the author for this submission"* (Rule 14's second sentence already
says "a decision's notification email", so the first sentence is the one out
of step).

**14.6 context — the row shows a date *and time*.** "2026-07-27 08:28 PM", not
a bare date; "subject and date" in Rule 14 is loose but not wrong.

**14.7 context — OMP's delivered decision email invites an author response.**
The OMP message body ends "You can access the reviews and submit your response
below / Submit Author Resp…" although OMP mounts no "Author Response" panel
(OMP5). Cross-feature (U30 / mail templates), noted only because it surfaced
in this run.

---

## Cross-rule observation from the rule-10 edge cases (rules 5–6)

**X.1 CLAIM — a round under a later active stage shows a stage-relocation
line, not its own status.** Once the submission's active stage is past Review,
the round's status area is headed plain "Status" and reads:

- current round: "The submission is currently in the Copyediting stage."
  (OJS 293, OMP 168 — author and editor alike)
- an earlier round: "The submission advanced to the next review round, was
  accepted, and is currently in the Copyediting stage." (OJS 292 round 1)

Consequences for the written rules:

- Rule 6's table lists "Submission accepted." as the decision-set status
  "after Accept Submission — final for the round". The stamp is stored, but
  **no screen displays it**: Accept always advances the active stage past
  Review, and from that moment the round shows the relocation line instead.
  (Contrast "Submission declined.", which does display, because a declined
  submission stays on the Review stage.)
- Rule 5 says an earlier round "is headed plain 'Status' and reads 'The
  submission has been advanced to the next round of review'". That text is
  what a past round shows **while the stage is still Review** (confirmed on
  OJS 292 before the accept, author and editor); once the submission moves on,
  the past round shows the "advanced … was accepted, and is currently in the
  {stage} stage." variant instead.

Suggested handling: add one sentence to Rule 5 and a footnote-j line to Rule 6
rather than a register entry — the behavior is coherent, the spec is just
silent on it.

---

## Proposed content for files this chunk must not edit

Nothing for PROGRESS.md or the atlas. For `docs/e2e/app-changes.md`: nothing —
no app change was made in this run.

Register-level proposals, for the merge agent:

1. **A6** — replace the entry text with the A6.2 wording above (the post-accept
   state is a completing state, not a refusal state); adjust footnote `f-a6`
   and footnote `c` to cite `SubmissionFileStageAccessPolicy`'s decision list
   (`ACCEPT`, `PENDING_REVISIONS`, `NEW_EXTERNAL_ROUND`, `RESUBMIT`, evaluated
   against the **latest** round) as the add-gate, keeping
   `SubmissionFileRequestedRevisionRequiredPolicy` where it belongs (edit and
   delete of an existing revision file).
2. **Rule 11** — add the "single comma-separated line, recommenders not named"
   precision (11.5).
3. **Rule 14** — narrow the first sentence to decision notifications (14.5) and
   state that the list is submission-wide (14.4).
4. **Rule 10** — optional clarity edit (10.7): only "Cancel Review Round" is
   conditional on an active latest round.
5. **Rules 5–6** — add the stage-relocation lines (X.1); note that "Submission
   accepted." never reaches a screen.

No change proposed to A1, A7, OMP4, Rule 12 or Rule 13: each was re-observed
exactly as written, on both apps.
