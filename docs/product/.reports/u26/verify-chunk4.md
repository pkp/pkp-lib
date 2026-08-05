# U26 — adversarial verification, chunk 4

Feature: `lib/pkp/docs/product/specs/review-stage-and-rounds.md`
Date: 2026-07-27 · fleets on 127.0.0.1 (OJS 8000, OMP 8100, OPS 8200) ·
Postgres `ojs_test` / `omp_test` / `ops_test`.
Scope: (a) Rules 15–18 attack states · (b) register-entry symptom accuracy
(A1–A8, OMP1–OMP5) · (c) scenarios 9, 11, 12 literal walk · (d) five queued
review observations.

All mutations were on scratch submissions seeded through the scenario API
(tags `c4*`); no `publicknowledge` journal/press setting, user, or seeded
submission was altered; Mailpit was never cleared.

Verdict key: **HOLDS** = the spec sentence is re-observable exactly as
written. **DISAGREES** = it is not. **CLAIM-VS-CONTEXT** = the sentence is
true of the case it was written from but the spec presents it more generally
than the screen supports.

Scratch objects driven by this chunk (`publicknowledge` on each fleet):
OJS 238/239/240 (mixed and unanswered rosters over two rounds), 241 (three
author revisions → new round → cancel), 242 (past-round curation), 243/244
(double return from copyediting, with and without a reviewer), 246/314
(one and two recommend-only participants), 295 (round 1 opened through the
real "Send for Review", used for scenario 11 and the release probe),
316/317/318 (scenario 9's three reviewer states); OMP 132/133/134, 137,
140, 171; OPS 15. Standard locators throughout: workflow side modal
`[data-cy="active-modal"]`, decision area `[data-cy="workflow-action-items"]`,
side column `[data-cy="workflow-secondary-items"]`, stacked modals
`[role="dialog"]` `.last()`, curation rows `input[name="selectedFiles[]"]`.

---

## Summary

**Held** — Rules 15, 16, 17, 18 under every attack state driven (mixed reviewer
roster, multi-revision carry-over, curation on a past round, double return from
copyediting); register entries A1, A2, A4, A5, A7, OMP1, OMP2, OMP3, OMP5;
scenario 9's behavior and scenario 11's outcomes. A5's basis moves from "code"
to "probe".

**Disagreed** (13):

1. Rule 17 / A8 / footnotes q, f-a8 — the select modal's checkbox is described
   as releasing and withdrawing files from reviewers; it does neither (d1b).
2. Rule 17 — "the copy arrives not yet released" is written as specific to the
   select modal; every promoted file starts in the same state (d1).
3. **A6** — "in any other state the dialog … is refused" mis-names the
   condition: the gate is the round's decision history, not its status, and the
   same status line completes on one round and is refused on another.
4. **A3** — masking starts at the *first* recommendation ("New editorial
   recommendations have been submitted."), not once all have recommended; and
   "the overdue signal returns only if the state changes" is not re-observable.
5. Rule 11 (twice) — the "Recommendation" restatement uses the decision's label,
   not the control's; and the side-column box carries no attribution (d2).
6. Rule 6 — two status-table "When" cells mis-state their conditions (a round
   whose only reviewer declined reads "All reviews are confirmed…").
7. Footnote w — "the only actions offered" is true of the decision area only;
   the OPS workflow header also carries Preview / Activity Log / Library (d4).
8. Scenario 9 — the Round-1 landing parenthetical is journal-first and wrong on
   a press (d3); plus "confirm through the wizard" (control is "Record
   Decision") and a final step that has no round left to run on.
9. Scenario 11 — "save" three times where the control is "OK"; and a missing
   precondition that makes its final step unreachable from the documented seed.
10. Scenario 12 — "every common scenario above runs identically from there"
    is contradicted by OMP4 and OMP5; plus two unquoted strings and a missing
    precondition.
11. OMP4 — "A Press Manager can add options by hand in the review settings" is
    false; a press has no such screen, so the Lean and the impact tier both need
    rewriting, and two other spec sentences inherit the error.
12. OMP1 — the cancel wizard's own description misdescribes the OMP landing;
    and "always lands on Submission" needs a Round-1 scope.
13. Rule 4 — the author panel roster's order is transposed against the screen.

**Footnote additions proposed** (not disagreements): `f-a4` records OJS only,
but all four round shapes reproduce on OMP.

**Confirmed as reported** — all five queued observations (d1, d2, d3, d4, d5),
with d5 relocated: `##common.help##` is shared side-modal chrome present on
every page of all three apps, not a defect of the file-curation modal.

---

## (a) Rules 15–18 — attack states

### Rule 15 — cancel round · cancel with a mixed reviewer roster · **HOLDS**

Cases driven (all as `editor.diana`, a Journal editor / Press editor
participant, i.e. a deciding editor):

| case | round shape | "Cancel Review Round" offered? |
|---|---|---|
| OJS 240 / OMP 134 | round 1: one **accepted** + one **invited** | **no** |
| OJS 239 / OMP 133 | round 2: one **accepted** + one **invited** | **no** |
| OJS 238 / OMP 132 | round 2: two **invited** | yes |
| OJS 316 | round 1: one **invited** | yes |
| OJS 317 | round 1: one **accepted** | no |
| OJS 318 | round 1: one **declined** | no |
| OJS 244 | round 1: **no reviewers** | yes |

Locator: `[data-cy="workflow-action-items"]` innerText on the workflow side
modal (`[data-cy="active-modal"]`).

Rule 15's cascade re-observed on OJS 238 (round 2, two invited reviewers),
driven through the real wizard to `Record Decision`:

- wizard steps `Cancel Review Round: Notify Authors` → `Cancel Review Round:
  Notify Reviewers`; the reviewer step's recipient field carried **both**
  reviewers (`Julia Reviewer`, `Paul Reviewer`), one message; author subject
  "A review round for your submission has been cancelled", reviewer subject
  "Request for Review Cancelled";
- after recording, "Review Round 2" was gone from the side menu, the column
  headed "WORKFLOW: REVIEW (ROUND 1)", and round 1's own reviewer and status
  were intact ("All reviews are confirmed and a decision is needed.").

Mail, scoped by tag `c4c15alltoxnojs` (Mailpit search, never cleared): exactly
three messages — "A review round for your submission has been cancelled" to
`author.alex@example.org`, and **one "Request for Review Cancelled" per
reviewer**, delivered separately to `reviewer.julia@example.org` and
`reviewer.paul@example.org`. The wizard composes a single reviewer message
addressed to both; delivery is per recipient. Rule 15's "Authors and affected
reviewers are notified by email" **HOLDS**.

Reviewer unassignment re-observed on the same case: after the cancel, both
round-2 assignments are gone from `review_assignments` (only round 1's own
assignment survives), and `reviewer.julia`'s request for
`/reviewer/submission/238` ends on `authorizationDenied?message=user.authorization.submissionReviewer`.
Positive control from the same live session: her request for
`/reviewer/submission/295` (an active assignment) rendered the review page.

Round-1 cancel (OJS 316, one invited reviewer) landed on
"WORKFLOW: SUBMISSION"; the "Review" side-menu stage entry remains but carries
no round entry.

**File persistence re-observed with real files** (OJS 241, round 2 carrying
three review files 372/373/374, no reviewers): after the cancel the three rows
survive in `submission_files` but their `review_round_files` links are gone,
and the surviving round 1's "Upload/Select Files" modal — *with* "Show files
from all accessible workflow stages." ticked — offers only 313 (submission)
and 360/362/363 (round 1 revisions). Rule 15's "not deleted, though no
editorial screen lists them once the round is gone" **HOLDS**.

Reviewer-less round: the wizard collapses to a single step (page title plain
"Cancel Review Round", no `: Notify Reviewers`), matching Rule 15's parenthetical.

**Screen vs server on the mixed roster.** With one reviewer accepted the
screen withholds the button *and* the server withholds the decision: as the
same deciding editor, `GET /decision/record/240?decision=31&reviewRoundId=266`
returned **404 Not Found**. Positive control from the same session, same URL
shape, on an entitled round (`…/record/244?decision=31&reviewRoundId=271`)
rendered the wizard. No divergence.

**Adjacent wording note (not a Rule 15 disagreement).** The cancel wizard's
own description, identical on both apps, reads: "Cancel the current round of
review and send the submission back to the last round of review. If this is
the first review round, it will be moved to the submission stage." Rule 15
and OMP1 both describe an OMP landing (Internal Review) that this sentence
denies. Screen-vs-server-vs-doc mismatch in the ordinary direction; see the
scenario-9 item under (d) and the merged OMP1 material.

### Rule 16 — new-round carry-over with several revisions · **HOLDS**

Case: OJS 241 (`resubmit` recorded), author `author.alex` uploaded **three**
revision files (`revA.pdf`, `revB.pdf`, `revC.pdf`) through the "Revisions
Uploaded" panel's own "Upload" wizard ("Upload Review File", component
"Article Text"). Round status then read "Revisions submitted. A new review
round needs to be created."; the author's action area was empty (A1's state).

"Create New Review Round" wizard, step 2 "New Review Round: Select Files"
(heading "Select Files", helper "Select files that should be sent for
review.", list label **"Revisions"**):

```
CB 363 revC.pdf  checked: true
CB 362 revB.pdf  checked: true
CB 360 revA.pdf  checked: true
```

All three preselected — Rule 16's "the previous round's uploaded revisions,
preselected" **HOLDS** for a multi-file roster, not just the single-file case.

After recording: round 2 opened at "Waiting for reviewers to be assigned." with
three **copies** in its "Files for Review" (372/373/374, each
`source_submission_file_id` pointing at 360/362/363), while 360/362/363 stayed
in round 1's "Revisions Uploaded". Rule 16's "copies the file's listing — the
original stays" **HOLDS**.

Repeated on OMP 135 (monograph, three author revisions uploaded the same way):
the "New Review Round: Select Files" step listed all three under "Revisions",
all three checked; recording produced copies 246/247/248 on external round 2
with the originals 243/244/245 left on round 1. Rule 16 is identical on both
apps, as its unmarked status asserts.

### Rule 17 — files-for-review curation on a past round · **HOLDS**

Case: OJS 242 (two rounds; round 2 current). Opened "Review Round 1" from the
side menu — the round reads plain "Status" / "The submission has been advanced
to the next round of review", the decision area is absent (Rule 5), and
"Upload/Select Files" is still offered on "Files for Review".

- modal title parameterizes to the **selected** round: "Current Review Files
  For Round 1" (not the current round's number);
- default listing = the selected round's review files (group "Review", "No
  Items" for this round);
- ticking "Show files from all accessible workflow stages." exposed
  Submission / Review / Copyediting / Production groups;
- ticking submission file 314 and pressing OK created copy **383**, written to
  `review_round_files.review_round_id = 268`, i.e. **round 1** — the past round,
  not the current one; the round-2 panel was unaffected;
- 383 arrived `viewable = NULL` and is listed **unticked** on reopening the
  modal.

So Rule 17's mechanics apply unchanged on a past round, and Rule 5's "their
panel-level controls … remain offered" is not merely decorative — the control
writes to the round the reader has selected.

OMP 136 behaves identically on its past external round: heading "WORKFLOW:
EXTERNAL REVIEW (ROUND 1)", plain "Status" / "The submission has been advanced
to the next round of review", no decision area, modal titled "Current Review
Files For Round 1". Note the all-stages group list is the app's own stage
roster — OJS shows Submission / Review / Copyediting / Production, OMP shows
Submission / **Internal Review** / **External Review** / Copyediting /
Production. Rule 17 does not name the groups, so nothing to correct; a test
author should not hard-code them.

Rule 5's full past-round control set re-observed on OJS 242 and OMP 136
(both on "Review Round 1" with a later round current): headings "Status" /
"Revisions Uploaded" / "Files for Review" / "Reviewers" / "Author Response"
(OJS only, per OMP5) / "Review Tasks & Discussions"; decision area absent;
panel buttons `Upload` · `Upload/Select Files` · `Add Reviewer` · `Request
Response` (OJS) · `Add`. Rule 5 does not mention that the past round's
reviewer rows also keep their own row actions — `Thank Reviewer` and `Revert
Decision` were both offered on both apps. Worth adding to Rule 5's list if the
list is meant to be exhaustive.

### Rule 18 — double return from copyediting · **HOLDS**

Cases, each driven twice through Copyediting → "Move to Review" → "Accept
Submission" → "Move to Review":

| case | round shape | pass 1 status | pass 2 status | rounds after |
|---|---|---|---|---|
| OJS 243 | round 1, one confirmed review | "Returned back to review." | "Returned back to review." | one (round 1, stored status 16) |
| OJS 244 | round 1, **no** reviewers | "Waiting for reviewers to be assigned." | "Waiting for reviewers to be assigned." | one (round 1, stored status 16) |
| OMP 137 | external round 1, one confirmed review | "Returned back to review." | "Returned back to review." | one (round 1, stored status 16) |

No new round is created on either pass, no files are carried (the "Move to
Review" wizard is a single step with no file selection), and the stamp is
re-applied each time. Rule 18 and A4's two documented shapes both survive a
second return.

Decision-button label on the Copyediting stage is exactly **"Move to Review"**,
alongside "Send To Production" — matching Rule 18's naming.

---

## (b) Register-entry symptom accuracy

**Basis audit.** Every entry states "Basis: probe" except **A5**, which states
"Basis: code" — the one entry resting on a reading rather than an observation.
Its record half is now a live observation from this chunk (the display half is
covered in the A1–A8 section):

> On `ojs_test`, `notifications` rows of type `0x1000014` (16777236) exist one
> per review round, `assoc_type = 523` (review round), `assoc_id` = the round
> id, `user_id` NULL. Across the whole database there are **zero** such rows
> whose `assoc_id` no longer resolves to a `review_rounds` row, and **zero**
> rows for the three rounds this chunk cancelled. So "every new round writes a
> round-status notice record, and cancelling the round deletes it" is
> re-observable, not merely code-derived.

### A1–A8 (all-apps entries)

Cases: OJS 241 / OMP 135 (resubmit round, three author revisions), OJS 323 /
OMP 190 (stay-in-round revisions), OJS 324 / OMP 191 (one submitted,
unconfirmed review), OJS 325 / OMP 192 (fresh round, then a UI-recorded
"Request Revisions" so a real author email exists), OJS 243/244 (returns from
copyediting), OJS 295 (release probe).

**A1 — "Author's 'Upload revisions' button disappears after the first upload of
a resubmit round" · HOLDS, both apps.** Resubmit flow: before upload the
author's action area reads "Upload revisions"; after the first file lands
(status "Revisions submitted. A new review round needs to be created.") the
action area is empty — observed on OJS 241 and OMP 135. Stay-in-round contrast
on OJS 323 / OMP 190: status goes "Revisions have been requested." → "Revisions
have been submitted and a decision is needed." and the button is **still
offered** after the upload. The entry's "as it does in the stay-in-round flow"
comparison is exact. Its second sentence also holds: further files went up
through the "Revisions Uploaded" panel's own "Upload" in that state (two more
were uploaded that way on each app).

**A2 — "Authors read the editor-phrased round status" · HOLDS, both apps.** On
OJS 324 / OMP 191 (one submitted, unconfirmed review) the editor's line and the
author's line are byte-identical: "New reviews have been submitted." The
overdue half re-observed too: on OJS 327 with the reviewer's due dates pushed
into the past, both roles read "A review is overdue." verbatim. The
author's panel roster in that state is "Round 1 Status" / "Revisions Uploaded" /
"Review Tasks & Discussions" — no "Reviewers" panel, consistent with Rule 13
(the review is anonymous by context default and not completed-open).

**A5 — "Round-status notices have no display surface" · HOLDS; basis should
move from "code" to "probe".** The record half is in the Basis audit above
(rows also confirmed for rounds created by `newExternalReviewRound`, and
cancelling a round removed exactly its own row while leaving round 1's).
Display surfaces checked, none of which renders the notice:

- `GET /api/v1/notifications` → **404 `api.404.endpointNotFound`** — no such
  REST endpoint;
- the header Tasks bell (editor and author, both apps) — contents are
  submission discussions, not `notifications` rows;
- the workflow review stage itself — a network capture of every request
  matching `/notification/i` during load returns three static `.js` assets and
  **no `notification/fetchNotification` call**; `WorkflowNotificationDisplay.vue`
  returns `null` from `getRequestOptionsPerStage()` for anything but Editing
  and Production;
- the retired panel `/authorDashboard/reviewRoundInfo/{id}` as the submitting
  author → **404** (control: `/authorDashboard/submission/{id}` → 200);
- the notice's own title string `notification.type.roundStatusTitle`
  ("Round {$round} Status") appears on no screen — the review panel's heading
  is a different string rendered from the round object.

**A6 — first half HOLDS, second half DISAGREES.**

*Holds.* OJS 325 / OMP 192 at "Waiting for reviewers to be assigned.": the
author's action area is empty, yet the "Revisions Uploaded" panel's "Upload"
button is present, visible and **enabled**. Clicking it opens a dialog titled
"Upload Review File" whose entire body is the single line **"You are not
allowed to add and edit these files."**; the only controls are page chrome and
"Close". The "every round state, past rounds included" quantifier held
everywhere either run looked — adding declined rounds ("Submission declined.")
and rounds whose submission has moved to Copyediting.

*Disagrees.* "While revisions are requested or submitted it completes; **in any
other state** the dialog opens with only a bare refusal notice" mis-names the
condition. The gate is not the round's status but the round's decision history:
`SubmissionFileStageAccessPolicy::effect()`
(`lib/pkp/classes/security/authorization/internal/SubmissionFileStageAccessPolicy.php:103–143`)
permits the author to write `SUBMISSION_FILE_REVIEW_REVISION` when the stage's
last round carries any of ACCEPT / PENDING_REVISIONS / NEW_EXTERNAL_ROUND /
RESUBMIT. Live counterexamples where the upload **completes** outside
"requested or submitted":

| case | round status line | dialog |
|---|---|---|
| OJS 305 R1 / OMP 182 R1 (accepted, submission in Copyediting) | "The submission is currently in the Copyediting stage." | wizard; upload completed (file 420 landed) |
| OJS 319 / OMP 186 (accept → Move to Review, no assignments) | "Waiting for reviewers to be assigned." | wizard |
| OJS 322 (accept → Move to Review, reviewer in flight) | "Awaiting responses from reviewers." | wizard |

Decisively: "Waiting for reviewers to be assigned." **refuses** on a fresh
round (OJS 325) and **completes** on a round returned from Copyediting (OJS
319). The status line does not predict the outcome, so a QA reader testing A6
by status will get contradictory results.

Proposed corrected symptom: "…While the stage's most recent review round
carries a revisions-request, resubmit, accept or new-round decision the upload
completes; otherwise the dialog opens with only a bare refusal notice — 'You
are not allowed to add and edit these files.' — as its entire body, with no way
to proceed. Round status does not decide it: the same status line completes on
one round and is refused on another."

**A7 — "'Notifications' subjects can't be opened by keyboard" · HOLDS, both
apps.** After a UI-recorded "Request Revisions" the author's panel roster gains
"Notifications", listing "Your submission has been reviewed and we encourage you
to submit revisions" with a timestamp. The clickable subject is an `<a>` element
with **no `href`, no `role` and no `tabindex`** (class
`text cursor-pointer text-base-normal hover:underline`); enumerating focusable
elements inside the panel returns an **empty list** on both apps. So the row is
neither keyboard-reachable nor exposed as a link — the entry's wording is
precise.

**A8 — symptom re-observable, but its explanation DISAGREES.** The
"identical rendering" half holds, and stronger than stated: the panel row's
full `outerHTML` is **byte-identical** across a `viewable` 0↔1 toggle (7215
chars on OJS, 7218 on OMP), and the row's More Actions menu offers only
"Update File Details" / "More Information" / "Delete" — no release affordance
anywhere; the Information Center logs a toggle merely as "The metadata for file
… was edited". The release/withdrawal explanation does not survive — see d1b
below, which is the substantive finding and requires rewriting A8's symptom,
mechanism and "Expected".

**A3 — "Recommendations outrank review trouble in the status line" ·
symptom true but stated NARROWER than the behavior · CLAIM-VS-CONTEXT.**

The single-recommender case reproduces the entry exactly. OJS 328: one
recommend-only Section Editor, one accepted reviewer whose due dates were
pushed into the past on this scratch submission. Before the recommendation the
round read **"A review is overdue."** with the Reviewers row "Overdue · Review
due: 2026-07-17 · Send Reminder"; after "Recommend Accept" it read **"All
recommendations are in and a decision is needed."** with the same row still
"Overdue".

The two-recommender case shows the entry's trigger is wrong. OJS 304 (two
recommend-only Section Editors, one overdue reviewer):

| recommendations in | status line | Reviewers row |
|---|---|---|
| 0 of 2 | "A review is overdue." | Overdue |
| **1 of 2** | **"New editorial recommendations have been submitted."** | Overdue |
| 2 of 2 | "All recommendations are in and a decision is needed." | Overdue |

So masking begins with the **first** recommendation, under a different string —
not "once every recommend-only participant has recommended". Confirmed on OMP
185 as well. Mechanism: `determineStatus()` returns `RECOMMENDATIONS_READY`
from the same pre-assignment branch as `RECOMMENDATIONS_COMPLETED`
(`ReviewRound.php:248–254`), so both outrank the overdue check.

Second imprecision: "the overdue signal returns only if the state changes" is
not re-observable. The line is recomputed on every display and the
recommendation branch wins every time; the overdue line does not come back
while any recommendation stands.

Proposed corrected symptom: "As soon as **any** recommend-only participant has
recommended, the round reads 'New editorial recommendations have been
submitted.' — and once all have, 'All recommendations are in and a decision is
needed.' — even while a review is still out or overdue, and the overdue line
does not come back while a recommendation stands. The 'Reviewers' panel still
shows the reviewer's row as 'Overdue', so the fact is one glance away; with no
recommendation yet recorded, nothing is masked."

**A4 — "'Returned back to review.' is often outranked" · HOLDS, all four
shapes.** Each driven through Copyediting → "Move to Review", stored round
status 16 in every case:

| round shape | line shown |
|---|---|
| no assignments (OJS 244) | "Waiting for reviewers to be assigned." |
| one declined assignment (OJS 326) | **"Returned back to review."** |
| one confirmed review (OJS 243) | **"Returned back to review."** |
| one accepted review in flight (OJS 327) | "Awaiting responses from reviewers." |

The footnote's four-shape table is reproduced exactly, and the stamp survives a
second return (Rule 18 above). All four shapes were independently reproduced on
**OMP** (186/187/188/189) with identical lines — footnote `f-a4` records OJS
only and could say the behavior is the same on both apps.

### OMP1–OMP5

Cases driven on OMP 152 (internal + external round 1), 154 (external round 1
only), 155 (internal round 1 only), 156/169 (Submission stage), 157 (external
round 1, one accepted + one completed open review), 170 (internal round 1 with
a review file added by hand, no author revisions), plus OJS contrasts on 283,
285, 286, 294, 300 and two freshly created scratch contexts.

**OMP1 — "Round-1 cancel lands by stage roster" · ✅ · minor — HOLDS.** All
four landings re-observed through the real wizard: OMP external round 1 with an
internal round → "WORKFLOW: INTERNAL REVIEW (ROUND 1)" (`stage_id 2`); OMP
external round 1 without → "WORKFLOW: SUBMISSION" (`stage_id 1`); OJS round 2 →
round 1 (`stage_id 3`); OJS round 1 → Submission (`stage_id 1`).

Two adjacent observations the entry does not carry:

- *The wizard's own description contradicts the OMP landing.* Both apps render
  "Cancel the current round of review and send the submission back to the last
  round of review. If this is the first review round, it will be moved to the
  submission stage." (`editor.submission.decision.cancelReviewRound.description`,
  `lib/pkp/locale/en/submission.po`, no OMP override). On a press with internal
  rounds the submission is *not* moved to the submission stage. A Press Editor
  reads a promise the server does not keep — which is a reason to question the
  entry's ✅ "intended parameterization" and its "minor" tier: the landing may
  be intended, the sentence the editor confirms against is not.
- *The landing round shows a stale decision-set status.* After the cancel,
  OMP 152's Internal Review Round 1 read "Submission accepted." — the stamp the
  earlier send-to-external decision left — so the round the editor is returned
  to announces an accept that has just been walked back.

Quantifier note: "OJS, having no internal stage, always lands on Submission"
scopes correctly only to Round 1 (OJS round 2 lands on round 1, still inside
Review). Proposed: "…always lands on the Submission stage when Round 1 is
cancelled."

**OMP2 — "Two entry decisions, one label" · ✅ · minor — HOLDS.** Both OMP
doors carry exactly "Send to External Review"; they never appear on one screen;
the wizards are identical in title, description and step names, differing only
in the query string (`decision=18` from Submission, `decision=3` from Internal
Review) and in the Select Files step's contents. The "skip" observation holds —
the only "skip" on the OMP Submission stage is "Accept and Skip Review".

Two corrections: (i) footnote `f-omp2` gives the OMP Submission-stage labels in
the order "Send to External Review, Send to Internal Review, Accept and Skip
Review, Decline Submission"; the live visual order is **Send to External Review,
Accept and Skip Review, Decline Submission, Send to Internal Review** — the
internal-review door renders last. (ii) Neither entry nor footnote records
OJS's label, so "OJS has a single send-to-review door" reads as if it carried
the same words; it is **"Send for Review"**.

**OMP3 — "The Internal Review door can open an empty external round" · ❓ ·
user-visible — HOLDS in full.** Built on the hardest case: OMP 170, whose
internal round's "Files for Review" panel held a real file and whose internal
round had no author revisions. The Internal-Review door's step 2 read

> **Send to External Review: Select Files** / "Select files that should be sent
> to the review stage." / **Revisions** / **No items found.**

with zero checkboxes — the internal round's own review file was not offered.
The resulting External Review Round 1 opened with "Files for Review — No Items"
and no warning anywhere. The Submission-stage door (OMP 169) offered
"**Submission Files**" with the file pre-ticked. Every sentence, including the
"never offered" absolute and the pre-ticked contrast, re-observed. Not driven:
the positive case (internal revisions present → offered), so the entry's first
clause is verified only in its negative half.

**OMP4 — "Presses ship without reviewer recommendation options" · ❓ ·
user-visible — symptom HOLDS; one body sentence DISAGREES.**

- Reviewer step: on OMP the review-submitting step carries no recommendation
  control and the word "recommendation" does not occur on the page; OJS's same
  step shows a "Recommendation" block with `select#reviewerRecommendationId`
  offering Accept Submission / Revisions Required / Resubmit for Review /
  Resubmit Elsewhere / Decline Submission / See Comments. **HOLDS.**
  *Wording nit*: the entry says "a reviewer's **completion step**", but the
  reviewer wizard's step 4 is literally named "Completion" while the
  recommendation control lives on step 3, "Download & Review". A literal reader
  opens the wrong step.
- "journals ship with six default options": **HOLDS, exactly six**, including
  on a journal created fresh during the run; `omp_test.reviewer_recommendations`
  is empty for every press including a freshly created one, so "ship" is
  literally true.
- "the author's read-review sheet shows no recommendation line": **HOLDS on
  OMP**, but **CLAIM-VS-CONTEXT** — on OJS the line is likewise absent whenever
  the reviewer chose no recommendation, and present ("Recommendation: Revisions
  Required") when they did. The OMP absence is unconditional, the OJS absence
  conditional; the entry's phrasing implies a flat app difference.
- **"A Press Manager can add options by hand in the review settings." —
  DISAGREES.** Live as `manager.maya`, Settings → Workflow → Review offers
  `Setup` · `Reviewer Guidance` · `Review Forms` on OMP and those three **plus
  `Reviewer Recommendations`** on OJS; the word "recommendation" does not appear
  anywhere on the rendered OMP page. The shared template gates the tab on
  `$hasCustomizableRecommendation`, which only OJS's `SettingsHandler` assigns —
  even though OMP's `Application::hasCustomizableReviewerRecommendation()`
  returns true.

  Neutral product voice: *on a press there is no screen for managing reviewer
  recommendation options; Settings → Workflow → Review offers Setup, Reviewer
  Guidance and Review Forms only. A press therefore has no in-application way
  to create the options its reviewers would choose from.*

  Proposed replacement sentence: "A press has no screen for adding them:
  Settings → Workflow → Review offers only Setup, Reviewer Guidance and Review
  Forms, while a journal's same screen carries a fourth 'Reviewer
  Recommendations' tab." The entry's Lean ("the shared review flow and its
  settings screen expect options; only OMP's defaults are missing") is wrong in
  both halves and needs rewriting; the ❓/user-visible tier understates a
  feature that is unreachable end to end on a press. This cascades into the
  "Settings that modify behavior" bullet ("presses with none") and Rule 13
  ("presses ship with none"), both of which should say a press has no list at
  all.

**OMP5 — "No 'Author Response' panel on OMP rounds" · ✅ · minor — HOLDS.**
OMP editor and author round rosters carry no "Author Response" panel and no
"Request Response" button; OJS's editor round carries the panel ("Invite the
author to respond to reviewer feedback before moving forward" / "Request
Response" / AUTHOR · RESPONSE STATUS), and the OJS author gains "Author
Response / Respond to Reviews / Submit Response" once a request is sent.

*Roster-order note (Rule 4, adjacent):* Rule 4 lists the author roster as
"round status, Notifications, Revisions Uploaded, Reviewers, Review Tasks &
Discussions, Author Response". The live order on both apps is round status →
Notifications → **Reviewers** → **Revisions Uploaded** → Review Tasks &
Discussions → Author Response. If Rule 4's order is meant as screen order it is
transposed.

---

## (c) Scenario walk-through spot check

### Scenario 9 — "Cancel a fresh round" · **1 wording disagreement, 2 literal-reading flags**

Executed literally on OJS (seeded round 1 with a single invited reviewer, OJS
316) and cross-checked on the availability matrix above.

| step as written | on screen | verdict |
|---|---|---|
| "Journal Editor" | `editor.diana` = participant "Journal editor" | ✓ |
| "on a round whose only reviewer has not yet answered the invitation, 'Cancel Review Round' is offered" | offered | ✓ |
| "confirm through the wizard" | wizard steps "Cancel Review Round: Notify Authors" → ": Notify Reviewers"; commit control is **"Record Decision"** | ✗ label |
| "The round vanishes from the side menu" | "Review Round 1" gone | ✓ |
| "and the previous round is current again" | verified separately on OJS 238 (round 2 → round 1) | ✓ |
| "(cancelling Round 1 lands on the Submission stage instead [OMP1])" | OJS: lands on "WORKFLOW: SUBMISSION" ✓ · press with internal rounds: lands on **Internal Review** | ✗ see (d) |
| "After a reviewer accepts — or declines — the invitation, the button is no longer offered." | accepted (OJS 317) and declined (OJS 318): button absent | ✓ |

**Flag 1 — "confirm through the wizard".** There is no "Confirm" control. A
literal reader looks for one and finds "Cancel / Previous / Record Decision".
Proposed: "record it through the wizard (its final button is 'Record Decision')".

**Flag 2 — the last sentence is not executable in place.** By the time the
reader reaches "After a reviewer accepts…", the round they were told to cancel
no longer exists. The step needs its own round. Proposed: "On a second round
whose reviewer has accepted — or declined — the invitation, the button is not
offered."

### Scenario 11 — "Curating Files for Review mid-round" · **1 label disagreement, 1 precondition flag**

Executed literally on OJS 295 (round 1 opened through the real "Send for
Review" decision, so its "Files for Review" holds one review file).

| step as written | on screen | verdict |
|---|---|---|
| "press 'Upload/Select Files'" | button labelled exactly that | ✓ |
| "'Current Review Files For Round 1' lists only the round's review files" | modal title exact; default listing = one group "Review" holding file 378 | ✓ |
| "tick 'Show files from all accessible workflow stages.'" | checkbox label exact, trailing period included | ✓ |
| "to reach the submission's other files" | groups Submission / Review / Copyediting / Production appear | ✓ |
| "Tick one and **save**" | the modal's commit button is **"OK"** (beside "Cancel"); there is no "Save" | ✗ label |
| "the panel now lists a copy of it" | copy 404 appears on the panel | ✓ |
| "reopen the modal and the copy is still unticked…" | 404 `checked: false`; DB `viewable = NULL` | ✓ |
| "…i.e. not yet released to reviewers" | the flag does not gate reviewer access — see d1b | ✗ meaning |
| "tick it and save to release it" | ticking 404 + OK → `viewable = 1`; no change to any reviewer's files | ✗ meaning + ✗ label |
| "Untick an existing review file and save: … remains listed on the panel and among the submission's files" | unticking 378 + OK → `viewable = 0`; row still on the panel; row still in `submission_files` | ✓ |
| "…it is withdrawn from reviewers…" | an already-assigned reviewer still receives it — see d1b | ✗ meaning |

**Flag 3 — the commit label.** "save" appears three times; the control is
"OK". Proposed: replace "and save" with "and press OK".

**Flag 4 — missing precondition.** The final step ("Untick an **existing**
review file") needs a round that already has one. A round seeded through the
scenario API opens with an empty "Files for Review" (verified: every `c4*`
seed), so a reader following footnote `s1`'s seeding note cannot reach it.
Proposed added clause: "…on a round entered through the real send-to-review
decision, so that 'Files for Review' already holds a file."

**Observation, not a disagreement.** The all-stages listing re-offers a
submission file that has *already* been copied into the round; ticking it
again produces a second, independent copy (295 ended with 378 and 404, both
sourced from 377). Rule 17 does not warn of this.

### Scenario 12 — "{OMP} Two doors into External Review" · **1 disagreement, 3 literal-reading flags**

Executed literally as `editor.diana` (participant label on OMP: "Press editor")
on OMP 169 (Submission door) and OMP 170 (Internal Review door).

Matching verbatim: the actor ("Press Editor"), both door labels ("Send to
External Review"), the side-menu entry gained ("External Review › Review Round
1"), the column heading ("WORKFLOW: EXTERNAL REVIEW (ROUND 1)"), the round
status heading and text ("Round 1 Status" / "Waiting for reviewers to be
assigned.") from both doors, the step name ("Select Files"), the pre-ticked
submission file from the Submission door, the "Revisions"-only list from the
Internal Review door, and the empty resulting panel.

**Flag 1 — "every common scenario above runs identically from there" ·
DISAGREES.** It is over-broad and is contradicted inside the same document:
scenario 6 does not run identically (no recommendation on a press, OMP4) and
the "Author Response" panel scenarios 3's neighbours rely on does not exist on
OMP at all (OMP5). It is also not executable as a step. Proposed: "…and the
common scenarios above run from there with the app deltas already marked — no
recommendation on a reviewer's review or the author's sheet [OMP4], and no
'Author Response' panel [OMP5]."

**Flag 2 — the scenarios it points at are journal-first.** Chief example is
scenario 9's parenthetical (item d3 above), which a reader executing scenario
12's "runs identically" instruction will hit on a press and observe as a
failure.

**Flag 3 — two string mismatches.** The scenario writes *empty "Files for
Review"*; the panel prints **"No Items"** and the wizard step prints **"No items
found."** — two different strings, neither quoted. And "offers the submission
files pre-ticked" is plural while a scenario-API monograph carries exactly one
submission file, so the step shows a single pre-ticked row.

**Flag 4 — missing precondition.** The Internal-Review half is only reachable
if the monograph is already in Internal Review, which needs a prior "Send to
Internal Review" decision from the Submission stage. Neither item 12 nor
footnote `s1` says so, so a reader starting from a fresh monograph cannot
execute the second door. Proposed added clause: "…or from Internal Review
(reach it with the Submission stage's 'Send to Internal Review' decision first)."

---

## (d) Queued review observations

### d1 — "arrives not yet released" also describes the round's OWN promoted files · **CONFIRMED, both apps**

Rule 17 attributes the unreleased arrival to files copied in through the
select modal. The same is true of every file the *decisions* promote into a
round:

| case | how the file got there | modal state on the round | DB `viewable` |
|---|---|---|---|
| OJS 295 file 378 | "Send for Review" wizard, Select Files step, offered file left ticked | "Current Review Files For Round 1": **unticked** | NULL |
| OMP 171 file 221 | "Send to External Review" wizard, same step | "Current Review Files For Round 1": **unticked** | NULL |
| OJS 241 files 372/373/374 | "Create New Review Round" wizard, three revisions carried | "Current Review Files For Round 2": **all three unticked** | NULL |
| OMP 135 files 246/247/248 | same wizard on a monograph | "Current Review Files For Round 2": **all three unticked** | NULL |
| OJS 242 file 383 | select modal on a past round | unticked | NULL |

Mechanism: the state the checkbox carries is the file's `viewable` flag, and
only `ManageSubmissionFilesForm::execute()` ever sets it
(`lib/pkp/controllers/grid/files/form/ManageSubmissionFilesForm.php`);
`importFile()` and the decisions' `PromoteFiles` step create the row without it.

So the *observable state* the queued note describes is confirmed and is
broader than Rule 17 says: every file that reaches "Files for Review", by any
route, starts unticked. **But the meaning the spec attaches to that state does
not survive verification — see the next item.**

### d1b — "released / withheld to reviewers" is not what the checkbox does · **Rule 17, A8, footnotes q and f-a8 DISAGREE**

Discovered while confirming d1. The spec (Rule 17, A8, footnote `q`, footnote
`f-a8`) treats the select modal's checkbox as the file's release state to
reviewers: "the copy arrives not yet released to reviewers", "unticking a
review file withdraws it from reviewers", "the only surface showing which files
reviewers actually receive is the select modal's checkbox column". Driven live
on OJS 295, round 1 holding file 404 (`viewable = 1`) and file 378
(`viewable = 0`):

1. **Adding a reviewer offers every review file in the round, pre-ticked,
   regardless of the flag.** The Add Reviewer modal's "Files To Be Reviewed"
   step listed **both** 404 and 378, both checkboxes `checked: true` on arrival.
   Confirming wrote `review_files` grants for both.
2. **The reviewer receives the "withheld" file.** `reviewer.julia`'s review page
   (`/reviewer/submission/295`, step 1 "Request for Review") listed both 404 and
   378 under "Review Files".
3. **Unticking a file afterwards does not withdraw it.** With Julia already
   assigned, unticking 404 in the curation modal and pressing OK set
   `viewable = 0` for both files — and Julia's page still listed both.

Same on OMP: on monograph 171, whose only review file (221) sits at
`viewable = NULL`, the Add Reviewer step's "Files To Be Reviewed" list offered
it pre-ticked.

Mechanism: reviewer file access is the per-assignment `review_files` grant
(`SubmissionFileAssignedReviewerAccessPolicy` calls
`ReviewFilesDAO::check($reviewAssignmentId, $submissionFileId)`), written by the
Add Reviewer step. The `viewable` flag the curation modal toggles is not
consulted on that path. No app screen claims otherwise: the panel says "These
files will be sent to the reviewers to review" and the modal's column is headed
plain "Select" — the release/withdrawal reading is the spec's own.

Neutral product voice: *the "Files for Review" select modal's checkbox records
a per-file flag on the round; it does not decide which files a reviewer
receives. What a reviewer receives is chosen when the reviewer is added, in the
Add Reviewer step's "Files To Be Reviewed" list, which offers every file on the
round's "Files for Review" panel pre-ticked. Changing the select modal's
checkbox afterwards does not add or remove a file from an already-assigned
reviewer.*

Proposed edits:
- **Rule 17**, replace "Ticking a file from another stage copies it into the
  round's review files; the copy arrives not yet released to reviewers, and
  unticking a review file withdraws it from reviewers without deleting
  anything" with: "Ticking a file from another stage copies it into the round's
  review files; the copy arrives with its own checkbox unticked, as does every
  file a decision promotes into a round (Round 1's opening files and a new
  round's carried-over revisions alike). The checkbox records a flag on the
  file and does not decide reviewer access: which files a reviewer receives is
  settled when that reviewer is added (U27), from the panel's full contents."
- **A8** — the entry's symptom, mechanism and "Expected" are all built on the
  release reading and need rewriting rather than patching. Its re-observable
  core is narrower: *the "Files for Review" panel shows no per-file state at
  all, while the select modal shows a checkbox whose meaning is not explained
  anywhere on either surface.* Whether that is still a 🐞 is a maintainer call;
  the "which files reviewers actually receive" claim must go.
- **Footnote q**, replace "deselection flips the file's `viewable` flag only"
  with the same, plus "reviewer access is the per-assignment `review_files`
  grant (`SubmissionFileAssignedReviewerAccessPolicy` →
  `ReviewFilesDAO::check()`), written by the Add Reviewer step and unaffected by
  this flag."
- **Rule 16**, no change needed once Rule 17 is corrected.
- **Scenario 11**, drop "i.e. not yet released to reviewers" and "tick it and
  save to release it"/"it is withdrawn from reviewers"; the observable outcomes
  are the checkbox state and the panel listing, both verified above.
- **Cross-feature interactions**, consider adding to the U27 row: "…and which of
  the round's files each reviewer receives."

### d2 — the side-column "Recommendation" box shows the decision label · **CONFIRMED, both apps; spec text does not match**

Cases: OJS 246 / OMP 140 (one recommend-only Section Editor, `sectioneditor.omar`);
OJS 314 (two recommend-only Section Editors, `omar` + `ravi`). Locator:
`[data-cy="workflow-secondary-items"]` and `[data-cy="workflow-action-items"]`.

| control pressed | recommender's own restatement | deciding editor's side-column box |
|---|---|---|
| "Recommend Revisions" | `RECOMMENDATION / Request Revisions / Change decision` | `RECOMMENDATION / Request Revisions` |
| "Recommend Accept" | `RECOMMENDATION / Accept Submission / Change decision` | `RECOMMENDATION / Accept Submission` |
| "Recommend Decline" | `RECOMMENDATION / Decline Submission / Change decision` | `RECOMMENDATION / Decline Submission` |
| both recommenders in (Accept + Decline) | — | `RECOMMENDATION / Accept Submission, Decline Submission` |

Two accuracy gaps against Rule 11:

1. **The label is the decision's, not the control's.** A recommender who
   presses "Recommend Revisions" sees their recommendation restated as
   "Request Revisions". Rule 11 says the area "restates their recommendation";
   scenario 8 says "restates it". Both invite the reader to expect the button's
   own words. Proposed for Rule 11: "…restates their recommendation under a
   'Recommendation' heading — using the matching decision's label ('Request
   Revisions', 'Accept Submission', 'Decline Submission'), not the button's —
   with a 'Change decision' button."
2. **The box carries no attribution.** Rule 11 says the box lists "every
   recommendation recorded for the stage". It does list them all, but as one
   comma-joined line of decision labels with no recommender name and no date;
   with two recommenders the deciding editor reads "Accept Submission, Decline
   Submission" and cannot tell who recommended which. Proposed for Rule 11:
   "…a 'Recommendation' box in the side column listing every recommendation
   recorded for the stage as a single comma-joined line of decision labels,
   without naming the recommender or the date."

Footnote `l`'s "assigned or unassigned manager" re-observed: `manager.maya`,
not a participant on OJS 314, sees the same side-column box ("RECOMMENDATION /
Accept Submission, Decline Submission") and the full decision set.

Side observations confirming Rule 6 / A3 statuses in the same run: with one of
two recommenders in, the round read "New editorial recommendations have been
submitted."; with both in, "All recommendations are in and a decision is
needed." The recommend-only participant's own side column holds only
"PARTICIPANTS" (footnote `l` holds), and the Participants row is flagged "Only
allowed to recommend an editorial decision".

### d3 — scenario 9's parenthetical is journal-first · **CONFIRMED**

Scenario 9 prints: "(cancelling Round 1 lands on the Submission stage instead
[OMP1])". OMP1 states: "Cancelling External Review's Round 1 returns the
monograph to Internal Review when internal rounds exist, else to the Submission
stage." The landing was re-observed live: on a press whose monograph had an
internal round, cancelling External Review's Round 1 left the workflow headed
"WORKFLOW: INTERNAL REVIEW (ROUND 1)" (`submissions.stage_id = 2`); without an
internal round it landed on Submission.

The scenario list is explicitly "Common to OJS and OMP (OMP runs them on
External Review…)", so a QA reader executes scenario 9 on a press and reads a
parenthetical that states the OJS outcome as the outcome. The `[OMP1]` tag
marks a divergence exists but the sentence itself asserts the wrong result.

Proposed: "(cancelling Round 1 lands on the Submission stage instead — on a
press, on Internal Review when internal rounds exist [OMP1])".

### d4 — the OPS footnote's "only actions offered" · **CONFIRMED over-broad; the claim is decision-area-scoped**

Footnote `w` ends: "…the only actions offered are 'Post the preprint' and
'Decline Submission'".

Live on OPS 8200, submission 15, as `manager.maya` (Preprint Server Manager):

- decision area `[data-cy="workflow-action-items"]` = exactly
  `Post the preprint` · `Decline Submission` — the footnote's claim, true of
  that region;
- the workflow header additionally carries **`Preview`**, **`Activity Log`**,
  **`Library`** beside the stage label "Production";
- the primary column carries "Production Tasks & Discussions" with its own
  **`Add`** control; the side menu carries **`Create New Version`**.

For contrast, the same header on OJS reads `Activity Log` · `Library`, and on
OMP `Activity Log` · `Library` · `Monograph`; OPS's reads `Preview` ·
`Activity Log` · `Library`. So the header is not an OPS-only feature — the
footnote's sentence would be over-broad on any app; OPS is simply where it was
written. As `author.alex` the OPS header carries `Library` only, and the
landing panel is "PREPRINT: TITLE & ABSTRACT".

The rest of footnote `w` re-observed and **HOLDS**: the side menu's Workflow
group holds only "Production"; there are no round panels and no "Reviewers"
panel in either role's view.

Proposed: "…the only **decisions** offered are 'Post the preprint' and
'Decline Submission' (the workflow header still carries Preview, Activity Log
and Library, and the stage still has its Tasks & Discussions panel)."

### d5 — the `##common.help##` placeholder · **CONFIRMED, and it is not the curation modal's**

Independently observed in this run on **all three apps** and on many surfaces:
it is the first line of `[data-cy="active-modal"]` innerText on every OJS, OMP
and OPS workflow screen I dumped, on the curation modal, and on the standalone
decision-record pages. It is the accessible name of the side-modal top bar's
Help icon, i.e. shared application chrome — not a string rendered in the
curation modal's body, and not OMP-specific.

Precise placement, measured with the curation modal open: the string is the
accessible name of the Help (ⓘ) icon link in the side modal's dark top bar,
top-right, next to the Tasks bell and the user avatar (`href` →
`docs.pkp.sfu.ca/learning-omp/en/` on OMP, `…/learning-ojs/` on OJS). It is
carried by a `span.-screenReader` clipped to zero size, so it is **not visible
on screen** — it is what a screen reader announces and what any text extraction
of the page reports. With the workflow modal open a page holds two occurrences
(page top nav + workflow modal); opening "Upload/Select Files" makes three.
Same counts on OJS.

The curation modal's own body holds no help text and no placeholder: title
"Current Review Files For Round N", then `Review Files` / `Upload Review File` /
`☐ Show files from all accessible workflow stages.` / `Select · Name ·
Component` / the stage groups (OJS "Review", OMP "External Review") / `Cancel ·
OK`.

Emitting source: `lib/ui-library/src/components/TopNavActions/TopNavActions.vue`
renders `<span class="-screenReader">{{ t('common.help') }}</span>`, and
`lib/ui-library/src/components/Modal/SideModalBody.vue` mounts `TopNavActions`
in every side modal's top bar. The key `common.help` is listed in both apps'
`registry/uiLocaleKeysBackend.json` but is defined in no `.po` file in
`ojs-main`, `omp-main` or either `lib/pkp` tree, so `Locale::get()` falls back
to `'##' . $key . '##'` and `t()` returns it verbatim.

**Ownership per the spec's own division.** The spec places the modal in
**U26's own** Reference table — "Editor review files grid & select modal |
'Files for Review' panel | GRID-025, 027" — and owns its behavior in Rule 17;
what U36 owns is narrower, per Cross-feature interactions ("U36 Submission
files — file-manager mechanics for both file panels") and Fields & validation
("File names and metadata belong to Submission files (U36)"). So the modal's
*screen* is U26's.

**But the defect belongs to neither.** It is global chrome rendering on every
page and every side modal in all three apps. Filing it in U26's register would
mis-scope it. Recommended: leave it out of the register and, if a trace is
wanted, add a footnote noting the modal inherits the shared side-modal top bar
whose Help link currently has an untranslated accessible name in every app —
routing the defect itself to UNASSIGNED / a shared-chrome owner.

Incidental, same neighbourhood: `PKPTemplateManager.php` registers a Smarty
`help` function pointing at `$this->smartyHelp(...)`, and no `smartyHelp`
method exists anywhere in `lib/pkp`.

---

## Unresolved: OMP recommend-only buttons — two runs disagree

One of this chunk's two live runs reported that on OMP a recommend-only
editor's "Recommend Revisions" / "Recommend Accept" / "Recommend Decline"
buttons render enabled but **do nothing** on click — no navigation, no request
— with `availableEditorialDecisions` empty for that user and a direct
`GET /decision/record/181?decision=10&reviewRoundId=182` returning 404
(OMP 181, `sectioneditor.omar`, three attempts). The same buttons worked on OJS.

**This does not reproduce here.** On OMP submissions whose recommend-only
participant was seeded through the scenario API
(`participants: [{user, role: 'sectionEditor', recommendOnly: true}]`):

- OMP 140, `sectioneditor.omar`: "Recommend Revisions" → the Request-Revisions
  variant modal → `/decision/record/140?decision=10&…` → recorded; the
  recommender's area then showed "RECOMMENDATION / Request Revisions / Change
  decision" and the deciding editor's side column showed the box.
- OMP 183, `sectioneditor.ravi`: "Recommend Accept" navigated to
  `/decision/record/183?decision=9&…&reviewRoundId=184`.

The reproduction difference is **how "Recommend only" was set**: the failing
run flipped an existing participant through the Participants panel's edit form;
the passing cases had the flag applied at assignment time by the scenario API.
That points at the participant-edit path rather than at the round screen — the
"Recommend only" toggle is **U35's** (Stage participants), and Rule 11 here
only owns the display. Recorded so it is not lost; it needs its own probe on
U35 before anyone states it as a defect. Nothing in U26's text is affected:
Rule 11's display claims verified on both apps (d2).

## Incidental finding outside the assigned scope (Rule 6)

Surfaced while walking scenario 9's "…or declines…" branch; reporting it
because it is re-observable and a QA reader would hit it.

**Rule 6's status table mis-states two rows.** Live on OJS 318 (round 1 whose
only reviewer **declined** the invitation) the round reads **"All reviews are
confirmed and a decision is needed."**

- Row 1 says "Waiting for reviewers to be assigned." is shown when there is
  "no active reviewer in the round". Observed: it is shown only when the round
  has **no review assignment at all**. A round whose every reviewer declined or
  was cancelled has no active reviewer and does *not* show this line.
- Row "All reviews are confirmed and a decision is needed." says "every active
  review confirmed; nothing else pending". Observed: it is the fall-through
  line — it is also what a round shows when every assignment is declined or
  cancelled and none was ever confirmed.

Mechanism, for the footnote: `ReviewRound::determineStatus()` skips DECLINED and
CANCELLED assignments when computing its flags, then branches on
`$reviewAssignments->isEmpty()` (the *collection*, declined rows included)
before falling through to `REVIEW_ROUND_STATUS_REVIEWS_COMPLETED`.

Proposed row edits:
- "Waiting for reviewers to be assigned." → When: "the round has no reviewer
  assignment at all (the state of every new round)".
- "All reviews are confirmed and a decision is needed." → When: "every review
  in the round is settled — confirmed, declined or cancelled — and nothing else
  is pending".

---

## Routing

Nothing observed in this chunk falls on the access-exceeding side of the
routing rules. Every disagreement above is a documentation-vs-screen wording
gap, a missing affordance, or a screen-says-X/server-does-Y mismatch in the
ordinary direction. The one server-side probe run (the cancel guard) found
screen and server in agreement, with a passing positive control.

## Proposed content for files this chunk must not write

- `PROGRESS.md`: no entry proposed beyond the loop's own step-8 record.
- atlas files: no atom claim changes proposed.
- `docs/e2e/app-changes.md`: **nothing** — no harness or app change was needed
  to run this chunk.
