# Step 2 · Stage C — wiring OPS into the rebuilt Playwright harness

**Date:** 2026-07-27 · **Repo:** `ops-main` (branch `e2e_ng`), `lib/pkp` at `a11e2b0fa7`
**Frame:** QA test-infrastructure work on a local disposable test install with seeded
fixture accounts. Nothing here touches real users, real data or third-party systems.
**Status:** built and verified against a freshly installed `ops_test`. Nothing committed,
nothing pushed. PROGRESS / atlas / app-changes untouched (proposed rows below).
**`lib/pkp` was treated as read-only and its working tree is clean** — every file below is
app-side. One shared-layer bug was found and is NOT worked around: §7.1.

---

## 1. What was built (all in `ops-main`)

| File | Lines | Role |
|---|---|---|
| `api/v1/_test/index.php` | 46 | Dispatcher. 404s and exits when `TEST_API_KEY` is absent. |
| `api/v1/_test/ServerScenarioController.php` | 268 | OPS context overlay: `sections`, `enableAuthorScreening`, `postedAcknowledgement`; the `users[].sections` overlay (moderator → section assignment through the real service). |
| `api/v1/_test/SubmissionScenarioController.php` | 108 | OPS submission overlay: `section` (by abbrev). Declares NO promoting decision. |
| `api/v1/_test/TestBootstrapController.php` | 36 | `extends ServerScenarioController` + `use BootstrapRoutes`. |
| `tools/installTest.php` | 498 | Ported verbatim from OJS bar the OAI repository id (`ops-test.localhost`) — including the §11 three-state pre-boot probe and non-zero exit. |
| `tools/testServer.sh` | 62 | Ported; default port 8200, log under `files-test-ops/`. |
| `playwright/playwright.config.js` | 23 | `definePkpConfig({appName:'ops', basePort: 8200})`. |
| `playwright/support/app.context.js` | 122 | Capabilities (APP-GLOSSARY §2 OPS column, verbatim), `vocab`, `seed.actors`, `seed.sections`. |
| `playwright/support/fixtures.js` | 35 | The OPS `test`; today only the `opsApi` alias. |
| `playwright/fixtures/bootstrap.js` | 197 | The base seed as data. |
| `playwright/tests/README.md`, `playwright/tests/serial/README.md` | — | Placement rules for the (empty) OPS suite. |
| `.env.playwright.example` | 30 | `PKP_CONFIG_FILE`, `TEST_API_KEY`, `PLAYWRIGHT_BASE_PORT=8200`, `PLAYWRIGHT_WORKERS`, `MAILPIT_URL`. |
| `config.test.inc.php` | — | Local, gitignored. See §3. |
| `package.json` | +9 | Eight `test:e2e:*` scripts + `@playwright/test` devDependency. |
| `.gitignore` | +6 | `/config.test.inc.php`, `.env.playwright`, `playwright/.auth/`, `playwright/.results/`, `playwright-report/`. |

No shared file was created or edited. `ServerScenarioController` is named for the OPS
context object (`Application::ASSOC_TYPE_SERVER`, `classes/server/Server.php`), the way
`JournalScenarioController` is named on OJS.

---

## 2. The OPS roster and the section seed

### 2.1 Roster — the SHARED roster, subset-enrolled

`lib/pkp/playwright/data/users.js` states its own contract: *"This file describes WHO
exists. Which user groups each one is enrolled in is the app's business and lives in that
app's bootstrap payload — the role vocabulary differs per app (OPS has no reviewer group,
OMP splits reviewers)."* So OPS does **not** define new usernames; it enrols a subset of
the shared, archetype-keyed roster into its own five groups. That keeps one home for
names/emails and keeps `seed.actors` a pure archetype→username map.

OPS ships exactly five user groups (`registry/userGroups.xml`), all with `stages="5"`
except the last, which has none:

| name key | on screen | role id | stages |
|---|---|---|---|
| `manager` | Preprint Server Manager | 16 | 5 |
| `sectionEditor` | **Moderator** | 17 | 5 |
| `author` | Author | 65536 | 5 |
| `reader` | Reader | 1048576 | — |
| `editorialBoardMember` | Editorial Board Member | 4097 | **none** |

