# U26 Review stage & rounds — probe list (draft → step 3)

**Frame**: QA verification of documented behavior on a local disposable test
install with seeded accounts. For each rule, confirm what the role's screen
offers and what the server does with the same request, and record where they
differ so the gap can be fixed.

Fleets: OJS http://localhost:8000 · OMP http://localhost:8100 · OPS
http://localhost:8200. Spec under test:
`docs/product/specs/review-stage-and-rounds.md` (rule/scenario numbers below
refer to it). Items are grouped so ONE agent owns a cross-app item end to end
(multi-app rule 4); never split an item by app. Use scratch contexts via the
scenario endpoints for anything mutating; seeded `publicknowledge` data is
read-only. Probe reports record the locator used and mark claim-vs-context.

## Group I — absence & entry (cross-app by construction)

1. **OPS absence (spec absence paragraph + scenario 13).** OPS, roles:
   Preprint Server Manager, Author. On a scratch submission's workflow:
   side menu offers Production only; no review stage entry, no round items,
   no reviewer panel, no review decision button anywhere; author view
   likewise. Positive control: the same walk on OJS shows "Review" with
   "Review Round 1" after send-to-review. Settles: title badge {OJS OMP},
   scenario 13.
2. **OMP entry doors (scenario 12, OMP2).** OMP, Press Editor/Press Manager.
   (a) From Submission stage: the skip-internal decision exists (record its
   exact on-screen label) and lands the monograph in External Review Round 1
   with promoted files. (b) From Internal Review: "Send to External Review"
   lands in External Review Round 1. Settles: scenario 12, register OMP2,
   and the decision-label vocabulary for the spec/glossary.

## Group II — editorial screen inventory & round decisions (OJS + OMP, one agent)

3. **Panel roster, editor view (Rules 3, 11).** OJS + OMP external review,
   deciding editor (Section Editor assigned, and Journal Manager unassigned).
   With a round selected: panels "Revisions Uploaded", "Files for Review",
   Reviewers, author-response request, Discussions; side column Participants;
   "Recommendation" box ABSENT when no recommendation recorded; Reviewer
   suggestions panel only when the journal setting is on. Record exact panel
   titles for the spec's label accuracy.
4. **Decision button matrix (Rule 10, scenarios 1–2, 10).** OJS + OMP, per
   round state: fresh round (no reviewers) → which of Request Revisions /
   Accept Submission / Create New Review Round / Cancel Review Round /
   Decline Submission appear; after decline → only Revert Decline (+ Delete);
   exclusivity: a Section Editor (assigned, deciding) sees NO "Delete" while
   declined; a Journal Manager does. Settles Rule 10, scenario 10.
5. **Recommend-only view & display exclusivity (Rules 8, 11; scenario 8).**
   OJS + OMP. Recommend-only Section Editor: recommendation controls replace
   decision buttons; NO "Recommendation" listing box for them. Deciding
   editor (Journal Manager): listing box appears with the recorded
   recommendation; round status walks "Awaiting recommendations…" → "All
   recommendations are in and a decision is needed." Also check A3's mask:
   with one review overdue AND all recommendations in, what does the status
   line say? Settles Rule 11, register A3.
6. **Author upload window & A1 (Rule 12, scenarios 3–4).** OJS + OMP, Author.
   Status revisions-requested: "Upload revisions" present; upload → status
   flips, button still present (stay-in-round). Resubmit flow: after FIRST
   upload, is the workflow "Upload revisions" button gone (A1)? And does the
   "Revisions Uploaded" panel itself still offer an "Upload" control to the
   author at that moment — or at any status outside Rule 12's window (footnote
   c question)? Record which statuses actually show each control. Settles A1's
   impact, Rule 12, footnote c.
7. **Past-round read-only (Rule 5, scenario 5).** OJS + OMP, deciding editor
   AND Author. With two rounds, select Round 1: no action buttons (either
   role), status text "The submission has been advanced to the next round of
   review", panels scoped to round 1's files/reviewers.

