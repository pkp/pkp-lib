# Runbook — spec + Playwright build loop (OJS · OMP · OPS)

**This file + `PROGRESS.md` are the source of truth for the build.** Any session —
fresh, restarted, `/clear`ed, or resumed after compaction — becomes correct by reading
these two files. Do not rely on conversation memory.

**The campaign docs live centrally in `lib/pkp/docs/`** (`docs/product/` +
`docs/e2e/`), one copy shared by all three apps through the submodule; tests
live in each app's own repo. Doc paths in these files are relative to the
`lib/pkp` repo root.

**The spec is the source of truth for the product.** Everything the campaign knows
about a feature — its behavior in all three apps, its divergences, its bugs, its open
product questions — lives in that feature's spec, written to be read by a product
owner or QA person. Every other artifact (tests, coverage views, bug lists) is
derived from specs and must never contradict them. `docs/e2e/app-changes.md` is NOT
a bug ledger — see "What goes where" below.

**The current mode lives in `PROGRESS.md`'s banner** — read it before doing
anything. Modes: **REVIEW/PILOT** (one feature per session, then STOP for
maintainer review) and **AUTONOMOUS WAVES** (see below).

## What this work is — state the frame

This campaign documents how PKP's own applications behave so a product owner or
QA person can read it and a developer can fix what is wrong. A large part of that
is **permission documentation**: for each screen, recording which roles are
offered which actions, and whether the server agrees with the screen. Finding a
place where they disagree is the deliverable — that is a documentation gap and a
product defect, reported so it gets fixed.

The working conditions are ordinary QA conditions, and every brief should say so
rather than assume it: a **disposable local test install** (seeded fixture
journals/presses/servers, seeded accounts, throwaway scratch contexts, no real
users, no real data, no third-party systems), driven through **the application's
own screens and API as a signed-in user of a given role would**. Nothing here
involves circumventing a protection; when the server refuses, the refusal is the
finding we wanted, and when it doesn't refuse, that is the gap we are looking for.

**Say this out loud in briefs and reports.** State the frame once, up front,
then describe the work and its outcomes plainly. Briefs and returns are NOT
sanitized: there is no restricted vocabulary between the orchestrator and its
subagents, in either direction. The one hard rule on this channel is routing,
not wording — anything `../e2e_ng/permissions.md` covers follows that file and
never reaches the orchestrator at all ("Private finding routing"); everything
else is communicated freely, in whatever terms describe it best.

> **Frame** (copy into probe/verify briefs): "QA verification of documented
> behavior on a local disposable test install with seeded accounts. For each rule,
> confirm what the role's screen offers and what the server does with the same
> request, and record where they differ so the gap can be fixed."

Volume discipline is separate and stays: detail lives in `.reports/`, returns
are short and outcome-shaped. That is context budgeting — keeping the
orchestrator lean — not a wording rule.

## Private finding routing

EVERY Opus-pinned subagent — the full Model discipline roster: probe agents
(including top-up probes), test authors, test-fix agents, verification chunks,
the verification merge agent — reads `../e2e_ng/permissions.md` (relative to
the app repo root) and follows it. Every Opus-pinned brief carries this line
verbatim, right after the Frame sentence:

> **Routing**: read `../e2e_ng/permissions.md` (relative to the app repo root)
> before writing your report, and follow its handling rules for anything it
> covers. Everything else you find is reported normally.

Nobody else opens that file, for any reason.

A covered finding is written ONLY where `permissions.md` directs — nowhere
else, in any form. That means it never comes back to the orchestrator (its
content, class, severity, and existence beyond the sentinel line below all
stay out of the return) AND it never lands in any campaign artifact: no
`.reports/` file, no test, no spec text, no doc — gitignored or not. This is
the ONLY restriction on what subagents report; everything the file does not
cover is reported and filed plainly.

If a return says `item N: dropped per security routing`, remove that item's
claim from the spec and put nothing in its place — no register entry, no test,
no PROGRESS or report note, no follow-up.

## What goes where

