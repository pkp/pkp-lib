# Readability report — review-stage-and-rounds.md

Verifier persona: QA/product reader, scholarly-publishing literate, no access to
code, screens, probes, or campaign documents. Read: the spec body down to
"## Footnotes — mechanism & evidence" (stopped there), plus the two glossary
entries the body pointed at (GLOSSARY.md "deciding editor"; APP-GLOSSARY.md
vocabulary map). Date: 2026-07-27.

---

## Exercise (a) — Restatements

### Permission-table rows

- **P1 · See the Review stage and its rounds** — Anyone assigned to this submission under a group that covers the Review stage — Author included — can open the stage and its rounds; the role only decides which panels and buttons appear.
- **P2 · Curate "Files for Review"** — Assigned Section Editors, Guest Editors, and assistant-group participants whose group covers the stage (only the Funding Coordinator among the seeded assistant groups) may add, edit and delete review files; Journal Manager, Journal Editor and Site Administrator may do so without any assignment.
- **P3 · Upload a revision file** — The Author gets the "Upload revisions" button only while the round is in a revisions-awaiting state; the "Revisions Uploaded" panel's own "Upload" is always shown to the Author but only actually succeeds when the stage's newest round "carries" one of three decisions; the editorial roles from P2 can use the panel "Upload" in any state. **STUMBLE — see summary (S-1)**: I cannot reconcile the completing state "a new-round decision" with A6's non-completing state "a freshly created next round".
- **P4 · Edit / remove a revision file** — Both the Author and the P2 editorial roles can update a revision file's details or delete it; the editorial roles additionally get a "More Information" notes action the Author lacks.
- **P5 · Record a round decision** — Only a deciding editor (per the glossary: an assigned editor without "Recommend only", or an unassigned Journal Manager / Site Admin), and only on the latest round; which decisions are offered when is Rule 10.
- **P6 · Record a recommendation** — A recommend-only participant records recommendations instead; their controls replace the decision buttons entirely.
- **P7 · See the round's "Recommendation" listing** — Only a deciding editor, only with a round open, and only once at least one recommendation exists; the Author never sees any side column.
- **P8 · Cancel a review round** — A deciding editor, but only while no reviewer in the round has accepted or declined the invitation or completed a review.
- **P9 · Delete the submission from the Review stage** — Journal Manager, Journal Editor or Site Administrator, and only while the submission stands declined.
- **P10 · Read a review (author view)** — The Author can read only open reviews, only after completion; anonymous review methods never expose a review to the Author.

### Numbered rules