Seeded (7 accounts + the installer's `admin`):

| Username | OPS group | Section | Why |
|---|---|---|---|
| `admin` | (site) + manager of the server | — | Installer-created; creating the server enrols it as manager. |
| `manager.maya` | manager | — | Server settings, users. Sees every submission. |
| `sectioneditor.ana` | **sectionEditor = Moderator** | PRE | The default "a moderator". |
| `sectioneditor.ravi` | sectionEditor | PRE | A second moderator on the same section. |
| `sectioneditor.omar` | sectionEditor | *(none)* | **Deliberately unassigned** — the same-role negative control for visibility (§5.3). |
| `author.alex` | author | — | Author-only permission gates. |
| `author.bea` | author | — | Second author / foreign-submission cases. |
| `assistant.rita` | editorialBoardMember | — | OPS's only ROLE_ID_ASSISTANT group — and it has **no workflow stages at all**, unlike OJS's Funding coordinator. Seeded so the archetype resolves; a test must not assume workflow access from it. |
| `reader.rosa` | reader | — | Logged-in-but-no-editorial-access. |

**Absent on purpose:** `editor.diana` (OPS has no editor group), all four `reviewer.*`,
both `copyeditor.*`, `layouteditor.leo`, `proofreader.pia`. Their archetypes are `null`
in `seed.actors`, which is what the shared layer is supposed to read.

Passwords follow the recorded rule (username doubled); `sectioneditor.*` produce 34–36
characters, and the shared `LoginPage.fillPassword()` maxlength workaround handles them —
verified by three real form logins.

**Why `sectioneditor.*` and not `moderator.*`.** On-screen names win for *labels*
(APP-GLOSSARY), but the roster is keyed by permission ARCHETYPE, and OPS's Moderator **is**
the sub-editor slot (glossary §1 note, confirmed here: its `nameLocaleKey` is literally
`default.groups.name.sectionEditor`). Inventing `moderator.*` would have forked the shared
roster app-side for a label. `seed.actors.sectionEditor*` is the name a shared test uses;
OPS's own suite may of course write "Moderator" in its prose and locators.

### 2.2 Section seed — one section, matched not duplicated

Creating an OPS server auto-creates a section from `section.default.*`: title
**"Preprints"**, abbrev **`PRE`**, path `preprints`. The base seed names exactly that
abbrev, so `seedSections()` **edits** the existing section rather than leaving a stray one
behind — confirmed: `sections` has one row after bootstrap. `seed.sections` is `['PRE']`.

OPS sections carry a `path` (they are addressable on the reader side, e.g.
`/publicknowledge/en/preprints`), so the overlay declares `path` and derives a slug from
the abbrev when a spec omits it.

*Note for test authors:* a **scratch** server created with a section whose abbrev is not
`PRE` keeps the auto-created `PRE` **plus** the named one (verified: scratch server had
`PRE` + `WP`). Same behaviour as OJS; a test must not assume a scratch context has only
the sections it named.

### 2.3 Context settings

Seeded: `urlPath publicknowledge`, name **"Public Knowledge Preprint Server"**, acronym
`PKPS`, description, `primaryLocale en`, `supportedLocales [en, fr_CA]`, contact/support
name+email (`rvaca@mailinator.com`), mailing address, copyright notice,
`enableAnnouncements`, `enablePublicComments`, `disableSubmissions:false`,
`keywords/citations: request`, the 7-category nested tree, the OPS overlay
`enableAuthorScreening:false` + `postedAcknowledgement:true`, and `sections[PRE]`.

Deliberately **not** seeded: `defaultReviewMode`, `numWeeksPerResponse/Review` (declared in
the shared schema, meaningless without a review stage), `enableDois` (half-configured
without a prefix — same call as OJS), issues (no such concept), galleys (not step-2 schema
in any app).

**Locales: `en` + `fr_CA`, not en-only.** The brief permitted en-only. Bilingual was chosen
for the same recorded reason OJS uses it: a bare front-end URL only 302s to the
locale-prefixed form on a multi-locale context, and that difference bites probes. It
matters more on OPS than on OJS, because OPS's reader side (`/en/preprint/view/N`,
`/en/preprints`) is where scenario (c) is asserted. `config.test.inc.php` therefore carries
`[i18n] installed_locales = en,fr_CA`.

---

## 3. `config.test.inc.php`

