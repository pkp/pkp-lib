# Atlas — AFFR (reader-facing front-end affordances)

- **Sub-modality**: AFFR — per-screen affordances (visible controls, links, forms) of the reader-facing front end.
- **Sweep date**: 2026-07-26
- **Trees swept**: `ojs-main/`, `omp-main/`, `ops-main/` app checkouts with `lib/pkp` content-identical across them (canonical lib/pkp SHA `ad4606f93e`, per README provenance note).
- **Sources**: `lib/pkp/templates/frontend/**` (shared), `<app>/templates/frontend/**`, `<app>/plugins/blocks/*/templates/block.tpl`, reader-facing generic-plugin templates (`citationStyleLanguage`, `pdfJsViewer`, `htmlArticleGalley`, `lensGalley`, `htmlMonographFile`, `webFeed`, `announcementFeed`, `staticPages`, `customBlockManager`, `pflPlugin`, `recommendByAuthor`, `recommendBySimilarity`, `crossref` crossmark), paymethod plugin frontend templates (`paymethod/manual`), and Vue frontend components in `lib/ui-library/src/frontend/components/` mounted from frontend templates. The default theme (`plugins/themes/default/`) ships **no template overrides** (CSS/JS + theme options only) — options that gate template blocks are recorded as quoted guards.
- **Method**: `find <tree>/templates/frontend -type f`; `find <tree>/plugins/blocks -name '*.tpl'`; `find <tree>/plugins/themes/default -name '*.tpl'` (empty in all three apps); full read of every frontend template; `grep -rn "pkp-cite|pkp-comments|pkp-usage|pkp-crossmark|data-vue-root"` over templates + classes to locate Vue mounts; `diff` of OMP/OPS block and object templates against OJS to collapse identical occurrences; `grep NMI_TYPE_` in `lib/pkp/classes/navigationMenu/NavigationMenuItem.php` for default menu item types.
- **Granularity**: one atom per control/link/form per screen; identical per-app occurrences collapsed with combined `apps:`. Conditions recorded only as quoted mechanical guards. No liveness judgment.
- **Boundary (out of scope here)**: login/registration/lost-password/activation/profile FORMS (`lib/pkp/templates/frontend/pages/userLogin.tpl`, `userRegister.tpl`, `userLostPassword.tpl`, `userConfirmActivation.tpl`, `userRegisterComplete.tpl`, `registrationForm*.tpl`), ORCID `orcidAbout.tpl`/`orcidVerify.tpl` → **AFFU**; backend screens → AFFW/AFFM. Links *to* those screens from the front end are swept here.

## Screen list

Site chrome (header/nav/footer) · shared components (breadcrumbs, pagination, edit link, highlights, notification) · site home (multi-context index) · context home (OJS/OMP/OPS) · announcements listing + detail · about pages (about, masthead, history, submissions, contact, privacy, publishing system, information) · OJS issue current/TOC + archive · article/monograph/preprint summaries in listings · galley link + galley/file viewers · OJS article landing · OMP catalog (index, category, series, new releases) + monograph/chapter landing · OPS preprint list/sections + preprint landing · how-to-cite · search (form + results) · sidebar blocks · custom pages · OJS subscription/payment reader screens · error/message page.

## Atoms

