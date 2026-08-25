# Runbook — spec + Playwright build loop (OJS · OMP · OPS)

**This file + `lib/pkp/docs/e2e/tracking/PROGRESS.md` are the source of truth for
the build.** Any session — fresh, restarted, cleared, or resumed after
compaction — becomes correct by reading these two files. Never rely on
conversation memory.

Campaign docs live centrally in the lib/pkp submodule, one copy shared by all
three apps; tests live in each app's own repo. **Doc paths in every campaign
file are written as seen from an app repo root** (`lib/pkp/docs/...`);
`../e2e_ng/` is the maintainer's private directory outside every repo.

**The spec is the source of truth for the product.** Everything the campaign
knows about a feature — its behavior in all three apps, its divergences, its
bugs, its open questions — lives in that feature's spec, written for a product
owner or QA person. Every other artifact (tests, coverage views, bug lists) is
derived from specs and must never contradict them.

**The critical goal** (maintainer): accurate spec files a QA/PO reader
understands, and per-app e2e tests derived from them with strong coverage.
Every rule here serves that goal and is adjusted to it — never the other way
around.

**The current mode lives in `PROGRESS.md`'s banner** — read it before doing
anything. Modes: **REVIEW/PILOT** (one feature per session, then STOP for
maintainer review) and **AUTONOMOUS WAVES** (below).

## Mission, scope & invariants

**Mission.** Document every OJS feature at the business-logic level — actors,
fields, rules, state, permissions, side effects — precisely enough that the
feature could be reimplemented from the spec alone, in language a product
owner or QA person reads without a developer. Audience: QA, developers, AI
agents, and the test suite built from each spec's canonical scenarios.

**Scope.** All three apps, OJS-anchored: any feature reachable in OJS — one
spec covers its behavior in OJS, OMP and OPS, and tests are written for each
app. Maintainer extension (2026-07-27): the OMP catalog's reader and
management surfaces are in scope as OMP-specific features. Out of scope,
dropped not parked: the catalog's object model and surfaces OJS never
exposes (chapter/publication-format authoring, ONIX, marketing/direct sales,
OMP/OPS-only Vue managers unwired in `WorkflowPageOJS`). Extending further
is a maintainer decision. Specs are Markdown, reviewed raw and in diffs;
inline HTML only where structure needs it.

