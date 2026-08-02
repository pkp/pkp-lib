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

**The critical goal (maintainer, restated 2026-07-31):** accurate spec files a
QA/PO reader understands, and per-app e2e tests derived from them with strong
coverage. Every rule in this file exists to serve that goal and is adjusted to
it — never the other way around.

**The current mode lives in `PROGRESS.md`'s banner** — read it before doing
anything. Modes: **REVIEW/PILOT** (one feature per session, then STOP for
maintainer review) and **AUTONOMOUS WAVES** (see below).

## What this work is — state the frame

This campaign documents how PKP's own applications behave **as their screens
present them**, so a product owner or QA person can read it and a developer can
fix what is wrong. The unit of documentation is a screen and a role: what a
signed-in user of that role is offered, what happens when they use it, and where
those two disagree. A role that sees a button that does nothing, a control
missing where that role should have it, a screen that renders for someone it
should not, a message that contradicts what actually happened — those are the
deliverable.

The working conditions are ordinary QA conditions, and every brief should say so
rather than assume it: a **disposable local test install** (seeded fixture
journals/presses/servers, seeded accounts, throwaway scratch contexts, no real
users, no real data, no third-party systems), driven as a signed-in user of a
given role.

### The screen is the instrument

Every probe, claim check and test acts as a signed-in user working the
application's own screens: clicking what is offered, filling what is presented,
and typing a URL straight into the address bar to reach a screen nothing linked
to. That last one is ordinary user behaviour — a bookmark, a stale link, an
address a colleague pasted — and it is how we learn whether a screen guards
itself. Visiting a screen and reporting what it shows is in scope, always.

What this campaign does NOT do: construct a request the application's own
screens would never send. No hand-built API calls, no altered form payloads, no
links or credentials carried over from another session, no client but a browser.
Whether the server would accept something the UI never offers is a real
question, but it is a **separate process's** job — folding it into this one
costs us the readable, product-voice spec we came for.

If a question can only be answered outside that line, it is out of scope here:
write one line on the deferred queue and move on — do not investigate it or
conclude anything about it. Saying in a return or report that a question was
parked is fine; the queue line is its record. If what was actually OBSERVED
suggests a security weakness, it goes to the private security file instead
("What goes where").

**The deferred queue** — `../e2e_ng/server-questions.md` (relative to the app
repo root, outside every repo). One line per question: the screen, the role,
and the question in plain terms. Nothing in this campaign reads it back.

**Say this out loud in briefs and reports.** State the frame once, up front,
then describe the work and its outcomes plainly. There is no restricted
vocabulary: findings are filed in whatever terms describe them best. ONE
routing rule exists (maintainer, 2026-07-31): a finding that could plausibly
be a **security weakness** goes to the maintainer's private security file,
because these repos are public — see "What goes where". Every other finding
has exactly one home, the spec's Findings register.

> **Frame** (copy into probe / claim-check / test briefs): "QA documentation of
> an application's own screens, on a local disposable test install with seeded
> accounts. Sign in as each role and use the screens the way that role would —
> including typing a URL directly to reach one. Record what the screen offers,
> what happens when it is used, and where the two disagree, so the product team
> can fix it. Never construct a request the screens themselves would not send;
> a question that needs that goes on the deferred queue instead. A finding
> that could plausibly be a security weakness is appended to the maintainer's
> private security file (`../e2e_ng/security.md`), never to a spec, test,
> report file or commit — these repos are public. Say THAT you routed
> something there; keep its content out."

Volume discipline is separate and stays: detail lives in `.reports/`, returns
are short and outcome-shaped. That is context budgeting — keeping the
orchestrator lean — not a wording rule.

## What goes where

- **Product findings — bugs, divergences, oddities, open questions** → the
  feature spec's **Findings register** (TEMPLATE). This is the only home. Never
  file them in `app-changes.md`, PROGRESS notes, or side documents.
