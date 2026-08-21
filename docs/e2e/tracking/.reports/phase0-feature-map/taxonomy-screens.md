# Feature taxonomy draft — screen lens

- **Date**: 2026-07-27 · **Author**: claude (Fable), desk pass over the Phase 0 atlas only
  (no live environment, no git history, no prior FEATURE-MAP consulted — RUNBOOK
  "Rebuilding a feature from scratch").
- **Method**: walked the four affordances files screen by screen
  (affordances-workflow → -management → -reader → -user), clustered screens and their
  entry-point atoms into features by user intent ("what would a journal manager call
  this?"), then attached routes/grids/vue/api/mail/notif/jobs/settings/plugins atoms to
  the features whose screens they serve. Settings forms are attached to the feature
  whose behavior they configure (a settings form + its front-end effect = one intent),
  not to a per-screen "settings" feature. CLI atoms seed nothing (atlas README ruling);
  a few are cited as reference-only counterparts.
- **Tier judgment** (RUNBOOK Budget & ceilings: H 10–13, M 6–8, L 3–4 common
  scenarios): judged from visible rule/affordance density and state count, not from
  code depth. Tiers here are proposals for PROGRESS rows, not commitments.
- **Atom lists are representative (5–15, spanning modalities), NOT a crosswalk.**
  Full claiming happens per feature in Phase 1.
- Apps badge `{OJS OMP OPS}` omitted when all three; deviations badged.

---

## A. Dashboards & submission intake

### 1. Submissions dashboard (editorial)
- **Intent**: editors and managers find, triage and open the submissions they are responsible for.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFW-001, AFFW-004, AFFW-014–021 (bulk delete), AFFW-028, AFFW-036–047 (activity cells/alerts), AFFW-051–055, AFFW-062–066 (sidebar views), ROUTE-008, VUE-003, VUE-075, API-006
- **Note**: TEMPLATE rule 9 — this feature owns only the editorial list; the workflow screen it opens is feature 4's. The reviewer view of the same page belongs to feature 12; My Submissions to feature 2.

### 2. My Submissions (author dashboard)
- **Intent**: an author tracks their own submissions, sees requested actions, and re-enters incomplete ones.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-027, AFFW-040–041, AFFW-048–049, AFFW-056, AFFW-066, AFFW-701–706 (legacy author-dashboard grids/emails), ROUTE-005, ROUTE-034/054/073, AFFW-351 (emails list panel)
- **Note**: per rule 9, the author's entry route (View → workflow) is owned here; everything after it belongs to workflow features.

### 3. Submission wizard
- **Intent**: an author starts, fills in, saves-for-later, cancels and submits a new submission.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFW-069–075 (start), AFFW-077–110 (steps, autosave, reconfigure, submit/cancel dialogs), AFFW-113–122 (review-step panels), AFFW-129–132 (outcome pages), AFFW-133–145 (wizard files panel instantiation), AFFM-053 (disable-submissions form; front-end effect AFFW-070, AFFR-035), ROUTE-027, VUE-023, VUE-029, VUE-081, MAIL-049/050/051/053, MAIL-074 {OPS}
- **Note**: the files/contributors/reviewer-suggestion panel *mechanics* live in their home features (15, 20, 8); the wizard owns its instantiations and step flow. OPS galleys step (AFFW-125–126) and OMP chapters step are app deltas (chapters step → out of scope, see D-list).

## B. The workflow screen

### 4. Workflow screen & stage access
- **Intent**: any participant opens a submission's workflow and reaches the right stage, tab and tools their role allows.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-226–236, AFFW-240–247, AFFW-249–259 (side menu incl. review-round nodes), AFFW-260–274 (publication-tab menu items), AFFW-280–285 (no-access notice, status panel), AFFW-707, ROUTE-031, ROUTE-053/072/088, VUE-012
- **Note**: one shared screen, author included (rule 9) — role gates are rows here, never separate features. Stage features own what mounts on each stage.

### 5. Submission stage
- **Intent**: editors screen a new submission and move it onward (to review, skipped, declined) or remove it.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-286–295 (panels + decision roster), AFFW-296–300 {OMP deltas}, AFFW-302–303 (author view), AFFW-454 (delete dialog), MAIL-009/016/017/019, NOTIF-014, NOTIF-039, MAIL-052
- **Note**: OMP's "Send to External Review" routing through the skip-internal decision (AFFW-296) stays here as a parameterization; the Send-to-Internal action (AFFW-301) is out of scope with the Internal Review stage.

### 6. Review stage & rounds
- **Intent**: editors run review rounds on a submission — round status, revisions, round-level decisions — and authors follow and respond on the same screen.
- **Apps**: {OJS OMP} · **Tier**: H
- **Atoms**: AFFW-323–339 (panels + decision roster), AFFW-340–344, AFFW-349 (recommend-only controls/listing), AFFW-350–355 (author view, upload revisions), AFFW-460 (select-revision modal), AFFW-461 (minimum-reviews warning), GRID-024/025/029, MAIL-010/013/014/032/047, NOTIF-023/025/031, VUE-086
- **Note**: OPS has no review stage (absence paragraph + absence tests). Reviewer roster mechanics = feature 7; the reviewer's own screens = feature 8. Internal Review (OMP) is out of scope wholesale.

### 7. Reviewer assignment & tracking (editor side)
- **Intent**: an editor finds, invites, manages and reads reviewers for a review round.
- **Apps**: {OJS OMP} · **Tier**: H
- **Atoms**: AFFW-213–225 (advanced reviewer search), AFFW-486–506 (ReviewerManager actions incl. read review, revert, ORCID deposit), AFFW-621–641 (add/create/enroll forms + footer options), AFFW-642–654 (edit/remind/thank/unassign/reinstate/resend/email/gossip), AFFW-655–665 (read review modal), GRID-086/098, MAIL-034–046 (review lifecycle mailables), NOTIF-019/048, JOB-012/054, AFFM-065 (review setup form: deadlines/reminders), AFFM-078–081 + API-054 (reviewer recommendation options)
- **Note**: review setup + recommendation options attach here as "settings that modify behavior". Review forms authoring is feature 9 (own screens).

### 8. Reviewer's review (respond, review, submit)
- **Intent**: a reviewer sees their assignments, responds to a request, completes and submits a review, and revisits it later.
- **Apps**: {OJS OMP} (assignments list renders in all three) · **Tier**: H
- **Atoms**: AFFU-138–169 (wizard steps 1–4 incl. CI, files, comments, recommendation {OJS}), AFFU-176–178 (decline modal), AFFU-179–196 (completion, discussions mount, round history), AFFU-197–204 (Review Assignments dashboard view), AFFU-205–209 (one-click access), ROUTE-022/047/066, GRID-013/028, VUE-009, VUE-076, AFFM-066 (reviewer guidance form)
- **Note**: the reviewer-facing discussions panel instantiates feature 17's mechanics. The `myReviewAssignments` view exists in OPS too — flag for Phase 1: what it lists in an app with no review stage.

### 9. Review forms
- **Intent**: a journal manager builds structured review forms whose questions reviewers answer instead of free-text comments.
- **Apps**: {OJS OMP} · **Tier**: M
- **Atoms**: AFFM-067–077 (forms grid + items grid + preview/copy/activate), AFFW-670 (editor tabset), AFFW-668 (review download rendering), AFFU-170–175 (element types in the wizard), GRID-046/047/059, AFFW-640/643 (form chooser on assignments)
- **Note**: filling happens in feature 8's screens; this feature owns element behavior once, per variance ownership.

### 10. Author review-round response
- **Intent**: an editor requests — and an author submits — a formal response to a review round's reviews.
- **Apps**: {OJS} · **Tier**: M
- **Atoms**: AFFW-326 (editor panel mount), AFFW-577–585 (author card, request table, form modal), ROUTE-023, VUE-008/049/070, API-032 (authorResponse endpoints), MAIL-033, AFFU-244 (authorResponse DOI row — cross-link to 26)
- **Note**: manager components are OJS-only per the vue sweep; API is mounted ojs+omp — Phase 1 resolves whether OMP reaches any UI (badge may widen).

### 11. Copyediting stage
- **Intent**: editors and copyeditors move accepted submissions through copyediting to production-ready text.
- **Apps**: {OJS OMP} · **Tier**: M
- **Atoms**: AFFW-356–363 (panels, decisions, author view), AFFW-610–611 (select final draft / copyedited files modals), GRID-014/015/019/020, MAIL-005/018, NOTIF-032/042/043, MAIL-076 (COPYEDIT_REQUEST alternate template)
- **Note**: OPS lacks the stage (absence tests). File mechanics live in feature 15.

### 12. Production stage
- **Intent**: editors ready the accepted text's final files and hand over to scheduling/publication.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-364–369 (panels, schedule entry, back-to-copyediting), AFFW-370–376 (OPS variant + author views), AFFW-612 (proof files modal), GRID-022/023, NOTIF-044/045, MAIL-006, MAIL-077/082/083 (production alternate templates)
- **Note**: OPS's production stage carries decline/revert/delete (AFFW-372–374) — its whole editorial flow lives here; the posting act itself is feature 30.

### 13. Editorial decision recording
- **Intent**: an editor records a decision through the guided steps — notification email, file promotion — and the right people are told.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFW-161–180 (decision wizard), AFFW-181–196 (email composer), AFFW-197–212 (file attacher sources), AFFW-455–456 (return-to-workflow/done), AFFW-462, ROUTE-009, VUE-017, VUE-087/088, API-042 (decisions endpoints), MAIL-004/011/012, NOTIF-021–028, SET-009
- **Note**: the Composer and FileAttacher mechanics are homed here (largest instantiation); discussions (17), invitations (46) and bulk email (42) link rather than restate. Which decisions appear on which stage belongs to the stage features.

### 14. Stage participants
- **Intent**: editors control who works on a submission at each stage — assign, notify, restrict, remove.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-466–473 (panel + log-out-as), AFFW-671–680 (add/edit participant, notify, user picker), GRID-054/055, MAIL-024, NOTIF-014/016–018 (editor-assignment types), NOTIF-040 (notify → discussion), AFFW-038 (dashboard assign-editor action), MAIL-078/079 (editor-assign alternate templates)
- **Note**: recommend-only and can-edit-metadata toggles are set here but their *consequences* are rules in features 6/13 and 19 respectively (one side-effect line + link each way).

### 15. Submission files
- **Intent**: anyone with stage access uploads, revises, organizes and downloads a submission's files.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFW-474–485 (FileManager actions), AFFW-586–598 (upload wizard incl. genre/revision selectors), AFFW-599–600 (file metadata/identifiers tabset), AFFW-613–614 (grid filters), GRID-001/002, GRID-016 (dependent files), GRID-066, API-043, API-045, VUE-038/053, AFFM-056–060 (components/genres grid), GRID-009 (per-file event log), SET-026
- **Note**: genre configuration lives here (it configures upload behavior). Stage features own which file lists mount where (variance rule); *_SELECT namespaces and per-stage lists are their deltas.

### 16. Submission activity log & notes
- **Intent**: editorial staff consult a submission's history and keep internal notes on it and its files.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFW-235 (header entry), AFFW-687–696 (information center tabs, notes, delete), AFFW-697 (event-log filter), GRID-008/056/057/058, SET-013, AFFW-711 (view-metadata modal), SET-011 (email log)
- **Note**: "ownership follows the screen" — log *entries* are written by many features; the log surface is one feature.

### 17. Tasks & Discussions
- **Intent**: participants coordinate work on a submission through discussions and trackable tasks with due dates and templates.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFW-507–523 (manager, form/display/history modals, messages, attachments), AFFM-086–089 (task-template settings), API-044, API-017, VUE-036/058/059/060, MAIL-020–023 (discussion mailables), NOTIF-040/041, MAIL-025 + JOB-011/046 + NOTIF-051 (editorial reminders), AFFU-181–191 (reviewer instantiation — gates only)
- **Note**: this is the new tasks system layered on discussions — one feature, since one screen hosts both and QA exercises them together.

### 18. Submission & Publisher Libraries
- **Intent**: staff keep reusable documents at the journal level and per submission, and share them with participants.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFW-615–620 (both library modals + file forms), AFFM-082–084 (settings-side publisher library), ROUTE-015, GRID-021/034/045, GRID-061, API-004, AFFW-203 (attach-from-library)

## C. The publication (metadata → published item)

### 19. Title, Abstract & Metadata
- **Intent**: authors and editors maintain a publication's descriptive metadata, in the right language.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-395, AFFW-397, AFFW-402 (identifier form mount — the form's *fields* split per features 25/26), AFFW-430, AFFW-379–380 (edit warnings), AFFW-285 + AFFW-457–459 (change submission language), AFFM-055 (metadata enablement form; wizard effect AFFW-114/116), VUE-085, API-042 (`_components` metadata forms), SET-019/025
- **Note**: the publish-time gates on editing (published = locked) are stated here once; feature 30 links.

