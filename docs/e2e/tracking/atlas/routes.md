# Atlas — routes (page handlers + ops)

- **Modality**: routes — legacy page-router surface: `pages/` handler classes and their ops.
- **Sweep date**: 2026-07-26
- **Trees swept**:
  - Shared: `lib/pkp/pages/` (swept once; task-canonical submodule SHA `9db481cf4d`; observed at sweep time: ojs-main lib/pkp working HEAD `24cb2d923c` with local docs commits, omp/ops submodules at `ad4606f93e` — the three `lib/pkp/pages/` trees verified **byte-identical** via `diff -rq`, so the single sweep is valid)
  - App: `ojs-main/pages/`, `omp-main/pages/`, `ops-main/pages/`
- **Method (exact commands)**:
  - `find <tree>/pages -name '*.php'` — enumerate handler + dispatcher files
  - perl extraction over every non-`index.php` file: `class X extends Y` declarations, `->addRoleAssignment(...)` blocks, `public function` names
  - `grep -oE 'return new [A-Za-z\\]+' <every index.php>` — dispatcher-to-handler mapping (which page name reaches which class)
  - `diff -rq` across the three `lib/pkp/pages/` checkouts
- **Granularity ruling** (maintainer-approved): one atom per handler class; ops inline in description. Ops = union of `addRoleAssignment` op names and public op-shaped methods, excluding `__construct`, `authorize`, `initialize`, `setupTemplate`, `setupIndex`, and underscore-prefixed helpers. Role-assigned ops with no matching method are listed as declared (no liveness judgment — Phase 1 resolves).
- **Apps column**: shared atoms default to `ojs omp ops`; deviations (missing app dispatcher, app-side replacement) noted inline. App-side subclass = its own atom, cross-referenced.

