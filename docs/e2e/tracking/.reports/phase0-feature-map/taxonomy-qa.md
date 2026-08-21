# Feature taxonomy draft — QA test-plan lens

- **Author lens**: a QA lead drafting the master manual test plan for OJS/OMP/OPS from
  scratch; chapters and sub-chapters ARE the features. Grouping litmus applied per
  CHARTER: rules tested together in one sitting (same fixtures, same screens, same
  mental model) share a feature; different fixtures or a different mental model split.
- **Inputs**: `atlas/*.md` (all 14 sweep files, canonical SHA `ad4606f93e`) + CHARTER,
  RUNBOOK, TEMPLATE rules 8–10, APP-GLOSSARY, atlas README (incl. the cli ruling).
  Built blind per RUNBOOK "Rebuilding a feature from scratch" — no prior FEATURE-MAP
  consulted.
- **Date**: 2026-07-27. Draft for the disagreement register / maintainer review;
  representative atom IDs are samples spanning modalities, NOT a crosswalk.
- **Tier bounds** (RUNBOOK Budget & ceilings): H 10–13, M 6–8, L 3–4 common scenarios.
- **Tally**: 65 features — 13 H · 43 M · 9 L. Estimated common-scenario load
  ≈ 480 per app, inside the ≤700/app ceiling before app-specific additions.
  (D6 is a pointer stub to F1, not counted.)

Conventions: apps braces = apps that have the feature ({OJS OMP} etc.). "ref-only"
after a CLI atom = claimable as reference only, per the cli ruling (never seeds
scenarios). Cross-ownership follows TEMPLATE rule 8 (mechanics in the mechanism's
home feature; context features own deltas) and rule 9 (one shared workflow screen,
author included).

---

## A. Reader-facing front end

### A1. Site front end & chrome
- **Intent**: a reader lands anywhere on the site and can orient — header, menus, footer, sidebar blocks, highlights, home pages.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFR-001, AFFR-008, AFFR-010, AFFR-017, AFFR-019, AFFR-025, AFFR-089, AFFR-093, PLUG-004, PLUG-010, PLUG-002, VUE-097, API-022, ROUTE-038, GRID-064
- **Note**: owns the theme-option toggles that only change what the front pages show (homepage image, description display) — see Settings decisions below. Highlights (VUE-097/API-022) live here because a tester exercises them on the home page.

### A2. Navigation menus & custom pages
- **Intent**: a manager shapes the site's navigation and publishes custom content pages (menu items, remote links, static pages).
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-037, GRID-038, API-028, VUE-066, ROUTE-019, AFFR-003, AFFR-096, AFFR-097, PLUG-027, SET-017, SET-018
- **Note**: staticPages plugin {OJS OMP} folded in — same user intent ("manager-authored page at a custom URL"), same sitting. VUE-042 (unmounted NavigationMenuManager) parked in UNASSIGNED.

### A3. Context identity & about pages
- **Intent**: a manager describes the journal (masthead, contact, policies) and readers see it on the About pages.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-001, ROUTE-001, ROUTE-002, ROUTE-039, AFFR-016, AFFR-032, AFFR-033, AFFR-034, AFFR-038, AFFR-041, API-013, SET-006, PLUG-003
- **Note**: merges Settings > Journal/Press/Server with the About pages that render those fields — QA tests them as one edit-then-view sitting. Information pages (For Readers/Authors/Librarians) are {OJS OMP} inline divergences. Masthead page content is a delta from Roles (A-glossary masthead flags, → C6).

### A4. Announcements
- **Intent**: a manager posts news; readers browse it; subscribed users get notified.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-004, AFFR-029, AFFR-030, AFFR-031, API-008, VUE-092, GRID-007, AFFM-129, MAIL-001, JOB-014, NOTIF-008, SET-002
- **Note**: announcement types grid included; the announcement RSS feed lives in A8 (same gateway mechanism as web feeds).

### A5. Article landing page & galley viewing
- **Intent**: a reader opens a published article/preprint and reads or downloads its files, metadata, citation, license and versions.
- **Apps**: {OJS OPS} · **Tier**: H
- **Atoms**: ROUTE-033, ROUTE-080, AFFR-046, AFFR-047, AFFR-048, AFFR-052, AFFR-059, AFFR-061, AFFR-065, AFFR-066, AFFR-082, PLUG-008, PLUG-022, PLUG-017, PLUG-023
- **Note**: includes viewer plugins (pdf.js, HTML, Lens), how-to-cite (CSL), PFL, recommend-* footers, Crossmark. OMP's monograph/chapter landing is a catalog surface → out-of-scope list. Version notices/preview notices are owned here (screen ownership rule); publishing transitions live in D8.

### A6. Issues — reader view (current, TOC, archive)
- **Intent**: a reader browses the current issue, an issue's table of contents, and the back-issue archive.
- **Apps**: {OJS} · **Tier**: M
- **Atoms**: ROUTE-040, AFFR-020, AFFR-022, AFFR-042, AFFR-043, AFFR-044, AFFR-045, AFFR-062, SET-031, API-053
- **Note**: issue *management* is D9. Access-restricted galley behavior on the TOC defers to F2 (subscriptions) for the who-may.

