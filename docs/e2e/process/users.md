# Users & Roles Reference

Everything auth-related: which user to log in as, passwords, how login caching
works, and the seeded context. Live — the harness files named here exist and
match. Display names read "Firstname Role" (`admin` is "Site Admin"); emails
are `<username>@mail.test`.

**Three distinct role vocabularies appear in this work — don't mix them:**

1. **PHP role constants** (`ROLE_ID_*`, `lib/pkp/classes/security/Role.php`) —
   what the backend checks.
2. **Scenario role keys** (`users[].roles` in scenario POSTs) — resolved by
   `UserSeeder::resolveUserGroup()` against the app's default user groups.
3. **Roster labels** (the "Role" column below) — informal names for the
   seeded accounts.

## 1. PHP role constants

| Constant | ID | Notes |
|---|---|---|
| `ROLE_ID_SITE_ADMIN` | 1 | Site-wide; the group has a null context id |
| `ROLE_ID_MANAGER` | 16 | Journal manager — settings, users, plugins |
| `ROLE_ID_SUB_EDITOR` | 17 | Editor / section editor |
| `ROLE_ID_ASSISTANT` | 4097 | Copyeditor, layout editor, proofreader, funding coordinator, editorial board member |
| `ROLE_ID_REVIEWER` | 4096 | Peer reviewer |
| `ROLE_ID_AUTHOR` | 65536 | Author (also implicit on submit) |
| `ROLE_ID_READER` | 1048576 | Any registered user |
| `ROLE_ID_SUBSCRIPTION_MANAGER` | 2097152 | OJS-only; not seeded in the roster |

## 2. Scenario role keys (per app)

A key the app does not ship throws a 400 that lists the whole set — the
cheapest way to re-check these lists.

| App | Keys |
|---|---|
| OJS | `manager`, `editor`, `productionEditor`, `sectionEditor`, `guestEditor`, `copyeditor`, `designer`, `funding`, `indexer`, `layoutEditor`, `marketing`, `proofreader`, `author`, `translator`, `externalReviewer`, `reader`, `subscriptionManager`, `editorialBoardMember` |
| OMP | as OJS minus `guestEditor`/`subscriptionManager`, plus `volumeEditor`, `chapterAuthor`, `internalReviewer` |
| OPS | `manager`, `sectionEditor`, `author`, `reader`, `editorialBoardMember` only — no `funding`, no reviewer keys, no production assistants |

Traps:

- **There is no `reviewer` key**: it is `externalReviewer` (OMP also
  `internalReviewer`).
- **On default (scratch-journal) user groups, `editor` resolves to "Journal
  editor" = `ROLE_ID_MANAGER`** (`registry/userGroups.xml`), NOT sub-editor —
  a `users: [{roles: ['editor']}]` throwaway passes manager-level gates
  (canPublish, settings). Use `sectionEditor` for a non-manager editorial
  role.
- The site administrator has no scenario key: the installer's `admin` is the
  only administrator, kept enabled and unmerged — every suite depends on it.
- Screens show the app's own label: `sectionEditor` renders as "Section
  editor" (OJS), "Series editor" (OMP), "Moderator" (OPS).

## 3. The seeded roster

Home: `lib/pkp/playwright/data/users.js`. All 18 users are enrolled in
`publicknowledge` (admin is site-level, created by the installer; the other 17
by the bootstrap seed). Usernames are `role.firstname`; one account per
permission archetype; **use the first listed account for a role** unless the
test needs a specific property. OMP/OPS enrol subsets — see `harness.md`.

