# OJS e2e Test Suite — Principles

> **Status 2026-07-26:** the implementation this contract describes was
> deleted in the full campaign reset (git history keeps it; it is not read
> back). This file is the contract + design record the rebuild follows — file
> paths below name where things live once rebuilt, not what exists today.
> The **legacy Cypress suite** still ships on `e2e_ng` (it came back with the
> fresh upstream main): it is OUT OF SCOPE — never maintained, never run by
> the campaign, deleted only when the maintainer decides the Playwright suite
> has replaced it.

The **test-authoring** contract for the Playwright e2e suite. Every test-writing session
follows these principles. The campaign's operating loop, test budget, and per-feature
definition-of-done live in `docs/product/RUNBOOK.md`; the spec-style rules live in
`docs/product/TEMPLATE.md`; the campaign invariants live in `docs/product/CHARTER.md`
(the spec-driven build: atoms → features → specs, under `docs/product/`). Progress state lives in
`docs/product/PROGRESS.md`, never in conversation memory — re-running the same prompt must
always resume correctly.

**Scope note (2026-07-28).** Tests assert behavior through the screens, under
CHARTER's "the screen is the instrument": a test drives the UI as a signed-in
role, including navigating straight to a URL. It never asserts against a request
the application's own screens would not send. The `/api/v1/_test/*` scenario
endpoints are the exception that proves it — they are harness plumbing for
*getting to* a state (principle 4), never the behavior under test.

## Why this suite exists

The legacy Cypress suite was a serial fixture chain: tests depended on state left by
earlier specs, could not run in parallel, and a failure mid-chain forced re-running
everything before it. This suite replaces that with parallel-first Playwright tests where
each test seeds its own state through test-only scenario endpoints
(`/api/v1/_test/scenarios/*`).

## Architecture principles

1. **Isolation unit is the submission.** Most tests create their own submission(s) via the
   scenario endpoint and never touch anyone else's. The shared base journal
   `publicknowledge` (seeded by `playwright/fixtures/bootstrap.js`) is **read-only**: no test
   may mutate journal-level settings, sections, categories, issues, or the 18 seeded users.
   Tests that need journal-level mutations create a **scratch journal** via
   `POST /api/v1/_test/scenarios/context` with a unique path.
2. **Scenario endpoints must be accurate.** A seeded scenario must leave the same database
   state, fire the same hooks, and produce the same notifications as a user performing the
   equivalent steps through the UI/REST API. Any change to a Processor requires a parity
   entry in `docs/e2e/scenario-processor-audit.md` (recreated with the harness
   rebuild) before it merges.
3. **Endpoint scope stays balanced.** Extend a Processor only when multiple tests need
   the same state. One-off or rare states are reached by driving the UI inside the test
   that needs them. The scenario spec schema should stay small enough to hold in your head;
   when in doubt, don't extend.
4. **Seed via endpoint, drive UI only for the behavior under test.** Getting *to* the state
   is the endpoint's job; exercising the state is the test's job.
5. **No hard-coded waits.** Use Playwright auto-waiting and web-first assertions. If an
   animation or debounce timer causes flake, shorten the store-side timer under test mode
   instead of sleeping.
6. **Group assertions per scenario.** One seeded scenario can support several related
   assertions in one test. Don't pay scenario-seeding cost per assertion; equally, don't
   build mega-tests that obscure failures — a test should still verify one coherent behavior.
7. **Tests are independent.** Specs run in parallel workers in arbitrary order; no test may
   depend on another test having run, nor leave state that can affect any other test. Each
   test creates what it needs (own submission, own users, scratch journal) and asserts only
   against state it created. Mutations of shared singletons (site settings) must restore
   state within the test; anything that cannot be isolated that way runs in a dedicated
   serial project with an explicit note. **NEVER enrol a shared seeded user in a new role**
   — that persists a global role other suites depend on (this bit the build once: a test
   made a seeded section editor a manager of a scratch journal and it leaked into an
   unrelated permission test). Use dedicated throwaway users for any role-mutation probe.
