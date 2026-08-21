# U26 Review stage & rounds — verification fold (RUNBOOK step 8, final stage)

Date: 2026-07-27. Spec edited in place:
`lib/pkp/docs/product/specs/review-stage-and-rounds.md` — frontmatter now
`status: verified`. Worklist: `verify-merge.md` (47 items).
`APP-GLOSSARY.md` updated per M47. Lint gate: **clean, exit 0**
(`node lint/lint-spec.mjs` — leak · vocab · glossary · register · links, zero
findings).

## Disposition per merge item

**A. Register entries**

- **M1 (A8)** — adjusted. Entry rewritten symptom-first around the narrowed,
  re-observable core: the panel shows no per-file state and the select
  modal's checkbox has no explained effect and does not decide reviewer
  access. Badge changed 🐞 → ❓ (the release-reading that made it a plain bug
  did not survive; what remains is a control whose purpose needs a product
  ruling) — maintainer call flagged below.
- **M2 (A6)** — applied. Rewritten to the decision-history gate (revision
  request / accept / new-round on the stage's newest round), including the
  post-accept completing case and the same-status-different-outcome fact.
  Stays 🐞 · user-visible. Actors "Upload a revision file" cell corrected to
  match.
- **M3 (A3)** — applied. Masking starts at the first recommendation, under
  "New editorial recommendations have been submitted."; the
  overdue-returns-on-state-change sentence dropped. Summary line reworded
  ("Recorded recommendations…").
- **M4 (A5)** — applied. Basis code → probe; footnote f-a5 now carries the
  record-side and display-surface observations. Entry text unchanged.
- **M5 (OMP4)** — adjusted. Rewritten per the observed settings screen: a
  press has no "Reviewer Recommendations" tab and no in-application way to
  create options — "unreachable end to end". Lean rewritten (defaults AND
  settings tab missing). Kept ❓ · user-visible (the impact vocabulary has no
  higher tier; the entry text now carries the severity) — flagged below.
- **M6** — applied. "Completion step" replaced with the step where the
  reviewer submits their review (footnote names step 3 "Download & Review"
  vs step 4 "Completion"); the author-sheet sentence now states the OJS
  condition (line appears exactly when the reviewer chose one) so it no
  longer reads as a flat app difference.
- **M7 (OMP1)** — adjusted. Round-1 scope added ("whenever Round 1 is
  cancelled"; later-round cancels stay in stage). Rather than flipping the
  landing verdict to ❓, I split the finding: the landing stays ✅ (the
  cascade is deliberate stage-roster parameterization) and the wizard-text
  mismatch became **new entry OMP6 · 🐞 · user-visible** (the wizard promises
  the Submission-stage landing a press does not keep), marked from Rule 15.
  The stale "Submission accepted." landing display is noted in OMP1 and
  covered by A10. Verdict split flagged below.
- **M8** — applied. New entry **A9 · ❓ · minor** (cancelled round's number
  reissued), paired with the Rule 1 text change (M17); footnote f-a9 carries
  ids and mechanism.
- **M9** — adjusted per the merge's lean, resolved as ❓. The When cell now
  says "stored only; the round shows Rule 5's stage line instead"; Rule 5
  gained the two stage lines; the claim is scoped to Review rounds (the OMP
  internal-round display is recorded). The register-entry question is
  resolved by **new entry A10 · ❓ · minor** with the display question stated
  for the team — cheap for the maintainer to strike if unwanted.
- **M10** — applied. Footnote f-a4: OMP reproduction of all four shapes +
  stamp survives a second return; footnote j: stored statuses are
  overwritten by later activity, not only outranked.
- **M11** — no-op confirm. The spec body never carried the code-derivation
  caveat (it lived in the step-4 report); footnote j now cites both
  recommendation lines as live-observed. Low-confidence item retired.

**B. Actors & permissions**

- **M12** — applied. Curate row split into the observed two bullets
  (participants: Section/Guest Editor + assistant groups that reach the
  stage, i.e. Funding Coordinator; unassigned: Journal Manager / Journal
  Editor / Site Administrator); footnote b carries the assign-dialog roster
  and the assistant-group facts.
- **M13** — applied. Journal Editor added to the Delete row (and to Rule
  10's Delete bullet); declined-state qualifier unchanged; footnote f
  updated with the live role list.
- **M14** — applied. Row names the real actions ("Update File Details" /
  "Delete") and the editorial-only "More Information".
- **M15** — applied. Participant definition rewritten so it cannot be read
  as "participant on the submission" (group must cover the Review stage;
  otherwise a no-access notice).
- **M16** — applied. Preamble line: OMP ships no Guest Editor group; the
  unprobed dialog groups (Translator, Volume Editor) stay unnamed.

**C. Rules & state**

- **M17** — applied (⚠ A9). — **M18** — applied ("in screen order", roster
  reordered). — **M19** — applied; the "was accepted" concern resolved by
  narrowing instead of a ❓: Accept is the stage's only forward exit, so the
  wording has no other path to describe (noted in footnote k). — **M20** —
  applied (reviewer row actions "Thank Reviewer" / "Revert Decision" named).
- **M21** — applied, and the "consider a ⚠" taken: **new entry A11 · ❓ ·
  minor** (the all-confirmed line shows with nothing confirmed), marked from
  the Rule 6 row; row 1 now keyed to "no reviewer assignment at all".
- **M22** — applied (needs settled reviews; empty round shows the waiting
  line). — **M23** — applied (chunk2's order incl. the returned-to-review
  stamp, ⚠ A3). — **M24** — applied (bounded to reviewer-activity statuses;
  per-round count; no prompt on past rounds — kept in Rule 9, Settings
  bullet untouched). — **M25** — applied (four always-offered, only Cancel
  conditional; revisions don't close it). — **M26** — applied to Rule 11 and
  scenario 8 (decision's label, with examples). — **M27** — applied
  (comma-joined line, no names, no dates; Participants list pointer).
- **M28** — applied (Rule 13 + Settings bullet both follow OMP4's rewrite:
  "no screen to add any").
- **M29** — adjusted: the review-form clause was **cut**, not kept. The
  template has no marker for "unverified", and a ❓ register entry would
  record a coverage gap, not a deviation. The mechanism stays in footnote g
  as provenance; the clause returns when U29/U28 probing exercises a
  form-based review. Flagged below.
- **M30** — applied (decision notifications only; submission-wide; date and
  time). — **M31/M32** — applied (chunk4 d1b replacement; duplicate-copy
  sentence included; ⚠ A8 relocated onto the checkbox claim). — **M33** —
  applied (U27 row owns which files each reviewer receives). — **M34** —
  applied (preselection qualified; stage selection deferred to U24).

**D. Scenarios & footnotes**

- **M35** — applied ("Record Decision"; press landing inline before the
  OMP1 marker; final step moved to its own round). — **M36** — applied
  ("OK" ×2, release phrases dropped, scenario-1-round precondition;
  seeding note in footnote s1). — **M37** — applied (Internal-door
  precondition, deltas-marked phrasing with OMP4/OMP5 markers, "No items
  found." / "No Items" quoted, "the submission's files"). — **M38** —
  applied with wording adjusted to spec voice: the in-app positive control
  (Workflow menu + Production decisions) with the journal contrast pointed
  at scenario 1, so no reader takes it as a cross-install instruction.
- **M39** — applied ("the only **decisions** offered…", header/panel
  parenthetical). — **M40** — applied (visual label order; OJS "Send for
  Review" added to footnote and to OMP2's entry). — **M41** — applied
  (footnotes q and f-a8 rewritten around the per-assignment grant). —
  **M42** — applied (footnotes c and f-a6 cite the add-gate policy and its
  decision list; the revision-required policy scoped to edit/delete). —
  **M43** — applied (f-omp3 notes the positive half was not driven).

**E. Belongs elsewhere** — **M44, M45, M46** — applied as directed: no U26
text or register change; proposals below. **M47** — applied to
`APP-GLOSSARY.md`: OJS cell now "Send for Review (decision; label
live-confirmed 2026-07-27)"; OMP cell reworded to the finalizer's proposal
(same label from both doors; Accept and Skip Review named).

**Counts: 38 applied · 9 adjusted (M1, M5, M7, M9, M11, M19, M21, M29,
M38) · 0 rejected.**

## Final register tally

**17 entries — 5 🐞 / 9 ❓ / 3 ✅.**
🐞 A1, A5, A6, A7, OMP6 · ❓ A2, A3, A4, A8, A9, A10, A11, OMP3, OMP4 ·
✅ OMP1, OMP2, OMP5. New this pass: A9, A10, A11, OMP6; A8 rebadged 🐞 → ❓;
A6, A3, OMP4, OMP1 rewritten. IDs dense; summary table mirrors entries;
lint register check passes.

## UNASSIGNED.md proposals (orchestrator writes)

1. **U35 (Stage participants)** — OMP recommend-only decision controls
   rendered but inert after "Recommend only" is flipped through the
   Participants panel's edit form; not reproducible when the flag is applied
   at assignment time. Needs a U35 probe before anyone states it as a
   defect (chunk4 "Unresolved" section). U26's display claims are
   unaffected.
2. **Shared side-modal chrome (no owning spec yet)** — the Help icon's
   screen-reader-only name renders as an untranslated placeholder on every
   page top bar and side modal of all three apps (locale key defined in no
   translation file). Two reports disagree on whether OJS is affected; the
   direct measurement (chunk4 d5) says all three apps — carry the conflict
   note. Also incidental: a template helper is registered pointing at a
   method that does not exist.
3. **U30 / mail templates** — OMP's "Request Revisions" author email invites
   the author to "submit your response" although OMP mounts no "Author
   Response" panel (OMP5); reconcile template with the press roster
   (chunk3 14.7).

## Maintainer attention first

1. **OMP4** (❓) — reviewer recommendations are unreachable end to end on a
   press: no seeded defaults AND no settings tab. The impact vocabulary
   tops out at "user-visible"; if the campaign wants a heavier tier, this is
   the entry that needs it.
2. **OMP1 kept ✅ + new OMP6 🐞** — the merge flagged OMP1's verdict; I
   resolved it by splitting: landing = intended parameterization (✅), the
   cancel wizard's description that denies the press landing = defect
   (OMP6). Confirm or overturn the split.
3. **A8 rebadged 🐞 → ❓** — the "release/withhold" reading collapsed under
   verification; the remaining symptom (an unexplained checkbox whose state
   nothing on the stage visibly consumes) is filed as a product question,
   not a plain bug.
4. **New ❓s A10 / A11** — accepted-status never displayed on a Review
   round; the all-confirmed line covering nothing-confirmed rounds. Both
   cheap to strike if the team calls them intended.
5. **M29** — Rule 13's "(or the author-visible parts of the review form)"
   clause was cut as unverifiable with current seeding; re-add once a
   form-based completed review is driven (U29/U28 territory).

Note for step-7 hygiene: scenarios 9, 11 and 12 changed labels, steps and
preconditions (now quoting "Record Decision" / "OK", scenario-11's
send-to-review precondition, scenario-12's internal-door precondition). The
app suites already drive the observed controls, but their scenario mapping
should be re-checked against the updated text on the next run.

Per the brief: nothing written to PROGRESS.md, atlas files, or
docs/e2e/app-changes.md.
