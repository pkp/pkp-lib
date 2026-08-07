# Atlas — vue modality

- **Modality**: vue (ui-library pages, managers, screen-level modals/dialogs)
- **Sweep date**: 2026-07-26
- **Trees swept**: `ojs-main/lib/ui-library/src/{pages,managers,components}` (component inventory); wiring resolved against `ojs-main`, `omp-main`, `ops-main` (each: `js/load.js`, `templates/`, `pages/`, `classes/`) and `ojs-main/lib/pkp` (`js/load.js`, `templates/`, `pages/`, `classes/`, `controllers/`)
- **lib/pkp SHA**: ad4606f93e (checked out at sweep time; task brief cited 9db481cf4d — mismatch noted, not resolved here)
- **Globs/greps used**:
  - `ls lib/ui-library/src/pages/*/`, `ls lib/ui-library/src/managers/`
  - `find lib/ui-library/src -name "*Modal*.vue"` (modal inventory)
  - `grep -rl "SideModalBody" lib/ui-library/src --include=*.vue` (side-modal surfaces)
  - per component name `N`: `grep -rl "N"` and kebab-case `n-n-n` over `{ojs,omp,ops}/{js/load.js,templates,pages,classes}` and `lib/pkp/{js/load.js,templates,pages,classes,controllers}` (mount/wiring)
  - per manager: `grep -rl "managers/N/N.vue" lib/ui-library/src` (in-library usage sites)
- **apps legend**: `ojs,omp,ops` = wiring found in that app's load.js registration, an app template, or a shared lib/pkp template/handler reachable by all apps. `?` = no mount found by mechanical grep (Phase 1 resolves). No liveness judgment: a shared lib/pkp mount counts for all three apps even where the feature is plausibly app-specific.
- **Skipped as chrome/infrastructure** (not atoms): `components/Container/Container.vue`, `Page.vue`, `StatsPage.vue` (base), `components/Modal/*` (Modal, SideModalBody, SideModalLayoutBasic, DialogBody, SideModalBodyLegacyAjax host), `components/FormModal/FormModal.vue` (generic), `frontend/components/PkpModalManager` (reader-frontend modal plumbing), `VocabularyModalCellName.vue` (table cell widget), `JobsPageBase.vue` (base), per-page sub-widgets (table cells, headers, steps, popovers). `pages/doi/` directory is empty (DOI page lives in `components/Container/DoiPage*`; see VUE-018).
- Inline dialogs opened via `useModal().openDialog()` with ad-hoc props are not components and belong to the **aff** sweep, not here.

## Pages

