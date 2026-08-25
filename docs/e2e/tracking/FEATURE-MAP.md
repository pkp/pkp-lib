# FEATURE-MAP — the campaign's unified feature list (Phase 0 deliverable)

This file is the single home for the campaign's feature taxonomy: **70 features
(U01–U70)** with every atlas atom assigned. Every spec session starts here: pick a
feature row, claim its listed atoms, write the spec per `TEMPLATE.md`.

- **Invariant (atom claim — RUNBOOK "Mission, scope & invariants")**: every atlas atom lands in **exactly one**
  of: a feature section below, the **Out of scope** section at the tail of this
  file, or `UNASSIGNED.md`. The unclaimed count is the campaign's completeness
  metric. This map accounts for all **2,163** atoms:
  **1,972 feature-assigned · 173 out of scope · 18 parked in UNASSIGNED.md** (13 at Phase-0 close; 5 U26-derived dead author-dashboard atoms parked later).
- **Atom IDs and modality conventions**: see `atlas/README.md`. IDs are dense per
  modality and never renumbered; ranges here (`AFFW-068..075`) are inclusive.
- **Sources** (removed from the tip 2026-08-25, reachable in git history —
  RUNBOOK ".reports/ retention"): `.reports/phase0-feature-map/synthesis.md` §1 (feature list and
  order) as amended by `.reports/phase0-feature-map/RULINGS.md` (all D-leans
  accepted; Q1–Q5 as ruled; D2 superseded; U68–U70 added), atomized by the six
  crosswalk files in `.reports/phase0-feature-map/`.
- **U68–U70** (maintainer scope extension, 2026-07-27) are NOT folded into
  section F: they get their own **section G — OMP catalog**, since they are
  OMP-specific features created by a scope extension, not part of the
  three-lens consensus taxonomy.
- **Tiers** (RUNBOOK scenario budgets): **14 H · 42 M · 14 L**. Apps badge is
  `{OJS OMP OPS}` unless shown.
- **`Notes:` lines** carry cross-feature *riders*: where a mixed-payload atom is
  claimed by one feature but serves another, both features carry a one-line
  note. Riders are claims on *portions*, never on the atom — the atom has one
  owner. Every `?` flag from the crosswalks is resolved in the
  **Flag resolutions** appendix; none is silently dropped.

---

## A. Accounts & identity

### U01 — Login & sessions {OJS OMP OPS} · M
A user signs in/out, recovers and changes passwords, passes re-authentication gates, and (admin) logs in as another user.
Atoms: AFFW-467, 470, 473, 499 · AFFM-105, 191, 209 · AFFU-001..008, 036..052 · ROUTE-016 · MAIL-030 · SET-052
Notes: log-in-as claimed here on every offering surface (D17); password flows folded in (D16). Riders: SET-052's plugin-install keys are cited by U62; SET-057's login-captcha keys (owned by U02) are cited here; ROUTE-003's confirmAccess ops and ROUTE-028's authorizationDenied op (owned by U61/U03) are cited here.

### U02 — Registration & account validation {OJS OMP OPS} · M
A visitor registers (site- or journal-level), consents, opts into roles, validates their email.
Atoms: AFFU-009..035 · ROUTE-030 · MAIL-058..059 · SET-057 · JOB-053
Notes: riders — SET-053's `require_validation`/`validation_timeout` keys (owned by U56) gate this flow; SET-057's login-captcha portion is cited by U01.

### U03 — User profile {OJS OMP OPS} · M
A user maintains identity, contact, roles, public profile, password tab, notification choices, API key.
Atoms: AFFU-053..064, 067..092, 096..098 · ROUTE-028..029, 071, 087 · GRID-065 · MAIL-003 · SET-027
Notes: owns the Notifications *tab* shell; the toggles' semantics (AFFU-093..095) are owned by U05 — the taxonomy's flagged fragile seam. Rider: ROUTE-028's authorizationDenied op is cited by U01.

### U04 — ORCID integration {OJS OMP OPS} · M
Users and authors connect verified iDs; the journal deposits works/reviews to ORCID.
Atoms: AFFW-502 · AFFM-117, 222 · AFFU-065..066, 099..110 · ROUTE-021 · API-029 · MAIL-027..029 · JOB-017..020, 033..034 · PLUG-021
Notes: riders — AFFW-506 (owned by U27) bundles an ORCID-deposit confirm cited here; API-032's sendToOrcid op (owned by U30) cited here. OPS plugin packaging is an install-fact divergence.

### U05 — Notifications center & email preferences {OJS OMP OPS} · M
Users receive, review, and control in-app and email notifications, incl. one-click unsubscribe.
Atoms: AFFW-698..700 · AFFU-093..095, 111..121 · AFFR-009 · ROUTE-020 · GRID-039..040 · NOTIF-001..007
Notes: individual NOTIF-* types live with their emitting features; the generic framework toasts (NOTIF-001..007) are homed here. Riders: AFFR-009 renders inside U08's chrome; GRID-064's `tasks` op (owned by U10) serves the header widget.

### U06 — User invitations {OJS OMP OPS} · M
A manager invites someone into roles; the recipient accepts (creating/linking an account) or declines.
Atoms: AFFM-099..101, 118..120 · AFFU-122..137, 206 · ROUTE-013..014 · VUE-001, 011, 052 · API-024 · MAIL-055 · SET-064 · JOB-013, 051
Notes: AFFU-206 (generic invitation-URL landing) also serves the reviewer one-click key link — U28 cites it. Rider: ROUTE-017's `editUser` op (owned by U07) is the wizard's second entry point — the users-table "Edit" route — and its `access` op mounts VUE-052; cited here, not claimed. MAIL-055's `ojs omp` badge is deliberate (OPS omits the mailable from its map, so the template has no row on the OPS emails screen though sending works) — see the spec's OPS1 finding.

## B. Reader & public site

### U07 — Journal identity & about pages {OJS OMP OPS} · M
Managers describe the journal (masthead, contact, policies, information pages); readers consult the About pages.
Atoms: AFFM-001..002, 022, 042 · AFFR-032..034, 038..041, 088 · ROUTE-001..002, 017, 039, 042, 061, 063, 078 · VUE-022 · PLUG-003
Notes: ROUTE-017/042/063/078 and VUE-022 are multi-feature settings dispatchers — dispatcher atoms need one bookkeeping owner and are homed here; the riders fan the ops out to their features (riders in U06, U10, U58, U29, U37, U53, U54, U56, U14, U66, U12, U40). Rider: AFFR-035 (owned by U58) renders on an About page. Rider: ROUTE-017's `editUser` op is the second entry point into the send-invitation wizard and its `access` op mounts VUE-052 — both cited by U06.

### U08 — Navigation menus & site chrome {OJS OMP OPS} · M
Managers shape menus; every visitor navigates the resulting header/footer frame.
Atoms: AFFM-028..034, 218 · AFFR-001..008, 010..016, 018, 091, 098 · GRID-037..038 · VUE-066 · API-028 · SET-017..018 · PLUG-002
Notes: chrome rides here per D14 (S shape). Riders: AFFR-009's badge state is U05's; ROUTE-019's preview op (owned by U09) serves menu management; GRID-064's frame portion (owned by U10) cited here. VUE-042 (the unmounted manager component) is parked in UNASSIGNED.

