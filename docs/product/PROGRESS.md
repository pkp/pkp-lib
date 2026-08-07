# Progress — live state

**Pure state, nothing else.** Detailed findings belong in each spec's Findings
register, never here. Read together with `RUNBOOK.md` (the loop); style rules in
`TEMPLATE.md`; test rules in `docs/e2e/PRINCIPLES.md`.

**FULL RESET #2 — 2026-07-31 (maintainer decision): the Fable-only experiment,
branch `e2e_ng_2`.** Everything the campaign had built was scratched from the
working tree so the new process rebuilds it on its own evidence — the previous
build survives intact on branch `e2e_ng` for side-by-side comparison, and in
git history. Never read the scratched artifacts back; regenerating without
anchoring is the point.

Scratched: all feature specs and `GLOSSARY.md`; all Playwright tests, POMs and
fixtures in all three apps; the entire test harness (scenario API PHP,
`classes/testing/`, the shared `lib/pkp/playwright/` layer, app fleet wiring,
npm scripts, the `Config.php` env-var line); all feature and harness
`.reports/` (`u26`, `u53`, `step2-harness`); every `Claimed by:` marker in the
atlas; the app-changes and parity ledgers' rows.

**What survives**: the canon — `CHARTER.md`, `RUNBOOK.md`, `TEMPLATE.md`,
`APP-GLOSSARY.md`, `docs/e2e/PRINCIPLES.md` (contract + design record for the
harness rebuild) — plus `FEATURE-MAP.md`, the `atlas/` (all claims cleared),
`UNASSIGNED.md`, `.reports/phase0-feature-map/` (the kept map's evidence), the
lint gate (`docs/product/lint/lint-spec.mjs`, reference-integrity only), and
the `ojs-playwright-tests` skill (ojs-main `.claude/`) as design record. Test
DBs, fleet setup facts and local config files remain valid.

**The process contract for this run (2026-07-31, maintainer)**: Fable runs
every role including the orchestrator and probes — no per-role model split, no
fallback; a safeguard flag/refusal/downgrade PAUSES the feature for maintainer
review (RUNBOOK "Model discipline"). Potential security concerns go to the
private `../e2e_ng/security.md`, never to a public artifact — the fact of
routing is always stated, the content never (RUNBOOK "What goes where"). The
lint gate checks reference integrity only; all wording is the writer's
judgment. The critical goal: accurate QA/PO-readable specs plus
strong-coverage per-app tests derived from them — every rule bends to that.

**Mode: REVIEW/PILOT** — nothing runs autonomously; the maintainer launches
each step and reviews its output.

## Restart sequence (maintainer launches each)

1. **Harness rebuild** per PRINCIPLES "Scenario-endpoint design record"
   (scenario API, bootstrap roster, config factory, fleet wiring); done when
   PRINCIPLES' **Rebuild acceptance** list passes on all three fleets. The
   step-2 scenario schema is the minimal core — richer keys return per
   feature, each with a parity entry.
   **DONE 2026-07-31** — acceptance green on all three fleets (cold bootstrap,
   login smoke, scenario seeds context + staged submission per app, reset tool
   forces cold bootstrap, Mail::fake suppression verified by Mailpit count,
   RUNBOOK operational names match). UI-vs-scenario parity spot-check found 3
   defects + 1 seed defect, all fixed and re-verified same session
   (`docs/e2e/scenario-processor-audit.md`; evidence
   `.reports/step1-harness/`). Skill flipped to live truth.
2. **Features** — one per session under the RUNBOOK loop; the maintainer picks
   each next feature and reviews its output. The lint gate exists — run it,
   don't rebuild it.

## Features

Seeded from `FEATURE-MAP.md` (Phase 0, 2026-07-27) — one row per feature,
order = FEATURE-MAP order. Budget = provisional tier (H/M/L per RUNBOOK);
maintainer adjusts on review. Statuses: pending / in_progress / done / parked.

| Row | Feature | Apps | Budget | Status | Note |
|---|---|---|---|---|---|
| U1 | Login & sessions | OJS OMP OPS | M | done | Spec verified; 8+setup tests per app, green ×2 + post-fold confirm (one OMP locator race fixed test-side); register 4🐞+3❓; several observations in private file; 1 readability friction left open (Rule 14 number provenance); maintainer review pending |
| U2 | Registration & account validation | OJS OMP OPS | M | pending | |
| U3 | User profile | OJS OMP OPS | M | pending | |
| U4 | ORCID integration | OJS OMP OPS | M | pending | |
| U5 | Notifications center & email preferences | OJS OMP OPS | M | pending | |
| U6 | User invitations | OJS OMP OPS | M | done | Spec verified; 8+setup tests OJS/OMP, 9+setup OPS, green ×2 each; register 8🐞+2❓ (A1 outcome in private file); maintainer review pending |
| U7 | Journal identity & about pages | OJS OMP OPS | M | pending | |
| U8 | Navigation menus & site chrome | OJS OMP OPS | M | pending | |
| U9 | Custom pages & blocks | OJS OMP OPS | L | pending | |
| U10 | Appearance & theming | OJS OMP OPS | M | pending | |
| U11 | Highlights | OJS OMP OPS | L | pending | |
| U12 | Announcements | OJS OMP OPS | M | pending | |
| U13 | Article landing page & reading | OJS OPS | H | pending | |
| U14 | Reader comments & moderation | OJS | M | pending | |
| U15 | Search | OJS OMP OPS | M | pending | |
| U16 | Categories | OJS OMP OPS | M | pending | |
| U17 | Sections | OJS OMP OPS | M | pending | |
| U18 | Web feeds | OJS OMP OPS | L | pending | |
| U19 | OAI-PMH | OJS OMP OPS | M | pending | |
| U20 | Search-engine metadata & analytics | OJS OMP OPS | L | pending | |
| U21 | Submission wizard | OJS OMP OPS | H | pending | |
| U22 | My Submissions (author dashboard) | OJS OMP OPS | L | pending | |
| U23 | Submissions dashboard (editorial) | OJS OMP OPS | H | pending | |
| U24 | Workflow screen & stage access | OJS OMP OPS | M | pending | |
| U25 | Submission stage | OJS OMP OPS | M | done | Spec verified; 7 tests OJS, 8 OMP, 2 OPS absence (+setup each), green ×2 + post-fold confirm; register 3❓+2✅ (A2 area has private-file items); 2 readability frictions left open (Actors "onward" wording, OPS1 code-facing sentence); maintainer review pending |
| U26 | Review stage & rounds | OJS OMP | H | done | Spec verified; 12 tests OJS, 13 OMP, 1 OPS absence (+setup each), green ×2 + post-fold confirm; register 3🐞+9❓+1✅ (A3 observation in private file); maintainer review pending |
| U27 | Reviewer assignment & management | OJS OMP | H | done | Spec verified; 13 tests OJS, 14 OMP, 1 OPS absence (+setup each), green ×2 + post-parity-fix confirm; register 13🐞+4❓+1✅+3 retired (A18 silent half-add is the headliner; 3 observations in private file); U26 spec gained A10 + builder parity fixed en route (self-healing); maintainer review pending |
| U28 | Reviewer's review | OJS OMP | H | pending | |
| U29 | Review setup & review forms | OJS OMP | M | pending | |
| U30 | Author response to reviews | OJS | M | pending | |
| U31 | Reviewer suggestions | OJS OMP | L | pending | |
| U32 | Copyediting stage | OJS OMP | M | pending | |
| U33 | Production stage | OJS OMP OPS | M | pending | |
| U34 | Editorial decision recording | OJS OMP OPS | H | pending | |
| U35 | Stage participants | OJS OMP OPS | M | pending | |
| U36 | Submission files | OJS OMP OPS | H | pending | |
| U37 | Tasks & discussions | OJS OMP OPS | H | pending | |
| U38 | Submission activity log & notes | OJS OMP OPS | L | pending | |
| U39 | Submission & Publisher Libraries | OJS OMP OPS | L | pending | |
| U40 | Publication metadata | OJS OMP OPS | M | pending | |
| U41 | Contributors & affiliations | OJS OMP OPS | M | pending | |
| U42 | Citations & references | OJS OMP OPS | M | pending | |
| U43 | Funding | OJS OMP OPS | L | pending | |
| U44 | Identifiers (publisher IDs & URN) | OJS OMP OPS | M | pending | |
| U45 | DOIs | OJS OMP OPS | H | pending | |
| U46 | Galleys | OJS OPS | M | pending | |
| U47 | Media files | OJS OMP OPS | L | pending | |
| U48 | JATS & Body Text | OJS | M | pending | |
| U49 | Publish, schedule & versions | OJS OMP OPS | H | pending | |
| U50 | Issues | OJS | H | pending | |
| U51 | Subscriptions & open access control | OJS | H | pending | |
| U52 | Payments & APCs | OJS | M | pending | |
| U53 | Users management | OJS OMP OPS | M | pending | |
| U54 | Roles configuration | OJS OMP OPS | M | pending | |
| U55 | Notify users (bulk email) | OJS OMP OPS | L | pending | |
| U56 | Emails management | OJS OMP OPS | M | pending | |
| U57 | Languages & locales | OJS OMP OPS | M | pending | |
| U58 | Submission intake configuration | OJS OMP OPS | M | pending | |
| U59 | Hosted journals (site admin) | OJS OMP OPS | M | pending | |
| U60 | Site settings | OJS OMP OPS | M | pending | |
| U61 | System administration & jobs | OJS OMP OPS | M | pending | |
| U62 | Plugins management | OJS OMP OPS | M | pending | |
| U63 | Import & export | OJS OMP OPS | M | pending | |
| U64 | Statistics — usage | OJS OMP OPS | H | pending | |
| U65 | Statistics — editorial activity & reports | OJS OMP OPS | M | pending | |
| U66 | Institutions | OJS OMP OPS | L | pending | |
| U67 | Archiving & preservation | OJS | L | pending | |
| U68 | Catalog browse | OMP | L | pending | |
| U69 | Monograph landing page | OMP | M | pending | |
| U70 | Catalog management | OMP | M | pending | |

## Model-fallback log

_Anomalies only — refusals, safeguard flags, downgrades, pauses (date ·
feature · role · what happened); appended by hand (RUNBOOK Model discipline).
Routine agents are not logged._

(none)
