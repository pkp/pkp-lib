# Scenario-endpoint parity ledger

Recreated empty at FULL RESET #2 (2026-07-31, PROGRESS banner) — the prior
rows described builders scratched with the harness; git history keeps them.
Contract: PRINCIPLES §"Architecture principles" 2 — a seeded scenario must
leave the same database state, fire the same hooks, and produce the same
notifications as a user performing the equivalent steps through the UI/REST
API. **Any change to a Processor requires a parity entry here before it
merges**; deliberate deviations are recorded as such, with rationale.

Format: one row per audited builder path per app. Evidence detail stays in
`.reports/`; this file records the verdict.

| Date | App | Builder path | Parity check | Verdict | Notes |
|---|---|---|---|---|---|
| 2026-07-31 | all | context create (`ContextFactory` → `PKPContextService::add`) | code path identity: same service + validate call as the admin hosted-contexts REST flow; acting user is the seeding request's admin | PASS (by construction) | Parity fact: every scratch context auto-enrols `admin` as Manager, exactly like a UI-created context |
| 2026-07-31 | all | submission create/submit (`PKPSubmissionScenarioBuilder`) | mirrors `PKPSubmissionController::add()` step-for-step (submit-as group resolution incl. author fallback enrolment, stage assignment flags, author record, primary contact), then real `Repo::submission()->submit()` (fires `SubmissionSubmitted` → discussions/AssignEditors/tasks) + the submit endpoint's `NotificationManager::updateNotification([APPROVE_SUBMISSION])` tail | PASS after fixes (below) | Step-2 core seeds NO submission file — the wizard requires one; tests needing files upload via UI until a `files` key lands with its own parity entry |
| 2026-07-31 | ojs | submission (submitted) — full UI-wizard vs scenario DB diff | live spot-check: real Chromium wizard run vs scenario twin, field-by-field psql diff (`docs/product/.reports/step1-harness/parity-ojs-submission.md`) | 3 DEFECTS found → FIXED same session, re-verified by SQL against the same UI baseline | (1) `publications.primary_contact_id` NULL — a stale-object publication edit erased it; builder now re-fetches before the title edit. (2) APPROVE_SUBMISSION + FORMAT_NEEDS_APPROVED_SUBMISSION notifications missing — builder now runs the submit endpoint's NotificationManager tail. (3) abstract absent (wizard-required) — the core now defaults an abstract; pass `abstract` to override |
| 2026-07-31 | ojs | submission (submitted) — event log + files | same spot-check | ACCEPTABLE DEVIATIONS | UI carries wizard-autosave `metadataUpdated`/`fileRevised` rows the scenario lacks (log noise, no state depends on it); `submission_files` 1 (UI, required upload) vs 0 (core seeds none — documented limitation) |
| 2026-07-31 | ojs, ops | bootstrap first section vs the Context::add default section | fresh-bootstrap section listing (was: TWO active "Articles"/ART sections — the hook default + the seeded one; ambiguous wizard radios and abbrev lookups) | DEFECT → FIXED | the first declared bootstrap section now RENAMES the hook-created default (what a journal manager would do) instead of adding a duplicate; verified: OJS seeds exactly ART+REV, OPS exactly PRE |
| 2026-07-31 | all | decisions (`Repo::decision()->add` with app-resolved `DecisionType`) | real repository add: event log, `runAdditionalActions` (stage moves, review-round creation), `DecisionAdded` event; empty actions array = the UI's skip-email path | PASS | Decision editor is the installer's `admin` (site admin), not a context editor — extend with an `editor` key when a feature needs attribution |
| 2026-07-31 | all | reviewers (`EditorAction::addReviewer` + `ReviewerAction::confirmReview`) | real editor/reviewer actions; seeding uses the Add Reviewer form's `skipEmail` path | DELIBERATE DEVIATION | Seeded assignments carry no `REVIEW_REQUEST` email-log row (the skip-email path writes none); accept/decline logs match the UI path. Scenario-side mail is dropped by `Mail::fake()` regardless |
| 2026-07-31 | all | builder harness (`PKPTestController::runBuilder`) | every build runs inside one DB transaction; failure rolls back (no half-created state); router context is forced to the target context so notification managers/mailables see the same context the UI path would | PASS | Files/dirs created before a rollback (context dirs) may orphan on disk — DB state stays clean |
| 2026-07-31 | ojs | issues overlay (bootstrap `issues[]`, publish via `Repo::issue()->updateCurrent`) | `IssueGridHandler::publishIssue` parity: published flag + datePublished + current issue | PASS | Scheduled-publication publishing on issue publish not exercised (no publications assigned at bootstrap) |
| 2026-07-31 | ops | `reviewRounds` key | rejected with 400 (`specKey: reviewRounds`) — never silently dropped | PASS (verified live) | |
