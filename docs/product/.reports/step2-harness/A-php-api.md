# Step 2 · Stage A — PHP-side test API for the OJS Playwright harness

**Date:** 2026-07-27 · **Repo:** `ojs-main` (branch `e2e_ng`) + `ojs-main/lib/pkp`
**Frame:** QA test-infrastructure work on a local disposable test install with seeded
fixture accounts. Nothing here touches real users, real data or third-party systems.
**Status:** built and verified end-to-end on a freshly installed `ojs_test` database.
Nothing committed, nothing pushed. PROGRESS/atlas/app-changes untouched (proposed
rows below).

---

## 1. What was built

### 1.1 Shared layer (`lib/pkp`)

| File | Lines | Role |
|---|---|---|
| `lib/pkp/classes/testing/TestApiGate.php` | 73 | The shared secret. `isEnabled()` / `accepts($header)`, read from `TEST_API_KEY` in the process environment with `$_SERVER`/`$_ENV` fallback. No config-file fallback, no default value. |
| `lib/pkp/classes/testing/RequireTestApiKey.php` | 46 | Laravel middleware on every `_test` route: 404 when the env var is absent, 403 when `X-Test-Key` does not match. |
| `lib/pkp/classes/testing/scenario/ScenarioException.php` | 43 | Carries the offending **spec key** and HTTP status through to the response. |
| `lib/pkp/classes/testing/scenario/SpecValidator.php` | 150 | Two-pass validation (see §2.3) + `withOverlay()` for app overlay properties. |
| `lib/pkp/classes/testing/scenario/BuildJournal.php` | 144 | Failure hygiene: records created contexts/submissions/users, rolls them back through the real repositories on failure, tags survivors `[ORPHAN] ` if a delete itself fails. |
| `lib/pkp/classes/testing/scenario/schema/context.json` | — | App-neutral context spec. |
| `lib/pkp/classes/testing/scenario/schema/submission.json` | — | App-neutral submission spec. |
| `lib/pkp/classes/testing/scenario/schema/user.json` | — | User spec, shared by both endpoints. |
| `lib/pkp/api/v1/_test/PKPTestApiController.php` | 455 | Base controller: routing/gating, `Mail::fake()` + rollback wrapper, `actingAs()`, `inContext()`, `withRequestVars()`, user seeding, role→user-group resolution, `initialStageId()`. |
| `lib/pkp/api/v1/_test/PKPContextScenarioController.php` | 223 | `POST scenarios/context`. Context creation, declared setting passthrough, categories, `afterContextCreated()` overlay hook. |
| `lib/pkp/api/v1/_test/BootstrapRoutes.php` | 110 | `POST/GET bootstrap`. A **trait**, see §5.1. |
| `lib/pkp/api/v1/_test/PKPSubmissionScenarioController.php` | 734 | `POST scenarios/submission`. Submission creation, real submission file, submit, decisions, review rounds + reviewers, publish. |

### 1.2 OJS layer (`ojs-main`)

| File | Lines | Role |
|---|---|---|
| `api/v1/_test/index.php` | 47 | Dispatcher. Emits 404 and exits when the gate is closed — the namespace is not registered at all. Otherwise selects the controller from `PATH_INFO`. |
| `api/v1/_test/JournalScenarioController.php` | 216 | OJS context overlay: `sections`, `issues`, `publishingMode`, `onlineIssn`, `printIssn`. |
| `api/v1/_test/TestBootstrapController.php` | 37 | `extends JournalScenarioController` + `use BootstrapRoutes` — inherits the OJS overlay. |
| `api/v1/_test/SubmissionScenarioController.php` | 143 | OJS submission overlay: `section` (by abbrev), `issue` (matched on volume/number/year); declares `sendExternalReview` as the review-promoting decision. |
| `tools/installTest.php` | 206 | Non-interactive installer driven entirely by the active config file; optional `--recreate-db`. |
| `tools/testServer.sh` | 60 | Start/stop/restart `php -S` with an absolute `-t` docroot and the two env vars exported. |

### 1.3 App-code / repo-file changes

1. **`lib/pkp/classes/config/Config.php`** — one line:
   ```php
   define('CONFIG_FILE', getenv('PKP_CONFIG_FILE') ?: \PKP\core\Core::getBaseDir() . '/config.inc.php');
   ```
2. **`ojs-main/.gitignore`** — added `/config.test.inc.php` (it holds local DB credentials and an app key, exactly like the already-ignored `/config.inc.php`).

Not committed. Proposed `app-changes.md` rows in §6.

---

## 2. Design decisions

### 2.1 Config selection: `PKP_CONFIG_FILE`, not a router script

`CONFIG_FILE` is a `define()` evaluated once when `PKP\config\Config` loads. Three
options were considered:

- **A router script for `php -S`** that sets `Registry::set('configFile', …)` before
  bootstrap. Rejected: `PKPAppKey::refreshEncrypter()` and
  `writeAppKeyVariableToConfig()` both call `Config::setConfigFileName(CONFIG_FILE)`,
  which would silently reset the registry back to `config.inc.php` mid-install —
  the installer would write the app key into the *development* config.