### Site chrome — header & navigation (every frontend page)

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-001 | ojs omp ops | `lib/pkp/templates/frontend/components/header.tpl` · `.pkp_site_name` | Header · site/context logo or title link → context home (`{if $displayPageHeaderLogo}` image link, `{elseif $displayPageHeaderTitle}` text link, else default app logo). |
| AFFR-002 | ojs omp ops | `lib/pkp/templates/frontend/components/header.tpl` · `.pkp_site_nav_toggle` | Header · "Open Menu" button toggling the primary nav on small screens. |
| AFFR-003 | ojs omp ops | `lib/pkp/templates/frontend/components/header.tpl` · `{load_menu name="primary"}` + `lib/pkp/templates/frontend/components/navigationMenu.tpl` | Header · primary navigation menu: nested link list rendered from the context's configured NavigationMenu (items skipped when `!getIsDisplayed()`; children shown when `getIsChildVisible()`). |
| AFFR-004 | ojs | `ojs-main/templates/frontend/components/primaryNavMenu.tpl` | Header · OJS fallback primary menu (no menu configured): Announcements link (`{if $enableAnnouncements}`); Current + Archives links (`{if $currentJournal->getData('publishingMode') != PUBLISHING_MODE_NONE}`); About submenu → about, editorialMasthead, submissions, contact (`{if mailingAddress || contactName}`). |
| AFFR-005 | omp | `omp-main/templates/frontend/components/primaryNavMenu.tpl` | Header · OMP fallback primary menu: Announcements link (`{if $enableAnnouncements}`); Catalog link; About submenu → about, editorialMasthead, submissions, contact (`{if mailingAddress || contactName}`). |
| AFFR-006 | ops | `ops-main/templates/frontend/components/primaryNavMenu.tpl` | Header · OPS fallback primary menu: Announcements link (`{if $enableAnnouncements}`); About submenu → about, editorialMasthead, submissions, contact (`{if mailingAddress || contactName}`). |
| AFFR-007 | ojs omp ops | `lib/pkp/templates/frontend/components/header.tpl` · `.pkp_navigation_search_wrapper` | Header · Search link → `search` page (`{if $currentContext && $requestedPage !== 'search'}`). |
| AFFR-008 | ojs omp ops | `lib/pkp/templates/frontend/components/header.tpl` · `{load_menu name="user"}` + `PKP\navigationMenu\NavigationMenuItem::NMI_TYPE_*` | Header · user menu rendered from the "user" NavigationMenu; default item types: Login (`NMI_TYPE_USER_LOGIN`), Register (`NMI_TYPE_USER_REGISTER`) when logged out; Dashboard (`NMI_TYPE_USER_DASHBOARD`), Profile (`NMI_TYPE_USER_PROFILE`), Administration (`NMI_TYPE_ADMINISTRATION`), Logout (`NMI_TYPE_USER_LOGOUT`), Logout-as (`NMI_TYPE_USER_LOGOUT_AS`) when logged in. |
| AFFR-009 | ojs omp ops | `lib/pkp/templates/frontend/components/navigationMenus/dashboardMenuItem.tpl` · `.task_count` | Header user menu · Dashboard item renders title plus unread notification count badge (`$unreadNotificationCount`). |
| AFFR-010 | ojs omp ops | `<app>/templates/frontend/components/skipLinks.tpl` | Header · skip links: "skip to main", "skip to nav", "skip to footer" anchors; on home page also skip-to-about (`{if $activeTheme->getOption('showDescriptionIn…Index')}`), skip-to-announcements (`{if $numAnnouncementsHomepage && $announcements|@count}`), and OJS-only skip-to-issue (`{if $issue}`). |

### Site chrome — footer & sidebar frame (every frontend page)

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-011 | ojs omp ops | `lib/pkp/templates/frontend/components/footer.tpl` · `Templates::Common::Sidebar` | Footer frame · sidebar region rendered from the sidebar hook (block plugins, AFFR-086…095); suppressed when `{if $isFullWidth}`. |
| AFFR-012 | ojs omp ops | `lib/pkp/templates/frontend/components/footer.tpl` · `.pkp_footer_content` | Footer · context-configured footer HTML (`{if $pageFooter}`) — may contain arbitrary links. |
| AFFR-013 | ojs omp ops | `lib/pkp/templates/frontend/components/footer.tpl` · `.pkp_brand_footer` | Footer · app brand image link → `about/aboutThisPublishingSystem`. |

### Shared components (used across screens)

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-014 | ojs omp ops | `lib/pkp/templates/frontend/components/breadcrumbs.tpl` (+ app variants `breadcrumbs_issue.tpl`, `breadcrumbs_article.tpl` (ojs), `breadcrumbs_preprint.tpl` (ops), shared `breadcrumbs_catalog.tpl`, `breadcrumbs_announcement.tpl`) | All pages · breadcrumb trail links: Home; OJS issue/article variants add Archives + issue link (`{if $issue}`); catalog variant adds parent category/series link (`{if $parent}`); announcement variant adds Announcements link. |
| AFFR-015 | ojs omp ops | `lib/pkp/templates/frontend/components/pagination.tpl` · `.cmp_pagination` | Listing pages · Previous / Next page links (`{if $prevUrl}` / `{if $nextUrl}`) with "X–Y of Z" status. |
| AFFR-016 | ojs omp ops | `lib/pkp/templates/frontend/components/editLink.tpl` · `.cmp_edit_link` | About/announcements/information pages · "Edit" link into management settings (`{if in_array(ROLE_ID_MANAGER, (array) $userRoles)}`). |
| AFFR-017 | ojs omp ops | `lib/pkp/templates/frontend/components/highlights.tpl` · `.swiper` | Site/context home · highlights carousel: per-slide CTA button link (`$highlight->getUrl()` with `getLocalizedUrlText()` label), prev/next buttons, pagination dots (`{if $highlights->count()}` on including pages). |
| AFFR-018 | ojs ops | `<app>/templates/frontend/components/notification.tpl` · `.cmp_notification` | Inline notification banner component (message display; no controls). OMP has no app copy; shared usage via page templates. |