### 20. Contributors
- **Intent**: maintain who authored the work — order, roles, affiliations, principal contact — everywhere the list appears.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-146–156 (list panel + preview modal), AFFW-396/532, AFFW-681–686 (deprecated legacy author form — liveness question for Phase 1), AFFM-061–063 (contributor roles manager), API-014, API-042 (contributors endpoints), GRID-051, VUE-033/093/094, SET-003, AFFR-053 (reader-side author display, ORCID/ROR icons), SET-001/022 + JOB-057 + API-033 (affiliations/ROR)
- **Note**: FieldOrcid behavior inside the contributor form is feature 63's mechanics (link).

### 21. Citations & references
- **Intent**: capture a publication's reference list, structure it automatically, and show it to readers.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFW-533–543 (manager: add raw, delete all, reprocess, expand), API-011, VUE-032/055, JOB-003–007 (lookup pipeline), AFFR-057 (references display), SET-005, AFFW-115 (wizard review panel), CLI-016 (reference only)

### 22. Data availability & data citations
- **Intent**: authors declare where the underlying data lives; editors curate data citations shown with the article.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFW-399–400, AFFW-544–550, AFFW-086 (wizard section), API-015, VUE-035/057, SET-008, AFFR-063 (reader display, shared with funding)

### 23. Funding
- **Intent**: record who funded the work (funder, ROR, grants) and disclose it to readers.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFW-401, AFFW-551–557, AFFW-087, API-020, VUE-039/061, SET-014, AFFR-063

