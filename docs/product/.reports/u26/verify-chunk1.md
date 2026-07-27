# U26 Review stage & rounds — adversarial verification, chunk 1

**Scope**: the **Actors & permissions** table of
`lib/pkp/docs/product/specs/review-stage-and-rounds.md` — all ten capability
rows, their footnotes (`a`, `b`, `c`, `d`, `e`, `f`, `g`, `l`) and the register
entries they cite.
**Date**: 2026-07-27. **Fleets**: OJS `http://127.0.0.1:8000`, OMP
`http://127.0.0.1:8100`. Facts only; no spec edits made.

## Environment used

Everything mutating ran in scratch contexts seeded through the scenario API.
Seeded `publicknowledge` / press data and the baseline roster were not touched;
Mailpit was not cleared.

- OJS scratch journal `u26v1` (`defaultReviewMode: 3` = open), section `ART`,
  throwaway users `u26jm` (Journal manager), `u26je` (Journal editor),
  `u26se` / `u26se2` (Section editor), `u26ge` (Guest editor), `u26fund`
  (Funding coordinator), `u26copy` (Copyeditor), `u26au` / `u26au2` (Author),
  `u26rev1` / `u26rev2` (Reviewer), `u26rdr` (Reader). Site `admin` used as
  Site Administrator.
- OMP scratch press `u26v1o`, series `monographs`, throwaway users `o26jm`
  (Press manager), `o26je` (Press editor), `o26se` / `o26se2` (Series editor),
  `o26fund`, `o26copy`, `o26au`, `o26rev1` / `o26rev2`.
- Scratch submissions per state: fresh round with an invited reviewer;
  recommend-only cast; declined; revisions requested; reviewer accepted;
  reviewer declined; two completed reviews (one open, one double-anonymous);
  recommendation recorded; two recommend-only editors; two rounds.
- Screens driven with Playwright (`chromium`, real form login, workflow opened
  at `/index.php/<ctx>/en/dashboard/{editorial|mySubmissions}?workflowSubmissionId=<id>`,
  round selected with `workflowMenuKey=workflow_3_<reviewRoundId>`); server
  requests issued from the same signed-in session with the session CSRF token.

Locator note: the workflow screen is a `role="dialog"`; panel titles were read
as `h1..h4` inside it, controls as `getByRole('button')`, file-row actions by
opening the row's **More Actions** menu, modals via `[data-cy="active-modal"]`.

---

## Item 1 — Row "See the Review stage and its rounds"

Spec: *every participant, the Author included — role decides which panels and
actions appear (Rules 3–4); access mechanism owned by U24.*

Cases driven (OJS, fresh round; the OMP equivalents repeated for the editorial
roles and the Author):

| App | Role / state | Observed |
|---|---|---|
| OJS | Section editor, participant | full editorial roster + side column |
| OJS | Guest editor, participant | same as Section editor |
| OJS | Funding coordinator (assistant), participant | full editorial panel roster; no decision area; no "Activity Log"; Participants panel without "Assign" |
| OJS | Journal manager, **no** assignment | full editorial view incl. decision area |
| OJS | Journal editor, **no** assignment | full editorial view incl. decision area |
| OJS | Site Administrator, no assignment | full editorial view incl. decision area |
| OJS | Section editor, **not** a participant | dialog shows "Error — The current role does not have access to this operation." |
| OJS | Author (submitter) | "Round 1 Status", "Revisions Uploaded", "Review Tasks & Discussions"; no side column |
| OJS | Author of a different submission | "Error — The current role does not have access to this operation." |
| OJS | Reviewer on the round | redirected to `/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied` |
| OJS | Reader | same redirect |
| OMP | Series editor / Funding coordinator / unassigned Press manager / unassigned Press editor / Author | same pattern, heading "WORKFLOW: EXTERNAL REVIEW (ROUND 1)" |

**Agree** with the row as written.