## Group III — author-side surfaces (OJS + OMP, one agent)

8. **Round status wording seen by the Author (Rule 8, register A2).** OJS
   (spot-check OMP): with a round at "new reviews submitted" and at "review
   overdue", record the exact status text the AUTHOR's workflow screen shows
   (editor phrasing vs the author variants). Settles A2's symptom.
9. **Notifications panel (Rule 14, scenario 7).** OJS + OMP, Author. Panel
   absent before any decision; after a decision email to the author, subject +
   date listed; click opens the full message in a panel. Also: an old
   author-dashboard URL for the submission lands on My Submissions (legacy
   redirect, footnote y). Settles Rule 14, part of footnote y.
10. **Author reads an open review; anonymity exclusivity (Rule 13, scenario
    6).** OJS + OMP. Open review completed: author's reviewers panel lists
    the reviewer; read action opens sheet with reviewer name, date,
    recommendation, author-shared comments only (a private-to-editor comment
    must NOT render), attachments. Anonymous review completed: panel does not
    list it (and no read path exists from the author's screen). Record the
    read action's exact label. Settles Rule 13, scenario 6.

## Group IV — lifecycle mutations (OJS + OMP, one agent)

11. **Cancel round (Rule 15, scenario 9; OMP1).** OJS + OMP. (a) Round 2 with
    an invited (not accepted) reviewer: "Cancel Review Round" offered;
    confirm; round 2 gone from menu, round 1 current; reviewer-unassign and
    author emails in Mailpit (scope by recipient+tag). (b) After a reviewer
    accepts: button no longer offered. (c) Round 1 cancel on OJS → back to
    Submission stage. (d) OMP: external Round 1 cancel with prior internal
    rounds → lands on Internal Review (OMP1); without internal rounds →
    Submission. Settles Rule 15, scenario 9, OMP1.
12. **New round carry-over (Rule 16, scenario 4).** OJS + OMP. Resubmit →
    author revision → "Create New Review Round": wizard lists the revision
    file preselected; after confirm, Round 2 selected, status waiting-for-
    reviewers, file in "Files for Review"; the original file still in round
    1's "Revisions Uploaded".
13. **Files-for-review curation (Rule 17, scenario 11).** OJS + OMP, editor.
    "Upload/Select Files" modal title "Current Review Files For Round N";
    tick/untick behavior: added file appears for the round; unticked file
    withdrawn but still present in the submission's file history (check via
    the modal reopening still listing it unticked). Settles Rule 17.
14. **Revision-upload side effects (Side effects; scenario 3).** OJS + OMP.
    Author uploads a revision: Mailpit shows "Revised Version Notify" to the
    assigned Section Editor AND to a recommend-only editor; round status
    flips both views. Also: after Request Revisions, does ANY current screen
    show the author a pending-revisions task/prompt beyond the status line
    and email (register A6 absence check)? Settles side-effects bullets, A6.

## Group V — single-app follow-ups

15. **Minimum-reviews setting (Rule 9, footnote u).** OJS. Locate the
    journal-level minimum-reviews-per-submission setting on screen (record
    its label and location — likely review settings); set it to 2; verify the
    round status line is replaced by the secure-N-reviews prompt until two
    reviews are confirmed. Settles Rule 9 + the setting's home (may move the
    Settings bullet to U29's territory — flag if its screen is U29's).
16. **Author read-review server agreement (register A7).** OJS. QA check per
    the Frame: the author read-review sheet offers reading only; verify
    whether the same surface's server side records a review-confirmation
    state change when the author's session submits that operation, and
    whether any author screen offers it. Report screen-vs-server agreement
    neutrally. Settles A7.
17. **Back from Copyediting status (Rule 18, register A4).** OJS. Accept →
    Copyediting → back-out decision: round status text immediately after
    return (a) with no active reviewers left, (b) with a confirmed review in
    the round. Settles Rule 18 / A4's visibility claim.

— 17 items. Routing reminder for the probe orchestrator: probe briefs carry
the Frame and the Routing line per RUNBOOK ("Private finding routing").