- **`php -d auto_prepend_file=…`.** Same defect as above, plus a `-d` flag on every
  invocation.
- **Chosen: make `CONFIG_FILE` itself honour an environment variable.** Every
  existing `setConfigFileName(CONFIG_FILE)` then resolves to the test config too, so
  install-time config writes land in the right file. Absent the variable — which is
  every production install — the constant is byte-for-byte what it was.

Verified both directions: a server started with `PKP_CONFIG_FILE` serves `ojs_test`
(session cookie `OJSTESTSID`, no journals before bootstrap); a server started with no
environment at all serves the development install unchanged.

### 2.2 Gating

The namespace exists only when `TEST_API_KEY` is in PHP's process environment:
`api/v1/_test/index.php` answers 404 and exits before any route is registered.
When it is present, `RequireTestApiKey` is the sole route-group middleware and
`authorize()` returns true — there is no session, no CSRF and no role policy, because
the caller is a test runner. `has.user` is deliberately NOT in the middleware list,
which is also what keeps `ValidateCsrfToken` out of the way (it only applies to routes
carrying `HasUser`).

`php -S` inherits the shell environment, so `export TEST_API_KEY=… ; php -S …` is
enough. `TestApiGate::configuredKey()` falls back to `$_SERVER`/`$_ENV` for SAPIs that
populate those instead of `getenv()` — the recorded trap ("the key silently didn't
reach PHP") is otherwise indistinguishable from "the endpoint doesn't exist".

### 2.3 Schema validation: two passes

Opis short-circuits on the first failing keyword: given a payload with both a stray
top-level key and a nested error, it reports only the nested one. Since "never silently
drop a spec key" is the whole point, `SpecValidator` runs its own recursive
unknown-key walk **first** (honouring `additionalProperties: false` + `items`), which
yields a dotted path, then hands the payload to Opis for types/required/enums.

```
{"error":"Unknown key 'users.0.superpower' …","specKey":"users.0.superpower"}   HTTP 400
```

Opis (`opis/json-schema` 2.x) is present in `lib/pkp/lib/vendor/opis/json-schema`,
installed as a `require-dev` transitive of `laravel/framework`. Noted as a caveat:
a `composer install --no-dev` tree would not have it — acceptable, since the whole
namespace is dev-only, and `SpecValidator` fails with an explicit message rather than
silently skipping validation.

### 2.4 App-neutral core + declared overlays

`schemaOverlayProperties()` on the app controller is the only way app concepts enter
a schema. Because the overlay is *declared*, `section` on OJS validates and `section`
on a hypothetical OPS controller that doesn't declare it fails as an unknown key —
never silently ignored.

Nothing in `lib/pkp` names a workflow stage:

- initial stage → `Application::get()->getApplicationStages()` (first entry)
- review stage → `Application::get()->getReviewStages()` (last entry; `null` ⇒ the
  app has no review stage and `reviewRounds` is rejected with that message)
- the decision that opens review → `promoteToReviewDecision()`, `null` in the base
  class, `'sendExternalReview'` in the OJS subclass
- decision names → resolved against `Repo::decision()->getDecisionTypes()`, i.e. the
  app's own roster; an unknown name returns 400 listing every name the app offers.

Roles are resolved by the user group's stored `nameLocaleKey`
(`default.groups.name.<role>`), which every app writes from its own
`registry/userGroups.xml`. No role ids, no translated names. An unresolvable role
returns 400 listing the roles that context actually has.

### 2.5 Real services only

| State | Service driven |
|---|---|
| context | `app()->get('context')->add()/edit()` |
| sections / issues / categories | `Repo::section()`, `Repo::issue()`, `Repo::category()` |
| users | `Repo::user()->add()` + `Repo::userGroup()->assignUserToGroup()` (the registration-form shape) |
| submission | `Repo::submission()->add()` + `Repo::stageAssignment()->build()` + `Repo::author()->newAuthorFromUser()` — mirroring `PKPSubmissionController::add()` |
| submission file | `app()->get('file')->add()` + `Repo::submissionFile()->add()` |
| submit | `Repo::submission()->submit()` **plus** `NotificationManager::updateNotification([APPROVE_SUBMISSION])` (see §4.4) |
| decisions | `Repo::decision()->validate()` then `->add()` |
| reviewers | `EditorAction::addReviewer()` then the `dateNotified`/`considered` stamp the Add Reviewer form applies |
| reviewer accept/decline | `ReviewerAction::confirmReview()` |
| publish | `Repo::publication()->validatePublish()` then `->publish()` |

Three plumbing helpers make those services behave as they do inside the app:

- **`actingAs($user, …)`** — sets `Registry::set('user', …)`, which is where
  `PKPRequest::getUser()` reads the acting user. Each step runs as the right person:
  the author submits, the editor decides, the reviewer confirms.
- **`inContext($context, …)`** — the namespace is site-wide (bootstrap creates the
  very context everything else lives in), but services such as
  `EditorAssignmentNotificationManager` call `$request->getContext()->getId()`
  directly. `PKPRouter::$_context` is public, so the builder sets it for the duration
  of the build and restores it after. Without this the first decision fatals.
- **`withRequestVars([...], …)`** — `EditorAction::addReviewer()` reads `template`
  and `personalMessage` from the request, exactly as the Add Reviewer form posts them.
  The builder merges them into the Illuminate request (which `getParameter()` reads)
  and restores the previous input afterwards.

### 2.6 `Mail::fake()`

Applied in `PKPTestApiController::build()`, i.e. for the whole seeding request only.
Verified: a scenario that sends a reviewer request and decision notifications left
Mailpit's message count unchanged at 500, while `email_log` rows were still written —
so log-side parity holds and mail assertions in tests see only test-action mail.

### 2.7 Failure hygiene: compensating deletes, orphan tagging as fallback

`BuildJournal` records every created context / submission / user and, on any
exception, deletes them newest-first through `Repo::submission()->delete()`,
`app()->get('context')->delete()`, `Repo::user()->delete()`. Chosen over a DB
transaction because the builder also writes files and fires events/jobs; a rollback
would leave those inconsistent and hide the failure. If a compensating delete itself
throws, the survivor's title/name is prefixed `[ORPHAN] ` and named in the `orphans`
array of the error response, so a half-built entity can never read as a usable seed.

### 2.8 Bootstrap idempotency: **no-op**, not failure

`POST /bootstrap` on an already-seeded database answers `200 {"seeded": false,
"reason": "already-seeded", "contextId": N}`. Rationale: the JS setup project uses
"detect seeded → skip", and every worker asks for the base seed; a hard failure there
would turn a warm database red for a reason that is not a test failure. `GET
/bootstrap?context=publicknowledge` is the cheap probe the JS side can use instead of
POSTing at all.

---

## 3. The step-2 schema (whole surface)

**`POST /api/v1/_test/bootstrap`**

```
{ context: { urlPath*, name*, acronym, description, primaryLocale, supportedLocales,
             categories[{path*, title*, description, parentPath}],
             contactName, contactEmail, supportName, supportEmail, mailingAddress,
             disableSubmissions, enableAnnouncements, enablePublicComments, enableDois,
             copyrightNotice, defaultReviewMode, numWeeksPerResponse, numWeeksPerReview,
             keywords, citations,
             — OJS overlay — sections[{title*, abbrev*, policy, editorRestricted,
               abstractsNotRequired, wordCount, identifyType}],
                              issues[{title, volume, number, year, description,
                                      published, current, accessStatus}],
                              publishingMode, onlineIssn, printIssn },
  users: [{ username*, roles*[], password, givenName, familyName, email, country, affiliation }] }
```

**`POST /api/v1/_test/scenarios/context`** — same context object, `tag*` required
(≤64 chars), `users` nested inside, `urlPath` defaults to `scratch-<slug(tag)>`.

**`POST /api/v1/_test/scenarios/submission`**

```
{ tag*, context*(urlPath), submitter*(username), title, abstract, locale,
  submitted, published,
  decisions: [{ decision*, editor }],
  reviewRounds: [{ reviewers: [{ user*, status: invited|accepted|declined }] }],
  — OJS overlay — section (abbrev), issue {volume, number, year} }
```

Response echoes ids: `submissionId`, `publicationId`, `submissionFileId`,
`decisionIds[]`, `reviewRounds[{id, round, status, reviewAssignments[{id, reviewerId,
username, status, dateConfirmed, declined}]}]`, `stageId`, `status`,
`submissionProgress`, `dateSubmitted`, `title`, plus OJS `sectionId` / `issueId`.

Semantics worth knowing:

- `submitted: false` seeds a wizard-resumable draft at parity: `submissionProgress =
  'start'`, no `dateSubmitted`, author stage assignment with `canChangeMetadata`.
- **Ordering:** all `decisions` run first, in order; then `reviewRounds[i]` is applied
  to review round `i+1`, which must already exist (round 1 is opened by
  `promoteToReviewDecision()` if the spec did not ask for it). Reviewers therefore
  land in the right round, but a round's `status` reflects the decision history, not
  the order reviewers were added — which matches the recorded OJS quirk that
  `newExternalRound` overwrites the previous round's status anyway.
- Every seeded submission gets a real PDF submission file. The PDF is *generated*
  (a minimal valid one-page document) rather than shipped as a binary fixture, so the
  PHP layer has no asset to keep in sync; the JS layer's richer fixture files take
  over when a test needs specific content.
- `decisions[].editor` should be named explicitly. When omitted the builder picks the
  first user in an editorial group, which on a freshly bootstrapped journal is `admin`
  (the context service enrols the creating user as manager).

---

## 4. Verification (all at HTTP/DB level)

Environment: `config.test.inc.php` (Postgres `ojs_test`, files dir
`/Users/jarda/git/pkp/pkp-main/files-test`, `[schedule] task_runner = Off`,
`[queues] job_runner = Off`, `enable_beacon = Off`, session cookie `OJSTESTSID`),
`php -S 127.0.0.1:8000 -t /Users/jarda/git/pkp/pkp-main/ojs-main` started via
`tools/testServer.sh`. The stale pre-reset process on :8000 was killed.

### 4.1 Gate (three checks)

```
GATE (a) correct key            200
GATE (b) missing key            403
GATE (b2) wrong key             403
GATE (c) server started with no TEST_API_KEY, correct header sent:
   {"error":"api.404.endpointNotFound", …}   HTTP 404
   (control: same server serves / with 200 — the app is up, the namespace is not)
```

### 4.2 Fresh install → bootstrap

```
$ PKP_CONFIG_FILE=…/config.test.inc.php php tools/installTest.php --recreate-db
Recreated database 'ojs_test'.
… 40 migrations … createData, createConfig, addPluginVersions, installDefaultNavigationMenus …
Successfully installed version 3.6.0.0
Restored base_url=http://127.0.0.1:8000 and allowed_hosts in …/config.test.inc.php
```

```
POST /bootstrap (cold) → 200
{"seeded":true,"contextId":1,"urlPath":"publicknowledge",
 "users":{"manager.maya":2,"editor.diana":3,"sectioneditor.ana":4,
          "reviewer.julia":5,"author.alex":6},
 "sections":{"ART":1,"REV":2},"issues":[1,2]}

POST /bootstrap (warm) → 200 {"seeded":false,"reason":"already-seeded","contextId":1}
GET  /bootstrap?context=publicknowledge → {"seeded":true,"contextId":1}
```

Rows present — `journals` 1 row, `sections` 2, `issues` 2 (2014 published + current,
2015 unpublished), `categories` 2 (nested), and role enrolments:

```
 username          | role_id | nameLocaleKey
 manager.maya      |      16 | default.groups.name.manager
 editor.diana      |      16 | default.groups.name.editor
 sectioneditor.ana |      17 | default.groups.name.sectionEditor
 reviewer.julia    |    4096 | default.groups.name.externalReviewer
 author.alex       |   65536 | default.groups.name.author
```

The design record's CAUTION is reproduced and preserved: role key `editor` resolves
to the "Journal editor" group, which is `ROLE_ID_MANAGER` (16), not sub-editor.

**Login smoke:** `author.alex` / `author.alexauthor.alex` (the doubled-username rule)
logs in over the web form (302 → index) and reaches
`/publicknowledge/dashboard/mySubmissions` (200).

### 4.3 Scenarios

```
scenarios/context → 200
{"tag":"e2e-ctx-1","contextId":2,"urlPath":"scratch-e2e-ctx-1",
 "users":{"tw-mgr.e2e-ctx-1":7,"tw-se.e2e-ctx-1":8}}

unknown key (top level)  → 400  specKey: "bogusSetting"
unknown key (nested)     → 400  specKey: "users.0.superpower"
```

```
(a) draft      submitted:false → stageId 1, submissionProgress "start", dateSubmitted null
(b) in review  sendExternalReview + 1 accepted reviewer
               → stageId 3, reviewRound {id, round 1, status 7},
                 reviewAssignment {reviewerId 5, dateConfirmed set, declined false}
(c) published  skipExternalReview + sendToProduction + published:true, issue 1/2/2014
               → status 3 (PUBLISHED), issueId 1, publishedPublicationId set
(d) two rounds sendExternalReview → requestRevisions → newExternalReviewRound,
               reviewers per round → rounds 1 and 2 each with the named reviewer
```

DB confirmation for (b)/(c):

```
review_rounds:       (sub 2, stage 3, round 1, status 7)
review_assignments:  date_assigned ✓ date_notified ✓ date_confirmed ✓ declined 0
edit_decisions:      sendExternalReview(3) stage 1 · accept(2) stage 3 round 1
                     · sendToProduction(7) stage 4 · moveToDone(33) stage 5
```

`moveToDone` was **not** in the spec — it is recorded by the app's own
`ApplyDoneWorkflowStage` listener on publish. Real behaviour, reproduced.

### 4.4 Notification parity (spot-check)

Control: a submission created and submitted through the **real REST API** as
`author.alex` over an authenticated browser session (`POST
/publicknowledge/api/v1/submissions`, `PUT …/publications/N`, `POST …/files`
multipart, `PUT …/submit`) — the same controller the wizard's final step calls.

First comparison exposed a genuine gap:

| type | UI path (sub 4) | seeded (sub 5) |
|---|---|---|
| EDITOR_ASSIGNMENT_SUBMISSION/EXTERNAL_REVIEW/EDITING/PRODUCTION | ✓ ×4 | ✓ ×4 |
| EDITOR_ASSIGNMENT_REQUIRED (users 1,2,3) | ✓ | ✓ |
| **APPROVE_SUBMISSION** | ✓ | **missing** |
| **FORMAT_NEEDS_APPROVED_SUBMISSION** | ✓ | **missing** |

Cause: `PKPSubmissionController::submit()` calls
`NotificationManager::updateNotification([NOTIFICATION_TYPE_APPROVE_SUBMISSION], …)`
*after* `Repo::submission()->submit()` — a side effect that lives in the controller,
not the repository. Fixed by calling the same notification service from
`submitSubmission()` (not by writing rows). Re-run:

```
 submission |   type   | level | rows | users
          4 | 16777220 |     3 |    1 | ALL      6 | 16777220 |     3 |    1 | ALL
          4 | 16777222 |     3 |    1 | ALL      6 | 16777222 |     3 |    1 | ALL
          4 | 16777223 |     3 |    1 | ALL      6 | 16777223 |     3 |    1 | ALL
          4 | 16777224 |     3 |    1 | ALL      6 | 16777224 |     3 |    1 | ALL
          4 | 16777243 |     2 |    1 | ALL      6 | 16777243 |     2 |    1 | ALL
          4 | 16777245 |     2 |    1 | ALL      6 | 16777245 |     2 |    1 | ALL
          4 | 16777247 |     3 |    3 | 1,2,3    6 | 16777247 |     3 |    3 | 1,2,3
```

Full parity. `stage_assignments` identical (`user 6, group 14, can_change_metadata 0`);
`event_log` identical (`metadataUpdated`, `fileRevised`, `submissionSubmitted`).

### 4.5 Failure hygiene

```
nonexistent section        → 400 "…no section with abbreviation 'NOPE'. Available: ART, REV."
mid-build failure          → 400 specKey "reviewRounds.0.reviewers.0.user"
   (submission + sendExternalReview decision + review round were already created)
scratch-context mid-build  → 400 specKey "users.1.roles.0"
   (context + first throwaway user were already created)
```

After all three: `submissions` count unchanged (7 → 7), `journals` still only
`publicknowledge` + the one intentional scratch journal, `users` still 8 (the
already-created `tw-ok.e2e-ctx-fail` is gone), zero `[ORPHAN] ` titles — rollback
succeeded, so the fallback never had to fire.

### 4.6 Mail

```
mailpit messages before scenario seed: 500
seed: submission + sendExternalReview + reviewer invite + reviewer accept
mailpit messages after:                500     (email_log rows: 28)
```

Test-action mail still flows: the UI-path control submission produced a real "Thank
you…" message in Mailpit addressed to `author.alex@example.org`.

### 4.7 Reproduce

```bash
cd /Users/jarda/git/pkp/pkp-main/ojs-main
PKP_CONFIG_FILE=$PWD/config.test.inc.php php tools/installTest.php --recreate-db
tools/testServer.sh restart          # exports PKP_CONFIG_FILE + TEST_API_KEY
curl -X POST -H 'X-Test-Key: playwright-test-key' -H 'Content-Type: application/json' \
     --data-binary @bootstrap.json \
     http://127.0.0.1:8000/index.php/index/api/v1/_test/bootstrap
```

URL shape: the namespace is site-wide, so the path is
`/index.php/index/api/v1/_test/…` (context segment `index`).

---

## 5. Deviations from the design record

1. **`BootstrapRoutes` is a trait, not `PKPBootstrapController`.** The record names a
   bootstrap controller; PHP has single inheritance and bootstrap needs *both* the
   base bootstrap behaviour *and* each app's context overlay. Each app's bootstrap
   controller therefore extends that app's context scenario controller and mixes the
   trait in (`TestBootstrapController extends JournalScenarioController use
   BootstrapRoutes`). Same routes, same semantics.
2. **The scenario route is `scenarios/context`, not `scenarios/journal`.** PRINCIPLES
   §1 still says `POST /api/v1/_test/scenarios/journal`, but the same document's
   design record §5 requires `context`, never `journal`, in shared code and schema.
   The app-neutral name won; the OJS controller is still `JournalScenarioController`
   as the record names it. **PRINCIPLES §"Architecture principles" 1 should be updated
   to say `scenarios/context`** (flagged, not edited — docs are the orchestrator's).
3. **Step-2 subset only**, as briefed. Deliberately not built: galleys, metrics,
   subscriptions, media files, review forms, reviewer suggestions, user comments,
   `commentsForEditor`, per-decision `toAuthor`/`toReviewers`/`toEditor`, `author.orcid`.
4. **`users[].sections`** (section-editor → section assignment, e.g.
   `sectioneditor.ana` → ART) is **not** implemented. `users.md` documents it as part
   of the base roster; it is a gap for a later stage, not a step-2 deliverable.
5. **Reset tool.** The record mentions `npm run test:e2e:reset`; the PHP-side reset is
   `tools/installTest.php --recreate-db`, which the npm script will wrap in stage B.

---

## 6. Proposed rows for `docs/e2e/app-changes.md`

> Do not paste verbatim if the file's column set differs — the content is what matters.

| Date | App | Change | Where | Why |
|---|---|---|---|---|
| 2026-07-27 | shared (lib/pkp) | `CONFIG_FILE` now honours a `PKP_CONFIG_FILE` environment variable (one line) | `lib/pkp/classes/config/Config.php:28` | The e2e fleets must run against `config.test.inc.php` without touching `config.inc.php`. A router-script/registry approach breaks because `PKPAppKey::refreshEncrypter()` and `writeAppKeyVariableToConfig()` reset the registry to `CONFIG_FILE`, so the installer would write the app key into the development config. No behaviour change when the variable is unset (every production install). |
| 2026-07-27 | OJS | `/config.test.inc.php` added to `.gitignore` | `ojs-main/.gitignore` | The test config holds local DB credentials and a generated app key, like the already-ignored `/config.inc.php`. |
| 2026-07-27 | OJS | New test-only files (not app behaviour): `api/v1/_test/*`, `tools/installTest.php`, `tools/testServer.sh`; shared: `lib/pkp/api/v1/_test/*`, `lib/pkp/classes/testing/*` | — | The harness's PHP seeding API. Inert without `TEST_API_KEY` in the process environment. |

## 7. Proposed rows for `docs/e2e/scenario-processor-audit.md`

| Date | App | Builder path | Parity check | Verdict | Notes |
|---|---|---|---|---|---|
| 2026-07-27 | OJS | submission · submit | Notifications + `event_log` + `stage_assignments` vs a submission created and submitted through the real REST API as `author.alex` | ✅ parity after fix | `Repo::submission()->submit()` alone omits `APPROVE_SUBMISSION` / `FORMAT_NEEDS_APPROVED_SUBMISSION`: the submit **controller** refreshes them via `NotificationManager::updateNotification()` after the repository call. The builder now calls that same service. |
| 2026-07-27 | OJS | submission · decisions | `edit_decisions`, `submissions.stage_id`, `review_rounds`, `event_log` after `sendExternalReview`/`accept`/`sendToProduction`/`published` | ✅ real path | Decisions go through `Repo::decision()->validate()` + `->add()`; the app's own `ApplyDoneWorkflowStage` listener adds the unrequested `moveToDone` decision on publish, as it does in the UI. |
| 2026-07-27 | OJS | submission · reviewers | `review_assignments` (`date_assigned`/`date_notified`/`date_confirmed`/`declined`), `event_log` (`reviewerAssigned`, `reviewAccepted`) | ✅ real path | `EditorAction::addReviewer()` + the Add Reviewer form's post-assign `dateNotified`/`considered` stamp; accept/decline via `ReviewerAction::confirmReview()`. The invitation body is taken from the `REVIEW_REQUEST` email template, which is what the form's pre-filled message box posts. |
| 2026-07-27 | OJS | context · scratch journal | Journal created through `app()->get('context')->add()` | ✅ real path | Genres, user groups, email templates, navigation menus and the default section all come from the real service. Sections named in the spec are matched to the auto-created default by abbrev and **edited**, so no stray "Articles" section survives. |
| 2026-07-27 | shared | seeding mail | Mailpit message count before/after a seed that invites and confirms a reviewer | ✅ suppressed | `Mail::fake()` for the seeding request only; `email_log` rows are still written, so mail-log assertions keep working. |

---

## 8. Observations worth a second look (not blocking)

1. **Empty personal message crashes Add Reviewer.** `EditorAction::addReviewer()` puts
   `$request->getUserVar('personalMessage')` straight into the mailable body; an empty
   string makes `Repo::emailLogEntry()->logMailable()` → `Mailable::render()` →
   `Mailer::renderView(null)` throw `InvalidArgumentException`. In the UI the box is
   pre-filled from the template, so it is normally non-empty — but the form itself
   initialises `personalMessage` to `''` (`AdvancedSearchReviewerForm.php:97`), so a
   user who clears the box appears to hit a 500. Not a harness issue (the builder now
   supplies the template body); worth a probe when the peer-review feature is specified.
2. **A journal with no `contactEmail` cannot accept a real submission.** Submitting
   through the wizard/REST path fails with `An email must have a "From" or a "Sender"
   header` while sending the acknowledgement — after the submission has already been
   marked submitted, leaving a partially-notified submission. Found because the first
   bootstrap payload omitted the contact address. The base seed now sets
   `contactName`/`contactEmail`/`supportName`/`supportEmail`, and those keys are
   declared passthroughs. Product-side judgement (should the wizard block earlier?)
   belongs to whichever spec owns journal setup.
3. **`opis/json-schema` is a transitive dev dependency** of `laravel/framework`, not a
   direct one. If lib/pkp ever installs with `--no-dev` in an environment that also
   sets `TEST_API_KEY`, validation fails loudly rather than silently. Adding it to
   lib/pkp's own `require-dev` would make the dependency explicit.

## 9. Handover to stage B (JS)

- Base URL shape: `http://127.0.0.1:8000/index.php/index/api/v1/_test/…`
- Header: `X-Test-Key: playwright-test-key` (value from the environment, not hard-coded)
- Cold-start probe: `GET /bootstrap?context=publicknowledge` → `{"seeded":bool,"contextId":…}`
- Reset: `PKP_CONFIG_FILE=… php tools/installTest.php --recreate-db`
- Serve: `tools/testServer.sh restart [port]` (per-app ports via `TEST_SERVER_PORT`)
- Bootstrap payload used in verification is a good starting point for
  `playwright/fixtures/bootstrap.js`; it currently carries 5 of the 18 documented
  users, and the full roster plus section-editor assignments (§5.4) is stage-B work.

---

## 11. Follow-up (2026-07-27, after stage B): `installTest.php` self-heals from an empty database

Stage B's §7.1 found the one place the tool could not recover on its own: a database
with **no tables at all** — the state a new contributor or a CI job starts from.
`tools/bootstrap.php` boots the application, and the application reads `versions`
whenever the configuration says `installed = On`, so the tool fataled before its own
first line. `--recreate-db` was unaffected (the drop happens after a successful boot),
which is why `npm run test:e2e:reset` always worked.

Stage B's §7.2 also recorded that a hand-made `config.test.inc.php` needs
`[i18n] installed_locales = en,fr_CA`, discovered only as a bootstrap failure deep in
the seed.

### 11.1 What changed

**`tools/installTest.php`** — restructured into a pre-boot half and a post-boot half.

| Piece | Role |
|---|---|
| `TestInstallConfigFile` | Dependency-free configuration access for the moment when no PKP class can be loaded, because loading them is what fails: `parse_ini_file` to read, a line-oriented rewrite to set one key in one section. The rewrite preserves comments and ordering, **only replaces an existing key** (an absent `installed` already means "not installed"), and refuses to write anything that would not parse back. |
| `TestInstallDatabase` | The harness's single driver dispatch, now shared by two callers instead of one: `dsn()`/`connect()`/`recreate()` (the existing drop-and-create) and the new `schemaState()`. No second dispatch was introduced. |
| pre-boot block | Runs before `require bootstrap.php`. If the configuration says `installed = On` but the database is not in a bootable state, it writes `installed = Off` and says so. |
| `execute()` guard | Refuses to install over a database that already has tables unless `--recreate-db` is given, naming that flag. |
| `install()` | Now returns success and the tool **exits non-zero** when the installer fails. |
| `readParams()` | Prints the locales being installed, and a NOTICE naming the consequence and the fix when `installed_locales` is absent. |
| `fixUpConfig()` | Also writes `installed = On`, making "a successful run leaves a usable configuration" an explicit post-condition rather than a side effect of the installer. |

**`schemaState()` — three states, two plain queries.** The first cut asked only "does
the `versions` table exist", and that was wrong: a run killed late leaves 151 tables
and 43 plugin version rows but **no `core` row**, and booting against *that* fatals at
`PKPApplication.php:179` (`VersionDAO::getCurrentVersion()` returns null and is
dereferenced). So:

```
SELECT COUNT(*) FROM versions                                        → fails  ⇒ none
SELECT COUNT(*) FROM versions WHERE current = 1 AND product_type = 'core'  → 0  ⇒ partial
                                                                          → ≥1 ⇒ installed
```

Both are plain SQL every supported driver spells identically — no information-schema
dialects — and the second is literally the question the application asks at boot.
`partial` counts as *not installed* for the flag (so the tool can boot) and as
*already populated* for the guard (so it refuses to install over it).

**Why the flag is left `Off` after a failed install.** That is the state that lets the
NEXT run boot, whatever the database looks like. Restoring `On` on failure would put
the tool back in exactly the corner stage B hit. Verified in §11.2 (3).

**`lib/pkp/api/v1/_test/PKPContextScenarioController.php`** — the uninstalled-locale
rejection now names the remedy, since locales are chosen at install time and cannot be
fixed from the spec:

```
Locale 'de' is not installed on this site (installed: en, fr_CA). Add it to [i18n]
installed_locales in the configuration file and re-run tools/installTest.php --recreate-db.
   specKey: supportedLocales.1
```

**`lib/pkp/playwright/tests/bootstrap.setup.js`** (stage B's file) — its failure message
told the reader to set `installed = Off` by hand. That advice is now wrong, so it was
replaced with the one case that remains: an interrupted install, which
`npm run test:e2e:reset` recovers from. (Running Prettier on the file also normalised
two unrelated pre-existing lines.)

**Where the `installed_locales` requirement lives.** One home, not three: the tool that
installs the locales says what it is installing and what happens if the list is short,
and the endpoint that rejects an uninstalled locale names the fix. Nothing duplicates
the literal `en,fr_CA` outside the app's own bootstrap fixture — so a suite that later
adds a locale cannot leave a stale copy behind.

### 11.2 Verification

**(1) Completely empty database, config says `installed = On`.**

```
$ psql -c 'DROP DATABASE ojs_test' -c 'CREATE DATABASE ojs_test'
tables: 0          installed = On

$ PKP_CONFIG_FILE=…/config.test.inc.php php tools/installTest.php
The database named in …/config.test.inc.php is empty; set installed = Off so the
application can boot. The installer restores it when it finishes.
Using configuration file: …/config.test.inc.php
Installing ojs2 schema into database 'ojs_test' (driver postgres9) …
Installing locales: en, fr_CA
… 40 migrations … Successfully installed version 3.6.0.0
Restored installed=On, base_url=http://127.0.0.1:8000 and allowed_hosts in …
exit=0                                                   installed = On

$ npm run test:e2e:setup
  ✓ [setup] the shared base context is seeded (7.3s)      1 passed (8.3s)
```

And the fully automatic path — empty database, `installed = On`, **only**
`npm run test:e2e:setup`, no `installTest.php` by hand:

```
tables: 0   installed = On   (playwright/.auth wiped, files dir wiped)
$ npm run test:e2e:setup
Installing the application schema: php tools/installTest.php
… 40 migrations … Successfully installed version 3.6.0.0
Restored installed=On, …
  ✓ [setup] the shared base context is seeded (3.1m)      1 passed (3.1m)
installed = On
```

**(2) `--recreate-db` unchanged.**

```
$ npm run test:e2e:reset
Recreated database 'ojs_test'. … Successfully installed version 3.6.0.0
Restored installed=On, … · Cleared cached storage states in …/playwright/.auth
tables after reset: 153        installed = On
$ npm run test:e2e:setup   →  1 passed (8.1s)
```

**(3) Mid-install failure, and recovery from it.** Two shapes, both produced
deliberately.

*Failure before the migrations* (`files_dir` pointed at an uncreatable path):

```
[code: Installer Installer::createDirectories]
ERROR: Installation failed: The directory specified for uploaded files does not exist or is not writable.

The configuration file still says installed = Off, which is what lets the
next run boot. Re-run with --recreate-db to start from a clean database.
real exit code = 1                     installed = Off
```

Restore `files_dir`, then a **plain re-run with no manual flag edit**:

```
$ php tools/installTest.php
… Successfully installed version 3.6.0.0
Restored installed=On, …                installed = On
```

*Failure after the migrations* (install killed during `addPluginVersions`; 151 tables,
43 plugin version rows, no `core` row — the state that used to fatal at boot):

```
$ php tools/installTest.php
The database named in … is partially installed (no core version row); set installed = Off
so the application can boot. The installer restores it when it finishes.
Using configuration file: …
ERROR: database 'ojs_test' is partially installed — a previous run did not finish.
Re-run with --recreate-db to drop and reinstall it (this is what `npm run test:e2e:reset` does).
exit=1                                  installed = Off

$ php tools/installTest.php --recreate-db
… Successfully installed version 3.6.0.0
Restored installed=On, …                exit=0   installed = On
```

**(4) The locale path.**

```
$ (installed_locales commented out) php tools/installTest.php --recreate-db
Installing locales: en
NOTICE: [i18n] installed_locales is not set in …/config.test.inc.php, so only 'en' will be
installed. A seed that declares any other locale will be refused with
"Locale 'xx' is not installed on this site". Add every locale the suite
needs (the base seed uses en,fr_CA) and re-run with --recreate-db.
```

```
POST /scenarios/context {"tag":"locale-msg","supportedLocales":["en","de"]}
→ {"error":"Locale 'de' is not installed on this site (installed: en, fr_CA). Add it to
   [i18n] installed_locales in the configuration file and re-run
   tools/installTest.php --recreate-db.", "specKey":"supportedLocales.1"}
```

**(5) The suite, twice, after all of the above.**

```
RUN 1 (fresh database, cold .auth):  3 passed (12.2s)
RUN 2:                               3 passed (3.6s)
RUN 3 (after the setup.js edit):     3 passed (4.0s)
```

### 11.3 Behaviour changes a reviewer should notice

1. **A failed install now exits 1.** It exited 0 before, which meant stage B's
   `execSync(installCommand)` could not detect a broken install at all — a silently
   wrong-state suite. This is a fix, but it is a behaviour change for any caller that
   ignored the exit code.
2. **Installing over a populated database is refused** rather than attempted. The only
   way to reinstall is `--recreate-db`, which is what `npm run test:e2e:reset` already
   passes. This makes the interrupted-install state actionable instead of confusing.
3. **The tool writes `installed` in the configuration file** (Off before a needed
   install, On after a successful one). It only ever touches a key that already exists,
   validates the result parses, and never adds keys.

### 11.4 Additional proposed `app-changes.md` row

| Date | App | Change | Where | Why |
|---|---|---|---|---|
| 2026-07-27 | OJS | `tools/installTest.php` self-heals from an empty or partially installed database (pre-boot `installed` flag management), refuses to install over a populated database, and exits non-zero on failure | `ojs-main/tools/installTest.php` | The application cannot bootstrap against a database with no `core` version row, which is the state a new contributor, a CI job, or an interrupted install starts from — so `npm run test:e2e:setup` could not bring a fresh checkout up without a manual configuration edit. Test-only tool; no application behaviour changes. |
