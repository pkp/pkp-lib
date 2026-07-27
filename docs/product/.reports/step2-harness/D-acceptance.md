# Step 2 · Stage D — two shared fixes + the tri-fleet acceptance sweep

Date: 2026-07-27. Fleets: OJS `ojs-main` (8000 / `ojs_test`), OMP `omp-main`
(8100 / `omp_test`), OPS `ops-main` (8200 / `ops_test`); one shared Mailpit at
:8025. Everything below was run; no result is inferred.

**Outcome: green.** Every fleet passed its full suite twice at 2 workers after a
reset, all three passed again while running simultaneously, the Mailpit
tag-scoping check held in all six cross-fleet directions, and the reset tool
still forces a cold bootstrap. No app defect was hit; nothing was worked around.

---

## 1. Fix 1 — the login smoke no longer hard-codes `editor.diana`

Blocking finding C-ops.md §7.1. Two shared files changed.

### 1.1 The design choice: keep BOTH consumption paths, add a persona sentinel

The brief's preferred route was taken; the C-ops fallback (drop `test.use` and
resolve everything through `asUser`) was **not** needed.

`test.use({user})` takes a value, not a callback, and the value is consumed by
the `storageState` fixture before any test body runs — so a shared spec cannot
compute it from `appContext`. The fix moves the resolution to where `appContext`
IS available (the fixture) and gives the option a third accepted value:

`lib/pkp/playwright/support/base-test.js`

```js
const DEFAULT_EDITORIAL_USER = '__default__';

function defaultEditorialUser(appContext) {
    const {actors} = appContext.seed;
    const username = actors.editor ?? actors.manager;
    if (!username) { throw new Error(/* …app declares neither archetype… */); }
    return username;
}

storageState: async ({user, appContext, browser, baseURL, authDir}, use) => {
    if (!user) { await use(undefined); return; }          // anonymous, unchanged
    const username =
        user === DEFAULT_EDITORIAL_USER ? defaultEditorialUser(appContext) : user;
    await use(await ensureAuthStateFor(browser, username, {baseURL, authDir}));
},
```

The option's three states are now: `null` → anonymous page (unchanged); a literal
username → that user (unchanged — an app's own suite may still name its own
accounts); `DEFAULT_EDITORIAL_USER` → this app's senior editorial account,
resolved through `appContext.seed.actors` with the editor → manager fallback the
brief specifies. `DEFAULT_EDITORIAL_USER` and `defaultEditorialUser` are exported
so any future shared spec uses the same resolution rather than reinventing it.

Consequence per app, observed in `.auth/` after the runs:

| App | `actors.editor` | resolves to | evidence |
|---|---|---|---|
| OJS | `editor.diana` | `editor.diana` | `ojs-main/playwright/.auth/editor.diana.json` |
| OMP | `editor.diana` | `editor.diana` | `omp-main/playwright/.auth/editor.diana.json` |
| OPS | `null` | `manager.maya` (fallback) | `ops-main/playwright/.auth/` holds `manager.maya.json` + `author.alex.json` and **no** `editor.diana.json` |

### 1.2 `lib/pkp/playwright/tests/login.spec.js`

The spec now names no usernames at all. Three tests:

1. **`a seeded editorial user lands on the editorial dashboard @smoke`** —
   `test.use({user: DEFAULT_EDITORIAL_USER})`. This is the `storageState` /
   `user`-option single-actor path, and it runs (and passes) on **every** app,
   which is what the brief required to preserve.
2. **`a second actor is authenticated alongside the default one @smoke`** —
   the `asUser` multi-actor path, opened as `actors.author` and landing on
   `mySubmissions`. The author archetype is non-null in all three rosters, so
   this path is now covered on every app too.
3. **`a reviewer reaches their own review queue @smoke`** — `asUser(actors.reviewer)`,
   keeping `test.skip(!appContext.capabilities.hasReviewerRoles, …)`. It SKIPS on
   OPS and passes on OJS/OMP.

