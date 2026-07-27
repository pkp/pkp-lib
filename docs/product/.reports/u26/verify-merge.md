# U26 Review stage & rounds — verification merge (RUNBOOK step 8)

Deduplicated change list for `lib/pkp/docs/product/specs/review-stage-and-rounds.md`,
merged from `verify-chunk1..4.md` (authoritative for observations), the
observation sections of `tests-ojs.md` / `tests-omp.md` / `tests-ops.md`, and
`finalizer-report.md`'s low-confidence list. The fold agent owns the spec edits;
nothing here was applied.

Disposition classes: **text correction** · **register entry rewrite** ·
**new register entry** · **wording nit** · **❓ unresolved (lean stated)** ·
**belongs elsewhere**.

---

## A. Findings-register entries

**M1 — A8 `#a8` "Released and withheld review files look identical"**
Claims: "a file added through the select modal arrives not yet released to
reviewers", "unticking one withdraws it", "the only surface showing which files
reviewers actually receive is the select modal's checkbox column".
Observed: the checkbox writes the file's `viewable` flag; the Add Reviewer step's
"Files To Be Reviewed" list offers every file on the panel pre-ticked whatever
the flag, an assigned reviewer's page lists a `viewable = 0` file, and unticking
afterwards leaves that reviewer's files unchanged. The panel row's `outerHTML` is
byte-identical across a 0↔1 toggle (chunk4 d1b, chunk4 §b A8).
Disposition: **register entry rewrite** — symptom narrows to "the panel shows no
per-file state, and the modal's checkbox meaning is explained on neither
surface"; symptom, mechanism and "Expected" all rest on the release reading and
must be rewritten, not patched. Whether the narrowed entry is still 🐞 is a
maintainer call.

**M2 — A6 `#a6` "revisions panel offers Upload outside the working window"**
Claim: "While revisions are requested or submitted it completes; in any other
state the dialog opens with only a bare refusal notice".
Observed: the gate is the stage's latest round's decision history (ACCEPT /
PENDING_REVISIONS / NEW_EXTERNAL_ROUND / RESUBMIT), not the round's status —
"Waiting for reviewers to be assigned." refuses on a fresh round and completes on
a round returned from Copyediting; the post-accept upload completes on both apps
(chunk3 A6.2; chunk4 §b A6, which subsumes chunk3's narrower post-accept case —
the two do not conflict).
Disposition: **register entry rewrite** to chunk4's proposed symptom text; the
enabled-in-every-state half stands unchanged.

**M3 — A3 `#a3` "Recommendations outrank review trouble"**
Claims: "Once every recommend-only participant has recommended…" and "the overdue
signal returns only if the state changes".
Observed: masking begins with the **first** recommendation, under a different
string ("New editorial recommendations have been submitted."), with the reviewer
still "Overdue"; the overdue line does not return while any recommendation stands
(chunk2 Rule 7; chunk4 §b A3, OJS 304 / OMP 185 — same observation, same trigger).
Disposition: **register entry rewrite** to chunk4's proposed symptom; the two
Rule 6 rows already carry the ⚠ marker.