- **Potential security concerns** → `../e2e_ng/security.md` (relative to the
  app repo root — outside every repo, maintainer-only). Decide by substance:
  a role seeing or doing more than it is entitled to, a guard that does not
  hold, data exposed to the wrong audience — anything you would not publish
  before a fix. The repos are PUBLIC: such a finding's CONTENT never appears
  in a spec, test, `.reports/` file, PROGRESS note, or commit message; the
  claim it would have supported is omitted or kept generic until the fix
  ships, after which the corrected behavior enters the spec like any other
  claim. The FACT of routing is never silent: a return or report says "one
  observation routed to the security file" so the maintainer always knows to
  look — silent claim-dropping is the old construct's failure mode and is not
  repeated. Ordinary UX defects (a dead button, a missing message) are not
  security concerns — they go to the register as usual.
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

## Campaign-artifact maintenance (self-healing)

**Maintainer ruling 2026-08-02**: artifacts the campaign itself created are
LIVING — when a session discovers one is stale or defective, it FIXES it in
that session as routine maintenance instead of parking a debt note. Covered:
the `ojs-playwright-tests` skill and its companions, shared and app-side
POMs/fixtures/helpers, earlier feature suites, the lint gate, and the `_test`
scenario API (a behavior change there still gets its parity entry). The gate
travels with the fix: every suite the fix touches re-runs green before commit
(ONE clean run suffices for a maintenance touch-up; the ×2 rule stays for new
suites), and the fix commits with the session's normal commits and is named in
the session report. Three boundaries stand unchanged:
- **App code stays minimal and gated** — `app-changes.md` row, and only when
  blocking green or a trivially-safe mirror of an existing pattern (the OPS
  int-cast class).
- **Another feature's SPEC is never silently edited** — a claim drift found
  while maintaining routes to that spec's register through the loop's own
  gates, like any finding.
- **The private-file policy is untouched** — maintenance never moves routed
  content.

## What to read, when

- **The floor, every iteration**: this file + `PROGRESS.md` + the target
  feature's row in `FEATURE-MAP.md` (its atom list).
- **When authoring the spec**: `TEMPLATE.md` (structure + style rules),
  `APP-GLOSSARY.md` (cross-app vocabulary), the feature's atoms in `atlas/*.md`.
- **When authoring tests**: `docs/e2e/PRINCIPLES.md` + the `ojs-playwright-tests`
  skill (env facts, seeded users, POMs, scenario endpoints).
