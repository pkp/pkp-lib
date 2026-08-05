# Step 2 · Stage C — wiring OMP into the rebuilt Playwright harness

**Date:** 2026-07-27 · **Repo:** `omp-main` (branch `e2e_ng`), submodule at `a11e2b0fa7`
**Frame:** QA test-infrastructure work on a local disposable test install with seeded
fixture accounts. Nothing here touches real users, real data or third-party systems.
**Status:** built and verified end-to-end against a freshly installed `omp_test`.
Nothing committed, nothing pushed. PROGRESS / atlas / app-changes untouched (proposed
rows below).

**The shared layer was not modified.** `git -C lib/pkp status` is clean at
`a11e2b0fa7`; the shared `bootstrap.setup.js` and `login.spec.js` pass against OMP
UNMODIFIED, which is the acceptance test of the app-neutral design.

---

## 1. What was built (all in `omp-main`)

| File | Lines | Role |
|---|---|---|
| `api/v1/_test/index.php` | 47 | Dispatcher. 404s and exits when the gate is closed. |
| `api/v1/_test/PressScenarioController.php` | 309 | OMP context overlay: `series[]`, `users[].series`, and the press-only settings. |
| `api/v1/_test/SubmissionScenarioController.php` | 297 | OMP submission overlay: `series` / `seriesPosition`, and the **internal/external** review-stage handling. |
| `api/v1/_test/TestBootstrapController.php` | 37 | `extends PressScenarioController` + `use BootstrapRoutes`. |
| `tools/installTest.php` | 498 | Ported from OJS verbatim except the OAI repository id; includes the §11 self-healing three-state probe and the non-zero exit. |
| `tools/testServer.sh` | 60 | Ported from OJS; default port 8100. |
| `playwright/playwright.config.js` | 23 | `definePkpConfig({appName:'omp', basePort: 8100})`. |
| `playwright/support/app.context.js` | 121 | Capabilities (APP-GLOSSARY §2, OMP column, verbatim), `vocab`, `seed.actors` / `seed.series`, `bootstrapPayload`. |
| `playwright/support/fixtures.js` | 35 | Extends the base test; today only the `ompApi` alias. |
| `playwright/fixtures/bootstrap.js` | 233 | The base seed as data: press, series, categories, press settings, 17 seeded users. |
| `playwright/tests/README.md`, `playwright/tests/serial/README.md` | — | Placement rules for the (still empty) OMP suite and its serial project. |

Plus `.env.playwright.example`, the nine `test:e2e:*` npm scripts, `@playwright/test` as a
devDependency (and its four `package-lock.json` entries), five `.gitignore` entries, and a
local gitignored `config.test.inc.php`.

---

## 2. The OMP roster and the base press

### 2.1 Roster — the shared 18, enrolled OMP's way

`lib/pkp/playwright/data/users.js` says in its own header that it "describes WHO exists"
and that enrolment "is the app's business… the role vocabulary differs per app (OPS has
no reviewer group, **OMP splits reviewers**)". So the roster is reused verbatim — same
usernames, same doubled-username passwords, same unique first names, same `role.firstname`
shape — and `playwright/fixtures/bootstrap.js` carries the OMP `enrolments` table:

| Username | OMP user-group key | OMP label | Series |
|---|---|---|---|
| `manager.maya` | `manager` | Press manager | — |
| `editor.diana` | `editor` | Press editor (ROLE_ID_MANAGER, **not** sub-editor) | monographs, textbooks |
| `sectioneditor.ana` | `sectionEditor` | **Series editor** | monographs |
| `sectioneditor.ravi` | `sectionEditor` | Series editor | textbooks |
| `sectioneditor.omar` | `sectionEditor` | Series editor | monographs |
| `reviewer.julia` | `externalReviewer` | External Reviewer (stage 3) | — |
| `reviewer.paul` | `externalReviewer` | External Reviewer (stage 3) | — |
| `reviewer.amara` | **`internalReviewer`** | Internal Reviewer (stage 2) | — |
| `reviewer.adam` | **`internalReviewer`** | Internal Reviewer (stage 2) | — |
| `copyeditor.carla`, `copyeditor.sam` | `copyeditor` | Copyeditor | — |
| `layouteditor.leo` | `layoutEditor` | Layout Editor | — |
| `proofreader.pia` | `proofreader` | Proofreader | — |
| `author.alex`, `author.bea` | `author` | Author | — |
| `assistant.rita` | `funding` | Funding coordinator — stages **1, 2 and 3**, so in OMP it reaches BOTH review stages | — |
| `reader.rosa` | `reader` | Reader | — |

