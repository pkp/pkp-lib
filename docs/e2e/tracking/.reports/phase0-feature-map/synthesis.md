# Phase 0 feature-map synthesis — three-lens merge

- **Date**: 2026-07-27 · **Author**: claude (Fable), desk synthesis of the three
  independent taxonomy drafts (`taxonomy-screens.md` S1–71, `taxonomy-roles.md`
  R1–60, `taxonomy-qa.md` A1–F20). No git history, no prior FEATURE-MAP consulted
  (blind-rebuild rule). Atlas files consulted only where the drafts disagreed about
  what an atom *is* (ROUTE-032/037/081-082, GRID-064, API-018/030/062/065,
  MAIL-076–083, AFFR-058/079-080, AFFU-197/205, NOTIF-038).
- **How to read**: §1 is the proposed unified list — consensus rows pass as
  drafted; contested rows carry a `→ D#` pointer and reflect my stated lean, which
  the maintainer can overturn by answering the numbered entry. §2 is the
  disagreement register (answer by D-number), §3 the scope/canon questions (answer
  by Q-number). §4–§5 merge the drafts' UNASSIGNED and out-of-scope lists. §6 is
  the budget check.
- **Consensus rule applied**: a boundary drawn identically (modulo naming) by ≥2
  lenses, uncontradicted by the third, passes; where the third lens genuinely
  contradicts, the row still shows the majority shape but gets a D entry — nothing
  is silently resolved.
- **Headline numbers**: **67 unified features** (14 H · 40 M · 13 L) ·
  **22 register entries** · **5 maintainer questions** · **≈ 486 common scenarios**
  at tier midpoints (OJS, the fullest app) vs the ≤ 700/app ceiling.

---

## 1. Proposed unified feature list

Provenance key: `S#` = screens draft, `R#` = roles draft, `Q<id>` = QA draft.
Apps badge omitted when `{OJS OMP OPS}`. Tier dissent is noted inline; the listed
tier is the majority. Atom lists stay in the source drafts — this table is the
boundary decision layer, not a crosswalk.

### A. Accounts & identity

