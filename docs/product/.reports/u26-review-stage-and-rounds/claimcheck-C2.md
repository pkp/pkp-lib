# Claim-check C2 — Review stage & rounds, Rules 7–12 + Side effects

Spec: `lib/pkp/docs/product/specs/review-stage-and-rounds.md` (draft, probes of
2026-07-31 accepted unless a cheap adversarial angle existed). All drives
2026-07-31 through the screens as signed-in users. Fixtures: OJS scratch
journal `c2ojs` (submissions 99 "C2 OJS A revisions probe" = UI-driven
decisions, 100 "…B seeded reqrev", 101 "…C two rounds", 107 "…D resubmit");
OMP scratch press `c2omp` (monographs 113 "C2 OMP A revisions probe" =
UI-driven, 117 "…B resubmit"). Actors: throwaway author + Journal/Press editor
per context. Probe scripts and screenshots in the session scratchpad
(`c2-ojs-probe*.js`, `c2-omp-probe*.js`, `c2-tasks-read.js`).

Verdict tally: **8 holds · 2 wrong-with-observed · 0 unresolvable.**

---

## Directed item 1 — the Request Revisions task wording (Side effects, fn m)

**Claim.** Side effects: after a Request Revisions decision "each author
assigned to the stage gets a task ('Revision required.') in their task list
naming the submission" — unmarked, i.e. asserted identical in OJS and OMP.
The U26 OMP test author observed "Revisions to consider in External Review."
live on the press.

**Driven.** The identical flow on both apps: editor presses "Request
Revisions" on the current round through the workflow screen, keeps
"Revisions will not be subject to a new round of peer reviews.", completes
the wizard; the author signs in and opens the header Tasks panel
(TopNavActions bell → legacy modal titled "Tasks",
`TaskNotificationsGridHandler`). Cross-checked with a second OJS submission
whose requestRevisions decision was seeded (sub 100), and with the DB rows
(`notifications` table, both test DBs).

**Observed.**
- **OJS** (subs 99 UI-driven AND 100 seeded): the Tasks row reads verbatim
  **"Revision required."** + submission title (grid link
  `…/task-notifications-grid/mark-read…`). DB: one task-level
  (level 3) row of type 0x1000010 `NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS`.
- **OMP** (monograph 113, same UI-driven flow in External Review): the Tasks
  row reads verbatim **"Revisions to consider in External Review."** +
  submission title. DB: the task row is type 0x1000016
  `NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS`; no 0x1000010 row exists.

**Which should the spec say: genuine divergence.** Same surface, same flow,
different wording per app. The OJS attribution in fn m is correct for OJS;
the unmarked "identical in both apps" is what's wrong. Mechanism (code-read
to explain, not replace, the observation): both apps create the
"Revision required." task first (`PKP\decision\Repository::updateNotifications()`
unshifts the editor-decision type), but OMP's
`APP\decision\Repository::getReviewNotificationTypes()` also lists
`NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS`; that delegate, finding no
*internal*-stage pending-revisions decision, takes its removal branch —
which deletes **both** its own type and the shared
`NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS` row
(`lib/pkp/classes/notification/managerDelegate/PendingRevisionsNotificationManager.php:148-159`)
— erasing the just-created "Revision required." task. The external delegate
then runs, finds the decision-task row absent, and creates its own
`pendingRevisions` task (locale `notification.type.pendingRevisions`,
"Revisions to consider in {$stage}.").

**Does "Revision required." live on some other surface?** On OJS the Tasks
grid **is** the surface that says "Revision required." — the original fn m
probe read it correctly. My Submissions says "Revision requested" (different
string, see below); no author surface on the press renders
"Revision required." in this flow (the row that would carry it is deleted —
DB-verified). One caution for spec wording: the OJS grid can ALSO show the
OMP wording in aged flows — any later review-stage file add while the
decision stands unanswered re-creates the task as the `pendingRevisions`
type (observed live on OMP sub 113 after a Files-for-Review upload; same
shared code path on OJS via `lib/pkp/classes/submissionFile/Repository.php:328-334`).
So "Revision required." is the *fresh-decision* wording on a journal, not an
invariant of the grid.

**Verdict: wrong-with-observed** (the unmarked both-apps attribution).
Proposed fix: mark the sentence app-specific — OJS task reads
"Revision required."; on a press it reads "Revisions to consider in External
Review." — and register a candidate ❓ finding (press wording differs because
the internal-review notification bookkeeping deletes the shared decision
task; likely unintended, wording inconsistency only, task-level behavior
otherwise the same).

