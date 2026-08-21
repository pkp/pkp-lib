# Playwright Patterns

Conventions and accumulated pitfalls for writing specs, with the rationale so
you can judge edge cases. Everything here is live truth for the current
harness (rebuilt clean-room 2026-07-31) except items explicitly marked
**(recorded design)** — helpers that do not exist yet and return with the
feature suites; until then the named pattern is the design to follow.

Harness layout, env facts, and running: `harness.md`. Seeding and Mailpit:
`scenarios.md`. Identities: `users.md`.

## Locator priority

Pick the first that works:

1. **`getByRole`** — preferred: resilient, reflects accessibility, matches
   user intent. `page.getByRole('button', {name: 'Submit'})`
2. **`getByLabel`** — form fields with a visible label.
3. **Stable IDs** — where the form is id-anchored (login is the canonical
   case: `input#username`, `form#login button`).
4. **`data-cy` hooks** — legacy Cypress markers still in the DOM
   (`[data-cy="workflow-controls-right"]`). Fine when role/label are ambiguous
   on a complex Vue view.
5. **CSS** — last resort; wrap with a role or data attribute when possible.

Anti-patterns: `nth-child`/`:first-child` (breaks on reorders); long
class-name chains; `page.waitForTimeout(n)` (auto-wait exists; fixed timeouts
hide real races); `waitForLoadState('networkidle')` on Vue pages (they poll —
it may never resolve; wait on a visible landmark).

## Locator pitfalls

Each of these has bitten at least once.

1. **OJS tabs are `role="tab"`, not `button`.** Stable hook for top-level
   tabs: `#{name}-button` (`#review-button`, `#setup-button`).
2. **Nested tab groups**: top-level *Setup* vs Appearance → Setup are
   different tabs — outer via `#setup-button`, inner via the visible-tab role.
3. **Headlessui menus (More Actions)**: items are `role="menuitem"`, and the
   menu portals to the document root — scope to `page`, not the row.
4. **Side modals**: scope via `[data-cy="active-modal"]`; when stacked, filter
   by a distinctive inner element, not `.first()`/`.last()`.
5. **The side-modal outer wrapper reports `visibility: hidden`** (during open,
   and permanently on some wrappers) — anchor `toBeVisible()` on inner
   content, never the wrapper.
6. **The workflow page itself is a reka-ui dialog** — when it opens another
   modal both are `[role="dialog"]`; disambiguate by accessible name:
   `getByRole('dialog', {name: /Add Reviewer/i})`.
7. **Confirmation dialogs**: `[role="dialog"]:has-text(...)` or legacy
   `[data-cy="dialog"]`; button labels vary (OK/Yes/No) between reka-ui and
   jQuery UI.
8. **fbvElement ids are runtime-suffixed** (`$FBV_uniqId`) — select by
   `name=`, not `#id`.
9. **Legacy pkp jQuery grids**: row controls hide until `a.show_extras` is
   clicked (class flips to `hide_extras`); rows are
   `tr.gridRow#component-grid-...`.
10. **PkpButton accessible names include row context** — the Edit button in a
    mailables list is named `Edit Discussion (Production)`. Use a row-scoped
    regex.
11. **AJAX-loaded email templates** (decision Composer steps): wait for
    `.composer__loadingTemplateMask` to be gone before submitting, or the POST
    validates on an empty body. `EditorialWorkflowPage#awaitEmailTemplateLoaded`
    (recorded design) is the helper shape.
12. **fbv plupload uploads**: the native `<input type=file>` is `opacity: 0`
    under a styled button — `setInputFiles()` on the input works; clicking the
    button opens a real OS dialog.
13. **`[role="status"]:has-text("Saved")`** is the canonical form-save
    confirmation — wait on it before reloading or asserting persistence.
14. **`getByRole` name strings are substring matches** — `{name: 'View'}`
    matches "Assign Re**view**ers". Use `exact: true` or an anchored regex for
    short common words.

## Fixture selection

- Single default user → `test.use({user: 'editor.diana'})` at file or
  describe level; storage state is cached across runs.
- Multi-actor → the `asUser` fixture opens extra authenticated contexts;
  they auto-close at teardown (don't `ctx.close()` manually unless the test
  verifies a closed-session scenario).
- Anonymous → omit `user` — but see parallel lesson 8 before trusting a
  context to be anonymous.

