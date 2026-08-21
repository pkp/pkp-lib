# Probe: OMP catalog — code lineage (inheritance-first, RUNBOOK rule 8)

Desk investigation, read-only. Checkouts under `/Users/jarda/git/pkp/pkp-main/`
(`ojs-main`, `omp-main`, `ops-main`); shared `lib/pkp` treated as canonical at
`/Users/jarda/git/pkp/pkp-main/ojs-main/lib/pkp/` (identical in all three).
Facts only — `class X extends Y`, template include, service used. No liveness,
quality, or scope judgments.

Notation: **[PKP]** = class/file lives in `lib/pkp` (shared).
**[APP]** = lives in the app checkout.

---

## 1. Reader-facing catalog browse

### The shared handler exists and all three apps mount it

`PKP\pages\catalog\PKPCatalogHandler` **[PKP]**
(`lib/pkp/pages/catalog/PKPCatalogHandler.php:29`) `extends APP\handler\Handler`
**[APP]** (`<app>/classes/handler/Handler.php:21`) `extends PKP\core\PKPHandler`
**[PKP]** (`lib/pkp/classes/handler/PKPHandler.php`).

It defines three ops: `category()`, `fullSize()`, `thumbnail()` — all
category-scoped. `category()` renders `frontend/pages/catalogCategory.tpl`.

Routing tables that instantiate it:

| App | Route file | Ops routed to `PKPCatalogHandler` |
|---|---|---|
| OMP | `omp-main/pages/catalog/index.php` | (via subclass, see below) |
| OJS | `ojs-main/pages/catalog/index.php` | `category`, `fullSize`, `thumbnail` |
| OPS | `ops-main/pages/catalog/index.php` | `category`, `fullSize`, `thumbnail` |
| OPS | `ops-main/pages/preprints/index.php` | `category`, `fullSize`, `thumbnail` (same class mounted a second time under `page=preprints`) |

So OJS and OPS each ship a `pages/catalog/index.php` whose only content is a
`switch` returning `PKP\pages\catalog\PKPCatalogHandler` directly — no app
subclass at all.

The category route is linked from app templates in all three:
- OJS: `ojs-main/templates/frontend/components/categoryHeader.tpl:17` and
  `ojs-main/templates/frontend/objects/article_details.tpl:458` →
  `page="catalog" op="category"`.
- OPS: `ops-main/templates/frontend/components/archiveHeader.tpl:23` and
  `ops-main/templates/frontend/objects/preprint_details.tpl:405` →
  `page="preprints" op="category"`.
- OMP: `omp-main/templates/frontend/objects/monograph_full.tpl:409`,
  `chapter.tpl:286`, and `catalogCategory.tpl` pagination → `page="catalog"`.

### OMP's subclass

`APP\pages\catalog\CatalogHandler` **[APP]**
(`omp-main/pages/catalog/CatalogHandler.php:34`)
`extends PKP\pages\catalog\PKPCatalogHandler` **[PKP]**.

It is **not** an empty subclass. Added ops: `index()`, `page()`,
`newReleases()`, `series()`; overridden: `fullSize()`, `thumbnail()`,
`setupTemplate()`; helper `_setupPaginationTemplate()`.
It does **not** override `category()` — the shared `PKPCatalogHandler::category()`
serves OMP's category page.

`omp-main/pages/catalog/index.php` also routes `case 'results'` to
`CatalogHandler`; no `results()` method exists anywhere in the chain
(`CatalogHandler`, `PKPCatalogHandler`, `APP\handler\Handler`, `PKPHandler`).

### Shared logic inside the shared handler

`PKPCatalogHandler::category()` uses:
- `Repo::category()` collector (shared category repo) — same call in all apps;
- `PKP\search\SubmissionSearchResult` **[PKP]**
  (`lib/pkp/classes/search/SubmissionSearchResult.php`) as the result builder;
  each app has a subclass `APP\search\SubmissionSearchResult extends
  \PKP\search\SubmissionSearchResult` (ojs `:20`, omp `:15`, ops `:15`);
- an explicit app branch: `if (Application::get()->getName() == 'omp')
  { $builder->orderBy('featured'); }` with the comment "Featured items are only
  in OMP at this time" (`PKPCatalogHandler.php:78-81`).

OMP's own catalog ops (`page()`, `series()`, `newReleases()`) do **not** use
`SubmissionSearchResult`; they use `Repo::submission()->getCollector()` with the
OMP-only `->orderByFeatured()` and `DAORegistry::getDAO('FeatureDAO')` /
`NewReleaseDAO`.

