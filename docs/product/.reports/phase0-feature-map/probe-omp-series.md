# Probe — OMP "Series" vs OJS/OPS "Sections": code lineage

- **Date**: 2026-07-27 · **Author**: claude (Opus), desk pass over local checkouts only
  (read-only; no live environment, no app changes).
- **Question**: does OMP Series share the lib/pkp section machinery (relabeled
  parameterization), or is it its own machinery — and what does it add/lack relative to
  OJS/OPS Sections?
- **Method**: RUNBOOK "The multi-app rules" rule 8 (inheritance first) — subclass-chain
  reading, schema field diffs, install-migration DDL diffs, locale-override diffs, greps
  for shared components/includes. Checkouts:
  `/Users/jarda/git/pkp/pkp-main/{ojs-main,omp-main,ops-main}`; `lib/pkp` identical
  across them (paths below cite `ojs-main/lib/pkp/...` for the shared copy).
- **Scope discipline**: facts only. No liveness, quality, or scope judgments.

---

## 1. Model / data layer

### 1.1 Class lineage

OMP has no class named `Series`. The entity is `APP\section\Section`, in a directory
named `classes/section/`, with docblocks that say "series".

| Layer | lib/pkp base | OJS | OMP | OPS |
|---|---|---|---|---|
| Entity | `PKP\section\PKPSection` extends `PKP\core\DataObject` | `APP\section\Section extends \PKP\section\PKPSection` | `APP\section\Section extends \PKP\section\PKPSection` | `APP\section\Section extends \PKP\section\PKPSection` |
| DAO | `abstract PKP\section\DAO extends PKP\core\EntityDAO` (trait `EntityWithParent`) | `APP\section\DAO extends \PKP\section\DAO` | `APP\section\DAO extends \PKP\section\DAO` | `APP\section\DAO extends \PKP\section\DAO` |
| Repository | `PKP\section\Repository` (concrete, not abstract) | `APP\section\Repository extends \PKP\section\Repository` | `APP\section\Repository extends \PKP\section\Repository` | `APP\section\Repository extends \PKP\section\Repository` |
| Collector | `PKP\section\Collector implements CollectorInterface` — **no app subclass in any of the three apps** | — | — | — |
| Schema map | `PKP\section\maps\Schema extends PKP\core\maps\Schema` | **no app subclass** (`ojs-main/classes/section/` has no `maps/` dir) | `APP\section\maps\Schema extends \PKP\section\maps\Schema` | `APP\section\maps\Schema extends \PKP\section\maps\Schema` |

Files:
- `/Users/jarda/git/pkp/pkp-main/ojs-main/lib/pkp/classes/section/PKPSection.php`
- `/Users/jarda/git/pkp/pkp-main/ojs-main/lib/pkp/classes/section/DAO.php`
- `/Users/jarda/git/pkp/pkp-main/ojs-main/lib/pkp/classes/section/Repository.php`
- `/Users/jarda/git/pkp/pkp-main/ojs-main/lib/pkp/classes/section/Collector.php`
- `/Users/jarda/git/pkp/pkp-main/ojs-main/lib/pkp/classes/section/maps/Schema.php`
- `/Users/jarda/git/pkp/pkp-main/omp-main/classes/section/{Section,DAO,Repository}.php`, `.../section/maps/Schema.php`
- `/Users/jarda/git/pkp/pkp-main/ojs-main/classes/section/{Section,DAO,Repository}.php`
- `/Users/jarda/git/pkp/pkp-main/ops-main/classes/section/{Section,DAO,Repository}.php`, `.../section/maps/Schema.php`

`PKPSection` (shared, all three apps inherit unchanged): `getEditorRestrictedRoles()`
(SITE_ADMIN / MANAGER / SUB_EDITOR), `contextId`, `sequence`, `title`,
`editorRestricted`, `isInactive`.

### 1.2 Table binding (pure parameterization of the shared DAO)

The lib/pkp DAO and Collector are parameterized entirely by DAO properties
(`$table`, `$settingsTable`, `$primaryKeyColumn`, `$primaryTableColumns`,
`getParentColumn()`). No `isOJS()/isOMP()/isOPS()` branch exists anywhere in
`lib/pkp/classes/section/`.

| DAO property | OJS | OMP | OPS |
|---|---|---|---|
| `$schema` | `PKPSchemaService::SCHEMA_SECTION` | `PKPSchemaService::SCHEMA_SECTION` (same constant) | `PKPSchemaService::SCHEMA_SECTION` |
| `$table` | `sections` | `series` | `sections` |
| `$settingsTable` | `section_settings` | `series_settings` | `section_settings` |
| `$primaryKeyColumn` | `section_id` | `series_id` | `section_id` |
| `getParentColumn()` | `journal_id` | `press_id` | `server_id` |