## Waiting strategy

Playwright auto-waits on every interaction; explicit waits are only for
**arrival** (navigation or DOM transition completed). Animations are globally
disabled by the harness (see `harness.md`), so nothing waits on modal slides —
durations are 0.01ms, not 0, because presence helpers wait on
`animationend` and a true 0 can mean the event never fires.

- **Wait on a landmark, not the network**:
  `await expect(page.getByRole('heading', {name: 'Dashboard'})).toBeVisible()`.
- **Auth-style redirects**: `page.waitForURL(url => !url.pathname.includes('/login'),
  {waitUntil: 'commit'})` — the default `'load'` is fragile under parallel
  load (Vue dashboards fan out XHRs); `'commit'` fires on the URL change.
- **API-triggered updates**: arm `page.waitForResponse(...)` before the click,
  await it after. Prefer this over toast assertions (parallel lesson 2).
- **Legacy jQuery flows** (AjaxModal saves, Smarty grid refreshes, tab-handler
  clicks): call `waitForJQueryIdle(page)` from `playwright/support/legacy.js`
  (OJS app-local — the shared layer deliberately ships no jQuery helper;
  promote it when a second app suite needs it). It waits until jQuery is
  absent or `jQuery.active` reaches 0. No-op on Vue-only surfaces — prefer
  `waitForResponse` there. Symptom that points here: a spec passes at
  `--workers=1` but flakes at 2 with timeouts right after a legacy form save.

## Parallel-load lessons

The suite runs parallel by default; shared seed data is the unit of
contention.

1. `waitForURL` needs `waitUntil: 'commit'` (above).
2. **`/notification/fetchNotification` drains ALL pending notifications for a
   user** — two parallel tests as the same user race the toast queue. Assert
   on the save endpoint via `waitForResponse`, not toasts.
3. **`searchPhrase=` OR-joins on whitespace** — search by the tag alone, a
   single whitespace-free token, never `'Published article {tag}'`.
4. **Mailpit is shared across workers AND fleets.** Never `clearAll()` outside
   the serial infrastructure spec; scope every assertion by a unique throwaway
   recipient; pair absence claims with a positive control. Full rules and the
   `pkpMail` API: `scenarios.md` (the Mailpit home).
5. **`.auth/{user}.json` can go stale after impersonation flows**
   (`signInAs`/`signOutAs` migrate the session) — `ensureAuthStateFor` probes
   before reuse and re-logs in when needed; nothing for specs to do.
6. **Server-side outbound HTTP fails fast at the dead-port `[proxy]`**
   (config contract in `harness.md`). A test must never depend on the app
   reaching an external service; flows that fire outbound calls as side
   effects (e.g. ORCID jobs popped by a queue drain) fail fast, harmlessly.
7. **Nothing queued or scheduled runs on its own** (`task_runner`/`job_runner`
   Off). A spec needing a scheduled task invokes
   `php lib/pkp/tools/scheduler.php run`; one needing a queued job's side
   effect (job-dispatched mail: ORCID mailables, deposits) invokes
   `runJobs()` (`lib/pkp/playwright/support/jobs.js`). Both belong in the
   serial project ONLY: an explicit runner drains the SHARED queue and can pop
   other tests' pending jobs — even inside a `Mail::fake()` seeding window,
   committing side effects while swallowing the message. Never run either
   while parallel workers are seeding; the project chain guarantees this in
   normal runs.
8. **"Anonymous" contexts aren't anonymous under `test.use({user})`** —
   `browser.newContext()` inherits the file's storageState, and editors can
   *preview* unpublished articles (expected 404s become 200s). Pass an
   explicit empty state: `browser.newContext({storageState: {cookies: [],
   origins: []}})`.
9. **Bare front-end URLs 302 to the locale-prefixed form** on multilingual
   contexts (`/article/...` → `/en/article/...`) — `page.goto` hides it, but
   `maxRedirects: 0` probes must use the prefixed URL. **Inverted on
   single-locale journals** (most scratch journals): the bare URL serves
   directly and the `/en/` form 302s back — probe scratch journals bare,
   `publicknowledge` prefixed.
10. **Tags backing COUNT assertions need a per-run random component**
    (long-lived DBs accumulate leftovers), and **tags backing SEARCH must be
    single hyphenless alphanumeric tokens** — Postgres splits `edd7-w0-x` into
    tokens and the search OR-matches them, so a `-w0-` tag matches every other
    worker-0 submission.
