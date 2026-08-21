# Atlas — AFFM (affordances: management/settings + site administration)

- **Sub-modality**: AFFM — per-screen affordances (visible controls/actions) for management/settings and site-administration screens. Boundary: workflow/wizard screens → AFFW, reader front end → AFFR, profile/reviewer/stats-dashboard screens → AFFU.
- **Sweep date**: 2026-07-26
- **Trees swept**: `ojs-main`, `omp-main`, `ops-main` (each: `templates/management/`, `templates/admin/` (lib/pkp only), `templates/payments/` (OJS), `controllers/grid/`, `pages/management/`, `plugins/importexport/*/templates/`), shared `lib/pkp` (`templates/management/`, `templates/admin/`, `templates/stats/reports.tpl`, `templates/user/confirmPassword.tpl`, `controllers/grid/{settings,admin,navigationMenus,announcements,plugins}/`, `classes/controllers/grid/plugins/`), and `ojs-main/lib/ui-library/src` (`managers/`, `pages/{manageEmails,jobs,userComments,userInvitation}/`, `components/ListPanel/{announcements,highlights,institutions,doi}/`, `components/Container/`, `components/Form/context|site/`). lib/pkp SHA `ad4606f93e` (see README sweep provenance).
- **Method**: read every management/admin/payments `.tpl` in the four trees (`ls`/`cat`); `grep -rn "new LinkAction(" -A2` over the grid handler/row/cell-provider trees to enumerate legacy grid toolbar/row/cell actions; `grep -rln "OrderGridItemsFeature\|PagingFeature\|getFilterForm"` for grid ordering/paging/filter affordances; `grep -nE "PkpButton|t\('|label:|@click|openDialog|v-if"` over the ui-library components mounted by those templates. Tab/guard conditions are quoted verbatim from `{if}` / `v-if` as mechanical facts — no liveness judgment.
- **Granularity**: one atom per control/action per screen; a Vue form tab (pkp-form + its Save) is one atom (its fields belong to the `settings` modality); a grid's toolbar action, row action, cell toggle, drag-order and filter are separate atoms. Identical per-app occurrences collapsed (combined `apps:`). Where a later screen re-embeds an earlier screen's component wholesale (e.g. site-settings highlights tab), the tab is one atom cross-referencing the canonical atoms.
- **Screens swept**: context settings (masthead/contact/sections·series/categories); website settings (appearance, setup, plugins, content); workflow settings (submission, review, library, emails, task templates); distribution settings (license, DOIs, indexing, payments, statistics, access, archiving); users & roles + invite-to-role wizard; emails management; announcements manager; institutions manager; user comments moderation; DOIs management; tools (import/export, permissions) + statistics reports tool; OJS subscriptions & payments; OJS issues management (assigned to AFFM by orchestrator 2026-07-27); OMP catalog management (assigned to AFFM by orchestrator 2026-07-27); site admin index, hosted contexts, context settings wizard, site settings, jobs/failed jobs/failed job details, system information, confirm-access.
- **Noted, not swept (out of AFFM per task brief)**: stats dashboards (AFFU), plugin-specific settings modals beyond the grid affordance that opens them (plugins modality).

## Context settings — Settings > Journal / Press / Server (`management/settings/context`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-001 | ojs omp ops | `templates/management/context.tpl` tab `masthead` · `PKPMastheadForm::FORM_MASTHEAD` | Context settings · Masthead form (edit + Save). |
| AFFM-002 | ojs omp ops | `templates/management/context.tpl` tab `contact` · `PKPContactForm::FORM_CONTACT` | Context settings · Contact form (edit + Save). |
| AFFM-003 | ojs ops | `SectionGridHandler` LinkAction `addSection` | Context settings · Sections grid · Create Section (modal `SectionForm`). |
| AFFM-004 | ojs ops | `SectionGridRow` LinkAction `editSection` | Context settings · Sections grid · row Edit (modal `SectionForm`). |
| AFFM-005 | ojs ops | `SectionGridRow` LinkAction `deleteSection` | Context settings · Sections grid · row Delete (confirmation). |
| AFFM-006 | ojs ops | `SectionGridCellProvider` LinkActions `activateSection`/`deactivateSection` | Context settings · Sections grid · inactive-column cell toggle activate/deactivate. |
| AFFM-007 | ojs ops | `SectionGridHandler::initFeatures` `OrderGridItemsFeature` | Context settings · Sections grid · drag-to-reorder rows (Order mode). |
| AFFM-008 | omp | `SeriesGridHandler` LinkAction `addSeries` | Context settings · Series grid · Create Series (modal `SeriesForm`). |
| AFFM-009 | omp | `SeriesGridRow` LinkAction `editSeries` | Context settings · Series grid · row Edit (modal `SeriesForm`, incl. in-form `deleteCoverImage` action). |
| AFFM-010 | omp | `SeriesGridRow` LinkAction `deleteSeries` | Context settings · Series grid · row Delete (confirmation). |
| AFFM-011 | omp | `SeriesGridCellProvider` LinkActions `activateSeries`/`deactivateSeries` | Context settings · Series grid · inactive-column cell toggle activate/deactivate. |
| AFFM-012 | omp | `SeriesGridHandler::initFeatures` `OrderGridItemsFeature` | Context settings · Series grid · drag-to-reorder rows. |
| AFFM-013 | ojs omp ops | `managers/CategoryManager/CategoryManager.vue` button `grid.category.add` | Context settings · Categories · Add Category (opens `EditCategoryFormModal`). |
| AFFM-014 | ojs omp ops | `managers/CategoryManager/useCategoryManagerConfig.js` item action `common.add` | Context settings · Categories · row more-actions Add (add sub-category under row). |
| AFFM-015 | ojs omp ops | `useCategoryManagerConfig.js` item action `common.edit` | Context settings · Categories · row Edit (opens `EditCategoryFormModal`). |
| AFFM-016 | ojs omp ops | `categoryManagerStore.js` `categoryDelete` · `CategoryDeleteDialogBody` | Context settings · Categories · row Delete (confirm dialog reporting sub-category count). |
| AFFM-017 | ojs omp ops | `CategoryManager.vue` `manager.category.toggleSubcategories` · `CategoryTreeRow.vue` expand control | Context settings · Categories · expand/collapse sub-categories (per row + global toggle). |

## Website settings — Appearance (`management/settings/website` tab `appearance`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-018 | ojs omp ops | `templates/management/website.tpl` tab `theme` · `PKPThemeForm::FORM_THEME` · `components/Form/context/ThemeForm.vue` | Website appearance · Theme form (theme select reloads theme-specific option fields + Save). |
| AFFM-019 | ojs omp ops | `website.tpl` tab `appearance-setup` · `AppearanceSetupForm::FORM_APPEARANCE_SETUP` | Website appearance · Setup form (logo, favicon, css etc. + Save). |
| AFFM-020 | ojs omp ops | `website.tpl` tab `appearance-masthead` · `PKPAppearanceMastheadForm::FORM_APPEARANCE_MASTHEAD` | Website appearance · Editorial masthead display form (+ Save). |
| AFFM-021 | ojs omp ops | `website.tpl` tab `advanced` · `AppearanceAdvancedForm::FORM_APPEARANCE_ADVANCED` | Website appearance · Advanced form (+ Save). |