### 24. Identifiers (publisher IDs & URN)
- **Intent**: assign non-DOI public identifiers to published objects.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFW-601–607 (public identifiers forms, per-app object branches), AFFW-736/741/752 (identifier tabs), PLUG-043 {OJS OMP}, AFFR-064, GRID-067/088/100 (clearPubId ops), GRID-073/074/102 (pub-id export grids)
- **Note**: OMP chapter/format identifier branches ride the out-of-scope surfaces (D-list); the shared form mechanics stay here.

### 25. DOIs
- **Intent**: configure, assign, manage and register DOIs with an agency, and track their status.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFM-091–092 (setup + registration settings), AFFU-210–246 (DOI page: tabs, bulk ops, per-item edit/deposit/errors, versions modal, per-app row types), ROUTE-010, API-016, API-052 {OJS issues}, API-001, JOB-008–010, JOB-030 {OJS}, JOB-045, PLUG-009/011 (crossref/datacite), AFFR-054, AFFR-069 (Crossmark), SET-010, CLI-030 (reference only)
- **Note**: registration-agency plugin *settings* stay with their plugins under this feature (they configure DOI behavior).

### 26. Galleys
- **Intent**: editors attach the publishable files (PDF, HTML, XML…) a reader will open, and order/label them.
- **Apps**: {OJS OPS} · **Tier**: M
- **Atoms**: AFFW-419, AFFW-524–531 (manager), AFFW-735–739 (OJS galley form incl. remote URL, dependent files), AFFW-753 {OPS}, GRID-068/101, VUE-040, SET-030/042, AFFW-125–126 (OPS wizard galleys step)
- **Note**: OMP's counterpart (publication formats) is out of scope per CHARTER — absence handled via `hasGalleys`. Reader-side viewing is feature 34.

### 27. Media files
- **Intent**: manage image/media assets belonging to a publication and link them into full-text displays.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFW-420, AFFW-558–571 (manager, batch/manual link, metadata), API-041, VUE-041/062/063/064/065

### 28. JATS
- **Intent**: keep a JATS XML representation of each article — auto-generated or uploaded — and optionally expose it publicly.
- **Apps**: {OJS} · **Tier**: M
- **Atoms**: AFFW-403–410 (panel: upload/delete/download/visibility), AFFW-463–465 (dialogs), API-025, PLUG-019 (jatsTemplate), PLUG-040 (oaiJats), AFFR-060 (public download link)

### 29. Body Text editor
- **Intent**: editors author/convert the article's full text in the built-in editor and hand it to a version.
- **Apps**: {OJS} · **Tier**: M
- **Atoms**: AFFW-411–418 (editor, import/convert, fullscreen, nav guard), AFFW-479 (Send-to-text-editor file action), AFFW-438 (send-to-version select), API-009, VUE-091 (insert summary-of-changes)

### 30. Publish, schedule & versions
- **Intent**: editors publish (or schedule, unschedule, unpublish) a publication, manage versions, and — in OJS — place it in an issue.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFW-383–394 (version control + right controls), AFFW-435–453 (version/schedule modals incl. OJS issue-assignment fields AFFW-445–448), AFFW-709–710 (publish modal + warnings), AFFW-256 (create-new-version), GRID-062/107, API-042 (publish/version endpoints), API-065 {OPS role widening}, JOB-050 (scheduled publishing), MAIL-002/031, NOTIF-035/050/054, MAIL-072/073 {OPS posted acks}, AFFR-061 (reader versions list)
- **Note**: OPS "Post the preprint" incl. author-may-post is this feature's app variant; the OJS issue-assignment *fields* render on this feature's screens (ownership follows the screen) while issue entities belong to feature 53.