### U09 — Custom pages & blocks {OJS OMP OPS} · L
Managers publish their own pages (static pages, custom NMI pages) and sidebar blocks of arbitrary content.
Atoms: AFFM-035 · AFFR-093, 096..097 · ROUTE-019 · API-007 · PLUG-010, 027
Notes: API-007 is the generic public-file upload behind rich-text image fields across all manager-authored content — consuming features cite it. Rider: ROUTE-019's preview op is U08's.

### U10 — Appearance & theming {OJS OMP OPS} · M
Managers control how the site looks: theme + options, logos, home-page composition, lists, date formats.
Atoms: AFFM-018..021, 041, 043, 198 · AFFR-023, 025, 027 · ROUTE-011, 038, 060, 077 · GRID-064 · PLUG-049
Notes: riders — GRID-064's `tasks` op serves U05 and its frame serves U08; AFFR-027's archive-header portion rides AFFR-078 (U17); API-013's theme sub-op (owned by U59) cited here.

### U11 — Highlights {OJS OMP OPS} · L
Managers pin promoted items into a home-page carousel (journal or site scope).
Atoms: AFFM-037..040, 219 · AFFR-017 · VUE-097 · API-022 · SET-015

### U12 — Announcements {OJS OMP OPS} · M
The journal posts news; readers browse; subscribed users are notified.
Atoms: AFFM-036, 129..136, 225..227 · AFFR-028..031, 095 · ROUTE-004 · GRID-007 · VUE-092 · API-008 · MAIL-001 · NOTIF-008 · SET-002 · JOB-014 · PLUG-007
Notes: the announcement web feed rides here (majority call recorded at U12/U18 in synthesis).

### U13 — Article landing page & reading {OJS OPS} · H
A reader lands on a published item, sees metadata/authors/versions/citation/license, and opens or downloads its files in the right viewer.
Atoms: AFFR-046..050, 052..057, 059, 061..062, 066..068, 081..082 · ROUTE-033, 080 · PLUG-008, 017, 020, 022..023, 025..026
Notes: OMP counterpart feature is **U69** (RULINGS: D2 superseded — one counterpart line, no absence paragraph). Forked-copy feature (RUNBOOK rule 7): OJS↔OPS surfaces are app-side copies — shared claims need cross-app probe evidence, chain check does not apply. Riders: AFFR-056's chart *display* is claimed here, its stats semantics are U64's; references display (AFFR-057) claimed here per rule 6 — U42 cites it; license display (AFFR-065) owned by U40; Crossmark (AFFR-069) owned by U45; AFFR-082's relation-notice portion is OOS (OPS preprint relations), marked at spec time; the OMP exposure of the shared landing atoms (AFFR-048, 052..057, 061, 066 · PLUG-008, 022) is covered by U69's rider.

### U14 — Reader comments & moderation {OJS} · M
Readers comment on published articles; moderators approve, hide, and handle reports.
Atoms: AFFM-052, 141..147 · AFFR-058 · VUE-010, 082..083 · API-012 · NOTIF-052..053
Notes: badge may widen — Phase-1 probe (front-end mount greps OJS-only, AFFR-058); the moderation machinery is shared code (synthesis).

### U15 — Search {OJS OMP OPS} · M
Readers search published content and refine results; the index stays current.
Atoms: AFFR-083..085 · ROUTE-024, 048, 067, 083 · SET-054 · JOB-027
Notes: rider — AFFR-078's search-form portion (owned by U17) cited here. App-side template/adapter copies — probe the copies (search page templates AFFR-083/084 are `<app>` copies; OMP's separate AFFR-085).

### U16 — Categories {OJS OMP OPS} · M
Managers build a browsable category tree; readers browse by category.
Atoms: AFFM-013..017 · AFFR-024, 072, 086 · ROUTE-006 · VUE-030, 054 · API-010 · SET-004 · PLUG-001
Notes: browse-by-category is a shared three-app surface incl. OMP (RULINGS — it does NOT belong to U68). Rider: AFFR-072's OMP New-Releases rows are U68's (RULINGS). Rider: PLUG-001's OMP series-browse links are a U68 register note.

### U17 — Sections {OJS OMP OPS} · M
Managers structure the journal into sections (OMP: series) shaping submission choices and reader presentation.
Atoms: AFFM-003..012 · AFFR-037, 078..080 · ROUTE-081..082 · GRID-079, 096, 106 · API-034 · SET-023, 033, 038, 044
Notes: OMP series covered via glossary substitution (Q3a); OPS preprints-archive + section-browse pages are in-scope OPS-specific scenarios here (Q4a: ROUTE-081..082, AFFR-079..080). OMP reader browse-by-series belongs to U68, not here. Riders: AFFR-078's search/category portions cited by U15/U16; GRID-049 (owned by U58) is the section grid's base. Dead-code notes attached in UNASSIGNED: `filterByTypeIds` dispatch, `api/v1/sections` mount gap, OMP dashboard series-filter gap.

### U18 — Web feeds {OJS OMP OPS} · L
Readers subscribe to new content by RSS/Atom.
Atoms: AFFR-094 · ROUTE-037, 059, 076 · PLUG-030
Notes: ROUTE-037 homed here with 059/076 (generic gateway dispatchers; the primary reachable consumer in all three apps is the feed gateway) — the OJS fork's extra lockss/clockss manifest ops belong to U67 (rider). App-side template/adapter copies — probe the copies (per-app webFeed plugin trees, PLUG-030).

### U19 — OAI-PMH {OJS OMP OPS} · M
Aggregators harvest metadata over the standard interface in each supported format.
Atoms: ROUTE-044, 064, 079 · SET-055 · PLUG-013, 036..040
Notes: app-side template/adapter copies — probe the copies (app-side OAI data adapters behind the shared protocol layer).

### U20 — Search-engine metadata & analytics {OJS OMP OPS} · L
Sitemaps, indexing meta tags, and analytics head-tags so third parties find and measure content.
Atoms: AFFM-093, 200 · ROUTE-025, 049, 068, 084 · PLUG-014..016

## C. Submitting & dashboards

### U21 — Submission wizard {OJS OMP OPS} · H
An author starts, fills, saves-for-later, reconfigures, cancels, and submits a new submission.
Atoms: AFFW-065, 068..075, 077..110, 112..122, 125..127, 129..132 · AFFR-092 · ROUTE-027, 051, 070, 086 · VUE-023, 029, 081 · MAIL-049..053, 074 · NOTIF-012 · SET-025, 034, 039, 045 · PLUG-005
Notes: panel mechanics live in U36/U41; the wizard owns step flow + gates. Rider: API-042's submit/saveForLater endpoint clusters (owned by U24) serve this feature.

### U22 — My Submissions (author dashboard) {OJS OMP OPS} · L
An author tracks their submissions, acts on requests, re-enters incomplete ones; owns the author's entry route into the workflow (TEMPLATE rule 9).
Atoms: AFFW-027, 040, 048..049
Notes: riders — the list's route/page component ride ROUTE-007..008 and VUE-003 (owned by U23); this feature cites its views there.