- **R1** — Review runs in numbered rounds: Round 1 exists the moment the submission enters the stage, each "Create New Review Round" adds the next number, and a cancelled round's number is released and reused by the next round created (flagged A9).
- **R2** — Every round is its own side-menu entry ("Review Round N"); opening one heads the page "Workflow: Review (Round N)" and scopes all panels to that round; the latest round is preselected while Review is the active stage, otherwise the reader must pick a round from the menu.
- **R3** — The editor's round shows: "Round N Status", "Revisions Uploaded", "Files for Review", "Reviewers", "Author Response" (OJS only), "Review Tasks & Discussions"; the side column holds "Participants", the "Recommendation" listing once one is recorded, and "Reviewers Suggested by Author" only when the setting is on AND a suggestion exists.
- **R4** — The author's round shows, in order: the status, "Notifications" (once a decision mail was sent), "Reviewers" (once a completed open review exists), "Revisions Uploaded", "Review Tasks & Discussions", and on OJS an "Author Response" block when invited; no side column.
- **R5** — Only the latest round takes decisions. Earlier rounds show no decision buttons for anyone; while Review is active they read (under plain "Status") "The submission has been advanced to the next round of review"; after acceptance every round shows a stage-location line instead ("currently in the Copyediting stage" wordings); yet the panels keep their working controls (upload, add reviewer, discussions, reviewer row actions).
- **R6** — Each round has exactly one status line, identical for every role, headed "Round N Status" ("Status" on past rounds); statuses are either stamped by a decision or computed live from reviewer/recommendation activity, plus a settings-gated minimum-reviews pair. I could restate every row of the status table (conditions are concrete: no reviewers → waiting line; in-progress none overdue → awaiting responses; unconfirmed submission → new reviews; overdue outranks those two; all settled → all-confirmed fall-through even if nothing was confirmed (A11); the three recommendation lines; the two request/submitted revision pairs; accepted stored-not-shown (A10); declined final unless reverted; returned-back stamp (A4); legacy "Sent for external review."; the two minimum lines).
- **R7** — Declined/sent-on statuses never recompute; accepted is stored but hidden; the two revisions statuses only flip between requested and submitted forms; everything else recomputes along the stated precedence chain. **STUMBLE — see summary (S-2)**: the chain's evaluation direction (first match wins, reading left to right?) is never stated; I inferred it from the table's "outranks" notes.
- **R8** — The status line is one line, and every role reads exactly the same text (flagged A2 — author-specific wordings exist but are unused).
- **R9** — With a journal minimum set, the current round shows a prompt line "Minimum number of confirmed reviews required: N." above the status; when all reviews are confirmed but the minimum is unmet, only the prompt shows; when the minimum is met, a "Minimum required number of reviews have been confirmed…" line appears beneath the prompt — but only while the status is a "reviewer-activity" one; count is per round; past rounds show no prompt. **STUMBLE — see summary (S-3)**: "reviewer-activity" statuses are an undefined subset of the computed family.
- **R10** — The latest active round always offers the deciding editor "Request Revisions" (wizard picks stay/resubmit), "Accept Submission", "Create New Review Round", "Decline Submission"; "Cancel Review Round" only while no invitation is answered and no review completed (author revisions don't remove it); on decline everything collapses to "Revert Decline", plus "Delete" for JM/JE/Site Admin. **Minor STUMBLE — see summary (S-4)**: "the author's submitted revisions do not close it" — I had to guess "it" = the cancel window.
- **R11** — A recommend-only participant sees "Recommend Revisions" / "Recommend Accept" / "Recommend Decline" instead of decision buttons; after recording, the area restates the choice as the matching decision's label under a "Recommendation" heading with "Change decision"; only the deciding editor sees the side-column "Recommendation" box — one comma-joined line of decision labels, no names, no dates.
- **R12** — The Author's "Upload revisions" button shows during exactly three statuses (requested, resubmit-requested, submitted-and-decision-needed) — but not after the first upload of a resubmit round (A1); uploads land in "Revisions Uploaded" on both views.
- **R13** — The author's "Reviewers" panel appears only once the round has a completed open review, lists open reviews only; "Read Review" opens name, completion date, recommendation (when the journal has options and the reviewer chose one — presses have none, OMP4), the author-shared review text and attachments; editor-only comments never appear.
- **R14** — "Notifications" lists each decision email sent to the author (subject, date/time), per submission — the same list on every round; clicking a subject (mouse only, A7) opens the full message; hidden until a decision mail was actually sent; Review stage only.
- **R15** — Cancelling a round unassigns all its reviewers (responses and file access withdrawn) and deletes the round; the previous round becomes current; cancelling Round 1 returns the submission to the Submission stage (press variants OMP1/OMP6); the round's files survive but are listed nowhere; author and reviewers get wizard-composed emails (reviewer step skipped when there are none).
- **R16** — "Create New Review Round" offers the previous round's revisions, preselected, to copy into the new round's "Files for Review" (originals stay put); the new round starts at "Waiting for reviewers to be assigned."
- **R17** — "Upload/Select Files" opens "Current Review Files For Round N"; by default round files only, the "Show files from all accessible workflow stages." tick expands it; ticking a foreign file copies it (ticking again makes a second copy); copies and decision-promoted files arrive unticked; the checkbox records a state nothing explains and does not control reviewer access (A8) — reviewer file access is settled at Add Reviewer time from the panel's full contents.
- **R18** — "Move to Review" from Copyediting reactivates the last round as-is — no new round, no file carry — and stamps it "Returned back to review.", a stamp usually outranked by recomputed statuses (A4).

---

## Exercise (b) — Scenario walk log

Fresh install, seeded roles, one test submission unless noted. "Observe" = what
I check on screen.

**1. Round 1 opens with the chosen files** — As Journal Editor, open a
submission sitting in the Submission stage and record the send-to-review
decision, leaving the offered file selection as-is. *Stumble (S-5)*: the
scenario never names the button; I only know to look for "Send for Review"
because finding OMP2 happens to state OJS's label in passing. Observe: side
menu gains "Review Round 1"; click it; page headed "Workflow: Review (Round
1)"; status block "Round 1 Status" / "Waiting for reviewers to be assigned.";
"Files for Review" lists exactly the files I left ticked; "Revisions Uploaded"
empty. Executable.

