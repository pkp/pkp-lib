# Harness Guide

Single home for how the Playwright e2e harness is laid out, configured, and
run — shared by OJS, OMP, and OPS via the lib/pkp submodule. Paths are given
relative to an app repo root (`lib/pkp/...` = inside the submodule). The
test-authoring rules live in `PRINCIPLES.md`; coding conventions and
pitfalls in `patterns.md`; the seeding API in `scenarios.md`; identities in
`users.md`.

## The two playwright folders

Every app has two Playwright layers. Picking the wrong one puts a test in the
wrong repo.

**`playwright/` (app repo root) — the app's feature suites.** Every feature
test lives here, even when the scenario is common to all three apps
(maintainer ruling 2026-07-26: per-app suites are derived from the spec;
duplication between apps is acceptable — the spec is the maintained artifact).

```
playwright/
├── tests/             # Spec files — flat, no subfolder taxonomy
│   └── serial/        # <app>-serial project — globally-scanning specs (queue drains etc.)
├── support/
│   ├── fixtures.js    # App test extension (api alias; feature fixtures land here)
│   ├── legacy.js      # (OJS) waitForJQueryIdle — legacy jQuery surfaces, app-local
│   └── app.context.js # Capability map + seed.actors archetype map
├── pages/             # App-only POMs
├── fixtures/
│   ├── bootstrap.js   # Static seed for the base context (journal/press/server, 18 users, sections, …)
│   └── files/         # Upload fixtures
└── .auth/             # Storage-state cache per user (gitignored)
```

Both test folders stay **flat** — no subfolder taxonomy until ~25–30 specs
make natural clusters obvious.

**`lib/pkp/playwright/` — shared infrastructure only.** Base fixtures, shared
POMs, the bootstrap + login smoke specs. Feature suites never live here. Be
conservative: when in doubt, it belongs in the app repo.

```
lib/pkp/playwright/
├── tests/                   # bootstrap.setup.js (setup project), login.spec.js (smoke)
├── support/
│   ├── base-test.js         # The extended `test` fixture — start here
│   ├── auth.js              # ensureAuthStateFor — storage-state cache w/ liveness probe
│   ├── api.js               # pkpApi — test-API client (bootstrap, createContext, createSubmission)
│   ├── mail.js              # pkpMail — Mailpit HTTP API wrapper
│   ├── jobs.js              # runJobs — drain the fleet's queued jobs (serial project only)
│   ├── motion.js            # disableMotion — animations forced to 0.01ms in every context
│   └── env.js               # loadEnv(appRoot) — .env.playwright parser (shell exports win)
├── pages/                   # BasePage, LoginPage, DashboardPage
├── data/users.js            # The 18 baseline identities + getPassword()/getEmail()
├── reset.js                 # test:e2e:reset — drop+recreate DB, wipe files dir + .auth/
├── serve.js                 # test:e2e:serve — manual PHP server on the fleet's base port
└── config-factory.js        # definePkpConfig({appName, appRoot, basePort}) — all three apps
```

The PHP side: shared builders in `lib/pkp/classes/testing/` + the gated
controller `lib/pkp/api/v1/_test/PKPTestController.php`; each app adds
`api/v1/_test/{index.php,TestController.php}`, `classes/testing/*` subclasses,
and `tools/installTest.php`.

## The fleets

| App | Checkout | Base port | Test DB |
|---|---|---|---|
| OJS | `ojs-main` | 8000 | `ojs_test` |
| OMP | `omp-main` | 8100 | `omp_test` |
| OPS | `ops-main` | 8200 | `ops_test` |

Sibling checkouts under one parent dir, all on the campaign branch. Test DBs
are **PostgreSQL** locally (harness code is DB-driver-agnostic —
PRINCIPLES design-record D8); Postgres-specific defects reproduce in-env.
Same scenario endpoints and `publicknowledge` context path everywhere.

**One shared roster, subset-enrolled per app** (see `users.md` for the
roster): OMP splits the four reviewers (`julia`/`paul` → External,
`amara`/`adam` → Internal) and seeds series `monographs`/`textbooks`
(identified by `path`, no abbrev); OPS enrols `sectioneditor.*` as Moderators
(`ana`/`ravi` assigned to section `PRE`, `omar` deliberately unassigned as a
visibility control), `assistant.rita` as Editorial Board Member (no stage
access), and has no editor/reviewer/copyeditor/layout/proofreader accounts
(`seed.actors` maps those archetypes to null).

## Runtime model

- **One `php -S` server per Playwright worker** at `basePort + parallelIndex`
  (`php -S` serves one request at a time; a single server would serialize the
  suite). Playwright's `webServer` array owns their lifetime; the ready probe
  is a static file so servers count as up before the DB is installed.
- **Worker count**: `PLAYWRIGHT_WORKERS`, or auto-detect when unset —
  performance cores where the OS exposes them (Apple Silicon sysctl, Intel
  hybrid sysfs), else CPU cores − 2, minimum 2. The measured knee matches the
  P-core count (2026-08-21); small CI runners want workers = cores and pin the
  env var explicitly.
- **Server output** goes to `playwright/.server-logs/server-<port>.log`
  (request log + PHP warnings) — check there when debugging server-side
  errors. A server adopted via `reuseExistingServer` (e.g. left over from
  `test:e2e:serve`) keeps logging wherever it was started.
- **Projects chain**: `setup → {shared, <app>} → <app>-serial`. The setup
  project probes `GET /api/v1/_test/bootstrap` (warm: <1s no-op; cold:
  installs schema via `tools/installTest.php` + seeds). The serial project
  runs alone at the end — globally-scanning specs and queue drains only.
