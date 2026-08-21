# Atlas — AFFW: per-screen affordances, submission wizard + editorial workflow

- **Sub-modality**: `AFFW` — within-screen affordances (visible controls/actions) for the submission wizard and editorial workflow area, per the four-way `aff` split in `atlas/README.md`.
- **Sweep date**: 2026-07-26
- **Trees swept**: `ojs-main/lib/ui-library/src` (pages, managers, components), `ojs-main/lib/pkp/templates`, `ojs-main/templates`, `omp-main/templates`, `ops-main/templates`. lib/pkp SHA `ad4606f93e` (canonical per README sweep provenance; content-identical across the three checkouts).
- **Method** (four parallel mechanical sub-sweeps, direct file reads + greps; no liveness judgment):
  - Vue workflow page: full read of `lib/ui-library/src/pages/workflow/**` — `WorkflowPage*.vue`, `components/**`, `modals/**`, and every `composables/useWorkflowConfig/workflowConfig{Editorial,Author}{OJS,OMP,OPS}.js` + `useWorkflowNavigationConfig{OJS,OMP,OPS}.js` + `useWorkflowActions/Decisions/VersionForm/PublicationFormIssue/Menu` (these declare the per-stage/per-tab action, menu and button items).
  - Vue managers: full read of `lib/ui-library/src/managers/{Participant,File,Reviewer,Discussion,Galley,Contributor,Citation,DataCitation,Funder,MediaFile,Chapter,PublicationFormat,Representative,ReviewerSuggestion,ReviewRoundResponse}Manager/**` (config/actions/store/modal files; `getTopItems`/`getItemActions`/dialog `actions`).
  - Vue dashboard + wizard + decisions: full read of `lib/ui-library/src/pages/dashboard/**`, `pages/submissionWizard/`, `components/Container/{StartSubmissionPage,SubmissionWizardPage*,DecisionPage,AdvancedSearchReviewerContainer}.vue`, `components/{Composer,FileAttacher}/**`, `components/ListPanel/{submissionFiles,contributors,reviewerSuggestions,users}/**`, plus their Smarty hosts `lib/pkp/templates/submission/*.tpl`, `lib/pkp/templates/decision/record.tpl`, `lib/pkp/templates/dashboard/editors.tpl`.
  - Smarty legacy: `find <tree>/templates -name '*.tpl'` over `lib/pkp/templates/{workflow,authorDashboard,controllers}` and `{ojs,omp,ops}-main/templates/{submission,controllers}`; controls extracted by grepping/reading for `<form`, `<button`, `{fbvFormButton`, `{fbvElement`, `linkAction`, `load_url_in_div`, and `{if}`-gated blocks.
- **Screens covered**: submissions dashboard (editorial / my submissions / view selector / filters modal / bulk delete); start submission; submission wizard (steps, review step, outcome pages, reconfigure modal, files/contributors/reviewer-suggestions panels); editorial decision wizard (+ email composer, file attacher, promote-files); reviewer selection (advanced search + legacy add-reviewer forms); workflow shell (header, side menu); workflow stages — Submission, Internal Review (OMP), External Review, Copyediting, Production — in editorial and author variants; publication tabs (Title & Abstract, Contributors, Metadata, Citations, Data availability, Funding, Identifiers, JATS, Body Text, Galleys, Media, License, Issue/Catalog Entry/Preprint Entry, Chapters, Publication Formats, OMP Marketing tabs); versioning/publish/unpublish/schedule modals and issue-assignment fields; workflow-embedded managers (participants, stage files, reviewers, discussions & tasks, galleys, citations, data citations, funders, media files, chapters, publication formats, representatives, reviewer suggestions, author review-round responses); legacy Smarty layer (file upload wizard, manage-files modals, document/publisher library, reviewer grid forms, read review, stage participants, legacy contributors grid, information center, event log/tasks, author dashboard grids, publish modal, grid framework chrome, OJS issue & galley forms, OPS galley forms, OMP catalog entry & chapter forms).
- **Conventions**: one atom per control/action per screen; identical per-app occurrences collapsed into one atom with a combined `apps` column; guards quoted symbolically as mechanical facts (`v-if`, `{if}`, permission flags) — no reachability judgment; pointers are file path + stable symbol (component, action constant, form/button id, locale key), never line numbers. IDs `AFFW-NNN`, dense in file order, never renumbered.
- **Boundary**: reviewer's own review screens (`templates/reviewer/`, `DiscussionManagerReviewer.vue`, review-assignment dashboard cell internals) → AFFU; settings/admin screens → AFFM; reader front end → AFFR. Deliberate edge overlaps kept here (workflow-reachable): publisher library file forms, review-form editor tabset, OJS edit-issue modal, dashboard Review Assignments column existence.
---

**Part 1 — Submission lists / dashboard**

## Dashboard (editorial / my submissions / view selector)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-001 | ojs,omp,ops | `pages/dashboard/DashboardPage.vue` · `store.currentView.name` heading | Dashboard · page heading · shows current view name + `store.submissionsPagination.itemCount`, with loading `Spinner` (guard: `!store.isSubmissionsLoading ? 'invisible' : ''`) |
| AFFW-002 | ojs,omp,ops | `pages/dashboard/DashboardPage.vue` · `store.leftControlItems` dynamic `<component>` | Dashboard · left control bar · renders configured left controls from `Components` map |
| AFFW-003 | ojs,omp,ops | `pages/dashboard/DashboardPage.vue` · `store.rightControlItems` dynamic `<component>` | Dashboard · right control bar · renders configured right controls |
| AFFW-004 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfig.js` · `getLeftControls` | Dashboard · left controls config · pushes `DashboardActionButton{label:t('common.filter'),action:'openFiltersModal'}`, `DashboardControlBulkActions`, `DashboardControlBulkDeleteButton` |
| AFFW-005 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfig.js` · `getRightControls` | Dashboard · right controls config · pushes `DashboardControlSearch` |
| AFFW-006 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `rightControlItems` filter | Dashboard · search control hidden on search view (guard: `currentViewId.value === SEARCH_VIEW_ID`) |
| AFFW-007 | ojs,omp,ops | `pages/dashboard/components/DashboardActionButton.vue` · `PkpButton @click="dashboardStore[action](actionArgs)"` | Dashboard · generic action button · dispatches a named store action (`Filter` button instance) |
| AFFW-008 | ojs,omp,ops | `pages/dashboard/components/DashboardControlSearch.vue` · `Search @search-phrase-changed → store.setSearchPhrase` | Dashboard · search box · label `editor.submission.search` |
| AFFW-009 | ojs,omp,ops | `pages/dashboard/components/DashboardActiveFilters.vue` · root `div` | Dashboard · active filter chips row (guard: `v-if="searchPhrase \|\| activeFiltersList.length"`) |
| AFFW-010 | ojs,omp,ops | `pages/dashboard/components/DashboardActiveFilters.vue` · `emit('clearSearch')` X button | Dashboard · search chip clear · `Cancel` icon, sr label `common.clearSearch` (guard: `v-if="searchPhrase"`) |
| AFFW-011 | ojs,omp,ops | `pages/dashboard/components/DashboardActiveFilters.vue` · `emit('removeFilter', filter.name, filter.value)` | Dashboard · per-filter chip X · sr label `common.filterRemove` (loop `v-for="filter in activeFiltersList"`) |
| AFFW-012 | ojs,omp,ops | `pages/dashboard/components/DashboardActiveFilters.vue` · `emit('clearFilters')` | Dashboard · "Clear filters" link button `common.filtersClear` (guard: `v-if="activeFiltersList.length"`) |
| AFFW-013 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `clearAllFilters` / `clearFiltersFormField` / `clearSearch` | Dashboard · filter-chip handlers · clear url query params + form state |
| AFFW-014 | ojs,omp,ops | `pages/dashboard/components/DashboardControlBulkActions.vue` · `DropdownActions` `common.moreActions`, `button-variant="ellipsis"` | Dashboard · bulk actions ellipsis menu (guard: `v-if="actions.length"`) |
| AFFW-015 | ojs,omp,ops | `pages/dashboard/components/DashboardControlBulkActions.vue` · action `bulkDeleteSelectionEnable` | Dashboard · "Delete incomplete submissions" menu item `dashboard.submissions.incomplete.bulkDelete.button`, warnable, icon `Cancel` (guard: `dashboardStore.bulkDeleteIsAvailableForUser`; `disabled` when `bulkDeleteSubmissionIdsCanBeDeleted.length === 0`) |
| AFFW-016 | ojs,omp,ops | `pages/dashboard/components/DashboardControlBulkDeleteButton.vue` · `store.bulkDeleteActionDelete()` | Dashboard · confirm bulk delete button (guard: `v-if="store.bulkDeleteSelectionEnabled"`; `is-disabled` when `bulkDeleteSelectedItems.length === 0`) |
| AFFW-017 | ojs,omp,ops | `pages/dashboard/components/DashboardControlBulkDeleteButton.vue` · `store.bulkDeleteSelectionDisable()` | Dashboard · cancel bulk-delete mode button `common.cancel` (guard: `v-if="store.bulkDeleteSelectionEnabled"`) |
| AFFW-018 | ojs,omp,ops | `pages/dashboard/composables/useDashboardBulkDelete.js` · `bulkDeleteActionDelete` dialog | Dashboard · bulk delete confirm dialog · title `dashboard.submissions.incomplete.bulkDelete.confirm`, body `...bulkDelete.body`, actions `common.confirm` / `common.cancel`, `modalStyle: 'negative'` |
| AFFW-019 | ojs,omp,ops | `pages/dashboard/composables/useDashboardBulkDelete.js` · `apiCall` | Dashboard · bulk delete request · `DELETE _submissions?ids[]` then refetch |
| AFFW-020 | ojs,omp,ops | `pages/dashboard/composables/useDashboardBulkDelete.js` · `bulkDeleteIsAvailableForUser` | Dashboard · bulk delete availability (guard: `EDITORIAL_DASHBOARD && hasCurrentUserAtLeastOneRole([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER])` or `MY_SUBMISSIONS`) |
| AFFW-021 | ojs,omp,ops | `pages/dashboard/composables/useDashboardBulkDelete.js` · `canBeDeleted` | Dashboard · per-row deletability (guard: `submission.submissionProgress && (hasCurrentUserAtLeastOneRole([SITE_ADMIN, MANAGER]) \|\| hasCurrentUserAtLeastOneAssignedRoleInAnyStage(submission,[AUTHOR]))`) |
| AFFW-022 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardTable.vue` · `PkpTable` + `@sort="columnId => $emit('sortColumn', columnId)"` | Dashboard · submissions table · sortable header columns from `columns`, `:allows-sorting="column.sortable"` |
| AFFW-023 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardTable.vue` · leading `TableColumn` / `DashboardCellBulkDelete` | Dashboard · bulk-select column, sr header `dashboard.submissions.incomplete.bulkDelete.column.description` (guard: `v-if="dashboardStore.bulkDeleteSelectionEnabled"`) |
| AFFW-024 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardTable.vue` · `TableBody :empty-text` | Dashboard · empty state · `common.loading` when `isSubmissionsLoading` else `grid.noItems` |
| AFFW-025 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardTable.vue` · `TablePagination @set-page` | Dashboard · pagination control |
| AFFW-026 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellBulkDelete.vue` · `TableCellSelect @change="change"` | Dashboard · row checkbox · selects/deselects via `bulkDeleteSelectItem`/`bulkDeleteDeselectItem` (guard: `:hidden="!canBeDeleted"`) |
| AFFW-027 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfig.js` · `getColumns` MY_SUBMISSIONS branch | Dashboard (My Submissions) · columns `id`(sortable) / `title` / `stage` / `activity` / `actions` |
| AFFW-028 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfig.js` · `getColumns` else branch | Dashboard (Editorial) · columns `id`(sortable) / `title` / `stage` / `lastActivity`(sortable) / `activity` / `actions` |
| AFFW-029 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfig.js` · `getColumns` MY_REVIEW_ASSIGNMENTS branch | Dashboard (Review Assignments) · columns `id`/`title`/`activity`/`actions` — reviewer-view internals belong to AFFU, recorded for completeness |
| AFFW-030 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionId.vue` · `TableCell` | Dashboard · ID cell (read-only) |
| AFFW-031 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionTitle.vue` · `TableCell :id="'submission-title-'+item.id"` | Dashboard · title cell · row header, `authorsStringShort` + localized `fullTitle` |
| AFFW-032 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionStage.vue` · `StageBubble` | Dashboard · stage cell · `getExtendedStage` / `getExtendedStageLabel` |
| AFFW-033 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionDays.vue` · `calculateDaysBetweenDates` | Dashboard · days-since-last-activity cell |
| AFFW-034 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionActions.vue` · `PkpButton` `common.view` → `openWorkflowModal(item.id)` | Dashboard · row "View" action button (guard: `v-if="showButton"` — false when `item.submissionProgress`, or on editorial dashboard when author/reviewer without MANAGER/SUB_EDITOR/ASSISTANT role) |
| AFFW-035 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionActivity/DashboardCellSubmissionActivity.vue` · `cellConfig` + `Components` map | Dashboard · activity cell · renders alert/reviews sub-components per `getEditorialActivityForEditorialDashboard` / `getEditorialActivityForMySubmissions` |
| AFFW-036 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionActivity/DashboardCellSubmissionActivityActionAlert.vue` · `PkpButton @click="handleAction"` | Dashboard · activity alert + inline action button (guard: `v-if="alert"` / `v-if="actionName"`), dispatches `dashboardStore[actionName]` |
| AFFW-037 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfigEditorialActivity.js` · `actionName: 'openSubmissionWizard'` | Dashboard · "Complete submission" action `submission.list.completeSubmission` on incomplete submissions |
| AFFW-038 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfigEditorialActivity.js` · `actionName: ParticipantManagerActions.PARTICIPANT_ASSIGN` | Dashboard · "Assign editor" action `submission.list.assignEditor` |
| AFFW-039 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfigEditorialActivity.js` · `actionName: ReviewerManagerActions.REVIEWER_ADD_REVIEWER` | Dashboard · "Assign reviewers" action `dashboard.assignReviewers` |
| AFFW-040 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfigEditorialActivity.js` · `actionName: FileManagerActions.FILE_UPLOAD` | Dashboard (My Submissions) · "Submit revisions" action `dashboard.submitRevisions` with alert `dashboard.revisionRequested` |
| AFFW-041 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfigEditorialActivity.js` · alert-only configs | Dashboard · non-actionable status alerts (e.g. `dashboard.noAccessBeingAuthor`, `dashboard.noAccessBeingReviewer`, `dashboard.declinedDuringStage`, `dashboard.revisionRequestedFromAuthor`, `dashboard.recommendOnly.*`, `dashboard.copyEditedFilesUploaded`, `dashboard.toBePublishedInIssue`, `dashboard.minimumReviewsConfirmedDecisionNeeded`, `dashboard.newReviewRoundToBeCreated`) |
| AFFW-042 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionActivity/DashboardCellSubmissionActivityReviews.vue` · `DashboardCellSubmissionActivityReviewsItem` loop | Dashboard · per-reviewer activity indicators (guard: `v-if="reviewAssignments.length"`) |
| AFFW-043 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionActivity/DashboardCellSubmissionActivityReviewsItem.vue` · `PkpPopover` + `ReviewActivityIndicator` | Dashboard · reviewer indicator popover trigger |
| AFFW-044 | ojs,omp,ops | `pages/dashboard/components/ReviewActivityIndicatorPopover/ReviewActivityIndicatorPopover.vue` · `textButton` / `primaryButton` / `negativeButton` | Dashboard · popover action buttons (guards: `v-if="textButton"`, `v-if="primaryButton \|\| negativeButton"`), emit `action` |
| AFFW-045 | ojs,omp,ops | `pages/dashboard/composables/useDashboardConfigReviewActivity.js` · `ActionsMapping` | Dashboard · popover actions map · `RESEND_REVIEW_REQUEST`, `EDIT_DUE_DATE`, `VIEW_DETAILS`, `CANCEL_REVIEWER`, `UNASSIGN_REVIEWER`, `VIEW_RECOMMENDATION`, `VIEW_UNREAD_RECOMMENDATION` → `ReviewerManagerActions.*` |
| AFFW-046 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `reviewerAddReviewer` / `reviewerResendRequest` / `reviewerEditReview` / `reviewerReviewDetails` / `reviewerCancelReviewer` / `reviewerUnassignReviewer` | Dashboard · reviewer manager action handlers wired to popover, each refetches submissions |
| AFFW-047 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `fileUpload` / `participantAssign` | Dashboard · file-upload and assign-participant action handlers |
| AFFW-048 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionActivity/DashboardCellSubmissionActivityReviewsOpen.vue` · `UserAvatar` in `PkpPopover` | Dashboard (My Submissions) · open reviewers avatars `dashboard.reviewersAssigned` (guard: `v-if="openReviewAssignements.length"`) |
| AFFW-049 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellSubmissionActivity/DashboardCellSubmissionActivityReviewsUpdate.vue` · `dashboard.reviewUpdateCounts` | Dashboard (My Submissions) · completed/total review counter |
| AFFW-050 | ojs,omp,ops | `pages/dashboard/components/DashboardTable/DashboardCellReviewAssignmentActions.vue` (+ `DashboardCellReviewAssignmentId/Title/Activity/ActivityAlert.vue`) | Dashboard (Review Assignments) · reviewer-view cells — enumerated as existing, internals deferred to the AFFU sweep |
| AFFW-051 | ojs,omp,ops | `pages/dashboard/modals/DashboardModalFilters.vue` · `SideModalBody` title `common.filter` | Dashboard · filters side modal · hosts `PkpForm` bound to cloned `filtersFormInitial` |
| AFFW-052 | ojs,omp,ops | `pages/dashboard/modals/DashboardModalFilters.vue` · `clearFiltersForm()` button `common.filtersClear` | Dashboard filters modal · clear-filters button |
| AFFW-053 | ojs,omp,ops | `pages/dashboard/modals/DashboardModalFilters.vue` · `applyFilters()` button `dashboard.applyFilters` | Dashboard filters modal · apply button, emits `updateFiltersForm` then `closeModal` |
| AFFW-054 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `openFiltersModal` | Dashboard · opens `DashboardModalFilters` via `openSideModal` |
| AFFW-055 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `openWorkflowModal` | Dashboard · opens `WorkflowPage` side modal, sets `queryParamsUrl.workflowSubmissionId`, refetches on close |
| AFFW-056 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `openSubmissionWizard` | Dashboard · redirects to `submission?id=<id>` |
| AFFW-057 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `openReviewerForm` | Dashboard · redirects to `reviewer/submission/<id>` |
| AFFW-058 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `applySort` / `setCurrentPage` / `submissionsQuery` | Dashboard · sorting, paging and query assembly (`searchPhrase` + view `queryParams` + filters + sort) |
| AFFW-059 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `DashboardPage.props.views` + `currentViewId` / `currentView` | Dashboard · view selector state · views come from props, selected via `currentViewId` url query param, falls back to `views[0].id` |
| AFFW-060 | ojs,omp,ops | `pages/dashboard/dashboardPageStore.js` · `SEARCH_VIEW_ID = 'search'` + `_preSearchViewId` watchers | Dashboard · search view enter/leave · resets page, clears filters, restores prior view when phrase+filters empty |
| AFFW-061 | ojs,omp,ops | `pages/dashboard/dashboardConstants.js` · `DashboardPageTypes` | Dashboard · page-type constants `editorialDashboard` / `myReviewAssignments` / `mySubmissions` |
| AFFW-062 | ojs,omp,ops | `components/SideNav/SideNav.vue` · `SideMenu` + `menuItemsEnriched` | Dashboard · views sidebar (view selector) · renders dashboards/reviewAssignments/mySubmissions submenus with per-view count badges (guard: `v-if="Object.keys(links).length"`) |
| AFFW-063 | ojs,omp,ops | `components/SideNav/SideNav.vue` · `onSearchSubmit` / `searchMenuItem` (`itemType === 'search'`) | Dashboard sidebar · global search box · sets `item.searchParam` query param in place or navigates; empty submit clears (guard: `queryParams.currentViewId === 'search' && compareUrlPaths(...)`) |
| AFFW-064 | ojs,omp,ops | `components/SideNav/SideNav.vue` · `ViewsWithAttentionBadge = ['reviewer-action-required','reviews-overdue']` | Dashboard sidebar · attention-colored count badges for those view ids |
| AFFW-065 | ojs,omp,ops | `lib/pkp/classes/template/PKPTemplateManager.php` · `$menu['submit']` `dashboard.startNewSubmission` | Dashboard sidebar · "New submission" link → page `submission` (guard: `!$request->getContext()->getData('disableSubmissions')`) |
| AFFW-066 | ojs,omp,ops | `lib/pkp/classes/template/PKPTemplateManager.php` · `$menu['dashboards'] / ['reviewAssignments'] / ['mySubmissions']` | Dashboard sidebar · three view groups, gated on roles MANAGER/SUB_EDITOR/ASSISTANT, REVIEWER, AUTHOR respectively |
| AFFW-067 | ojs,omp,ops | `lib/pkp/templates/dashboard/editors.tpl` · `<dashboard-page v-bind="pageInitConfig" />` | Dashboard · Smarty host mounting `DashboardPage` |
| AFFW-068 | ojs,omp,ops | `components/ListPanel/submissions/SubmissionsListPanel.vue` · `submission.submit.newSubmissionSingle` button | Legacy submissions list panel · "New submission" button (pre-dashboard surface) |
---

**Part 2 — Submission wizard**

## Start submission

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-069 | ojs,omp,ops | `lib/pkp/templates/submission/start.tpl` · `{block name="page"}` heading `submission.wizard.title` | Start Submission · page heading |
| AFFW-070 | ojs,omp,ops | `lib/pkp/templates/submission/start.tpl` · `<notification>` `manager.setup.disableSubmissions.notAccepting` | Start Submission · submissions-closed notice (guard: `{if $currentContext->getData('disableSubmissions')}`) |
| AFFW-071 | ojs,omp,ops | `lib/pkp/templates/submission/start.tpl` · `<start-submission-form v-bind="form" @set="updateForm">` | Start Submission · start form inside `panel/panel-section` (guard: `{else}` of `disableSubmissions`) |
| AFFW-072 | ojs,omp,ops | `components/Container/StartSubmissionPage.vue` · `updateForm` | Start Submission page container · merges form field updates into `this.form` |
| AFFW-073 | ojs,omp,ops | `components/Form/submission/StartSubmissionForm.vue` · `submitValues` | Start Submission form · strips `title` from the submission API payload |
| AFFW-074 | ojs,omp,ops | `components/Form/submission/StartSubmissionForm.vue` · `success(submission)` | Start Submission form · saves `title` to `publications[0]._href` then redirects to `submission.urlSubmissionWizard` |
| AFFW-075 | ojs,omp,ops | `components/Form/submission/StartSubmissionForm.vue` · `complete(xhr, status)` | Start Submission form · keeps spinner while redirecting |
| AFFW-076 | ojs | `ojs-main/templates/submission/form/sectionPolicy.tpl` · `{fbvFormSection title="section.policy"}` | Start Submission · section policy block (deprecated 3.4) |