**Why the second actor moved off the reviewer.** In the old file the only
`asUser` call sat *inside* the reviewer-gated test, so on OPS — the one app where
`actors.editor` is null and the one app the multi-app conventions exist for — the
`asUser` path had zero coverage in the suite that actually runs (OPS's own
project is still empty until step 3). C-ops proved `asUser` works on OPS with a
throwaway mirror spec that was then deleted; splitting the two concerns puts that
coverage in the permanent suite: test 2 exercises the mechanism everywhere, test
3 keeps the capability skip that the acceptance bar wants to see. Net cost is one
extra ~2 s test per fleet. This is the only structural addition to the shared
smoke; flag it if the orchestrator prefers the original two-test shape (test 2
and 3 would merge back, at the price of no `asUser` coverage on OPS).

`appContext` is now a dependency of `storageState`. It is worker-scoped and was
already resolved by every test through the `appContext` fixture, so this adds no
work and no ordering risk; the fixture is still lazy (test 3 never builds a
storage state, because it asks for `asUser` and not `page`).

---

## 2. Fix 2 — `reviewStageId()` no longer assumes the stage roster is ordered

Finding C-omp.md §7.1. `lib/pkp/api/v1/_test/PKPSubmissionScenarioController.php`:

```php
-        return empty($stages) ? null : (int) end($stages);
+        return empty($stages) ? null : (int) max($stages);
```

with a docblock stating the roster is UNORDERED, naming OMP's
`[EXTERNAL_REVIEW, INTERNAL_REVIEW]` as the counter-example, explaining why the
highest id is the right answer (stage ids are assigned in workflow order, so the
max is the LATEST review stage the app has), and pointing at the override an app
uses when it wants something else.

`max()` is sound on every roster in the fleet — verified against the real
constants (`WORKFLOW_STAGE_ID_INTERNAL_REVIEW = 2`,
`WORKFLOW_STAGE_ID_EXTERNAL_REVIEW = 3`,
`lib/pkp/classes/core/PKPApplication.php:744-745`):

```
OMP  getReviewStages() = [3, 2]   end = 2 (INTERNAL — the bug)   max = 3 (EXTERNAL)
OJS  getReviewStages() = [3]      end = 3                        max = 3 (EXTERNAL)
OPS  getReviewStages() = []       → null, unchanged
```

**Behavioural proof, not just arithmetic.** OJS's `SubmissionScenarioController`
does *not* override `reviewStageId()`, so OJS exercises the patched base method
for real. Seeding a submission with `reviewRounds` and no stage key through the
live endpoint:

```
POST /index.php/index/api/v1/_test/scenarios/submission  (ojs, 8000)
  {"tag":"twd-revstage-160221", …, "reviewRounds":[{"reviewers":[{"user":"reviewer.julia"}]}]}
→ {"submissionId":1,"stageId":3,"reviewRounds":[{"id":1,"round":1,"status":7,…}]}

ojs_test=# select review_round_id, submission_id, stage_id, round from review_rounds;
  1 | 1 | 3 | 1          -- stage_id 3 = EXTERNAL_REVIEW
```

Regression checks that the patched shared file did not disturb the two apps that
do not use its default:

```
OMP  reviewRounds, no stage key   → stageId 3, round stage "external"   (subclass default)
OMP  reviewRounds, stage internal → stageId 2, round stage "internal"
     omp_test review_rounds: (1,1,3,1) and (2,2,2,1)
OPS  reviewRounds                 → 400 {"error":"This application has no review stage,
                                          so reviewRounds cannot be seeded.",
                                          "specKey":"reviewRounds"}
     ops_test submissions after the refusal: 0   (failure hygiene intact)
```

The probe rows were removed by resetting `ojs_test` and `omp_test` afterwards
(`review_rounds` = 0, `submissions` = 0 on OJS at the end of the sweep).

---

## 3. The identical patch in all three working trees

