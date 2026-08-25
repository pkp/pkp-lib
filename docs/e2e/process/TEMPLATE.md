# Spec template

Copy this file to `lib/pkp/docs/e2e/specs/U<nn>-<feature>.md` (zero-padded FEATURE-MAP row number) and fill every section (or mark it `N/A —
<reason>`). HTML comments are guidance — delete them in the real spec.

**Who these specs are for** (the maintainer's standing principle): a QA person or
product owner who wants to **learn** the feature, **review** whether it behaves as
intended, or **add** to it. Concise, but covering all the relevant details for the
area. The spec is the **source of truth**: everything the campaign knows about the
feature — behavior in all three apps, divergences, bugs, open product questions —
lives here, and everything else (tests, bug lists, coverage views) is derived from
it. If a reader needs a developer or an internal report to understand a sentence,
the sentence is wrong.

**One spec covers OJS, OMP and OPS.** The body describes shared behavior; an
UNMARKED claim asserts "verified identical in every app that has the surface" —
absence of a marker is itself a claim, never "not yet checked" (how that bar
is met — inheritance evidence + targeted probes — is RUNBOOK multi-app
rule 1). Divergences carry
an inline marker linking to the Findings register. A feature an app lacks
entirely: title badge (`{OJS OMP}`) + one absence paragraph up front, written as
an install fact ("OPS does not install X by default"), never an impossibility.
Cross-app vocabulary (press/server for journal, monograph/preprint for
submission…) follows `lib/pkp/docs/e2e/specs/GLOSSARY.md` Part II; write the OJS term once with the recast
noted in the preamble, not re-badged on every mention.

**Altitude — two principles, no length quota** (maintainer, 2026-07-10). (1)
*Complete*: enough to accurately recreate the feature from the spec alone. (2)
*Compressed*: state each fact once, at its home section, and reference it
elsewhere — never repeat mechanically. Length is whatever those two principles
produce.

**The lint gate (reference integrity only):** every spec must pass
`lint/lint-spec.mjs` with ZERO findings before test authoring (RUNBOOK
step 5). The gate checks only what is mechanically decidable AND is a broken
reference for the reader (maintainer, 2026-07-31 — broader linting was
constraining the writing): Findings-register integrity (markers ↔ entries
both ways, badges present, summary table agrees with entries, dense IDs),
link resolution (every link/anchor/footnote resolves), and campaign
identifiers in the body (rules 6–7 — a FEATURE-MAP row code or atlas atom ID
is a reference no PO/QA reader can resolve). Everything else — the leak rule,
glossary vocabulary, app badges, phrasing — is the writer's judgment, checked
by the readability pass, never by the gate.

The non-negotiable rules (the gate enforces only the reference-integrity
subset above; the rest bind by the writer's judgment):

1. **Business-language bodies, code in footnotes.** No section BODY may contain a
   class/method name, route, Vue component, DB table/column, constant, or HTTP
   status code. Describe what a user OBSERVES or DOES. Every code symbol is
   PROVENANCE and lives in a `<sup>x</sup>` footnote — and ALL footnote blocks
   live in one `## Footnotes — mechanism & evidence` section at the document
   tail, so the upper spec stays pure product language. Anchor to STABLE SYMBOLS
   (`ClassName::method()`, a constant, a route path), never line numbers.
2. **Concrete role names, never umbrellas.** Use the app's actual role names —
   e.g. **Site Administrator, Journal Manager, Section Editor, Assistant,
   Author, Reviewer, Reader** (illustrative, not exhaustive: the app's Roles
   settings screen is the source; OMP/OPS analogues per GLOSSARY Part II). Never
   "editorial staff", "editors", "the full manager". If several roles qualify,
   LIST them; if assignment- or scope-based, say so. One canonical name per
   role per spec.
3. **One home for permissions.** Actors & permissions is the single source of
   who-may. Rules & state describes behavior and state and never restates the
   permission matrix; where behavior depends on a role, name the state and defer
   the who to Actors.
4. **Probe evidence is provenance, exactly like code anchors.** Status codes,
   redirect targets, "live-probed" notes and dates, seeded usernames and
   journals prove a claim; they are not the claim. They live ONLY in footnotes.
   The body states the observable outcome in the user's terms.
   Footnote citations are **self-contained**: a probe is cited by its date and
   what was observed ("live-probed 2026-07-31: both links present, single-use"),
   NEVER by a report file or item number — `.reports/` is session scratch
   (RUNBOOK ".reports/ retention") and a committed spec must resolve on its
   own.
   - **Bad**: "`/authorDashboard/submission/{id}` redirects to My Submissions
     … (live-probed 302, both author kinds)"
   - **Good**: "An old bookmarked author-dashboard link lands on My Submissions
     with that submission's tracking view open. <sup>g</sup>"
5. **Findings are accurate and easy to read — those are the only wording
   tests.** A finding states expected behavior, observed behavior, and impact
   in the same product voice as the rest of the spec. **Write it as what it
   is: a product defect report for the team that maintains this code** — here
   is what the screen led the user to expect, here is what they got, fix one
   of them. Say exactly what happens, in whichever words say it best; never
   soften a finding into vagueness — a reader must be able to tell precisely
   what is broken from the spec alone. What a finding is
   NOT is a walkthrough: the spec states the outcome; step-by-step
   reproduction and accumulated evidence narrative stay in the session's
   `.reports/` scratch (read on demand while the feature is being built),
   because a PO reader needs the outcome, not the trail. Every
   finding the campaign produces belongs in a register, with ONE exception:
   a potential security concern goes to the maintainer's private security
   file and never into a public spec, test or report until the fix ships
   (RUNBOOK "What goes where" — these repos are public).
   - **Bad**: "⚠ the restriction may not fully apply in every case."
   - **Good**: "⚠ the Section Editor is offered **Remove Role** on this screen,
     but pressing it returns to the list with the role still assigned and no
     message [A3](#a3)."
6. **Shared mechanisms: one owner, real links — never a restatement.** A
   mechanism serving several features is described fully in exactly one spec —
   the one whose subject it is — and every other spec links instead of
   retelling: the owner marks the passage `<a id="stage-access"></a>` (explicit,
   never heading-derived); a referencing spec keeps only what its own reader
   needs, then points `[→ stage access](U24-workflow-screen-and-stage-access.md#stage-access)`.
   The lint gate resolves every link (missing file/anchor/duplicate id =
   finding). Before describing any cross-feature behavior, grep `specs/` for
   its user-facing string: if another spec owns it, link; if this spec is the
   natural owner, take the passage over and leave links behind.
   **Name the feature, never its row code.** A cross-feature pointer reads as
   the feature's NAME in the reader's words — "participants are managed on the
   Participants panel (see *Stage participants*)" — and becomes a real link
   once that spec exists. Until then the name stands alone; a bare `U35` is
   unresolvable for a PO or QA reader and the lint gate rejects it, as it does
   atlas atom IDs outside the Reference tables. Builder's phrasing goes with
   it: features do not "own" things in a spec — say where the reader goes.
   **Ownership follows the screen, not the trigger** (maintainer, 2026-07-26):
   a status, notice or other display belongs to the feature that owns the
   screen where it renders, even when another feature's actions drive its
   transitions — a status may read from many sources but it is a status OF one
   surface. The triggering feature keeps one side-effect line plus a pointer
   to the owning spec.
7. **Findings enter at the weight their impact earns.** The digest (RUNBOOK
   step 3b) is raw material, not spec content; the writer works from it by
   default, opening the report behind a block only when judgment needs the
   detail. The writer judges each candidate finding: does it
   belong to THIS feature, is it user-relevant, and does the proposed weight
   match what a user would actually notice — then writes it symptom-first in
   product language, at proportionate length. **Severity is proposed by the
   digest and settled by the reader, not argued in the spec**: badge it, state
   the symptom, state the impact in one plain word, and stop. Trivia, fixture
   accidents, and neighboring features' findings stay in `.reports/` or move to
   their owning spec. Campaign-internal vocabulary ("probe", "digest", "claim
   check", "orchestrator") doesn't belong in a spec — the reader is a PO or QA
   person, not the campaign; evidence citations live in footnotes as opaque
   pointers. (Writer's judgment, not lint-enforced.)
8. **Variance-based ownership.** Behavior invariant across contexts is
   specified ONCE, in the mechanism's home feature; context features own the
   deltas — presence, configuration, permissions, consequences — and point to
   the home for mechanics (rule 6 links, verifiable both directions). Litmus
   per sentence: "if I changed stage/role/surface, would this still be true?"
   Reusable managers (file manager, participant manager, tasks & discussions…)
   get their mechanics in the manager's own feature; stage features own each
   instantiation — which panels mount, which actions/columns appear, and the
   role × state gates on that stage. Affordance-atom attribution follows the
   same split. Test corollary: mechanics are deep-tested once in the home
   feature; context features test only gates and instantiation — duplicate
   mechanism coverage is a reviewable defect.
9. **One shared workflow screen, author included.** The dashboards are
   separate features (the editorial dashboard and My Submissions each own
   their list), but the workflow screen both open is ONE shared surface.
   Workflow-page specs cover EVERY role on it — the Author included — in the
   same permission rows: role determines what is available; it never creates
   a separate surface. Never split a stage into editor-view and author-view
   features, and never frame the author's access as its own reduced screen.
   The author's entry route (View on My Submissions) belongs to the
   author-dashboard feature; everything after it belongs to the workflow
   features.
10. **Glossary discipline.** `lib/pkp/docs/e2e/specs/GLOSSARY.md` is the living definition home for
    the product vocabulary the specs use — the terms as OJS/OMP/OPS screens
    use them, defined so a QA/PO reader can follow any spec, plus the
    settled resolutions of term collisions (which of two competing words the
    specs use, and what a shared word means where). On-screen names always
    win; a term may be coined only when the screen offers none; every
    coined term has ONE definition home (the glossary), and first use per
    spec carries a gloss or pointer. Applies to test naming too. Cross-app
    NAME substitution (journal/press/server…) is `GLOSSARY.md Part II`'s job,
    not this one's — the glossary defines meanings and links there for
    renames. New specs check the glossary before coining and add missing
    terms as part of authoring.

**Everything clickable.** Body markers link to register entries; register IDs
link back from the summary table; `<sup>` marks link to their footnotes; cross-
spec pointers are real links. Anchors are explicit `<a id="…"></a>` on their own
line. A reference a reader might want to follow IS a link — lint enforces
resolution both ways.

---

```markdown
---
name: <feature-slug>
scope: <one-line: the user job this feature serves>
apps: [ojs, omp, ops]       # apps that have the feature (all three unless absent)
shared: pkp-lib | no        # implemented in lib/pkp or app-only
status: draft | verified    # verified = the full RUNBOOK loop passed (claim check resolved, lint zero, readability, tests green ×2)
atlas-claims: [<atom IDs this spec owns>]
---

# <Feature name> {OJS OMP OPS}

<!-- Title badge only when an app LACKS the feature; omit when all three have it.
     If badged, follow the Purpose section with a one-paragraph absence note. -->

> Conventions (markers, badges, footnotes): [Reading a spec](GLOSSARY.md#reading-a-spec).

<!-- That one line replaces the old per-spec "How to read this file" section
     (maintainer ruling 2026-08-25): the legend has ONE home, the glossary's
     "Reading a spec" section — specs link, never restate. A spec that needs a
     construct the legend doesn't cover should simplify the construct, not
     grow a local legend. -->

## Purpose

<!-- One paragraph. Whose job, what job, why it exists. A new PM should get it. -->

## Actors & permissions

<!-- One ROW PER CAPABILITY (View, Create, Edit, Remove…), so a reader compares a
     capability across roles in one row. Two columns: Action | Who may — and when.
     Cells are PLAIN PRODUCT LANGUAGE, one bullet per role-group/condition
     (`<br>• `), each bullet: actor(s) then condition. Lead with a short
     paragraph defining recurring terms (assigned, participant…) and site-wide
     baselines, so cells stay terse. THE SCREEN IS THE INSTRUMENT (RUNBOOK):
     the row records what the role is OFFERED and what happens when they use
     it. Where the offer and the outcome disagree — the control is there and
     does nothing, or is refused, or is missing where the role should have it —
     the observed outcome is the headline and the offer carries a ⚠ marker into
     the register. Never state a capability the screen does not expose.
     ONE sentence per fact: keep each cell's rule here, and the register entry
     carries only the finding — not a retelling of the cell. A cell states WHO
     and WHEN and stops. When the "when" needs a chain of conditions to be
     accurate, that belongs in Rules & state as its own numbered rule and the
     cell cites it ("… while the round awaits revisions (Rule 12)"): a bullet
     a reader has to re-read is a defect, however true it is. Anchors hang off
     `<sup>a</sup>` markers; the blocks live in the Footnotes tail. -->

| Action | Who may — and when |
|--------|--------------------|
| **<Action>** | • <actor(s)> — <condition><br>• <actor(s)> — <condition; ⚠ [A1](#a1) for oddities> <sup>a</sup> |

## Fields & validation

<!-- Fields by on-screen LABEL, not internal attribute names. Validation in plain
     terms. Drop purely server-set fields. Three columns; anchors as footnotes. -->

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|

## Rules & state

<!-- The heart: state machine, invariants, computed behavior, ordering rules.
     Numbered rules; if a rule is really two rules, split it (10a/10b) so other
     sections can cite the half they mean. ONE sentence, one rule: prefer three
     short sentences over one 90-word chain — front-load the condition, then the
     consequence. Name states and fields as the UI shows them. Enumerations of
     3+ parallel items get bullets or a compact table, never a prose run.
     ⚠-mark as-built oddities with a register link — symptom stated ONCE here
     (its home), bare marker anywhere else it surfaces. -->

## Side effects

<!-- Emails (mailable, recipients, opt-outs), notifications (type, where
     surfaced), log entries, jobs, cross-entity mutations. One bullet per
     effect — don't stack five findings in one bullet. -->

## Settings that modify behavior

<!-- Site/context settings, config vars, plugin toggles that change the rules
     above — and HOW. -->

## Cross-feature interactions

<!-- Other specs this one touches; who owns each shared rule (rule 6 links). -->

## Canonical scenarios

<!-- Named narrative journeys a QA person can act out on any install — these are
     the units tests map onto (each app's suite implements them per the RUNBOOK
     multi-app rules). ORDER: first the scenarios COMMON to every app that has
     the feature, then the app-specific ones (title the block or badge the
     scenario). A common scenario is written once, app-neutral, and each app
     implements it in its own context — roles, data and vocabulary per
     GLOSSARY Part II — so keep any app-varying step behind an inline marker
     rather than baking one app's nouns into the flow.
     Name actors BY ROLE, never seeded accounts.
     WRITE EACH AS A MANUAL TEST SCRIPT: what the tester does and what appears
     on screen, quoting real UI labels; no builder's-seat verbs (pins, fires,
     wires), no nouns invisible on screen. Scenarios must stand alone — a
     reader executes them without a trip back into Rules. Mark per-app
     divergence inline ([OMP2](#omp2)); a scenario an app cannot run gets its
     analogue or absence noted at the end of the scenario. Seeding recipes and
     usernames live in the scenario's footnote.
     Acceptance test: a QA person who has NEVER opened the screen can execute
     the scenario and judge pass/fail. -->

1. **<Scenario name>** — <actor(s)>: <flow in 2–4 sentences, including the
   observable outcome>. <sup>s1</sup>

## Findings register

<!-- THE single home for everything as-built that deviates, diverges, or needs a
     product ruling — there is no separate Known-deviations or Open-questions
     section, and no external bug ledger. Structure:

     Preamble (3–5 lines): "Verdicts are the author's judgment (claude, <date>),
     unreviewed unless an entry notes otherwise; the team settles them on spec
     review." + the sort rule + "each entry opens with the user-observable
     symptom; mechanism and evidence live in the entry's footnote."

     Summary table — the triage view, sorted 🐞 → ❓ → ✅, mirroring the entries
     (entries are the source):

     | ID | Finding (one line, symptom) | Bug? | Impact | Review |

     Badges: 🐞 defect (author's call) · ❓ needs a product ruling · ✅ intended
     divergence. Impact: one plain value (user-visible / invisible / latent /
     minor). Review: — until someone reviews; then their name+date.

     Entries under `### All apps` / `### OMP` / `### OPS`. IDs are LOCAL and
     DENSE — A1, A2… / OMP1… / OPS1… — no gaps, no foreign keys. Anchor each:
     `<a id="a1"></a>`. Body markers: `⚠ [A1](#a1)` when the entry is 🐞/❓
     (⚠ means "as-built deviation here", any scope); plain `[OMP2](#omp2)` for
     ✅ intended divergences.

     Entry anatomy (5–8 lines):
     **A1 — <short title>** · 🐞 · user-visible.
     <Symptom, 1–3 sentences: present tense, user as subject, expected vs
     observed. State it at the weight it earns (rule 7).>
     <For ❓ only> Question: <the one sentence the team answers>. Lean: <the
     author's lean and why, one sentence>.
     Since: <date (age)> · Basis: probe | commit | judgment. <sup>f-a1</sup>
     <A maintainer verdict is a BLOCKQUOTE after the entry, so it stands out
     when scanning (maintainer convention 2026-08-25):
     > **Reviewed — name, date**: confirmed | overturned (was 🐞). Ruling: <the
     > decision, and any adjustment it sets>.
     Only when it happens; never pre-printed. Mirror the reviewer + date in
     the summary table's Review column.>

     `Since:` only when dated (omit the line otherwise). One rationale sentence
     for a 🐞-vs-✅ call is welcome ("worked for OPS's whole life; broke in the
     2025 stage removal — regression, not choice"); the commit archaeology goes
     in the footnote. A finding another feature owns: one line + link to that
     spec, full entry there. -->

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<!-- ALL `<sup>` blocks from every section above, in section order, each anchored
     (`<a id="fn-a1"></a>`) so marks link down. Code symbols, probe dates,
     seeded accounts, commit archaeology — the developer's layer. A reader can
     ignore this section entirely and lose no behavior. -->

## Reference — entry points & surfaces

<!-- Where the feature is reached: UI paths, API endpoints, CLI, email links.
     One row per entry point with its atlas atom ID. -->

| Entry | Path | Atom |
|-------|------|------|

## Reference — code anchors

<!-- The load-bearing files (handler/controller/manager/schema). Not exhaustive. -->
```