8. **Mailpit is shared.** Never `clearAll()` outside the dedicated serial infrastructure
   spec. **Scope every assertion by a UNIQUE THROWAWAY RECIPIENT ADDRESS** — that is the
   only scoping this install actually supports. Mailpit's own *tag* facility is unused
   here: nothing in the apps sets `X-Tags`, `GET /api/v1/tags` returns `[]` and every
   message carries `Tags: []`, so "scope by the per-app tag" is not an executable
   instruction (verified 2026-07-29; three probe sessions each discovered this the hard
   way). What `pkpMail.find({to, contains})` calls a tag is a **content marker** — a
   substring searched in the subject/body — which only helps when the test controls some
   text in the message; it is a supplement to the recipient scope, never a substitute.
   Give the recipient a distinctive per-test address (`u53top-a@mail.test`), and pair
   every negative assertion ("no email sent") with a positive control message taken the
   same way, which bounds the wait.
9. **Globally-scanning operations run serially.** Scheduled tasks (reviewer/editorial
   reminders), site-level plugin toggles, site-settings mutations, and cache clears affect
   state across all journals and workers; they live in a dedicated serial Playwright project
   (`playwright/tests/serial/`), never in parallel specs. The serial project depends on the
   parallel app project, so it runs alone at the end.

## Organization

- **Tests are organized by feature**, one spec file (or a small set) named after the
  feature; the feature list and per-feature test budget live in `docs/product/PROGRESS.md`.
- **Placement rule:** the genuinely app-agnostic layer (base fixtures, POMs,
  bootstrap/smoke specs) → `lib/pkp/playwright/`; each app's feature suites →
  that app's own `playwright/tests/`. Folders stay FLAT until ~25–30 specs
  exist and natural groupings are obvious; clusters then emerge as one
  refactor commit (skill convention — don't pre-invent taxonomy).

## Multi-app conventions (OJS · OMP · OPS)

The three fleets run side by side (OJS 8000 / OMP 8100 / OPS 8200; Postgres test DBs
`ojs_test` / `omp_test` / `ops_test`; same scenario endpoints). What gets tested per app
is owned by `docs/product/RUNBOOK.md` "The multi-app rules"; the authoring conventions
are:

1. **Per-app suites, derived from the spec** (maintainer ruling, 2026-07-26): the
   spec lists its COMMON scenarios first, then app-specific ones. Each app's suite
   implements every common scenario in that app's own context — its roles, seeded
   users, stages and vocabulary, not a literal OJS transplant — plus that app's
   specific scenarios, written in that app's own repo
   (`playwright/tests/<feature>.spec.js`). Duplication between app suites is acceptable — the spec is the
   maintained artifact; don't build sharing machinery to keep tests dry. A test may
   name its own app's seeded users and stages directly.
2. **The shared `lib/pkp/playwright/` tree** keeps the genuinely app-agnostic layer:
   base fixtures, POMs, bootstrap/smoke specs. Code there gates on capabilities from
   `support/app.context.js` (`appContext.hasReviewStage`), never app names, and
   resolves personas through `appContext.seed.actors` (archetype →
   seeded-username-or-null) — an archetype can be null on an app. Capability names are
   canonical in `APP-GLOSSARY.md`: glossary row first, then the same key in all three
   `app.context.js` files.
3. **Never write a test asserting a 🐞 register finding** — that freezes the defect as
   contract; the register entry is its record. A claim parked on an open ❓ is not a
   coverage gap; each app suite's file header declares what it deliberately does not
   cover.
4. **An absence test** asserts the surface is not offered AND pairs every negative with
   a positive control taken the same way; an absence assertion against an async-filtered
   list must be bounded by that filter's own response (the list analogue of the
   negative-mail rule).
5. **Concurrent fleets share Mailpit** — scope by a unique throwaway recipient address
   that carries the app in it (`u53top-omp@mail.test`), not by a Mailpit tag: this
   install has none (principle 8). Attribute failures by the seed tag carried in the
   test's own data, never by row id (parallel writers make ids unstable).

## Scenario-endpoint design record

The concrete implementation may be rebuilt from scratch; these are the design
decisions that must survive any rebuild (each was earned the hard way):

1. **A test-only API namespace** (`/api/v1/_test/*`), gated by a shared-secret
   header key from the environment — never a config default, never present in a
   production install. The key must actually reach PHP's environment under the
   server manager used (this silently failed once and broke all seeding).
2. **Declarative, end-state scenario specs.** A seed request describes the state
   the test needs (a context; users with roles; a submission at stage N with
   review rounds, decisions, files, discussions, publications) and the builder
   walks the application to that state. Tests never script the journey to their
   starting point.