Confirmed intact in both apps alongside the divergence:
- My Submissions row: **"Revision requested"** + **"Submit revisions"**
  button, verbatim, OJS and OMP. Holds.
- "The task disappears when a revised file is uploaded" — holds, both apps
  (Tasks grid shows "No Items" for that submission after the author's
  upload).
- "The new-round variant's task reads 'Resubmit for review.'" — **holds in
  both apps** (subs 107/117, decision chain sendExternalReview → resubmit;
  live grid rows verbatim "Resubmit for review." + title on OJS AND OMP; DB
  type 0x1000011 in both). The press divergence does not extend to the
  resubmit task — the internal delegate's removal branch does not touch the
  RESUBMIT type.

## Directed item 2 — footnote i probe-pending: the editor-side "Revisions Uploaded" panel

**Claim area.** fn i: "Editor-side revisions panel is the same `FileManager`
namespace (AFFW-323); its editor actions are probe pending." Actors table:
editorial roles file revisions "through the Revisions Uploaded panel's own
controls".

**Driven.** As the assigned editor, on a round holding an author-uploaded
revision (OJS sub 99, file "article.pdf"; OMP monograph 113, file
"c2-omp-revision.txt").

**Observed — identical on OJS and OMP.** The panel (heading "Revisions
Uploaded", subtext "These files have been submitted by the author after
revisions were requested") offers the editor:
- Header button **"Upload"** — opens the same legacy 3-tab upload wizard the
  author gets (Upload File → Review Details → Confirm), filing a revision on
  the author's behalf. Not gated by round status (matches fn i).
- Per-file row: file id, linked file name
  (`…/$$$call$$$/api/file/file-api/download-file?submissionFileId=…&stageId=3`),
  date, genre badge, and one **"More Actions"** ellipsis button → menu with
  exactly three items: **"Update File Details"**, **"More Information"**,
  **"Delete"**. Delete confirms with "Are you sure you wish to delete this
  item? This action cannot be undone." (OK/Cancel) and removes the file.
- No select/checkbox control — unlike Files for Review, there is no
  selection dialog on this panel.

For contrast (Rule 9 author side): the **author's** per-file menu on the same
panel is narrower — **"Update File Details"**, **"Delete"** (no "More
Information") — identical OJS/OMP.

**Verdict: holds** (permission claim confirmed; proposed content to close
fn i's probe-pending: the list above).

---

## Rule 7 — requested↔submitted flips; task revival

**Claim.** "The two 'requested' sentences flip to their 'submitted' partner
as soon as a revised file newer than the decision exists, and flip back if
that file is deleted (which also revives the author's revisions task — see
*Side effects*)."

**Adversarial cases driven.** (a) author uploads → status forward-flip;
(b) editor deletes the only revised file from the panel → flip-back;
(c) author deletes their own only revised file → flip-back; (d) after each
deletion, the author's Tasks panel re-read for the revived task. Both apps
for (a),(b); OJS additionally (c).

**Observed.**
- Forward flip: holds, both apps — "Revisions have been requested." →
  "Revisions have been submitted and a decision is needed." on the author's
  upload.
- Flip-back: holds, both apps — deleting the only revised file returns the
  box to "Revisions have been requested." (editor-side delete OJS+OMP,
  author-side delete OJS+OMP).
- **Task revival: fails in both apps, from either deleter.** After the
  deletion the author's Tasks grid holds NO task for the submission (OJS:
  only the unrelated sub-100 row remained; OMP: "No Items"). The My
  Submissions row does return to "Revision requested" + "Submit revisions",
  and the bottom "Upload revisions" button returns — only the task list
  stays silent. Mechanism: `Repo::submissionFile()->delete()` runs its
  "Update tasks" notification pass BEFORE deleting the file row
  (`lib/pkp/classes/submissionFile/Repository.php` — the switch on
  `fileStage` precedes the deletion), so
  `revisionsUploadedSinceDecision()` still sees the file and the delegate
  takes the removal branch; nothing re-evaluates after the row is gone. A
  LATER review-stage file add does re-create the task (observed on OMP 113)
  — as "Revisions to consider in External Review.", not the fresh-decision
  wording.

**Verdict: wrong-with-observed** — the status sentence halves hold; the
parenthetical "(which also revives the author's revisions task)" and the
Side-effects sentence "returns if the only revised file is deleted" (and
fn m's "deleting the last revision file re-creates it") do not match the
app. Proposed fix: drop/correct the revival claim and register a candidate
🐞/❓ finding (author's task does not return when the only revised file is
deleted; the revival machinery exists but its delete-time evaluation runs
while the file still counts).

## Rule 8 — Files for Review panel & selection dialog

**Claim.** Panel "Files for Review"; dialog "Current Review Files For Round
{N}" lists workflow files with checkboxes; new files uploadable from the
dialog; nothing deleted here; confirming reports "Review files updated.";
⚠ A7 unticking changes nothing visible (A7 basis: OJS only).

**Adversarial case driven.** OMP sample (the un-probed app): press editor on
monograph 113 opened the panel's "Upload/Select Files".

**Observed (OMP).** Dialog headed verbatim **"Current Review Files For Round
1"**; in-dialog link **"Upload Review File"**
(`…/wizard/file-upload/file-upload-wizard/start-wizard?fileStage=4&reviewRoundId=122…`)
runs the 3-tab wizard and adds the file; "Show files from all accessible
workflow stages." checkbox; Cancel / OK. Confirming reports **"Review files
updated."** — verbatim on the press. No delete affordance anywhere in the
dialog. A7 reproduces on OMP, in a sharper variant: the file uploaded *from
the dialog itself* arrives with its checkbox UNCHECKED, yet after OK the
Files for Review panel lists it (file 44); reopening shows the checkbox
still unchecked, and untick+OK changes nothing the editor can see (panel
still lists the file, notice still "Review files updated.").

**Verdict: holds** (all quoted strings verbatim on both apps; proposed:
extend A7's basis note from "OMP not checked" to "reproduces on OMP,
including for a file uploaded from the dialog itself").

## Rule 9 — Revisions Uploaded panel, author upload button gating

**Claim.** Panel per round; author's bottom "Upload revisions" button only in
the three listed statuses ⚠ A1; the panel's own Upload control opens the same
wizard regardless.

**Driven.** Incidental to the flows above (fresh adversarial ground here is
A1's, already registered and probed 2026-07-31; not re-driven).

**Observed.** Both apps: bottom button present in "Revisions have been
requested." and again in "Revisions have been submitted and a decision is
needed." (upload #3 on OJS sub 99 went through the bottom button while the
round already held a revision); button label verbatim "Upload revisions";
panel heading and subtext verbatim (quoted under directed item 2); the
panel's own "Upload" control opens the same wizard for author and editor.
Upload takes effect at the wizard's first step — on OJS sub 99 the
Revised-Version email landed while the wizard was still on later tabs
(matches fn l's step-1 claim).

**Verdict: holds.**

## Rule 10 — past rounds lose the decision buttons; panels keep their controls

**Claim.** Decision buttons render only on the current round; a past round
shows that round's panels, "and the panels keep their own controls (upload,
add a reviewer, assign a participant)". fn e disclosed the controls'
*acting* was never exercised.

**Adversarial case driven.** OJS sub 101 (two seeded rounds, Round 2
current): selected past "Review Round 1" and actually USED a panel control —
the Revisions Uploaded panel's "Upload", completing the wizard.

**Observed.** On the past round: "Request Revisions" absent (0 matches; the
action rail renders no decision buttons — screenshot `ojs3-f-past-round.png`),
panels present with their buttons. The past-round "Upload" is fully live:
the wizard opened, completed, and the file (id 40, article.pdf) now sits in
**past Round 1's** Revisions Uploaded panel — Round 2's panel stays empty.

**Verdict: holds** — exactly as written, including "keep their own
controls", which turn out to ACT on the past round. NOTE for the register /
C1: this observed fact contradicts Rule 1's "only the current round can
still change" (outside my chunk): a past round's file set is editable
through its panels. Candidate ❓ finding if C1 concurs.

## Rule 11 — the decision buttons

**Claim.** Roster of five (Accept highlighted), Request Revisions entry
modal with preselected no-new-round radio, Cancel offered only while
retractable, declined state → Revert Decline (+ Delete for JM/admin).
fn e records full probes 2026-07-31 (both apps for roster; entry modal,
declined roster, past-round vanish).

**Driven.** Not re-probed as such; incidental confirmations collected while
driving the directed items on scratch state the earlier probes did not use:
on both apps, a round whose only editorial event was seeding showed exactly
"Request Revisions", "Accept Submission" (the filled/highlighted one),
"Create New Review Round", "Cancel Review Round", "Decline Submission"
(screenshots `ojs2-p4-*.png`, `omp2-p4-*.png`); the OMP entry-modal helper
asserted live that "Revisions will not be subject to a new round of peer
reviews." arrives preselected before the wizard opened under "Request
Revisions".

**Verdict: holds.**

## Rule 12 — creating and cancelling rounds

**Claim.** Create New Review Round carries revised files (preticked), no
reviewers; Cancel removes the round + invitations, lands on previous round /
Submission stage ⚠ A6.

**Driven.** Not re-driven — fn f/g record full live probes 2026-07-31
(including the reviewer-side vanish and both cancel landings), A6 is
registered, and no cheaper adversarial angle presented itself that the
probes missed. Accepted on that evidence.

**Verdict: holds** (on prior probe evidence).

## Side effects — remaining bullets

**Author uploads a revised file → email.** Claim: automatic email, subject
"Revised Version Uploaded", under the author's own name and address, to the
JMs/Editors/SEs **assigned to the stage**; throttle: same editor not emailed
again within a day "unless they have signed in since the last notice"
(sign-in branch was the unprobed half of fn l).

Driven adversarially on OJS sub 99, Mailpit scoped to the throwaway editor
address `c2ojsed@mail.test`:
1. While the journal-level Editor was NOT a stage participant: author
   uploads → **no email** (count 0) — confirms the "assigned to the stage"
   qualifier is load-bearing, not decorative.
2. Editor assigned via the Participants panel ("Journal editor" group);
   author upload #1 → **one email**: subject exactly "Revised Version
   Uploaded", From `c2ojsau <c2ojsau@mail.test>` (the author's own name and
   address).
3. Upload #2, editor not signed in since → **no new email** (count still 1).
4. Editor performs a fresh sign-in; upload #3 → **second email** (count 2).
   **The sign-in branch of the throttle holds** — fn l can drop its
   remaining hedge.
Status flip and My Submissions "Revisions submitted" wording observed
alongside. **Verdict: holds** (fn l now fully probed).

**Request Revisions decision → task/row.** See directed item 1:
**wrong-with-observed** on the both-apps wording attribution and on the
"returns if the only revised file is deleted" sentence (see Rule 7);
"Revision requested" row + "Submit revisions" button and the
upload-clears-task behavior hold in both apps; "Resubmit for review."
new-round task wording holds in both apps.

**Round created → internal notice surfaces nowhere.** Footnote-only claim
(fn n), no user-visible surface asserted — not driven. No verdict needed.

**Round cancelled → withdrawals + optional notifications.** fn f records the
2026-07-31 probes (incl. the reviewerless wizard's author-only step).
Accepted; not re-driven. **Verdict: holds** (on prior probe evidence).

---

## Proposed spec deltas (for the spec owner; this report does not edit)

1. Side effects, Request Revisions bullet: make the task wording
   app-specific — OJS "Revision required."; OMP "Revisions to consider in
   External Review." + title — with a plain or ⚠ marker and a new register
   entry (candidate: press deletes the shared decision task via the
   internal-review delegate's removal branch,
   `PendingRevisionsNotificationManager.php:148-159` +
   `omp: classes/decision/Repository.php::getReviewNotificationTypes()`).
2. Same bullet + Rule 7 parenthetical + fn m: remove/correct the
   task-returns-on-delete claim; new register entry (task not revived on
   deletion, both apps, either deleter role; delete-time evaluation runs
   before the row is gone — `lib/pkp/classes/submissionFile/Repository.php`;
   a later file add does re-create it, under the pendingRevisions wording).
3. fn i: replace "its editor actions are probe pending" with the observed
   list (header "Upload"; per-file More Actions → Update File Details /
   More Information / Delete; author variant lacks More Information;
   identical OJS/OMP, probed 2026-07-31).
4. fn l: record the sign-in throttle branch and the
   unassigned-editor-gets-nothing control as probed.
5. A7: extend basis to "reproduces on OMP (2026-07-31), including for a
   file uploaded from the dialog itself (arrives unticked while the panel
   lists it)".
6. Hand to C1/register: past-round panel controls act (past-round upload
   lands in the past round's file set) — tension with Rule 1's "only the
   current round can still change".

No observation in this chunk suggested over-entitlement or a security
weakness; nothing was routed to the private security file. The A3 register
area was not touched.
