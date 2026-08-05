# U26 Review stage & rounds — probe report, Group V

**Frame**: QA verification of documented behavior on a local disposable test
install with seeded accounts. For each rule, confirm what the role's screen
offers and what the server does with the same request, and record where they
differ so the gap can be fixed.

**Run**: 2026-07-27, OJS fleet `http://127.0.0.1:8000` (live). Driver:
throwaway Playwright (chromium) scripts — real UI login, real clicks; state
built through the scenario endpoints (`/api/v1/_test/scenarios/*`) and through
the app's own decision wizard. Seeded `publicknowledge` was **not touched**;
everything below happened in two scratch journals created for this run
(`u26g5a`, `u26g5b`) with throwaway users. Mailpit was not read and not
cleared.

Scratch fixtures used:

| Journal | Setting of note | Users (roles) |
|---|---|---|
| `u26g5a` | `numReviewsPerSubmission` set to **2** via the UI | `u26g5mgr` (manager), `u26g5auth` (author), `u26g5rev1`/`u26g5rev2` (externalReviewer) |
| `u26g5b` | minimum reviews left at default `0` — negative control | `u26g5bmgr` (manager), `u26g5bauth` (author), `u26g5brev`/`u26g5brev2` (externalReviewer) |

Locator used for every round-status observation below (both items):

```js
page.locator('div.border.border-light.p-4').filter({has: page.locator('h3')})
// heading read from .locator('h3'); lines read from .locator('p').allInnerTexts()
```

That block is `WorkflowSubmissionStatus.vue`; it renders an optional `body1`
line above the `body` line, so "the paragraphs, in order" is the faithful
reading of what the screen shows.

---

## Item 15 — Minimum-reviews setting (Rule 9, footnote u)

### 15a. Where the setting lives, and what it is called — **CLAIM**

Driven: OJS, journal manager `u26g5mgr` (enrolled `manager` only), scratch
journal `u26g5a`.

- Screen: `/index.php/u26g5a/management/settings/workflow`; `h1` reads
  **"Workflow Settings"**.
- Outer tabs, read with `page.getByRole('tab')` before and after selecting
  Review: **Submission · Review · Publisher Library · Emails · Tasks and
  Discussions**.
- Clicking `page.locator('#review-button')` selects **Review** and exposes its
  inner tabs: **Setup · Reviewer Guidance · Review Forms · Reviewer
  Recommendations**. `[role="tab"][aria-selected="true"]` reported `Setup` as
  the selected inner tab — the field is on the tab that opens by default, no
  extra click needed.
- The control: `input#reviewSetup-numReviewsPerSubmission-control`, visible
  (`isVisible() === true`).
  - **on-screen label: "Minimum Confirmed Reviews Required"**
  - **on-screen description: "Minimum number of confirmed reviews required for
    a submission"**
  - default value on a fresh journal: `0`
  - it sits in the first (unlabelled) group of the **Review Setup** form
    (`reviewSetup`), the same group as "Default Review Mode" — there is no
    group heading of its own.