3. **The builder invokes REAL application services** — the same repositories,
   hooks, mails and notifications the UI path uses — and never hand-mirrors
   their side effects. A re-implementation that mirrors production behavior
   hides exactly what tests exist to find: a mirrored publish() once normalized
   away a real cross-app permission difference, and scenario-seeded submissions
   lacked notification rows real submissions create. Any deliberate deviation
   gets a parity entry reviewed before merge.
4. **Failure hygiene**: a failed scenario build must not leave half-created
   state behind (clean up or tag as orphan); an unsupported spec key must THROW,
   never be silently dropped (a silently-ignored reviewer block cost a real
   investigation).
5. **Cross-app schema**: an app-neutral core (`context`, not `journal`) with
   app-specific concepts as declared OVERLAY properties (sections/issues/galleys
   OJS-side; series/publication formats OMP-side; internal/external key on
   review rounds). Shared builder code may touch an app-only service ONLY when
   gated on that app's overlay key, and must never hard-code a workflow stage id
   (a hard-coded initial stage once made every seeded OPS submission invisible).
   Per-app subclasses own the app specifics.
6. **Identity roster**: role-keyed usernames (`manager.maya`, `editor.diana`,
   `sectioneditor.ana`, …), deterministic password rule (username doubled —
   mind client-side maxlength on login), one shared archetype map per app
   resolving archetype → username-or-null where a role doesn't exist.
7. **Harness facts worth keeping**: per-user storage-state auth cache with a
   liveness probe; an `asUser()` fixture for multi-actor tests; a config
   factory parameterized by base port so app fleets run side by side
   (8000/8100/8200) with per-worker port offsets; Postgres test DBs (Postgres
   strictness reproduces real defects MySQL hides); a reset tool that forces a
   cold bootstrap.
8. **DB-driver-agnostic** (maintainer, 2026-07-27): harness code — seeding
   endpoints, bootstrap, tooling — must run against ALL supported databases:
   app services and the query builder only, no raw driver-specific SQL; the
   single permitted driver dispatch is the setup/reset tool's drop/recreate
   step, keyed off the configured driver. The local fleets *choose* Postgres
   for strictness; nothing may depend on it.
9. **Never reach a state by editing the running configuration.** A scenario
   that needs a config-dependent state (an invitation past its validity
   window, a lapsed subscription) gets a schema key that produces it for that
   one entity. Editing `config.test.inc.php` mid-run is global, hits every
   parallel worker and both other fleets, and cannot survive in a retained
   suite. Where the application itself offers no service call for the state —
   it only ever stamps expiry dates *forward* — the builder may write the
   stored value directly, but the WINDOW stays the application's: read back
   what the app just wrote and shift from there, so the seed still means
   "expired" when the configured window changes.

## Scenario keys added per feature

The step-2 core schema is deliberately minimal (design record 2 above) and
grows one key at a time, under principle 3: extend only when several tests
need the same state. Full per-key reference lives in the `ojs-playwright-tests`
skill (`scenarios.md`); the keys themselves are recorded here as they land.

- **`invitations[]`** on `POST scenarios/context` (U6, 2026-07-28, all three
  apps) — user-role invitations already sent in the scratch context:

  ```js
  invitations: [
      {email: 'newcomer@example.org', roles: ['sectionEditor'],   // no account yet
       givenName: 'Nadia', familyName: 'Newcomer', country: 'CA'},
      {user: 'someone.existing', roles: ['externalReviewer']},    // existing account
      {email: 'lapsed@example.org', roles: ['reader'], status: 'expired'},
  ]
  ```

  Exactly one of `email` or `user` names the recipient. `roles` is required and
  uses the app's own scenario role keys. Optional: the newcomer's
  `givenName`/`familyName`/`affiliation`/`country` (prohibited for an existing
  account, as in the app), `masthead` (default true), `inviter` (username;
  defaults to the site admin), `status: 'pending'|'expired'` (default pending).
  The builder walks the Invite-a-user wizard's own add → populate → invite
  calls, so nothing is written by hand and a refused step throws with the app's
  validation errors. The response echoes `{id, status, email, userId, roles,
  invitedAt, expiryDate, key, acceptUrl, declineUrl}` — the one-time key exists
  in plaintext only inside the seeding request, so this is where a test gets the
  recipient's journey without scraping email; scenarios about the delivered
  message still read Mailpit. Note that expiry is a date, not a status: an
  `'expired'` invitation is still `PENDING` with `expiryDate` a day past the
  configured window, and the manager's Invitations table (filtered by
  `stillActive()`) does not list it.