The two fixes span three shared files. They were written in `ojs-main/lib/pkp`
and byte-copied into `omp-main/lib/pkp` and `ops-main/lib/pkp` (`diff -r` clean),
so all three fleets ran the same code. All three submodules sit at the same
commit `a11e2b0fa7f4d272191d4a6a53f37003d1b8b204`; nothing was committed or
pushed. Ground truth at the end of the sweep:

```
--- omp-main/lib/pkp ---            --- ops-main/lib/pkp ---
 M api/v1/_test/PKPSubmissionScenarioController.php    (same)
 M playwright/support/base-test.js                     (same)
 M playwright/tests/login.spec.js                      (same)
?? docs/product/.reports/step2-harness/C-omp.md        ?? …/C-ops.md
```

— exactly the patched shared files plus each fleet's pre-existing untracked stage
report, nothing else. (`ojs-main/lib/pkp` additionally shows `docs/e2e/app-changes.md`
and `docs/e2e/scenario-processor-audit.md` as modified; both were already modified
before this stage started and were not touched here.) The temporary Mailpit probe
spec placed in each app's `playwright/tests/` for §5 was deleted; all three
`playwright/tests/` directories are back to `README.md` + `serial/`.

---

## 4. Per-fleet sweep — reset, green, green again (2 workers)

Command per fleet: `npm run test:e2e:reset`, then `PLAYWRIGHT_WORKERS=2 npm run
test:e2e` twice. `test:e2e` runs all four projects; the app project and the
`<app>-serial` project are empty and pass with no tests.

### OJS

```
$ npm run test:e2e:reset
Recreating the test database named in …/ojs-main/config.test.inc.php
Successfully installed version 3.6.0.0
Cleared cached storage states in …/ojs-main/playwright/.auth

RUN 1 (cold)                                          RUN 2 (warm)
✓ 1 [setup] the shared base context is seeded         ✓ 1 [setup] … (88ms)
✓ 2 [shared] a seeded editorial user lands …          ✓ 2 [shared] … (1.6s)
✓ 3 [shared] a second actor is authenticated … (4.4s) ✓ 3 [shared] … (2.8s)
✓ 4 [shared] a reviewer reaches their own review queue ✓ 4 [shared] … (1.4s)
  4 passed (13.4s)                                      4 passed (4.2s)
```

### OMP

```
RUN 1 (cold)                                          RUN 2 (warm)
✓ 1 [setup] … (7.4s)                                  ✓ 1 [setup] … (69ms)
✓ 2 [shared] a seeded editorial user lands … (2.1s)   ✓ 2 [shared] … (1.2s)
✓ 4 [shared] a reviewer reaches … (1.9s)              ✓ 4 [shared] … (1.2s)
✓ 3 [shared] a second actor … (4.0s)                  ✓ 3 [shared] … (2.5s)
  4 passed (13.1s)                                      4 passed (3.7s)
```

### OPS — the capability skip appears, as the acceptance bar asks

```
RUN 1 (cold)                                          RUN 2 (warm)
✓ 1 [setup] … (2.7s)                                  ✓ 1 [setup] … (85ms)
✓ 2 [shared] a seeded editorial user lands … (2.1s)   ✓ 2 [shared] … (1.1s)
- 4 [shared] a reviewer reaches their own review queue - 4 [shared] …  SKIPPED
✓ 3 [shared] a second actor is authenticated … (3.9s) ✓ 3 [shared] … (2.5s)
  1 skipped · 3 passed (7.8s)                           1 skipped · 3 passed (3.6s)
```

`ops-main/playwright/.auth/` after the run holds `manager.maya.json` and
`author.alex.json` — the fix's resolution, visible on disk.

A further green run per fleet was taken at the very end of the sweep, after the
probe data was reset away (OJS 4 passed / OMP 4 passed / OPS 1 skipped + 3 passed).

---

## 5. Concurrent tri-fleet run + Mailpit tag scoping

