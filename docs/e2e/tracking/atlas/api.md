# Atlas — api modality

- **Modality**: api — REST API controllers (Laravel-style route groups) + app overlays
- **Sweep date**: 2026-07-26
- **Trees swept**:
  - Shared: `lib/pkp/api/` — lib/pkp submodule verified identical in all three apps, checked out at `ad4606f93e` (`ad4606f93ef153d018e3c4cce751698258c9bbcd`; task brief cited `9db481cf4d` — superseded, actual checkout recorded here)
  - App: `ojs-main/api/`, `omp-main/api/`, `ops-main/api/`
- **Method (exact commands)**:
  - `find <tree> -type f` (full file census, all four trees)
  - `find <tree> -name "*Controller.php"` → per controller: `grep "public function getHandlerPath" -A 3` (base path) + `grep -nE "Route::(get|post|put|delete|patch|middleware|prefix)\("` (endpoints)
  - `find <app>/api -name index.php` → `grep -nE "new |Controller|::class"` (which controllers each app mounts; sets per-atom apps field)
  - Multiline route registrations read directly (`sed -n` on OJS/OPS `SubmissionController`, `PKPJatsController` tail, OMP `SubmissionController`, OJS `BackendSubmissionsController`)
- **Conventions**: one atom per controller class; endpoints listed as `METHOD relative-path` under the atom's base path (full URL = `/api/v1/<base>/<relative>`; `/` = base itself). Shared atoms default to `ojs omp ops`; overridden where an app does not mount the controller. App subclasses that register no new endpoints are noted on the shared atom only; subclasses with new endpoints also get an app atom listing only the added endpoints. formRequests/resources support classes are not separate atoms (they belong to their controller). No liveness judgment.

