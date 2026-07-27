# Step 2 · Stage B — the shared Playwright JS layer + OJS wiring

**Date:** 2026-07-27 · **Repo:** `ojs-main` (branch `e2e_ng`) + `ojs-main/lib/pkp`
**Frame:** QA test-infrastructure work on a local disposable test install with seeded
fixture accounts. Nothing here touches real users, real data or third-party systems.
**Status:** built and verified against the live stage-A PHP API on a freshly reset
`ojs_test`. Nothing committed, nothing pushed. PROGRESS / atlas / app-changes untouched
(proposed rows below).

The harness was rebuilt from the design record only
(`.claude/skills/ojs-playwright-tests/`, `lib/pkp/docs/e2e/PRINCIPLES.md`) plus stage A's
handover. The deleted implementation was not read back from git history.

---

## 1. What was built

### 1.1 Shared layer — `lib/pkp/playwright/` (app-agnostic)

| File | Lines | Role |
|---|---|---|
| `config-factory.js` | 208 | `definePkpConfig({appName, appRoot, basePort})`. Projects, per-worker `webServer` array, `use` options, and a dependency-free `.env.playwright` reader (`loadEnvFile`, also exported). |
| `support/base-test.js` | 175 | The extended `test`. Options (`user`, `pkpServer`, `authDir`, `appContextModule`, `testApiKey`, `mailpitUrl`, `installCommand`, `appRoot`, `configFile`) plus fixtures `baseURL` (per-worker), `appContext`, `storageState`, `asUser`, `pkpApi`, `pkpMail`. |
| `support/auth.js` | 112 | `ensureAuthStateFor(browser, username, {baseURL, authDir})` — disk cache at `<appRoot>/playwright/.auth/<username>.json`, HTTP liveness probe before reuse, atomic write (temp + rename) so parallel workers cannot read a half-written state. |
| `support/api.js` | 126 | `PkpApi` — client for `/index.php/index/api/v1/_test/*`, sends `X-Test-Key` from the environment. `bootstrapStatus`, `bootstrap`, `createContext`, `createSubmission`, plus raw `get`/`post`. Non-JSON and non-2xx bodies are reported verbatim. |
| `support/mail.js` | 275 | `PkpMail` — `find`, `expectNone`, `inboxFor`, `latestTo`, `messageCount`, `fullMessage`, `extractLink`, `clearAll` (with the serial-spec-only warning in the file header and on the method). |
| `data/users.js` | 214 | The full 18-user roster from `users.md` (username, archetype label, display name, email, "use this when…"), `getPassword()`, `byUsername`, `byRole`. |
| `pages/BasePage.js` | 46 | POM base + `siteUrl()` / `contextUrl()` URL builders. |
| `pages/LoginPage.js` | 91 | `form#login` selectors, `login()` (waits for the redirect away from `/login` with `waitUntil:'commit'`), `requestPasswordReset()`. |
| `pages/DashboardPage.js` | 46 | The three dashboard views + a landmark that holds on all of them. |
| `tests/bootstrap.setup.js` | 115 | The setup project: probe → (install schema) → POST the app's bootstrap payload. |
| `tests/login.spec.js` | 67 | The smoke: single-actor dashboard landing, and a second actor via `asUser`. |
| `reset.js` | 58 | `npm run test:e2e:reset` — recreate the database and wipe `playwright/.auth/`. |

### 1.2 OJS wiring — `ojs-main/playwright/`

| File | Lines | Role |
|---|---|---|
| `playwright.config.js` | 23 | `definePkpConfig({appName:'ojs', appRoot, basePort: 8000})`. |
| `support/app.context.js` | 100 | Capabilities (APP-GLOSSARY §2, OJS column, verbatim), `vocab`, `seed.contextPath`/`actors` (18 archetypes) / `sections`, and `bootstrapPayload`. |
| `support/fixtures.js` | 35 | Extends the base test; today only the `ojsApi` alias. |
| `fixtures/bootstrap.js` | 214 | The base seed as data: journal, sections, categories, issues, settings, and the 17 seeded users built from the shared roster + an OJS `enrolments` table. |
| `tests/README.md`, `tests/serial/README.md` | — | The placement rules for the (still empty) OJS suite and the serial project. |

