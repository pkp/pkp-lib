# UNASSIGNED — parked atoms & dead-code candidates

A campaign deliverable per CHARTER's atom-claim invariant and liveness rule:
atoms no spec claims land here **with a reason**, alongside dead-code
candidates surfaced by probes. Every entry names what would resolve it (a
Phase-1 probe or a maintainer ruling). The claim check counts these as
accounted for; the campaign is done only when each entry is either claimed by a
spec, confirmed dead (stays here with evidence), or ruled out of scope.

Sources: the six crosswalks in `.reports/phase0-feature-map/` (their UNASSIGNED
lists, consistent with `synthesis.md` §4) + `RULINGS.md`'s probe-derived
dead-code additions + U26 spec-time findings (2026-07-27). **18 parked atoms**
+ **8 noted dead-code/defect candidates attached to claimed atoms**. (PLUG-028
moved to FEATURE-MAP's Out-of-scope tail per the cross-cutting UI
infrastructure orchestrator ruling, 2026-07-27 — see RULINGS.md.)

## Parked atoms (18)

### AFFW-701, AFFW-703, AFFW-704 — legacy author-dashboard round surfaces
- What: the old author-dashboard templates' editor-message link, author
  reviewer grid, and review attachments + revisions grids (FEATURE-MAP had
  them under U26).
- Why parked: dead — the U26 build found the legacy author-dashboard round
  panel unreachable: its URLs answer 404 and the current author round view is
  the shared workflow screen (spec footnote on retired legacy surfaces;
  AFFW-702's email-modal body remains live via the Notifications panel and
  stays claimed by U26).
- Resolves: maintainer confirmation as dead code (removal candidates).

### GRID-011, GRID-024 — legacy author review-attachment / revisions grids
- What: author-view grids for review attachments and uploaded revision files,
  mounted only from the retired author-dashboard round panel (AFFW-703/704).
- Why parked: dead with their mount — no reachable screen loads them in the
  current UI (U26 build, same evidence as above; the open-review attachment
  grid GRID-010 is live inside the read-review sheet and stays claimed).
- Resolves: maintainer confirmation as dead code (removal candidates).

### AFFW-076 — sectionPolicy.tpl start-submission block
- What: OJS template block offering "start a submission" from the section
  policy display; marked deprecated 3.4 — the current Start Submission is the
  Vue form.
- Why parked: liveness unknown; claiming it in U17 or U21 would document a
  possibly-dead surface.
- Resolves: Phase-1 liveness probe (is the block reachable in the current UI?).

### AFFM-170 — OMP statisticsSettingsForm.tpl
- What: OMP-only settings template that posts to an op no PHP handler declares.
- Why parked: dead-code candidate — the post target does not exist.
- Resolves: Phase-1 probe (does the page render/submit at all?); if dead, stays
  here as confirmed; if live, claim in U64.

### NOTIF-011 — NOTIFICATION_TYPE_PLUGIN_BASE
- What: plugin-type base offset constant.
- Why parked: zero references outside its definition — nothing to specify.
- Resolves: maintainer ruling to confirm dead (grep evidence already in
  synthesis §4); no probe needed.

### ROUTE-043 — OJS legacy `manager` dispatcher stub
- What: legacy page-handler switch that returns no handler for any op.
- Why parked: dead routing stub, no reachable behavior.
- Resolves: maintainer confirmation as dead code (candidate for removal).

### VUE-004 — ExamplePage
- What: ui-library docs example fixture.
- Why parked: no app mount; not product behavior.
- Resolves: none needed — remains here as a permanent non-product atom unless
  the atlas convention adds a "fixture" marking.

### VUE-042 — NavigationMenuManager component
- What: Vue manager component for navigation menus.
- Why parked: unmounted in any app; only its form modal (VUE-066, claimed by
  U8) is wired.
- Resolves: Phase-1 grep/probe at spec time for U8 — if a mount appears, claim
  there; else confirmed dead-code candidate.

### API-030 — open-peer-review data API (+ unmounted display components)
- What: OJS-mounted API whose display components (`PkpCite`, `PkpOpenReview`,
  `PkpOrcidDisplay` — recorded in `atlas/affordances-reader.md`'s boundary
  notes, no atom IDs) grep to no mount.
- Why parked: emerging surface with no reachable UI.
- Resolves: Phase-1 liveness probe; if a UI exists, likely U13/U26 territory;
  else stays parked as pre-release machinery.