Generated from `ops-main/config.TEMPLATE.inc.php` (the repo's `config.inc.php` predates the
`[schedule]` and `[features]` sections). Local and gitignored. Differences from the
template:

```
[general]  installed = Off (installTest.php flips it On) · base_url http://127.0.0.1:8200
           session_cookie_name OPSTESTSID (never collides with the dev install's OPSSID)
           allowed_hosts ["127.0.0.1"] (installTest adds ":8200") · enable_beacon Off
           session_lifetime 30
[database] postgres9 · ops_test · localhost:5432
[i18n]     locale en · installed_locales en,fr_CA
[files]    files_dir /Users/jarda/git/pkp/pkp-main/files-test-ops (separate from OJS's)
           public_files_dir <ops-main>/public
[email]    default smtp · smtp On · localhost:1025 (Mailpit)
[oai]      repository_id ops-test.localhost
[queues]   job_runner Off       [schedule] task_runner Off
[security] api_key_secret set
```

The stale pre-reset PHP process on :8200 (pid 16082, a `php -S` with per-port error logs)
was killed. `ops_test` held 137 stale pre-reset tables and was dropped and recreated.

---

## 4. How "staged" and "published" map onto a single-stage workflow

OPS has ONE workflow stage (`Application::getApplicationStages()` → `[PRODUCTION]`) and its
decision roster is `Decline`, `RevertDecline`, plus the bookkeeping `MoveToDone`,
`ReturnToWorkflow`, `ReturnToDone`. Nothing opens review;
`Application::getReviewStages()` returns `[]`.

| Spec | Means on OPS | Observed |
|---|---|---|
| `submitted: false` | a wizard-resumable draft in Production | `stageId 5`, `submissionProgress "start"`, `dateSubmitted null`, `status 1` |
| `submitted: true` (default) | **a submitted preprint sitting in its single Production stage, awaiting moderation** | `stageId 5`, `submissionProgress ""`, `dateSubmitted` set, `status 1`, moderators auto-assigned |
| `published: true` | **posting the preprint** — the real publish path | `status 3` (PUBLISHED), `publishedPublicationId` set, and `stageId 6` (`WORKFLOW_STAGE_ID_DONE`) with an unrequested `moveToDone` decision at stage 5 |
| `reviewRounds` | **rejected** | 400, `specKey reviewRounds` |
| `issue` / `issues` / `series` | **rejected** as unknown keys | 400 |

`stageId 6` and the extra `moveToDone` row are **not** harness artifacts: they are written
by the app's own `ApplyDoneWorkflowStage` listener on publish, exactly as OJS's report
records for its own publish path. Nothing in the OPS controllers names a stage id — the
shared layer reads the roster, which is the fix for the recorded trap where a hard-coded
initial stage made every seeded OPS submission invisible.

`SubmissionScenarioController` does **not** override `promoteToReviewDecision()`; the base
class's `null` is the declaration that OPS has no such decision. The shared builder then
refuses `reviewRounds` on the review-stage check first, with the message quoted in §5.4.

---

## 5. Verification (everything below was run)

Server: `tools/testServer.sh` on 127.0.0.1:8200 with `PKP_CONFIG_FILE` and `TEST_API_KEY`
exported. Namespace URL shape: `/index.php/index/api/v1/_test/…`.

### 5.1 Gate — four checks

```
(a) correct key                                    200   {"seeded":…}
(b) no X-Test-Key header                           403
(b2) wrong X-Test-Key                              403
(c) server started with TEST_API_KEY unset,
    correct header sent                            404   {"error":"api.404.endpointNotFound"}
    control: the same server serves /index.php/index with 302 — the app is up,
             the namespace is not
```

### 5.2 Empty database → seeded, in one command

```
$ psql -c 'DROP DATABASE ops_test' -c 'CREATE DATABASE ops_test'     tables: 0
  (playwright/.auth wiped, files-test-ops wiped, config still says installed = On)

$ npm run test:e2e:setup
  The database named in …/config.test.inc.php is empty; set installed = Off …
  … 41 migrations … Successfully installed version 3.6.0.0
  Restored installed=On, base_url=http://127.0.0.1:8200 and allowed_hosts …
  ✓ [setup] the shared base context is seeded (1.2m)      1 passed        real 1m11s
  installed flag after: On
```