Context worth recording (not a disagreement): a user who *is* a submission
participant but was assigned under a user group whose stages exclude Review
(Copyeditor, stages `4`) opens the workflow and the round is selected, but the
column reads only **"You don't currently have access to that stage of the
workflow."** — no panels. The row's own definition of *participant* ("listed
for this submission **on the Review stage**") already excludes this user; the
observation is recorded because "participant" is easy to read as
"participant on the submission". Same on OMP.

---

## Item 2 — Row "Curate 'Files for Review'"

Spec: *Journal Manager, Journal Editor, Section Editor, Guest Editor,
assistant-role participants (Copyeditor, Layout Editor, Proofreader…) — as
stage participants · Site Administrator — without assignment.*
Footnote `b` basis: **code** (grid role policies + chain check), no live line —
so this row got both a screen and a server pass.

**Screen** (presence of "Upload/Select Files" on the "Files for Review" panel):
offered to the Section editor, Guest editor, Funding coordinator (all as
participants), and to the unassigned Journal manager, unassigned Journal
editor and Site Administrator. Absent for the Author (the panel itself is not
mounted in the author view), the Reviewer and the Reader (no stage access), and
the Copyeditor participant (no stage access). Same list on OMP for Series
editor / Funding coordinator / unassigned Press manager / unassigned Press
editor.

**Server** (legacy grid ops named in footnote `b`, POSTed from the live
session: `grid/files/review/editor-review-files-grid/{fetch-grid,select-files}`
and `grid/files/review/manage-review-files-grid/{fetch-grid,update-review-files}`,
with `submissionId`/`stageId=3`/`reviewRoundId`):

| App | Role | Result |
|---|---|---|
| OJS | Section editor (participant) | `status:true` on all four ops |
| OJS | Funding coordinator (participant) | `status:true` on all four ops |
| OJS | Author (participant, submitter) | `status:false` — "The current role does not have access to this operation." on all four |
| OJS | Copyeditor (participant, stage-4 group) | `status:false` — "You don't currently have access to that stage of the workflow." on all four |
| OJS | Reviewer | same stage refusal on all four |
| OMP | Author / Funding coordinator / Copyeditor | identical results |

Screen and server agree with each other on every case.

**Disagree** with the row's text on two points:

1. **The role list's "as stage participants" grouping does not match the
   product.** A Journal Manager cannot be added as a Review-stage participant
   at all: the "Assign Participant" dialog opened from the Review stage offers
   the user groups *Journal editor, Section editor, Guest editor, Funding
   coordinator, Translator, Author* — "Journal manager" is not among them (its
   seeded group carries no workflow stages). Both the Journal Manager and the
   Journal Editor reach the panel and its controls **without any assignment**,
   exactly as the Site Administrator does: an unassigned `u26jm` and an
   unassigned `u26je` each saw the complete editorial roster, "Upload/Select
   Files" included. The observed split is *Journal Manager / Journal Editor /
   Site Administrator — without assignment* versus *Section Editor / Guest
   Editor / assistant-role participants — as stage participants*. (Footnote `a`
   already records the unassigned-Journal-Manager observation; the table row
   places it in the other bucket.)
2. **The assistant groups named as examples cannot curate here.** Of the
   default assistant groups, only the **Funding coordinator** reaches the
   Review stage (its group carries stages `1,3`). Copyeditor (stage `4`),
   Layout Editor (stage `5`) and Proofreader (stage `5`) — the three named in
   the row — are not offered in the Review-stage "Assign Participant" dialog,
   and a user assigned under the Copyeditor group is refused the stage on both
   the screen and the server (see the table above). Same on OMP, where the
   dialog offers *Press editor, Series editor, Funding coordinator, Author,
   Volume editor, Translator*.

Context, not a disagreement: OMP ships **no Guest editor group** at all, so the
row's "Guest Editor" has no OMP counterpart. The row is unmarked, i.e. asserted
identical in both apps; the preamble's "role names below are the app's seeded
groups" softens this, but a QA reader working on OMP will look for a group that
does not exist.

