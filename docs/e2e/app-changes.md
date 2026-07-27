# App changes & build blockers

Per `docs/product/RUNBOOK.md` "What goes where", this file records ONLY:

- **actual app-code changes** made by the campaign (what, why, where, commit);
- **build blockers**: app defects that prevented tests from running green
  (race conditions, nondeterministic UI, harness-hostile behavior) and the
  workaround taken.

Product findings — bugs, divergences, open questions — live in each feature
spec's Findings register, never here.

**Reset 2026-07-26**: the previous contents (8 production fixes and a 272-row
findings archive) were deleted and the fixes reverted (git history keeps both);
the restarted process rediscovers what matters on its own evidence.

One file for all three apps: name the app per row (`ojs` / `omp` / `ops`;
shared lib/pkp changes say `pkp-lib`).

| # | App | What & why | Files | Commit | Notes |
|---|-----|-----------|-------|--------|-------|
| 1 | pkp-lib | `CONFIG_FILE` honours a `PKP_CONFIG_FILE` env var (one line) so the e2e fleets run against `config.test.inc.php` without touching `config.inc.php`. Router-script/registry alternatives provably break: `PKPAppKey::refreshEncrypter()`/`writeAppKeyVariableToConfig()` reset to `CONFIG_FILE` mid-install, writing the app key into the dev config. No behaviour change when unset. | `classes/config/Config.php` | pending | Harness rebuild, stage A (2026-07-27) |
| 2 | ojs | `/config.test.inc.php` gitignored — holds local DB credentials + generated app key, like the already-ignored `/config.inc.php`. | `.gitignore` | pending | Harness rebuild, stage A (2026-07-27) |
| 3 | ojs + pkp-lib | New test-only files, inert without `TEST_API_KEY` in the process env (no app behaviour change): `api/v1/_test/*`, `tools/installTest.php`, `tools/testServer.sh` (ojs); `api/v1/_test/*`, `classes/testing/*` (pkp-lib). | see row | pending | Harness rebuild, stage A (2026-07-27) |
| 4 | pkp-lib | Test-API user specs gained a declared app overlay (`userSchemaOverlayProperties()` + `userSchema()`; `seedUsers()` calls a new `afterUserSeeded()` hook) so app concepts enter the shared user schema only by declaration — `sections` validates on OJS, rejected as unknown elsewhere. Test-only namespace. | `api/v1/_test/PKPTestApiController.php`, `api/v1/_test/BootstrapRoutes.php` | pending | Harness rebuild, stage B (2026-07-27) |
| 5 | ojs | `users[].sections` overlay — section-editor assignment via the real service (`SubEditorsDAO::insertEditor`, as `PKPSectionForm::execute()` calls it); makes the base roster's recorded section-editor assignments seedable. | `api/v1/_test/JournalScenarioController.php` | pending | Harness rebuild, stage B (2026-07-27) |
| 6 | ojs + pkp-lib | New test-only Playwright harness files (no app behaviour change): `playwright/*`, `.env.playwright.example`, nine `test:e2e:*` npm scripts, `@playwright/test` devDependency, `.gitignore` entries (ojs); `playwright/*` (pkp-lib). | see row | pending | Harness rebuild, stage B (2026-07-27) |
| 7 | ojs | `tools/installTest.php` self-heals from an empty or partially installed DB (pre-boot `installed`-flag management with a three-state schema probe), refuses to install over a populated DB (`--recreate-db` instead), exits non-zero on failure. The app cannot bootstrap against a DB with no `core` version row — the state a new contributor, CI, or an interrupted install starts from. Test-only tool. | `tools/installTest.php` | pending | Harness rebuild, stage A follow-up (2026-07-27) |
