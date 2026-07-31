# U26 Review stage & rounds — Claim-check C1

**Chunk:** Actors & permissions section + Rules 1–6 of
`lib/pkp/docs/product/specs/review-stage-and-rounds.md`.
**Method:** driven through the workflow screens as signed-in users on live OJS
(`127.0.0.1:8000`) and OMP (`127.0.0.1:8100`). Scratch contexts `u26c1x` (OJS)
and `u26c1o` (OMP); scratch users/submissions only, seeded via the `_test`
scenario API. No seeded roster or `publicknowledge` state mutated.
**Author/date:** claude C1, 2026-07-31.

Seed inventory (OJS `u26c1x`): S1 #94 submission-stage (no round); S2 #95 R1
[reva declined, revb invited]; S3 #96 R1 [reva declined only]; S4 #97 R1 [revc
accepted]; S5 #98 two rounds [R1 revb invited, R2 empty]. OMP `u26c1o`: O1 #114
external R1 [reva declined, revb invited]; O2 #115 external R1 [reva declined
only]. Scratch users: `u26mgr`(manager), `u26sea/seb/sec`(section editors),
`u26aut`(author), `u26reva/revb/revc`(external reviewers); OMP `u26o*`.

Verdict tally: **holds 10 · wrong-with-observed 2 · unresolvable 0.**

---

## Actors & permissions

### Preamble — "Typing the workflow address of a submission outside these grants gets only an access error, with nothing of that submission shown." (⟵ footnote a)
**Adversarial cases driven (three refused visitors, each with a positive control):**
- Author `u26aut` typed the **editorial** dashboard URL of their OWN submission
  #95 (`/u26c1x/dashboard/editorial?workflowSubmissionId=95`). → redirected to
  `/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`,
  page body "The current role does not have access to this operation." No
  submission data. Positive control: same author via
  `/u26c1x/dashboard/mySubmissions?workflowSubmissionId=95` → workflow opened
  in the author view (title "U26C1 S2…", status box shown).
- **Unassigned** section editor `u26seb` typed the editorial URL of #95. → the
  dashboard mounted for the SE but the workflow modal showed only a stacked
  **"Error / The current role does not have access to this operation. / OK"**
  dialog; `GET /api/v1/submissions/95` returned **401**; body never contained
  the title or author name. Positive control: the dashboard's own
  `_submissions/assigned` list returned 200 (session live).