- **Background contract (once per session)**: `CHARTER.md`.
- Beyond the floor, read whatever helps — subject to context hygiene: a
  spec-writing agent works from the draft plus the digest (step 3b) by
  default, and pulls a specific probe report only when its judgment needs the
  detail behind one digest block, never the whole `.reports/` tree. The
  orchestrator stays lean to keep detail in subagent contexts where it
  belongs.

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
   archaeology stays in footnotes (and the session's scratch reports).
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

**Orchestration shape**: heavy work is DELEGATED — spec author, probe agents,
the digest agent, test authors, claim checkers are separate subagents; model per
role in Model discipline. The
orchestrator briefs them (each brief points at TEMPLATE / PRINCIPLES — never
paraphrases the rules), judges results, and is the ONLY writer of PROGRESS rows,
atlas `Claimed by:` markers, and `app-changes.md` entries. EVERY subagent brief
carries verbatim: "Do NOT write to PROGRESS.md, atlas files, or
docs/e2e/app-changes.md; return proposed content in your report instead."

EVERY probe, claim-check and test brief ALSO opens with the **Frame** paragraph
from "What this work is" — verbatim, before the task. It states the working
conditions, the scope boundary (browser-only, deferred queue) and the two
routing destinations, so every agent starts with the same contract without the
orchestrator paraphrasing it.

1. **Claim it** — set the feature's PROGRESS row to `in_progress`.
2. **Author the spec** → `docs/product/specs/<feature>.md` per `TEMPLATE.md`,
   covering all three apps from the start. Draw from the feature's atlas atoms
   + the code, including its `atlas/affordances.md` rows — every affordance on
   the feature's screens ends up covered by a rule or scenario, delegated with
   a verifiable pointer, or explicitly waived. Where the code is ambiguous,
   don't guess — put the question on the PROBE LIST the author returns with
   its draft (the author never probes; step 3 executes the list). The list
   includes the cross-app controls from multi-app rule 4.
   **Every probe item is phrased as screen actions and observations** — "as
   role R, on screen S, do X; record what appears". An item that cannot be
   phrased that way is out of scope ("The screen is the instrument"): it goes
   on the deferred queue, and the claim it would have supported either gets a
   marker or leaves the draft. This is the author's job, not the probe's.
3. **Probe** — the probe list is farmed to probe subagents (fresh context,
   tight scope, facts-only returns to `.reports/`). A probe answers "what does
   this role actually see and get on a running install?", driven through the
   screens as a signed-in user ("The screen is the instrument"). Any statement
   about what a UI control *does* — appears /
   enabled / says X / absent, in state Z for role R — is the error class
   code-reading gets wrong; no affordance claim ships without driving it live. Probes are throwaway; retained tests are
   step 6's. Probe reports record the locator used and mark claim-vs-context
   (incidental DOM observations are not promotable to assertions). They are
   written for the digest agent — and for the maintainer, who may audit a
   feature's scratch reports before sign-off; the spec writer works from the
   digest.

   **Step 3b — Digest, the context seam.** ONE digest agent reads every probe
   report for the feature and emits `.reports/<feature>/digest.md`: the
   spec-affecting facts and nothing else. This is the ONLY evidence artifact
   step 4 reads. One block per fact:

   > `### D<n> — <one line, product voice: what a person sees or gets, on which screen, as which role>`
   > `Affects:` Rule 9 | Actors row 2 | scenario 3 | register A5 | new
   > `Status:` confirms | corrects | new | undetermined
   > `Apps:` the apps it holds for (per-app difference stated in the line)
   > `Proposed:` 🐞 | ❓ | ✅ | plain claim · rule text | register entry | footnote | drop
   > `Evidence:` report file + item number — a pointer, never a quotation

   Style is the digest agent's judgment with one aim: each line reads as
   product behaviour, in the spec's own voice, at digest length. Reproduction
   narrative, status codes, request/response detail and quoted report prose
   live in `.reports/`, which the Evidence pointer names — the digest carries
   the fact, the report carries the trail. An `undetermined` entry says only
   that, plus the one observation that would
   settle it. `Proposed:` is the digest's SUGGESTION; step 4 decides. Size is the
   gate: about two pages for an M-tier feature. A digest that will not fit
   means the probes overshot — cut trivia, don't compress prose.
4. **Finalize the spec (writer's judgment).** A fresh agent folds the DIGEST
   into the draft; its brief carries the draft path and `digest.md` — it may
   pull the one probe report behind a digest block when its judgment needs the
   detail. **The digest is raw material, not spec content**: it still
   overshoots — trivia, fixture accidents, neighboring features' territory,
   optimistic severity. The finalizer includes a finding only at the weight its
   user impact earns, in product voice, and may downgrade or drop anything the
   digest proposed; what doesn't clear the bar stays in `.reports/`. Findings
   that belong to another feature go to that spec (or its future register) via
   a link, not here.
   **Fold in slices** — one digest section, or one spec section, per agent for
   an H-tier feature. Small chunks are the standing rule for writing work.
   **If a fold agent stalls on a technical limit** (context, tool error):
   respawn it on a NARROWER slice — the single section, then the single entry
   (max 2 retries); nothing is ever left half-folded to be rediscovered later.
   **If an agent is refused or flagged by safeguards**: that is not a stall —
   PAUSE per Model discipline (record the point reached, stop for the
   maintainer). Never press the same brief again or degrade the item to route
   around a flag.
5. **Lint gate** — the mechanical gate (TEMPLATE "The lint gate": reference
   integrity ONLY — register ↔ marker integrity, link/anchor/footnote
   resolution, campaign identifiers a reader cannot resolve; wording,
   vocabulary and the leak rule are the writer's judgment, never linted —
   maintainer, 2026-07-31) must pass with ZERO findings before tests are
   written.
6. **Write the Playwright tests** — per `docs/e2e/PRINCIPLES.md` + the skill:
   one suite per app, derived from the spec (multi-app rules 2–3) — one test
   per canonical scenario in each app that runs it. Scenario-seed state, reuse/extend POMs, scope Mailpit
   **by a unique throwaway recipient address** that names the app and the test
   (`u53top-omp@mail.test`) — this install has NO Mailpit tags, so "scope by the
   per-app tag" is not executable (PRINCIPLES principle 8); pair every silence
   claim with a positive control. `--output` to a private dir, `--reporter=list`.
7. **Run them green twice** per app against the live fleets. If a test
   contradicts the spec, the **SPEC is wrong**: fix it (the finding goes to
   the register). Never edit a test to pass a claim the app disproves. An app
   defect that blocks green tests (races, flake sources) → work around and
   record in `app-changes.md` ("What goes where").
8. **Claim check** — chunked subagents test the spec's own claims against
   the running app, per app where behavior diverges: take each permission and
   state rule and check the cases most likely to show it wrong, including
   whether the surface is still reachable at all. The target is OUR OWN TEXT —
   the goal is to catch a rule that is inaccurate before a QA reader trusts it.
   Driven through the screens like every probe, and bounded by the same scope
   line. The merge agent returns a change list **in the step-3b digest
   schema**; a fold agent folds ACCEPTED findings into the spec under step 4's
   rules, including its slice-and-retry ladder. Unresolvable items become ❓
   register entries with a stated lean.
9. **Readability verify** — a SEPARATE Fable subagent in strict persona: a
   QA/PO person who has never seen a campaign document, has NO code access,
   and reads ONLY the body (above the footnote tail). They must (a) restate
   every rule in their own words and (b) walk each scenario as a manual test.
   Three things are stumbles, and the brief says so:
   - a verb or noun they cannot map to something on screen;
   - **any token they cannot resolve from the page itself** — a code, an ID, an
     abbreviation, a cross-reference that names no feature. The persona has
     read no other spec and must not "recognise" campaign notation. This is
     the class a reader inside the campaign normalises, so it is called out
     explicitly (U26 shipped 59 unresolvable row codes past a passing
     readability pass);
   - a step they could execute two different ways, or an outcome they could
     not judge pass/fail from what is written.

   Rewrite the stumbles, re-run the lint, then run the persona ONCE MORE over
   the rewritten passages only: a rewrite is not verified by the person who
   wrote it. The gate is zero blockers; frictions are the writer's call.
10. **Update PROGRESS** — status, #tests per app, a ONE-line note. Findings
    live in the spec, never in PROGRESS.
11. **Commit** — `lib/pkp` and root **separately**, NEVER bump submodule
    pointers in ANY app repo — ojs-main, omp-main, or ops-main (`git restore
    --staged lib/pkp lib/ui-library plugins` before any root commit; stage
    files explicitly, never `git add -A`/`git add .` at an app root). Specs,
    campaign docs (`lib/pkp/docs/` — but NEVER the feature's `.reports/`
    scratch, see ".reports/ retention") and shared test/POM/Processor changes
    commit inside `lib/pkp`; app-only tests commit in each app's root. Multi-repo
    flow for shared changes: commit in `ojs-main/lib/pkp` → push
    `e2e_ng_2` to the `jardakotesovec` fork → in `omp-main`/`ops-main`
    `lib/pkp`: fetch and check out the SAME branch. That checkout IS the sync
    — no re-pin commit follows (maintainer ruling 2026-07-28: pointer commits
    make later conflict resolution painful; every repo rides the campaign
    branch — `e2e_ng_2` since 2026-07-31 — and an
    `M lib/pkp` in app status is normal and stays uncommitted). This is the
    single home of the commit rule — PRINCIPLES points here.
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

- **FABLE RUNS EVERYTHING** (maintainer, 2026-07-31): the main orchestrator
  session and every subagent — spec author, probe agents, the digest agent,
  finalizer and fold agents, test authors and test-fix agents, claim-check
  chunks and their merge agent, the readability verifier. No per-role model
  split, no per-agent model pins (subagents inherit the session model), and no
  fallback to another model for any role. (The 2026-07-25/28 arrangement —
  Fable writes, Opus investigates, Opus orchestrator — is retired; history in
  git and the PROGRESS banner.)
- **Pause on flag.** If any agent is refused, flagged by safeguards, or
  silently downgraded to a non-Fable model mid-run: discard that attempt's
  output, do NOT re-press the same brief, do NOT respawn onto another model,
  and do NOT degrade the item to route around the flag. (A silent subagent
  downgrade is detected from its transcript — grep the agent's JSONL for
  `"model":` values; every assistant line must be claude-fable. Spot-check
  writing agents at completion.) Record the point
  reached in the feature's PROGRESS note, log the event in the Model-fallback
  log, and STOP the session for maintainer review. An ordinary technical
  stall (context overflow, tool error, environment breakage) is not a flag —
  step 4's narrower-slice retry applies there.
- **Small chunks stay the rule** for writing work, and the digest (step 3b)
  stays the default evidence input for spec-writing agents. Both are context
  hygiene — keeping each agent's context lean and on-topic — not sanitization:
  nothing is withheld from the writer, and the trail behind each digest block
  stays in `.reports/` where the writer can go read it for the feature's
  duration.
- **Subagent returns are pointers, not findings.** A probe or claim-check
  agent returns where its report is, how many items it covered, and whether
  anything blocked it — not what it found. The digest agent reads the
  reports; the orchestrator has no reason to carry their contents.
- **Model check at session start**: verify the model (`/model`) is **Fable**
  at every fresh/handoff session — a handoff session starts on the saved
  default, not the predecessor's.
- **Model-fallback log** (PROGRESS): from 2026-07-31 it records ANOMALIES
  ONLY — refusals, safeguard flags, downgrades, pauses (date · feature ·
  role · what happened). Routine agents are not logged; enumerating them is
  PROGRESS bloat.
- **Brief hygiene**: spawn briefs are the Frame paragraph ("What this work is")
  plus pointers (feature, spec path, report path, "follow RUNBOOK step N") —
  point at the rule files rather than paraphrasing them. A spec-writing brief
  names the draft and the digest; the report behind a digest block is
  available on demand, not pre-loaded. Probe and claim-check returns are
  pointers (report path, item count, blockers) — the detail is in the file,
  and the digest agent is who reads it.
- **The orchestrator NEVER probes, verifies or edits a spec inline** — inline
  completion is how the controlling agent gets lost (2026-07-10 rehearsal),
  and spec edits belong to the writing agents it briefs. If context runs low
  mid-feature: finish the current gate, commit what's commit-worthy, END; a
  fresh session resumes.
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
- **Git**: push only to `jardakotesovec` remotes, campaign branch `e2e_ng_2`
  (previously `e2e_ng`);
  verify the remote URL before every push; a bad pushed commit gets a
  follow-up commit, never a force-push.
- **`.reports/` retention (maintainer ruling 2026-07-31 — supersedes the
  keep-and-commit rule)**: per-feature reports — probe reports, `digest.md`,
  claim-check chunks and merge — are SESSION-LOCAL SCRATCH. They are required
  during the loop (the digest is the context seam; resuming after compaction
  needs files on disk; the maintainer may audit them before sign-off in
  review mode) but are never committed: the directory is gitignored, and a
  feature's scratch may be deleted after its review sign-off. The SPEC must
  be self-sufficient — probe dates and verbatim on-screen strings live in its
  footnotes (TEMPLATE rule 4), never citations of report files. A shipped
  claim disputed later is settled by a fresh probe on the current build, not
  by re-reading a stale snapshot. The security rule is unchanged: a potential
  security concern never appears even in scratch reports — it goes to the
  private file ("What goes where"). The two pre-rule evidence sets
  (`.reports/phase0-feature-map/`, `.reports/step1-harness/`) stay committed
  as historical record; the rule covers per-feature reports.

## Definition of done

- **Per feature**: spec `verified` + lint-clean, all three apps covered per
  the multi-app rules; every affordance atom covered / delegated / waived;
  each app's suite green twice; PROGRESS row updated (one-line
  note); committed; **maintainer sign-off in review mode**.
- **Campaign**: the campaign-level bar lives in `CHARTER.md` (single home).