`PKP\section\Collector::getQueryBuilder()` builds every clause from those properties,
including `withPublished()` (`whereColumn('p.' . primaryKeyColumn, …)` against
`publications`) — which resolves to `publications.series_id` on OMP without any OMP-side
code. `filterByAbbrevs()` exists in the shared Collector and queries the
`abbrev` setting, which OMP's schema does not declare.

`omp-main/classes/core/Application.php:36`:
`public const ASSOC_TYPE_SERIES = self::ASSOC_TYPE_SECTION;` — the OMP "series" assoc
type is a literal alias of the shared `PKPApplication::ASSOC_TYPE_SECTION` (`0x0000212`).
OMP's sub-editor assignments are stored under `ASSOC_TYPE_SECTION` like OJS's.

### 1.3 Schema overlay diff

`PKPSchemaService::get()` loads `lib/pkp/schemas/<name>.json` then merges
`<app>/schemas/<name>.json` over it. All three apps overlay `section.json`.

Shared base — `/Users/jarda/git/pkp/pkp-main/ojs-main/lib/pkp/schemas/section.json`
(`required: [contextId, title]`): `contextId`, `editorRestricted`, `id`, `isInactive`,
`sequence`, `title` (multilingual). All six are `apiSummary`.

| Property | lib/pkp | OJS overlay | OMP overlay | OPS overlay |
|---|---|---|---|---|
| `abbrev` (ml) | — | yes | — | yes |
| `abstractsNotRequired` | — | yes | — | yes |
| `description` (ml) | — | — | yes | yes |
| `featured` | — | — | yes | — |
| `fullTitle` (readOnly) | — | — | yes | — |
| `hideAuthor` | — | yes | — | yes |
| `hideTitle` | — | yes | — | yes |
| `identifyType` (ml) | — | yes | — | yes |
| `image` (array) | — | — | yes | — |
| `metaIndexed` | — | yes | — | yes |
| `metaReviewed` | — | yes | — | yes |
| `onlineIssn` | — | — | yes | — |
| `path` | — | — | yes | yes |
| `policy` (ml) | — | yes | — | yes |
| `prefix` (ml) | — | — | yes | — |
| `printIssn` | — | — | yes | — |
| `reviewFormId` | — | yes | yes | yes |
| `sortOption` | — | — | yes | — |
| `subtitle` (ml) | — | — | yes | — |
| `wordCount` | — | yes | — | yes |

Also: OJS and OPS overlays restate `required: [contextId, title]`; OMP's overlay declares
`required: []` (the merge keeps the lib/pkp requirement — the overlay does not remove it,
it simply does not restate it).

Where the app fields live physically:
- OMP `series` table columns (beyond shared): `featured`, `path`, `image`,
  plus `review_form_id`. Unique index `series_path` on `(press_id, path)`.
  `description`, `prefix`, `subtitle`, `onlineIssn`, `printIssn`, `sortOption` are
  `series_settings` rows.
- OJS/OPS `sections` table columns (beyond shared): `meta_indexed`, `meta_reviewed`,
  `abstracts_not_required`, `hide_title`, `hide_author`, `abstract_word_count`,
  `review_form_id`. `abbrev`, `policy`, `identifyType` (and OPS `description`, `path`)
  are `section_settings` rows.
- Files: `omp-main/classes/migration/install/OMPMigration.php:273-315`,
  `ojs-main/classes/migration/install/OJSMigration.php:31-68`,
  `ops-main/classes/migration/install/OPSMigration.php:31-68`.

### 1.4 App-side additions to the model layer

**OMP-only** (`omp-main/classes/section/DAO.php` + `Repository.php`):
- `getByPath(string $path, ?int $pressId)` — path lookup (no OJS/OPS counterpart, even
  though OPS declares a `path` schema prop).
- Category linkage against an OMP-only join table `series_categories`
  (`omp-main/classes/migration/install/SeriesCategoriesMigration.php`):
  `addToCategory()`, `removeFromCategory()`, `getAssignedCategoryIds()`,
  `getAssignedCategories()`, `categoryAssociationExists()`.
- `Repository::isEmpty()` **override**. The shared
  `PKP\section\Repository::isEmpty()` calls `Repo::submission()->getCollector()
  ->filterBySectionIds(...)`; that method is declared per app
  (`ojs-main/classes/submission/Collector.php:46`,
  `ops-main/classes/submission/Collector.php:35`) and OMP declares
  `filterBySeriesIds()` instead (`omp-main/classes/submission/Collector.php:44`).
  The OMP override exists to bridge that rename.
