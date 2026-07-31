# U26 Review stage & rounds — probe digest (step 3b)

One block per spec-affecting fact. Evidence pointers name the probe report and
its item number; reproduction detail lives there. Digest date: 2026-07-31.

### D1 — The panels are headed "Files for Review" and "Revisions Uploaded" on screen, not "Review Files" and "Revisions"
Affects: Rule 8 | Rule 9 | Rule 14 | Actors rows 3–4
Status: corrects
Apps: OJS and OMP (verbatim match, incl. subtexts)
Proposed: plain claim · use the on-screen panel names in the rule text; panel roster and order otherwise confirmed
Evidence: probe-P1.md item 1; probe-P7.md item 16a

### D2 — A Copyeditor cannot reach the review stage: the stage's Assign dialog offers no assistant-level groups, and one assigned on another stage sees only "You don't currently have access to that stage of the workflow."
Affects: Actors row 1 | Actors row 3
Status: corrects
Apps: OJS (OMP not probed for this)
Proposed: ❓ register entry + trim assistant-level roles from the two Actors rows — no screen path assigns an assistant to this stage, and an assigned-elsewhere assistant gets no panels; whether an assistant assigned to the review stage by other means would get access is the open half
Evidence: probe-P1.md item 1

### D3 — The round status sentences render verbatim as specified across the probed lifecycle
Affects: Rule 4 | Rule 5 | scenarios 1–5, 9
Status: confirms
Apps: OJS full chain; OMP for waiting / new-reviews / all-confirmed / revisions-requested
Proposed: plain claim · confirmed live: waiting → awaiting responses → new reviews → all confirmed; both revision-request paths and their submitted partners; declined; past-round text; "The submission is currently in the Copyediting stage." after accept
Evidence: probe-P1.md item 2; probe-P2.md items 4–5; probe-P3.md item 6; probe-P4.md item 9; probe-P7.md item 16b

### D4 — On a past round or a past stage the box is headed plain "Status", not "Round {N} Status"
Affects: Rule 4
Status: corrects
Apps: OJS (OMP not probed for this)
Proposed: plain claim · one-line heading adjustment; the past-round body text has no trailing period on screen
Evidence: probe-P3.md item 6; probe-P4.md item 9

### D5 — The author's status box wording is character-identical to the editor's at every state probed
Affects: A2 | Rule 4 | Actors row 2
Status: confirms
Apps: OJS
Proposed: ❓ stays · update A2's basis from code-reading to live observation (four states checked side by side)
Evidence: probe-P1.md items 2–3

### D6 — A round whose only invited reviewer declined reads "All reviews are confirmed and a decision is needed."
Affects: A4 | Rule 5
Status: confirms
Apps: OJS
Proposed: ❓ stays · A4's basis is now live
Evidence: probe-P1.md item 3