| ID | apps | pointer | what it is |
|---|---|---|---|
| VUE-001 | ojs,omp,ops | pages/acceptInvitation/AcceptInvitationPage.vue | Accept-invitation flow (roles, account details, ORCID verify) mounted from lib/pkp invitation/acceptInvitation.tpl · Claimed by: user-invitations. |
| VUE-002 | ojs,omp,ops | pages/counter/CounterReportsPage.vue | COUNTER R5 usage-report builder page (lib/pkp stats/counterReports.tpl, PKPStatsHandler::counterR5) |
| VUE-003 | ojs,omp,ops | pages/dashboard/DashboardPage.vue | Submissions dashboard (editorial/my-assignments/mySubmissions views) mounted from lib/pkp dashboard/editors.tpl |
| VUE-004 | ? | pages/example/ExamplePage.vue | ui-library docs example page; no app mount found by grep |
| VUE-005 | ojs,omp,ops | pages/jobs/JobsPage.vue | Admin queued-jobs listing (lib/pkp admin/jobs.tpl) |
| VUE-006 | ojs,omp,ops | pages/jobs/FailedJobsPage.vue | Admin failed-jobs listing with requeue/delete (lib/pkp admin/failedJobs.tpl) |
| VUE-007 | ojs,omp,ops | pages/jobs/FailedJobDetailsPage.vue | Admin failed-job detail/payload view (lib/pkp admin/failedJobDetails.tpl) |
| VUE-008 | ojs,omp,ops | pages/requestReviewRoundAuthorResponse/RequestReviewRoundAuthorResponse.vue | Author-facing review-round response request page (lib/pkp reviewResponse/requestAuthorResponse.tpl, ReviewResponseHandler) |
| VUE-009 | ojs,omp,ops | pages/reviewerSubmission/ReviewerSubmissionPage.vue | Reviewer's completed-review submission view (lib/pkp reviewer/review/reviewStepHeader.tpl) |
| VUE-010 | ojs,omp,ops | pages/userComments/UserCommentsPage.vue | User comments moderation page (lib/pkp management/userComments.tpl, ManagementHandler) |
| VUE-011 | ojs,omp,ops | pages/userInvitation/UserInvitationPage.vue | Send-user-invitation wizard (search, details, email composer; lib/pkp invitation/userInvitation.tpl) · Claimed by: user-invitations. |
| VUE-012 | ojs,omp,ops | pages/workflow/WorkflowPage.vue (variants WorkflowPageOJS/OMP/OPS) | Submission workflow side-modal surface (stages, publication tabs); per-app variant registered as `WorkflowPage` in each app's js/load.js |
| VUE-013 | ojs,omp,ops | components/Container/AccessPage.vue | Users & Roles management page container (ManagementHandler; registered in all app load.js) |
| VUE-014 | ojs,omp,ops | components/Container/AddContextContainer.vue | Site-admin add/edit context (journal/press/server) wizard container (lib/pkp admin/editContext.tpl) |
| VUE-015 | ojs,omp,ops | components/Container/AdminPage.vue | Site administration page container (lib/pkp AdminHandler) |
| VUE-016 | ojs,omp,ops | components/Container/AdvancedSearchReviewerContainer.vue | Advanced reviewer-search surface hosted in legacy add-reviewer form (lib/pkp advancedSearchReviewerForm.tpl) Claimed by: reviewer-assignment-and-management. |
| VUE-017 | ojs,omp,ops | components/Container/DecisionPage.vue | Editorial decision wizard page (lib/pkp DecisionHandler) |
| VUE-018 | ojs,omp,ops | components/Container/DoiPageOJS.vue (variants DoiPageOMP/DoiPageOPS) | DOI management page; per-app variant registered as `DoiPage` in each app's js/load.js (lib/pkp PKPDoisHandler) |
| VUE-019 | ojs,omp,ops | components/Container/ImportExportPage.vue | Import/export plugin page container (importexport plugins in all three apps + lib/pkp native plugin) |
| VUE-020 | omp | components/Container/ManageCatalogPage.vue | OMP catalog management page (omp ManageCatalogHandler; only in omp js/load.js) |
| VUE-021 | ojs,omp,ops | components/Container/ManageEmailsPage.vue | Emails (mailables + templates) management page (ManagementHandler) |
| VUE-022 | ojs,omp,ops | components/Container/SettingsPage.vue | Settings pages container (context/website/workflow/distribution; ManagementHandler) |
| VUE-023 | ojs,omp,ops | components/Container/StartSubmissionPage.vue | Begin-submission page (title/language checklist; lib/pkp PKPSubmissionHandler) |
| VUE-024 | ojs,omp,ops | components/Container/StatsEditorialPage.vue | Editorial activity stats page (PKPStatsHandler::editorial; OPS registers it with chart view removed) |
| VUE-025 | ojs,omp,ops | components/Container/StatsPublicationsPage.vue | Publication/article usage stats page (PKPStatsHandler::publications) |
| VUE-026 | ojs,omp,ops | components/Container/StatsContextPage.vue | Context (journal/press/server) usage stats page (PKPStatsHandler::context) |
| VUE-027 | ojs | components/Container/StatsIssuesPage.vue | Issue usage stats page (ojs StatsHandler::issues; only in ojs js/load.js) |
| VUE-028 | ojs,omp,ops | components/Container/StatsUsersPage.vue | Users-by-role stats page (PKPStatsHandler::users) |
| VUE-029 | ojs,omp,ops | components/Container/SubmissionWizardPage.vue (variants SubmissionWizardPageOMP/OPS) | Multi-step submission wizard; per-app variant registered as `SubmissionWizardPage` in each app's js/load.js (PKPSubmissionHandler) |

## Managers

