# Stage C rulings — applied to the Stage D crosswalk

**Standing maintainer rule (2026-07-27, recorded in RUNBOOK rule 7)**: a
cross-app feature is grouped as ONE shared feature only when it is
essentially the same feature rebranded — sharing most of the code and
business logic. Otherwise it is an app-specific feature. Forked-copy code
with identical business logic (OJS↔OPS pattern) groups as one feature with a
raised probe bar. The map critic must re-test every {multi-app} feature
whose surfaces are app-local code against this rule.

Decision record for building FEATURE-MAP.md from synthesis.md. Maintainer
rulings 2026-07-27 (review session); everything not listed here follows the
stated lean in synthesis.md §2–3 verbatim.

## Disagreements (synthesis §2)

All 22 leans ACCEPTED as stated: D1 merge (one H landing feature), D3 one
Issues feature, D4 fold reviewer list into U28, D5 own feature, D6 U29, D7 own
L (U31), D8 galleys separate, D9 Q's settings shape (keep U58 + U29, dissolve
Distribution per the D9 text), D10 fold license into U40, D11 dissolve per
R/Q, D12 merge at M, D13 sections standalone (U17), D14 S's shape (chrome with
menus U8; one Custom-pages-&-blocks L U9; Highlights own L), D15 one L (U20),
D16 fold into U1, D17 U1, D18 split by variance litmus exactly as the lean
describes, D19 composer home (U37/U34 seam), D20 split users M + roles M +
Notify-users own L (U55), D21 two-way split (U64 H + U65 M), D22 own L (U67)
with liveness-probe-first scenario.

**D2 — superseded by the maintainer scope extension below**: U13 badges
{OJS OPS}; OMP's book landing page is covered by the new OMP-specific feature
U69, not by U13 and not by an absence paragraph. U13 carries one counterpart
line pointing at U69.

## Maintainer questions (synthesis §3)

- **Q1 (a)**: installer/upgrader out of campaign test scope —
  ROUTE-012, CLI-027, CLI-031 marked out-of-scope-with-reason.
- **Q2 — amended by maintainer scope extension** (2026-07-27, evidence:
  `probe-omp-catalog.md`): the OMP catalog cluster's reader and management
  surfaces come INTO scope as OMP-specific features (see below). The
  APP-GLOSSARY "Archive → Catalog" cell is corrected to a counterpart
  reference (→ U68), per the glossary's own counterpart rule. The catalog's
  *object-model* remains out of scope: chapters authoring, publication-format
  authoring, ONIX/codelists, marketing/supply-chain/direct-sales, internal
  review (CHARTER's dropped list minus the catalog reader/management
  surfaces).
- **Q3 (a)**: series is the sections machinery relabeled (evidence:
  `probe-omp-series.md` — shared PKPSection chain, ASSOC_TYPE_SERIES =
  ASSOC_TYPE_SECTION, shared PKPSectionForm base). One Sections spec (U17)
  covers OMP series via glossary substitution; OMP's field adds/lacks are
  future register divergences. APP-GLOSSARY gets a clarifying note
  (vocabulary wins for spec scope; hasSections gates shared-layer tests
  only). OMP reader browse-by-series belongs to U68 (catalog), NOT U17.
- **Q4 (a)**: OPS preprints archive + section-browse pages are in scope as
  U17's OPS-specific scenarios (ROUTE-081/082, AFFR-079/080).
- **Q5 (a)**: blanket out-of-scope-with-reason header in `settings.md` for
  pure-infrastructure config sections (deployment-only keys per synthesis
  §3 Q5 list); those SET atoms are OOS-infra in the crosswalk. The
  operator-visible few referenced by U61 stay claimable as reference.

## New OMP-specific features (maintainer scope extension, 2026-07-27)

Evidence basis (`probe-omp-catalog.md`): catalog index / browse-by-series /
new-releases pages, catalog management, featured/new-release DAOs and the
book landing handler are OMP-own machinery with no OJS/OPS counterpart;
browse-by-category (`PKPCatalogHandler::category`) is a SHARED three-app
surface and belongs to the shared reader-browse feature per the synthesis
list, not to these rows.

- **U68 — Catalog browse {OMP} (M)**: catalog index, browse-by-series,
  new releases; the OMP-variant category page rows; NMI_TYPE_SERIES menu
  entries. (Reader-facing.)
- **U69 — Monograph landing page {OMP} (M)** (renamed from "Book landing
  page" on critic review 2026-07-27, per APP-GLOSSARY's article→monograph
  row): CatalogBookHandler surface —
  monograph landing incl. chapter/format display, download/purchase links,
  how-to-cite as displayed. (Format/chapter AUTHORING stays out of scope.)
- **U70 — Catalog management {OMP} (M)**: ManageCatalogPage/CatalogListPanel,
  add-to-catalog, featured/new-release flags and ordering (incl. the
  saveDisplayFlags/saveFeaturedOrder controller ops), AFFM-263..271.

Tier: U68 L per critic review; U69/U70 remain provisional M — maintainer
confirms at sign-off.

Orchestrator clarification (2026-07-27, follows from the extension): OMP's
`htmlMonographFile` viewer plugin (PLUG-018) — and its reader-surface atoms,
e.g. AFFR-051 — are IN scope under U69: the book landing page's
format-display surface, the OMP analogue of the OJS/OPS viewer plugins under
U13. The synthesis §5 OOS entry for it is superseded.

## Critic-review rulings (2026-07-27)

Cross-cutting UI infrastructure ruling (2026-07-27): framework chrome with no
feature of its own is out-of-scope-with-reason ("exercised implicitly by every
consuming feature's scenarios"), mirroring the cli/Q5 pattern. Applied to
AFFW-712..734 (formerly a U36 reference block) and PLUG-028 (formerly an
UNASSIGNED park) — both now in FEATURE-MAP's Out-of-scope tail.

## New dead-code / defect candidates from the probes (route to UNASSIGNED
notes or future registers, do not force-claim)

- OMP `catalogCategory.tpl` reads `$featuredMonographIds`/
  `$newReleasesMonographs` never assigned by the shared handler that renders
  it (probe-omp-catalog.md §7).
- OMP `pages/catalog/index.php` routes op `results` to a method that exists
  nowhere in the chain (ditto).
- Shared `SectionController` dispatches `filterByTypeIds()` which no section
  collector defines (probe-omp-series.md).
- OMP lacks the dashboard series filter OJS/OPS get from shared
  `addSectionFields()` (probe-omp-series.md — register material for U17/U21
  at spec time, note only).
- `api/v1/sections` mounted by OJS only; OMP and OPS both lack the mount
  (probe-omp-series.md — corrects the api sweep's "ojs-only" framing: it is
  a mounting gap, not an OMP divergence).