### Site home (multi-context installations)

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-019 | ojs omp ops | `<app>/templates/frontend/pages/indexSite.tpl` · `.journals`/`.presses`/`.servers` list | Site home · per-context entry: thumbnail link (`{if $thumb}`), context name link, "View Journal/Press/Server" link → context home. |
| AFFR-020 | ojs | `ojs-main/templates/frontend/pages/indexSite.tpl` · `li.current` | Site home · per-journal "Current Issue" link → `issue/current`. |
| AFFR-021 | ojs omp ops | `<app>/templates/frontend/pages/indexSite.tpl` · `.about_site` | Site home · site "about" HTML blob (`{if $about}`) — may contain arbitrary links. |

### Context home

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-022 | ojs | `ojs-main/templates/frontend/pages/indexJournal.tpl` · `.current_issue` | Journal home · current-issue section: issue identification + embedded TOC (`issue_toc.tpl`, AFFR-043) and "View All Issues" link → `issue/archive` (`{if $issue}`). |
| AFFR-023 | ojs | `ojs-main/templates/frontend/pages/indexJournal.tpl` · `latest_article.tpl` include | Journal home · "Latest publications" article list with `{page_info}`/`{page_links}` pagination (`{if $publishedPublications && $publishedPublications->count()}`); items are article summaries (AFFR-046). |
| AFFR-024 | ojs | `ojs-main/templates/frontend/components/categoryHeader.tpl` · `.categories_listing` | Journal home · top-level category links → `catalog/category/<path>` (`{if $categories && $categories->count() > 0}`; `{if !$category->getParentId()}`). |
| AFFR-025 | ojs omp ops | `<app>` context home template (`indexJournal.tpl` / `index.tpl` / `indexServer.tpl`) · `.homepage_about` | Context home · about-the-context section (`{if $activeTheme->getOption('showDescriptionInJournalIndex'/'showDescriptionInPressIndex'/'showDescriptionInServerIndex')}`); homepage image (`{if !$activeTheme->getOption('useHomepageImageAsHeader') && $homepageImage}`); additional homepage content HTML (`{if $additionalHomeContent}`). |
| AFFR-026 | omp | `omp-main/templates/frontend/pages/index.tpl` · `monographList.tpl` includes | Press home · "Featured" monograph list (`{if !empty($featuredMonographs)}`) and "New Releases" monograph list (`{if !empty($newReleases)}`); items are monograph summaries (AFFR-071). |
| AFFR-027 | ops | `ops-main/templates/frontend/pages/indexServer.tpl` · `.homepage_latest_preprints` + `archiveHeader.tpl` include | Server home · archive header (search form + category links, AFFR-078) and "Latest preprints" list of preprint summaries (AFFR-081). |

### Announcements (listing, detail, homepage list)

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-028 | ojs omp ops | `lib/pkp/templates/frontend/pages/announcements.tpl` | Announcements page · intro HTML (`$announcementsIntroduction`) + list of announcement summaries; manager Edit link (AFFR-016). |
| AFFR-029 | ojs omp ops | `lib/pkp/templates/frontend/objects/announcement_summary.tpl` | Announcement summary · title link and "Read more" link → `announcement/view/<id>`; image (`{if $announcement->image}`). |
| AFFR-030 | ojs omp ops | `lib/pkp/templates/frontend/objects/announcements_list.tpl` · `#homepageAnnouncements` | Home pages · announcements section (`{if $numAnnouncements && $announcements|@count}`): first item as full summary, subsequent items as title links → `announcement/view/<id>`. |
| AFFR-031 | ojs omp ops | `lib/pkp/templates/frontend/pages/announcement.tpl` + `objects/announcement_full.tpl` | Announcement detail · full announcement display (title, date, image, description); no controls beyond breadcrumb (AFFR-014). |