### D7 — A1 holds live, but the panel's own Upload button survives: after the first upload on the resubmit path the bottom "Upload revisions" button is gone, while the Revisions Uploaded panel's header "Upload" still opens the wizard
Affects: A1 | Rule 9 | scenario 5 | Side effects
Status: corrects
Apps: OJS (OMP not probed for this)
Proposed: 🐞 stays · reword A1: the author is not locked out — the panel path still works — the defect is the inconsistent affordance plus nothing telling the author they are done; the "Resubmit for review." task also persists after the upload (the in-round path's task clears on upload, confirmed)
Evidence: probe-P2.md item 5 (contrast item 4)

### D8 — Both Request Revisions paths behave as specified: radio labels and default, distinct wizard names ("Request Revisions" / "Resubmit for Review"), author task and dashboard "Submit revisions" button, task cleared by the in-round upload
Affects: Rule 11 | Rule 5 | scenarios 3–5 | Side effects
Status: confirms
Apps: OJS
Proposed: plain claim
Evidence: probe-P2.md items 4–5

### D9 — The revised-version email fires the moment the file lands in wizard step 1, goes to the assigned editor once (a second completed upload sends nothing), and arrives From the author's own name and address
Affects: Side effects (first bullet) | footnote l
Status: corrects
Apps: OJS
Proposed: footnote · side effects (file row, status flip, email) trigger at the step-1 file transfer, not wizard completion; footnote l's sender line needs the observed From; throttle confirmed
Evidence: probe-P2.md items 4–5

### D10 — "Create New Review Round" works as specified, except the revised file arrives already ticked in the Select Files step, and the carried file is a copy (new file id) in Round 2
Affects: Rule 12 | scenario 6
Status: corrects
Apps: OJS (OMP not probed for this)
Proposed: plain claim · scenario 6 should not say "tick the revised file" — it is pre-selected; menu/round/status transitions all confirmed
Evidence: probe-P3.md item 6

### D11 — A past round loses only the decision buttons; its panel buttons (Upload, Upload/Select Files, Add Reviewer, Assign) still render
Affects: Rule 10 | scenario 6
Status: corrects
Apps: OJS
Proposed: plain claim · soften "read-only" to "no decision buttons"; whether the surviving panel buttons actually mutate a past round was not probed
Evidence: probe-P3.md item 6

### D12 — When cancelling is blocked, the "Cancel Review Round" button simply vanishes; the restriction explanation never renders anywhere
Affects: Rule 11 (second qualification) | scenario 8
Status: corrects
Apps: OJS and OMP (OMP observed the vanish explicitly, no text in its place)
Proposed: plain claim · drop the "otherwise the screen explains…" sentence; availability condition itself confirmed (present with no accepted/completed review, absent otherwise)
Evidence: probe-P3.md item 7a; probe-P7.md item 16a; probe-P1.md items 1–2

### D13 — Cancelling a round behaves as specified: the round leaves the menu, the submission lands on the previous round (or the Submission stage for Round 1), and the cancelled round's unanswered invitation vanishes from every reviewer-side list including "All assignments"
Affects: Rule 12 | scenario 7
Status: confirms
Apps: OJS
Proposed: plain claim · one refinement: the wizard's "Notify Reviewers" step appears only when the round has reviewers
Evidence: probe-P3.md items 7b–7c

### D14 — Cancelling Round 2 forgets Round 1's pending revision request: the restored Round 1 reports "Awaiting responses from reviewers." although a resubmit decision had been recorded on it
Affects: Rule 12 | new register entry
Status: new
Apps: OJS
Proposed: ❓ register entry · should the prior round's revision-request status survive a cancel? The recompute discards it silently
Evidence: probe-P3.md item 7b

### D15 — Unticking a file in "Current Review Files For Round {N}" changes nothing the editor can see, and a decision-carried file arrives in that dialog unticked while the panel lists it
Affects: Rule 8 | new register entry
Status: corrects
Apps: OJS (OMP not probed for this)
Proposed: ❓ register entry + soften Rule 8 · the checkbox state persists but the Files for Review panel renders the file identically either way; the observation that would settle the intent: whether a reviewer of the round sees an unticked file
Evidence: probe-P3.md item 8

### D16 — Decline, Revert Decline and Delete behave as specified: while declined, a Section Editor gets only "Revert Decline", a Journal Manager also "Delete"; revert restores the recomputed status and the full button set
Affects: Rule 11 (third qualification) | Actors row 8 | scenario 10
Status: confirms
Apps: OJS
Proposed: plain claim
Evidence: probe-P4.md item 9

### D17 — Recommend-only works as specified except precedence: the controls are three buttons ("Recommend Revisions / Recommend Accept / Recommend Decline"), the "Recommendation" box lists the bare label — but on a round with no reviewers the box reads "Waiting for reviewers to be assigned.", not "Awaiting recommendations from editors."
Affects: Rule 5 | Rule 6 | Rule 13 | scenario 11
Status: corrects
Apps: OJS (OMP not probed for this)
Proposed: plain claim · add to Rule 6 that the no-reviewers sentence outranks the recommendation sentences; scenario 11 needs at least one reviewer record on the round to show "Awaiting recommendations from editors." (a declined one suffices)
Evidence: probe-P4.md item 10

### D18 — With a minimum set and unmet, the confirmed-reviews state shows the minimum line and no status sentence at all
Affects: Settings (Minimum Confirmed Reviews Required)
Status: corrects
Apps: OJS (OMP not probed for this)
Proposed: plain claim · with minimum 2 and one confirmed review the box carries only "Minimum number of confirmed reviews required: 2." — the reviewer-derived sentence is omitted; settles footnote r's pending wording (the met-minimum wording remains unobserved live)
Evidence: probe-P4.md item 15

### D19 — The author's "Read Review" window on OJS renders no review text at all — the comment shared "for author and editor" is missing, and the decision letter's reviewer appendix also stops at the recommendation; on OMP the shared comment does render (editor-only text correctly absent in both)
Affects: Rule 15 | A3 | scenario 12
Status: corrects
Apps: per-app difference — OJS shows no text; OMP shows the shared comment only
Proposed: 🐞 register entry (OJS) · the window otherwise lists reviewer name, completion date, recommendation and a files grid as specified; Rule 15's "the review text the author is meant to see" does not hold on OJS
Evidence: probe-P5.md item 11; probe-P7.md items 16c–16d

### D20 — The attachment listing in the author's read-review window (A3's exact question) was observed on both apps and ROUTED to the maintainer's private security file
Affects: A3 | Rule 15 | scenario 12
Status: undetermined
Apps: OJS and OMP (both probes routed their observation)
Proposed: keep A3's wording generic until a fix ships; the spec and tests must not assert what the window lists; the public record carries only the routing declaration
Evidence: probe-P5.md item 11 (routing note); probe-P7.md item 16c

### D21 — With only an anonymous completed review, the author's view has no Reviewers panel at all — no empty list, no name anywhere on the page
Affects: Rule 15 | scenario 12 (control)
Status: confirms
Apps: OJS
Proposed: plain claim · refine: the author-side reviewers list mounts only when an open completed review exists, rather than listing nothing
Evidence: probe-P5.md item 11 (control)

### D22 — The "Notifications" list and its read-only letter behave as specified: subject + date rows; the opened letter has zero editable fields and no reply controls
Affects: Rule 16 | Actors row 7 | scenario 12
Status: confirms
Apps: OJS and OMP
Proposed: plain claim
Evidence: probe-P5.md item 12; probe-P7.md item 16d

### D23 — OMP collects no reviewer recommendation: the reviewer form has no recommendation field, the author's window shows no recommendation line, and the decision letter prints "Recommendation:" with an empty value
Affects: Rule 15 | scenario 12 | new OMP register entry
Status: new
Apps: OMP only (OJS shows the recommendation throughout)
Proposed: register entry (OMP) · qualify Rule 15's recommendation line as OJS; the empty "Recommendation:" label in the OMP letter is a 🐞 candidate
Evidence: probe-P7.md items 16c–16d

### D24 — Typing another submission's workflow address gets a role or access error with no submission data: unconnected author and assigned reviewer are turned away from the editorial dashboard; a foreign id on My Submissions opens only an "Error / The current role does not have access to this operation." dialog
Affects: Actors row 1 | footnote a (its "probe pending" line)
Status: confirms
Apps: OJS
Proposed: plain claim · closes footnote a's pending probe of what a refused visitor sees
Evidence: probe-P6.md item 13

### D25 — The legacy addresses behave as specified: "authorDashboard/submission" redirects to My Submissions with the workflow open; "reviewRoundInfo" answers HTTP 404 with and without an id
Affects: Rule 17 | footnote o (six legacy waivers)
Status: confirms
Apps: OJS
Proposed: plain claim · closes footnote o's typed-URL pending note; the waivers stand
Evidence: probe-P6.md item 14

### D26 — OMP's External Review round matches the OJS screen panel-for-panel and button-for-button, the Internal Review menu entry exists — but no Author Response panel renders in either view (silent absence, console clean)
Affects: OMP1 | Rule 3 | footnote q (its pending probe) | Purpose
Status: confirms
Apps: OMP (parity vs the OJS record)
Proposed: plain claim for parity and OMP1 · the Author Response absence is forwarded to *Author response to reviews* (footnote q already points there); note the OMP author still receives a letter inviting a response it has no panel for
Evidence: probe-P7.md items 16a–16b (addendum)

### D27 — OPS: a preprint's workflow menu holds exactly one entry, Production; no review surface exists anywhere, and the Production controls render as positive control
Affects: Purpose (OPS paragraph) | scenario 14 | footnote p
Status: confirms
Apps: OPS
Proposed: plain claim · closes footnote p's pending live probe
Evidence: probe-P7.md item 17

### D28 — On scratch journals, submitting assigns no editor even with a matching section editor seeded; every probe assigned participants by hand
Affects: footnote s (scenario seeding) | other feature
Status: new
Apps: OJS (two probes hit it; one did not)
Proposed: footnote · seeding recipes for the tests must include a manual Assign Participant step (or fixed auto-assignment); the underlying defect belongs to the submission-stage/editor-assignment feature — forward, not register here
Evidence: probe-P2.md fixture section; probe-P4.md fixture section (contrast probe-P5.md seeded state)