### OJS / OPS closest counterparts (non-category browse)

| Surface | Class | Extends | Shared base below Handler? |
|---|---|---|---|
| OJS issue archive | `APP\pages\issue\IssueHandler` (`ojs-main/pages/issue/IssueHandler.php:47`) | `APP\handler\Handler` | no |
| OPS preprint archive | `APP\pages\preprints\PreprintsHandler` (`ops-main/pages/preprints/PreprintsHandler.php:31`) | `APP\handler\Handler` | no |
| OPS section browse | `APP\pages\preprints\SectionsHandler` (`ops-main/pages/preprints/SectionsHandler.php:30`) | `APP\handler\Handler` | no |
| OMP catalog index/series/new releases | `APP\pages\catalog\CatalogHandler` | `PKP\pages\catalog\PKPCatalogHandler` | yes (the catalog handler itself) |
| Site search (all 3) | `APP\pages\search\SearchHandler` | `PKP\pages\search\SearchHandler` **[PKP]** (`lib/pkp/pages/search/SearchHandler.php:23`) | yes |

Search detail: OMP's `APP\pages\search\SearchHandler`
(`omp-main/pages/search/SearchHandler.php`) is a **completely empty subclass**
(class body `{}`); OJS's and OPS's each override only `authorize()`.
The shared `PKP\pages\search\SearchHandler` renders
`frontend/pages/search.tpl`, defines `index()`, `search()`,
`_assignDateFromTo()`, `setupTemplate()`.

### Templates

`frontend/pages/catalogCategory.tpl` exists as an **app-local copy in all three
apps** (`ojs-main/`, `omp-main/`, `ops-main/templates/frontend/pages/`); there is
no copy in `lib/pkp`. All three include the same shared chrome from
`lib/pkp/templates/frontend/components/`:
`header.tpl`, `footer.tpl`, `breadcrumbs.tpl`, `breadcrumbs_catalog.tpl`,
`pagination.tpl`. The three copies diverge in the body:

- OJS → `frontend/objects/article_summary.tpl` **[APP]**
- OPS → `frontend/objects/preprint_summary.tpl` **[APP]**
- OMP → `frontend/components/monographList.tpl` **[APP, OMP-only]**

`breadcrumbs_catalog.tpl` and `pagination.tpl` are the only browse-surface
templates that live in `lib/pkp`; every list/summary template is app-local.
`lib/pkp/templates/frontend/objects/` contains only `announcement_*.tpl` — no
shared submission/issue/monograph object templates exist.

Note (fact, not judgment): OMP's `catalogCategory.tpl` reads
`$featuredMonographIds` and `$newReleasesMonographs` (lines 75-80); those two
template vars are assigned by `CatalogHandler::page()` and
`CatalogHandler::series()` but **not** by the shared
`PKPCatalogHandler::category()` that renders this template.

Browse chrome that is app-only: `ojs-main/.../components/categoryHeader.tpl`,
`ops-main/.../components/archiveHeader.tpl` + `searchForm_archive.tpl`,
`omp-main/.../components/monographList.tpl` + `searchForm_simple.tpl`.

**shared base: partial** — the category-browse slice (`category`/`fullSize`/
`thumbnail`) is one shared class, `PKP\pages\catalog\PKPCatalogHandler`, that
OJS and OPS instantiate directly and OMP subclasses; the shared class also
carries an `isOMP`-style featured branch. Everything else on OMP's catalog
(index/paged catalog, series, new releases) is added in the OMP subclass with no
lib/pkp counterpart, and OJS issue-archive / OPS preprints-archive share nothing
with it below `APP\handler\Handler` → `PKPHandler`. Templates: shared chrome
(header/footer/breadcrumbs_catalog/pagination) from lib/pkp; all list and
summary templates app-local, including three separate copies of
`catalogCategory.tpl`.

---

## 2. Monograph landing page vs article / preprint landing

### Handlers

| App | Class | Extends |
|---|---|---|
| OMP | `APP\pages\catalog\CatalogBookHandler` (`omp-main/pages/catalog/CatalogBookHandler.php:51`) | `APP\handler\Handler` **[APP]** → `PKP\core\PKPHandler` **[PKP]** |
| OJS | `APP\pages\article\ArticleHandler` (`ojs-main/pages/article/ArticleHandler.php:55`) | `APP\handler\Handler` → `PKPHandler` |
| OPS | `APP\pages\preprint\PreprintHandler` (`ops-main/pages/preprint/PreprintHandler.php:42`) | `APP\handler\Handler` → `PKPHandler` |