**M4 — A5 `#a5` "Round-status notices have no display surface" — basis line**
Claim: "Basis: code" (the register's only non-probe entry).
Observed: notice rows exist one per round with `assoc_type` 523, zero orphans and
zero rows for the three rounds cancelled in the run; five candidate display
surfaces checked, none renders them (chunk4 §b basis audit).
Disposition: **text correction** — basis code → probe; entry text unchanged.

**M5 — OMP4 `#omp4` "Presses ship without reviewer recommendation options"**
Claim: "A Press Manager can add options by hand in the review settings."
Observed: on a press, Settings → Workflow → Review offers Setup / Reviewer
Guidance / Review Forms only; a journal's same screen carries a fourth "Reviewer
Recommendations" tab, and the word "recommendation" appears nowhere on the
rendered press page (chunk4 §b OMP4).
Disposition: **register entry rewrite** — replacement sentence per chunk4; the
Lean is wrong in both halves and needs rewriting; the ❓/user-visible tier
understates a capability with no in-application path on a press (maintainer call).

**M6 — OMP4 `#omp4` two precision points**
Claims: "a reviewer's **completion step**" and "the author's read-review sheet
shows no recommendation line".
Observed: the recommendation control lives on the reviewer wizard's step 3
"Download & Review"; step 4 is the one literally named "Completion". And the
author-sheet absence is unconditional on OMP but conditional on OJS (absent
whenever the reviewer chose no recommendation) (chunk4 §b OMP4).
Disposition: **wording nit** on the step name; **text correction** on the
sheet sentence so it does not read as a flat app difference.

**M7 — OMP1 `#omp1` "Round-1 cancel lands by stage roster"**
Claim: "OJS, having no internal stage, always lands on Submission."
Observed: the entry's four landings all reproduce, but OJS round 2 cancels back to
round 1 inside Review — the quantifier scopes only to Round 1. Adjacent: the
cancel wizard's own description ("If this is the first review round, it will be
moved to the submission stage.") denies the OMP landing on both apps, and the
landing internal round displayed the stale "Submission accepted." stamp (chunk4
§b OMP1, §a Rule 15).
Disposition: **register entry rewrite** — add the Round-1 scope and the wizard-text
mismatch; chunk4 questions the ✅ "intended parameterization" verdict and the
"minor" tier on the strength of that mismatch — flag for the maintainer.

**M8 — NEW entry: a cancelled round's number is reissued**
Rule 1 claim: "Rounds are never renumbered or reused."
Observed: after cancelling Review Round 2, the next "Create New Review Round"
produces a second "Review Round 2" (new round record, same number) — OJS round ids
299/301, OMP 179/181; nothing on the side menu or in the headings signals that an
earlier Round 2 existed (chunk2 Rule 1).
Disposition: **new register entry**, lean 🐞/❓ (chunk2's call), paired with the
Rule 1 text change (M14).

**M9 — "Submission accepted." is stamped but never reaches a screen — ❓ UNRESOLVED**
Rule 6 table claim: "'Submission accepted.' | decision-set | after Accept
Submission — final for the round".
Observed, agreeing: Accept always advances the active stage past Review and from
that moment the round reads "The submission is currently in the Copyediting
stage."; the stamp is stored and serialized only (chunk2 Rule 6 ¶3; chunk3 X.1).
Observed, in tension: on OMP, after cancelling External Review's Round 1, the
Internal Review round the editor lands on **did** display "Submission accepted."
(chunk4 §b OMP1) — so "never displayed" is not absolute for the shared status
machinery, only for the round that an Accept ends.
The two chunks also split on weight: chunk2 proposed a register entry, chunk3
proposed rule text plus a footnote line and no entry.
Disposition: **❓ unresolved for the fold agent.** Lean: correct the When cell and
add the Rule 5 relocation sentence (M16), phrase the claim as "no external-review
round displays it" rather than "never", and leave the register-entry question to
the maintainer.

**M10 — A4 `#a4` footnote `f-a4` scope, and the storage half**
Claim: the footnote records four round shapes on OJS only.
Observed: all four shapes reproduce identically on OMP (186–189), and the stamp
survives a second return from Copyediting (chunk4 §a Rule 18, §b A4). Separately,
the stamp is not only outranked at display time — later reviewer/file/decision
activity overwrites it in storage (chunk2 Rule 6 context).
Disposition: **text correction** to footnotes `f-a4` and `j`; entry text unchanged.

**M11 — finalizer low-confidence list, item 1: two statuses now observed**
Claim (report, not spec): "New editorial recommendations have been submitted." and
"All reviews are confirmed and a decision is needed." rest on shared-code derivation.
Observed: both live on both apps — the partial-recommenders line on chunk2 case 258
and chunk4 OJS 304 / OMP 185, the all-confirmed line on chunk2 case 251.
Disposition: **text correction** — footnote `j`'s evidence line drops the
code-derivation caveat; low-confidence item retired.

---

## B. Actors & permissions rows

**M12 — Row "Curate 'Files for Review'"**
Claim: "Journal Manager, Journal Editor, Section Editor, Guest Editor,
assistant-role participants (Copyeditor, Layout Editor, Proofreader…) — as stage
participants • Site Administrator — without assignment".
Observed: a Journal Manager cannot be added as a Review-stage participant at all
(not offered in "Assign Participant"); Journal Manager, Journal Editor and Site
Administrator all reach the panel and its controls unassigned; of the default
assistant groups only Funding coordinator reaches the Review stage — Copyeditor,
Layout Editor and Proofreader are refused on screen and on the server, both apps
(chunk1 item 2).
Disposition: **text correction** — chunk1's proposed two-bullet split.

**M13 — Row "Delete the submission from the Review stage" (screen side)**
Claim: "Journal Manager, Site Administrator — only while the submission stands
declined".
Observed: the "Delete" control is also shown to the seeded **Journal editor**
group (OMP: **Press editor**), which holds the same MANAGER role slot; absent for
Section/Series editor participants; absent for every role while the submission is
active; label exactly "Delete" (chunk1 item 9).
Disposition: **text correction** — add Journal Editor / Press Editor to the row's
role list; the declined-state qualifier stands.

**M14 — Row "Edit / remove a revision file" — on-screen labels**
Claim: "Edit / remove a revision file".
Observed: the row actions are "Update File Details" and "Delete"; the editorial
roles additionally get "More Information" (the notes action), which the Author
does not (chunk1 item 4).
Disposition: **wording nit** — name the labels, or note the editorial-only extra.

**M15 — Actors preamble, definition of *participant***
Claim: "**participant** — a user listed for this submission on the Review stage".
Observed: a user who is a submission participant under a group whose stages
exclude Review (e.g. Copyeditor) opens the workflow with the round selected but
the column reads only "You don't currently have access to that stage of the
workflow." — no panels, both apps (chunk1 item 1).
Disposition: **wording nit** — the definition already excludes this user; make it
harder to read as "participant on the submission".

**M16 — Actors table, app coverage of the named groups**
Claim: the rows are unmarked, i.e. asserted identical in OJS and OMP.
Observed: OMP ships no Guest editor group at all, so "Guest Editor" has no OMP
counterpart; the Review-stage assign dialog also offers Translator (both apps) and
Volume editor (OMP), whose capabilities were not probed (chunk1 item 2 context).
Disposition: **wording nit** — a preamble line or app marker; leave the unprobed
groups unnamed.

---

## C. Rules & state

**M17 — Rule 1, round numbering**
Claim: "Rounds are never renumbered or reused."
Observed: see M8 — a cancelled round releases its number and the next round takes
it again, both apps (chunk2 Rule 1).
Disposition: **text correction** — chunk2's suggested sentence, marked ⚠ against
the new entry from M8.

**M18 — Rule 4, author panel roster order**
Claim: "the round status, a 'Notifications' list…, 'Revisions Uploaded', a
'Reviewers' panel…".
Observed: the live order on both apps is round status → Notifications →
**Reviewers** → **Revisions Uploaded** → Review Tasks & Discussions → Author
Response (chunk2 Rule 4; chunk4 §b OMP5 roster note — same transposition).
Disposition: **text correction** — swap the two names, or say the list is a set
and not an order.

**M19 — Rule 5, the past-round text is stage-conditional**
Claim: on an earlier round "the status area is headed plain 'Status' and reads
'The submission has been advanced to the next round of review'".
Observed: that text holds only while Review is the active stage. Once the
submission has moved on, the latest round reads "The submission is currently in
the Copyediting stage." and an earlier round reads "The submission advanced to the
next review round, was accepted, and is currently in the Copyediting stage." —
neither offering decision buttons (chunk2 Rule 5; chunk3 X.1 — identical strings,
no conflict).
Disposition: **text correction** — add both lines. ❓ note for the fold agent: the
earlier-round sentence asserts "was accepted" whichever way the submission left
the stage; the decline-then-move path was not driven.

**M20 — Rule 5, the past-round control inventory**
Claim: "their panel-level controls (upload, select files, add reviewer,
discussions) remain offered".
Observed: also true of the reviewer rows' own row actions — "Thank Reviewer" and
"Revert Decision" were both offered on a past round, both apps (chunk4 §a Rule 17).
Disposition: **text correction** if the list is meant to be exhaustive; otherwise
**wording nit**.

**M21 — Rule 6 table, two "When" cells (rows 1 and 5)**
Claims: "Waiting for reviewers to be assigned. | no active reviewer in the round"
and "All reviews are confirmed and a decision is needed. | every active review
confirmed; nothing else pending".
Observed: the waiting line shows only when the round has **no review assignment at
all**; a round whose only reviewer declined the invitation — or whose only
assignment was cancelled — reads "All reviews are confirmed and a decision is
needed." with zero confirmed reviews. The all-confirmed line is the round's
fall-through (chunk2 Rule 6 rows 5–7; chunk4 incidental finding — same behavior,
same mechanism, reconciled).
Disposition: **text correction** — chunk4's proposed row wording; consider a ⚠ on
the all-confirmed row.

**M22 — Rule 6 table, "Awaiting recommendations from editors."**
Claim: "recommend-only participants assigned, none has recommended, reviews done".
Observed: a round with a recommend-only participant and no reviewer ever assigned
reads "Waiting for reviewers to be assigned." instead; the awaiting-recommendations
line needs at least one review assignment with none outstanding (chunk2 Rule 6
row 9, Rule 7 collision table).
Disposition: **text correction** — chunk2's suggested When cell.

**M23 — Rule 7, precedence order**
Claim: "no reviewers → overdue → unread reviews → reviews under way →
recommendation states → all-confirmed".
Observed: two of the three recommendation states ("some, not all" and "all in") are
evaluated **before** any reviewer state and outrank overdue, unread and in-flight
reviews; only "Awaiting recommendations from editors." sits where the rule puts it
(chunk2 Rule 7; corroborated by chunk4 §b A3).
Disposition: **text correction** — chunk2's suggested order, ⚠ against A3.

**M24 — Rule 9, minimum confirmed reviews**
Claim: "Once N reviews are confirmed the round reads 'Minimum required number of
reviews have been confirmed. A decision is needed.'"
Observed: that line replaces the ordinary status only while the round's status is
a reviewer-activity one; a recommendation state or a decision-set state shows
instead, with the minimum met. Also: the count is per **round**, not per
submission (a new round restarts it), and a past round shows no prompt line at all
(chunk2 Rule 9).
Disposition: **text correction** — bound the sentence, add both scope facts (the
per-round point may belong in the Settings bullet).

**M25 — Rule 10, which bullets are actually conditional**
Claim: each button is "shown only when the server currently offers that decision".
Observed: across every active latest round driven, Request Revisions / Accept
Submission / Create New Review Round / Decline Submission were always present;
only Cancel Review Round varied. A round already holding the author's submitted
revisions can still be cancelled (chunk3 10.7, 10.8).
Disposition: **wording nit** — say plainly that four of the five are offered on
every active latest round and only Cancel is conditional.

**M26 — Rule 11, the label the restatement uses**
Claim: "after recording, that area restates their recommendation under a
'Recommendation' heading" (scenario 8: "restates it").
Observed: the restatement and the side-column box both use the matching
**decision's** label — pressing "Recommend Revisions" yields "Request Revisions",
"Recommend Accept" yields "Accept Submission" — on both apps (chunk4 d2;
tests-ojs obs 2; tests-omp obs 3 — three independent sightings, no conflict).
Disposition: **text correction** to Rule 11 and to scenario 8.

**M27 — Rule 11, the side-column box's shape**
Claim: "a 'Recommendation' box in the side column listing every recommendation
recorded for the stage".
Observed: it lists them as a single comma-joined line of decision labels —
"Accept Submission, Decline Submission" — with no recommender name and no date, so
a deciding editor cannot tell who recommended which (chunk3 11.5; chunk4 d2).
Disposition: **text correction** — chunk4's proposed sentence.

**M28 — Rule 13 and the Settings bullet, "presses ship with none"**
Claims: Rule 13 "presses ship with none ⚠ OMP4"; Settings bullet "journals ship
with defaults, presses with none".
Observed: a press has no recommendation options **and** no screen on which to
create any (M5), so "ship with none" understates the state on both surfaces
(chunk4 §b OMP4 cascade).
Disposition: **text correction** — both sentences follow OMP4's rewrite.

**M29 — Rule 13, the review-form variant**
Claim: "the review text the reviewer shared with authors (**or the author-visible
parts of the review form**)".
Observed: not exercised — the live scenario schema carries no per-reviewer
`reviewForm` key, so a form-based completed review cannot be seeded without UI
work (chunk3 13.5).
Disposition: **❓ unresolved** — lean: leave the clause as written and record it as
unverified rather than dropping it; everything else in Rule 13 re-observed exactly
on both apps.

