# U26 Review stage & rounds — author report (blind rebuild, 2026-07-27)

Spec draft: `docs/product/specs/review-stage-and-rounds.md` (status: draft).
Probe list: `.reports/u26/probe-list.md` (17 items). Glossary created
(`GLOSSARY.md`) with one coined term: **deciding editor**.

## Evidence base (all produced by this build)

- Atlas atoms (FEATURE-MAP row U26) fixed the surface inventory: panels,
  guards, grids, mail/notif/setting atoms. Every atom is claimed in the
  frontmatter and mapped in "Reference — entry points".
- Three code sweeps (this session): (1) Vue workflow configs + FileManager +
  round navigation/status display; (2) backend round model, status machine,
  decision lifecycle, notifications, revised-version mail, grids,
  author-read-review; (3) cross-app subclass-chain check (RUNBOOK rule 8)
  across ojs/omp/ops.
- Chain-check highlights carried into footnotes w/x: ReviewRound(DAO), all
  seven review grid handlers, NewExternalReviewRound, CancelReviewRound,
  RevisedVersionNotify, PendingRevisionsNotificationManager, reviewRound.json
  — empty chains in all three apps; OMP's external-review UI block and
  decision roster are the OJS ones verbatim; OPS absence is positively
  enforced at stage/decision/file-stage layers (table + locale keys still
  ship — absence written as an install fact).

## Claims resting on code derivation only (probe items cover each)

- Every affordance/label claim (panel titles, button presence per state,
  read-action label) — probe groups II–III.
- Register A1 (upload-button gap), A2 (author phrasing), A3 (recommendation
  mask), A4 (returned-to-review mask), A6 (invisible task flag), A7
  (read-review server-side acceptance — permission class, probe item 16).
- Cancel-round landing stages incl. OMP1; new-round carry-over; select-files
  semantics (probe group IV).
- Minimum-reviews setting surface unknown from code pass — probe item 15.
- Author "Revisions Uploaded" panel Upload control vs Rule 12's window
  (footnote c open question) — probe item 6.

## Open ownership questions (for the orchestrator/maintainer)

1. **Minimum-reviews setting**: if its admin surface is Settings → Workflow →
   Review, the Settings bullet may belong to U29 with U26 keeping the
   status-line effect (rule 8 variance split). Probe item 15 flags it.
2. **AFFW-326 / AuthorResponse**: the round record carries the author
   response and a response-requested flag; I treated ALL of it as U30's and
   only mounted the panels (Rules 3–4). If U30's badge widens to OMP
   (FEATURE-MAP note), nothing here changes.
3. **Dead legacy atoms**: AFFW-701, 703..704, GRID-011, GRID-024 are
   unreachable (footnote y evidence: unrouted op, redirecting author
   dashboard, no callers). Proposal: mark as dead-code candidates in
   UNASSIGNED.md while U26 keeps the claim for bookkeeping — orchestrator's
   write. AFFW-702 and GRID-010 are LIVE via side modals.
4. **APP-GLOSSARY nit**: the workflow-decisions table says OJS "Send to
   Review"; the shared locale string is "Send for Review" (OMP overrides to
   "Send to External Review"). The spec avoids the label (entry decision is
   U25/U34 territory); probe item 2 records OMP's labels — suggest fixing the
   glossary cell from whichever probe confirms.
5. **Recommendation statuses**: `recommendationMadeByYou` locale string
   exists but is unreturned by the status mapping — left out of the spec
   (dead string, not register-worthy).

## Proposed PROGRESS row note (orchestrator writes)

> U26 in_progress — draft spec + 17-item probe list (blind rebuild); 7
> register candidates (3🐞/4❓) all code-basis, pending probes; GLOSSARY.md
> created (deciding editor).

## Proposed app-changes note (ONLY if test authoring later trips on it)

Round-status notices (NOTIF-029) have no display surface and the legacy
author-dashboard round panel is unreachable — assert round status via the
workflow screen / submission API `reviewRounds[].statusId`, never via
notifications. (Not filed now: no test has been blocked yet.)

## Model note

Authoring done on the main-session model (Fable-pinned role); code sweeps
delegated to three Explore subagents; no probes executed, no browser driven.