**Method** — enumerate mechanically, then document, then map coverage ("did
we miss a feature?" must be a grep): Phase 0, the surface atlas
(`lib/pkp/docs/e2e/tracking/atlas/` — complete 2026-07-28: 2,163 atoms, assigned
in `FEATURE-MAP.md`, parked in `UNASSIGNED.md`); Phase 1, feature specs
(current — this file's loop); Phase 2, the coverage crosswalk (every spec
scenario mapped against the suite).

**Invariants** (every iteration holds these):

- **Atom claim**: every atlas atom ends up claimed by exactly one spec, OR
  parked in `UNASSIGNED.md`, OR marked out-of-scope in its sweep file with a
  reason. The unclaimed count is the campaign's completeness metric. Never
  force-fit — a wrong grouping is worse than a deferred one; group by user
  intent ("what would a journal manager call this?"), never code-module
  boundaries.
- **As-built AND intent**: specs document what the code actually does.
  Behaviour that is inconsistent, loses data, contradicts an affordance, or
  would surprise a product owner gets a ⚠ + register entry with the author's
  bug-vs-intended call (non-blocking; settled on review). A merely-strict
  rule is usually intended: write it plain plus a ❓ with a stated lean. A
  spec that silently transcribes bugs as requirements is poison for QA;
  there is no separate bug ledger — all-bugs views are computed from the
  registers.
- **Verified, not just written**: every spec passes a claim check (step 8)
  and a readability pass (step 9); ambiguous rules are probed live, never
  guessed from code.
- **Liveness before documentation**: code existing is not evidence the
  feature exists — the apps carry unreachable surfaces. Establish
  reachability in the current UI first; record dead candidates in
  `UNASSIGNED.md`. Where a legacy and a Vue path are both live for one job,
  document both and say which is primary.
- **The screen is the instrument** — the campaign's frame; operative rules
  in the next section.
- **Business language, one statement per fact** — style rules, anchor
  format, and the lint gate live in `TEMPLATE.md` (single home).

**Campaign definition of done**: unclaimed atom count = 0; every PROGRESS
row `done` or `parked`; each app's full suite within Budget & ceilings;
parked list + register highlights reported to the maintainer. "Recreatable
from the spec" sets the altitude; those lines are the checkable bar.

## What this work is — state the frame

This campaign documents how PKP's applications behave **as their screens
present them**. The unit of documentation is a screen and a role: what a
signed-in user of that role is offered, what happens when they use it, and
where those two disagree — a button that does nothing, a control missing where
a role should have it, a screen that renders for someone it should not, a
message that contradicts what happened. Those are the deliverable.

The working conditions are ordinary QA conditions, and every brief says so: a
disposable local test install (seeded fixtures, seeded accounts, scratch
contexts, no real users or data), driven as a signed-in user of a given role.

### The screen is the instrument

Every probe, claim check and test acts as a signed-in user working the
application's own screens — clicking what is offered, filling what is
presented, and typing a URL straight into the address bar (ordinary user
behaviour: a bookmark, a stale link). Visiting a screen and reporting what it
shows is always in scope.

What this campaign does NOT do as its working method: explore features by
constructing requests the application's own screens would never send. No
hand-built API calls as exploration, no credentials carried across
sessions, no client but a browser. But the API is not invisible — the
browser's own traffic is part of what the screen shows. An API problem
observed while driving a screen (a wrong status code, an error payload
leaking through, a response carrying data the screen never asked for, a
save that silently drops a field) is a real finding and is surfaced, not
parked:

- **No security dimension** → the feature spec's **Findings register**,
  like any other finding (🐞 or ❓ with its evidence footnote).
- **Possibly security-shaped** (a guard that may not hold, data a role may
  not be entitled to) → the security routing in "What goes where":
  verified first, and only confirmed problems stay in the private file.

There is no deferred-questions queue (dropped, maintainer 2026-08-25): a
suspicion nobody verifies is not filed anywhere. Either it earns one
targeted verification this session, or the claim it touches gets a ❓
register entry with a stated lean (kept generic if security-shaped), or it
is dropped.

There is no restricted vocabulary: findings are filed in whatever terms
describe them best. ONE routing rule exists — the security routing below.

> **Frame** (copy verbatim into probe / claim-check / test briefs): "QA
> documentation of an application's own screens, on a local disposable test
> install with seeded accounts. Sign in as each role and use the screens the
> way that role would — including typing a URL directly to reach one. Record
> what the screen offers, what happens when it is used, and where the two
> disagree — including any API misbehavior the browser's own traffic shows
> along the way — so the product team can fix it. Never construct a request
> the screens themselves would not send; if a claim can only be settled
> that way, return it as an open question instead of probing it. A finding
> that could plausibly be a security weakness goes ONLY into the
> maintainer's private security file (`../e2e_ng/security.md`) — never into
> a spec, test, report file or commit; these repos are public. Writes there
> are read-first: read the whole file, and if the problem is already
> recorded (Open or Handled), update that entry instead of adding a new
> one; new entries use the file's fixed entry shape, marked `unverified`.
> Say THAT you routed something there; keep its content out of everything
> else."

Volume discipline is separate and stays: detail lives in `.reports/`, returns
are short and outcome-shaped — context budgeting, not a wording rule.

## What goes where

- **Product findings** (bugs, divergences, oddities, open questions —
  including API misbehavior observed in the browser's own traffic with no
  security dimension) → the feature spec's **Findings register** (TEMPLATE).
  The only home — never `app-changes.md`, PROGRESS notes, or side documents.
- **Potential security concerns** → `../e2e_ng/security.md` (maintainer-only,
  outside every repo) — **verified problems only** (maintainer 2026-08-25).
  Decide by substance: a role seeing or doing more than it is entitled to, a
  guard that does not hold, data exposed to the wrong audience — anything you
  would not publish before a fix. The file is a list of ACTUAL problems, not
  suspicions: an observation enters marked `unverified`, and before the
  session report the orchestrator dispatches one targeted verification probe
  on the disposable install — through the screens where possible; where only
  a direct request can settle it, that single constructed check is permitted
  FOR VERIFICATION, never as exploration, and its content obeys the same
  quarantine. Confirmed → the entry stays, marked `verified` with the date
  and what was observed. Not confirmed (or unverifiable in this environment)
  → the entry is deleted; if the underlying claim still matters, the spec
  gets a generic ❓ register entry. The repos are PUBLIC: such a finding's
  CONTENT never appears in a spec, test, `.reports/` file, PROGRESS note, or
  commit message; the claim it would have supported is omitted or kept
  generic until the fix ships. The FACT of routing is never silent — a
  return or report says "one observation routed to the security file, and
  verified/dismissed" so the maintainer knows to look. Ordinary UX defects
  are not security concerns; they go to the register.

  **File hygiene** (maintainer 2026-08-25 — the file had grown repetitive
  and hard to review): any agent may write the file — the quarantine is
  about WHERE content goes, not who writes it — but **every write is
  read-first**: read the whole file, and an observation matching an
  existing entry — same guard, same screen, same root cause, even if
  noticed on a different app or role — updates that entry (add the date
  and the new context on its `observed` line) instead of adding a new one.
  **One entry per distinct problem, ever.** Every entry uses this shape,
  nothing free-form:

  ```
  ## SEC-YYYYMMDD-<slug> — one-line problem statement
  status: unverified | verified YYYY-MM-DD
  where: <app(s) · screen · role>
  observed: <2–4 lines, what was actually seen>
  verified-by: <the one check that settled it>
  ```

  The file has two sections. **Open** holds the entries above. **Handled**
  holds one line per closed item (`SEC-id — disposition, date`: fixed /
  accepted / dismissed) — the maintainer moves entries there on review.
  Handled lines are tombstones: check them before filing and do not
  re-file a handled problem unless the behavior has demonstrably changed
  (then a new Open entry naming the old id). If the file is absent, create
  it with the two section headings — absent or empty-Open means "no open
  concerns", not "never checked". At session end, after the verification
  pass, the orchestrator leaves the file tidy: dismissed entries deleted,
  duplicates merged, every remaining Open entry distinct and `verified`.
- **Build blockers** → `lib/pkp/docs/e2e/tracking/app-changes.md`: an app defect that
  had to be worked around or fixed to get tests green (races,
  nondeterministic UI, harness-hostile behavior), plus the record of actual
  app-code changes the campaign made. Nothing else.
- **Scenario-builder parity notes** → `lib/pkp/docs/e2e/tracking/parity-ledger.md`.
- **Cross-feature mechanisms** → described fully in ONE owning spec; other
  specs link (TEMPLATE rule 6).
- **Process learnings** → this file or TEMPLATE (via maintainer review),
  never inside specs.
- **Aggregate views** ("all bugs", coverage) → computed from specs on demand,
  never hand-maintained.

## Campaign-artifact maintenance (self-healing)

Artifacts the campaign itself created are LIVING (maintainer ruling
2026-08-02) — when a session discovers one is stale or defective, it FIXES it
that session as routine maintenance instead of parking a debt note. Covered:
the shared process docs (the single home — there are no per-app doc trees), shared and app-side
POMs/fixtures/helpers, earlier feature suites, the lint gate, and the `_test`
scenario API (a behavior change there still gets its parity entry). The gate
travels with the fix: every suite the fix touches re-runs green before commit
(ONE clean run suffices for a maintenance touch-up; ×2 stays for new suites),
and the fix is named in the session report.

**Shipped SPECS are maintainable too**: when a session's own live evidence
shows a claim in a shipped spec is inaccurate, or a gap sits squarely in that
spec's territory, correct THAT spec in the same session, through the spec's
own quality bar scaled to the correction's size — a writing agent folds it
(the orchestrator never edits a spec inline); evidence gets its footnote with
probe date and verbatim strings; a new defect becomes a proper register entry
with the next free ID; lint re-runs to zero on the touched spec; the session
report names every spec touched and why. Bounds: only what THIS session's
evidence established — no speculative rewrites; a correction too large or
uncertain to fold confidently becomes that spec's register ❓ with a stated
lean. A rewrite that changes how a reader would execute a rule or scenario
gets the persona re-read of the rewritten spans (step 9).

Two standing boundaries: **app code stays minimal and gated**
(`app-changes.md` row; only when blocking green or a trivially-safe mirror of
an existing pattern), and **the private-file policy is untouched** —
maintenance never moves routed content.

## What to read, when

- **The floor, every iteration**: this file + `PROGRESS.md` + the target
  feature's row in `FEATURE-MAP.md` (its atom list).
- **When authoring the spec**: `TEMPLATE.md`,
  `lib/pkp/docs/e2e/specs/GLOSSARY.md` (Part I reader vocabulary; Part II
  cross-app substitution), the feature's
  atoms in `atlas/` (`affordances-{workflow,management,user,reader}.md` per
  surface).
- **When authoring tests**: `lib/pkp/docs/e2e/process/PRINCIPLES.md` + the
  shared harness docs (`lib/pkp/docs/e2e/process/` — start at `harness.md`;
  per-app deltas live inside them, not in per-app files).
- Beyond the floor, read whatever helps — subject to context hygiene: a
  spec-writing agent works from the draft plus the digest (step 3b) and pulls
  a specific probe report only when its judgment needs the detail behind one
  digest block. The orchestrator stays lean; detail belongs in subagent
  contexts.

## Budget & ceilings (HARD — single home for the numbers)

- **Per app: ≤ 700 tests, ≤ 25 min** full-suite runtime on a fresh DB (fleets
  run in parallel, so wall-time does not stack across apps).
- Tiers live in each PROGRESS row (`Budget: tier·target`): H 10–13, M 6–8,
  L 3–4 — the tier bounds the spec's COMMON-scenario count (±1–2 by author
  judgment). Each app's suite implements the common scenarios plus its own
  specifics, so its per-feature count is the tier plus those.

