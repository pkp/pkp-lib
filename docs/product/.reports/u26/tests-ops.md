# U26 "Review stage & rounds" — OPS test author report

**Feature:** U26, spec `lib/pkp/docs/product/specs/review-stage-and-rounds.md`
**App:** OPS (`/Users/jarda/git/pkp/pkp-main/ops-main`), fleet on `http://127.0.0.1:8200`
**RUNBOOK step:** per-feature loop 6–7, ABSENCE test only
**Author:** claude (Opus 5), 2026-07-27

---

## 1. Deliverable

OPS lacks the feature, so the whole OPS suite for U26 is **one absence test**
implementing the spec's scenario 13 (`{OPS} Absence check`), written in OPS's
own vocabulary and roles.

### Files created

| File | Note |
|---|---|
| `/Users/jarda/git/pkp/pkp-main/ops-main/playwright/tests/review-stage-and-rounds.spec.js` | The one absence test. Named after the spec; flat in the app's own `playwright/tests/`. |

**No other file was created or modified.** Nothing under `ops-main/lib/pkp`
was touched (verified: `git status` in the submodule is clean). No shared POM,
fixture, scenario-schema or app-code change was needed.

### Test shape

- One `test()`, tagged `@regression`.
- Seeds its own preprint via `POST /api/v1/_test/scenarios/submission`
  (step-2 core schema, no extension needed): `tag` + `context: publicknowledge`
  + `submitter: author.alex` + `title`. The base server and its seeded accounts
  are read-only; no server-level mutation is required for this scenario, so no
  scratch context is created — the isolation unit is the submission
  (PRINCIPLES architecture principle 1).