### U23 — Submissions dashboard (editorial) {OJS OMP OPS} · H
Editors, managers and assistants find, filter, triage and open submissions; views, search, activity cells, bulk cleanup.
Atoms: AFFW-001..026, 028, 030..039, 041..047, 051..056, 058..064, 066..067 · ROUTE-007..008, 035, 057, 074 · VUE-003, 075 · API-006 · MAIL-025 · JOB-011, 046
Notes: ROUTE-007..008 and VUE-003 also serve the mySubmissions (U22) and reviewAssignments (U28, per D4) views — riders both ways.

## D. The workflow

### U24 — Workflow screen & stage access {OJS OMP OPS} · M
Any participant (author included) opens a submission's workflow and reaches the stages, tabs and tools their role allows; mechanism home for stage access.
Atoms: AFFW-226..230, 233..234, 240..242, 244..247, 249..255, 259..274, 277..280, 282..284, 377..378, 454..456, 707 · ROUTE-005, 031, 034, 053..054, 072..073, 088 · VUE-012 · API-042
Notes: API-042 (omnibus submissions/publications controller) is homed here; U21, U34, U40, U41 and U49 cite their endpoint clusters — riders both ways. ROUTE-072's added internalReview op is OOS (OMP Internal Review), marked at spec time.

### U25 — Submission stage {OJS OMP OPS} · M
The team screens a new submission and moves it onward (to review / skipped / declined) or removes it.
Atoms: AFFW-286..300, 302..303, 706 · GRID-032..033
Notes: OMP skip-internal routing (within AFFW-296..300) is in-scope parameterization; the Internal Review stage itself is OOS.

### U26 — Review stage & rounds {OJS OMP} · H
Editors run review rounds (round status, files, revisions, round decisions); authors follow and respond on the same screen.
Atoms: AFFW-323..325, 327..330, 332..339, 349..353, 355, 487, 666..667, 701..704 · GRID-010..011, 024..025, 027, 029, 053 · MAIL-047 · NOTIF-029, 031 · SET-021
Notes: owns the round's recommendation *display* per D18 (recording → U34, toggle → U35). Rider: API-032's history op (owned by U30) cited here.

### U27 — Reviewer assignment & management {OJS OMP} · H
An editor finds, invites, edits, reminds, thanks, unassigns, reinstates reviewers and reads their reviews.
Atoms: AFFW-213..215, 217..224, 486, 488..498, 500..501, 503..506, 621..665, 668, 708 · GRID-012, 026, 086, 098 · VUE-016, 046, 067..068 · API-048 · MAIL-026, 034, 038..046 · NOTIF-019, 048 · SET-020 · JOB-012
Notes: automatic reminders (MAIL-043, 046) are sent here; their clocks are configured in U29 (JOB-054). Riders: AFFW-506's ORCID-deposit confirm cited by U04; API-047's GET-reviewers sub-op (owned by U53) cited here.

### U28 — Reviewer's review {OJS OMP} · H
A reviewer sees assignments, responds to a request, completes the wizard (or declines), submits, and revisits past rounds.
Atoms: AFFW-029, 050, 057 · AFFU-138..205, 207..209 · ROUTE-022, 047, 066 · GRID-013, 028, 063 · VUE-009, 076 · MAIL-035..037 · NOTIF-013
Notes: the reviewer dashboard list is folded here per D4 — it cites ROUTE-008/VUE-003 (U23) for its view; OPS listing behavior needs a Phase-1 probe. One-click entry: the landing atom AFFU-206 is owned by U06, cited here. Review-form elements render here (AFFU-170..175); they are built in U29 (config→effect cross-link). Rider: API-032's confirmReview op (owned by U30) cited here.

### U29 — Review setup & review forms {OJS OMP} · M
A manager configures how review runs: mode, deadlines, reminders, guidance, review forms, recommendation options.
Atoms: AFFW-669..670 · AFFM-065..081 · GRID-046..047, 059..060 · VUE-047, 069 · API-054 · JOB-054
Notes: owns the reminder clocks (JOB-054); the reminder sends live with U27 (MAIL-043, 046). Review-form elements' rendering in the wizard is U28's (AFFU-170..175).

### U30 — Author response to reviews {OJS} · M
An editor requests, and an author submits, a formal response to a review round.
Atoms: AFFW-326, 354, 577..585 · ROUTE-023 · VUE-008, 049, 070 · API-032 · MAIL-033
Notes: badge may widen to OMP — the API is mounted ojs+omp and AFFW-326 is swept all three apps; Phase-1 resolves. API-032's confirmReview / history / export+sendToOrcid ops are cited by U28 / U26 / U04 (riders).

### U31 — Reviewer suggestions {OJS OMP} · L
An author suggests reviewers at submission; an editor sees and acts on them during review.
Atoms: AFFW-157..160, 216, 225, 575..576 · VUE-048, 099 · API-031
Notes: own mechanism feature per D7 — wizard and review stage own only their instantiation gates.

### U32 — Copyediting stage {OJS OMP} · M
Editor, copyeditor and author move accepted submissions through copyediting.
Atoms: AFFW-356..363, 705 · GRID-014..015, 019..020 · NOTIF-032, 042..043

### U33 — Production stage {OJS OMP OPS} · M
The team readies final files and hands over to scheduling/publication; OPS's whole editorial flow (decline/revert) lives here.
Atoms: AFFW-243, 364..376, 429 · GRID-022..023 · NOTIF-033..034, 044..045
Notes: AFFW-429 (OPS author discussions-panel presence) homed here; panel internals are U37's.

### U34 — Editorial decision recording {OJS OMP OPS} · H
An editor records any decision/recommendation through the guided wizard (notification email, file promotion); mechanism home for the email Composer + FileAttacher.
Atoms: AFFW-161..212, 331, 340..344, 460..462 · ROUTE-009 · VUE-017, 086..089 · MAIL-004..019, 032 · NOTIF-021..028 · SET-009
Notes: owns the recommend-only *recording* flow per D18 (display → U26, toggle → U35). Riders: API-042's decisions cluster (owned by U24) cited here; the alternate templates MAIL-076..083 (owned by U37) surface only in this Composer's template picker.

### U35 — Stage participants {OJS OMP OPS} · M
Editors control who works on a submission per stage: assign, notify, restrict (recommend-only, metadata), remove.
Atoms: AFFW-466, 468..469, 471..472, 671..680 · GRID-054..055 · VUE-043 · MAIL-024 · NOTIF-014, 016..018, 039, 046
Notes: owns the recommend-only *toggle* (D18) with one side-effect line each way (U34/U26).

### U36 — Submission files {OJS OMP OPS} · H
Anyone with stage access uploads, revises, organizes, inspects and downloads submission files; mechanism home for the file manager + upload wizard.
Atoms: AFFW-133..145, 474..478, 480..485, 586..599, 609..614 · GRID-001..002, 016..018, 031, 035, 066 · VUE-038, 053, 100 · API-043, 045 · SET-026
Notes: Genre *config* lives in U58; its effects are tested here. Rider: AFFW-479's row action mounts U48's send-to-editor mechanism.

