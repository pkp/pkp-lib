# Crosswalk — JOB / PLUG / CLI atoms → unified features (U1–U70)

- **Date**: 2026-07-27 · desk crosswalk for FEATURE-MAP Stage D.
- **Inputs**: synthesis.md §1 (taxonomy, fixed), §4–5 (parks/OOS) · RULINGS.md
  (all D-leans accepted; Q1 installer OOS; U68/U69/U70 added) · atlas/README.md
  cli ruling (blanket) · atlas/jobs.md · atlas/plugins.md · atlas/cli.md.
- **Rules applied**: jobs → the feature whose behavior queues/schedules them;
  infra jobs with no feature-visible effect → UNASSIGNED or U61; plugins → the
  feature they extend; ALL CLI atoms → `OOS: cli-ruling` (no reference claims
  at crosswalk time). `?` = best candidate, one-line doubt.
- **Coverage**: JOB-001..067 (67) + PLUG-001..049 (49) + CLI-001..031 (31) =
  147 IDs, no gaps.

## Jobs (JOB-001..067)

JOB-001 → UNASSIGNED: abstract queued-job base class, no feature-visible behavior
JOB-002 → U55
JOB-003..007 → U42
JOB-008..010 → U45
JOB-011 → U23 ? outstanding-editorial-actions digest mirrors the dashboard's needs-attention queue; U37 (tasks) was the runner-up
JOB-012 → U27
JOB-013 → U6
JOB-014 → U12
JOB-015..016 → U65
JOB-017..020 → U4
JOB-021..026 → U64
JOB-027 → U15
JOB-028..029 → UNASSIGNED: queue smoke-test fixtures, assert nothing about the product (synthesis §4 park)
JOB-030 → U45
JOB-031 → U50
JOB-032 → U51
JOB-033..034 → U4
JOB-035..044 → U64
JOB-045 → U45
JOB-046 → U23 ? scheduled dispatcher of JOB-011 — same doubt, same home
JOB-047 → UNASSIGNED: abstract staged-file-processing framework base, no feature-visible behavior of its own
JOB-048 → U64
JOB-049 → U61
JOB-050 → U49
JOB-051 → U6
JOB-052 → U61
JOB-053 → U2
JOB-054 → U29 ? the automated reminder clock is U29's fixture per D6; the manual remind path stays U27 (JOB-012)
JOB-055 → U65
JOB-056 → U64
JOB-057 → U41
JOB-058..059 → U51
JOB-060 → U64
JOB-061 → U63
JOB-062 → U42 ? citation-DOI enrichment behavior, but it ships inside the U45-owned Crossref plugin
JOB-063..067 → U61

Notes: JOB-038 (OMP series metrics) rides U64 per synthesis §5 borderline lean
(stats pipeline parameterized by series), accepted by RULINGS' "everything not
listed follows the lean". JOB-065 is an abstract base grouped with its scheduler
infra siblings into U61 per the sysadmin-shape option.

## Plugins (PLUG-001..049)

PLUG-001 → U16 ? browse block surfaces category/series browse links; OMP series browse belongs to U68 — register note at spec time
PLUG-002 → U8
PLUG-003 → U7
PLUG-004 → U57
PLUG-005 → U21 ? CTA block whose whole behavior is the wizard entry route; U8 (chrome) was the runner-up
PLUG-006 → U51
PLUG-007 → U12
PLUG-008 → U13
PLUG-009 → U45
PLUG-010 → U9
PLUG-011 → U45
PLUG-012 → U63
PLUG-013 → U19
PLUG-014..016 → U20
PLUG-017 → U13
PLUG-018 → U69 ? synthesis §5 listed the HTML monograph viewer OOS, but the Q2 scope extension pulls book-landing reader surfaces into U69 and RULINGS' retained-OOS list omits this cluster — confirm
PLUG-019 → U48
PLUG-020 → U13
PLUG-021 → U4
PLUG-022..023 → U13
PLUG-024 → OOS: OPS preprint relations & journal relay
PLUG-025..026 → U13
PLUG-027 → U9
PLUG-028 → UNASSIGNED: cross-cutting rich-text editor infrastructure (cannot be disabled), extends every form — no single owning feature
PLUG-029 → U64
PLUG-030 → U18
PLUG-031 → OOS: OMP-only exporters
PLUG-032 → U63
PLUG-033 → OOS: OMP publication formats & ONIX
PLUG-034..035 → U63
PLUG-036 → U19 ? schema adapter whose only consumer surface is the oai_dc crosswalk
PLUG-037..040 → U19
PLUG-041..042 → U52
PLUG-043 → U44
PLUG-044 → U65
PLUG-045 → U64
PLUG-046 → OOS: OMP-only exporters
PLUG-047..048 → U65
PLUG-049 → U10

Notes: viewer plugins (PLUG-017/020/022) home in U13 per D1 (merged landing
feature owns galley viewing). Report plugins split by domain: usage/COUNTER
(PLUG-045) → U64, editorial-style CSV reports (PLUG-044/047/048) → U65 per
D21's two-way split ("report plugins" named in U65; COUNTER named in U64).

## CLI (CLI-001..031)

CLI-001..031 → OOS: cli-ruling

Notes: blanket maintainer ruling 2026-07-27 (atlas/README.md; reason header in
cli.md) — CLI atoms never seed features or scenarios; reference claims happen
at spec time, not in this crosswalk. CLI-027/031 are additionally covered by
Q1(a) (installer/upgrader out of campaign test scope).

## Totals

| Bucket | JOB | PLUG | CLI | Total |
|---|---|---|---|---|
| Feature-assigned (U##) | 63 | 44 | 0 | 107 |
| UNASSIGNED | 4 | 1 | 0 | 5 |
| OOS | 0 | 4 | 31 | 35 |
| **All** | **67** | **49** | **31** | **147** |

`?` flags: 8 (JOB-011, JOB-046, JOB-054, JOB-062, PLUG-001, PLUG-005,
PLUG-018, PLUG-036).