Warm re-run: `✓ … seeded (105ms)  1 passed (739ms)` — the GET probe and nothing else.
`npm run test:e2e:reset` → recreate + reinstall + wipe `playwright/.auth`, then
`npm run test:e2e:setup` → `1 passed (3.8s)`. `npm run test:e2e:ops` (empty suite,
`--pass-with-no-tests`) → `1 passed`.

### 5.3 The seed, read back

```
users            admin(16) · manager.maya(16) · sectioneditor.ana/ravi/omar(17)
                 author.alex/bea(65536) · assistant.rita(4097) · reader.rosa(1048576)
sections         exactly ONE — id 1, title "Preprints", abbrev PRE, path preprints
subeditor rows   2 — ana and ravi on section 1 under user group 3 (the Moderator group)
                     omar deliberately absent
categories       applied-science > comp-sci > computer-vision · eng
                 social-sciences > sociology · anthropology
locales          primary en, supported ["en","fr_CA"]
settings         name/acronym en+fr_CA, contact/support rvaca@mailinator.com,
                 enableAuthorScreening 0, postedAcknowledgement 1, keywords/citations request
```

### 5.4 Scenarios — the recorded regression class first

Seeded on a fresh database, then read back **through the app's own submissions API** in
three authenticated sessions (real form logins, not DB reads):

```
final-a  submitted:false   id=1  stage=5  status=1  progress="start"
final-b  submitted         id=2  stage=5  status=1  progress=""
final-c  published:true    id=3  stage=6  status=3  publishedPublicationId=3

GET /publicknowledge/api/v1/submissions
  sectioneditor.ana   (assigned moderator)     2 items -> #3, #2
  sectioneditor.omar  (moderator, unassigned)  0 items
  manager.maya        (manager)                3 items -> #1, #3, #2
```

That is the acceptance point for the recorded OPS trap, and it is asserted with a
same-role control: `omar` is the same Moderator group as `ana` and sees nothing, so `ana`'s
two items are the effect of the section assignment, not of the role. `manager.maya` sees
the draft as well, which is correct — managers see incomplete submissions, moderators do
not.

Reader side, anonymous:

```
GET /publicknowledge/en/preprint/view/3   200, <h1> "Final posted / A posted preprint"
GET /publicknowledge/en/preprint/view/2   404      ← submitted but not posted (control)
GET /publicknowledge/en                   lists the posted preprint only
GET /publicknowledge/en/preprints         lists the posted preprint only (section listing)
```

Rejections — every one carries the offending `specKey`:

```
reviewRounds                → "This application has no review stage, so reviewRounds
                               cannot be seeded."                      specKey reviewRounds
submission.issue  (OJS)     → Unknown key 'issue'                      specKey issue
context.issues    (OJS)     → Unknown key 'issues'                     specKey issues
context.series    (OMP)     → Unknown key 'series'                     specKey series
bogusSetting                → Unknown key 'bogusSetting'               specKey bogusSetting
users[0].superpower         → Unknown key 'users.0.superpower'         specKey users.0.superpower
section "NOPE"              → "Server 'publicknowledge' has no section with abbreviation
                               'NOPE'. Available: PRE."                specKey section
roles ['reviewer']          → "No default user group for role 'reviewer' … Available roles:
                               manager, sectionEditor, author, reader,
                               editorialBoardMember."                  specKey users.1.roles.0
```

**Failure hygiene.** After all eight failures — including two mid-build ones (a submission
whose section failed after the submission existed; a scratch server whose SECOND user
failed after the server and the first user existed) — `servers` still held only
`publicknowledge`, `submissions` was unchanged at 3, `users` was unchanged at 9 (no
`tw-ops-ok` survivor), and zero titles carried the `[ORPHAN] ` prefix: the compensating
deletes succeeded, so the orphan fallback never had to fire. Probed through the API, all
five failed scratch paths answer `{"seeded":false,"contextId":null}`. Positive control
taken the same way: `{"tag":"ops-ok","contextId":3,"urlPath":"scratch-ops-ok",
"users":{"tw-ops-mod":12},"sections":{"WP":4}}`.

### 5.5 Per-worker servers and parallel runs