- `maps\Schema::mapByProperties()` adds `urlPublished` (route
  `catalog/series/{path}`) — note `urlPublished` is not declared in
  `omp-main/schemas/section.json`.

**OMP entity accessors** (`omp-main/classes/section/Section.php`) beyond `PKPSection`:
`getLocalizedTitle($includePrefix = true)` / `getTitle($locale, $includePrefix = true)`
(both **widen the shared signature** to prepend the prefix), `getLocalizedFullTitle()`,
prefix/subtitle/description getters+setters, `featured`, `image`, `onlineIssn`,
`printIssn`, `reviewFormId`, `sortOption`, `path`, and `getEditorsString()` (comma-joined
sub-editor names via `SubEditorsDAO::getBySubmissionGroupIds(..., ASSOC_TYPE_SECTION, ...)`).

**OJS entity accessors** beyond `PKPSection`: `abbrev`, `policy`, `reviewFormId`,
`metaIndexed`, `metaReviewed`, `abstractsNotRequired`, `hideTitle`, `hideAuthor`,
`wordCount` (via `get/setAbstractWordCount`), `identifyType`.
**OPS entity**: same list as OJS minus none observed — OPS `Section.php` mirrors OJS's
accessor set (plus schema-level `description`/`path`).

**OJS-only Repository additions**: issue-scoped helpers — `getByIssueId()`,
`customSectionOrderingExists()`, `deleteCustomSectionOrdering()`,
`getCustomSectionOrder()`, `deleteCustomSectionOrder()`, `upsertCustomSectionOrder()`
(table `custom_section_orders`, `OJSMigration.php:207`).
**OPS Repository**: empty subclass (positive evidence of fully shared behavior, rule 8).

### 1.5 Publication-side linkage (this is where the name actually forks)

| | OJS | OMP | OPS |
|---|---|---|---|
| `publications` column | `section_id` (FK → `sections`) | `series_id` (FK → `series`), plus `series_position` (varchar 255) | `section_id` |
| Publication schema prop | `sectionId` (in `required`) | `seriesId`, `seriesPosition` (not required) | `sectionId` (in `required`) |
| Publication DAO mapping | `'sectionId' => 'section_id'` | `'seriesId' => 'series_id'`, `'seriesPosition' => 'series_position'` | `'sectionId' => 'section_id'` |

Files: `omp-main/schemas/publication.json:29,37`, `ojs-main/schemas/publication.json:4,59`,
`ops-main/schemas/publication.json:4,39`; `omp-main/classes/publication/DAO.php:24-44`,
`ojs-main/classes/publication/DAO.php:31-50`.
The OMP index on `publications.series_id` is still named `publications_section_id`
(`OMPMigration.php:47`).

### 1.6 Labels

The apps relabel by overriding the *same* locale keys, not by new keys:

| key | OJS | OMP | OPS |
|---|---|---|---|
| `section.section` | "Section" | "Series" | "Section" |
| `section.any` | "Any Section" | "Any Series" | (not overridden) |
| `manager.sections.alertDelete` | "…sections…" | "…series…" | (empty) |

OMP additionally defines three genuinely new keys (`series.series`, `series.path`,
`series.featured.description`). Counts of `section.*` / `manager.sections.*` / `series.*`
msgids in `locale/en`: lib/pkp 6, OJS 37, OPS 24, OMP 14.

**shared base: yes (partial overlay)** — OMP Series *is* `APP\section\Section` on
`PKP\section\{PKPSection,DAO,Repository,Collector,maps\Schema}`, the identical shared
chain OJS and OPS use, parameterized to the `series`/`series_settings` tables and
relabeled via locale overrides of the same `section.*` keys; `ASSOC_TYPE_SERIES` is a
literal alias of `ASSOC_TYPE_SECTION`. The one structural fork is at the *publication*
edge (`seriesId`/`seriesPosition` vs `sectionId`), which is app-side, not lib/pkp.

- **OMP adds** (vs OJS/OPS section fields): `prefix`, `subtitle`, `description`
  (OPS also has this), `featured`, `image`, `path` (OPS also declares this), `onlineIssn`,
  `printIssn`, `sortOption`, `fullTitle` (readOnly), `urlPublished` (map-only),
  category linkage (`series_categories`), `getByPath()`, `seriesPosition` on publications.
- **OMP lacks** (vs OJS): `abbrev`, `policy`, `identifyType`, `metaIndexed`,
  `metaReviewed`, `abstractsNotRequired`, `hideTitle`, `hideAuthor`, `wordCount`;
  and OJS's issue/custom-section-ordering repository methods.
- **OMP shares with both**: `contextId`, `id`, `title`, `sequence`, `editorRestricted`,
  `isInactive`, `reviewFormId`, sub-editor assignment under `ASSOC_TYPE_SECTION`.