## Shared tree (lib/pkp/api)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| API-001 | ojs omp ops | `PKP\API\v1\_dois\PKPBackendDoiController` | Backend DOI editing (base `_dois`): PUT publications/{publicationId}, PUT peerReviews/{reviewId}, PUT authorResponses/{responseId}. Subclassed in all three apps as `APP\API\v1\_dois\BackendDoiController` adding app object types (API-050, API-058, API-063). |
| API-002 | ojs omp ops | `PKP\API\v1\_email\PKPEmailController` | Backend bulk-email send (base `_email`): POST /. |
| API-003 | ojs omp ops | `PKP\API\v1\_i18n\I18nController` | UI translation bundle (base `_i18n`): GET ui.js. |
| API-004 | ojs omp ops | `PKP\API\v1\_library\PKPLibraryController` | Library files listing (base `_library`): GET /. |
| API-005 | ojs omp | `PKP\API\v1\_payments\PKPBackendPaymentsSettingsController` | Backend payments settings (base `_payments`): PUT /. Not mounted by OPS (no `_payments` entry point). |
| API-006 | ojs omp ops | `PKP\API\v1\_submissions\PKPBackendSubmissionsController` | Backend submission lists (base `_submissions`): GET /, DELETE / (bulk delete incomplete), DELETE {submissionId}, GET assigned, GET reviews, GET viewsCount, GET reviewerAssignments. Subclassed in all three apps as `APP\API\v1\_submissions\BackendSubmissionsController`; OJS adds payment (API-051), OMP adds catalog flags (API-059), OPS subclass adds no endpoints. |
| API-007 | ojs omp ops | `PKP\API\v1\_uploadPublicFile\PKPUploadPublicFileController` | Public file upload (base `_uploadPublicFile`): POST /. |
| API-008 | ojs omp ops | `PKP\API\v1\announcements\PKPAnnouncementController` | Announcements CRUD (base `announcements`): GET /, GET {announcementId}, POST /, PUT {announcementId}, DELETE {announcementId}. |
| API-009 | ojs | `PKP\API\v1\bodyText\PKPBodyTextController` | Publication body text (base `submissions/{submissionId}/publications/{publicationId}/bodyText`): GET /, PUT /, DELETE /. Mounted only by OJS `submissions` entry point. |
| API-010 | ojs omp ops | `PKP\API\v1\categories\CategoryCategoryController` | Categories CRUD (base `categories`): GET /, GET categoryFormComponent, POST /, PUT {categoryId}, DELETE {categoryId}. |
| API-011 | ojs omp ops | `PKP\API\v1\citations\PKPCitationController` | Publication citations (base `submissions/{submissionId}/publications/{publicationId}/citations`): GET /, GET {citationId}, PUT {citationId}, POST {citationId}/reprocessCitation, DELETE {citationId}, POST importAdditionalCitations, DELETE deleteCitationsByPublicationId, POST reprocessCitationsByPublicationId. |
| API-012 | ojs omp ops | `PKP\API\v1\comments\UserCommentController` | Reader comments + moderation (base `comments`): GET public, POST /, DELETE {commentId}, POST {commentId}/reports, GET /, GET {commentId}, PUT {commentId}/setApproval, GET {commentId}/reports, DELETE {commentId}/reports, GET {commentId}/reports/{reportId}, DELETE {commentId}/reports/{reportId}. |
| API-013 | ojs omp ops | `PKP\API\v1\contexts\PKPContextController` | Contexts (journals/presses/servers) CRUD (base `contexts`): GET /, GET {contextId}, GET {contextId}/theme, PUT {contextId}, PUT {contextId}/theme, PUT {contextId}/registrationAgency, POST /, DELETE {contextId}. |
| API-014 | ojs omp ops | `PKP\API\v1\contributorRoles\ContributorRoleController` | Contributor roles CRUD (base `contributorRoles`): GET {roleId}, GET /, GET identifiers, POST /, PUT {roleId}, DELETE {roleId}. |
| API-015 | ojs omp ops | `PKP\API\v1\dataCitations\PKPDataCitationController` | Publication data citations (base `submissions/{submissionId}/publications/{publicationId}/dataCitations`): GET /, GET {dataCitationId}, POST /, PUT {dataCitationId}, DELETE {dataCitationId}, PUT order. |
| API-016 | ojs omp ops | `PKP\API\v1\dois\PKPDoiController` | DOI management (base `dois`): GET /, GET {doiId}, GET exports/{fileId}, POST /, POST submissions/assignDois, PUT {doiId}, PUT submissions/export, PUT submissions/deposit, PUT submissions/markRegistered, PUT submissions/markUnregistered, PUT submissions/markStale, PUT depositAll, DELETE {doiId}. OJS/OMP mount subclass `APP\API\v1\dois\DoiController` (OJS adds issue ops, API-052; OMP subclass adds no endpoints); OPS mounts shared class directly. |
| API-017 | ojs omp ops | `PKP\API\v1\editTaskTemplates\PKPEditTaskTemplateController` | Editorial task templates (base `editTaskTemplates`): POST /, PUT {templateId}, DELETE {templateId}, GET variables, GET /. |
| API-018 | ojs omp ops | `PKP\API\v1\emails\PKPEmailController` | Logged emails (base `emails`): GET /authorEmails, GET {emailId}. |
| API-019 | ojs omp ops | `PKP\API\v1\emailTemplates\PKPEmailTemplateController` | Email templates CRUD (base `emailTemplates`): GET /, GET {key}, POST /, PUT {key}, DELETE restoreDefaults, DELETE {key}. |
| API-020 | ojs omp ops | `PKP\API\v1\funders\PKPFunderController` | Publication funders (base `submissions/{submissionId}/publications/{publicationId}/funders`): GET /, GET {funderId}, POST /, PUT {funderId}, DELETE {funderId}, PUT order. |
| API-021 | ojs omp ops | `PKP\API\v1\genres\GenreController` | File genres (base `genres`): GET /, GET /{genreId}. OPS additionally mounts this at a legacy non-versioned entry point (API-066). |
| API-022 | ojs omp ops | `PKP\API\v1\highlights\HighlightsController` | Highlights CRUD (base `highlights`): GET /, GET {highlightId}, POST /, PUT {highlightId}, PUT order, DELETE {highlightId}. |
| API-023 | ojs omp ops | `PKP\API\v1\institutions\PKPInstitutionController` | Institutions CRUD (base `institutions`): GET /, GET {institutionId}, POST /, PUT {institutionId}, DELETE {institutionId}. |
| API-024 | ojs omp ops | `PKP\API\v1\invitations\InvitationController` | Invitations lifecycle (base `invitations`): GET {type}, GET {invitationId}, POST add/{type}, PUT {invitationId}/populate, PUT {invitationId}/invite, GET {invitationId}/getMailable, PUT {invitationId}/cancel, GET {invitationId}/key/{key}, PUT {invitationId}/key/{key}/finalize, PUT {invitationId}/key/{key}/refine, PUT {invitationId}/key/{key}/decline. Claimed by: user-invitations. |
| API-025 | ojs | `PKP\API\v1\jats\PKPJatsController` | Publication JATS XML (base `submissions/{submissionId}/publications/{publicationId}/jats`): GET /, POST /, DELETE /, PUT visibility, GET download (conditionally-guarded public download). Mounted only by OJS `submissions` entry point. |
| API-026 | ojs omp ops | `PKP\API\v1\jobs\PKPJobController` | Queue jobs admin (base `jobs`): GET all, GET failed/all, POST redispatch/all, POST redispatch/{jobId}, DELETE failed/delete/{jobId}. |
| API-027 | ojs omp ops | `PKP\API\v1\mailables\PKPMailableController` | Mailables listing (base `mailables`): GET /, GET {id}. |
| API-028 | ojs omp ops | `PKP\API\v1\navigationMenus\PKPNavigationMenuController` | Navigation menus (base `navigationMenus`): GET items, GET areas, GET {navigationMenuId}/items, POST /, PUT {navigationMenuId}. |
| API-029 | ojs omp ops | `PKP\API\v1\orcid\OrcidController` | ORCID author verification (base `orcid`): POST requestAuthorVerification/{authorId}, POST deleteForAuthor/{authorId}. Claimed by: orcid-integration. |
| API-030 | ojs | `PKP\API\v1\peerReviews\PeerReviewController` | Open peer review data (base `peerReviews`): GET open/submissions/, GET open/submissions/summary, GET open/submissions/{submissionId}, GET open/submissions/{submissionId}/summary. Mounted only by OJS; OMP has a `publicationPeerReviews` entry point referencing a class absent from lib/pkp (API-062). |
| API-031 | ojs omp | `PKP\API\v1\reviewers\suggestions\ReviewerSuggestionController` | Author reviewer suggestions (base `submissions/{submissionId}/reviewers/suggestions`): GET {suggestionId}, GET /, POST /, PUT {suggestionId}, DELETE {suggestionId}. Not mounted by OPS `submissions` entry point. |
| API-032 | ojs omp | `PKP\API\v1\reviews\PKPReviewController` | Review round tools (base `reviews`): POST {submissionId}/{reviewRoundId}/authorResponse/requestResponse, POST {submissionId}/{reviewRoundId}/authorResponse/, PUT {submissionId}/{reviewRoundId}/authorResponse/{responseId}, DELETE {submissionId}/{reviewRoundId}/authorResponse/{responseId}, GET history/{submissionId}/{reviewRoundId}, PUT {submissionId}/{reviewAssignmentId}/confirmReview, GET {submissionId}/{reviewAssignmentId}/export-pdf, GET {submissionId}/{reviewAssignmentId}/export-xml, GET {submissionId}/exports/{fileId}, POST {submissionId}/{reviewAssignmentId}/sendToOrcid. No OPS `reviews` entry point. |
| API-033 | ojs omp ops | `PKP\API\v1\rors\PKPRorController` | ROR registry lookup (base `rors`): GET {rorId}, GET /, POST / (addOrEdit). |
| API-034 | ojs | `PKP\API\v1\sections\SectionController` | Sections listing (base `sections`): GET /, GET {sectionId}. Only OJS has a `sections` entry point. |
| API-035 | ojs omp ops | `PKP\API\v1\site\PKPSiteController` | Site settings (base `site`): GET /, GET theme, PUT /, PUT theme. |
| API-036 | ojs omp ops | `PKP\API\v1\stats\contexts\PKPStatsContextController` | Context view stats (base `stats/contexts`): GET timeline, GET {contextId}/timeline, GET {contextId}, GET /. |
| API-037 | ojs omp ops | `PKP\API\v1\stats\editorial\PKPStatsEditorialController` | Editorial activity stats (base `stats/editorial`): GET /, GET averages. Subclassed in all three apps as `APP\API\v1\stats\editorial\StatsEditorialController` (no new endpoints). |
| API-038 | ojs omp ops | `PKP\API\v1\stats\publications\PKPStatsPublicationController` | Publication usage stats (base `stats/publications`): GET /, GET timeline, GET {submissionId}, GET {submissionId}/timeline, GET files, GET countries, GET regions, GET cities. Subclassed in all three apps as `APP\API\v1\stats\publications\StatsPublicationController` (no new endpoints). |
| API-039 | ojs omp ops | `PKP\API\v1\stats\sushi\PKPStatsSushiController` | COUNTER SUSHI service (base `stats/sushi`): GET status, GET members, GET reports, GET reports/pr, GET reports/pr_p1. Subclassed in all three apps adding app report types (API-055, API-060, API-064). |
| API-040 | ojs omp ops | `PKP\API\v1\stats\users\PKPStatsUserController` | User count stats (base `stats/users`): GET /. |
| API-041 | ojs omp ops | `PKP\API\v1\submissions\MediaFilesController` | Publication media files (base `submissions/{submissionId}/publications/{publicationId}/mediaFiles`): GET /, POST /, POST /link, PUT /{submissionFileId}/link, PUT /{submissionFileId}, DELETE /{submissionFileId}. |
| API-042 | ojs omp ops | `PKP\API\v1\submissions\PKPSubmissionController` | Submissions + publications workflow (base `submissions`): GET /, GET {submissionId}, GET {submissionId}/publications, GET {submissionId}/publications/{publicationId}, GET {submissionId}/publications/{publicationId}/contributors, GET {submissionId}/publications/{publicationId}/contributors/{contributorId}, GET {submissionId}/participants, GET {submissionId}/participants/{stageId}, GET {submissionId}/decisions, POST {submissionId}/decisions, POST {submissionId}/returnToDone, DELETE {submissionId}, PUT {submissionId}/publications/{publicationId}/changeLocale, PUT {submissionId}/publications/{publicationId}/version, GET {submissionId}/nextAvailableVersion, POST {submissionId}/publications, POST {submissionId}/publications/{publicationId}/version, PUT {submissionId}/publications/{publicationId}/publish, PUT {submissionId}/publications/{publicationId}/unpublish, DELETE {submissionId}/publications/{publicationId}, POST {submissionId}/publications/{publicationId}/contributors, PUT {submissionId}/publications/{publicationId}, PUT {submissionId}/publications/{publicationId}/contributors/{contributorId}, PUT {submissionId}/publications/{publicationId}/contributors/saveOrder, DELETE {submissionId}/publications/{publicationId}/contributors/{contributorId}, GET {submissionId}/publications/{publicationId}/_components/{metadata|dataAvailability|titleAbstract|changeLanguageMetadata|identifier|permissionDisclosure}, PUT {submissionId}, PUT {submissionId}/saveForLater, PUT {submissionId}/submit, POST /. Subclassed in all three apps as `APP\API\v1\submissions\SubmissionController` (API-057, API-061, API-065). |
| API-043 | ojs omp ops | `PKP\API\v1\submissions\PKPSubmissionFileController` | Submission files (base `submissions/{submissionId}/files`): GET /, GET {submissionFileId}, POST /, PUT {submissionFileId}, DELETE {submissionFileId}, PUT {submissionFileId}/copy. |
| API-044 | ojs omp ops | `PKP\API\v1\submissions\tasks\EditorialTaskController` | Editorial tasks + discussions (base `submissions`): POST {submissionId}/tasks, PUT {submissionId}/tasks/{taskId}, DELETE {submissionId}/tasks/{taskId}, GET {submissionId}/tasks/{taskId}, GET {submissionId}/stages/{stageId}/tasks, PUT {submissionId}/tasks/{taskId}/close, PUT {submissionId}/tasks/{taskId}/open, PUT {submissionId}/tasks/{taskId}/start, GET {submissionId}/stages/{stageId}/tasks/fromTemplate/{templateId}, POST {submissionId}/tasks/{taskId}/notes, DELETE {submissionId}/tasks/{taskId}/notes/{noteId}, GET {submissionId}/stages/{stageId}/tasks/participants. |
| API-045 | ojs omp ops | `PKP\API\v1\temporaryFiles\PKPTemporaryFilesController` | Temporary file upload (base `temporaryFiles`): POST /. |
| API-046 | ojs omp ops | `PKP\API\v1\userGroups\UserGroupController` | User groups listing (base `userGroups`): GET /. |
| API-047 | ojs omp ops | `PKP\API\v1\users\PKPUserController` | Users (base `users`): GET reviewers, GET report, GET {userId}, GET /, PUT {userId}/endRole/{userGroupId}, PUT {userId}/masthead/{userUserGroupId}. |
| API-048 | ojs omp | `PKP\API\v1\vocabs\PKPInterestController` | Reviewer interests vocab (base `vocabs/interests`): GET /. OPS `vocabs` entry point mounts only PKPVocabController. Claimed by: reviewer-assignment-and-management. |
| API-049 | ojs omp ops | `PKP\API\v1\vocabs\PKPVocabController` | Controlled vocabulary suggestions (base `vocabs`): GET /. |

