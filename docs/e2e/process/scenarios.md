# Scenario API & Mailpit

The `/api/v1/_test/*` endpoints assemble realistic state in one POST — the
canonical alternative to driving the UI for setup. This file has two strata:
**LIVE** (the step-2 core, rebuilt 2026-07-31) and **recorded designs** —
pre-reset field shapes the API grows back into per feature. Growth rule
(PRINCIPLES): extend a builder only when multiple tests need the same state,
and every builder change needs a parity entry in
`parity-ledger.md` before it merges.

## LIVE surface

Routes are site-wide (`/index.php/index/api/v1/_test/…`), gated by
`X-Test-Key` (env `TEST_API_KEY`; the namespace 404s when the server lacks the
env var, 403s on a wrong header). **Validation is the strict `Spec` reader**
(`lib/pkp/classes/testing/Spec.php`) — there is no JSON-schema layer; every
key a builder does not consume → 400 with a dotted `specKey`, so the builders
themselves are the authoritative field list.

- **`POST/GET bootstrap`** — declarative base seed: context +
  sections/series + categories (nested via `children`) + issues (OJS) + users
  with roles and `sections`/`series` sub-editor assignments. Warm calls no-op
  (`{seeded: true, warm: true}`). The FIRST declared section RENAMES the app's
  hook-created default section (OJS "Articles", OPS "Preprints") instead of
  duplicating it.
