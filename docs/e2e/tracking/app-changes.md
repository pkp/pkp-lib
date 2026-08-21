# App changes & build blockers

> Append-only ledger: rows are dated records — corrected by dated follow-up
> notes, never rewritten.

Per `lib/pkp/docs/e2e/process/RUNBOOK.md` "What goes where", this file records ONLY:

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
| 1 | pkp-lib | `CONFIG_FILE` honours the `PKP_CONFIG_FILE` env var so the test fleets run against `config.test.inc.php` without touching the dev install (harness rebuild, 2026-07-31) | `classes/config/Config.php` (one line) | harness-rebuild commit (2026-07-31), branch `e2e_ng_2` | Recreates the change scratched at FULL RESET #2; no behavior change when the env var is unset |
| 2 | ops | `getPublicationTitleAbstractForm()` passed raw `$section->getData('wordCount')` (null when the section sets no word limit) into `TitleAbstractForm::__construct(int)` → TypeError; the author's workflow view of any such submission showed an Error dialog (hit by U01 scenario 8's author variant). Added the `(int)` cast OJS already has at its equivalent line | `api/v1/submissions/SubmissionController.php` (one line) | U01 commit, branch `e2e_ng_2` | App-code fix (build blocker); fails closed before the fix, no data issue |
| 3 | pkp-lib | Build blocker WORKED AROUND, not changed: `SubEditorsDAO::assignEditors()` filters candidate assignments with `$userGroups->keys()` — positional collection indexes, not group ids — so sub-editor auto-assignment on submit silently fails in any context whose user-group ids exceed the group count (every scratch/late-created context; first-context installs pass by accident). Verified in `ops_test`: correct `subeditors` row, matching section, no stage assignment | `classes/submission/SubEditorsDAO.php` (unchanged) | — | U01 OPS scenario-8 test seeds on `publicknowledge` instead of a scratch context to dodge it; product finding belongs to the future editor-assignment spec's register |
| 4 | pkp-lib | Build blocker WORKED AROUND, not changed: the Add Reviewer request-letter prefill races TinyMCE init — selecting a reviewer before the editor's async render finishes has `setContent` wiped by late init, so the letter silently arrives empty and the add then 500s server-side (`EditorAction.php` `logMailable(REVIEW_REQUEST)` renders a null body). Product symptom is the U27 spec's register (silent half-completed add) | `ReviewStagePages.js` `selectReviewer` waits on the letter editor's `initialized` flag (app code unchanged) | — | App fix should guard the prefill and the empty-body mailable render |
| 5 | pkp-lib | Scenario-builder parity fix: `PKPSubmissionScenarioBuilder` seeded review due = today + (response+review) weeks vs the app's today + review weeks (masked on the 4/4-week baseline), fixed by reusing the app's `HasReviewDueDate` trait; second suspected gap (missing "Review pending." reviewer task) DISPROVEN — builder drives `EditorAction::addReviewer`, which creates it; observed absence was the app's own on-submit task deletion. Ledger rows in `lib/pkp/docs/e2e/tracking/parity-ledger.md` (2026-08-02) | `classes/testing/scenario/PKPSubmissionScenarioBuilder.php` | U27 commit, branch `e2e_ng_2` | Campaign-owned harness (self-healing rule); review suites re-run green ×3 fleets post-fix; two OJS test assertions corrected to app-true values |