## Website settings — Setup (`management/settings/website` tab `setup`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-022 | ojs omp | `website.tpl` tab `information` · `PKPInformationForm::FORM_INFORMATION` · guard `{if $includeInformationForm}` (OPS `SettingsHandler::getInformationForm()` returns null) | Website setup · Information form (+ Save). |
| AFFM-023 | ojs omp ops | `ManageLanguageGridHandler` op `setContextPrimaryLocale` | Website setup · Languages grid · primary-locale radio per row. |
| AFFM-024 | ojs omp ops | `ManageLanguageGridHandler` op `saveLanguageSetting` | Website setup · Languages grid · per-locale enable checkboxes (UI / forms / submissions columns). |
| AFFM-025 | ojs omp ops | `LanguageGridRow` LinkAction `reload` (op `reloadLocale`) | Website setup · Languages grid · row Reload localized default settings (confirmation). |
| AFFM-026 | ojs omp ops | `SubmissionLanguageGridHandler` LinkAction `addLanguageModal` (save op `addLanguages`) | Website setup · Submission languages grid · Add Language (modal). |
| AFFM-027 | ojs omp ops | `SubmissionLanguageGridHandler::initialize` `addPrimaryColumn('defaultSubmissionLocale')` + `addSubmissionColumns()` | Website setup · Submission languages grid · default-submission-locale radio + submission/metadata checkbox columns (cell toggles; save ops are role-assigned on `ManageLanguageGridHandler`). |
| AFFM-028 | ojs omp ops | `NavigationMenusGridHandler` LinkAction `addNavigationMenu` | Website setup · Navigation menus grid · Add Menu (opens `NavigationMenuManagerFormModal`, VUE-066). |
| AFFM-029 | ojs omp ops | `NavigationMenusGridRow` LinkAction `edit` (also title cell action) | Website setup · Navigation menus grid · row Edit menu. |
| AFFM-030 | ojs omp ops | `NavigationMenusGridRow` LinkAction `remove` | Website setup · Navigation menus grid · row Delete menu (confirmation). |
| AFFM-031 | ojs omp ops | `managers/NavigationMenuManager/NavigationMenuManagerFormModal.vue` · `NavigationMenuForm` | Website setup · Navigation menu edit modal · drag menu items between Assigned/Unassigned areas + title + Save. |
| AFFM-032 | ojs omp ops | `NavigationMenuItemsGridHandler` LinkAction `addNavigationMenuItem` | Website setup · Navigation menu items grid · Add Item (modal `PKPNavigationMenuItemsForm`). |
| AFFM-033 | ojs omp ops | `NavigationMenuItemsGridRow` LinkAction `edit` | Website setup · Navigation menu items grid · row Edit item. |
| AFFM-034 | ojs omp ops | `NavigationMenuItemsGridRow` LinkAction `remove` | Website setup · Navigation menu items grid · row Delete item (confirmation). |
| AFFM-035 | ojs omp ops | `templates/controllers/grid/navigationMenus/customNMIType.tpl` `#previewButton` (page op `navigationMenu/preview`) | Website setup · Navigation menu item modal · Preview button for custom-page items. |
| AFFM-036 | ojs omp ops | `website.tpl` tab `announcements` · `PKPAnnouncementSettingsForm::FORM_ANNOUNCEMENT_SETTINGS` | Website setup · Announcements enable/settings form (+ Save). |
| AFFM-037 | ojs omp ops | `components/ListPanel/highlights/HighlightsListPanel.vue` Order / Save order / Cancel buttons | Website setup · Highlights panel · order mode (reorder highlights, save/cancel). |
| AFFM-038 | ojs omp ops | `HighlightsListPanel.vue` `openAddModal` (`HighlightsEditModal`) | Website setup · Highlights panel · Add highlight (side modal form). |
| AFFM-039 | ojs omp ops | `HighlightsListPanel.vue` `openEditModal` | Website setup · Highlights panel · item Edit. |
| AFFM-040 | ojs omp ops | `HighlightsListPanel.vue` `openDeleteModal` | Website setup · Highlights panel · item Delete (confirm dialog). |
| AFFM-041 | ojs omp ops | `website.tpl` tab `lists` · `PKPListsForm::FORM_LISTS` | Website setup · Lists form (items per page etc. + Save). |
| AFFM-042 | ojs omp ops | `website.tpl` tab `privacy` · `PKPPrivacyForm::FORM_PRIVACY` | Website setup · Privacy statement form (+ Save). |
| AFFM-043 | ojs omp ops | `website.tpl` tab `dateTime` · `PKPDateTimeForm::FORM_DATE_TIME` · `components/Form/context/DateTimeForm.vue` | Website setup · Date & time formats form (preset radios + custom format inputs + Save). |

## Website settings — Plugins (`management/settings/website` tab `plugins`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-044 | ojs omp ops | `PluginGridCellProvider` LinkActions `enable`/`disable` | Website plugins · Installed grid · per-plugin enable/disable checkbox (disable asks confirmation). |
| AFFM-045 | ojs omp ops | `PluginGridRow` (`$plugin->getActions()` when `_canEdit`) | Website plugins · Installed grid · per-plugin extra actions (e.g. Settings modal), supplied by each plugin. |
| AFFM-046 | ojs omp ops | `PluginGridRow` LinkAction `delete` · guard `in_array(Role::ROLE_ID_SITE_ADMIN, $this->_userRoles)` | Website plugins · Installed grid · row Delete plugin (confirmation). |
| AFFM-047 | ojs omp ops | `PluginGridRow` LinkAction `upgrade` · guards site admin + `PluginHelper::isUploadAllowed()` | Website plugins · Installed grid · row Upgrade (upload-tarball modal). |
| AFFM-048 | ojs omp ops | `PluginGridHandler` LinkAction `upload` · guards site admin + `PluginHelper::isUploadAllowed()` | Website plugins · Installed grid · Upload A New Plugin (file-upload modal). |
| AFFM-049 | ojs omp ops | `PluginGalleryGridHandler::getFilterForm` · tab guard `{if $canSeePluginGallery}` | Website plugins · Plugin gallery grid · category/search filter. |
| AFFM-050 | ojs omp ops | `PluginGalleryGridCellProvider` LinkAction `moreInformation` (op `viewPlugin`) | Website plugins · Plugin gallery grid · row View details modal. |
| AFFM-051 | ojs omp ops | `PluginGalleryGridHandler::_showPluginInfo` LinkAction `installPlugin` · guards `Validation::isSiteAdmin()` + `PluginHelper::isGalleryInstallAllowed()` | Website plugins · Gallery details modal · Install / Upgrade button (confirmation). |

## Website settings — Content (`management/settings/website` tab `content`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-052 | ojs omp ops | `website.tpl` tab `publicComments` · `ContentCommentsForm::FORM_CONTENT_COMMENT` | Website content · Public comments settings form (+ Save). |

## Workflow settings — Submission (`management/settings/workflow` tab `submission`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-053 | ojs omp ops | `templates/management/workflow.tpl` tab `disableSubmissions` · `PKPDisableSubmissionsForm::FORM_DISABLE_SUBMISSIONS` | Workflow submission · Disable submissions form (+ Save; page banner `{if $currentContext->getData('disableSubmissions')}` on all settings pages). |
| AFFM-054 | ojs omp ops | `workflow.tpl` tab `instructions` · component `submissionGuidanceSettings` | Workflow submission · Author guidance/instructions form (+ Save). |
| AFFM-055 | ojs omp ops | `workflow.tpl` tab `metadata` · `PKPMetadataSettingsForm::FORM_METADATA_SETTINGS` | Workflow submission · Metadata enablement form (+ Save). |
| AFFM-056 | ojs omp ops | `GenreGridHandler` LinkAction `addGenre` | Workflow submission · Components (genres) grid · Add a Component (modal). |
| AFFM-057 | ojs omp ops | `GenreGridHandler` LinkAction `restoreGenres` | Workflow submission · Components grid · Restore Defaults (confirmation). |
| AFFM-058 | ojs omp ops | `GenreGridRow` LinkAction `editGenre` | Workflow submission · Components grid · row Edit. |
| AFFM-059 | ojs omp ops | `GenreGridRow` LinkAction `deleteGenre` | Workflow submission · Components grid · row Delete (confirmation). |
| AFFM-060 | ojs omp ops | `GenreGridHandler::initFeatures` `OrderGridItemsFeature` | Workflow submission · Components grid · drag-to-reorder. |
| AFFM-061 | ojs omp ops | `managers/ContributorRoleManager/ContributorRoleManager.vue` button `manager.contributorRoles.add` | Workflow submission · Contributor roles manager · Add role (opens `EditContributorRoleFormModal`). |
| AFFM-062 | ojs omp ops | `contributorRoleManagerStore.js` item action `common.edit` | Workflow submission · Contributor roles manager · row Edit. |
| AFFM-063 | ojs omp ops | `contributorRoleManagerStore.js` item action `manager.contributorRoles.delete.role` · `ContributorRoleDeleteDialogBody` | Workflow submission · Contributor roles manager · row Delete (confirm dialog). |
| AFFM-064 | ops | `ops-main/pages/management/SettingsHandler.php` hook `Template::Settings::workflow::submission` tab `authorScreening` · guard `if (empty($rules)) return` | Workflow submission · Author screening tab listing screening-plugin rules (read-only table; only when screening plugins register rules). |