### About the context

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-032 | ojs omp ops | `lib/pkp/templates/frontend/pages/about.tpl` | About page · context "about" HTML; manager Edit link (AFFR-016). |
| AFFR-033 | ojs omp ops | `lib/pkp/templates/frontend/pages/editorialMasthead.tpl` | Editorial masthead · per-role member listing with ORCID icon links (`{if getData('orcid') && hasVerifiedOrcid()}`, `target="_blank"`); inline link → `about/editorialHistory`; peer reviewers section (`{if $reviewers->count()}`) with ORCID links (`{if getData('orcid') && getData('orcidAccessToken')}`). |
| AFFR-034 | ojs omp ops | `lib/pkp/templates/frontend/pages/editorialHistory.tpl` | Editorial history · per-role past member listing with service dates and ORCID icon links; context `editorialHistory` HTML; manager Edit link. |
| AFFR-035 | ojs omp ops | `lib/pkp/templates/frontend/pages/submissions.tpl` · `.cmp_notification` | About Submissions · action notice: "make a new submission" link → `submission` + "view your pending submissions" link → `submissions` (`{if $isUserLoggedIn}`); Login link + Register link (`{else}`); replaced by not-accepting message (`{if $sections|@count == 0 || $currentContext->getData('disableSubmissions')}`; OMP override checks only `disableSubmissions`). |
| AFFR-036 | ojs omp ops | `lib/pkp/templates/frontend/pages/submissions.tpl` (+ OMP app override) | About Submissions · content sections: author guidelines, submission preparation checklist, copyright notice, privacy statement (each `{if $currentContext->getLocalizedData(...)}`, each with manager Edit link). |
| AFFR-037 | ojs ops | `<app>/templates/frontend/pages/submissions.tpl` · `$submissionChecklistAfterContent` | About Submissions · per-section policy blocks with "make a submission to the X section" link → `submission?sectionId=` (`{if $section->getLocalizedPolicy()}`, `{if $isUserLoggedIn}`). |
| AFFR-038 | ojs omp ops | `lib/pkp/templates/frontend/pages/contact.tpl` | Contact page · mailing address; principal contact + support contact blocks with obfuscated `{mailto}` email links (`{if $contactEmail}` / `{if $supportEmail}`); manager Edit link. |
| AFFR-039 | ojs omp ops | `lib/pkp/templates/frontend/pages/privacy.tpl` | Privacy page · privacy statement HTML (no controls). |
| AFFR-040 | ojs omp ops | `<app>/templates/frontend/pages/aboutThisPublishingSystem.tpl` | About the publishing system · version blurb; the translate key embeds a link to the context contact page (`{if $currentContext}` variant passes `contactUrl`). |
| AFFR-041 | ojs omp | `lib/pkp/templates/frontend/pages/information.tpl` (handlers `APP\pages\information\InformationHandler`) | Information page (For Readers/Authors/Librarians) · content HTML + manager Edit link; OPS has no `pages/information` dispatcher. |

### OJS issue pages

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-042 | ojs | `ojs-main/templates/frontend/pages/issue.tpl` | Issue landing / Current · issue TOC (AFFR-043); "no current issue" heading + warning notice (`{if !$issue}`). |
| AFFR-043 | ojs | `ojs-main/templates/frontend/objects/issue_toc.tpl` | Issue TOC · preview warning (`{if !$issue->getPublished()}`); cover image; description; pubId resolving links + DOI link (`{if $doiObject}`); published date (`{if $includeIssuePublishDate}`); "Full Issue" galley links (`{if $issueGalleys}`, purchase fee params passed); per-section article summary lists. |
| AFFR-044 | ojs | `ojs-main/templates/frontend/pages/issueArchive.tpl` | Issue archive · list of issue summaries + Previous/Next pagination (AFFR-015). |
| AFFR-045 | ojs | `ojs-main/templates/frontend/objects/issue_summary.tpl` | Issue summary · cover image link and title link → `issue/view/<bestId>`; description. |

### Submission summaries in listings (TOC, home, search, category)

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-046 | ojs | `ojs-main/templates/frontend/objects/article_summary.tpl` | Article summary · cover image link + title/subtitle link → `article/view/<path>`; authors (`{if (!$section.hideAuthor && hideAuthor == AUTHOR_TOC_DEFAULT) || hideAuthor == AUTHOR_TOC_SHOW}`), pages, date published (`{if $showDatePublished}`); galley links per galley (`{if !$hideGalleys}`, open-access override `{if publishingMode == PUBLISHING_MODE_OPEN || accessStatus == ARTICLE_ACCESS_OPEN}`); hook `Templates::Issue::Issue::Article`. |
| AFFR-047 | ojs ops | `<app>/templates/frontend/objects/galley_link.tpl` · `.obj_galley_link` | Galley link · view/download link → `article|issue|preprint /view/<path…galleyId>` (versioned path when `{if $publication->getId() !== …currentPublicationId}`); `restricted` class + screen-reader "subscription/fee access" text (`{if !$hasAccess}` with `{if $restrictOnlyPdf && $type=="pdf"}` narrowing); OJS purchase price label (`{if $restricted && $purchaseFee && $purchaseCurrency}`). |

### Galley / file viewers (plugins)

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-048 | ojs omp ops | `plugins/generic/pdfJsViewer/templates/display.tpl` · `header.header_view` | PDF galley viewer · return link + title link → parent landing page; "Download" button (`href=$pdfUrl download`); embedded pdf.js iframe; old-version notice bar (`{if !$isLatestPublication}`). |
| AFFR-049 | ojs | `plugins/generic/htmlArticleGalley/templates/display.tpl` | HTML galley viewer · return link + title link → article page; iframe loading `article/download/...?inline=true`; outdated-version notice with link to current version (`{if !$isLatestPublication}`). |
| AFFR-050 | ojs | `plugins/generic/lensGalley/templates/articleGalley.tpl`, `issueGalley.tpl` | Lens XML galley viewer for article and full-issue XML galleys (eLife Lens reader UI). |
| AFFR-051 | omp | `omp-main/plugins/generic/htmlMonographFile/` templates | HTML monograph file viewer (OMP counterpart of AFFR-049). |