### 5.1 The three suites, overlapping

All three `npm run test:e2e` runs were backgrounded together. PHP-server
timestamps from each run's own log show real overlap, not sequencing:

```
run A (suites only)          run B (suites + the mail probe)
OJS  15:47:36 → 15:47:41     OJS  15:50:29 → 15:50:37
OMP  15:47:37 → 15:47:43     OMP  15:50:31 → 15:50:35
OPS  15:47:40 → 15:47:45     OPS  15:50:33 → 15:50:37
     overlap 15:47:40–41          overlap 15:50:33–35
```

Run A: OJS 4 passed, OMP 4 passed, OPS 1 skipped + 3 passed. Run B added the
mail probe: OJS 5 passed, OMP 5 passed, OPS 1 skipped + 4 passed. All exit 0.

### 5.2 One real mail-producing action per fleet, at the same time

A temporary spec (deleted afterwards) was placed in each app's own suite and run
inside run B, so each fleet's action happened while the other two fleets' servers
and suites were live. Per fleet it:

1. seeded a **throwaway** context + user through that fleet's own
   `POST /api/v1/_test/scenarios/context` — user `twd.<app>.160221`, recipient
   `tw-d-<app>-160221@example.org`, role `reader`, in a scratch context
   `twd<app>160221` (no shared seeded user was touched, no role mutated);
2. drove a **real lost-password request** through the UI
   (`LoginPage.requestPasswordReset`) — a genuine `PASSWORD_RESET_CONFIRM` mail,
   not a seeded one (seeding runs under `Mail::fake()`);
3. asserted with the shared `pkpMail` helper.

The per-fleet **tag** is the throwaway username, which reaches the message body
inside the password-reset URL (`PasswordResetUrl` builds it from the username).

Assertions, per fleet:

- **positive** — `find({to: <own recipient>, contains: <own tag>})` returns ≥1,
  and every returned message's `To` contains the fleet's own recipient;
- **concurrency bound** — `find({to: <other fleet's recipient>, contains: <other
  fleet's tag>})` for BOTH other fleets, so the negatives below run at a moment
  when all three fleets' mail is provably in the shared inbox at once;
