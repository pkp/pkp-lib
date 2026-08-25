---
name: submission-stage
scope: The editorial team screens a new submission on the workflow's Submission stage and moves it onward — sends it to review, accepts and skips review, declines, reverts a decline — or deletes it
apps: [ojs, omp, ops]
shared: pkp-lib
status: verified
atlas-claims: [AFFW-286, AFFW-287, AFFW-288, AFFW-289, AFFW-290, AFFW-291, AFFW-292, AFFW-293, AFFW-294, AFFW-295, AFFW-296, AFFW-297, AFFW-298, AFFW-299, AFFW-300, AFFW-302, AFFW-303, AFFW-706, GRID-032, GRID-033]
---

# Submission stage {OJS OMP OPS}

<!-- OPS lacks the Submission stage as a reachable screen; see the absence note under Purpose. -->

> Conventions (markers, badges, footnotes): [Reading a spec](GLOSSARY.md#reading-a-spec).

## Purpose

Every new submission arrives at the **Submission stage** — the first stop in
the editorial workflow, before any review begins. Here the editorial team looks
the submission over: the files the author uploaded, the participants assigned to
it, and the discussions that coordinate the work. From this screen the team
moves the submission onward with a single decision — send it into peer review,
accept it and skip review straight to copyediting, or decline it — and can
reverse a decline or delete the submission outright. This spec covers the
Submission-stage screen itself: which panels and decision buttons each role is
offered per state, and what each decision does to the submission. The guided
wizard behind every decision button (its email step, its file-promotion step)
belongs to *Editorial decision recording*; the panels' own mechanics belong to
their features (see *Cross-feature interactions*). Reaching the screen at all —
who may open a submission's workflow — belongs to *Workflow screen & stage
access*.

A preprint server runs a **single-stage workflow**: a posted preprint's only
stage is Production, and no Submission stage ever appears in a preprint's
workflow menu. None of the decision buttons or the Submission-stage panels in
this file render there; a preprint server's editorial moves (decline, revert,
post the preprint) happen on the Production stage instead and belong to
*Production stage* and *Publish, schedule & versions* [OPS1](#ops1). <sup>p</sup>

## Actors & permissions

"Assigned to the stage" means listed on the Participants panel for the
Submission stage. A **deciding editor** is a Journal Manager or Editor, or an
assigned Section Editor or Guest Editor whose participation is not limited to
recommendations; a **recommending editor** is one whose participation is so
limited (the limitation is set when participants are managed — see *Stage
participants*). Who may **open** the Submission-stage screen at all — the
role × assignment gate shared by every workflow stage — is defined once in
*Workflow screen & stage access*; the rows below record only what each role is
offered **on** the screen once opened. The Author reaches this screen only for
their own submission, through My Submissions, in a reduced author view
(Rule 10). <sup>a</sup>

| Action | Who may — and when |
|--------|--------------------|
| **See the Submission-stage panels** (files, discussions, participants) | • Site Administrator; Journal Manager; Editor — every submission in the journal<br>• assigned Section Editor, Guest Editor, and the assistant groups the stage admits (Copyeditor, Layout Editor, Proofreader, Funding Coordinator — the four named in the decision row below) — their assigned submissions<br>• Author — their own submission, in the author view (Rule 10; the author sees files and discussions only) <sup>a</sup> |
| **Record a decision** (the buttons at the top of the screen) | • Deciding editors — while the submission is queued at this stage (queued — still awaiting a decision at this stage) they get the onward decisions (Rules 2–4); while it stands declined they get "Revert Decline" (Rule 5)<br>• Recommending editors — are offered a reduced subset of the same decision buttons ("Send for Review" on a journal; "Send to Internal Review" on a press), not the dedicated recommendation controls the Review stage gives them ⚠ [A2](#a2)<br>• Assistants (Copyeditor, Layout Editor, Proofreader, Funding Coordinator) — no decision buttons, whatever stage access they hold <sup>c</sup> <sup>d</sup> |
| **Send the submission to review** | • Deciding editors — "Send for Review" on a journal; "Send to External Review" (skipping internal review) or "Send to Internal Review" on a press (Rule 2)<br>• Recommending editors — one of these buttons appears for them too ⚠ [A2](#a2) <sup>e</sup> |
| **Accept and skip review** | • Deciding editors — "Accept and Skip Review" (Rule 3) <sup>e</sup> |
| **Decline the submission** | • Deciding editors — "Decline Submission" while the submission is queued (Rule 4) <sup>e</sup> |
| **Revert a decline** | • Deciding editors — "Revert Decline", only while the submission stands declined (Rule 5) <sup>e</sup> |
| **Delete the submission** | • Journal Manager; Site Administrator — the "Delete" button, only while the submission stands declined (Rule 6); no other role is offered the button, and as a guarantee behind that absence the system additionally refuses the delete operation itself for anyone who is not a Manager or Administrator <sup>f</sup> |
| **Jump to the publication (Schedule For Publication)** | • Every role the editorial view admits, on a journal (the author's own view never shows it — Rule 10) — the "Schedule For Publication" shortcut at the top of the screen is not tied to decision rights: assigned assistants and recommend-only editors see it too. It opens the publication's Title & Abstract; a press does not show it (Rule 7) <sup>g</sup> |

## Fields & validation

N/A — the Submission stage's own surfaces are panels and buttons. The forms
they open belong to their own features: every decision button opens the wizard
of *Editorial decision recording*; the file panel's upload wizard belongs to
*Submission files*; the Delete button opens a plain confirm dialog (Rule 6).

## Rules & state

<a id="panels"></a>
1. **What the screen shows.** Opening a submission at the Submission stage, the
   editorial view shows, in order: the **Submission Files** panel (headed
   "Submission Files", the files the author uploaded), the discussions panel
   (headed "Desk Review Tasks & Discussions"), and the **Participants** panel.
   When the journal has reviewer suggestions enabled **and** the author entered
   suggestions when submitting, a panel headed "Reviewers Suggested by Author"
   appears under Participants, listing each suggested reviewer with the
   author's reason; with the setting on but no suggestions entered, no such
   panel appears. Each panel's own mechanics belong to its feature (see
   *Cross-feature interactions*); this spec covers only which panels the
   Submission stage displays. The submission's decision buttons sit at the top of
   the screen (Rules 2–7). <sup>a</sup>
2. <a id="send-to-review"></a> **Send to review.** While the submission is
   queued at this stage, a deciding editor is offered a send-to-review button.
   On a journal it reads "Send for Review" and, when recorded, moves the
   submission into the Review stage and opens Round 1 (see *Review stage &
   rounds*). On a press the same primary button reads "Send to External Review"
   and skips the internal review stage, opening External Review Round 1; a press
   additionally offers "Send to Internal Review", which routes the submission
   into the earlier Internal Review stage instead [OMP1](#omp1). <sup>e</sup>
3. **Accept and skip review.** "Accept and Skip Review" accepts the submission
   for publication without any review and sends it straight to the Copyediting
   stage. <sup>e</sup>
4. **Decline.** "Decline Submission" sets the submission's status to declined.
   The submission stays on the Submission stage; the stage label under the
   submission's title reads "Declined" in place of "Submission", and the onward
   buttons of Rules 2–3 are replaced by the declined-state buttons (Rules 5–6).
   <sup>e</sup> <sup>h</sup>
5. **Revert a decline.** While the submission stands declined the only decision
   button offered is "Revert Decline", which returns the submission to the
   queued state and restores the onward buttons of Rules 2–3. <sup>e</sup>
6. <a id="delete"></a> **Delete.** While the submission stands declined, a
   Journal Manager or Site Administrator additionally sees a "Delete" button.
   Pressing it opens a confirm dialog titled "Delete" reading "Are you sure you
   want to permanently delete this submission?" with "Confirm" / "Cancel";
   confirming removes the submission permanently and closes the workflow. The
   button is offered only in the declined state and only to those two roles; no
   other role is offered the button, and the system additionally refuses the
   delete operation itself — a guarantee behind the button's absence, not
   something a screen shows. After the delete, the
   submission's old workflow address (a stale bookmark, say) opens the
   dashboard with an explicit error dialog — "Error" / "Invalid submission." /
   "OK" — so the reader is told the submission is gone; an empty leftover
   dialog lingers behind the error ⚠ [A3](#a3). <sup>f</sup>
7. <a id="schedule"></a> **The Schedule For Publication shortcut** {OJS}.
   At the top of the Submission stage a journal also shows a "Schedule For
   Publication" button; it records no decision — it opens the submission's
   publication at its Title & Abstract (see *Publish, schedule & versions*),
   where scheduling is offered. It appears for every role the editorial view
   admits, not only deciding editors (Actors, last row) — the author's own
   view never shows it (Rule 10) — and its appearance does not
   depend on the submission's status, so it is shown even while the submission
   stands declined ⚠ [A1](#a1). A press does not show it. <sup>g</sup>
8. **The status box is quiet at this stage.** The status box that narrates a
   submission's standing on the review and later stages — the panel that sits
   at the top of the stage's panel column, above the Submission Files panel —
   shows nothing while the submission is still active at the Submission stage;
   it appears only once the submission has moved on to a later stage (then
   reading, e.g., "The submission is currently in the Copyediting stage.").
   <sup>h</sup>
9. **No decision applies off the active stage.** A decision button renders only
   when the Submission stage is the submission's active stage; once the
   submission has moved onward, opening the Submission-stage entry shows its
   panels but none of the decision buttons. <sup>e</sup>
10. <a id="author-view"></a> **The author's view.** An author opening their own
    submission at the Submission stage sees the **Submission Files** panel and
    the "Desk Review Tasks & Discussions" panel, and nothing else: no
    Participants panel, no decision buttons, no Delete, no Schedule For
    Publication shortcut — the author cannot move the submission onward. A
    submission-status summary appears only once the submission has left the
    Submission stage. The author's entry route (View on My Submissions) belongs
    to *My Submissions*; everything on the screen after it is covered here.
    <sup>i</sup>
11. **The legacy author-dashboard address forwards — the author only.** Older
    versions reached the submission's files through a separate author-dashboard
    page. That page's address is the old `…/authorDashboard/submission/<number>`
    address under the journal's own address, the number being the same
    submission number the current workflow address carries. Typed into the
    address bar today by the submission's own author, it forwards to the
    current workflow screen — on a
    journal or press the author lands on My Submissions with the submission's
    workflow open; on a preprint server, on the submission's publication tabs.
    The forward is not a general redirect: any other signed-in role — an
    Editor, a Journal Manager, even an author who is not this submission's —
    gets an authorization-denied page ("You do not currently have sufficient
    privileges to view the submission.") instead of the workflow. The legacy
    page and its file lists render nowhere on a current install; the
    Submission Files panel is their replacement. <sup>j</sup>

## Side effects

- **On each decision** — the notification email and any file promotion are the
  decision wizard's, not this screen's; each decision's own emails, notifications
  and log entry are described in *Editorial decision recording*. This spec
  records only the stage/status change each decision makes (Rules 2–5).
- **On Send to review** — Round 1 of the review stage is created (see *Review
  stage & rounds*).
- **On Delete** — the submission and its associated records are removed
  permanently; nothing is emailed (Rule 6). <sup>f</sup>

## Settings that modify behavior

- **"Reviewer Suggestion at Submission"** (Settings > Workflow > Review; off by
  default) — lets authors suggest reviewers when submitting; on the Submission
  stage the "Reviewers Suggested by Author" panel then appears for submissions
  that carry suggestions (Rule 1). The panel itself belongs to *Reviewer
  suggestions*. <sup>a</sup>
- **The app's workflow stages** — a preprint server ships a single-stage
  workflow, which is why its submissions never occupy a Submission stage
  [OPS1](#ops1). This is a fixed property of each application, not a
  configurable setting. <sup>p</sup>

## Cross-feature interactions

- **Workflow screen & stage access** — who may open a submission's workflow and
  reach this stage; this spec owns only what the Submission stage offers once
  opened.
- **Editorial decision recording** — every decision button (Rules 2–5) opens
  that feature's guided wizard; this spec owns the buttons' presence and the
  stage/status change each decision makes.
- **Review stage & rounds** — the destination of Send to review (Rule 2): the
  Review stage and its Round 1.
- **Copyediting stage** — the destination of Accept and Skip Review (Rule 3).
- **Submission files** — the Submission Files panel's upload, revise and
  download mechanics.
- **Tasks & discussions** — the "Desk Review Tasks & Discussions" panel present
  on this stage in both views.
- **Stage participants** — the Participants panel, and the recommend-only
  limitation behind the recommending-editor rows.
- **Reviewer suggestions** — the suggestions panel, shown here when enabled.
- **My Submissions** — the author's entry route into this screen (Rule 10).
- **Publish, schedule & versions** — the destination of the Schedule For
  Publication shortcut (Rule 7).
- **Production stage / Publish, schedule & versions** — on a preprint server,
  where the editorial moves this stage would carry (decline, revert, post) live
  instead [OPS1](#ops1).

## Canonical scenarios

Common to OJS and OMP (a preprint server has no Submission stage — scenario 9
covers its absence); substitute roles and vocabulary per the
[application glossary](GLOSSARY.md). Actors are named by role; seeded
accounts and recipes live in the footnotes. <sup>s</sup>

1. **Open a new submission at the Submission stage** — Editor: on a freshly
   submitted article's workflow, land on the Submission stage. The screen shows
   the "Submission Files" panel listing the author's uploaded file, the "Desk
   Review Tasks & Discussions" panel and the Participants panel, and — at the
   top — the decision buttons "Send for Review", "Accept and Skip Review" and
   "Decline Submission". <sup>s</sup>
2. **Send the submission to review** — Editor: press "Send for Review" and
   complete the decision wizard. The workflow moves to the Review stage: the
   menu now shows "Review" with "Review Round 1" and no decision buttons remain
   on the Submission stage. <sup>s</sup>
3. **Accept and skip review** — Editor: on a second new submission, press
   "Accept and Skip Review" and complete the wizard. The submission moves to the
   Copyediting stage; reopening the Submission stage shows its panels but no
   decision buttons.
4. **Decline a submission** — Editor: on a third new submission, press "Decline
   Submission" and complete the wizard. Back on the Submission stage, the onward
   buttons are gone and "Revert Decline" is offered in their place; a Journal
   Manager additionally sees "Delete" while a Section Editor does not.
5. **Revert a decline** — Editor: on the declined submission from scenario 4,
   press "Revert Decline" and complete the wizard. The submission returns to the
   queued state and the onward buttons "Send for Review", "Accept and Skip
   Review" and "Decline Submission" are offered again.
6. **Delete a declined submission** — Journal Manager: on a declined submission
   (re-decline the submission from scenario 5, or decline a fresh one),
   press "Delete"; a dialog asks "Are you sure you want to permanently delete
   this submission?" Confirm — the submission is removed and the workflow
   closes. To verify it is gone, open the submissions dashboard's **Declined**
   view (the view list beside the submissions table — declined submissions are
   listed only there, not in the default view): the submission no longer
   appears. Control: on the same declined submission a Section Editor sees no
   "Delete" button.
7. **The author's view** — Author: from My Submissions, open your own new
   submission's workflow. The Submission stage shows the "Submission Files"
   panel and the "Desk Review Tasks & Discussions" panel, and no decision
   buttons — there is no way to send, accept, decline or delete the submission
   from the author's view. <sup>s</sup>

App-specific:

8. **{OMP} A press's Submission-stage decisions** — Press Editor: on a new
   monograph's Submission stage, the buttons read, in order: "Send to External
   Review" (which skips the internal review stage), "Accept and Skip Review",
   "Decline Submission", and last "Send to Internal Review" (which routes into
   the internal review stage); the journal's "Schedule For Publication"
   shortcut is absent. Choosing "Send to External Review" opens External Review
   Round 1 directly [OMP1](#omp1). The Internal Review stage itself is
   documented separately.
9. **{OPS} No Submission stage on a preprint server** — Preprint Server
   Manager: open any preprint's workflow. The menu offers only the Production
   stage — no Submission entry appears, and none of the send-to-review,
   accept-and-skip, or Submission-stage decision buttons exist anywhere on the
   screen. Positive control: the Production stage shows its own controls
   ("Post the preprint", "Decline Submission"), so the workflow itself is
   working [OPS1](#ops1). <sup>p</sup>

## Findings register

Verdicts are the author's judgment (claude, 2026-08-02), unreviewed unless an
entry notes otherwise; the team settles them on spec review. Sorted 🐞 → ❓ → ✅
in the summary (🐞 defect, author's call · ❓ needs a product ruling · ✅
intended divergence); the entries below are the source. Each entry opens with
the user-observable symptom; mechanism and evidence live in the entry's
footnote. Impact values: user-visible = real effect in ordinary use · minor =
cosmetic only, however often seen · latent = only in an unusual situation or
configuration.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|-----------------------------|------|--------|--------|
| [A2](#a2) | A recommend-only editor is offered a subset of the real decision buttons, not the Review stage's dedicated recommendation controls | ❓ | user-visible | — |
| [A1](#a1) | The "Schedule For Publication" shortcut is offered on the Submission stage of a declined submission, where scheduling has no meaning | ❓ | minor | — |
| [A3](#a3) | A deleted submission's old address answers with an "Invalid submission." error dialog — but an empty leftover dialog lingers behind it | ❓ | minor | — |
| [OMP1](#omp1) | A press adds Internal Review routing to the Submission stage (Send to Internal Review; External Review skips internal) | ✅ | — | — |
| [OPS1](#ops1) | A preprint server has no Submission stage at all — its workflow is single-stage (Production only) | ✅ | — | — |

### All apps

<a id="a1"></a>
**A1 — "Schedule For Publication" shown on a declined submission** · ❓ · minor.
On a journal the "Schedule For Publication" shortcut at the top of the
Submission stage is shown regardless of the submission's status, so a declined
submission — whose only meaningful action is Revert Decline or Delete — still
offers a button that jumps to the publication's Title & Abstract, where
scheduling remains offered. The shortcut records no decision and changes
nothing on its own, so the cost is a misleading affordance rather than a wrong
action.
Question: should the Schedule For Publication shortcut be hidden while the
submission stands declined (or otherwise not yet ready to schedule)? Lean:
hide it in the declined state — it invites an action that cannot sensibly
follow.
Basis: observed on a running site (2026-08-02) + code inspection (the shortcut
is added unconditionally, ahead of the status-gated decision buttons).
<sup>[g](#fn-g)</sup>

<a id="a2"></a>
**A2 — Recommend-only editor is offered real decision buttons** · ❓ · user-visible.
On the Review stage an editor whose participation is limited to
recommendations gets dedicated "Recommend…" controls in place of the decision
buttons. On the Submission stage the same editor is instead offered a reduced
subset of the ordinary decision buttons — "Send for Review" on a journal,
"Send to Internal Review" on a press — with the standard panels around them
(including a working "Assign" on Participants); their row on the Participants
panel is annotated "Only allowed to recommend an editorial decision". The
reduced button is a working control, not a leftover: pressing it completes the
decision wizard and records the decision.
Question: what is a recommend-only editor meant to be offered on the
Submission stage? Lean: mirror the Review stage's recommend-only treatment —
offering the ordinary decision buttons to an editor the screen itself labels
recommend-only is at least misleading.
Basis: observed on a running site (2026-08-02) + code inspection (the
Submission-stage button logic has no recommend-only branch, unlike the Review
stage's). <sup>[c](#fn-c)</sup> <sup>[d](#fn-d)</sup>

<a id="a3"></a>
**A3 — An empty leftover dialog lingers behind a deleted submission's error dialog** · ❓ · minor.
After a submission is deleted (Rule 6), revisiting its old workflow address —
a stale bookmark, a link in an old email — does answer: the dashboard opens
with an error dialog reading "Error" / "Invalid submission." / "OK", so the
reader is told the submission is gone. The residue is cosmetic: the error
arrives stacked over a lingering, essentially empty workflow dialog that shows
only the submission's number and a raw placeholder code where its help link
should be, instead of that dialog closing (or the listing itself noting the
submission is gone).
Question: should the empty workflow dialog close (and its placeholder render
as a real label) when the error reports the submission invalid? Lean: tidy
it — the explicit error dialog is arguably correct behavior; the leftover
shell behind it just reads as unfinished.
Basis: observed on a running site (2026-08-02, journal and press, every role
and address form tried — identical terminal state).
<sup>[f-a3](#fn-a3)</sup>

### OMP

<a id="omp1"></a>
**OMP1 — Internal Review routing on the Submission stage** · ✅ · intended divergence.
A press's Submission stage offers two routes into review: "Send to External
Review", which skips the internal review stage and opens External Review
Round 1, and "Send to Internal Review", which routes the submission into the
earlier Internal Review stage. A journal has a single "Send for Review" route
and no internal review stage. The Internal Review stage itself, and its own
round machinery, are documented separately.
Basis: code inspection (the press's Submission-stage decision set is
press-specific by design) + observed live (2026-08-02): both routes present
and landing as described. <sup>[f-omp1](#fn-omp1)</sup>

### OPS

<a id="ops1"></a>
**OPS1 — No Submission stage on a preprint server** · ✅ · intended divergence.
A preprint server runs a single-stage workflow: its only workflow stage is
Production, so a preprint is never in a Submission stage and no Submission entry
appears in a preprint's workflow menu. None of this spec's decision buttons or
Submission-stage panels render there. The editorial moves that a Submission
stage would carry on a journal — declining, reverting a decline, and moving the
work onward (posting the preprint) — happen on the Production stage instead and
belong to *Production stage* and *Publish, schedule & versions*. The
Submission-stage configuration a preprint server inherits from the shared
workflow code never comes on screen because no preprint ever occupies that stage.
Basis: code inspection (the application declares Production as its only
workflow stage) + observed live (2026-08-02): Production is the only menu
entry and none of this spec's buttons appear anywhere in a preprint's
workflow. <sup>[p](#fn-p)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<a id="fn-a"></a>
**a** — The workflow page composes each stage from the shared UI library's
`useWorkflowConfig` composables (`lib/ui-library/src/pages/workflow/composables/
useWorkflowConfig/`). Editorial Submission-stage panels come from
`workflowConfigEditorialOJS.js` `[WORKFLOW_STAGE_ID_SUBMISSION]`:
`getPrimaryItems` → `FileManager` namespace `SUBMISSION_FILES` (atom AFFW-286,
panel title `submission.submit.submissionFiles` "Submission Files") +
`DiscussionManager` (AFFW-287); `getSecondaryItems` → `ParticipantManager`
(AFFW-288) + `ReviewerSuggestionManager` when
`pageInitConfig.publicationSettings.isReviewerSuggestionEnabled` (AFFW-289). A
common `getPrimaryItems` prepends `WorkflowSubmissionStatus` for every stage
(note h). OMP composes via `useWorkflowConfigOMP.js` (`deepMerge` of the OJS
config then `workflowConfigEditorialOMP.js`); OMP's config defines its own
`[WORKFLOW_STAGE_ID_SUBMISSION].getActionItems` (note e) but does not redefine
the primary/secondary panels, so the panel roster is shared. OPS composes via
`useWorkflowConfigOPS.js` (`deepMerge` of OJS + `workflowConfigEditorialOPS.js`)
— see note p for why the Submission block never mounts there. Stage-open access
is the shared `permissions.accessibleStages` gate, owned by *Workflow screen &
stage access*. Live-observed 2026-08-02 (OJS and OMP): the panels render as
listed; the discussions panel is headed "Desk Review Tasks & Discussions" (not
"Discussions"). The reviewer-suggestions panel was observed on OJS only
(shared panel config): with "Reviewer Suggestion at Submission" enabled but no
suggestions entered, no panel renders; with one suggestion entered through the
submission wizard, the panel "Reviewers Suggested by Author" renders under
Participants with the suggested reviewer and the author's reason.

<a id="fn-c"></a>
**c** — The decision the buttons expose is built server-side by
`APP\submission\maps\Schema::getAvailableEditorialDecisions()` (per app),
gated by `checkDecisionPermissions()` (shared, `lib/pkp/classes/submission/
maps/Schema.php`): an editor's `canMakeDecision` is true only when they hold a
non-recommend-only Manager/Sub-editor stage assignment (or a non-recommend-only
Manager/Site-Admin group when unassigned); `isOnlyRecommending` is true when
they hold only recommend-only editorial assignments. Assistant-level roles pass
the `userHasAccessibleRoles` check but never satisfy `canMakeDecision`, so
`getAvailableEditorialDecisions()` returns an empty roster for them and no
buttons render. The UI reads the roster through `useSubmission().
isDecisionAvailable()`, which matches a decision's `stageId` against the
submission's active stage (note e). Live-observed 2026-08-02: an assigned
assistant gets no decision buttons on either app — on OMP no action area at
all, on OJS only the Schedule For Publication shortcut (note g) — and her
Participants panel is read-only (no "Assign", no row actions; panel mechanics
are *Stage participants*'). A recommend-only editor's observed offering is
finding A2.

<a id="fn-d"></a>
**d** — For a **queued** submission at `WORKFLOW_STAGE_ID_SUBMISSION` the OJS
roster is `SendExternalReview` + `SkipExternalReview` + `InitialDecline`; for a
**declined** submission it is `RevertInitialDecline` only. OMP's roster
(`omp-main/classes/submission/maps/Schema.php`) is `SkipInternalReview` +
`SkipExternalReview` while queued, plus `InitialDecline` and `SendInternalReview`;
declined → `RevertInitialDecline` only. Both apps' recommend-only rosters come
from `Repo::decision()->getDecisionTypesMadeByRecommendingUsers(SUBMISSION)`:
OJS `[SendExternalReview]`, OMP `[SendInternalReview]`. Live-observed
2026-08-02: a recommend-only editor sees exactly those buttons — "Send for
Review" (OJS), "Send to Internal Review" (OMP) — with the participant row
annotated "Only allowed to recommend an editorial decision" (finding A2).
Driven 2026-08-02 (OJS): pressing the recommend-only editor's button completed
the decision wizard and recorded the decision — the control works; OMP shows
the equivalent button (the press was not driven through the press itself).

<a id="fn-e"></a>
**e** — Buttons: `workflowConfigEditorialOJS.js` `[WORKFLOW_STAGE_ID_SUBMISSION].
getActionItems` pushes `WorkflowActionButton`s guarded by `isDecisionAvailable(
submission, DECISION_*)` — `DECISION_EXTERNAL_REVIEW` (label
`editor.submission.decision.sendExternalReview`, "Send for Review" on OJS),
`DECISION_SKIP_EXTERNAL_REVIEW` (`…skipReview`, "Accept and Skip Review"),
`DECISION_INITIAL_DECLINE` (`…decline`, "Decline Submission"),
`DECISION_REVERT_INITIAL_DECLINE` (`…revertDecline`, "Revert Decline"). OMP's
`workflowConfigEditorialOMP.js` replaces this getter wholesale: it uses
`DECISION_SKIP_INTERNAL_REVIEW` for the primary button (which localizes to
`editor.submission.decision.sendExternalReview`, "Send to External Review" on a
press), plus `DECISION_SKIP_EXTERNAL_REVIEW`, `DECISION_INITIAL_DECLINE`,
`DECISION_REVERT_INITIAL_DECLINE`, and `DECISION_INTERNAL_REVIEW`
(`…sendInternalReview`, "Send to Internal Review"). Decision types and their
transitions (`lib/pkp/classes/decision/types/`, OMP `omp-main/classes/decision/
types/`): `SendExternalReview` → `WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`;
`SkipExternalReview` → `WORKFLOW_STAGE_ID_EDITING` (copyediting); `InitialDecline`
→ status `STATUS_DECLINED`, no stage change; `RevertInitialDecline` → status
`STATUS_QUEUED`; OMP `SkipInternalReview extends SendExternalReview` → external
review; `SendInternalReview` → `WORKFLOW_STAGE_ID_INTERNAL_REVIEW`. `isDecisionAvailable`
matches against `getActiveStage(submission)`, so buttons vanish once the
submission leaves the stage (Rule 9). No app subclasses either grid handler or
the decision types beyond the OMP internal-stage set (positive chain evidence
for the OJS types). Live-observed 2026-08-02: the queued rosters verbatim —
OJS "Schedule For Publication" / "Send for Review" / "Accept and Skip Review" /
"Decline Submission"; OMP "Send to External Review" / "Accept and Skip Review"
/ "Decline Submission" / "Send to Internal Review" (that order — Internal
last; re-confirmed verbatim and in order on a second drive, 2026-08-02, with
no Schedule For Publication shortcut) — and the declined rosters of Rules 5–6.
"Send for Review" opened Review Round 1; "Send to External Review" opened
External Review Round 1 with the Internal stage left round-less; "Send to
Internal Review" opened Internal Review Round 1. Accept and Skip Review
observed live 2026-08-02 on OJS and OMP (wizard "Accept and Skip Review:
Notify Authors" → "Skipped Review"; stage label "Copyediting" on both apps;
Rule 3). Revert Decline observed live 2026-08-02 on OJS (wizard "Revert
Decline / Notify Authors / Submission Reactivated"; stage label back to
"Submission" with the full onward roster restored; Rule 5). The buttons'
disappearance after the stage is left observed live 2026-08-02 on OJS on two
independent submissions: after Accept and Skip Review, as after Send for
Review, the reopened Submission-stage entry offered only the Schedule For
Publication shortcut (note g) and none of the decision buttons (Rule 9,
scenarios 2–3).

<a id="fn-f"></a>
**f** — Delete: `workflowConfigEditorialOJS.js` (and the OMP getter) push a
`WORKFLOW_DELETE_SUBMISSION` button guarded by
`isDecisionAvailable(DECISION_REVERT_INITIAL_DECLINE)` (so, declined only) **and**
`hasCurrentUserAtLeastOneAssignedRoleInAnyStage(submission, [ROLE_ID_MANAGER,
ROLE_ID_SITE_ADMIN])`. The action (`useWorkflowActions().workflowDeleteSubmission`)
opens a `useModal` dialog titled `common.delete` "Delete", message
`editor.submissionArchive.confirmDelete` "Are you sure you want to permanently
delete this submission?", buttons "Confirm"/"Cancel", then `DELETE
/api/v1/{context}/_submissions/{id}`. The endpoint
(`lib/pkp/api/v1/_submissions/PKPBackendSubmissionsController::delete()`)
enforces `Repo::submission()->canCurrentUserDelete()` =
Manager (context) or Site Administrator, or an Author of an **incomplete**
submission (the wizard-draft path, not this button). No app overrides
`canCurrentUserDelete` (empty chain — shared). Live-observed 2026-08-02 (OJS
and OMP): the button and dialog verbatim as ruled — shown to a Journal Manager
on a declined submission only, not to a Section Editor on the same submission,
not on a queued one; confirming removed the submission from the dashboard's
Declined view and closed the workflow. Re-confirmed 2026-08-02 on a second
pass: the press delete driven end to end (declined roster, dialog verbatim,
confirm removes the monograph and closes the workflow), and the journal's
Declined-view before/after check walked as scenario 6 prescribes — the
declined submission listed only in the Declined view, never the default view,
gone from it after the delete; the Section Editor's missing Delete control
re-confirmed. The server-side refusal for other roles rests on the code above;
only the button's absence was verified live.

<a id="fn-a3"></a>
**f-a3** — Live-observed 2026-08-02 (OJS and OMP, identical terminal state;
driven as Journal Manager, Section Editor and Site Administrator on the
editorial address and as the author on My Submissions): after the delete, the
old workflow address opens the populated dashboard with an error dialog —
heading "Error", message "Invalid submission.", button "OK" — stacked over a
lingering workflow dialog that contains only the submission's id and a raw
`##common.help##` locale key in place of its help link. The empty shell also
paints alone for ~200 ms (warm; longer cold) before the error dialog attaches
— the transient state an earlier probe recorded as a silent empty page
(finding A3).

<a id="fn-g"></a>
**g** — `workflowConfigEditorialOJS.js` `[WORKFLOW_STAGE_ID_SUBMISSION].
getActionItems` pushes a `WorkflowActionButton` labeled
`editor.submission.schedulePublication` "Schedule For Publication",
`action: 'navigateToMenu'`, target `publication_{publicationId}_titleAbstract`
— a navigation to the publication's Title & Abstract, not a decision — and it is
pushed **unconditionally**, ahead of the status-gated decision buttons (basis of
finding A1). OMP's replacement getter omits it, so a press shows no such button
(atom AFFW-290 is `ojs,ops`). Live-observed 2026-08-02 (OJS): present while
queued and while declined, and for every role probed with workflow access —
recommend-only editor, Section Editor on a declined submission, assigned
assistant (whose action area holds that single button and nothing else);
pressing it landed on the publication's Title & Abstract with scheduling still
offered. OMP showed no such button in either state.

<a id="fn-h"></a>
**h** — The status box is `WorkflowSubmissionStatus.vue`
(`lib/ui-library/src/pages/workflow/components/primary/`), mounted by every
stage's common `getPrimaryItems`. Its message is computed for: stage not started
(`workflow.stageNotStarted`), a future active stage (`workflow.submissionInFutureStage`
/ "The submission is currently in the {$stage} stage."), the review stages, and Production;
for a submission **active at the Submission stage** none of those branches
matches and the component renders nothing (Rule 8). Live-observed 2026-08-02
(OJS and OMP): a queued submission shows no status box; the only state
indicator is the stage label under the submission's title — "Submission" while
queued, "Declined" after a decline (Rule 4).

<a id="fn-i"></a>
**i** — Author view: `workflowConfigAuthorOJS.js` (and the OMP duplicate)
`[WORKFLOW_STAGE_ID_SUBMISSION].getPrimaryItems` → `SubmissionStatus` only when
`hasSubmissionPassedStage(submission, WORKFLOW_STAGE_ID_SUBMISSION)`
(`submission.stageId > SUBMISSION`), then `FileManager` `SUBMISSION_FILES`
(AFFW-302..303) + `DiscussionManager`; the author config defines no
`getActionItems` for this stage, so no decision buttons render. `SUBMISSION_FILES`
grants the author `FILE_LIST`/`FILE_EDIT`/`FILE_DOWNLOAD_ALL`
(`managers/FileManager/useFileManagerConfig.js`). OPS's author config
(`workflowConfigAuthorOPS.js`) defines no Submission stage at all (note p).
Live-observed 2026-08-02 (OJS and OMP): the author's view holds the two panels
only — no Participants panel, no action area, no schedule shortcut — and the
author's Submission Files panel offers no Upload button despite the config's
file-edit grant (list-only live; panel mechanics are *Submission files*').

<a id="fn-j"></a>
**j** — Legacy grids: `AuthorSubmissionDetailsFilesGridHandler` (GRID-032) and
`EditorSubmissionDetailsFilesGridHandler` (GRID-033),
`lib/pkp/controllers/grid/files/submission/` (roles include Manager, Sub-editor,
Assistant, Author; ops `fetchGrid`/`fetchRow`), mounted by
`lib/pkp/templates/controllers/tab/authorDashboard/submission.tpl` (`load_url_in_div
id="submissionFilesGridDiv"`, atom AFFW-706) and `…/editorial.tpl`. The
author-dashboard entry point `PKPAuthorDashboardHandler::submission()` now
`redirectUrl`s to `dashboard/mySubmissions?workflowSubmissionId={id}` — the
current Vue workflow — so the legacy tab is no longer shown. No app subclasses
either grid handler (empty chains). Live-observed 2026-08-02 on all three
apps: the old address, typed as the submitter, 302-redirects (via a locale
hop) to the current dashboard — OJS and OMP land on My Submissions with the
modern workflow dialog open, OPS on the submission's publication tabs — and
the legacy page's body class matches zero elements, so the legacy grids render
nowhere. The author forward re-confirmed live 2026-08-02 on OJS and OPS; every
other signed-in role is denied before any redirect — an Editor and a Journal
Manager (OJS) and a Moderator (OPS) get "You do not currently have sufficient
privileges to view the submission.", and an author who is not this
submission's gets "You don't currently have access to that stage of the
workflow." — the legacy handler admits the author role only
(`AuthorDashboardAccessPolicy`, `ROLE_ID_AUTHOR`), one shared code path across
apps.

<a id="fn-omp1"></a>
**f-omp1** — OMP Submission-stage roster (note d) and action-item builder
(note e): `SkipInternalReview` (primary, labeled "Send to External Review") and
`SendInternalReview` ("Send to Internal Review") give the press its two review
routes; `SendInternalReview` targets `WORKFLOW_STAGE_ID_INTERNAL_REVIEW`
(the Internal Review stage, documented separately). Live-observed 2026-08-02:
"Send to External Review" opened External Review Round 1 with no internal
round created; "Send to Internal Review" opened Internal Review Round 1.

<a id="fn-p"></a>
**p** — `ops-main/classes/core/Application::getApplicationStages()` returns
`[WORKFLOW_STAGE_ID_PRODUCTION]` only; the OPS workflow menu
(`useWorkflowNavigationConfigOPS.js::getWorkflowItems()`) pushes only the
Production stage entry (`manager.publication.productionStage` "Production"), and
`workflowConfigAuthorOPS.js` exports an empty `WorkflowConfig`. OPS's editorial
config still inherits the OJS Submission-stage block through the `deepMerge` in
`useWorkflowConfigOPS.js` (atoms AFFW-286..289, 290..295 carry an `ops` marking
on this basis), but no preprint ever occupies `WORKFLOW_STAGE_ID_SUBMISSION`, so
the block is never mounted. OPS's own decision roster
(`ops-main/classes/submission/maps/Schema::getAvailableEditorialDecisions()`)
serves only the Production and Done stages (`Decline`/`RevertDecline`/
`ReturnToWorkflow`/`ReturnToDone`), never the Submission stage. Live-observed
2026-08-02: a preprint's workflow menu offered Production only, no Submission
entry and none of this spec's decision buttons anywhere in the dialog, while
Production showed its own controls ("Post the preprint", "Decline
Submission"); same-day checks on OJS and OMP showed the Submission entry and
its decisions as expected (finding OPS1). Cited from the body as
<sup>p</sup>.

<a id="fn-s"></a>
**s** — Scenario seeding uses the seeded test journal/press (`publicknowledge`)
and roster accounts (passwords = username doubled). New submissions are seeded
through the scenario submission endpoint with no decisions
(`submitted: true`, stage 1); the declined and post-decision states are reached
by driving the decision wizard through the UI so the emails and transitions are
real. Scenario 1–5 `editor.diana` (a Journal-editor-group account with decision
rights) on scratch submissions with `author.alex` as author; 4 also needs a
`manager.maya` (Journal Manager) and a `sectioneditor.ana` (Section Editor) to
contrast the Delete button; 6 `manager.maya` on a declined scratch submission;
7 `author.alex` on their own scratch submission; 8 a scratch press submission
via the OMP fleet; 9 a scratch preprint via the OPS fleet. Never decline or
delete a shared roster submission — use scratch submissions so parallel tests
are unaffected.

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Submission stage (editorial view) | workflow → Submission stage menu entry | AFFW-286..289 |
| Submission-stage decision buttons {OJS} | workflow Submission stage → action buttons | AFFW-290..295 |
| Submission-stage decision buttons {OMP} | workflow Submission stage → action buttons | AFFW-296..301 (296..300 in scope; 301 Send to Internal Review routes to the OOS Internal Review stage) |
| Submission stage (author view) | My Submissions → View → Submission stage | AFFW-302, 303 |
| Legacy author-dashboard files grid | author-dashboard tab (redirects to current workflow) | AFFW-706 · GRID-032 · GRID-033 |

## Reference — code anchors

- `lib/ui-library/src/pages/workflow/composables/useWorkflowConfig/workflowConfigEditorialOJS.js` · `workflowConfigEditorialOMP.js` · `workflowConfigEditorialOPS.js` — Submission-stage panels and buttons
- `lib/ui-library/src/pages/workflow/composables/useWorkflowConfig/workflowConfigAuthorOJS.js` · `workflowConfigAuthorOMP.js` · `workflowConfigAuthorOPS.js` — author view
- `lib/ui-library/src/pages/workflow/composables/useWorkflowConfig/useWorkflowConfig{OJS,OMP,OPS}.js` — per-app deepMerge composition
- `lib/ui-library/src/pages/workflow/composables/useWorkflowActions.js` — `workflowDeleteSubmission`
- `classes/submission/maps/Schema.php` (each app) — `getAvailableEditorialDecisions()`; shared `lib/pkp/.../Schema.php::checkDecisionPermissions()`
- `classes/decision/Repository.php` (each app) — `getDecisionTypesMadeByRecommendingUsers()`
- `lib/pkp/classes/decision/types/` — `SendExternalReview`, `SkipExternalReview`, `InitialDecline`, `RevertInitialDecline`; OMP `omp-main/classes/decision/types/` — `SkipInternalReview`, `SendInternalReview`
- `lib/pkp/api/v1/_submissions/PKPBackendSubmissionsController.php::delete()` · `lib/pkp/classes/submission/Repository.php::canCurrentUserDelete()`
- `lib/pkp/controllers/grid/files/submission/{Author,Editor}SubmissionDetailsFilesGridHandler.php` (GRID-032/033) · `lib/pkp/templates/controllers/tab/authorDashboard/submission.tpl` (AFFW-706)
- `ops-main/classes/core/Application.php::getApplicationStages()` — OPS single-stage workflow
- App divergence points checked: no app subclass of either files-grid handler or of the OJS decision types; OMP adds its internal-review decision set; OPS reduces the workflow to one stage