---

## 2. Management UI (settings → Series grid vs Sections grid)

### 2.1 Mount point — same tab, same mechanism, different label

All three apps mount the manager UI as a Smarty/AJAX **legacy grid** inside a Vue `<tab>`
wrapper on the context settings page. None of the three uses a Vue component for
section/series management (the adjacent `categories` tab, by contrast, is
`<category-manager>` in all three).

| App | File | Tab id | Tab label key | Grid loaded |
|---|---|---|---|---|
| OMP | `omp-main/templates/management/context.tpl:43-46` | `sections` | `series.series` | `grid.settings.series.SeriesGridHandler::fetchGrid` → `#seriesGridContainer` |
| OJS | `ojs-main/templates/management/context.tpl:42-45` | `sections` | `section.sections` | `grid.settings.sections.SectionGridHandler::fetchGrid` → `#sectionsGridContainer` |
| OPS | `ops-main/templates/management/context.tpl:42-45` | `sections` | `section.sections` | same as OJS |

The OMP tab id is literally `sections`.

### 2.2 Subclass chains

| Class | OMP | OJS / OPS | Common ancestor |
|---|---|---|---|
| Grid handler | `APP\controllers\grid\settings\series\SeriesGridHandler` (`omp-main/controllers/grid/settings/series/SeriesGridHandler.php:39`) | `APP\controllers\grid\settings\sections\SectionGridHandler` (`ojs-main/…/sections/SectionGridHandler.php:36`, `ops-main/…:36`) | `PKP\controllers\grid\settings\SetupGridHandler` → `PKP\controllers\grid\GridHandler` |
| Grid row | `SeriesGridRow:24` | `SectionGridRow:24` | `PKP\controllers\grid\GridRow` (no section-specific shared row) |
| Cell provider | `SeriesGridCellProvider:25` | `SectionGridCellProvider:24/25` | `PKP\controllers\grid\GridCellProvider` |
| Form | `APP\controllers\grid\settings\series\form\SeriesForm` (`omp-main/…/series/form/SeriesForm.php:34`) | `APP\controllers\grid\settings\sections\form\SectionForm` (`ojs-main/…:27`, `ops-main/…:25`) | **`PKP\controllers\grid\settings\sections\form\PKPSectionForm`** → `PKP\form\Form` |

`lib/pkp/controllers/grid/settings/sections/` contains **only** `form/PKPSectionForm.php` —
no shared grid handler, row, cell provider, or template.

`PKPSectionForm` (shared, all three subclass it) supplies: `$_sectionId`, `$_userId`,
`$_imageExtension`, `$_sizeArray`, `?Section $section`,
`$assignableRoles = [MANAGER, SUB_EDITOR, ASSISTANT]`; `readInputData()` →
`['title', 'subEditors']`; `initData()` → `subeditorUserGroups`; `fetch()` →
`assignableUserGroups`; `execute()` → sub-editor writes via `SubEditorsDAO` under
`ASSOC_TYPE_SECTION`; `FormValidatorPost` + `FormValidatorCSRF`.
Note: the cover-image members `$_imageExtension` / `$_sizeArray` sit in the **shared** PKP
base but are exercised only by OMP's `SeriesForm`; likewise `SetupGridHandler::uploadImage()`
is shared but called only by OMP.

### 2.3 Grid handler diffs

- **Ops in role assignments**: OMP `fetchGrid, fetchRow, addSeries, editSeries,
  updateSeries, deleteSeries, saveSequence, deactivateSeries, activateSeries,
  deleteImage` (`SeriesGridHandler.php:47-61`); OJS/OPS the same set minus `deleteImage`.
  Both `[ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN]`.
- **Columns**: OMP `title`, **`categories`**, `editors`, `inactive`
  (`SeriesGridHandler.php:152-176`); OJS/OPS `title`, `editors`, `inactive`
  (`SectionGridHandler.php:120-144`). All three use
  `controllers/grid/common/cell/selectStatusCell.tpl` for `inactive`.
- **Grid title / add action**: OMP `catalog.manage.series` / `grid.action.addSeries`
  (icon `add_category`); OJS/OPS `section.sections` / `manager.sections.create`
  (icon `add_section`).
- **Features**: all three `[new OrderGridItemsFeature()]`.
- **OMP-only handler methods**: `getPublishChangeEvents()` → `['updateSidebar']`
  (`:191`), `deleteImage()` (`:401`, removes files under `<contextFiles>/series/`).
- OJS/OPS `initialize()` explicitly `uasort()`s grid data by `seq`; OMP does not.
- Delete confirmation key: OMP `common.confirmDelete` (`SeriesGridRow.php:61`) vs
  OJS/OPS `manager.sections.confirmDelete` (`SectionGridRow.php:62`).