11. **Component-router URLs are kebab-cased** (`saveSequence` →
    `.../save-sequence`) — `waitForResponse` predicates on the camelCase op
    name never match.
12. **Scratch journals auto-enrol `admin` as Manager**
    (`PKPContextService::add()` parity) — every participant/user count on a
    scratch journal is +1 over the seeded `users[]`.
13. **Legacy forms that embed a sub-grid contain that grid's own `<form>` and
    submit buttons** — e.g. subscription forms embed SubscriberSelect, whose
    nested `form#userSearchForm` puts a "Search" submit first in DOM order;
    `form.locator('button[type=submit]').first()` clicks Search and silently
    clears your picked radio. Click Save by accessible name.

## Tag conventions

Tests that seed via the scenario API need a unique tag for parallel isolation:

- **≤32 chars** (`journals.urlPath` is varchar(32); longer 500s).
- **Single hyphenless alphanumeric token** (lesson 10).
- Pattern: `{prefix}w{parallelIndex}{suffix}`, e.g. `subw0k3f9qa`; give the
  suffix a per-run random component whenever the tag backs a COUNT assertion.

## Test tags

Filter with `--grep @tagname`: `@smoke` (must-pass, every PR), `@regression`
(scheduled/nightly), `@slow` (opt-out locally), `@flaky` (quarantined).
Apply: `test('name', {tag: ['@smoke']}, async ({page}) => {...})`.

## Page Object Model

Inherit from `BasePage` (`lib/pkp/playwright/pages/BasePage.js`); POMs hold
`page` and locators as instance properties. Placement: shared mechanics →
`lib/pkp/playwright/pages/`; app-specific → `playwright/pages/`. OJS POMs
live today: `OrcidPages.js`, `ReviewStagePages.js`, `UserInvitationPages.js`.
**(recorded design)** `EditorialWorkflowPage.js` (decisions, Publication
side-nav, publish, galleys — helpers `clickDecision`,
`clickRequestRevisions({newRound})`, `awaitEmailTemplateLoaded`,
`recordDecision`, `publishCurrentPanel`, `addGalley`, `deleteGalley`),
`SubmissionWizardPage.js`, `IssuePage.js`.

## Decision flow

Button labels don't always match legacy Cypress names:

| Decision | Button label |
|---|---|
| sendExternalReview | `Send for Review` |
| acceptFromReview | `Accept Submission` |
| acceptInitial | `Accept and Skip Review` |
| sendToProduction | `Send To Production` |
| requestRevisions | `Request Revisions` |
| decline | `Decline` |

`Request Revisions` is the only primary decision that does NOT navigate
straight to `decision/record/{id}` — it first opens a side modal
(`WorkflowSelectRevisionFormModal`) with a PENDING_REVISIONS (default) vs
RESUBMIT (new round) radio; only after Next does the page navigate.
Decision-constant and round-status gotchas: `scenarios.md`.

## Data seeding

Prefer the API over the UI for setup; drive the UI only for what the test
actually exercises. Bootstrap (once per DB lifetime) is handled by the setup
project. Composite state → the scenario endpoints via
`pkpApi.createContext()`/`createSubmission()`; one-off mutations → the app's
api fixture. Full surface and quirks: `scenarios.md`. There is no cleanup
fixture — when you hit a TODO stub, flag it rather than inventing an
alternative.

## Things to avoid

- **Absolute database IDs** — use the ID the seeding call returned.
- **Mutating shared seed data** (`publicknowledge`, the 18 users — renames,
  role changes, flag changes). Need special attributes? Create a throwaway
  user in a scratch journal. No baseline account is `mustChangePassword`-
  flagged; `manager.maya` logs straight in.
- **Running `test:e2e:serve` alongside a Playwright run** (fights over the
  base port — see `harness.md`).
- **Committing `.auth/` files** (session cookies; gitignored — un-stage if
  you see one staged).

## UI realities learned the hard way

- **There is no `queries` table** (verified 2026-07-31): discussions live in
  `edit_tasks`; `Repo::submission()->submit()` auto-creates editorial tasks.
  Any older doc or SQL referencing `queries`/`query_participants` is stale.