**M30 — Rule 14, scope of the "Notifications" list**
Claims: "lists each message the editorial team sent the author about this
submission — subject and date"; the rule sits inside "The author's round".
Observed: the listing is filtered to decision notification emails (discussion and
other editorial mail never appear) and is **submission-wide**, not round-scoped —
the same entry shows on every round of the stage; rows carry a date *and* time
(chunk3 14.4, 14.5, 14.6).
Disposition: **text correction** on both sentences; the date/time point is a
**wording nit**.

**M31 — Rule 17, what the select modal's checkbox does**
Claim: "the copy arrives not yet released to reviewers, and unticking a review
file withdraws it from reviewers without deleting anything".
Observed: every file that reaches "Files for Review" by any route — the entry
decision's promoted files, a new round's carried-over revisions, a select-modal
copy — arrives unticked; and the flag does not gate reviewer access at all (M1).
Reviewer access is settled when the reviewer is added (chunk4 d1, d1b; tests-ojs
obs 1 reported the same unreleased-starting-state half).
Disposition: **text correction** — chunk4 d1b's proposed replacement sentence.
Retires finalizer low-confidence item 3.

**M32 — Rule 17, duplicate copies**
Claim: "Ticking a file from another stage copies it into the round's review files".
Observed: the all-stages listing re-offers a file that has already been copied into
the round; ticking it again produces a second, independent copy (chunk4 §c
scenario 11 observation).
Disposition: **wording nit** — optional sentence; Rule 17 gives no warning.