No intermediate shared landing-page base class exists in `lib/pkp` (there is no
`PKPArticleHandler` / `PKPSubmissionLandingHandler`); `lib/pkp/pages/` has no
article/preprint/book directory.

### Shared services the three landing handlers use in common

All three import and use the same `lib/pkp` machinery: `PKP\submission\Genre` +
`GenreDAO`, `PKP\submissionFile\SubmissionFile`, `PKP\orcid\OrcidManager`,
`PKP\plugins\Hook` + `PluginRegistry`, `PKP\core\Core`, `PKP\core\PKPApplication`,
`PKP\db\DAORegistry`, `PKP\security\authorization\ContextRequiredPolicy`,
`PKP\publication\PKPPublication` / `PKP\submission\PKPSubmission`.
All three emit `APP\observers\events\UsageEvent`, and in each app
`APP\observers\events\UsageEvent extends \PKP\observers\events\UsageEvent`
**[PKP]** (ojs `:29`, omp `:30`, ops `:20`).

App-only pieces per landing handler: OMP adds `APP\monograph\Chapter` +
`ChapterDAO` and OMP payments (`OMPPaymentManager`, `OMPCompletedPaymentDAO`);
OJS adds `APP\issue\Issue` + `IssueAction` and OJS payments;
OPS adds neither chapters nor payments.

Authorization policies are app-specific classes on generic lib/pkp bases:
`OmpPublishedSubmissionAccessPolicy extends ContextPolicy`,
`OjsJournalMustPublishPolicy extends AuthorizationPolicy`,
`OpsServerMustPublishPolicy extends AuthorizationPolicy` — different lib/pkp
bases, no shared app-level policy.

### Templates

| App | Page template | Object template(s) |
|---|---|---|
| OMP | `omp-main/.../pages/book.tpl` | `objects/monograph_full.tpl`, `objects/chapter.tpl` |
| OJS | `ojs-main/.../pages/article.tpl` | `objects/article_details.tpl` (+ `objects/galley_link.tpl`) |
| OPS | `ops-main/.../pages/preprint.tpl` | `objects/preprint_details.tpl` (+ `objects/galley_link.tpl`) |

All three page templates include only `frontend/components/header.tpl` and
`footer.tpl` from `lib/pkp`. Breadcrumbs diverge: OJS uses app-local
`breadcrumbs_article.tpl`, OPS app-local `breadcrumbs_preprint.tpl`, OMP's
`book.tpl` includes no breadcrumb template at all.
OJS and OPS share the *name* `frontend/objects/galley_link.tpl` but each ships
its own app-local copy; OMP has no `galley_link.tpl` and instead uses app-local
`components/publicationFormats.tpl`, `components/downloadLink.tpl`,
`components/authors.tpl`. None of these exist in `lib/pkp`.

**shared base: no** (below generic `Handler`/`PKPHandler`) — the three landing
handlers have no common ancestor other than `APP\handler\Handler` →
`PKPHandler`, and no landing-page template is shared beyond `header.tpl` /
`footer.tpl`. What is shared is service-level, not structural: Genre/GenreDAO,
SubmissionFile, OrcidManager, Hook/PluginRegistry, and the `PKP\observers\events\UsageEvent`
base.

---

## 3. Catalog management backend

### Page handlers — OMP manageCatalog vs OJS manageIssues

| App | Class | Extends | Renders |
|---|---|---|---|
| OMP | `APP\pages\manageCatalog\ManageCatalogHandler` (`omp-main/pages/manageCatalog/ManageCatalogHandler.php:32`) | `APP\handler\Handler` **[APP]** → `PKP\core\PKPHandler` **[PKP]** | `manageCatalog/index.tpl` **[APP]** |
| OJS | `APP\pages\manageIssues\ManageIssuesHandler` (`ojs-main/pages/manageIssues/ManageIssuesHandler.php:24`) | `APP\handler\Handler` → `PKPHandler` | `manageIssues/issues.tpl` **[APP]** |
| OPS | — no manageCatalog-like page (`ops-main/pages/` has no such directory) | — | — |