- **`siteAdmin` role key** in `users[].roles` (U53, 2026-07-29, all three
  apps; `POST scenarios/context` and `POST bootstrap`) — a throwaway **site
  administrator**:

  ```js
  users: [
      {username: 'u53top.admin.ojs', roles: ['siteAdmin']},             // admin only
      {username: 'u53top.both.ojs',  roles: ['siteAdmin', 'manager']},  // and a context role
  ]
  ```

  Not a new key: it is a role key like any other, spelled the way the others
  are (`default.groups.name.siteAdmin` is a real locale key), so the password
  rule, the `{username: id}` echo and the rollback-on-failure journal all apply
  unchanged. What is different is the GROUP it names. The site administrator
  group is installed once for the whole site with a **null context id** and no
  `nameLocaleKey` (`PKPInstall::createData()` writes the translated names
  directly), so it can never resolve through the context-scoped name-key lookup
  every other role uses; `resolveRoleGroup()` special-cases this one key and
  finds the group by role id instead. Enrolment itself is the application's own
  `Repo::userGroup()->assignUserToGroup()` — the same call the installer makes
  for the first admin and the same call the seeder already makes for every other
  role. Deliberately NOT available to `invitations[].roles`: no screen in the
  application invites anyone to this role, so neither does the seeder.

  Exists because **no screen grants site administrator**. The Users & Roles user
  form intersects the submitted group ids with `UserGroup::withContextIds([$contextId])`
  before saving, and the site admin group is not in any context — so the only
  site administrator on a fresh install is the installer's `admin`, which the
  suite must keep enabled and unmerged. Any test about administrator behaviour
  (self-disable, merge, one admin acting on another) needs its own throwaway.
  Parity note in `scenario-processor-audit.md`.

**Rebuild acceptance** (PROGRESS restart step 2 is done when): bootstrap seeds
green in all three apps; a login smoke passes per fleet; the scenario endpoint
seeds a context and a staged submission in each app, with one parity
spot-check against the equivalent UI path; the reset tool forces a cold
bootstrap; concurrent fleets keep Mailpit assertions scoped to unique
throwaway recipient addresses (principle 8); and the
operational names RUNBOOK "Ops & environment safeguards" cites (reset script,
test-key header, ports) either match the rebuilt harness or RUNBOOK is updated
in the same commit.

## Bootstrap data policy

- Base seed lives in `playwright/fixtures/bootstrap.js` (journal `publicknowledge`,
  18 users, sections, categories, issues). Seeded users and roles are documented in the
  `ojs-playwright-tests` skill.
- **Richer defaults are encouraged**: enable features and metadata most real journals use
  (e.g. additional submission-wizard metadata fields, categories, DOIs where it doesn't
  force per-test cleanup), so tests exercise representative configuration.
- A bootstrap change requires checking every implemented spec against the new defaults —
  do it deliberately, not casually.

## Commit discipline

Owned by `docs/product/RUNBOOK.md` (per-feature loop, Commit step): `lib/pkp` and root
commit separately, never bump submodule pointers.

## App-code change ledger

Actual app-code changes (anything outside `playwright/`, `docs/`, `.claude/`)
and build blockers — app defects that had to be worked around to get tests
running green — append a row to `docs/e2e/app-changes.md`, naming the app.
Product findings (bugs, divergences, open questions) never go there: they live
in the feature spec's Findings register (RUNBOOK "What goes where").
A test result that contradicts the spec — permission behavior included — means
the SPEC is wrong: report it so the finding reaches the register (RUNBOOK step
7). Never park it as a skipped/`fixme` test or a "not covered" header note.
Scenario-endpoint parity notes go to `docs/e2e/scenario-processor-audit.md`.

## Budget & definition of done

Owned by `docs/product/RUNBOOK.md` — see its **Budget & ceilings** (per-app
suite budget; single home for the numbers) and **Definition of done** sections.

## Related documents

- `.claude/skills/ojs-playwright-tests/` (ojs-main) — design record: users, app map, patterns, scenarios
- `docs/e2e/scenario-processor-audit.md` — Processor parity ledger (recreated with the harness rebuild)
- `docs/product/RUNBOOK.md` · `docs/product/CHARTER.md` — the spec-driven build loop + charter