### 31. License & permissions
- **Intent**: set the copyright/license terms a publication carries and show them to readers.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFM-090 (license form), AFFW-421 (permission & disclosure form), AFFW-273, AFFR-065 (reader license block), AFFW-127 {OPS wizard license panel}, AFFM-163 (reset permissions tool)

## D. Reader-facing content surfaces

### 32. Article landing page
- **Intent**: a reader lands on a published item and sees everything about it — metadata, authors, versions, how to reach the files.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-033/080, AFFR-052 (preview/outdated notices), AFFR-053–057, AFFR-059, AFFR-062–065, AFFR-067 {OJS PFL}, AFFR-068 {OJS recommendations}, AFFR-081/082 {OPS summary/landing deltas}, PLUG-023/025/026
- **Note**: shared details atoms span all three apps; OMP's book/chapter-specific blocks (AFFR-075–077) ride the out-of-scope catalog (D-list). OPS relation notice is out of scope (relation dropdown ruling).

### 33. How to cite
- **Intent**: readers copy or download a formatted citation for the item in their chosen style.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFR-066, AFFR-082 (OPS inline block), PLUG-008, AFFR-211-noted unmounted `PkpCite` (→ UNASSIGNED)

### 34. Reading & downloading files (galley access)
- **Intent**: readers open or download the published files, in the right viewer, subject to access rules.
- **Apps**: {OJS OPS} (OMP file links ride the catalog) · **Tier**: M
- **Atoms**: AFFR-047 (galley link + restricted states), AFFR-048 (pdf.js viewer, all apps), AFFR-049/050 {OJS html/lens viewers}, PLUG-017/020/022, ROUTE-033 (viewFile/download ops), ROUTE-040 (issue galley download), GRID-001 (downloadFile), AFFR-046 (summary galley links)
- **Note**: *what* is restricted is subscriptions' rule (56); this feature owns the reader-visible behavior of the link/viewer.

### 35. Issue pages & archive (reader)
- **Intent**: readers browse the current issue, its TOC, and back issues.
- **Apps**: {OJS} · **Tier**: — folded into feature 53 (one intent with issue management; see hardest calls).

### 36. Search
- **Intent**: readers search the site's published content and refine results.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFR-083–085, AFFR-078 {OPS inline form}, ROUTE-024, ROUTE-048/067/083, JOB-027, SET-054, AFFR-007 (header search link), CLI-029 (reference only)

### 37. Categories
- **Intent**: organize content into browsable categories and let readers browse them.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-013–017 (manager), AFFR-024 (home category links), AFFR-072 (category page), AFFR-086 (browse block), API-010, VUE-030/054, SET-004, ROUTE-006 (category/thumbnail ops), AFFW-117 (wizard categories panel)

### 38. Announcements
- **Intent**: the journal posts news items; readers browse them and can be notified.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-036 (settings form), AFFM-129–136 (manager + types), AFFM-225–227 (site scope), AFFR-028–031, ROUTE-004, API-008, VUE-092, MAIL-001, NOTIF-008, JOB-014, PLUG-007 {OJS feed}, SET-002

### 39. Journal identity & about pages
- **Intent**: managers describe the journal — masthead, contact, policies, information pages — and readers consult it.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-001/002 (masthead + contact forms), AFFR-032–041 (about, masthead, history, submissions, contact, privacy, publishing system, information), AFFM-042 (privacy form), AFFM-054 (author guidance form → About Submissions), ROUTE-001/002, ROUTE-032 (OJS about subclass), ROUTE-039/061 (information handlers {OJS OMP}), PLUG-003 (information block), PLUG-005 (make-submission block), AFFR-016 (manager Edit links)
- **Note**: masthead *content* derives from role masthead flags (features 45/44 own the flags; this feature owns the page).

### 40. Navigation menus & site chrome
- **Intent**: managers shape the site's menus; every visitor navigates through the resulting header/footer.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-028–035 (menus + items grids, preview), AFFM-218 (site scope), AFFR-001–010 (header, primary/user menus, skip links), AFFR-011–013 (footer/sidebar frame), GRID-037/038, API-028, VUE-066, SET-017/018, PLUG-002 (developedBy block), GRID-064 (page css/tasks ops — reference)

### 41. Custom pages & blocks
- **Intent**: managers publish their own pages and sidebar blocks of arbitrary content.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: ROUTE-019, AFFR-096 (custom NMI page), PLUG-027 {OJS OMP staticPages}, AFFR-097, PLUG-010 + AFFR-093 (custom block manager)

### 42. Highlights
- **Intent**: managers pin promoted items into a home-page carousel.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFM-037–040, AFFM-219 (site scope), AFFR-017 (carousel), API-022, VUE-097, SET-015

### 43. Appearance & website presentation
- **Intent**: managers control how the site looks — theme, logos, home-page composition, list lengths, date formats.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-018–021 (theme/setup/masthead-display/advanced), AFFM-041 (lists), AFFM-043 (date & time), AFFR-025 (home about/homepage image/additional content), AFFR-022/023 {OJS home composition}, AFFR-026-noted OMP blocks (out-of-scope portion), AFFR-027 {OPS home}, PLUG-049 (default theme + options), API-013 (theme endpoints), SET-006

### 44. Languages & locales
- **Intent**: administrators install locales; managers choose which languages the UI, forms and submissions run in; visitors switch language.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-023–027 (context grids), AFFM-214–217 (site grid), AFFM-199, GRID-005/036/043/044, AFFR-089 + PLUG-004 (language toggle block), SET-049
- **Note**: per-publication language change is feature 19's; working languages on the profile are feature 62's.

