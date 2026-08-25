# Progress — live state

**Pure state.** Row notes are SHORT (1–3 lines) and may carry register
highlights (🐞/❓ counts, the headline finding, low-confidence flags); finding
DETAIL lives in each spec's Findings register, never here. Read together with
`RUNBOOK.md` (the loop); style rules in `TEMPLATE.md`; test rules in
`lib/pkp/docs/e2e/process/PRINCIPLES.md`.

**This run**: branch `e2e_ng_2`, started 2026-07-31 as a clean-room rebuild
(FULL RESET #2 — the previous build survives on branch `e2e_ng` and in git
history; **never read the scratched artifacts back** — regenerating on this
run's own evidence is the point). The harness rebuild (the restart's first step) passed
PRINCIPLES' Rebuild-acceptance on all three fleets 2026-07-31; since then,
features run one per session under the RUNBOOK loop, maintainer-picked.

**The process contract**: Fable runs every role — no per-role model split, no
fallback; a safeguard flag/refusal/downgrade PAUSES the feature for
maintainer review (RUNBOOK "Model discipline"). Potential security concerns
go to the private `../e2e_ng/security.md`, never to a public artifact —
writes there are read-first and deduped per the RUNBOOK hygiene rules; the
fact of routing is always stated, the content never (RUNBOOK "What goes
where"). The lint gate checks reference integrity only; wording is the
writer's judgment. The critical goal: accurate QA/PO-readable specs plus
strong-coverage per-app tests derived from them — every rule bends to that.

**Mode: REVIEW/PILOT** — nothing runs autonomously; the maintainer launches
each step and reviews its output.

## Features

Seeded from `FEATURE-MAP.md` (Phase 0, 2026-07-27) — one row per feature,
order = FEATURE-MAP order. Budget = provisional tier (H/M/L per RUNBOOK);
maintainer adjusts on review. Statuses: pending / in_progress / done / parked.

| Row | Feature | Apps | Budget | Status | Note |
|---|---|---|---|---|---|
| U01 | Login & sessions | OJS OMP OPS | M | done | Spec verified; 8+setup tests per app, green ×2 + post-fold confirm; several observations in private file; **maintainer review DONE 2026-08-25** — register fully triaged (now 6🐞+1❓+1✅ incl. new A8): A1–A4, A7–A8 confirmed with fix rulings, A5 to triage with the team, A6 ✅ intended (pkp/pkp-lib#12162); 2026-08-25 upstream-rebase check: impersonation side-effect updated (#13059 — event log names both users), tests unaffected |
| U02 | Registration & account validation | OJS OMP OPS | M | pending | |
| U03 | User profile | OJS OMP OPS | M | pending | |
| U04 | ORCID integration | OJS OMP OPS | M | paused | Suites `describe.skip`ped in all three fleets 2026-08-20 (maintainer): pending a decision on handling ORCID's external communication in tests (mock vs dead-port proxy). Spec verified; 9 tests OJS, 8 OMP, 9 OPS incl. 2 absence (+setup each), green ×2 + post-fold confirm; register 6🐞+5❓+2✅ (A5 Assistant false-success + A1 silent-close/over-offer are the headliners); claim check 40 claims 0 wrong; OAuth/deposit legs code-anchored (dead-port proxy + dummy ORCID sandbox credentials); skill rule-7 job_runner note fixed (self-healing); 2026-08-25 upstream-rebase check: A7 resolved upstream for OJS/OMP (pkp/pkp-lib#13050), noted in register; maintainer review pending |
| U05 | Notifications center & email preferences | OJS OMP OPS | M | pending | |
| U06 | User invitations | OJS OMP OPS | M | done | Spec verified; 8+setup tests OJS/OMP, 9+setup OPS, green ×2 each; register 8🐞+2❓ (A1 outcome in private file); maintainer review pending |
| U07 | Journal identity & about pages | OJS OMP OPS | M | pending | |
| U08 | Navigation menus & site chrome | OJS OMP OPS | M | pending | |
| U09 | Custom pages & blocks | OJS OMP OPS | L | pending | |
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
| U25 | Submission stage | OJS OMP OPS | M | done | Spec verified; 7 tests OJS, 8 OMP, 2 OPS absence (+setup each), green ×2 + post-fold confirm; register 3❓+2✅ (A2 area has private-file items); 2 readability frictions left open (Actors "onward" wording, OPS1 code-facing sentence); 2026-08-25 upstream-rebase check: clean; maintainer review pending |
| U26 | Review stage & rounds | OJS OMP | H | done | Spec verified; 12 tests OJS, 13 OMP, 1 OPS absence (+setup each), green ×2 + post-fold confirm; register 3🐞+9❓+1✅ (A3 observation in private file); 2026-08-25 upstream-rebase check: clean on shared+OJS ranges (parity holds; PMUR publish-gate note in parity ledger); OMP range: OMP2's form-field half confirmed intended upstream (recommendations flag now false on presses); maintainer review pending |
| U27 | Reviewer assignment & management | OJS OMP | H | done | Spec verified; 13 tests OJS, 14 OMP, 1 OPS absence (+setup each), green ×2 + post-parity-fix confirm; register 12🐞+3❓+1✅+5 retired (A18 silent half-add is the headliner; 3 observations in private file); U26 spec gained A10 + builder parity fixed en route (self-healing); 2026-08-25 upstream-rebase check: A11 fixed upstream (#13162), A5 moot (#10403 revert), Add Reviewer modal now shows the reviewer's email — spec+register updated, tests unaffected; OMP now exposes the reviewAssignments API route (editor edit-review claims worth an OMP re-probe at next touch); maintainer review pending |
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