**M33 — Cross-feature interactions, U27 row**
Claim: "U27 Reviewer assignment & management — everything inside the Reviewers
panel; round status consumes reviewer states (Rule 6)."
Observed: the Add Reviewer step's "Files To Be Reviewed" list is what decides which
of the round's files a reviewer receives (chunk4 d1b).
Disposition: **text correction** — add "…and which of the round's files each
reviewer receives."

**M34 — Rule 2, "The latest round is preselected"**
Claim: as written, unqualified.
Observed: true when the Review stage is what opens; once the submission has moved
on, the workflow opens on the active stage and a round is shown only after the
reader picks it from the side menu (stage selection is U24's) (chunk2 Rule 2).
Disposition: **wording nit**.

---

## D. Scenarios, footnotes, wording nits

**M35 — Scenario 9 "Cancel a fresh round"**
Claims: "confirm through the wizard"; "(cancelling Round 1 lands on the Submission
stage instead [OMP1])"; "After a reviewer accepts — or declines — the invitation,
the button is no longer offered."
Observed: the commit control is "Record Decision" (no "Confirm" exists); on a press
with internal rounds Round-1 cancel lands on Internal Review, so the parenthetical
states the OJS outcome as the outcome; and by that last sentence the round the
reader cancelled no longer exists, so the step needs its own round (chunk4 §c
scenario 9 and d3; tests-omp obs 2 flagged the same parenthetical).
Disposition: **text correction** — chunk4's three proposed rewrites.