### OJS article landing page

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-052 | ojs omp ops | `<app>` details object (`article_details.tpl` / `monograph_full.tpl` / `preprint_details.tpl`) · `.cmp_notification.notice` | Landing pages · preview notice with link to workflow (`{if status !== STATUS_PUBLISHED}`, url `dashboard/editorial?workflowSubmissionId=`); outdated-version notice with link to latest version (`{elseif $currentPublication->getId() !== $publication->getId()}`). |
| AFFR-053 | ojs omp ops | `<app>` details/authors templates (`article_details.tpl` `.item.authors`; `omp components/authors.tpl`; `preprint_details.tpl`) | Landing pages · author list: affiliation ROR icon links (`{if $affiliation->getRor()}`), ORCID links with verified/unverified icon (`{if $author->getData('orcid')}`, `target="_blank"`), contributor role + CRediT role labels. |
| AFFR-054 | ojs omp ops | `<app>` details object · `.item.doi` | Landing pages · DOI resolving link (`{if $doiObject}` / `{if $monographDoiObject}` / OPS `{if $doiObject}`). |
| AFFR-055 | ojs omp ops | `<app>` details object · `.item.keywords` / `.item.abstract` | Landing pages · keywords list, abstract, plain-language summary (each `{if $publication->getLocalizedData(...)}`) — display sections, no controls. |
| AFFR-056 | ojs omp ops | `<app>` details object · `.downloads_chart` `canvas.usageStatsGraph` | Landing pages · monthly downloads usage chart (`{if $activeTheme && $activeTheme->getOption('displayStats') != 'none'}`); Vue store `lib/ui-library/src/frontend/components/PkpUsageChart/PkpUsageChart.vue`. |
| AFFR-057 | ojs omp ops | `<app>` details object · `.item.references` | Landing pages · references list with linked citations (`getRawCitationWithLinks()`; OJS/OPS hook `Templates::Article|Preprint::Details::Reference` per citation). |
| AFFR-058 | ojs | `ojs-main/templates/frontend/objects/article_details.tpl` · `#public-comments` `<pkp-comments>` + `<pkp-scroll-to-comments>` | Article landing · public comments Vue app (`{if $enablePublicComments}`): "login to comment" button (logged out; `PkpCommentsLogInto.vue`), new-comment textarea + submit button (`PkpCommentsNew*.vue`), "show more" button (`PkpCommentsShowMore.vue`), per-comment actions dropdown (`PkpCommentsMessageActions.vue`), report dialog with reason input (`PkpCommentReportDialog*.vue`); sidebar scroll-to-comments link (`PkpScrollToComments.vue`). |
| AFFR-059 | ojs ops | `<app>` details object · `.entry_details .item.galleys` | Landing pages · primary galley links list and supplementary/additional-files galley links list (`{if $primaryGalleys}` / `{if $supplementaryGalleys}`), each a galley link (AFFR-047); OJS passes purchase fee params. |
| AFFR-060 | ojs | `ojs-main/templates/frontend/objects/article_details.tpl` · `.item.jats` | Article landing · "Download JATS XML" link (`{if isset($jatsDownloadUrl)}`). |
| AFFR-061 | ojs omp ops | `<app>` details object · `.sub_item.versions` | Landing pages · published-versions list with links to current and older versions (`article|catalog/book|preprint /view … /version/<id>`). |
| AFFR-062 | ojs | `ojs-main/templates/frontend/objects/article_details.tpl` · `.item.issue` + `.item.cover_image` | Article landing · issue link → `issue/view/<bestId>` (both title link and issue cover-image link `{if $issue…getLocalizedCoverImage()}`); section name; category links → `catalog/category/<path>` (`{if $categories}`); article number. |
| AFFR-063 | ojs omp ops | `<app>` details object · `.item.funders` `#funding-data` | Landing pages · funders list with ROR links (`{if $funder->ror}`) and grant DOI links (`{if $grant.grantDoi}`); data-availability and funding-statement display sections (`{if $publication->getLocalizedData(...)}`). |
| AFFR-064 | ojs omp ops | `<app>` details object · `.item.pubid` | Landing pages · non-DOI pubId resolving links from pubId plugins (`{foreach from=$pubIdPlugins}`, `{if $pubId}`). |
| AFFR-065 | ojs omp ops | `<app>` details object · `.item.copyright`/`.item.license` | Landing pages · license block: CC badge (`{if $ccLicenseBadge}`) or license URL link, copyright statement, context license terms (`{if licenseTerms || licenseUrl}`). |
| AFFR-066 | ojs omp ops | `plugins/generic/citationStyleLanguage/templates/citation-block.blade` (mounted via hooks `Templates::Article::Details`, `Templates::Catalog::Book::Details`, `Templates::Catalog::Chapter::Details`) | How-to-cite (OJS/OMP; plugin) · primary citation display (`#citationOutput`), "Cite" formats dropdown button (`data-csl-dropdown`), per-style AJAX links (`citationstylelanguage/get/<style>`, `data-load-citation`), download-citation links (`citationstylelanguage/download/<id>`). OPS inline equivalent is AFFR-078. (New Vue `PkpCite` component exists in ui-library but greps to no template/PHP mount.) |
| AFFR-067 | ojs | `plugins/generic/pflPlugin/templates/pfl.tpl` · `<publication-facts-label>` | Article landing · Publication Facts Label web-component panel (expandable facts UI; data injected via JS; no template links). |
| AFFR-068 | ojs | `plugins/generic/recommendByAuthor/templates/articleFooter.tpl`, `recommendBySimilarity/templates/articleFooter.tpl` (hook `Templates::Article::Footer::PageFooter`) | Article landing footer · recommended-article lists: article title links and issue links (`{if $articlesBySameAuthor->count()}`; similarity variant analogous). |
| AFFR-069 | ojs omp ops | `plugins/generic/crossref/templates/crossmarkButton.tpl` · `<pkp-crossmark-button>` | Landing pages · Crossmark status button (Vue `PkpCrossmarkButton.vue`; injected by `CrossrefPlugin` when configured). |