## Workflow settings — Review (`management/settings/workflow` tab `review`; tab guard `{if $hasReviewStage}` — OPS has no review stage)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-065 | ojs omp | `workflow.tpl` tab `reviewSetup` · `PKPReviewSetupForm::FORM_REVIEW_SETUP` | Workflow review · Review setup form (mode, deadlines, reminders + Save). |
| AFFM-066 | ojs omp | `workflow.tpl` tab `reviewerGuidance` · `PKPReviewGuidanceForm::FORM_REVIEW_GUIDANCE` | Workflow review · Reviewer guidance form (+ Save). |
| AFFM-067 | ojs omp | `ReviewFormGridHandler` LinkAction `createReviewForm` | Workflow review · Review forms grid · Create Review Form (modal). |
| AFFM-068 | ojs omp | `ReviewFormGridRow` LinkAction `edit` | Workflow review · Review forms grid · row Edit (modal with form + items + preview tabs). |
| AFFM-069 | ojs omp | `ReviewFormGridRow` LinkAction `copy` | Workflow review · Review forms grid · row Copy (duplicate form, confirmation). |
| AFFM-070 | ojs omp | `ReviewFormGridRow` LinkAction `preview` | Workflow review · Review forms grid · row Preview. |
| AFFM-071 | ojs omp | `ReviewFormGridCellProvider` LinkActions `activateReviewForm`/`deactivateReviewForm` | Workflow review · Review forms grid · active-column cell toggle (confirmation). |
| AFFM-072 | ojs omp | `ReviewFormGridRow` LinkAction `delete` | Workflow review · Review forms grid · row Delete (confirmation; only for unused forms per row logic). |
| AFFM-073 | ojs omp | `ReviewFormGridHandler::initFeatures` `OrderGridItemsFeature` | Workflow review · Review forms grid · drag-to-reorder forms. |
| AFFM-074 | ojs omp | `ReviewFormElementsGridHandler` LinkAction `createReviewFormElement` | Workflow review · Review form edit modal · Items grid · Create New Item. |
| AFFM-075 | ojs omp | `ReviewFormElementGridRow` LinkAction `edit` | Workflow review · Review form items grid · row Edit item. |
| AFFM-076 | ojs omp | `ReviewFormElementGridRow` LinkAction `delete` | Workflow review · Review form items grid · row Delete item (confirmation). |
| AFFM-077 | ojs omp | `ReviewFormElementsGridHandler::initFeatures` `OrderGridItemsFeature` | Workflow review · Review form items grid · drag-to-reorder items. |
| AFFM-078 | ojs omp | `workflow.tpl` tab `reviewerRecommendations` · guard `{if $hasCustomizableRecommendation}` · `ReviewerRecommendationManager.vue` button `grid.action.addReviewerRecommendation` | Workflow review · Reviewer recommendations manager · Add recommendation (opens `ReviewerRecommendationsEditModal`). |
| AFFM-079 | ojs omp | `ReviewerRecommendationManager.vue` `handleStatusToggle` | Workflow review · Reviewer recommendations · per-item active/inactive switch (yes/no confirm dialog). |
| AFFM-080 | ojs omp | `ReviewerRecommendationManager.vue` item action `common.edit` (`openEditModal`) | Workflow review · Reviewer recommendations · row Edit. |
| AFFM-081 | ojs omp | `ReviewerRecommendationManager.vue` item action `common.delete` (`openDeleteModal`) | Workflow review · Reviewer recommendations · row Delete (confirm dialog). |

## Workflow settings — Publisher library (`management/settings/workflow` tab `library`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-082 | ojs omp ops | `LibraryFileGridHandler` LinkAction `addFile` (`LibraryFileAdminGridHandler`, `canEdit=true`) | Publisher library · Add File (upload + metadata modal, per file-type category). |
| AFFM-083 | ojs omp ops | `LibraryFileGridRow` LinkAction `editFile` | Publisher library · row Edit file (metadata modal incl. public-access setting). |
| AFFM-084 | ojs omp ops | `LibraryFileGridRow` LinkAction `deleteFile` | Publisher library · row Delete file (confirmation). |

## Workflow settings — Emails setup + Tasks & discussions (`management/settings/workflow` tabs `emails`, `taskTemplates`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-085 | ojs omp ops | `workflow.tpl` tab `emails` · component `emailSetup` (`EmailSetupForm`) | Workflow emails · Email setup form (signature, bounce address, options + Save). |
| AFFM-086 | ojs omp ops | `managers/TaskTemplateManager/TaskTemplateManager.vue` per-stage button `taskTemplates.add` | Task & discussion templates · Add template under a workflow stage (opens `TaskTemplateManagerFormModal`). |
| AFFM-087 | ojs omp ops | `TaskTemplateManagerCellAutoAdd.vue` confirm-toggle `taskTemplates.templateAutoAdd` | Task & discussion templates · per-template auto-add switch (enable/disable with confirm text). |
| AFFM-088 | ojs omp ops | `useTaskTemplateManagerConfig.js` item action `common.edit` | Task & discussion templates · row Edit. |
| AFFM-089 | ojs omp ops | `useTaskTemplateManagerConfig.js` item action `common.delete` · `useTaskTemplateManagerActions.js` confirm dialog | Task & discussion templates · row Delete (ok/cancel dialog). |

## Distribution settings (`management/settings/distribution`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-090 | ojs omp ops | `templates/management/distribution.tpl` tab `license` · `PKPLicenseForm::FORM_LICENSE` | Distribution · License form (+ Save). |
| AFFM-091 | ojs omp ops | `distribution.tpl` tab `doisSetup` · `PKPDoiSetupSettingsForm::FORM_DOI_SETUP_SETTINGS` · `DoiSetupSettingsForm.vue` | Distribution · DOIs setup form (enable DOIs, objects, prefix, suffix pattern + Save). |
| AFFM-092 | ojs omp ops | `distribution.tpl` tab `doisRegistration` · `PKPDoiRegistrationSettingsForm::FORM_DOI_REGISTRATION_SETTINGS` · `DoiRegistrationSettingsForm.vue` | Distribution · DOIs registration-agency form (+ Save). |
| AFFM-093 | ojs omp ops | `distribution.tpl` tab `indexing` · `PKPSearchIndexingForm::FORM_SEARCH_INDEXING` | Distribution · Search engine indexing form (+ Save). |
| AFFM-094 | ojs omp | `distribution.tpl` tab `payments` · `PKPPaymentSettingsForm::FORM_PAYMENT_SETTINGS` (OPS `templates/management/distribution.tpl` override omits the tab) | Distribution · Payments method form (enable payments, currency, payment plugin + plugin sub-fields + Save). |
| AFFM-095 | ojs omp ops | `distribution.tpl` tab `statistics` · guard `{if $displayStatisticsTab}` · `PKPContextStatisticsForm::FORM_CONTEXT_STATISTICS` | Distribution · Statistics collection form (+ Save; tab shown only when site enables geo/institution usage-stats options). |
| AFFM-096 | ojs ops | OJS `templates/management/additionalDistributionTabs.tpl` tab `access` / OPS `distribution.tpl` tab `access` · `AccessForm::FORM_ACCESS` | Distribution · Access form (open access / publishing mode + Save). |
| AFFM-097 | ojs | `additionalDistributionTabs.tpl` tab `pln` · component `archivePn` (`FieldArchivingPn`) | Distribution archiving · PKP Preservation Network form (enable PN plugin; falls back to read-only HTML when plugin unavailable). |
| AFFM-098 | ojs | `additionalDistributionTabs.tpl` tab `lockss` · `ArchivingLockssForm::FORM_ARCHIVING_LOCKSS` | Distribution archiving · LOCKSS/CLOCKSS enablement form (+ Save). |