**2. Status follows reviewer activity** — As Journal Editor, in the round's
"Reviewers" panel use its add-reviewer control (Rule 5 says one exists) and
complete the assignment (mechanics deferred to U27 — acceptable). Observe:
status "Awaiting responses from reviewers." As the Reviewer, accept and submit
the review (U28's flow). Observe: "New reviews have been submitted." Then "the
editor confirms the review (U27)". *Stumble (S-6)*: no on-screen control for
"confirm" is named anywhere in this spec, and the U27 spec I'm told owns it
does not exist yet — I would be hunting the Reviewers panel for anything
confirm-shaped. Observe after guessing right: "All reviews are confirmed and a
decision is needed." Executable with guessing.

**3. Revisions round-trip inside the round** — As Journal Editor, press
"Request Revisions", pick the stay-in-round option in the wizard, finish.
Observe: status "Revisions have been requested." Log in as the Author, open the
same round. Observe: "Upload revisions" button present; use it, upload a file.
Observe on both accounts: status "Revisions have been submitted and a decision
is needed."; the file listed in "Revisions Uploaded"; one "Revised Version
Uploaded" email in the editors' inboxes (mail capture). Clean.

**4. Resubmit leads to a new round** — As Journal Editor, press "Request
Revisions", pick the resubmit variant. Observe: "Revisions requested from the
author to be taken to a new review round." As Author, upload via "Upload
revisions". Observe: "Revisions submitted. A new review round needs to be
created."; and per A1, the "Upload revisions" button is now gone — a check the
spec pre-announces. As Journal Editor, press "Create New Review Round";
observe the wizard offers the uploaded revision preselected; confirm. Observe:
"Review Round 2" in the side menu and selected, status "Waiting for reviewers
to be assigned.", carried file in its "Files for Review". Clean.

**5. Earlier rounds are read-only** — With two rounds, as Journal Editor click
"Review Round 1" in the side menu. Observe: no decision buttons anywhere;
status area headed plain "Status" reading "The submission has been advanced to
the next round of review"; panels show only round 1's files and reviewers.
Clean.

**6. Author reads an open review** — Run a review to completion "open". *Stumble
(S-7)*: nothing in the walked text says where "open" is chosen — the Settings
section says review method is per reviewer assignment with defaults in U29, but
the concrete control I'd click during Add Reviewer is unnamed. After completing
it: as Author, observe the round now shows a "Reviewers" panel listing that
reviewer; click "Read Review"; observe reviewer's name, date, recommendation
(journal only), the author-shared comments, attached files; verify a comment
the reviewer marked editor-only is absent. Rerun with an anonymous method;
observe the author's panel never lists it. Executable with guessing.

**7. Author follows the editor's messages** — As Author, before any decision:
observe no "Notifications" panel. As editor, record any decision and send its
notification email in the wizard (U34). As Author: observe the panel now lists
the subject and date; click the subject (mouse — the spec warns keyboard fails,
A7); observe the full message opens. Clean.

**8. Recommendations reach the deciding editor** — Precondition: a Section
Editor assigned with "Recommend only" checked (toggle named, owner U35 — I can
find a checkbox by that name in participant assignment). As that Section
Editor, open the round: observe "Recommend Revisions" / "Recommend Accept" /
"Recommend Decline" instead of decision buttons; record one (U34 wizard).
Observe: the area restates it as the matching decision label (e.g. "Accept
Submission") with a "Change decision" button. As Journal Manager: observe the
side-column "Recommendation" box with that label, and — once every
recommend-only participant has recorded — status "All recommendations are in
and a decision is needed." Back as the recommend-only user: observe no
side-column box. Clean.

**9. Cancel a fresh round** — As Journal Editor, on a round whose one reviewer
has not answered the invitation: observe "Cancel Review Round" offered; run the
wizard to its final "Record Decision" button. Observe: the round gone from the
side menu, previous round current (Round 1 case: submission back on the
Submission stage; press: Internal Review when internal rounds exist). Set up a
second round whose reviewer accepted (or declined) the invitation: observe the
button absent. Clean.

**10. Decline and revert in review** — As Journal Editor: "Decline Submission";
observe status "Submission declined." and only "Revert Decline" offered. As
Journal Manager: observe "Revert Decline" plus "Delete". Press "Revert
Decline": observe active review restored and a computed status back. As a
Section Editor: observe "Delete" is never offered. Clean.

**11. Curating Files for Review mid-round** — As Journal Editor on scenario 1's
round: press "Upload/Select Files"; observe modal "Current Review Files For
Round 1" listing only the round's review files; tick "Show files from all
accessible workflow stages."; tick one foreign file; press "OK". Observe: the
panel lists a copy; reopen the modal and observe the copy's checkbox unticked
(A8, pre-announced). Untick the round's existing review file, press "OK";
observe it still listed in the panel and still among the submission's files.
Clean.

**12. {OMP} Two doors into External Review** — As Press Editor: door one —
record "Send to External Review" straight from the Submission stage; observe
the Select Files step offers the submission files pre-ticked. Door two — first
record "Send to Internal Review", then from Internal Review record "Send to
External Review"; observe its files step offers only author-uploaded internal
revisions, and with none reads "No items found.", after which External Review
Round 1's "Files for Review" shows "No Items" (OMP3, pre-announced). Either
door: observe "Review Round 1" of External Review at "Waiting for reviewers to
be assigned.", and the common scenarios run with the press deltas (no
recommendation line, no "Author Response" panel). Clean.

**13. {OPS} Absence check** — As Preprint Server Manager, open any submission's
workflow on a preprint server. Observe: the side menu offers Production only —
no review stage, rounds, reviewer panels, or review decisions anywhere.
Positive control: the Workflow menu renders and Production decisions are
offered; the journal-side contrast is scenario 1. Clean.

---

## Stumble summary

| # | Location | Quote | Severity | What's missing |
|---|----------|-------|----------|----------------|
| S-1 | Permissions, "Upload a revision file" (with A6) | "completes only when the stage's newest round carries a revision request, an accept, or a new-round decision" vs A6's non-completing "a freshly created next round" | BLOCKER | The two statements read as contradictory: a round created by the new-round decision seems to both satisfy and fail the completing condition — I cannot state when the "new-round decision" case ever completes. Fix: say which round a decision "carries" attaches to. |
| S-2 | Rule 7 | "recompute from activity in this order: no reviewers → … → all-confirmed" | FRICTION | The chain never says first-match-wins reading left to right; I inferred it from the status table's "outranks" notes. Fix: one clause naming the evaluation direction. |
| S-3 | Rule 9 | "only while the round's status is a reviewer-activity one" | FRICTION | "Reviewer-activity" is an undefined sub-family — I cannot tell whether e.g. "Revisions have been submitted…" counts. Fix: enumerate or point at the table rows meant. |
| S-4 | Rule 10, Cancel bullet | "the author's submitted revisions do not close it" | FRICTION | Antecedent of "it" (the cancel window, presumably) is a guess. Fix: name the thing not closed. |
| S-5 | Scenario 1 | "send a Submission-stage submission to review" | FRICTION | The decision's on-screen label is not in the scenario; I found "Send for Review" only inside finding OMP2. Fix: quote the label in the scenario. |
| S-6 | Scenario 2 | "after the editor confirms the review (U27)" | FRICTION | No on-screen control named for "confirm", and the owning spec doesn't exist yet — pure guesswork at the Reviewers panel. Fix: quote the control's label. |
| S-7 | Scenario 6 | "run an open review to completion" | FRICTION | Where the "open" method is chosen is never made concrete (Settings section defers to U27/U29). Fix: name the field on the Add Reviewer step. |

**Totals**: 28 restatements (10 permission rows + 18 rules) — 24 clean, 4
stumbled (1 blocker, 3 friction). 13 scenarios walked — 10 clean, 3 with
friction, 0 blocked. Overall: 1 BLOCKER, 6 FRICTION.

The spec's habit of pre-announcing its own defects (A1, A7, A8, OMP3) inside
the scenarios materially helped the walk — those steps verified as written.

---

## Rewrites applied (2026-07-28, spec editor)

- **S-1** (P3 row + A6 entry) — resolved per verify-chunk3/4 A6 evidence: the
  gate follows the decisions recorded ON the stage's newest round itself; a
  decision stays on the round it was recorded on, so a freshly created next
  round carries no decision (the new-round decision belongs to the previous
  round) and refuses until one of the completing decisions is recorded on it.
  Row and A6 now state this identically.
- **S-2** (Rule 7) — added the evaluation-direction clause: "the first
  condition that holds, checked left to right, sets the status".
- **S-3** (Rule 9) — "reviewer-activity" now enumerated as the five rows of
  Rule 6's table (waiting, awaiting-responses, new-reviews, overdue,
  all-confirmed), per verify-chunk2's Rule 9 cases.
- **S-4** (Rule 10, Cancel bullet) — "do not close it" → "do not remove the
  button".
- **S-5** (Scenario 1) — decision label quoted: "Send for Review" (press:
  "Send to External Review" [OMP2]).
- **S-6** (Scenario 2) — confirm step made concrete from probe-group5's
  observed flow: the reviewer row's "Read Review" and its closing "Confirm"
  button, with the flow still pointed at U27.
- **S-7** (Scenario 6) — the review-method choice point named: "Open" method
  via the journal's "Default Review Mode" (Settings → Workflow → Review, U29)
  or the per-assignment choice at Add Reviewer time (U27). (The Add Reviewer
  step's own field label is not recorded in the chunk evidence, so the
  scenario cites the recorded setting label and the per-assignment
  precondition instead of coining one.)