```
$ PLAYWRIGHT_WORKERS=3 npx playwright test --project=ops
  (lsof DURING the run)
  php 87955  127.0.0.1:8200 (LISTEN)
  php 87956  127.0.0.1:8201 (LISTEN)
  php 87957  127.0.0.1:8202 (LISTEN)
  1 skipped · 3 passed (4.5s)
```

Two consecutive 2-worker runs of the smoke (see §7.1 for what was run) were green, run 1
cold-`.auth` and run 2 warm: `3 passed (6.2s)` then `3 passed (4.5s)`.

### 5.6 Notification parity (acceptance spot-check)

Control: a preprint created, titled/abstracted, filed and **submitted through the real
submission controllers** as `author.alex` over an authenticated browser session —
`POST /publicknowledge/api/v1/submissions`, `PUT …/publications/N`,
`POST …/files` (multipart), `PUT …/submit`, i.e. the same controller the wizard's final
step calls. Compared against a scenario-seeded submitted preprint in the same server.

```
NOTIFICATIONS (assoc_type SUBMISSION)     seeded(5)      real path(16)
  SUBMISSION_SUBMITTED            lvl 2   2 rows u3,u4   2 rows u3,u4
  EDITOR_ASSIGNMENT_SUBMISSION    lvl 3   1              1
  EDITOR_ASSIGNMENT_EXTERNAL_REVIEW lvl 3 1              1
  EDITOR_ASSIGNMENT_EDITING       lvl 3   1              1
  APPROVE_SUBMISSION              lvl 2   1              1
  FORMAT_NEEDS_APPROVED_SUBMISSION lvl 2  1              1

STAGE ASSIGNMENTS                 identical: (group 3, user 3) (group 3, user 4)
                                             (group 4, user 6)  can_change_metadata 1

EVENT LOG                         submissionSubmitted  1 : 1
                                  fileRevised          1 : 1
                                  metadataUpdated      1 : 2   ← see below

EMAIL LOG                         1 row, same event type, on both
```

**Full parity.** `APPROVE_SUBMISSION` and `FORMAT_NEEDS_APPROVED_SUBMISSION` — the two the
OJS stage-A fix had to add to `submitSubmission()` — are present on the seeded side too, so
the shared fix carries to OPS unchanged. The `metadataUpdated` 1-vs-2 is an artifact of the
control, not a builder gap: the control wrote publication metadata twice (once implicitly at
`POST /submissions`, once at `PUT …/publications/N`) where the builder writes it once.

Two OPS observations that are **identical on both sides**, so app behaviour rather than
harness behaviour: OPS records `EDITOR_ASSIGNMENT_SUBMISSION` / `_EXTERNAL_REVIEW` /
`_EDITING` notifications despite having none of those stages, and records no
`EDITOR_ASSIGNMENT_PRODUCTION` despite Production being its only stage. Recorded here, not
asserted anywhere; it belongs to whichever spec owns editorial notifications.

### 5.7 Mail

Mailpit is shared with the other fleets and another agent was working concurrently, so every
read was scoped by recipient **plus** an OPS-only tag, no counts were asserted, and
`clearAll()` was never called.

```
to:author.alex@example.org "ops-parity-ctl"   → 1  "Thank you for your submission to
                                                    Public Knowledge Preprint Server"
to:author.alex@example.org "ops-parity-seed"  → 0     (Mail::fake — suppressed)
to:author.alex@example.org "ops-b-submitted"  → 0     (Mail::fake — suppressed)
```

The first line is the positive control that bounds the two negatives: the same recipient,
the same query shape, taken in the same run. `email_log` rows were still written on the
seeded side (§5.6), so log-side assertions keep working.

---

## 6. Deviations from the design record / the brief, with rationale

1. **Eight `test:e2e:*` scripts, not nine.** The brief says "the same nine npm scripts";
   OJS actually ships eight (`install`, `setup`, `e2e`, `<app>`, `ui`, `debug`, `reset`,
   `serve`) and stage B's own table lists eight. All eight were ported, with
   `test:e2e:ojs` → `test:e2e:ops`.
2. **The roster reuses the shared usernames** (`sectioneditor.*` for Moderators) rather
   than minting `moderator.*`. Rationale in §2.1 — `data/users.js` declares itself the
   cross-fleet roster and the sub-editor slot is the archetype OPS's Moderator fills.