`admin` is created by the installer and enrolled as press manager by the context service.

**The reviewer split is the OMP divergence most likely to bite.** OMP ships two reviewer
groups, `internalReviewer` (stage 2 only) and `externalReviewer` (stage 3 only), and a
reviewer in the wrong one simply cannot be assigned in the other stage. Julia and Paul are
external, Amara and Adam internal, and **nobody holds both on purpose** — a reviewer in
both groups makes every "can this person be assigned here?" assertion vacuous.

`seed.actors` keeps every OJS archetype key (so shared code that resolves
`actors.reviewer` works unchanged and gets an EXTERNAL reviewer, the stage every
review-capable app has), maps `subscriptionManager` → `null`, and adds four OMP-only
aliases so an OMP spec can say what it means: `externalReviewer`, `externalReviewer2`,
`internalReviewer`, `internalReviewer2`.

### 2.2 The base press

`Public Knowledge Press` (`PKP`) at path `publicknowledge`, `en` + `fr_CA`, contact and
support addresses set (a context with no contact address cannot accept a submission —
stage A's §8.2), double-anonymous review with 4-week deadlines, announcements and public
comments on, keywords and citations requested, the same seven-category nested tree as OJS.

Press-only settings the OMP overlay declares and the seed uses: `publisher`, `location`,
`codeType`/`codeValue` (ONIX), `displayNewReleases`, `displayFeaturedBooks`,
`catalogSortOption`, `restrictMonographAccess`, and `internalReviewGuidelines` — the last
being OMP-unique, the Internal Review stage's own guidance field with no OJS counterpart.
`reviewerSuggestionEnabled` is declared but deliberately left at its default (turning it
on adds a submission-wizard step).

### 2.3 Seeded series

**Creating a press creates NO series** — unlike OJS, whose context service builds a
default section, `APP\services\ContextService::afterAddContext()` adds only contributor
roles. So both series below are genuinely created by the seed, and the OMP overlay has no
match-and-edit branch.

| path | title | flags |
|---|---|---|
| `monographs` | Monographs | `featured: true` (the catalog's Featured block needs something featured), `sortOption: datePublished-DESC` |
| `textbooks` | Textbooks | `editorRestricted: true`, `onlineIssn`/`printIssn` set |

`textbooks` is editor-restricted on purpose: authors cannot choose it in the wizard, which
is a real OMP configuration and the base seed's one asymmetry between the two series.
Seed submissions into `monographs` unless the test is about the restriction itself.

**OMP series have no `abbrev`.** `schemas/section.json` in `omp-main` is titled "A press
series" and replaces OJS's `abbrev` with `path`, which is unique per press
(`series_path` index) and is what the catalog addresses a series by. `path` is therefore
this overlay's identifier everywhere — in `series[]`, in `users[].series`, and in the
submission's `series` key.

---

## 3. Internal vs External Review — the design decision

The design record (PRINCIPLES "Scenario-endpoint design record" §5) requires an
"internal/external key on review rounds". Two shared-layer facts shaped how that was
delivered **without touching `lib/pkp`**:

1. **The shared schema closes `reviewRounds[]` with `additionalProperties: false`** and
   declares only `reviewers`. `SpecValidator::withOverlay()` merges overlay properties
   over the core schema with `array_merge`, so an app that redeclares `reviewRounds`
   REPLACES the shared definition. OMP's `schemaOverlayProperties()` therefore restates
   the property with an added `stage: internal|external` (default `external`). The rest of
   the shape is kept byte-identical on purpose.
2. **`PKPSubmissionScenarioController::reviewStageId()` takes the LAST entry of
   `Application::getReviewStages()`** — and OMP declares that roster
   `[EXTERNAL_REVIEW, INTERNAL_REVIEW]`, **external first**. The shared heuristic would
   therefore silently resolve to Internal Review on OMP. The subclass overrides
   `reviewStageId()` to answer from the stage the round asked for, so the roster's
   ordering decides nothing. (See §7.1 — this is worth a shared-layer look.)

`applyReviewRounds()` is overridden to group the round specs by the stage they name and
run the shared walk once per group, **internal first** — which is also the order the
workflow moves in, so the external group's promoting decision correctly finds a submission
already sitting in Internal Review when a spec asks for both. The response is re-keyed by
the spec's own position, so it lines up with the request even though the two stages were
built separately, and each entry echoes its `stage`.

The promoting decision follows from where the submission IS, exactly as it does for an
editor choosing from the decision menu:

| From | To | Decision recorded |
|---|---|---|
| Submission (1) | Internal Review (2) | `sendInternalReview` |
| Submission (1) | External Review (3) | `skipInternalReview` |
| Internal Review (2) | External Review (3) | `sendExternalReview` |

All three are real entries in `APP\decision\Repository::getDecisionTypes()`; nothing is
hard-coded beyond OMP's own stage constants, which an app subclass is entitled to name.

One consequence had to be handled explicitly. The shared builder stamps a round's status
as soon as that round's reviewers are in place, but on OMP a LATER group still changes an
EARLIER one: recording `sendExternalReview` sets the internal round it was taken in to
`REVIEW_ROUND_STATUS_ACCEPTED`. A both-stages seed therefore echoed the internal round
with a status the database no longer held (9, then 4). `withFinalRoundStatuses()` re-reads
every echoed round from `ReviewRoundDAO` once the whole build is done — read it back
rather than reason about it. Verified: echo and database now agree (`internal round 1
status 4`, `external round 1 status 7`).

---

## 4. Verification (everything below was run)

### 4.1 The gate — three checks plus a control

```
GATE (a) correct key                200   {"seeded":false,"contextId":null}
GATE (b) missing key                403   {"error":"testApi.403.invalidKey", …}
GATE (b2) wrong key                 403   {"error":"testApi.403.invalidKey", …}
GATE (c) server started with NO TEST_API_KEY, correct header sent:
                                    404   {"error":"api.404.endpointNotFound", …}
   control: the same server answers / with 302 — the app is up, the namespace is not.
```

### 4.2 Cold path: an EMPTY database to a seeded press in one command

`omp_test` dropped and recreated with `psql` (0 tables), `playwright/.auth` and the files
directory removed, `config.test.inc.php` still saying `installed = On` — the state a new
contributor or a CI job starts from. Then **only** `npm run test:e2e:setup`, no
`installTest.php` by hand:

```
$ npm run test:e2e:setup
The database named in …/config.test.inc.php is empty; set installed = Off so the
application can boot. The installer restores it when it finishes.
… migrations … Installer::createData, createConfig, addPluginVersions,
                installDefaultNavigationMenus, updateRorRegistryDataset, downloadIPGeoDB
Successfully installed version 3.6.0.0
Restored installed=On, base_url=http://127.0.0.1:8100 and allowed_hosts in …
  ✓ [setup] the shared base context is seeded (1.3m)        1 passed (1.4m)

installed = On
presses 1 · series 2 · users 18 · categories 7 · subeditor rows 5 · submissions 0
```

Stage A's §11 self-healing three-state probe works unchanged on OMP: the pre-boot block
flipped `installed` to Off, the installer put it back.

Warm re-run — the GET probe and nothing else:

```
$ npm run test:e2e:setup
  ✓ [setup] the shared base context is seeded (349ms)       1 passed (1.1s)   real 0m1.6s
```

Full chain and the (still empty) app project:

```
$ npm run test:e2e        →  3 passed (3.8s)     setup → shared → omp → omp-serial
$ npm run test:e2e:omp    →  1 passed (950ms)    --pass-with-no-tests, setup as dependency
```

The reset tool forces a cold bootstrap, as the acceptance list requires:

```
$ npm run test:e2e:reset
Recreated database 'omp_test'. … Successfully installed version 3.6.0.0
Restored installed=On, base_url=http://127.0.0.1:8100 and allowed_hosts in …
Cleared cached storage states in …/playwright/.auth
Done. The next `npm run test:e2e:setup` will seed from cold.
exit=0        presses 0 · .auth files 0

$ npm run test:e2e
  ✓ [setup]  the shared base context is seeded (7.4s)     ← cold seed, not the warm probe
  ✓ [shared] login smoke › … editorial dashboard @smoke (1.9s)
  ✓ [shared] login smoke › … second actor … (4.0s)
  3 passed (12.7s)
```

Every scenario in §4.5 was then re-run on that reset database and reproduced identically,
with each echoed round status matching the database row.

### 4.2b The login smoke, twice, at 2 workers — UNMODIFIED shared spec

```
=== RUN 1 (2 workers, cold .auth)
  ✓ [setup]  the shared base context is seeded (308ms)
  ✓ [shared] login smoke › a seeded editor lands on the editorial dashboard @smoke (1.9s)
  ✓ [shared] login smoke › a second actor is authenticated alongside the default one (3.6s)
  3 passed (5.2s)
=== RUN 2 (2 workers, warm .auth)
  3 passed (3.7s)
```

The second test resolves `appContext.seed.actors.reviewer` → `reviewer.julia` and gates on
`capabilities.hasReviewerRoles`; it passes with no OMP special-casing anywhere in
`lib/pkp`. Per-worker servers observed live with `lsof` during a 3-server run:

```
php 127.0.0.1:8100
php 127.0.0.1:8101
php 127.0.0.1:8102          (basePort + parallelIndex, one php -S each)
```

### 4.3 The seed, read back from the database

```
presses 1 · series 2 · categories 7 · users 18 (17 seeded + admin)

ROSTER + ENROLMENTS
 manager.maya      16      default.groups.name.manager
 editor.diana      16      default.groups.name.editor
 sectioneditor.*   17      default.groups.name.sectionEditor      (×3)
 reviewer.julia    4096    default.groups.name.externalReviewer
 reviewer.paul     4096    default.groups.name.externalReviewer
 reviewer.amara    4096    default.groups.name.internalReviewer
 reviewer.adam     4096    default.groups.name.internalReviewer
 copyeditor.*      4097    default.groups.name.copyeditor         (×2)
 layouteditor.leo  4097    default.groups.name.layoutEditor
 proofreader.pia   4097    default.groups.name.proofreader
 author.alex/bea   65536   default.groups.name.author
 assistant.rita    4097    default.groups.name.funding
 reader.rosa       1048576 default.groups.name.reader

SERIES   monographs (featured 1, editor_restricted 0, seq 0)
         textbooks  (featured 0, editor_restricted 1, seq 1, onlineIssn 0378-5955)

PRESS SETTINGS  publisher, location, codeType 01, codeValue PKPTEST,
                displayNewReleases 1, displayFeaturedBooks 1,
                catalogSortOption datePublished-DESC, restrictMonographAccess 0,
                internalReviewGuidelines (en), contactEmail, defaultReviewMode 2,
                numWeeksPerReview 4, keywords/citations request, copyrightNotice
```

### 4.4 `users[].series` — the assignment lands

```
subeditor_submission_group (assoc_type 530 = ASSOC_TYPE_SERIES ≡ ASSOC_TYPE_SECTION)
  monographs : editor.diana (16), sectioneditor.ana (17), sectioneditor.omar (17)
  textbooks  : editor.diana (16), sectioneditor.ravi (17)
```

Exactly the enrolments table. The two lists are each other's positive control:
`sectioneditor.ravi` is absent from `monographs` because he is assigned to `textbooks`,
not because the read returns nothing. §4.7 shows the assignment doing its real job — those
three users become stage participants on a new monograph in `monographs`.

### 4.5 Scenarios

```
(a) draft        submitted:false, series monographs
                 → stageId 1, submissionProgress "start", dateSubmitted null

(b1) EXTERNAL    reviewRounds[{stage:"external", reviewers:[julia accepted]}]
                 → stageId 3; decision skipInternalReview(18) at stage 1;
                   review_round (stage 3, round 1, status 7 PENDING_REVIEWS);
                   review_assignment date_notified ✓ date_confirmed ✓ declined 0
                 → seriesPosition "Volume 2" echoed back

(b2) INTERNAL    reviewRounds[{stage:"internal", reviewers:[amara accepted]}]
                 → stageId 2; decision sendInternalReview(1) at stage 1;
                   review_round (stage 2, round 1, status 7)

(b3) BOTH        external + internal in ONE spec
                 → decisions sendInternalReview(1)@stage1, sendExternalReview(3)@stage2 r1
                   review_rounds (stage 2, round 1, status 4 ACCEPTED)
                                 (stage 3, round 1, status 7)
                   adam declined in internal, paul accepted in external
                 → response order matches the SPEC order, each entry carrying its stage

(c) PUBLISHED    skipInternalReview → accept → sendToProduction → published:true
                 → status 3 (PUBLISHED), stageId 6, date_published set
                 → the catalog page lists the monograph by its tag
```

All five were re-run from scratch on the freshly installed database of §4.2 and produced
identical rows:

```
review_rounds  (sub 2, stage 3, round 1, status 7)   external only
               (sub 3, stage 2, round 1, status 7)   internal only
               (sub 4, stage 2, round 1, status 4)   both: internal, closed by
               (sub 4, stage 3, round 1, status 7)         sendExternalReview
               (sub 5, stage 3, round 1, status 4)   published
```

Two pieces of real behaviour reproduced rather than mirrored:

- The internal round's status became **4 (ACCEPTED)** when `sendExternalReview` was
  recorded, because OMP's `SendExternalReview::getNewReviewRoundStatus()` says so.
- Publishing recorded an unrequested **`moveToDone` (33)** decision at stage 5 — the app's
  own `ApplyDoneWorkflowStage` listener, exactly as in the UI and exactly as stage A saw
  in OJS.

### 4.6 Unknown-key rejection — including OJS-only keys

Every one 400, with the offending dotted `specKey`:

```
context    sections            → specKey "sections"           (OJS-only)
context    issues              → specKey "issues"             (OJS-only)
context    onlineIssn          → specKey "onlineIssn"         (a PRESS has none; series do)
context    publishingMode      → specKey "publishingMode"     (OJS-only)
context    users[0].sections   → specKey "users.0.sections"   (OJS-only, one level down)
context    users[0].superpower → specKey "users.0.superpower"
submission section             → specKey "section"            (OJS-only)
submission issue               → specKey "issue"              (OJS-only)
submission reviewRounds[0].stage:"middle"
           → specKey "reviewRounds.0.stage"  "should match one item from enum"
```

Value errors name the remedy:

```
series "nope"          → "Press 'publicknowledge' has no series with path 'nope'.
                          Available: monographs, textbooks."          specKey "series"
seriesPosition alone   → "`seriesPosition` needs a `series` to be a position in."
duplicate series path  → "Press 'scratch-uk12' already has a series at path 'dup'."
                                                                 specKey "series.1.path"
role subscriptionManager → "No default user group for role 'subscriptionManager' …
                          Available roles: manager, editor, productionEditor,
                          sectionEditor, copyeditor, designer, funding, indexer,
                          layoutEditor, marketing, proofreader, author, volumeEditor,
                          chapterAuthor, translator, internalReviewer, externalReviewer,
                          reader, editorialBoardMember."           specKey "users.1.roles.0"
```

That last message is also independent confirmation that `subscriptionManager` genuinely
does not exist in OMP, which is what the `null` archetype records.

### 4.7 Notification parity — the acceptance spot-check

Control: a monograph created and submitted through the **real REST path** as
`author.alex` over an authenticated browser session — `POST
/publicknowledge/api/v1/submissions` (with `seriesId`), `PUT …/publications/N`,
`POST …/files` multipart, `PUT …/submit` — the same controllers the wizard's steps call.
Seeded comparison: the same submitter, same series, no decisions.

```
NOTIFICATIONS (assoc_type 1048585 = ASSOC_TYPE_SUBMISSION)
  submission |   type   | level | rows | users        UI path (6)   seeded (7)
        0x1000001 (SUBMISSION_SUBMITTED)   level 2 |  3 | 3,4,6   ==   3 | 3,4,6
        0x100001B (APPROVE_SUBMISSION)     level 2 |  1 |         ==   1 |
        0x100001D (FORMAT_NEEDS_APPROVED_SUBMISSION) 2 |  1 |     ==   1 |

STAGE ASSIGNMENTS                                    identical, 4 rows each
  user 3  (editor.diana)        group 3  can_change_metadata 1
  user 4  (sectioneditor.ana)   group 5  can_change_metadata 1
  user 6  (sectioneditor.omar)  group 5  can_change_metadata 1
  user 15 (author.alex)         group 13 can_change_metadata 0

EVENT LOG
  submission.event.submissionSubmitted        1  ==  1
  submission.event.fileRevised                1  ==  1
  submission.event.general.metadataUpdated    2  vs  1
```

**Full parity, no builder fix needed.** Two things worth calling out:

- `APPROVE_SUBMISSION` / `FORMAT_NEEDS_APPROVED_SUBMISSION` are present on BOTH sides.
  These are the two notifications stage A had to fix for OJS by calling
  `NotificationManager::updateNotification()` from `submitSubmission()`; that shared fix
  carries to OMP unchanged.
- The three editorial `stage_assignments` and the three `SUBMISSION_SUBMITTED`
  notifications are the **series editors of `monographs`**, put there by
  `SubEditorsDAO::assignEditors()` reading the publication's `seriesId`
  (`Application::getSectionIdPropName()` is `'seriesId'` in OMP). Both paths produce them,
  which is the end-to-end proof that `users[].series` does the job it exists for.
- The only difference, `metadataUpdated` ×2 vs ×1, is an artefact of the CONTROL, not of
  the builder: the wizard path writes metadata twice (create, then the title/abstract
  `PUT`) while the seed sets title and abstract in the create call. Not a parity gap.

### 4.8 Failure hygiene

```
before: presses 1 · series 2 · users 18 · submissions 7

(h1) submission: series resolved and the review round opened, then a bad reviewer
     → 400 specKey "reviewRounds.0.reviewers.0.user"  "No user with username 'nobody.here'."
(h2) scratch press: press + series + first throwaway user created, then a bad role
     → 400 specKey "users.1.roles.0"

after:  presses 1 · series 2 · users 18 · submissions 7      (unchanged)
        [ORPHAN]-tagged titles: 0
        leftover scratch presses: (none)
        leftover tw-* users: (none)
```

Rollback succeeded in both, so the orphan-tagging fallback never had to fire. Positive
control taken the same way — a well-formed scratch press —
`{"contextId":4,"urlPath":"scratch-…","users":{"tw-…":21},"series":{"s1":5}}`.

### 4.9 Mailpit — scoped, never cleared

Shared with the other fleets, so the assertion is scoped to a throwaway OMP-specific
recipient and never to a global count. `clearAll()` was not called.

```
recipient tw-<tag>@omp-e2e.example.org
  messages to this recipient BEFORE (after seeding a scratch press + user):  0
  → password reset requested through the real lost-password form
  messages to this recipient AFTER:                                          1
     "Password Reset Confirmation -> tw-<tag>@omp-e2e.example.org"
```

The 0 is itself the evidence that `Mail::fake()` suppresses SEEDING mail while
test-action mail still flows.

---

## 5. Deviations from the design record / the OJS wiring, with rationale

1. **`reviewRounds` is redeclared, not extended.** Adding `stage` one level inside a
   closed shared object has no other seam: `schemaOverlayProperties()` merges at the top
   level only. Restating the property app-side keeps `lib/pkp` untouched and keeps the
   unknown-key contract intact in both directions (verified, §4.6). The cost is a copy of
   the `reviewers` sub-schema that must be kept in step if the shared one changes; a
   `reviewRoundSchemaOverlayProperties()` hook, mirroring `userSchemaOverlayProperties()`,
   would remove it (§7.2).
2. **`reviewStageId()` is overridden rather than inherited** — see §3 and §7.1.
3. **`workType` is NOT declared.** The shared builder exposes `applyPublicationOverlay()`
   but no hook onto `submissionProps`, and `workType` is a SUBMISSION property in OMP. It
   is also not needed for step 2, and PRINCIPLES §3 says not to extend without callers.
   Recorded as a precisely-scoped shared-layer gap (§7.2) rather than worked around with a
   second write. **Note the seeded and UI paths agree** — both leave `work_type = 0`
   (§7.3), so nothing is lost by the omission today.
4. **`series` on a submission has no default.** OJS defaults `section` to the journal's
   first section because an article always has one; an OMP monograph with no series is a
   valid and common state, so naming none means none.
5. **A duplicate series path is an error, not an update.** OJS matches-and-edits because a
   default section already exists; OMP creates none, so a repeated path is a typo in a
   base seed, and the `series_path` unique index would reject it anyway.
6. **No OMP-only accounts were added to the roster** (no Designer, Volume editor, Chapter
   author or Translator, all of which OMP ships as user groups). Step 2 needs none, and a
   base-seed change means re-checking every implemented spec. Worth revisiting when the
   edited-volume / production specs land.
7. **`installed_locales = en,fr_CA`.** The record does not demand `fr_CA` for OMP
   specifically, but the reason OJS's base is bilingual is app-neutral (a bare front-end
   URL 302s to the locale-prefixed form only on a multi-locale context) and it keeps the
   three fleets comparable, so OMP matches OJS.
8. **Round statuses are re-read at the end of the build** (`withFinalRoundStatuses()`)
   rather than trusted as the shared builder stamped them. Only OMP needs this, because
   only OMP builds two stages in one request and the later one closes the earlier one's
   round. Stage A recorded the same underlying quirk for OJS ("a round's status reflects
   the decision history, not the order reviewers were added"); here it is simply read back.
9. **`package-lock.json` was synced** (`npm install --package-lock-only`) so the declared
   `@playwright/test` devDependency is installable; the diff is 64 added lines, all
   playwright entries.

---

## 6. Proposed rows

### 6.1 `docs/e2e/app-changes.md`

| Date | App | Change | Where | Why |
|---|---|---|---|---|
| 2026-07-27 | OMP | `/config.test.inc.php`, `.env.playwright`, `playwright/.auth/`, `playwright/.results/`, `playwright-report/` added to `.gitignore` | `omp-main/.gitignore` | The test config holds local DB credentials and a generated app key, like the already-ignored `config.inc.php`; the rest are run artefacts. |
| 2026-07-27 | OMP | New test-only files (not app behaviour): `api/v1/_test/*`, `tools/installTest.php`, `tools/testServer.sh` | — | The harness's PHP seeding API for OMP. Inert without `TEST_API_KEY` in the process environment. |
| 2026-07-27 | OMP | New test-only files: `playwright/*`, `.env.playwright.example`, nine `test:e2e:*` npm scripts, `@playwright/test` devDependency (+ its `package-lock.json` entries) | — | The OMP Playwright wiring. Nothing outside `playwright/` changes application behaviour. |

### 6.2 `docs/e2e/scenario-processor-audit.md`

| Date | App | Builder path | Parity check | Verdict | Notes |
|---|---|---|---|---|---|
| 2026-07-27 | OMP | submission · submit | `notifications`, `stage_assignments`, `event_log` vs a monograph created and submitted through the real REST path as `author.alex` into the same series | ✅ parity, no fix needed | Both sides carry `SUBMISSION_SUBMITTED` (×3, to the series editors), `APPROVE_SUBMISSION` and `FORMAT_NEEDS_APPROVED_SUBMISSION`; stage assignments identical. Stage A's OJS fix (calling `NotificationManager::updateNotification()` after `Repo::submission()->submit()`) carries to OMP unchanged. The only diff, `metadataUpdated` ×2 vs ×1, is the control's extra wizard `PUT`, not a builder gap. |
| 2026-07-27 | OMP | submission · review rounds, internal AND external | `review_rounds.stage_id` / `round` / `status`, `review_assignments`, `edit_decisions` for internal-only, external-only, and both-in-one-spec seeds | ✅ real path | Rounds are opened by real decisions chosen from where the submission stands — `sendInternalReview` (1→2), `skipInternalReview` (1→3), `sendExternalReview` (2→3) — never by writing a round. The internal round's status becomes 4 (ACCEPTED) on `sendExternalReview` because OMP's `SendExternalReview::getNewReviewRoundStatus()` says so: real behaviour reproduced, not mirrored. |
| 2026-07-27 | OMP | context · series | Series created through `Repo::section()` (which IS OMP's series repository — `schemas/section.json` is titled "A press series" and the DAO writes the `series` table) | ✅ real path | A press is created with NO series (unlike OJS's default section), so every series is genuinely created. `image` is initialised to `[]` as `SeriesForm::execute()` does. Duplicate paths throw rather than silently update. |
| 2026-07-27 | OMP | context/bootstrap · `users[].series` | `subeditor_submission_group` rows, then the end-to-end proof: the three `monographs` series editors appear as `stage_assignments` and `SUBMISSION_SUBMITTED` recipients on BOTH a seeded and a UI-path submission into that series | ✅ real path | Assignment goes through `SubEditorsDAO::insertEditor(contextId, seriesId, userId, ASSOC_TYPE_SERIES, userGroupId)` — the call `PKPSectionForm::execute()` makes, which OMP's `SeriesForm` delegates to — restricted to that form's `assignableRoles` (sub-editor → manager → assistant). Unknown path and un-assignable role both THROW with the offending `specKey`. Idempotent re-runs skip existing rows. |
| 2026-07-27 | OMP | seeding mail | Messages to a throwaway OMP-specific recipient before/after seeding a scratch press and a user | ✅ suppressed | 0 before, 1 after a real lost-password request — `Mail::fake()` covers the seeding request only. Mailpit was never cleared and no global count was asserted (the fleets share it). |

---

## 7. Findings for the orchestrator

### 7.1 Shared: `reviewStageId()` assumes `getReviewStages()` is ordered — it is not on OMP

`lib/pkp/api/v1/_test/PKPSubmissionScenarioController::reviewStageId()`:

```php
$stages = Application::get()->getReviewStages();
return empty($stages) ? null : (int) end($stages);      // "the last one"
```

`APP\core\Application::getReviewStages()` in `omp-main/classes/core/Application.php:183`
returns `[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_INTERNAL_REVIEW]` —
**external first**. `end()` therefore yields Internal Review, so on OMP the shared default
review stage is the internal one, silently. OJS has a single-entry roster and never
notices.

**Not blocking**: the OMP subclass overrides `reviewStageId()` and never lets that roster's
ordering decide anything, which is also what the internal/external key requires. But the
base method's comment reads "the app's stage roster… last entry" as if the roster were
ordered, and any future shared code doing the same will inherit the bug. Suggested shared
fix: `max($stages)` with a comment saying the roster is unordered, or an explicit
`defaultReviewStageId()` the app declares. The orchestrator owns whether this is worth a
`lib/pkp` change now.

### 7.2 Shared gaps that forced an app-side copy (neither blocking)

1. **No hook onto `submissionProps`.** `applyPublicationOverlay(array $spec, Context,
   array &$publicationProps)` lets an app add publication properties; there is no
   counterpart for the submission itself. OMP's `workType`, `audience*` and
   `enableChapterPublicationDates` are SUBMISSION properties, so none of them can be
   declared without either overriding all ~60 lines of `createSubmission()` or issuing a
   second `edit()` write the wizard does not make. A one-line
   `applySubmissionOverlay(array $spec, Context, array &$submissionProps)` hook, called
   next to the existing one, would close it. **Not built** — nothing needs it in step 2.
2. **No hook onto a nested schema object.** `userSchemaOverlayProperties()` solved this
   one level down for `users[]`; `reviewRounds[]` has no equivalent, so OMP restates the
   whole property to add `stage` (§5.1). A `reviewRoundSchemaOverlayProperties()` in the
   same shape would remove the copy.

### 7.3 Product observation: a new OMP submission gets `work_type = 0`

`schemas/submission.json` documents `workType` with `"default": 2`
(`WORK_TYPE_AUTHORED_WORK`) and `"validation": ["in:1,2"]`, yet a submission created
through the **real REST path** without naming one lands with `work_type = 0` — which is
neither of the two legal values. The seeded path agrees (that is why this is not a parity
gap), so it is the application's behaviour, not the harness's. Whether a monograph with an
out-of-range work type is a defect belongs to whichever spec owns the submission wizard;
recorded here rather than asserted anywhere.

### 7.4 Observations carried over from stage A that also hold on OMP

- `SubEditorsDAO::editorExists()` is still broken (stage B §7.3); the OMP overlay reads
  assignments through `getBySubmissionGroupIds()` for the same reason.
- `APP\decision\types\ResubmitInternal` is defined but absent from
  `APP\decision\Repository::getDecisionTypes()`, and returns the same
  `Decision::PENDING_REVISIONS_INTERNAL` (20) as `RequestRevisionsInternal`. The harness
  resolves decisions by class name against the roster, so `resubmitInternal` simply
  answers "unknown decision" with the full list — no silent surprise. Product-side
  judgement is not mine; recording it.
- `registry/genres.xml` lists `OTHER` twice. The shared `primaryGenre()` picks by shape
  and correctly resolves `MANUSCRIPT` ("Book Manuscript"), so nothing here is affected.

---

## 8. Reproduce

```bash
cd /Users/jarda/git/pkp/pkp-main/omp-main
git -C lib/pkp checkout e2e_ng && git -C lib/pkp reset --hard origin/e2e_ng
cp .env.playwright.example .env.playwright      # edit PKP_CONFIG_FILE if your paths differ
# config.test.inc.php is local + gitignored: Postgres omp_test,
#   base_url http://127.0.0.1:8100, session_cookie_name OMPTESTSID,
#   files_dir …/files-test-omp, [i18n] installed_locales = en,fr_CA,
#   [schedule] task_runner = Off, [queues] job_runner = Off, enable_beacon = Off
npm install                                     # brings in @playwright/test
npm run test:e2e:install                        # once
npm run test:e2e:setup                          # installs the schema if needed, then seeds
npm run test:e2e                                # setup → shared → omp → omp-serial
```

Requires Mailpit on `:8025`. The fleet ports are OJS 8000 / **OMP 8100** / OPS 8200, one
`php -S` per parallel worker at `basePort + parallelIndex`.