- **Animations are globally disabled** (`reducedMotion: 'reduce'` +
  `motion.js` CSS in every context); `trace: 'on-first-retry'` records nothing
  while retries are 0 — flip retries on when hunting a failure.
- **One shared DB and files dir per fleet** behind all its worker servers.
  Isolation is by data namespacing (unique tags), not DB separation — see
  `patterns.md` tag conventions.

## config.test.inc.php — the local test config

Each app has a **local, gitignored** `config.test.inc.php`; the app reads it
via the `PKP_CONFIG_FILE` env var, which is the whole switch between the dev
and test installs. Generate it from the app's own template —
`node lib/pkp/playwright/make-test-config.js > config.test.inc.php` (env
inputs documented in the script; CI uses the same generator) — or write it by
hand. It must carry, besides its own Postgres `<app>_test` DB and
files dir:

- `allowed_hosts` pinned to `127.0.0.1` (+ the fleet's port)
- `installed_locales = en,fr_CA` — the bilingual base context needs it
- `[schedule] task_runner = Off` and `[queues] job_runner = Off` — nothing
  queued or scheduled runs on its own; serial specs invoke the runners
  explicitly (see `patterns.md` parallel lesson on runners)
- `[proxy] http_proxy/https_proxy = http://127.0.0.1:9` — a dead local port.
  PKP wires `[proxy]` into Guzzle and Laravel HTTP, so all server-side
  outbound HTTP fails fast: tests never reach real external services, and a
  hung outbound call can't stall a single-threaded worker server. SMTP to
  Mailpit and 127.0.0.1 traffic are unaffected. Do not remove; re-add by hand
  on new machines. (There is no OS-level firewall and no DTD mirror.)
- `enable_minified = On` — backend pages load `js/pkp.min.js` instead of ~107
  scripts. The bundle is committed; when its sources change, recompile via
  `lib/pkp/tools/buildjs.sh`'s Closure minify pass (the script's lint gate
  blocks on long-standing style nits — run the final compile step directly).

Never delete `config.test.inc.php` alone (the template resets
`installed=Off`); `npm run test:e2e:reset` is the sanctioned nuke — it refuses
any DB whose name lacks "test".

**Always drive the fleets via `127.0.0.1`, never `localhost`.** A page request
carrying `Host: localhost` ends in a bare 400 — after the locale 302, so the
first response looks fine and only the followed redirect fails. The `_test`
API answers on either host, which makes the mistake worse: seeding succeeds,
the browser step dies.

## Env vars (`.env.playwright`; shell exports win)

- `PKP_CONFIG_FILE` — absolute path to `config.test.inc.php`
- `PLAYWRIGHT_BASE_PORT` / `PLAYWRIGHT_WORKERS` — worker 0's port; worker
  count (unset = auto-detect, above)
- `TEST_API_KEY` — enables and gates `/api/v1/_test/*` (namespace answers 404
  unless the var is in the server's environment, 403 unless the request's
  `X-Test-Key` header matches; never set it on a production install)
- `MAILPIT_URL` — Mailpit HTTP API (default `http://127.0.0.1:8025`). Mailpit
  is ONE shared instance across every worker and all three fleets
  (`brew services start mailpit`).

## Running

```bash
npm run test:e2e:install    # one-time, installs Chromium
npm run test:e2e:setup      # seed the test DB (cold ~1-3 min; warm <1s no-op)
npm run test:e2e            # full run
npm run test:e2e:ojs        # only the app project (name varies per app)
npm run test:e2e:ui         # Playwright UI mode — best for iterating
npm run test:e2e:debug      # PWDEBUG=1 step-through
npm run test:e2e:reset      # nuke the test DB (forces cold bootstrap next run)
npm run test:e2e:serve      # manual PHP server on the fleet's base port
```

Reset the DB before any full-suite timing run and every ~8–10 features —
long-lived DBs accumulate state that pollutes COUNT assertions and tag
searches. After a reset, the first run can die on a webServer start race —
relaunch. Don't run `test:e2e:serve` while a Playwright run is live (same
port). After a killed run, kill orphan chromium/php processes before
re-running.

## Quick start: writing a new test

1. **Folder**: feature test → the app's `playwright/tests/`; shared
   infrastructure only → `lib/pkp/playwright/`.
2. **Import**: shared spec `require('../support/base-test.js')`; app spec
   `require('../support/fixtures.js')` (adds the app's api fixture).
3. **User**: see `users.md`; `test.use({user: 'sectioneditor.ana'})` sets the
   file's default logged-in user; `asUser('reviewer.julia')` opens extra
   authenticated contexts for multi-actor flows.
4. **Screen**: no screen map is kept — read the Vue/PHP sources directly
   and confirm selectors against the running app (patterns.md).
5. **Conventions**: `patterns.md` — locators, waits, parallel lessons, tags.
6. **Seed via API, drive the UI only for what the test exercises**:
   `scenarios.md`.

## Pointers (single homes elsewhere)

- Commit discipline, push rules, what-goes-where routing:
  `lib/pkp/docs/e2e/process/RUNBOOK.md`.
- Security-shaped observations: never into public artifacts — RUNBOOK "What
  goes where" names the private destination.
- Test-authoring rules and the scenario-endpoint design record:
  `PRINCIPLES.md`.

## Verify before trusting

File paths, selectors, and schema fields cited across these docs are
snapshots; UIs drift faster than docs. Before finalizing a test, open the
named component/class and confirm, re-grep moved line numbers, and run the
test (`npm run test:e2e:ui`) before claiming it works. Treat every doc here as
a map, not a GPS.