### A7. Sections & section browse
- **Intent**: a manager defines the content groupings submissions land in (sections; OMP series per glossary) and readers browse them where the app exposes browse pages.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-079, GRID-106, GRID-096, API-034, SET-023, SET-033, SET-038, SET-044, ROUTE-081, ROUTE-082, AFFR-079, AFFR-080, AFFR-037
- **Note**: hard call — OMP series settings covered via glossary vocabulary (section→series), but the OMP reader-facing series page is a catalog surface → out-of-scope list. OPS preprints-archive + section pages treated as this feature's OPS browse instantiation (glossary maps Archive→Preprints archive listing as vocabulary, not a counterpart feature).

### A8. Web & announcement feeds
- **Intent**: a reader subscribes to RSS/Atom feeds of published content and announcements.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: PLUG-030, PLUG-007, AFFR-094, AFFR-095, ROUTE-037, ROUTE-059, ROUTE-076
- **Note**: announcementFeed is {OJS}-only inline divergence; grouped with webFeed — identical mechanism (block + gateway XML), one sitting.

### A9. Categories & category browse
- **Intent**: a manager builds a category tree and readers browse content by category.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: VUE-030, VUE-054, API-010, SET-004, ROUTE-006, AFFR-024, AFFR-072, AFFR-086, PLUG-001
- **Note**: category assignment during submission/publication is a delta owned by the wizard (C1) / metadata tab (E1) respectively, pointing here for the tree mechanics.

### A10. Site search
- **Intent**: a reader searches published content and gets ranked, filterable results.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-024, ROUTE-048, ROUTE-083, AFFR-007, AFFR-078, AFFR-083, AFFR-084, AFFR-085, JOB-027, SET-054, CLI-029 (ref-only)
- **Note**: indexing side-effect (index on publish, deindex on unpublish) asserted here; test-env fact: PDF fulltext not indexed in the test environment.

### A11. Reader comments & moderation
- **Intent**: readers comment on published articles; the team moderates and handles reports.
- **Apps**: {OJS} · **Tier**: M
- **Atoms**: API-012, VUE-010, VUE-082, VUE-083, AFFR-058, NOTIF-052, NOTIF-053, ROUTE-017 (userComments op), AFFM-141
- **Note**: boundary unsure — the moderation page + API are shared lib/pkp (routes mount in all apps) but the reader-side comments component greps to an OJS-only mount; drafted {OJS}, Phase 1 probes OMP/OPS reachability.

### A12. OAI-PMH interface
- **Intent**: a harvester queries the OAI endpoint and receives valid records in each supported metadata format.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-044, ROUTE-064, ROUTE-079, PLUG-036, PLUG-037, PLUG-038, PLUG-039, PLUG-040, PLUG-013, SET-055
- **Note**: distinct mental model (protocol responses, not screens); MARC/MARCXML/JATS formats and DRIVER are {OJS} inline divergences.

### A13. Search-engine metadata & analytics
- **Intent**: the site exposes sitemaps, indexing meta tags and analytics hooks so third parties can find and measure content.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: ROUTE-025, ROUTE-049, ROUTE-068, ROUTE-084, PLUG-014, PLUG-016, PLUG-015
- **Note**: slight heterogeneity (sitemap + meta tags + GA) accepted to avoid three sub-L fragments; all are "invisible head/output surfaces verified by page-source assertions" — one sitting, one mental model.

---

## B. Accounts & identity

### B1. Login & sessions
- **Intent**: a user signs in and out, and privileged flows re-confirm identity (incl. administrator "log in as").
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-016, AFFU-001, AFFU-044, AFFU-049, ROUTE-003 (confirmAccess ops), SET-052, SET-057
- **Note**: owns the confirm-password gate and login-forced password change; captcha-on-login is a settings delta here.

### B2. Registration & account validation
- **Intent**: a visitor registers, validates their email, and lands with the right starting roles.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-030, AFFU-009, AFFU-031, MAIL-058, MAIL-059, JOB-053, SET-053, SET-057
- **Note**: reviewer-interest capture at registration is {OJS OMP} (API-048 delta). Unvalidated-user expiry job asserted here.

### B3. Password management & recovery
- **Intent**: a user resets a forgotten password or changes their current one safely.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFU-036, AFFU-040, AFFU-089, MAIL-030, ROUTE-016 (lostPassword/reset ops), GRID-065 (changePassword ops)
- **Note**: profile Password tab mechanics owned here; B4 links.

### B4. User profile
- **Intent**: a user maintains their own identity, contact, roles, public profile, notification preferences and API key.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-029, GRID-065, AFFU-053, AFFU-061, AFFU-069, AFFU-078, AFFU-084, AFFU-093, AFFU-096, SET-027, API-047 (masthead op)
- **Note**: the Notifications tab is the settings surface FOR B6 (owned here as a tab, behavior asserted in B6); the Roles tab self-service is a delta pointing to C6.

### B5. ORCID integration
- **Intent**: authors/reviewers verify their ORCID iD and the app displays it and deposits works/reviews to their record.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-021, API-029, AFFU-099, AFFU-104, AFFU-106, JOB-017, JOB-018, JOB-019, JOB-020, MAIL-027, MAIL-028, MAIL-029, PLUG-021, JOB-033, JOB-034
- **Note**: mechanism home (OAuth verify, deposit jobs, emails). Contributor-side request buttons (E2), reviewer deposits {OJS} and OPS plugin packaging are deltas/divergences. Kept whole rather than scattered: one OAuth fixture, one sitting.