## Atoms

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| ROUTE-001 | ojs omp ops | `PKP\pages\about\AboutContextHandler` | Public about-the-context pages. Ops: index, editorialMasthead, editorialHistory, submissions, contact. Subclassed by OJS `AboutHandler` (ROUTE-032); reached directly in OMP/OPS via `lib/pkp/pages/about/index.php`. |
| ROUTE-002 | ojs omp ops | `PKP\pages\about\AboutSiteHandler` | Site-level about pages. Ops: aboutThisPublishingSystem, privacy. |
| ROUTE-003 | ojs omp ops | `PKP\pages\admin\AdminHandler` | Site administration (role: site admin). Ops: index, contexts, settings, wizard, systemInfo, phpinfo, expireSessions, clearTemplateCache, clearDataCache, downloadScheduledTaskLogFile, clearScheduledTaskLogFiles, jobs, failedJobs, failedJobDetails, confirmAccess, confirmAccessSubmit. |
| ROUTE-004 | ojs omp ops | `PKP\pages\announcement\AnnouncementHandler` | Public announcements. Ops: index, view. |
| ROUTE-005 | ojs omp ops | `PKP\pages\authorDashboard\PKPAuthorDashboardHandler` | Author dashboard base (role: author). Ops: submission, readSubmissionEmail. Subclassed by app `AuthorDashboardHandler` in all three (ROUTE-034/054/073). |
| ROUTE-006 | ojs omp ops | `PKP\pages\catalog\PKPCatalogHandler` | Catalog category browse + cover images. Ops: category, fullSize, thumbnail. Dispatched directly as `catalog` page in OJS and OPS (app `catalog/index.php` returns this class), and for the same three ops under the OPS `preprints` page; OMP replaces it with `CatalogHandler` (ROUTE-056). |
| ROUTE-007 | ojs omp ops | `PKP\pages\dashboard\DashboardHandler` | Legacy `submissions` page (dispatched by `lib/pkp/pages/submissions/index.php`). Role-assigned ops (admin/manager/sub-editor/author/reviewer/assistant): index, tasks, myQueue, unassigned, active, archives; op methods present: index, tasks. |
| ROUTE-008 | ojs omp ops | `PKP\pages\dashboard\PKPDashboardHandler` | New dashboard base (page `dashboard`). Role-assigned ops: editorial (admin/manager/sub-editor/assistant), reviewAssignments (reviewer), mySubmissions (author); op methods also: index, tasks. App dispatchers construct the app subclass with a `DashboardPage` enum per op. Subclassed in all three (ROUTE-035/057/074). |
| ROUTE-009 | ojs omp ops | `PKP\pages\decision\DecisionHandler` | Editorial decision recording wizard (roles: manager/admin/sub-editor). Op: record. Dispatched directly by all three apps' `pages/decision/index.php` (no app subclass). |
| ROUTE-010 | ojs omp ops | `PKP\pages\dois\PKPDoisHandler` | DOI management page (roles: manager/admin). Role-assigned ops: index, management; op method present: index. Subclassed by app `DoisHandler` in all three (ROUTE-036/058/075). |
| ROUTE-011 | ojs omp ops | `PKP\pages\index\PKPIndexHandler` | Base class for app home-page handlers; declares no ops itself. Subclassed by app `IndexHandler` in all three (ROUTE-038/060/077). |
| ROUTE-012 | ojs omp ops | `PKP\pages\install\InstallHandler` | Installer / upgrader. Ops: index, install, upgrade, installUpgrade (plus `validate` lifecycle override). |
| ROUTE-013 | ojs omp ops | `PKP\pages\invitation\InitializeInvitationUIHandler` | Invitation UI bootstrap (roles: admin/manager/sub-editor/assistant — the handler's own assignment; the screen that offers the route is gated to admin + manager-with-`permitSettings` via `CanAccessSettingsPolicy` in `ManagementHandler::authorize()`, and the mismatch is U06's main open question). Ops: create, edit. Claimed by: user-invitations. |
| ROUTE-014 | ojs omp ops | `PKP\pages\invitation\InvitationHandler` | Invitation acceptance flow (public). Ops: accept, decline, confirmDecline. Claimed by: user-invitations. |
| ROUTE-015 | ojs omp ops | `PKP\pages\libraryFiles\LibraryFileHandler` | Library file downloads. Ops: downloadPublic, downloadLibraryFile. |
| ROUTE-016 | ojs omp ops | `PKP\pages\login\LoginHandler` | Authentication + password flows. Ops: index, signIn, signOut, lostPassword, requestResetPassword, resetPassword, updateResetPassword, changePassword, savePassword, signInAsUser, signOutAsUser. Claimed by: login-and-sessions. |
| ROUTE-017 | ojs omp ops | `PKP\pages\management\ManagementHandler` | Context settings base. Ops: settings, context, website, workflow, distribution, announcements, institutions, access, manageEmails, userComments, editUser. Subclassed by app `SettingsHandler` in all three (ROUTE-042/063/078). |
| ROUTE-018 | ojs omp ops | `PKP\pages\management\PKPToolsHandler` | Management tools (roles: manager/admin; extends ManagementHandler). Ops: tools, importexport, permissions, resetPermissions (plus index alias). Dispatched from each app's `pages/management/index.php` for tool ops. |
| ROUTE-019 | ojs omp ops | `PKP\pages\navigationMenu\NavigationMenuItemHandler` | Custom navigation-menu-item pages. Ops: preview, view (plus index no-op). |
| ROUTE-020 | ojs omp ops | `PKP\pages\notification\NotificationHandler` | In-app notification fetch + email unsubscribe. Ops: fetchNotification, unsubscribe. |
| ROUTE-021 | ojs omp ops | `PKP\pages\orcid\OrcidHandler` | ORCID OAuth callbacks. Ops: verify, authorizeOrcid, about, updateScope. Claimed by: orcid-integration. |
| ROUTE-022 | ojs omp | `PKP\pages\reviewer\PKPReviewerHandler` | Reviewer wizard base. Ops: submission, step, saveStep, showDeclineReview, saveDeclineReview, getReviewForm. No dispatcher in lib/pkp; reached only via app subclass `ReviewerHandler` (ROUTE-047/066) — OPS has no `pages/reviewer/` dispatcher, hence apps = ojs omp. |
| ROUTE-023 | ojs omp ops | `PKP\pages\reviewResponse\ReviewResponseHandler` | Request author response to a review (roles: manager/admin/sub-editor). Op: requestAuthorResponse. |
| ROUTE-024 | ojs omp ops | `PKP\pages\search\SearchHandler` | Public search base. Ops: index, search. Subclassed by app `SearchHandler` in all three (ROUTE-048/067/083). |
| ROUTE-025 | ojs omp ops | `PKP\pages\sitemap\PKPSitemapHandler` | Sitemap XML base. Op: index. Subclassed by app `SitemapHandler` in all three (ROUTE-049/068/084). |
| ROUTE-026 | ojs omp ops | `PKP\pages\stats\PKPStatsHandler` | Statistics pages (roles: admin/manager/sub-editor). Role-assigned ops: editorial, publications, context, users, reports, counterR5; additional public op methods: displayReports, report. Subclassed by app `StatsHandler` in all three (ROUTE-050/069/085). |
| ROUTE-027 | ojs omp ops | `PKP\pages\submission\PKPSubmissionHandler` | Submission wizard (roles: author/sub-editor/manager/admin). Ops: index, wizard, saved, cancelled. Subclassed by app `SubmissionHandler` in all three (ROUTE-051/070/086). |
| ROUTE-028 | ojs omp ops | `PKP\pages\user\PKPUserHandler` | User page base. Ops: index, authorizationDenied. Subclassed by app `UserHandler` in all three (ROUTE-052/071/087). |
| ROUTE-029 | ojs omp ops | `PKP\pages\user\ProfileHandler` | User profile (extends UserHandler; dispatched by `lib/pkp/pages/user/index.php`). Op: profile. |
| ROUTE-030 | ojs omp ops | `PKP\pages\user\RegistrationHandler` | User registration (extends UserHandler; dispatched by `lib/pkp/pages/user/index.php`). Ops: register, registerUser, activateUser (plus `validate` lifecycle override). |
| ROUTE-031 | ojs omp ops | `PKP\pages\workflow\PKPWorkflowHandler` | Editorial workflow base. Ops: access, index, submission, externalReview, editorial, production. Subclassed by app `WorkflowHandler` in all three (ROUTE-053/072/088), which carry the role assignments. |
| ROUTE-032 | ojs | `APP\pages\about\AboutHandler` (OJS) | Extends `AboutContextHandler` (ROUTE-001). Adds op: subscriptions. |
| ROUTE-033 | ojs | `APP\pages\article\ArticleHandler` | Article landing page + galley/file access. Ops: view, viewFile, downloadSuppFile, download (plus public helper userCanViewGalley). |
| ROUTE-034 | ojs | `APP\pages\authorDashboard\AuthorDashboardHandler` (OJS) | Extends `PKPAuthorDashboardHandler` (ROUTE-005); no new ops. |
| ROUTE-035 | ojs | `APP\pages\dashboard\DashboardHandler` (OJS) | Extends `PKPDashboardHandler` (ROUTE-008); overrides setupIndex; no new ops. Dispatcher maps ops index/editorial/mySubmissions/reviewAssignments to `DashboardPage` enum variants. |
| ROUTE-036 | ojs | `APP\pages\dois\DoisHandler` (OJS) | Extends `PKPDoisHandler` (ROUTE-010); no new ops. |
| ROUTE-037 | ojs | `APP\pages\gateway\GatewayHandler` (OJS) | Gateway plugin endpoints. Ops: index, lockss, clockss, plugin. |
| ROUTE-038 | ojs | `APP\pages\index\IndexHandler` (OJS) | Journal / site home page. Extends `PKPIndexHandler` (ROUTE-011). Op: index. |
| ROUTE-039 | ojs | `APP\pages\information\InformationHandler` (OJS) | Information pages for readers/authors/librarians. Ops: index, readers, authors, librarians, competingInterestGuidelines, sampleCopyrightWording. |
| ROUTE-040 | ojs | `APP\pages\issue\IssueHandler` | Issue landing / TOC / archive / galley download. Ops: index, current, view, archive, download (plus public helpers getGalley, setGalley, userCanViewGalley). |
| ROUTE-041 | ojs | `APP\pages\manageIssues\ManageIssuesHandler` | Issue management page (roles: manager/admin). Op: index. |
| ROUTE-042 | ojs | `APP\pages\management\SettingsHandler` (OJS) | Extends `ManagementHandler` (ROUTE-017). Role assignments: access, settings (admin); settings (manager). Overrides ops workflow, distribution. |
| ROUTE-043 | ojs | `pages/manager/index.php` (OJS; routing stub, no class) | Legacy `manager` page dispatcher: switch over old subscription ops (subscriptionPolicies, saveSubscriptionPolicies, subscriptionTypes, deleteSubscriptionType, createSubscriptionType, selectSubscriber, editSubscriptionType, updateSubscriptionType, moveSubscriptionType) that returns no handler. |
| ROUTE-044 | ojs | `APP\pages\oai\OAIHandler` (OJS) | OAI-PMH endpoint. Op: index (plus validate/requireSSL lifecycle overrides). |
| ROUTE-045 | ojs | `APP\pages\payment\PaymentHandler` (OJS) | Payment plugin callback + pay flow. Ops: plugin, pay. |
| ROUTE-046 | ojs | `APP\pages\payments\PaymentsHandler` | Subscriptions and payments management (roles: manager/admin/subscription manager). Ops: index, subscriptions, subscriptionTypes, subscriptionPolicies, saveSubscriptionPolicies, paymentTypes, savePaymentTypes, payments. |
| ROUTE-047 | ojs | `APP\pages\reviewer\ReviewerHandler` (OJS) | Extends `PKPReviewerHandler` (ROUTE-022). Role assignment (reviewer): submission, step, saveStep, showDeclineReview, saveDeclineReview, downloadFile (downloadFile has no method in this hierarchy). Overrides getReviewForm. |
| ROUTE-048 | ojs | `APP\pages\search\SearchHandler` (OJS) | Extends `PKP\pages\search\SearchHandler` (ROUTE-024); adds authorize only, no new ops. |
| ROUTE-049 | ojs | `APP\pages\sitemap\SitemapHandler` (OJS) | Extends `PKPSitemapHandler` (ROUTE-025); overrides context sitemap generation; no new ops. |
| ROUTE-050 | ojs | `APP\pages\stats\StatsHandler` (OJS) | Extends `PKPStatsHandler` (ROUTE-026). Adds op (admin/manager/sub-editor): issues. |
| ROUTE-051 | ojs | `APP\pages\submission\SubmissionHandler` (OJS) | Extends `PKPSubmissionHandler` (ROUTE-027); no new ops. |
| ROUTE-052 | ojs | `APP\pages\user\UserHandler` (OJS) | Extends `PKPUserHandler` (ROUTE-028). Adds ops: subscriptions, purchaseSubscription, payPurchaseSubscription, completePurchaseSubscription, payRenewSubscription, payMembership. |
| ROUTE-053 | ojs | `APP\pages\workflow\WorkflowHandler` (OJS) | Extends `PKPWorkflowHandler` (ROUTE-031). Role assignment (sub-editor/manager/admin/assistant): access, index, submission, externalReview, editorial, production. |
| ROUTE-054 | omp | `APP\pages\authorDashboard\AuthorDashboardHandler` (OMP) | Extends `PKPAuthorDashboardHandler` (ROUTE-005); overrides submission op and setupTemplate; no new ops. |
| ROUTE-055 | omp | `APP\pages\catalog\CatalogBookHandler` | Monograph (book) landing page + publication format access. Ops: book, view, download. |
| ROUTE-056 | omp | `APP\pages\catalog\CatalogHandler` (OMP) | Extends `PKPCatalogHandler` (ROUTE-006); replaces the shared catalog page in OMP. Ops: index, page, newReleases, series, plus inherited/overridden category, fullSize, thumbnail. |
| ROUTE-057 | omp | `APP\pages\dashboard\DashboardHandler` (OMP) | Extends `PKPDashboardHandler` (ROUTE-008); overrides setupIndex; no new ops. |
| ROUTE-058 | omp | `APP\pages\dois\DoisHandler` (OMP) | Extends `PKPDoisHandler` (ROUTE-010); no new ops. |
| ROUTE-059 | omp | `APP\pages\gateway\GatewayHandler` (OMP) | Gateway plugin endpoints. Ops: index, plugin. |
| ROUTE-060 | omp | `APP\pages\index\IndexHandler` (OMP) | Press / site home page. Extends `PKPIndexHandler` (ROUTE-011). Op: index. |
| ROUTE-061 | omp | `APP\pages\information\InformationHandler` (OMP) | Information pages. Ops: index, readers, authors, librarians, competingInterestPolicy, sampleCopyrightWording. |
| ROUTE-062 | omp | `APP\pages\manageCatalog\ManageCatalogHandler` | Catalog management page (roles: sub-editor/manager/admin). Op: index. |
| ROUTE-063 | omp | `APP\pages\management\SettingsHandler` (OMP) | Extends `ManagementHandler` (ROUTE-017). Role assignments: access (admin); settings (manager). Overrides ops workflow, distribution. |
| ROUTE-064 | omp | `APP\pages\oai\OAIHandler` (OMP) | OAI-PMH endpoint. Op: index. |
| ROUTE-065 | omp | `APP\pages\payment\PaymentHandler` (OMP) | Payment plugin callback. Op: plugin. |
| ROUTE-066 | omp | `APP\pages\reviewer\ReviewerHandler` (OMP) | Extends `PKPReviewerHandler` (ROUTE-022). Role assignment (reviewer): submission, step, saveStep, showDeclineReview, saveDeclineReview, downloadFile (downloadFile has no method in this hierarchy). |
| ROUTE-067 | omp | `APP\pages\search\SearchHandler` (OMP) | Extends `PKP\pages\search\SearchHandler` (ROUTE-024); no new ops. |
| ROUTE-068 | omp | `APP\pages\sitemap\SitemapHandler` (OMP) | Extends `PKPSitemapHandler` (ROUTE-025); overrides context sitemap generation; no new ops. |
| ROUTE-069 | omp | `APP\pages\stats\StatsHandler` (OMP) | Extends `PKPStatsHandler` (ROUTE-026); overrides section (series) filters; no new ops. |
| ROUTE-070 | omp | `APP\pages\submission\SubmissionHandler` (OMP) | Extends `PKPSubmissionHandler` (ROUTE-027); no new ops. |
| ROUTE-071 | omp | `APP\pages\user\UserHandler` (OMP) | Extends `PKPUserHandler` (ROUTE-028); no new ops. |
| ROUTE-072 | omp | `APP\pages\workflow\WorkflowHandler` (OMP) | Extends `PKPWorkflowHandler` (ROUTE-031). Role assignment (sub-editor/manager/admin/assistant): access, index, submission, internalReview, externalReview, editorial, production. Adds op: internalReview. |
| ROUTE-073 | ops | `APP\pages\authorDashboard\AuthorDashboardHandler` (OPS) | Extends `PKPAuthorDashboardHandler` (ROUTE-005); overrides setupTemplate; no new ops. |
| ROUTE-074 | ops | `APP\pages\dashboard\DashboardHandler` (OPS) | Extends `PKPDashboardHandler` (ROUTE-008); overrides setupIndex; no new ops. |
| ROUTE-075 | ops | `APP\pages\dois\DoisHandler` (OPS) | Extends `PKPDoisHandler` (ROUTE-010); no new ops. |
| ROUTE-076 | ops | `APP\pages\gateway\GatewayHandler` (OPS) | Gateway plugin endpoints. Ops: index, plugin. |
| ROUTE-077 | ops | `APP\pages\index\IndexHandler` (OPS) | Server / site home page. Extends `PKPIndexHandler` (ROUTE-011). Op: index. |
| ROUTE-078 | ops | `APP\pages\management\SettingsHandler` (OPS) | Extends `ManagementHandler` (ROUTE-017). Role assignments: access (admin); settings (manager). Overrides ops workflow, distribution. |
| ROUTE-079 | ops | `APP\pages\oai\OAIHandler` (OPS) | OAI-PMH endpoint. Op: index (plus validate/requireSSL lifecycle overrides). |
| ROUTE-080 | ops | `APP\pages\preprint\PreprintHandler` | Preprint landing page + galley/file access. Ops: view, download (plus public helper userCanViewGalley). |
| ROUTE-081 | ops | `APP\pages\preprints\PreprintsHandler` | Preprints archive listing (`preprints` page). Op: index. Dispatcher also routes ops category/fullSize/thumbnail on this page to `PKPCatalogHandler` (ROUTE-006) and op `section` to `SectionsHandler` (ROUTE-082). |
| ROUTE-082 | ops | `APP\pages\preprints\SectionsHandler` | Section browse under the `preprints` page. Op: section. |
| ROUTE-083 | ops | `APP\pages\search\SearchHandler` (OPS) | Extends `PKP\pages\search\SearchHandler` (ROUTE-024); adds authorize only, no new ops. |
| ROUTE-084 | ops | `APP\pages\sitemap\SitemapHandler` (OPS) | Extends `PKPSitemapHandler` (ROUTE-025); overrides context sitemap generation; no new ops. |
| ROUTE-085 | ops | `APP\pages\stats\StatsHandler` (OPS) | Extends `PKPStatsHandler` (ROUTE-026); overrides section filters and removes the editorial stats chart view; no new ops. |
| ROUTE-086 | ops | `APP\pages\submission\SubmissionHandler` (OPS) | Extends `PKPSubmissionHandler` (ROUTE-027); no new ops. |
| ROUTE-087 | ops | `APP\pages\user\UserHandler` (OPS) | Extends `PKPUserHandler` (ROUTE-028); overrides incomplete-setup check; no new ops. |
| ROUTE-088 | ops | `APP\pages\workflow\WorkflowHandler` (OPS) | Extends `PKPWorkflowHandler` (ROUTE-031). Role assignment (sub-editor/manager/admin/assistant): access, index, submission, editorDecisionActions, externalReview, editorial, production (editorDecisionActions has no method in this hierarchy). |
