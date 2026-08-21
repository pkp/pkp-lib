# U26 finalizer report (RUNBOOK step 4) — 2026-07-27

Spec updated in place: `docs/product/specs/review-stage-and-rounds.md`
(status stays `draft`). `GLOSSARY.md` unchanged — probes confirmed the
"deciding editor" definition as written; no coined term changed.

## Disposition per probe correction (accepted unless noted)

Group I:
- OPS absence confirmed both roles + API → footnote w updated to settled form. Accepted.
- OMP door labels ("Send to External Review" on both doors; "skip" only in "Accept and Skip Review") → OMP2 rewritten, scenario 12 updated. Accepted.
- Internal-door promotes no files (only revisions; empty round possible) → new register entry OMP3 (❓, lean defect), scenario 12 split by door. Accepted.
- `##common.help##` header string, 401-on-invalid-decision status, `localhost` 400s → rejected (out of feature / harness context / status codes stay out of body).

Group II:
- Panel titles + roster confirmed; "Author Response" is OJS-only → Rule 3/4 marked, new ✅ entry OMP5 (feature itself stays U30's, one line + pointer). Accepted.
- Reviewer-suggestions panel needs setting AND ≥1 suggestion; title "Reviewers Suggested by Author" → Rule 3 + Settings bullet rewritten (setting surface stays U31's). Accepted.
- Page heading "Workflow: Review (Round N)"; past-round heading plain "Status" → Rules 2, 5, 6 corrected. Accepted.
- Rule 5 narrowed to decision buttons; panel controls remain on past rounds. Accepted.
- Cancel Review Round also withdrawn after a *declined* invitation → Actors row, Rule 10 bullet, footnote e, scenario 9 updated (corroborated by group IV). Accepted.
- Recommend-only view details (three "Recommend *" buttons, post-record "Recommendation"/"Change decision" block; exclusivity = the side-column listing) → Rule 11 + scenario 8 enriched. Accepted.
- Author panel "Upload" offered at every status, refused outside the window with a raw refusal string → new entry A6 (🐞); A1's impact corrected (panel path still completes in A1's state). Accepted, rule-5 phrasing kept.
- A3 confirmed + Reviewers-row "Overdue" mitigation + pending-recommendations never masks overdue → A3 rewritten, basis probe. Accepted.
- A2 corroborated (author reads editor phrasing verbatim, both apps) → basis upgraded to probe; stays ❓. Accepted.
- Deciding-editor parity (unassigned JM ≡ assigned SE) → folded into footnote a. Accepted as evidence, not a body claim.

Group III:
- Author status wording = editor's verbatim both apps → A2 symptom text firmed. Accepted.
- Notifications panel gate = author-notify email actually sent (not "any decision") → Rule 14 + scenario 7 corrected. Accepted.
- Subject row is a dead anchor (mouse-only) → new entry A7 (🐞 user-visible), marker in Rule 14 + scenario 7. Accepted.
- Legacy URLs: round panel + dashboard index unreachable; submission link redirects carrying the submission through → footnote y updated. Accepted.
- Author's Reviewers panel appears only once an open review is *completed* → Rule 13 corrected as behavior; the probe's suggested ❓ entry ("should under-way reviews show?") rejected — spec-text fix, no as-built deviation.
- OMP shows no recommendation line (presses seed zero recommendation options) → new entry OMP4 (❓, lean defect — missing seeding call, classic silent divergence), Rule 13 + Settings + scenario 6 marked. Accepted.
- Editor-only comment absent from DOM; anonymous read refused server-side → folded into footnote g as evidence. Accepted.
- OMP's anonymous-denial wording nit ("not a valid reviewer") → rejected (server-message trivia, arguably U27/U28; stays in the report).
- Date-format / DOM class details → rejected (trivia).

Group IV:
- Cancel lifecycle confirmed end to end (menu, landing stages incl. all four OMP1/OJS cases, reviewer access withdrawn, both emails) → OMP1 basis probe; footnote o carries subjects + one-step collapse on reviewer-less rounds. Accepted.
- Cancelled round's file survives but no screen lists it → Rule 15 sentence qualified; no register entry (data-vs-screen nuance, invisible to users). Accepted at rule weight.
- New-round carry-over confirmed (copy, original stays in round 1) → Rule 16 tightened, footnote p. Accepted.
- Select-files modal: default lists review-stage files only; all-stages toggle; added file arrives unticked; untick leaves panel unchanged → Rule 17 rewritten, scenario 11 rewritten, new entry A8 (🐞 user-visible: release state invisible on panel). Accepted.
- Revised-version email: delivered subject "Revised Version Uploaded"; ONE message to all assigned editors incl. recommend-only; suppression per-submission, not per-day-per-editor → Side-effects bullet + footnote r rewritten. Accepted.
- Pending-revisions task DOES have surfaces (Tasks panel, My Submissions row, view counter) → old A6 entry REMOVED (not reproducing); Side-effects bullet now names the visible surfaces with ownership pointer; footnote s rewritten. Accepted (rewrite-or-drop: dropped as a finding, kept as behavior).
- Reviewer sees no review files without a grant (13.6) → deferred to U27/U36 probing, as the report itself suggests.

Group V:
- Minimum-reviews setting located ("Minimum Confirmed Reviews Required", Settings → Workflow → Review → Setup) → Settings bullet keeps the effect, hands the surface to U29 (one line + FEATURE-MAP pointer); footnote u settled. Accepted.
- Rule 9 rewritten per report (prompt line added above status; replacement only in the all-confirmed-below-minimum state; distinct minimum-met text); the two setting-gated status texts added to Rule 6's table. Accepted.
- A4 rewritten per report (stamp shows iff ≥1 assignment and all settled; empty round loses to waiting-for-reviewers) — stays ❓ with the empty-round lean. Accepted.
- "Move to Review" label recorded in Rule 18 (ownership U32/U34 noted). Accepted.
- Read-Review/Confirm modal contents → rejected (U27's surfaces).

## Register after the fold

13 entries: A1–A8, OMP1–OMP5 (IDs dense). Tally **5 🐞 / 5 ❓ / 3 ✅**.
New this pass: A6, A7, A8, OMP3, OMP4, OMP5. Removed: the invisible-task-flag
entry (did not reproduce). Basis is probe for all but A5 (code).

## Proposed content for orchestrator-owned files

**UNASSIGNED.md — dead-code candidates** (judged against probe evidence;
supported): `AFFW-701` (legacy author email list — superseded by the live
"Notifications" panel), `AFFW-703`, `AFFW-704` (authorDashboard round panel —
route returns not-found, no handler/role assignment), `GRID-011`, `GRID-024`
(its sub-grids — no other caller). NOT candidates: `AFFW-702` (read-message
page — live via the Notifications subject) and `GRID-010` (author open-review
attachments grid — live via Read Review); both were exercised on screen
2026-07-27.

**APP-GLOSSARY.md corrections**:
1. Workflow-decisions table, OMP cell: probes confirm the Submission-stage
   skip decision is labelled "Send to External Review" (identical to the
   Internal-Review-stage decision's label), alongside "Send to Internal
   Review" and "Accept and Skip Review". Suggested OMP cell: "Send to
   External Review (same label from Submission [skips internal] and from
   Internal Review; plus Send to Internal Review, Accept and Skip Review,
   internal recommend-only variants)".
2. OJS cell says "Send to Review"; the shared locale string is "Send for
   Review". The OJS button label was not read on screen this pass —
   recommend confirming live before editing the cell (on-screen names win).

**PROGRESS note (proposed)**: U26 spec finalized from 5 probe reports —
18 rules / 13 scenarios / 13 register entries (5🐞/5❓/3✅); invisible-task-flag
finding dropped (not reproducing), 6 entries added (incl. OMP
recommendation-seeding gap, empty-round internal door); Rules 9/17 + A4
rewritten from live behavior; next: lint gate (step 5).

## Low-confidence remainders

- "New editorial recommendations have been submitted." (partial-recommenders
  state) and "All reviews are confirmed and a decision is needed." at default
  settings were not directly observed — both rest on shared-code derivation
  (empty chains); acceptable under multi-app rule 1, flagged for step 8.
- OMP4's lean (defect vs intended) is the register call most worth a
  maintainer look; OMP3's likewise.
- Rule 17's "unticking withdraws it from reviewers" rests on the flag
  mechanism; what a *granted* reviewer actually sees is deferred to U27/U36.
