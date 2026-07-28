# Progress — live state

**Pure state, nothing else.** Detailed findings belong in each spec's Findings
register, never here. Read together with `RUNBOOK.md` (the loop); style rules in
`TEMPLATE.md`; test rules in `docs/e2e/PRINCIPLES.md`.

**FULL RESET 2026-07-26 (maintainer decision — start over).** Too much detail had
piled up to focus. Deleted from the working tree (git history keeps everything —
never read the old artifacts back; regenerating without anchoring is the point):

- all feature specs and all Playwright tests + the entire harness in all three
  apps (scenario API, processors, test tools, npm scripts, POMs, fixtures);
- the OJS-only atlas, FEATURE-MAP and UNASSIGNED (the next atlas covers all
  three apps);
- the campaign's app-code fixes were REVERTED — including real product bugs
  (empty masthead, dead sitemap URLs, OAI Postgres fatal, roles-grid missing
  actions, login 32-char truncation, webFeed scope) — so the new process can
  rediscover them on its own evidence.

**What survives**: the canon — `CHARTER.md`, `RUNBOOK.md`, `TEMPLATE.md`,
`APP-GLOSSARY.md`, `docs/e2e/PRINCIPLES.md` (including the
scenario-endpoint design record) — and `.claude/skills/ojs-playwright-tests/`
(ojs-main) as the harness design record. Test DBs and fleet setup facts remain
valid. As of 2026-07-26 the campaign docs live centrally in **`lib/pkp/docs/`**
(one copy shared by all three apps); tests live in each app's own repo.

**Mode: REVIEW/PILOT** — nothing runs autonomously; the maintainer launches each
step and reviews its output.

## Restart sequence (maintainer launches each)

1. **Phase 0 — surface atlas across ALL THREE apps** (the previous pass was
   OJS-only): mechanical sweeps per CHARTER Method → `atlas/*.md`, then a
   FEATURE-MAP with `apps:` applicability per feature. The modality baseline
   and atom anatomy are defined in CHARTER Method (Phase 0 may extend the
   list; the final list is recorded in the atlas). Phase 0 also seeds this
   file's Features
   table: one `pending` row per feature with a provisional `Budget:` tier
   (H/M/L per RUNBOOK) — the maintainer adjusts tiers on review.
2. **Harness rebuild** per PRINCIPLES "Scenario-endpoint design record"
   (scenario API, bootstrap roster, config factory, fleet wiring); done when
   PRINCIPLES' **Rebuild acceptance** list passes.
   **DONE 2026-07-27** — all acceptance items pass on all three fleets
   (bootstrap green, login smoke ×2 per fleet, scenario seeds + per-app
   notification-parity spot-checks, reset-forces-cold, concurrent-fleet
   Mailpit tag-scoping, RUNBOOK names aligned). Evidence:
   `docs/product/.reports/step2-harness/` (stages A–D); parity ledger seeded
   (`docs/e2e/scenario-processor-audit.md`, 18 verdicts); app-changes rows
   1–11; skill flipped to live truth. Step-2 scenario schema is the minimal
   core — richer keys return per feature.
3. **First feature** spec + per-app tests under the canon — maintainer picks;
   one feature per session, stop for review.
   **Maintainer pick (2026-07-27): U26 Review stage & rounds.** The first
   feature session also rebuilds the lint gate (RUNBOOK step 5).
   U26 DONE 2026-07-28 (row + spec committed; awaiting maintainer sign-off —
   `.reports/u26/` kept on disk until then).
   **Next pick (2026-07-28, maintainer): U6 User invitations** — lint gate
   exists now (`docs/product/lint/lint-spec.mjs`), run it, don't rebuild.
   U6's first attempt was reset to `pending` the same day when the probing
   contract changed (see "U6 RESET TO PENDING" below); it is the first feature
   to run end to end under the new loop.

**Process change 2026-07-28 (maintainer) — "the screen is the instrument".**
The pipeline kept stalling at the probe→spec fold, because the loop was
designed to produce screen-vs-server findings and then asked the spec writer to
weigh them. Four changes, live now: (a) probing is browser-only — questions
that need a request the UI would not send go to `../e2e_ng/server-questions.md`
and are not investigated here (RUNBOOK "What this work is"); (b) a new Opus
**digest** step (RUNBOOK 3b) is the only evidence artifact a Fable agent ever
reads — no Fable agent opens a probe report again, **the orchestrator
included**; (c) the **main session stays Fable** (maintainer reaffirmed the
same day) — (a) and (b) are expected to carry it, and main-session protection
stays in place; (d) a fold that stalls narrows its slice, then degrades the
item to ❓ — it never parks a feature. `adversarial verify` is now `claim
check` (step 8). If main-session flags return despite (a)+(b), moving the
orchestrator to Opus 5 is the next lever.

**Routing construct RETIRED (2026-07-28, maintainer).** The private
finding-routing rule (`permissions.md`, the Routing line in every Opus brief,
the `dropped per security routing` sentinel) is REMOVED from the whole process:
RUNBOOK, TEMPLATE, PRINCIPLES and the `ojs-playwright-tests` skill. Rationale:
the campaign does no security discovery and never intended to — it signs in as
each role and records whether the screens show what that role should see. Under
the browser-only rule there is nothing for a private channel to carry, and in
practice the construct was doing harm: it classified ordinary role×screen
reachability work as sensitive and silently deleted the claims it covered.
There is now no private class of finding and no destination for one except the
spec's Findings register. **The orchestrator also moves to Opus 5** the same
day (Fable stays pinned to spec/doc writing), and probe/claim-check returns
become pointers rather than findings.