### OMP catalog

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-070 | omp | `omp-main/templates/frontend/pages/catalog.tpl` | Catalog index · series nav links → `catalog/series/<path>` (`{if $activeTheme->getOption('showCatalogSeriesListing') && $contextSeries|@count > 1}`); monograph list; Previous/Next pagination. |
| AFFR-071 | omp | `omp-main/templates/frontend/objects/monograph_summary.tpl` | Monograph summary · cover image link and title link → `catalog/book/<bestId>`; author string; date. |
| AFFR-072 | ojs omp ops | `<app>/templates/frontend/pages/catalogCategory.tpl` | Category page · full-size category image link (`{if $image}`, op `fullSize`); subcategory links (`{if $subcategories|@count}`); item list (article/monograph/preprint summaries); pagination (`{page_links}` OJS/OPS; prev/next OMP); OMP adds "New Releases" list (`{if !empty($newReleasesMonographs)}`). OJS reaches it via `catalog/category`, OPS via `preprints/category`. |
| AFFR-073 | omp | `omp-main/templates/frontend/pages/catalogSeries.tpl` | Series page · series image, description, online/print ISSN display; new-releases list; monograph list; Previous/Next pagination. |
| AFFR-074 | omp | `omp-main/templates/frontend/pages/catalogNewReleases.tpl` | New releases page · monograph list (no pagination). |
| AFFR-075 | omp | `omp-main/templates/frontend/objects/monograph_full.tpl` (via `pages/book.tpl`) | Monograph landing · chapters list: chapter title links (`{if $chapter->isPageEnabled()}`, versioned when older publication), chapter DOI links, per-chapter file download links; series link → `catalog/series/<path>`; category links → `catalog/category/<path>`; publication-format detail blocks (identification codes, publication dates, pubIds, format DOI links, physical dimensions, `{if $publicationFormat->getIsApproved()}`); hooks `Templates::Catalog::Book::Main/Details`. |
| AFFR-076 | omp | `omp-main/templates/frontend/components/publicationFormats.tpl` + `components/downloadLink.tpl` | Monograph/chapter landing · download affordances: remote-format external link (`{if $format->getData('urlRemote') && !$isChapterRequest}`, `target="_blank"`); per-file download link → `catalog/view/<bookId>/<formatId>/<fileId>` (versioned variant), label switches to "Purchase … (amount currency)" (`{if $downloadFile->getDirectSalesPrice() && $currency}`). |
| AFFR-077 | omp | `omp-main/templates/frontend/objects/chapter.tpl` (via `pages/book.tpl` `{if $isChapterRequest}`) | Chapter landing · chapter file download links; cover-image link and "Volume" title link back to the book (versioned variants); versions list with chapter-aware links; series link + ISSNs; category links; license/CC badge; hooks `Templates::Catalog::Chapter::Main/Details`. |