## Users & roles (`management/settings/access`, `templates/management/access.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-099 | ojs omp ops | `managers/UserInvitationManager/UserInvitationManager.vue` button `invitation.inviteToRole.btn` | Users & roles · Users tab · "Invite to a role" button (opens invite wizard, AFFM-118). Claimed by: user-invitations. |
| AFFM-100 | ojs omp ops | `UserInvitationManagerStore.js` action `editInvite` (`userInvitation.edit.title` dialog) | Users & roles · pending invitation row · Edit invitation (confirm dialog then wizard). Claimed by: user-invitations. |
| AFFM-101 | ojs omp ops | `UserInvitationManagerStore.js` cancel action · `UserInvitationManagerCancelInvitationDialogBody` | Users & roles · pending invitation row · Cancel invitation (confirm dialog). Claimed by: user-invitations. |
| AFFM-102 | ojs omp ops | `UserAccessManagerActionSearch.vue` (`userAccess.search`) | Users & roles · current users table · search users. |
| AFFM-103 | ojs omp ops | `useUserAccessManagerConfig.js` action `common.edit` (USER_ACCESS_EDIT → `management/settings/user/{user.id}`, dispatched by `ManagementHandler::editUser()`) | Users & roles · user row · Edit user (routes into role-assignment invitation flow). |
| AFFM-104 | ojs omp ops | `useUserAccessManagerConfig.js` action `email.email` (legacy email modal) | Users & roles · user row · Send email. |
| AFFM-105 | ojs omp ops | `useUserAccessManagerConfig.js` action `grid.user.logInAs` · guards `getCurrentUserId() !== user.id` && `user.canLoginAs` | Users & roles · user row · Login As (ok/cancel dialog). Claimed by: login-and-sessions. |
| AFFM-106 | ojs omp ops | `useUserAccessManagerConfig.js` action `grid.user.remove` · guards not-self && `user.groups.find(g => g.dateEnd === null)` | Users & roles · user row · Remove from active roles (confirm dialog). |
| AFFM-107 | ojs omp ops | `useUserAccessManagerConfig.js` action `grid.user.enable`/`grid.user.disable` · guard not-self | Users & roles · user row · Enable/Disable account (side modal with reason). |
| AFFM-108 | ojs omp ops | `useUserAccessManagerConfig.js` action `grid.action.mergeUser` · guards not-self && `user.canMergeUsers` | Users & roles · user row · Merge user (legacy merge modal). |
| AFFM-109 | ojs omp ops | `UserGroupGridHandler` LinkAction `addUserGroup` | Users & roles · Roles tab grid · Create New Role (modal). |
| AFFM-110 | ojs omp ops | `UserGroupGridRow` LinkAction `editUserGroup` | Users & roles · Roles grid · row Edit role. |
| AFFM-111 | ojs omp ops | `UserGroupGridRow` LinkAction `removeUserGroup` | Users & roles · Roles grid · row Remove role (confirmation). |
| AFFM-112 | ojs omp ops | `UserGroupGridCellProvider::getCellActions` ops `assignStage`/`unassignStage` (skips `RoleDAO::getForbiddenStages`) | Users & roles · Roles grid · per-workflow-stage checkbox toggle assigning the role to a stage. |
| AFFM-113 | ojs omp ops | `UserGroupGridHandler::getFilterForm` + `PagingFeature` | Users & roles · Roles grid · filter form + pagination. |
| AFFM-114 | ojs omp ops | `access.tpl` tab `notify` · guard `{if $enableBulkEmails}` · `PKPNotifyUsersForm::FORM_NOTIFY_USERS` · `NotifyUsersForm.vue` send-confirm dialog | Users & roles · Notify tab · compose bulk email to selected role groups + Send (confirm dialog). |
| AFFM-115 | ojs omp ops | `access.tpl` `v-if="totalBulkJobs"` block · button `manager.setup.notifyUsers.sendAnother` (`AccessPage.vue::reload`) | Users & roles · Notify tab · queued-notice with "send another" reload button after a bulk send. |
| AFFM-116 | ojs omp ops | `access.tpl` tab `access` · `PKPUserAccessForm::FORM_USER_ACCESS` | Users & roles · Site access options form (registration/login restrictions + Save). |
| AFFM-117 | ojs omp ops | `access.tpl` tab `orcidSettings` · component `orcidSettings` | Users & roles · ORCID settings form (context-level ORCID API config + Save). Claimed by: orcid-integration. |

## Invite-to-role wizard (`management/settings/invitation`-launched `pages/userInvitation/UserInvitationPage.vue`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-118 | ojs omp ops | `UserInvitationSearchFormStep.vue` | Invite wizard · search for existing user by email/ORCID/username (or proceed to invite new). Claimed by: user-invitations. |
| AFFM-119 | ojs omp ops | `UserInvitationDetailsFormStep.vue` + `UserInvitationUserGroupsTable.vue` | Invite wizard · enter user details and add/remove role assignments (start/end dates, masthead flag). Claimed by: user-invitations. |
| AFFM-120 | ojs omp ops | `UserInvitationEmailComposerStep.vue` + `UserInvitationPage.vue` Cancel/Back/Continue/Submit buttons | Invite wizard · compose invitation email; step navigation and final Invite submit. Claimed by: user-invitations. |

## Emails management (`management/settings/manageEmails`, `templates/management/manageEmails.tpl` + `ManageEmailsPage`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-121 | ojs omp ops | `manageEmails.tpl` `<search>` (`manager.mailables.search`) | Emails · search mailables. |
| AFFM-122 | ojs omp ops | `manageEmails.tpl` sidebar `pkp-filter` sets `groupIds`/`fromRoleIds`/`toRoleIds` | Emails · filter mailables by group / sent-from role / sent-to role. |
| AFFM-123 | ojs omp ops | `manageEmails.tpl` button `manager.emails.resetAll` · `ManageEmailsPage.vue::confirmResetAll` | Emails · Reset All templates (warnable confirm dialog). |
| AFFM-124 | ojs omp ops | `manageEmails.tpl` item action `openMailable` → `EditMailableModal.vue` | Emails · per-mailable Edit (side modal listing its templates). |
| AFFM-125 | ojs omp ops | `EditMailableModal.vue` button `manager.emails.addEmail` (`openTemplate(null)`) | Emails · mailable modal · Add Template. |
| AFFM-126 | ojs omp ops | `EditMailableModal.vue` item button `common.edit` → `EditTemplateModal.vue` template form | Emails · mailable modal · Edit template (subject/body form + Save). |
| AFFM-127 | ojs omp ops | `EditMailableModal.vue` button `common.reset` · `v-if="item.key === mailable.emailTemplateKey && item.id"` | Emails · mailable modal · Reset default template to factory content (confirm dialog). |
| AFFM-128 | ojs omp ops | `EditMailableModal.vue` button `common.remove` · `v-else-if="item.id"` | Emails · mailable modal · Remove custom template (confirm dialog). |