- **Product findings — bugs, divergences, oddities, open questions** → the
  feature spec's **Findings register** (TEMPLATE). This is the only home. Never
  file them in `app-changes.md`, PROGRESS notes, or side documents.
  Exception: see "Private finding routing".
- **Build blockers** → `docs/e2e/app-changes.md`: an app defect encountered
  while getting tests to run green — race conditions, nondeterministic UI,
  harness-hostile behavior — that had to be worked around or fixed in app code.
  Record what blocked, the workaround/change, and where. That file also keeps
  its record of actual app-code changes made by the campaign. Nothing else.
- **Cross-feature mechanisms** → described fully in ONE owning spec; other
  specs link (`[→ label](spec.md#anchor)`, TEMPLATE rule 6).
- **Process learnings** → this file or TEMPLATE (via maintainer review), never
  inside specs.
- **Aggregate views** ("all bugs", coverage) → computed from specs on demand;
  never hand-maintained.

## What to read, when

- **The floor, every iteration**: this file + `PROGRESS.md` + the target
  feature's row in `FEATURE-MAP.md` (its atom list).
- **When authoring the spec**: `TEMPLATE.md` (structure + style rules),
  `APP-GLOSSARY.md` (cross-app vocabulary), the feature's atoms in `atlas/*.md`.
- **When authoring tests**: `docs/e2e/PRINCIPLES.md` + the `ojs-playwright-tests`
  skill (env facts, seeded users, POMs, scenario endpoints).
- **Background contract (once per session)**: `CHARTER.md`.
- Beyond the floor, read whatever helps. Fable agents keep ONE ordering rule:
  draft prose BEFORE accumulating probe narrative (Model discipline). The
  orchestrator stays lean to keep detail in subagent contexts where it belongs.

## Budget & ceilings (HARD)

- **Per app: ≤ 700 tests, ≤ 25 min** full-suite runtime on a fresh DB. Each
  app's fleet runs its own suite, so wall-time does not stack across apps.
- Tiers live in each PROGRESS row (`Budget: tier·target`): H 10–13, M 6–8,
  L 3–4 — the tier bounds the spec's COMMON-scenario count (±1–2 by author
  judgment). Each app's suite implements the common scenarios plus that app's
  specific ones (multi-app rules 2–3), so its per-feature count is the tier
  plus its own specifics.

## The multi-app rules

Every feature is specified and tested across **OJS, OMP and OPS** (fleets on
ports 8000/8100/8200; same scenario-seeding endpoints; Postgres test DBs).

