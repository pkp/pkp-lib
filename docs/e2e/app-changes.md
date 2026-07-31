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

**FULL RESET #2, 2026-07-31**: rows 1–15 were removed with the harness and
feature artifacts they described (the Fable-only rebuild, PROGRESS banner).
The one prior app-code change — the `Config.php` `PKP_CONFIG_FILE` line — was
reverted with them. Deliberately kept: the `.gitignore` entries for local test
config (`config.test.inc.php`, `.env.playwright`), so credentials on disk can
never be swept into a commit; they reveal nothing the PRINCIPLES design record
does not already state.

One file for all three apps: name the app per row (`ojs` / `omp` / `ops`;
shared lib/pkp changes say `pkp-lib`).

| # | App | What & why | Files | Commit | Notes |
|---|-----|-----------|-------|--------|-------|
| 1 | pkp-lib | `CONFIG_FILE` honours the `PKP_CONFIG_FILE` env var so the test fleets run against `config.test.inc.php` without touching the dev install (harness rebuild, PROGRESS restart step 1) | `classes/config/Config.php` (one line) | step-1 rebuild commit, branch `e2e_ng_2` | Recreates the change scratched at FULL RESET #2; no behavior change when the env var is unset |
| 2 | ops | `getPublicationTitleAbstractForm()` passed raw `$section->getData('wordCount')` (null when the section sets no word limit) into `TitleAbstractForm::__construct(int)` → TypeError; the author's workflow view of any such submission showed an Error dialog (hit by U1 scenario 8's author variant). Added the `(int)` cast OJS already has at its equivalent line | `api/v1/submissions/SubmissionController.php` (one line) | U1 commit, branch `e2e_ng_2` | App-code fix (build blocker); fails closed before the fix, no data issue |
| 3 | pkp-lib | Build blocker WORKED AROUND, not changed: `SubEditorsDAO::assignEditors()` filters candidate assignments with `$userGroups->keys()` — positional collection indexes, not group ids — so sub-editor auto-assignment on submit silently fails in any context whose user-group ids exceed the group count (every scratch/late-created context; first-context installs pass by accident). Verified in `ops_test`: correct `subeditors` row, matching section, no stage assignment | `classes/submission/SubEditorsDAO.php` (unchanged) | — | U1 OPS scenario-8 test seeds on `publicknowledge` instead of a scratch context to dodge it; product finding belongs to the future editor-assignment spec's register |