- OJS ↔ OPS grid handlers are functionally identical (diff is `getJournal()`/`getServer()`
  and naming/whitespace).

### 2.4 Form field diff (fields actually declared)

Shared via `PKPSectionForm`: `title`, `subEditors`.

| Field | OMP Series | OJS Section | OPS Section |
|---|---|---|---|
| `title` (ml, required) | yes | yes | yes |
| `subEditors` (assignment) | yes | yes | yes |
| `isInactive` | yes | yes (+ last-active guard) | yes (+ last-active guard) |
| `editorRestricted` | yes | yes | yes, spelled `editorRestriction` |
| `abbrev` | — | yes (required) | yes (required) |
| `prefix` (ml) | yes | — | — |
| `subtitle` (ml) | yes | — | — |
| `description` (ml, rich) | yes | — | yes |
| `policy` (ml, rich) | — | yes | yes |
| `path` | yes (regex `^[a-zA-Z0-9/._-]+$` + uniqueness via `Repo::section()->getByPath`) | — | yes (plain required) |
| `image` / `temporaryFileId` (cover upload + delete) | yes | — | — |
| `categories[]` (link to Category) | yes | — | — |
| `featured` | yes | — | — |
| `onlineIssn` / `printIssn` (`FormValidatorISSN` + must differ) | yes | — | — |
| `sortOption` | yes | — | — |
| `reviewFormId` | — | yes (validated select) | set in `initData()` only, not read/rendered |
| `wordCount` | — | yes | yes |
| `abstractsNotRequired` | — | yes | yes |
| `metaIndexed` (inverted in form) | — | yes | yes |
| `metaReviewed` (inverted) | — | yes | `initData()` only, not read/rendered |
| `identifyType` (ml) | — | yes | yes |
| `hideTitle` / `hideAuthor` | — | yes | `initData()` only, not read/rendered |
| `getLocaleFieldNames()` override | **absent** (falls through to `Form::getLocaleFieldNames()` → `[]`) | `[title, policy, abbrev, identifyType]` | `[title, policy, abbrev, identifyType, description]` |
| sequence on create | not set; no `resequence()` call | `setSequence(REALLY_BIG_NUMBER)` + `resequence()` | same as OJS |

OMP `SeriesForm::execute()` additionally: generates the cover thumbnail into
`<contextFiles>/series/`, and writes category links via
`Repo::section()->removeFromCategory()` / `addToCategory()`.

### 2.5 Templates

| App | Template | Lines |
|---|---|---|
| OMP | `omp-main/templates/controllers/grid/settings/series/form/seriesForm.tpl` | 135 |
| OJS | `ojs-main/templates/controllers/grid/settings/sections/form/sectionForm.tpl` | 86 |
| OPS | `ops-main/templates/controllers/grid/settings/sections/form/sectionForm.tpl` | 85 |

No shared section/series template exists in `lib/pkp/templates/controllers/grid/settings/`
(that dir holds only `genre`, `library`, `reviewForms`, `roles`, `user`). All three form
templates are app-only. Shared includes: `lib/pkp/templates/controllers/fileUploadContainer.tpl`
(OMP only) and `controllers/grid/common/cell/selectStatusCell.tpl` (all three).

App-only template blocks:
- OMP: `FileUploadFormHandler` script wiring plupload → `op="uploadImage"`
  (`seriesForm.tpl:11-32`), `coverImage` form area + thumbnail preview
  (`page="catalog" op="thumbnail" type="series"`) + delete link action,
  `categories[]` checkbox tree with `submission.categories.circularReferenceWarning`,
  ISSN pair (`catalog.manage.series.issn`), sort-option select (`catalog.sortBy`),
  live sample URL for `path` (`grid.series.urlWillBe`, maxlength 32).
- OJS/OPS: `{fbvFormArea id="indexingInfo" title="submission.sectionOptions"}` checkbox
  list, and the plugin hook
  `{call_hook name="Templates::Manager::Sections::SectionForm::AdditionalMetadata" sectionId=$sectionId}`
  — OMP's template declares no equivalent hook.
- The `subEditors` block is near-identical in all three (differs only in the description
  locale key `manager.series.form.assignEditors.description` vs
  `manager.sections.form.assignEditors.description`, plus `|escape` filters present in OJS).

No second Series grid exists in OMP (nothing under `controllers/grid/catalog/`).