## Announcements manager (`management/announcements`, `templates/management/announcements.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-129 | ojs omp ops | `AnnouncementsListPanel.vue` `<Search>` | Announcements · search announcements. |
| AFFM-130 | ojs omp ops | `AnnouncementsListPanel.vue` `openAddModal` (`AnnouncementsEditModal`) | Announcements · Add announcement (side modal form). |
| AFFM-131 | ojs omp ops | `AnnouncementsListPanel.vue` item button `common.view` (`item.url`) | Announcements · item View (opens public announcement page). |
| AFFM-132 | ojs omp ops | `AnnouncementsListPanel.vue` `openEditModal` | Announcements · item Edit. |
| AFFM-133 | ojs omp ops | `AnnouncementsListPanel.vue` `openDeleteModal` (yes/no dialog) | Announcements · item Delete. |
| AFFM-134 | ojs omp ops | `AnnouncementTypeGridHandler` LinkAction `addAnnouncementType` | Announcements · Types tab grid · Add Announcement Type. |
| AFFM-135 | ojs omp ops | `AnnouncementTypeGridRow` LinkAction `edit` (also title cell action) | Announcements · Types grid · row Edit. |
| AFFM-136 | ojs omp ops | `AnnouncementTypeGridRow` LinkAction `remove` | Announcements · Types grid · row Delete (confirmation). |

## Institutions manager (`management/institutions`, `templates/management/institutions.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-137 | ojs omp ops | `InstitutionsListPanel.vue` `<Search>` | Institutions · search institutions. |
| AFFM-138 | ojs omp ops | `InstitutionsListPanel.vue` `openAddModal` (`InstitutionsEditModal`) | Institutions · Add institution (side modal form). |
| AFFM-139 | ojs omp ops | `InstitutionsListPanel.vue` `openEditModal` | Institutions · item Edit. |
| AFFM-140 | ojs omp ops | `InstitutionsListPanel.vue` `openDeleteModal` (yes/no dialog) | Institutions · item Delete. |

## User comments moderation (`management/userComments`, `templates/management/userComments.tpl` + `UserCommentsPage`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-141 | ojs omp ops | `UserCommentsPage.vue` `<Tabs>` · `useUserCommentsConfig.js::getPageTabs` (all/approved/hiddenOrNeedsApproval/reported) | User comments · status filter tabs. |
| AFFM-142 | ojs omp ops | `useUserCommentsConfig.js` comment action `manager.userComment.viewComment` → `UserCommentDetailModal` | User comments · comment row · View (detail side modal). |
| AFFM-143 | ojs omp ops | `useUserCommentsConfig.js` comment action `manager.userComment.deleteComment` · `userCommentStore.js` confirm dialog | User comments · comment row · Delete (confirm dialog). |
| AFFM-144 | ojs omp ops | `UserCommentDetailModal.vue` buttons `approveComment`/`hideComment`/`deleteComment` | User comments · detail modal · Approve / Hide / Delete the comment. |
| AFFM-145 | ojs omp ops | `useUserCommentsConfig.js` report action `manager.userComment.viewReport` → `UserCommentReportDetailModal` | User comments · Reports table · row View report. |
| AFFM-146 | ojs omp ops | `useUserCommentsConfig.js` report action `manager.userComment.deleteReport` · confirm dialog | User comments · Reports table · row Delete report. |
| AFFM-147 | ojs omp ops | `UserCommentsTable.vue`/`UserCommentReportsTable.vue` `TablePagination` | User comments · comments and reports tables pagination. |

## DOIs management page (`dois`, app `templates/management/dois.tpl` + `DoiListPanel[OJS/OMP/OPS]`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-148 | ojs omp ops | app `dois.tpl` tabs · guards `{if $displaySubmissionsTab}` / OJS `{if $displayIssuesTab}` | DOIs page · object-type tabs (OJS: Articles + Issues; OMP: Monographs; OPS: Publications). |
| AFFM-149 | ojs omp ops | `DoiListPanel.vue` `<Search>` | DOIs · search list. |
| AFFM-150 | ojs omp ops | `DoiListPanel.vue` bulk dropdown `toggleSelectAll` / `toggleExpandAll` | DOIs · bulk-actions dropdown · Select all/none and Expand/Collapse all. |
| AFFM-151 | ojs omp ops | `DoiListPanel.vue` `openBulkExport` | DOIs · bulk Export selected (via configured registration plugin). |
| AFFM-152 | ojs omp ops | `DoiListPanel.vue` `openBulkMarkRegistered` / `openBulkMarkUnregistered` / `openBulkMarkStale` | DOIs · bulk Mark registered / unregistered / stale (confirm dialogs). |
| AFFM-153 | ojs omp ops | `DoiListPanel.vue` `openBulkAssign` | DOIs · bulk Assign DOIs to selected items (confirm dialog). |
| AFFM-154 | ojs omp ops | `DoiListPanel.vue` `openBulkDeposit` and button `manager.dois.actions.deposit.all` (`openBulkDepositAll`) | DOIs · bulk Deposit selected and Deposit-all jobs. |
| AFFM-155 | ojs omp ops | `DoiListPanel.vue` filters sidebar (`common.filter`, backend-supplied filters) | DOIs · status/object filters sidebar toggle. |
| AFFM-156 | ojs omp ops | `DoiListPanel.vue` `openStatusInfoModal` (`DoiStatusInfoModal`) | DOIs · registration-status legend/info side modal. |
| AFFM-157 | ojs omp ops | `DoiListItem.vue` select checkbox + expand toggle | DOIs · per-item select and expand. |
| AFFM-158 | ojs omp ops | `DoiListItem.vue` button `common.edit`/`common.save` (`editDois`/`saveDois`) | DOIs · per-item edit DOI value(s) inline + save. |
| AFFM-159 | ojs omp ops | `DoiListItem.vue` `openVersionModal` (`DoiItemVersionModal`) | DOIs · per-item view DOIs of other publication versions. |
| AFFM-160 | ojs omp ops | `DoiListItem.vue` `viewRecord` / `handleDepositorActions` / `openViewErrorModal` | DOIs · per-item View registered record, Deposit now, View registration error (dialogs). |

## Tools (`management/tools`, `templates/management/tools/*.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-161 | ojs omp ops | `tools/index.tpl` tabs `importexport`/`permissions` | Tools · tab bar (Import/Export, Permissions). |
| AFFM-162 | ojs omp ops | `tools/importexport.tpl` plugin link list (`op=importexport path=plugin/<name>`) | Tools · Import/Export · list of import/export plugin links opening each plugin's screen. |
| AFFM-163 | ojs omp ops | `tools/permissions.tpl` `#resetPermissionsForm` (op `tools/resetPermissions`, `confirmText manager.setup.resetPermissions.confirm`) | Tools · Permissions · Reset article/monograph permissions submit (confirm). |
| AFFM-164 | ojs omp ops | `plugins/importexport/native/templates/index.tpl` `#importXmlForm` (upload container + `importBounce`) | Tools · Native XML plugin · Import tab: upload XML file + Import button. |
| AFFM-165 | ojs omp ops | `native/templates/index.tpl` `#exportSubmissions-tab` (`SubmissionsListPanel` select + `toggleSelectAll` + submit `exportSubmissionsBounce`; per-item workflow link) | Tools · Native XML plugin · Export submissions: select submissions (select all/none), per-item View-workflow link, Export button. |
| AFFM-166 | ojs | `ojs-main/plugins/importexport/native/templates/index.tpl` `#exportIssues-tab` | Tools · Native XML plugin (OJS) · Export issues tab: select issues + Export button. |
| AFFM-167 | ojs omp | `plugins/importexport/users/templates/index.tpl` `#importXmlForm` / `#exportXmlForm` | Tools · Users XML plugin · Import users (upload + Import) and Export users (select + Export) tabs. |
| AFFM-168 | ojs | `ojs-main/plugins/importexport/pubmed/templates/index.tpl` (+ `settingsForm.tpl`) | Tools · PubMed export plugin (OJS) · select/export articles UI + plugin settings form. |
| AFFM-169 | omp | `omp-main/plugins/importexport/onix30/templates/index.tpl` | Tools · ONIX 3.0 export plugin (OMP) · select/export monographs UI. |
| AFFM-170 | omp | `omp-main/templates/management/tools/form/statisticsSettingsForm.tpl` (op `tools/saveStatisticsSettings`) | Tools (OMP) · statistics settings form template with Save — no PHP op found by grep (dead-code candidate for Phase 1). |
| AFFM-171 | ojs omp ops | `templates/stats/reports.tpl` report plugin link list (op `reports/report`) | Statistics reports tool · list of report-generator plugin links (each triggers a CSV report download/screen). |