Context: the Review-stage participant dialog also offers **Translator** (OJS
and OMP) and **Volume editor** (OMP) — author-role-slot groups the row does not
mention. Their capabilities were not probed in this chunk.

---

## Item 3 — Row "Upload a revision file"

Spec: *Author (participant) — via "Upload revisions" while the round awaits
revisions (Rule 12) ⚠ A1; the "Revisions Uploaded" panel's own "Upload" is
shown to the Author in every round state but completes only while revisions are
requested or submitted ⚠ A6 · the editorial roles above — via the "Revisions
Uploaded" panel's "Upload" button, any round state.*

Cases driven (OJS): Author on a round awaiting reviewer responses; Author on a
round at "Revisions have been requested."; Author after a revision file landed
("Revisions have been submitted and a decision is needed."); Section editor,
Funding coordinator, unassigned Journal manager, Site Administrator on a fresh
round, on a revisions-requested round, on a declined submission and on an
earlier (superseded) round. OMP: Author and Series editor on a fresh external
round.

Observed: the Author's **"Upload revisions"** button was present only on the
revisions-requested / revisions-submitted rounds and absent on the
awaiting-responses round, matching Rule 12. The "Revisions Uploaded" panel's
own **"Upload"** button was present for the Author in every state probed,
including the awaiting-responses round — consistent with A6 as written. Every
editorial role listed rendered "Upload" on that panel in every state probed,
including the earlier round and the declined submission.