## OJS overlay (ojs-main/api)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| API-050 | ojs | `APP\API\v1\_dois\BackendDoiController` (ojs) | Extends API-001; adds PUT issues/{issueId}, PUT galleys/{galleyId} under `_dois`. |
| API-051 | ojs | `APP\API\v1\_submissions\BackendSubmissionsController` (ojs) | Extends API-006; adds PUT {submissionId}/payment under `_submissions`. |
| API-052 | ojs | `APP\API\v1\dois\DoiController` (ojs) | Extends API-016; adds POST issues/assignDois, PUT issues/export, PUT issues/deposit, PUT issues/markRegistered, PUT issues/markUnregistered, PUT issues/markStale under `dois`. |
| API-053 | ojs | `APP\API\v1\issues\IssueController` | Issues (base `issues`): GET /, GET current, GET {issueId}, GET assignmentOptions. |
| API-054 | ojs | `APP\API\v1\reviewers\recommendations\ReviewerRecommendationController` | Reviewer recommendation settings (base `reviewers/recommendations`): GET {reviewerRecommendationId}, GET /, POST /, PUT {reviewerRecommendationId}, PUT {reviewerRecommendationId}/status, DELETE {reviewerRecommendationId}. |
| API-055 | ojs | `APP\API\v1\stats\sushi\StatsSushiController` (ojs) | Extends API-039; adds GET reports/tr, GET reports/tr_j3, GET reports/ir, GET reports/ir_a1 under `stats/sushi`. |
| API-056 | ojs | `APP\API\v1\stats\issues\StatsIssueController` | Issue usage stats (base `stats/issues`): GET timeline, GET {issueId}, GET {issueId}/timeline, GET /. |
| API-057 | ojs | `APP\API\v1\submissions\SubmissionController` (ojs) | Extends API-042; adds GET {submissionId}/publications/{publicationId}/issueAssignmentStatus, GET {submissionId}/publications/{publicationId}/_components/{issue|submissionPayment} under `submissions`. |