- Runs the same walk for **two roles**: `manager.maya` (Preprint Server
  Manager — scenario 13's named actor) and, via `asUser`,
  `sectioneditor.ana` (Moderator, OPS's sub-editor slot per APP-GLOSSARY §1,
  auto-assigned to the preprint as a section-PRE participant).
- Tag pattern is a single hyphenless alphanumeric token with a per-run random
  component (`u26w{parallelIndex}{rand}`), per patterns.md.

### Assertions, each with its positive control

Every negative is paired with a control taken **through the same locator
shape, from the same root**, so "not found" can never mean "not rendered".
The list below is per role (both roles run the identical block):

| # | Positive control | Negative assertion |
|---|---|---|
| 1 | Workflow side-menu group renders and its treeitems are exactly `['Production']` | (same assertion — exhaustive: no review stage, no "Review Round N" entry) |
| 2 | `sideMenu.getByRole('link', {name: /production/i})` → count 1 (the name-matched-menu-entry mechanism works) | `sideMenu.getByRole('link', {name: /review/i})` → count 0 |
| 3 | Workflow column heading `Workflow: Production` visible | no heading matching `Workflow: (External )?Review` (spec Rule 2's round heading) |
| 4 | `Production Tasks & Discussions` heading visible; `Participants` side-column heading visible | no heading matching `Round N Status`, `Files for Review`, `Revisions Uploaded`, `Reviewers`, `Reviewers Suggested by Author`, `Review Tasks & Discussions`, `Recommendation`, `Notifications` (spec Rules 3–4, 11, 14) |
| 5 | `[data-cy="workflow-action-items"]` buttons are exactly `['Post the preprint', 'Decline Submission']` | (same assertion — exhaustive: no review decision in the action area) |
| 6 | `Decline Submission` found by exact-name button lookup over the whole modal → count 1 (the mechanism works) | count 0 for each of `Send for Review`, `Send to External Review`, `Send to Internal Review`, `Request Revisions`, `Accept Submission`, `Create New Review Round`, `Cancel Review Round`, `Recommend Revisions`, `Recommend Accept`, `Recommend Decline` (spec Rules 10–11) |

Plus two server-side assertions taken once (not per role):

| # | Positive control | Negative assertion |
|---|---|---|
| 7 | the seed POST **succeeds** with the review-free spec | the echoed `reviewRounds` is `[]` |
| 8 | (control = #7: the identical spec minus the review block builds) | the identical spec **plus** `reviewRounds: [{reviewers: []}]` is REFUSED — 400, `specKey: "reviewRounds"`, `"This application has no review stage, so reviewRounds cannot be seeded."` |

Assertion ordering matters and is deliberate: the positive control runs first
in each block, so the loaded-menu / loaded-panel state bounds every negative
that follows (the list analogue of the negative-mail rule, PRINCIPLES
multi-app convention 4).

---

## 2. Run results — GREEN TWICE

Both runs are fresh invocations with `--reporter=list` and `--output` under the
session scratchpad. Warm DB, warm `.auth` cache; workers left at the config
default (Playwright allocated 1 for a single-test file).

**Run 1** — `--output=.../scratchpad/pw-u26-run1`

```
Running 2 tests using 1 worker

  ✓  1 [setup] › lib/pkp/playwright/tests/bootstrap.setup.js:30:1 › the shared base context is seeded (75ms)
  ✓  2 [ops] › playwright/tests/review-stage-and-rounds.spec.js:193:1 › U26/13 a preprint server offers no review stage, no rounds and no review decision @regression (3.4s)

  2 passed (4.5s)
```

**Run 2** — `--output=.../scratchpad/pw-u26-run2`

```
Running 2 tests using 1 worker

  ✓  1 [setup] › lib/pkp/playwright/tests/bootstrap.setup.js:30:1 › the shared base context is seeded (73ms)
  ✓  2 [ops] › playwright/tests/review-stage-and-rounds.spec.js:193:1 › U26/13 a preprint server offers no review stage, no rounds and no review decision @regression (3.1s)

  2 passed (4.1s)
```

(The `setup` project row is the bootstrap probe dependency; it no-ops warm.)

---

## 3. Contradictions with the spec

**None.** Every claim scenario 13 and footnote `w` make about OPS held live on
the 8200 fleet, for both roles:

- side menu's Workflow group holds only "Production";
- no round panels, no "Reviewers" panel, no review decision anywhere;
- the only workflow actions offered are "Post the preprint" and
  "Decline Submission";
- the seeding layer refuses review rounds outright with a named key.

Two observations that **refine** rather than contradict the spec — offered for
the spec author, not blocking:

1. **Footnote `w` says "the only actions offered are 'Post the preprint' and
   'Decline Submission'".** True of the workflow *decision* area
   (`[data-cy="workflow-action-items"]`), which is what the sentence is about.
   The workflow *header* additionally offers "Preview", "Activity Log" and
   "Library" buttons, and the Participants side column offers "Assign". If the
   footnote is meant as an exhaustive on-screen inventory it should say
   "the only editorial decisions offered"; as a decision-roster statement it is
   exact. The test asserts the decision-area form, which is the defensible one.
2. **Scenario 13's positive control is cross-app** ("the same walk on a journal
   shows the Review stage") and cannot be executed from an OPS suite — a
   per-app suite drives one fleet. Under the per-app-suite ruling that control
   is the OJS/OMP U26 suites; the in-app per-assertion controls in §1 are what
   this test carries. Worth a one-line edit to scenario 13 so a future test
   author does not read it as an instruction to cross fleets. **Proposed
   wording:** *"Positive control: on the same screen, the Workflow menu renders
   with Production and the Production decision controls are offered (the
   cross-app control — the same walk on a journal showing the Review stage — is
   the OJS/OMP suites')."*

Neither is a permission finding, so no private-routing action was taken.

---

## 4. Shared-code gaps worked around

Nothing under `ops-main/lib/pkp` was edited. Gaps recorded instead:

1. **No shared workflow POM.** `lib/pkp/playwright/pages/` holds only
   `BasePage`, `LoginPage`, `DashboardPage`. The workflow-screen knowledge this
   test needs — the URL shape
   `/index.php/{context}/en/dashboard/editorial?workflowSubmissionId={id}`, the
   `[data-cy="active-modal"]` root, the `[data-cy="workflow-action-items"]`
   action area, and the PanelMenu `region`/`treeitem` shape of the stage menu —
   is inlined in a small app-local helper (`openWorkflow()`) inside the spec.
   That is deliberate under the skill's "build sharing machinery only when
   several specs demonstrate the need"; the OJS and OMP U26 suites will need
   the same three hooks, so a shared `WorkflowPage` POM (or at minimum a
   `workflowUrl()` helper on `BasePage`) becomes worth extracting **as soon as
   the second app-local U26 spec lands**. Flagging, not pre-building.
2. **Scenario core schema was sufficient** — no key was missing, so no
   extension and no `scenario-processor-audit.md` entry is due from this
   session. Worth recording as live truth for the schema doc: the OPS
   `reviewRounds` refusal is real and message-stable
   (`"This application has no review stage, so reviewRounds cannot be
   seeded."`, `specKey: "reviewRounds"`), and the submission echo carries
   `submissionId`, `reviewRounds: []`, `stageId: 5`, `sectionId`.
3. **`playwright/support/fixtures.js` (OPS) needed no new fixture.** `opsApi`
   covered seeding; no OPS-only feature fixture was added, since one spec is
   not "several".

---

## 5. Proposed content for files this session must not write

### `docs/product/PROGRESS.md` — proposed row/entry

> **U26 Review stage & rounds — OPS: DONE (absence test), 2026-07-27.**
> 1 test (budget: 1 — absence-only, feature not installed).
> `ops-main/playwright/tests/review-stage-and-rounds.spec.js` implements spec
> scenario 13 for Preprint Server Manager + Moderator, green twice.
> No contradictions; two spec refinements proposed in
> `.reports/u26/tests-ops.md` §3. No app-code change, no shared-harness change.

### `docs/e2e/app-changes.md` — proposed rows

**None.** No app-code change and no build blocker: the test runs against the
app as shipped.

### `docs/e2e/scenario-processor-audit.md` — proposed entries

**None.** No Processor was changed.