`lib/pkp/pages/` contains no `manageCatalog` or `manageIssues` directory — no
shared page handler for either. Both app handlers use the same generic lib/pkp
pieces (`PKPSiteAccessPolicy`, `PKP\security\Role`); nothing catalog-specific is
shared between them.

The two differ in UI generation: OJS's `issues.tpl` mounts legacy component
grids (`grid.issues.FutureIssueGridHandler`, `grid.issues.BackIssueGridHandler`),
while OMP's handler builds a Vue list panel via
`$templateMgr->setState()` + `pageComponent = 'ManageCatalogPage'`.

### The OMP catalog-management UI lives in the shared `lib/ui-library` submodule

`omp-main/js/load.js:39` imports
`@/components/Container/ManageCatalogPage.vue`. That file resolves to
`lib/ui-library/src/components/Container/ManageCatalogPage.vue` — the
`lib/ui-library` submodule, which is checked out at the **same commit
`332e4db8850d079c4cb18eb8f6c36f4caabe97df` in all three apps**, so the file is
physically present in `ojs-main/lib/ui-library/` and `ops-main/lib/ui-library/`
as well. Neither `ojs-main/js/load.js` nor `ops-main/js/load.js` references
`Catalog` at all — OMP is the only app that registers the component.

Catalog components shipped in the shared `lib/ui-library`:
`Container/ManageCatalogPage.vue` (`extends: Page` from `Container/Page.vue`),
`ListPanel/submissions/CatalogListPanel.vue` (+ `.mdx` / `.stories.js`),
`ListPanel/submissions/CatalogListItem.vue`,
`ListPanel/submissions/CatalogEditModal.vue`, `Icon/icons/Catalog.vue`.
They sit alongside `SubmissionsListPanel.vue` / `SubmissionsListItem.vue` in the
same directory and reuse the same generic library parts
(`ListPanel/ListPanel.vue`, `Pagination`, `Search`, `Filter`, `Header`,
`Orderer`, mixins `ajaxError` / `fetch`).

PHP side of that panel: `APP\components\listPanels\CatalogListPanel` **[APP]**
(`omp-main/classes/components/listPanels/CatalogListPanel.php:26`)
`extends PKP\components\listPanels\ListPanel` **[PKP]**
(`lib/pkp/classes/components/listPanels/ListPanel.php`) — i.e. the *generic*
list-panel base, not `PKPSubmissionsListPanel` (which OJS/OMP/OPS
`SubmissionsListPanel` all extend). No OJS or OPS app has a `CatalogListPanel`.

### Featured / new-release machinery (write path)

The write path is the OMP override of the shared backend submissions API:

`APP\API\v1\_submissions\BackendSubmissionsController` **[APP]**
(`omp-main/api/v1/_submissions/BackendSubmissionsController.php:34`)
`extends \PKP\API\v1\_submissions\PKPBackendSubmissionsController` **[PKP]**
(`lib/pkp/api/v1/_submissions/PKPBackendSubmissionsController.php`).
OMP adds routes/methods `saveDisplayFlags()`, `saveFeaturedOrder()`,
`addToCatalog()` plus a `getSubmissionCollector()` override.
OJS's subclass of the same base adds `payment()` + `authorize()` +
`getSubmissionCollector()`; OPS's adds only `getSubmissionCollector()`.
Neither OJS nor OPS has any `saveDisplayFlags` / `saveFeaturedOrder` /
`addToCatalog` equivalent.

Backing DAOs (see area 4): `APP\press\FeatureDAO` and `APP\press\NewReleaseDAO`,
both `extends PKP\db\DAO` **[PKP]** and both terminal. Registered in
`omp-main/classes/core/Application.php:101,105`. Consumed by
`classes/services/ContextService.php`, `classes/submission/DAO.php`,
`classes/submission/maps/Schema.php`, `api/v1/_submissions/BackendSubmissionsController.php`,
`pages/catalog/CatalogHandler.php`, `pages/index/IndexHandler.php`.
Greps for `FeatureDAO` / `NewReleaseDAO` over `ojs-main/classes`,
`ops-main/classes` and `lib/pkp/classes` return zero hits.

No spotlight classes exist in current OMP — grep for `spotlight` over
`omp-main` (excluding `lib/pkp`) hits only upgrade migrations
(`classes/migration/upgrade/v3_5_0/I9262_Highlights.php`, v3_4_0 migrations).
Highlights are shared: `lib/pkp/classes/components/listPanels/HighlightsListPanel.php`.