### B6. Notifications center & email preferences
- **Intent**: a user sees in-app notifications/tasks and controls which notification emails they receive (incl. one-click unsubscribe).
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-039, GRID-040, ROUTE-020, AFFU-111, AFFU-118, GRID-064 (tasks op), NOTIF-001
- **Note**: owns the notification framework's user-facing behavior (unread counts, mark read, unsubscribe); each producing feature asserts only that its own notification arrives.

---

## C. Submitting & dashboards

### C1. Submission wizard
- **Intent**: an author starts, completes, saves-for-later and submits a new submission.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: ROUTE-027, VUE-023, VUE-029, VUE-081, VUE-099, VUE-100, AFFW-069, AFFW-077, AFFW-113, AFFW-129, AFFW-133, AFFW-146, MAIL-049, MAIL-053, NOTIF-012
- **Note**: owns wizard steps, reconfigure (section/language), wizard file/contributor/reviewer-suggestion panels, acknowledgement emails (incl. OPS can-post variant as divergence), needs-editor email. Intake *configuration* is F8; wizard consumes it.

### C2. Editorial dashboard (submission lists)
- **Intent**: editorial staff find, filter and act on submissions from the list views.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: ROUTE-007, ROUTE-008, VUE-003, VUE-075, API-006, AFFW-001, AFFW-015, AFFW-022, AFFW-028, AFFW-035, AFFW-041, AFFW-045
- **Note**: TEMPLATE rule 9 — dashboards are separate features from the workflow screen. Owns views/filters/search/bulk-delete/activity cells and the row-level quick actions' *presence* (the actions' mechanics belong to their homes, e.g. reviewer popover → D4).

### C3. My Submissions (author list & tracking)
- **Intent**: an author tracks their own submissions and acts on requests (complete submission, submit revisions).
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-005, ROUTE-034, AFFW-027, AFFW-034, AFFW-037, AFFW-040, AFFW-048, AFFW-049, AFFW-701, API-006 (assigned)
- **Note**: owns the author's entry route into the workflow screen (rule 9); everything after entry belongs to the workflow features.

### C4. Review assignments list (reviewer dashboard)
- **Intent**: a reviewer sees their review invitations and deadlines and opens each assignment.
- **Apps**: {OJS OMP} · **Tier**: L
- **Atoms**: AFFU-197, AFFU-204, ROUTE-008 (reviewAssignments op), API-006 (reviewerAssignments)
- **Note**: kept separate from D5 per the dashboards-own-their-list rule; scenarios stay to the list itself.

---

## D. Editorial workflow

### D1. Workflow screen shell & stage navigation
- **Intent**: any assigned participant (author included) opens a submission's workflow and moves between stages and publication tabs they may access.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-031, ROUTE-053, ROUTE-072, ROUTE-088, VUE-012, AFFW-226, AFFW-244, AFFW-260, AFFW-280
- **Note**: home of stage-access mechanics (`stage access` anchor other specs link to). Per-stage panels/actions belong to the stage features.

### D2. Submission stage
- **Intent**: the team receives a new submission, assigns editors, and moves it onward or declines it at the desk.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-286, AFFW-303, GRID-032, GRID-033, NOTIF-014, NOTIF-039, MAIL-024, MAIL-052, MAIL-009
- **Note**: owns which decisions the stage offers (desk decline / send to review / skip-to-x rosters per app); the decision *wizard* mechanics live in D7. OMP's Internal Review stage is out of scope (CHARTER).

### D3. Review stage — rounds, files & author revisions
- **Intent**: an editor runs review rounds — choosing files for review, collecting revisions, requesting/receiving the author's response to reviews.
- **Apps**: {OJS OMP} · **Tier**: H
- **Atoms**: AFFW-323, GRID-024, GRID-025, GRID-027, GRID-029, SET-021, NOTIF-029, NOTIF-031, MAIL-047, MAIL-033, API-032, VUE-008, AFFW-577, AFFW-579, VUE-049
- **Note**: round lifecycle + revisions + author response-to-reviewers (editor request, author response manager) in one chapter — same round fixture. Reviewer-assignment lifecycle is deliberately split out (D4).

### D4. Reviewer management (assignment lifecycle)
- **Intent**: an editor finds, invites, monitors, reminds, unassigns and thanks reviewers, and reads their reviews.
- **Apps**: {OJS OMP} · **Tier**: H
- **Atoms**: GRID-086, GRID-098, VUE-046, VUE-068, VUE-016, AFFW-486, AFFW-621, AFFW-642, AFFW-655, MAIL-044, MAIL-042, MAIL-034, MAIL-026, NOTIF-019, SET-020
- **Note**: includes advanced reviewer search, create/enroll reviewer, edit/reinstate/resend, review history & gossip, read-review (editor and author views — screen ownership), anonymity file limiting (GRID-026).

### D5. Reviewer's review wizard
- **Intent**: a reviewer accepts or declines a request, reads guidelines, downloads files, submits a review (form or free-text + attachments), and sees their completed review.
- **Apps**: {OJS OMP} · **Tier**: H
- **Atoms**: ROUTE-022, ROUTE-047, ROUTE-066, AFFU-138, AFFU-143, AFFU-159, AFFU-170, AFFU-176, AFFU-179, AFFU-192, VUE-009, VUE-037, VUE-076, GRID-013, MAIL-036
- **Note**: includes one-click (access-key) review entry (AFFU-205..209) and the reviewer-facing discussions panel; the review-form *builder* is F1.

