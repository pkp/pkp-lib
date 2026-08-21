# e2e Test Suite — Principles

The **test-authoring contract** for the Playwright e2e suite, and the design
record a clean-room rebuild starts from. Every test-writing session follows
this file. Paths here are relative to an app repo root (`lib/pkp/...` =
inside the submodule).

Related contracts: the campaign manual (loop, mission, invariants, budgets,
definition-of-done) is `lib/pkp/docs/e2e/process/RUNBOOK.md`; spec style
`lib/pkp/docs/e2e/process/TEMPLATE.md`; progress state
`lib/pkp/docs/e2e/tracking/PROGRESS.md`, never in conversation memory.
Harness layout and env facts: `harness.md`. Parity verdicts:
`lib/pkp/docs/e2e/tracking/parity-ledger.md` (append-only ledger).

**Terms.** "Scenario builder" (historically "Processor") = the PHP classes
behind `/api/v1/_test/*` (`PKPBootstrapSeeder`, `PKP*ScenarioBuilder`,
`ContextFactory` and their app subclasses). "Seed tag" = the unique per-test
token from `patterns.md` tag conventions.

**Scope.** Tests assert behavior through the screens, under RUNBOOK's "The
screen is the instrument": a test drives the UI as a signed-in role, including
navigating straight to a URL, and never asserts against a request the
application's own screens would not send. The `/api/v1/_test/*` endpoints are
the exception that proves it — harness plumbing for *getting to* a state
(A4), never the behavior under test.

The **legacy Cypress suite** still ships on this branch: out of scope — never
maintained, never run by the campaign, deleted only when the maintainer
decides the Playwright suite has replaced it.

## Why this suite exists

The Cypress suite was a serial fixture chain — tests depended on state left by
earlier specs, could not parallelize, and a mid-chain failure forced re-runs.
This suite is parallel-first: each test seeds its own state through the
test-only scenario endpoints.

## Architecture principles (A1–A9)

- **A1 — The isolation unit is the submission.** Tests create their own
  submissions via the scenario endpoint and never touch anyone else's. The
  base journal `publicknowledge` is **read-only** — no test mutates its
  settings, sections, categories, issues, or the 18 seeded users. Tests
  needing journal-level mutations create a **scratch journal** with a unique
  path.
- **A2 — Scenario builders must be accurate.** A seeded scenario leaves the
  same database state, fires the same hooks, and produces the same
  notifications as a user doing the equivalent through the UI/REST API. Any
  builder change requires a parity entry in `lib/pkp/docs/e2e/tracking/parity-ledger.md`
  before it merges.
- **A3 — Builder scope stays balanced.** Extend a builder only when multiple
  tests need the same state; one-off states are reached by driving the UI in
  the test that needs them. The spec schema should stay small enough to hold
  in your head — when in doubt, don't extend.
- **A4 — Seed via endpoint, drive the UI only for the behavior under test.**
- **A5 — No hard-coded waits.** Auto-waiting and web-first assertions; if an
  animation or debounce timer causes flake, shorten it at the source under
  test mode instead of sleeping. (The harness already disables animations
  globally — `harness.md`.)
- **A6 — Group assertions per scenario.** One seeded scenario can support
  several related assertions; don't pay seeding cost per assertion, and don't
  build mega-tests that obscure failures — one coherent behavior per test.
- **A7 — Tests are independent.** Arbitrary order, parallel workers; no test
  depends on another or leaves state that affects one. Mutations of shared
  singletons (site settings) restore state in-test; what can't be isolated
  runs in the serial project with an explicit note. **NEVER enrol a shared
  seeded user in a new role** — roles are global and leak into unrelated
  suites (this bit the build once); use throwaway users for any role-mutation
  probe.
- **A8 — Mailpit is shared** (across workers AND fleets) and **this install
  has no Mailpit tags** — nothing sets `X-Tags` (verified 2026-07-29; three
  probe sessions each rediscovered it the hard way). The only real scoping is
  a **unique throwaway recipient address** carrying the app and test
  (`u53top-omp@mail.test`); `find()`'s `contains` is a subject/body content
  marker, a supplement, never a substitute. Never `clearAll()` outside the
  serial infrastructure spec; pair every negative assertion with a positive
  control taken the same way, which also bounds the wait. API and mechanics:
  `scenarios.md`.
- **A9 — Globally-scanning operations run serially.** Scheduled tasks,
  site-level plugin toggles, site-settings mutations, cache clears, queue
  drains: serial project only (`playwright/tests/serial/`), which depends on
  the parallel projects and runs alone at the end.

## Organization

Tests are organized by feature — one spec file (or small set) per feature;
the feature list and budgets live in `lib/pkp/docs/e2e/tracking/PROGRESS.md`. Placement:
genuinely app-agnostic infrastructure → `lib/pkp/playwright/`; every feature
suite → its app's own `playwright/tests/`. Folders stay flat until ~25–30
specs make natural clusters obvious.

## Multi-app conventions (M1–M5)

What gets tested per app is owned by RUNBOOK "The multi-app rules"; the
authoring conventions:

- **M1 — Per-app suites, derived from the spec** (maintainer ruling
  2026-07-26). The spec lists common scenarios first, then app-specific ones;
  each app's suite implements every common scenario in that app's own context
  (its roles, users, stages, vocabulary — not an OJS transplant) plus its own
  specifics, in that app's repo. Duplication between app suites is acceptable
  — the spec is the maintained artifact; don't build sharing machinery. A test
  may name its own app's seeded users and stages directly.