## Submission wizard

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-077 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `.submissionWizard__submissionDetails` | Wizard · breadcrumb line · submission id / `publication.authorsStringShort` / title (guards: `v-if="publication.authorsStringShort"`, `v-if="localize(publication.title)"`) |
| AFFW-078 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · header `pkp-button @click="saveForLater"` | Wizard · header "Save for later" button `reviewer.submission.saveReviewForLater` (guard: `:is-disabled="isDisconnected"`) |
| AFFW-079 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `#submission-configuration` `<button class="-linkButton" @click="openReconfigureModal">` | Wizard · reconfigure link `manager.reviewerSearch.change` next to `{$submittingTo}` (guard: `{if $submittingTo}`) |
| AFFW-080 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<steps @step:open="openStep">` | Wizard · step navigator · `submission.wizard.completeSteps`, `common.showingSteps`, `common.showAllSteps`, scrolls to `$refs.pageTitle` |
| AFFW-081 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<step v-for="step in steps">` / `<panel-section v-for="section in step.sections">` | Wizard · per-step panel with section header (`section.name` + `section.description`) |
| AFFW-082 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<pkp-form ref="autosaveForms" @set="updateAutosaveForm">` | Wizard · form section (guard: `v-if="section.type === 'form'"`) |
| AFFW-083 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<submission-files-list-panel v-bind="components.submissionFiles" @set="set">` | Wizard · files section (guard: `v-else-if="section.type === 'files'"`) |
| AFFW-084 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<contributors-list-panel @updated:contributors="setContributors" @updated:publication="setPublication">` | Wizard · contributors section (guard: `v-else-if="section.type === 'contributors'"`) |
| AFFW-085 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<reviewer-suggestions-list-panel @updated:reviewer-suggestions="setReviewerSuggestion">` | Wizard · reviewer suggestions section (guard: `v-else-if="section.type === 'reviewerSuggestions'"`) |
| AFFW-086 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<data-citation-manager v-bind="components.dataCitation">` | Wizard · data citations section (guard: `v-else-if="section.type === 'dataCitations'"`) |
| AFFW-087 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<funder-manager v-bind="components.funder">` | Wizard · funders section (guard: `v-else-if="section.type === 'funders'"`) |
| AFFW-088 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<component :is="section.component">` | Wizard · generic component section (guard: `v-else-if="section.component"`) |
| AFFW-089 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · review section `<notification>` `submission.wizard.errors` | Wizard review step · errors banner (guard: `v-if="Object.keys(errors).length"`) |
| AFFW-090 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `{foreach from=$reviewSteps}` `{include file=$step.reviewTemplate}` / `<component :is="'{$step.component}'">` | Wizard review step · per-step review panels (guard: `{if $step.reviewTemplate}{elseif $step.component}`) |
| AFFW-091 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `.submissionWizard__loadingReview` transition | Wizard review step · validating overlay `submission.wizard.validating` (guard: `v-if="isAutosaving \|\| isValidating"`) |
| AFFW-092 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `<pkp-form v-bind="section.form" @set="updateForm">` | Wizard review step · confirmation form (guard: `v-if="section.type === 'confirm'"`) |
| AFFW-093 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · footer `pkp-button @click="previousStep"` `common.back` | Wizard footer · Back button (guard: `v-if="!isOnFirstStep"`, else empty `<span>`) |
| AFFW-094 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `.submissionWizard__lastSaved` `role="status"` | Wizard footer · autosave status · `common.saving` / `common.reconnecting` / `lastAutosavedMessage` (guards: `v-if="isAutosaving"`, `v-else-if="isDisconnected"`, `v-else-if="lastAutosavedMessage"`) |
| AFFW-095 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · `#cancelSubmission` `@click="cancelSubmission"` | Wizard footer · Cancel submission link button `common.cancel` (guard: `{if $canCancelSubmission}`) |
| AFFW-096 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · footer `pkp-button @click="saveForLater"` | Wizard footer · Save for later button (guard: `:is-disabled="isDisconnected"`) |
| AFFW-097 | ojs,omp,ops | `lib/pkp/templates/submission/wizard.tpl` · footer `pkp-button :is-primary @click="nextStep"` | Wizard footer · Continue / Submit button · label `form.submit` when `isOnLastStep` else `common.continue` (guard: `:is-disabled="isOnLastStep && !canSubmit"`) |
| AFFW-098 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `openReconfigureModal` | Wizard · opens `ReconfigureSubmissionModal` with `components.reconfigureSubmission` form |
| AFFW-099 | ojs,omp,ops | `pages/submissionWizard/ReconfigureSubmissionModal.vue` · `SideModalBody` title `submission.wizard.changeSubmission` | Reconfigure modal · hosts `PkpForm`, emits `set` / `reconfigureSubmission` |
| AFFW-100 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `reconfigureSubmission(values)` | Wizard · splits values into `reconfigureSubmissionProps` / `reconfigurePublicationProps`, PUTs both, reloads page |
| AFFW-101 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `openStep` / `nextStep` / `previousStep` / `openUrlHash` / `addHistory` | Wizard · step navigation + `#hash` browser history sync |
| AFFW-102 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `saveForLater` | Wizard · flushes autosaves then `PUT {submissionApiUrl}/saveForLater` and redirects to `submissionSavedUrl` |
| AFFW-103 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `openSaveForLaterFailed` | Wizard · save-for-later failure dialog (`i18nDisconnected` / `i18nUnableToSave`, `common.ok`, `modalStyle: 'negative'`) |
| AFFW-104 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `submit()` dialog `name: 'submitConfirmation'` | Wizard · submit confirmation dialog (`i18nSubmit` / `i18nConfirmSubmit`), primary submit + `common.cancel`; PUTs `submitApiUrl` with confirm fields, redirects to `submissionWizardUrl` |
| AFFW-105 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `cancelSubmission()` dialog `name: 'SubmissionCancel'` | Wizard · cancel confirmation (`submission.wizard.submissionCancel` / `submission.wizard.cancel.confirmation`), DELETEs `submissionCancelApiUrl`, redirects to `submissionCancelledUrl`, `modalStyle: 'negative'` |
| AFFW-106 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `validate()` | Wizard · `_validateOnly` PUT on entering last step, populates `errors` |
| AFFW-107 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `canSubmit` / `isConfirmed` / `isValid` | Wizard · submit enablement · requires not autosaving, connected, no errors, all `field-options` in `review`/`confirmSubmission` ticked |
| AFFW-108 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `restoreStoredAutosave` / `addAutosaves` / `updateAutosaveForm` | Wizard · autosave restore from local storage (`i18nUnsavedChanges`, `i18nDiscardChanges`) and stale-form flush |
| AFFW-109 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `errors` watcher `pkp.eventBus.$emit('submission:submit:errors')` | Wizard · maps validation errors back to step forms and broadcasts |
| AFFW-110 | ojs,omp,ops | `components/Container/SubmissionWizardPage.vue` · `setContributors` / `setReviewerSuggestion` / `setPublication` / `reloadSubmission` / `reloadPublication` / `triggerDataChange` | Wizard · list-panel data sync handlers |
| AFFW-111 | omp | `components/Container/SubmissionWizardPageOMP.vue` · `chapters` + `pkp.eventBus.$on('chapter:added'\|'chapter:edited'\|'chapter:deleted')` | Wizard (OMP) · chapter list state for the review step |
| AFFW-112 | ops | `components/Container/SubmissionWizardPageOPS.vue` · `galleys` + `addGalley`/`editGalley`/`deleteGalley`/`setSubmissionFile` | Wizard (OPS) · galley list state from `galley:*` and `submissionFile:*` events |

## Wizard review-step panels (Smarty)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-113 | ojs,omp,ops | `lib/pkp/templates/submission/review-details.tpl` · `.submissionWizard__reviewPanel__edit` `@click="openStep('{$step.id}')"` | Wizard review step · Details panel "Edit" button `common.edit`, one panel per locale (`{foreach from=$locales}`) |
| AFFW-114 | ojs,omp,ops | `lib/pkp/templates/submission/review-details.tpl` · `{include review-publication-field.tpl}` calls | Wizard review step · title/abstract always; keywords, plainLanguageSummary, dataAvailability gated on `in_array($currentContext->getData(<prop>), [METADATA_REQUEST, METADATA_REQUIRE])` |
| AFFW-115 | ojs,omp,ops | `lib/pkp/templates/submission/review-details.tpl` · dataCitations / citations / funders blocks | Wizard review step · citation & funder summaries with required-warning notifications (guards: `in_array($currentContext->getData(...), [...])` and `$localeKey === $submission->getData('locale')`) |
| AFFW-116 | ojs,omp,ops | `lib/pkp/templates/submission/review-editors.tpl` · edit button + metadata includes | Wizard review step · For-the-Editors panel · subjects/disciplines/agencies/coverage/rights/source/type/fundingStatement (each guarded by its `METADATA_REQUEST\|METADATA_REQUIRE` setting) |
| AFFW-117 | ojs,omp,ops | `lib/pkp/templates/submission/review-editors.tpl` · categories block | Wizard review step · categories list (guard: `{if $isCategoriesEnabled}`), else `common.noneSelected` |
| AFFW-118 | ojs,omp,ops | `lib/pkp/templates/submission/review-editors.tpl` · `submission.submit.coverNote` block | Wizard review step · comments for the editors (guard: `v-if="submission.commentsForTheEditors"` else `common.none`) |
| AFFW-119 | ojs,omp,ops | `lib/pkp/templates/submission/review-files.tpl` · edit button + `components.submissionFiles.items` list | Wizard review step · Files panel · file links with genre badge, error notifications from `errors.files` |
| AFFW-120 | ojs,omp,ops | `lib/pkp/templates/submission/review-contributors.tpl` · edit button + `publication.authors` list | Wizard review step · Contributors panel · principal-contact and role badges; empty warning `submission.wizard.noContributors` (guard: `v-if="!publication.authors.length"`) |
| AFFW-121 | ojs,omp,ops | `lib/pkp/templates/submission/review-reviewer-suggestions.tpl` · edit button + `submission.reviewerSuggestions` list | Wizard review step · Reviewer suggestions panel · empty warning `submission.wizard.noReviewerSuggestions` (guard: `v-if="!submission.reviewerSuggestions?.length ?? 0"`) |
| AFFW-122 | ojs,omp,ops | `lib/pkp/templates/submission/review-publication-field.tpl` · `$prop/$inLocale/$name/$type/$dataField` | Wizard review step · reusable publication-field renderer with per-field error notifications and `common.noneProvided` fallback |
| AFFW-123 | omp | `omp-main/templates/submission/chapters.tpl` · `{load_url_in_div id="chaptersGridContainer"}` | Wizard (OMP) · chapters step · legacy `ChapterGridHandler` grid |
| AFFW-124 | omp | `omp-main/templates/submission/review-chapters.tpl` · edit button `#review-chapters` | Wizard review step (OMP) · Chapters panel listing `chapters` with authors |
| AFFW-125 | ops | `ops-main/templates/submission/galleys.tpl` · `{load_url_in_div id="galleysGridUrl"}` | Wizard (OPS) · galleys step · legacy `PreprintGalleyGridHandler` grid |
| AFFW-126 | ops | `ops-main/templates/submission/review-galleys.tpl` · edit button + `galleys` list | Wizard review step (OPS) · Galleys panel · errors from `errors.files`, empty warning `author.submit.noFiles` (guard: `v-if="!errors.files && !galleys.length"`) |
| AFFW-127 | ops | `ops-main/templates/submission/review-license.tpl` · edit button `#review-license` | Wizard review step (OPS) · License panel · link to `publication.licenseUrl` else `submission.licenseUrl.missing` |
| AFFW-128 | ops | `ops-main/templates/submission/review-relation.tpl` · edit button `#review-relation` | Wizard review step (OPS) · Relation panel · published/unknown/none branches on `publication.relationStatus` |

## Submission wizard outcome pages

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-129 | ojs,omp | `lib/pkp/templates/submission/complete.tpl` · `{block name="page"}` links list | Submission Complete · heading `submission.submit.submissionComplete` + links `whatNext.review` ($workflowUrl), `whatNext.create` (page `submission`), `whatNext.return` (page `submissions`) |
| AFFW-130 | ops | `ops-main/templates/submission/complete.tpl` · `{if !$canAuthorPublish}` branch | Submission Complete (OPS) · `submission.submit.complete.canNotPost` + 3 links, else `submission.submit.complete.canPost` with `workflowUrl` |
| AFFW-131 | ojs,omp,ops | `lib/pkp/templates/submission/saved.tpl` · `<a href="{$submissionWizardUrl}">` | Saved for Later · heading `submission.wizard.saved`, resume link with author string + title, email confirmation note |
| AFFW-132 | ojs,omp,ops | `lib/pkp/templates/submission/cancelled.tpl` · links list | Submission Cancelled · heading `submission.wizard.submissionCancelled` + links `whatNext.create`, `whatNext.return` |

## Wizard list panel — Submission files

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-133 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListPanel.vue` · `ref="addFileButton" @click="openFileBrowser"` | Files panel · header "Add file" button (`addFileLabel`) opening the browser dialog |
| AFFW-134 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListPanel.vue` · `#itemsEmpty` `-linkButton @click="openFileBrowser"` | Files panel · empty-state upload link (`emptyLabel` + `emptyAddLabel`) |
| AFFW-135 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListPanel.vue` · `<FileUploader :id="id + '-uploader'" @updated:files="setFiles">` | Files panel · Dropzone uploader bound to `apiUrl` with `{fileStage}` query and `uploadProgressLabel` |
| AFFW-136 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListPanel.vue` · `edit(item)` → `SubmissionFilesEditModal` | Files panel · opens edit side modal (`common.editItem`) with form action `<action>/<id>?stageId=` |
| AFFW-137 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListPanel.vue` · `remove(item)` dialog | Files panel · remove confirm (`common.remove` / `removeConfirmLabel`, `common.yes`/`common.no`, `modalStyle: 'negative'`), DELETEs `<apiUrl>/<id>?stageId=` |
| AFFW-138 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListPanel.vue` · `cancelUpload(id)` / `formSuccess` / `updateItem` / `setForm` | Files panel · upload cancel + post-save item refresh and focus return |
| AFFW-139 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListItem.vue` · `@click="$emit('edit', item)"` `common.edit` | Files item · Edit button (guard: `v-if="item.fileId"` branch) |
| AFFW-140 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListItem.vue` · `@click="$emit('remove', item)"` `common.remove` | Files item · Remove button, warnable |
| AFFW-141 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListItem.vue` · `Badge` `localize(item.genreName)` | Files item · genre badge (guard: `v-if="item.genreId"`, `:is-primary="isPrimaryGenre"`) |
| AFFW-142 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListItem.vue` · `setGenre(genre.id)` per `primaryGenres` | Files item · quick genre-type buttons + `otherLabel` button opening edit (guard: `v-if="!item.genreId"`), PUTs `genreId` |
| AFFW-143 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesListItem.vue` · `<FileUploadProgress @cancel="$emit('cancel', item.id)">` | Files item · in-progress upload row with cancel (guard: `v-else` of `item.fileId`) |
| AFFW-144 | ojs,omp,ops | `components/ListPanel/submissionFiles/SubmissionFilesEditModal.vue` · `SideModalBody` + `PkpForm` | Files edit modal · emits `setForm` / `formSuccess` |
| AFFW-145 | ojs,omp,ops | `components/ListPanel/submissionFiles/SelectSubmissionFileListItem.vue` · slot selector + download `PkpButton element="a"` | Selectable file row (decision promote-files, file attachers) · checkbox slot, genre badge, download link (guard: `v-if="url"`) |

## Wizard list panel — Contributors

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-146 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `@click="toggleOrdering"` icon `Sort` | Contributors panel · Order button (guard: `v-if="canEditPublication"`, `:disabled="isLoading"`, `:is-active="isOrdering"`) |
| AFFW-147 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `@click="cancelOrdering"` `common.cancel` | Contributors panel · cancel ordering (guard: `v-if="isOrdering"`) |
| AFFW-148 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `@click="openPreviewModal"` `contributor.listPanel.preview` | Contributors panel · Preview button (guard: `v-if="!isOrdering"`) |
| AFFW-149 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `@click="openAddModal"` `grid.action.addContributor` | Contributors panel · Add contributor (guard: `v-if="!isOrdering && canEditPublication"`) |
| AFFW-150 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `<Orderer @up="contributorItemOrderUp" @down="contributorItemOrderDown">` | Contributors item · up/down reorder controls (guard: `v-if="isOrdering"` inside `#item-actions`, itself `v-if="canEditPublication"`) |
| AFFW-151 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `@click="setPrimaryContact(item.id)"` `author.users.contributor.setPrincipalContact` | Contributors item · set principal contact (guard: `v-else` of `publication.primaryContactId == item.id`, which renders the principal-contact `Badge`) |
| AFFW-152 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `@click="openEditModal(item.id)"` `common.edit` | Contributors item · Edit button |
| AFFW-153 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `@click="openDeleteModal(item.id)"` `common.delete` | Contributors item · Delete button, warnable |
| AFFW-154 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsListPanel.vue` · `contributorsApiUrl` / `publicationApiUrl` / `setItemOrderSequence` / `getAndUpdatePublication` / `formSuccess` / `updateForm` | Contributors panel · API plumbing for add/edit/delete/order/primary-contact |
| AFFW-155 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsEditModal.vue` · `SideModalBody` + `PkpForm` | Contributors add/edit side modal · emits `updateForm` / `formSuccess` |
| AFFW-156 | ojs,omp,ops | `components/ListPanel/contributors/ContributorsPreviewModal.vue` · `PkpTable` rows | Contributors preview modal · abbreviated / publication-lists / full author strings (`authorsStringShort`, `authorsStringIncludeInBrowse`, `authorsString`) |

## Wizard list panel — Reviewer suggestions

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-157 | ojs,omp,ops | `components/ListPanel/reviewerSuggestions/ReviewerSuggestionsListPanel.vue` · `@click="openAddModal"` `grid.action.addReviewerSuggestion` | Reviewer suggestions panel · Add button (guard: `v-if="publication.status !== getConstant('STATUS_PUBLISHED') && canEditPublication"`) |
| AFFW-158 | ojs,omp,ops | `components/ListPanel/reviewerSuggestions/ReviewerSuggestionsListPanel.vue` · `@click="openEditModal(item.id)"` `common.edit` | Reviewer suggestions item · Edit (guard: same `#item-actions` `v-if` as above) |
| AFFW-159 | ojs,omp,ops | `components/ListPanel/reviewerSuggestions/ReviewerSuggestionsListPanel.vue` · `@click="openDeleteModal(item.id)"` `common.delete` | Reviewer suggestions item · Delete, warnable |
| AFFW-160 | ojs,omp,ops | `components/ListPanel/reviewerSuggestions/ReviewerSuggestionsEditModal.vue` · `SideModalBody` + `PkpForm` | Reviewer suggestion add/edit side modal · emits `updateForm` / `formSuccess` |
---

**Part 2b — Decision wizard · composer · file attacher · reviewer selection**

## Editorial decision wizard

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-161 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · `{block name="page"}` `h1` | Decision wizard · heading · `$decisionType->getLabel()` + `currentStep.name` (guard: `v-if="steps.length > 1"`) and `app__pageDescription` |
| AFFW-162 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · error `<notification>` + `<button @click="openStep(stepId)">` `common.viewError` | Decision wizard · per-step error banners with jump-to-step link (loop `v-for="(error, stepId) in errors"`) |
| AFFW-163 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · `<steps @step:open="openStep">` | Decision wizard · step navigator `editor.decision.completeSteps` / `common.showAllSteps` (guard: `v-if="steps.length"`) |
| AFFW-164 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · `<pkp-form v-bind="step.form" @set="updateStep">` | Decision wizard · form step (guard: `v-if="step.type === 'form'"`) |
| AFFW-165 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · skipped-email notice + `<button @click="toggleSkippedStep(step.id)">` `editor.decision.dontSkipEmail` | Decision wizard · un-skip email link (guard: `v-if="skippedSteps.includes(step.id)"` within `step.type === 'email'`) |
| AFFW-166 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · `<composer ... @set="updateStep">` | Decision wizard · email step composer with all label props (attach-files, add CC/BCC, recipients, subject, body, templates, locales) (guard: `v-else` of skipped) |
| AFFW-167 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · `<list-panel v-for="(list, i) in step.lists">` + `<select-submission-file-list-item>` + `<input type="checkbox" v-model="step.selected">` | Decision wizard · promote-files step · per-list file checkboxes (guard: `v-else-if="step.type === 'promoteFiles'"`) |
| AFFW-168 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · `.decision__skipStep` `@click="toggleSkippedStep(currentStep.id)"` `editor.decision.skipEmail` | Decision footer · Skip email link (guard: `v-if="currentStep.type === 'email' && currentStep.canSkip && !skippedSteps.includes(currentStep.id)"`, `:disabled="isSubmitting"`) |
| AFFW-169 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · footer `<spinner v-if="isSubmitting">` | Decision footer · submitting spinner |
| AFFW-170 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · footer `pkp-button :is-warnable @click="cancel"` `common.cancel` | Decision footer · Cancel button (guard: `:disabled="isSubmitting"`) |
| AFFW-171 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · footer `pkp-button @click="previousStep"` `help.previous` | Decision footer · Previous button (guard: `v-if="!isOnFirstStep && steps.length > 1"`) |
| AFFW-172 | ojs,omp,ops | `lib/pkp/templates/decision/record.tpl` · footer `pkp-button @click="nextStep"` | Decision footer · Continue / Record Decision button · `editor.decision.recordDecision` when `isOnLastStep` else `common.continue` |
| AFFW-173 | ojs,omp,ops | `components/Container/DecisionPage.vue` · `stepTypes = {email, form, promoteFiles}` | Decision page · step type constants |
| AFFW-174 | ojs,omp,ops | `components/Container/DecisionPage.vue` · `created()` email step init | Decision wizard · seeds each email step with `attachments/subject/body/cc/bcc/recipients` from `recipientOptions` |
| AFFW-175 | ojs,omp,ops | `components/Container/DecisionPage.vue` · `cancel()` dialog | Decision wizard · abandon-decision confirm (`abandonDecisionLabel` / `cancelConfirmationPrompt` / `keepWorkingLabel`, `modalStyle: 'negative'`), returns to `returnUrlToSubmissionSummary` or `submissionUrl` |
| AFFW-176 | ojs,omp,ops | `components/Container/DecisionPage.vue` · `returnUrlToSubmissionSummary` | Decision wizard · reads `?ret=` query param to decide return target |
| AFFW-177 | ojs,omp,ops | `components/Container/DecisionPage.vue` · `submit()` | Decision wizard · POSTs `{submissionApiUrl}/decisions` with per-step `actions`, `reviewRoundId`, then copies promoted files |
| AFFW-178 | ojs,omp,ops | `components/Container/DecisionPage.vue` · `copyFile(fileId, toFileStage, callback)` | Decision wizard · PUT `{submissionApiUrl}/files/<id>/copy?stageId=` for each selected promote-file |
| AFFW-179 | ojs,omp,ops | `components/Container/DecisionPage.vue` · `openCompletedDialog()` | Decision wizard · completion dialog · actions `viewSubmissionLabel` + `viewAllSubmissionsLabel`, or `viewSubmissionSummaryLabel` when returning to summary, `modalStyle: 'success'` |
| AFFW-180 | ojs,omp,ops | `components/Container/DecisionPage.vue` · `toggleSkippedStep` / `openStep` / `nextStep` / `previousStep` / `setStepErrors` / `updateStep` | Decision wizard · step skip toggle, navigation, error mapping and per-step data updates |

## Email composer (decisions / discussions)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-181 | ojs,omp,ops | `components/Composer/Composer.vue` · `.composer__templates` block | Composer · email-template picker (guard: `v-if="emailTemplates.length \|\| emailTemplatesApiUrl"`), heading `loadTemplateLabel` |
| AFFW-182 | ojs,omp,ops | `components/Composer/Composer.vue` · `<Search :search-label="findTemplateLabel">` | Composer · template search (guard: `v-if="emailTemplatesApiUrl"`) |
| AFFW-183 | ojs,omp,ops | `components/Composer/Composer.vue` · `.composer__template @click="loadTemplate(emailTemplate.key)"` | Composer · template item button (two loops: `emailTemplates` when `!searchPhrase`, `limitedSearchResults` otherwise) |
| AFFW-184 | ojs,omp,ops | `components/Composer/Composer.vue` · `.composer__templates__moreSearchResults @click="showSearchResultCount = searchResults.length"` | Composer · "show N more" results button (guard: `v-if="searchResults.length > showSearchResultCount"`) |
| AFFW-185 | ojs,omp,ops | `components/Composer/Composer.vue` · `.composer__templates__searching` | Composer · searching spinner `searchingLabel` (guard: `v-if="isSearching"`) |
| AFFW-186 | ojs,omp,ops | `components/Composer/Composer.vue` · `.composer__locales` `@click="openSwitchLocale(otherLocale.locale)"` | Composer · locale switch links `switchToLabel` (guard: `v-if="otherLocales.length"` + `v-if="locale !== otherLocale.locale"`), confirm via `confirmSwitchLocaleLabel` |
| AFFW-187 | ojs,omp,ops | `components/Composer/Composer.vue` · `FieldAutosuggestPreset name="to"` `@change="changeRecipients"` | Composer · recipients field (guard: `:is-disabled="!canChangeRecipients"`) |
| AFFW-188 | ojs,omp,ops | `components/Composer/Composer.vue` · `#end` slot `PkpButton @click="enableCC"` `addCCLabel` | Composer · Add CC/BCC button (guard: `v-if="!ccIsEnabled"`) |
| AFFW-189 | ojs,omp,ops | `components/Composer/Composer.vue` · `input#<id>-cc` / `input#<id>-bcc` with `FieldError` | Composer · CC and BCC inputs (guard: `v-if="ccIsEnabled"`) |
| AFFW-190 | ojs,omp,ops | `components/Composer/Composer.vue` · `input#<id>-subject` + `FieldError` | Composer · Subject input |
| AFFW-191 | ojs,omp,ops | `components/Composer/Composer.vue` · `FieldPreparedContent name="body"` | Composer · Body editor with `insertLabel` / `insertModalLabel` / `insertContentLabel` / `insertSearchLabel` prepared-content insertion, plugins `['link']` |
| AFFW-192 | ojs,omp,ops | `components/Composer/Composer.vue` · `toolbar()` adding `'\| pkpAttachFiles'` | Composer · attach-files toolbar button registration (guard: attachers present) |
| AFFW-193 | ojs,omp,ops | `components/Composer/Composer.vue` · `editor.ui.registry.addButton('pkpAttachFiles')` → `openSideModal(FileAttacherModal, {title: attachFilesLabel, onAddAttachments})` | Composer · opens the file attacher side modal from the editor toolbar |
| AFFW-194 | ojs,omp,ops | `components/Composer/Composer.vue` · `.composer__attachments` Badge list + `removeAttachment(i)` | Composer · attached-file chips with remove X (guard: `v-if="attachments.length"`), sr label `attachedFilesLabel` / `removeItemLabel` |
| AFFW-195 | ojs,omp,ops | `components/Composer/Composer.vue` · `FieldError` for `errors.attachments` / `.composer__loadingTemplateMask` | Composer · attachment errors and template-loading overlay (guard: `v-if="isLoadingTemplate"`) |
| AFFW-196 | ojs,omp,ops | `components/Composer/FileAttacherModal.vue` · `SideModalBody` + `FileAttacher @attached:files → emit('addAttachments')` | Composer · file attacher side modal wrapper |

## File attacher

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-197 | ojs,omp,ops | `components/FileAttacher/FileAttacher.vue` · `ActionPanel v-for="(attacher, key) in attachers"` + `PkpButton @click="setAttacher(attacher)"` | File attacher · source chooser · one panel per attacher with `attacher.label` / `.description` / `.button` |
| AFFW-198 | ojs,omp,ops | `components/FileAttacher/FileAttacher.vue` · `setAttacher` / `attachFiles` / `cancel` | File attacher · opens `AttacherModal`, emits `attached:files`, closes modal |
| AFFW-199 | ojs,omp,ops | `components/FileAttacher/AttacherModal.vue` · `Components = {FileAttacherFileStage, FileAttacherLibrary, FileAttacherReviewFiles, FileAttacherUpload, FileAttacherWorkflowStage}` | File attacher modal · renders the chosen source component, wires `selected:files` / `cancel` |
| AFFW-200 | ojs,omp,ops | `components/FileAttacher/FileAttacherFileStage.vue` · `Dropdown label="Other Files"` + `@click="currentFileStage = fileStage"` | Attach from file stage · stage switcher dropdown (guard: `v-if="fileStages.length > 1"`) |
| AFFW-201 | ojs,omp,ops | `components/FileAttacher/FileAttacherFileStage.vue` · `SelectSubmissionFileListItem` + `<input type="checkbox" v-model="selected">` | Attach from file stage · per-file checkbox list |
| AFFW-202 | ojs,omp,ops | `components/FileAttacher/FileAttacherFileStage.vue` · footer `@click="$emit('cancel')"` `backLabel` / `@click="$emit('selected:files', selectedFiles)"` `attachSelectedLabel` | Attach from file stage · Back link + Attach selected (guard: `:is-disabled="!selected.length"`) |
| AFFW-203 | ojs,omp,ops | `components/FileAttacher/FileAttacherLibrary.vue` · file checkbox list + Back / `attachSelectedLabel` footer | Attach from library · loading/`common.noItemsFound` states (guard: `v-if="!files.length"`), `libraryApiUrl` |
| AFFW-204 | ojs,omp,ops | `components/FileAttacher/FileAttacherReviewFiles.vue` · file checkbox list + Back / `attachSelectedLabel` footer | Attach review files · items labelled `reviewerName — name`, `common.noItemsFound` when empty |
| AFFW-205 | ojs,omp,ops | `components/FileAttacher/FileAttacherUpload.vue` · `.fileAttacherUpload__prompt` `-linkButton @click="selectFile"` | Attach by upload · drag-and-drop prompt link (guard: `v-if="!files.length"`) |
| AFFW-206 | ojs,omp,ops | `components/FileAttacher/FileAttacherUpload.vue` · `FileUploadProgress @cancel="removeFile(i)"` / uploaded-file `PkpButton common.remove` | Attach by upload · per-file progress cancel and remove (guard: `v-if="'progress' in file"` / `v-else`) |
| AFFW-207 | ojs,omp,ops | `components/FileAttacher/FileAttacherUpload.vue` · footer Back link + `@click="selectFile"` `addFilesLabel` + `@click="$emit('selected:files', files)"` `attachFilesLabel` | Attach by upload · Add files and Attach files buttons (guard: `:is-disabled="!files.length \|\| isUploading"`) |
| AFFW-208 | ojs,omp,ops | `components/FileAttacher/FileAttacherWorkflowStage.vue` · `FieldSelect @change="onStageChange"` `editor.selectSubmissionFilesStage` | Attach from workflow stage · stage select |
| AFFW-209 | ojs,omp,ops | `components/FileAttacher/FileAttacherWorkflowStage.vue` · `FileManager v-for="fileManager in fileManagerUploadNamespaces[selectedStage]"` `v-model:selected-files` | Attach from workflow stage · per-namespace file managers (guard: `v-if="selectedStage && fileManagerUploadNamespaces[selectedStage]"`) |
| AFFW-210 | ojs,omp,ops | `components/FileAttacher/FileAttacherWorkflowStage.vue` · footer Back link + `attachSelectedLabel` | Attach from workflow stage · Back / Attach selected (guard: `:is-disabled="!selectedFiles?.length"`) |
| AFFW-211 | ojs,omp,ops | `components/FileAttacher/useFileAttacherWorkflowStage.js` · `selectedStage` / `fileManagerUploadNamespaces` / `options` / `onStageChange` | Attach from workflow stage · stage options and namespace resolution |
| AFFW-212 | ojs,omp,ops | `components/FileAttacher/FileAttacherAttachedFiles.vue` · `PkpButton @click="emit('remove-file', file.id)"` `common.remove` | Attached files list · per-file remove link |