### Catalog-entry controllers (workflow-side ONIX/format editing)

All live in `omp-main/controllers/grid/catalogEntry/` and sit directly on
generic lib/pkp grid/form infrastructure — no OMP-side intermediate base:

- `extends PKP\controllers\grid\GridHandler` **[PKP]**:
  `IdentificationCodeGridHandler:40`, `MarketsGridHandler:40`,
  `PublicationDateGridHandler:40`, `SalesRightsGridHandler:40`
- `extends PKP\controllers\grid\CategoryGridHandler` **[PKP]**:
  `PublicationFormatGridHandler:54`, `RepresentativesGridHandler:37`
- `extends PKP\controllers\grid\GridRow` **[PKP]**: `IdentificationCodeGridRow`,
  `MarketsGridRow`, `PublicationDateGridRow`, `SalesRightsGridRow`,
  `RepresentativesGridRow`
- `extends PKP\controllers\grid\GridCategoryRow` **[PKP]**:
  `PublicationFormatGridCategoryRow`, `RepresentativesGridCategoryRow`
- `extends PKP\controllers\grid\DataObjectGridCellProvider` **[PKP]**: the five
  `*GridCellProvider` classes
- `extends PKP\form\Form` **[PKP]**: `IdentificationCodeForm`, `MarketForm`,
  `PublicationDateForm`, `PublicationFormatForm`,
  `PublicationFormatMetadataForm`, `RepresentativeForm`, `SalesRightsForm`
- Two exceptions that use *file-grid* bases rather than plain grid bases:
  `PublicationFormatGridRow:25 extends PKP\controllers\grid\files\SubmissionFilesGridRow` **[PKP]**
  and
  `PublicationFormatCategoryGridDataProvider:28 extends PKP\controllers\grid\files\SubmissionFilesCategoryGridDataProvider` **[PKP]**.

Mount point: these grids are fetched by URL from
`omp-main/templates/controllers/tab/catalogEntry/form/publicationMetadataFormFields.tpl`
(component `grid.catalogEntry.*`), with app-only JS handlers in
`omp-main/js/controllers/modals/catalogEntry/form/`.

Cross-app use of the same bases for a comparable job:
- `CategoryGridHandler` — OJS `controllers/grid/toc/TocGridHandler:34` (issue
  table of contents), OMP `controllers/grid/users/chapter/ChapterGridHandler:45`,
  plus lib/pkp's own `StageParticipantGridHandler`, `LibraryFileGridHandler`,
  `SelectableSubmissionFileListCategoryGridHandler`.
- `SubmissionFilesCategoryGridDataProvider` — only two subclasses exist:
  OMP's `PublicationFormatCategoryGridDataProvider` and lib/pkp's own
  `ReviewCategoryGridDataProvider`.
- OJS issue grids sit on the same generic bases:
  `IssueGridHandler` (OJS-local base for `BackIssueGridHandler:25`,
  `FutureIssueGridHandler:24`, `ExportableIssuesListGridHandler:24`),
  `IssueGridRow extends GridRow`, `IssueGridCellProvider extends GridCellProvider`,
  `IssueForm` / `IssueAccessForm` / `IssueGalleyForm` `extends PKP\form\Form`,
  `IssueGalleyGridHandler:38 extends GridHandler`. OPS's
  `PreprintGalleyGridHandler:46 extends GridHandler` likewise.

### Series vs section settings grids — the one genuinely parallel triple

- OMP `APP\controllers\grid\settings\series\SeriesGridHandler:39`
- OJS `APP\controllers\grid\settings\sections\SectionGridHandler:36`
- OPS `APP\controllers\grid\settings\sections\SectionGridHandler:36`