**M36 — Scenario 11 "Curating Files for Review mid-round"**
Claims: "Tick one and save" (×3); "i.e. not yet released to reviewers"; "tick it
and save to release it"; "it is withdrawn from reviewers".
Observed: the modal's commit button is "OK"; the release/withdrawal meaning does
not survive (M1/M31); and the final step needs a round that already holds a review
file, which a scenario-API seed never produces (chunk4 §c scenario 11).
Disposition: **text correction** — label fix, drop the three release phrases, add
the "opened through the real send-to-review decision" precondition.

**M37 — Scenario 12 "{OMP} Two doors into External Review"**
Claims: "every common scenario above runs identically from there"; "opens with an
empty 'Files for Review'"; "offers the submission files pre-ticked".
Observed: contradicted inside the document by OMP4 and OMP5; the panel prints "No
Items" and the wizard step "No items found." (two unquoted strings); a
scenario-API monograph carries exactly one submission file; and the Internal
Review door needs a prior "Send to Internal Review" decision that neither the
scenario nor footnote `s1` mentions (chunk4 §c scenario 12).
Disposition: **text correction** — chunk4's proposed clauses.

**M38 — Scenario 13 "{OPS} Absence check", the positive control**
Claim: "Positive control: the same walk on a journal shows the Review stage."
Observed: not executable from a per-app suite — an OPS suite drives one fleet; the
in-app controls are the menu/decision-roster ones the OPS test carries (tests-ops
§3 obs 2).
Disposition: **text correction** — tests-ops' proposed wording, so a future test
author does not read it as an instruction to cross fleets.

**M39 — Footnote `w`, "the only actions offered"**
Claim: "the only actions offered are 'Post the preprint' and 'Decline Submission'".
Observed: true of the decision area only; the OPS workflow header also carries
Preview, Activity Log and Library, the Production Tasks & Discussions panel has its
own "Add", the side menu carries "Create New Version", and the Participants column
offers "Assign"; as the author the header carries Library only (chunk4 d4;
tests-ops §3 obs 1 — same finding, same scoping).
Disposition: **text correction** — "the only **decisions** offered…", with the
header/panel parenthetical.

