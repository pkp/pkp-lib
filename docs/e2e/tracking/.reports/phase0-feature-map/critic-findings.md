# FEATURE-MAP adversarial review — critic findings

Reviewer: claude (map critic pass), 2026-07-27. Scope: FEATURE-MAP.md +
UNASSIGNED.md against CHARTER invariants, TEMPLATE rules 6/8–10, RUNBOOK
multi-app rules (esp. rule 7's 2026-07-27 maintainer test), RULINGS.md,
synthesis.md §2, APP-GLOSSARY.md, atlas/*.md. Desk work only; no files edited
beyond this report.

**Mechanical claim check: PASS.** Script-expanded every ID range in the map's
Atoms lines, the Out-of-scope tail, and UNASSIGNED, against the atlas
inventory: atlas = 2,163 atoms, dense per modality (no gaps); feature-assigned
= 2,000 unique, OOS = 149, UNASSIGNED = 14; zero duplicates within features,
zero overlaps between the three pools, zero phantom IDs, zero atlas atoms
unaccounted. The headline numbers are exactly right. The absence check (item E)
passes by construction — every atlas family (announcements, funding, payments,
static pages, OAI, sitemap, feeds, highlights, institutions, preservation…) is
findable in a feature, the OOS tail, or UNASSIGNED.

Severity counts: **2 BLOCKER · 6 SHOULD-FIX · 8 NIT**.

---

## BLOCKER

1. **BLOCKER** · Rule-7 re-test outcome not recorded for the forked-copy
   {OJS OPS} features — U13 and U46 group app-local forked code with no
   raised-probe-bar marker · U13 (ROUTE-033 `APP ArticleHandler` vs ROUTE-080
   `APP PreprintHandler` — separate app classes, no shared base named in the
   atlas; plus OJS-only viewer/landing plugins PLUG-017/020/023/025..026) and
   U46 (GRID-068 `ArticleGalleyGridHandler` vs GRID-101
   `PreprintGalleyGridHandler` — identical op lists, textbook fork; legacy
   form templates AFFW-735..739 vs 752..753 are app-side copies) · RULINGS'
   standing rule explicitly tasks the critic with this re-test, and rule 7
   says forked-copy-identical groups as ONE feature **but every shared claim
   needs probe evidence, since the subclass-chain check cannot vouch for
   copies** — the map's Notes for U13/U46 carry no such marker, so a Phase-1
   author would wrongly apply the rule-8 chain check to these features'
   shared claims · Proposed: keep both groupings (they pass the maintainer
   test as forked-copy-identical) and add one Notes line to each: "forked-copy
   feature (RUNBOOK rule 7): OJS↔OPS surfaces are app-side copies — shared
   claims need cross-app probe evidence, chain check does not apply."

2. **BLOCKER** · U69's atom list omits the shared landing-page display atoms
   whose OMP exposure only U69 can cover — the OMP book page would be specced
   without its metadata/display blocks · U69 vs U13: U13 is badged {OJS OPS}
   (D2 superseded — its spec will assert nothing about OMP), yet it claims
   `ojs omp ops`/`ojs omp` atoms whose OMP surface IS the book landing page:
   AFFR-048 (pdf.js viewer), AFFR-052..057 (preview/version notices, authors,
   DOI, keywords/abstract, chart display, references display), AFFR-061
   (versions list), AFFR-066 (how-to-cite, hooks
   `Templates::Catalog::Book|Chapter::Details`), PLUG-008, PLUG-022 — the flag
   table says this only for AFFR-052 ("OMP exposure is covered by U69"), and
   U69's own Notes/rider lines never name these atoms, so a U69 author working
   from section G would spec chapters/formats/covers and miss DOI, license
   seam, versions, how-to-cite, viewers on the same page · Proposed: add one
   rider line to U69 enumerating the U13-claimed shared atoms whose OMP
   exposure U69 covers (AFFR-048, 052..057, 061, 066 · PLUG-008, 022), with
   the reciprocal one-liner in U13's Notes.

## SHOULD-FIX

3. **SHOULD-FIX** · ⚑ AFFR-057 (references display on the landing page)
   assigned to U42 by letting a synthesis feature-definition sentence beat
   TEMPLATE rule 6's screen-ownership rule — a standing maintainer ruling
   (2026-07-26) the map's own resolution rule never included (it checked only
   rules 8–10) · Appendix AFFR row + U42/U13 Notes · Inconsistent with the
   map's own AFFR-056 call (chart display → U13 "per rule 6") and with D11's
   accepted lean, which used ownership-follows-the-screen to give the
   adjacent data-availability display block to U13 · Proposed: reassign
   AFFR-057 to U13 with a U42 side-effect line (or record an explicit
   maintainer ruling in RULINGS.md that U42 keeps it — note U42's {OJS OMP
   OPS} badge does conveniently cover the atom's OMP exposure, which is a
   real argument the appendix never states).

4. **SHOULD-FIX** · ⚑ AFFW-712..734 (23 shared legacy grid-chrome atoms)
   claimed in U36 as a "reference block" while the mirror-image case PLUG-028
   (TinyMCE — cross-cutting UI infrastructure, "claiming it anywhere would
   violate the never-force-fit invariant") is parked in UNASSIGNED — the two
   identical situations got opposite treatments, and UNASSIGNED's PLUG-028
   entry itself asks for exactly this ruling · U36 Notes + Appendix AFFW row +
   UNASSIGNED PLUG-028 · Proposed: one maintainer ruling picking a single
   convention (blanket reference-block-in-heaviest-consumer, or infra park)
   and apply it to both; recorded in RULINGS.md.

5. **SHOULD-FIX** · ROUTE-037 asymmetry: the OJS gateway dispatcher lands in
   U67 while the byte-alike OMP/OPS gateway dispatchers (ROUTE-059/076) land
   in U18, purely because the OJS fork adds `lockss`/`clockss` ops — the
   dispatcher's primary REACHABLE consumer in all three apps is the web-feed
   gateway, and U67 is the map's one liveness-doubtful feature: per D22 it
   may collapse to an UNASSIGNED park, which would strand OJS's live
   feed-gateway route with it · U67/U18 Notes + Appendix ROUTE rows ·
   Proposed: home ROUTE-037 in U18 (consistent with 059/076), U67 cites the
   lockss/clockss manifest ops as its rider — then a D22 collapse moves only
   citations, not a claimed live route.

6. **SHOULD-FIX** · AFFM-094 and MAIL-075 OMP portions dropped from the
   mixed-payload OOS accounting: both atoms are `ojs omp` (OMP = direct-sales
   payments, a CHARTER drop synthesis §5 explicitly listed as "AFFM-094's OMP
   use"), both are claimed by {OJS}-badged U52, and the Out-of-scope
   preamble's marked-at-spec-time list (ROUTE-072, API-058, API-065,
   AFFR-082, AFFW-424) omits them — the OMP halves currently have no recorded
   disposition anywhere in the map · U52 Atoms + Out-of-scope preamble ·
   Proposed: add AFFM-094 and MAIL-075 OMP portions to the preamble's
   mixed-payload list (owning spec marks them OOS at spec time).

7. **SHOULD-FIX** · U16↔U68 rider is one-way: U68's Notes claim "the
   OMP-variant category page rows" (the New Releases list inside U16-owned
   AFFR-072, per RULINGS) but U16's Notes carry no reciprocal rider — the
   map's own convention ("both features carry a one-line note") is violated on
   its newest seam, the one a U16 author is least likely to know about ·
   U16 Notes · Proposed: add to U16: "Rider: AFFR-072's OMP New-Releases rows
   are U68's (RULINGS)."

8. **SHOULD-FIX** · U14 badge {OJS OMP OPS} is stated as fact in the header
   while the Notes admit the front-end mount greps OJS-only — of the three
   provisional-badge features (U14, U30, U28-OPS-listing) U14 is the only one
   whose header badge asserts the widest reading rather than the evidenced
   one; the atlas (AFFR-058 `ojs` only) supports {OJS} + probe-to-widen ·
   U14 header + Notes · Proposed: either badge U14 {OJS} with a
   "may widen, Phase-1 probe" note (matching how U30's narrower badge is
   handled), or mark the badge itself `provisional` in the header so a spec
   session doesn't inherit an unverified three-app claim.

## NIT

9. **NIT** · Tier: U22 (My Submissions) is thin for M — 4 owned atoms
   (AFFW-027, 040, 048..049) with route/view cited from U23; its journeys
   (track, act on request, re-enter incomplete, entry route) fill L's 3–4,
   not M's 6–8 · U22 · Proposed: retier L (or note why M at spec time).

10. **NIT** · Tier: U44 (Identifiers) is heavy for L — assign/clear across
    four object types (publication, galley, file, issue) plus URN plugin
    check-digit config; 3–4 scenarios will strain to parameterize it ·
    U44 · Proposed: retier M, or accept with a Notes line that scenarios
    parameterize by object type.

11. **NIT** · Tier: U68 provisional M may be L — reader-only browsing (index,
    series, new releases, category variant, browse block, covers) with no
    forms or state machine; RULINGS says the maintainer adjusts tiers for
    U68–70 on this review, so decide now · U68 · Proposed: maintainer picks
    M or L here and strikes "provisional" from RULINGS.

12. **NIT** · ⚑ MAIL-080..081 → U37 is CORRECT but the recorded rationale is
    weaker than the evidence: the atlas shows both are
    `alternateTo="DISCUSSION_NOTIFICATION_PRODUCTION"` — production-stage
    alternates, so synthesis §5's internal-review OOS listing was factually
    wrong, not merely superseded by D19's blanket · Appendix MAIL row ·
    Proposed: add the atlas fact to the Why cell so the next reviewer doesn't
    reopen the call.

13. **NIT** · Naming: U69 "Book landing page" vs APP-GLOSSARY's
    article→monograph row (on-screen work types: Monograph, Edited Volume;
    "book" survives mainly in URLs/`CatalogBookHandler`) · U69 title ·
    Proposed: rename "Monograph landing page" or keep with a glossary note
    citing the on-screen "book" evidence, whichever the catalog UI actually
    shows.

14. **NIT** · Naming: U58 "Submission intake configuration" is a coined term
    (screen: Settings → Workflow → Submission); glossary discipline allows
    coining only when the screen offers no name and requires a definition
    home · U58 · Proposed: note at spec time that the term is coined, with
    its GLOSSARY.md entry.

15. **NIT** · U7 as "context-identity hub" home for the four settings
    dispatchers (ROUTE-017/042/063/078) + VUE-022 is code-module-shaped
    bookkeeping wearing an intent costume — harmless because eleven riders
    fan the ops out, but the justification should say plainly "dispatcher
    atoms need one bookkeeping owner" rather than implying intent grouping ·
    U7 Notes · Proposed: reword the Notes line; no reassignment needed.

16. **NIT** · Lesser forked-surface instances worth a one-line marker at spec
    time (same rule-7 class as finding 1, but with shared lib/pkp machinery
    behind them so the chain check still carries most claims): U15's search
    page templates (AFFR-083/084 `<app>` copies; OMP's separate AFFR-085),
    U18's per-app webFeed plugin trees (PLUG-030), U19's app-side OAI data
    adapters behind the shared protocol layer · U15/U18/U19 · Proposed: one
    "app-side template/adapter copies — probe the copies" line each.
