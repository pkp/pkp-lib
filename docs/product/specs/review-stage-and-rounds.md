---
name: review-stage-and-rounds
scope: Editors run peer review in numbered rounds on the submission workflow screen; authors follow the round and respond on the same screen
apps: [ojs, omp]
shared: pkp-lib
status: draft
atlas-claims: [AFFW-323, AFFW-324, AFFW-325, AFFW-327, AFFW-328, AFFW-329, AFFW-330, AFFW-332, AFFW-333, AFFW-334, AFFW-335, AFFW-336, AFFW-337, AFFW-338, AFFW-339, AFFW-349, AFFW-350, AFFW-351, AFFW-352, AFFW-353, AFFW-355, AFFW-487, AFFW-666, AFFW-667, AFFW-701, AFFW-702, AFFW-703, AFFW-704, GRID-010, GRID-011, GRID-024, GRID-025, GRID-027, GRID-029, GRID-053, MAIL-047, NOTIF-029, NOTIF-031, SET-021]
---

# Review stage & rounds {OJS OMP}

## How to read this file

An unmarked claim asserts the behavior is identical in OJS and OMP (the two apps
that have the feature); markers flag everything else. `⚠ [A1](#a1)` marks an
as-built deviation with a Findings-register entry; a plain `[OMP1](#omp1)` marks
an intended app divergence. `<sup>[a](#fn-a)</sup>` marks link to evidence
footnotes at the document tail — a reader can skip them and lose no behavior.
Vocabulary is OJS's, per `APP-GLOSSARY.md`: for OMP read *press* for journal,
*monograph* for submission, and **External Review** for the Review stage.
<sup>[x](#fn-x)</sup> OMP's separate Internal Review stage is out of the
campaign's scope; this spec notes only where it parameterizes shared behavior. Cross-feature pointers cite the
owning feature's FEATURE-MAP row until that feature's spec exists.

## Purpose

The Review stage is where a journal's editorial team runs peer review. Work is
organized into numbered **review rounds**: each round has its own set of files
sent to reviewers, its own reviewer assignments, its own status line, and its
own outcome (a decision that ends the stage, or a new round). Editors watch a
round's progress, curate its files, receive reviewer input, and act; the Author
follows the same round on the same workflow screen — reading the status, the
editor's notices, and any reviews shared with them — and responds by uploading
revised files. This spec owns the round machinery and the round's screens; the
features it touches (reviewer management, decision recording, file mechanics,
discussions) are linked, not restated.

**OPS does not install this feature.** A preprint server's workflow has a
single Production stage by default: no review stage appears in any submission's
workflow, no review rounds or review files exist, no reviewer role is seeded,
and no review decision is offered anywhere. <sup>[w](#fn-w)</sup>

## Actors & permissions

Terms used below: **participant** — a user listed for this submission on the
Review stage (assignment mechanics: FEATURE-MAP row U24, workflow screen &
stage access; participant management: row U35). **Deciding editor** — a
participant with an editor role whose stage assignment does not have
"Recommend only" checked, or a Journal Manager / Site Administrator acting
without an assignment (full definition: [GLOSSARY](../GLOSSARY.md#deciding-editor)).
**Recommend-only participant** — a Journal Editor / Section Editor / Guest
Editor assigned with "Recommend only" checked (toggle owned by U35).
Editorial staff role names below are the app's seeded groups; any group holding
the same role slot behaves alike. <sup>[a](#fn-a)</sup>

| Action | Who may — and when |
|--------|--------------------|
| **See the Review stage and its rounds** | • every participant, the Author included — role decides which panels and actions appear (Rules 3–4); access mechanism owned by U24 <sup>[a](#fn-a)</sup> |
| **Curate "Files for Review"** (add via Upload/Select Files, edit, delete) | • Journal Manager, Journal Editor, Section Editor, Guest Editor, assistant-role participants (Copyeditor, Layout Editor, Proofreader…) — as stage participants<br>• Site Administrator — without assignment <sup>[b](#fn-b)</sup> |
| **Upload a revision file** | • Author (participant) — via "Upload revisions" while the round awaits revisions (Rule 12) ⚠ [A1](#a1); the "Revisions Uploaded" panel's own "Upload" is shown to the Author in every round state but completes only while revisions are requested or submitted ⚠ [A6](#a6)<br>• the editorial roles above — via the "Revisions Uploaded" panel's "Upload" button, any round state <sup>[c](#fn-c)</sup> |
| **Edit / remove a revision file** | • Author (participant), and the editorial roles above <sup>[c](#fn-c)</sup> |
| **Record a round decision** (Request Revisions, Accept Submission, Create New Review Round, Cancel Review Round, Decline Submission, Revert Decline) | • deciding editor — latest round only (Rule 5), per-decision availability in Rule 10; the recording wizard is owned by U34 <sup>[d](#fn-d)</sup> |
| **Record a recommendation** | • recommend-only participant — their controls replace the decision buttons (Rule 11); recording U34, toggle U35 <sup>[d](#fn-d)</sup> |
| **See the round's "Recommendation" listing** | • deciding editor only, with a round selected and a recommendation recorded (Rule 11); the Author has no side column at all <sup>[l](#fn-l)</sup> |
| **Cancel a review round** | • deciding editor — only while no reviewer in the round has answered the invitation (accepting or declining it) or completed a review <sup>[e](#fn-e)</sup> |
| **Delete the submission from the Review stage** | • Journal Manager, Site Administrator — only while the submission stands declined <sup>[f](#fn-f)</sup> |
| **Read a review** (author view) | • Author — open reviews only, once the review is completed; anonymous review methods never offer it <sup>[g](#fn-g)</sup> |

## Fields & validation

N/A — the stage's panels collect no fields of their own. File names and
metadata belong to Submission files (U36), decision forms to Editorial decision
recording (U34), discussion posts to Tasks & discussions (U37), the author's
formal response to Author response to reviews (U30).

## Rules & state

<a id="rounds"></a>
**Rounds**

1. Review happens in numbered rounds. Round 1 is created the moment the
   submission enters the Review stage; each "Create New Review Round" decision
   adds the next number. Rounds are never renumbered or reused. <sup>[h](#fn-h)</sup>
2. Each round appears as its own side-menu entry — "Review Round 1", "Review
   Round 2", … — under the stage; opening one heads the workflow column
   "Workflow: Review (Round N)" (the submission header shows the short form
   "Review (Round N)") and scopes every panel (files, reviewers, status,
   discussions) to that round. The latest round is preselected. <sup>[i](#fn-i)</sup>
3. Editorial panel roster, per round (titles as shown on screen): "Round N
   Status", the "Revisions Uploaded" and "Files for Review" file panels
   (mechanics U36), "Reviewers" (U27), "Author Response" — OJS only
   [OMP5](#omp5) (U30) — and "Review Tasks & Discussions" (U37). The side
   column holds "Participants" (U35), the "Recommendation" listing once a
   recommendation is recorded (Rule 11), and "Reviewers Suggested by Author"
   when the journal's reviewer-suggestion setting is on **and** the submission
   carries at least one suggestion (U31). <sup>[i](#fn-i)</sup>
4. Author panel roster, per round: the round status, a "Notifications" list of
   the editor's messages once one exists (Rule 14), "Revisions Uploaded", a
   "Reviewers" panel once the round has a completed open review (Rule 13),
   "Review Tasks & Discussions", and — on OJS [OMP5](#omp5) — an "Author
   Response" block when the round invites one (U30). The author's view has no
   side column. <sup>[i](#fn-i)</sup>
5. Only the latest round accepts decisions. On an earlier round no decision
   buttons appear for any role; the status area is headed plain "Status" and
   reads "The submission has been advanced to the next round of review". The
   panels still show that round's own files and reviewers, and their
   panel-level controls (upload, select files, add reviewer, discussions)
   remain offered. <sup>[k](#fn-k)</sup>

<a id="round-status"></a>
**Round status**

6. Every round carries exactly one status, shown under the heading "Round N
   Status" (past rounds: plain "Status", Rule 5) and identical for every role
   (Rule 8). Statuses come in two families: **decision-set** statuses stamped
   by an editorial decision, and **computed** statuses derived live from the
   round's reviewer and recommendation activity; a journal minimum adds a
   setting-gated pair (Rule 9). <sup>[j](#fn-j)</sup>

   | Status text shown | Family | When |
   |---|---|---|
   | "Waiting for reviewers to be assigned." | computed | no active reviewer in the round (also the state of every new round) |
   | "Awaiting responses from reviewers." | computed | at least one review still under way, none overdue, none awaiting the editor |
   | "New reviews have been submitted." | computed | at least one submitted review the editorial team has not yet confirmed |
   | "A review is overdue." | computed | any reviewer past a deadline — outranks the two lines above |
   | "All reviews are confirmed and a decision is needed." | computed | every active review confirmed; nothing else pending |
   | "Awaiting recommendations from editors." | computed | recommend-only participants assigned, none has recommended, reviews done |
   | "New editorial recommendations have been submitted." | computed | some, not all, recommend-only participants have recommended ⚠ [A3](#a3) |
   | "All recommendations are in and a decision is needed." | computed | every recommend-only participant has recommended ⚠ [A3](#a3) |
   | "Revisions have been requested." | decision-set | after Request Revisions (stay-in-round) |
   | "Revisions have been submitted and a decision is needed." | computed | a revision file arrived after that request |
   | "Revisions requested from the author to be taken to a new review round." | decision-set | after the resubmit variant of Request Revisions |
   | "Revisions submitted. A new review round needs to be created." | computed | a revision file arrived after the resubmit request |
   | "Submission accepted." | decision-set | after Accept Submission — final for the round |
   | "Submission declined." | decision-set | after Decline Submission — final unless the decline is reverted |
   | "Returned back to review." | decision-set | the submission was sent back from Copyediting ⚠ [A4](#a4) |
   | "Sent for external review." | decision-set | not produced by any current decision; rounds from older versions may still carry it <sup>[j](#fn-j)</sup> |
   | "Minimum number of confirmed reviews required: N." | setting-gated | prompt line shown above the status while the journal sets a minimum (Rule 9) |
   | "Minimum required number of reviews have been confirmed. A decision is needed." | setting-gated | replaces the ordinary status once the minimum is met (Rule 9) |

7. Precedence: the accepted / declined / sent-on statuses are final and never
   recompute. The two revisions-requested statuses flip only between their
   "requested" and "submitted" forms, driven by whether a revision file has
   arrived since the decision. All other rounds recompute from activity, in
   the order the table implies: no reviewers → overdue → unread reviews →
   reviews under way → recommendation states → all-confirmed. <sup>[j](#fn-j)</sup>
8. The status line is one line of the workflow screen, identical for every
   role that opens the round ⚠ [A2](#a2).
9. When the journal sets a minimum number of confirmed reviews per submission
   (setting's home: U29, see Settings), the round adds a first line above its
   status: "Minimum number of confirmed reviews required: N." While the
   minimum is unmet the ordinary status line continues below it — except when
   every review in the round is already confirmed, in which case the ordinary
   line is dropped and only the prompt shows. Once N reviews are confirmed the
   round reads "Minimum required number of reviews have been confirmed. A
   decision is needed." beneath the prompt. <sup>[u](#fn-u)</sup>

<a id="round-decisions"></a>
**Round decisions (display; recording is U34's)**

10. The latest round offers the deciding editor these action buttons, each
    shown only when the server currently offers that decision for the
    submission's active stage: <sup>[d](#fn-d)</sup>
    - "Request Revisions" — while the round can still take revisions (covers
      both the stay-in-round and the resubmit variants; the wizard asks which).
    - "Accept Submission" — while the submission is active.
    - "Create New Review Round" — always available on an active round.
    - "Cancel Review Round" — only while no reviewer has answered the
      invitation (accepted or declined) or completed a review (see Actors).
      <sup>[e](#fn-e)</sup>
    - "Decline Submission" — while the submission is active.
    - "Revert Decline" — replaces all of the above once the submission is
      declined.
    - "Delete" — alongside Revert Decline, for Journal Manager / Site
      Administrator only. <sup>[f](#fn-f)</sup>
11. Recommendation display. A recommend-only participant sees, instead of the
    decision buttons, three controls — "Recommend Revisions", "Recommend
    Accept", "Recommend Decline"; after recording, that area restates their
    recommendation under a "Recommendation" heading with a "Change decision"
    button (recording U34). The deciding editor — and no other role — sees a
    "Recommendation" box in the side column listing every recommendation
    recorded for the stage. <sup>[l](#fn-l)</sup>

<a id="author-side"></a>
**The author's round**

12. "Upload revisions" appears for the Author while the round's status is
    "Revisions have been requested.", "Revisions requested from the author to
    be taken to a new review round.", or "Revisions have been submitted and a
    decision is needed." — ⚠ but not after the first upload of a resubmit
    round [A1](#a1). Uploaded files land in "Revisions Uploaded" on both the
    author's and the editor's view of the round. <sup>[m](#fn-m)</sup>
13. Reading reviews. The author's "Reviewers" panel appears once the round has
    a completed open review, and lists open reviews only — a review still
    under way shows the author nothing, and a review run anonymously never
    surfaces here in any form. Each completed row offers a "Read Review"
    action opening the review: reviewer's name, completion date, the
    recommendation — when the journal offers reviewer recommendation options;
    presses ship with none ⚠ [OMP4](#omp4) — the review text the reviewer
    shared with authors (or the author-visible parts of the review form), and
    the reviewer's attached files. Comments the reviewer addressed to the
    editor alone are not shown. Editors read reviews in their own Reviewers
    panel (U27). <sup>[g](#fn-g)</sup>
14. The "Notifications" panel lists each message the editorial team sent the
    author about this submission — subject and date; clicking a subject opens
    the full message, ⚠ by mouse only [A7](#a7). The panel is hidden until a
    decision's notification email has actually been sent to the author, and
    only the Review stage shows it. <sup>[n](#fn-n)</sup>

<a id="round-lifecycle"></a>
**Round lifecycle**

15. Cancelling a round unassigns every reviewer in it (their responses and
    file access are withdrawn) and removes the round entirely; the previous
    round becomes the current one again. Cancelling Round 1 returns the
    submission to the Submission stage [OMP1](#omp1). Files uploaded for the
    cancelled round are not deleted, though no editorial screen lists them
    once the round is gone. Authors and affected reviewers are notified by
    email (composed in the decision wizard, U34; the reviewer step is skipped
    when the round has no reviewers). <sup>[o](#fn-o)</sup>
16. Creating a new round offers the previous round's uploaded revisions,
    preselected, to carry over as the new round's "Files for Review"; carrying
    over copies the file's listing — the original stays in the previous
    round's "Revisions Uploaded". The new round starts at "Waiting for
    reviewers to be assigned." <sup>[p](#fn-p)</sup>
17. "Upload/Select Files" on "Files for Review" opens "Current Review Files
    For Round N". By default it lists only the round's current review files;
    ticking "Show files from all accessible workflow stages." expands the list
    to every stage's files. Ticking a file from another stage copies it into
    the round's review files; the copy arrives not yet released to reviewers,
    and unticking a review file withdraws it from reviewers without deleting
    anything — ⚠ the panel lists released and withheld files identically
    [A8](#a8). <sup>[q](#fn-q)</sup>
18. Returning from Copyediting (the "Move to Review" decision, U32/U34)
    reactivates the last round — no new round, no files carried — and stamps
    it "Returned back to review." ⚠ though the stamp often loses to a
    recomputed status [A4](#a4). <sup>[j](#fn-j)</sup>

## Side effects

- **Author uploads a revision file** → the round status flips to its
  "submitted" form in every role's view; the stage's assigned editors
  (recommend-only included) receive one "Revised Version Uploaded" email,
  addressed to all of them together. A later upload on the same submission
  sends no further email, while an upload on another submission notifies
  immediately whatever the interval; there is no setting to turn the email
  off. The author's pending-revisions task clears. <sup>[r](#fn-r)</sup>
- **Request Revisions is recorded** → the author gains a pending-revisions
  task: a "Revision required." row in their Tasks panel ("Revisions to
  consider in External Review." on OMP) and a "Revision requested" note with
  a "Submit revisions" link on My Submissions; both clear on upload. Those
  surfaces belong to their own features; this stage's decision is the
  trigger. <sup>[s](#fn-s)</sup>
- **A round is created** → a round-status notice record is written ⚠
  [A5](#a5). <sup>[t](#fn-t)</sup>
- **Any decision or reviewer/file activity** → the round status recomputes
  (Rule 7).
- Decision notification emails to authors and reviewers belong to the
  recording flow (U34); this spec only notes they exist for round decisions.

## Settings that modify behavior

- **Reviewer suggestions** (journal setting, feature owned by U31): when the
  setting is enabled **and** the submission carries at least one author
  suggestion, a "Reviewers Suggested by Author" panel joins the editorial side
  column; either condition alone shows nothing. <sup>[i](#fn-i)</sup>
- **"Recommend only"** (per-participant toggle, U35): swaps that participant's
  decision buttons for recommendation controls and adds the three
  recommendation statuses to the round's repertoire (Rules 6, 11).
- **"Minimum Confirmed Reviews Required"** (journal setting; its home screen —
  Settings → Workflow → Review — is U29's territory, FEATURE-MAP row U29):
  adds the prompt line and status swap of Rule 9. <sup>[u](#fn-u)</sup>
- **Reviewer recommendation options** (journal list, owned by U29): the
  choices a reviewer records and the author sees in Rule 13; journals ship
  with defaults, presses with none ⚠ [OMP4](#omp4).
- **Review method** (per reviewer assignment; defaults configured in U29):
  anonymous methods keep the review out of the author's view entirely; only
  open reviews reach Rule 13's panel. <sup>[v](#fn-v)</sup>

## Cross-feature interactions

- **U24 Workflow screen & stage access** — owns how any participant reaches
  the stage; this spec starts at "the Review stage is open".
- **U25 Submission stage** — the decision that puts a submission into review
  lives there; Round 1's creation (Rule 1) is this spec's.
- **U27 Reviewer assignment & management** — everything inside the Reviewers
  panel; round status consumes reviewer states (Rule 6).
- **U28 Reviewer's review** — the reviewer's own experience; what they share
  with authors surfaces here in Rule 13.
- **U29 Review setup & review forms** — review method defaults, deadlines,
  forms, recommendation options, the minimum-reviews setting; their effects on
  this stage are Rules 6, 9 and 13.
- **U30 Author response to reviews** — the author response request/panel
  mounted on this stage's rounds (OJS [OMP5](#omp5)).
- **U31 Reviewer suggestions** — panel mounted here when its conditions hold.
- **U34 Editorial decision recording** — every button in Rule 10 opens U34's
  wizard; U26 owns which buttons show, U34 owns what happens next; the
  recommendation *recording* is U34's, its *display* here (Rule 11).
- **U35 Stage participants** — participant management and the "Recommend
  only" toggle; one side-effect line each way.
- **U36 Submission files** — file-manager mechanics for both file panels.
- **U37 Tasks & discussions** — the Discussions panel.
(All rows: `../FEATURE-MAP.md` until the neighbor specs exist.)

## Canonical scenarios

Common to OJS and OMP (OMP runs them on External Review with press/monograph
vocabulary). Actors are roles, never accounts; seeding notes in the footnote.
<sup>[s1](#fn-s1)</sup>

1. **Round 1 opens with the chosen files** — Journal Editor: send a
   Submission-stage submission to review, keeping the offered file selection.
   The side menu gains "Review Round 1"; open it. The page is headed
   "Workflow: Review (Round 1)", the status reads "Round 1 Status" / "Waiting
   for reviewers to be assigned.", "Files for Review" lists exactly the files
   chosen, and "Revisions Uploaded" is empty.
2. **Status follows reviewer activity** — Journal Editor, Reviewer: assign a
   reviewer (U27); the status becomes "Awaiting responses from reviewers."
   After the reviewer submits their review, it reads "New reviews have been
   submitted."; after the editor confirms the review (U27), "All reviews are
   confirmed and a decision is needed."
3. **Revisions round-trip inside the round** — Journal Editor, Author: record
   "Request Revisions" choosing the stay-in-round option (wizard: U34). Status:
   "Revisions have been requested." As the Author, open the same round: the
   "Upload revisions" button is offered; upload a file. Both views now show
   "Revisions have been submitted and a decision is needed." and the file in
   "Revisions Uploaded"; the assigned editors receive one "Revised Version
   Uploaded" email.
4. **Resubmit leads to a new round** — Journal Editor, Author: record the
   resubmit variant. Status: "Revisions requested from the author to be taken
   to a new review round." The Author uploads a revision; status: "Revisions
   submitted. A new review round needs to be created." (⚠ the author's upload
   button is gone at this point [A1](#a1)). Press "Create New Review Round":
   the wizard offers the uploaded revision preselected; confirm. "Review
   Round 2" appears and is selected, at "Waiting for reviewers to be
   assigned.", with the carried file in its "Files for Review".
5. **Earlier rounds are read-only** — Journal Editor: with two rounds, select
   "Review Round 1". No decision buttons are shown; the status area is headed
   "Status" and reads "The submission has been advanced to the next round of
   review"; the panels show round 1's files and reviewers only.
6. **Author reads an open review** — Journal Editor, Reviewer, Author: run an
   open review to completion in the round. The Author's view of the round now
   shows the "Reviewers" panel listing that reviewer; its "Read Review" action
   opens the review with the reviewer's name, date, recommendation (absent on
   a press ⚠ [OMP4](#omp4)), the comments shared with the author, and any
   attached files — a comment the reviewer wrote for the editor alone does
   not appear. Rerun with an anonymous review: the panel never lists it.
7. **Author follows the editor's messages** — Author: before any decision
   email the "Notifications" panel is absent. After the editor records a
   decision and sends its notification email (U34), the panel lists the
   message's subject and date; clicking the subject (⚠ mouse only
   [A7](#a7)) opens the full message.
8. **Recommendations reach the deciding editor** — Journal Manager, Section
   Editor (recommend-only): the recommend-only Section Editor opens the round
   and finds "Recommend Revisions" / "Recommend Accept" / "Recommend Decline"
   in place of decision buttons; they record a recommendation (U34). Their own
   action area now restates it with a "Change decision" button. The Journal
   Manager's view shows the side-column "Recommendation" box with that
   recommendation and, once all recommenders are in, status "All
   recommendations are in and a decision is needed." The recommend-only
   participant never sees the side-column box.
9. **Cancel a fresh round** — Journal Editor: on a round whose only reviewer
   has not yet answered the invitation, "Cancel Review Round" is offered;
   confirm through the wizard. The round vanishes from the side menu and the
   previous round is current again (cancelling Round 1 lands on the
   Submission stage instead [OMP1](#omp1)). After a reviewer accepts — or
   declines — the invitation, the button is no longer offered.
10. **Decline and revert in review** — Journal Editor, then Journal Manager:
    "Decline Submission" → status "Submission declined." and the action list
    collapses to "Revert Decline" — plus "Delete" when a Journal Manager
    looks. "Revert Decline" restores the submission to active review and the
    status to its computed value. A Section Editor is never offered "Delete".
11. **Curating Files for Review mid-round** — Journal Editor: press
    "Upload/Select Files"; "Current Review Files For Round 1" lists only the
    round's review files, so tick "Show files from all accessible workflow
    stages." to reach the submission's other files. Tick one and save: the
    panel now lists a copy of it — reopen the modal and the copy is still
    unticked, i.e. not yet released to reviewers ⚠ [A8](#a8); tick it and
    save to release it. Untick an existing review file and save: it is
    withdrawn from reviewers but remains listed on the panel and among the
    submission's files (U36).

App-specific:

12. **{OMP} Two doors into External Review** — Press Editor: send a monograph
    to External Review either straight from the Submission stage or from
    Internal Review — both doors are labelled "Send to External Review"
    [OMP2](#omp2). Either way "Review Round 1" of External Review opens at
    "Waiting for reviewers to be assigned." and every common scenario above
    runs identically from there. The Submission-stage door's "Select Files"
    step offers the submission files pre-ticked; the Internal Review door
    offers only revisions the author uploaded during internal review, so
    without any it opens with an empty "Files for Review" ⚠ [OMP3](#omp3).
13. **{OPS} Absence check** — Preprint Server Manager: open any submission's
    workflow on a preprint server. The side menu offers Production only — no
    review stage, no rounds, no reviewer panels, and no review decision
    anywhere on the screen. Positive control: the same walk on a journal
    shows the Review stage. <sup>[w](#fn-w)</sup>

## Findings register

Verdicts are the author's judgment (claude, 2026-07-27), unreviewed unless an
entry notes otherwise; the team settles them on spec review. Sorted 🐞 → ❓ →
✅ in the summary; entries are the source. Each entry opens with the
user-observable symptom; mechanism and evidence live in the entry's footnote.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|-----------------------------|------|--------|--------|
| [A1](#a1) | Author's "Upload revisions" button disappears after the first upload of a resubmit round | 🐞 | user-visible | — |
| [A5](#a5) | Round-status notice records are written but no screen ever shows them | 🐞 | invisible | — |
| [A6](#a6) | The author's revisions panel offers "Upload" in states where the upload is refused | 🐞 | user-visible | — |
| [A7](#a7) | The "Notifications" subject rows cannot be opened by keyboard | 🐞 | user-visible | — |
| [A8](#a8) | Released and withheld review files look identical on "Files for Review" | 🐞 | user-visible | — |
| [A2](#a2) | Authors read the editor-phrased round status; the author wordings exist but go unused | ❓ | user-visible | — |
| [A3](#a3) | Completed recommendations mask live review trouble in the status line | ❓ | minor | — |
| [A4](#a4) | "Returned back to review." is outranked by activity statuses and by the empty-round line | ❓ | minor | — |
| [OMP3](#omp3) | External Review entered from Internal Review can open with no files for reviewers | ❓ | user-visible | — |
| [OMP4](#omp4) | Presses ship without reviewer recommendation options | ❓ | user-visible | — |
| [OMP1](#omp1) | Cancelling external Round 1 lands on Internal Review when internal rounds exist | ✅ | minor | — |
| [OMP2](#omp2) | External Review is entered from Internal Review or straight from Submission | ✅ | minor | — |
| [OMP5](#omp5) | OMP rounds mount no "Author Response" panel | ✅ | minor | — |

### All apps

<a id="a1"></a>
**A1 — Resubmit round loses the author's upload button** · 🐞 · user-visible.
After a resubmit request, the Author sees "Upload revisions" and can upload;
the moment the first file lands (status: "Revisions submitted. A new review
round needs to be created.") the button vanishes. Expected: the button stays
through the resubmitted state, as it does in the stay-in-round flow. A second
file remains possible through the "Revisions Uploaded" panel's own "Upload",
which still completes in this state — a control offered in every state
regardless (A6). The show-list names one status twice and omits the
resubmitted one — a typo-shaped defect, identical in OJS and OMP.
Basis: probe. <sup>[f-a1](#fn-a1)</sup>

<a id="a2"></a>
**A2 — Author-phrased status texts go unused** · ❓ · user-visible.
Two round statuses have gentler author wordings ("New reviews have been
submitted and are being considered by the editor.", and a reassuring overdue
text), but the workflow screen serves every role the editor phrasing — the
Author reads "New reviews have been submitted." and "A review is overdue."
verbatim in both apps; the author variants are reachable only through a
retired notice path. Question: should the author's round status use the author
wordings? Lean: yes — the strings were written for exactly this and the
current text ("A review is overdue.") invites author worry. Basis: probe.
<sup>[f-a2](#fn-a2)</sup>

<a id="a3"></a>
**A3 — Recommendations outrank review trouble in the status line** · ❓ · minor.
Once every recommend-only participant has recommended, the round reads "All
recommendations are in and a decision is needed." even while a review is still
out or overdue; the overdue signal returns only if the state changes. The
"Reviewers" panel still shows the reviewer's row as "Overdue", so the fact is
one glance away — pending recommendations, conversely, never mask an overdue
review. Question: is recommendation-completeness meant to outrank overdue
reviews? Lean: defect — an overdue review is the more actionable fact.
Basis: probe. <sup>[f-a3](#fn-a3)</sup>

<a id="a4"></a>
**A4 — "Returned back to review." is often outranked** · ❓ · minor.
A submission sent back from Copyediting stamps its last round "Returned back
to review.", and the stamp is stored, but the round recomputes its line on
every display. The stamp surfaces only when the round holds at least one
review assignment and every one of them is settled (declined, cancelled, or
confirmed): a reviewer still in play outranks it with an activity status, and
a round that never had a reviewer outranks it with "Waiting for reviewers to
be assigned." Question: which line should a returned round show? Lean:
as-built is acceptable for the activity cases, but the empty-round case reads
wrong — the round is not waiting for reviewers, it was just returned.
Basis: probe. <sup>[f-a4](#fn-a4)</sup>

<a id="a5"></a>
**A5 — Round-status notices have no display surface** · 🐞 · invisible.
Every new round writes a round-status notice record, and cancelling the round
deletes it, but no current screen renders these notices — the panel that once
showed them was retired with the old author dashboard. No user impact beyond
dead records; the workflow screen shows round status from the round itself.
Basis: code. <sup>[f-a5](#fn-a5)</sup>

<a id="a6"></a>
**A6 — The revisions panel offers "Upload" outside the working window** · 🐞 · user-visible.
The Author's "Revisions Uploaded" panel shows an enabled "Upload" button in
every round state, past rounds included. While revisions are requested or
submitted it completes; in any other state the dialog opens with only a bare
refusal notice — "You are not allowed to add and edit these files." — and no
way to proceed. The screen offers a control the server then refuses; expected:
the button appears only when an upload can succeed, or the dialog explains
the state in plain terms. Basis: probe. <sup>[f-a6](#fn-a6)</sup>

<a id="a7"></a>
**A7 — "Notifications" subjects can't be opened by keyboard** · 🐞 · user-visible.
The "Notifications" panel's subject rows respond to a pointer click only: the
subject is not presented as a link or button, so keyboard and
assistive-technology users cannot open the message at all. Expected: the
subject is a real link or button. Basis: probe. <sup>[f-a7](#fn-a7)</sup>

<a id="a8"></a>
**A8 — Review-file release state is invisible on the panel** · 🐞 · user-visible.
"Files for Review" lists released and withheld review files identically: a
file added through the select modal arrives not yet released to reviewers,
and unticking one withdraws it, with no visible change to the panel either
way. The only surface showing which files reviewers actually receive is the
select modal's checkbox column. Expected: the panel distinguishes the two, or
an added file is released outright. Basis: probe. <sup>[f-a8](#fn-a8)</sup>

### OMP

<a id="omp1"></a>
**OMP1 — Round-1 cancel lands by stage roster** · ✅ · minor.
Cancelling External Review's Round 1 returns the monograph to Internal Review
when internal rounds exist, else to the Submission stage; OJS, having no
internal stage, always lands on Submission. Intended parameterization of the
shared cancel cascade. Basis: probe. <sup>[f-omp1](#fn-omp1)</sup>

<a id="omp2"></a>
**OMP2 — Two entry decisions, one label** · ✅ · minor.
OMP reaches External Review from the Internal Review stage or from Submission
by skipping internal review; OJS has a single send-to-review door. Both OMP
doors carry the same on-screen label, "Send to External Review" — the word
"skip" appears on the Submission stage only in the separate "Accept and Skip
Review" decision — so the two are told apart only by the stage they are
offered on. Round behavior after entry is identical; the doors differ on
files ⚠ [OMP3](#omp3). Basis: probe. <sup>[f-omp2](#fn-omp2)</sup>

<a id="omp3"></a>
**OMP3 — The Internal Review door can open an empty external round** · ❓ · user-visible.
"Send to External Review" recorded from the Internal Review stage offers to
carry only files the Author uploaded as internal-review revisions; the
internal round's own review files are never offered. When no revisions exist,
External Review Round 1 opens with an empty "Files for Review" and no warning
— reviewers would receive nothing until an editor re-adds files by hand. The
Submission-stage door, by contrast, offers the submission files pre-ticked.
Question: should the internal door offer the internal round's files (or the
submission files) when no revisions exist? Lean: defect — a round that opens
with no files and no warning reads as an oversight, not a choice.
Basis: probe. <sup>[f-omp3](#fn-omp3)</sup>

<a id="omp4"></a>
**OMP4 — Presses ship without reviewer recommendation options** · ❓ · user-visible.
On a press, a reviewer's completion step offers no recommendation choice, and
the author's read-review sheet shows no recommendation line; journals ship
with six default options. A Press Manager can add options by hand in the
review settings. Question: are presses meant to run review without
recommendations by default? Lean: defect — the shared review flow and its
settings screen expect options; only OMP's defaults are missing.
Basis: probe. <sup>[f-omp4](#fn-omp4)</sup>

<a id="omp5"></a>
**OMP5 — No "Author Response" panel on OMP rounds** · ✅ · minor.
OMP's External Review round mounts no author-response panel for editors or
authors; OJS shows one. The author-response feature itself is U30's
(FEATURE-MAP row U30); this entry records only the roster difference on this
screen, a deliberate per-app configuration. Basis: probe.
<sup>[f-omp5](#fn-omp5)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<a id="fn-a"></a>
**a** — Stage access: `WorkflowStageAccessPolicy` via U24; the screen blanks
all panels for a non-accessible stage (`common.getPrimaryItems` guard on
`permissions.accessibleStages`, `workflowConfigEditorialOJS.js` /
`workflowConfigAuthorOJS.js`). Role slots: `registry/userGroups.xml` — Journal
manager + Journal editor hold `ROLE_ID_MANAGER`; Section editor + Guest editor
hold `ROLE_ID_SUB_EDITOR`; Copyeditor/Layout Editor/Proofreader etc. hold
`ROLE_ID_ASSISTANT`. Live 2026-07-27: an unassigned Journal Manager's round
view (panels, status, decision buttons) matched an assigned Section Editor's
exactly, both apps.

<a id="fn-b"></a>
**b** — "Files for Review" = FileManager namespace `EDITOR_REVIEW_FILES`
(`useFileManagerConfig.js`): actions `FILE_SELECT_UPLOAD/EDIT/DELETE/SEE_NOTES`
granted to `SUB_EDITOR, MANAGER, SITE_ADMIN, ASSISTANT`; no Author entry.
Server side: `EditorReviewFilesGridHandler` + `ManageReviewFilesGridHandler`
role policies `[MANAGER, SITE_ADMIN, SUB_EDITOR, ASSISTANT]`. Empty subclass
chains in all three apps (chain check 2026-07-27).

<a id="fn-c"></a>
**c** — "Revisions Uploaded" = namespace `WORKFLOW_REVIEW_REVISIONS`: Author
gets `FILE_UPLOAD/EDIT/DELETE`; editorial roles that plus `FILE_SEE_NOTES`;
`FILE_SEND_TO_EDITOR` manager/admin only. File stage
`SUBMISSION_FILE_REVIEW_REVISION` (internal variant on OMP's internal stage).
The Author's panel "Upload" renders regardless of round status; the server
gate is `SubmissionFileAccessPolicy` +
`SubmissionFileRequestedRevisionRequiredPolicy` (A6). Live 2026-07-27 both
apps.

<a id="fn-d"></a>
**d** — Button guards call `isDecisionAvailable()` (`useSubmission.js`), a pure
lookup into server-computed `submission.availableEditorialDecisions`
(`APP\submission\maps\Schema::getAvailableEditorialDecisions()`; empty unless
the user `canMakeDecision` per `PKPSubmissionSchema::checkDecisionPermissions()`
and the stage is active). Recommend-only branch: stage flag
`currentUserCanRecommendOnly`; server enforcement `DecisionAllowedPolicy`.
External-review decision roster is byte-identical OJS/OMP
(`classes/submission/maps/Schema.php` both apps; chain check 2026-07-27).
Matrix live-confirmed 2026-07-27 both apps: fresh round shows all five
active-round buttons in Rule 10's order; declined shows `Revert Decline`
(+ `Delete` for Manager/Site Admin only); recommend-only view shows the three
`Recommend *` buttons only.

<a id="fn-e"></a>
**e** — `CancelReviewRound::canRetract()` false once any assignment is
confirmed or completed; the server-computed availability additionally drops
the decision once an invitation is declined — live 2026-07-27 (both apps) the
button disappeared for a round whose only reviewer had declined, and stayed
for an unanswered invitation. Re-validated on record
(`editor.submission.decision.cancelReviewRound.restriction`).

<a id="fn-f"></a>
**f** — Delete guard (UI): `isDecisionAvailable(DECISION_REVERT_DECLINE) &&
hasCurrentUserAtLeastOneAssignedRoleInAnyStage([MANAGER, SITE_ADMIN])`
(`workflowConfigEditorialOJS.js`, action `WORKFLOW_DELETE_SUBMISSION`). Button
label live-confirmed as exactly "Delete" (2026-07-27, both apps).

<a id="fn-g"></a>
**g** — `AuthorReviewerGridHandler` (author-role read ops):
`ReviewAssignmentRequiredPolicy` allows `SUBMISSION_REVIEW_METHOD_OPEN` only;
grid lists open assignments only; the read row action appears for completed
statuses (`AuthorReviewerGridCellProvider`). Modal `authorReadReview.tpl`:
reviewer full name, completed date, recommendation, author-shareable comments
(`getReviewerCommentsByReviewerId(..., true)`) or author-viewable form
elements, attachments via `AuthorOpenReviewAttachmentsGridHandler`. Live
2026-07-27 both apps: panel title "Reviewers", columns Reviewer/Type/Actions,
action label "Read Review"; the panel was absent while an accepted open
review was still under way, and absent entirely for a completed anonymous
review — the same session's read request for the anonymous assignment was
refused by the server on both apps; an editor-only comment was absent from
the delivered page, not merely hidden.

<a id="fn-h"></a>
**h** — `DecisionType::createReviewRound()` (round 1, status pending
reviewers, on entering a review stage with no rounds);
`NewExternalReviewRound::runAdditionalActions()` creates last round + 1.
`ReviewRound`/`ReviewRoundDAO`: empty subclass chains in all three apps; no
app overrides `reviewRound.json` (chain check 2026-07-27).

<a id="fn-i"></a>
**i** — Side-menu rounds: `useWorkflowNavigationConfigOJS.js`
`getReviewItems()` — label `workflow.reviewRoundN`, title
`submission.stage.externalReviewWithRound`; selection carries `reviewRoundId`
into `workflowStore.selectedReviewRound`; deep-linkable via the
`workflowMenuKey` query parameter. Live 2026-07-27 both apps: workflow-column
heading "Workflow: Review (Round N)" (OMP "Workflow: External Review
(Round N)"); editorial roster and side column as Rule 3, verbatim titles;
author roster as Rule 4, no side column. Reviewer-suggestions panel:
`ReviewerSuggestionManager.vue` renders only with a non-empty suggestions
list — live, enabling the setting alone produced no panel; panel title
"Reviewers Suggested by Author"; setting at Settings → Workflow → Review,
"Reviewer Suggestion at Submission" / "Allow authors to suggest potential
reviewers at submission process" (`reviewerSuggestionEnabled`).

<a id="fn-j"></a>
**j** — `ReviewRound::REVIEW_ROUND_STATUS_*` (16 values),
`determineStatus()` precedence as in Rules 6–7, `getStatusKey()` →
`editor.submission.roundStatus.*` strings (`lib/pkp/locale/en/submission.po`).
Serialized per round as `statusId`/`status`
(`PKPSubmissionSchema::getPropertyReviewRounds()`). Status stamping:
RequestRevisions→1, Resubmit→2, Accept→4, Decline→5, NewExternalReviewRound→6,
`BackFromCopyediting`→16; value 3 ("Sent for external review.") is set by no
current decision type in OJS or OMP. Empty chains all apps (chain check).
Status walks live-verified 2026-07-27 (both apps) for the waiting / awaiting /
new-reviews / overdue / recommendation / revisions pairs / accepted /
declined states; "Move to Review" back-out stamps value 16 (stored,
display per A4).

<a id="fn-k"></a>
**k** — Editorial `getActionItems` early-returns when `!selectedReviewRound`
or `selectedReviewRound.round < currentReviewRound.round`; past-round status
text `workflow.submissionInNextReviewRound`
(`WorkflowSubmissionStatus.vue`). Live 2026-07-27 both apps: past-round
heading is plain "Status", text "The submission has been advanced to the next
round of review" (no trailing period); the decision area is empty for editor
and Author, while panel-level buttons (Upload, Upload/Select Files, Add
Reviewer, Request Response on OJS, Add) remain rendered.

<a id="fn-l"></a>
**l** — `WorkflowRecommendOnlyListingRecommendations.vue`; data =
`stages[].recommendations`, populated server-side only for the deciding
editor (`$isCurrentUserDecidingEditor`, `PKPSubmissionSchema`); config guard
`selectedReviewRound && selectedStage?.isCurrentUserDecidingEditor`.
Recommender's own view: `WorkflowRecommendOnlyControls.vue`
(`currentUserRecommendation`). Live 2026-07-27 both apps: box absent for
every role until a recommendation exists; then present for deciding editors
(assigned or unassigned manager), absent for the recommend-only participant;
the Author's view has no side column. Recommend buttons "Recommend
Revisions" / "Recommend Accept" / "Recommend Decline"; post-recording block
headed "Recommendation" with "Change decision". Participant list flags the
assignment "Only allowed to recommend an editorial decision".

<a id="fn-m"></a>
**m** — Author action guard (`workflowConfigAuthorOJS.js`): statuses
`[REVISIONS_REQUESTED, RESUBMIT_FOR_REVIEW, REVISIONS_SUBMITTED,
REVISIONS_SUBMITTED]` — the duplicate and the missing
`RESUBMIT_FOR_REVIEW_SUBMITTED` are A1. Identical block in
`workflowConfigAuthorOMP.js`. Live 2026-07-27 both apps: button present at
exactly the three Rule 12 statuses, absent at waiting / awaiting / after the
resubmit upload (A1) / declined / past rounds. Upload wizard "Upload Review
File" (Upload File → Review Details → Confirm), file stage
`SUBMISSION_FILE_REVIEW_REVISION`.

<a id="fn-n"></a>
**n** — `WorkflowListingEmails.vue`: fetches the author-emails listing
filtered to editor-notify-author events (`GET
api/v1/emails/authorEmails?submissionId=…&eventType=…`); fetch fires only on
the external review stage (code comment "currently only used in review
stage"); subject click opens the legacy read-message page in a side modal
(`authorDashboard/readSubmissionEmail` — the live remnant of the old author
dashboard, AFFW-702). Live 2026-07-27 both apps: panel absent until a
decision's author email was actually sent (a stage-promoting decision with
no author mail added nothing); then subject + timestamp rows; other stages
never show the panel.

<a id="fn-o"></a>
**o** — `CancelReviewRound::runAdditionalActions()`: deletes the round's
review assignments (cascades: form responses, file access, notices,
invitation) and the round row (with its notices); files bound to the round
persist (no file deletion path). Landing stage `getNewStageId()`: >1 external
round → external; else internal rounds exist → internal (OMP1); else
Submission. Mailables `DecisionCancelReviewRoundNotifyAuthor` (delivered
subject "A review round for your submission has been cancelled"),
`ReviewerUnassign` ("Request for Review Cancelled"). Live 2026-07-27 both
apps: wizard steps Notify Authors → Notify Reviewers, the reviewer step
absent on a reviewer-less round; cancelled round gone from the side menu,
previous round's reviewers and files intact; the cancelled reviewer's
assignment list and review page access withdrawn; the round's file record
survives but no files listing (including the select modal with all stages
shown) offers it afterwards.

<a id="fn-p"></a>
**p** — `NewExternalReviewRound`: `PromoteFiles` step, source = current
round's `SUBMISSION_FILE_REVIEW_REVISION` files (preselected, labelled
`editor.submission.revisions`), target `SUBMISSION_FILE_REVIEW_FILE`; new
round status pending reviewers. Live 2026-07-27 both apps: wizard "New Review
Round: Notify Authors" → "New Review Round: Select Files", revision
preselected; the new round's "Files for Review" lists a copy under a new
listing while the original stays in round 1's "Revisions Uploaded".

<a id="fn-q"></a>
**q** — `ManageReviewFilesForm` (modal title
`editor.submission.review.currentFiles`): default listing = the current
review stage's files; the "Show files from all accessible workflow stages."
checkbox expands to every stage. Selection copies via `importFile()` (clone +
`sourceSubmissionFileId`, bound to the round); deselection flips the file's
`viewable` flag only. Live 2026-07-27 both apps: a copy created this way is
listed unticked on reopen (its release checkbox persists per file); ticked
vs unticked files render identically on the "Files for Review" panel (A8).

<a id="fn-r"></a>
**r** — `RevisedVersionNotify` (template key `REVISED_VERSION_NOTIFY`,
delivered subject "Revised Version Uploaded"), sent from
`PKP\submissionFile\Repository::add()` when a file lands in a revision stage
and the uploader is an author participant; recipients = stage assignments
with `MANAGER`/`SUB_EDITOR` roles incl. `recommendOnly` — one message
addressed to all of them. The code's skip guard (prior send + no sign-in
since + under a day old) behaves per-submission in practice: live 2026-07-27
two different submissions notified 32 s apart while a second upload on one
submission sent nothing further. No disable setting. Empty chains all apps.
MAIL-047.

<a id="fn-s"></a>
**s** — `PendingRevisionsNotificationManager`: raised for author participants
on an active pending-revisions decision, cleared on upload or decision
change; NOTIF-031. Live 2026-07-27 both apps: renders in the header Tasks
panel (OJS row "Revision required.", OMP "Revisions to consider in External
Review."; resubmit variant "Resubmit for review.") and on My Submissions
("Revision requested" + "Submit revisions"); all clear on upload.

<a id="fn-t"></a>
**t** — `NOTIFICATION_TYPE_REVIEW_ROUND_STATUS` created once per round in
`DecisionType::createReviewRound()`, deleted with the round; NOTIF-029. A5:
its only renderer was `authorDashboard/reviewRoundInfo.tpl` (dead — see y).
The author-phrased status strings (A2) are applied only on this dead path
(`PKPNotificationManager::getNotificationMessage()` passes `$isAuthor`).

<a id="fn-u"></a>
**u** — `WorkflowSubmissionStatus.vue` `checkMinimumConsideredReviews` against
`contextMinReviewsPerSubmission`; strings
`dashboard.minimumConfirmedReviewsRequired` /
`dashboard.minimumReviewsConfirmedDecisionNeeded`. Setting located live
2026-07-27 (OJS): Settings → Workflow → Review → Setup, field "Minimum
Confirmed Reviews Required" ("Minimum number of confirmed reviews required
for a submission", `numReviewsPerSubmission`, journal-level, default 0);
surface owned by U29. Rule 9's line-by-line behavior live-verified on a
minimum-2 journal, including the below-minimum replacement (only the prompt
line shows when all reviews are confirmed but under N) and the minimum-met
text.

<a id="fn-v"></a>
**v** — Review methods: `SUBMISSION_REVIEW_METHOD_OPEN/ANONYMOUS/DOUBLEANONYMOUS`
on `ReviewAssignment`; Rule 13's gate is the open method (see g).

<a id="fn-w"></a>
**w** — OPS absence evidence (chain check 2026-07-27):
`ops-main/classes/core/Application.php::getApplicationStages()` returns
Production only; `getReviewStages()` inherits the empty base; OPS decision
registry has no review types (source comments "OPS does not support review
rounds"); OPS `submissionFile` repository offers no review file stages; no
reviewer pages/grids exist. The `review_rounds` table and locale keys still
ship — absence is enforced at the stage/decision/file-stage layers, not the
database. Live 2026-07-27 (Preprint Server Manager and Author): the workflow
side menu's Workflow group holds only "Production"; no round panels, no
"Reviewers" panel; the only actions offered are "Post the preprint" and
"Decline Submission"; the API offers Decline alone and refuses review
decision types.

<a id="fn-x"></a>
**x** — OMP shared-behavior evidence (chain check 2026-07-27): OMP's
editorial and author workflow configs define no external-review block — the
OJS block applies verbatim via config merge; `NewExternalReviewRound` /
`CancelReviewRound` / all seven review grid handlers / `ReviewRound(DAO)` /
`RevisedVersionNotify` / `PendingRevisionsNotificationManager` /
`reviewRound.json` have empty OMP chains; the external-review decision roster
in `omp-main/classes/submission/maps/Schema.php` matches OJS's. OMP-side
deltas: `APP\decision\types\SendExternalReview` (entry from internal stage),
`SkipInternalReview`, `CancelInternalReviewRound` (OMP1's shorter cascade is
the *internal* variant; the shared external cascade produces OMP1's landing),
internal file-stage variants, three internal notification constants. Live
2026-07-27: OMP external rounds matched OJS on panel roster (minus "Author
Response", OMP5), decision matrix, status texts, and the cancel / new-round
wizards.

<a id="fn-y"></a>
**y** — Retired legacy surfaces (atoms claimed for bookkeeping): the old
author dashboard's round panel `authorDashboard/reviewRoundInfo.tpl`
(AFFW-703..704) is unreachable — no handler method or role assignment backs
its route op; its sub-grids `AuthorReviewAttachmentsGridHandler` (GRID-011)
and `AuthorReviewRevisionsGridHandler` (GRID-024) have no other caller. Live
2026-07-27 both apps: the round-panel route and the author-dashboard index
return not-found; a bookmarked author-dashboard submission link redirects to
My Submissions with that submission's workflow open at its review round.
Still live: `authorReadReview.tpl` modal (AFFW-666..667) via Rule 13, its
attachments grid `AuthorOpenReviewAttachmentsGridHandler` (GRID-010), and the
read-message page (AFFW-702) via Rule 14; the legacy email list AFFW-701 is
superseded by the "Notifications" panel. Dead-code candidacy proposed to
UNASSIGNED.

<a id="fn-s1"></a>
**s1** — Scenario seeding: scratch journal/press context + submission via the
scenario endpoints (`ojs-playwright-tests` skill); use seeded editor / section
editor / author / reviewer accounts from the harness roster. Scenario 8 needs
a participant assigned with "Recommend only"; scenario 6 needs the open
review method (context default or per assignment) and a completed review;
scenario 9 needs a reviewer left in the invited state; scenario 11 uses the
"Show files from all accessible workflow stages." toggle. Never mutate
`publicknowledge` seed data. All probe observations cited above: 2026-07-27,
fleets on 127.0.0.1.

<a id="fn-a1"></a>
**f-a1** — Guard array in `workflowConfigAuthorOJS.js` /
`workflowConfigAuthorOMP.js` lists `REVISIONS_SUBMITTED` twice and omits
`RESUBMIT_FOR_REVIEW_SUBMITTED` (value 15). Live 2026-07-27 both apps: button
gone after the first resubmit upload; the panel Upload wizard completed a
second upload in that state (see c, A6).

<a id="fn-a2"></a>
**f-a2** — `getStatusKey($isAuthor)` author variants
(`author.submission.roundStatus.reviewsReady` / `.reviewOverdue`) are invoked
only from `PKPNotificationManager` (dead display path, see t);
`getPropertyReviewRounds()` serializes with the default editor phrasing for
all roles. Live 2026-07-27 (OJS + OMP): the Author's status line was
byte-identical to the editor's for the reviews-ready and overdue states.

<a id="fn-a3"></a>
**f-a3** — `determineStatus()` returns
`RECOMMENDATIONS_COMPLETED`/`RECOMMENDATIONS_READY` before evaluating review
assignments; `PENDING_RECOMMENDATIONS` conversely ranks after the assignment
checks. Live 2026-07-27 both apps: with an overdue reviewer, the line read
"A review is overdue." until the last recommendation landed, then "All
recommendations are in and a decision is needed." while the Reviewers row
still showed "Overdue".

<a id="fn-a4"></a>
**f-a4** — `RETURNED_TO_REVIEW` is checked only after the assignment-derived
chain. Live 2026-07-27 (OJS, four round shapes, stored status 16 in all):
no assignments → "Waiting for reviewers to be assigned."; one declined
assignment → stamp shown; one confirmed review → stamp shown; one accepted
review in flight → "Awaiting responses from reviewers."

<a id="fn-a5"></a>
**f-a5** — See t. Creation `DecisionType::createReviewRound()`; deletion
`ReviewRoundDAO::deleteById()`. No `.vue`/`.tpl` renders the type outside the
dead template.

<a id="fn-a6"></a>
**f-a6** — Panel grant: see c. Server refusal
`api.submissionFiles.403.unauthorizedFileStageIdWrite`, rendered as the
entire dialog body. Live 2026-07-27 both apps: refused on a fresh round and
on a past round; completed at the requested/submitted statuses including
A1's state.

<a id="fn-a7"></a>
**f-a7** — The row's clickable element is an anchor with no target and no
role (`WorkflowListingEmails.vue` listing markup), invisible to
role-based/keyboard navigation. Live 2026-07-27 both apps.

<a id="fn-a8"></a>
**f-a8** — See q: the release state is the file's `viewable` flag, surfaced
only as the select modal's checkbox; the panel row carries no marker for it.
Live 2026-07-27 both apps.

<a id="fn-omp1"></a>
**f-omp1** — Shared `CancelReviewRound::getNewStageId()` cascade: >1 external
round → external; any internal round → internal; else submission. In OJS the
internal branch is vacuously never taken. Live 2026-07-27: all four landings
observed (OJS round 2 → round 1, OJS round 1 → Submission, OMP external
round 1 → Internal Review with internal rounds, → Submission without).

<a id="fn-omp2"></a>
**f-omp2** — OMP `APP\decision\types\SendExternalReview` (from internal
stage; also stamps the internal round accepted) and `SkipInternalReview`
(from Submission). OJS: shared `SendExternalReview` from Submission. Live
2026-07-27, OMP Submission-stage decision labels: "Send to External Review"
(skip-internal), "Send to Internal Review", "Accept and Skip Review",
"Decline Submission"; Internal Review stage offers "Send to External Review"
alongside the common round set.

<a id="fn-omp3"></a>
**f-omp3** — OMP
`SendExternalReview::withFilePromotionLists()` builds its single wizard list
("Revisions", `editor.submission.revisions`) from
`SUBMISSION_FILE_INTERNAL_REVIEW_REVISION` only. Live 2026-07-27: on two
monographs whose internal round's "Files for Review" held files, the step
read "No items found." with nothing selectable, and the external round
opened with "Files for Review" empty; `SkipInternalReview`'s step offered
the submission files pre-ticked.

<a id="fn-omp4"></a>
**f-omp4** — `ojs-main/classes/services/ContextService.php` calls
`Repo::reviewerRecommendation()->addDefaultRecommendations()` on context
creation; OMP's `ContextService` never does, though the shared repository
method exists (`lib/pkp/.../recommendation/Repository.php`). Live
2026-07-27: OMP's reviewer completion step shows no Recommendation control;
every press carries zero recommendation options while every OJS journal has
six; the author sheet on OMP shows no recommendation line.

<a id="fn-omp5"></a>
**f-omp5** — Only `workflowConfigEditorialOJS.js` /
`workflowConfigAuthorOJS.js` mount the author-response managers; OMP's
configs have no entry. Live 2026-07-27: no "Author Response" panel on OMP
rounds for editor or author.

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Review stage, editorial panels & actions | workflow screen → Review → Review Round N | AFFW-323..325, 327..330, 332..339, 349 |
| Review stage, author panels & action | workflow screen (author) → Review → Review Round N | AFFW-350..353, 355 |
| Author read-review modal | author "Reviewers" panel → "Read Review" | AFFW-487, 666..667, GRID-053, GRID-010 |
| Author read-message modal | "Notifications" panel → subject | AFFW-702 |
| Editor review files grid & select modal | "Files for Review" panel | GRID-025, 027 |
| Revisions grid (workflow) | "Revisions Uploaded" panel | GRID-029 |
| Legacy author-dashboard round panel (retired) | — unreachable, see footnote <sup>[y](#fn-y)</sup> | AFFW-701, 703..704, GRID-011, 024 |
| Round record (API shape) | round data on the submission | SET-021 |
| Revised-version email | on author revision upload | MAIL-047 |
| Round notices | round creation / revisions pending | NOTIF-029, 031 |

## Reference — code anchors

- `lib/pkp/classes/submission/reviewRound/ReviewRound.php`, `ReviewRoundDAO.php`
- `lib/pkp/classes/decision/DecisionType.php` (`createReviewRound`,
  `runAdditionalActions`); `lib/pkp/classes/decision/types/`
  (`SendExternalReview`, `NewExternalReviewRound`, `CancelReviewRound`,
  `RequestRevisions`, `Resubmit`, `BackFromCopyediting`)
- `classes/submission/maps/Schema.php` (OJS + OMP overlays; round
  serialization, decision availability, recommendations)
- `lib/pkp/controllers/grid/files/review/` (editor/manage/workflow/author
  revision grids), `lib/pkp/controllers/grid/files/attachment/`,
  `lib/pkp/controllers/grid/users/reviewer/AuthorReviewerGridHandler.php`
- `lib/ui-library/src/pages/workflow/composables/useWorkflowConfig/`
  (`workflowConfigEditorialOJS.js`, `workflowConfigAuthorOJS.js`, OMP/OPS
  variants), `src/managers/FileManager/useFileManagerConfig.js`,
  `src/pages/workflow/components/primary/WorkflowSubmissionStatus.vue`
- `lib/pkp/classes/mail/mailables/RevisedVersionNotify.php`;
  `lib/pkp/classes/notification/managerDelegate/PendingRevisionsNotificationManager.php`
- `lib/pkp/schemas/reviewRound.json`