## Advanced reviewer search / select reviewer

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-213 | ojs,omp,ops | `components/Container/AdvancedSearchReviewerContainer.vue` · `.pkpAdvancedSearchReviewerContainer` author list | Select Reviewer · submission author list (`labels.submissionAuthorList`) showing author — affiliations Claimed by: reviewer-assignment-and-management. |
| AFFW-214 | ojs,omp,ops | `components/Container/AdvancedSearchReviewerContainer.vue` · `.show_authors_action @click="toggleShowAllAuthors"` | Select Reviewer · show all / show less authors toggle (guard: `v-if="authorCount > 4"`, `labels.showAll`/`labels.showLess`) Claimed by: reviewer-assignment-and-management. |
| AFFW-215 | ojs,omp,ops | `components/Container/AdvancedSearchReviewerContainer.vue` · `<SelectReviewerListPanel v-bind="components.selectReviewer" @set="set">` | Select Reviewer · reviewer list panel host Claimed by: reviewer-assignment-and-management. |
| AFFW-216 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListPanel.vue` · suggestions `ListPanel` `reviewer-sugestions-list` | Select Reviewer · reviewer-suggestions list (guard: `v-if="suggestions.length > 0"`, item guard `v-if="!item.approvedAt"`) |
| AFFW-217 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListPanel.vue` · `<Search @search-phrase-changed="setSearchPhrase">` | Select Reviewer · reviewer name search Claimed by: reviewer-assignment-and-management. |
| AFFW-218 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListPanel.vue` · `PkpButton @click="isSidebarVisible = !isSidebarVisible"` `common.filter` | Select Reviewer · filter sidebar toggle (icon `Filter`, `:is-active="isSidebarVisible"`) Claimed by: reviewer-assignment-and-management. |
| AFFW-219 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListPanel.vue` · `#sidebar` `<component :is="filter.filterType \|\| 'filter-slider'" v-for="filter in filters">` | Select Reviewer · filter sidebar controls emitting `add-filter` / `update-filter` / `remove-filter` Claimed by: reviewer-assignment-and-management. |
| AFFW-220 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListPanel.vue` · `#footer` `<Pagination @set-page="setPage">` | Select Reviewer · pagination (guard: `v-if="lastPage > 1"`) Claimed by: reviewer-assignment-and-management. |
| AFFW-221 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListItem.vue` · `PkpButton @click="select"` | Select Reviewer item · Select/Reassign button · `reassignLabel` when `assignedToLastRound` else `selectReviewerLabel` (guard: `v-if="canSelect"`) Claimed by: reviewer-assignment-and-management. |
| AFFW-222 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListItem.vue` · `<Expander @toggle="isExpanded = !isExpanded">` | Select Reviewer item · expand/collapse reviewer details (`listPanel__itemExpanded--reviewer`) Claimed by: reviewer-assignment-and-management. |
| AFFW-223 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListItem.vue` · `.listPanel__item--reviewer__noticeAction @click.prevent="unlockAssignment"` | Select Reviewer item · unlock warned assignment `warnOnAssignmentUnlockLabel` (guard: `v-if="warnOnAssignment && !isWarningBypassed && !currentlyAssigned"`) Claimed by: reviewer-assignment-and-management. |
| AFFW-224 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerListItem.vue` · notices `currentlyAssignedLabel` / `assignedToLastRoundLabel` / rating stars / `reviewerSameInstitutionLabel` | Select Reviewer item · status badges and brief stats (guards: `v-if="currentlyAssigned"`, `v-else-if="assignedToLastRound"`, `v-if="item.reviewerRating !== null && canSelect"`, `v-if="affiliationMatch(item.affiliation)"`) Claimed by: reviewer-assignment-and-management. |
| AFFW-225 | ojs,omp,ops | `components/ListPanel/users/SelectReviewerSuggestionListItem.vue` · `PkpButton @click="select"` `selectReviewerLabel` | Select Reviewer suggestion item · select button (guard: `v-if="canSelect"`), shows `suggestionReason` and `currentlyAssignedLabel` notice |
---

**Part 3 — Workflow page (Vue: WorkflowPage + stage/publication configs)**

## Workflow modal shell / header

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-226 | ojs,omp,ops | `src/pages/workflow/WorkflowPage.vue` → `#pre-title` `workflowStore.submissionId` + `Spinner` | Workflow header · submission id + refresh spinner · shows id and `common.refreshingData` spinner (guard: `progressStore.screensInProgress.includes('modal_1')` toggles visibility) |
| AFFW-227 | ojs,omp,ops | `src/pages/workflow/WorkflowPage.vue` → `#title` `selectedPublication.authorsStringShort` | Workflow header · title (authors / fallback `workflowStore.props.title`) (guard: `v-if="selectedPublication"`) |
| AFFW-228 | ojs,omp,ops | `src/pages/workflow/WorkflowPage.vue` → `#description` `localizeSubmission(selectedPublication.fullTitle)` | Workflow header · publication full title (guard: `v-if="selectedPublication"`) |
| AFFW-229 | ojs,omp,ops | `src/pages/workflow/WorkflowPage.vue` → `#post-description` `StageBubble` `workflowStore.stageLabel` | Workflow header · stage bubble showing extended stage label (guard: `v-if="submission"`) |
| AFFW-230 | ojs,omp,ops | `src/pages/workflow/WorkflowPage.vue` → `#actions` loop over `workflowStore.headerItems` | Workflow header · action bar rendering all header items (guard: `v-if="submission"`) |
| AFFW-231 | ojs | `src/pages/workflow/composables/useWorkflowConfig/workflowConfigEditorialOJS.js` → `getHeaderItems` → `WorkflowPaymentDropdown` | Workflow header · Payments dropdown (guard: `publicationSettings.submissionPaymentsEnabled`; declared only in editorial OJS header config, not in OMP/OPS overrides) |
| AFFW-232 | ojs | `src/pages/workflow/components/header/WorkflowPaymentDropdown.vue` → `Dropdown label t('common.payments')` | Workflow header · Payments dropdown body · renders `_components/submissionPayment` form |
| AFFW-233 | ojs,omp,ops | `workflowConfigEditorialOJS.js` / `workflowConfigEditorialOMP.js` / `workflowConfigEditorialOPS.js` → `getHeaderItems` → `label: t('common.view')`, `WORKFLOW_VIEW_PUBLISHED_SUBMISSION` | Workflow header · View button → redirects to `submission.urlPublished` (guard: `submission.status === STATUS_PUBLISHED`) |
| AFFW-234 | ojs,omp,ops | same three `getHeaderItems` → `label: t('common.preview')`, `WORKFLOW_VIEW_PUBLISHED_SUBMISSION` | Workflow header · Preview button (guard: `status !== STATUS_PUBLISHED && (stageId === EDITING || stageId === PRODUCTION)`) |
| AFFW-235 | ojs,omp,ops | same three `getHeaderItems` → `label: t('editor.activityLog')`, `WORKFLOW_VIEW_ACTIVITY_LOG` | Workflow header · Activity log button → legacy `SubmissionInformationCenterHandler` modal (guard: `permissions.canAccessEditorialHistory`) |
| AFFW-236 | ojs,omp,ops | `workflowConfigEditorialOJS/OMP/OPS.js` + `workflowConfigAuthorOJS/OMP/OPS.js` → `label: t('editor.submissionLibrary')`, `WORKFLOW_VIEW_LIBRARY` | Workflow header · Submission library button → legacy `documentLibraryHandler` modal (no guard; only header item in the author configs) |
| AFFW-237 | omp | `src/pages/workflow/composables/useWorkflowConfig/workflowConfigEditorialOMP.js` → `getHeaderItems` → `component: 'WorkflowWorkTypeOMP'` | Workflow header · Work type dropdown (app-specific: declared only in OMP editorial header config; component registered only in `WorkflowPageOMP.vue`) |
| AFFW-238 | omp | `src/pages/workflow/components/header/WorkflowWorkTypeOMP.vue` → action `setAsEditedVolume` | Workflow header · Work type → "Edited Volume" · calls `workflowChangeWorktype({workType: WORK_TYPE_EDITED_VOLUME})` |
| AFFW-239 | omp | `src/pages/workflow/components/header/WorkflowWorkTypeOMP.vue` → action `setAsAuthoredWork` | Workflow header · Work type → "Publication" · calls `workflowChangeWorktype({workType: WORK_TYPE_AUTHORED_WORK})` |
| AFFW-240 | ojs,omp,ops | editorial header configs → `label: t('editor.submission.decision.returnToWorkflow')`, `WORKFLOW_DECISION_RETURN_TO_WORKFLOW` | Workflow header · Return to workflow button (guard: `isDecisionAvailable(submission, DECISION_RETURN_TO_WORKFLOW)`) |
| AFFW-241 | ojs,omp,ops | editorial header configs → `label: t('editor.submission.decision.returnToDone')`, `WORKFLOW_DECISION_RETURN_TO_DONE` | Workflow header · Return to done button (guard: `isDecisionAvailable(submission, DECISION_RETURN_TO_DONE)`) |
| AFFW-242 | ojs,omp,ops | `src/pages/workflow/components/action/WorkflowActionButton.vue` | Workflow header/actions · generic button component · props `label,isPrimary,isSecondary,isWarnable,action,actionArgs`; click calls `store[action](actionArgs)` |
| AFFW-243 | ops | `src/pages/workflow/components/action/WorkflowActionChangeDecision.vue` → `t('editor.submission.workflowDecision.changeDecision')` | Workflow actions · "Change decision" link that reveals wrapped action items (guard: `!isActionsShowed && showChangeDecision`; component registered only in `WorkflowPageOPS.vue`) |

## Workflow side menu / navigation

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-244 | ojs,omp,ops | `src/pages/workflow/WorkflowPage.vue` → `SideMenu v-bind="workflowStore.sideMenuProps"` (`width-variant="compact"`) | Workflow side menu · navigation tree; selection synced to `workflowMenuKey` query param via `useWorkflowMenu` |
| AFFW-245 | ojs,omp | `useWorkflowNavigationConfigOJS.js` / `useWorkflowNavigationConfigOMP.js` → `getMenuItems` key `workflow`, `t('manager.workflow')`, icon `Dashboard` | Workflow side menu · Workflow group |
| AFFW-246 | ops | `useWorkflowNavigationConfigOPS.js` → `getMenuItems` key `workflow` | Workflow side menu · Workflow group (guard: `dashboardPage === EDITORIAL_DASHBOARD`) |
| AFFW-247 | ojs,omp | `useWorkflowNavigationConfigOJS.js`/`OMP.js` → `getWorkflowItems` → `getWorkflowItem` stage `WORKFLOW_STAGE_ID_SUBMISSION`, `t('manager.publication.submissionStage')` | Workflow side menu · Submission stage item (colorStripe when `isActive`) |
| AFFW-248 | omp | `useWorkflowNavigationConfigOMP.js` → `getWorkflowItem` stage `WORKFLOW_STAGE_ID_INTERNAL_REVIEW`, `t('workflow.review.internalReview')` | Workflow side menu · Internal Review stage item (app-specific: only OMP nav config) |
| AFFW-249 | ojs | `useWorkflowNavigationConfigOJS.js` → `getWorkflowItem` stage `WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`, `t('manager.publication.reviewStage')` | Workflow side menu · Review stage item |
| AFFW-250 | omp | `useWorkflowNavigationConfigOMP.js` → `getWorkflowItem` stage `WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`, `t('workflow.review.externalReview')` | Workflow side menu · External Review stage item (OMP label variant) |
| AFFW-251 | ojs,omp | `useWorkflowNavigationConfigOJS.js` → `getReviewItems`/`getReviewItem` → `t('workflow.reviewRoundN')` | Workflow side menu · Review round N sub-item per review round (guard: `isActive` when `activeStage.id === stageId && activeReviewRound.id === reviewRound.id`) |
| AFFW-252 | ojs,omp | `useWorkflowNavigationConfigOJS.js`/`OMP.js` → `getWorkflowItem` stage `WORKFLOW_STAGE_ID_EDITING`, `t('submission.copyediting')` | Workflow side menu · Copyediting stage item |
| AFFW-253 | ojs,omp,ops | `useWorkflowNavigationConfigOJS.js`/`OMP.js`/`OPS.js` → `getWorkflowItem` stage `WORKFLOW_STAGE_ID_PRODUCTION`, `t('manager.publication.productionStage')` | Workflow side menu · Production stage item (only stage item in OPS nav) |
| AFFW-254 | ojs,omp,ops | nav configs → `getMenuItems` key `publication`, `t('submission.publication')`, icon `MySubmissions` | Workflow side menu · Publication group (guard: `dashboardPage ∈ [EDITORIAL_DASHBOARD, MY_SUBMISSIONS]`) |
| AFFW-255 | ojs,omp,ops | nav configs → `getPublicationVersionItems` → `key: publication_${publication.id}`, label `publication.versionString` | Workflow side menu · per-version node (guard: `EDITORIAL_DASHBOARD && permissions.canAccessPublication` → editorial items; `MY_SUBMISSIONS` → author items) |
| AFFW-256 | ojs,omp,ops | nav configs → `getPublicationVersionItems` → `key: 'publication_create_new_version'`, `t('publication.createVersion')`, `action: 'createNewVersion'` | Workflow side menu · Create New Version item → `workflowStore.workflowCreateNewVersion()` (guard: `permissions.canPublish`) |
| AFFW-257 | omp | `useWorkflowNavigationConfigOMP.js` → `getMenuItems` key `marketing`, `t('settings.libraryFiles.category.marketing')` | Workflow side menu · Marketing group (guard: `dashboardPage === EDITORIAL_DASHBOARD`; app-specific: OMP nav config only) |
| AFFW-258 | omp | `useWorkflowNavigationConfigOMP.js` → `getMarketingItem` `audience` / `representatives` / `publicationDates` | Workflow side menu · Marketing sub-items: Audience, Representatives, Publication Dates |
| AFFW-259 | ojs,omp,ops | `useWorkflowMenu.js` → `navigateToMenu` / `getInitialSelectionItemKey` | Workflow side menu · initial selection & deep-link behaviour (uses `queryParamsUrl.workflowMenuKey` if present) |

## Workflow side menu — publication tab items

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-260 | ojs,omp,ops | nav configs → `getPublicationItem name:'titleAbstract'`, `t('publication.titleAbstract')` | Workflow side menu · Title & Abstract tab (author + editorial) |
| AFFW-261 | ojs,omp,ops | nav configs → `name:'contributors'`, `t('publication.contributors')` | Workflow side menu · Contributors tab |
| AFFW-262 | ojs | `useWorkflowNavigationConfigOJS.js` → `name:'metadata'`, `t('article.metadata')` | Workflow side menu · Metadata tab (OJS label) |
| AFFW-263 | omp | `useWorkflowNavigationConfigOMP.js` → `name:'metadata'`, `t('submission.metadata')` | Workflow side menu · Metadata tab (OMP label) |
| AFFW-264 | ops | `useWorkflowNavigationConfigOPS.js` → `name:'metadata'`, `t('submission.informationCenter.metadata')` | Workflow side menu · Metadata tab (OPS label) |
| AFFW-265 | ojs,omp,ops | nav configs → `name:'citations'`, `t('submission.citations')` | Workflow side menu · Citations tab (guard: `publicationSettings.supportsCitations`) |
| AFFW-266 | ojs,omp,ops | nav configs → `name:'dataAvailabilityAndCitation'`, `t('submission.dataAvailabilityAndCitation.data')` | Workflow side menu · Data availability & citation tab (guard: `supportsDataCitations \|\| supportsDataAvailability`) |
| AFFW-267 | ojs,omp,ops | nav configs → `name:'funding'`, `t('submission.funding')` | Workflow side menu · Funding tab (guard: `publicationSettings.supportsFunders`) |
| AFFW-268 | ojs,omp,ops | nav configs → `name:'identifiers'`, `t('submission.identifiers')` | Workflow side menu · Identifiers tab (guard: `publicationSettings.identifiersEnabled`; editorial item lists only) |
| AFFW-269 | ojs | `useWorkflowNavigationConfigOJS.js` → `getPublicationItemsEditorial` → `name:'jats'`, `t('publication.jats')` | Workflow side menu · JATS tab (editorial; declared only in OJS nav config) |
| AFFW-270 | ojs | `useWorkflowNavigationConfigOJS.js` → `name:'bodyText'`, `t('publication.bodyText')` | Workflow side menu · Body Text tab (guard: `permissions.canAccessProduction`; OJS nav only) |
| AFFW-271 | ojs,ops | `useWorkflowNavigationConfigOJS.js`/`OPS.js` → `name:'galleys'`, `t('submission.layout.galleys')` | Workflow side menu · Galleys tab (editorial guard: `permissions.canAccessProduction`; unguarded in OJS/OPS author lists) |
| AFFW-272 | ojs,omp,ops | nav configs → `name:'media'`, `t('publication.media')` | Workflow side menu · Media tab (editorial OJS/OPS guard: `permissions.canAccessProduction`) |
| AFFW-273 | ojs,omp,ops | nav configs → `name:'license'`, `t('publication.publicationLicense')` | Workflow side menu · License/Permissions tab (guard: `permissions.canAccessProduction`; editorial only) |
| AFFW-274 | ojs | `useWorkflowNavigationConfigOJS.js` → `name:'issue'`, `t('publication.publicationSettings')` | Workflow side menu · Issue / publication settings tab (guard: `permissions.canAccessProduction`; OJS nav only) |
| AFFW-275 | omp | `useWorkflowNavigationConfigOMP.js` → `name:'chapters'`, `t('submission.chapters')` | Workflow side menu · Chapters tab (OMP nav only, author + editorial) |
| AFFW-276 | omp | `useWorkflowNavigationConfigOMP.js` → `name:'publicationFormats'`, `t('submission.publicationFormats')` | Workflow side menu · Publication Formats tab (OMP nav only) |
| AFFW-277 | omp | `useWorkflowNavigationConfigOMP.js` → `name:'catalogEntry'`, `t('publication.catalogEntry')` | Workflow side menu · Catalog Entry tab (guard: `permissions.canAccessProduction`; OMP nav only) |
| AFFW-278 | ops | `useWorkflowNavigationConfigOPS.js` → `name:'preprintEntry'`, `t('preprint.entry')` | Workflow side menu · Preprint Entry tab (guard: `permissions.canAccessProduction`; OPS nav only) |
| AFFW-279 | ops | `useWorkflowNavigationConfigOPS.js` → `getPublicationItemsAuthor` → `name:'discussions'`, `t('submission.queries.production')` | Workflow side menu · Discussions tab (OPS author list only) |

## Workflow stage screens — shared "common" section

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-280 | ojs,omp,ops | `workflowConfigEditorialOJS.js` / `workflowConfigAuthorOJS.js` → `WorkflowConfig.common.getPrimaryItems` → `WorkflowPrimaryBasicMetadata` `t('user.authorization.accessibleWorkflowStage')` | Any stage screen · no-access notice, suppresses all other items (guard: `!permissions.accessibleStages.includes(selectedStageId)` → `shouldContinue: false`) |
| AFFW-281 | ojs,omp,ops | `WorkflowConfig.common.getPrimaryItems` → `WorkflowChangeSubmissionLanguage` with `canChangeSubmissionLanguage: false` | Any stage screen · current submission language readout (button suppressed on stage screens) |
| AFFW-282 | ojs,omp,ops | `WorkflowConfig.common.getPrimaryItems` → `WorkflowSubmissionStatus` | Any stage screen · status panel (round status, min-reviews messaging, "stage not started", future-stage messaging) |
| AFFW-283 | ojs,omp,ops | `src/pages/workflow/components/primary/WorkflowSubmissionStatus.vue` → `message` computed | Any stage screen · status text variants: `workflow.stageNotStarted`, `workflow.submissionInFutureStage`, `workflow.submissionNextReviewRoundInFutureStage`, `workflow.submissionInNextReviewRound`, `notification.type.roundStatusTitle`, `dashboard.minimumConfirmedReviewsRequired`, `dashboard.minimumReviewsConfirmedDecisionNeeded`, `editor.submission.workflowDecision.submission.published` |
| AFFW-284 | ojs,omp,ops | `WorkflowConfig.common.getSecondaryItems` / `getActionItems` returning `shouldContinue: false` | Any stage screen · secondary + action columns hidden (guard: `!permissions.accessibleStages.includes(selectedStageId)`) |
| AFFW-285 | ojs,omp,ops | `src/pages/workflow/components/publication/WorkflowChangeSubmissionLanguage.vue` → `t('submission.list.changeSubmissionLanguage.buttonLabel')` | Any workflow screen · Change-language link → `store.workflowChangeSubmissionLanguage()` (guard: `v-if="canChangeSubmissionLanguage"`) |

## Submission stage

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-286 | ojs,omp,ops | `workflowConfigEditorialOJS.js` → `WorkflowConfig[WORKFLOW_STAGE_ID_SUBMISSION].getPrimaryItems` → `FileManager` namespace `SUBMISSION_FILES` | Submission stage (editorial) · Submission files panel Claimed by: submission-stage. |
| AFFW-287 | ojs,omp,ops | same → `DiscussionManager` | Submission stage (editorial) · Discussions panel Claimed by: submission-stage. |
| AFFW-288 | ojs,omp,ops | same → `getSecondaryItems` → `ParticipantManager` | Submission stage (editorial) · Participants panel Claimed by: submission-stage. |
| AFFW-289 | ojs,omp,ops | same → `getSecondaryItems` → `ReviewerSuggestionManager` | Submission stage (editorial) · Reviewer suggestions panel (guard: `pageInitConfig.publicationSettings.isReviewerSuggestionEnabled`) Claimed by: submission-stage. |
| AFFW-290 | ojs,ops | `workflowConfigEditorialOJS.js` → `[WORKFLOW_STAGE_ID_SUBMISSION].getActionItems` → `t('editor.submission.schedulePublication')`, `action: 'navigateToMenu'`, `publication_${publicationId}_titleAbstract` | Submission stage (editorial) · Schedule For Publication primary button (OMP replaces this getter wholesale in `workflowConfigEditorialOMP.js` via `deepMerge`) Claimed by: submission-stage. |
| AFFW-291 | ojs,ops | same getter → `t('editor.submission.decision.sendExternalReview')`, `DECISION_EXTERNAL_REVIEW` | Submission stage (editorial) · Send to External Review (guard: `isDecisionAvailable(submission, DECISION_EXTERNAL_REVIEW)`) Claimed by: submission-stage. |
| AFFW-292 | ojs,ops | same getter → `t('editor.submission.decision.skipReview')`, `DECISION_SKIP_EXTERNAL_REVIEW` | Submission stage (editorial) · Skip Review (guard: `isDecisionAvailable(submission, DECISION_SKIP_EXTERNAL_REVIEW)`) Claimed by: submission-stage. |
| AFFW-293 | ojs,ops | same getter → `t('editor.submission.decision.decline')`, `DECISION_INITIAL_DECLINE` | Submission stage (editorial) · Decline (guard: `isDecisionAvailable(submission, DECISION_INITIAL_DECLINE)`) Claimed by: submission-stage. |
| AFFW-294 | ojs,ops | same getter → `t('editor.submission.decision.revertDecline')`, `DECISION_REVERT_INITIAL_DECLINE` | Submission stage (editorial) · Revert Decline (guard: `isDecisionAvailable(submission, DECISION_REVERT_INITIAL_DECLINE)`) Claimed by: submission-stage. |
| AFFW-295 | ojs,ops | same getter → `t('common.delete')`, `WORKFLOW_DELETE_SUBMISSION` | Submission stage (editorial) · Delete submission (guard: `isDecisionAvailable(submission, DECISION_REVERT_INITIAL_DECLINE) && hasCurrentUserAtLeastOneAssignedRoleInAnyStage(submission, [ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN])`) Claimed by: submission-stage. |
| AFFW-296 | omp | `workflowConfigEditorialOMP.js` → `[WORKFLOW_STAGE_ID_SUBMISSION].getActionItems` → `t('editor.submission.decision.sendExternalReview')`, `DECISION_SKIP_INTERNAL_REVIEW` | Submission stage (editorial, OMP) · Send to External Review — routes through skip-internal decision (guard: `isDecisionAvailable(submission, DECISION_SKIP_INTERNAL_REVIEW)`) Claimed by: submission-stage. |
| AFFW-297 | omp | same OMP getter → `t('editor.submission.decision.skipReview')`, `DECISION_SKIP_EXTERNAL_REVIEW` | Submission stage (editorial, OMP) · Skip Review (guard: `isDecisionAvailable(submission, DECISION_SKIP_EXTERNAL_REVIEW)`) Claimed by: submission-stage. |
| AFFW-298 | omp | same OMP getter → `t('editor.submission.decision.decline')`, `DECISION_INITIAL_DECLINE` | Submission stage (editorial, OMP) · Decline (guard: `isDecisionAvailable(submission, DECISION_INITIAL_DECLINE)`) Claimed by: submission-stage. |
| AFFW-299 | omp | same OMP getter → `t('editor.submission.decision.revertDecline')`, `DECISION_REVERT_INITIAL_DECLINE` | Submission stage (editorial, OMP) · Revert Decline (guard: `isDecisionAvailable(submission, DECISION_REVERT_INITIAL_DECLINE)`) Claimed by: submission-stage. |
| AFFW-300 | omp | same OMP getter → `t('common.delete')`, `WORKFLOW_DELETE_SUBMISSION` | Submission stage (editorial, OMP) · Delete submission (guard: `isDecisionAvailable(...DECISION_REVERT_INITIAL_DECLINE) && hasCurrentUserAtLeastOneAssignedRoleInAnyStage(...)`) Claimed by: submission-stage. |
| AFFW-301 | omp | same OMP getter → `t('editor.submission.decision.sendInternalReview')`, `DECISION_INTERNAL_REVIEW` | Submission stage (editorial, OMP) · Send to Internal Review (guard: `isDecisionAvailable(submission, DECISION_INTERNAL_REVIEW)`) |
| AFFW-302 | ojs,omp,ops | `workflowConfigAuthorOJS.js` → `[WORKFLOW_STAGE_ID_SUBMISSION].getPrimaryItems` → `SubmissionStatus` | Submission stage (author) · submission status panel (guard: `hasSubmissionPassedStage(submission, WORKFLOW_STAGE_ID_SUBMISSION)`) Claimed by: submission-stage. |
| AFFW-303 | ojs,omp,ops | `workflowConfigAuthorOJS.js` (and `workflowConfigAuthorOMP.js` duplicate) → `FileManager` `SUBMISSION_FILES`, `DiscussionManager` | Submission stage (author) · submission files + discussions panels Claimed by: submission-stage. |