### OPS preprint list & landing

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-078 | ops | `ops-main/templates/frontend/components/archiveHeader.tpl` + `searchForm_archive.tpl` | Preprint list/home · inline search form (query input + Search submit → `search/search`; form suppressed `{if publishingMode == PUBLISHING_MODE_NONE}`) and top-level category links → `preprints/category/<path>`. |
| AFFR-079 | ops | `ops-main/templates/frontend/pages/preprints.tpl` | Preprint archive page ("Archives") · archive header (AFFR-078); preprint summary list; Previous/Next pagination. |
| AFFR-080 | ops | `ops-main/templates/frontend/pages/sections.tpl` | Section page · section description; preprint summary list; Previous/Next pagination (`preprints/section/<path>/<page>`). |
| AFFR-081 | ops | `ops-main/templates/frontend/objects/preprint_summary.tpl` | Preprint summary · cover image link + title/subtitle link → `preprint/view/<bestId>`; author string (hideAuthor guards as AFFR-046); DOI resolving link; keyword list; downloads count / submitted-posted dates / versions count line; galley links (`{if !$hideGalleys}`, open when `{if publishingMode == PUBLISHING_MODE_OPEN}`); hook `Templates::Archive::Preprint`. |
| AFFR-082 | ops | `ops-main/templates/frontend/objects/preprint_details.tpl` (via `pages/preprint.tpl`) | Preprint landing · OPS-specific top matter: published-relation notice with VOR DOI link (`{if relationStatus == PUBLICATION_RELATION_PUBLISHED}`, `{if vorDoi}`); "Preprint / Version X" label line; category links → `preprints/category/<path>`; inline how-to-cite block (`{if $citation}`: `#citationOutput`, formats dropdown button, per-style `citationstylelanguage/get` links, download links); shared sections per AFFR-052…065; hooks `Templates::Preprint::Main/Details`. |

### Search

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-083 | ojs ops | `<app>/templates/frontend/pages/search.tpl` · `form.cmp_form` | Search page · GET search form: query text input, advanced date-from/date-to selects (`{html_select_date_a11y}`), context select (`{if $searchableContexts}`; OJS only — OPS form omits it), hook `Templates::Search::SearchResults::AdditionalFilters`, Search submit button. |
| AFFR-084 | ojs ops | `<app>/templates/frontend/pages/search.tpl` · `.search_results` | Search results · result list (article/preprint summaries with `showDatePublished=true hideGalleys=true`), result-count status, no-results notice, `{page_info}`/`{page_links}` pagination, hook `Templates::Search::SearchResults::PreResults`. |
| AFFR-085 | omp | `omp-main/templates/frontend/pages/search.tpl` + `components/searchForm_simple.tpl` | OMP search page · results (monograph summaries) with found/none status and "search again" anchor link; simple search form at page foot (query input + Search submit); `{page_info}`/`{page_links}` pagination. |

### Sidebar blocks (rendered via `Templates::Common::Sidebar`, AFFR-011)

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-086 | ojs ops | `<app>/plugins/blocks/browse/templates/block.tpl` (identical files) | Browse block · nested category links → `<catalogPage>/category/<path>` with `current` highlight (`{if $browseCategories}`). |
| AFFR-087 | omp | `omp-main/plugins/blocks/browse/templates/block.tpl` | Browse block (OMP) · "New Releases" link → `catalog/newReleases` (`{if $browseNewReleases}`); category links (`{if $browseCategories}`); series links (`{if $browseSeries}`, `{if !$browseSeriesItem->getIsInactive()}`); options set in `templates/settingsForm.tpl` checkboxes. |
| AFFR-088 | ojs omp | `<app>/plugins/blocks/information/templates/block.tpl` | Information block · "For Readers" / "For Authors" / "For Librarians" links → `information/readers|authors|librarians` (each `{if !empty($forReaders|$forAuthors|$forLibrarians)}`). |
| AFFR-089 | ojs omp ops | `<app>/plugins/blocks/languageToggle/templates/block.tpl` | Language block · one link per enabled locale → `user/setLocale/<locale>?source=…` with `current` marking (`{if $enableLanguageToggle}`). |
| AFFR-090 | ojs | `ojs-main/plugins/blocks/subscription/templates/block.tpl` | Subscription block · status display (institutional provider/IP, individual type + expiry, awaiting-payment states); "My Subscriptions" link → `user/subscriptions` (`{if $institutionalSubscription || $individualSubscription}`); "learn more" link → `about/subscriptions` (else branch); login prompt (`{elseif !$userLoggedIn}`). |
| AFFR-091 | ojs omp ops | `<app>/plugins/blocks/developedBy/templates/block.tpl` | Developed-by block · external link to pkp.sfu.ca app page (per-app URL). |
| AFFR-092 | ojs omp | `<app>/plugins/blocks/makeSubmission/templates/block.tpl` | Make-a-Submission block · link → `about/submissions`. |
| AFFR-093 | ojs omp ops | `plugins/generic/customBlockManager/templates/block.tpl` | Custom block · manager-defined HTML content (arbitrary links), optional visible title (`{if !$showName}` hides it). |
| AFFR-094 | ojs omp ops | `plugins/generic/webFeed/templates/block.tpl` | Web-feed block · Atom / RSS 2.0 / RSS 1.0 image links → `gateway/plugin/WebFeedGatewayPlugin/<format>`. |
| AFFR-095 | ojs | `ojs-main/plugins/generic/announcementFeed/templates/block.tpl` | Announcement-feed block · Atom / RSS 2.0 / RSS 1.0 image links → `gateway/plugin/AnnouncementFeedGatewayPlugin/<format>`. |