- Saving: the Save button inside that form
  (`page.locator('form').filter({has: field}).getByRole('button', {name: 'Save'})`
  — the form's only button) issued `POST /index.php/u26g5a/api/v1/contexts/14`
  → **200**. After a full reload and re-selecting Review, the field read back
  `2`. Setting is journal-level and persists.

**Ownership flag (asked for by the item).** The hosting screen is Settings →
Workflow → **Review → Setup**, i.e. the Review Setup form. FEATURE-MAP U29
("Review setup & review forms — *A manager configures how review runs: mode,
deadlines, reminders, guidance, review forms, recommendation options*") owns
that screen. **Recommendation**: the spec's Settings bullet should keep the
*effect* (Rule 9's status-line behavior, which is U26's) but hand the
setting's home surface to U29, the same way the Reviewer-suggestions bullet
already defers to U31 and the Review-method bullet to U29. Footnote `u`'s
"The setting's admin surface was not located in this pass — probe item" can be
replaced with the label + path above.

*Context (not promotable)*: the Submission tab's inner tabs, seen while it was
still the default selection, were Disable Submissions · Author Guidance ·
Metadata · Components · Contributor Roles.

### 15b. What the status line does once the minimum is 2 — **CLAIM**

Driven: OJS, `u26g5a` (minimum = 2), deciding editor `u26g5mgr`, workflow
screen `…/dashboard/editorial?workflowSubmissionId=<id>`, Review Round 1
selected. Reviews were made *confirmed* the real way: the reviewer completed
the reviewer wizard and submitted, then the editor opened the row's **"Read
Review"** action and pressed **Confirm** (which posts
`…/$$$call$$$/grid/users/reviewer/reviewer-grid/review-read`, observed 200).

Submission `u26g5s1` (id 36), two reviewers:

| Round state | Heading | Paragraphs, in order |
|---|---|---|
| 2 reviewers accepted, no review submitted | `Round 1 Status` | 1. "Minimum number of confirmed reviews required: 2." 2. "Awaiting responses from reviewers." |
| 1 review submitted, not yet confirmed | `Round 1 Status` | 1. "Minimum number of confirmed reviews required: 2." 2. "New reviews have been submitted." |
| 1 review confirmed, 1 reviewer still out | `Round 1 Status` | 1. "Minimum number of confirmed reviews required: 2." 2. "Awaiting responses from reviewers." |
| both reviews confirmed (minimum met) | `Round 1 Status` | 1. "Minimum number of confirmed reviews required: 2." 2. "Minimum required number of reviews have been confirmed. A decision is needed." |

Submission `u26g5s2` (id 38), **one** reviewer, review confirmed — the round
would otherwise read "All reviews are confirmed and a decision is needed.":

| Round state | Heading | Paragraphs, in order |
|---|---|---|
| every review confirmed but below the minimum | `Round 1 Status` | 1. "Minimum number of confirmed reviews required: 2." — **and nothing else** |

Negative control, journal `u26g5b` with the setting left at `0`: the same
block renders a single paragraph and no prompt line (observed as
"Awaiting responses from reviewers." on id 44 and "Waiting for reviewers to be
assigned." on id 39 during item 17's runs).

**Verdict on Rule 9.** The rule's "*the status line is replaced by a prompt to
secure that many confirmed reviews until the minimum is met*" is only
half-right, and the half it gets wrong is the common case:

- In every state **except** all-reviews-confirmed, the prompt is **added as a
  first line above** the ordinary computed status, which stays visible.
- Replacement happens in exactly one state: when the round would say "All
  reviews are confirmed and a decision is needed." but the count is below the
  minimum, the ordinary line is dropped and only the prompt remains.
- Once the minimum **is** met, the prompt line stays and the ordinary status
  is replaced by a distinct line, "Minimum required number of reviews have
  been confirmed. A decision is needed." — a status text the spec's Rule 6
  table does not list at all.

Proposed replacement for Rule 9 (for the spec author to fold in):

> 9. When the journal sets a minimum number of confirmed reviews per
>    submission (Settings, U29), the round adds a first line above its status:
>    "Minimum number of confirmed reviews required: N." While the minimum is
>    unmet the ordinary status line continues below it, except when every
>    review in the round is already confirmed — then the ordinary line is
>    dropped and only the prompt shows. Once N reviews are confirmed the round
>    reads "Minimum required number of reviews have been confirmed. A decision
>    is needed." beneath the prompt.

The two extra status texts belong in Rule 6's table as a setting-gated pair.

*Context (not promotable)*: the editor's Read Review modal (the confirm path
used to build state) carries, in this order, a completed date, "Recommendation:
…", "Reviewer Comments / For author and editor / For editor", a Reviewer Files
grid, a "Recommendation" select, a "Reviewer rating" select described as "not
shared with the reviewer", and Cancel / **Confirm**. The reviewer-row status
column read "Request Accepted" → "Review Submitted" → (after Confirm) the row
offered "Thank Reviewer" and "Revert Decision". Those are U27's surfaces.

---

## Item 17 — Round status after returning from Copyediting (Rule 18, A4)

Driven: OJS, journal `u26g5b` (minimum reviews at default `0`, so nothing
masks the status line), editor `u26g5bmgr` (manager).

### 17a. The back-out control — **CLAIM (incidental to this item, but observed)**

On the Copyediting stage of the workflow screen the decision button is labelled
exactly **"Move to Review"**
(`page.getByRole('button', {name: 'Move to Review', exact: true})`, alongside
"Send To Production"). Clicking it navigates to
`/index.php/u26g5b/decision/record/<id>?decision=30&ret=…`. The wizard is a
single step (`h1` "Move to Review", step "Notify Authors") committed by
**"Record Decision"**. Ownership of that button is U32/U34; recorded here only
so the round-status observations are reproducible.

### 17b. The round status text immediately after the return — **CLAIM**

Four round shapes were taken through `sendExternalReview` → `Accept
Submission` → `Move to Review`. In every case the stored stamp landed
(`select status from review_rounds` = `16`,
`REVIEW_ROUND_STATUS_RETURNED_TO_REVIEW`); what the screen shows differs:

| # | Round 1 contains | Stored `review_rounds.status` | Paragraph shown under "Round 1 Status" |
|---|---|---|---|
| (a1) | **no review assignments at all** | 16 | **"Waiting for reviewers to be assigned."** |
| (a2) | one assignment, **declined** | 16 | **"Returned back to review."** |
| (b) | one **confirmed** review (submitted by the reviewer, Confirmed by the editor) | 16 | **"Returned back to review."** |
| (c) | one accepted review still **in flight** | 16 | **"Awaiting responses from reviewers."** |

(a1) was submission id 39 — its whole decision chain was recorded through the
scenario endpoint's real decision service; (a2) id 42, (b) id 40 and (c) id 44
were seeded with their reviewers first and then driven through the Accept and
Move to Review wizards in the UI, because a reviewer added *after* a decision
re-stamps the round.

**Verdict on Rule 18 / A4.**

- **Rule 18 holds as written** — the back-out reactivates the last round, adds
  no round, and stamps it "Returned back to review.". Confirmed on the stored
  status in all four shapes.
- **A4's stated condition is wrong in both directions.** A4 says the stamp
  "only persists when the round has no active reviewers left" and that "any
  live reviewer activity in that round recomputes the line".
  - A round with **no reviewers at all** — the most literal reading of "no
    active reviewers left" — is precisely where the stamp does **not** show:
    the empty-round check runs first and the line reads "Waiting for reviewers
    to be assigned." (case a1).
  - A round holding a **confirmed** review — indisputably reviewer activity —
    shows the stamp perfectly well (case b).
- The behavior actually observed: **the stamp shows when the round has at
  least one review assignment and none of them is still in play** (each one
  declined, cancelled, or confirmed). Anything awaiting a response, accepted,
  overdue, or submitted-but-unconfirmed wins over the stamp; an empty round
  loses to the waiting-for-reviewers line.

Proposed rewrite of A4's symptom line and body (for the spec author):

> **A4 — "Returned back to review." is often outranked** · ❓ · minor.
> A submission sent back from Copyediting stamps its last round "Returned back
> to review.", and the stamp is stored, but the round recomputes its line on
> every display. The stamp only surfaces when the round holds at least one
> review assignment and every one of them is settled (declined, cancelled, or
> confirmed): a reviewer still in play outranks it with an activity status,
> and a round that never had a reviewer outranks it with "Waiting for
> reviewers to be assigned." Question: which line should a returned round
> show? Lean: as-built is acceptable for the activity cases, but the
> empty-round case reads as a regression to the reader — the round is not
> waiting for reviewers, it was just returned. Basis: live probe 2026-07-27
> (OJS), four round shapes.

Register-table row to match: `| A4 | "Returned back to review." is outranked
by activity statuses and by the empty-round line | ❓ | minor | — |`.

*Context (not promotable)*: the workflow side menu on these submissions read
Workflow · Submission · Review · Review Round 1 · Copyediting · Production ·
Publication (plus the Publication sub-items); the Copyediting stage heading
rendered as "WORKFLOW: COPYEDITING" (uppercased by CSS); the Accept Submission
wizard ran Notify Authors → (Notify Reviewers, only when the round had a
reviewer) → Select Files → Record Decision.

---

## Proposed content for other files (not written by this agent)

Nothing for PROGRESS.md, the atlas, or `docs/e2e/app-changes.md` — both items
land entirely in the spec:

1. Rule 9 rewritten as in 15b, and the two setting-gated status texts added to
   Rule 6's table.
2. The Settings bullet "Minimum reviews per submission" gains its located home
   — Settings → Workflow → Review → Setup, label "Minimum Confirmed Reviews
   Required" — and a note that the surface is U29's, mirroring the Reviewer
   suggestions (U31) and Review method (U29) bullets. Footnote `u`'s
   "not located in this pass — probe item" can be retired.
3. A4 rewritten as in 17b; Rule 18 unchanged.
