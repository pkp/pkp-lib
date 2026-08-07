# Atlas — plugins modality

- **Modality**: plugins (bundled plugin trees)
- **Sweep date**: 2026-07-26
- **Trees swept**: `ojs-main/plugins/*/*`, `omp-main/plugins/*/*`, `ops-main/plugins/*/*`, `ojs-main/lib/pkp/plugins/*/*` (lib/pkp SHA `9db481cf4d`)
- **Globs used**: `ls -d <app>/plugins/*/*` per app; matched across apps by `category/dirName`.
- **Notes**:
  - `lib/pkp/plugins/` holds only base classes for same-named app plugins
    (`generic/usageEvent`, `importexport/native`, `importexport/users`,
    `metadata/dc11`, `oaiMetadataFormats/dc`) — no independent bundled plugins,
    so no separate atoms; each is covered by its app-tree atom below.
  - Skipped: `ojs-main/plugins/generic/backendUiExamplePlugin` — broken symlink
    to a local dev checkout (`/Users/jarda/git/pkp/backendUiExamplePlugin`), not
    a bundled plugin. Skipped: `omp-main/plugins/gateways/README` — a file, not
    a plugin directory (empty category placeholder).
  - Mechanical flags: "settings form" = a `*SettingsForm.php` / schema-based
    `classes/*Settings.php` file exists in the tree. "install default
    enabled=true/false" = declared in the plugin's `settings.xml`. "no
    lazy-load flag" = `version.xml` has no `<lazy-load>` (plugin is always
    loaded; importexport/reports/metadata/oaiMetadataFormats categories and a
    few generics). No liveness judgment.

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| PLUG-001 | OJS, OMP, OPS | blocks/browse | blocks; browse sidebar block (`BrowseBlockPlugin`); no settings form; install default enabled=true declared in OMP only |
| PLUG-002 | OJS, OMP, OPS | blocks/developedBy | blocks; "Developed By" (PKP link) sidebar block (`DevelopedByBlockPlugin`); no settings form; install default enabled=false (all 3 apps) |
| PLUG-003 | OJS, OMP | blocks/information | blocks; information block — For Readers/Authors/Librarians links (`InformationBlockPlugin`); no settings form; install default enabled=true |
| PLUG-004 | OJS, OMP, OPS | blocks/languageToggle | blocks; language selector sidebar block (`LanguageToggleBlockPlugin`); no settings form; install default enabled=true |
| PLUG-005 | OJS, OMP | blocks/makeSubmission | blocks; "Make a Submission" call-to-action block (`MakeSubmissionBlockPlugin`); no settings form; install default enabled=true |
| PLUG-006 | OJS | blocks/subscription | blocks; subscription status/login sidebar block (`SubscriptionBlockPlugin`); no settings form; install default enabled=true |
| PLUG-007 | OJS | generic/announcementFeed | generic; announcement RSS/Atom feeds via block + gateway child plugins (`AnnouncementFeedBlockPlugin`/`AnnouncementFeedGatewayPlugin`); settings form (`AnnouncementFeedSettingsForm`); install default enabled=true |
| PLUG-008 | OJS, OMP, OPS | generic/citationStyleLanguage | generic; CSL "How to Cite" citation display + citation-format downloads (`CitationStyleLanguagePlugin`); settings form (`CitationStyleLanguageSettingsForm`); lazy-load, no install default declared |
| PLUG-009 | OJS, OPS | generic/crossref | generic; Crossref XML metadata export and DOI deposit (`CrossrefExportPlugin`); schema-based settings (`classes/CrossrefSettings.php`); no lazy-load flag |
| PLUG-010 | OJS, OMP, OPS | generic/customBlockManager | generic; create/manage custom sidebar blocks as `CustomBlockPlugin` children (`CustomBlockManagerPlugin`); no settings form (grid UI); acts as site plugin outside a context (`isSitePlugin`) |
| PLUG-011 | OJS | generic/datacite | generic; DataCite export/registration for DOIs (`DataciteExportPlugin`); schema-based settings (`classes/DataciteSettings.php`); no lazy-load flag |
| PLUG-012 | OJS | generic/doaj | generic; DOAJ export plugin (`DOAJExportPlugin`); settings form (`classes/form/DOAJSettingsForm.php`); install default enabled=true |
| PLUG-013 | OJS | generic/driver | generic; DRIVER guidelines compliance for OAI interface (`DRIVERPlugin`); no settings form; lazy-load |
| PLUG-014 | OJS, OMP | generic/dublinCoreMeta | generic; injects Dublin Core meta tags into submission views for indexing (`DublinCoreMetaPlugin`); no settings form; install default enabled=true |
| PLUG-015 | OJS, OMP, OPS | generic/googleAnalytics | generic; Google Analytics tracking code injection (`GoogleAnalyticsPlugin`); settings form (`GoogleAnalyticsSettingsForm`); lazy-load, no install default declared |
| PLUG-016 | OJS, OMP, OPS | generic/googleScholar | generic; injects Google Scholar meta tags into submission views (`GoogleScholarPlugin`); no settings form; install default enabled=true |
| PLUG-017 | OJS | generic/htmlArticleGalley | generic; renders HTML article galleys inline (`HtmlArticleGalleyPlugin`); no settings form; install default enabled=true |
| PLUG-018 | OMP | generic/htmlMonographFile | generic; renders HTML monograph files inline (`HtmlMonographFilePlugin`); no settings form; install default enabled=true |
| PLUG-019 | OJS | generic/jatsTemplate | generic; generates a default JATS XML representation of articles (`JatsTemplatePlugin`); no settings form; install default enabled=true |
| PLUG-020 | OJS | generic/lensGalley | generic; eLife Lens viewer for JATS XML galleys (`LensGalleyPlugin`); no settings form; install default enabled=true |
| PLUG-021 | OPS | generic/orcidProfile | generic; ORCID Profile integration (`OrcidProfilePlugin`); settings form (`classes/form/OrcidProfileSettingsForm.php`); lazy-load (OJS/OMP carry ORCID in core, only OPS bundles the plugin) Claimed by: orcid-integration. |
| PLUG-022 | OJS, OMP, OPS | generic/pdfJsViewer | generic; embeds the pdf.js viewer for PDF display (`PdfJsViewerPlugin`); no settings form; install default enabled=true |
| PLUG-023 | OJS | generic/pflPlugin | generic; Publication Facts Label on article pages (`PflPlugin`); settings form (`PflSettingsForm`); lazy-load, no install default declared |
| PLUG-024 | OPS | generic/preprintToJournal | generic; relay a posted preprint into a journal submission (`PreprintToJournalPlugin`, no docblock brief); no settings form; lazy-load |
| PLUG-025 | OJS | generic/recommendByAuthor | generic; recommends articles by the same author on article pages (`RecommendByAuthorPlugin`); no settings form; lazy-load |
| PLUG-026 | OJS | generic/recommendBySimilarity | generic; recommends similar articles on article pages (`RecommendBySimilarityPlugin`); no settings form; lazy-load |
| PLUG-027 | OJS, OMP | generic/staticPages | generic; static content pages at custom URLs (`StaticPagesPlugin`); no settings form (grid/page editor UI); lazy-load, no install default declared |
| PLUG-028 | OJS, OMP, OPS | generic/tinymce | generic; TinyMCE WYSIWYG editor for textareas (`TinyMCEPlugin`); no settings form; install default enabled=true and `getCanDisable()` returns false (cannot be disabled) |
| PLUG-029 | OJS, OMP, OPS | generic/usageEvent | generic; generates usage events feeding the statistics framework (`UsageEventPlugin`, base in lib/pkp); no settings form; no lazy-load flag; install default enabled=true declared in OJS/OPS |
| PLUG-030 | OJS, OMP, OPS | generic/webFeed | generic; RSS/Atom web feeds via block + gateway child plugins (`WebFeedBlockPlugin`/`WebFeedGatewayPlugin`); settings form (`SettingsForm.php`); install default enabled=true |
| PLUG-031 | OMP | importexport/csv | importexport; CSV import/export of monographs (`CSVImportExportPlugin`); no settings form; no lazy-load flag |
| PLUG-032 | OJS, OMP, OPS | importexport/native | importexport; Native XML import/export of submissions (+issues in OJS) (`NativeImportExportPlugin`, base in lib/pkp); no settings form; no lazy-load flag |
| PLUG-033 | OMP | importexport/onix30 | importexport; ONIX 3.0 XML export of monograph metadata (`Onix30ExportPlugin`); no settings form; no lazy-load flag |
| PLUG-034 | OJS | importexport/pubmed | importexport; PubMed/MEDLINE XML metadata export (`PubMedExportPlugin`); settings form (`PubMedSettingsForm`); no lazy-load flag |
| PLUG-035 | OJS, OMP | importexport/users | importexport; user XML import/export (`UserImportExportPlugin`, base in lib/pkp); no settings form; no lazy-load flag |
| PLUG-036 | OJS, OMP, OPS | metadata/dc11 | metadata; Dublin Core 1.1 metadata schema adapter (`Dc11Plugin`, base in lib/pkp); no settings form; no lazy-load flag |
| PLUG-037 | OJS, OMP, OPS | oaiMetadataFormats/dc | oaiMetadataFormats; oai_dc metadata format for the OAI interface (`OAIMetadataFormatPlugin_DC`, base in lib/pkp); no settings form; no lazy-load flag |
| PLUG-038 | OJS | oaiMetadataFormats/marc | oaiMetadataFormats; MARC metadata format for OAI (`OAIMetadataFormatPlugin_MARC`); no settings form; no lazy-load flag |
| PLUG-039 | OJS | oaiMetadataFormats/marcxml | oaiMetadataFormats; MARC21/MARCXML metadata format for OAI (`OAIMetadataFormatPlugin_MARC21`); no settings form; no lazy-load flag |
| PLUG-040 | OJS | oaiMetadataFormats/oaiJats | oaiMetadataFormats; JATS XML format for OAI (`OAIMetadataFormatPlugin_JATS`); settings form (`OAIJatsSettingsForm`); no lazy-load flag but overrides `getCanEnable`/`getCanDisable` (true) and `getEnabled` from plugin settings |
| PLUG-041 | OJS, OMP | paymethod/manual | paymethod; manual/offline payment method (`ManualPaymentPlugin`); settings fields injected into the payments form (`manualInstructions` textarea); ships mailable + email template; no lazy-load flag |
| PLUG-042 | OJS, OMP | paymethod/paypal | paymethod; PayPal payment method with bundled vendor SDK (`PaypalPaymentPlugin`, `PaypalPaymentForm`); settings fields injected into the payments form; no lazy-load flag |
| PLUG-043 | OJS, OMP | pubIds/urn | pubIds; URN public identifier assignment (`URNPubIdPlugin`); settings form (`classes/form/URNSettingsForm.php` + JS handler); lazy-load, no install default declared |
| PLUG-044 | OJS | reports/articles | reports; article report CSV generator (`ArticleReportPlugin`); no settings form; no lazy-load flag |
| PLUG-045 | OJS | reports/counter | reports; COUNTER report (`CounterReportPlugin`); no settings form; no lazy-load flag |
| PLUG-046 | OMP | reports/monographReport | reports; monograph report — CSV of basic info (title, DOI, …) for all monographs (`MonographReportPlugin`); no settings form; no lazy-load flag |
| PLUG-047 | OJS, OMP | reports/reviewReport | reports; review report CSV generator (`ReviewReportPlugin`); no settings form; no lazy-load flag |
| PLUG-048 | OJS | reports/subscriptions | reports; subscription report CSV generator (`SubscriptionReportPlugin`); no settings form; no lazy-load flag |
| PLUG-049 | OJS, OMP, OPS | themes/default | themes; default frontend theme with theme options (`DefaultThemePlugin`); no settings form (theme options UI); install default enabled=true |
