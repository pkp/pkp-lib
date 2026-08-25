# OJS Product Specification Campaign — Charter

The contract for this campaign: **why** it exists, **what** is in scope, and the
invariants every wave must hold. **How** to run an iteration lives in `RUNBOOK.md`;
**how** to write a spec lives in `TEMPLATE.md`; **how** to write tests lives in
`docs/e2e/PRINCIPLES.md`; live state lives in `PROGRESS.md`. Started 2026-07-02;
the current mode always lives in `PROGRESS.md`'s banner.

## Mission

Document every OJS feature at the **business-logic level** — actors, fields, rules,
state, permissions, side effects — precisely enough that the feature could be
reimplemented from the spec alone, in language a product owner or QA person reads
without a developer. Audience: QA, developers, AI agents, and the test suite built
from each spec's canonical scenarios.

## Scope

**All three apps, OJS-anchored** (the operative rules live in `RUNBOOK.md`
"The multi-app rules" and `TEMPLATE.md`). In
scope: any feature reachable in OJS — one spec covers its behavior in OJS, OMP and
OPS, and tests are written for each app. **Maintainer scope extension
(2026-07-27)**: the OMP catalog's reader and management surfaces — catalog
browse, the book landing page, catalog management (featured/new releases) —
are IN scope as OMP-specific features (evidence:
`.reports/phase0-feature-map/probe-omp-catalog.md`; they are OMP-own
machinery). Still out of scope, dropped not parked: the catalog's object
model and the other surfaces OJS never exposes (chapters and
publication-format authoring, ONIX/codelists, marketing/supply-chain/direct
sales, `NOTIFICATION_TYPE_BOOK_*`, `*_INTERNAL` review-stage decisions,
OMP/OPS-only Vue managers unwired in `WorkflowPageOJS`) — extending further is
a separate maintainer decision.

**Format: Markdown, not HTML.** Specs are reviewed raw and in diffs; inline HTML only
where a structure needs it (`<br>` in cells, `<sup>` footnotes, rare `<details>`).

## Method

Enumerate mechanically first, then document, then map coverage — "did we miss a
feature?" must be a grep, not a judgment call (round 1's single exploration pass
demonstrably missed features):

1. **Phase 0 — surface atlas** (`atlas/*.md`), covering ALL THREE apps:
   mechanical sweeps, one per modality, emitting **atoms**. Completeness over
   depth; no analysis and no liveness judgment in sweeps (dead code surfaces
   in Phase 1 → `UNASSIGNED.md`). Overlap between modalities is fine — the
   same surface in two sweeps is harmless (claiming dedups it); a missed
   surface is unrecoverable.
   **Atom anatomy**: `<MOD>-<seq>` ID (dense per modality, never renumbered) ·
   which apps expose it · a stable code pointer (symbol, not line) · one line
   of what it is. `Claimed by:` is added later, by the orchestrator only.
   **Required modality baseline** (Phase 0 may extend it; the final list is
   recorded in the atlas): the apps' surface has two layers and both are
   swept —
   - *Entry points*: **routes** (page handlers + ops), **grids** (legacy
     `controllers/` handler ops — the easiest surface to miss), **vue**
     (ui-library pages/managers/modals), **api** (REST controllers + app
     overlays), **notif** (notification types), **mail** (mailables + email
     templates), **jobs** (queued + scheduled), **settings** (site/context
     schemas, `config.inc.php`, plugin settings), **plugins** (bundled trees),
     **cli** (`tools/`).
   - *Within-screen*: **aff** — per-screen affordances (controls/actions from
     templates + components; `atlas/affordances.md`). This is the layer the
     permission documentation and the RUNBOOK loop consume (step 2 and the
     per-feature definition of done depend on affordance atoms existing).
2. **Phase 1 — feature specs** (`specs/*.md`): written per `TEMPLATE.md` to the
   RUNBOOK loop, claim-checked, claiming their atoms.
3. **Phase 2 — coverage crosswalk**: every spec scenario mapped against the test
   suite → covered / gap / unit-test-territory / accept-untested.

## Invariants

- **Atom claim invariant**: every atlas atom ends up claimed by exactly one spec, OR
  parked in `UNASSIGNED.md`, OR marked out-of-scope in its sweep file with a reason.
  The unclaimed count is the campaign's completeness metric.