| # | Feature — intent | Apps | Tier | Provenance | Notes |
|---|---|---|---|---|---|
| U1 | **Login & sessions** — a user signs in/out, recovers and changes passwords, passes re-authentication gates, and (admin) logs in as another user. | all | M | S48 · R1 · Q B1+B3 | Q splits password recovery out as B3 (L) → **D16**; log-in-as home contested → **D17**. |
| U2 | **Registration & account validation** — a visitor registers (site- or journal-level), consents, opts into roles, validates their email. | all | M | S49 · R2 · Q B2 | Consensus; identical boundary in all three. |
| U3 | **User profile** — a user maintains identity, contact, roles, public profile, password tab, notification choices, API key. | all | M | S50 · R3 · Q B4 | Consensus, incl. the shared split: profile owns the Notifications *tab*, U5 owns toggle semantics (all three drew this line; S flags it as the taxonomy's most fragile seam). |
| U4 | **ORCID integration** — users and authors connect verified iDs; the journal deposits works/reviews to ORCID. | all | M | S51 · R4 · Q B5 | Consensus. OPS plugin packaging = install-fact divergence (R/Q). |
| U5 | **Notifications center & email preferences** — users receive, review, and control in-app and email notifications, incl. one-click unsubscribe. | all | M | S65 · R5 · Q B6 | Consensus. Individual NOTIF-* types stay with their triggering features (all three). |
| U6 | **User invitations** — a manager invites someone into roles; the recipient accepts (creating/linking an account) or declines. | all | M | S47 (as H) · R6 · Q F7 | Tier: majority M; S dissents H but self-flagged it for demotion. Send + accept kept as one journey (R/Q; S same boundary). |

### B. Reader & public site

| # | Feature — intent | Apps | Tier | Provenance | Notes |
|---|---|---|---|---|---|
| U7 | **Journal identity & about pages** — managers describe the journal (masthead, contact, policies, information pages); readers consult the About pages. | all | M | S39 · R7 (as "Public site & journal pages") · Q A3 | Consensus core (edit-then-view of Settings>Journal + About). R also folded home page/custom pages/sitemap in — those edges → **D14**, **D15**. |
| U8 | **Navigation menus & site chrome** — managers shape menus; every visitor navigates the resulting header/footer frame. | all | M | S40 · R8 · Q A2 (menus) + A1 (chrome) | Menus consensus; where the header/footer *chrome* and custom pages live is 3-way → **D14**. |
| U9 | **Custom pages & blocks** — managers publish their own pages (static pages, custom NMI pages) and sidebar blocks of arbitrary content. | all | L | S41 (own feature) · R dissolves (R7/R9) · Q dissolves (A2/A1) | Contested home → **D14**. Listed per my lean (S shape). |
| U10 | **Appearance & theming** — managers control how the site looks: theme + options, logos, home-page composition, lists, date formats. | all | M | S43 · R9 · Q F11+A1 | Consensus that this exists; the theming-vs-front-page-content seam (Q splits F11/A1) and GA placement → **D14**/**D15**. |
| U11 | **Highlights** — managers pin promoted items into a home-page carousel (journal or site scope). | all | L | S42 · R56 · Q folds into A1 | Majority: own L feature; Q dissent noted (fold into chrome). |
| U12 | **Announcements** — the journal posts news; readers browse; subscribed users are notified. | all | M | S38 · R10 · Q A4 | Consensus. Announcement feed: majority rides here (S/R); Q grouped it with web feeds — minor, noted at U18. |
| U13 | **Article landing page & reading** — a reader lands on a published item, sees metadata/authors/versions/citation/license, and opens or downloads its files in the right viewer. | all* | H | R12 · Q A5 · S splits (S32 landing M + S33 cite L + S34 galley access M) | Split-vs-merge → **D1** (lean: merge, H). *OMP badge contested → **D2** + **Q2**. Restriction *rules* stay with U51 (all three). |
| U14 | **Reader comments & moderation** — readers comment on published articles; moderators approve, hide, and handle reports. | all? | M | S54 · R13 · Q A11 | Boundary consensus (post→moderate = one feature). Badge unresolved in all three drafts: front-end mount greps OJS-only, moderation is shared — Phase-1 probe, not a ruling. |
| U15 | **Search** — readers search published content and refine results; the index stays current. | all | M | S36 · R14 · Q A10 | Consensus. |
| U16 | **Categories** — managers build a browsable category tree; readers browse by category. | all | M | S37 · R45 (as L) · Q A9 | Tier: majority M, R dissents L. |
| U17 | **Sections** — managers structure the journal into sections (OMP: series) shaping submission choices and reader presentation. | all | M | S66 · Q A7 · R merges into R44 "Journal setup" | R's merge → **D13**. OMP series treatment → **Q3**; OPS section-browse/archive pages → **Q4**. |
| U18 | **Web feeds** — readers subscribe to new content by RSS/Atom. | all | L | S69 · R15 · Q A8 | Consensus. (Q also put the announcement feed here — see U12.) |
| U19 | **OAI-PMH** — aggregators harvest metadata over the standard interface in each supported format. | all | M | S67 · R16 (as L) · Q A12 | Tier: majority M, R dissents L (protocol probes only). |
| U20 | **Search-engine metadata & analytics** — sitemaps, indexing meta tags, and analytics head-tags so third parties find and measure content. | all | L | S68 · Q A13 · R dissolves (R7/R9/R12) | Majority: one L feature incl. Google Analytics; R dissent → **D15**. |

### C. Submitting & dashboards

| # | Feature — intent | Apps | Tier | Provenance | Notes |
|---|---|---|---|---|---|
| U21 | **Submission wizard** — an author starts, fills, saves-for-later, reconfigures, cancels, and submits a new submission. | all | H | S3 · R19 · Q C1 | Consensus, incl. panel-instantiation treatment (mechanics live in U36/U41; wizard owns step flow + gates). |
| U22 | **My Submissions (author dashboard)** — an author tracks their submissions, acts on requests, re-enters incomplete ones; owns the author's entry route into the workflow (TEMPLATE rule 9). | all | M | S2 · R20 · Q C3 | Consensus. |
| U23 | **Submissions dashboard (editorial)** — editors, managers and assistants find, filter, triage and open submissions; views, search, activity cells, bulk cleanup. | all | H | S1 · R21 · Q C2 | Consensus. The reviewer's "Review Assignments" view of the same page → **D4**. |

### D. The workflow

| # | Feature — intent | Apps | Tier | Provenance | Notes |
|---|---|---|---|---|---|
| U24 | **Workflow screen & stage access** — any participant (author included) opens a submission's workflow and reaches the stages, tabs and tools their role allows; mechanism home for stage access. | all | M | S4 · R22 · Q D1 | Consensus (rule 9 honored identically by all three). |
| U25 | **Submission stage** — the team screens a new submission and moves it onward (to review / skipped / declined) or removes it. | all | M | S5 · R23 · Q D2 | Consensus, incl. OMP skip-internal routing as in-scope parameterization while the Internal Review stage itself is out of scope (all three). |
| U26 | **Review stage & rounds** — editors run review rounds (round status, files, revisions, round decisions); authors follow and respond on the same screen. | {OJS OMP} | H | S6 · R24 · Q D3 | Consensus core. Q folded the author-response manager in here → **D5**. Recommend-only ownership → **D18**. |
| U27 | **Reviewer assignment & management** — an editor finds, invites, edits, reminds, thanks, unassigns, reinstates reviewers and reads their reviews. | {OJS OMP} | H | S7 · R25 · Q D4 | Consensus. One-click access: majority home is U28 (S/Q); R put it here — minor, noted. |
| U28 | **Reviewer's review** — a reviewer sees assignments, responds to a request, completes the wizard (or declines), submits, and revisits past rounds. | {OJS OMP} | H | S8 · R26 · Q D5 | Consensus. Includes one-click (access-key) entry (S/Q majority). The reviewer's list view → **D4**. |
| U29 | **Review setup & review forms** — a manager configures how review runs: mode, deadlines, reminders, guidance, review forms, recommendation options. | {OJS OMP} | M | R27 · Q F1 · S splits (S9 forms-only; setup form attached to S7) | Majority: one settings feature → **D6**. |
| U30 | **Author response to reviews** — an editor requests, and an author submits, a formal response to a review round. | {OJS} | M | S10 · R29 · Q folds into D3 | Majority: own feature → **D5**. Badge may widen to OMP (API mounted ojs+omp; Phase-1 resolves — S note). |
| U31 | **Reviewer suggestions** — an author suggests reviewers at submission; an editor sees and acts on them during review. | {OJS OMP} | L | R28 (own feature) · S homes in reviewer-side · Q homes in wizard | 3-way home dispute → **D7**. Listed per my lean (own L feature). |
| U32 | **Copyediting stage** — editor, copyeditor and author move accepted submissions through copyediting. | {OJS OMP} | M | S11 · R30 · Q D10 | Consensus. |
| U33 | **Production stage** — the team readies final files and hands over to scheduling/publication; OPS's whole editorial flow (decline/revert) lives here. | all | M | S12 · Q D11 · R merges galleys in (R31) | Galleys kept separate (majority) → **D8**. |
| U34 | **Editorial decision recording** — an editor records any decision/recommendation through the guided wizard (notification email, file promotion); mechanism home for the email Composer + FileAttacher. | all | H | S13 · R34 (as M) · Q D7 | Boundary consensus incl. Composer home (all three hardest-calls agree). Tier: majority H, R dissents M. Stage features own their decision rosters (all three). |
| U35 | **Stage participants** — editors control who works on a submission per stage: assign, notify, restrict (recommend-only, metadata), remove. | all | M | S14 · R35 · Q D12 | Consensus. Toggle-vs-consequence split per rule 8 (all three). |
| U36 | **Submission files** — anyone with stage access uploads, revises, organizes, inspects and downloads submission files; mechanism home for the file manager + upload wizard. | all | H | S15 · R36 · Q D13 (as M) | Tier: majority H, Q dissents M. Genre *config* home → **D9** (majority: U58). |
| U37 | **Tasks & discussions** — participants (reviewer included) coordinate through discussions and trackable tasks with due dates and templates. | all | H | S17 · R37 · Q D14 | Consensus, incl. task-template settings owned here. Alternate `alternateTo` templates → **D19**. |
| U38 | **Submission activity log & notes** — staff consult a submission's event history and logged emails, and keep internal notes. | all | L | S16 · R38 · Q D15 (as M) | Tier: majority L, Q dissents M. API-018 (logged-emails API) belongs here per the atlas (R/Q; S had loosely listed it under emails management — atlas resolves it). |
| U39 | **Submission & Publisher Libraries** — reusable documents at journal level and per submission, shared with participants. | all | L | S18 · R48 · Q F20 (as M) | Tier: majority L, Q dissents M. |

### E. The publication

| # | Feature — intent | Apps | Tier | Provenance | Notes |
|---|---|---|---|---|---|
| U40 | **Publication metadata** — authors (pre-publication) and editors maintain title & abstract, descriptive metadata, submission language, and license/permissions; owns published-state edit locks. | all | M | S19 · R42 · Q E1 | Consensus core. License home → **D10** (lean: here). R also merged public identifiers in — kept separate (majority, U44). |
| U41 | **Contributors & affiliations** — maintain who authored the work: order, roles, ROR-backed affiliations, principal contact, everywhere the list appears. | all | M | S20 · R39 · Q E2 | Consensus. ROR lookup mechanics: majority here (S/Q); R homed them in Institutions — noted, no entry. |
| U42 | **Citations & references** — capture reference lists and data citations, structure them via lookups, show them to readers. | all | M | S21 · R40 · Q E3 | Consensus core; S's separate "Data availability & data citations" feature → **D11**. |
| U43 | **Funding** — record funders/grants and disclose them to readers. | all | L | S23 · R41 · Q E4 | Consensus. |
| U44 | **Identifiers (publisher IDs & URN)** — assign/clear non-DOI public identifiers on publications, galleys, files. | all | L | S24 · Q E7 · R merges into R42 | Majority: separate L; R dissent noted (no entry — pure fold, no new rules). |
| U45 | **DOIs** — configure, assign, manage, register DOIs with an agency; track statuses and errors across object types. | all | H | S25 · R52 · Q F4 | Consensus, incl. registration-agency plugin settings owned here. |
| U46 | **Galleys** — editors attach, label and order the publishable files a reader will open. | {OJS OPS} | M | S26 · Q E5 · R merges into R31 | Majority: separate → **D8**. OMP counterpart (publication formats) out of scope (all three). |
| U47 | **Media files** — manage image/media assets of a publication and link them into full-text displays. | all | L | S27 · R43 · Q E8 | Consensus. |
| U48 | **JATS & Body Text** — keep a JATS XML representation (auto/uploaded, optionally public) and author/convert the article's full text in the built-in editor. | {OJS} | M | R32 · Q E6 (as L) · S splits (S28 JATS M + S29 Body Text M) | Merge-vs-split + tier → **D12** (lean: one M feature). |
| U49 | **Publish, schedule & versions** — editors publish/schedule/unschedule/unpublish and manage versions; OJS issue-assignment fields render here; OPS "Post the preprint" (incl. author-may-post, moderation-before-posting) is the app variant. | all | H | S30 · R33 · Q D8 | Consensus — all three independently drew the same screen-ownership line (publish modal owns issue-assignment fields; U50 owns issue entities). |

### F. Communication, money & administration

| # | Feature — intent | Apps | Tier | Provenance | Notes |
|---|---|---|---|---|---|
| U50 | **Issues** — editors assemble, order, publish and manage issues and issue galleys; readers browse the current issue, TOC and back archive. | {OJS} | H | S53 · R11 · Q splits (A6 reader M + D9 mgmt H) | Merge-vs-split → **D3** (lean: one feature). |
| U51 | **Subscriptions & open access control** — the journal sells/manages subscriptions and controls when content becomes openly accessible; owns restricted-access rules incl. publishing mode. | {OJS} | H | S56 · R17 · Q F2 | Consensus. Publishing-mode/Access form: majority owned here (S/Q); R put it in its distribution-settings feature → part of **D9**. Open-access notify: majority here (S/Q); R had it in Issues — noted. |
| U52 | **Payments & APCs** — the journal charges fees through configured payment methods. | {OJS} | M | S57 · R18 · Q F3 | Consensus (pay-per-view access rides U51 per Q's hardest call; S/R compatible). |
| U53 | **Users management** — managers/admins find user accounts and act on them: edit, email, disable, merge, remove. | all | M | S45 (as H, self-flagged) · Q F5 · R merges into R50 | Users/roles/bulk-email carve-up → **D20**. Tier listed M (Q + S's own demotion flag). |
| U54 | **Roles configuration** — managers define role groups, stage access, self-registration and masthead behavior. | all | M | S46 · Q F6 · R merges into R50 | → **D20** (majority: separate). |
| U55 | **Notify users (bulk email)** — a manager composes email to whole role groups within site-set limits. | all | L | S55 (own feature) · R in R50 · Q in F17 | 3-way home dispute → **D20**. Listed per my lean (own L). |
| U56 | **Emails management** — managers review every mailable, customize/add/reset templates, disable optional ones; template mechanics home. | all | M | S52 · R51 · Q F9 | Consensus; each mailable's *trigger* stays with its owning feature (all three). |
| U57 | **Languages & locales** — admins install locales; managers choose UI/forms/submission languages; visitors switch language. | all | M | S44 · R47 · Q F10 | Consensus. |
| U58 | **Submission intake configuration** — a manager shapes intake: open/closed, author guidance, metadata enablement, file components (genres), screening. | all | M | R46 · Q F8 · S dissolves into consuming features | Settings-taxonomy dispute → **D9** (lean: keep this feature). |
| U59 | **Hosted journals (site admin)** — the administrator creates, configures, orders and removes hosted journals; settings wizard. | all | M | S58 · R58 · Q F16 | Consensus. |
| U60 | **Site settings** — the administrator sets site-wide identity, security, information, appearance, bulk-email policy. | all | M | S59 · Q F17 · R merges ops into R59 | Majority: settings separate from operations; R dissent noted (no entry — R's own 60 already splits jobs out). |
| U61 | **System administration & jobs** — the administrator maintains the running system: caches, sessions, system info, queued/failed jobs, scheduled tasks. | all | M | S60 · Q F18 · R59-part + R60 | Consensus modulo R's seam above. |
| U62 | **Plugins management** — enable, configure, upload, gallery-install plugins at journal and site level; each plugin's behavior lives with its owning feature. | all | M | S61 · R57 · Q F19 | Consensus. |
| U63 | **Import & export** — managers move content and users in/out as XML/CSV via Tools (native XML, users XML, PubMed, DOAJ, pub-id export lists). | all | M | S62 · R54 · Q F13 | Consensus. (Q alone surfaced DOAJ PLUG-012/JOB-061 — include.) |
| U64 | **Statistics — usage** — editors see how content is read/downloaded, filter, download reports, serve COUNTER/SUSHI; the ETL pipeline feeds it. | all | H | S63 · Q F14 · R merges all stats into R53 | Monolith-vs-split → **D21** (lean: split). |
| U65 | **Statistics — editorial activity & reports** — editorial throughput views, user stats, monthly report email, report plugins. | all | M | S64 · Q F15 · R merges into R53 | → **D21**. |
| U66 | **Institutions** — managers maintain the institution list used by subscriptions and usage stats. | all | L | S71 · R55 · Q F12 | Consensus (ROR mechanics live in U41, majority). |
| U67 | **Archiving & preservation** — the journal preserves content in LOCKSS/CLOCKSS/PKP PN networks (settings forms + manifest gateways). | {OJS} | L | S70 (own feature) · R folds into R49 · Q parks | 3-way → **D22**. Listed per my lean (own L, liveness-probed). |

---

## 2. Disagreement register

Format per entry: decision · options (which lens holds which) · consequences ·
**Lean** + one-sentence rationale. Answer by number.

<a id="d1"></a>
**D1 — Article landing page: one feature or three.**
Decision: is the reader's published-item experience one feature or three (landing page / how-to-cite / galley viewing)?
Options: **merge into one H** (R12, Q A5) vs **three features** — landing M + cite L + galley access M (S32/33/34).
Consequences: merged = one spec a QA person reads for the whole page, but a large one; split = cleaner atom homes (viewer plugins get their own scenario space) at the cost of three specs for one screen and cross-links for every page block.
**Lean: merge (H).** The QA litmus is one sitting — a tester opens one page and exercises cite/viewers/metadata together; the S split exists mainly to keep tiers small, which H already accommodates.

<a id="d2"></a>
**D2 — OMP coverage of the landing page.**
Decision: does U13 cover OMP's monograph landing page as the app variant, or badge OMP out with an absence paragraph pointing at the out-of-scope catalog?
Options: **cover OMP** — the shared details atoms are marked `ojs omp ops`, so the spec covers the book page minus chapter/format blocks (S, hardest-call 1) vs **OMP absence paragraph** — the whole book page is a catalog surface per CHARTER (R item 6, Q A5).
Consequences: covering OMP honors multi-app rule 1 for shared atoms but drags spec text into CHARTER's dropped catalog; excluding OMP leaves OMP with zero reader-side coverage of shared lib/pkp display atoms (orphaned `ojs omp ops` atoms must then be out-of-scope-marked per app).
**Lean: depends on Q2 — with Q2 answered per my lean (catalog stays dropped), badge OMP out and mark the shared atoms' OMP exposure out-of-scope-with-reason.** CHARTER names the catalog explicitly and half-covering a page produces a spec no OMP QA person can execute.

<a id="d3"></a>
**D3 — Issues: one feature or reader/management split.**
Options: **one H feature** spanning back office + reader TOC/archive (S53, R11) vs **two features** — reader view M + management H (Q A6/D9).
Consequences: merged = publish-issue scenarios end on the reader TOC where they're verifiable (both S and R argue this); split = smaller specs but the reader-view feature barely clears 3 scenarios that aren't management echoes.
**Lean: one feature (H).** Two of three lenses — including the QA-adjacent screens lens — found the reader pages can't stand alone.

<a id="d4"></a>
**D4 — Reviewer's dashboard list: own feature or folded.**
Decision: does the "Review Assignments" view of the dashboard page get its own feature (TEMPLATE rule 9: "dashboards own their list") or fold into the reviewer's-review feature?
Options: **own L feature** (Q C4, citing rule-9 symmetry with My Submissions) vs **fold into U28** (S, R — both argue the view is thin and its statuses are projections of wizard state).
Consequences: own feature = literal rule-9 consistency, but a feature whose 3 scenarios all end inside another feature's wizard; folding = one fewer spec, with U28 owning the list's entry-route the way U22 owns the author's.
**Lean: fold into U28.** Rule 9 names *two* dashboards deliberately; extending it to a third, thinner list is symmetry for its own sake — but this is a canon-interpretation call the maintainer should confirm. Note either way: AFFU-197 is swept `ojs omp ops`, so what the view lists in OPS (no review stage) needs a Phase-1 probe (S flag).

<a id="d5"></a>
**D5 — Author response to reviews: own feature or inside the review stage.**
Options: **own M feature {OJS}** (S10, R29) vs **inside Review stage** (Q D3 — same round fixture).
Consequences: own = a small, sharply-bounded spec for a new-ish mechanism (own API controller, own manager components); folded = one fewer spec but the review-stage H spec grows past its ceiling and the {OJS}-only badge infects a {OJS OMP} feature's body with markers.
**Lean: own feature.** The badge mismatch alone justifies it — a per-app divergence block inside U26 for a whole sub-feature fights multi-app rule 7.

<a id="d6"></a>
**D6 — Review setup & forms: one settings feature or forms-only.**
Options: **one feature** covering the review-setup form + review forms + recommendation options (R27, Q F1) vs **forms-only feature**, with the setup form and recommendation options attached to reviewer management as settings-that-modify-behavior (S9 + S7).
Consequences: one feature = single home with its own fixtures (built form, reminder clocks — Q's argument); S's shape spreads review configuration across two specs but keeps "settings live with the behavior they change" pure.
**Lean: one feature (U29).** Reminder scheduling and recommendation options configure *three* consuming features (U26/U27/U28), so no single behavior feature is their natural home — that is exactly when a mechanism earns its own spec.

<a id="d7"></a>
**D7 — Reviewer suggestions: who owns the mechanism.**
Options: **own L feature** (R28) vs **mechanics in the submission wizard** (Q C1) vs **mechanics in the reviewer-side feature** (S3 note).
Consequences: the mechanism spans two screens (wizard panel + review-stage panel); homing it in either screen's feature forces the other to restate or cross-link; own feature gives one owner at the cost of a very small spec.
**Lean: own L feature (U31).** Rule 8's litmus ("would this still be true if I changed surface?") says the suggestion lifecycle is invariant across its two surfaces — one mechanism, one home; wizard and review stage own only their instantiation gates.

<a id="d8"></a>
**D8 — Galleys inside Production, or separate.**
Options: **separate galleys feature** (S26, Q E5) vs **production stage + galleys merged** (R31).
Consequences: merged = the Layout Editor's whole journey in one spec; separate = galleys' publication-tab/per-version rules (which outlive the production stage) keep one home, and the {OJS OPS} galley badge doesn't infect the all-apps production spec.
**Lean: separate.** Badge hygiene plus variance ownership — galley rules hold wherever the tab appears, not only during production.

<a id="d9"></a>
**D9 — Settings taxonomy: which settings screens become features.**
Decision: the drafts agree settings tabs should mostly dissolve into the features they configure, but disagree on the exceptions.
Options: **no settings features at all** (S hardest-call 4 — every form attaches to its behavior feature, incl. genres→files, metadata-enablement→metadata, disable-submissions→wizard) vs **keep Submission-intake + Review-setup, dissolve Distribution** (Q F8/F1 + settings-decisions table) vs **keep Submission-config + a Distribution-&-licensing feature** (R46 + R49).
Consequences: S's purism maximizes intent-grouping but slices the Workflow>Submission tab across ≥4 specs (claim-scatter risk S itself flags); Q's shape keeps a settings feature only where it has its own fixtures; R's Distribution feature is a screen-shaped grouping the other two lenses both reject.
**Lean: Q's shape** — keep U58 (intake config) and U29 (review setup), dissolve Distribution (license→U40, indexing→U20, access/publishing-mode→U51, DOI agency→U45, payments toggle→U52, archiving→U67). The two kept features have their own fixtures and parameterize *multiple* consumers; Distribution's tabs each configure exactly one. Genre config consequently lives in U58 (R/Q majority) with effects tested in U36 (S dissents: genres in files).

<a id="d10"></a>
**D10 — License & permissions: own feature, metadata, or distribution.**
Options: **own L feature** (S31: license form + permission/disclosure form + reader license block + reset-permissions tool) vs **inside Publication metadata** (Q E1; R42 has the workflow side, though R also listed the license-defaults form under its R49) vs **inside a distribution-settings feature** (R49's other half).
Consequences: own = tiny but coherent (default + per-publication + display + reset tool in one QA sitting); metadata-home = one fewer spec, license as a metadata field group among others.
**Lean: fold into U40.** Majority; the license block behaves exactly like other publication metadata (default → per-publication override → reader display), and the reset-permissions tool is its settings delta.

<a id="d11"></a>
**D11 — Data availability & data citations.**
Options: **own L feature** combining both (S22) vs **data citations merge into References, availability statement rides the metadata forms** (R40 + Q E3/E1).
Consequences: S's grouping matches the shared reader display block; the majority split matches editing surfaces (citations tab cluster vs metadata form).
**Lean: dissolve per R/Q** — data citations → U42 (same tab cluster, same enrichment model), availability statement → U40; the shared reader display gets one owner (U13 screen, per ownership-follows-the-screen) with links.

<a id="d12"></a>
**D12 — JATS & Body Text: merged or split, and at which tier.**
Options: **one feature M** (R32) vs **one feature L** (Q E6) vs **two features M+M** (S28/S29 — hardest-call 7: different tabs, different jobs).
Consequences: split gives the Body Text editor (a substantial authoring surface: import/convert, fullscreen, nav guard, send-to-version) room S argues it needs; merged keeps the {OJS} badge and "machine-readable full text" intent in one spec.
**Lean: merge at M.** Two lenses merged it; the tier disagreement (Q's L) suggests the surface is smaller than S's two-M reading — M splits the difference and the tier can move at spec time.

<a id="d13"></a>
**D13 — Sections standalone vs inside "Journal setup".**
Options: **standalone Sections feature** (S66, Q A7) vs **merged with masthead/contact into Journal setup** (R44).
Consequences: R's merge couples two unrelated QA sittings (identity forms vs content-structure grids with submission/reader effects); standalone keeps sections' wizard/reader effects testable in one plan.
**Lean: standalone (U17).** Majority, and the masthead/contact half already has a consensus home (U7).

<a id="d14"></a>
**D14 — Site chrome, custom pages and blocks: three homes.**
Decision: where do (a) the header/footer frame, (b) static/custom pages, (c) custom sidebar blocks live?
Options: S — chrome rides Navigation menus (S40), pages+blocks = one "Custom pages & blocks" L (S41); R — pages ride Public site (R7), blocks ride Appearance (R9); Q — chrome is its own "Site front end & chrome" feature (A1, also holding highlights + front-page toggles), pages ride Navigation menus (A2).
Consequences: Q's chrome feature gives the front-end frame one spec but overlaps every reader feature's screens; S's shape keeps manager-authored content (pages, blocks) as one intent; R's dissolution leaves no single home for "arbitrary manager content".
**Lean: S's shape** — chrome with menus (U8), one Custom-pages-&-blocks L (U9); no two lenses agreed here, so this is a pure maintainer pick. Highlights stays its own L (S+R majority against Q's fold).

<a id="d15"></a>
**D15 — SEO/analytics feature vs dissolution.**
Options: **one L feature** for sitemap + meta tags + GA (S68, Q A13 — "page-source assertions, one sitting") vs **dissolve** (R: sitemap→public site, meta-tag injectors→landing page, GA→appearance).
Consequences: the L feature accepts mild heterogeneity to avoid three sub-3-scenario fragments; dissolution spreads head-tag assertions across three specs.
**Lean: one L feature (U20).** Majority, and both holders independently made the same "can't sustain fragments" argument.

<a id="d16"></a>
**D16 — Password management: split from login?**
Options: **inside Login & sessions** (S48, R1) vs **own L feature** (Q B3).
**Lean: inside U1.** Majority; recovery/change flows share login's fixtures and can't fill an independent test plan beyond 3 thin scenarios. Consequence of folding: U1's M tier absorbs them comfortably.

<a id="d17"></a>
**D17 — "Log in as" mechanism home.**
Options: **Login & sessions** (R1, Q B1 — it's a session mechanic surfaced from three screens) vs **Users management** (S45 — the most prominent offering surface).
Consequences: sessions-home keeps one owner for a mechanic that workflow screens (participants grid, reviewer grid) also offer without cross-linking into a management feature; users-home matches where QA would first look.
**Lean: Login & sessions (U1).** Majority, and R's argument is structural (three offering surfaces ⇒ the screen-owner rule can't pick one).

<a id="d18"></a>
**D18 — Recommend-only: who owns the flow.**
Options: **Review stage owns the recommend-controls and listing** (S6, R24) vs **Decision wizard owns the recommend-only flow** (Q D7) — with all three agreeing the *toggle* is set in Stage participants.
Consequences: this is a rule-8 variance call — recording a recommendation is the decision wizard parameterized, but the recommendation roster/status renders on the review stage.
**Lean: split by the variance litmus** — the recording flow (wizard variant, mails) in U34, the round's recommendation display in U26, the toggle in U35 with one side-effect line each way. No lens drew exactly this; flag for the FEATURE-MAP author to encode explicitly.

<a id="d19"></a>
**D19 — Alternate ("orphan `alternateTo`") email templates MAIL-076–083.**
Options: **scatter to the stage features they name** (S: copyedit→U32, layout/production→U33, editor-assign→U35; R similar) vs **home with Discussions/the Composer** (Q D14 — the atlas shows they are `alternateTo` discussion-notification templates, i.e. they surface as composer template choices).
**Lean: Q's home (U37/U34 seam).** The atlas fact decides it: their only surface is the composer's template picker; stage specs get one availability line each.

<a id="d20"></a>
**D20 — Users / Roles / bulk email carve-up.**
Decision: three sub-questions. (a) Users and Roles: one feature or two? (b) Where does Notify-users (bulk email) live? (c) Users tier.
Options: (a) **merged H** (R50) vs **split** (S45+S46, Q F5+F6 — majority). (b) **own L feature** (S55) vs **inside Users & roles** (R50) vs **inside Site settings** (Q F17, with the site-enablement gates). (c) H (S45, self-flagged for demotion) vs M (Q F5).
Consequences: (b) is a real 3-way — bulk email spans a context tab, a site form, per-role restriction and a queue job; site-settings-home buries a manager job in an admin spec, users-home bloats an already-large feature.
**Lean: split users (M) + roles (M); Notify users = own L (U55)** — it is one nameable manager job with its own send/limit/queue scenarios; the site-policy gates are its settings-that-modify-behavior.

<a id="d21"></a>
**D21 — Statistics: monolith vs usage/editorial split (vs three-way).**
Options: **one H monolith** (R53 — shared date-range/filter/download mechanics and one ETL pipeline) vs **usage H + editorial M** (S63/64, Q F14/15) vs **three-way** with COUNTER/SUSHI split out (R's own flagged alternative).
Consequences: monolith risks blowing the H ceiling (R self-flags it as the most likely forced split); the two-way split needs a home for shared filter mechanics (put in U64, U65 links); COUNTER-split adds a protocol-shaped feature à la OAI.
**Lean: two-way split (U64 H + U65 M)**, shared mechanics homed in U64. Majority, and R itself predicted this outcome.

<a id="d22"></a>
**D22 — Archiving & preservation (LOCKSS/CLOCKSS/PKP PN).**
Options: **own L feature {OJS}** (S70 — two settings forms + gateway manifest ops exist) vs **fold the switches into distribution settings** (R49) vs **park pending ruling** (Q — "no bundled PLN plugin to anchor it").
Consequences: the surfaces are real (atlas: ROUTE-037 ops `lockss`/`clockss`, AFFM-097/098) but their liveness without the PN plugin is unproven; folding leaves manifest gateways unclaimed; parking defers a tiny decision.
**Lean: own L feature (U67), first scenario is a liveness probe.** If Phase 1 finds the forms dead without the plugin, the feature collapses to an UNASSIGNED park with evidence — better than parking on suspicion.

---

## 3. Maintainer-ruling questions (scope & canon — not grouping)

<a id="q1"></a>
**Q1 — Installer & upgrader (ROUTE-012, CLI-027, CLI-031).** All three drafts park
it: a real surface, but exercising it means reinstalling the app, which the
shared-fixture e2e model cannot do.
Options: (a) **rule it out of campaign test scope** — atoms marked
out-of-scope-with-reason in their sweeps (mirroring the cli ruling), no feature
row; (b) **spec it, accept-untested** — a feature row whose scenarios are
documented for manual QA only; (c) in scope with a dedicated throwaway
environment.
**Lean: (a).** The campaign's completeness metric survives via the with-reason
marking; (b) is available later without re-ruling if a spec is ever wanted.

<a id="q2"></a>
**Q2 — OMP catalog vs APP-GLOSSARY's "Archive (back issues) → Catalog" row.**
CHARTER drops the catalog explicitly; the glossary's vocabulary table maps OJS
"Archive" to OMP "Catalog" as a plain substitution, which would pull catalog
browse INTO every archive-touching spec (QA draft flags the collision).
Options: (a) **CHARTER wins** — fix the glossary cell to a counterpart dash
("— counterpart feature: catalog"), OMP gets absence paragraphs on archive-browse
scenarios; (b) **glossary wins** — archive-browse specs cover OMP catalog browse
via substitution, shrinking the out-of-scope list.
**Lean: (a).** The glossary's own preamble says a counterpart feature is "never a
substitution" — the cell as written contradicts the file's own rule, and CHARTER
is the scope authority. (D2 follows this answer.)

<a id="q3"></a>
**Q3 — APP-GLOSSARY internal contradiction: `section → series` vs `hasSections: OMP ✗`.**
The vocabulary table maps section→series as substitution; the capability table
says OMP lacks sections ("OMP uses optional series"). Screens draft flags it; all
three drafts nevertheless converged on covering series as the sections feature's
OMP divergence.
Options: (a) **vocabulary wins for spec scope** — one Sections spec covers series
via substitution; `hasSections` remains a shared-test-layer gate only (a
shared-layer test written against OJS sections skips on OMP and OMP coverage
comes from the per-app suite) — add a clarifying note to the glossary; (b)
**capability wins** — series is OMP-unique, out of scope per CHARTER.
**Lean: (a).** It matches all three drafts' independent judgment and RUNBOOK
rule 7 (series parameterizes the same machinery); series-only extras (covers,
ISSN fields) stay register divergences unless Phase 1 shows they need own rules.

<a id="q4"></a>
**Q4 — OPS preprints archive & section-browse pages (ROUTE-081/082, AFFR-079/080).**
The glossary maps OJS "Archive (back issues)" → OPS "Preprints archive listing"
as *vocabulary* (not a counterpart dash — unlike the catalog row), but OJS's
archive lives inside the {OJS}-only Issues feature, which OPS lacks — so the
substitution has no host spec. Screens parks it; roles and QA claimed the pages
(into journal-setup/sections respectively) without confronting the glossary.
Options: (a) **in-scope OPS instantiation** — claim the section-browse pages as
U17's OPS app-specific scenarios and the flat archive listing as an OPS-specific
scenario there too (continuous posting makes the archive a section-driven
listing); (b) **out-of-scope counterpart** like the catalog — fix the glossary
cell to a dash; (c) leave parked.
**Lean: (a).** The surfaces are two templates and one handler op — trivially
small, reader-relevant, and unlike the catalog they carry no dropped object
model; the glossary cell then stands as written.

<a id="q5"></a>
**Q5 — Blanket out-of-scope for pure-infrastructure config keys.**
Screens proposes a blanket out-of-scope-with-reason header in `settings.md` for
deployment-only config sections (SET-046..051, SET-056, SET-058..061, SET-065
etc. — no user-facing behavior to specify), mirroring the cli ruling; QA instead
claims them as "reference" under its sysadmin feature; roles similar.
Options: (a) **blanket header in `settings.md`** + the sysadmin spec references
only the operator-visible few; (b) reference-claims by U61 for all of them (no
atlas-convention change needed).
**Lean: (a).** It keeps the atom-claim invariant honest — a "reference claim" on
a key no spec sentence will ever cite is a fig leaf; the cli precedent exists for
exactly this. Needs a maintainer ruling because it amends atlas conventions.

---

## 4. Merged candidate-UNASSIGNED list

Union of the three drafts, deduped. "Listed by" shows which drafts parked it;
where a draft *claimed* an atom others parked, that is said — those rows need the
maintainer (or the linked D/Q) to settle the park-vs-claim.

| Atom(s) | Reason (one line) | Listed by |
|---|---|---|
| ROUTE-043 (OJS legacy `manager` dispatcher stub) | Switch returns no handler for any op — dead routing stub. | S · R · Q |
| API-062 (OMP `publicationPeerReviews` entry point) | Instantiates a controller class absent from lib/pkp at the swept SHA — dangling mount. | S · R · Q |
| API-030 + unmounted `PkpCite`/`PkpOpenReview`/`PkpOrcidDisplay` (AFFR-211 note) | Open-peer-review data API mounted (OJS) but its display components grep to no mount — emerging surface, no reachable UI; Phase-1 liveness probe. | S · R · Q |
| VUE-004 (ExamplePage) | ui-library docs fixture, no app mount. | S · R · Q |
| VUE-042 (NavigationMenuManager component) | Manager unmounted; only its form modal (VUE-066) is wired (claimed by U8). | S · R · Q |
| NOTIF-011 (`NOTIFICATION_TYPE_PLUGIN_BASE`) | Base offset constant, zero references outside its definition. | S · R · Q |
| JOB-028/JOB-029 (TestJobFailure/TestJobSuccess) | Queue smoke-test fixtures, not product behavior. | S · Q — R attached them to its jobs feature; propose park (they assert nothing about the product). |
| AFFM-170 (OMP `statisticsSettingsForm.tpl`) | Posts to an op no PHP handler declares — dead-code candidate (OMP-only page). | S · R |
| AFFW-076 (OJS `sectionPolicy.tpl` start-submission block) | Marked deprecated 3.4; current Start Submission is the Vue form — liveness unknown. | S — R claimed it in its journal-setup feature; propose park pending Phase-1 liveness. |
| API-066 (OPS legacy non-versioned `api/genres/` entry) | Legacy alias mount outside `/v1/` — dead-code candidate pending liveness. | S |
| ROUTE-037 `lockss`/`clockss` ops | Preservation manifest gateways — fate follows **D22**. | Q (parked) — S claimed via U67. |
| ROUTE-012 + CLI-027/CLI-031 (web installer / upgrader) | Real surface, untestable in the shared-fixture model — fate follows **Q1**. | S · R · Q |
| ROUTE-081/082 + AFFR-079/080 (OPS preprints archive + section browse) | Counterpart-vs-variant unresolved — fate follows **Q4**. | S (parked) — R and Q claimed them. |
| AFFM-064 (OPS author-screening settings tab) | Renders only when screening plugins register rules; no bundled plugin does — dead-by-default. | S (parked) — R and Q claimed it in intake config; propose claim in U58 with a Phase-1 liveness flag. |
| SET-050/051/056/058/059 + other pure-infra config keys | Deployment configuration with no user-facing rules — fate follows **Q5**. | S (blanket proposal) — Q claimed as F18 reference. |
| AFFW-712–734 (legacy grid-framework chrome) | UI infrastructure shared by every legacy grid — S proposes a one-line reference block in the first spec that needs it; the crosswalk must not lose these atoms. | S (flag; not parked) |
| NOTIF-056..065 (`NOTIFICATION_TYPE_BOOK_*`) | Moved to out-of-scope (§5) per CHARTER's explicit drop — R had listed them here as dead code; the with-reason marking satisfies both readings. | R (here) · S/Q (out-of-scope) |

## 5. Merged out-of-scope candidates (per CHARTER: surfaces OJS never exposes; dropped, not parked)

Union of the three drafts, deduped by cluster; atom enumerations are the union of
the drafts' lists.

- **OMP Internal Review stage & `*_INTERNAL` decisions** (CHARTER names them):
  ROUTE-072 `internalReview` op · AFFW-248, AFFW-301, AFFW-304–322, AFFW-345–348 ·
  MAIL-071 · NOTIF-015/020/030 (internal-stage types) · MAIL-080/081 (OMP
  index-request/complete orphan templates). All three drafts agree the
  *submission-stage* skip-internal routing (AFFW-296–300) stays IN scope as a U25
  parameterization.
- **OMP monographs, chapters & work types**: AFFW-111, AFFW-123–124, AFFW-237–239,
  AFFW-275, AFFW-425, AFFW-572, AFFW-772–777 · GRID-097 · VUE-031 · AFFR-075/077 ·
  API-058 (chapter DOI rows), API-061 chapter portions · AFFU-245 (chapter row
  type) · Volume Editor / Chapter Author / Translator chapter-role vocabulary.
- **OMP publication formats & ONIX**: AFFW-276, AFFW-426, AFFW-573, AFFW-754–769 ·
  GRID-092 · VUE-044 · AFFR-076 · SET-036, SET-040 (format-file overlays) ·
  PLUG-033 (ONIX 3.0).
- **OMP catalog**: ROUTE-055/056/062 · VUE-020, VUE-101 · AFFM-263–271 · AFFR-026
  (featured/new releases blocks), AFFR-070/071/073/074, AFFR-087 (OMP browse
  block) · API-059 (catalog flags) · GRID-099 (monograph covers) · AFFW-427
  (catalog entry tab), AFFW-424 (catalogEntry half). NOTIF-038 (visit-catalog
  prompt) rides here with Q's caveat: the constant lives in shared managers, so it
  is mentionable only as a U49 register note. (Subject to **Q2**.)
- **OMP marketing & supply chain**: AFFW-257–258, AFFW-431–434, AFFW-574 ·
  GRID-089/090/091/093/094 · VUE-045.
- **OMP direct sales / approved proofs**: AFFW-770–771 · API-059 pricing flags ·
  AFFM-094's OMP use (paymethod plugins PLUG-041/042 themselves stay claimed by
  U52 per the glossary "shared paymethod plugins only" note).
- **OMP-only exporters**: PLUG-031 (CSV), PLUG-046 (monograph report), AFFM-169.
- **OMP HTML monograph viewer**: PLUG-018, AFFR-051.
- **`NOTIFICATION_TYPE_BOOK_*`**: NOTIF-056–065 (explicit CHARTER drop; no UI in
  any sweep).
- **OPS preprint relations & journal relay**: AFFW-128, AFFW-385–386 (relation
  dropdown/panel — OPS-only Vue unwired in OJS), API-065 `relate` endpoint +
  AFFR-082's relation-notice portion · PLUG-024 (preprintToJournal). (Q noted
  these could alternatively be U49 register divergences; CHARTER's "OMP/OPS-only
  Vue managers unwired in WorkflowPageOJS" clause covers them — no ruling needed.)
- **Borderline, noted**: JOB-038 (OMP series-metrics compile) — R leans claimable
  by U64 as an OMP divergence rather than dropped; propose that, since the job is
  the stats pipeline parameterized by series.

## 6. Budget sanity

Tier midpoints: H = 11.5 · M = 7 · L = 3.5 (RUNBOOK: H 10–13, M 6–8, L 3–4).

| | H | M | L | Common-scenario total |
|---|---|---|---|---|
| Unified list | 14 | 40 | 13 | 14×11.5 + 40×7 + 13×3.5 ≈ **486** |

Per app (common scenarios only; each app's suite adds its own app-specific
scenarios and absence tests on top):

- **OJS** — has every feature: ≈ **486**.
- **OMP** — skips {OJS}-only U30/U48/U50/U51/U52/U67 and {OJS OPS} U46
  (≈ −55): ≈ **431** (≈ 424 if U14 resolves OJS-only).
- **OPS** — additionally skips the review cluster U26–U32 (≈ −95 total):
  ≈ **390** (≈ 383 if U14 resolves OJS-only).

All three sit comfortably under the ≤ 700/app ceiling with ≈ 200+ headroom for
app-specific scenarios — looser than any single draft predicted (513/445/480),
mostly because the merge adopted the majority's M over several solo-H calls.

**Top demotion candidates if the map tightens** (each already carries a lens
dissent): U34 Editorial decision recording H→M (R's tier), U36 Submission files
H→M (Q's tier), U13 Article landing H→M (S's split summed smaller), U64 Usage
statistics H→M if the COUNTER surface proves thin. Conversely the drafts flagged
no L that looks under-tiered; U6 and U53 already absorbed the drafts' own
demotion flags (H→M) in this synthesis.