**shared base: partial** — the *form* shares a real lib/pkp base
(`PKPSectionForm`, supplying `title` + `subEditors` + CSRF/post validators, and even the
unused-in-OJS/OPS cover-image members); the *grid handler / row / cell provider* share only
the generic `SetupGridHandler` / `GridHandler` / `GridRow` / `GridCellProvider` — there is
no section-specific grid base in lib/pkp, so OMP's, OJS's and OPS's grids are three
parallel app copies of the same shape. Templates are app-only in all three.

- **OMP adds** (vs OJS section form): `prefix`, `subtitle`, `description`, `path`
  (with regex + uniqueness), `image`/`temporaryFileId` + `deleteImage` op, `categories[]`
  + a `categories` grid column, `featured`, `onlineIssn`, `printIssn`, `sortOption`,
  `getPublishChangeEvents()`.
- **OMP lacks** (vs OJS section form): `abbrev`, `policy`, `reviewFormId`, `wordCount`,
  `abstractsNotRequired`, `metaIndexed`, `metaReviewed`, `identifyType`, `hideTitle`,
  `hideAuthor`; the `indexingInfo` form area; the
  `Templates::Manager::Sections::SectionForm::AdditionalMetadata` plugin hook; the
  last-active-section deactivation guard; `getLocaleFieldNames()`; and the
  `setSequence(REALLY_BIG_NUMBER)` + `resequence()` create path.

---

## 3. Where they surface

### 3.0 The shared naming seam

`PKPApplication::getSectionIdPropName(): string` returns `'sectionId'`
(`ojs-main/lib/pkp/classes/core/PKPApplication.php:725`). OJS and OPS do **not** override
it; OMP overrides it to return `'seriesId'`
(`omp-main/classes/core/Application.php:215-218`). This is the single indirection that
lets shared lib/pkp submission/publication code work on both naming conventions. It is
consulted in 14 places, including:
`lib/pkp/classes/publication/Repository.php:850`,
`lib/pkp/classes/submission/Repository.php:329`,
`lib/pkp/classes/context/SubEditorsDAO.php:185`,
`lib/pkp/api/v1/submissions/PKPSubmissionController.php:629,861,873`.

### 3.1 Submission wizard — same base form, different step

All three apps subclass the shared
`PKP\components\forms\submission\StartSubmission` (`FormComponent`):

| App | Picker field | Declared in | Widget |
|---|---|---|---|
| OJS | `sectionId` | `ojs-main/classes/components/forms/submission/StartSubmission.php:36-55` | `FieldOptions` + per-section `FieldHTML` `sectionDescription{id}` shown via `showWhen`; collapsed to `addHiddenField` when only one section |
| OPS | `sectionId` | `ops-main/…/StartSubmission.php:36-55` | identical to OJS |
| OMP | *(none)* — adds `workType` instead | `omp-main/…/StartSubmission.php:29` | — |
| OMP | `seriesId` | `omp-main/classes/components/forms/submission/ForTheEditors.php:25-63` | `FieldOptions` type `radio`, options prefixed with `common.none`, labels from `getLocalizedFullTitle()` |

`ForTheEditors` is a shared lib/pkp form (`lib/pkp/classes/components/forms/submission/ForTheEditors.php:27`,
extends `PKPMetadataForm`). **OMP is the only app that subclasses it**; OJS and OPS use the
lib/pkp class directly (no `classes/components/forms/submission/ForTheEditors.php` in
either). So the series picker sits one wizard step later than the OJS/OPS section picker,
and is optional (`common.none`) where the OJS/OPS one is not.

`ReconfigureSubmission` (`sectionId` FieldOptions) exists in OJS
(`ojs-main/…/ReconfigureSubmission.php:48-64`) and OPS (`ops-main/…:48-64`); OMP has no
counterpart declaring a series field.

### 3.2 Publication tab — no shared base

| App | Form | Chain | Section/series field |
|---|---|---|---|
| OMP | `APP\components\forms\publication\CatalogEntryForm` (`omp-main/…/CatalogEntryForm.php:31`) | `extends FormComponent` | `FieldSelect('seriesId')` (`:84-88`) |
| OJS | `APP\components\forms\publication\IssueEntryForm` (`ojs-main/…/IssueEntryForm.php:27`) | `extends FormComponent` | `FieldSelect('sectionId')` (`:78-83`) |
| OPS | `APP\components\forms\publication\IssueEntryForm` (`ops-main/…:30`) | `extends FormComponent` | `FieldSelect('sectionId')` (`:74-79`) |

`lib/pkp/classes/components/forms/publication/` contains no IssueEntry/CatalogEntry base
(only `ContributorForm`, `Details`, `PKPCitationsForm`, `PKPDataAvailabilityForm`,
`PKPMetadataForm`, `PKPPublicationIdentifiersForm`, `PKPPublicationLicenseForm`,
`TitleAbstractForm`). All three publication-tab forms are parallel app copies extending
the generic `FormComponent`.