| ID | apps | pointer | what it is |
|---|---|---|---|
| VUE-030 | ojs,omp,ops | managers/CategoryManager/CategoryManager.vue | Category tree management (mounted via each app's templates/management/context.tpl) |
| VUE-031 | omp | managers/ChapterManager/ChapterManager.vue | Monograph chapters management (WorkflowPageOMP only) |
| VUE-032 | ojs,omp,ops | managers/CitationManager/CitationManager.vue | Publication citations list management (all three WorkflowPage variants) |
| VUE-033 | ojs,omp,ops | managers/ContributorManager/ContributorManager.vue | Publication contributors management (all three WorkflowPage variants) |
| VUE-034 | ojs,omp,ops | managers/ContributorRoleManager/ContributorRoleManager.vue | Contributor-role (CRediT-style) settings manager (lib/pkp management/workflow.tpl) |
| VUE-035 | ojs,omp,ops | managers/DataCitationManager/DataCitationManager.vue | Data citations management (SubmissionWizardPage + all WorkflowPage variants) |
| VUE-036 | ojs,omp,ops | managers/DiscussionManager/DiscussionManager.vue | Workflow discussions & tasks manager (all three WorkflowPage variants) |
| VUE-037 | ojs,omp,ops | managers/DiscussionManager/DiscussionManagerReviewer.vue | Reviewer-facing discussions surface (lib/pkp reviewer/review/step3.tpl, reviewCompleted.tpl) |
| VUE-038 | ojs,omp,ops | managers/FileManager/FileManager.vue | Workflow-stage file manager (all WorkflowPage variants + FileAttacherWorkflowStage) |
| VUE-039 | ojs,omp,ops | managers/FunderManager/FunderManager.vue | Funding sources management (SubmissionWizardPage + all WorkflowPage variants) |
| VUE-040 | ojs,omp,ops | managers/GalleyManager/GalleyManager.vue | Publication galleys management (imported by all three WorkflowPage variants) |
| VUE-041 | ojs,omp,ops | managers/MediaFileManager/MediaFileManager.vue | Publication media files (image linking) manager (all WorkflowPage variants) |
| VUE-042 | ? | managers/NavigationMenuManager/NavigationMenuManager.vue | Navigation menu manager component; no mount of the manager itself found (only its form modal is wired, VUE-065) |
| VUE-043 | ojs,omp,ops | managers/ParticipantManager/ParticipantManager.vue | Submission participants management (all WorkflowPage variants) |
| VUE-044 | omp | managers/PublicationFormatManager/PublicationFormatManager.vue | Publication formats management (WorkflowPageOMP only) |
| VUE-045 | omp | managers/RepresentativeManager/RepresentativeManager.vue | Market representatives management (WorkflowPageOMP only) |
| VUE-046 | ojs,omp,ops | managers/ReviewerManager/ReviewerManager.vue | Review-round reviewers management (imported by all three WorkflowPage variants) Claimed by: reviewer-assignment-and-management. |
| VUE-047 | ojs,omp,ops | managers/ReviewerRecommendationManager/ReviewerRecommendationManager.vue | Reviewer recommendation options settings manager (SettingsPage; lib/pkp management/workflow.tpl) |
| VUE-048 | ojs,omp | managers/ReviewerSuggestionManager/ReviewerSuggestionManager.vue | Author reviewer-suggestions manager in workflow (WorkflowPageOJS + WorkflowPageOMP) |
| VUE-049 | ojs | managers/ReviewRoundResponseManager/ReviewRoundResponseManager.vue | Author review-round response manager (WorkflowPageOJS + workflowConfigAuthorOJS) |
| VUE-050 | ojs,omp,ops | managers/TaskTemplateManager/TaskTemplateManager.vue | Task/discussion template settings manager (SettingsPage; lib/pkp management/workflow.tpl) |
| VUE-051 | ojs,omp,ops | managers/UserAccessManager/UserAccessManager.vue | Users list with access actions (lib/pkp management/accessUsers.tpl) |
| VUE-052 | ojs,omp,ops | managers/UserInvitationManager/UserInvitationManager.vue | Pending user invitations table (lib/pkp management/access.tpl) · Claimed by: user-invitations. |

## Screen-level modals

| ID | apps | pointer | what it is |
|---|---|---|---|
| VUE-053 | ojs,omp,ops | managers/FileManager/modals/FileMetadataForm.vue | File metadata edit modal (registered as controller in all three app load.js; also opened from legacy file grids) |
| VUE-054 | ojs,omp,ops | managers/CategoryManager/EditCategoryFormModal.vue | Add/edit category side modal |
| VUE-055 | ojs,omp,ops | managers/CitationManager/modals/CitationEditModal.vue | Edit citation side modal |
| VUE-056 | ojs,omp,ops | managers/ContributorRoleManager/EditContributorRoleFormModal.vue | Add/edit contributor role side modal |
| VUE-057 | ojs,omp,ops | managers/DataCitationManager/modals/DataCitationEditModal.vue | Edit data citation side modal |
| VUE-058 | ojs,omp,ops | managers/DiscussionManager/DiscussionManagerFormModal.vue | Add/edit discussion or task side modal |
| VUE-059 | ojs,omp,ops | managers/DiscussionManager/DiscussionManagerFormDisplayModal.vue | Discussion/task read-only display side modal |
| VUE-060 | ojs,omp,ops | managers/DiscussionManager/DiscussionManagerHistoryModal.vue | Discussion/task activity history side modal |
| VUE-061 | ojs,omp,ops | managers/FunderManager/modals/FunderEditModal.vue | Add/edit funder side modal |
| VUE-062 | ojs,omp,ops | managers/MediaFileManager/MediaFileManagerAddFileModal.vue | Media file upload side modal |
| VUE-063 | ojs,omp,ops | managers/MediaFileManager/MediaFileManagerBatchLinkImagesModal.vue | Batch link images to galley text side modal |
| VUE-064 | ojs,omp,ops | managers/MediaFileManager/MediaFileManagerManualLinkImageFormModal.vue | Manually link a single image side modal |
| VUE-065 | ojs,omp,ops | managers/MediaFileManager/MediaFileManagerMetadataFormModal.vue | Media file metadata (alt text) side modal |
| VUE-066 | ojs,omp,ops | managers/NavigationMenuManager/NavigationMenuManagerFormModal.vue | Navigation menu add/edit side modal (opened from lib/pkp navigationMenus legacy grid) |
| VUE-067 | ojs,omp,ops | managers/ReviewerManager/modals/WorkflowLogResponseModal.vue | Reviewer response log side modal (ReviewerManager) Claimed by: reviewer-assignment-and-management. |
| VUE-068 | ojs,omp,ops | managers/ReviewerManager/ReviewerManagerReadReviewModal.vue | Read-review side modal (lib/pkp controllers/grid/users/reviewer/readReview.tpl) Claimed by: reviewer-assignment-and-management. |
| VUE-069 | ojs,omp,ops | managers/ReviewerRecommendationManager/ReviewerRecommendationsEditModal.vue | Add/edit reviewer recommendation option side modal |
| VUE-070 | ojs | managers/ReviewRoundResponseManager/AuthorResponseFormModal.vue | Author review-round response form side modal (OJS workflow) |
| VUE-071 | ojs,omp,ops | managers/TaskTemplateManager/TaskTemplateManagerFormModal.vue | Add/edit task template side modal |
| VUE-072 | ojs,omp,ops | pages/manageEmails/EditMailableModal.vue | Mailable settings edit side modal (ManageEmailsPage) |
| VUE-073 | ojs,omp,ops | pages/manageEmails/EditTemplateModal.vue | Email template edit side modal (ManageEmailsPage) |
| VUE-074 | ojs,omp,ops | pages/counter/components/CounterReportsEditModal.vue | COUNTER R5 report parameters side modal |
| VUE-075 | ojs,omp,ops | pages/dashboard/modals/DashboardModalFilters.vue | Dashboard filters side modal |
| VUE-076 | ojs,omp,ops | pages/reviewerSubmission/RoundHistoryModal.vue | Reviewer's past review-round history side modal |
| VUE-077 | ojs,omp,ops | pages/statsContext/ContextDownloadReportModal.vue | Context stats download-report side modal |
| VUE-078 | ojs | pages/statsIssues/IssueDownloadReportModal.vue | Issue stats download-report side modal (OJS-only stats page) |
| VUE-079 | ojs,omp,ops | pages/statsPublications/PublicationsDownloadReportModal.vue | Publication stats download-report side modal |
| VUE-080 | ojs,omp,ops | pages/statsUsers/UserExportModal.vue | Users stats export side modal |
| VUE-081 | ojs,omp,ops | pages/submissionWizard/ReconfigureSubmissionModal.vue | Change section/language mid-wizard side modal (SubmissionWizardPage) |
| VUE-082 | ojs,omp,ops | pages/userComments/UserCommentDetailModal.vue | User comment detail/moderation side modal |
| VUE-083 | ojs,omp,ops | pages/userComments/UserCommentReportDetailModal.vue | User comment report detail side modal |
| VUE-084 | ojs,omp,ops | pages/workflow/components/publication/WorkflowVersionSideModal.vue | Create/edit publication version side modal (useWorkflowActions) |
| VUE-085 | ojs,omp,ops | pages/workflow/modals/WorkflowChangeSubmissionLanguageModal.vue | Change submission language side modal (useWorkflowActions) |
| VUE-086 | ojs,omp,ops | pages/workflow/modals/WorkflowSelectRevisionFormModal.vue | Select revision target for decision side modal (useWorkflowDecisions) |
| VUE-087 | ojs,omp,ops | components/Composer/FileAttacherModal.vue | Attach-files side modal opened from Composer (decision emails, discussions) |
| VUE-088 | ojs,omp,ops | components/FileAttacher/AttacherModal.vue | File attacher source-picker side modal (FileAttacher; also used by Composer/discussions) |
| VUE-089 | ojs,omp,ops | components/Form/fields/FieldPreparedContentInsertModal.vue | Insert prepared-content/variable side modal for rich text fields |
| VUE-090 | ojs,omp,ops | components/Form/fields/VocabularyModal/VocabularyModal.vue | Controlled-vocabulary browser side modal (FieldBaseAutosuggest) |
| VUE-091 | ojs,omp,ops | components/InsertSummaryOfChanges/InsertSummaryOfChangesModal.vue | Insert summary-of-changes side modal (workflow publication form / version form) |
| VUE-092 | ojs,omp,ops | components/ListPanel/announcements/AnnouncementsEditModal.vue | Add/edit announcement side modal (AnnouncementsListPanel; site admin + context settings) |
| VUE-093 | ojs,omp,ops | components/ListPanel/contributors/ContributorsEditModal.vue | Add/edit contributor side modal (ContributorsListPanel) |
| VUE-094 | ojs,omp,ops | components/ListPanel/contributors/ContributorsPreviewModal.vue | Contributor display-formats preview side modal (ContributorsListPanel) |
| VUE-095 | ojs,omp,ops | components/ListPanel/doi/DoiItemVersionModal.vue | Per-version DOI display side modal (DoiListItem) |
| VUE-096 | ojs,omp,ops | components/ListPanel/doi/DoiStatusInfoModal.vue | DOI registration status info side modal (DoiListPanel) |
| VUE-097 | ojs,omp,ops | components/ListPanel/highlights/HighlightsEditModal.vue | Add/edit highlight side modal (HighlightsListPanel; site admin + context settings) |
| VUE-098 | ojs,omp,ops | components/ListPanel/institutions/InstitutionsEditModal.vue | Add/edit institution side modal (InstitutionsListPanel) |
| VUE-099 | ojs,omp,ops | components/ListPanel/reviewerSuggestions/ReviewerSuggestionsEditModal.vue | Add/edit reviewer suggestion side modal (ReviewerSuggestionsListPanel; submission wizard) |
| VUE-100 | ojs,omp,ops | components/ListPanel/submissionFiles/SubmissionFilesEditModal.vue | Edit submission file (wizard) side modal (SubmissionFilesListPanel) |
| VUE-101 | omp | components/ListPanel/submissions/CatalogEditModal.vue | Catalog entry edit side modal (CatalogListPanel; OMP manage catalog) |