## Internal Review stage (OMP)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-304 | omp | `workflowConfigEditorialOMP.js` → `[WORKFLOW_STAGE_ID_INTERNAL_REVIEW].getPrimaryItems` → `FileManager` `WORKFLOW_REVIEW_REVISIONS` | Internal Review (editorial) · Revisions files panel |
| AFFW-305 | omp | same → `FileManager` `EDITOR_REVIEW_FILES` | Internal Review (editorial) · Editor review files panel |
| AFFW-306 | omp | same → `ReviewerManager` | Internal Review (editorial) · Reviewers panel |
| AFFW-307 | omp | same → `DiscussionManager` | Internal Review (editorial) · Discussions panel |
| AFFW-308 | omp | same → `getSecondaryItems` → `WorkflowRecommendOnlyListingRecommendations` `stageId: WORKFLOW_STAGE_ID_INTERNAL_REVIEW` | Internal Review (editorial) · Recommendations listing (guard: `selectedStage?.isCurrentUserDecidingEditor`) |
| AFFW-309 | omp | same → `getSecondaryItems` → `ParticipantManager` | Internal Review (editorial) · Participants panel |
| AFFW-310 | omp | same → `getActionItems` → `WorkflowRecommendOnlyControls` | Internal Review (editorial) · Recommend-only control block (guard: `selectedStage.currentUserCanRecommendOnly`) |
| AFFW-311 | omp | same → `t('editor.submission.decision.requestRevisions')`, `DECISION_PENDING_REVISIONS_INTERNAL` | Internal Review (editorial) · Request Revisions (guard: `isDecisionAvailable(submission, DECISION_PENDING_REVISIONS_INTERNAL)`, in `else` branch of recommend-only) |
| AFFW-312 | omp | same → `t('editor.submission.decision.sendExternalReview')`, `DECISION_EXTERNAL_REVIEW` | Internal Review (editorial) · Send to External Review (guard: `isDecisionAvailable(submission, DECISION_EXTERNAL_REVIEW)`) |
| AFFW-313 | omp | same → `t('editor.submission.decision.accept')`, `DECISION_ACCEPT_INTERNAL` | Internal Review (editorial) · Accept Submission (guard: `isDecisionAvailable(submission, DECISION_ACCEPT_INTERNAL)`) |
| AFFW-314 | omp | same → `t('editor.submission.createNewRound')`, `DECISION_NEW_INTERNAL_ROUND` | Internal Review (editorial) · New Review Round (guard: `isDecisionAvailable(submission, DECISION_NEW_INTERNAL_ROUND)`) |
| AFFW-315 | omp | same → `t('editor.submission.decision.cancelReviewRound')`, `DECISION_CANCEL_INTERNAL_REVIEW_ROUND` | Internal Review (editorial) · Cancel Review Round (guard: `isDecisionAvailable(submission, DECISION_CANCEL_INTERNAL_REVIEW_ROUND)`) |
| AFFW-316 | omp | same → `t('editor.submission.decision.decline')`, `DECISION_DECLINE_INTERNAL` | Internal Review (editorial) · Decline (guard: `isDecisionAvailable(submission, DECISION_DECLINE_INTERNAL)`) |
| AFFW-317 | omp | same → `t('editor.submission.decision.revertDecline')`, `DECISION_REVERT_INTERNAL_DECLINE` | Internal Review (editorial) · Revert Decline (guard: `isDecisionAvailable(submission, DECISION_REVERT_INTERNAL_DECLINE)`) |
| AFFW-318 | omp | same → `t('common.delete')`, `WORKFLOW_DELETE_SUBMISSION` | Internal Review (editorial) · Delete submission (guard: `isDecisionAvailable(...DECISION_REVERT_INTERNAL_DECLINE) && hasCurrentUserAtLeastOneAssignedRoleInAnyStage(...)`) |
| AFFW-319 | omp | same `getActionItems` early returns | Internal Review (editorial) · no actions rendered (guard: `!selectedReviewRound` or `selectedReviewRound.round < currentReviewRound.round`) |
| AFFW-320 | omp | `workflowConfigAuthorOMP.js` → `[WORKFLOW_STAGE_ID_INTERNAL_REVIEW].getPrimaryItems` → `ReviewerManager` `redactedForAuthors: true` | Internal Review (author) · redacted reviewers panel (guard: `getOpenReviewAssignmentsForRound(...).length`) |
| AFFW-321 | omp | same → `FileManager` `WORKFLOW_REVIEW_REVISIONS`, `DiscussionManager` | Internal Review (author) · revisions files + discussions panels |
| AFFW-322 | omp | `workflowConfigAuthorOMP.js` → `[WORKFLOW_STAGE_ID_INTERNAL_REVIEW].getActionItems` → `t('workflow.uploadRevisions')`, `FileManagerActions.FILE_UPLOAD`, fileStage `SUBMISSION_FILE_INTERNAL_REVIEW_REVISION` | Internal Review (author) · Upload Revisions button (guard: `selectedReviewRound.statusId ∈ [REVISIONS_REQUESTED, RESUBMIT_FOR_REVIEW, REVISIONS_SUBMITTED]`) |

## External Review stage

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-323 | ojs,omp,ops | `workflowConfigEditorialOJS.js` → `[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW].getPrimaryItems` → `FileManager` `WORKFLOW_REVIEW_REVISIONS` | External Review (editorial) · Revisions files panel Claimed by: review-stage-and-rounds. |
| AFFW-324 | ojs,omp,ops | same → `FileManager` `EDITOR_REVIEW_FILES` | External Review (editorial) · Editor review files panel Claimed by: review-stage-and-rounds. |
| AFFW-325 | ojs,omp,ops | same → `ReviewerManager` (props `componentForms`, `recommendations`) | External Review (editorial) · Reviewers panel Claimed by: review-stage-and-rounds. |
| AFFW-326 | ojs,omp,ops | same → `AuthorResponseRequestManager` | External Review (editorial) · Author response request panel |
| AFFW-327 | ojs,omp,ops | same → `DiscussionManager` | External Review (editorial) · Discussions panel Claimed by: review-stage-and-rounds. |
| AFFW-328 | ojs,omp,ops | same → `getSecondaryItems` → `WorkflowRecommendOnlyListingRecommendations` `stageId: WORKFLOW_STAGE_ID_EXTERNAL_REVIEW` | External Review (editorial) · Recommendations listing (guard: `selectedReviewRound && selectedStage?.isCurrentUserDecidingEditor`) Claimed by: review-stage-and-rounds. |
| AFFW-329 | ojs,omp,ops | same → `getSecondaryItems` → `ParticipantManager` | External Review (editorial) · Participants panel Claimed by: review-stage-and-rounds. |
| AFFW-330 | ojs,omp,ops | same → `getSecondaryItems` → `ReviewerSuggestionManager` | External Review (editorial) · Reviewer suggestions panel (guard: `selectedReviewRound && publicationSettings.isReviewerSuggestionEnabled`) Claimed by: review-stage-and-rounds. |
| AFFW-331 | ojs,omp,ops | same → `getActionItems` → `WorkflowRecommendOnlyControls` | External Review (editorial) · Recommend-only control block (guard: `selectedStage.currentUserCanRecommendOnly`) |
| AFFW-332 | ojs,omp,ops | same → `t('editor.submission.decision.requestRevisions')`, `DECISION_REQUEST_REVISION` | External Review (editorial) · Request Revisions (guard: `isDecisionAvailable(submission, DECISION_RESUBMIT) \|\| isDecisionAvailable(submission, DECISION_PENDING_REVISIONS)`) Claimed by: review-stage-and-rounds. |
| AFFW-333 | ojs,omp,ops | same → `t('editor.submission.decision.accept')`, `DECISION_ACCEPT` | External Review (editorial) · Accept Submission (guard: `isDecisionAvailable(submission, DECISION_ACCEPT)`) Claimed by: review-stage-and-rounds. |
| AFFW-334 | ojs,omp,ops | same → `t('editor.submission.createNewRound')`, `DECISION_NEW_EXTERNAL_ROUND` | External Review (editorial) · New Review Round (guard: `isDecisionAvailable(submission, DECISION_NEW_EXTERNAL_ROUND)`) Claimed by: review-stage-and-rounds. |
| AFFW-335 | ojs,omp,ops | same → `t('editor.submission.decision.cancelReviewRound')`, `DECISION_CANCEL_REVIEW_ROUND` | External Review (editorial) · Cancel Review Round (guard: `isDecisionAvailable(submission, DECISION_CANCEL_REVIEW_ROUND)`) Claimed by: review-stage-and-rounds. |
| AFFW-336 | ojs,omp,ops | same → `t('editor.submission.decision.decline')`, `DECISION_DECLINE` | External Review (editorial) · Decline (guard: `isDecisionAvailable(submission, DECISION_DECLINE)`) Claimed by: review-stage-and-rounds. |
| AFFW-337 | ojs,omp,ops | same → `t('editor.submission.decision.revertDecline')`, `DECISION_REVERT_DECLINE` | External Review (editorial) · Revert Decline (guard: `isDecisionAvailable(submission, DECISION_REVERT_DECLINE)`) Claimed by: review-stage-and-rounds. |
| AFFW-338 | ojs,omp,ops | same → `t('common.delete')`, `WORKFLOW_DELETE_SUBMISSION` | External Review (editorial) · Delete submission (guard: `isDecisionAvailable(...DECISION_REVERT_DECLINE) && hasCurrentUserAtLeastOneAssignedRoleInAnyStage(submission,[ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN])`) Claimed by: review-stage-and-rounds. |
| AFFW-339 | ojs,omp,ops | same `getActionItems` early returns | External Review (editorial) · no actions (guard: `!selectedReviewRound` or `selectedReviewRound.round < currentReviewRound.round`) Claimed by: review-stage-and-rounds. |
| AFFW-340 | ojs,omp,ops | `src/pages/workflow/components/action/WorkflowRecommendOnlyControls.vue` → `t('editor.submission.workflowDecision.changeDecision')` | External/Internal Review · "Change decision" link revealing recommendation buttons (guard: `isDecidingEditorAssigned && !showRecommendationActions`) |
| AFFW-341 | ojs,omp,ops | `WorkflowRecommendOnlyControls.vue` → `t('editor.submission.recommendation.noDecidingEditors')` | External/Internal Review · no-deciding-editor notice (guard: `!isDecidingEditorAssigned`) |
| AFFW-342 | ojs,omp,ops | `WorkflowRecommendOnlyControls.vue` → `getRecommendationActions` → `t('editor.submission.recommend.revisions')`, `DECISION_RECOMMEND_REVISION` | External Review · Recommend Revisions (guard: `stageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`) |
| AFFW-343 | ojs,omp,ops | same → `t('editor.submission.recommend.accept')`, `DECISION_RECOMMEND_ACCEPT` | External Review · Recommend Accept (guard: `stageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`) |
| AFFW-344 | ojs,omp,ops | same → `t('editor.submission.recommend.decline')`, `DECISION_RECOMMEND_DECLINE` | External Review · Recommend Decline (guard: `stageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`) |
| AFFW-345 | omp | same → `t('editor.submission.recommend.revisions')`, `DECISION_RECOMMEND_PENDING_REVISIONS_INTERNAL` | Internal Review · Recommend Revisions (guard: `stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW`) |
| AFFW-346 | omp | same → `t('editor.submission.recommend.accept')`, `DECISION_RECOMMEND_ACCEPT_INTERNAL` | Internal Review · Recommend Accept (guard: `stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW`) |
| AFFW-347 | omp | same → `t('editor.submission.recommend.decline')`, `DECISION_RECOMMEND_DECLINE_INTERNAL` | Internal Review · Recommend Decline (guard: `stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW`) |
| AFFW-348 | omp | same → `t('editor.submission.recommend.sendExternalReview')`, `DECISION_RECOMMEND_EXTERNAL_REVIEW` | Internal Review · Recommend Send to External Review (guard: `stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW`) |
| AFFW-349 | ojs,omp,ops | `src/pages/workflow/components/secondary/WorkflowRecommendOnlyListingRecommendations.vue` → `t('editor.submission.recommendation')` | Review stages · read-only recommendation list (guard: `v-if="currentRecommendations"`) Claimed by: review-stage-and-rounds. |
| AFFW-350 | ojs,omp,ops | `workflowConfigAuthorOJS.js` → `[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW].getPrimaryItems` → `WorkflowListingEmails` | External Review (author) · Notifications/emails list panel Claimed by: review-stage-and-rounds. |
| AFFW-351 | ojs,omp,ops | `src/pages/workflow/components/primary/WorkflowListingEmails.vue` → `openEmail(email.id)` | External Review (author) · email subject link → opens `LegacyAjax` side modal `authorDashboard/readSubmissionEmail` (guard: `v-if="emails?.length"`) Claimed by: review-stage-and-rounds. |
| AFFW-352 | ojs,omp,ops | `workflowConfigAuthorOJS.js` → `ReviewerManager` `redactedForAuthors: true` | External Review (author) · redacted reviewers panel (guard: `getOpenAndCompletedReviewAssignmentsForRound(...).length`) Claimed by: review-stage-and-rounds. |
| AFFW-353 | ojs,omp,ops | `workflowConfigAuthorOJS.js` → `FileManager` `WORKFLOW_REVIEW_REVISIONS`, `DiscussionManager` | External Review (author) · revisions files + discussions panels Claimed by: review-stage-and-rounds. |
| AFFW-354 | ojs,omp,ops | `workflowConfigAuthorOJS.js` → `AuthorResponseManager` | External Review (author) · author response panel (guard: `selectedReviewRound?.isAuthorResponseRequested \|\| statusId ∈ [REVIEW_ROUND_STATUS_ACCEPTED, REVIEW_ROUND_STATUS_REVISIONS_REQUESTED]`) |
| AFFW-355 | ojs,omp,ops | `workflowConfigAuthorOJS.js` → `[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW].getActionItems` → `t('workflow.uploadRevisions')`, `FileManagerActions.FILE_UPLOAD`, fileStage `SUBMISSION_FILE_REVIEW_REVISION` | External Review (author) · Upload Revisions button (guard: `selectedReviewRound.statusId ∈ [REVISIONS_REQUESTED, RESUBMIT_FOR_REVIEW, REVISIONS_SUBMITTED]`) Claimed by: review-stage-and-rounds. |

## Copyediting stage

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-356 | ojs,omp,ops | `workflowConfigEditorialOJS.js` → `[WORKFLOW_STAGE_ID_EDITING].getPrimaryItems` → `WorkflowNotificationDisplay` | Copyediting (editorial) · notification panel (`NOTIFICATION_TYPE_ASSIGN_COPYEDITOR`, `NOTIFICATION_TYPE_AWAITING_COPYEDITS`) |
| AFFW-357 | ojs,omp,ops | same → `FileManager` `FINAL_DRAFT_FILES` | Copyediting (editorial) · Draft files panel |
| AFFW-358 | ojs,omp,ops | same → `DiscussionManager` | Copyediting (editorial) · Discussions panel |
| AFFW-359 | ojs,omp,ops | same → `FileManager` `COPYEDITED_FILES` | Copyediting (editorial) · Copyedited files panel |
| AFFW-360 | ojs,omp,ops | same → `getSecondaryItems` → `ParticipantManager` | Copyediting (editorial) · Participants panel |
| AFFW-361 | ojs,omp,ops | same → `getActionItems` → `t('editor.submission.decision.sendToProduction')`, `DECISION_SEND_TO_PRODUCTION` | Copyediting (editorial) · Send To Production (guard: `isDecisionAvailable(submission, DECISION_SEND_TO_PRODUCTION)`) |
| AFFW-362 | ojs,omp,ops | same → `t('editor.submission.decision.backFromCopyediting')`, `DECISION_BACK_FROM_COPYEDITING` | Copyediting (editorial) · Back from Copyediting (guard: `isDecisionAvailable(submission, DECISION_BACK_FROM_COPYEDITING)`) |
| AFFW-363 | ojs,omp,ops | `workflowConfigAuthorOJS.js` / `workflowConfigAuthorOMP.js` → `[WORKFLOW_STAGE_ID_EDITING].getPrimaryItems` → `DiscussionManager`, `FileManager` `COPYEDITED_FILES` | Copyediting (author) · discussions + copyedited files panels |

## Production stage

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-364 | ojs,omp | `workflowConfigEditorialOJS.js` → `[WORKFLOW_STAGE_ID_PRODUCTION].getPrimaryItems` → `WorkflowNotificationDisplay` | Production (editorial) · notification panel (OJS: `ASSIGN_PRODUCTIONUSER`/`AWAITING_REPRESENTATIONS`; OMP: `VISIT_CATALOG`/`FORMAT_NEEDS_APPROVED_SUBMISSION` per `WorkflowNotificationDisplay.vue` `getRequestOptionsPerStage`) |
| AFFW-365 | ojs,omp | same → `FileManager` `PRODUCTION_READY_FILES` | Production (editorial) · Production ready files panel |
| AFFW-366 | ojs,omp | same → `DiscussionManager` | Production (editorial) · Discussions panel |
| AFFW-367 | ojs,omp | same → `getSecondaryItems` → `ParticipantManager` | Production (editorial) · Participants panel |
| AFFW-368 | ojs,omp | same → `getActionItems` → `t('editor.submission.schedulePublication')`, `action:'navigateToMenu'`, `publication_${publicationId}_titleAbstract` | Production (editorial) · Schedule For Publication primary button |
| AFFW-369 | ojs,omp | same → `t('editor.submission.decision.backToCopyediting')`, `DECISION_BACK_FROM_PRODUCTION` | Production (editorial) · Back to Copyediting (guard: `isDecisionAvailable(submission, DECISION_BACK_FROM_PRODUCTION)`) |
| AFFW-370 | ops | `workflowConfigEditorialOPS.js` → `[WORKFLOW_STAGE_ID_PRODUCTION].getPrimaryItems` → `WorkflowNotificationDisplay`, `DiscussionManager` | Production (editorial, OPS) · notification + discussions panels only (OPS override replaces OJS production getters) |
| AFFW-371 | ops | `workflowConfigEditorialOPS.js` → `getActionItems` → `t('editor.submission.schedulePublication')`, `action:'navigateToMenu'` | Production (editorial, OPS) · Schedule For Publication (guard: `getActiveStage(submission).id === WORKFLOW_STAGE_ID_PRODUCTION`) |
| AFFW-372 | ops | same → `t('editor.submission.decision.decline')`, `DECISION_INITIAL_DECLINE` | Production (editorial, OPS) · Decline (guard: `isDecisionAvailable(submission, DECISION_INITIAL_DECLINE)`) |
| AFFW-373 | ops | same → `t('editor.submission.decision.revertDecline')`, `DECISION_REVERT_INITIAL_DECLINE` | Production (editorial, OPS) · Revert Decline (guard: `isDecisionAvailable(submission, DECISION_REVERT_INITIAL_DECLINE)`) |
| AFFW-374 | ops | same → `t('common.delete')`, `WORKFLOW_DELETE_SUBMISSION` | Production (editorial, OPS) · Delete submission (guard: `isDecisionAvailable(...DECISION_REVERT_INITIAL_DECLINE) && hasCurrentUserAtLeastOneAssignedRoleInAnyStage(...)`) |
| AFFW-375 | ojs,ops | `workflowConfigAuthorOJS.js` → `[WORKFLOW_STAGE_ID_PRODUCTION].getPrimaryItems` → `DiscussionManager` | Production (author) · discussions panel |
| AFFW-376 | omp | `workflowConfigAuthorOMP.js` → `[WORKFLOW_STAGE_ID_PRODUCTION].getPrimaryItems` → `WorkflowNotificationDisplay`, `DiscussionManager` | Production (author, OMP) · notification + discussions panels |

## Publication tabs — shared controls

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-377 | ojs,omp,ops | `WorkflowPage.vue` → `#publication-controls-left` (`data-cy="workflow-controls-left"`) | Publication tab · left controls region; supports nested arrays for horizontal rows (guard: `v-if="workflowStore.primaryControlsLeft?.length"`) |
| AFFW-378 | ojs,omp,ops | `WorkflowPage.vue` → `#publication-controls-right` (`data-cy="workflow-controls-right"`) | Publication tab · right controls region (guard: `v-if="workflowStore.primaryControlsRight?.length"`) |
| AFFW-379 | ojs,omp | `workflowConfigEditorialOJS.js` → `PublicationConfig.common.getPrimaryItems` → `WorkflowPublicationEditWarning` | Publication tab (editorial) · `publication.editorEditWarning` banner (guard: `selectedPublication.status === STATUS_PUBLISHED`) |
| AFFW-380 | ojs,omp,ops | `workflowConfigAuthorOJS.js` → `PublicationConfig.common.getPrimaryItems` → `WorkflowPublicationEditDisabled` | Publication tab (author) · `publication.editDisabled` banner (guard: `selectedPublication.status === STATUS_PUBLISHED`) |
| AFFW-381 | ojs,omp | `workflowConfigEditorialOJS.js` → `PublicationConfig.common.getPrimaryControlsLeft` → `WorkflowChangeSubmissionLanguage` `canChangeSubmissionLanguage: permissions.canChangeSubmissionLanguage` | Publication tab (editorial) · change-language control (guard: `submission.status !== STATUS_PUBLISHED && submission.publications.length < 2`) |
| AFFW-382 | ops | `workflowConfigEditorialOPS.js` → `PublicationConfig.common.getPrimaryControlsLeft` → `WorkflowChangeSubmissionLanguage` | Publication tab (editorial, OPS) · same control, OPS override (guard: `submission.status !== STATUS_PUBLISHED && submission.publications.length < 2`) |
| AFFW-383 | ojs,omp,ops | `workflowConfigEditorialOJS.js` / `workflowConfigEditorialOPS.js` / `workflowConfigAuthorOJS.js` / `workflowConfigAuthorOPS.js` → `WorkflowPublicationVersionControl` | Publication tab · version status indicator |
| AFFW-384 | ojs,omp,ops | `src/pages/workflow/components/publication/WorkflowPublicationVersionControl.vue` → `statusProps` | Publication tab · status dot + label: `publication.status.unscheduled` / `.scheduled` / `.published` / `.unpublished` |
| AFFW-385 | ops | `workflowConfigEditorialOPS.js` + `workflowConfigAuthorOPS.js` → `getPrimaryControlsLeft` → `WorkflowPublicationRelationDropdownOPS` | Publication tab (OPS) · Relation dropdown, paired horizontally with version control (component registered only in `WorkflowPageOPS.vue`) |
| AFFW-386 | ops | `src/pages/workflow/components/publication/WorkflowPublicationRelationDropdownOPS.vue` → `Dropdown label t('publication.relation')` + `relationForm` | Publication tab (OPS) · Relation form (fields `relationStatus`, `vorDoi`) |
| AFFW-387 | ojs | `workflowConfigEditorialOJS.js` → `PublicationConfig.common.getPrimaryControlsRight` → `t('common.preview')`, `WORKFLOW_PREVIEW_PUBLICATION` | Publication tab (editorial) · Preview (guard: `permissions.canPublish` && status ∈ `[STATUS_QUEUED, STATUS_READY_TO_PUBLISH, STATUS_READY_TO_SCHEDULE]` && `hasSubmissionPassedStage(submission, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)`) |
| AFFW-388 | ojs | same → label `submission.status === STATUS_PUBLISHED ? t('publication.publish') : t('editor.submission.schedulePublication')`, `WORKFLOW_ASSIGN_TO_ISSUE_AND_SCHEDULE_FOR_PUBLICATION` | Publication tab (editorial, OJS) · Schedule For Publication / Publish (guard: `permissions.canPublish` && status ∈ `[STATUS_QUEUED, STATUS_READY_TO_PUBLISH, STATUS_READY_TO_SCHEDULE]`) |
| AFFW-389 | omp,ops | `workflowConfigEditorialOMP.js` / `workflowConfigEditorialOPS.js` → `PublicationConfig.common.getPrimaryControlsRight` → `t('publication.publish')`, `WORKFLOW_SCHEDULE_FOR_PUBLICATION` | Publication tab (editorial, OMP/OPS) · Publish — skips issue assignment step (guard: `permissions.canPublish && selectedPublication.status === STATUS_QUEUED`) |
| AFFW-390 | omp,ops | same OMP/OPS getters → `t('common.preview')`, `WORKFLOW_PREVIEW_PUBLICATION` | Publication tab (editorial, OMP/OPS) · Preview (guard: `status === STATUS_QUEUED && hasSubmissionPassedStage(submission, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)`) |
| AFFW-391 | ojs,omp,ops | all three editorial `getPrimaryControlsRight` → `t('common.preview')` in scheduled branch | Publication tab (editorial) · Preview (guard: `selectedPublication.status === STATUS_SCHEDULED`) |
| AFFW-392 | ojs,omp,ops | all three → `t('publication.unschedule')`, `WORKFLOW_UNSCHEDULE_PUBLICATION` | Publication tab (editorial) · Unschedule (guard: `selectedPublication.status === STATUS_SCHEDULED`) |
| AFFW-393 | ojs,omp,ops | all three → `t('publication.unpublish')`, `WORKFLOW_UNPUBLISH_PUBLICATION` | Publication tab (editorial) · Unpublish (guard: `selectedPublication.status === STATUS_PUBLISHED`) |
| AFFW-394 | ojs,omp,ops | all three → early `return []` | Publication tab · entire right controls region hidden (guard: `!permissions.canPublish`) |