### 3.3 Editorial dashboard filter — shared field, OMP omits it

`PKP\components\forms\dashboard\PKPSubmissionFilters::addSectionFields()`
(`lib/pkp/classes/components/forms/dashboard/PKPSubmissionFilters.php:64-84`) declares
`FieldOptions('sectionIds')` labelled with the shared key `section.section` (which OMP
relabels to "Series"), and self-suppresses when the context has exactly one section.

All three apps subclass it (`APP\components\forms\dashboard\SubmissionFilters`), but:
- OJS calls `->addSectionFields()` (`ojs-main/…/SubmissionFilters.php:36`) and adds
  `addIssues()`.
- OPS calls `->addSectionFields()` (`ops-main/…:33`).
- **OMP does not call it** (`omp-main/…/SubmissionFilters.php:29-35` → `addAssignedTo()`,
  `addCategories()`, `addDaysSinceLastActivity()` only), and its constructor does not even
  accept a `$sections` argument. Per rule 8 this is a *missing override* on shared
  machinery, not an app-side replacement.

`ojs-main/api/v1/submissions/SubmissionController.php:57-59` reads a `sectionIds` query
param into the collector; OMP's `SubmissionController` has no equivalent.

### 3.4 Reader front end — both OMP and OPS have browse-by-{series,section}; OJS does not

| App | Route | Handler | Template |
|---|---|---|---|
| OMP | `catalog/series/{path}` | `APP\pages\catalog\CatalogHandler::series()` (`omp-main/pages/catalog/CatalogHandler.php:150-204`), `extends PKPCatalogHandler` | `omp-main/templates/frontend/pages/catalogSeries.tpl` |
| OPS | section browse by `path` | `APP\pages\preprints\SectionsHandler::section()` (`ops-main/pages/preprints/SectionsHandler.php:58-79`), `extends Handler` — matches on `getData('path')` by iteration, not a DAO lookup | `ops-main/templates/frontend/pages/sections.tpl` |
| OJS | — | no section browse page | sections appear only as **grouping headings** inside the issue ToC (`ojs-main/templates/frontend/objects/issue_toc.tpl:127-146`) |

OMP's `series()` uses `Repo::section()->getByPath()` (the OMP-only DAO method),
`$series->getSortOption()` for ordering, `filterBySeriesIds()`, and OMP-only
`FeatureDAO`/`NewReleaseDAO` lookups keyed on `Application::ASSOC_TYPE_SERIES`; it also
fires `UsageEvent(ASSOC_TYPE_SERIES, …)`. `catalogSeries.tpl` renders series cover image
(`catalog/thumbnail?type=series`), description, and online/print ISSN — none of which have
an OJS/OPS counterpart. OPS also lists sections on its index page
(`ops-main/pages/index/IndexHandler.php:70-94`).

### 3.5 OMP-only: series navigation menu item type

`omp-main/classes/services/NavigationMenuService.php` defines `NMI_TYPE_SERIES`
(`:30`), offered only when the press has ≥1 series (`:76`), rendered by the OMP-only
template `omp-main/templates/controllers/grid/navigationMenus/seriesNMIType.tpl`
(`:119`), resolved via `Repo::section()->get()` (`:155`) and URL-built to
`catalog/series/{path}` (`:187`). Handled in
`omp-main/controllers/grid/navigationMenus/form/NavigationMenuItemsForm.php:117`. A 3.5
upgrade migration (`omp-main/classes/migration/upgrade/v3_5_0/I10511_RemoveSeriesMenuItems.php`)
deletes `NMI_TYPE_SERIES` items whose `path` no longer matches a `series_id`. No OJS/OPS
section-based NMI type exists.

**shared base: partial** — the *naming* seam is genuinely shared and parameterized
(`getSectionIdPropName()`, plus `SubEditorsDAO`, `publication/Repository`,
`submission/Repository`, `PKPSubmissionController` all reading through it), and the
submission-wizard `StartSubmission` / `ForTheEditors` bases are shared lib/pkp classes all
three apps extend. But every *surface* that renders the picker or the reader page is an
app-side class with no section-specific lib/pkp base: publication-tab forms are three
parallel `FormComponent` subclasses, and the reader browse pages are app-only
(OMP catalog/series, OPS preprints/sections, OJS none).

- **OMP adds**: series picker in `ForTheEditors` (radio, optional via `common.none`),
  `CatalogEntryForm` `seriesId`, catalog browse-by-series page with cover/ISSN/sort,
  `ASSOC_TYPE_SERIES` feature/new-release/usage-event integration, `NMI_TYPE_SERIES`
  navigation menu item type, `seriesPosition` on the publication.