### D6. Review setup (settings, forms, recommendations)
- **Apps**: {OJS OMP} · **Tier**: M — listed as F1 below under settings decisions; see there.

### D7. Editorial decisions & the decision wizard
- **Intent**: a decider records any editorial decision or recommendation through the multi-step wizard, notifying the right people with the right files.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: ROUTE-009, VUE-017, VUE-086, AFFW-161, AFFW-181, AFFW-197, MAIL-004, MAIL-011, MAIL-013, MAIL-032, NOTIF-021, NOTIF-023, API-042 (decisions ops), SET-009, MAIL-012
- **Note**: mechanism home — wizard steps, email composer + file attacher (Composer/FileAttacher components), promote-files, notify-other-authors/reviewers, recommend-only flow, revert decisions. Stage features own which decisions appear where. OPS's reduced roster (Decline/Revert only) is a parameterization, not a split.

### D8. Publishing, scheduling & versions
- **Intent**: the team approves and publishes a publication (posts a preprint), schedules it, unpublishes it, and manages published versions.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: GRID-062, GRID-107, VUE-084, VUE-091, AFFW-435, AFFW-707, API-042 (publish/version ops), API-057, NOTIF-054, NOTIF-035, MAIL-002, MAIL-031, MAIL-072, JOB-050, API-065
- **Note**: owns publish/unpublish gates, version creation + change summaries, scheduled publishing (task), issue-assignment delta {OJS} (points to D9 for issue lifecycle), OPS posting + moderation-before-posting + posted acknowledgements as divergences. OPS relate-to-journal surfaces flagged out-of-scope (see list).

### D9. Issues management
- **Intent**: an editor creates issues, orders the table of contents, publishes/unpublishes issues and maintains back issues and issue galleys.
- **Apps**: {OJS} · **Tier**: H
- **Atoms**: ROUTE-041, GRID-069, GRID-070, GRID-072, GRID-085, AFFM-243, SET-031, API-052, API-053, MAIL-060, JOB-031, NOTIF-055
- **Note**: includes issue identifiers/DOI delta (points to F4), issue-published notify emails, current-issue switching. Reader-side rendering is A6.

### D10. Copyediting stage
- **Intent**: the team moves accepted submissions through copyediting — drafts in, copyedited files out, author checks.
- **Apps**: {OJS OMP} · **Tier**: M
- **Atoms**: AFFW-356, GRID-014, GRID-015, GRID-019, GRID-020, NOTIF-032, NOTIF-042, NOTIF-043, MAIL-020
- **Note**: stage instantiation + copyediting-specific file flows; file-manager mechanics live in D13.

### D11. Production stage
- **Intent**: the team prepares production-ready files and formats ahead of publication.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-364, GRID-022, GRID-023, NOTIF-044, NOTIF-045, NOTIF-018, MAIL-021, MAIL-077
- **Note**: galley/format creation is E5 (OJS/OPS); OMP publication formats are out of scope. Production status prompts (assign user, awaiting representations) owned here.

### D12. Stage participants
- **Intent**: an editor manages who participates in a submission's stages and notifies them.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: VUE-043, GRID-054, GRID-055, AFFW-466, AFFW-671, NOTIF-046, API-042 (participants ops), MAIL-024
- **Note**: manager mechanics home (rule 8); stage features own which roles are offered per stage. Recommend-only vs full-decision participant setting owned here, consumed by D7.

### D13. Workflow file management
- **Intent**: any participant uploads, revises, annotates and downloads submission files anywhere the file manager appears.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: VUE-038, VUE-053, GRID-001, GRID-002, GRID-066, GRID-016, AFFW-474, AFFW-586, AFFW-599, AFFW-609, API-043, API-045, SET-026
- **Note**: mechanism home for upload wizard, genre choice, metadata edit, dependent files, download-all; stages own which file lists mount where (rule 8 test corollary: mechanics deep-tested once, stages test gates only).

### D14. Discussions & editorial tasks
- **Intent**: participants discuss a submission and track named tasks with owners and due dates, from templates where configured.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: VUE-036, VUE-058, VUE-059, VUE-060, VUE-050, VUE-071, API-044, API-017, AFFW-507, NOTIF-040, NOTIF-041, MAIL-023, MAIL-021, MAIL-076
- **Note**: owns the new tasks system (open/start/close, notes, participants), task templates settings manager, per-stage discussion mailables, and the orphan `alternateTo` templates (MAIL-076..083) that surface as composer template choices.

### D15. Activity log & notes
- **Intent**: staff review what happened to a submission — event history, emails sent, and notes on submissions and files.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-008, GRID-009, GRID-056, GRID-057, GRID-058, AFFW-687, AFFW-697, SET-013, SET-011, API-018

---

## E. The publication (metadata & outputs)

### E1. Publication metadata editing
- **Intent**: staff and (pre-publication) authors edit a publication's descriptive metadata — title & abstract, metadata fields, language, license/permissions.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-377, AFFW-395, VUE-085, VUE-090, API-042 (_components ops), SET-019, AFFR-055
- **Note**: owns the published-state edit locks and multilingual entry; category assignment delta → A9; license defaults come from the distribution settings it owns (see Settings decisions). Reader-side display of each block belongs to A5 (screen ownership).