## OMP overlay (omp-main/api)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| API-058 | omp | `APP\API\v1\_dois\BackendDoiController` (omp) | Extends API-001; adds PUT chapters/{chapterId}, PUT publicationFormats/{publicationFormatId}, PUT submissionFiles/{submissionFileId} under `_dois`. |
| API-059 | omp | `APP\API\v1\_submissions\BackendSubmissionsController` (omp) | Extends API-006; adds POST saveDisplayFlags, POST saveFeaturedOrder, PUT addToCatalog under `_submissions`. |
| API-060 | omp | `APP\API\v1\stats\sushi\StatsSushiController` (omp) | Extends API-039; adds GET reports/tr, GET reports/tr_b3 under `stats/sushi`. |
| API-061 | omp | `APP\API\v1\submissions\SubmissionController` (omp) | Extends API-042; adds GET {submissionId}/publications/{publicationId}/_components/{audience|catalogEntry|publicationDates|permissionDisclosure} under `submissions`. |
| API-062 | omp | `omp-main/api/v1/publicationPeerReviews/index.php` (entry point; no class pointer available) | `publicationPeerReviews` entry point instantiates `PKP\API\v1\publicationPeerReviews\PublicationPeerReviewController`, which does not exist in lib/pkp at `ad4606f93e` — dangling mount recorded mechanically; endpoints unknown. |

## OPS overlay (ops-main/api)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| API-063 | ops | `APP\API\v1\_dois\BackendDoiController` (ops) | Extends API-001; adds PUT galleys/{galleyId} under `_dois`. |
| API-064 | ops | `APP\API\v1\stats\sushi\StatsSushiController` (ops) | Extends API-039; adds GET reports/ir under `stats/sushi`. |
| API-065 | ops | `APP\API\v1\submissions\SubmissionController` (ops) | Extends API-042; adds PUT {submissionId}/publications/{publicationId}/relate and GET {submissionId}/publications/{publicationId}/_components/issue under `submissions`; also re-registers POST {submissionId}/publications, POST {submissionId}/publications/{publicationId}/version, PUT {submissionId}/publications/{publicationId}/publish, PUT {submissionId}/publications/{publicationId}/unpublish with an author-inclusive role set before delegating to parent. |
| API-066 | ops | `ops-main/api/genres/index.php` (entry point; mounts `PKP\API\v1\genres\GenreController`) | Legacy non-versioned entry point `api/genres/` (outside `v1/`) mounting the shared GenreController (API-021) — recorded mechanically. |