## OJS Subscriptions & Payments (`payments` page, `templates/payments/index.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-172 | ojs | `payments/index.tpl` `#subscriptionsTabs` | Payments page · tab bar: Individual subscriptions, Institutional subscriptions, Subscription types, Subscription policies, Payment types, Payment method. |
| AFFM-173 | ojs | `SubscriptionsGridHandler` LinkAction `addSubscription` (Individual + Institutional grids) | Subscriptions grids · Create New Subscription (select-user + subscription form modal). |
| AFFM-174 | ojs | `SubscriptionsGridHandler::getFilterForm` | Subscriptions grids · search/filter form (search field + match criteria). |
| AFFM-175 | ojs | `SubscriptionsGridRow` LinkAction `edit` | Subscriptions grids · row Edit subscription. |
| AFFM-176 | ojs | `SubscriptionsGridRow` LinkAction `renew` | Subscriptions grids · row Renew (confirmation). |
| AFFM-177 | ojs | `SubscriptionsGridRow` LinkAction `delete` | Subscriptions grids · row Delete (confirmation). |
| AFFM-178 | ojs | `SubscriptionTypesGridHandler` LinkAction `addSubscriptionType` | Subscription types grid · Create New Subscription Type (modal `SubscriptionTypeForm`). |
| AFFM-179 | ojs | `SubscriptionTypesGridRow` LinkActions `edit` / `delete` | Subscription types grid · row Edit and Delete (confirmation). |
| AFFM-180 | ojs | `templates/payments/subscriptionPolicyForm.tpl` (ops `subscriptionPolicies`/`saveSubscriptionPolicies`) | Subscription policies · form + Save. |
| AFFM-181 | ojs | `templates/payments/paymentTypesForm.tpl` (ops `paymentTypes`/`savePaymentTypes`) | Payment types · fee amounts/options form + Save. |
| AFFM-182 | ojs | `PaymentsGridHandler` op `viewPayment` | Payment method tab · payments grid · row payment Details modal (read-only). |
| AFFM-239 | ojs | `templates/payments/individualSubscriptionForm.tpl` `#individualSubscriptionForm` (op `updateSubscription`) | Individual subscription add/edit modal (behind AFFM-173/175) · type/status/date/reference fields, `notifyEmail` checkbox, Save. |
| AFFM-240 | ojs | `templates/payments/institutionalSubscriptionForm.tpl` `#institutionalSubscriptionForm` (op `updateSubscription`) | Institutional subscription add/edit modal (behind AFFM-173/175) · type/status/institution select/mailing/domain-IP fields, `notifyEmail` checkbox, Save. |
| AFFM-241 | ojs | `SubscriberSelectGridHandler` (`grid.users.subscriberSelect`) embedded via `{load_url_in_div id='subscriberSelectGridContainer'}` in both subscription form templates | Subscription add/edit modals · subscriber-select grid: search/filter users (`getFilterForm`) + row radio to pick the subscriber. |
| AFFM-242 | ojs | `templates/payments/subscriptionTypeForm.tpl` `#subscriptionTypeForm` (op `updateSubscriptionType`) | Subscription type add/edit modal (behind AFFM-178/179) · name/cost/currency select/format select/options fields, Save. |

## Site administration — index (`admin`, `templates/admin/index.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-183 | ojs omp ops | `admin/index.tpl` button `admin.hostedContexts` (op `contexts`) | Site admin · Hosted Journals/Presses/Servers link. |
| AFFM-184 | ojs omp ops | `admin/index.tpl` button `admin.siteSettings` (op `settings`) | Site admin · Site Settings link (single-context installs route here for site setup). |
| AFFM-185 | ojs omp ops | `admin/index.tpl` button `admin.systemInformation.view` (op `systemInfo`) | Site admin · System Information link. |
| AFFM-186 | ojs omp ops | `admin/index.tpl` POST form op `expireSessions` (`confirm admin.confirmExpireSessions`) | Site admin · Expire User Sessions button (confirm; logs everyone out). |
| AFFM-187 | ojs omp ops | `admin/index.tpl` POST form op `clearDataCache` | Site admin · Clear Data Caches button. |
| AFFM-188 | ojs omp ops | `admin/index.tpl` POST form op `clearTemplateCache` (`confirm admin.confirmClearTemplateCache`) | Site admin · Clear Template Cache button (confirm). |
| AFFM-189 | ojs omp ops | `admin/index.tpl` POST form op `clearScheduledTaskLogFiles` (`confirm admin.scheduledTask.confirmClearLogs`) | Site admin · Clear scheduled task execution logs button (confirm). |
| AFFM-190 | ojs omp ops | `admin/index.tpl` buttons `navigation.tools.jobs.view` (op `jobs`) / `navigation.tools.jobs.failed.view` (op `failedJobs`) | Site admin · View Jobs and View Failed Jobs links. |
| AFFM-191 | ojs omp ops | `templates/user/confirmPassword.tpl` (ops `confirmAccess`/`confirmAccessSubmit`) | Site admin · re-enter password confirmation form gating admin ops. Claimed by: login-and-sessions. |

## Site administration — hosted contexts (`admin/contexts`, `templates/admin/contexts.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-192 | ojs omp ops | `ContextGridHandler` LinkAction `createContext` · `admin/editContext.tpl` `add-context-form` (`AddContextForm.vue` redirects to wizard on success) | Hosted contexts · Create Journal/Press/Server (side modal `PKPContextForm`; on save redirects to the context's settings wizard). |
| AFFM-193 | ojs omp ops | `ContextGridRow` LinkAction `edit` (`editContext.tpl` `pkp-form`) | Hosted contexts · row Edit context form (name/path/enabled + Save). |
| AFFM-194 | ojs omp ops | `ContextGridRow` LinkAction `delete` | Hosted contexts · row Remove context (confirmation; deletes context and content). |
| AFFM-195 | ojs omp ops | `ContextGridRow` LinkAction `wizard` (op `admin/wizard/{path}`) | Hosted contexts · row Settings wizard link (opens the context settings wizard screen, AFFM-197..210). |
| AFFM-196 | ojs omp ops | `ContextGridHandler::initFeatures` `OrderGridItemsFeature` | Hosted contexts · drag-to-reorder contexts (site-wide listing order). |