### API-062 — OMP `publicationPeerReviews` entry point
- What: API mount instantiating a controller class absent from lib/pkp at the
  swept SHA.
- Why parked: dangling mount (confirmed by task ruling in the crosswalk).
- Resolves: maintainer confirmation as dead code / upstream sync artifact.

### API-066 — OPS legacy non-versioned `api/genres/` entry
- What: legacy alias mount outside `/v1/`.
- Why parked: dead-code candidate pending liveness.
- Resolves: Phase-1 probe (does the alias respond?); if live, claim as a U58
  reference row.

### JOB-001 — abstract queued-job base class
- What: framework base, no feature-visible behavior.
- Why parked: nothing to specify.
- Resolves: none needed — permanent infra atom (mirrors the Q5 infra logic at
  job level).

### JOB-028, JOB-029 — TestJobFailure / TestJobSuccess
- What: queue smoke-test fixtures.
- Why parked: they assert nothing about the product (synthesis §4 park; R's
  claim-in-jobs reading rejected there).
- Resolves: none needed; optionally citable by U61 as a reference line if the
  jobs spec wants a smoke-test mention.

### JOB-047 — abstract staged-file-processing framework base
- What: framework base for staged file processing jobs.
- Why parked: no feature-visible behavior of its own.
- Resolves: none needed — permanent infra atom.

## Dead-code / defect candidates attached to claimed atoms (RULINGS + spec-time additions)

These atoms ARE claimed in FEATURE-MAP; the notes below travel with them to
spec time as register material. Listed here so the candidates have one home
until their specs exist. Do not force-claim the defects themselves.

1. **catalogCategory.tpl unassigned variables** — attached to **U68**
   (OMP-variant category page rows; seam with U16's shared category page).
   The template reads `$featuredMonographIds` / `$newReleasesMonographs`,
   never assigned by the shared handler that renders it
   (probe-omp-catalog.md §7). Resolves: U68/U16 spec-time register entry;
   probe confirms the display silently no-ops.
2. **Dangling `results` op** — attached to **U68** (ROUTE-056, OMP
   `pages/catalog/index.php`). The route map dispatches op `results` to a
   method that exists nowhere in the handler chain (probe-omp-catalog.md §7).
   Resolves: U68 spec-time register entry; dead-code candidate for removal.
3. **SectionController `filterByTypeIds()` dispatch** — attached to **U17**
   (API-034). The shared controller dispatches a collector method no section
   collector defines (probe-omp-series.md). Resolves: U17 spec-time register
   entry (latent error path); probe the endpoint with the filter param.
4. **Missing OMP dashboard series filter** — attached to **U17/U21** (register
   material at spec time, note only — RULINGS). OMP lacks the series filter
   OJS/OPS get from the shared `addSectionFields()` (probe-omp-series.md).
   Resolves: spec-time ❓ register entry — bug-vs-intended call for the team.
5. **`api/v1/sections` mount gap** — attached to **U17** (API-034). Mounted by
   OJS only; OMP and OPS both lack the mount — a mounting gap, not an OMP
   divergence (probe-omp-series.md corrects the api sweep's "ojs-only"
   framing). Resolves: spec-time register entry; maintainer call on whether
   OMP/OPS should mount it.
6. **OMP recommend-only controls inert after a participant-panel flag flip** —
   attached to **U35** (stage participants). Recommendation controls render
   but do nothing when "Recommend only" is switched on through the
   Participants panel's edit form; the same flag applied at assignment time
   works (U26 verification, `.reports/u26/verify-chunk4.md` unresolved
   section). U26's display rules are unaffected. Resolves: U35 spec-time
   check of the participant-edit path before anyone states it as a defect.
7. **Untranslated help-icon name in shared side-modal chrome** — no owning
   spec yet. The Help icon's screen-reader-only name renders as a raw locale
   placeholder on top bars and side modals; direct measurement says all three
   apps, one report saw OJS clean — conflict carried, not resolved
   (U26 verification). Incidental: a template helper registers a method that
   does not exist. Resolves: claim by whichever spec takes shared page
   chrome, or a maintainer-approved fix.
8. **OMP "Request Revisions" author email invites a response OMP cannot
   collect** — attached to **U30** (author response) / mail templates. The
   press email asks the author to "submit your response" but OMP mounts no
   Author Response panel (U26 register OMP5 documents the panel absence; the
   template mismatch is U30's / the mail templates'). Resolves: U30 spec-time
   register entry; reconcile template with the press roster.