- **OMP lacks**: the `sectionId` picker in `StartSubmission` step 1 and its per-section
  `sectionDescription` HTML; `ReconfigureSubmission`'s section field; the shared
  `sectionIds` dashboard filter (`addSectionFields()` never called); the
  `sectionIds` submission-API query param; OJS's issue-ToC section grouping.

---

## 4. API layer

### 4.1 Mounting

The shared controller `PKP\API\v1\sections\SectionController extends PKPBaseController`
lives at `ojs-main/lib/pkp/api/v1/sections/SectionController.php:33` — i.e. it is present
in **all three** checkouts (lib/pkp is identical). An endpoint is mounted only when the app
ships `api/v1/<name>/index.php`:

| App | `api/v1/sections/index.php` | Endpoint mounted |
|---|---|---|
| OJS | present (`ojs-main/api/v1/sections/index.php`, 435 bytes: `return new \PKP\handler\APIHandler(new \PKP\API\v1\sections\SectionController());`) | yes |
| OMP | absent | no |
| OPS | absent | **no** |

So `sections` is OJS-only *as mounted*, but the reason is a missing 1-line index.php in
OMP and OPS, not a divergent controller — and OPS is in the same position as OMP here,
despite OPS sections otherwise tracking OJS.

`SectionController` details (`getHandlerPath()` → `sections`;
`getRouteGroupMiddleware()` roles `ROLE_ID_SITE_ADMIN`, `ROLE_ID_MANAGER`):
- `GET ''` → `section.getMany`
- `GET '{sectionId}'` → `section.getSection` (`whereNumber`)

**The endpoint is read-only** — no POST/PUT/DELETE routes. `get()` 404s on
`api.sections.404.sectionNotFound` and 400s on `api.sections.400.contextsNotMatched` when
the section's context differs from the request context; both return
`Repo::section()->getSchemaMap()->map(...)`.

Note: `getMany()` dispatches a `typeIds` query param to `$collector->filterByTypeIds(...)`
(`SectionController.php:131`), but **no `filterByTypeIds()` method is defined** on
`PKP\section\Collector` or on any app's section collector — the branch has no
implementation behind it.

### 4.2 What OMP uses instead for Series CRUD

There is no REST CRUD for sections in *any* app — OJS's mounted endpoint is read-only.
Create/update/delete/reorder happens through the legacy component grid in all three
(see §2.3), via `ROUTE_COMPONENT`:

- OMP: `grid.settings.series.SeriesGridHandler` ops `addSeries`, `editSeries`,
  `updateSeries`, `deleteSeries`, `saveSequence`, `deactivateSeries`, `activateSeries`,
  `deleteImage` — roles `[ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN]`.
- OJS/OPS: `grid.settings.sections.SectionGridHandler` ops `addSection`, `editSection`,
  `updateSection`, `deleteSection`, `saveSequence`, `deactivateSection`,
  `activateSection` — same roles.

### 4.3 Submission/publication API — shared, parameterized

`PKP\API\v1\submissions\PKPSubmissionController` (lib/pkp) is the base for all three apps'
`APP\API\v1\submissions\SubmissionController`. It handles the section/series property
generically through `Application::getSectionIdPropName()`:
- `:629-641` validates the incoming prop: section must exist
  (`api.submission.400.sectionDoesNotExist`), must not be inactive
  (`api.submission.400.inactiveSection`), and enforces `editorRestricted` against
  `PKPSection::getEditorRestrictedRoles()` (`submission.sectionRestrictedToEditors`).
- `:696-698` moves the prop from submission params into publication props.
- `:861-873` re-checks on submit (`submission.wizard.sectionClosed.message`).

All of that runs unchanged on OMP with `seriesId` substituted. App-side additions:
`ojs-main/api/v1/submissions/SubmissionController.php:57-59` (`sectionIds` filter param)
and `:178` (`Repo::section()->get($publication->getData('sectionId'), …)`); OMP's
`SubmissionController` declares neither.

**shared base: yes (controller), no (mounting)** — the section REST controller is a single
shared `PKP\API\v1\sections\SectionController` in lib/pkp with no app subclass anywhere;
only OJS ships the `api/v1/sections/index.php` that mounts it, so OMP *and* OPS have no
sections/series REST endpoint. The submission/publication API is fully shared and
parameterized over `getSectionIdPropName()`.

- **OMP adds** (API layer): nothing — no series REST controller, no OMP api/v1 series dir.
- **OMP lacks** (vs OJS): the mounted `GET /sections` + `GET /sections/{id}` endpoints
  (shared by this gap with OPS), and the `sectionIds` submission-list query param.
- **Neither side has**: REST write operations for sections/series — CRUD is grid-only in
  all three apps.