### E2. Contributors & affiliations
- **Intent**: anyone editing a publication maintains its contributor list, order, roles and ROR-backed affiliations.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-051, VUE-033, VUE-093, VUE-094, AFFW-532, AFFW-681, SET-003, SET-001, API-033, JOB-057, AFFR-053, VUE-034, API-014
- **Note**: includes contributor-role (CRediT-style) settings manager + ROR registry lookup/update; ORCID request buttons are deltas → B5.

### E3. References & citations
- **Intent**: staff manage a publication's reference list and data citations, including automated structuring/enrichment.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: VUE-032, VUE-055, VUE-035, VUE-057, API-011, API-015, JOB-003, JOB-004, JOB-006, AFFW-533, AFFW-544, SET-005, SET-008, AFFR-057, CLI-016 (ref-only)
- **Note**: data citations merged in — same tab cluster, same fixture, same enrichment mental model.

### E4. Funding
- **Intent**: staff record funders and grants and readers see the funding statement.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: VUE-039, VUE-061, API-020, AFFW-551, AFFR-063, SET-014

### E5. Galleys
- **Intent**: staff attach the publication's presentation files (PDF, HTML, XML galleys) per version.
- **Apps**: {OJS OPS} · **Tier**: M
- **Atoms**: VUE-040, GRID-068, GRID-101, AFFW-524, AFFW-735, AFFW-752, SET-030, SET-042, AFFR-047
- **Note**: OMP publication formats out of scope (CHARTER). Remote-URL galleys, ordering, per-version behavior here; viewers are A5.

### E6. Full-text formats — JATS & body text
- **Intent**: staff maintain machine-readable full text (JATS XML, body text) alongside galleys.
- **Apps**: {OJS} · **Tier**: L
- **Atoms**: API-025, API-009, PLUG-019, AFFR-060
- **Note**: {OJS}: only OJS mounts these controllers; default JATS from the jatsTemplate plugin.

### E7. Identifiers (public IDs)
- **Intent**: staff assign/clear non-DOI public identifiers (e.g. URN) on publications, galleys and files.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: PLUG-043, GRID-067, GRID-088, GRID-100, AFFW-599, AFFR-064
- **Note**: URN plugin is {OJS OMP}; the identifiers tab/ops exist in all three. DOIs are F4 (different registry mental model + fixtures).

### E8. Media files
- **Intent**: staff upload images/media for a publication and link them into galley text.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: VUE-041, VUE-062, VUE-063, VUE-064, VUE-065, API-041, AFFW-558

---

## F. Management & administration

### F1. Review setup (settings, review forms, recommendations)
- **Intent**: a manager configures how review runs — deadlines, anonymity, reminders, review forms, recommendation options.
- **Apps**: {OJS OMP} · **Tier**: M
- **Atoms**: AFFM-065, GRID-046, GRID-047, GRID-059, VUE-047, VUE-069, API-054, MAIL-043, MAIL-046, JOB-054, JOB-012
- **Note**: settings-group ruling: OWN chapter — it parameterizes three review features (D3/D4/D5) but is tested with its own fixtures (a built form, reminder clocks). Automated reminder emails asserted here since their triggers are these settings.

### F2. Subscriptions & reader access control
- **Intent**: a subscription manager sells and manages access — subscription types/policies, individual & institutional subscriptions, paywalled reading, expiry reminders, open-access notices.
- **Apps**: {OJS} · **Tier**: H
- **Atoms**: ROUTE-046, ROUTE-052, ROUTE-032, GRID-080, GRID-081, GRID-084, GRID-087, AFFM-172, AFFR-090, AFFR-099, AFFR-101, MAIL-063, MAIL-066, JOB-059, PLUG-006
- **Note**: includes reader purchase flows, IP/domain institutional access, pay-per-view galley access labels, subscription reports plugin, delayed open access + open-access notify (JOB-058, MAIL-061, NOTIF-066). Publishing-mode setting owned here (it defines the paywall).

### F3. Payments (APC & payment methods)
- **Intent**: a manager charges author fees and configures how payments are collected (manual, PayPal).
- **Apps**: {OJS} · **Tier**: M
- **Atoms**: ROUTE-045, API-005, API-051, GRID-083, PLUG-041, PLUG-042, MAIL-062, MAIL-075, NOTIF-036, NOTIF-047, AFFR-104
- **Note**: {OJS}: OMP's direct sales is a different, out-of-scope feature sharing only paymethod plugins (glossary). Subscription *purchases* run through F2 scenarios; this owns fee configuration + payment records + APC.

### F4. DOI management & registration
- **Intent**: staff assign, export, deposit and track DOIs for the context's objects with a registration agency.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: VUE-018, VUE-095, VUE-096, API-016, API-001, API-052, AFFM-148, AFFU-210, JOB-008, JOB-010, JOB-030, JOB-045, PLUG-009, PLUG-011, SET-010
- **Note**: per-object-type coverage (publications, galleys, issues {OJS}, peer reviews) as parameterization; Crossref/DataCite plugins; registration-agency distribution settings owned here. Reader-side DOI links (AFFR-054) belong to A5/A6 screens.

### F5. Users administration
- **Intent**: a manager finds, edits, disables, merges and emails user accounts.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-050, GRID-003, VUE-013, VUE-051, AFFM-099, API-047, MAIL-054, SET-027, CLI-028 (ref-only), ROUTE-017 (editUser op)