### U37 — Tasks & discussions {OJS OMP OPS} · H
Participants (reviewer included) coordinate through discussions and trackable tasks with due dates and templates.
Atoms: AFFW-507..523 · AFFM-086..089 · VUE-036..037, 050, 058..060, 071 · API-017, 044 · MAIL-020..023, 076..083 · NOTIF-040..041
Notes: task-template settings owned here. MAIL-076..083 are orphan `alternateTo` templates homed here per D19 — their only surface is U34's Composer picker (rider); MAIL-080..081 get an OMP-only availability line at spec time.

### U38 — Submission activity log & notes {OJS OMP OPS} · L
Staff consult a submission's event history and logged emails, and keep internal notes.
Atoms: AFFW-235, 687..697 · GRID-008..009, 056..058 · API-018 · SET-011, 013

### U39 — Submission & Publisher Libraries {OJS OMP OPS} · L
Reusable documents at journal level and per submission, shared with participants.
Atoms: AFFW-236, 615..620 · AFFM-082..084 · ROUTE-015 · GRID-021, 030, 034, 045, 061 · API-004

## E. The publication

### U40 — Publication metadata {OJS OMP OPS} · M
Authors (pre-publication) and editors maintain title & abstract, descriptive metadata, submission language, and license/permissions; owns published-state edit locks.
Atoms: AFFW-281, 285, 379..382, 395, 397, 400, 421, 430, 457..459, 711 · AFFM-090, 163 · AFFR-065 · VUE-085, 090 · API-049, 061 · SET-019, 032, 037, 043
Notes: license + data-availability statement folded here (D10/D11); the reset-permissions tool (AFFM-163) is the license fold's settings delta — it cites ROUTE-018's permissions ops and AFFM-161's tab bar (owned by U63). Riders: API-061's catalogEntry component cited by U70; API-042's component-forms cluster (owned by U24) cited here.

### U41 — Contributors & affiliations {OJS OMP OPS} · M
Maintain who authored the work: order, roles, ROR-backed affiliations, principal contact, everywhere the list appears.
Atoms: AFFW-146..156, 396, 532, 681..686 · AFFM-061..063 · GRID-051 · VUE-033..034, 056, 093..094 · API-014, 033 · SET-001, 003, 007, 022 · JOB-057
Notes: ROR lookup mechanics homed here (API-033, SET-022) — U66 links. Rider: API-042's contributors cluster (owned by U24) cited here.

### U42 — Citations & references {OJS OMP OPS} · M
Capture reference lists and data citations, structure them via lookups, show them to readers.
Atoms: AFFW-398..399, 533..550 · VUE-032, 035, 055, 057 · API-011, 015 · SET-005, 008 · JOB-003..007, 062
Notes: data citations ride here per D11. Riders: the reader-side references display (AFFR-057) is owned by U13 per rule 6 (critic review 2026-07-27) — cited here as this feature's reader surface; JOB-062 (citation-DOI enrichment) ships inside U45's Crossref plugin.

### U43 — Funding {OJS OMP OPS} · L
Record funders/grants and disclose them to readers.
Atoms: AFFW-401, 551..557 · AFFR-063 · VUE-039, 061 · API-020 · SET-014
Notes: AFFR-063's data-availability/funding-statement portion sits on the U40/U13 seam per D11 (rider).

### U44 — Identifiers (publisher IDs & URN) {OJS OMP OPS} · M
Assign/clear non-DOI public identifiers on publications, galleys, files.
Atoms: AFFW-402, 600..605, 607, 741, 751 · AFFM-255 · AFFR-064 · GRID-067, 088, 100 · PLUG-043
Notes: issue pub-ids ride the same mechanism (AFFW-741, 751, AFFM-255) — rule 8; U50 owns the hosting forms and keeps side-effect lines for the AFFW-605/606 publish seam.

### U45 — DOIs {OJS OMP OPS} · H
Configure, assign, manage, register DOIs with an agency; track statuses and errors across object types.
Atoms: AFFM-091..092, 148..160 · AFFU-210..244, 246 · AFFR-069 · ROUTE-010, 036, 058, 075 · VUE-018, 095..096 · API-001, 016, 050, 052, 058, 063 · SET-010 · JOB-008..010, 030, 045 · PLUG-009, 011
Notes: registration-agency plugin settings owned here; Crossmark (AFFR-069) renders on U13's screen (rider). API-058's OMP chapter/publicationFormat DOI rows are OOS objects, marked at spec time; AFFU-245 (OOS) has its `publication` row covered as a U45 OMP-variant line, not a claim. Rider: JOB-062 (owned by U42) ships inside the Crossref plugin.

### U46 — Galleys {OJS OPS} · M
Editors attach, label and order the publishable files a reader will open.
Atoms: AFFW-419, 524..531, 735..739, 752..753 · GRID-068, 101 · VUE-040 · SET-030, 042
Notes: separate from U33 per D8; OMP's counterpart (publication formats) is out of scope. Forked-copy feature (RUNBOOK rule 7): OJS↔OPS surfaces are app-side copies — shared claims need cross-app probe evidence, chain check does not apply.

### U47 — Media files {OJS OMP OPS} · L
Manage image/media assets of a publication and link them into full-text displays.
Atoms: AFFW-420, 558..571 · VUE-041, 062..065 · API-041

### U48 — JATS & Body Text {OJS} · M
Keep a JATS XML representation (auto/uploaded, optionally public) and author/convert the article's full text in the built-in editor.
Atoms: AFFW-403..418, 463..465, 479 · AFFR-060 · API-009, 025 · PLUG-019
Notes: AFFW-479 (FileManager send-to-text-editor row action) is the Body Text import mechanism claimed here per D12; U36 mounts the action (rider).

### U49 — Publish, schedule & versions {OJS OMP OPS} · H
Editors publish/schedule/unschedule/unpublish and manage versions; OJS issue-assignment fields render here; OPS "Post the preprint" is the app variant.
Atoms: AFFW-256, 383..384, 387..394, 422..424, 428, 435..453, 709..710 · GRID-062, 107 · VUE-084, 091 · API-057, 065 · MAIL-002, 031, 072..073 · NOTIF-035, 037, 050, 054 · JOB-050
Notes: the publish modal owns issue-assignment fields (API-057; U52 cites its submissionPayment portion). API-065's `relate` portion is OOS (OPS preprint relations), marked at spec time. AFFW-424's OMP catalogEntry half is U70 spec-side variance (rider both ways). Riders: API-042's publish/versions cluster (owned by U24) cited here; NOTIF-038 (owned by U70) is mentionable here as a register note per synthesis.

## F. Communication, money & administration