## Publication tab content panels

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-395 | ojs,omp,ops | `workflowConfigEditorialOJS.js` / `workflowConfigAuthorOJS.js` → `PublicationConfig.titleAbstract` → `WorkflowPublicationForm formName:'titleAbstract'` | Title & Abstract · publication form (guard: `canEdit: permissions.canEditPublication`) |
| AFFW-396 | ojs,omp,ops | `PublicationConfig.contributors` → `ContributorManager` | Contributors · contributor manager (editorial passes `canEdit: permissions.canEditPublication`; author config omits `canEdit`) |
| AFFW-397 | ojs,omp,ops | `PublicationConfig.metadata` → `WorkflowPublicationForm formName:'metadata'`, `noFieldsMessage` | Metadata · metadata form; shows "No metadata fields are currently enabled." when form has no fields |
| AFFW-398 | ojs,omp,ops | `PublicationConfig.citations` → `CitationManager` (`citationsMetadataLookup`) | Citations · citation manager (guard: `canEdit: permissions.canEditPublication`) |
| AFFW-399 | ojs,omp,ops | `PublicationConfig.dataAvailabilityAndCitation` → `DataCitationManager` | Data availability & citation · data citation manager (guard: `pageInitConfig.publicationSettings.supportsDataCitations`) |
| AFFW-400 | ojs,omp,ops | `PublicationConfig.dataAvailabilityAndCitation` → `WorkflowPublicationForm formName:'dataAvailability'` | Data availability & citation · data availability form (guard: `publicationSettings.supportsDataAvailability`) |
| AFFW-401 | ojs,omp,ops | `PublicationConfig.funding` → `FunderManager` (`funderEditForm`) | Funding · funder manager (guard: `canEdit: permissions.canEditPublication`) |
| AFFW-402 | ojs,omp,ops | `workflowConfigEditorialOJS.js` → `PublicationConfig.identifiers` → `WorkflowPublicationForm formName:'identifier'` | Identifiers · identifier form (editorial config only) |
| AFFW-403 | ojs | `workflowConfigEditorialOJS.js` → `PublicationConfig.jats` → `WorkflowPublicationJats` | JATS · JATS panel (component registered only in `WorkflowPageOJS.vue`) |
| AFFW-404 | ojs | `src/pages/workflow/components/publication/WorkflowPublicationJats.vue` → `t('common.upload')` / `openFileBrowser` | JATS · Upload button (guard: `publication.status !== STATUS_PUBLISHED && canEdit`) |
| AFFW-405 | ojs | same → `t('grid.action.moreInformation')` / `openFileInformationCenter` | JATS · More Information button → `FileInformationCenterHandler` modal (guard: `!isDefaultContent`) |
| AFFW-406 | ojs | same → `t('common.delete')` / `openDeleteModal` | JATS · Delete button (guard: `!isDefaultContent && publication.status !== STATUS_PUBLISHED && canEdit`; disabled while `isLoading`) |
| AFFW-407 | ojs | same → `t('common.download')` / `downloadJatsXML` | JATS · Download button (guard: `workingJatsProps['loadingContentError'] == null`) |
| AFFW-408 | ojs | same → `Checkbox t('publication.jats.makePublic')` / `handleVisibilityChange` | JATS · Make-public checkbox (guard: `workingJatsProps['loadingContentError'] == null`; disabled while `isUpdatingVisibility`) |
| AFFW-409 | ojs | same → `FileUploader` ref `uploader` | JATS · hidden file uploader (`upload-progress-label t('submission.upload.percentComplete')`) |
| AFFW-410 | ojs | same → `t('publication.jats.autoCreatedMessage')` / `t('publication.jats.lastModified')` | JATS · footer status text (guard: `isDefaultContent` vs uploaded file) |
| AFFW-411 | ojs | `workflowConfigEditorialOJS.js` → `PublicationConfig.bodyText` → `WorkflowPublicationBodyText` | Body Text · SciFlow editor screen (component registered only in `WorkflowPageOJS.vue`) |
| AFFW-412 | ojs | `src/pages/workflow/components/publication/WorkflowPublicationBodyText.vue` → `saveDocument` / `saveButtonLabel` | Body Text · Save button (label toggles `common.save` / `form.saved`) |
| AFFW-413 | ojs | same → `toggleFullscreen` / `fullscreenBtnRef` | Body Text · Fullscreen toggle (`common.fullscreen` / `common.exitFullscreen`, `aria-pressed="isFullscreen"`) |
| AFFW-414 | ojs | same → `Badge t('common.unsavedChanges')` | Body Text · unsaved-changes badge (guard: `v-show="isDirty"`) |
| AFFW-415 | ojs | same → `PandocConverter` `handlePandocHtmlReady` / `handleFigureUpload` | Body Text · import/convert control (disabled: `!isEditorReady`); auto-import via `importFileUrl`/`importFileName` query params |
| AFFW-416 | ojs | same → `sciflow-formatbar`, `sciflow-editor`, `sciflow-selection-editor`, `sciflow-outline`, `sciflow-reference-list` | Body Text · editor toolbar, editor surface, and sidebar widgets |
| AFFW-417 | ojs | same → `sidebarSections` (`references`, `selected-element`, `outline`) | Body Text · sidebar accordions: Citations, Selected Element, Outline (`openAccordionSection`, single-open) |
| AFFW-418 | ojs | same → `navigationGuard` → `window.confirm(t('form.dataHasChanged'))` | Body Text · unsaved-changes navigation confirm (guard: `isDirty`) |
| AFFW-419 | ojs,ops | `workflowConfigEditorialOJS.js` / `workflowConfigAuthorOJS.js` → `PublicationConfig.galleys` → `GalleyManager` | Galleys · galley manager (editorial passes `canEdit: permissions.canEditPublication`; author omits it) |
| AFFW-420 | ojs,omp,ops | `PublicationConfig.media` → `MediaFileManager` | Media · media file manager (editorial passes `canEdit`; author omits it) |
| AFFW-421 | ojs,omp,ops | `workflowConfigEditorialOJS.js` → `PublicationConfig.license` → `WorkflowPublicationForm formName:'permissionDisclosure'` | License / Permissions · permission & disclosure form |
| AFFW-422 | ojs | `workflowConfigEditorialOJS.js` → `PublicationConfig.issue` → `WorkflowPublicationForm formName:'issue'` with `issueCount: publicationSettings.countIssues` | Issue assignment · issue entry form (OJS editorial config only) |
| AFFW-423 | ojs | `src/pages/workflow/components/publication/WorkflowPublicationForm.vue` → `useWorkflowPublicationFormIssue(newForm, publication, {groupId:'placement'})` | Issue assignment · injected assignment radio + issue select (guard: `formName === 'issue' && issueCount > 0 && isOJS()`) |
| AFFW-424 | ojs,omp | `WorkflowPublicationForm.vue` → `useInsertSummaryOfChangesContent(newForm,'summaryOfChanges',submission)` | Issue / Catalog Entry · "Insert Content" wiring on `summaryOfChanges` field (guard: `(formName === 'issue' && isOJS()) \|\| (formName === 'catalogEntry' && isOMP())`) |
| AFFW-425 | omp | `workflowConfigEditorialOMP.js` + `workflowConfigAuthorOMP.js` → `PublicationConfig.chapters` → `ChapterManager` | Chapters · chapter manager (OMP config files only) |
| AFFW-426 | omp | `workflowConfigEditorialOMP.js` + `workflowConfigAuthorOMP.js` → `PublicationConfig.publicationFormats` → `PublicationFormatManager` | Publication Formats · format manager (OMP config files only) |
| AFFW-427 | omp | `workflowConfigEditorialOMP.js` → `PublicationConfig.catalogEntry` → `WorkflowPublicationForm formName:'catalogEntry'` | Catalog Entry · catalog entry form (OMP editorial config only) |
| AFFW-428 | ops | `workflowConfigEditorialOPS.js` → `PublicationConfig.preprintEntry` → `WorkflowPublicationForm formName:'issue'` | Preprint Entry · preprint entry form reusing the `issue` component form (OPS editorial config only) |
| AFFW-429 | ops | `workflowConfigAuthorOPS.js` → `PublicationConfig.discussions` → `DiscussionManager` `submissionStageId: WORKFLOW_STAGE_ID_PRODUCTION` | Discussions (OPS author publication tab) · discussions panel |
| AFFW-430 | ojs,omp,ops | `src/pages/workflow/components/publication/WorkflowPublicationForm.vue` → `displayNoFieldsEnabled` / `noFieldsMessage` | Any publication form tab · empty-form message (guard: `publicationForm.fields.length === 0`); form submit disabled via `newPublicationForm.canSubmit = props.canEdit` |

## Marketing tabs (OMP)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-431 | omp | `workflowConfigEditorialOMP.js` → `MarketingConfig.audience` → `WorkflowMarketingForm formName:'audience'` | Marketing → Audience · audience form (OMP editorial config; `MarketingConfig` only consumed by `useWorkflowConfigOMP.js`) |
| AFFW-432 | omp | `workflowConfigEditorialOMP.js` → `MarketingConfig.representatives` → `RepresentativeManager` | Marketing → Representatives · representative manager |
| AFFW-433 | omp | `workflowConfigEditorialOMP.js` → `MarketingConfig.publicationDates` → `WorkflowMarketingForm formName:'publicationDates'` | Marketing → Publication Dates · publication dates form |
| AFFW-434 | omp | `src/pages/workflow/components/publication/WorkflowMarketingForm.vue` → `PkpForm` `@success="triggerDataChange"` | Marketing tabs · form host fetching `_components/${formName}` off publication[0] |

## Workflow modals & dialogs

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-435 | ojs | `src/pages/workflow/composables/useWorkflowActions.js` → `workflowAssignPublicationStage` → `WorkflowVersionSideModal` | Schedule-for-publication step 1 side modal (guard: `requirePublicationStage` — `!selectedPublication.versionStage \|\| status ∉ [STATUS_READY_TO_PUBLISH, STATUS_READY_TO_SCHEDULE]` in OJS) |
| AFFW-436 | ojs,omp,ops | `src/pages/workflow/components/publication/WorkflowVersionSideModal.vue` → `t('publication.scheduledForPublication.reviewDetails.label')` / `.description` | Version side modal · title/description + `useWorkflowVersionForm('publish')` form; `@cancel="onCloseFn"` |
| AFFW-437 | ojs,omp,ops | `src/pages/workflow/composables/useWorkflowVersionForm.js` → `addPage('default', {submitButton: t('common.confirm'), cancelButton: t('common.cancel')})` | Version modals · Confirm / Cancel footer buttons |
| AFFW-438 | ojs,omp,ops | `useWorkflowVersionForm.js` → `addFieldSelect('sendToVersion')` `t('publication.sendToTextEditor.label')` | Version dialog · Send-to-version select (guard: `modeState.isTextEditorMode`; includes `t('publication.createVersion')` option value `create`) |
| AFFW-439 | ojs,omp,ops | `useWorkflowVersionForm.js` → `addFieldSelect('versionSource')` `t('publication.versionSource.create.label')` | Version modals · Version source select (guard: `showWhen: ['sendToVersion','create']` unless `isCreateMode`) |
| AFFW-440 | ojs,omp,ops | `useWorkflowVersionForm.js` → `addFieldSelect('versionStage')` `t('publication.versionStage.label')` | Version modals · Version stage select from `store.versionStageOptions` (guard: `isRequired: modeState.isPublishMode`; `showWhen: ['sendToVersion', getUnassignedVersions()]` in text-editor mode) |
| AFFW-441 | ojs,omp,ops | `useWorkflowVersionForm.js` → `getVersionIsMinorField` `t('publication.revisionSignificance.label')` | Version modals · Major/Minor radio-select (`.major` / `.minor`; minor option `disabled: !allowMinorVersion`) |
| AFFW-442 | ojs,omp,ops | `useWorkflowVersionForm.js` → `addFieldSelect('updateType')` `t('publication.updateType.label')` | Schedule-for-publication modal · Update type select (guard: `modeState.isPublishMode`) |
| AFFW-443 | ojs,omp,ops | `useWorkflowVersionForm.js` → `addFieldRichTextArea('summaryOfChanges')` `t('submission.form.summaryOfChanges')` | Schedule-for-publication modal · Summary of changes rich text (multilingual) (guard: `modeState.isPublishMode`) |
| AFFW-444 | ojs,omp | `useWorkflowVersionForm.js` → `useInsertSummaryOfChangesContent(form,'summaryOfChanges',store.submission)` | Schedule-for-publication modal · Insert-content helper on summary field (guard: `!isOPS()`) |
| AFFW-445 | ojs | `useWorkflowVersionForm.js` → `useWorkflowPublicationFormIssue(form, store.selectedPublication)` | Schedule-for-publication modal · issue assignment fields (guard: `modeState.isPublishMode && issueCount > 0 && isOJS()`) |
| AFFW-446 | ojs | `src/pages/workflow/composables/useWorkflowPublicationFormIssue.js` → `addFieldOptions('assignment','radio')` `t('publication.assignToIssue.assignmentType')` | Issue assignment · assignment-type radio group (options from `issues/assignmentOptions`) |
| AFFW-447 | ojs | same → `addFieldSelect('issueId')` `t('issue.issue')` | Issue assignment · issue select (guard: `showWhen: ['assignment', showWhenAssignmentIds]`) |
| AFFW-448 | ojs | same → `setHiddenValue('status', ...)` | Issue assignment · hidden publication status field driven by selected assignment option |
| AFFW-449 | ojs,omp,ops | `useWorkflowActions.js` → `workflowScheduleForPublication` → legacy `modals.publish.PublishHandler` | Publish modal · legacy Publish/Schedule modal, title `t('editor.submission.schedulePublication')`, closes on `FORM_PUBLISH` success |
| AFFW-450 | ojs,omp,ops | `useWorkflowActions.js` → `workflowCreateNewVersion` → `WorkflowVersionDialogBody` mode `createNewVersion` | Create New Version dialog, title `t('publication.createVersion')`, `showCloseButton: false` |
| AFFW-451 | ojs,omp,ops | `src/pages/workflow/components/publication/WorkflowVersionDialogBody.vue` → prop `mode` validator `['createNewVersion','sendToTextEditor']` | Version dialog body · hosts `useWorkflowVersionForm(mode)`; `sendToTextEditor` mode navigates to Body Text with import params |
| AFFW-452 | ojs,omp,ops | `useWorkflowActions.js` → `workflowUnschedulePublication` → `openDialog` title `t('publication.unschedule')` | Unschedule dialog · `t('publication.unschedule')` (isWarnable) + `t('common.cancel')`; PUT `.../unpublish`, `modalStyle: 'negative'` |
| AFFW-453 | ojs,omp,ops | `useWorkflowActions.js` → `workflowUnpublishPublication` → `openDialog` title `t('publication.unpublish')` | Unpublish dialog · `t('publication.unpublish')` (isWarnable) + `t('common.cancel')`; PUT `.../unpublish` |
| AFFW-454 | ojs,omp,ops | `useWorkflowActions.js` → `workflowDeleteSubmission` | Delete submission dialog · `t('editor.submissionArchive.confirmDelete')`; `t('common.confirm')` (isPrimary) + `t('common.cancel')`; DELETE `_submissions/{id}` then `store.closeWorkflowModal()` |
| AFFW-455 | ojs,omp,ops | `useWorkflowActions.js` → `workflowDecisionReturnToWorkflow` | Return-to-workflow dialog · `t('editor.submission.decision.returnToWorkflow.description')`; Confirm/Cancel; POST decision `DECISION_RETURN_TO_WORKFLOW` |
| AFFW-456 | ojs,omp,ops | `useWorkflowActions.js` → `workflowDecisionReturnToDone` | Return-to-done dialog · `t('editor.submission.decision.returnToDone.description')`; Confirm/Cancel; POST `submissions/{id}/returnToDone` |
| AFFW-457 | ojs,omp,ops | `useWorkflowActions.js` → `workflowChangeSubmissionLanguage` → `WorkflowChangeSubmissionLanguageModal` | Change submission language side modal |
| AFFW-458 | ojs,omp,ops | `src/pages/workflow/modals/WorkflowChangeSubmissionLanguageModal.vue` → `t('submission.list.changeSubmissionLanguage.title')` | Change-language modal · title, publication title description, `changeLanguageMetadata` form with `@cancel="store.closeSideModal"` |
| AFFW-459 | ojs,omp,ops | `src/pages/workflow/modals/workflowChangeSubmissionLanguageModalStore.js` → `setCustom` / `success` | Change-language modal · locale select re-populates title/abstract fields; on success `window.location.reload()`; action `.../changeLocale` |
| AFFW-460 | ojs,omp,ops | `src/pages/workflow/modals/WorkflowSelectRevisionFormModal.vue` → `t('editor.submission.decision.requestRevisions')` | Select-revision-type side modal · form from `selectRevisionDecisionForm` / `selectRevisionRecommendationForm`; success closes modal and opens decision page |
| AFFW-461 | ojs,omp,ops | `src/pages/workflow/composables/useWorkflowDecisions.js` → `showWarningDialogAboutMinimumReviewsIfEnabled` | Minimum-reviews warning dialog · `t('dashboard.proceedWithoutMinimumReviews')` / `t('dashboard.minimumConfirmedReviewsNotMet')`; `t('common.yesContinue')` (isWarnable) + `t('common.cancel')` (guard: `shouldMinimumReviewsBeConsidered && !hasMinimumReviewsCount`) |
| AFFW-462 | ojs,omp,ops | `useWorkflowDecisions.js` → `openDecisionPage` | All decision buttons · redirect to `decision/record/{submissionId}` with `decision`, `ret`, `reviewRoundId`, `stageId` query params |
| AFFW-463 | ojs | `WorkflowPublicationJats.vue` → `openDeleteModal` → `t('publication.jats.confirmDeleteFileTitle')` | JATS delete dialog · `t('publication.jats.confirmDeleteFileButton')` + `t('common.cancel')` |
| AFFW-464 | ojs | `WorkflowPublicationJats.vue` → `handleVisibilityChange` → `t('publication.jats.enableVisibilityTitle')` / `disableVisibilityTitle` | JATS visibility dialog · `t('common.confirm')` + `t('common.cancel')` |
| AFFW-465 | ojs | `WorkflowPublicationJats.vue` → `openFileInformationCenter` → `t('informationCenter.informationCenter')` | JATS file information center modal |
---

**Part 4 — Workflow-embedded managers (Vue)**

## ParticipantManager

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-466 | ojs,omp,ops | `src/managers/ParticipantManager/useParticipantManagerConfig.js` → `getTopItems` / `Actions.PARTICIPANT_ASSIGN` | Participants panel · "Assign" header button · opens legacy StageParticipantGrid `addParticipant` modal (guard: `if (canAdminister)` from `hasCurrentUserAtLeastOneAssignedRoleInStage(... [MANAGER, SITE_ADMIN, SUB_EDITOR])`) |
| AFFW-467 | ojs,omp,ops | `src/managers/ParticipantManager/ParticipantManager.vue` → `participantLogoutAs()` / `t('user.logOutAs')` | Participants panel · "Log out as" link row · ends login-as session (guard: `v-if="participantManagerStore.isUserLoggedInAs"`) Claimed by: login-and-sessions. |
| AFFW-468 | ojs,omp,ops | `src/managers/ParticipantManager/useParticipantManagerConfig.js` → `getItemActions` / `Actions.PARTICIPANT_EDIT` | Participants panel · row action "Edit" · opens legacy addParticipant modal with `assignmentId` (guard: `if (canAdminister && isEditable)` where `isEditable = canCurrentUserEditParticipant(...)`) |
| AFFW-469 | ojs,omp,ops | `src/managers/ParticipantManager/useParticipantManagerConfig.js` → `Actions.PARTICIPANT_NOTIFY` | Participants panel · row action "Notify" · opens legacy `viewNotify` modal (guard: none — always pushed) |
| AFFW-470 | ojs,omp,ops | `src/managers/ParticipantManager/useParticipantManagerConfig.js` → `Actions.PARTICIPANT_LOGIN_AS` | Participants panel · row action "Log in as" · confirm dialog then redirect to dashboard login-as URL (guard: `if (participant.canLoginAs)`) Claimed by: login-and-sessions. |
| AFFW-471 | ojs,omp,ops | `src/managers/ParticipantManager/useParticipantManagerConfig.js` → `Actions.PARTICIPANT_REMOVE` | Participants panel · row action "Remove" (warnable) · confirm dialog → POST `deleteParticipant` (guard: `if (canAdminister)`) |
| AFFW-472 | ojs,omp,ops | `src/managers/ParticipantManager/useParticipantManagerActions.js` → `participantRemove` dialog `actions` | Remove-participant dialog · "OK" / "Cancel" buttons · confirm or dismiss removal (guard: `modalStyle: 'negative'`) |
| AFFW-473 | ojs,omp,ops | `src/managers/ParticipantManager/useParticipantManagerActions.js` → `participantLoginAs` dialog `actions` | Log-in-as dialog · "OK" / "Cancel" buttons · confirm or dismiss identity switch Claimed by: login-and-sessions. |

## FileManager (workflow stage files)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-474 | ojs,omp,ops | `src/managers/FileManager/useFileManagerConfig.js` → `getTopItems` / `Actions.FILE_UPLOAD` | File list · "Upload" header button · opens legacy FileUploadWizard (guard: `enabledActions.includes(Actions.FILE_UPLOAD)` from `managerConfig.permittedActions`) |
| AFFW-475 | ojs,omp,ops | `src/managers/FileManager/useFileManagerConfig.js` → `getTopItems` / `Actions.FILE_SELECT_UPLOAD` | File list · "Upload/Select files" header button · opens grid `selectFiles` modal (guard: `enabledActions.includes(Actions.FILE_SELECT_UPLOAD)`) |
| AFFW-476 | ojs,omp,ops | `src/managers/FileManager/useFileManagerConfig.js` → `getBottomItems` / `Actions.FILE_DOWNLOAD_ALL` | File list · "Download all" bottom link · `window.location.href` to `downloadAllFiles` (guard: `enabledActions.includes(Actions.FILE_DOWNLOAD_ALL) && filesCount`) |
| AFFW-477 | ojs,omp,ops | `src/managers/FileManager/useFileManagerConfig.js` → `getColumns` / `FileManagerCellSelect` | File list · per-row select checkbox · `fileManagerStore.toggleSelectedFile(file)` for selection sets (guard: `enabledActions.includes(Actions.FILE_SELECT)` — `*_SELECT` namespaces) |
| AFFW-478 | ojs,omp,ops | `src/managers/FileManager/FileManagerCellFileName.vue` → `file.url` anchor | File list · file-name link · opens/downloads the file in new tab (guard: `v-if="file.url"`) |
| AFFW-479 | ojs,omp,ops | `src/managers/FileManager/useFileManagerConfig.js` → `getItemActions` / `Actions.FILE_SEND_TO_EDITOR` | File list · row action "Send to text editor" · opens `WorkflowVersionDialogBody` in `sendToTextEditor` mode (guard: `enabledActions.includes(Actions.FILE_SEND_TO_EDITOR) && PANDOC_IMPORT_EXTENSIONS.includes(ext)`) |
| AFFW-480 | ojs,omp,ops | `src/managers/FileManager/useFileManagerConfig.js` → `Actions.FILE_EDIT` | File list · row action "Update file" · legacy `ManageFileApiHandler::editMetadata` modal (guard: `enabledActions.includes(Actions.FILE_EDIT)`) |
| AFFW-481 | ojs,omp,ops | `src/managers/FileManager/useFileManagerConfig.js` → `Actions.FILE_SEE_NOTES` | File list · row action "More information" · opens FileInformationCenter modal (guard: `enabledActions.includes(Actions.FILE_SEE_NOTES)`) |
| AFFW-482 | ojs,omp,ops | `src/managers/FileManager/useFileManagerConfig.js` → `Actions.FILE_DELETE` | File list · row action "Delete" (warnable) · confirm dialog → POST `deleteFile` (guard: `enabledActions.includes(Actions.FILE_DELETE)`) |
| AFFW-483 | ojs,omp,ops | `src/managers/FileManager/useFileManagerActions.js` → `fileDelete` dialog `actions` | Delete-file dialog · "OK" / "Cancel" buttons · confirm or dismiss deletion |
| AFFW-484 | ojs,omp,ops | `src/managers/FileManager/modals/useFileMetadataForm.js` → `submitButton` / `cancelButton` | File metadata form modal · "Save" / "Cancel" buttons · submit or cancel submission-file metadata (guard: `showButtons ? {label: t('common.save')} : null`) |
| AFFW-485 | ojs,omp,ops | `src/managers/FileManager/modals/FileMetadataForm.vue` → `wizardAdvanceRequested.submissionFileMetadata` handler | File metadata form modal · legacy wizard "Continue" bridge · triggers `pkpFormRef.submit()` (guard: `if (!$container.length) return` on `[data-submission-file-metadata-wrapper]`) |