Claims lost to the retired rule, to re-check: **U6** item 14 (who reaches the
invitation screens — re-probe before the digest, marked in
`.reports/u6/probe-d.md`); **U26** verify items 10 and 13 — the item numbers
map to Actors-table rows in order (10 sits between "Delete the submission from
the Review stage" and "Read a review"), so both are recoverable on U26's
sign-off pass.

**U6 RESET TO PENDING (2026-07-28).** Every U6 artifact was produced under the
superseded contract, so the feature starts over from step 1 with nothing to
anchor on (RUNBOOK "Rebuilding a feature from scratch" — the author works blind;
a rebuild checked against the old version reimports the scope we just dropped).
Removed: the spec draft, the probe list, all five probe reports, and the
`Claimed by: U6` markers on 33 atlas atoms (those get re-claimed by the new
run). No U6 tests were ever written. The next session opens U6 as an ordinary
`pending` row and runs the loop from the top under the new contract.

U26 was built under the old contract and is left as-is pending maintainer
sign-off; sweeping its register to the new scope is a separate decision.

## Features

Seeded from `FEATURE-MAP.md` (Phase 0, 2026-07-27) — one row per feature,
order = FEATURE-MAP order. Budget = provisional tier (H/M/L per RUNBOOK);
maintainer adjusts on review. Statuses: pending / in_progress / done / parked.

| Row | Feature | Apps | Budget | Status | Note |
|---|---|---|---|---|---|
| U1 | Login & sessions | OJS OMP OPS | M | pending | |
| U2 | Registration & account validation | OJS OMP OPS | M | pending | |
| U3 | User profile | OJS OMP OPS | M | pending | |
| U4 | ORCID integration | OJS OMP OPS | M | pending | |
| U5 | Notifications center & email preferences | OJS OMP OPS | M | pending | |
| U6 | User invitations | OJS OMP OPS | M | in_progress | Attempt 2 (2026-07-28) under the new contract, blind rebuild (attempt 1 reset when the probing contract changed) |
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
| U25 | Submission stage | OJS OMP OPS | M | pending | |
| U26 | Review stage & rounds | OJS OMP | H | done | Attempt 2 (blind rebuild) 2026-07-27/28: spec verified, lint-clean; tests OJS 11 / OMP 12 / OPS 1 absence, each green ×2; lint gate rebuilt (`docs/product/lint/lint-spec.mjs`); review first: OMP4 (press recommendations unreachable), OMP1✅/OMP6🐞 cancel-landing split, A8 rebadge; harness leads in `.reports/u26/tests-omp.md` |
| U27 | Reviewer assignment & management | OJS OMP | H | pending | |
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

_Rows for Fable-pinned agents and Opus anomalies only, appended by hand
(RUNBOOK Model discipline)._

- 2026-07-26 · phase0-atlas · sweep author · routes · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · grids · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · vue · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · api · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · notif · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · mail · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · jobs · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · settings · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · plugins · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · cli · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · aff-workflow · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · aff-management (incl. top-ups) · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · aff-reader · claude-fable-5
- 2026-07-26 · phase0-atlas · sweep author · aff-user · claude-fable-5
- 2026-07-27 · phase0-feature-map · taxonomy author · screens lens · claude-fable-5
- 2026-07-27 · phase0-feature-map · taxonomy author · roles lens · claude-fable-5
- 2026-07-27 · phase0-feature-map · taxonomy author · qa lens · claude-fable-5
- 2026-07-27 · phase0-feature-map · synthesis · merge+register · claude-fable-5
- 2026-07-27 · phase0-feature-map · probe (Opus) anomaly · omp-catalog + omp-series lineage · both killed mid-run by an account API error (terms acceptance), resumed from transcript, completed clean — environmental, not a model flip
- 2026-07-27 · phase0-feature-map · crosswalk authors ×6 · affw / affm-set / affu-notif / affr-route-vue / grid-api-mail / job-plug-cli · claude-fable-5 (all six)
- 2026-07-27 · phase0-feature-map · assembler · FEATURE-MAP + UNASSIGNED · claude-fable-5
- 2026-07-27 · phase0-feature-map · map critic · adversarial review · claude-fable-5
- 2026-07-27 · phase0-feature-map · fix agent · critic fixes applied · claude-fable-5
- 2026-07-27 · U26 · spec author · draft + GLOSSARY + probe list · claude-fable-5 -discarded (attempt 1 scrapped; feature restarted from scratch)
- 2026-07-27 · U26 · spec author (attempt 2) · draft + GLOSSARY + probe list · claude-fable-5
- 2026-07-27 · U26 · finalizer · probe fold · claude-fable-5
- 2026-07-28 · U26 · verification fold · merge-list fold + APP-GLOSSARY cell · claude-fable-5
- 2026-07-28 · U26 · readability verifier · persona pass (1 blocker, 6 frictions) · claude-fable-5
- 2026-07-28 · U26 · readability rewrite · stumble fixes · claude-fable-5
- 2026-07-28 · U6 · spec author · draft + probe list · claude-fable-5 -discarded
  (attempt 1; feature reset to pending when the probing contract changed)
- 2026-07-28 · U6 · finalizer · never started — the probe fold was the block
  that triggered the process change above
- 2026-07-28 · U6 · spec author (attempt 2) · draft + probe list · claude-fable-5
