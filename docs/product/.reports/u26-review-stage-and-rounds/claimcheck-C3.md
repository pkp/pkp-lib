# U26 Review stage & rounds — Claim-check C3

**Chunk:** Rules 13–17 + Settings section + Cross-feature interactions + the
OMP/OPS app paragraphs of
`lib/pkp/docs/product/specs/review-stage-and-rounds.md`.
**Method:** driven through the workflow/settings screens as signed-in users on
live OJS (`127.0.0.1:8000`) and OMP (`127.0.0.1:8100`). Scratch contexts
`u26c3x`/`u26c3y` (OJS) and `u26c3o` (OMP); scratch users/submissions only,
seeded via the `_test` scenario API. No seeded roster or `publicknowledge`
state mutated; Mailpit never cleared.
**Author/date:** claude C3, 2026-07-31.

Seed inventory (OJS `u26c3x`, review mode = context default/anonymous): #102
T1 [rva+rvb accepted]; #103 T2 [rvc declined]; #104 T3 two rounds [R1 rvc
invited, R2 empty]; #105 T5 R1 empty; #106 T6 in Copyediting (sendExternalReview
+accept, round had 0 reviewers); #109 T7 in Copyediting (sendExternalReview
+accept, round had rva accepted). OJS `u26c3y`: #108 [rvy declined] used for
the Rule-13 positive case. OMP `u26c3o`: #116 O1 external R1 [orva declined];
#118 O2 external R1 [orva accepted]. Scratch users: OJS `u26c3mgr`(manager),
`u26c3se`/`u26c3yse`(section editors), `u26c3ed`/`u26c3yed`(journal editors),
`u26c3aut`/`u26c3yaut`(authors), `u26c3rv{a,b,c}`/`u26c3yrv`(reviewers); OMP
`u26o3*`.

**Verdict tally: holds 6 · wrong-with-observed 3 · unresolvable 1.**

Headline: two spec claims are wrong before a QA reader would trust them — the
**Settings "Minimum Confirmed Reviews Required"** claim that the box shows "no
status sentence at all" below the minimum, and the **Cross-feature "Returned
back to review."** claim. Rule 13's button claim also carries an unstated
precondition (a deciding-editor participant must exist).

---

## Rule 13 — Recommendations

**Claim:** "A recommending editor sees three recommendation buttons in place of
the decision buttons — 'Recommend Revisions', 'Recommend Accept', 'Recommend
Decline' … On the deciding editor's screen, once a recommendation for the
selected round exists, a box headed 'Recommendation' lists the
recommendation(s) recorded — e.g. 'Accept Submission' — with no further detail.
The round status walks the three recommendation sentences of Rule 5."

### 13a — the three buttons (positive) — HOLDS
On #108 I assigned via the Assign-Participant screen a **deciding** editor
(`u26c3yed`, Journal editor, no recommend-only) **and** a recommend-only
Section editor (`u26c3yse`, the "Assignment privileges … only allowed to
recommend an editorial decision" checkbox ticked). The recommend-only SE's
Review-stage right rail then showed exactly three buttons —
**"Recommend Revisions", "Recommend Accept", "Recommend Decline"** — and no
decision buttons. Status box: **"Awaiting recommendations from editors."**
HOLDS.

### 13b — deciding editor's "Recommendation" box + status walk — HOLDS
The SE recorded "Recommend Accept" (wizard heading **"Recommend Accept"**,
single step "Notify Editors", primary button **"Record Decision"**). On the
deciding editor's screen the secondary box **"RECOMMENDATION"** then listed the
bare label **"Accept Submission"** (no further detail), and the status box read
**"All recommendations are in and a decision is needed."** (one recommending
editor, all recorded — the Rule 5 third recommendation sentence). HOLDS.

### 13c — ADVERSARIAL EDGE: no deciding-editor participant → NO buttons — WRONG-WITH-OBSERVED
On **#103** I assigned only the recommend-only Section editor `u26c3se` (no
deciding editor as a stage participant — the journal Manager `u26c3mgr` has
journal-wide access but was *not* a participant). The recommend-only SE's
screen then showed **no recommendation buttons at all**; the right rail carried
a "RECOMMENDATION" box reading:

> "You can not make a recommendation until an editor is assigned with permission
> to record a decision."

Rule 13 states unconditionally that the recommending editor "sees three
recommendation buttons in place of the decision buttons." That is true only
when a deciding-editor **participant** also exists on the stage; with none, the
recommend-only editor is shown a guard message and no buttons. Footnote e's
probe-P4 item 10 evidently had a deciding editor present, so it never saw this
state. **Verdict: WRONG-WITH-OBSERVED** — Rule 13 needs the precondition
stated. (This is a restriction, not over-entitlement — nothing routed to the
security file.)

Locators: Assign dialog role filter `select` (options observed OJS: Journal
editor / Section editor / Guest editor / Funding coordinator / Translator /
Author, matching C1); recommend-only control `input[name="recommendOnly"]`;
label text "This participant is only allowed to recommend an editorial decision
and will require an authorised editor to record editorial decisions." Buttons
by accessible name.

---

## Rule 14 — The author's view

**Claim:** author view per round = "a 'Notifications' list (Rule 16), the
reviews they may read (Rule 15), the Revisions Uploaded panel with the upload
button (Rule 9), and the stage's discussions."

**Observed (author `u26c3aut`, My Submissions → #102):** the author view
rendered the status box, the **Revisions Uploaded** panel, and **Review Tasks &
Discussions** — but **no "Notifications" list and no reviewers list** (see
Rules 15–16 for why: no editor letter and no open+completed review existed on
#102). On **#109**, where the author had received an editor letter, the
**Notifications** list *was* present (Rule 16 below). **Verdict: HOLDS**, with
the nuance worth carrying into the spec: each of the four author-view panels is
**conditional** — Notifications renders only when ≥1 editor letter exists, the
reviewers list only when an open+completed review exists (Rule 15). The flat
enumeration in Rule 14 reads as "always four panels"; in practice a fresh
in-review submission shows only Revisions Uploaded + Discussions.

---

## Rule 15 — Reading reviews as the author

**Claim (the part checked):** "until such a review exists the author's screen
shows no reviewers list at all — not an empty one — and anonymous reviews never
appear, whatever their state."

**Adversarial case:** #102 carries two reviewers in the **accepted** (in
progress, not completed) state under the context's default (anonymous) review
mode. The author view (`u26c3aut` via My Submissions) showed **no "Reviewers"
heading and no "Read Review" control** — the reviewers list was entirely
absent, not an empty table. **Verdict: HOLDS** for the "no list, not an empty
one" claim in the not-completed / anonymous direction. (The OJS1 no-review-text
bug and the A3 attachments area are footnoted probes; A3 not re-probed per the
C3 instruction, and the open+completed-review positive is probe-P5 item 11 —
not re-driven here as it needs a UI-submitted review.)

---

## Rule 16 — The "Notifications" list

**Claim:** "holds the emails editors sent the author about this submission —
each row a subject line and date; clicking one opens the full letter read-only
in a side panel."

