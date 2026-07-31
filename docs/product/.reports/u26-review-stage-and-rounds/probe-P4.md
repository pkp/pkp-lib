# U26 Review stage & rounds — probe P4 (items 9, 10, 15)

Probe date: 2026-07-31. OJS fleet at `http://127.0.0.1:8000`, driven as signed-in
users via Playwright (throwaway scripts under the session scratchpad; screenshots
there too, prefix `u26p4-*`). Facts only; each observation is tagged **[claim]**
(what the probe item asked for) or **[context]** (incidental).

## Fixture

Scratch journal seeded via `POST /api/v1/_test/scenarios/context`:
tag/path `u26p4q7` (contextId 65), users `u26p4mgr` (manager "Mira Manager"),
`u26p4se` (sectionEditor "Sela SectionEditor"), `u26p4rec` (sectionEditor "Rena
Recommender"), `u26p4ed` (editor → "Journal editor" group, "Edda Editor"),
`u26p4au` (author "Ava Author"), `u26p4rev1`/`u26p4rev2` (externalReviewer "Rio
ReviewerOne"/"Rex ReviewerTwo").

Submissions via `POST /api/v1/_test/scenarios/submission`, all
`decisions: ["sendExternalReview"]` (external review, round 1):

| id | tag | extra seed state |
|---|---|---|
| 11 | u26p4s1 | — (accept target) |
| 12 | u26p4s2 | — (decline/revert target) |
| 13 | u26p4s3 | — (recommend-only, zero reviewers) |
| 14 | u26p4s4 | reviewer u26p4rev1 `status: accepted` (min-reviews target) |
| 17 | u26p4s5 | reviewer u26p4rev2 `status: declined` (recommend-only, non-empty round) |

**Fixture context (known sibling quirk confirmed):** submit-time editor
auto-assignment assigned **no editor** on this scratch journal — every workflow's
Participants rail initially listed only "Ava Author / Author". All editor
assignments below were made through the workflow's "Assign Participant" screen as
`u26p4mgr` (right-rail `PARTICIPANTS` → button `Assign`; dialog
`getByRole('dialog')` with `select[name="filterUserGroupId"]` (options observed:
`Journal editor`, `Section editor`, `Guest editor`, `Funding coordinator`,
`Translator`, `Author`), search input `input[name="name"]` (label "Search User By
Name"), radio `input[name="userId"]`, checkboxes `input[name="recommendOnly"]` /
`input[name="canChangeMetadata"]`, submit button `OK`).

Workflow screens were opened at
`/index.php/u26p4q7/dashboard/editorial?workflowSubmissionId={id}`; the workflow
renders inside `[data-cy="active-modal"]`. "Status box" below = the panel headed
"Round {N} Status" (component renders heading + paragraph(s); no dedicated
`data-cy`, observations read via the modal's innerText around the heading, plus
screenshots). "Decision buttons" = buttons inside `[data-cy="active-modal"]`
enumerated by `getByRole('button')`.

## Item 9 — Accept; Decline/Revert Decline; Delete visibility

### Accept (submission 11, actor `u26p4se`, assigned Section editor)

- Before the decision, the right rail showed the five decision buttons, all
  enabled **[context]**: "Request Revisions", "Accept Submission", "Create New
  Review Round", "Cancel Review Round", "Decline Submission". Status box:
  "Round 1 Status" / "Waiting for reviewers to be assigned." **[context]**
- Clicked `getByRole('button', {name: 'Accept Submission'})`. Wizard: one
  "Continue" step then "Record Decision" (email step; waited out
  `.composer__loadingTemplateMask`). Completion screen **[context]**:
  > "Submission Accepted" / "The submission, Accept probe u26p4s1, has been
  > accepted for publication and sent to the copyediting stage. All
  > notifications have been sent, except any you chose to skip." / "View
  > Submission Summary"
- **Review stage status box afterwards [claim]** (reopened workflow, side-menu
  "Review Round 1"; submission now sits in Copyediting): heading **"Status"**
  (not "Round 1 Status"), body verbatim:
  > "The submission is currently in the Copyediting stage."
- After accept the review stage showed **no decision buttons at all** (button
  enumeration: no Request Revisions / Accept / Create New Review Round / Cancel
  Review Round / Decline; no Revert) **[context]**.

### Decline, Delete visibility, Revert (submission 12)

- As `u26p4se` (assigned Section editor): same five decision buttons before
  **[context]**. Clicked "Decline Submission"; wizard was a single step ending
  in "Record Decision". Completion **[context]**:
  > "Submission Declined" / "The submission, Decline probe u26p4s2, has been
  > declined and sent to the archives. All notifications have been sent, except
  > any you chose to skip." / "View Submission Summary"
- **While declined, as assigned Section editor `u26p4se` [claim]**: the five
  decision buttons were replaced by a single **"Revert Decline"** button
  (`getByRole('button')` enumeration inside `[data-cy="active-modal"]`:
  "Revert Decline" present, **no "Delete"**). Status box: "Round 1 Status" /
  "Submission declined."
- **While declined, as Journal Manager `u26p4mgr` [claim]**: right rail showed
  **both "Revert Decline" and "Delete"** (modal text ends "…Revert Decline /
  Delete / PARTICIPANTS"; screenshot `u26p4-dump-s12-declined-mgr.png`). Same
  status box: "Round 1 Status" / "Submission declined."
- **Revert [claim]**: as `u26p4se`, clicked "Revert Decline" → wizard, single
  step, final button "Record Decision". Completion:
  > "Submission Reactivated" / "The submission, Decline probe u26p4s2, is now
  > an active submission in the review stage." / "View Submission Summary"
- **Recomputed status box after revert [claim]**: "Round 1 Status" /
  "Waiting for reviewers to be assigned." (this round has no reviewers). The
  five decision buttons were restored, including "Cancel Review Round"
  **[context]**.

## Item 10 — Recommend-only Section editor + deciding editor

### Assignment mechanics [context]

- On submission 13: `u26p4ed` assigned via group "Journal editor" (no flag);
  `u26p4rec` assigned via group "Section editor" **with**
  `input[name="recommendOnly"]` checked. The checkbox's on-screen label text
  (dialog, ancestor `<label>`):
  > "This participant is only allowed to recommend an editorial decision and
  > will require an authorised editor to record editorial decisions."
  Dialog also shows section headings "Assignment privileges" and "Permissions"
  (the latter for `canChangeMetadata`).
- After assignment the Participants rail lists under Rena Recommender /
  "Section editor" the line: "Only allowed to recommend an editorial decision".
- The same two assignments were repeated on submission 17.

### Limited editor's round view

- **Submission 13 (round has zero reviewers), as `u26p4rec` [claim]**: the
  decision buttons are replaced by three recommendation controls —
  `getByRole('button')`: **"Recommend Revisions", "Recommend Accept",
  "Recommend Decline"** — but the status box read "Round 1 Status" /
  **"Waiting for reviewers to be assigned."**, NOT "Awaiting recommendations
  from editors." With an empty reviewer set the pending-reviewers sentence wins
  over the recommendations sentence. (Code cross-check **[context]**:
  `ReviewRound::determineStatus()`,
  `lib/pkp/classes/submission/reviewRound/ReviewRound.php:296-298` — empty
  `$reviewAssignments` returns `REVIEW_ROUND_STATUS_PENDING_REVIEWERS` before
  the `$pendingRecommendations` branch at :304-305.)
- **Submission 17 (round has one reviewer, Request Declined), as `u26p4rec`
  [claim]**: same three "Recommend …" buttons, no decision buttons; status box
  verbatim:
  > "Round 1 Status" / "Awaiting recommendations from editors."

### Recording the recommendation (submission 17, `u26p4rec`) [context]

- Clicked "Recommend Accept" → decision wizard; final button labeled
  **"Record Decision"** (not "Record Recommendation"). Completion screen:
  > "Recommendation Submitted" / "Your recommendation has been recorded and the
  > deciding editor(s) have been notified." / "View Submission Summary"

### Deciding editor's view after the recommendation (submission 17, `u26p4ed`)

- **"Recommendation" box [claim]**: right rail shows a box headed
  "RECOMMENDATION" (rendered uppercase) with body exactly:
  > "Accept Submission"
  (no recommender name, date, or other detail).
- **Status wording [claim]**: "Round 1 Status" /
  "All recommendations are in and a decision is needed."
- Deciding editor's buttons **[context]**: "Request Revisions", "Accept
  Submission", "Create New Review Round", "Decline Submission" — **"Cancel
  Review Round" was absent** on this round (its one reviewer had declined the
  request; the seeded decline stamps a confirmation date).
- Review Tasks & Discussions gained an "In progress" discussion titled
  "Editor Recommendation", "Created by: u26p4rec" **[context]**. The deciding
  editor's Tasks badge showed 1 **[context]**.

## Item 15 — Minimum Confirmed Reviews Required = 2, one confirmed review

### Setting (as `u26p4mgr`) [context]

- `/index.php/u26p4q7/management/settings/workflow` → tab "Review" (sub-tabs
  observed: "Setup", "Reviewer Guidance", "Review Forms", "Reviewer
  Recommendations"; field is on the default Setup sub-tab). Field label
  verbatim: **"Minimum Confirmed Reviews Required"**
  (`getByLabel('Minimum Confirmed Reviews Required')`); value was `0`, set to
  `2`, saved (`[role="status"]:has-text("Saved")`), persisted on reload
  (re-read value `2`).

### Producing exactly one editor-confirmed review (submission 14) [context]

- Reviewer `u26p4rev1` (seeded accepted) at
  `/index.php/u26p4q7/reviewer/submission/14`: steps "1. Request / 2. Guidelines
  / 3. Download & Review / 4. Completion"; "Save and continue" →
  "Continue to Step #3" → recommendation select
  (`select#reviewerRecommendationId`, options: "Choose One | Accept Submission |
  Revisions Required | Resubmit for Review | Resubmit Elsewhere | Decline
  Submission | See Comments") set to "Accept Submission" → "Submit Review" →
  confirm dialog "Confirm" / "Are you sure you want to submit this review?" /
  OK|Cancel → step 4 "Review Submitted" ("Thank you for completing the review of
  this submission…").
  Note **[context]**: a first "Submit Review" attempt with the recommendation
  select still on "Choose One" showed the same confirm dialog, but after OK the
  page stayed on step 3 with no visible error; the submit only took effect once
  a recommendation was selected.
- Status box before editor confirmation **[context]** (as `u26p4se`):
  > "Round 1 Status" / "Minimum number of confirmed reviews required: 2." /
  > "New reviews have been submitted."
- Editor confirmation (as `u26p4se`): reviewer row action "Read Review" opened
  "Review: MinReviews probe u26p4s4" — modal text includes "Rio ReviewerOne",
  "Once this review has been read, press \"Confirm\" to indicate that the
  review process may proceed…", "Completed: 2026-07-31 03:44 PM",
  "Recommendation: Accept Submission", a "Set or adjust the reviewer
  recommendation." select, "Reviewer rating" stars, buttons Cancel /
  **Confirm**. Clicked Confirm. Reviewer row afterwards: "Rio ReviewerOne /
  Complete / Accept Submission", row actions "Thank Reviewer", "Revert
  Decision" **[context]**.

### The status box with min = 2 and exactly one confirmed review [claim]

ALL lines of the box, verbatim (nothing follows the second line — the next text
in the modal is the "Revisions Uploaded" panel):

> "Round 1 Status"
>
> "Minimum number of confirmed reviews required: 2."

There is **no status sentence** in the box in this state — the round's
reviewer-derived sentence is omitted entirely. (Code cross-check **[context]**:
`lib/ui-library/src/pages/workflow/components/primary/WorkflowSubmissionStatus.vue`
~:136-153 — when the minimum is set but not met and the round's statusId is
`REVIEW_ROUND_STATUS_REVIEWS_COMPLETED`, the component returns heading + the
minimum line only, body deliberately omitted. The met-minimum branch would
render body "Minimum required number of reviews have been confirmed. A decision
is needed." (`dashboard.minimumReviewsConfirmedDecisionNeeded`,
`lib/pkp/locale/en/submission.po:3370`) — that branch was not observed live by
this probe.)

Decision buttons in this state **[context]**: "Request Revisions", "Accept
Submission", "Create New Review Round", "Decline Submission" (no "Cancel Review
Round" — the round has a confirmed-complete review); "Request Response" (Author
Response panel) became enabled, panel line "Ready To Invite Author".

## Security routing

No observation from these three items was judged a plausible security weakness;
nothing was routed to the private security file.