- **`POST scenarios/context`** — scratch context: `tag*` (≤32 chars, single
  hyphenless alphanumeric token — see `patterns.md` tag conventions; defaults
  the urlPath), `context` object (path, name, acronym, description, locales,
  contact, enabled), `users[]` throwaways. Setting passthroughs return per
  feature; live today: `orcid` (settings-tab state incl. encrypted secret —
  see the parity ledger's 2026-08-07 rows).
- **`POST scenarios/submission`** — `tag*`, `context*` (urlPath),
  `submitter*` (username), `title` (default "Submission {tag}"), `abstract`
  (defaulted — abstract-requiring sections need one; pass your own to
  override), `locale`, `submitted` (default true; EXPLICIT false = a true
  wizard-resumable draft at parity: no `dateSubmitted`, `submissionProgress`
  set, author `canChangeMetadata` — it appears in the author's Incomplete
  list), `decisions[]` (real decision names, app-resolved — an unknown name
  400s listing the app's roster), `reviewRounds[].reviewers[]`
  (`{username, status: invited|accepted|declined}` + per-reviewer
  `status`/`method`/`responseDueDate`/`reviewDueDate`), `published`, and the
  `author: {orcid, orcidIsVerified}` passthrough (pre-verified ORCID without
  the OAuth flow). Overlays: OJS `section` (abbrev; defaults to the first
  section) / `issue` ({volume, number, year} matching a seeded issue); OMP
  `series` (path) / `seriesPosition` + per-round `stage: internal|external`;
  OPS `section` — and `reviewRounds` is REJECTED (no review stage).

Facts tests rely on (parity-verified — see the parity ledger):

- Every build runs under `Mail::fake()` inside one DB transaction — failed
  builds roll back; seeding-side mail never reaches Mailpit, only test-action
  mail does.
- Submitted seeds carry the submit endpoint's notifications and the author as
  publication primary contact.
- Every seeded submission gets a real Article Text file — but NO review-round
  files (review files are grant-based; see `patterns.md`).
- The submission scenario resolves usernames but never creates them — users
  are minted only by the context scenario's `users[]` (explicit `password`
  honored, else `username+username`).
- Multilingual fields (`name`, section `title`/`abbrev`, publication `title`)
  must be locale maps (`{"en": …}`); a bare string 400s.

Implementation: shared base `lib/pkp/api/v1/_test/PKPTestController.php` +
builders in `lib/pkp/classes/testing/` (`PKPBootstrapSeeder`,
`scenario/PKPContextScenarioBuilder`, `scenario/PKPSubmissionScenarioBuilder`,
`Spec`, `UserSeeder`, `ContextFactory`); each app subclasses them under
`api/v1/_test/` and `classes/testing/`.

## Recorded designs (not live — field shapes to grow back into)

Nothing below exists in the rebuilt builders. When a feature needs one of
these states, implement the field at the recorded shape (adjusting to live
code) and add its parity row to the parity ledger.

Submission scenario, top level: `commentsForEditor` (fires the editors
discussion via `SubmissionSubmitted`; discussions live in `edit_tasks`),
`reviewerSuggestions[]` ({givenName, familyName, email, affiliation?,
suggestionReason?} — plain strings wrapped under the spec locale),
`userComments[]` ({user, text, approved?} — requires a published
publication), `metrics` (OJS-only: {views?, downloads?, months?} — compiled
`metrics_submission` rows spread backwards from the current month).

Per publication: `galleys[]` ({label, locale?, file?, urlRemote?} — `file` is
a basename under `lib/pkp/playwright/fixtures/files/`; the two are mutually
exclusive), `metadata.datePublished` (survives `published: true`; without it
publish stamps today), `mediaFiles[]` ({variantType*: 'web'|'high_resolution',
file?, name?, genre?, group?} — `group` links pairs via `VariantGroup`, first
entry primary).

Per reviewer: `reviewForm: "<title>"` — attaches an existing active review
form by exact title, as the Add Reviewer form does (seed it first via the
context scenario's `reviewForms[]`).

Per decision: `toAuthor` / `toReviewers` (soft-fails when the decision type
lacks that action) / `toEditor` (internal `submission_comments` row,
`viewable=0`).

Context scenario passthroughs: `copyrightNotice`, `enablePublicComments`,
`submitWithCategories`, `publishingMode`, `enableAnnouncements`; DOI
(`enableDois`, `doiPrefix`, `doiVersioning`, `enabledDoiTypes`,
`registrationAgency`, `doiCreationTime`); metadata modes (`keywords`,
`citations`); review setup (`defaultReviewMode`, `reviewerSuggestionEnabled`,
`numWeeksPerResponse/Review`, reminder thresholds); ISSNs; `plugins:
{pluginName: {enabled, settings}}` (keys are `LazyLoadPlugin::getName()` —
lowercased class name); `reviewForms[]`; OJS-only `issues[]` (incl.
`accessStatus`) and `subscriptions[]` (`'expired'` seeds an ACTIVE-status row
with a past `date_end` — a state unreachable through the create form).

Spec-builder fixtures (`playwright/fixtures/scenarios/`) are also recorded:
`submission-draft`, `submission-in-review`, `submission-in-round-2`,
`submission-published` — until they return, specs POST via
`pkpApi.createContext()`/`createSubmission()` directly. There is deliberately
no typed scenario client; build one only when a feature suite demonstrates
the need.

The baseline `publicknowledge` journal is seeded with enriched defaults
(`playwright/fixtures/bootstrap.js`): announcements, public comments,
categories-in-wizard, keywords/citations on request, reviewer suggestions,
DOIs auto-assigned on publish, CSL plugin, double-anonymous review with
deadlines. Tests needing any of these OFF use a scratch journal.

## Decision behaviour worth knowing

- **Decision constants are easy to misread**: `Decision::PENDING_REVISIONS = 4`
  (not 1); `ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED = 1` (not
  8). Grep before quoting.
- **`requestRevisions` followed by `newExternalRound` overwrites round 1's
  status** (reset to `PENDING_REVIEWERS` by `runAdditionalActions`) — read
  "round 1 closed with revisions" from decision history, not
  `review_rounds.status`.
- **`NewExternalReviewRound` has 2 wizard steps** (notifyAuthors +
  PromoteFiles), not 1.
- **`Repo::stageAssignment()->build()` uses `firstOr`** — re-assigning the
  same user/role silently drops new flags (e.g. `canChangeMetadata`). If a
  participant needs different flags from the auto-author assignment, route the
  submitter through a different user.

## Mailpit

`pkpMail` (fixture; `lib/pkp/playwright/support/mail.js`) wraps Mailpit's HTTP
API. Mailpit is ONE shared instance across every worker and all three fleets.

**There are no Mailpit tags on this install** (verified 2026-07-29:
`GET /api/v1/tags` → `[]`; nothing sets `X-Tags`). The executable scoping rule
is: **a unique throwaway recipient address that names the app and the test**
(`u53top-omp@mail.test`). `find()`'s `contains` is a *content marker* — a
substring searched in subject/body, useful when the test controls some text in
the message — never a substitute for the recipient scope. (Note the word
"tag" in this doc set otherwise means the seed tag from `patterns.md` tag
conventions — a different thing.)

- **`find({to, contains, subject?, timeoutMs?, poll?})`** — THE canonical
  assertion: polls Mailpit search scoped by recipient + content marker; throws
  on unscoped use.
- **`expectNone({to, contains, afterControl: {to, contains}})`** — negative
  assertion done right: waits for the control message (bounding the wait),
  then asserts zero matches. Every absence claim needs a positive control.
- `inboxFor(email)` / `latestTo(email)` — polling reads for one recipient;
  `latestTo` can still race two mails to the SAME recipient — prefer `find`.
- `messageCount()` — total messages (useful to assert `Mail::fake()`
  suppressed all seeding mail).
- `fullMessage(id)`, `extractLink(html, linkText)` — body access and
  click-the-link flows.
- **`clearAll()`** — permitted ONLY in the serial infrastructure spec; never
  from a parallel spec.

Local: `brew services start mailpit`; URL via `MAILPIT_URL`
(default `http://127.0.0.1:8025`).