Plus `ojs-main/.env.playwright.example`, nine `test:e2e:*` npm scripts, `@playwright/test`
as a devDependency, and four `.gitignore` entries (`.env.playwright`,
`playwright/.auth/`, `playwright/.results/`, `playwright-report/`).

### 1.3 PHP extension — `users[].sections` (the §5.4 gap)

| File | Change |
|---|---|
| `lib/pkp/api/v1/_test/PKPTestApiController.php` | New `userSchemaOverlayProperties()` (default `[]`) + `userSchema()`; `schema()` and the bootstrap schema now build the user spec through it; `seedUsers()` calls a new `afterUserSeeded()` hook (default no-op) for every user, new or reused. |
| `lib/pkp/api/v1/_test/BootstrapRoutes.php` | Uses `$this->userSchema()` instead of loading `user.json` directly. |
| `ojs-main/api/v1/_test/JournalScenarioController.php` | Declares the `sections` user overlay (array of abbreviations) and implements `afterUserSeeded()` + `assignableUserGroupFor()`. |

Design, following stage A's conventions exactly:

- **Declared overlay, one level down.** `schemaOverlayProperties()` already lets an app add
  keys to the context spec; `userSchemaOverlayProperties()` is the same contract for the
  user spec, which both endpoints share. `sections` therefore validates on OJS and is
  rejected as an unknown key on an app that does not declare it — the unknown-key
  rejection is intact end to end (verified below).
- **Real service.** The assignment goes through
  `SubEditorsDAO::insertEditor($contextId, $sectionId, $userId, ASSOC_TYPE_SECTION, $userGroupId)`,
  which is exactly what `PKPSectionForm::execute()` calls, and only for users enrolled in
  one of that form's `assignableRoles` (sub-editor, manager, assistant — preferred in that
  order, since the primary key allows one group per user per section). A user with none of
  those roles THROWS with `specKey users.N.sections`, as does an unknown abbreviation.
- **Idempotent.** An existing assignment is skipped (read back through
  `getBySubmissionGroupIds`, not through `SubEditorsDAO::editorExists()` — that method's
  SQL still names the pre-3.2 `section_id` column and would fatal).
- Nothing in `lib/pkp` learned the word "section": the hook is app-neutral and only the OJS
  subclass implements it.

---

## 2. The per-worker server design

`php -S` is single-threaded. One server shared by N workers serialises the suite and — the
real hazard — lets one worker's slow request stall another worker's page load into a
timeout. So each parallel worker gets its own server:

```
port = basePort + testInfo.parallelIndex          # 8000, 8001, 8002 …
```

- `config-factory.js` builds a `webServer` ARRAY with one entry per worker
  (`php -S 127.0.0.1:<port> -t <appRoot>`, with `PKP_CONFIG_FILE` and `TEST_API_KEY` in
  `env`), and `support/base-test.js` derives `baseURL` from `parallelIndex`. The two
  always agree because both read the same `workers` number.
- **`parallelIndex`, not `workerIndex`**: `workerIndex` keeps climbing when a crashed
  worker is replaced, so it would eventually address a port with no server behind it.
- **Readiness is probed on `/favicon.ico`**, not on `/`. "The server is up" must not depend
  on the database being installed — the setup project is what installs it, and Playwright's
  readiness check does not accept a 500.
- **One installation, several processes.** All the servers share the database and the files
  directory. That is safe because the app derives its base URL from the request host
  (config `base_url` is only consulted for command-line calls) and `AllowedHostsPolicy`
  compares the host with the PORT STRIPPED, so the existing `"127.0.0.1"` entry covers
  every worker port. Session cookies are host-scoped, not port-scoped — which is also why
  ONE cached storage state authenticates a user on every worker's port.