## The multi-app rules

Every feature is specified and tested across OJS, OMP and OPS.

1. **One spec, all three apps.** No per-app copies. The body describes shared
   behavior; an UNMARKED claim asserts "verified identical in every app that
   has the surface" — absence of a marker is itself a claim. The evidence bar
   for an unmarked claim (no budget allows exhaustive per-claim probing):
   - claims about *shared code paths* → the subclass-chain check (rule 8); an
     empty chain on the load-bearing path is positive evidence;
   - claims about *permissions, exclusivity, or affordances* — the classes
     code-reading gets wrong — additionally need a live cross-app probe
     (rule 4);
   - a claim covered by neither gets probed, or gets a marker.
   Divergences carry an inline app marker linking to the Findings register. A
   feature an app doesn't have: title badge (`{OJS OMP}`) + one absence
   paragraph — absences are written as install facts ("not installed by
   default"), never impossibilities, and probed as such.
2. **Detailed scenarios live in the spec, common first.** Canonical scenarios
   are the QA-executable description of the feature and the units tests map
   onto: FIRST the scenarios common to every app that has the feature, THEN
   the app-specific ones. Per-app divergence inside a common scenario is
   marked inline; a scenario an app can't run is flagged with its analogue or
   absence.
3. **Tests are written per app, derived from the spec** (maintainer ruling
   2026-07-26). Each app's suite covers the common scenarios in that app's
   own context — its roles, seeded data, vocabulary — plus its app-specific
   scenarios. Duplication between suites is acceptable: the SPEC is the
   maintained artifact. Standing constraints: never assert a 🐞 finding as
   contract; a claim parked on an open ❓ is not a coverage gap; an absent
   feature costs one absence test with a positive control per assertion; each
   suite's file header declares what it deliberately does not cover.
4. **Probing is cross-app by construction.** Every exclusivity claim ("only X
   can", "never shows") gets a read-only control probe in the other apps. A
   probe item that spans apps is owned by ONE agent driving all fleets (or an
   explicit merge step) — never split by app mid-item.
5. **Base corrections are expected output.** OMP/OPS probing routinely
   disproves what the spec says about OJS itself; a finding touching shared
   base text is re-checked on OJS before the spec is finalized. Normal yield,
   not scope creep.
6. **Divergence provenance guides the verdict.** Weigh the age signal:
   behavior untouched since the app's early years reads as intent; behavior
   broken inside a modernization window reads as decay. The verdict lands in
   the register badge + one rationale sentence; the archaeology stays in
   footnotes and scratch reports.
7. **Graduation: divergence vs own feature.** A difference that PARAMETERIZES
   existing machinery (another stage, another decision, a reduced role set)
   stays a register divergence — pure reductions never graduate. A difference
   earns its own spec (and FEATURE-MAP row) when it needs rules of its own:
   UI surfaces whose atoms no existing spec claims, or replacement scenarios
   rather than modifications. **Maintainer test (2026-07-27)**: similar
   features are ONE shared feature only when one is essentially the other
   *rebranded* — sharing most of the code (rule-8 check) and the business
   logic; otherwise separate app-specific features even when the intent
   rhymes (OMP catalog vs OJS archive: own handlers, own data model → own
   features). Forked-copy code with provably identical logic (the OJS↔OPS
   pattern) counts as one feature, but every shared claim there needs probe
   evidence — the chain check cannot vouch for copies. (OMP/OPS-unique
   surfaces stay out of scope per the Scope above until the maintainer extends it.)
8. **Where divergences live in code: inheritance first.** The apps' primary
   mechanism is class inheritance — each app subclasses shared lib/pkp
   classes. The first move for any load-bearing shared class is each app's
   subclass chain: an EMPTY subclass is positive evidence of shared behavior;
   an override is where intended divergence lives; a MISSING override — or an
   extension point a refactor quietly turned into a constant — is the classic
   silent divergence. Explicit `isOJS()`-style branches, registry/seed file
   differences (`userGroups.xml`, `emailTemplates.xml`) and config-merge
   survivors are the secondary seams, greppable after the hierarchy is
   understood.

## The per-feature loop

**Orchestration shape**: heavy work is DELEGATED — spec author, probe agents,
digest agent, test authors, claim checkers are separate subagents (model
policy in Model discipline). The orchestrator briefs them (each brief points
at TEMPLATE / PRINCIPLES — never paraphrases the rules), judges results, and
is the ONLY writer of PROGRESS rows, atlas `Claimed by:` markers, and
`app-changes.md` entries. EVERY subagent brief carries verbatim: "Do NOT
write to PROGRESS.md, atlas files, or lib/pkp/docs/e2e/tracking/app-changes.md; return
proposed content in your report instead." Every probe, claim-check and test
brief ALSO opens with the **Frame** paragraph, verbatim, before the task.

1. **Claim it** — set the feature's PROGRESS row to `in_progress`.
2. **Author the spec** → `lib/pkp/docs/e2e/specs/U<nn>-<feature>.md` (zero-padded
   FEATURE-MAP row number first, so files sort in map order) per
   TEMPLATE, covering all three apps from the start. Draw from the feature's
   atlas atoms + the code, including its `atlas/affordances-*.md` rows —
   every affordance on the feature's screens ends up covered by a rule or
   scenario, delegated with a verifiable pointer, or explicitly waived. Where
   the code is ambiguous, don't guess — put the question on the PROBE LIST
   the author returns with its draft (the author never probes). **Every probe
   item is phrased as screen actions and observations** — "as role R, on
   screen S, do X; record what appears". An item that cannot be phrased that
   way is not probed: the claim it would have supported gets a ❓ register
   entry with a stated lean (kept generic if security-shaped), a marker, or
   leaves the draft — there is no deferred-questions queue. The list
   includes the cross-app controls from rule 4.
3. **Probe** — the list is farmed to probe subagents (fresh context, tight
   scope, facts-only reports to `.reports/`). A probe answers "what does this
   role actually see and get on a running install?", through the screens. Any
   statement about what a UI control *does* — appears / enabled / says X /
   absent, in state Z for role R — is the error class code-reading gets
   wrong; no affordance claim ships without driving it live. Probes are
   throwaway (retained tests are step 6's); reports record the locator used
   and mark claim-vs-context (incidental DOM observations are not
   promotable). They are written for the digest agent — and the maintainer,
   who may audit before sign-off.

   **3b. Digest — the context seam.** ONE digest agent reads every probe
   report and emits `.reports/<feature>/digest.md`: the spec-affecting facts
   and nothing else — the ONLY evidence artifact step 4 reads. One block per
   fact:

   > `### D<n> — <one line, product voice: what a person sees or gets, on which screen, as which role>`
   > `Affects:` Rule 9 | Actors row 2 | scenario 3 | register A5 | new
   > `Status:` confirms | corrects | new | undetermined
   > `Apps:` the apps it holds for (per-app difference stated in the line)
   > `Proposed:` 🐞 | ❓ | ✅ | plain claim · rule text | register entry | footnote | drop
   > `Evidence:` report file + item number — a pointer, never a quotation

   Each line reads as product behaviour in the spec's own voice; reproduction
   narrative and quoted report prose stay in `.reports/`. An `undetermined`
   entry says only that plus the one observation that would settle it.
   `Proposed:` is a suggestion; step 4 decides. Size is the gate: ~two pages
   for an M-tier feature — a digest that won't fit means the probes overshot.
4. **Finalize the spec (writer's judgment).** A fresh agent folds the DIGEST
   into the draft (brief carries draft path + digest; it may pull the one
   report behind a digest block when needed). **The digest is raw material,
   not spec content** — it still overshoots: trivia, fixture accidents, other
   features' territory, optimistic severity. The finalizer includes a finding
   only at the weight its user impact earns, in product voice, and may
   downgrade or drop anything; what doesn't clear the bar stays in
   `.reports/`. Findings belonging to another feature go to that spec via a
   link. **Fold in slices** — one digest section or one spec section per
   agent for an H-tier feature; small chunks are the standing rule for
   writing work. A fold agent that stalls on a technical limit is respawned
   on a NARROWER slice (max 2 retries); nothing is left half-folded. **A
   refusal or safeguard flag is not a stall** — PAUSE per Model discipline;
   never re-press the brief or degrade the item to route around a flag.
5. **Lint gate** — TEMPLATE "The lint gate": reference integrity ONLY
   (register ↔ marker integrity, link/anchor/footnote resolution, campaign
   identifiers a reader cannot resolve). Wording, vocabulary and the leak
   rule are the writer's judgment, never linted (maintainer 2026-07-31).
   ZERO findings before tests are written.
6. **Write the Playwright tests** — per PRINCIPLES + the shared harness
   docs: one
   suite per app, derived from the spec (rules 2–3), one test per canonical
   scenario in each app that runs it. Seed via scenario endpoints,
   reuse/extend POMs, scope Mailpit by a unique throwaway recipient
   (PRINCIPLES A8), pair every silence claim with a positive control.
   `--output` to a private dir, `--reporter=list`.
7. **Run them green twice** per app against the live fleets. A test
   contradicting the spec means the **SPEC is wrong**: fix the spec (finding
   → register). Never edit a test to pass a claim the app disproves. An app
   defect blocking green → work around and record in `app-changes.md`.
8. **Claim check** — chunked subagents test the spec's own claims against
   the running app, per app where behavior diverges: each permission and
   state rule, the cases most likely to show it wrong, including whether the
   surface is still reachable at all. The target is OUR OWN TEXT — catch an
   inaccurate rule before a QA reader trusts it. Same screen-instrument
   scope. The merge agent returns a change list **in the digest schema**; a
   fold agent folds ACCEPTED findings under step 4's rules. Unresolvable
   items become ❓ register entries with a stated lean.
9. **Readability verify** — a SEPARATE subagent in strict persona: a QA/PO
   person who has never seen a campaign document, no code access, reads ONLY
   the body above the footnote tail. They (a) restate every rule in their own
   words and (b) walk each scenario as a manual test. Three things are
   stumbles, and the brief says so: a verb or noun they cannot map to
   something on screen; **any token they cannot resolve from the page
   itself** (a code, an ID, a cross-reference naming no feature — the persona
   has read no other spec and must not "recognise" campaign notation); a step
   they could execute two ways, or an outcome they could not judge pass/fail.
   Rewrite the stumbles, re-run lint, then run the persona ONCE MORE over the
   rewritten passages only — a rewrite is not verified by its writer. Gate:
   zero blockers; frictions are the writer's call. **Rewrites preserve
   verified meaning** (maintainer 2026-08-07): the rewrite brief names the
   digest and footnotes behind every claim being reworded and carries
   verbatim "Preserve the verified meaning — reword the phrasing, never the
   claim." A rewrite that nevertheless changes a claim's substance goes back
   through its evidence before commit (top-up probe or re-run of the derived
   tests) — readability runs after steps 7–8, so drift here is the one error
   nothing downstream re-checks.
10. **Update PROGRESS** — status, #tests per app, and a SHORT note (1–3
    lines): register highlights are welcome (🐞/❓ counts, the finding a
    reviewer should read first, anything low-confidence — it drives sampling
    review); finding DETAIL stays in the register.
11. **Commit** — single home of the commit rule (PRINCIPLES points here). `lib/pkp` and app root commit **separately**; NEVER bump
    submodule pointers in ANY app repo (`git restore --staged lib/pkp
    lib/ui-library plugins` before any root commit; stage files explicitly,
    never `git add -A` at an app root). Specs, campaign docs, and shared
    test/POM/builder changes commit inside `lib/pkp` — but never `.reports/`
    scratch (retention rule below); app-only tests commit in each app's
    root. Shared-change flow: commit in `ojs-main/lib/pkp` → push the
    campaign branch (`e2e_ng_2`) to the `jardakotesovec` fork → in OMP/OPS
    `lib/pkp` fetch and check out the SAME branch. That checkout IS the sync
    — no re-pin commit follows (maintainer ruling 2026-07-28); `M lib/pkp`
    in app status is normal and stays uncommitted.
12. **Report** — what was built, register highlights, anything
    low-confidence. If anything was routed to the security file this
    session, the verification pass ("What goes where") has already run and
    the report states the outcome as counts only (verified / dismissed).
    Open questions stay recorded, not resolved — the team
    settles them on spec review. Then STOP: in review mode, one feature per
    fresh session; in wave mode, also flag when the wave counter reaches 7.

## Autonomous waves (post-review mode)

Active ONLY when the PROGRESS banner says so. One full loop per session;
every feature starts in a clean session the maintainer launches. Context is
disposable — all state lives in PROGRESS + files.

- **Selection**: first `pending` row in PROGRESS table order; respect
  maintainer edits.
- **Wave = 7 features**, then STOP and request a sampling review (maintainer
  spot-reads 1–2 specs + everything flagged). Nits → fix in place; systemic →
  HALT, encode the fix in TEMPLATE/RUNBOOK first, sweep the wave, resume.
  The wave counter is tracked in the PROGRESS banner while wave mode is
  active.
- **Park-and-continue**: 3 failed attempts → row `parked` with reason, move
  on (wave mode only; in review mode, stop and report instead).

## Rebuilding a feature from scratch

Replaced artifacts are DELETED, not archived, and the rebuild is never
checked against them — the author works blind (code + atlas only) so the new
spec's quality measures the process, not the copy. The legitimate check is
self-contained: does every claim rest on evidence THIS build produced? Any
claim that doesn't gets a top-up probe or leaves the spec. If the old version
is ever genuinely needed, git history has it.

## Resuming a feature mid-flight

If PROGRESS shows `in_progress` and the tree holds uncommitted work, RESUME —
the gates are idempotent: re-run lint (step 5), the tests twice (step 7), and
judge from the spec's own footnotes whether live probing ran (probe dates in
`<sup>` notes). What a prior session's subagents reported is GONE — only
files count. If a stage's completion is undecidable from files, re-run it.

## Model discipline

- **FABLE RUNS EVERYTHING** (maintainer, 2026-07-31): the orchestrator and
  every subagent, every role. No per-role model split, no per-agent model
  pins (subagents inherit the session model), no fallback to another model.
- **Pause on flag.** If any agent is refused, flagged by safeguards, or
  silently downgraded to a non-Fable model mid-run: discard that attempt's
  output, do NOT re-press the brief, do NOT respawn onto another model, do
  NOT degrade the item to route around the flag. (Detect silent downgrades
  from the agent's transcript: grep its JSONL for `"model":` — every
  assistant line must be claude-fable; spot-check writing agents at
  completion.) Record the point reached in the feature's PROGRESS note, log
  the event in the Model-fallback log, and STOP for maintainer review. An
  ordinary technical stall (context overflow, tool error, environment
  breakage) is not a flag — the narrower-slice retry applies.
- **Small chunks stay the rule** for writing work, and the digest stays the
  default evidence input for spec writers. Both are context hygiene, not
  sanitization: nothing is withheld — the trail behind each digest block
  stays readable in `.reports/` for the feature's duration.
- **Subagent returns are pointers, not findings.** A probe or claim-check
  agent returns where its report is, how many items it covered, and whether
  anything blocked it. The digest agent reads reports; the orchestrator never
  carries their contents.
- **Model check at session start** (`/model` must be Fable) — a handoff
  session starts on the saved default, not the predecessor's model.
- **Model-fallback log** (PROGRESS): anomalies ONLY — refusals, flags,
  downgrades, pauses (date · feature · role · what happened).
- **Brief hygiene**: briefs are the Frame paragraph plus pointers (feature,
  spec path, report path, "follow RUNBOOK step N") — point at the rule
  files, never paraphrase them.
- **The orchestrator NEVER probes, verifies or edits a spec inline** —
  inline completion is how the controlling agent gets lost, and spec edits
  belong to the writing agents. If context runs low mid-feature: finish the
  current gate, commit what's commit-worthy, END; a fresh session resumes.
- **Liveness**: the completion notification is the only reliable subagent
  signal. Never judge by transcript size; check ground truth (did the target
  file change?) or wait.

## Ops & campaign safeguards

Environment facts (fleets, ports, config contract, env vars, run commands,
recovery) have ONE home: `lib/pkp/docs/e2e/process/harness.md`. Campaign-side
rules that stay here:

- **Live-probe etiquette** (campaign invariant): scratch contexts for anything
  mutating; `publicknowledge` and seeded users are read-only; never
  `clearAll()` Mailpit.
- **DB hygiene cadence**: reset before any full-suite timing run and every
  ~8–10 features.
- **Git**: push only to `jardakotesovec` remotes, campaign branch `e2e_ng_2`;
  verify the remote URL before every push; a bad pushed commit gets a
  follow-up commit, never a force-push.
- **`.reports/` retention** (maintainer ruling 2026-07-31): per-feature
  reports — probe reports, `digest.md`, claim-check chunks and merge — are
  SESSION-LOCAL SCRATCH: required during the loop, never committed (the
  directory is gitignored); a feature's scratch may be deleted after review
  sign-off. The SPEC must be self-sufficient — probe dates and verbatim
  on-screen strings live in its footnotes (TEMPLATE rule 4), never citations
  of report files; a shipped claim disputed later is settled by a fresh probe
  on the current build. Security rule unchanged: a potential security concern
  never appears even in scratch. Maintainer ruling 2026-08-25: the two
  pre-rule evidence sets (`.reports/phase0-feature-map/`,
  `.reports/step1-harness/`) are no longer kept on the tip either — they were
  removed that day and remain reachable in git history; accumulated
  per-feature scratch was deleted at the same time. Citations of those files
  in FEATURE-MAP, UNASSIGNED, GLOSSARY and the parity ledger resolve via git
  history.

## Definition of done

- **Per feature**: spec `verified` + lint-clean; all three apps covered per
  the multi-app rules; every affordance atom covered / delegated / waived;
  each app's suite green twice; PROGRESS row updated (short note); committed;
  **maintainer sign-off in review mode**.
- **Campaign**: the campaign-level bar lives in "Mission, scope & invariants" above.