- **Never force-fit**: a wrong grouping is worse than a deferred one — push poor fits
  onto `UNASSIGNED.md`. Group by **user intent** ("what would a journal manager call
  this?"), never by code-module boundaries. Litmus: would a QA person test these rules
  together?
- **As-built AND intent — the spec is the source of truth**: specs document what
  the code actually does. Behaviour that is internally inconsistent, loses data,
  contradicts a UI affordance, or would genuinely surprise a product owner gets a
  ⚠ + an entry in the spec's **Findings register** (TEMPLATE) with the author's
  bug-vs-intended call — non-blocking; the team settles calls on spec review. A
  rule that is merely strict is usually intended: write it plain plus a ❓ register
  entry with a stated lean, never assert a suspected intent the code doesn't
  prove. A spec that silently transcribes bugs as requirements is poison for QA.
  There is no separate bug ledger: any all-bugs view is computed from the
  registers.
- **Verified, not just written**: every spec passes a **claim check** (RUNBOOK
  step 8 — take our own permission and state rules and look for the case that
  shows them wrong, liveness included) and a readability pass. Ambiguous rules
  are probed **live** on the test environment, never guessed from code.
- **Liveness before documentation**: code existing is not evidence the feature exists
  — OJS carries superseded, unreachable surfaces. Establish a surface is reachable in
  the current UI before documenting it; record unreachable atoms as dead-code
  candidates in `UNASSIGNED.md` (a campaign deliverable). Where a legacy path and a
  Vue path are both live for the same job, document both and say which is primary.
- **The screen is the instrument** (maintainer, 2026-07-28 — operative rules in
  `RUNBOOK.md`): the reader interacts with the UI, so the spec documents what
  each role sees and can do on screen, and what the application does when they
  do it. Evidence comes from using the screens as a signed-in user — including
  typing a URL directly to reach one, which is how we learn whether a screen
  guards itself. Backend code is read for mechanism and provenance; it is never
  exercised by constructing a request the screens would not send. Whether the
  server would accept something the UI never offers belongs to a separate
  process, not this campaign — the questions are parked on the deferred queue
  (RUNBOOK) and nothing here reads them back. What stays firmly in scope is the
  disagreement a user can see: a control offered that fails or is refused, a
  control missing where the role should have it, a screen that renders for
  someone it should not, a message that contradicts what happened. Those are
  ⚠ register entries and they are the deliverable.
- **Business language, one statement per fact**: the spec body reads as a functional
  spec for PO/QA; every code symbol, probe result and seeded account lives in `<sup>`
  footnotes and the Reference blocks. The non-negotiable style rules, the anchor
  format (stable symbols, never line numbers) and the mechanical lint gate are defined
  in `TEMPLATE.md` — the single home for spec style.

## Standing maintainer rulings

Folded into `TEMPLATE.md` rules 8–10 (single home): **variance-based
ownership** (mechanics specified once in the mechanism's home feature; context
features own the deltas), **one shared workflow screen, author included**
(never split a stage into editor-view and author-view features), and the
**glossary discipline** (`GLOSSARY.md`; on-screen names win). They bind as
written there.

## Definition of done

- **Per spec**: the operational checklist is `RUNBOOK.md`'s Definition of done
  (single home).
- **Campaign** (single home — RUNBOOK points here): unclaimed atom count = 0
  (claimed / parked-with-reason / out-of-scope-with-reason); every PROGRESS row
  `done` or `parked` (row states defined in `PROGRESS.md`'s header); each
  app's full suite within RUNBOOK's Budget & ceilings (single home for the
  numbers); parked list + register highlights reported to the maintainer.
- "Recreatable from the spec" sets the altitude; the lines above are the
  checkable bar.

## Operating rules

- State lives in these files, not in conversation — any session resumes from disk.
- No application-code changes unless a defect blocks tests from running green
  (race conditions and the like): those changes — and only those — are recorded
  in `docs/e2e/app-changes.md`. Product bug findings never go there; they live
  in the specs' Findings registers.
- **Live-probe etiquette** (invariant): probes never mutate shared seeded state.
  The operational rules live in RUNBOOK's "Ops & environment safeguards" and the
  `ojs-playwright-tests` skill.