- **negatives** — `expectNone` in both directions for each other fleet (their tag
  never appears in my recipient's mail; my tag never appears in theirs), each
  bounded by the corresponding fleet's own positive control message, never by a
  bare timeout.

`clearAll()` was never called and no global count was asserted.

Each fleet's log line:

```
[tagscope ojs] own=1 to=tw-d-ojs-160221@example.org tag=twd.ojs.160221; others present: omp, ops
[tagscope omp] own=1 to=tw-d-omp-160221@example.org tag=twd.omp.160221; others present: ojs, ops
[tagscope ops] own=1 to=tw-d-ops-160221@example.org tag=twd.ops.160221; others present: ojs, omp
```

### 5.3 Independent Mailpit read-back

Taken afterwards straight against `GET /api/v1/search`, outside Playwright:

```
own    ojs: 1 | tw-d-ojs-160221@example.org :: Password Reset Confirmation
own    omp: 1 | tw-d-omp-160221@example.org :: Password Reset Confirmation
own    ops: 1 | tw-d-ops-160221@example.org :: Password Reset Confirmation

tag alone (proves each tag IS findable, so a 0 below means scoping, not a dead string)
tag    ojs: 1 | tw-d-ojs-160221@example.org      tag omp: 1 | …omp…      tag ops: 1 | …ops…

cross pairs — every one 0
to:ojs x tag:omp: 0   to:ojs x tag:ops: 0
to:omp x tag:ojs: 0   to:omp x tag:ops: 0
to:ops x tag:ojs: 0   to:ops x tag:omp: 0
```

Exactly one message per fleet, each scoped query returns only its own fleet's
message, and all six mismatched (recipient × tag) combinations are empty while
all three messages sit in the same inbox.

---

## 6. The reset tool still forces a cold bootstrap (OPS)

Warm baseline first, so "cold" has something to be measured against:

```
BEFORE
  GET  …/_test/bootstrap?context=publicknowledge  → {"seeded":true,"contextId":1}
  playwright/.auth/                               → author.alex.json, manager.maya.json
  npm run test:e2e:setup                          → ✓ setup (71ms)          [warm no-op]

$ npm run test:e2e:reset
  Recreating the test database named in …/ops-main/config.test.inc.php
  Successfully installed version 3.6.0.0
  Cleared cached storage states in …/ops-main/playwright/.auth
  Done. The next `npm run test:e2e:setup` will seed from cold.

AFTER
  GET  …/_test/bootstrap?context=publicknowledge  → {"seeded":false,"contextId":null}
  playwright/.auth/                               → ls: No such file or directory
  npm run test:e2e:setup                          → ✓ setup (2.5s)          [COLD: re-seeds]
  GET  …/_test/bootstrap?context=publicknowledge  → {"seeded":true,"contextId":1}
```

71 ms → 2.5 s, `seeded:false` → `seeded:true`, and the auth cache gone: the two
halves the tool promises both happened. The same behaviour was seen on OJS and
OMP resets earlier in the sweep (both printed the cleared-`.auth` line and both
took the cold seed path on the next run).

---

## 7. Rebuild-acceptance checklist (PRINCIPLES "Rebuild acceptance")

| Acceptance item | Status | Evidence |
|---|---|---|
| Bootstrap seeds green in all three apps | ✅ | §4 — cold `[setup]` green on OJS, OMP and OPS after a reset each; cold-path detail per app in A-php-api.md, C-omp.md §4.2, C-ops.md §5.2 |
| A login smoke passes per fleet | ✅ | §4 — the shared smoke green on all three, twice each at 2 workers; on OPS the reviewer-gated test SKIPS (capability gating), which is the shape the bar asks for. The blocking bug that prevented this (C-ops §7.1) is fixed in §1 |
| The scenario endpoint seeds a **context** in each app | ✅ | §5.2 — a scratch context created live in all three fleets during the concurrent run; per-app detail in A-php-api.md, C-omp.md §4.2/§4.5, C-ops.md §5.4 |
| The scenario endpoint seeds a **staged submission** in each app | ✅ | §2 — OJS seeded into external review, OMP into external and internal, OPS correctly refusing `reviewRounds`; full staged-submission matrices in A-php-api.md, C-omp.md §4.5, C-ops.md §5.4 |
| One parity spot-check against the equivalent UI path | ✅ (cited) | OJS A-php-api.md; OMP C-omp.md §4.7; OPS C-ops.md §5.6 — notifications, stage assignments, event/email logs row-for-row against a real controller-driven submission. Not re-run here; nothing in this stage's two fixes touches a Processor's side effects |
| The reset tool forces a cold bootstrap | ✅ | §6 — OPS end to end with before/after probes; also observed on OJS and OMP |
| Concurrent fleets keep Mailpit assertions tag-scoped | ✅ | §5 — three fleets overlapping, one real mail action each, six cross-fleet negatives empty with positive controls bounding every one, plus the independent read-back in §5.3. Per-fleet seeding-side suppression in C-omp.md §4.9 and C-ops.md §5.7 |
| RUNBOOK "Ops & environment safeguards" names match the harness | ⚠️ one stale name | reset script `npm run test:e2e:reset` ✅ exists in all three `package.json`; test key `X-Test-Key: playwright-test-key` ✅ (`TEST_API_KEY=playwright-test-key` in all three `.env.playwright`); ports 8000/8100/8200 ✅. **Mismatch:** the bullet "After a killed/aborted test gate" says re-run `--project=serial --no-deps`, but the rebuilt config names the serial projects `ojs-serial` / `omp-serial` / `ops-serial` (`lib/pkp/playwright/config-factory.js`, `${appName}-serial`). RUNBOOK needs that one word updated in the same commit as this stage |

Additional acceptance-adjacent facts confirmed in this sweep: every suite ran at
≥2 workers with per-worker PHP servers (8000/8001, 8100/8101, 8200/8201 —
B-js-harness.md §2); the app and `<app>-serial` projects are empty and pass with
no tests; failure hygiene leaves nothing behind (§2, `ops_test` submissions 0
after a refused spec).

---

## 8. Proposed rows for `docs/e2e/app-changes.md`

Both fixes are test-harness-only — `lib/pkp/playwright/` and the `_test` API
namespace, nothing outside it — so under PRINCIPLES "App-code change ledger"
neither is strictly an app-code change. The `_test` namespace is app code by
path, though, and stage A/B/C rows already record it there, so for continuity:

| Date | App | Change | Where | Why |
|---|---|---|---|---|
| 2026-07-27 | all | Test-harness only: `user` test option accepts the `DEFAULT_EDITORIAL_USER` sentinel, resolved by the `storageState` fixture through `appContext.seed.actors` (editor → manager fallback); the shared login smoke names no usernames and covers the `asUser` path on every app | `lib/pkp/playwright/support/base-test.js`, `lib/pkp/playwright/tests/login.spec.js` | The shared smoke hard-coded `editor.diana`, which OPS does not seed (no editor group), so both smoke tests failed in the fixture before their capability skips could run (C-ops.md §7.1). PRINCIPLES multi-app convention 2 requires shared code to resolve personas through the archetype map |
| 2026-07-27 | all | Test-harness only: `PKPSubmissionScenarioController::reviewStageId()` takes `max()` of the app's review-stage roster instead of `end()`, with a comment recording that the roster is unordered | `lib/pkp/api/v1/_test/PKPSubmissionScenarioController.php` | `Application::getReviewStages()` is a set, not a sequence: OMP returns `[EXTERNAL, INTERNAL]`, so `end()` silently answered "internal review". Harmless today (OMP overrides the method) but a trap any future shared code would inherit (C-omp.md §7.1) |

No row is proposed for `docs/e2e/scenario-processor-audit.md`: `max()` changes
which stage the *default* resolves to on a hypothetical multi-stage app, and on
the three real fleets it resolves to exactly what `end()` did (OJS) or to a value
the app's own override supersedes (OMP) or to null (OPS) — no Processor's
behaviour against a UI path changed. The C-omp and C-ops parity rows stand as
written.

---

## 9. Open items handed back

1. **RUNBOOK** — `--project=serial --no-deps` should read `--project=<app>-serial`
   (§7, last row). One-word fix, belongs in this stage's commit.
2. **The third smoke test** (§1.2) is the only structural addition to the shared
   smoke. Keep or merge back into the reviewer-gated test — the orchestrator's
   call; merging back costs `asUser` coverage on OPS.
3. Carried forward untouched, all recorded in the stage-C reports: the OPS
   galley/`SUBMISSION_FILE_SUBMISSION` parity gap (C-ops §7.2), scratch contexts
   keeping the auto-created default section (C-ops §7.3), `installTest.php`
   duplicated per app (C-ops §7.4), OMP `work_type = 0` (C-omp §7.3), and the two
   missing shared overlay hooks (C-omp §7.2).

---

## 10. Reproduce

```bash
# per fleet (ojs-main 8000 / omp-main 8100 / ops-main 8200)
npm run test:e2e:reset
PLAYWRIGHT_WORKERS=2 npm run test:e2e     # twice; OPS shows 1 skipped

# concurrent: background all three of the above `test:e2e` runs together
# mail scoping: the probe spec lives only in this report; it seeded a throwaway
#   context+user per fleet via POST /api/v1/_test/scenarios/context, drove
#   LoginPage.requestPasswordReset(), then asserted find()/expectNone() scoped by
#   recipient + tag with the other fleets' own messages as controls.
```
