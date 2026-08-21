# Atlas — Phase 0 surface sweeps (OJS · OMP · OPS)

Mechanical enumeration of the apps' surface per the campaign Method (RUNBOOK "Mission, scope & invariants"). Atoms only:
no analysis, no liveness judgment — dead/unreachable surfaces are expected
here and get resolved in Phase 1 (→ `UNASSIGNED.md`). Overlap between
modalities is deliberate and harmless.

## Final modality list (Method baseline + Phase-0 extensions)

Entry points: `routes` · `grids` · `vue` · `api` · `notif` · `mail` · `jobs`
· `settings` · `plugins` · `cli` — one file each.

Within-screen affordances (Phase-0 extension: the `aff` modality is split
into four area sub-modalities so sweeps could run in parallel with dense IDs):

- `AFFW` — submission wizard + editorial workflow screens → `affordances-workflow.md`
- `AFFM` — management/settings + site administration screens → `affordances-management.md`
- `AFFR` — reader-facing front end → `affordances-reader.md`
- `AFFU` — user account/profile/registration + reviewer + DOI/stats screens → `affordances-user.md`

## Conventions

- Atom anatomy per the campaign Method: `<MOD>-<seq>` (dense per modality, never
  renumbered) · `apps:` · stable code pointer (symbol, never line numbers) ·
  one line. `Claimed by:` is added later by the orchestrator only.
- Granularity ruling (maintainer-approved 2026-07-26): routes/grids/api emit
  ONE atom per handler/controller class with its ops/endpoints enumerated
  inline — the claim unit stays feature-sized; every op remains greppable.
- `apps:` on shared (lib/pkp) atoms defaults to `ojs omp ops`; deviations are
  mechanical facts (entry-point mounts, `Repository::map()` registrations,
  app overrides), recorded per sweep. `apps: ?` = wiring not mechanically
  determinable; Phase 1 resolves it.
- **Maintainer ruling (2026-07-27) — cli modality**: out-of-scope for test
  coverage by default. CLI atoms never seed features or scenarios; a spec may
  claim one only as reference — the "CLI-only" operational counterpart of a
  UI feature it documents (analogous to the scope ruling's API-only rule). Unclaimed
  CLI atoms count as out-of-scope-with-reason under the atom-claim
  invariant (blanket reason in `cli.md`'s header). The FEATURE-MAP therefore
  contains no CLI-seeded feature rows.

## Sweep provenance (2026-07-26)

All entry-point sweeps ran against the three app checkouts with `lib/pkp`
content-identical across them. During the sweep window lib/pkp HEAD moved
from `9db481cf4d` to `ad4606f93e` via a commit + revert pair
(`24cb2d923c` + revert) whose net diff is EMPTY — so file headers cite
`9db481cf4d`, `24cb2d923c` or `ad4606f93e` interchangeably; all three name
identical content for every swept tree (verified: `git diff 9db481cf4d
ad4606f93e --stat` empty; agents additionally `diff -rq`-verified their
trees across the three checkouts). Canonical SHA for the atlas:
`ad4606f93e`.

Each entry-point sweep passed the orchestrator's mechanical completeness
check (every handler/controller/mailable/registry-key/schema/section/tool/
plugin-dir/page-dir/constant/job/task class greps to a row) with zero
misses.