## E. People, roles & access

### 45. Users management
- **Intent**: managers and admins find user accounts and act on them — edit, email, disable, merge, remove, impersonate.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFM-102–108 (users tab actions incl. login-as, merge, enable/disable), AFFM-203–210 (wizard users grid), ROUTE-017 (editUser op), GRID-050, GRID-003 (suggest username), VUE-051, API-047, MAIL-054, SET-027, ROUTE-016 (signInAsUser/signOutAsUser ops), PLUG-035 (users XML import/export {OJS OMP}), CLI-028 (reference only)
- **Note**: login-as appears on three screens (users, participants, reviewers) — mechanics homed here, instantiations link.

### 46. Roles configuration
- **Intent**: managers define the journal's roles, their stage access and self-registration/masthead behavior.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-109–113 (roles grid + stage toggles), GRID-048, API-046, SET-028, AFFU-078–081 (profile self-registration checkboxes — instantiation), API-047 (endRole/masthead ops)

### 47. User invitations
- **Intent**: a manager invites someone into roles; the recipient accepts (creating or linking an account) or declines.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFM-099–101 (invite/edit/cancel), AFFM-118–120 (send wizard), AFFU-122–137 (accept/decline flow incl. ORCID verify step), ROUTE-013/014, API-024, VUE-001/011/052, MAIL-055/056/057, JOB-013, JOB-051, SET-064
- **Note**: the reviewer one-click access invite rides feature 8; this feature owns the invitation machinery once.

### 48. Login & authentication
- **Intent**: users sign in and out, recover and change passwords, and pass re-authentication gates.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFU-001–008 (login), AFFU-036–048 (lost/reset/forced change), AFFU-049–052 + AFFM-191 (confirm-password gate), ROUTE-016, MAIL-030, SET-057 (captcha/altcha config), SET-052 (partial: password/session keys)

### 49. Registration & account validation
- **Intent**: a visitor registers (site- or journal-level), consents, opts into roles, and validates their email.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFU-009–035 (forms, consents, reviewer opt-in, activation), ROUTE-030, AFFM-116 (site access options form), MAIL-058/059, JOB-053, AFFU-031–034 (registration-complete links)

### 50. User profile
- **Intent**: a user maintains their own identity, contact, roles, public profile, password, notification choices and API key.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFU-053–098 (all tabs), GRID-065, ROUTE-029, AFFU-082 (interests {OJS OMP} — OPS delta), SET-027, CLI-024 (reference only)
- **Note**: the Notifications tab's form renders here (screen ownership) but the *meaning* of each toggle is feature 65's; the API-key gate on `api_key_secret` cross-links system configuration.

### 51. ORCID
- **Intent**: users and authors connect verified ORCID iDs, and the journal deposits works/reviews to ORCID.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFU-099–110 (inline connect, verify/about pages, FieldOrcid), AFFM-117/222 (context + site settings), ROUTE-021, API-029, JOB-017–020, JOB-033/034 {OJS review deposits}, MAIL-027/028/029, PLUG-021 {OPS plugin}, AFFW-502 (send review to ORCID action)

## F. Communication & moderation

### 52. Emails (templates & setup)
- **Intent**: managers customize every email the journal sends and its sending options.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-085 (email setup form), AFFM-121–128 (manage emails page + mailable/template modals), ROUTE-017 (manageEmails), API-018/019/027, VUE-021/072/073, SET-012, SET-053 (config [email]), MAIL-076–083 (orphan alternate templates surfacing only here), CLI-010 (reference only)
- **Note**: individual mailables are claimed by their triggering features; this feature owns the management surface and template mechanics.

### 53. Issues
- **Intent**: {OJS} editors assemble, order, publish and manage issues; readers browse current and back issues.
- **Apps**: {OJS} · **Tier**: H
- **Atoms**: AFFM-243–262 (grids, edit-issue tabs, TOC ordering, publish/unpublish, current), AFFW-740–751 (edit issue modal + issue galleys), GRID-069/070/072/085, ROUTE-040/041, API-053, AFFR-042–045 (current/TOC/archive pages), MAIL-060, NOTIF-055, JOB-031, SET-031, AFFM-166 (issue XML export tab)
- **Note**: merged back-office + reader pages into one feature — the TOC settings and their front-end are one intent. Article-to-issue *assignment fields* render on feature 30's screens (link).

### 54. Reader comments
- **Intent**: readers comment on published articles; moderators approve, hide and handle reports.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-052 (settings form), AFFR-058 (front-end comments UI {OJS mount}), AFFM-141–147 (moderation page + reports), ROUTE-017 (userComments), API-012, VUE-010/082/083, NOTIF-052/053
- **Note**: front-end mount greps OJS-only while moderation is shared — Phase 1 must resolve the OMP/OPS reader-side reality before badging.

### 55. Notify users (bulk email)
- **Intent**: a manager composes an email to whole role groups at once, within site-set limits.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFM-114–115 (notify tab + queued notice), AFFM-201 (restrict per role), AFFM-220 (site bulk form), API-002, JOB-002

## G. Money & access control {mostly OJS}

### 56. Subscriptions & open access control
- **Intent**: {OJS} the journal sells and manages subscriptions, and controls when content becomes openly accessible.
- **Apps**: {OJS} · **Tier**: H
- **Atoms**: AFFM-172–182 + AFFM-239–242 (grids, types, policies, subscriber select), AFFM-096 (Access form — publishing mode; note: form exists in OPS as open-access-only, flag), ROUTE-046, ROUTE-052 (user purchase/renew ops), AFFR-090 (subscription block), AFFR-099–103 (about subscriptions, my subscriptions, purchase forms), GRID-080–084/087, MAIL-063–070, JOB-059, AFFR-047 (restricted galley states — rule source), NOTIF-066 + MAIL-061 + JOB-032/058 (open-access notify), AFFM-250 (per-article open access in TOC), PLUG-006, PLUG-048 (subscription report)