- **M2 — The shared tree gates on capabilities, never app names.**
  `lib/pkp/playwright/` code uses `appContext.hasReviewStage` etc. and
  resolves personas through `appContext.seed.actors` (archetype →
  username-or-null). Capability names are canonical in
  GLOSSARY.md Part II §2 (`lib/pkp/docs/e2e/specs/GLOSSARY.md`): glossary row first, then the same key in
  all three `app.context.js` files.
- **M3 — Never write a test asserting a 🐞 register finding** (that freezes
  the defect as contract), and **never a test that demonstrates a potential
  security concern** — these repos are public; the finding goes to the
  maintainer's private file (RUNBOOK "What goes where") and the suite stays
  silent until the fix ships. A claim parked on an open ❓ is not a coverage
  gap; each suite's file header declares what it deliberately does not cover.
- **M4 — Absence tests** assert the surface is not offered AND pair every
  negative with a positive control taken the same way; an absence assertion
  against an async-filtered list must be bounded by that filter's own
  response.
- **M5 — Attribute failures by the seed tag** carried in the test's own data,
  never by row id (parallel writers make ids unstable).

## Scenario-endpoint design record (D1–D9)

The implementation may be rebuilt from scratch; these decisions must survive
any rebuild — each was earned by a concrete failure. (D6–D7 deliberately
duplicate facts documented elsewhere: this section is the rebuild seed and
must stand alone when everything else is scratched.)

- **D1 — A test-only API namespace** (`/api/v1/_test/*`) gated by a
  shared-secret header key from the *environment* — never a config default,
  never present in production. The key must actually reach PHP's environment
  under the server manager used (this silently failed once and broke all
  seeding).
- **D2 — Declarative, end-state scenario specs.** A seed request describes
  the state the test needs; the builder walks the application to it. Tests
  never script the journey to their starting point.
- **D3 — Builders invoke REAL application services** — the same repositories,
  hooks, mails and notifications the UI path uses — never hand-mirrored side
  effects. A mirrored `publish()` once normalized away a real cross-app
  permission difference; mirrored submissions lacked notification rows real
  ones create. Any deliberate deviation gets a parity entry reviewed before
  merge.
- **D4 — Failure hygiene.** A failed build must not leave half-created state
  (roll back or tag as orphan); an unsupported spec key must THROW, never be
  silently dropped (a silently-ignored reviewer block cost a real
  investigation).
- **D5 — Cross-app schema.** An app-neutral core (`context`, not `journal`)
  with app-specific concepts as declared OVERLAY properties. Shared builder
  code touches an app-only service ONLY gated on that app's overlay key, and
  never hard-codes a workflow stage id (a hard-coded initial stage once made
  every seeded OPS submission invisible). Per-app subclasses own the
  specifics.
- **D6 — Identity roster.** Role-keyed usernames (`manager.maya`,
  `editor.diana`, …), deterministic password rule (username doubled — mind
  client-side maxlength on login), one archetype map per app resolving
  archetype → username-or-null.
- **D7 — Harness facts worth keeping.** Per-user storage-state auth cache
  with a liveness probe; an `asUser()` fixture; a config factory
  parameterized by base port so fleets run side by side with per-worker port
  offsets; Postgres test DBs (Postgres strictness reproduces defects MySQL
  hides); a reset tool that forces a cold bootstrap.
- **D8 — DB-driver-agnostic** (maintainer ruling 2026-07-27). Harness code
  runs against ALL supported databases: app services and the query builder
  only, no raw driver-specific SQL; the single permitted driver dispatch is
  the reset tool's drop/recreate step. The local fleets *choose* Postgres;
  nothing may depend on it.
- **D9 — Never reach a state by editing the running configuration.** A
  config-dependent state (an expired invitation, a lapsed subscription) gets
  a schema key producing it for that one entity — editing
  `config.test.inc.php` mid-run is global across workers and fleets. Where
  the app offers no service call (it only stamps expiry forward), the builder
  may write the stored value directly, but the WINDOW stays the
  application's: read back what the app wrote and shift from there, so the
  seed still means "expired" when the configured window changes.

Per-feature scenario keys are documented as they land in `scenarios.md`
(LIVE surface) with their parity rationale in `lib/pkp/docs/e2e/tracking/parity-ledger.md` —
not here.

## Rebuild acceptance

A harness rebuild from this record is done when: bootstrap seeds green in all
three apps; a login smoke passes per fleet; the scenario endpoint seeds a
context and a staged submission in each app with one parity spot-check
against the equivalent UI path; the reset tool forces a cold bootstrap;
concurrent fleets keep Mailpit assertions scoped per A8; and the operational
names `harness.md` cites (reset script, test-key header, ports) match
the rebuilt harness — or harness.md is updated in the same commit.

## Bootstrap data policy

Base seed: `playwright/fixtures/bootstrap.js` (journal `publicknowledge`, 18
users, sections, categories, issues — documented in `users.md`). Richer
defaults are encouraged — enable what most real journals use, so tests
exercise representative configuration. A bootstrap change requires checking
every implemented spec against the new defaults — deliberately, not casually.

## Findings and changes — where they go

Owned by RUNBOOK "What goes where"; the authoring-side summary: app-code
changes and build blockers → `lib/pkp/docs/e2e/tracking/app-changes.md` (ledger); builder parity notes →
`lib/pkp/docs/e2e/tracking/parity-ledger.md`; product findings → the feature spec's Findings
register — and a test result that contradicts the spec (permissions included)
means the SPEC is wrong: report it to the register, never park it as a
skipped/`fixme` test or a "not covered" note. Commit discipline and budgets:
RUNBOOK (single home).