### U50 — Issues {OJS} · H
Editors assemble, order, publish and manage issues and issue galleys; readers browse the current issue, TOC and back archive.
Atoms: AFFW-606, 740, 743..747, 749..750 · AFFM-243..254, 256..262 · AFFR-020, 022, 042..045 · ROUTE-040..041 · GRID-069..070, 072, 085 · API-053 · MAIL-060 · NOTIF-055 · SET-031 · JOB-031
Notes: one merged feature per D3. The issue Identifiers mechanism is U44's (AFFM-255, AFFW-741/751 — riders); the issue Access tab (AFFM-256) and per-article OA toggle (AFFM-250) are claimed here as issue-TOC surfaces implementing U51's rules (rider to U51); AFFW-605 (U44's assign-pub-ids modal) posts the issue-publish action — side-effect line here.

### U51 — Subscriptions & open access control {OJS} · H
The journal sells/manages subscriptions and controls when content becomes openly accessible; owns restricted-access rules incl. publishing mode.
Atoms: AFFW-742, 748 · AFFM-096, 172..180, 239..242 · AFFR-090, 099..103 · ROUTE-032, 046, 052 · GRID-080..082, 084, 087 · MAIL-061, 063..070 · NOTIF-066 · JOB-032, 058..059 · PLUG-006
Notes: publishing-mode/Access form (AFFM-096) and open-access notify (MAIL-061) homed here per synthesis. Riders: ROUTE-046's paymentTypes/payments ops and ROUTE-052's payMembership op cited by U52; the issue-surface instantiations of its rules (AFFM-250, 256) are owned by U50.

### U52 — Payments & APCs {OJS} · M
The journal charges fees through configured payment methods.
Atoms: AFFW-231..232 · AFFM-094, 181..182 · AFFR-104 · ROUTE-045 · GRID-083 · API-005, 051 · MAIL-062, 075 · NOTIF-036, 047 · PLUG-041..042
Notes: shared paymethod plugins claimed here (glossary "shared paymethod plugins only" note); pay-per-view access rides U51. Riders: ROUTE-046/052 payment ops (owned by U51) and API-057's submissionPayment portion (owned by U49) cited here.

### U53 — Users management {OJS OMP OPS} · M
Managers/admins find user accounts and act on them: edit, email, disable, merge, remove.
Atoms: AFFM-102..104, 106..108, 203..208, 210 · GRID-003, 050 · VUE-013, 051 · API-047 · MAIL-054, 056..057
Notes: user-management mechanism is invariant of surface (context tab + site wizard grid both here). Riders: API-047's GET-reviewers/report sub-ops cited by U27/U65; VUE-013 also hosts roles configuration (U54 cites).

### U54 — Roles configuration {OJS OMP OPS} · M
Managers define role groups, stage access, self-registration and masthead behavior.
Atoms: AFFM-109..113, 116 · GRID-048 · API-046 · SET-028
Notes: rider — hosted inside VUE-013 (owned by U53).

### U55 — Notify users (bulk email) {OJS OMP OPS} · L
A manager composes email to whole role groups within site-set limits.
Atoms: AFFM-114..115, 201 · API-002 · JOB-002
Notes: the per-role restriction form (AFFM-201) is its settings-that-modify-behavior; the site-wide bulk-email policy form (AFFM-220) is owned by U60 (rider).

### U56 — Emails management {OJS OMP OPS} · M
Managers review every mailable, customize/add/reset templates, disable optional ones; template mechanics home.
Atoms: AFFM-085, 121..128 · VUE-021, 072..073 · API-019, 027 · SET-012, 053
Notes: each mailable's *trigger* stays with its owning feature. Rider: SET-053's require_validation/validation_timeout keys are cited by U02.

### U57 — Languages & locales {OJS OMP OPS} · M
Admins install locales; managers choose UI/forms/submission languages; visitors switch language.
Atoms: AFFM-023..027, 199, 214..217 · AFFR-089 · GRID-005, 036, 043..044 · API-003 · PLUG-004

### U58 — Submission intake configuration {OJS OMP OPS} · M
A manager shapes intake: open/closed, author guidance, metadata enablement, file components (genres), screening.
Atoms: AFFM-053..060, 064 · AFFR-035..036 · GRID-042, 049 · API-021
Notes: coined term — "Submission intake configuration" names no on-screen label (screen: Settings → Workflow → Submission); GLOSSARY.md entry required at spec time. Genre config home per D9; effects tested in U36 (upload wizard consumes API-021 from there). AFFM-064 (OPS author-screening tab) claimed with a Phase-1 liveness flag. Rider: GRID-049 (setup-grid base) is also the base of U17's section grid.

### U59 — Hosted journals (site admin) {OJS OMP OPS} · M
The administrator creates, configures, orders and removes hosted journals; settings wizard.
Atoms: AFFM-183, 192..197 · AFFR-019 · GRID-004 · VUE-014 · API-013 · SET-006, 029, 035, 041
Notes: the context schema (SET-006 + overlays) is homed with the context-entity lifecycle owner; features cite their own props at spec time. Riders: API-013's theme/registrationAgency sub-ops cited by U10/U45; ROUTE-003's contexts/wizard ops (owned by U61) cited here. Wizard tabs that re-embed other features' forms are claimed by those features (AFFM-198..202 → U10/U57/U20/U55/U62).

### U60 — Site settings {OJS OMP OPS} · M
The administrator sets site-wide identity, security, information, appearance, bulk-email policy.
Atoms: AFFM-184, 211..213, 220, 223..224 · AFFR-021 · API-035 · SET-024
Notes: riders — ROUTE-003's settings op and VUE-015's hosting (owned by U61) cited here.

### U61 — System administration & jobs {OJS OMP OPS} · M
The administrator maintains the running system: caches, sessions, system info, queued/failed jobs, scheduled tasks.
Atoms: AFFM-185..190, 230..238 · ROUTE-003 · VUE-005..007, 015 · API-026 · SET-062..063 · JOB-049, 052, 063..067
Notes: ROUTE-003 and VUE-015 (multi-feature admin shells) homed here; U59/U60/U01 cite their ops/screens. Operator-visible config keys are claimable as reference here; pure-infra config sections are OOS per Q5a (see Out of scope).

### U62 — Plugins management {OJS OMP OPS} · M
Enable, configure, upload, gallery-install plugins at journal and site level; each plugin's behavior lives with its owning feature.
Atoms: AFFM-044..051, 202, 228..229 · GRID-006, 041, 077, 095, 104 · NOTIF-009..010
Notes: rider — SET-052's allow_plugin_install/plugin_gallery_urls keys (owned by U01) are cited by this feature's guards.

### U63 — Import & export {OJS OMP OPS} · M
Managers move content and users in/out as XML/CSV via Tools (native XML, users XML, PubMed, DOAJ, pub-id export lists).
Atoms: AFFM-161..162, 164..168 · ROUTE-018 · GRID-052, 071, 073..076, 078, 102..103, 105 · VUE-019 · JOB-061 · PLUG-012, 032, 034..035
Notes: DOAJ pair (PLUG-012 + JOB-061) included per synthesis. Riders: ROUTE-018's permissions/resetPermissions ops and AFFM-161's Permissions tab serve U40's reset tool.

### U64 — Statistics — usage {OJS OMP OPS} · H
Editors see how content is read/downloaded, filter, download reports, serve COUNTER/SUSHI; the ETL pipeline feeds it.
Atoms: AFFM-095, 221 · AFFU-254..268, 271..282, 284..288 · ROUTE-026, 050, 069, 085 · VUE-002, 025..027, 074, 077..079 · API-036, 038..039, 055..056, 060, 064 · JOB-021..026, 035..044, 048, 056, 060 · PLUG-029, 045
Notes: shared filter/date-range/download mechanics homed here per D21; U65 links. Riders: ROUTE-026's editorial/users ops cited by U65; the landing-page chart display (AFFR-056) is owned by U13. JOB-038 (OMP series-metrics compile) claimed as the stats pipeline parameterized by series (OMP divergence).

### U65 — Statistics — editorial activity & reports {OJS OMP OPS} · M
Editorial throughput views, user stats, monthly report email, report plugins.
Atoms: AFFM-171 · AFFU-247..253, 269..270, 283 · VUE-024, 028, 080 · API-037, 040 · MAIL-048 · NOTIF-049, 051 · JOB-015..016, 055 · PLUG-044, 047..048
Notes: riders — ROUTE-026's editorial/users ops (owned by U64) and API-047's report sub-op (owned by U53) cited here.

### U66 — Institutions {OJS OMP OPS} · L
Managers maintain the institution list used by subscriptions and usage stats.
Atoms: AFFM-137..140 · VUE-098 · API-023 · SET-016
Notes: ROR lookup mechanics live in U41 (link).

### U67 — Archiving & preservation {OJS} · L
The journal preserves content in LOCKSS/CLOCKSS/PKP PN networks (settings forms + manifest gateways).
Atoms: AFFM-097..098
Notes: first scenario is a liveness probe (D22) — if Phase 1 finds the forms dead without the PN plugin, the feature collapses to an UNASSIGNED park with evidence. Rider: ROUTE-037's lockss/clockss manifest ops (atom owned by U18) are cited here.

## G. OMP catalog (maintainer scope extension, 2026-07-27)

### U68 — Catalog browse {OMP} · L
Readers browse the press catalog: catalog index, browse-by-series, new releases.
Atoms: AFFR-026, 070..071, 073..074, 087 · ROUTE-056 · GRID-099
Notes: browse-by-category is a SHARED three-app surface owned by U16, not here (RULINGS). NMI_TYPE_SERIES menu entries and the OMP-variant category page rows belong here per RULINGS. Riders: GRID-099 (cover/thumbnail server) also feeds U69's landing page; PLUG-001's series-browse links (owned by U16) get a register note here. Dead-code candidates attached in UNASSIGNED: catalogCategory.tpl's unassigned variables, the dangling `results` op.

### U69 — Monograph landing page {OMP} · M
A reader lands on a monograph: landing incl. chapter/format display, download/purchase links, how-to-cite as displayed.
Atoms: AFFR-051, 075..077 · ROUTE-055 · PLUG-018
Notes: OMP counterpart of U13 (counterpart lines both ways). PLUG-018 + AFFR-051 (htmlMonographFile viewer) are IN scope here per the RULINGS clarification — the OMP analogue of U13's viewer plugins. Format/chapter AUTHORING stays out of scope. Rider: this feature covers the OMP exposure of the U13-claimed shared landing atoms (AFFR-048, 052..057, 061, 066 · PLUG-008, 022) — U13 asserts nothing about OMP. Rider: GRID-099 (owned by U68) serves this page's covers.

### U70 — Catalog management {OMP} · M
Press staff manage the catalog: add-to-catalog, featured/new-release flags and ordering, the per-submission Catalog Entry form.
Atoms: AFFW-427 · AFFM-263..271 · ROUTE-062 · VUE-020, 101 · API-059 · NOTIF-038
Notes: AFFM-263..271 and API-059's saveDisplayFlags/saveFeaturedOrder/addToCatalog ops named to U70 by RULINGS. Riders: AFFW-424's catalogEntry half (owned by U49) and API-061's catalogEntry component (owned by U40) are cited here as spec-side variance lines; NOTIF-038 is mentionable in U49 as a register note.

---

## Appendix — Flag resolutions

Every `?`-flagged line from the six crosswalks, with the final call. Resolution
rule applied: accept the crosswalk's primary assignment unless RULINGS or
TEMPLATE rules 8–10 clearly say otherwise. **101 of the 104 flagged lines
resolve to the crosswalk's primary; three were overturned on critic review
2026-07-27 (AFFR-057 → U13, AFFW-712..734 → OOS, ROUTE-037 → U18).** Four are
additionally confirmed by explicit RULINGS text (AFFW-427, NOTIF-038,
AFFR-051, PLUG-018). The three calls flagged ⚑ for maintainer review were all
adjudicated in that critic pass.

### AFFW (17)

| Atom(s) | Final | Why |
|---|---|---|
| AFFW-240..241 | U24 | Header-level return-to-workflow/done buttons have no owning stage; the workflow shell owns cross-stage chrome (rule 8). |
| AFFW-326 | U30 | Panel-presence swept all three apps, but the mechanism owner wins per D5; badge check deferred to Phase 1. |
| AFFW-424 | U49 | Shared insert-content mechanism: OJS issue half is U49's; the OMP catalogEntry half is a U70 variance line (riders both ways). |
| AFFW-427 | U70 | RULINGS Q2 brings the per-submission catalog surface in scope; U70 is its owner (confirmed by RULINGS). |
| AFFW-429 | U33 | Panel presence homed with the mounting stage; internals stay U37 (rule 8 instantiation split). |
| AFFW-454..456 | U24 | Multi-stage dialogs with no single stage owner; shell home, consistent with 240..241. |
| AFFW-479 | U48 | Send-to-text-editor is the Body Text import mechanism per D12; U36 only mounts the row action. |
| AFFW-487 | U26 | Author-facing read-review surface belongs to the author's review-stage view, not the manager (rule 9: one shared screen). |
| AFFW-506 | U27 | Atom bundles two confirms; the majority mechanism (revert-consider) wins, the ORCID-deposit confirm is a U04 rider. |
| AFFW-605 | U44 | The modal is the assign-public-identifiers mechanism (rule 8); U50 keeps a publish side-effect line. |
| AFFW-606 | U50 | The notify-readers checkbox's consequence belongs to the issue publish flow. |
| AFFW-608 | OOS (formats/ONIX) | The approve-format/proof confirm rides the dropped format object model on both readings (proof pricing = direct sales, equally OOS). |
| AFFW-666..667 | U26 | Author read-review modal — same author-side line as AFFW-487. |
| AFFW-698..700 | U05 | The legacy header Tasks grid is the in-app notification inbox, not U37's task manager. |
| AFFW-712..734 | OOS (UI infrastructure) | ⚑ Orchestrator ruling 2026-07-27 (RULINGS): framework chrome with no feature of its own — exercised implicitly by every consuming feature's scenarios; supersedes the U36 reference-block claim, treated the same as PLUG-028. |
| AFFW-741 | U44 | Issue Identifiers tab anchor rides the pub-id mechanism (rule 8); U50 owns the hosting modal. |
| AFFW-751 | U44 | Publisher-id field on the issue-galley form: mechanism home; U50 owns the form (AFFW-749..750). |

### AFFM / SET (12)

| Atom(s) | Final | Why |
|---|---|---|
| AFFM-064 | U58 | Claimed per synthesis §4 lean with a Phase-1 liveness flag (no bundled screening plugin registers rules). |
| AFFM-161 | U63 | Tools tab bar homed with its dominant tab; U40 cites the Permissions tab. |
| AFFM-171 | U65 | U65's synthesis row names "report plugins"; the usage-shaped reading stays a U64 cross-link. |
| AFFM-172 | U51 | Payments-page tab bar homed with its dominant (subscription) tabs; U52 cites its tabs. |
| AFFM-250 | U50 | Per-article OA toggle is issue-TOC surface — ownership follows the screen (rule 6); U51 owns the access rules. |
| AFFM-255 | U44 | Issue Identifiers tab: pub-id mechanism invariant of surface (rule 8), matching AFFW-741. |
| AFFM-256 | U50 | Issue Access tab is the issue-surface instantiation of U51's delayed-OA rules; screen owner wins, U51 rider. |
| SET-006 | U59 | Context schema homed with the context-entity lifecycle owner; each feature cites its own props at spec time. |
| SET-025 | U21 | Submission schema homed with creation (the wizard); consumers cite fields. |
| SET-052 | U01 | [security] is dominated by session/login keys; U62 cites the plugin-install keys; allowed_html noted near-infra. |
| SET-053 | U56 | [email] homed with email mechanics; U02 cites require_validation/validation_timeout. |
| SET-057 | U02 | [captcha]'s dominant surface is registration/lost-password; U01 cites the login-captcha keys. |

### AFFU / NOTIF (10)

| Atom(s) | Final | Why |
|---|---|---|
| AFFU-093..095 | U05 | The flagged fragile seam resolved to the semantics owner; U03 keeps the tab shell. |
| AFFU-170..175 | U28 | Review-form elements as rendered in the wizard: surface ownership wins; U29 cross-links config→effect. |
| AFFU-206 | U06 | Generic invitation-URL landing homed with the invitation mechanism; U28's one-click entry cites it. |
| AFFU-245 | OOS (monographs/chapters) | The chapter row type rides the dropped object model; its `publication` row is a U45 OMP-variant line, not a claim. |
| NOTIF-001..007 | U05 | Generic framework toasts have no single emitting feature; the notifications mechanism owns them. |
| NOTIF-031 | U26 | Lives/clears with the round's revision upload; U34's decision keeps a side-effect line. |
| NOTIF-034 | U33 | Production-era task per the app NotificationManager mapping; the U32 reading noted, not adopted. |
| NOTIF-037 | U49 | The approval-gate prompt tracks the approve-submission mechanism; its OMP format surface is OOS. |
| NOTIF-038 | U70 | RULINGS Q2 brings catalog management in scope (confirmed by RULINGS); U49 keeps the register-note caveat. |
| NOTIF-051 | U65 | Reminder digest grouped with U65's report email; the U23 reading noted, not adopted. |

### AFFR (17)

| Atom(s) | Final | Why |
|---|---|---|
| AFFR-009 | U05 | The unread badge is notification-center state; U08 owns only the chrome mount (rider). |
| AFFR-016 | U08 | The shared "Edit" shortcut is chrome; consuming settings features cite it. |
| AFFR-019 | U59 | Site-home context list is the display side of hosted-journal ordering. |
| AFFR-027 | U10 | Server-home composition owned by appearance; the archive-header portion rides AFFR-078 (U17). |
| AFFR-035 | U58 | Intake open/closed notice: the settings owner wins over the About-page adjacency (U07 rider). |
| AFFR-046 | U13 | Listing-summary component homed at the landing feature; TOC/home/search screens cite it. |
| AFFR-051 | U69 | RULINGS htmlMonographFile clarification: a catalog READER surface, in scope under U69 (confirmed by RULINGS). |
| AFFR-052 | U13 | Shared details atom; OMP exposure is covered by U69 (D2 superseded), not by U13. |
| AFFR-056 | U13 | Chart *display* owned by the screen (rule 6); pipeline and stats semantics stay U64's. |
| AFFR-057 | U13 | ⚑ TEMPLATE rule 6 screen ownership, consistent with AFFR-056 and D11's lean; U42 cites; OMP exposure via U69 rider. |
| AFFR-063 | U43 | Funders display is primary; the availability-statement portion sits on the U40/U13 seam per D11. |
| AFFR-065 | U40 | License display owned by U40 per D10; U13 cites it as landing content. |
| AFFR-069 | U45 | Crossmark is registration-agency plugin behavior (plugin-owner rule); renders on U13's screen (rider). |
| AFFR-078 | U17 | Archive-header homed with the OPS archive pages per Q4a; search/category portions cited by U15/U16. |
| AFFR-081 | U13 | OPS listing summary — same seam and call as AFFR-046. |
| AFFR-082 | U13 | Landing display claimed; the relation-notice portion is OOS (OPS preprint relations), marked at spec time. |
| AFFR-092 | U21 | The make-a-submission CTA's whole intent is the wizard entry route. |

### ROUTE (20)

| Atom(s) | Final | Why |
|---|---|---|
| ROUTE-003 | U61 | Multi-feature admin handler homed at primary intent (sysadmin); U59/U60/U01 cite their ops. |
| ROUTE-005 | U24 | Rule 9 gives U22 only the entry route; the author's workflow screen is the shared workflow surface. |
| ROUTE-007 | U23 | Legacy submissions dispatcher homed with the editorial dashboard; U22/U28 cite their role ops. |
| ROUTE-008 | U23 | Dashboard base page; mySubmissions/reviewAssignments views cited by U22/U28 (D4). |
| ROUTE-017 | U07 | Settings dispatcher homed at the context-identity hub; every tab's feature cites its ops. |
| ROUTE-018 | U63 | Tools page's primary is import/export; U40 cites the permissions/resetPermissions ops. |
| ROUTE-019 | U09 | The view op (custom pages) is primary; U08 cites the preview op. |
| ROUTE-026 | U64 | Stats hub homed with usage stats; U65 cites the editorial/users ops (D21). |
| ROUTE-028 | U03 | Thin user-page base; U01 cites authorizationDenied. |
| ROUTE-032 | U51 | The subclass delta (about/subscriptions op) is the assignment driver; inherited about ops stay U07's. |
| ROUTE-037 | U18 | Critic review 2026-07-27: the dispatcher's primary reachable consumer in all three apps is the web-feed gateway, consistent with ROUTE-059/076; U67 cites the lockss/clockss manifest ops — a D22 collapse moves only citations, not a claimed live route. |
| ROUTE-042 | U07 | Same settings-dispatcher seam and call as ROUTE-017. |
| ROUTE-046 | U51 | Subscriptions management is primary; U52 cites paymentTypes/payments ops. |
| ROUTE-052 | U51 | Purchase/renewal ops are primary; U52 cites payMembership. |
| ROUTE-059 | U18 | The generic gateway dispatcher's primary reachable consumer is the web-feed gateway. |
| ROUTE-063 | U07 | Same settings-dispatcher seam as ROUTE-017. |
| ROUTE-065 | OOS (direct sales) | The payment-plugin callback route rides the dropped sales flow; shared paymethod plugins stay U52's. |
| ROUTE-072 | U24 | Workflow-access primary; the added internalReview op is OOS-marked at spec time. |
| ROUTE-076 | U18 | Same call as ROUTE-059. |
| ROUTE-078 | U07 | Same settings-dispatcher seam as ROUTE-017. |

### VUE (5)

| Atom(s) | Final | Why |
|---|---|---|
| VUE-003 | U23 | One page component, three views; U22/U28 cite their views. |
| VUE-013 | U53 | The Users & Roles container homed with users management; U54 cites its hosting. |
| VUE-015 | U61 | The admin container's primary intent is the sysadmin shell; U59/U60 cite their screens. |
| VUE-022 | U07 | Settings-pages container — same seam as ROUTE-017. |
| VUE-089 | U34 | The prepared-content insert's primary consumer is the Composer; settings-form rich text cites it. |

### GRID / API / MAIL (15)

| Atom(s) | Final | Why |
|---|---|---|
| GRID-049 | U58 | Setup-grid base homed with the shared-tree consumer (genre grid) per the atlas uploadImage tie; U17 cites it. |
| GRID-064 | U10 | Theme-stylesheet delivery is the heavier op; U05 (tasks) and U08 (frame) cite theirs. |
| GRID-083 | U52 | Payment-records intent beats the embedding area (subscription pages). |
| GRID-099 | U68 | Cover server homed with catalog listings per the probe's framing; U69 cites it. |
| API-007 | U09 | Generic public-file upload homed with arbitrary manager content; consumers cite it. |
| API-032 | U30 | The dominant authorResponse cluster is D5's own-controller argument; U28/U26/U04 cite their ops. |
| API-042 | U24 | Omnibus controller homed at the workflow mechanism owner; U21/U34/U40/U41/U49 cite their endpoint clusters (riders in each). |
| API-057 | U49 | Issue-assignment publish-modal fields dominate; U52 cites submissionPayment. |
| API-058 | U45 | The in-scope submissionFiles DOI row carries the claim; chapter/format rows OOS-marked at spec time. |
| API-061 | U40 | Metadata components are the majority; U70 cites the catalogEntry component. |
| API-065 | U49 | Author-may-post re-registrations are the in-scope U49 variant; the relate portion OOS-marked at spec time. |
| MAIL-025 | U23 | The digest enumerates workflow states, not tracked tasks; triage-dashboard owner. |
| MAIL-043 | U27 | Send-side owner; U29 owns the clock configuration. |
| MAIL-046 | U27 | Same clock/send split as MAIL-043. |
| MAIL-080..081 | U37 | ⚑ D19's blanket alternateTo ruling (accepted by RULINGS) covers them; atlas: both are `alternateTo="DISCUSSION_NOTIFICATION_PRODUCTION"` — production-stage alternates, so synthesis §5's internal-review OOS listing was factually wrong, not merely superseded; OMP-only availability line at spec time. |

### JOB / PLUG (8)

| Atom(s) | Final | Why |
|---|---|---|
| JOB-011 | U23 | The outstanding-actions digest mirrors the dashboard's needs-attention queue; U37 runner-up noted. |
| JOB-046 | U23 | Scheduled dispatcher of JOB-011 — same doubt, same home. |
| JOB-054 | U29 | The automated reminder clock is U29's fixture per D6; the manual remind path stays U27 (JOB-012). |
| JOB-062 | U42 | Citation-DOI enrichment behavior owns it; it ships inside U45's Crossref plugin (rider). |
| PLUG-001 | U16 | The browse block homed with categories; OMP series-browse links are a U68 register note. |
| PLUG-005 | U21 | The CTA block's whole behavior is the wizard entry route; U08 runner-up noted. |
| PLUG-018 | U69 | RULINGS htmlMonographFile clarification confirms: in scope under U69 (confirmed by RULINGS). |
| PLUG-036 | U19 | The schema adapter's only consumer surface is the oai_dc crosswalk. |

---

## Out of scope

The single home for out-of-scope atoms — **173 atoms**, grouped by cluster, each
with its authority. "Dropped, not parked": these need no Phase-1 follow-up. The
claim check treats every ID below as accounted for. Mixed-payload atoms whose
OOS *portion* rides a claimed atom (ROUTE-072's internalReview op, API-058's
chapter/format rows, API-065's relate endpoint, AFFR-082's relation notice,
AFFW-424's OMP half, AFFM-094's and MAIL-075's OMP direct-sales portions) are
NOT listed here — the owning spec marks the portion at spec time.

### Scope drop — OMP Internal Review stage & `*_INTERNAL` decisions (29)

the scope ruling names them explicitly. The submission-stage skip-internal routing stays
in scope as a U25 parameterization.

- AFFW-248, 301, 304..322, 345..348 (25)
- MAIL-071
- NOTIF-015, 020, 030

### Scope drop — OMP monographs, chapters & work types (18)

Object model OJS never exposes; chapter/work-type authoring stays dropped under
the Q2 amendment (only catalog reader/management surfaces came in).

- AFFW-111, 123..124, 237..239, 275, 425, 572, 772..777 (15)
- GRID-097 · VUE-031 · AFFU-245

### Scope drop — OMP publication formats & ONIX (25)

- AFFW-276, 426, 573, 608, 754..769 (20)
- GRID-092 · VUE-044 · SET-036, 040 · PLUG-033

### Scope drop — OMP marketing & supply chain (13)

- AFFW-257..258, 431..434, 574 (7)
- GRID-089..091, 093..094 (5) · VUE-045

### Scope drop — OMP direct sales / approved proofs (3)

Shared paymethod plugins themselves stay claimed by U52 (glossary note).

- AFFW-770..771 · ROUTE-065

### Scope drop — OMP-only exporters (3)

- AFFM-169 · PLUG-031, 046

### Scope drop — `NOTIFICATION_TYPE_BOOK_*` (10)

Explicit scope drop; no UI in any sweep.

- NOTIF-056..065

### Scope drop — OPS preprint relations & journal relay (4)

Covered by the scope ruling's "OMP/OPS-only Vue managers unwired in WorkflowPageOJS"
clause; API-065's relate portion rides its claimed atom (U49).

- AFFW-128, 385..386 · PLUG-024

### Q1 ruling — installer & upgrader (1)

Out of campaign test scope per Q1(a): exercising it means reinstalling the app,
which the shared-fixture model cannot do. Marked with-reason in the sweep.

- ROUTE-012

The installer's CLI counterparts (CLI-027, CLI-031) are placed once, inside
the cli-ruling blanket below; Q1 is their additional authority.

### cli-ruling — all CLI atoms (31)

Blanket maintainer ruling 2026-07-27 (`atlas/README.md`; reason header in
`atlas/cli.md`): CLI atoms never seed features or scenarios; reference claims
happen at spec time.

- CLI-001..031

### Q5 ruling — OOS-infra: pure-infrastructure config sections (12)

Deployment-only keys with no user-facing behavior to specify, per the Q5(a)
blanket header in `atlas/settings.md`. The operator-visible few stay claimable
as reference by U61.

- SET-046..051 ([general], [database], [cache], [i18n], [files], [finfo])
- SET-056 ([interface]) · SET-058..061 ([cli], [proxy], [debug], [logs])
- SET-065 ([features] empty placeholder)

### Cross-cutting UI infrastructure (orchestrator ruling 2026-07-27) (24)

Framework chrome with no feature of its own is out-of-scope-with-reason —
"exercised implicitly by every consuming feature's scenarios" — mirroring the
cli/Q5 pattern (RULINGS).

- AFFW-712..734 (23) (shared legacy grid-framework chrome)
- PLUG-028 (TinyMCE rich-text editor infrastructure)