3. **`assistant.rita` is seeded into Editorial Board Member**, a group with NO workflow
   stages. The archetype resolves, but it carries far less than OJS's assistant. Flagged in
   both the bootstrap and `app.context.js` so no test silently assumes stage access.
4. **`en` + `fr_CA`, not en-only** (§2.3).
5. **Galleys not seeded** — explicitly out of step-2 schema per the brief. This has a real
   consequence on OPS specifically: see §7.2.
6. **`playwright/data/` was not created** — nothing needs OPS-local test data yet.
7. **No OPS feature specs.** `playwright/tests/` holds only the placement README; the
   `ops` project runs with `--pass-with-no-tests` until step 3.

---

## 7. Findings

### 7.1 🔴 BLOCKING (shared layer): the login smoke hard-codes `editor.diana`

**File:** `lib/pkp/playwright/tests/login.spec.js:26`

```js
test.describe('login smoke', () => {
	test.use({user: 'editor.diana'});
```

**Symptom.** OPS has no editor group, so `editor.diana` is not seeded and
`seed.actors.editor` is `null` — which is exactly what the design record asks an app to
declare. But `test.use({user})` is a literal string that no `appContext` can influence, and
it is consumed by the `storageState` fixture *before* any test body runs. So **both** tests
fail in the fixture, at `LoginPage.login()`, with a 15 s `waitForURL` timeout — and the
second test never reaches its own `test.skip(!hasReviewerRoles, …)` guard, so the
capability skip the acceptance bar asks for cannot happen either.

```
2 failed
  [shared] › login.spec.js:28:2 › a seeded editor lands on the editorial dashboard @smoke
  [shared] › login.spec.js:40:2 › a second actor is authenticated alongside the default one @smoke
TimeoutError: page.waitForURL: Timeout 15000ms exceeded
  at LoginPage.login (lib/pkp/playwright/pages/LoginPage.js:49)
  at ensureAuthStateFor (lib/pkp/playwright/support/auth.js:99)
  at Object.storageState (lib/pkp/playwright/support/base-test.js:124)
```