### Custom pages

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-096 | ojs omp ops | `lib/pkp/templates/frontend/pages/navigationMenuItemViewContent.tpl` | Custom navigation-menu-item page · manager-authored HTML content (arbitrary links). |
| AFFR-097 | ojs omp | `plugins/generic/staticPages/templates/content.tpl` | Static page (staticPages plugin) · manager-authored HTML content (arbitrary links). |
| AFFR-098 | ojs omp ops | `lib/pkp/templates/frontend/pages/error.tpl`, `message.tpl` | Error / message page · single back link (`{$backLink}` with `$backLinkLabel`). |

### OJS subscriptions & reader payments

| ID | Apps | Pointer | Description |
|----|------|---------|-------------|
| AFFR-099 | ojs | `ojs-main/templates/frontend/pages/subscriptions.tpl` (op `about/subscriptions`) | Subscriptions page · individual and institutional subscription-type tables (name/format/duration/cost); "Purchase New Subscription" link per table → `user/purchaseSubscription/individual|institutional` (`{if $isUserLoggedIn}`). |
| AFFR-100 | ojs | `ojs-main/templates/frontend/components/subscriptionContact.tpl` | Subscriptions/My-subscriptions pages · subscriptions contact block: additional info HTML, contact name/address/phone, `mailto:` email link (`{if $subscriptionEmail}`). |
| AFFR-101 | ojs | `ojs-main/templates/frontend/pages/userSubscriptions.tpl` (op `user/subscriptions`) | My Subscriptions · payment-status legend table (`{if $paymentsEnabled}`); per-subscription row with status text and action buttons: "Purchase" to complete (`{if $subscriptionStatus == SUBSCRIPTION_STATUS_AWAITING_ONLINE_PAYMENT}` → `completePurchaseSubscription`), "Renew" (`{elseif … STATUS_ACTIVE}` and `{if !$isNonExpiring}` → `payRenewSubscription`), "Purchase" (→ `purchaseSubscription`); "Purchase New Subscription" link (`{elseif $paymentsEnabled}`); "view subscription types" link → `about/subscriptions#subscriptionTypes` (`{else}`). |
| AFFR-102 | ojs | `ojs-main/templates/frontend/pages/purchaseIndividualSubscription.tpl` · `form#subscriptionForm` | Purchase individual subscription · POST form → `user/payPurchaseSubscription/individual/<id>`: subscription-type select, membership input, Save submit. |
| AFFR-103 | ojs | `ojs-main/templates/frontend/pages/purchaseInstitutionalSubscription.tpl` · `form#subscriptionForm` | Purchase institutional subscription · POST form → `user/payPurchaseSubscription/institutional[/<id>]`: type select (required), membership input, institution name input, mailing-address textarea, domain input, IP-ranges textarea, Continue submit, Cancel link → `user/subscriptions`. |
| AFFR-104 | ojs | `ojs-main/plugins/paymethod/manual/templates/paymentForm.tpl` | Manual payment page · purchase summary table (item, fee `{if $itemAmount}`), manual instructions, "notify of payment" button link → `payment/plugin/ManualPayment/notify/<queuedPaymentId>`. (PayPal paymethod ships no frontend template — external redirect.) |

## Not swept here (boundary pointers)

- Login / registration / lost-password / activation / profile forms, ORCID about+verify pages: **AFFU** (`lib/pkp/templates/frontend/pages/userLogin.tpl`, `userRegister.tpl`, `userLostPassword.tpl`, `userConfirmActivation.tpl`, `userRegisterComplete.tpl`, `registrationForm.tpl`, `registrationFormContexts.tpl`, `orcidAbout.tpl`, `orcidVerify.tpl`).
- Submission wizard and dashboards reached by links recorded above: **AFFW**.
- Metadata-only head/output surfaces with no visible control (`headerHead.tpl`, `googleScholar`, `dublinCoreMeta`, feed XML templates `atom.tpl`/`rss.tpl`/`rss2.tpl`, sitemap, OAI): entry points live in the routes/plugins sweeps; no AFFR atoms.
- `PkpCite`, `PkpOpenReview`, `PkpOrcidDisplay` Vue frontend components exist in `lib/ui-library/src/frontend/components/` but grep to **no template or PHP mount** in any app tree — recorded here as a mechanical fact only (Phase 1 resolves; `CiteComponent.php` config class is likewise unreferenced).