- **Dashboard search commits on Enter only** (`Search.vue`
  `@keydown.enter.prevent`); `fill()` alone never filters. A committed search
  flips the dashboard to a cross-status "Search Results" view
  (`currentViewId=search`) that removes the in-page search box (the side-nav
  box owns the query there — scope by accessible name
  `/Search submissions, ID/`). To assert on a specific status view, assert on
  that view's own list response (`status[]=…`), not through search.
- **Paginated lists accumulate state across runs** on a long-lived DB — never
  assert presence on an unscoped first page; search by the test's tag first.
  Seeded drafts carry no `dateSubmitted` so they sort LAST in date-ordered
  lists.
- **Server-rendered TinyMCE values never reach the backing textarea.** No
  helper exists (deliberately); read the editor directly:
  `page.evaluate((id) => window.tinymce?.get(id)?.getContent(), fieldId)`.
- **The wizard Steps rail collapses when it overflows** (non-current pills
  1px-clipped; `force: true` clicks are silent no-ops). Use
  `SubmissionWizardPage.gotoStep()`/`expectStep()` (recorded design) — the
  pattern handles expansion, end-anchored name matching, and re-render-
  swallowed clicks.
- **`useFetch` tunnels DELETE and PUT via POST + `X-Http-Method-Override`;
  unauthorized API calls return 401**, not 403 — match `waitForResponse`
  method predicates and status assertions accordingly.
- **The reviewer dashboard endpoint (`_submissions/reviewerAssignments`)
  ignores `searchPhrase` AND pagination** — reviewer-side list assertions
  need scratch-journal scoping or a bounded full-list assertion.
- **The submission GET's `reviewAssignments` is a hand-rolled summary**
  (`statusId` + Y-m-d dates only — no `cancelled`/`declined`/`dateReminded`
  fields). Assert via `statusId` constants or the row's History modal.
- **Review files are grant-based**: seeded in-review submissions carry no
  review-round files; the Add Reviewer modal's file selection writes the
  `review_files` grant. Mirror that flow; don't expect seeded files to be
  reviewer-visible.
- **A real wizard submit fires `AssignEditors`** (auto-assigns the section's
  editors), so `participants` on submitted scenarios is additive; seeding
  `participants: []` WITHOUT `submitted` is what produces a genuine
  needs-editor state.
- **Reviewer-select copies the email template into TinyMCE client-side** — an
  uninitialized editor loses the body and the save 500s on a null message.
  Wait for the editor's `initialized` before selecting (fixed in
  `ReviewStagePages#addReviewer`; the 450ms modal slide used to mask it).

## Live-probe cookbook (spec verification)

Throwaway probes that verify spec claims against the running app; these idioms
cost half a session to rediscover.

- **Authenticate with Playwright request contexts, never bare curl**: log in
  via the real UI form, then fire probes through `context.request` — cookies
  ride along, and `page.evaluate(() => window.pkp?.currentUser?.csrfToken)`
  supplies the CSRF header for mutating calls. curl login is a trap:
  multilingual journals 302 `/login` → `/en/login`, so a naive scrape reads an
  empty page and every later request runs anonymous.
- **An anonymous XHR to a legacy grid op returns a plausible JSON denial**
  ("You don't currently have access to that stage…"), indistinguishable from a
  real role denial. NEVER trust a DENIED verdict without (a) proof the session
  is live (an API GET returning 200) and (b) a positive control — a plainly
  entitled actor running the SAME op and getting ALLOWED.
- **Legacy grid-op URLs**: `.../$$$call$$$/grid/<path>/<op-name>` with the op
  HYPHENATED (`read-review`, not `readReview`) and header `X-Requested-With:
  XMLHttpRequest` — camelCase names or a missing header → opaque 500s.
- **REST verbs vary per route** (`confirmReview` is PUT; a wrong verb can
  surface as a 500, not 405) — check the `Route::` registration before
  concluding anything from an error status.
- **Scenario seeding in probes**: users are minted ONLY by the context
  scenario's `users[]` (explicit `password` honored, else
  `username+username`); the submission scenario resolves usernames but never
  creates them. Multilingual fields must be locale maps (`{"en": …}`) — a
  bare string 400s.
- **Dual-role traps**: an author-editor probe needs a user genuinely enrolled
  in BOTH groups who is the submitter — a bare stage assignment without the
  global author role doesn't trip author checks; a same-user second
  `participants` entry rides on `build()`'s firstOr semantics (see
  `scenarios.md`).