## ReviewerManager

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-486 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `getTopItems` / `Actions.REVIEWER_ADD_REVIEWER` | Reviewers table · "Add reviewer" header button · opens legacy `ReviewerGridHandler::showReviewerForm` (guard: `if (redactedForAuthors) return []`) Claimed by: reviewer-assignment-and-management. |
| AFFW-487 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `getItemPrimaryActions` / `Actions.REVIEWER_READ_REVIEW_BY_AUTHOR` | Reviewers table · row primary "Read review" (author view) · opens `AuthorReviewerGridHandler::readReview` (guard: `redactedForAuthors` and status in COMPLETE/THANKED/RECEIVED/VIEWED) Claimed by: review-stage-and-rounds. |
| AFFW-488 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_SEND_REMINDER` | Reviewers table · row primary "Send reminder" · legacy `sendReminder` modal (guard: status in RESPONSE_OVERDUE/REVIEW_OVERDUE) Claimed by: reviewer-assignment-and-management. |
| AFFW-489 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_THANK_REVIEWER` | Reviewers table · row primary "Thank reviewer" · legacy thank-reviewer modal (guard: `statusId === REVIEW_ASSIGNMENT_STATUS_COMPLETE`) Claimed by: reviewer-assignment-and-management. |
| AFFW-490 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_REVERT_CONSIDER` | Reviewers table · row primary "Revert decision" · confirm dialog → revert consider (guard: `statusId === COMPLETE` or `=== THANKED`) Claimed by: reviewer-assignment-and-management. |
| AFFW-491 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_READ_REVIEW` | Reviewers table · row primary "Read review" · `ReviewerGridHandler::readReview` modal (guard: `statusId` in RECEIVED/VIEWED) Claimed by: reviewer-assignment-and-management. |
| AFFW-492 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `getItemActions` / `Actions.REVIEWER_REVIEW_DETAILS` | Reviewers table · row action "Review details" · legacy review-details modal (guard: `statusId !== REVIEW_ASSIGNMENT_STATUS_CANCELLED`) Claimed by: reviewer-assignment-and-management. |
| AFFW-493 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_EMAIL_REVIEWER` | Reviewers table · row action "Email reviewer" · legacy email modal (guard: none — always pushed) Claimed by: reviewer-assignment-and-management. |
| AFFW-494 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_RESEND_REQUEST` | Reviewers table · row action "Resend request" · legacy resend-request modal (guard: `statusId === REVIEW_ASSIGNMENT_STATUS_DECLINED`) Claimed by: reviewer-assignment-and-management. |
| AFFW-495 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_EDIT_REVIEW` | Reviewers table · row action "Edit" · legacy edit-review modal (guard: `statusId !== CANCELLED`) Claimed by: reviewer-assignment-and-management. |
| AFFW-496 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_CANCEL_REVIEWER` / `Actions.REVIEWER_UNASSIGN_REVIEWER` | Reviewers table · row action "Cancel reviewer"/"Unassign reviewer" (warnable) · label+action swap on `reviewAssignment.dateConfirmed` (guard: `statusId !== CANCELLED`) Claimed by: reviewer-assignment-and-management. |
| AFFW-497 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_REINSTATE_REVIEWER` | Reviewers table · row action "Reinstate reviewer" · reinstate a cancelled assignment (guard: `else` branch of `statusId !== CANCELLED`) Claimed by: reviewer-assignment-and-management. |
| AFFW-498 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_REVIEW_HISTORY` | Reviewers table · row action "History" · legacy `reviewHistory` modal (guard: none — always pushed) Claimed by: reviewer-assignment-and-management. |
| AFFW-499 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_LOGIN_AS` | Reviewers table · row action "Log in as" · confirm + redirect (guard: `if (reviewAssignment.canLoginAs)`) Claimed by: login-and-sessions. |
| AFFW-500 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_EDITORIAL_NOTES` | Reviewers table · row action "Gossip" · legacy editorial-notes modal (guard: `if (reviewAssignment.canGossip)`) Claimed by: reviewer-assignment-and-management. |
| AFFW-501 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_LOG_RESPONSE` | Reviewers table · row action "Log response" · opens `WorkflowLogResponseModal` (guard: `if (!reviewAssignment.dateConfirmed)`) Claimed by: reviewer-assignment-and-management. |
| AFFW-502 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `Actions.REVIEWER_SEND_TO_ORCID` | Reviewers table · row action "Send review to ORCID" · confirm dialog → ORCID deposit (guard: `reviewAssignment.reviewerHasOrcid && pkp.const.REVIEW_ASSIGNMENT_STATUS_COMPLETE`) Claimed by: orcid-integration. |
| AFFW-503 | ojs | `src/managers/ReviewerManager/useReviewerManagerConfig.js` → `getRecommendationString` | Reviewers table · status-cell recommendation text · null when recommendations absent (guard: `if (recommendations === undefined) return null` — comment: "only available for OJS, not in OMP and OPS") Claimed by: reviewer-assignment-and-management. |
| AFFW-504 | ojs,omp,ops | `src/managers/ReviewerManager/ReviewerManagerReadReviewModal.vue` → `exportOptions` / `handleExport` | Read-review modal · "Download" dropdown with 4 items (author-only PDF/XML, all-sections PDF/XML) · fetches `export-pdf`/`export-xml` and clicks a temp anchor Claimed by: reviewer-assignment-and-management. |
| AFFW-505 | ojs,omp,ops | `src/managers/ReviewerManager/modals/WorkflowLogResponseModal.vue` → `store.form` / `store.formSuccess` | Log-response side modal · PkpForm submit (buttons from `componentForms.logResponseForm`) · POSTs to `reviews/{id}/{reviewAssignmentId}/confirmReview` Claimed by: reviewer-assignment-and-management. |
| AFFW-506 | ojs,omp,ops | `src/managers/ReviewerManager/useReviewerManagerActions.js` → `reviewerRevertConsider` / `reviewerSendToOrcid` dialog `actions` | Confirm dialogs · "OK" / "Cancel" buttons · confirm or dismiss revert / ORCID deposit Claimed by: reviewer-assignment-and-management. |

## DiscussionManager (editorial-facing)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-507 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionManagerConfig.js` → `getTopItems` / `Actions.TASKS_AND_DISCUSSIONS_ADD` | Tasks & discussions table · "Add" header button · opens `DiscussionManagerFormModal` (guard: `enabledActions.includes(Actions.TASKS_AND_DISCUSSIONS_ADD)` from `config.permittedActions`) |
| AFFW-508 | ojs,omp,ops | `src/managers/DiscussionManager/DiscussionManagerCellName.vue` → `discussionManagerStore.discussionView({workItem})` | Tasks & discussions table · row title link · opens `DiscussionManagerFormDisplayModal` (guard: none) |
| AFFW-509 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionManagerConfig.js` → `getItemActions` / `Actions.TASKS_AND_DISCUSSIONS_EDIT` | Tasks & discussions table · row action "Edit" · opens form modal in edit mode (guard: `if (currentUserHasWriteAccess)` from `userHasWriteAccess`; `disabled: !!workItem?.dateClosed`) |
| AFFW-510 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionManagerConfig.js` → `Actions.TASKS_AND_DISCUSSIONS_ADD_TASK_DETAILS` | Tasks & discussions table · row action "Add task details" · edit modal with `autoAddTaskDetails: true` (guard: `workItem.type === EDITORIAL_TASK_TYPE_DISCUSSION && workItem.status === EDITORIAL_TASK_STATUS_IN_PROGRESS && currentUserHasWriteAccess`) |
| AFFW-511 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionManagerConfig.js` → `Actions.TASKS_AND_DISCUSSIONS_HISTORY` | Tasks & discussions table · row action "History" · opens `DiscussionManagerHistoryModal` (guard: `config.permittedActions.includes(...HISTORY) && currentUserHasWriteAccess`) |
| AFFW-512 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionManagerConfig.js` → `Actions.TASKS_AND_DISCUSSIONS_DELETE` | Tasks & discussions table · row action "Delete" (warnable) · confirm dialog → DELETE `submissions/{id}/tasks/{taskId}` (guard: `config.permittedActions.includes(...DELETE) && currentUserHasWriteAccess`) |
| AFFW-513 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionManagerActions.js` → `discussionDelete` dialog `actions` | Delete-discussion dialog · "OK" / "Cancel" buttons · confirm or dismiss deletion |
| AFFW-514 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionManagerForm.js` → `submitButton` / `cancelButton` (page `default`) | Discussion/task form modal · "Save" / "Cancel" buttons · persist or discard the work item |
| AFFW-515 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionManagerForm.js` → radio options `discussion.form.startTaskUponSaving` / `discussion.form.createDontStartTask` | Discussion/task form modal · start-task radio choice · start the task on save or create without starting |
| AFFW-516 | ojs,omp,ops | `src/managers/DiscussionManager/DiscussionManagerTemplates.vue` → `emit('selectTemplate', template)` + search field | Discussion/task form modal · template search + per-template select button · prefills the form from a task template (guard: `v-if="!inDisplayMode"`, list `v-if="taskTemplatesData.length > 0"`) |
| AFFW-517 | ojs,omp,ops | `src/managers/DiscussionManager/DiscussionManagerTaskInfo.vue` → `statusUpdateValue` checkbox | Discussion/task form modal · task status checkbox (start/close task) · toggles status on save (guard: `v-if="discussionManagerStore.userHasWriteAccess({workItem}) && showStatusUpdateCheckbox"`, `:disabled="isStatusCheckboxDisabled"`) |
| AFFW-518 | ojs,omp,ops | `src/managers/DiscussionManager/DiscussionManagerDiscussion.vue` → `t('discussion.closeThisDiscussion')` checkbox | Discussion display modal · "Close this discussion" checkbox · marks discussion closed (guard: `v-if="userHasWriteAccess({workItem}) && showCloseDiscussion"` where `showCloseDiscussion = inDisplayMode && type === EDITORIAL_TASK_TYPE_DISCUSSION`) |
| AFFW-519 | ojs,omp,ops | `src/managers/DiscussionManager/DiscussionMessages.vue` → `addMessage` / `t('discussion.addNewMessage')` | Discussion display modal · "Add new message" button · reveals the rich-text message field (guard: `v-if="hasAccessToAddMessage"`, `:is-disabled="showNewMessageField"`) |
| AFFW-520 | ojs,omp,ops | `src/managers/DiscussionManager/useDiscussionMessages.js` → `t('common.attachFiles')` → `FileAttacherModal` | Discussion display modal · "Attach files" button · opens FileAttacher (`FileAttacherUpload`, `FileAttacherWorkflowStage`) with "Add file"/"Attach selected" actions |
| AFFW-521 | ojs,omp,ops | `src/managers/DiscussionManager/DiscussionMessages.vue` → `FileAttacherAttachedFiles` `@remove-file="onRemoveFile"` | Discussion display modal · per-attachment remove control · drops a staged attachment (guard: `v-if="showNewMessageField"`) |
| AFFW-522 | ojs,omp,ops | `src/managers/DiscussionManager/DiscussionManagerFormDisplayModal.vue` → `editForm` / `t('common.edit')` | Discussion display modal · "Edit" header action · reopens item in `DiscussionManagerFormModal` (guard: `v-if="discussionManagerStore.userHasWriteAccess({workItem})"`, `:disabled="isWorkItemClosed \|\| isLoadingWorkItem"`) |
| AFFW-523 | ojs,omp,ops | `src/managers/DiscussionManager/DiscussionManagerHistoryModal.vue` → `activity.settings.downloadUrl` | Discussion history modal · per-activity "Download" link · downloads the logged file (guard: `v-if="activity.settings?.downloadUrl"`) |

## GalleyManager

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-524 | ojs,ops | `src/managers/GalleyManager/useGalleyManagerConfig.js` → `getTopItems` / `GalleyManagerSortButton` | Galleys table · "Order"/"Save ordering" toggle button · enters/saves drag ordering (guard: `config.permittedActions.includes(Actions.GALLEY_SORT) && galleys.value.length`) |
| AFFW-525 | ojs,ops | `src/managers/GalleyManager/useGalleyManagerConfig.js` → `getBottomItems` / `Actions.GALLEY_ADD` | Galleys table · "Add galley" bottom link · opens legacy galley grid `addGalley` (guard: `config.permittedActions.includes(Actions.GALLEY_ADD)`) |
| AFFW-526 | ojs,ops | `src/managers/GalleyManager/GalleyManagerCellActions.vue` → `TableCellOrder` `@up`/`@down` | Galleys table · per-row move up / move down · reorders galleys while sorting (guard: `v-if="galleyManagerStore.sortingEnabled"`) |
| AFFW-527 | ojs,ops | `src/managers/GalleyManager/GalleyManagerCellName.vue` → `galley.file.url` anchor | Galleys table · galley file link · opens the galley file (guard: `v-if="galley?.file?.url"`) |
| AFFW-528 | ojs,ops | `src/managers/GalleyManager/useGalleyManagerConfig.js` → `getItemActions` / `Actions.GALLEY_EDIT` | Galleys table · row action "Edit"/"View" · label+icon swap on publication status (guard: `config.permittedActions.includes(Actions.GALLEY_EDIT)`; label `publication.status === STATUS_PUBLISHED ? view : edit`) |
| AFFW-529 | ojs,ops | `src/managers/GalleyManager/useGalleyManagerConfig.js` → `Actions.GALLEY_CHANGE_FILE` | Galleys table · row action "Change file" · opens legacy change-file modal (guard: `config.permittedActions.includes(Actions.GALLEY_CHANGE_FILE)`) |
| AFFW-530 | ojs,ops | `src/managers/GalleyManager/useGalleyManagerConfig.js` → `Actions.GALLEY_MORE_INFO` | Galleys table · row action "More information" · opens FileInformationCenter (guard: `config.permittedActions.includes(Actions.GALLEY_MORE_INFO) && galley.submissionFileId`) |
| AFFW-531 | ojs,ops | `src/managers/GalleyManager/useGalleyManagerConfig.js` → `Actions.GALLEY_DELETE` | Galleys table · row action "Delete" (warnable) · confirm dialog → delete galley (guard: `config.permittedActions.includes(Actions.GALLEY_DELETE)`) |

## ContributorManager (workflow Contributors tab host)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-532 | ojs,omp,ops | `src/managers/ContributorManager/ContributorManager.vue` → hosts `ContributorsListPanel` with `canEditPublication` from prop `canEdit` | Contributors tab · manager wrapper · all controls are the ContributorsListPanel atoms in Part 2 (Order/Cancel/Preview/Add/Orderer/set-primary/Edit/Delete/edit modal) |

## CitationManager

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-533 | ojs,omp,ops | `src/managers/CitationManager/useCitationManagerFormAddRawCitation.js` → `submitButton` `t('common.add')` (`CitationManagerAddRawCitations.vue`) | Citations panel · raw-citations "Add" form button · POSTs pasted raw citations |
| AFFW-534 | ojs,omp,ops | `src/managers/CitationManager/CitationManager.vue` → `citationStore.deleteAllCitations` | Citations panel · "Delete all" link button · confirm dialog → DELETE `deleteCitationsByPublicationId` (guard: `:is-disabled="!citationStore.canEditPublication"`) |
| AFFW-535 | ojs,omp,ops | `src/managers/CitationManager/CitationManager.vue` → `citationStore.reprocessAllCitations` | Citations panel · "Reprocess all citations" link button · confirm dialog → POST `reprocessCitationsByPublicationId` (guard: `v-show="citationStore.citationsMetadataLookup"`, `:is-disabled="!citationStore.canEditPublication"`) |
| AFFW-536 | ojs,omp,ops | `src/managers/CitationManager/useCitationManagerConfig.js` → `getTopItems` / `CitationManagerSearchField` | Citations table · search field · `citationStore.setSearchPhrase` filters `citationsFiltered` |
| AFFW-537 | ojs,omp,ops | `src/managers/CitationManager/CitationManagerToggleAll.vue` → `citationStore.toggleAllItemsExpansion` | Citations table · "Expand all"/"Collapse all" header button · toggles all structured-citation detail rows (guard: `v-if="citationStore.citationsMetadataLookup"`) |
| AFFW-538 | ojs,omp,ops | `src/managers/CitationManager/CitationManagerCellToggle.vue` → `TableCellTreeExpand` `@toggle` | Citations table · per-row expander · expands structured citation metadata (guard: `:is-displayed="citationStore.citationsMetadataLookup && citation.isStructured"`) |
| AFFW-539 | ojs,omp,ops | `src/managers/CitationManager/useCitationManagerConfig.js` → `getItemActions` / `Actions.CITATION_EDIT_CITATION` | Citations table · row action "Edit" · opens `CitationEditModal` (guard: none — always pushed) |
| AFFW-540 | ojs,omp,ops | `src/managers/CitationManager/useCitationManagerConfig.js` → `Actions.CITATION_DELETE_CITATION` | Citations table · row action "Delete" (warnable) · confirm dialog → delete single citation (guard: none — always pushed) |
| AFFW-541 | ojs,omp,ops | `src/managers/CitationManager/useCitationManagerConfig.js` → `Actions.CITATION_REPROCESS_CITATION` | Citations table · row action "Reprocess" · re-runs metadata lookup for one citation (guard: `if (store.citationsMetadataLookup.value)` and `if (!citation.isStructured)`) |
| AFFW-542 | ojs,omp,ops | `src/managers/CitationManager/modals/CitationEditModal.vue` → `PkpForm` `@success` → `closeModal()` | Citation edit modal · form save/cancel buttons · saves citation and closes the side modal |
| AFFW-543 | ojs,omp,ops | `src/managers/CitationManager/citationManagerStore.js` → `deleteAllCitations` / `reprocessAllCitations` dialog `actions` | Citations bulk confirm dialogs · "OK" / "Cancel" buttons · confirm or dismiss delete-all / reprocess-all |

## DataCitationManager

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-544 | ojs,omp,ops | `src/managers/DataCitationManager/useDataCitationManagerConfig.js` → `getTopItems` / `Actions.DATA_CITATION_ADD_DATA_CITATION` | Data citations table · "Add data citation" header button · opens `DataCitationEditModal` (POST) (guard: none — always pushed) |
| AFFW-545 | ojs,omp,ops | `src/managers/DataCitationManager/DataCitationManagerSortButton.vue` → `startSorting` / `saveSorting` | Data citations table · "Order"/"Save ordering" header button · toggles and persists row ordering |
| AFFW-546 | ojs,omp,ops | `src/managers/DataCitationManager/DataCitationManagerCellActions.vue` → `TableCellOrder` `@up`/`@down` | Data citations table · per-row move up / move down · reorders data citations (guard: `v-if="dataCitationManagerStore.sortingEnabled"`) |
| AFFW-547 | ojs,omp,ops | `src/managers/DataCitationManager/useDataCitationManagerConfig.js` → `getItemActions` / `Actions.DATA_CITATION_EDIT_DATA_CITATION` | Data citations table · row action "Edit" · opens `DataCitationEditModal` (PUT) (guard: none) |
| AFFW-548 | ojs,omp,ops | `src/managers/DataCitationManager/useDataCitationManagerConfig.js` → `Actions.DATA_CITATION_DELETE_DATA_CITATION` | Data citations table · row action "Delete" (warnable) · confirm dialog → DELETE `.../dataCitations/{id}` (guard: none) |
| AFFW-549 | ojs,omp,ops | `src/managers/DataCitationManager/useDataCitationManagerActions.js` → `dataCitationDeleteDataCitation` dialog `actions` | Delete-data-citation dialog · "OK" / "Cancel" buttons · confirm or dismiss deletion |
| AFFW-550 | ojs,omp,ops | `src/managers/DataCitationManager/modals/DataCitationEditModal.vue` → `PkpForm` `@success` | Data citation add/edit modal · form save/cancel buttons · submits `dataCitationEditForm` |

## FunderManager

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-551 | ojs,omp,ops | `src/managers/FunderManager/useFunderManagerConfig.js` → `getTopItems` / `FunderManagerSortButton` | Funders table · "Order"/"Save ordering" header button (icon `Sort`) · toggles and persists ordering |
| AFFW-552 | ojs,omp,ops | `src/managers/FunderManager/useFunderManagerConfig.js` → `getTopItems` / `Actions.FUNDERS_ADD_FUNDER` | Funders table · "Add funder" header button · opens `FunderEditModal` (POST) (guard: none — always pushed) |
| AFFW-553 | ojs,omp,ops | `src/managers/FunderManager/FunderManagerCellActions.vue` → `TableCellOrder` `@up`/`@down` | Funders table · per-row move up / move down · reorders funders (guard: `v-if="funderManagerStore.sortingEnabled"`) |
| AFFW-554 | ojs,omp,ops | `src/managers/FunderManager/useFunderManagerConfig.js` → `getItemActions` / `Actions.FUNDERS_EDIT_FUNDER` | Funders table · row action "Edit" · opens `FunderEditModal` (PUT) prefilled with funder + grants (guard: none) |
| AFFW-555 | ojs,omp,ops | `src/managers/FunderManager/useFunderManagerConfig.js` → `Actions.FUNDERS_DELETE_FUNDER` | Funders table · row action "Delete" (warnable) · confirm dialog → DELETE `.../funders/{id}` (guard: none) |
| AFFW-556 | ojs,omp,ops | `src/managers/FunderManager/useFunderManagerActions.js` → `fundersDeleteFunder` dialog `actions` | Delete-funder dialog · "OK" / "Cancel" buttons · confirm or dismiss deletion |
| AFFW-557 | ojs,omp,ops | `src/managers/FunderManager/modals/FunderEditModal.vue` → `PkpForm` `@success` | Funder add/edit modal · form save/cancel buttons · submits `funderEditForm` (funder ROR + grants) |

## MediaFileManager

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-558 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerConfig.js` → `getTopItems` / `Actions.MEDIA_FILE_BATCH_LINK_IMAGES` | Media files table · "Batch link media" header button · opens `MediaFileManagerBatchLinkImagesModal` (guard: `enabledActions.includes(Actions.MEDIA_FILE_BATCH_LINK_IMAGES)`) |
| AFFW-559 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerConfig.js` → `getTopItems` / `Actions.MEDIA_FILE_ADD` | Media files table · "Add media file" header button · opens `MediaFileManagerAddFileModal` (guard: `enabledActions.includes(Actions.MEDIA_FILE_ADD)`) |
| AFFW-560 | ojs,omp,ops | `src/managers/MediaFileManager/MediaFileManagerCellName.vue` → `mediaFile.url` anchor | Media files table · file-name link · opens the media file |
| AFFW-561 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerConfig.js` → `getItemActions` / `Actions.MEDIA_FILE_INFO` | Media files table · row action "More information" · opens FileInformationCenter modal (guard: `config.permittedActions.includes(Actions.MEDIA_FILE_INFO)`) |
| AFFW-562 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerConfig.js` → `Actions.MEDIA_FILE_EDIT_METADATA` | Media files table · row action "Edit metadata" · opens `MediaFileManagerMetadataFormModal` (guard: `config.permittedActions.includes(Actions.MEDIA_FILE_EDIT_METADATA)`) |
| AFFW-563 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerConfig.js` → `Actions.MEDIA_FILE_MANUALLY_LINK_IMAGE` | Media files table · row action "Manually link media" · opens `MediaFileManagerManualLinkImageFormModal` (guard: `config.permittedActions.includes(...) && mediaFile.genreSupportsFileVariants`) |
| AFFW-564 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerConfig.js` → `Actions.MEDIA_FILE_DELETE` | Media files table · row action "Delete file" (warnable) · confirm dialog → DELETE `.../mediaFiles/{id}` (guard: `config.permittedActions.includes(Actions.MEDIA_FILE_DELETE)`) |
| AFFW-565 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerActions.js` → `mediaFileDelete` dialog `actions` | Delete-media-file dialog · "OK" / "Cancel" buttons · confirm or dismiss deletion |
| AFFW-566 | ojs,omp,ops | `src/managers/MediaFileManager/MediaFileManagerAddFileModal.vue` → `FileMediaUploader` `openFileBrowser` / drop zone | Add media file modal · drag-drop zone + "Click to upload files" · stages temporary files (guard: `v-if="mediaFileManagerStore.genreOptions?.length"`, else "no media types" text) |
| AFFW-567 | ojs,omp,ops | `src/components/FileMediaUploader/FileMediaUploader.vue` → `removeFile(file.id)` | Add media file modal · per-staged-file remove button · drops a queued upload |
| AFFW-568 | ojs,omp,ops | `src/components/FileMediaUploader/FileMediaUploader.vue` → `submit` / `t('common.upload.addFiles')` | Add media file modal · "Add files" primary button · commits uploads (guard: `:is-disabled="!canSubmit"`; per-file media-type and resolution-type selects are required) |
| AFFW-569 | ojs,omp,ops | `src/managers/MediaFileManager/MediaFileManagerBatchLinkImagesModal.vue` → `handleLinkMedia` / `closeModal()` | Batch link images modal · "Link media" / "Cancel" buttons · applies high-res selections per web-version row (guard: `:disabled="!webVersionFiles?.length"`) |
| AFFW-570 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerManualLinkImageFormModal.js` → `submitButton` / `cancelButton` | Manual link image modal · "Link media" / "Cancel" buttons · links a selected media file as variant |
| AFFW-571 | ojs,omp,ops | `src/managers/MediaFileManager/useMediaFileManagerMetadataFormModal.js` → `submitButton` / `cancelButton` | Media file metadata modal · "Save" / "Cancel" buttons · saves name/caption/credit/copyright/etc. |

## ChapterManager (OMP)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-572 | omp | `src/managers/ChapterManager/ChapterManager.vue` → `GridWrapper` `grid-component="grid.users.chapter.ChapterGridHandler"` | Chapters tab · entire UI delegated to legacy `ChapterGridHandler` grid · all buttons/row actions come from the PHP grid, none declared in Vue (params: `submissionId`, `publicationId`) |

## PublicationFormatManager (OMP)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-573 | omp | `src/managers/PublicationFormatManager/PublicationFormatManager.vue` → `GridWrapper` `grid-component="grid.catalogEntry.PublicationFormatGridHandler"` | Publication Formats tab · entire UI delegated to legacy `PublicationFormatGridHandler` grid · no controls declared in Vue (params: `submissionId`, `publicationId`; `data-cy="publication-format-manager"`) |

## RepresentativeManager (OMP)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-574 | omp | `src/managers/RepresentativeManager/RepresentativeManager.vue` → `GridWrapper` `grid-component="grid.catalogEntry.RepresentativesGridHandler"` | Representatives tab · entire UI delegated to legacy `RepresentativesGridHandler` grid · no controls declared in Vue (params: `submissionId`) |

## ReviewerSuggestionManager

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-575 | ojs,omp | `src/managers/ReviewerSuggestionManager/useReviewerSuggestionManagerActions.js` → `getItemActions` / `Actions.REVIEWER_SUGGESTION_APPROVE` | Reviewer suggestions panel (workflow) · row action "Add reviewer" · opens `ReviewerGridHandler::showReviewerForm` with `reviewerSuggestionId` and computed `selectionType` (guard: dropdown rendered `v-if="reviewerSuggestionManagerStore.atActiveReviewStage()"` = `reviewRoundId && submissionStageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW && submissionStageId === submission.stageId`) |
| AFFW-576 | ojs,omp | `src/managers/ReviewerSuggestionManager/ReviewerSuggestionManager.vue` → root `v-if` | Reviewer suggestions panel (workflow) · whole panel visibility · hidden when there are no suggestions (guard: `v-if="reviewerSuggestionManagerStore.reviewerSuggestionsList.length > 0"`) |

## ReviewRoundResponseManager — AuthorResponseManager (author-side card)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-577 | ojs | `src/managers/ReviewRoundResponseManager/AuthorResponseManager/AuthorResponseManager.vue` → `openReviewResponseFormModal` | Author response card · "Submit response"/"View submitted response" button · opens `AuthorResponseFormModal` (guard: label switches on `!!reviewRound.authorResponse`) |
| AFFW-578 | ojs | `src/managers/ReviewRoundResponseManager/AuthorResponseManager/AuthorResponseManager.vue` → `watch(isReady)` + `queryParamsUrl.reviewResponseAction === 'respond'` | Author response card · auto-open from email link · opens the response modal when data is ready (guard: `if (ready && queryParamsUrl?.reviewResponseAction === 'respond')`) |

## ReviewRoundResponseManager — AuthorResponseRequestManager (editor-side table)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-579 | ojs | `src/managers/ReviewRoundResponseManager/AuthorResponseRequestManager/useReviewRoundAuthorResponseConfig.js` → `getTopItems` / `navigateToRequestAuthorReviewResponsePage` | Author response table · "Request response" header button · redirects to `reviewResponse/requestAuthorResponse` (guard: `:is-disabled="!store.canRequestReviewRoundAuthorResponse"`) |
| AFFW-580 | ojs | `src/managers/ReviewRoundResponseManager/AuthorResponseRequestManager/useReviewRoundAuthorResponseConfig.js` → `getAuthorItemActions` / `Actions.RESPONSE_VIEW` | Author response table · row action "View" · opens `AuthorResponseFormModal` for the submitted response (guard: `if (!response) return []`; `disabled: submittingUser.id !== authorUser.id`) |
| AFFW-581 | ojs | `src/managers/ReviewRoundResponseManager/AuthorResponseRequestManager/useReviewRoundAuthorResponseConfig.js` → `Actions.RESPONSE_DELETE` | Author response table · row action "Delete" (warnable) · confirm dialog → DELETE `reviews/{id}/{roundId}/authorResponse/{responseId}` (guard: `if (!response) return []`; `disabled: submittingUser.id !== authorUser.id`) |
| AFFW-582 | ojs | `src/managers/ReviewRoundResponseManager/AuthorResponseRequestManager/useReviewRoundAuthorResponseConfig.js` → `getColumns` `AuthorResponseRequestManagerCellMoreActions` | Author response table · actions column visibility · dropdown column only rendered once a response exists (guard: `if (reviewRound.value.authorResponse)`) |
| AFFW-583 | ojs | `src/managers/ReviewRoundResponseManager/AuthorResponseRequestManager/AuthorResponseRequestManagerStore.js` → `responseDelete` dialog `actions` | Delete-response dialog · "OK" / "Cancel" buttons · confirm or dismiss response deletion (`modalStyle: 'negative'`) |
| AFFW-584 | ojs | `src/managers/ReviewRoundResponseManager/useAuthorResponseForm.js` → `submitButton` / `cancelButton` | Author response form modal · "Save" (editor) / "Submit" (author) and "Cancel" buttons · POST or PUT `reviews/{id}/{roundId}/authorResponse` (guard: submit label `isEditor.value ? t('common.save') : t('submission.reviewRound.authorReviewResponse.submit')`; `method: reviewRound.authorResponse ? 'PUT' : 'POST'`) |
| AFFW-585 | ojs | `src/managers/ReviewRoundResponseManager/AuthorResponseFormModal.vue` → `isAuthor` computed | Author response form modal · title/description variants · author vs editor wording, incl. "author cannot edit" notice (guard: `hasCurrentUserAtLeastOneAssignedRoleInStage(submission, stageId, [ROLE_ID_AUTHOR])` and `!!reviewRound.authorResponse`) |
---

**Part 5 — Legacy Smarty layer (grids, modals, forms)**

## File upload wizard (legacy modal)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-586 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/fileUploadWizard.tpl` · tab link `submission.submit.uploadStep` | Upload file wizard · step-1 tab "Upload File" · jQuery-UI tab anchor to `displayFileUploadForm` |
| AFFW-587 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/fileUploadWizard.tpl` · tab link `href="metadata"` | Upload file wizard · step-2 tab "Review Details" · local tab anchor |
| AFFW-588 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/fileUploadWizard.tpl` · tab link `href="finish"` | Upload file wizard · step-3 tab "Confirm" · local tab anchor |
| AFFW-589 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/fileUploadWizard.tpl` · handler opts `cancelButtonText/continueButtonText/finishButtonText` | Upload file wizard · Cancel / Continue / Complete wizard buttons · injected by FileUploadWizardHandler from these translate keys |
| AFFW-590 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/form/fileUploadForm.tpl` · `<form id={$uploadFormId}>` | Upload file wizard step 1 · upload form (action `#`, posts via uploader) |
| AFFW-591 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/form/fileUploadForm.tpl` · select `revisedFileId` | Upload file wizard step 1 · "file to revise" selector (guard: `{elseif $showFileSelector}`, mandatory when `$revisionOnly`) |
| AFFW-592 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/form/fileUploadForm.tpl` · select `genreId` | Upload file wizard step 1 · file-component/genre selector (guard: `{if $showGenreSelector}`) |
| AFFW-593 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/form/fileUploadForm.tpl` · `{include linkAction/linkAction.tpl action=$ensuringLink}` | Upload file wizard step 1 · "Ensuring a blind review" link action (guard: `{if $ensuringLink}`) |
| AFFW-594 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/form/fileUploadForm.tpl` · `{translate key="submission.upload.noAvailableReviewFiles"}` | Upload file wizard step 1 · no-form fallback message (guard: `{if $revisionOnlyWithoutFileOptions}`) |
| AFFW-595 | ojs,omp,ops | `lib/pkp/templates/controllers/fileUploadContainer.tpl` · `<button id={$browseButton}>` (`common.upload.addFile` / `common.upload.changeFile`) | Any legacy upload widget · Add/Change file browse button + drag-and-drop zone |
| AFFW-596 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl` · mount `#{$metadataMountId}` (FileMetadataForm Vue app) | Upload file wizard step 2 · file metadata form (Save/Cancel emitted as `formSubmitted`/`formCanceled`) |
| AFFW-597 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl` · `load_url_in_div id="dependentFilesGridDiv"` | Upload file wizard step 2 · dependent-files grid block (guard: `{if $supportsDependentFiles}`) |
| AFFW-598 | ojs,omp,ops | `lib/pkp/templates/controllers/wizard/fileUpload/form/fileSubmissionComplete.tpl` · `<button id="newFile" name="newFile">` `submission.submit.newFile` | Upload file wizard step 3 · "Add Another File" button (guard: `{if $fileStage != SUBMISSION_FILE_PROOF}`) |

## Submission file metadata / identifiers tabset

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-599 | ojs,omp,ops | `lib/pkp/templates/controllers/api/file/editMetadata.tpl` · tab link `grid.action.editMetadata` | Edit submission file modal · "Edit Metadata" tab anchor (`editMetadataTab`) |
| AFFW-600 | ojs,omp,ops | `lib/pkp/templates/controllers/api/file/editMetadata.tpl` · tab link `submission.identifiers` | Edit submission file modal · "Identifiers" tab anchor (guard: `{if $showIdentifierTab}`) |
| AFFW-601 | ojs | `ojs-main/templates/controllers/tab/pubIds/form/publicIdentifiersForm.tpl` · `<form id="publicIdentifiersForm">` + `{fbvFormButtons id="publicIdentifiersFormSubmit" submitText="common.save"}` | Identifiers tab · Save; form action varies by `{if $pubObject instanceof Submission / Galley / SubmissionFile / else Issue}`; Save disabled via `submitDisabled=$formDisabled` |
| AFFW-602 | ojs | `ojs-main/templates/controllers/tab/pubIds/form/publicIdentifiersForm.tpl` · publisher-id field block | Identifiers tab · publisher ID input (guard: `{if $enablePublisherId}`) |
| AFFW-603 | ops | `ops-main/templates/controllers/tab/pubIds/form/publicIdentifiersForm.tpl` · `<form id="publicIdentifiersForm">` + `publicIdentifiersFormSubmit` | Identifiers tab (preprint/galley/file) · Save; branch guard `{if $pubObject instanceof Preprint / Galley / SubmissionFile}` |
| AFFW-604 | omp | `omp-main/templates/controllers/tab/pubIds/form/publicIdentifiersForm.tpl` · `<form id="publicIdentifiersForm">` + `publicIdentifiersFormSubmit` | Identifiers tab (submission/chapter/format/file) · Save; branch guard `{if $pubObject instanceof Submission / Chapter / Representation / SubmissionFile}` |
| AFFW-605 | ojs | `ojs-main/templates/controllers/grid/pubIds/form/assignPublicIdentifiersForm.tpl` · `{fbvFormButtons id="assignPublicIdentifierForm" submitText="common.ok"}` | Assign public identifiers confirm modal · OK; posts to `publishIssue` or `assignPubIds` (guard: `{if $pubObject instanceof Issue}{elseif instanceof PKPSubmission}`), Cancel hidden by `$hideCancel` |
| AFFW-606 | ojs | `ojs-main/templates/controllers/grid/pubIds/form/assignPublicIdentifiersForm.tpl` · checkbox `sendIssueNotification` | Assign public identifiers (issue) · "send notification" checkbox, default checked |
| AFFW-607 | ops | `ops-main/templates/controllers/grid/pubIds/form/assignPublicIdentifiersForm.tpl` · `assignPublicIdentifierForm` submit `common.ok` | Assign public identifiers confirm · OK (guard: `{if $pubObject instanceof Preprint}`; extra text under `{if $approval}`) |
| AFFW-608 | omp | `omp-main/templates/controllers/grid/pubIds/form/assignPublicIdentifiersForm.tpl` · `assignPublicIdentifierForm` submit `common.ok` | Approve format / proof-file completion confirm · OK; action `setApproved` vs `setProofFileCompletion` (guard: `{if $pubObject instanceof Representation}{elseif instanceof SubmissionFile}`) |

## File grids — manage-files selection modals

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-609 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/files/review/manageReviewFiles.tpl` · `<form id="manageReviewFilesForm">` + `{fbvFormButtons}` | Review stage · "Select review files" modal · OK/Cancel posting `updateReviewFiles` |
| AFFW-610 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/files/final/manageFinalDraftFiles.tpl` · `<form id="manageFinalDraftFilesForm">` + `{fbvFormButtons}` | Copyediting · "Select final draft files" modal · OK/Cancel posting `updateFinalDraftFiles` |
| AFFW-611 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/files/copyedit/manageCopyeditFiles.tpl` · `<form id="manageCopyeditFilesForm">` + `{fbvFormButtons}` | Copyediting · "Select copyedited files" modal · OK/Cancel posting `updateCopyeditFiles` |
| AFFW-612 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/files/proof/manageProofFiles.tpl` · `<form id="manageProofFilesForm">` + `{fbvFormButtons}` | Production · "Select proof files" modal · OK/Cancel posting `updateProofFiles`; hidden `publicationId` (guard: `{if $publicationId}`) |
| AFFW-613 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/files/filesGridFilter.tpl` · `<form id={$formId}>` + `{fbvFormButtons hideCancel=true submitText="common.search"}` | Any submission-files grid · search box + column select + Search button |
| AFFW-614 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/files/selectableSubmissionFileListCategoryGridFilter.tpl` · `<form id="fileListFilterForm">` checkbox `allStages` | Selectable file category grid · "Include files from all stages" toggle filter (auto-submits) |

## Submission document library (workflow modal)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-615 | ojs,omp,ops | `lib/pkp/templates/controllers/modals/documentLibrary/documentLibrary.tpl` · `load_url_in_div id="submissionLibraryGridContainer"` | Submission Library modal · loads SubmissionDocumentsFilesGrid |
| AFFW-616 | ojs,omp,ops | `lib/pkp/templates/controllers/modals/documentLibrary/publisherLibrary.tpl` · `load_url_in_div id="libraryGridDiv"` | Publisher Library modal · loads LibraryFileAdminGrid (`canEdit=$canEdit` passthrough) |
| AFFW-617 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/files/submissionDocuments/form/newFileForm.tpl` · `<form id="uploadForm" action=saveFile>` + `{fbvFormButtons}` | Submission library · Add file form · name/type/description + uploader, OK/Cancel |
| AFFW-618 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/files/submissionDocuments/form/editFileForm.tpl` · `<form id="uploadForm" action=updateFile>` + `{fbvFormButtons}` | Submission library · Edit file form · OK/Cancel |
| AFFW-619 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/settings/library/form/newFileForm.tpl` · `<form id="uploadForm">` + `{fbvFormButtons}` | Publisher library (reachable from workflow) · Add file · OK/Cancel |
| AFFW-620 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/settings/library/form/editFileForm.tpl` · `<form id="uploadForm">` + `{fbvFormButtons}` | Publisher library (reachable from workflow) · Edit file + replace-file uploader · OK/Cancel |

## Reviewer grid — add reviewer flow (legacy forms)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-621 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/advancedSearchReviewerForm.tpl` · `<div id="advancedReviewerSearch">` / mount `select-reviewer-{$uuid}` (AdvancedSearchReviewerContainer) | Add reviewer modal · reviewer-selection Vue list + row "Select reviewer" (guard whole block: `{if !isset($reviewerId)}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-622 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/advancedSearchReviewerForm.tpl` · `{foreach $reviewerActions}` in `form_buttons` section, skipping `advancedSearch` | Add reviewer modal · "Create New Reviewer" / "Enroll Existing User" link actions (guard: `{if $action->getId() == 'advancedSearch'}{continue}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-623 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/advancedSearchReviewerForm.tpl` · `span.actions` action id `advancedSearch` | Add reviewer modal · "Select a different reviewer / Advanced search" link action next to selected reviewer name (guard: `{if !isset($reviewerId)}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-624 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/advancedSearchReviewerAssignmentForm.tpl` · `<form id="advancedSearchReviewerForm" action=updateReviewer>` + `{fbvFormButtons submitText="editor.submission.addReviewer"}` | Add reviewer modal · "Add Reviewer" submit; hidden `reviewerId`; hidden `reviewerSuggestionId` (guard: `{if $reviewerSuggestionId}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-625 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/defaultReviewerForm.tpl` · `<form id="defaultReviewerForm" action=updateReviewer>` + `{fbvFormButtons submitText="editor.submission.addReviewer"}` | Add reviewer (default form) · Add Reviewer submit Claimed by: reviewer-assignment-and-management. |
| AFFW-626 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/createReviewerForm.tpl` · `<form id="createReviewerForm" action=createReviewer>` + `{fbvFormButtons submitText="editor.submission.addReviewer"}` | Create new reviewer · Add Reviewer submit Claimed by: reviewer-assignment-and-management. |
| AFFW-627 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/createReviewerForm.tpl` · `div.action_links` `{foreach $reviewerActions}` | Create new reviewer · back-links to other reviewer-selection modes (link actions) Claimed by: reviewer-assignment-and-management. |
| AFFW-628 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/createReviewerForm.tpl` · `{fbvElement type="button" id="suggestUsernameButton" label="common.suggest"}` | Create new reviewer · "Suggest" username button (AJAX `api.user.UserApiHandler::suggestUsername`) Claimed by: reviewer-assignment-and-management. |
| AFFW-629 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/createReviewerForm.tpl` · select `userGroupId` | Create new reviewer · reviewer user-group select (guard: `{if count($userGroups)>1}`; `{elseif count($userGroups)==1}` renders hidden field) Claimed by: reviewer-assignment-and-management. |
| AFFW-630 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/createReviewerForm.tpl` · checkbox `masthead` (disabled, checked) | Create new reviewer · "show on masthead" indicator checkbox Claimed by: reviewer-assignment-and-management. |
| AFFW-631 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/enrollExistingReviewerForm.tpl` · `<form id="enrollExistingReviewerForm" action=enrollReviewer>` + `{fbvFormButtons submitText="editor.submission.addReviewer"}` | Enroll existing user as reviewer · Add Reviewer submit Claimed by: reviewer-assignment-and-management. |
| AFFW-632 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/enrollExistingReviewerForm.tpl` · `{fbvElement type="autocomplete" id="userId"}` (`getUsersNotAssignedAsReviewers`) | Enroll existing user · user autocomplete search Claimed by: reviewer-assignment-and-management. |
| AFFW-633 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/enrollExistingReviewerForm.tpl` · `div.action_links` `{foreach $reviewerActions}` | Enroll existing user · switch-mode link actions Claimed by: reviewer-assignment-and-management. |
| AFFW-634 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewerFormFooter.tpl` · select `template` | Add-reviewer forms footer · email-template chooser (guard: `{if $hasCustomTemplates}`; else hidden input `template`) Claimed by: reviewer-assignment-and-management. |
| AFFW-635 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewerFormFooter.tpl` · textarea `personalMessage` | Add-reviewer forms footer · personal message to reviewer (rich text, email variables) Claimed by: reviewer-assignment-and-management. |
| AFFW-636 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewerFormFooter.tpl` · checkbox `skipEmail` | Add-reviewer forms footer · "Do not send email" checkbox Claimed by: reviewer-assignment-and-management. |
| AFFW-637 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewerFormFooter.tpl` · datepickers `responseDueDate`, `reviewDueDate` | Add-reviewer forms footer · response/review due date pickers Claimed by: reviewer-assignment-and-management. |
| AFFW-638 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewerFormFooter.tpl` · radios `reviewMethod` | Add-reviewer forms footer · review-type radio group (checked per `{if $reviewMethod == $methodId}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-639 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewerFormFooter.tpl` · checkbox `isReviewPubliclyVisible` | Add-reviewer forms footer · public reviewer comments toggle Claimed by: reviewer-assignment-and-management. |
| AFFW-640 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewerFormFooter.tpl` · select `reviewFormId` | Add-reviewer forms footer · review form chooser (guard: `{if count($reviewForms)>0}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-641 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/noFilesWarning.tpl` · `div#noFilesWarning` (`editor.submission.noReviewerFilesSelected`) | Add/edit reviewer · hidden inline warning shown by JS when no files selected Claimed by: reviewer-assignment-and-management. |

## Reviewer grid — manage existing assignment (legacy forms)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-642 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/editReviewForm.tpl` · `<form id="editReviewForm" action=updateReview>` + `{fbvFormButtons}` | Edit review modal · OK/Cancel saving review settings Claimed by: reviewer-assignment-and-management. |
| AFFW-643 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/editReviewForm.tpl` · datepickers `responseDueDate`/`reviewDueDate`, radios `reviewMethod`, checkbox `isReviewPubliclyVisible`, select `reviewFormId` | Edit review modal · due dates, review type, public visibility, review form (select guard: `{if $reviewForms}{if count($reviewForms)>0}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-644 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/editReviewForm.tpl` · `load_url_in_div id="limitReviewFilesGrid"` | Edit review modal · "restrict files" grid (LimitReviewFilesGridHandler) with per-row file checkboxes Claimed by: reviewer-assignment-and-management. |
| AFFW-645 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/thankReviewerForm.tpl` · `<form id="sendThankYouForm" action=thankReviewer>` + `{fbvFormButtons submitText="editor.review.thankReviewer"}` | Thank reviewer modal · Thank Reviewer submit; message textarea; `skipEmail` checkbox Claimed by: reviewer-assignment-and-management. |
| AFFW-646 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewReminderForm.tpl` · `<form id="sendReminderForm" action=sendReminder>` + `{fbvFormButtons submitText="editor.review.sendReminder"}` | Send review reminder modal · Send Reminder submit Claimed by: reviewer-assignment-and-management. |
| AFFW-647 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewReminderForm.tpl` · select `template` | Send review reminder · email template chooser (guard: `{if count($templates)}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-648 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewReminderForm.tpl` · readonly `dateConfirmed` vs `responseDue` | Send review reminder · schedule readouts (guard: `{if $reviewAssignment->getDateConfirmed()}{else}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-649 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/unassignReviewerForm.tpl` · `{fbvFormButtons submitText="editor.review.cancelReviewer"}` / `submitText="editor.review.unassignReviewer"` | Unassign/cancel reviewer modal · submit label switches on `{if $dateConfirmed}`; posts `updateUnassignReviewer` Claimed by: reviewer-assignment-and-management. |
| AFFW-650 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/unassignReviewerForm.tpl` · textarea `personalMessage`, checkbox `skipEmail` | Unassign reviewer · message + skip-email controls Claimed by: reviewer-assignment-and-management. |
| AFFW-651 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reinstateReviewerForm.tpl` · `<form id="reinstateReviewerForm" action=updateReinstateReviewer>` + `{fbvFormButtons submitText="editor.review.reinstateReviewer"}` | Reinstate reviewer modal · Reinstate submit; message + `skipEmail` Claimed by: reviewer-assignment-and-management. |
| AFFW-652 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/resendRequestReviewerForm.tpl` · `<form id="resendRequestReviewerForm" action=updateResendRequestReviewer>` + `{fbvFormButtons submitText="editor.review.resendRequestReviewer"}` | Resend review request modal · Resend submit; message, `skipEmail`, both due-date pickers Claimed by: reviewer-assignment-and-management. |
| AFFW-653 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/emailReviewerForm.tpl` · `<form id="emailReviewerForm" action=sendEmail>` + `{fbvFormButtons submitText="common.sendEmail"}` | Email reviewer modal · Send Email submit; subject + body required Claimed by: reviewer-assignment-and-management. |
| AFFW-654 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/form/reviewerGossipForm.tpl` · `<form id="reviewerGossipForm" action=gossip>` + `{fbvFormButtons}` | Reviewer notes (gossip) modal · OK/Cancel saving editor-only notes Claimed by: reviewer-assignment-and-management. |

## Read review (editor) / read review (author)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-655 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · `<form id="readReviewForm" action=reviewRead>` + `{fbvFormButtons id="closeButton" hideCancel=false submitText="common.confirm"}` | Read review modal (editor) · Confirm + Cancel Claimed by: reviewer-assignment-and-management. |
| AFFW-656 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · `<reviewer-manager-read-review-modal>` mount `readReview-{$uuid}` | Read review modal · Vue read-review body (review details/actions) Claimed by: reviewer-assignment-and-management. |
| AFFW-657 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · `#btnExport` / `#exportOptions` show-hide script | Read review modal · Export button toggling export-options dropdown (rendered by the Vue component) Claimed by: reviewer-assignment-and-management. |
| AFFW-658 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · competing-interests block | Read review modal · competing interests display (guard: `{if $reviewAssignment->getCompetingInterestsDeclared()}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-659 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · review-form vs comments block | Read review modal · review-form responses vs reviewer comments (guard: `{if getReviewFormId()}{elseif $comments->getCount() \|\| $commentsPrivate->getCount()}`, all inside `{if getDateCompleted()}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-660 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · `load_url_in_div id="readReviewAttachmentsGridContainer"` (EditorReviewAttachmentsGridHandler) | Read review modal · reviewer-files/attachments grid Claimed by: reviewer-assignment-and-management. |
| AFFW-661 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · `{$reviewerRecommendations}` capture slot | Read review modal · editor recommendation selector injected per app Claimed by: reviewer-assignment-and-management. |
| AFFW-662 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · `input[type=radio][name=quality]` star loop | Read review modal · reviewer star rating radios (`editor.review.rateReviewer`) Claimed by: reviewer-assignment-and-management. |
| AFFW-663 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/readReview.tpl` · `div#noFilesWarning` | Read review modal · hidden "no review files uploaded" warning Claimed by: reviewer-assignment-and-management. |
| AFFW-664 | ojs | `ojs-main/templates/controllers/grid/users/reviewer/readReview.tpl` · capture `$reviewerRecommendations` = `reviewer/review/reviewerRecommendations.tpl` | Read review modal (OJS) · recommendation select injected before including core template; AjaxFormHandler attached Claimed by: reviewer-assignment-and-management. |
| AFFW-665 | omp | `omp-main/templates/controllers/grid/users/reviewer/readReview.tpl` · empty `$reviewerRecommendations` + ReadReviewHandler `reviewCompleted` | Read review modal (OMP) · no recommendation control; handler flagged by `{if $reviewAssignment->getDateCompleted()}` Claimed by: reviewer-assignment-and-management. |
| AFFW-666 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/authorReadReview.tpl` · `<form id="readReviewForm" action="">` | Author workflow · read open review modal (no submit button); attachments grid via AuthorOpenReviewAttachmentsGridHandler Claimed by: review-stage-and-rounds. |
| AFFW-667 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/authorReadReview.tpl` · review-form vs comments block | Author read review · responses (guard: `{if getReviewFormId()}{elseif $comments->getCount()}` inside `{if getDateCompleted()}`) Claimed by: review-stage-and-rounds. |
| AFFW-668 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/reviewer/reviewDownload.tpl` · element-type branches (`{if in_array(...)}`,`{elseif}` checkboxes/radios/dropdown), `{if !$authorFriendly}` private-comments block | Review download (print/export view) · read-only rendering of review form answers and comments Claimed by: reviewer-assignment-and-management. |
| AFFW-669 | omp | `omp-main/templates/controllers/grid/settings/reviewForm/previewReviewForm.tpl` · element-type `{if}/{elseif}` chain | Review form preview · non-functional text/checkbox/radio/select previews |
| AFFW-670 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/settings/reviewForms/editReviewForm.tpl` · tab links `manager.reviewForms.edit` / `manager.reviewFormElements` / `manager.reviewForms.preview` | Review form editor tabset (settings-side, reachable from workflow edge) · three tab anchors; edit tabs disabled via `{if !$canEdit}disabled: [0,1]{/if}`, preselect `{if $preview}2{else}0{/if}` |

## Stage participants grid (legacy forms)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-671 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl` · `<form id="addParticipantForm" action=saveParticipant>` + `{fbvFormButtons}` | Add/edit participant modal · OK/Cancel |
| AFFW-672 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl` · `load_url_in_div id="userSelectGridContainer"` (UserSelectGridHandler) | Add participant modal · role + user picker grid (guard: `{else}` branch of `{if $assignmentId}`) |
| AFFW-673 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl` · checkbox `recommendOnly` | Add/edit participant · "can recommend only" toggle (edit-mode guard: `{if $isChangeRecommendOnlyAllowed}`) |
| AFFW-674 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl` · checkbox `canChangeMetadata` | Add/edit participant · "can edit publication metadata" toggle (edit-mode guard: `{if $isChangePermitMetadataAllowed}`) |
| AFFW-675 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl` · `{translate key="stageParticipants.noOptionsToHandle"}` | Edit participant · empty-state text (guard: `{if !$isChangePermitMetadataAllowed && !$isChangeRecommendOnlyAllowed}`) |
| AFFW-676 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl` · select `template` + textarea `message` in `notifyFormArea` | Add participant · notification template chooser + message body (guard: `{if !isset($assignmentId)}`) |
| AFFW-677 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/stageParticipant/form/notify.tpl` · `<form id="notifyForm" action=sendNotification>` + `{fbvFormButtons id="notifyButton" hideCancel=true submitText="submission.stageParticipants.notify"}` | Notify participant / start discussion modal · Notify submit (no cancel) |
| AFFW-678 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/stageParticipant/form/notify.tpl` · select `template`, textarea `message` (required) | Notify participant · template chooser + required message |
| AFFW-679 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/userSelect/searchUserFilter.tpl` · `<form id={$formId}>` + `{fbvFormButtons hideCancel=true submitText="common.search"}` | Add participant · user-select grid filter · role select + name field + Search |
| AFFW-680 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/userSelect/userSelectRadioButton.tpl` · `input[type=radio].advancedUserSelect#user_{$rowId}` | Add participant · per-row user selection radio (checked via `{if $userId==$rowId}`) |

## Contributors grid (deprecated legacy author form)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-681 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/author/form/authorForm.tpl` · `<form id="editAuthor" action=updateAuthor>` + `{fbvFormButtons id="step2Buttons" submitText="common.save"}` | Contributor add/edit form (deprecated 3.4) · Save/Cancel |
| AFFW-682 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/author/form/authorForm.tpl` · radios `userGroupId` in section `userGroupId` | Contributor form · contributor role radio group |
| AFFW-683 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/author/form/authorForm.tpl` · checkboxes `primaryContact`, `includeInBrowse`, `{$additionalCheckboxes}` | Contributor form · principal-contact / include-in-browse toggles + app-injected extras |
| AFFW-684 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/author/form/authorForm.tpl` · textarea `competingInterests` | Contributor form · competing interests (guard: `{if $requireAuthorCompetingInterests}`) |
| AFFW-685 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/author/primaryContact.tpl` · `div#isChecked` | Contributors grid cell · principal-contact checkmark (guard: `{if $isPrincipalContact}`) |
| AFFW-686 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/users/author/includeInBrowse.tpl` · `div#isChecked` | Contributors grid cell · include-in-browse checkmark (guard: `{if $includeInBrowse}`) |

## Information Center (notes / history)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-687 | ojs,omp,ops | `lib/pkp/templates/controllers/informationCenter/informationCenter.tpl` · tab link `submission.informationCenter.history` | Information Center modal · History tab anchor (`viewHistory`) (guard: `{if !$removeHistoryTab}`) |
| AFFW-688 | ojs,omp,ops | `lib/pkp/templates/controllers/informationCenter/informationCenter.tpl` · tab link `submission.informationCenter.notes` | Information Center modal · Notes tab anchor (`viewNotes`) |
| AFFW-689 | ojs,omp,ops | `lib/pkp/templates/controllers/informationCenter/notes.tpl` · `{include controllers/extrasOnDemand.tpl id="showPastNotesLink"}` | Information Center notes · "Past Notes" expand/collapse (guard: `{if $showEarlierEntries}`) |
| AFFW-690 | ojs,omp,ops | `lib/pkp/templates/controllers/informationCenter/newNoteForm.tpl` · `<form id="newNoteForm" action=saveNote>` + `{fbvFormButtons hideCancel=true submitText=$submitNoteText}` | Information Center notes · add-note textarea + submit |
| AFFW-691 | ojs,omp,ops | `lib/pkp/templates/controllers/informationCenter/note.tpl` · `<button type="submit" id="deleteNote-{$noteId}">` `common.delete` + `buttonConfirmationLinkAction.tpl` (`informationCenter.deleteConfirm`, negative style) | Information Center note · Delete note with confirm dialog (guard: `{if $notesDeletable && array_intersect([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], $userRoles)}`, nested twice) |
| AFFW-692 | ojs,omp,ops | `lib/pkp/templates/controllers/informationCenter/note.tpl` · `{include linkAction/linkAction.tpl action=$noteFileDownloadLink}` | Information Center note · attached-file download link (guard: `{if $noteFileDownloadLink}`) |
| AFFW-693 | ojs,omp,ops | `lib/pkp/templates/controllers/informationCenter/notesList.tpl` · `p.no_notes` `informationCenter.noNotes` | Information Center notes · empty state (guard: `{if $notes->isEmpty()}`) |
| AFFW-694 | ojs,omp,ops | `lib/pkp/templates/controllers/informationCenter/submissionHistory.tpl` · `load_url_in_div id="submissionHistoryGridContainer"` | Information Center · History tab loads SubmissionEventLogGrid |
| AFFW-695 | ojs,omp,ops | `lib/pkp/templates/controllers/revealMore.tpl` · `<button class="revealMoreButton">` `common.readMore` | Notes/comments · "Read More" expand button |
| AFFW-696 | ojs,omp,ops | `lib/pkp/templates/controllers/extrasOnDemand.tpl` · `<a href="#" class="toggleExtras">` (`$moreDetailsText`/`$lessDetailsText`) | Any extras-on-demand widget · show/hide toggle link |

## Event log / tasks

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-697 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/eventLog/eventLogGridFilter.tpl` · `<form id="eventLogFilterForm">` checkbox `allEvents` | Submission history grid · "Show all events" filter toggle (ToggleFormHandler auto-submit) |
| AFFW-698 | ojs,omp,ops | `lib/pkp/templates/controllers/page/tasks.tpl` · `load_url_in_div id="notificationsGrid"` | Tasks panel · loads TaskNotificationsGrid |
| AFFW-699 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/tasks/task.tpl` · `div.task{if !$notification->dateRead} unread{/if}` + `{$message}` | Tasks grid row · task message (usually a link action from the notification manager); unread styling guard |
| AFFW-700 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/tasks/task.tpl` · `span.acronym` / `span.submission` | Tasks grid row · context acronym (guard: `{if $isMultiContext && $context}`) and submission title (guard: `{if $notificationObjectTitle && $notificationObjectTitle !== '—'}`) |

## Author dashboard (legacy templates)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-701 | ojs,omp,ops | `lib/pkp/templates/authorDashboard/submissionEmails.tpl` · `<a href="#" id="submissionEmail-{$submissionEmail->id}">` (ModalRequest → `authorDashboard::readSubmissionEmail`) | Author dashboard · editor-message link opening email modal (guard: `{if $submissionEmails && $submissionEmails->count()}`) Claimed by: review-stage-and-rounds. |
| AFFW-702 | ojs,omp,ops | `lib/pkp/templates/authorDashboard/submissionEmail.tpl` · read-only body | Author dashboard · single email modal body (no controls) Claimed by: review-stage-and-rounds. |
| AFFW-703 | ojs,omp,ops | `lib/pkp/templates/authorDashboard/reviewRoundInfo.tpl` · `load_url_in_div id="reviewersGrid-round_..."` | Author dashboard review round · author reviewer grid (guard: `{if $showReviewerGrid}`) Claimed by: review-stage-and-rounds. |
| AFFW-704 | ojs,omp,ops | `lib/pkp/templates/authorDashboard/reviewRoundInfo.tpl` · `load_url_in_div id="reviewAttachmentsGridContainer-..."` and `revisionsGrid-...` | Author dashboard review round · review attachments + revisions grids (guard: `{if $showReviewAttachments}`) Claimed by: review-stage-and-rounds. |
| AFFW-705 | ojs,omp,ops | `lib/pkp/templates/controllers/tab/authorDashboard/editorial.tpl` · `load_url_in_div id="copyeditedFilesGrid"` | Author dashboard Copyediting tab · copyedited files grid (guards: `{if $submission->getData('stageId') >= WORKFLOW_STAGE_ID_EDITING}` then `{if $canAccessCopyeditingStage}`; `{else}` renders `submission.stageNotInitiated`) |
| AFFW-706 | ojs,omp,ops | `lib/pkp/templates/controllers/tab/authorDashboard/submission.tpl` · `load_url_in_div id="submissionFilesGridDiv"` | Author dashboard Submission tab · author submission files grid Claimed by: submission-stage. |

## Workflow page fragments / publish modal (legacy)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-707 | ojs,omp,ops | `lib/pkp/templates/workflow/submissionIdentification.tpl` · `span.pkpWorkflow__identificationTitle` (Vue interpolation) | Workflow header · submission id/author/title display (author span guard: `v-if="currentPublication.authorsStringShort"`) |
| AFFW-708 | ojs,omp,ops | `lib/pkp/templates/workflow/reviewHistory.tpl` · `{foreach $dates}` rows | Review history popover · per-date rows (guard: `{if $date}`) Claimed by: reviewer-assignment-and-management. |
| AFFW-709 | ojs,omp,ops | `lib/pkp/templates/controllers/modals/publish/publish.tpl` · `<pkp-form v-bind="components.PublishForm::FORM_PUBLISH">` mount `publish-{$uuid}` | Publish modal · publish confirmation form (submit rendered by PkpForm) |
| AFFW-710 | ojs,omp,ops | `lib/pkp/templates/controllers/modals/publish/publish.tpl` · `div.pkpNotification--warning` list | Publish modal · pre-publication warnings block (guard: `{if $publishWarnings}`) |
| AFFW-711 | ojs,omp,ops | `lib/pkp/templates/controllers/modals/submission/viewSubmissionMetadata.tpl` · `#viewSubmissionMetadata` | View submission metadata modal · read-only; blocks guarded by `{if $authors}`, `{if $additionalMetadata \|\| $dataAvailability \|\| $fundingStatement}`, `{if $dataAvailability}`, `{if $fundingStatement}` |

## Grid framework chrome (shared by all workflow grids)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-712 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/gridHeader.tpl` · `{include grid/gridActionsAbove.tpl}` | Any grid · header actions region (guard: `{if $grid->getActions(GRID_ACTION_POSITION_ABOVE)}`) |
| AFFW-713 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/gridActionsAbove.tpl` · `ul.actions` `{foreach}` linkAction | Any grid · above-grid action buttons (e.g. Upload File, Add Reviewer) |
| AFFW-714 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/gridActionsBelow.tpl` · `ul.actions.btm` `{foreach}` linkAction | Any grid · below-grid action buttons (guard in grid.tpl: `{if $grid->getActions(GRID_ACTION_POSITION_BELOW) \|\| $grid->getFootNote()}`) |
| AFFW-715 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/grid.tpl` · `span.options.pkp_linkActions` last-column actions | Any grid · last-column header actions (guard: `{if $smarty.foreach.columns.last && $grid->getActions(GRID_ACTION_POSITION_LASTCOL)}`) |
| AFFW-716 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/gridRow.tpl` · `<a href="#" class="show_extras">` (`grid.settings`) | Any grid row · expand row-actions toggle (guard: `{if $row->hasActions() && $column->hasFlag('firstColumn')}` + `{if $row->getActions(GRID_ACTION_POSITION_DEFAULT)}`) |
| AFFW-717 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/gridRow.tpl` · `div.row_actions` left-position `{foreach}` linkAction | Any grid row · inline left row actions (guard: `{if $row->getActions(GRID_ACTION_POSITION_ROW_LEFT)}`) |
| AFFW-718 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/gridRow.tpl` · `tr#{$rowId}-control-row` `{foreach}` linkAction | Any grid row · expanded control-row action buttons (guard: `{if $row->getActions(GRID_ACTION_POSITION_DEFAULT)}`) |
| AFFW-719 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/gridCellContents.tpl` · `{foreach $actions}` linkAction | Any grid cell · cell-level link actions (guard: `{if count($actions) gt 0}`) |
| AFFW-720 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/gridRowSelectInput.tpl` · `input[type=checkbox]#select-{$elementId}` | Selectable grids (manage files, limit review files) · per-row select checkbox (checked via `{if $selected}`) |
| AFFW-721 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/common/cell/selectStatusCell.tpl` · `input[type=checkbox]#select-{$cellId}` + `buttonGenericLinkAction.tpl` | Grid cell · checkbox bound to a default cell action (guard: `{if count($actions) gt 0}`; `{if $disabled}disabled{/if}`) |
| AFFW-722 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/common/cell/radioButtonCell.tpl` · `input[type=radio]#select-{$cellId}` + `buttonGenericLinkAction.tpl` | Grid cell · radio bound to a default cell action (guard: `{if count($actions) gt 0}`) |
| AFFW-723 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/common/cell/statusCell.tpl` · linkAction with `imageClass="task"` / `<a class="task {$status}">` | Grid cell · status icon as action or static (guard: `{if count($actions) gt 0}{elseif $status}`) |
| AFFW-724 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/common/cell/checkMarkCell.tpl` · checkmark div | Grid cell · boolean checkmark (guard: `{if $isChecked}`) |
| AFFW-725 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/feature/gridPaging.tpl` · `select.itemsPerPage`, `div.gridPages` | Any paged grid · items-per-page select + page links |
| AFFW-726 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/feature/gridOrderFinishControls.tpl` · `<a id="{$gridId}-saveButton" class="saveButton">` / `<a id="{$gridId}-cancel" class="cancelFormButton">` | Orderable grid · Save Order / Cancel ordering |
| AFFW-727 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/feature/collapsibleGridFeature.tpl` · linkAction `$controlLink` contextId `collapsibleGridControl` | Collapsible grid · expand/collapse control |
| AFFW-728 | ojs,omp,ops | `lib/pkp/templates/controllers/grid/feature/infiniteScrolling.tpl` · linkAction `$moreItemsLinkAction` | Infinite-scroll grid · "load more" action (guards: `{if $iterator->getCount()}` + `{if $moreItemsLinkAction}`) |
| AFFW-729 | ojs,omp,ops | `lib/pkp/templates/linkAction/linkActionButton.tpl` · `<a class="pkp_controllers_linkAction pkp_linkaction_{$action->getId()}">` | All grid/link actions · the anchor renderer for every link action (tooltip guard: `{if $action->getToolTip()}`) |
| AFFW-730 | ojs,omp,ops | `lib/pkp/templates/linkAction/buttonConfirmationLinkAction.tpl` · `buttonSelector` + `$dialogText` | Any button · attaches confirmation dialog (default `{if !$modalStyle}basic{/if}`) |
| AFFW-731 | ojs,omp,ops | `lib/pkp/templates/linkAction/buttonGenericLinkAction.tpl` / `buttonRedirectLinkAction.tpl` · `buttonSelector` | Any button · generic/redirect action binding for an existing button element |
| AFFW-732 | ojs,omp,ops | `lib/pkp/templates/controllers/notification/linkActionNotificationContent.tpl` · `span#{$linkAction->getId()}` | Workflow notification · embedded action button inside a notification |
| AFFW-733 | ojs,omp,ops | `lib/pkp/templates/controllers/listbuilder/listbuilder.tpl` · `input.deletions` hidden + `{if $hasOrderLink}` ordering block | Listbuilder widget · add/delete/order controls region |
| AFFW-734 | ojs,omp,ops | `lib/pkp/templates/controllers/listbuilder/listbuilderGridRow.tpl` · `div.row_actions` `{foreach}` linkAction, hidden `rowId`/`isModified` | Listbuilder row · per-row delete/edit actions (guards: `{if $row->getId()}`, `{if !$row->getId() \|\| $row->getIsModified()}`) |

## OJS — galleys / issue assignment surfaces (workflow-adjacent)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-735 | ojs | `ojs-main/templates/controllers/grid/articleGalleys/editFormat.tpl` · tab link `grid.action.editMetadata` | Edit galley modal · "Edit Metadata" tab (`editGalleyTab`) |
| AFFW-736 | ojs | `ojs-main/templates/controllers/grid/articleGalleys/editFormat.tpl` · tab link `submission.identifiers` | Edit galley modal · Identifiers tab (guard: `{if $enableIdentifiers}`) |
| AFFW-737 | ojs | `ojs-main/templates/controllers/grid/articleGalleys/form/articleGalleyForm.tpl` · `<form id="articleGalleyForm" action=updateGalley>` + `{fbvFormButtons submitText="common.save" submitDisabled=$formDisabled hideCancel=$formDisabled}` | Galley form · Save/Cancel (both suppressed when `$formDisabled`) |
| AFFW-738 | ojs | `ojs-main/templates/controllers/grid/articleGalleys/form/articleGalleyForm.tpl` · checkbox `remotelyHostedContent` | Galley form · remote-URL toggle (disabled via `$formDisabled`; URL field guard `{if $urlRemote}`) |
| AFFW-739 | ojs | `ojs-main/templates/controllers/grid/articleGalleys/form/articleGalleyForm.tpl` · `load_url_in_div id="dependentFilesGridDiv"` | Galley form · dependent files grid (guard: `{if $supportsDependentFiles}`) |
| AFFW-740 | ojs | `ojs-main/templates/controllers/grid/issues/issue.tpl` · tab links `issue.toc`, `editor.issues.issueData`, `editor.issues.galleys` | Edit issue modal · Table of Contents / Issue Data / Galleys tabs |
| AFFW-741 | ojs | `ojs-main/templates/controllers/grid/issues/issue.tpl` · tab link `editor.issues.identifiers` (`li#identifiersTab`) | Edit issue modal · Identifiers tab (guard: `{if $enableIdentifiers}`) |
| AFFW-742 | ojs | `ojs-main/templates/controllers/grid/issues/issue.tpl` · tab link `editor.issues.access` | Edit issue modal · Access tab (guard: `{if $currentJournal->getData('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION}`) |
| AFFW-743 | ojs | `ojs-main/templates/controllers/grid/issues/issueToc.tpl` · `load_url_in_div id="issueTocGridContainer"` | Edit issue · Table of Contents tab loads issue TOC grid (per-article remove/order row actions) |
| AFFW-744 | ojs | `ojs-main/templates/controllers/grid/issues/form/issueForm.tpl` · `<form id="issueForm" action=updateIssue>` + `{fbvFormButtons submitText="common.save"}` | Issue Data tab · Save |
| AFFW-745 | ojs | `ojs-main/templates/controllers/grid/issues/form/issueForm.tpl` · datepicker `datePublished` | Issue Data tab · publication date (guard: `{if $issue && $issue->getPublished()}` / `{if !$issuePublished}`) |
| AFFW-746 | ojs | `ojs-main/templates/controllers/grid/issues/form/issueForm.tpl` · checkboxes `showVolume`,`showNumber`,`showYear`,`showTitle` | Issue Data tab · issue identification display toggles |
| AFFW-747 | ojs | `ojs-main/templates/controllers/grid/issues/form/issueForm.tpl` · `{include linkAction/linkAction.tpl action=$deleteCoverImageLinkAction contextId="issueForm"}` | Issue Data tab · delete cover image action + alt-text field (guard: `{if $coverImage != ''}`) |
| AFFW-748 | ojs | `ojs-main/templates/controllers/grid/issues/form/issueAccessForm.tpl` · `<form id="issueAccessForm" action=updateAccess>` + `{fbvFormButtons submitText="common.save"}` | Issue Access tab · Save access status/open-access date |
| AFFW-749 | ojs | `ojs-main/templates/controllers/grid/issueGalleys/form/issueGalleyForm.tpl` · `<form id="issueGalleyForm" action=update>` + `{fbvFormButtons submitText="common.save"}` | Issue galley form · Save |
| AFFW-750 | ojs | `ojs-main/templates/controllers/grid/issueGalleys/form/issueGalleyForm.tpl` · `<a href="{url op="download" ...}" target="_blank">` | Issue galley form · download existing galley file link (guard: `{if $issueGalley}`) |
| AFFW-751 | ojs | `ojs-main/templates/controllers/grid/issueGalleys/form/issueGalleyForm.tpl` · publisher-id field | Issue galley form · publisher ID (guard: `{if $enablePublisherId}`) |

## OPS — galleys (workflow-adjacent)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-752 | ops | `ops-main/templates/controllers/grid/preprintGalleys/editFormat.tpl` · tab links `grid.action.editMetadata` / `submission.identifiers` | Edit preprint galley modal · metadata tab + identifiers tab (guard: `{if $enableIdentifiers}`) |
| AFFW-753 | ops | `ops-main/templates/controllers/grid/preprintGalleys/form/preprintGalleyForm.tpl` · `<form id="preprintGalleyForm" action=updateGalley>` + `{fbvFormButtons submitText="common.save" submitDisabled=$formDisabled hideCancel=$formDisabled}` | Preprint galley form · Save/Cancel; remote-content checkbox; dependent-files grid (guard: `{if $supportsDependentFiles}`) |

## OMP — catalog entry / publication formats / chapters (legacy forms)

| ID | apps | pointer | screen · control · description |
|---|---|---|---|
| AFFW-754 | omp | `omp-main/templates/controllers/grid/catalogEntry/editFormat.tpl` · tab link `editFormatTab` | Edit publication format modal · format tab anchor |
| AFFW-755 | omp | `omp-main/templates/controllers/grid/catalogEntry/editFormat.tpl` · tab link `editFormatMetadata` | Edit publication format modal · catalog-metadata tab (guard: `{if isset($representationId)}`) |
| AFFW-756 | omp | `omp-main/templates/controllers/grid/catalogEntry/editFormat.tpl` · tab link `submission.identifiers` | Edit publication format modal · Identifiers tab (guards: `{if !$remoteRepresentation && $representationId}` + `{if $showIdentifierTab}`) |
| AFFW-757 | omp | `omp-main/templates/controllers/grid/catalogEntry/dependentFiles.tpl` · `load_url_in_div id="dependentFilesGridDiv"` | Publication format · dependent files grid |
| AFFW-758 | omp | `omp-main/templates/controllers/grid/catalogEntry/form/formatForm.tpl` · `<form id="addPublicationFormatForm" action=updateFormat>` + `{fbvFormButtons}` | Add/edit publication format · OK/Cancel; remote URL block (guard: `{if $remoteURL}`) |
| AFFW-759 | omp | `omp-main/templates/controllers/grid/catalogEntry/form/codeForm.tpl` · `<form id="addIdentificationCodeForm" action=updateCode>` + `{fbvFormButtons}` | Catalog entry · identification code form · OK/Cancel |
| AFFW-760 | omp | `omp-main/templates/controllers/grid/catalogEntry/form/marketForm.tpl` · `<form id="marketForm" action=updateMarket>` + `{fbvFormButtons}` | Catalog entry · market form · OK/Cancel |
| AFFW-761 | omp | `omp-main/templates/controllers/grid/catalogEntry/form/pubDateForm.tpl` · `<form id="addPubDateForm" action=updateDate>` + `{fbvFormButtons}` | Catalog entry · publication date form · OK/Cancel |
| AFFW-762 | omp | `omp-main/templates/controllers/grid/catalogEntry/form/representativeForm.tpl` · `<form id="representativeForm" action=updateRepresentative>` + `{fbvFormButtons}` | Catalog entry · representative/supplier form · OK/Cancel; agent vs supplier field visibility (guards: `{if $isSupplier}` / `{if !$isSupplier}`) |
| AFFW-763 | omp | `omp-main/templates/controllers/grid/catalogEntry/form/salesRightsForm.tpl` · `<form id="addSalesRightsForm" action=updateRights>` + `{fbvFormButtons}` | Catalog entry · sales rights form · OK/Cancel; ROW block (guard: `{if $ROWSetting}`) |
| AFFW-764 | omp | `omp-main/templates/controllers/grid/catalogEntry/form/countriesAndRegions.tpl` · countries/regions multiselect fragment | Catalog entry · sales-rights territory selector (included fragment, no own submit) |
| AFFW-765 | omp | `omp-main/templates/controllers/tab/catalogEntry/form/publicationMetadataFormFields.tpl` · `<form id={$publicationFormId} action=updateFormatMetadata>` + `{fbvFormButtons id="publicationMetadataFormSubmit" submitText="common.save"}` | Publication format metadata tab · Save (guard: `{if !$formParams.hideSubmit}`) |
| AFFW-766 | omp | `omp-main/templates/controllers/tab/catalogEntry/form/publicationMetadataFormFields.tpl` · four `load_url_in_div` blocks (identification codes, sales rights, markets, publication dates) | Publication format metadata tab · embedded catalog sub-grids each with own add/edit row actions |
| AFFW-767 | omp | `omp-main/templates/controllers/tab/catalogEntry/form/publicationMetadataFormFields.tpl` · physical vs digital field include | Publication format metadata tab · physical/digital field set (guard: `{if $isPhysicalFormat}{elseif !$remoteURL}`) |
| AFFW-768 | omp | `omp-main/templates/controllers/tab/catalogEntry/form/digitalPublicationFormat.tpl` · checkbox `override` | Digital format fields · "override file size" toggle |
| AFFW-769 | omp | `omp-main/templates/controllers/tab/catalogEntry/form/physicalPublicationFormat.tpl` · dimension/weight fields | Physical format fields · no independent submit |
| AFFW-770 | omp | `omp-main/templates/controllers/grid/files/proof/form/approvedProofForm.tpl` · `<form id="approvedProofForm" action=saveApprovedProof>` + `{fbvFormButtons id="saveApprovedProofForm" submitText="common.save"}` | Approved proof pricing modal · Save |
| AFFW-771 | omp | `omp-main/templates/controllers/grid/files/proof/form/approvedProofFormFields.tpl` · radios `salesType` + text `price` | Approved proof pricing · sales-type radio group and price field |
| AFFW-772 | omp | `omp-main/templates/controllers/grid/users/chapter/editChapter.tpl` · tab links `grid.action.editMetadata` / `submission.identifiers` | Edit chapter modal · metadata tab + identifiers tab (guard: `{if $showIdentifierTab}`) |
| AFFW-773 | omp | `omp-main/templates/controllers/grid/users/chapter/form/chapterForm.tpl` · `<form id="editChapterForm" action=updateChapter>` + `{fbvFormButtons submitText="common.save"}` | Chapter form · Save |
| AFFW-774 | omp | `omp-main/templates/controllers/grid/users/chapter/form/chapterForm.tpl` · checkbox `isPageEnabled` | Chapter form · "has landing page" toggle |
| AFFW-775 | omp | `omp-main/templates/controllers/grid/users/chapter/form/chapterForm.tpl` · checkboxes `authors[]` | Chapter form · chapter-author selection (guard: `{if $chapterAuthorOptions}`) |
| AFFW-776 | omp | `omp-main/templates/controllers/grid/users/chapter/form/chapterForm.tpl` · checkboxes `files[]` | Chapter form · chapter-file selection (guard: `{if $chapterFileOptions}`) |
| AFFW-777 | omp | `omp-main/templates/controllers/grid/users/chapter/form/chapterForm.tpl` · chapter pub-date fields / DOI display | Chapter form · publication dates (guard: `{if $enableChapterPublicationDates}`), edited-volume authors (guard: `{if $submissionWorkType === WORK_TYPE_EDITED_VOLUME}`), DOI (guard: `{if $doi}`) |
