# Scenario-endpoint parity ledger

Recreated with the harness rebuild (2026-07-27, PROGRESS restart step 2).
Contract: PRINCIPLES §"Architecture principles" 2 — a seeded scenario must
leave the same database state, fire the same hooks, and produce the same
notifications as a user performing the equivalent steps through the UI/REST
API. **Any change to a Processor requires a parity entry here before it
merges**; deliberate deviations are recorded as such, with rationale.

Format: one row per audited builder path per app. Evidence detail stays in
`.reports/`; this file records the verdict.

| Date | App | Builder path | Parity check | Verdict | Notes |
|---|---|---|---|---|---|
| 2026-07-27 | OJS | submission · submit | Notifications + `event_log` + `stage_assignments` vs a submission created and submitted through the real REST API as `author.alex` | ✅ parity after fix | `Repo::submission()->submit()` alone omits `APPROVE_SUBMISSION`/`FORMAT_NEEDS_APPROVED_SUBMISSION` — the submit **controller** refreshes them via `NotificationManager::updateNotification()` after the repository call; the builder now calls that same service. |
| 2026-07-27 | OJS | submission · decisions | `edit_decisions`, `submissions.stage_id`, `review_rounds`, `event_log` after `sendExternalReview`/`accept`/`sendToProduction`/publish | ✅ real path | `Repo::decision()->validate()` + `->add()`; the app's own `ApplyDoneWorkflowStage` listener adds the unrequested `moveToDone` decision on publish, as in the UI. |
| 2026-07-27 | OJS | submission · reviewers | `review_assignments` (assigned/notified/confirmed/declined), `event_log` (`reviewerAssigned`, `reviewAccepted`) | ✅ real path | `EditorAction::addReviewer()` + the Add Reviewer form's post-assign `dateNotified`/`considered` stamp; accept/decline via `ReviewerAction::confirmReview()`; invitation body from the `REVIEW_REQUEST` template as the form posts it. |
| 2026-07-27 | OJS | context · scratch journal | Journal created through `app()->get('context')->add()` | ✅ real path | Genres, user groups, email templates, nav menus and the default section come from the real service; spec sections are matched to the auto-created default by abbrev and edited, so no stray "Articles" survives. |
| 2026-07-27 | shared | seeding mail | Mailpit count before/after a seed that invites + confirms a reviewer | ✅ suppressed | `Mail::fake()` for the seeding request only; `email_log` rows still written, so mail-log assertions keep working. |
| 2026-07-27 | OJS | bootstrap/context · `users[].sections` | `GET /publicknowledge/api/v1/users?assignedToSection={id}` (the Sections settings form's own query) for both seeded sections, each list the other's positive control | ✅ real path | `SubEditorsDAO::insertEditor(contextId, sectionId, userId, ASSOC_TYPE_SECTION, userGroupId)` — the call `PKPSectionForm::execute()` makes — restricted to that form's `assignableRoles` (sub-editor → manager → assistant preference; the PK allows one group per user per section). Unknown abbrev and un-assignable role both THROW with the offending `specKey`; idempotent re-runs skip existing rows. Read-back avoids `SubEditorsDAO::editorExists()` (broken pre-3.2 SQL — see stage-B report §7.3). |