### F6. Roles (user groups) & masthead
- **Intent**: a manager defines user groups, their stage assignments, permission levels and masthead visibility.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-048, SET-028, API-046, API-047 (endRole/masthead ops), MAIL-056, MAIL-057, AFFR-033
- **Note**: masthead *display* is A3's screen; role vocabulary per app is APP-GLOSSARY's job.

### F7. User invitations
- **Intent**: a manager invites a person (new or existing) into roles; the recipient accepts or declines and lands with an account.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: VUE-011, VUE-052, VUE-001, ROUTE-013, ROUTE-014, API-024, AFFM-118, AFFU-122, MAIL-055, JOB-013, JOB-051, SET-064
- **Note**: mechanism home for the invitation lifecycle (create → send → accept/decline → expiry); reviewer-specific invites are D4 deltas pointing here.

### F8. Submission intake configuration
- **Intent**: a manager shapes what authors must provide — submission components (genres), checklist, metadata field toggles, author guidelines, disabling submissions.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-053, AFFM-064, GRID-042, SET-006, AFFR-035, AFFR-036
- **Note**: settings-group ruling: OWN chapter — each toggle is verified by re-running C1, but the *configuration* rules (per-field enable/require matrix, genre setup) are a distinct sitting with its own grid fixtures. C1 links here.

### F9. Emails management
- **Intent**: a manager reviews and customizes every email the app can send — templates, per-mailable settings, restoring defaults.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: VUE-021, VUE-072, VUE-073, API-019, API-027, AFFM-121, AFFM-085, SET-012, CLI-010 (ref-only)
- **Note**: owns template CRUD/override mechanics and the emails setup tab (signature etc.); each mailable's *sending* is asserted by its owning feature.