| Username | Roster label | Use when you need… |
|---|---|---|
| `admin` | site admin | Admin console, multi-journal operations, plugins |
| `manager.maya` | manager | Journal settings, managing users |
| `editor.diana` | editor | A senior editor (also sectionEditor in both sections) |
| `sectioneditor.ana` | sectionEditor | Section editor for Articles (`ART`) — the default pick |
| `sectioneditor.ravi` | sectionEditor | Section editor for Reviews (`REV`) |
| `sectioneditor.omar` | sectionEditor | Another Articles section editor; the designated account for recommend-only assignments (the flag itself is per-assignment) |
| `reviewer.julia` | reviewer | The default reviewer |
| `reviewer.paul` | reviewer | A second reviewer |
| `reviewer.amara` | reviewer | A third (OMP: Internal) |
| `reviewer.adam` | reviewer | A fourth (OMP: Internal) |
| `copyeditor.carla` | copyeditor | Copyediting actions |
| `copyeditor.sam` | copyeditor | A second copyeditor |
| `layouteditor.leo` | layoutEditor | Layout / galley production |
| `proofreader.pia` | proofreader | Proofreading actions |
| `author.alex` | author | A non-privileged author (author-only permission gates) |
| `author.bea` | author | A second author — co-author and foreign-submission cases |
| `assistant.rita` | assistant | OJS/OMP: Funding coordinator — the one default assistant group WITH review-stage access (stages 1,3). OPS: Editorial Board Member, NO stage access |
| `reader.rosa` | reader | Registered user with no roles beyond reader |

**Why `author.alex` matters**: every other seeded user with workflow access
holds a manager/editor role that short-circuits
`canEditPublication` — only the two author-only accounts make author-side
permission tests meaningful.

No pre-seeded subscription manager (OJS-only role); `reader.rosa` covers the
plain-reader case.

## Password rule

`getPassword()` in `users.js`: `admin` → `admin`; everyone else → username
repeated twice (`editor.diana` → `editor.dianaeditor.diana`). No seeded
account is `mustChangePassword`-flagged. **Maxlength trap**: the login form's
password input carries `maxlength="32"` while `sectioneditor.*` passwords run
longer — `LoginPage.fillPassword()` lifts the attribute, so specs never see
it (the UI limitation itself is a product finding, not something to test
around).

## Login flow internals

`ensureAuthStateFor(browser, username, {baseURL})`
(`lib/pkp/playwright/support/auth.js`):

1. If `playwright/.auth/<username>.json` exists, **probe** it: replay the
   cookies, GET the profile URL FOLLOWING redirects, and judge by where the
   request ends (`ok() && !url.includes('/login')` → live). Status-with-
   redirects-disabled cannot work here — even a signed-in profile request
   redirects twice (locale prefix, then into the context).
2. Otherwise perform a real UI login (`/index.php/index/en/login`; stable ids
   `input#username`, `input#password`, `form#login button`), wait for the
   redirect away from `/login` (`waitUntil: 'commit'`), snapshot
   `storageState()` to the file.
3. Two workers may race on a missing/stale file — both log in (concurrent
   sessions are allowed), last write wins.

**Why the probe**: impersonation flows (`signInAs`/`signOutAs`) migrate the
session id and destroy the previous row, stranding cached cookies. The probe
catches that without special-casing those specs.

Consumption: `test.use({user: 'editor.diana'})` for the file's default
identity (the `storageState` fixture in `base-test.js` wires it), `asUser()`
for additional actors.

## Bootstrap prerequisite

Auth works only after the setup project ran. It probes
`GET /api/v1/_test/bootstrap?context=publicknowledge`; warm → no-op, cold →
`tools/installTest.php` (empty DB → install; install debris → drop all tables
and reinstall; refuses any DB whose name lacks "test") then seeds
`publicknowledge` + the 17 non-admin users from
`playwright/fixtures/bootstrap.js`. Stale `.auth/` files are recreated on
demand; `npm run test:e2e:reset` forces a full cold bootstrap.

## The `publicknowledge` context

Path `publicknowledge`, base `/index.php/publicknowledge/`, primary locale
`en` (supported `en`, `fr_CA`), acronym `JPK`.

| Section | Abbrev | Section editors | Notes |
|---|---|---|---|
| Articles | `ART` | editor.diana, sectioneditor.ana, sectioneditor.omar | Word count limit 500 |
| Reviews | `REV` | editor.diana, sectioneditor.ravi | Abstracts not required |

Categories: `applied-science` (children `comp-sci/computer-vision`, `eng`) and
`social-sciences` (children `sociology`, `anthropology`).

Issues: Vol 1 No 2 (2014) **published**; Vol 2 No 1 (2015) unpublished — use
the unpublished one when publishing unless the test targets a back issue.
