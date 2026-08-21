# Product Specification Campaign — Charter

The contract for this campaign: **why** it exists, **what** is in scope, and
the invariants every iteration must hold. **How** to run an iteration lives in
`RUNBOOK.md`; **how** to write a spec in `TEMPLATE.md`; **how** to write tests
in `lib/pkp/docs/e2e/PRINCIPLES.md`; live state in `PROGRESS.md` (the current
mode is its banner). Read this once per session as background.

## Mission

Document every OJS feature at the **business-logic level** — actors, fields,
rules, state, permissions, side effects — precisely enough that the feature
could be reimplemented from the spec alone, in language a product owner or QA
person reads without a developer. Audience: QA, developers, AI agents, and
the test suite built from each spec's canonical scenarios.

## Scope

**All three apps, OJS-anchored** (operative rules: RUNBOOK "The multi-app
rules"). In scope: any feature reachable in OJS — one spec covers its
behavior in OJS, OMP and OPS, and tests are written for each app.
**Maintainer scope extension (2026-07-27)**: the OMP catalog's reader and
management surfaces (catalog browse, book landing page, catalog management)
are in scope as OMP-specific features. Out of scope, dropped not parked: the
catalog's object model and surfaces OJS never exposes (chapters and
publication-format authoring, ONIX/codelists, marketing/supply-chain/direct
sales, `NOTIFICATION_TYPE_BOOK_*`, internal-review-stage decision variants,
OMP/OPS-only Vue managers unwired in `WorkflowPageOJS`). Extending further is
a separate maintainer decision.

**Format: Markdown, not HTML** — specs are reviewed raw and in diffs; inline
HTML only where structure needs it (`<br>` in cells, `<sup>` footnotes).

## Method

Enumerate mechanically first, then document, then map coverage — "did we miss
a feature?" must be a grep, not a judgment call.

1. **Phase 0 — surface atlas** (`atlas/`, one sweep per modality — routes,
   grids, vue, api, notif, mail, jobs, settings, plugins, cli, plus the
   per-screen affordance sweeps `affordances-{workflow,management,user,reader}.md`)
   emitting **atoms**: `<MOD>-<seq>` ID · which apps expose it · a stable
   code pointer (symbol, not line) · one line of what it is. **Complete**
   (2026-07-28): 2,163 atoms; results in `FEATURE-MAP.md` (every atom
   assigned to a feature U1–U70) and `UNASSIGNED.md` (parked atoms).
   `Claimed by:` markers are added per feature by the orchestrator only.
2. **Phase 1 — feature specs** (`specs/*.md`), written per TEMPLATE to the
   RUNBOOK loop, claim-checked, claiming their atoms. **Current phase.**
3. **Phase 2 — coverage crosswalk**: every spec scenario mapped against the
   test suite → covered / gap / unit-test-territory / accept-untested.

## Invariants

- **Atom claim invariant**: every atlas atom ends up claimed by exactly one
  spec, OR parked in `UNASSIGNED.md`, OR marked out-of-scope in its sweep
  file with a reason. The unclaimed count is the campaign's completeness
  metric.
- **Never force-fit**: a wrong grouping is worse than a deferred one — push
  poor fits onto `UNASSIGNED.md`. Group by **user intent** ("what would a
  journal manager call this?"), never by code-module boundaries. Litmus:
  would a QA person test these rules together?
- **As-built AND intent — the spec is the source of truth**: specs document
  what the code actually does. Behaviour that is internally inconsistent,
  loses data, contradicts a UI affordance, or would genuinely surprise a
  product owner gets a ⚠ + a Findings-register entry with the author's
  bug-vs-intended call — non-blocking; the team settles calls on review. A
  rule that is merely strict is usually intended: write it plain plus a ❓
  entry with a stated lean; never assert a suspected intent the code doesn't
  prove. A spec that silently transcribes bugs as requirements is poison for
  QA. There is no separate bug ledger — any all-bugs view is computed from
  the registers. ONE routing exception: potential security weaknesses go to
  the maintainer's private file (operative rule: RUNBOOK "What goes where").
- **Verified, not just written**: every spec passes a claim check (RUNBOOK
  step 8) and a readability pass. Ambiguous rules are probed **live**, never
  guessed from code.
- **Liveness before documentation**: code existing is not evidence the
  feature exists — the apps carry superseded, unreachable surfaces. Establish
  a surface is reachable in the current UI before documenting it; record
  unreachable atoms as dead-code candidates in `UNASSIGNED.md`. Where a
  legacy path and a Vue path are both live for the same job, document both
  and say which is primary.
- **The screen is the instrument** (operative rules: RUNBOOK "What this work
  is"): evidence comes from using the screens as a signed-in user — including
  typing a URL directly, which is how we learn whether a screen guards
  itself. Backend code is read for mechanism and provenance, never exercised
  by constructing a request the screens would not send; such questions go to
  the deferred queue. What stays firmly in scope is the disagreement a user
  can see — a control that fails or is refused, a control missing, a screen
  rendering for someone it should not, a message contradicting what happened.
- **Business language, one statement per fact**: the body reads as a
  functional spec for PO/QA; every code symbol, probe result and seeded
  account lives in `<sup>` footnotes and Reference blocks. Style rules,
  anchor format, and the lint gate: TEMPLATE (single home).

## Standing maintainer rulings

Folded into TEMPLATE rules 8–10 (single home): **variance-based ownership**
(mechanics specified once, in the mechanism's home feature; context features
own the deltas), **one shared workflow screen, author included** (never split
a stage into editor-view and author-view features), and **glossary
discipline** (`GLOSSARY.md` defines the reader-facing vocabulary; on-screen
names win).

## Definition of done

- **Per spec**: RUNBOOK's Definition of done (single home).
- **Campaign** (single home — RUNBOOK points here): unclaimed atom count = 0
  (claimed / parked-with-reason / out-of-scope-with-reason); every PROGRESS
  row `done` or `parked`; each app's full suite within RUNBOOK's Budget &
  ceilings; parked list + register highlights reported to the maintainer.
- "Recreatable from the spec" sets the altitude; the lines above are the
  checkable bar.

## Operating rules

- State lives in these files, not in conversation — any session resumes from
  disk.
- No application-code changes unless a defect blocks tests from running green
  — recorded in `lib/pkp/docs/e2e/app-changes.md`, and only there. Product
  findings never go there.
- **Live-probe etiquette** (invariant): probes never mutate shared seeded
  state. Operational rules: RUNBOOK "Ops & campaign safeguards" and
  `lib/pkp/docs/e2e/dev/harness.md`.