### F10. Languages & locales
- **Intent**: an administrator/manager installs and enables locales at site and context level and controls which UI/forms/submission languages are offered.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-005, GRID-036, GRID-043, GRID-044, SET-049, AFFR-089
- **Note**: reader language toggle asserted here end-to-end (block is A1's screen, behavior owned here — one sitting).

### F11. Website appearance & theming
- **Intent**: a manager restyles the site — theme + options, logos, CSS, sidebar arrangement.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-018, AFFM-021, PLUG-049, API-013 (theme ops), SET-006, AFFM-044
- **Note**: front-page content toggles live in A1; this owns theme/branding mechanics. Boundary noted as soft.

### F12. Institutions
- **Intent**: a manager maintains the institution list used by subscriptions and statistics.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: VUE-098, API-023, AFFM-137, SET-016

### F13. Import, export & registry deposits
- **Intent**: staff move content in and out — native XML, users import/export, PubMed export, DOAJ deposit.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: VUE-019, ROUTE-018 (importexport op), PLUG-032, PLUG-035, PLUG-034, PLUG-012, JOB-061, GRID-052, GRID-076, GRID-078, CLI-026 (ref-only)
- **Note**: PubMed/DOAJ {OJS} inline divergences; OMP CSV/ONIX exports out of scope. Export grids (GRID-073/074/102/103/105) claimed here.

### F14. Usage statistics dashboards & reports
- **Intent**: staff view usage of publications/context/users (and issues in OJS), download report spreadsheets, build COUNTER R5 reports, and serve SUSHI.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: VUE-025, VUE-026, VUE-028, VUE-027, VUE-002, VUE-074, VUE-079, API-036, API-038, API-039, API-040, JOB-060, JOB-023, PLUG-029, PLUG-045
- **Note**: includes the stats ETL pipeline as side-effect assertions (seed via test-metrics tooling, CLI ref-only); geo settings + institution stats deltas; issue stats {OJS}. Pipeline internals below job granularity are unit-test territory, flagged for Phase 2.

### F15. Editorial statistics & monthly report
- **Intent**: staff view editorial activity (submissions received/accepted/declined, response times) and editors receive the monthly report email.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: VUE-024, API-037, AFFU-247, ROUTE-026, NOTIF-049, MAIL-048, JOB-015, JOB-016, JOB-055, PLUG-047
- **Note**: OPS registers the page with the chart removed — inline divergence. Review report plugin {OJS OMP} grouped here (editorial-data spreadsheet mental model).

### F16. Hosted contexts administration
- **Intent**: a site administrator creates, configures, orders and removes the journals/presses/servers this installation hosts.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-004, VUE-014, AFFM-192, AFFM-197, API-013, ROUTE-003 (contexts/wizard ops), SET-006

### F17. Site settings
- **Intent**: a site administrator sets site-wide identity, appearance, information and bulk-email policy.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: API-035, SET-024, AFFM-211, AFFM-183, VUE-015, API-002, SET-015
- **Note**: site-level highlights/announcements toggles are deltas of A1/A4; bulk email (API-002, JOB-002) owned here with its site/context enablement gates.

### F18. System administration & job monitoring
- **Intent**: an administrator inspects system state (system info, sessions, caches), monitors/requeues queued jobs, and relies on scheduled tasks running.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-003 (systemInfo/expireSessions/clear* ops), VUE-005, VUE-006, VUE-007, API-026, AFFM-230, AFFM-236, JOB-049, JOB-052, JOB-063, JOB-067, SET-062, SET-063, CLI-012 (ref-only), CLI-021 (ref-only)
- **Note**: owns the queue/scheduler *frameworks* (each feature asserts only its own jobs' effects); infra config sections claimed as reference (see Settings decisions).

### F19. Plugins management & gallery
- **Intent**: an administrator/manager enables, configures, uploads and installs plugins at site and context level.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-006, GRID-041, GRID-077, GRID-095, GRID-104, AFFM-044, NOTIF-009, NOTIF-010, SET-052, CLI-017 (ref-only)
- **Note**: individual plugins' behavior belongs to their owning features; this owns the lifecycle (enable/disable/upload/gallery-install, `allow_plugin_install` gate).

### F20. Publisher & submission libraries
- **Intent**: a manager keeps a context-level document library; staff/authors use per-submission document libraries and public file links.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: GRID-045, GRID-021, GRID-034, GRID-061, ROUTE-015, AFFM-082, AFFW-615, API-004

---

## Settings decisions (per group, as required)

| Settings group | Ruling | Why |
|---|---|---|
| Settings > Journal/Press/Server (context/masthead/contact) | owned by **A3** (merged with About pages) | tested as edit-then-view with the pages that render the fields |
| Website > Appearance | split: theme/branding mechanics → **F11**; front-page content toggles → **A1** | different sittings: restyling vs what the home page shows |
| Website > Setup (languages, navigation, privacy, date/time) | dissolved into **F10** (languages), **A2** (navigation), **A3** (privacy/info content) | the tab is a code container, not a user intent |
| Website > Plugins | **F19** | it IS the plugin lifecycle surface |
| Workflow > Submission | own chapter **F8** | own grid fixtures (genres, checklist); consumed by C1 |
| Workflow > Review | own chapter **F1** | parameterizes D3/D4/D5 but needs its own fixtures (forms, reminder clocks) |
| Workflow > Publisher library | **F20** | the library is the feature |
| Workflow > Emails + Tasks & discussions tabs | **F9** and **D14** respectively | each tab configures exactly one feature |
| Distribution | dissolved: license → **E1**, indexing → **A13**, access/publishing-mode → **F2**, DOI agency → **F4**, payments toggle → **F3** | tabs are toggles OF those features; no shared fixture |
| Site admin > Site settings | **F17** | coherent site-identity sitting |
| `config.inc.php` sections | behavior-bearing keys claimed by owning features (SET-052 → B1/F19, SET-053 → B2/F9, SET-054 → A10, SET-055 → A12, SET-057 → B1/B2, SET-062/063 → F18, SET-064 → F7); pure-infra sections (SET-046..051, SET-056, SET-058..061, SET-065) claimed by **F18** as reference | infra keys aren't QA-testable rules; they parameterize the environment |

## (a) Candidate-UNASSIGNED

| Atom(s) | Reason |
|---|---|
| ROUTE-043 (legacy `manager` page stub) | dispatcher switch that returns no handler — dead-code candidate; claims nothing testable |
| ROUTE-012 (installer/upgrader), CLI-027, CLI-031 | install/upgrade wizard is not a per-context QA feature; needs a maintainer ruling on campaign scope |
| API-030 + unmounted `PkpOpenReview`/`PkpCite`/`PkpOrcidDisplay` (AFFR "not swept" note), API-062 (OMP dangling `publicationPeerReviews` mount) | emerging open/published-peer-review surface with no confirmed UI mount — liveness unresolved; force-fitting into D4/F4 would invent a feature |
| VUE-004 (ExamplePage) | ui-library docs fixture, no app mount |
| VUE-042 (NavigationMenuManager component) | manager itself unmounted (only its modal is wired) — dead-code candidate |
| NOTIF-011 (PLUGIN_BASE) | reserved base constant, zero references |
| JOB-028, JOB-029 (test jobs) | queue smoke fixtures, not product behavior |
| ROUTE-037 lockss/clockss ops | LOCKSS/CLOCKSS manifest gateway {OJS} — preservation slice too thin for a feature, no bundled PLN plugin to anchor it; park pending ruling |

(8 entries.)

## (b) Out-of-scope candidates (per CHARTER Scope)

- **OMP catalog cluster**: ROUTE-055, ROUTE-056, ROUTE-062, VUE-020, VUE-101, GRID-099, AFFM-263..271, AFFR-070, AFFR-071, AFFR-073, AFFR-074, AFFR-075, AFFR-076, AFFR-077, API-059 (catalog flags), NOTIF-038 (visit-catalog prompt — shared constant; usable only as D8 register note)
- **OMP monographs/chapters/publication formats/ONIX**: GRID-089, GRID-090, GRID-091, GRID-092, GRID-093, GRID-094, GRID-097, VUE-031, VUE-044, VUE-045, AFFW-572, AFFW-573, AFFW-574, AFFW-431..434 (marketing tabs), AFFW-754..777, PLUG-018, PLUG-031, PLUG-033, SET-040 (submissionFile overlay), SET-036
- **OMP Internal Review stage & `*_INTERNAL` decisions**: ROUTE-072 (internalReview op), AFFW-304..322, MAIL-071, NOTIF-015, NOTIF-020, NOTIF-030 (internal variants), MAIL-080, MAIL-081 (index request/complete orphans)
- **OMP direct sales**: the payments row of the glossary — only paymethod plugins (PLUG-041/042) are shared and they're claimed by F3
- **OMP series reader page**: AFFR-073 (catalog surface; series *settings* stay in A7 via glossary)
- **`NOTIFICATION_TYPE_BOOK_*`**: NOTIF-056..065 (explicit CHARTER drop; OJS-defined but surfaceless)
- **OPS preprint-to-journal relations**: PLUG-024, API-065 (relate op), the vorDoi/relations portion of AFFR-082 — surfaces OJS never exposes; flag for maintainer (could alternatively live as D8 register divergences)
- **OMP-only chapter roles** (Volume Editor / Chapter Author vocabulary) — no OJS surface

## (c) Hardest grouping calls (disagreement-register feed)

1. **Peer review split into D3/D4/D5 (+F1)** — three H features + an M instead of one "Peer review" monolith. Rejected the monolith: it would carry 30+ scenarios and three distinct sitting/fixture sets (editor-round fixtures vs reviewer-persona fixtures vs settings fixtures). Risk: cross-links between D3 and D4 must be tight (round state drives assignment availability).
2. **OMP catalog out-of-scope vs the glossary's Archive→Catalog vocabulary row** — CHARTER drops the catalog, but APP-GLOSSARY maps "Archive (back issues)" to "Catalog" as plain vocabulary, implying archive-browse specs cover OMP via substitution. I followed CHARTER (catalog out), leaving OMP with no reader-browse coverage at all. Needs a maintainer ruling; the alternative (one "published-content browsing" feature spanning OJS archive/OPS preprints/OMP catalog) was rejected only because CHARTER names the catalog explicitly.
3. **Three dashboard features (C2/C3/C4) over one Vue page** — rule 9 says the dashboards own their lists separately, and fixtures/roles differ per view; rejected both "one dashboard feature" and folding C4 into D5. Risk: shared table/filter mechanics need one home (put in C2, C3/C4 point to it).
4. **Decision wizard as mechanism home (D7) with stages owning only their decision rosters** — rejected per-stage ownership of decision flows (would restate wizard+composer mechanics four times). Consequence: D2/D3/D10/D11 specs stay thin on decisions, which will feel sparse to a reader until the links are followed.
5. **Settings taxonomy dissolved by intent, not by settings page** — Distribution and Website>Setup have no feature of their own; Submission and Review tabs DO (F8, F1). The rejected alternative (one chapter per settings screen) is code-shaped and fails the QA-sitting litmus; the risk is claim-scatter for `context.json`'s 133 props across ~10 features.
6. **Scheduling/issue-assignment in Publishing (D8), not Issues (D9)** — the tester schedules from the workflow screen; D9 keeps the issue lifecycle. Rejected the inverse because "schedule for publication" without publish gates is untestable. OPS posting/moderation stays a D8 divergence (pure parameterization), not an OPS feature.
7. **Subscriptions (F2) vs Payments (F3) split, with pay-per-view under F2** — pay-per-view is access control (reader hits a paywall), even though it produces payment records; rejected a single "commerce" feature (subscription policy fixtures vs fee configuration are different sittings) and rejected putting per-article purchase in F3.
8. **ORCID as one mechanism feature (B5)** — verify/deposit mechanics + emails + jobs in one place, with contributors/reviewer/profile owning only their entry buttons. Rejected scattering by entry point: every scenario needs the same OAuth sandbox fixture.
9. **Open/published peer review parked, not featured** — API-030 + unmounted Vue components suggest an emerging OJS feature, but with no confirmed mount a feature row would be speculation; parked in UNASSIGNED for a Phase 1 liveness probe rather than force-fitted into D4/F4.
10. **A13 (sitemap + meta tags + analytics) as one L** — mildly heterogeneous, but all are page-source/XML assertions with no UI; rejected three sub-L fragments that couldn't each fill 3 scenarios.

## Coverage self-check (mechanical walk)

- **routes**: every ROUTE-001..088 handler maps to a feature above or a listed
  exception (ROUTE-012/043 → UNASSIGNED; OMP catalog/internal-review handlers →
  out-of-scope). App subclasses ride their base atom's feature.
- **grids**: GRID-001..107 all claimed; abstract bases (GRID-017/018/030/031/035/049/057/060) ride their concrete subclasses' features (D13/F20/F1/D15).
- **vue**: all VUE atoms claimed except VUE-004/042 (UNASSIGNED) and VUE-020/031/044/045/101 (out-of-scope).
- **api**: all claimed except API-030/062 (UNASSIGNED), API-059-catalog/API-065-relate portions (out-of-scope).
- **notif**: all claimed except NOTIF-011 (UNASSIGNED), NOTIF-015/020/030/038 internal/catalog variants and NOTIF-056..065 (out-of-scope). Toast plumbing NOTIF-001..007 → B6 as framework reference.
- **mail**: all claimed; MAIL-071/080/081 out-of-scope; orphan alternateTo keys → D14/F9.
- **jobs**: all claimed (JOB-028/029 UNASSIGNED; JOB-038 series metrics → F14 OMP divergence; infra JOB-063..067 → F18).
- **settings**: schemas claimed by their entity's home feature; config sections per the Settings decisions table.
- **plugins**: every PLUG row claimed by a feature above except PLUG-018/024/031/033 (out-of-scope).
- **cli**: never seeds features (README ruling); ref-only claims noted inline; the rest fall under the blanket out-of-scope reason in `cli.md`.
- **aff (AFFW/AFFM/AFFR/AFFU)**: every section of the four files maps to at least one feature above; per-atom attribution is Phase 1 crosswalk work.