## Site administration — context settings wizard (`admin/wizard/{path}`, `templates/admin/contextSettings.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-197 | ojs omp ops | `contextSettings.tpl` tab `context` · `PKPContextForm::FORM_CONTEXT` | Context wizard · Context form (name, path, description, enabled + Save). |
| AFFM-198 | ojs omp ops | `contextSettings.tpl` tab `appearance` · `PKPThemeForm::FORM_THEME` | Context wizard · Theme form for the edited context (+ Save). |
| AFFM-199 | ojs omp ops | `contextSettings.tpl` tab `languages` · `ManageLanguageGridHandler` + `SubmissionLanguageGridHandler` for `$editContext` | Context wizard · Languages tab embedding the context language grids (same controls as AFFM-023..027). |
| AFFM-200 | ojs omp ops | `contextSettings.tpl` tab `indexing` · `PKPSearchIndexingForm::FORM_SEARCH_INDEXING` | Context wizard · Search indexing form (+ Save). |
| AFFM-201 | ojs omp ops | `contextSettings.tpl` tab `restrictBulkEmails` · `PKPRestrictBulkEmailsForm::FORM_RESTRICT_BULK_EMAILS` · guard `{if $bulkEmailsEnabled}` else link to site settings `setup/bulkEmails` | Context wizard · Restrict bulk emails per-role form (+ Save; disabled-state text links to site settings). |
| AFFM-202 | ojs omp ops | `contextSettings.tpl` tab `plugins` · `SettingsPluginGridHandler` + `PluginGalleryGridHandler` for `$editContext` · gallery guard `{if $canSeePluginGallery}` | Context wizard · Plugins tab embedding the context plugin grids (same controls as AFFM-044..051). |
| AFFM-203 | ojs omp ops | `UserGridHandler` LinkAction `addUser` | Context wizard · Users tab grid · Add User (create user form modal). |
| AFFM-204 | ojs omp ops | `UserGridHandler::getFilterForm` + `PagingFeature` | Context wizard · Users grid · search/filter + pagination. |
| AFFM-205 | ojs omp ops | `UserGridRow` LinkAction `email` | Context wizard · Users grid · row Email user. |
| AFFM-206 | ojs omp ops | `UserGridRow` LinkAction `edit` | Context wizard · Users grid · row Edit user (legacy user form modal). |
| AFFM-207 | ojs omp ops | `UserGridRow` LinkActions `enable`/`disable` | Context wizard · Users grid · row Enable/Disable user (reason modal). |
| AFFM-208 | ojs omp ops | `UserGridRow` LinkAction `remove` | Context wizard · Users grid · row Remove user from context (confirmation). |
| AFFM-209 | ojs omp ops | `UserGridRow` LinkAction `logInAs` | Context wizard · Users grid · row Login As (confirmation). Claimed by: login-and-sessions. |
| AFFM-210 | ojs omp ops | `UserGridRow` LinkAction `mergeUser` (row + grid-level variants) | Context wizard · Users grid · row Merge User (pick target then merge). |

## Site administration — site settings (`admin/settings`, `templates/admin/settings.tpl`; every tab guarded by `{if $componentAvailability['<key>']}`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-211 | ojs omp ops | `admin/settings.tpl` tab `settings` · `PKPSiteConfigForm::FORM_SITE_CONFIG` · guard `componentAvailability['siteConfig']` | Site settings · Site setup form (title, redirect, min password length + Save). |
| AFFM-212 | ojs omp ops | `settings.tpl` tab `security` · `PKPSiteSecurityForm::FORM_SITE_SECURITY` · guard `['siteSecurity']` | Site settings · Security form (+ Save). |
| AFFM-213 | ojs omp ops | `settings.tpl` tab `info` · `PKPSiteInformationForm::FORM_SITE_INFO` · guard `['siteInfo']` | Site settings · Site information form (about, contact, privacy + Save). |
| AFFM-214 | ojs omp ops | `AdminLanguageGridHandler` LinkAction `installLocale` · tab guard `['languages']` | Site settings · Languages grid · Install Locale (checkbox modal, op `saveInstallLocale`). |
| AFFM-215 | ojs omp ops | `LanguageGridRow` LinkAction `uninstall` (op `uninstallLocale`) | Site settings · Languages grid · row Uninstall locale (confirmation). |
| AFFM-216 | ojs omp ops | `LanguageGridRow` LinkAction `reload` (op `reloadLocale`) | Site settings · Languages grid · row Reload localized defaults (confirmation). |
| AFFM-217 | ojs omp ops | `AdminLanguageGridHandler` ops `enableLocale`/`disableLocale`/`setPrimaryLocale` | Site settings · Languages grid · enable checkboxes + site primary-locale radio. |
| AFFM-218 | ojs omp ops | `settings.tpl` tab `nav` · `NavigationMenusGridHandler` + `NavigationMenuItemsGridHandler` (site-level) · guard `['navigationMenus']` | Site settings · Navigation tab embedding the site-level navigation menu grids (same controls as AFFM-028..035). |
| AFFM-219 | ojs omp ops | `settings.tpl` tab `highlights` · `highlights-list-panel` · guard `['highlights']` | Site settings · Highlights tab embedding the highlights panel at site scope (same controls as AFFM-037..040). |
| AFFM-220 | ojs omp ops | `settings.tpl` tab `bulkEmails` · `PKPSiteBulkEmailsForm::FORM_SITE_BULK_EMAILS` · guard `['bulkEmails']` | Site settings · Bulk emails form (which contexts may send + Save). |
| AFFM-221 | ojs omp ops | `settings.tpl` tab `statistics` · `PKPSiteStatisticsForm::FORM_SITE_STATISTICS` · guard `['statistics']` | Site settings · Site statistics collection form (geo/institution/SUSHI + Save). |
| AFFM-222 | ojs omp ops | `settings.tpl` tab `orcidSiteSettings` · component `orcidSiteSettings` · guard `['orcidSiteSettings']` | Site settings · site-wide ORCID API settings form (+ Save). Claimed by: orcid-integration. |
| AFFM-223 | ojs omp ops | `settings.tpl` appearance tab `theme` · `PKPThemeForm::FORM_THEME` · guards `['siteAppearance']`/`['siteTheme']` | Site settings · Appearance · site Theme form (+ Save). |
| AFFM-224 | ojs omp ops | `settings.tpl` appearance tab `setup` · `PKPSiteAppearanceForm::FORM_SITE_APPEARANCE` · guard `['siteAppearanceSetup']` | Site settings · Appearance · setup form (logo, stylesheet, sidebar + Save). |
| AFFM-225 | ojs omp ops | `settings.tpl` tab `announcement-settings` · `PKPAnnouncementSettingsForm::FORM_ANNOUNCEMENT_SETTINGS` · guard `['announcements']` | Site settings · Announcements · enable/settings form (+ Save). |
| AFFM-226 | ojs omp ops | `settings.tpl` tab `announcement-items` · `announcements-list-panel` · guard `v-if="announcementsEnabled"` (else notEnabled text) | Site settings · Announcements · site announcements panel (same controls as AFFM-129..133). |
| AFFM-227 | ojs omp ops | `settings.tpl` tab `announcement-types` · `AnnouncementTypeGridHandler` (site scope) · guard `v-if="announcementsEnabled"` | Site settings · Announcements · site announcement types grid (same controls as AFFM-134..136). |
| AFFM-228 | ojs omp ops | `settings.tpl` tab `installedPlugins` · `AdminPluginGridHandler` · guard `['sitePlugins']` | Site settings · Plugins · site-wide plugin grid (same row controls as AFFM-044..048, site scope). |
| AFFM-229 | ojs omp ops | `settings.tpl` tab `pluginGallery` · `PluginGalleryGridHandler` (site scope) | Site settings · Plugins · plugin gallery grid (same controls as AFFM-049..051). |

## Site administration — jobs pages (`admin/jobs`, `admin/failedJobs`, `admin/failedJobDetails`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-230 | ojs omp ops | `pages/jobs/JobsPage.vue` (`admin/jobs.tpl`) | Jobs page · read-only queued-jobs table with pagination. |
| AFFM-231 | ojs omp ops | `pages/jobs/FailedJobsPage.vue` button `admin.jobs.failed.action.redispatch.all` (`requeueAll`) | Failed jobs · Requeue All Failed Jobs button. |
| AFFM-232 | ojs omp ops | `FailedJobsPage.vue` row button `admin.jobs.failed.action.redispatch` | Failed jobs · row Try Again (redispatch). |
| AFFM-233 | ojs omp ops | `FailedJobsPage.vue` row button `common.delete` | Failed jobs · row Delete failed job. |
| AFFM-234 | ojs omp ops | `FailedJobsPage.vue` row link `common.details` (`row._hrefs._details`) | Failed jobs · row Details link to failed-job payload page. |
| AFFM-235 | ojs omp ops | `pages/jobs/FailedJobDetailsPage.vue` (`admin/failedJobDetails.tpl`) | Failed job details · read-only attribute/payload/exception table. |