1. **One spec, all three apps.** No per-app spec copies. The body describes the
   shared behavior; an UNMARKED claim asserts "verified identical in every app
   that has the surface" — absence of a marker is itself a claim. The evidence
   bar for that assertion is NOT exhaustive per-claim probing (no budget allows
   it): shared-code claims rest on the subclass-chain check (rule 8 — an empty
   chain on the load-bearing path is positive evidence); permission,
   exclusivity and affordance claims — the classes code-reading gets wrong —
   additionally need a live cross-app probe (rule 4). A claim covered by
   neither gets probed or gets a marker. Divergences
   carry an inline app marker linking to the Findings register. A feature an
   app doesn't have at all: title badge (e.g. `{OJS OMP}`) + one absence
   paragraph; absences are written as install facts ("not installed by
   default"), never impossibilities, and probed as such.
2. **Detailed scenarios live in the spec, common first.** Canonical scenarios
   are the QA-executable description of the feature and the units tests map
   onto. Author them in two blocks: FIRST the scenarios common to every app
   that has the feature, THEN the app-specific ones. Per-app divergence
   inside a common scenario is marked inline; a scenario an app can't run is
   flagged with its analogue or absence. Each common scenario is implemented
   in EVERY app's suite, in that app's own context — its roles, seeded data
   and vocabulary (glossary substitution), not a literal OJS transplant.
3. **Tests are written for each app, derived from the spec** (maintainer
   ruling, 2026-07-26). The spec's canonical scenarios — with their register
   divergences — are the source; each app's suite covers the common scenarios
   in that app's context (rule 2) plus its own app-specific scenarios. Duplication between the app suites is acceptable:
   the SPEC is the artifact being maintained, and tests are regenerated from
   it rather than kept dry through sharing machinery. Standing constraints:
   never assert a 🐞 finding as contract (the bug's register entry is its
   record; a claim parked on an open ❓ is not a coverage gap); an absent
   feature costs one absence test with a positive control per assertion; each
   app suite's file header declares what it deliberately does not cover.
4. **Probing is cross-app by construction.** Every exclusivity claim ("only X
   can", "never shows") gets a read-only control probe in the other apps. A
   probe item that spans apps is owned by ONE agent driving all fleets (or an
   explicit merge step) — never split by app mid-item.
5. **Base corrections are expected output.** OMP/OPS probing routinely
   disproves what the spec says about OJS itself. A finding that touches
   shared base text is re-checked on OJS before the spec is finalized; such
   corrections are normal yield of cross-app work, not scope creep.
6. **Divergence provenance guides the verdict.** When judging bug-vs-intended,
   weigh the age signal: behavior untouched since the app's early years reads
   as intent; behavior broken inside a modernization window reads as decay.
   The verdict lands in the register badge + one rationale sentence — the
   archaeology stays in `.reports/` and footnotes.
7. **Graduation: divergence vs own feature.** An app difference that
   PARAMETERIZES existing machinery (another stage, another decision in a
   roster, a reduced role set) stays a register divergence in the shared spec,
   however many entries it takes — pure reductions never graduate. A
   difference earns its own spec (and FEATURE-MAP row) the moment it needs
   rules of its own: UI surfaces whose atoms no existing spec claims, or
   replacement scenarios/rules rather than modifications. (OMP/OPS-unique
   surfaces remain out of scope per CHARTER until the maintainer extends it.)
   **Maintainer test (2026-07-27)**: similar-looking features across apps are
   ONE shared feature only when one is essentially the other *rebranded* —
   sharing most of the code (rule-8 chain check) and the business logic.
   Otherwise they are separate app-specific features, even when the user
   intent rhymes (the OMP catalog vs the OJS archive is the canonical case:
   own handlers, own data model → own features). Forked-copy code with
   provably identical business logic (the OJS↔OPS pattern) still counts as
   one feature, but every shared claim there needs probe evidence, since the
   subclass-chain check cannot vouch for copies.
8. **Where divergences live in code: inheritance first.** The apps' primary
   mechanism is CLASS INHERITANCE — each app subclasses shared lib/pkp
   classes (handlers, managers, forms, mail/notification repositories) to add
   or override custom OMP/OPS functionality. So the spec author's and probe
   battery's first move for any load-bearing shared class is to check each
   app's subclass chain: an EMPTY subclass is positive evidence of fully
   shared behavior; an app-side override is where intended divergence lives;
   a MISSING override — or an extension point a refactor quietly turned into
   a constant — is the classic silent divergence. Explicit
   `isOJS()/isOMP()/isOPS()` branches, registry/seed file differences
   (`userGroups.xml`, `emailTemplates.xml`) and config-merge survivors are
   the secondary seams, greppable after the hierarchy is understood.

## The per-feature loop

**Orchestration shape**: heavy work is DELEGATED — spec author, probe agents, test
authors, verifiers are separate subagents; model per role in Model discipline. The
orchestrator briefs them (each brief points at TEMPLATE / PRINCIPLES — never
paraphrases the rules), judges results, and is the ONLY writer of PROGRESS rows,
atlas `Claimed by:` markers, and `app-changes.md` entries. EVERY subagent brief
carries verbatim: "Do NOT write to PROGRESS.md, atlas files, or
docs/e2e/app-changes.md; return proposed content in your report instead."

EVERY probe, verification and test brief ALSO opens with the **Frame** sentence
from "What this work is" — one line, before the task: it costs nothing, it is
what a human reviewer would want stated, and an agent asked to check permission
behavior with no stated purpose is the case that has historically gone wrong.
EVERY Opus-pinned brief additionally carries the **Routing** line from
"Private finding routing", verbatim, immediately after the Frame.

1. **Claim it** — set the feature's PROGRESS row to `in_progress`.
2. **Author the spec** → `docs/product/specs/<feature>.md` per `TEMPLATE.md`,
   covering all three apps from the start. Draw from the feature's atlas atoms
   + the code, including its `atlas/affordances.md` rows — every affordance on
   the feature's screens ends up covered by a rule or scenario, delegated with
   a verifiable pointer, or explicitly waived. Where the code is ambiguous,
   don't guess — put the question on the PROBE LIST the author returns with
   its draft (the author never probes; step 3 executes the list). The list
   includes the cross-app controls from multi-app rule 4.
3. **Probe** — the probe list is farmed to Opus probe subagents (fresh context,
   tight scope, facts-only returns to `.reports/`). A probe answers "what does
   this role actually see and get on a running install?" Any statement about
   what a UI control *does* — appears /
   enabled / says X / absent, in state Z for role R — is the error class
   code-reading gets wrong; no affordance claim ships without driving it live. Probes are throwaway; retained tests are
   step 6's. Probe reports record the locator used and mark claim-vs-context
   (incidental DOM observations are not promotable to assertions).
4. **Finalize the spec (Fable judgment).** A fresh Fable agent folds the probe
   facts into the draft. **Probe output is raw material, not spec content**:
   Opus reports overshoot — they surface trivia, fixture accidents, neighboring
   features' territory, and speculative severity. The finalizer includes a
   finding only at the weight its user impact earns, in product voice; what
   doesn't clear the bar stays in `.reports/`. Findings that belong to another
   feature go to that spec (or its future register) via a link, not here.
5. **Lint gate** — the mechanical gate (TEMPLATE "The lint gate": leak rule,
   glossary vocabulary, register integrity, link resolution — mechanical
   checks only, never wording)
   must pass with ZERO findings before tests are written. The first feature
   session REBUILDS the gate against the new corpus format — small, checks
   over ceremony; every later session just runs it.
6. **Write the Playwright tests** — per `docs/e2e/PRINCIPLES.md` + the skill:
   one suite per app, derived from the spec (multi-app rules 2–3) — one test
   per canonical scenario in each app that runs it. Scenario-seed state, reuse/extend POMs, scope Mailpit
   by recipient+tag (per-app tags when fleets run concurrently), `--output` to
   a private dir, `--reporter=list`.
7. **Run them green twice** per app against the live fleets. If a test
   contradicts the spec, the **SPEC is wrong**: fix it (the finding goes to
   the register). Never edit a test to pass a claim the app disproves. Test
   agents carry the Routing line like every Opus-pinned agent; a sentinel
   item in a test report is handled per "Private finding routing" (the test
   is dropped along with the claim). An app
   defect that blocks green tests (races, flake sources) → work around and
   record in `app-changes.md` ("What goes where").
8. **Adversarial verify** — chunked Opus subagents test the spec's own claims
   against the running app, per app where behavior diverges: take each
   permission and state rule and check the cases most likely to show it wrong,
   including whether the surface is still reachable at all. "Adversarial" here
   means hard on OUR OWN TEXT — the goal is to catch a rule that is inaccurate
   before a QA reader trusts it, not to get past anything. The Opus merge
   agent returns a change list; a Fable agent
   folds ACCEPTED findings into the spec with the same judgment filter as
   step 4. Unresolvable items become ❓ register entries with a stated lean.
9. **Readability verify** — a SEPARATE Fable subagent in strict persona: a
   QA/PO person with NO code access reads ONLY the body (above the footnote
   tail) and must (a) restate every rule in their own words and (b) walk each
   scenario as a manual test, flagging any verb or noun they cannot map to
   something on screen. Rewrite stumbles; re-run the lint.
10. **Update PROGRESS** — status, #tests per app, a ONE-line note. Findings
    live in the spec, never in PROGRESS.
11. **Commit** — `lib/pkp` and root **separately**, NEVER bump submodule
    pointers (`git restore --staged lib/pkp lib/ui-library plugins` before the
    root commit). Specs, campaign docs (`lib/pkp/docs/`, including the
    feature's `.reports/` files — see ".reports/ retention") and shared
    test/POM/Processor changes commit inside `lib/pkp`; app-only tests commit
    in each app's root. Multi-repo
    flow for shared changes: commit in `ojs-main/lib/pkp` → push
    `e2e_ng` to the `jardakotesovec` fork → in `omp-main`/`ops-main`
    `lib/pkp`: fetch and check out the SAME branch, then commit the re-pin in
    that app's repo (the omp/ops submodule re-pins ARE the work there; only
    ojs-main root never bumps pointers). This is the single home of the commit
    rule — PRINCIPLES points here.
12. **Report** — what was built, register highlights (🐞 and ❓ counts, the
    findings a reviewer should look at first), anything low-confidence (flag
    in the PROGRESS note — it drives sampling review). Open questions stay
    recorded, not resolved — the team settles them on spec review. Then STOP
    (one feature per fresh session; flag if the wave counter reached 7).

## Autonomous waves (post-review mode)

Active ONLY when the PROGRESS banner says so. One full loop per session; every
feature starts in a clean session the maintainer launches. Context is
DISPOSABLE — all state lives in PROGRESS + files; mid-feature compaction is
routine (resume below).

- **Selection**: first `pending` row in PROGRESS table order; respect
  maintainer edits.
- **Wave = 7 features**, then STOP and request a sampling review (maintainer
  spot-reads 1–2 specs + everything flagged). Nits → fix in place; systemic →
  HALT, encode the fix in TEMPLATE/RUNBOOK first, sweep the wave, resume.
- **Park-and-continue**: 3 failed attempts → row `parked` with reason, move on
  (autonomous mode only; in review mode, stop and report instead).

## Rebuilding a feature from scratch

When a feature is rebuilt, the replaced artifacts are DELETED, not archived, and
the rebuild is never checked against them. The author works blind (code + atlas
only) so the new spec's quality measures the process, not the copy. A
completeness check against the old version reimports its judgment — including
its mistakes — and destroys the only evidence the rebuild was meant to produce.
The legitimate check is self-contained: does every claim in the new spec rest on
evidence THIS build produced (probe report or defensible code derivation)? Any
claim that does not gets a top-up probe or leaves the spec. If the old version
is ever genuinely needed, git history has it.

## Resuming a feature mid-flight (fresh/empty session)

If PROGRESS shows `in_progress` and the working tree holds uncommitted work,
RESUME — the gates are idempotent: re-run lint (step 5), the tests twice
(step 7), and judge from the spec's own footnotes whether live probing ran
(probe dates in `<sup>` notes). What a prior session's subagents reported is
GONE — only files count. If a stage's completion is undecidable from files,
re-run it.

## Model discipline

- **FABLE WRITES AND JUDGES; OPUS INVESTIGATES** (maintainer, 2026-07-25/26).
  - **Fable-pinned** (`model: fable`): the spec author, finalizer,
    readability verifier, spec/doc auditors and reviewers, and any agent that
    writes or edits specs or campaign docs. The MAIN session is always Fable.
  - **Opus-pinned** (`model: opus`): probe agents, test authors and test-fix
    agents, adversarial-verification chunks and their merge agent. Opus is
    better tuned against false safeguard flags for investigative work — and
    ONLY does tests + probes; it never writes or edits spec content. Every
    Opus-pinned brief carries the Routing line (Private finding routing).
  - **The judgment seam**: Opus findings reach Fable as plain summaries (full
    detail in `.reports/`, readable by whoever needs it); Fable decides what
    enters the spec and at what weight (loop step 4). Opus tends to
    over-weight what it finds; the filter is Fable's job, not Opus's. The seam
    is editorial judgment, not sanitization — the only content barred from the
    return channel is what "Private finding routing" covers.
- **Fable flip policy**: a Fable-pinned agent that downgrades mid-run finishes
  and is logged, but its output is DISCARDED and the agent respawned (max 2,
  then park). Keep writing chunks small; prose is drafted BEFORE probe context
  accumulates. (History and the dormant split-authoring fallback: git log,
  RUNBOOK @ 88b9e02d8d.)
- **Main-session protection**: `switchModelsOnFlag: false` (pauses instead of
  switching; the old fable-guard hook was removed 2026-07-26). If the MAIN
  session flips anyway: END the session; a fresh one resumes from files.
  **At every fresh/handoff session start, verify the model (`/model`) before
  writing any doc** — a handoff session starts on the saved default, not the
  predecessor's model.
- **Model-mix log**: append a row to PROGRESS's "Model-fallback log" for every
  FABLE-PINNED agent (date · feature · role · label · models seen in its
  transcript; `-discarded` for discarded attempts) and for any anomaly on an
  Opus-pinned agent. Routine Opus agents are NOT logged — the log exists to
  catch silent downgrades where they matter, not to enumerate subagents
  (PROGRESS bloat is the failure mode). Flips in authoring rows trigger the
  discard rule; mention flips in the feature report.
- **Brief hygiene**: spawn briefs are the Frame sentence ("What this work is")
  plus pointers (feature, spec path, report path, "follow RUNBOOK step N") —
  point at the rule files rather than paraphrasing them. Returns are short
  plain-language summaries with detail in files — that is context budget, not
  a wording restriction; say what was found in whatever terms fit. If the
  main session is flag-killed anyway: END it; never recompose the same turn.
- **The orchestrator NEVER probes or verifies inline** — inline completion is
  how the controlling agent gets lost (2026-07-10 rehearsal). If context runs
  low mid-feature: finish the current gate, commit what's commit-worthy, END;
  a fresh session resumes.
- **Liveness**: the completion notification is the only reliable subagent
  signal. Never judge by transcript size; check ground truth (target file
  changed?) or wait.

## Ops & environment safeguards

- **Live-probe etiquette** (CHARTER invariant): scratch contexts via the
  scenario endpoints for anything mutating; `publicknowledge` and seeded users
  are read-only; never `clearAll()` Mailpit; test key `X-Test-Key:
  playwright-test-key`; fleets on ports 8000 (OJS) / 8100 (OMP) / 8200 (OPS)
  — env facts in the `ojs-playwright-tests` skill (ojs-main `.claude/skills/`).
- **DB hygiene**: reset (`npm run test:e2e:reset`) before any full-suite
  timing run and every ~8–10 features. Never delete `config.test.inc.php`
  alone. Test DBs are **PostgreSQL** — Postgres-specific defects reproduce
  in-env.
- **After a killed/aborted test gate**: kill orphan chromium processes before
  re-running; restart PHP servers with an ABSOLUTE `-t` docroot. If the
  parallel result line shows a passed count, re-run only serial; else both
  (`--project=ojs`, then `--project=ojs-serial --no-deps`; serial projects are
  named `<app>-serial`).
- **Plugin-submodule alignment**: on a plugin "contains N abstract methods"
  fatal, align that submodule to the SHA root records (`git ls-tree HEAD
  plugins/<path>`).
- **Env invariants**: egress firewalled; `[schedule] task_runner=Off`;
  DTD/XSD mirrors via `XML_CATALOG_FILES`; Mailpit read-only scoped;
  globally-scanning ops in serial specs.
- **Git**: push only to `jardakotesovec` remotes, branch `e2e_ng`;
  verify the remote URL before every push; a bad pushed commit gets a
  follow-up commit, never a force-push.
- **`.reports/` retention**: per-feature reports are KEPT and committed with
  the feature (step 11) — they are the evidence trail behind the spec's claims
  and stay useful for later re-verification and archaeology. Never delete a
  feature's reports; the only content barred from them is what "Private
  finding routing" covers (that never lands in a report in the first place).

## Definition of done

- **Per feature**: spec `verified` + lint-clean, all three apps covered per
  the multi-app rules; every affordance atom covered / delegated / waived;
  each app's suite green twice; PROGRESS row updated (one-line
  note); committed; **maintainer sign-off in review mode**.
- **Campaign**: the campaign-level bar lives in `CHARTER.md` (single home).