This contradicts PRINCIPLES multi-app convention 2 ("code there … resolves personas through
`appContext.seed.actors` … an archetype can be null on an app") — the spec's *second* test
obeys it (`asUser(actors.reviewer)`), the first does not. It will not bite OMP, which has a
Press Editor; OPS is the app that catches it, as the design record predicts.

**Not worked around.** Seeding an `editor.diana` on OPS would mean enrolling her in a group
OPS does not have (the only honest candidate is the manager group, which would normalise
away a real cross-app difference — the thing design record item 3 forbids). No shared file
was touched.

**Proposed fix** (orchestrator's call; app-neutral, uses only existing shared fixtures —
drop `test.use` and resolve the persona in the test):

```js
test.describe('login smoke', () => {
	test('a seeded editorial user lands on the editorial dashboard', {tag: '@smoke'},
		async ({asUser, appContext}) => {
			const {actors, contextPath} = appContext.seed;
			// Every app has SOME senior editorial account; not every app calls it "editor".
			const context = await asUser(actors.editor ?? actors.manager);
			const page = await context.newPage();
			const dashboard = new DashboardPage(page, contextPath);
			await dashboard.goto('editorial');
			await expect(dashboard.heading).toBeVisible();
			await expect(page).not.toHaveURL(/\/login/);
		});
	// second test: same, plus its existing hasReviewerRoles skip
});
```

**Evidence the rest of the OPS fleet is sound.** A temporary app-side mirror of
`login.spec.js` — byte-for-byte identical except that the default actor comes from
`appContext.seed.actors` — was run twice at 2 workers and then deleted:

```
RUN 1 (cold .auth, 2 workers)          RUN 2 (warm, 2 workers)
✓ [setup] base context seeded          ✓ [setup] base context seeded
- a second actor … @smoke   SKIPPED    - a second actor … @smoke   SKIPPED
✓ a seeded editorial user lands …      ✓ a seeded editorial user lands …
✓ … (moderator variant, asUser)        ✓ … (moderator variant, asUser)
1 skipped · 3 passed (6.2s)            1 skipped · 3 passed (4.5s)
```

The skipped test is the reviewer-gated one — `hasReviewerRoles: false` on OPS — which is the
capability-gating evidence the acceptance bar asks for. The third test proves the
multi-actor `asUser` path works on OPS with a Moderator in the reviewer's place.

### 7.2 ⚠️ Parity gap (not blocking, out of step-2 scope): OPS submissions carry a GALLEY

A live browser probe of the real OPS submission wizard (the affordance had to be seen, not
read) shows its "Upload Files" step is a **galley** step: it asks for a *Galley Label* and
*Language*, creates the galley, then opens a legacy 3-step modal ("1. Upload File → 2.
Review Details → 3. Confirm") with a *Preprint Component* genre select. A real OPS preprint
therefore ends up with a galley whose file is at `SUBMISSION_FILE_PROOF`.

The shared builder creates a `SUBMISSION_FILE_SUBMISSION` file and **no galley** — correct
per the brief (galleys are not step-2 schema in any app), and it does not affect the
submit-step side effects that were checked (§5.6 is full parity). But a seeded OPS preprint
is *not* file-shaped like a real one, so any step-3 spec about files, galleys or the
reader-side download must seed its galley itself until the schema grows one.

Related and worth the orchestrator's eye: an OPS **author** cannot write
`SUBMISSION_FILE_SUBMISSION` through the REST API at all. `SubmissionFileStageAccessPolicy`
grants that stage to an author only when `$stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION]`
exists — stage 1, which OPS does not have — while
`APP\submissionFile\Repository::getAssignedFileStages()` adds only `SUBMISSION_FILE_PROOF`
for an author at Production. Verified live: `POST …/files fileStage=2` → 403
`api.submissionFiles.403.unauthorizedFileStageIdWrite`; `fileStage=10` → 201. That is
consistent with the galley-shaped wizard, so it is probably by design rather than a defect —
but `lib/pkp/pages/submission/PKPSubmissionHandler.php:569` still advertises
`'fileStage' => SUBMISSION_FILE_SUBMISSION` to the wizard's file panel in every app,
which is at least confusing. Product judgement is not mine; recording it.

### 7.3 ℹ️ Scratch contexts keep the auto-created default section

A scratch OPS server declaring a section other than `PRE` ends up with both. Same as OJS;
noted so no step-3 spec asserts "the scratch server has exactly one section".

### 7.4 ℹ️ `installTest.php` is duplicated per app

`ops-main/tools/installTest.php` differs from `ojs-main`'s by exactly one line (the OAI
repository id). Per-app duplication was the brief's instruction and it is fine, but the
§11 self-healing logic now lives in three places; if it changes again, it changes three
times. A shared `lib/pkp/tools/installTestBase.php` would be a small later cleanup.

---

## 8. Proposed rows for `docs/e2e/app-changes.md`

| Date | App | Change | Where | Why |
|---|---|---|---|---|
| 2026-07-27 | OPS | `/config.test.inc.php` and the four Playwright paths added to `.gitignore` | `ops-main/.gitignore` | The test config holds local DB credentials and a generated app key, like the already-ignored `/config.inc.php`; `.env.playwright` holds the test-API secret; `.auth/`, `.results/`, `playwright-report/` are run artifacts. |
| 2026-07-27 | OPS | New test-only files (not app behaviour): `api/v1/_test/*`, `tools/installTest.php`, `tools/testServer.sh`, `playwright/*`, `.env.playwright.example`, eight `test:e2e:*` npm scripts, `@playwright/test` devDependency | — | OPS's slot in the Playwright fleet (port 8200, Postgres `ops_test`). The `_test` namespace is inert without `TEST_API_KEY` in the server's process environment. |
| 2026-07-27 | OPS | `users[].sections` overlay implemented — Moderator → section assignment through `SubEditorsDAO::insertEditor()` | `ops-main/api/v1/_test/ServerScenarioController.php` | On OPS this is load-bearing, not cosmetic: a Moderator holds ROLE_ID_SUB_EDITOR and sees only submissions they are assigned to, and `SubEditorsDAO::assignEditors()` (fired by the `SubmissionSubmitted` listener) is what assigns them. Without it, every seeded preprint is invisible to every moderator. |

## 9. Proposed rows for `docs/e2e/scenario-processor-audit.md`

| Date | App | Builder path | Parity check | Verdict | Notes |
|---|---|---|---|---|---|
| 2026-07-27 | OPS | submission · submit | Notifications, `stage_assignments`, `event_log` and `email_log` vs a preprint created and submitted through the real submission controllers as `author.alex` over an authenticated session | ✅ parity | Six notification types identical row-for-row and user-for-user, including `APPROVE_SUBMISSION` / `FORMAT_NEEDS_APPROVED_SUBMISSION` (the pair OJS's stage-A fix added to `submitSubmission()` — it carries to OPS unchanged). Stage assignments identical, including the two Moderators auto-assigned by the `SubmissionSubmitted` → `AssignEditors` listener. `metadataUpdated` differs 1 vs 2 only because the control wrote publication metadata twice. |
| 2026-07-27 | OPS | submission · publish (`published: true`) | `submissions.status/stage_id`, `edit_decisions`, and reader-side reachability | ✅ real path | `Repo::publication()->validatePublish()` + `->publish()` acting as an editorial user, which is what OPS's `canCurrentUserPublish()` requires (authors cannot post themselves without a screening plugin). The app's own `ApplyDoneWorkflowStage` listener then records an unrequested `moveToDone` and moves the submission to `WORKFLOW_STAGE_ID_DONE` — reproduced, not simulated. Verified end to end: the posted preprint is anonymously reachable at `/en/preprint/view/N` and listed on the server home page and the section listing, while a submitted-but-unposted one 404s. |
| 2026-07-27 | OPS | submission · `reviewRounds` refusal | `POST scenarios/submission` with a `reviewRounds` block | ✅ refused | `Application::getReviewStages()` is empty on OPS, so the shared builder throws before touching anything: 400, `specKey reviewRounds`. `promoteToReviewDecision()` is left at the base class's `null` — the absence is the declaration. |
| 2026-07-27 | OPS | bootstrap/context · `users[].sections` | `GET /publicknowledge/api/v1/submissions` in three real authenticated sessions after seeding a submitted preprint | ✅ real path | Assignment through `SubEditorsDAO::insertEditor(contextId, sectionId, userId, ASSOC_TYPE_SECTION, userGroupId)`, restricted to `PKPSectionForm::$assignableRoles`. `sectioneditor.ana` (assigned) sees the submitted and posted preprints; `sectioneditor.omar` — same Moderator group, no section assignment — sees none, which is the control that makes ana's list mean something; `manager.maya` sees all three including the draft. Read-back avoids `SubEditorsDAO::editorExists()` (broken SQL, stage-B §7.3). |
| 2026-07-27 | OPS | context · scratch server | Server created through `app()->get('context')->add()` | ✅ real path | Genres ("Preprint Text" first), the five user groups, email templates, navigation menus and the default section all come from the real service. The base seed names abbrev `PRE`, which matches the auto-created default section, so it is EDITED — exactly one section survives. A scratch server naming a different abbrev keeps `PRE` too (§7.3). |
| 2026-07-27 | OPS | seeding mail | Mailpit, scoped by recipient + OPS-only tag, with a positive control bounding the negatives | ✅ suppressed | `Mail::fake()` for the seeding request: 0 hits for `"ops-parity-seed"` and `"ops-b-submitted"`, 1 hit for the real path's `"ops-parity-ctl"` acknowledgement, taken the same way in the same run. `email_log` rows still written on both sides. `clearAll()` never called, no global counts asserted. |

---

## 10. Reproduce

```bash
cd /Users/jarda/git/pkp/pkp-main/ops-main
git -C lib/pkp fetch origin e2e_ng && git -C lib/pkp reset --hard origin/e2e_ng
cp .env.playwright.example .env.playwright   # edit PKP_CONFIG_FILE if your paths differ
npm run test:e2e:install                     # once
npm run test:e2e:reset                       # recreate ops_test + install the schema
npm run test:e2e:setup                       # seed (≈3 s warm) — run again for the no-op
npm run test:e2e:ops                         # empty suite until step 3
```

Requires Mailpit on `:8025` and, in `config.test.inc.php`, `installed_locales = en,fr_CA`.
`npm run test:e2e` currently ends red on the two shared smoke tests — §7.1, not an OPS
defect. The environment was left with a freshly reset `ops_test`, the base seed in place and
`tools/testServer.sh` running on 8200.