### 57. Payments & APCs
- **Intent**: {OJS} the journal charges fees (APCs, purchases) through configured payment methods.
- **Apps**: {OJS} (paymethod plugins shared with OMP; OMP's use rides the out-of-scope catalog) · **Tier**: M
- **Atoms**: AFFM-094 (payment settings form {OJS OMP}), AFFM-181 (payment types), AFFM-182 (payments grid), AFFW-231–232 (workflow Payments dropdown), ROUTE-045, API-051 (record payment), API-057 (submissionPayment form), MAIL-062, NOTIF-036/047, PLUG-041/042, AFFR-104 (manual payment page), MAIL-075

## H. Site administration & operations

### 58. Hosted journals (site admin)
- **Intent**: a site administrator creates, configures, orders and removes the journals the installation hosts.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-183, AFFM-192–196 (contexts grid + wizard entry + ordering), AFFM-197–202 (wizard tabs), GRID-004, API-013, VUE-014, AFFR-019–021 (multi-journal site home listing), SET-006 + SET-029/035/041 (context schemas)

### 59. Site settings
- **Intent**: the administrator sets site-wide configuration shown and enforced across all journals.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-184, AFFM-211–213 (setup/security/info), AFFM-223–224 (site appearance), AFFM-220–222 (bulk email / statistics / ORCID cross-links), API-035, SET-024
- **Note**: tabs that embed other features' panels (highlights, announcements, nav, plugins, languages) are one-line instantiation atoms here; mechanics live in those features.

### 60. System administration & jobs
- **Intent**: the administrator maintains the running system — caches, sessions, queued/failed jobs, scheduled tasks, version info.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-185–190 (maintenance ops + jobs links), AFFM-230–238 (jobs/failed-jobs/details pages, system info), ROUTE-003, API-026, VUE-005/006/007, JOB-049/052/063–067 (scheduler/queue infra), SET-046/047/048/060/061/062/063 (config sections as reference), CLI-012/021 (reference only)

### 61. Plugins
- **Intent**: managers and admins enable, configure, upload and install plugins at journal and site level.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-044–051 (installed grid + gallery), AFFM-202/228/229 (wizard + site scope), GRID-006/041/077/095/104, NOTIF-009/010, SET-052 (allow_plugin_install keys — reference), CLI-017/011 (reference only)
- **Note**: each bundled plugin's *behavior* is claimed by the feature it serves; this feature owns the management surface.

### 62. Import & export tools
- **Intent**: managers move content in and out of the journal as XML/CSV through the Tools screen.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFM-161–167 (tools tabs, native XML import/export, users XML, issue export {OJS}), AFFM-168 {OJS PubMed}, ROUTE-018, VUE-019, PLUG-032/034/035, GRID-052/071/075/076/078/103/105, CLI-026 (reference only)
- **Note**: ONIX/CSV/monograph-report exporters are OMP-only (D-list).

### 63. Statistics — usage
- **Intent**: editors see how much their content is read/downloaded, filter it, and download reports.
- **Apps**: {OJS OMP OPS} · **Tier**: H
- **Atoms**: AFFU-254–268 (publications stats), AFFU-271–274 (context stats), AFFU-275–282 {OJS issue stats}, AFFM-095/221 (collection settings), AFFR-056 (reader usage chart), ROUTE-026, API-036/038, API-056 {OJS}, VUE-025/026/027, JOB-021–026, JOB-035–044, JOB-048/056/060, PLUG-029 (usage event), SET-016 (institutions link)

### 64. Statistics — editorial activity & reports
- **Intent**: editors track editorial throughput (submissions, acceptance, turnaround) and receive/download activity reports.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFU-247–253 (editorial stats incl. OPS chart removal), AFFU-269–270 (users stats + CSV export), AFFU-283 + AFFM-171 (report plugin links), ROUTE-026, API-037/040, VUE-024/028/080, MAIL-048, NOTIF-049, JOB-015/016/055, PLUG-044/047 (article/review reports)

### 65. Notifications (in-app & email delivery)
- **Intent**: users receive, review and control the notifications the system sends them.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: AFFU-111–117 (tasks panel grid: mark new/read, delete), AFFU-118–121 (email unsubscribe), AFFU-093–095 (preference toggles — form renders on profile), ROUTE-020, GRID-039/40, AFFR-009 (unread badge), AFFW-698–700 (tasks rows), NOTIF-001–007 (toast plumbing — reference)
- **Note**: individual NOTIF-* types are claimed by their triggering features; this feature owns delivery, the panel, preferences semantics and unsubscribe.

### 66. Sections
- **Intent**: managers structure the journal into sections that shape submission choices and reader presentation.
- **Apps**: {OJS OMP OPS} (OMP = Series per APP-GLOSSARY) · **Tier**: M
- **Atoms**: AFFM-003–007 {OJS OPS grids}, AFFM-008–012 {OMP series grid}, GRID-079/096/106, API-034 {OJS}, SET-023 + overlays, AFFR-037 (per-section policy + submit links), AFFW-079 (wizard section context via reconfigure), AFFR-080 {OPS section browse — pending ruling, see UNASSIGNED}
- **Note**: glossary maps section→series as vocabulary, while the capability table says `hasSections: OMP ✗` — the spec must reconcile (hard call #3).

## I. Interoperability & preservation

### 67. OAI-PMH
- **Intent**: aggregators harvest the journal's metadata over the OAI-PMH standard interface.
- **Apps**: {OJS OMP OPS} · **Tier**: M
- **Atoms**: ROUTE-044/064/079, PLUG-036/037 (dc11 + oai_dc), PLUG-038/039 {OJS MARC}, PLUG-040 {OJS JATS}, PLUG-013 {OJS DRIVER}, SET-055

### 68. Search-engine indexing & metadata tags
- **Intent**: managers help external search engines find and rank the journal's content.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFM-093/200 (indexing forms), ROUTE-025 + ROUTE-049/068/084 (sitemap), PLUG-016 (googleScholar), PLUG-014 (dublinCoreMeta {OJS OMP}), PLUG-015 (googleAnalytics)
- **Note**: GA is tracking rather than discovery — kept here as "third-party head-tag integrations" (hard call #9).

### 69. Web feeds
- **Intent**: readers subscribe to the journal's new content by RSS/Atom.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: PLUG-030 + AFFR-094 (web feed block + gateway), ROUTE-037/059/076 (gateway plugin endpoints)
- **Note**: announcement feed rides feature 38.

### 70. Archiving & preservation
- **Intent**: {OJS} the journal preserves its content in LOCKSS/CLOCKSS/PKP PN networks.
- **Apps**: {OJS} · **Tier**: L
- **Atoms**: AFFM-097 (PN form), AFFM-098 (LOCKSS/CLOCKSS form), ROUTE-037 (lockss/clockss gateway ops)

### 71. Institutions
- **Intent**: managers maintain the institution list used by subscriptions and usage statistics.
- **Apps**: {OJS OMP OPS} · **Tier**: L
- **Atoms**: AFFM-137–140, ROUTE-017 (institutions op), API-023, VUE-098, SET-016

---

## Tier tally

- **H (12)**: 1 submissions dashboard · 3 submission wizard · 6 review stage · 7 reviewer assignment & tracking · 8 reviewer's review · 13 decision recording · 15 submission files · 17 tasks & discussions · 25 DOIs · 30 publish & versions · 45 users management · 47 invitations · 53 issues · 56 subscriptions · 63 usage statistics — **15** (over my 12 estimate; flag for maintainer trim — candidates to demote to M: 45 users management, 47 invitations)
- **M (37)**: 2, 4, 5, 9, 10, 11, 12, 14, 19, 20, 21, 26, 28, 29, 32, 34, 36, 37, 38, 39, 40, 43, 44, 46, 48, 49, 50, 51, 52, 54, 57, 58, 59, 60, 61, 62, 64, 65, 66, 67 — **40** as listed
- **L (13)**: 16, 18, 22, 23, 24, 27, 31, 33, 41, 42, 55, 68, 69, 70, 71 — **15**
- **Total features**: 70 (one number, 35, retired by merge into 53).

Rough budget sanity: 15×12 + 40×7 + 15×3.5 ≈ 180 + 280 + 53 ≈ **513 common scenarios**; per app = common + app-specifics, under the ≤700/app ceiling but not by much — supports demoting borderline H's and pruning L's at spec time.

---

## Candidate-UNASSIGNED (fits no feature without force-fitting)

| Atom(s) | Reason |
|---|---|
| AFFR-211 note (`PkpCite`, `PkpOpenReview`, `PkpOrcidDisplay` Vue components; `CiteComponent.php`) | Exist in ui-library, grep to no template/PHP mount — likely in-flight work; no screen to attach to. |
| API-030 (`PeerReviewController`, open peer-review data {OJS}) | Public open-review data API whose display component (`PkpOpenReview`) is unmounted; no reachable screen found in the atlas. |
| API-062 (OMP `publicationPeerReviews` entry point) | Mounts a controller class that does not exist at the swept SHA — dangling mount. |
| VUE-004 (ExamplePage) | ui-library docs fixture, no app mount. |
| VUE-042 (NavigationMenuManager manager component) | Only its form modal (VUE-066) is wired; the manager itself has no mount. |
| ROUTE-043 (OJS legacy `manager` page dispatcher stub) | Switch over old subscription ops that returns no handler — dead dispatcher. |
| AFFM-170 (OMP `statisticsSettingsForm.tpl`) | Template posts to an op no PHP handler declares — dead-code candidate (also OMP-only). |
| AFFW-076 (OJS `sectionPolicy.tpl` start-submission block) | Marked deprecated 3.4 in the sweep; liveness unknown, and current Start Submission is the Vue form. |
| NOTIF-011 (`NOTIFICATION_TYPE_PLUGIN_BASE`) | Base constant with zero references outside its definition. |
| JOB-028/029 (TestJobFailure/TestJobSuccess) | Queue smoke-test fixtures, not product behavior. |
| API-066 (OPS legacy non-versioned `api/genres/` entry point) | Legacy alias mount outside `/v1/`; dead-code candidate pending liveness. |
| ROUTE-012 + CLI-027/031 (web installer / upgrade surfaces) | Real surfaces, but testing them means reinstalling the app — needs a maintainer ruling whether "Installation & upgrade" becomes a feature or is ruled out of test scope. |
| ROUTE-081/082 + AFFR-079/080 (OPS `preprints` archive listing + section browse pages) | OPS-only browse surfaces OJS never exposes. Glossary maps OJS "Archive (back issues)" → OPS "Preprints archive listing", which reads as a counterpart *feature* (out of scope like the catalog) — but unlike the catalog these are trivially small. Parked for a ruling: out-of-scope counterpart vs. app-specific scenarios inside features 53/66. |
| AFFM-064 (OPS author-screening settings tab) | OPS-only tab, rendered only when screening plugins register rules; no bundled plugin in the atlas registers any — dead-by-default surface, and OPS-only. |
| SET-050/051/056/058/059 + misc config keys not claimed above | Pure deployment configuration with no user-facing behavior to specify; propose blanket out-of-scope-with-reason in `settings.md`'s header (mirroring the cli ruling) rather than forced claims. |

## Out-of-scope candidates (CHARTER: surfaces OJS never exposes; dropped not parked)

- **Internal Review stage (OMP)**: AFFW-248, AFFW-301, AFFW-304–322, AFFW-345–348, ROUTE-072 (`internalReview` op only), MAIL-071, NOTIF-015/020/030 (internal-stage types), GLOSSARY internal-review rows. (CHARTER names `*_INTERNAL` decisions explicitly.)
- **Chapters (OMP)**: AFFW-111, AFFW-123–124, AFFW-275, AFFW-425, AFFW-572, AFFW-772–777, GRID-097, VUE-031, AFFR-075 (chapter blocks)/AFFR-077, API-058 (chapter DOI row), AFFU-245 (chapter row type).
- **Publication formats (OMP)**: AFFW-276, AFFW-426, AFFW-573, AFFW-754–769, GRID-092, VUE-044, AFFR-076, AFFM-608-family format branches, SET-040 (format-file overlay props).
- **Catalog (OMP)**: ROUTE-055/056/062, VUE-020/101, AFFM-263–271, AFFR-026 (featured/new releases), AFFR-070/071/073/074, AFFR-087 (OMP browse block), API-059 (catalog flags), GRID-099 (monograph covers), AFFW-427 (catalog entry tab), AFFW-424 (catalogEntry insert-content half).
- **OMP marketing & supply chain**: AFFW-237–239 (work type), AFFW-257–258, AFFW-431–434, AFFW-574, GRID-089/090/091/093/094, VUE-045.
- **OMP direct sales / approved proofs**: AFFW-770–771, AFFM-094's OMP use (direct sales), OMP paymethod usage.
- **OMP-only exporters**: PLUG-031 (CSV), PLUG-033 (ONIX 3.0), PLUG-046 (monograph report), AFFM-169.
- **`NOTIFICATION_TYPE_BOOK_*` (OJS books-for-review remnants)**: NOTIF-056–065 (CHARTER names them; no UI in any sweep).
- **OPS-only Vue surfaces unwired in OJS**: AFFW-385–386 (relation dropdown), AFFW-128 (review-relation panel), API-065 (`relate` endpoint), AFFR-082's relation notice; PLUG-024 (preprintToJournal).
- **OMP HTML monograph viewer**: PLUG-018, AFFR-051.

## Hardest grouping calls (feed the disagreement register)

1. **OMP monograph landing inside "Article landing page" (32) vs. out-of-scope catalog.** The shared details atoms (AFFR-052–057, 059, 061–065) are marked `ojs omp ops`, so the landing feature claims them and covers OMP's book page as the app variant — while chapters/format blocks and catalog browse stay out of scope. Rejected alternative: pushing the whole OMP book page out with the catalog — it would orphan shared atoms and break multi-app rule 1 for a feature OJS plainly has.
2. **Composer + FileAttacher homed in "Editorial decision recording" (13), not their own feature.** They serve decisions, discussions, invitations and bulk email; a standalone "email composer" feature has no manager-facing name and no QA scenario of its own. Rejected: own feature (violates the QA litmus); per-consumer duplication (violates rule 8).
3. **One "Sections" feature (66) covering OMP Series via glossary substitution.** APP-GLOSSARY's vocabulary table maps section→series (not "—"), but the capability table says `hasSections: OMP ✗`. I chose one feature with an OMP divergence block. Rejected: series as OMP-only out-of-scope (contradicts the glossary's substitution row); separate series feature (pure parameterization, fails graduation rule 7).
4. **No "Workflow settings"/"Distribution settings" screen-features — settings distributed to the features they configure.** Follows the brief ("a settings form + its front-end effect = one intent") and TEMPLATE's Settings-that-modify-behavior section. Rejected: one feature per settings screen (code/screen-module grouping, fails the intent test; QA would never test "the distribution tab" as one thing). Cost: settings screens are sliced across many specs — the crosswalk stage must verify every tab atom found a home.
5. **Review stage (6) split from Reviewer assignment & tracking (7), both on the same screen.** The litmus: QA tests "run a round / record decisions" separately from "invite and manage reviewers", and the reviewer-manager mechanics recur on OMP internal review (out of scope) and the dashboard popovers. Rejected: one mega-feature (would blow past H's 13-scenario ceiling).
6. **Issues (53) merged with reader issue pages, while Publishing (30) keeps the issue-assignment fields.** Ownership-follows-the-screen: the assignment radio/select render in the schedule-for-publication modal (30's screen); TOC/back-issue surfaces are the issue feature's. Rejected: an "issue assignment" micro-feature (can't fill 3 scenarios alone) and issues-owning-the-modal-fields (would split one modal between two specs).
7. **JATS (28), Body Text (29) and Media files (27) as three features, not one "full text" feature.** Different tabs, different user jobs (archival XML vs. authoring vs. asset management), and Media is all-apps while the other two are OJS-only — one feature would need three badges internally. Rejected: merge (badge soup, and Body Text alone sustains M).
8. **Notification preferences: form on the profile screen (50) but semantics owned by Notifications (65).** Screen-ownership says profile; variance-ownership says the per-type meaning is delivery behavior. Split: profile owns the tab/form shell, notifications owns what each toggle does. Rejected: all-in-profile (each type's meaning restated away from its delivery mechanics) — this is the taxonomy's most fragile seam.
9. **Google Analytics under "Search-engine indexing & metadata tags" (68).** It's tracking, not discovery, but it is the same mechanical surface (head-tag injection + tiny settings form) and can't sustain a feature alone. Rejected: own L feature (fails the 3-scenario floor); appearance feature (not presentation).
10. **OPS preprints archive/sections browse parked instead of assigned.** Both readings are defensible (out-of-scope counterpart of the OJS archive, per the glossary counterpart rule; or small OPS-specific scenarios inside 53/66) — force-fitting either way is worse than a ruling. Parked in UNASSIGNED.

## Coverage sanity note

Every AFFW/AFFM/AFFR/AFFU section of the atlas was visited; entry-point modalities were attached feature-by-feature. Families deliberately handled by blanket rules rather than per-feature attachment: CLI (out of scope by ruling; reference-only citations noted), config.TEMPLATE sections without user-facing behavior (proposed blanket reason, see UNASSIGNED), NOTIF toast plumbing (NOTIF-001–007 as reference under 65), and grid-framework chrome atoms (AFFW-712–734) which are UI infrastructure shared by every legacy grid — proposed to be claimed by feature 15 or a one-line "legacy grid chrome" reference block in whichever spec first needs them (flag for the FEATURE-MAP author; they must not be forgotten by the crosswalk).