- Invited reviewer `u26revb` (a live review assignment on #95) typed the
  editorial URL. → same `roleBasedAccessDenied`, nothing shown. Positive
  control: `/u26c1x/reviewer/submission/95` rendered the reviewer's own
  4-step review page (Accept/Decline controls present).

**Verdict: HOLDS.** The refused-visitor claim reproduces for all three
out-of-grant roles, each cross-checked against an entitled path.

### Preamble — "The Author reaches this screen only for their own submission, through My Submissions."
**Case:** author's editorial-dashboard URL denied (above) while the
My-Submissions URL succeeds. **Verdict: HOLDS.**

### Actors row "Open the review stage" — Assistant-level roles "no path in: the stage's assignment dialog offers no assistant groups" (⚠ A5)
**Adversarial case driven:** opened the review-stage **Participants → Assign**
dialog ("Assign Participant / Locate a User") as manager on both apps and read
the role-filter roster it offers.
- OJS (#96): **Journal editor · Section editor · Guest editor · Funding
  coordinator · Translator · Author.**
- OMP (#115): **Press editor · Series editor · Funding coordinator · Author ·
  Volume editor · Translator.**

**Observed vs claim:** the dialog **does** offer **Funding coordinator** on
both apps. Funding coordinator is an assistant-level group
(`ROLE_ID_ASSISTANT` = 4097, per `lib/pkp/classes/security/Role.php`; it is the
one default assistant group with external-review stage access, stages 1,3 —
`.claude/skills/ojs-playwright-tests/users.md`). So the flat claim "the stage's
assignment dialog offers no assistant groups" and the row header "Assistant-level
roles … no path in" are **over-broad**: no *production* assistant groups
(Copyeditor, Layout Editor, Proofreader) are offered, but the Funding-coordinator
assistant group **is** assignable to the review stage through this very dialog —
a screen path that admits an assistant-level role.

**Verdict: WRONG-WITH-OBSERVED.** Refines A5 and the Actors row. This is
intended app behavior (funding has review access by design), so the fix is
wording, not code. Suggested correction below. (Not a security concern — the
entitlement is expected; nothing routed to the private file.)

### Actors row "Open the review stage" — "Section Editor … when assigned to the stage"
**Adversarial case:** unassigned section editor `u26seb` refused (above);
`GET submissions/95` → 401. **Verdict: HOLDS** (the "when assigned" gate holds
in its negative direction; an assigned SE was not separately driven, but the
manager path and the unassigned-refusal bracket the claim).

### Actors row "Record a decision on the round" — "Deciding editors — on the current round only"
**Case:** manager (a deciding editor) on the **current** round of #95/#96/#97
saw the decision-button cluster (Request Revisions / Accept Submission / Create
New Review Round / Decline Submission; #98 R2 additionally Cancel Review Round).
On the **past** round 1 of #98 the decision buttons were **absent** (Rule 10).
**Verdict: HOLDS** for the presence-on-current claim in this row. (The Cancel
Review Round gating is Rule 11 — out of this chunk; see cross-note.)

### Actors row "See the round status line" — "the same status box for every role" (⚠ A2)
**Case:** editor view of #95 (manager) and author view of #95 (`u26aut` via My
Submissions) both render the box "Round 1 Status — Awaiting responses from
reviewers." — character-identical heading and sentence. **Verdict: HOLDS /
corroborates A2** (author sees the editor wording).

---

## Rules 1–6

### Rule 1 — "the highest-numbered round is the current round, and only the current round can still change."
**Adversarial case (the angle footnote e records as NOT exercised):** on #98,
current round = Round 2; selected the **past Review Round 1** and used its
retained **Add Reviewer** panel control to add `u26reva` (Anonymous
Reviewer/Anonymous Author). The add **succeeded** — after submitting the Add
Reviewer form, Round 1's Reviewers panel listed `u26reva` with status "Request
Sent" alongside the pre-existing `u26revb`. A past (closed) round accepted a
new reviewer assignment.

**Observed vs claim:** the numbering/visibility/current-round halves hold
(Round 1 + Round 2 both visible, Round 2 current), but **"only the current
round can still change" is false**: a past round's participant/reviewer panels
are live and mutate the past round. Footnote e explicitly left this untested
("whether they still act on a past round was not exercised"); it acts.

**Verdict: WRONG-WITH-OBSERVED** (the immutability clause). The round's *status
sentence* did not change (past-round box still read "The submission has been
advanced to the next round of review"), but its reviewer set did. Not a
security issue — the manager is entitled to manage reviewers.

### Rule 2 — round creation ("Round 1 on Send for Review; later rounds only via Create New Review Round; never renumbered")
**Observed:** S1 #94 (never sent to review) carries no round and the menu shows
plain "Review". #95–#97 (one `sendExternalReview` decision) each opened Round 1.
#98 (seeded to two rounds) shows Review Round 1 + Review Round 2. The "Create
New Review Round" button is present on current rounds. **Verdict: HOLDS**
(construction-level; renumbering-after-cancel was not adversarially stressed —
that path is Rule 12, out of chunk).

### Rule 3 — "stage entry 'Review' expands into one entry per round … page title reads 'Review (Round {N})'"
**Observed:**
- OJS #98 menu: Review → Review Round 1, Review Round 2; landed on Round 2
  (current); header "WORKFLOW: REVIEW (ROUND 2)". #95/#96/#97 header "REVIEW
  (ROUND 1)".
- OMP #114/#115 menu: **Internal Review · External Review · Review Round 1**;
  header "WORKFLOW: EXTERNAL REVIEW (ROUND 1)" — glossary substitution
  ("Review" → "External Review") holds, and the separate Internal Review entry
  is present (OMP1).

**Verdict: HOLDS** on both apps.

### Rule 4 — status-box headings and the non-round texts
**Observed on OJS:**
- Current round: heading **"Round 1 Status"** (#95/#96/#97), **"Round 2 Status"**
  (#98). ✓
- Past round (#98 → Round 1): heading **plain "Status"**, body **"The
  submission has been advanced to the next round of review"** (no closing
  period, matching footnote d). ✓
- Not-yet-initiated (#94, Review stage selected before any round): heading
  **"Status"**, body **"The Review stage has not yet been initiated."** ✓
  (spec's "{stage} stage has not yet been initiated." with {stage}=Review).

**Verdict: HOLDS.** (Post-accept "currently in the {stage} stage" text was left
to the existing footnote-d probe.)

### Rule 5 — the status sentence table
**Sentences sampled live:**
- "Awaiting responses from reviewers." — #95 (declined + invited), #97
  (accepted), OMP #114. ✓ (declined reviewer ignored, an outstanding invite
  present.)
- "Waiting for reviewers to be assigned." — #98 Round 2 (no reviewers). ✓
- **"All reviews are confirmed and a decision is needed." — #96 (only reviewer
  declined) AND OMP #115 (only reviewer declined).** This is exactly finding
  **A4**, and it **reproduces on both OJS and OMP**: a round whose sole invited
  reviewer declined reports the all-confirmed sentence though no review exists.

**Verdict: HOLDS** for the sampled rows; A4 independently reproduced and
confirmed cross-app (the unmarked "identical in OJS and OMP" assumption holds
for this defect).

### Rule 6 — precedence ("'Awaiting recommendations…' appears only once the round has at least one reviewer record — a declined reviewer is record enough")
**Adversarial case:** #96 / OMP #115 have exactly one reviewer, declined. Both
render "All reviews are confirmed and a decision is needed." — **not** "Waiting
for reviewers to be assigned." So a declined assignment counts as a reviewer
record (past the no-assignments branch), matching Rule 6's "a declined reviewer
is record enough." **Verdict: HOLDS.** (The recommendation-precedence half —
reviewerless recommend-only round reading "Waiting for reviewers" — is
footnote-e's probe-P4 item 10 and needs the per-assignment recommendOnly flag,
not seedable here; not re-probed.)

---

## Cross-note for the Rule 11 owner (outside this chunk — not adjudicated here)

Observed while reading the decision cluster: on **#95** (reviewers = one
declined + one "Request Sent", none accepted/completed) the **"Cancel Review
Round" button was ABSENT**, whereas Rule 11 says Cancel is offered "only while
no reviewer of the round has accepted the invitation or completed a review."
#98 Round 2 (no reviewers) DID show Cancel. This suggests a *declined*
assignment also suppresses Cancel — worth the Rule-11 checker confirming
against `CancelReviewRound::canRetract()` (footnote f says canRetract is false
"when any assignment is confirmed or completed"; a declined assignment's effect
is the open question). Flagged, not verdicted — Rule 11 is C2's chunk.

---

## Proposed content (for the maintainer to fold into the spec on review — NOT applied here)

1. **Actors row "Open the review stage" + finding A5.** Replace "the stage's
   assignment dialog offers no assistant groups" with a precise statement: the
   Assign-Participant dialog offers no *production*-assistant groups
   (Copyeditor, Layout Editor, Proofreader), **but does offer the Funding
   coordinator** group (an `ROLE_ID_ASSISTANT` group with external-review
   access) on both OJS and OMP — so one assistant-level role does have a screen
   path onto the review stage. The Actors header "Assistant-level roles … no
   path in" should carve out funding accordingly. (Observed OJS + OMP,
   2026-07-31.)

2. **Rule 1 + finding note.** "only the current round can still change" is
   contradicted by Rule 10's own retained panel controls: adding a reviewer to
   a **past** round via its Add Reviewer control **succeeds** and mutates that
   past round (footnote e's untested angle, now exercised — OJS #98,
   2026-07-31). Recommend softening Rule 1 to "only the current round's
   *status/decision machinery* advances" (or documenting that past-round
   participant/reviewer panels remain live), and updating footnote e's "was not
   exercised" clause.

3. **Finding A4 app-scope.** A4 reproduces identically on OMP as well as OJS —
   the register entry (currently undated-for-app) can note "both apps".

## Security routing
No new over-entitlement observed in this chunk. The A3 register area was not
probed (per the C1 instruction). Nothing routed to the private security file.

## Blockers
None. All planned cases were driven live on both apps.
