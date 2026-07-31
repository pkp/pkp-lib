# U26 Review stage & rounds — Claim-check merge (RUNBOOK step 8)

Change list distilled from claimcheck-C1.md, claimcheck-C2.md, claimcheck-C3.md
against `docs/product/specs/review-stage-and-rounds.md`, in the step-3b digest
schema. `Proposed:` is a suggestion; the fold agent decides under step 4's
rules. Holds-only verdicts (C1: preamble access error, author path, SE gate,
deciding-editor row, A2 corroboration, Rules 2–6; C2: Rules 8–12 rosters, fn f/g
acceptance; C3: Rules 15–17, OMP paragraphs) carry no block — they change no
spec text and close no open caveat. Blocks M3, M8, M9, M15 are caveat-closers.
Author: claude merge agent, 2026-07-31.

---

### M1 — The review stage's Assign Participant dialog does offer one assistant-level group: Funding coordinator, on both apps
Affects: Actors row "Open the review stage" · register A5 · footnote f-a5
Status: corrects
Apps: OJS and OMP (dialog roster read on both)
Proposed: reword the row and A5 from "offers no assistant groups" to "offers no *production* assistant groups (Copyeditor, Layout Editor, Proofreader)" and carve out Funding coordinator — an assistant-level group with a screen path onto the stage. Intended design (funding's review access is by design), so wording only; A5's question narrows accordingly.
Evidence: claimcheck-C1.md — Actors row "Open the review stage" (A5) + proposed content 1.

### M2 — A past round still changes: its retained panel controls act — adding a reviewer and uploading a file both land in the past round
Affects: Rule 1 ("only the current round can still change") · Rule 10 · footnote e ("was not exercised" clause)
Status: corrects
Apps: OJS (both mutations driven there; Rule 10's retained controls confirmed on both apps)
Proposed: soften Rule 1 to scope immutability to the round's status/decision machinery, and state that past-round participant/reviewer/file panels remain live and mutate the past round; update footnote e's untested-angle clause. Rule 10's "keep their own controls" stands, now known to act. Not over-entitlement — the acting role is entitled to manage those panels.
Evidence: claimcheck-C1.md — Rule 1 + proposed content 2; claimcheck-C2.md — Rule 10 + proposed delta 6 (one fact, two independent drives).

### M3 — The all-declined round's false "all confirmed" sentence reproduces on the press too
Affects: register A4 · footnote f-a4
Status: confirms (closes the entry's single-app evidence caveat)
Apps: OJS and OMP — identical
Proposed: extend A4's basis to note the finding reproduces on OMP (2026-07-31); no wording change to the entry's question or lean.
Evidence: claimcheck-C1.md — Rule 5 + proposed content 3.

### M4 — After Move to Review, the round does not read "Returned back to review." in common states — reviewer facts mask it
Affects: Rule 5 row "Returned back to review." · Rule 6 precedence · Cross-feature "Copyediting stage" bullet
Status: corrects
Apps: OJS (driven); mechanism is shared code per footnote c
Proposed: qualify the Rule 5 row and the Copyediting bullet — the sentence appears only when the round's reviewers are all completed and confirmed; a reviewerless round reads "Waiting for reviewers to be assigned.", an in-progress one "Awaiting responses from reviewers." Add the returned-back status to Rule 6's precedence discussion, below the reviewer-derived sentences.
Evidence: claimcheck-C3.md — Cross-feature Copyediting section + proposed content 3.

### M5 — Whether "Returned back to review." ever positively renders was not observed — the surfacing state needs a UI-completed, confirmed review the seeder cannot produce
Affects: Rule 5 row "Returned back to review." · footnote c/d evidence trail
Status: undetermined
Apps: OJS/OMP
Proposed: footnote caveat on the requalified row (M4): the positive appearance in the all-confirmed state stays code-confirmed only. Lean: the code path is unambiguous (stored status survives the reviewer scan only then), so a caveat, not a ❓ register entry.
Evidence: claimcheck-C3.md — Cross-feature Copyediting section + Blockers (by-cost item 2).

### M6 — Deleting the only revised file does not bring the author's revisions task back, from either deleter, in either app
Affects: Rule 7 parenthetical · Side effects "Request Revisions decision" bullet ("returns if the only revised file is deleted") · footnote m
Status: corrects
Apps: OJS and OMP — identical (editor-side and author-side deletion)
Proposed: drop/correct the revival claim in all three places (the status flip-back itself holds) and add a candidate 🐞/❓ register entry: the revival machinery evaluates at delete time while the file still counts, so the task never returns; a later review-stage file add does re-create it, under the pendingRevisions wording.
Evidence: claimcheck-C2.md — Rule 7 + proposed delta 2.

### M7 — On a press the fresh Request Revisions task reads "Revisions to consider in External Review.", not "Revision required."
Affects: Side effects "Request Revisions decision" bullet (unmarked both-apps sentence) · footnote m
Status: corrects
Apps: genuine divergence — OJS "Revision required."; OMP "Revisions to consider in External Review." (My Submissions row and Resubmit-task wording stay identical in both)
Proposed: mark the task-wording sentence app-specific and add a candidate ❓ register entry (the press's internal-review notification bookkeeping deletes the shared decision task — likely unintended, wording-level only). Caution for the wording: on a journal too, an aged flow (later file add while the decision stands) re-creates the task under the pendingRevisions wording, so "Revision required." is the fresh-decision wording, not a grid invariant.
Evidence: claimcheck-C2.md — Directed item 1 + proposed delta 1.

### M8 — The editor's controls on the Revisions Uploaded panel are now observed: header Upload (full wizard, on the author's behalf), per-file Update File Details / More Information / Delete
Affects: footnote i ("its editor actions are probe pending") · Actors row "Upload revised files"
Status: confirms (closes the footnote's probe-pending caveat)
Apps: OJS and OMP — identical; author's per-file menu is narrower (no More Information)
Proposed: replace fn i's probe-pending clause with the observed action list; no rule-text change.
Evidence: claimcheck-C2.md — Directed item 2 + proposed delta 3.

### M9 — A7 reproduces on the press, in a sharper form: a file uploaded from the dialog itself arrives unticked while the panel lists it
Affects: register A7 · footnote f-a7 · Rule 8 marker context
Status: confirms (closes A7's "OMP not checked" caveat)
Apps: OJS and OMP
Proposed: extend A7's basis to "reproduces on OMP (2026-07-31), including for a file uploaded from the dialog itself"; entry's question and lean unchanged.
Evidence: claimcheck-C2.md — Rule 8 + proposed delta 5.

### M10 — A round whose reviewers only declined or stand invited may also lose the Cancel Review Round button — a declined assignment appears to suppress Cancel
Affects: Rule 11 Cancel qualification · footnote f
Status: undetermined
Apps: OJS (single observation; OMP not driven for this)
Proposed: footnote caveat on Rule 11's Cancel line or a ❓ register entry: Cancel was absent on a round with one declined + one unanswered invitation, though the rule's stated gate (no acceptance or completed review) was not met. Lean: the retractability check counts a declined assignment as disqualifying, so the rule's "only while no reviewer has accepted or completed" is narrower than the app's gate; the one observation that settles it is a second declined-only round with the retractability code path confirmed.
Evidence: claimcheck-C1.md — Cross-note for the Rule 11 owner (C2 did not adjudicate; its Rule 11 confirmations used rounds without declined assignments).

### M11 — A recommending editor gets the three recommendation buttons only when a deciding-editor participant is also assigned; alone, they get a guard message and no buttons
Affects: Rule 13 · Actors row "Record a decision on the round" (recommending-editor line) · footnote e
Status: corrects
Apps: OJS (driven); config shared per footnote a
Proposed: state the precondition on Rule 13 — with no deciding-editor stage participant, the Recommendation box instead tells the recommending editor an authorised editor must be assigned first, and no buttons render. A restriction, not an entitlement.
Evidence: claimcheck-C3.md — Rule 13c + proposed content 1.

### M12 — The author view's panels are conditional, not a fixed four: Notifications renders only once an editor letter exists, the reviewers list only once an open completed review does
Affects: Rule 14 · Rule 16 context
Status: corrects
Apps: OJS (driven); mount guards shared per footnote a
Proposed: note on Rule 14 that a fresh in-review submission shows only Revisions Uploaded + Discussions; Notifications and the reviewers list appear with their preconditions (which Rules 15–16 already state — the flat enumeration is what misleads). Rule 16 gains "absent, not empty, when no letter exists".
Evidence: claimcheck-C3.md — Rule 14 + Rule 16 + proposed content 4.

### M13 — Below the reviews minimum the status box does not stand the minimum line alone: "Awaiting responses from reviewers." still shows beneath it
Affects: Settings "Minimum Confirmed Reviews Required" bullet · footnote r
Status: corrects
Apps: OJS and OMP — identical
Proposed: scope "the box shows no status sentence at all" to the submitted/confirmed reviewer sentences (which the minimum-aware override suppresses); the in-progress awaiting sentence still renders. Update footnote r, whose probe had one confirmed review and so saw the line alone.
Evidence: claimcheck-C3.md — Settings section + proposed content 2.

### M14 — What the box says once the minimum is met stays unobserved — crossing the threshold needs a UI-submitted, editor-confirmed review the seeder cannot produce
Affects: Settings "Minimum Confirmed Reviews Required" bullet · footnote r
Status: undetermined
Apps: OJS/OMP
Proposed: keep footnote r's existing "wording once the minimum is met remains unobserved" caveat, now with the reason (by cost, not oversight). Lean: leave as footnote caveat, not a ❓ register entry — no user-facing claim in the body depends on it.
Evidence: claimcheck-C3.md — Settings section (unresolvable item) + Blockers (by-cost item 1).

### M15 — The revised-version email's two open hedges close: an unassigned editor gets nothing, and a fresh sign-in re-arms the daily throttle
Affects: Side effects "Author uploads a revised file" bullet · footnote l
Status: confirms (closes fn l's unprobed sign-in branch and evidences the "assigned to the stage" qualifier)
Apps: OJS (driven); mechanism shared per footnote l
Proposed: record both branches as probed in footnote l and drop its remaining hedge; no body-text change.
Evidence: claimcheck-C2.md — Side effects, revised-file email + proposed delta 4.