- `reuseExistingServer: !CI`, so a manually started `npm run test:e2e:serve` is picked up
  instead of fought over.

Verified with 2 and with 4 workers (§4.4).

---

## 3. The npm-script surface as built

| Script | Command | Notes |
|---|---|---|
| `test:e2e:install` | `playwright install chromium` | One-time. |
| `test:e2e:setup` | `playwright test … --project=setup` | Cold ≈ 7 s (seed) or minutes (schema); warm 0.34 s. |
| `test:e2e` | `playwright test …` | setup → shared → ojs → ojs-serial. |
| `test:e2e:ojs` | `… --project=ojs --pass-with-no-tests` | Runs `setup` as a dependency. `--pass-with-no-tests` because the OJS suite is empty until step 3. |
| `test:e2e:ui` | `… --ui` | |
| `test:e2e:debug` | `PWDEBUG=1 playwright test …` | |
| `test:e2e:reset` | `node lib/pkp/playwright/reset.js` | `installTest.php --recreate-db` + wipe `playwright/.auth/`. |
| `test:e2e:serve` | `tools/testServer.sh restart` | Manual server on 8000 for poking around. |

`.env.playwright.example` carries `PKP_CONFIG_FILE`, `TEST_API_KEY`,
`PLAYWRIGHT_BASE_PORT`, `PLAYWRIGHT_WORKERS`, `MAILPIT_URL`. `PLAYWRIGHT_BASE_URL` is also
honoured (it names worker 0's server; the others still sit above it) — the recorded name
kept working under the per-worker design rather than dropped.

---

## 4. Verification (everything below was run)

### 4.1 Cold reset → cold setup → warm setup

```
$ npm run test:e2e:reset
Recreated database 'ojs_test'. … 40 migrations … Successfully installed version 3.6.0.0
Cleared cached storage states in …/playwright/.auth
$ npm run test:e2e:setup            # cold
  ✓ [setup] the shared base context is seeded (7.3s)          real 0m8.8s
$ npm run test:e2e:setup            # warm
  ✓ [setup] the shared base context is seeded (341ms)         real 0m1.6s
```

The warm path is the GET probe and nothing else. `npm run test:e2e` straight after a
reset also works in one go — the `setup` dependency seeds first (`3 passed (15.2s)`), and
the next run is `3 passed (4.7s)`.

### 4.2 The seed, read back through the REST API (no SQL)

As `manager.maya`, through her authenticated browser context:

```
ROSTER(18): admin, assistant.rita, author.alex, author.bea, copyeditor.carla,
  copyeditor.sam, editor.diana, layouteditor.leo, manager.maya, proofreader.pia,
  reader.rosa, reviewer.adam, reviewer.amara, reviewer.julia, reviewer.paul,
  sectioneditor.ana, sectioneditor.omar, sectioneditor.ravi

SECTIONS: ART (wordCount 500, abstractsNotRequired false)
          REV (abstractsNotRequired true, identifyType "Review Article")

ISSUES:   v1 n2 2014 datePublished 2026-07-27 · current
          v2 n1 2015 datePublished null
CURRENT:  v1 n2 2014

CATEGORY TREE: applied-science > comp-sci > computer-vision
                                > eng
               social-sciences > sociology
                               > anthropology

CONTEXT: name/acronym in en+fr_CA, primary en, contactEmail rvaca@mailinator.com,
  defaultReviewMode 2, keywords request, citations request, enableAnnouncements true,
  enablePublicComments true, onlineIssn 0378-5955, numWeeksPerResponse/Review 4
```

### 4.3 `users[].sections` — the assignment lands

`GET /publicknowledge/api/v1/users?assignedToSection={id}` — the same query the Sections
settings form uses to list a section's editors:

```
ART: editor.diana, sectioneditor.ana, sectioneditor.omar
REV: editor.diana, sectioneditor.ravi
```

Exactly the `users.md` table. The two lists are each other's positive control:
`sectioneditor.ravi` is absent from ART because he is assigned to REV, not because the
query returns nothing.

### 4.4 Parallel workers and per-worker servers

```
$ npx playwright test --workers=2        (ports observed via lsof DURING the run)
php 127.0.0.1:8000
php 127.0.0.1:8001
  3 passed (4.1s)
$ PLAYWRIGHT_WORKERS=4 npx playwright test
  7 passed (5.5s)
```

Independent evidence that a worker really talks to its own server: the password-reset mail
produced by the test running on worker 1 carries a link to
`http://127.0.0.1:8001/index.php/index/en/login/resetPassword/…` — the app generated it
from that worker's request host.

### 4.5 `login.spec.js`, twice, on a freshly reset database

```
=== FINAL RUN 1 (2 workers)
  ✓ [setup]  the shared base context is seeded (343ms)
  ✓ [shared] login smoke › a seeded editor lands on the editorial dashboard @smoke (2.5s)
  ✓ [shared] login smoke › a second actor is authenticated alongside the default one @smoke (4.3s)
  3 passed (6.0s)
=== FINAL RUN 2 (2 workers)
  ✓ … 3 passed (4.1s)
```

Run 2 is faster because the storage states written by run 1 were reused. 12 further
consecutive runs were green (§6.1 records the one failure seen across all of them).

### 4.6 The storage-state cache and its liveness probe

```
md5 of playwright/.auth/*.json before run   →  identical after run      (reused)
tamper: rewrite every cookie value to garbage
  → next run re-logs in, writes a new state, 3 passed (4.7s)            (probe caught it)
```

This is where the rebuild found a real bug in its own first cut: the recorded probe is
"GET `/index/user/profile`, 200 means live". On this app that URL redirects TWICE even for
a signed-in user — to the locale-prefixed form, then into the single journal — so a
status-only probe reported every cached state as dead and silently re-logged everyone in on
every run (observed: all three `.auth` files rewritten between two green runs). The probe
now follows redirects and asks WHERE the request ended: `response.ok() && !url.includes('/login')`.

### 4.7 Multi-actor

`login.spec.js` second test: `test.use({user: 'editor.diana'})` gives an authenticated
`page`, `asUser(appContext.seed.actors.reviewer)` (→ `reviewer.julia`) gives a second
authenticated context whose page reaches the reviewer dashboard. Both contexts live at
once; the second closes at teardown. Gated on `capabilities.hasReviewerRoles`.

### 4.8 Mailpit

A throwaway user is minted in a scratch journal via `pkpApi.createContext`, a password
reset is requested through the real lost-password form, and the assertion is scoped by
recipient AND content:

```
pkpMail.find({to: 'tw-mailw0hg2ji@example.org', contains: 'Password Reset'})
  → "Password Reset Confirmation -> tw-mailw0hg2ji@example.org"
```

`clearAll()` was never called. `find()` refuses an unscoped call (recipient plus a content
marker are both required).

### 4.9 Spec-key rejection still holds after the schema change

Against `POST /scenarios/context`:

```
users[0].superpower: "x"
  → {"error":"Unknown key 'users.0.superpower' in the scenario spec. …",
     "specKey":"users.0.superpower"}
users[0].sections: ["NOPE"]
  → {"error":"Context 'scratch-gapchk2' has no section with abbreviation 'NOPE'.
              Available: ART.","specKey":"users.0.sections.0"}
users[0]: {roles:["author"], sections:["ART"]}
  → {"error":"User 'tw-gapchk3' cannot be assigned to a section: it is not enrolled
              in any editorially assignable role (sub-editor, manager or assistant)
              in context 'scratch-gapchk3'.","specKey":"users.0.sections"}
```

Failure hygiene held for all three: `GET /bootstrap?context=scratch-gapchk{1,2,3}` answers
`{"seeded":false,"contextId":null}` — the partially-built contexts and users were rolled
back. Positive control taken the same way, a well-formed spec:
`{"tag":"gapok","contextId":5,"urlPath":"scratch-gapok","users":{"tw-gapok":22}}`.

### 4.10 The long-password trap, confirmed and handled

The login form's password input carries `maxlength="32"`
(`lib/pkp/templates/frontend/pages/userLogin.tpl:65`), while the roster rule (username
doubled) produces 34–36 characters for the three `sectioneditor.*` accounts.
`LoginPage.fillPassword()` removes the attribute when the password is longer, then fills.
Verified by logging `sectioneditor.omar` in through the real form with his 36-character
password and reaching the dashboard.

---

## 5. Bootstrap defaults: what was seeded and what was skipped

Seeded (all declared in stage A's §3 context schema): `urlPath`, `name`, `acronym`,
`description`, `primaryLocale`, `supportedLocales` (`en` + `fr_CA`), `contactName`,
`contactEmail`, `supportName`, `supportEmail`, `mailingAddress`, `onlineIssn`, `printIssn`,
`copyrightNotice`, `enableAnnouncements`, `enablePublicComments`, `disableSubmissions`,
`defaultReviewMode` (2 = double-anonymous), `numWeeksPerResponse`, `numWeeksPerReview`,
`keywords: request`, `citations: request`, `categories[]`, and the OJS overlay
`sections[]` / `issues[]`.

**Skipped, pending schema keys the step-2 API does not declare** (no PHP schema was
extended for them):

| Recorded enriched default | Missing key(s) |
|---|---|
| DOIs auto-assigned on publish | `doiPrefix`, `enabledDoiTypes`, `doiCreationTime`, `doiVersioning`, `registrationAgency`. `enableDois` IS declared, but enabling DOIs without a prefix or a creation time is a half-configured state, so it was left off rather than set alone. |
| Categories offered in the submission wizard | `submitWithCategories` |
| Reviewer suggestions in the wizard | `reviewerSuggestionEnabled` |
| Review reminder thresholds | `numDaysBefore/AfterReviewResponseReminderDue`, `numDaysBefore/AfterReviewSubmitReminderDue` |
| CSL / citation-style plugin enabled | `plugins: {…}` |
| Review forms | `reviewForms[]` |
| Subscriptions, subscription-required issues | `subscriptions[]`; `accessStatus` IS declared on issues but is meaningless without them |
| `publishingMode` | Declared; left at the default (open access) deliberately — the record does not name a non-default value for the base journal. |

Also not seeded: any submission. The base seed is journal + structure + people only, as
recorded; submissions come from the scenario endpoint per test.

---

## 6. Deviations from the design record, with rationale

1. **Four projects, not three.** The record names `setup → app-project → serial`. A
   Playwright project has exactly ONE `testDir`, and the shared specs
   (`lib/pkp/playwright/tests/`) and the app suite (`playwright/tests/`) are not under a
   common directory except the repository root — where test discovery would have to walk
   `node_modules/`, `cache/` and `lib/pkp/lib/vendor/`. So the shared specs get their own
   project: `setup → {shared, ojs} → ojs-serial`. The recorded chain is intact; `shared` is
   a sibling of the app project. `--project=ojs` therefore runs the OJS suite and its setup
   dependency, exactly as the recorded `test:e2e:ojs` describes.
2. **`config-factory` launches the servers itself** rather than shelling out to
   `tools/testServer.sh`. Playwright's `webServer` kills the process it starts, and
   `testServer.sh` deliberately backgrounds and exits (it is the ops tool). The factory
   runs `php -S` in the foreground with the two environment variables in `webServer.env`;
   `testServer.sh` stays the manual path behind `npm run test:e2e:serve`, and
   `reuseExistingServer` makes the two cooperate.
3. **`DashboardPage.heading` is "the main region's H1", not a named heading.** The
   dashboard's H1 names the current VIEW ("Assigned to me (0)"), which varies by role and
   by app; the page title "Submissions" is not rendered as a heading at all. A shared POM
   cannot name it, and a smoke does not need to: the assertion "the main region has its
   heading" is true on every dashboard view in every app and false on the login page.
4. **`reset.js` lives in `lib/pkp/playwright/`.** The record puts the reset behind an npm
   script but does not say where the code lives; it is app-agnostic (it reads the app's own
   `.env.playwright` and calls that app's `tools/installTest.php`), so it belongs in the
   shared tree where OMP and OPS get it for free.
5. **`support/tinymce.js`, `support/jquery.js`, `support/scenarios.js` were NOT recreated.**
   The record lists them, but each exists to serve a feature spec (TinyMCE assertions,
   legacy jQuery grids, a typed scenario client). Building them now would be building
   against no caller. `pkpApi.createSubmission()` / `createContext()` already cover what the
   scenario client would wrap.
6. **`playwright/data/` (OJS-side) was not created** — nothing needs app-local test data
   yet; the roster is shared.
7. **The setup project cannot install into a COMPLETELY empty database.** See §7.1 — this
   is a stage-A tool property, not a JS-layer choice; the setup reports it with the
   one-line remedy instead of pretending to handle it.

---

## 7. Findings worth the orchestrator's attention

### 7.1 `installTest.php` cannot install into an empty database (stage-A follow-up)

`tools/bootstrap.php` constructs the application, which queries the `versions` table
whenever the configuration says `installed = On` — which the test configuration does. So:

- `installTest.php --recreate-db` works, because the drop happens AFTER the application has
  booted against the still-intact database. That is the `npm run test:e2e:reset` path and
  it is solid.
- `installTest.php` against a database with no tables fatals in the bootstrap, before its
  own first line.

Two escapes were tried and rejected: `define('RUNNING_UPGRADE', true)` before the require
gets past `PKPApplication::__construct()` but not past `CommandLineTool::__construct()`,
which loads generic plugins and queries `versions` again; a second configuration file with
`installed = Off` duplicates local credentials. **What does work, verified:** set
`installed = Off` in `config.test.inc.php`, run `php tools/installTest.php`, and the
installer writes the flag back to `On` when it finishes. The setup project's failure
message names exactly that remedy. Worth a small stage-A fix if the maintainer wants
`test:e2e:setup` to be self-healing from a truly empty database.

### 7.2 `config.test.inc.php` needed `installed_locales`

The base seed's journal is `en` + `fr_CA` (recorded), but the test config declared no
`installed_locales`, so the installer installed `en` only and the first cold bootstrap
failed with `Locale 'fr_CA' is not installed on this site` — correctly, at
`context.supportedLocales.1`. Added to the local (gitignored) config:

```ini
[i18n]
locale = en
installed_locales = en,fr_CA
```

Anyone recreating the environment needs this line; it is documented in the setup's error
path only implicitly, so it may deserve a line in the RUNBOOK's environment section.

### 7.3 `SubEditorsDAO::editorExists()` is broken

```sql
SELECT COUNT(*) … WHERE context_id = ? AND section_id = ? AND user_id = ? AND assoc_id = ?
```

`section_id` was renamed to `assoc_id` in the 3.2.1 migration, and the method also binds
four parameters to a predicate that names `assoc_id` twice. Any caller gets a SQL error.
Nothing in the app appears to call it. Not worked around in application code — the harness
reads assignments through `getBySubmissionGroupIds()` instead. Product-side judgement is
not mine; recording it here.

### 7.4 One unreproduced failure

The very first of the paired `login.spec.js` runs failed with
`browserContext.newPage: Target page, context or browser has been closed` before any
assertion. It did not recur in the 12 consecutive runs that followed (2 and 4 workers,
warm and cold caches). Recorded rather than dismissed; if it returns, the suspect is the
`storageState` fixture opening a login context on the same worker-scoped `browser`.

### 7.5 `pkpMail.extractLink()` returns null on the password-reset mail

Correctly: that mail carries a bare URL, not an `<a href>`. `extractLink` is for
anchor-bearing mail (invitations, decision notifications). If a click-the-link test ever
needs the reset URL, it wants a text-scan helper, not a change to `extractLink`.

### 7.6 PRINCIPLES §"Architecture principles" 1 still says `scenarios/journal`

Stage A flagged it (its §5.2); the JS client is written against `scenarios/context`, which
is what the API serves. Still unfixed in the doc as of this writing.

---

## 8. Proposed row for `docs/e2e/scenario-processor-audit.md`

| Date | App | Builder path | Parity check | Verdict | Notes |
|---|---|---|---|---|---|
| 2026-07-27 | OJS | bootstrap/context · `users[].sections` | `GET /publicknowledge/api/v1/users?assignedToSection={id}` — the query the Sections settings form itself uses — for both seeded sections, each list acting as the other's positive control | ✅ real path | Assignment goes through `SubEditorsDAO::insertEditor(contextId, sectionId, userId, ASSOC_TYPE_SECTION, userGroupId)`, the call `PKPSectionForm::execute()` makes, and is restricted to that form's `assignableRoles` (sub-editor → manager → assistant, in that preference order, because the table's primary key allows one group per user per section). Unknown abbreviation and un-assignable role both THROW with the offending `specKey`. Idempotent re-runs skip existing rows. Read-back avoids `SubEditorsDAO::editorExists()`, whose SQL still names the pre-3.2 `section_id` column (§7.3). |

## 9. Proposed rows for `docs/e2e/app-changes.md`

| Date | App | Change | Where | Why |
|---|---|---|---|---|
| 2026-07-27 | shared (lib/pkp) | Test-API user specs gained a declared app overlay: `userSchemaOverlayProperties()` + `userSchema()`, and `seedUsers()` calls a new `afterUserSeeded()` hook | `lib/pkp/api/v1/_test/PKPTestApiController.php`, `BootstrapRoutes.php` | Closes the stage-A §5.4 gap (`users[].sections`) the same way context overlays already work: app concepts enter a shared schema only by being DECLARED, so `sections` validates on OJS and is rejected as an unknown key elsewhere. Test-only namespace; inert without `TEST_API_KEY`. |
| 2026-07-27 | OJS | `users[].sections` overlay implemented — section-editor assignment through the real service | `ojs-main/api/v1/_test/JournalScenarioController.php` | The base roster's section-editor assignments (`users.md`) are what make a seeded section editor a participant on new submissions in that section. |
| 2026-07-27 | OJS | New test-only files (not app behaviour): `playwright/*`, `.env.playwright.example`, nine `test:e2e:*` npm scripts, `@playwright/test` devDependency, four `.gitignore` entries; shared: `lib/pkp/playwright/*` | — | The Playwright harness. Nothing outside `playwright/` changes application behaviour. |

## 10. Reproduce

```bash
cd /Users/jarda/git/pkp/pkp-main/ojs-main
cp .env.playwright.example .env.playwright   # edit PKP_CONFIG_FILE if your paths differ
npm run test:e2e:install                     # once
npm run test:e2e:reset                       # recreate + install the schema
npm run test:e2e:setup                       # seed (≈7 s) — run it again to see the warm no-op
npm run test:e2e                             # setup → shared → ojs → ojs-serial
```

Requires Mailpit on `:8025` (`brew services start mailpit`) and, in
`config.test.inc.php`, `installed_locales = en,fr_CA` (§7.2).