Server: an author-role upload into the revision file stage was accepted while
the round stood at "Revisions have been requested." (a file was created at
`fileStage=15`, assoc'd to the round). Reading the guard chain, the
revisions-window constraint (`SubmissionFileRequestedRevisionRequiredPolicy`)
is attached only inside the `ROLE_ID_AUTHOR` branch of
`SubmissionFileAccessPolicy`, which is consistent with the row's unrestricted
"any round state" for editorial roles.

**Agree** with the row.

---

## Item 4 — Row "Edit / remove a revision file"

Spec: *Author (participant), and the editorial roles above.*

Case driven (OJS, revisions-requested round carrying one author-uploaded
revision file): opened the file row's **More Actions** menu as each role.

| Role | Menu items |
|---|---|
| Author (uploader) | "Update File Details", "Delete" |
| Section editor (participant) | "Update File Details", "More Information", "Delete" |
| Funding coordinator (participant) | "Update File Details", "More Information", "Delete" |

**Agree** with the row. Recorded for the writer: the on-screen labels are
"Update File Details" and "Delete" (the row says "edit / remove"), and the
editorial roles additionally get "More Information" (the notes action), which
the Author does not.

---

## Item 5 — Row "Record a round decision"

Spec: *deciding editor — latest round only (Rule 5), per-decision availability
in Rule 10.*

Screen cases (OJS): Section editor participant, Guest editor participant,
unassigned Journal manager, unassigned Journal editor, Site Administrator — all
five showed the decision area ("Request Revisions", "Accept Submission",
"Create New Review Round", "Cancel Review Round", "Decline Submission").
Funding coordinator participant, Author and recommend-only participant showed
**no** decision buttons. On an earlier (superseded) round the decision area was
empty for the deciding Section editor and for the unassigned Journal manager
alike, with the status headed plain "Status". Same pattern on OMP.

Server cases (`POST /api/v1/submissions/<id>/decisions` from the live session):

| App | Role | Request | Result |
|---|---|---|---|
| OJS | Funding coordinator (participant) | `accept` | 401 `user.authorization.roleBasedAccessDenied` |
| OJS | Author (submitter) | `accept` | 401 `user.authorization.roleBasedAccessDenied` |
| OJS | Recommend-only participant | `accept`, `newExternalReviewRound`, `decline` | 401 `editor.submission.workflowDecision.disallowedDecision` — "You do not have permission to record this decision on this submission." |
| OJS | Recommend-only participant | `GET /api/v1/submissions/<id>` | `availableEditorialDecisions: []` |

**Agree** with the row on every role case above (who may and may not record a
decision, assigned and unassigned).

---

## Item 6 — Row "Record a recommendation"

Spec: *recommend-only participant — their controls replace the decision buttons
(Rule 11).*

Cases: OJS Section editor participant carrying "Recommend only"; OMP Series
editor participant carrying "Recommend only"; OJS Guest editor participant
carrying "Recommend only".

Observed on all three: the decision area held exactly **"Recommend Revisions",
"Recommend Accept", "Recommend Decline"** and none of the five decision
buttons. After a recommendation was recorded, that area restated it under a
**"Recommendation"** heading with a **"Change decision"** button. The
Participants panel flagged those assignments "Only allowed to recommend an
editorial decision".

Server: a Funding coordinator participant POSTing a recommendation decision was
refused with 401 `user.authorization.roleBasedAccessDenied`; the recommend-only
participant's attempts at real decisions are in item 5.

**Agree** with the row.

---

## Item 7 — Row "See the round's 'Recommendation' listing"

Spec: *deciding editor only, with a round selected and a recommendation
recorded; the Author has no side column at all.*

Cases (OJS scratch submission with a deciding Section editor, a recommend-only
Section editor who recorded a recommendation, and a **second** recommend-only
editor (Guest editor) who recorded nothing; plus a Funding coordinator
participant, an unassigned Journal manager and the Author):

| Role | Side column |
|---|---|
| Deciding Section editor (participant) | "RECOMMENDATION" box listing the recorded recommendation, above "PARTICIPANTS" |
| Unassigned Journal manager | same box |
| Recommend-only editor who recorded | **no** listing box; only their own restatement block in the decision area ("RECOMMENDATION" + "Change decision") |
| Second recommend-only editor, nothing recorded | **no** "RECOMMENDATION" anywhere — the other editor's recommendation is not visible to them |
| Funding coordinator (participant) | no "RECOMMENDATION" |
| Author | no side column at all |

Before any recommendation existed, no role showed the box.

**Agree** with the row (and with footnote `l`). The second-recommender case is
the one the row's wording most invites doubt about, and it held.

---

## Item 8 — Row "Cancel a review round"

Spec: *deciding editor — only while no reviewer in the round has answered the
invitation (accepting or declining it) or completed a review.*

Cases (OJS, deciding Section editor on the latest round):

| Round state | "Cancel Review Round" button |
|---|---|
| one **invited** reviewer, unanswered | present |
| one reviewer who **accepted** | absent |
| one reviewer who **declined** the invitation | absent |
| one reviewer with a **completed** review | absent |
| earlier (superseded) round | absent (whole decision area empty) |

Server, same session, round with an accepted reviewer:
`GET /api/v1/submissions/<id>` returned
`availableEditorialDecisions: [Request Revisions, Resubmit for Review, Accept
Submission, New Review Round, Decline Submission]` — Cancel Review Round
absent; `POST .../decisions {decision: 31}` returned **400** —
`{"reviewRoundId":["Can only cancel review round if no associated reviewer has
any completed or submitted review available."]}`.

**Agree** with the row; screen and server agree with each other.

---

## Item 9 — Row "Delete the submission from the Review stage"

Spec: *Journal Manager, Site Administrator — only while the submission stands
declined.*
Footnote `f` basis: the guard expression is **code**-derived; only the button
*label* carries a live line.

Cases (declined scratch submission, OJS and OMP; plus the same roles on an
active submission):

| App | Role | "Delete" |
|---|---|---|
| OJS | Journal manager (unassigned) | present, next to "Revert Decline" |
| OJS | **Journal editor** (unassigned) | **present** |
| OJS | Site Administrator | present |
| OJS | Section editor (participant) | absent ("Revert Decline" alone) |
| OMP | Press manager (unassigned) | present |
| OMP | **Press editor** (unassigned) | **present** |
| OMP | Series editor (participant) | absent |
| OJS/OMP | any of the above, submission **active** | absent for every role |

The declined-state qualifier held on every case. Button label read exactly
**"Delete"**.

**Disagree** on the role list: the control is also shown to the seeded
**Journal editor** group (OMP: **Press editor**), which holds the same
`ROLE_ID_MANAGER` slot as Journal manager — footnote `a` already records that
role-slot fact, but the row names only "Journal Manager, Site Administrator".
Neutral wording for the row: *Journal Manager, Journal Editor, Site
Administrator — only while the submission stands declined.*

---

## Item 10

> item 10: dropped per security routing

---

## Item 11 — Row "Read a review" (author view)

Spec: *Author — open reviews only, once the review is completed; anonymous
review methods never offer it.*

Cases (OJS and OMP, round carrying one **completed open** review and one
**completed double-anonymous** review; plus a round whose only open review was
accepted but not yet submitted):

- Author view, completed open + completed anonymous: the "Reviewers" panel
  appeared and listed **one** row, with a **"Read Review"** action. The
  double-anonymous assignment was not listed in any form. Same on OMP.
- Editorial view of the same round listed **both** assignments, each with
  "Read Review", the anonymous one labelled "Anonymous Reviewer/Anonymous
  Author".
- Author view, open review accepted but not submitted: no "Reviewers" panel at
  all.
- The author's "Read Review" modal (OJS and OMP) contained: reviewer full name,
  "Completed: <date/time>", a "Reviewer Comments — For author and editor"
  block carrying the comment the reviewer shared with the author, and a
  "Reviewer Files" listing. The comment the reviewer addressed to the editor
  alone was **not** present in the delivered page. The modal carried no control
  other than page chrome and "Close".

**Agree** with the row.

---

## Item 12 — Extra fact for APP-GLOSSARY

On the **OJS Submission stage**, the decision that sends a submission to review
is labelled exactly:

> **Send for Review**

Read live 2026-07-27 as an assigned Section editor on a stage-1 scratch
submission (`u26v1`). The full Submission-stage decision area on that screen
read, in order: "Schedule For Publication", **"Send for Review"**, "Accept and
Skip Review", "Decline Submission".

For contrast (already recorded in the spec's OMP2 entry and re-seen this run):
OMP's equivalent label is "Send to External Review", and OMP's Submission-stage
door is the decision the scenario roster names `skipInternalReview`.

---

## Item 13

> item 13: dropped per security routing

---

## Summary

| Item | Row | Verdict |
|---|---|---|
| 1 | See the Review stage and its rounds | agree |
| 2 | Curate "Files for Review" | **disagree** (role grouping; named assistant groups) |
| 3 | Upload a revision file | agree |
| 4 | Edit / remove a revision file | agree |
| 5 | Record a round decision | agree on the role cases |
| 6 | Record a recommendation | agree |
| 7 | See the round's "Recommendation" listing | agree |
| 8 | Cancel a review round | agree |
| 9 | Delete the submission from the Review stage | **disagree** (role list omits Journal Editor) |
| 10 | — | item 10: dropped per security routing |
| 11 | Read a review (author view) | agree |
| 12 | OJS send-to-review decision label | fact recorded: "Send for Review" |
| 13 | — | item 13: dropped per security routing |

### Proposed row text (for the merge agent to consider; no spec edit made here)

Row "Curate 'Files for Review'":

> • Section Editor, Guest Editor, assistant-role participants whose user group
> reaches the Review stage (of the default groups, only the Funding
> coordinator) — as stage participants
> • Journal Manager, Journal Editor, Site Administrator — without assignment

Row "Delete the submission from the Review stage":

> • Journal Manager, Journal Editor, Site Administrator — only while the
> submission stands declined

### Proposed APP-GLOSSARY cell

OJS Submission-stage "send to review" decision label: **Send for Review**
(OMP: **Send to External Review**).