**Positive case (new, cheap):** the Move-to-Review decision on #109 sent the
author the "Submission Sent Back from Copyediting" letter. On the author's
review-stage view of #109 the **Notifications** list then held one row —
**"Your submission has been moved to review" · "2026-07-31 06:17 PM"** (subject
+ date). **Verdict: HOLDS.** Corroborates footnote k; adds that the panel is
**absent** (not empty) when no such letter exists (#102 above). Did not re-open
the read-only side panel (footnote k probed it).

---

## Rule 17 — Old addresses

**Claim:** "A bookmarked author link of the form used by earlier versions
('authorDashboard') lands on My Submissions with the submission's workflow
open; the old per-round tab address from those versions no longer renders
anything."

**Cases (authenticated as author `u26c3aut`):**
- `…/u26c3x/en/authorDashboard/submission/102` → redirected to
  `…/dashboard/mySubmissions?workflowSubmissionId=102&currentViewId=active&workflowMenuKey=workflow_3_113`
  — My Submissions with the workflow open. ✓
- `…/u26c3x/en/authorDashboard/reviewRoundInfo/102` → **HTTP 404**, nothing
  rendered (URL stayed on the dead route). ✓

**Verdict: HOLDS.** Matches footnote o (probe-P6 item 14).

---

## Settings — Minimum Confirmed Reviews Required — WRONG-WITH-OBSERVED

**Claim:** "when set, the round status box shows 'Minimum number of confirmed
reviews required: {N}.' while reviews are being gathered — and while confirmed
reviews remain below that minimum, the line stands alone: the box shows no
status sentence at all."

**Case driven:** via the **Workflow Settings → Review** tab
(`input[name="numReviewsPerSubmission"]`, label "Minimum Confirmed Reviews
Required") I set the minimum to **2** on `u26c3x`, then opened **#102** (two
**accepted**, zero confirmed reviews — below the minimum). The status box read
**two lines**:

> "Minimum number of confirmed reviews required: 2."
> "Awaiting responses from reviewers."

So below the minimum the box did **not** stand the minimum line alone — the
reviewer-derived sentence "Awaiting responses from reviewers." was shown as
well. The spec's "the box shows no status sentence at all" is **over-broad**:
the minimum-aware override suppresses the *submitted/confirmed* reviewer
sentences (footnote r's probe-P4 item 15 had **1 confirmed** review and saw the
line alone), but **not** the "Awaiting responses from reviewers." (in-progress)
sentence. footnote r checked only the one-confirmed state and so missed this.

**Cross-app:** reproduced **identically on OMP** — I set the minimum to 2 on
`u26c3o` and opened #118 (one accepted reviewer, zero confirmed); the box read
"Minimum number of confirmed reviews required: 2. / Awaiting responses from
reviewers." The unmarked "identical in OJS and OMP" assumption holds for this
behavior.

**Verdict: WRONG-WITH-OBSERVED.** The "line stands alone / no status sentence"
claim is true only for the states where reviews have been submitted or
confirmed below the minimum; with reviews merely awaited, the awaiting sentence
still shows.

**Minimum-met wording — UNRESOLVABLE (cost).** Footnote r flagged "the wording
once the minimum is met remains unobserved"; observing it needs a UI-submitted,
editor-confirmed review to cross the threshold, which the scenario API cannot
seed (only invited/accepted/declined). Not driven. Marked unresolvable rather
than holds/wrong.

Other Settings bullets: **Reviewer suggestions enabled** and **Review type per
assignment** are cross-feature pointers; the Review-type gating of author
access was exercised indirectly via Rule 15 (anonymous reviewers → no author
list). Not separately adjudicated.

---

## Cross-feature — Copyediting "Returned back to review." — WRONG-WITH-OBSERVED

**Claim (Cross-feature interactions + Rule 5 row):** "Copyediting stage —
sending a submission back from copyediting puts the round into 'Returned back
to review.'"

**Cases driven:** from the Copyediting stage I pressed **"Move to Review"**
(the `backFromCopyediting` decision; wizard "1 Notify Authors" → "Record
Decision") and then read the review-stage status box:
- **#106** (round had **0 reviewers**): box read **"Waiting for reviewers to be
  assigned."** — not "Returned back to review."
- **#109** (round had **1 accepted, in-progress reviewer**): box read
  **"Awaiting responses from reviewers."** — not "Returned back to review."

In both realistic states the "Returned back to review." sentence did **not**
appear. Per footnote c the stored status 16 "survives" only *after* the
reviewer scan (no-assignments → 6, overdue → 10, unread → 8, incomplete → 7,
pending-recs → 12) finds nothing — i.e. only on a round whose reviewers are all
completed **and** confirmed. Rule 5 lists "Returned back to review."
unconditionally, and Rule 6's precedence discussion covers reviewer facts and
the revision/closing sentences but **never mentions status 16's subordination
to the reviewer scan**. A QA reader following Rules 5–6 would expect the
sentence after Move to Review and, in the two common states above, would not
see it.

**Verdict: WRONG-WITH-OBSERVED** (Rules 5–6 wording, reached via the
Copyediting cross-feature). I observed the masking in two states; I did **not**
positively reach the all-confirmed state that footnote c says surfaces status
16 (needs a UI-completed, confirmed review — same seeding limit as above), so
the sentence's positive appearance stays code-confirmed only. This is a NEW
angle: footnote d's probe chain never observed "Returned back to review." at
all.

---

## OMP / OPS app paragraphs — HOLDS

- **"On a press, everything in this file describes the External Review stage. A
  press also offers a separate, earlier Internal Review stage [OMP1]."** — On
  OMP the workflow menu carried both **Internal Review** and **External
  Review** entries (corroborates C1's Rule-3 observation); the External Review
  round (#116/#118) behaved like the OJS external round panel-for-panel
  (status box, Revisions Uploaded, Files for Review, Reviewers, minimum-line
  behavior above). **HOLDS.**
- **OPS absence paragraph** — not re-probed (footnote p, probe-P7 item 17); no
  new angle seen.
- The unmarked "identical in OJS and OMP" assumption was sampled on the two
  claims in this chunk most likely to diverge — the recommend-only machinery
  (Rule 13; config is a deep-merge of the OJS external block per footnote a, and
  the OMP external round rendered identically) and the minimum-reviews status
  line (reproduced identically on OMP, above). No divergence found beyond the
  documented OMP1/OMP2/A-series.

---

## Proposed content (for the maintainer to fold into the spec on review — NOT applied here)

1. **Rule 13 — add the precondition.** The three recommendation buttons appear
   only when a **deciding-editor participant** is also assigned to the stage;
   with a recommend-only editor as the *only* editorial participant, the screen
   shows no buttons and the "Recommendation" box instead reads "You can not make
   a recommendation until an editor is assigned with permission to record a
   decision." (Observed OJS #103, 2026-07-31.) Suggest a clause on Rule 13
   and/or a note that the buttons are gated on a deciding editor existing.

2. **Settings — Minimum Confirmed Reviews Required — correct the "stands alone"
   claim.** Below the minimum the box suppresses the *submitted/confirmed*
   reviewer sentences but **still shows** "Awaiting responses from reviewers."
   when reviews are in progress (observed with 0 confirmed / 2 accepted on both
   OJS #102 and OMP #118, 2026-07-31). Reword "the box shows no status sentence
   at all" to scope it to the submitted/confirmed states. Update footnote r,
   which only checked the one-confirmed state.

3. **Rule 5 / Rule 6 / Cross-feature (Copyediting) — qualify "Returned back to
   review."** The sentence is masked by the reviewer scan and appears only when
   the round's reviewers are all completed and confirmed; on a reviewerless
   round it reads "Waiting for reviewers to be assigned." and on an in-progress
   round "Awaiting responses from reviewers." (observed OJS #106 and #109,
   2026-07-31). Recommend adding status 16 to Rule 6's precedence discussion (it
   sits *below* the reviewer-derived sentences) and softening the Rule 5 row /
   Copyediting bullet accordingly.

4. **Rule 14 — note the panels are conditional.** Notifications renders only
   with ≥1 editor letter; the reviewers list only with an open+completed review;
   a fresh in-review submission shows only Revisions Uploaded + Discussions
   (observed OJS #102 vs #109, 2026-07-31).

## Security routing
No over-entitlement observed in this chunk. The Rule-13 guard and the
stage-access gates all restrict correctly (they deny, never over-grant). The A3
register/attachments area was not probed (per the C3 instruction) and is not
described anywhere here. Nothing routed to the private security file.

## Blockers
- **Fleet servers went down mid-session** (8000/8100/8200 all returned 000
  after ~30 min). I restarted them with `node lib/pkp/playwright/serve.js`
  (`PLAYWRIGHT_BASE_PORT` 8000/8100/8200 from the three app roots) and all three
  returned 200 + warm bootstrap; work resumed with scratch state intact
  (Postgres). They are left **running** — a subsequent `npm run test:e2e` will
  contend for these ports and should stop them first.
- Two claims need a **UI-submitted, editor-confirmed review** to reach their
  surfacing state (Settings minimum-met wording; positive "Returned back to
  review."); the scenario API seeds only invited/accepted/declined, so both are
  left unresolved-by-cost rather than driven.