## Site administration — system information (`admin/systemInfo`, `templates/admin/systemInfo.tpl`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-236 | ojs omp ops | `systemInfo.tpl` link `admin.version.checkForUpdates` (`{url versionCheck=1}`) | System info · Check for updates link (re-renders with latest-version info). |
| AFFM-237 | ojs omp ops | `systemInfo.tpl` links `admin.version.downloadPackage`/`downloadPatch`/`moreInfo` (shown when `$latestVersionInfo` present) | System info · download package/patch and more-info links for available update. |
| AFFM-238 | ojs omp ops | `systemInfo.tpl` link `admin.phpInfo` (op `phpinfo`, target _blank) | System info · Extended PHP information link. |

## OJS Issues management (`manageIssues` page, `templates/manageIssues/issues.tpl`; overlap with AFFW workflow-embedded issue-assignment is deliberate at this edge)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-243 | ojs | `templates/manageIssues/issues.tpl` tabs `future`/`back` | Issues page · tab bar: Future Issues (`FutureIssueGridHandler`) and Back Issues (`BackIssueGridHandler`). |
| AFFM-244 | ojs | `FutureIssueGridHandler` LinkAction `addIssue` | Future issues grid · Create Issue (modal `IssueForm`). |
| AFFM-245 | ojs | `IssueGridRow` LinkAction `edit` (op `editIssue`, `templates/controllers/grid/issues/issue.tpl` `#editIssueTabs`) | Issues grids · row Edit issue — modal with tabs: Table of Contents, Issue Data, Galleys, Identifiers (`{if $enableIdentifiers}`), Access (`{if $currentJournal->getData('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION}`). |
| AFFM-246 | ojs | `IssueGridHandler` ops `editIssueData`/`updateIssue` · `issueForm.tpl` · `IssueForm` LinkAction `deleteCoverImage` | Edit issue · Issue Data tab · issue form (identification, dates, cover upload + Delete Cover Image, Save). |
| AFFM-247 | ojs | `TocGridHandler::initFeatures` `OrderCategoryGridItemsFeature(ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS)` | Edit issue · TOC tab · drag-to-reorder sections and articles within sections. |
| AFFM-248 | ojs | `TocGridRow` LinkAction `workflow` | Edit issue · TOC tab · article row link to its submission workflow. |
| AFFM-249 | ojs | `TocGridRow` LinkAction `removeArticle` | Edit issue · TOC tab · article row Remove from issue (confirmation). |
| AFFM-250 | ojs | `TocGridCellProvider::getCellActions` LinkAction `disable` (op `setAccessStatus`) · guard `publishingMode == PUBLISHING_MODE_SUBSCRIPTION && $issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION` | Edit issue · TOC tab · per-article open-access checkbox toggle. |
| AFFM-251 | ojs | `IssueGalleyGridHandler` LinkAction `add` (`issueGalleyForm.tpl`) | Edit issue · Galleys tab · Add Galley (upload + label/locale form). |
| AFFM-252 | ojs | `IssueGalleyGridRow` LinkAction `edit` | Edit issue · Galleys tab · row Edit galley. |
| AFFM-253 | ojs | `IssueGalleyGridRow` LinkAction `delete` | Edit issue · Galleys tab · row Delete galley (confirmation). |
| AFFM-254 | ojs | `IssueGalleyGridHandler::initFeatures` `OrderGridItemsFeature` | Edit issue · Galleys tab · drag-to-reorder galleys. |
| AFFM-255 | ojs | `IssueGridHandler` ops `identifiers`/`updateIdentifiers`/`clearPubId`/`clearIssueObjectsPubIds` · tab guard `{if $enableIdentifiers}` | Edit issue · Identifiers tab · public identifiers form (+ Save, Clear pub id, Clear pub ids of issue objects). |
| AFFM-256 | ojs | `IssueGridHandler` ops `access`/`updateAccess` · `issueAccessForm.tpl` · tab guard subscription publishing mode | Edit issue · Access tab · issue access status + open-access date form (+ Save). |
| AFFM-257 | ojs | `IssueGridRow` LinkAction `viewIssue`/`previewIssue` (`OpenWindowAction` → page `issue/view`) | Issues grids · row View Issue (published) / Preview Issue (unpublished) in new window. |
| AFFM-258 | ojs | `IssueGridRow` LinkAction `publish` (op `publishIssue` modal; assign-public-identifiers form via `getAssignPublicIdentifiersFormTemplate`) · guard `!$issue->getPublished()` | Future issues grid · row Publish Issue (modal, incl. pub-id assignment confirmation). |
| AFFM-259 | ojs | `IssueGridRow` LinkAction `unpublish` (op `unpublishIssue`, `RemoteActionConfirmationModal editor.issues.confirmUnpublish`) · guard `$issue->getPublished()` | Back issues grid · row Unpublish Issue (confirmation). |
| AFFM-260 | ojs | `IssueGridRow` LinkAction `setCurrentIssue` · guard `$issue->getPublished() && !$isCurrentIssue` | Back issues grid · row set as Current Issue (confirmation). |
| AFFM-261 | ojs | `IssueGridRow` LinkAction `delete` (op `deleteIssue`) | Issues grids · row Delete issue (confirmation). |
| AFFM-262 | ojs | `BackIssueGridHandler::initFeatures` `OrderGridItemsFeature` | Back issues grid · drag-to-reorder published issues. |

## OMP Catalog management (`manageCatalog` page, `templates/manageCatalog/index.tpl` + `CatalogListPanel`)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFM-263 | omp | `templates/manageCatalog/index.tpl` tab `monographs` · `ManageCatalogPage.vue` (VUE-020) | Catalog management · single "All monographs" tab hosting `catalog-list-panel` (category/series views reached via its filters). |
| AFFM-264 | omp | `CatalogListPanel.vue` `<Search>` | Catalog management · search monographs. |
| AFFM-265 | omp | `CatalogListPanel.vue` filter toggle `common.filter` + sidebar filters | Catalog management · filters sidebar (category / series scoping; switching filter changes featured/new-release context). |
| AFFM-266 | omp | `CatalogListPanel.vue` `openAddEntryForm` → `CatalogEditModal.vue` (VUE-101) | Catalog management · Add Entry (side modal form selecting a published monograph for the catalog). |
| AFFM-267 | omp | `CatalogListPanel.vue` `toggleOrdering`/`cancelOrdering` (`submission.list.orderFeatures`/`saveFeatureOrder`) · shown `v-if` any displayed item is featured (`canOrderCurrent`) | Catalog management · Order Features mode toggle with Save/Cancel. |
| AFFM-268 | omp | `CatalogListItem.vue` `toggleFeatured` (`catalog.manage.isFeatured`/`isNotFeatured`) | Catalog management · per-monograph Featured toggle (catalog/category/series scope per active filter). |
| AFFM-269 | omp | `CatalogListItem.vue` `toggleNewRelease` (`catalog.manage.isNewRelease`/`isNotNewRelease`) | Catalog management · per-monograph New Release toggle (scope per active filter). |
| AFFM-270 | omp | `CatalogListItem.vue` `@up`/`@down` order arrows (`order-up`/`order-down`) | Catalog management · per-featured-monograph move up/down while in ordering mode. |
| AFFM-271 | omp | `CatalogListItem.vue` links `submission.list.viewSubmission` (`item.urlWorkflow`) / `submission.list.viewEntry` (`item.urlPublished`) | Catalog management · per-monograph View Submission (workflow) and View Entry (public catalog page) links. |