**M40 — Footnote `f-omp2`, label order and the OJS label**
Claims: the OMP Submission-stage labels in the order "Send to External Review, Send
to Internal Review, Accept and Skip Review, Decline Submission"; and "OJS has a
single send-to-review door" with no label given.
Observed: the live visual order is Send to External Review, Accept and Skip Review,
Decline Submission, **Send to Internal Review** (the internal door renders last);
OJS's label is exactly "Send for Review" (chunk4 §b OMP2; chunk1 item 12).
Disposition: **text correction**.

**M41 — Footnotes `q` and `f-a8`, reviewer access mechanism**
Claim: "deselection flips the file's `viewable` flag only" / "the release state is
the file's `viewable` flag, surfaced only as the select modal's checkbox".
Observed: reviewer access is the per-assignment `review_files` grant
(`SubmissionFileAssignedReviewerAccessPolicy` → `ReviewFilesDAO::check()`) written
by the Add Reviewer step and unaffected by the flag (chunk4 d1b).
Disposition: **text correction** — chunk4's proposed footnote wording; pairs with
M1 and M31.

**M42 — Footnotes `c` and `f-a6`, the add gate**
Claim: both cite `SubmissionFileRequestedRevisionRequiredPolicy` as the server gate
on the author's panel upload.
Observed: that policy governs edit/delete of an existing revision file; the **add**
gate is `SubmissionFileStageAccessPolicy`, evaluated against the stage's latest
round's decisions (chunk3 A6 mechanism; chunk4 §b A6, with file/line).
Disposition: **text correction** — pairs with M2.

**M43 — Footnote `f-omp3`, coverage of the entry's first clause**
Claim: the internal door "offers to carry only files the Author uploaded as
internal-review revisions".
Observed: OMP3 re-observed in full on the hardest case, but only in its negative
half — the positive case (internal revisions present → offered) was not driven
(chunk4 §b OMP3).
Disposition: **wording nit** — a basis line noting the untested half; entry
unchanged.

---

## E. Belongs elsewhere

**M44 — Recommend-only controls inert after a Participants-panel flag flip → U35**
One chunk-4 run reported OMP recommend-only buttons rendering but doing nothing,
with `availableEditorialDecisions` empty; it did not reproduce when the flag was
applied at assignment time by the scenario API. The reproduction difference is the
**participant-edit path**, not the round screen (chunk4 "Unresolved: OMP
recommend-only buttons").
Disposition: **belongs to U35 (Stage participants)** — needs its own probe there
before anyone states it as a defect. Nothing in U26's text is affected: Rule 11's
display claims verified on both apps.

**M45 — `##common.help##` → shared side-modal chrome, not this spec — CONFLICT NOTED**
tests-omp obs 4 recorded it as an OMP defect of the legacy "Current Review Files
For Round N" modal, adding "(OJS renders it fine)". chunk4 d5 measured the same
string on **all three apps** and on every side modal and page top bar: it is the
screen-reader-only accessible name of the shared Help icon
(`TopNavActions.vue` in `SideModalBody`), absent from every `.po`; the curation
modal's own body holds no placeholder.
Disposition: **belongs elsewhere (shared chrome / UNASSIGNED)** — no U26 register
entry; optional footnote noting the modal inherits the shared top bar. The two
reports conflict on the OJS half; chunk4's is the direct measurement, but the
call is flagged rather than made here. Either way the disposition for this spec is
unchanged.

**M46 — OMP decision email invites an author response → U30 / mail templates**
The OMP "Request Revisions" author email ends "You can access the reviews and
submit your response below / Submit Author Resp…", although OMP mounts no "Author
Response" panel (OMP5) (chunk3 14.7).
Disposition: **belongs to U30 (Author response to reviews) / the mail templates** —
no U26 text change.

**M47 — OJS send-to-review label → APP-GLOSSARY**
The finalizer flagged the glossary's OJS cell ("Send to Review") as needing a live
read. Observed live: the OJS Submission-stage decision is labelled exactly **"Send
for Review"**; OMP's is "Send to External Review" (chunk1 item 12).
Disposition: **belongs to `APP-GLOSSARY.md`** — settles the finalizer's open
glossary correction; not a spec edit.

---

## F. Removal directives

- verify-chunk1.md → item 10: dropped per security routing
- verify-chunk1.md → item 13: dropped per security routing