All three `extend PKP\controllers\grid\settings\SetupGridHandler` **[PKP]**
(also the base of lib/pkp's own `GenreGridHandler:36`).
Their forms all `extend PKP\controllers\grid\settings\sections\form\PKPSectionForm`
**[PKP]** (`lib/pkp/controllers/grid/settings/sections/form/PKPSectionForm.php`):
OMP `SeriesForm:34`, OJS `SectionForm:27`, OPS `SectionForm:25`.
Rows/cell providers all `extend GridRow` / `GridCellProvider` **[PKP]**.

**shared base: partial** — split by sub-surface.
(a) *Catalog list management page*: no shared PHP base (`ManageCatalogHandler`
and OJS `ManageIssuesHandler` meet only at `APP\handler\Handler` → `PKPHandler`),
but the Vue UI (`ManageCatalogPage.vue`, `CatalogListPanel.vue`,
`CatalogListItem.vue`, `CatalogEditModal.vue`) ships inside the **shared
`lib/ui-library` submodule** present at an identical commit in all three
checkouts, registered only by OMP's `js/load.js`; its PHP list panel sits on the
generic `PKP\components\listPanels\ListPanel`.
(b) *Featured/new-release writes*: OMP-added methods on the shared
`PKPBackendSubmissionsController`, backed by `FeatureDAO`/`NewReleaseDAO` that
extend only `PKP\db\DAO` and have no OJS/OPS counterpart on any base.
(c) *Catalog-entry (ONIX/format) grids and forms*: no lib/pkp base beyond
generic `GridHandler` / `CategoryGridHandler` / `GridRow` /
`DataObjectGridCellProvider` / `Form`, except two that share the file-grid bases
`SubmissionFilesGridRow` and `SubmissionFilesCategoryGridDataProvider` with
lib/pkp's own review/file grids.
(d) *Series settings*: fully parallel to OJS/OPS section settings on the same
`SetupGridHandler` + `PKPSectionForm` bases.

---

## 4. Data layer behind catalog display

### Publication formats vs galleys

- `APP\publicationFormat\PublicationFormat` **[APP]**
  (`omp-main/classes/publicationFormat/PublicationFormat.php:29`)
  `extends PKP\submission\Representation` **[PKP]**
  (`lib/pkp/classes/submission/Representation.php:25`)
  `extends PKP\core\DataObject` **[PKP]**.
- `PKP\galley\Galley` **[PKP]** (`lib/pkp/classes/galley/Galley.php:25`)
  extends the **same** `PKP\submission\Representation`. Neither OJS nor OPS has
  an app-side `Galley` subclass — the entity lives only in lib/pkp.
- DAO side diverges. `APP\publicationFormat\PublicationFormatDAO` **[APP]**
  (`omp-main/classes/publicationFormat/PublicationFormatDAO.php:29`)
  `extends PKP\db\DAO` **[PKP]** (legacy DAO) and implements
  `PKP\submission\RepresentationDAOInterface`. OJS's `APP\galley\DAO`
  (`ojs-main/classes/galley/DAO.php:11`) `extends PKP\galley\DAO`
  `extends PKP\core\EntityDAO`. OPS has no `classes/galley/` at all and uses
  `PKP\galley\DAO` / `PKP\galley\Repository` directly.
- OMP has **no** Repo/Repository/Collector for publication formats:
  `omp-main/classes/facades/Repo.php` has no `galley()` or
  `publicationFormat()` method (OJS and OPS both have `Repo::galley()`);
  access is via `DAORegistry::getDAO('PublicationFormatDAO')`.
- App-only with no lib/pkp base at all:
  `APP\publicationFormat\PublicationFormatTombstoneManager` (no `extends`).

### ONIX display data

No ONIX-aware class or base exists in `lib/pkp`, `ojs-main`, or `ops-main`
(greps for `onix` over `lib/pkp/classes|controllers|pages`, `ojs-main/classes`,
`ops-main/classes` return zero hits). All ONIX classes are `APP\...` in
`omp-main` and bottom out in generic lib/pkp primitives:

- `extends PKP\core\DataObject` **[PKP]**: `IdentificationCode`, `Market`,
  `SalesRights`, `PublicationDate` (all `omp-main/classes/publicationFormat/`),
  `APP\monograph\Representative`, `APP\codelist\CodelistItem` (abstract),
  `APP\codelist\ONIXCodelistItem`.
- `extends PKP\db\DAO` **[PKP]**: `IdentificationCodeDAO`, `MarketDAO`,
  `SalesRightsDAO`, `PublicationDateDAO`, `APP\monograph\RepresentativeDAO`,
  `APP\codelist\CodelistItemDAO` (abstract), `APP\codelist\ONIXCodelistItemDAO`.
- App-only intermediate bases: `APP\codelist\CodelistItem` (parent of
  `Subject`, `Qualifier`) and `APP\codelist\CodelistItemDAO` (parent of
  `SubjectDAO`, `QualifierDAO`).
- `APP\codelist\ONIXParserDOMHandler extends PKP\xml\XMLParserDOMHandler` **[PKP]**.

### Features / new releases

- `APP\press\FeatureDAO` **[APP]** (`omp-main/classes/press/FeatureDAO.php:21`)
  `extends PKP\db\DAO` **[PKP]** — terminal.
- `APP\press\NewReleaseDAO` **[APP]**
  (`omp-main/classes/press/NewReleaseDAO.php:25`) `extends PKP\db\DAO` — terminal.
- No `Feature` or `NewRelease` entity/DataObject class exists anywhere in
  `omp-main`; both DAOs return raw ids/arrays.
- Registered in `omp-main/classes/core/Application.php:101,105`. Consumers:
  `classes/services/ContextService.php`, `classes/submission/DAO.php`,
  `classes/submission/maps/Schema.php`, `pages/catalog/CatalogHandler.php`,
  `pages/index/IndexHandler.php`.
- Greps for `FeatureDAO|NewReleaseDAO|features_|new_releases` over
  `ojs-main/classes`, `lib/pkp/classes`, `ops-main/classes`: **zero hits**.
  No OJS/OPS class does a comparable job on any base.

### Submission / publication classes used by the catalog

All three apps subclass identical lib/pkp bases; none of the subclasses is empty.

- `APP\submission\Submission` → `PKP\submission\PKPSubmission` (abstract) →
  `PKP\core\DataObject` — same in OJS, OMP, OPS.
- `APP\submission\Collector` → `PKP\submission\Collector` (abstract) — same in
  all three. OMP's adds `ORDERBY_SERIES_POSITION`, `filterBySeriesIds()`,
  **`orderByFeatured()`**, DOI filters, `getQueryBuilder()`, `getReviewStages()`.
- `APP\submission\Repository` → `PKP\submission\Repository`;
  `APP\submission\DAO` → `PKP\submission\DAO` → `PKP\core\EntityDAO` — same in
  all three.
- `APP\publication\Publication` → `PKP\publication\PKPPublication` →
  `PKP\core\DataObject`; `APP\publication\Repository` →
  `PKP\publication\Repository`; `APP\publication\DAO` → `PKP\publication\DAO` →
  `PKP\core\EntityDAO` — same in all three. OMP's `Publication` adds
  `getCoverImageUrl()`, `getLocalizedCoverImageThumbnailUrl()`,
  `getEditorString()`; its `Repository` adds `makeThumbnail()`,
  `getThumbnailFileName()`, `addChapterLicense()`.
- OMP has no `classes/publication/Collector.php` (OJS has one extending
  `PKP\publication\Collector`; OPS has none).

### Series vs Section

- There is **no** `Series` / `SeriesDAO` class in `omp-main`. Series is
  implemented as `APP\section\*` against the `series` / `series_settings` tables
  (`omp-main/classes/section/DAO.php:33,36,39`).
- `APP\section\Section` → `PKP\section\PKPSection` **[PKP]**
  (`lib/pkp/classes/section/PKPSection.php:23`) → `PKP\core\DataObject`;
  `APP\section\DAO` → `PKP\section\DAO` → `PKP\core\EntityDAO`;
  `APP\section\Repository` → `PKP\section\Repository`.
  **Identical chains in OJS and OPS.** OMP's `Section` adds `getFeatured()`,
  `setFeatured()`, `getImage()`, `setImage()`, `getPath()`, `getSortOption()`,
  prefix/subtitle accessors, ISSN accessors.
- OMP's `CatalogHandler::page()` obtains series via the shared
  `Repo::section()->getCollector()`.
- Related: `APP\press\Press extends PKP\context\Context` **[PKP]**;
  `APP\press\PressDAO extends PKP\context\ContextDAO` **[PKP]**.

**shared base: partial** — the submission/publication/section spine under the
catalog is fully shared (`PKPSubmission`, `PKP\submission\Collector`,
`PKPPublication`, `PKPSection`, `Representation`), and OMP's
`PublicationFormat` shares `PKP\submission\Representation` with OJS/OPS galleys.
The catalog-specific layers are app-only on generic primitives:
`FeatureDAO`/`NewReleaseDAO` extend only `PKP\db\DAO` with no OJS/OPS
counterpart on any base, every ONIX/codelist class extends only
`PKP\core\DataObject` / `PKP\db\DAO` with no lib/pkp counterpart, and OMP's
publication-format access path (legacy `PKP\db\DAO`, no `Repo::` facade entry)
diverges from OJS/OPS galleys (`EntityDAO` + `Repo::galley()`).
