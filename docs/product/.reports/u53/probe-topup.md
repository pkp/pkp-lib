# U53 — Users management · top-up probe (the four questions the missing fixture blocked)

RUNBOOK step 3, follow-up run. Facts only, same shape as probes A–D. Each
observation is marked **claim** (a deliberate, repeated observation of the thing
the item asks about) or **context** (incidental, not promotable to an assertion).

Frame: QA documentation of the applications' own screens on a local disposable
test install. Everything below was driven as a signed-in user through the
applications' own screens (Playwright-driven Chromium, real clicks, real form
fills, typed URLs where an item calls for one). The only non-browser traffic was
the sanctioned `/api/v1/_test/scenarios/*` seeding that created this probe's own
throwaway accounts and scratch contexts.

Date of run: 2026-07-29. Fleets: OJS `127.0.0.1:8000`, OMP `127.0.0.1:8100`,
OPS `127.0.0.1:8200`. This probe was the only agent on the fleets.

**What unblocked it:** the new `users[].roles: ['siteAdmin']` scenario role key
(committed in `lib/pkp` as `47e0a043c3`), which seeds a throwaway site
administrator. Items 1–4 were all previously undetermined for want of one.

---

## 0. Safety statement, staging and namespacing

**The seeded `admin` account was never disabled, never merged, never removed and
never edited.** It was signed into once per fleet, at the very end, purely to
*prove* it is still enabled and still reaches Administration (§6). No action was
taken on `publicknowledge` or on any of its 18 seeded users. Mailpit was never
cleared and was not read by this probe.

Every account acted on was created by this probe, under the reserved `u53top`
prefix, in scratch contexts created by this probe.

### Scratch contexts created (`POST /api/v1/_test/scenarios/context`)

| App | urlPath / tag | contextId | Name | Purpose |
|---|---|---|---|---|
| OJS | `u53topojs` | 14 | U53 Topup ojs | fixture verification only |
| OMP | `u53topomp` | 10 | U53 Topup omp | fixture verification only |
| OPS | `u53topops` | 7 | U53 Topup ops | fixture verification only |
| OJS | `u53toppojs` | 16 | U53 Topup Probe ojs | items 1–3 |
| OMP | `u53toppomp` | 11 | U53 Topup Probe omp | items 1–3 |
| OPS | `u53toppops` | 8 | U53 Topup Probe ops | items 1–2 |

### Throwaway accounts created

Per fleet `<app>` ∈ {ojs, omp, ops}, address always `<username>@mail.test`
rendered as `u53top-<slot>-<app>@mail.test`:

| Username | Roles | Used for |
|---|---|---|
| `u53top.admin.<app>` | `siteAdmin` | fixture verification; item 4 |
| `u53top.sa.<app>` | `siteAdmin`, `manager`, `externalReviewer` (OPS: `author`) | items 1, 3 — the administrator whose *own* row/record is under test |
| `u53top.sb.<app>` | `siteAdmin`, `manager`, `externalReviewer` (OPS: `author`) | item 2 target; item 3 second actor |
| `u53top.jm.<app>` | `manager` | item 2 actor (the journal manager) |
| `u53top.rv.<app>` | `externalReviewer` (OPS: `author`) | item 2 positive control |

### Accounts this probe disabled

`u53top.rv.ojs` only — the positive control for item 2. No other account on any
fleet was disabled, merged or removed.

### Submissions seeded (`POST scenarios/submission`)

OJS `u53toppojs`: id 1 (`u53topgojs`, Review round 1, reviewers `sa` + `rv`),
id 2 (`u53toph2ojs`, Review round 1, no reviewers).
OMP `u53toppomp`: id 2 (`u53topgomp`, external Review round 1, reviewers
`sa` + `rv`), id 3 (`u53toph2omp`, external round, no reviewers).

---

## 1. Item 1 — the site administrator's own row: is Disable offered, and what happens?

**Verdict: CORRECTS the draft claim's premise. The control does not exist.** A
site administrator meets **no Disable control on their own row**, on any screen,
on any of the three fleets. The follow-up questions (what the dialog says, what
the list shows afterwards, whether that administrator can still sign in) are
therefore unreachable through the screens and are moot rather than open.

### 1a — The Hosted Journals list carries no per-row Disable at all · **claim**

**App:** OJS · **Role:** site administrator (`u53top.admin.ojs`) ·
**Screen:** Administration → Hosted Journals,
`http://127.0.0.1:8000/index.php/index/en/admin/contexts`

Signed in through the sign-in screen, navigated to `/admin`, took the
**Hosted Journals** link. Each journal row was read in full.

Row text, verbatim: `Settings U53 Topup Probe ojs u53toppojs`

Controls in the row, enumerated (`locator('li, tr').filter({hasText: …})` then
every visible `button, a`): exactly **one** — an `<a>` reading **"Settings"**.
No Disable, no Remove, no actions menu. The list's own controls are page-level
("Order", "Create Journal"), not per-row.

Same on OMP ("Hosted Presses") and OPS ("Hosted Servers"): the crumb changes,
the row shape does not.

**Context:** the Hosted Journals list is a list of *journals*, not of users, so
there is no "own row" on it for an administrator to meet. If the draft claim was
written from this screen, it was written from the wrong screen.

### 1b — On Users & Roles, one's own row offers only Edit and Email · **claim**

**App:** OJS, OMP, OPS · **Role:** site administrator, and separately journal
manager · **Screen:** Settings → Users & Roles → Users tab,
`/{context}/en/management/settings/access`

Locator: the row's actions button is the last `button` in the `<tr>`, carrying
`aria-label="##userAccess.management.options##"` (untranslated — see §5); the
menu entries are `[role="menuitem"]`.

Opened the actions menu on every row, as two different actors, on all three
fleets. Complete result:

| Fleet | Signed in as | Row | Menu entries offered |
|---|---|---|---|
| OJS | `u53top.sa.ojs` (site admin) | **own row** | `Edit`, `Email` |
| OJS | `u53top.sa.ojs` | `Sb` (another site admin) | `Edit`, `Email`, `Login As`, `Remove User`, `Disable User`, `Merge user` |
| OJS | `u53top.sa.ojs` | `Jm` (manager) | `Edit`, `Email`, `Login As`, `Remove User`, `Disable User`, `Merge user` |
| OJS | `u53top.sa.ojs` | `Rv` (reviewer) | `Edit`, `Email`, `Login As`, `Remove User`, `Disable User`, `Merge user` |
| OJS | `u53top.jm.ojs` (manager) | **own row** | `Edit`, `Email` |
| OMP | `u53top.sa.omp` | **own row** | `Edit`, `Email` |
| OMP | `u53top.jm.omp` | **own row** | `Edit`, `Email` |
| OPS | `u53top.sa.ops` | **own row** | `Edit`, `Email` |
| OPS | `u53top.jm.ops` | **own row** | `Edit`, `Email` |

(The non-own rows are identical on OMP and OPS to the OJS lines above; the full
matrix was taken on all three.)

So the self-exclusion is **not administrator-specific**: a journal manager's own
row is equally stripped of `Disable User`, `Remove User`, `Login As` and
`Merge user`. Whatever the screen withholds, it withholds from everyone about
themselves.

**Positive control taken the same way:** in the same menu-open pass, in the same
session, the *other* rows did offer `Disable User` — so the absence on the own
row is the screen's decision, not a rendering failure.

### 1c — What was NOT done

The Disable action for another user is a request the screen sends; aiming that
same request at oneself is a request no screen sends. Per the frame, that was not
constructed. **Deferred queue:** "what the application does if a self-targeted
disable request reaches it" stays unasked.

---

## 2. Item 2 — a journal manager acting on a site administrator's account

**Verdict: CORRECTS. The offer is made and then refused.** `Disable User` is
offered against a site administrator and, when taken, produces a permission
refusal with no way to proceed. `Merge user` is **not offered** at all.
`Remove User` *is* offered and reaches a real confirmable dialog.

### 2a — Disable is offered, then refused · **claim**

**Apps:** OJS, OMP, OPS · **Role:** journal / press / server **manager**
(`u53top.jm.<app>`) · **Screen:** Settings → Users & Roles → Users tab, row of
`Sb U53top` (a site administrator) → actions menu → `Disable User`

The menu entry is present (`getByRole('menuitem', {name: 'Disable User'})` →
count 1). Taking it opens the modal, which reads, verbatim (OJS):

```
Disable Sb U53top

Current Roles : Journal manager, Reviewer

You do not have sufficient permissions to administer this user. In order to
administer a user, you must either be site administrator, or administer all
contexts that this user is enrolled in.
```

Buttons in the modal: **`Close` only.** There is no OK, no Disable, no Cancel —
nothing that could carry the action out. The account is unchanged.

Identical on the other two fleets, differing only in the roles line:

- OMP: `Current Roles : Press manager, External Reviewer` — same refusal
  paragraph, same `Close`-only footer.
- OPS: `Current Roles : Preprint Server manager, Author` — same refusal
  paragraph, same `Close`-only footer.

### 2b — Positive control: the same manager, the same action, an ordinary user · **claim**

**App:** OJS · **Role:** manager `u53top.jm.ojs` · **Screen:** same list, row of
`Rv U53top` (Reviewer only) → `Disable User`

Same click path, same session, minutes apart. The modal reads instead:

```
Disable Rv U53top

Current Roles : Reviewer

Reason for disabling user

Please note that once a user is disabled, you won't be able to add them to any
roles until the user is enabled again.
```

Buttons: **`Cancel` and `OK`.** Took `OK`. Outcome, read back through the
screens:

- The list row for `Rv U53top` gains a red icon after the name (an eye-slash,
  `class="… pkpIcon--inline h-4 w-4 text-negative"`); the row otherwise reads
  unchanged (`Rv U53top | u53top-rv-ojs@mail.test | Reviewer | 2026-07-29`).
- Signing in as `u53top.rv.ojs` at `/index.php/index/en/login` with the correct
  password lands on `/index.php/index/en/login/signIn` showing, verbatim:
  **"Your account has been disabled. Please contact the administrator for more
  information."**
- The account also disappears from the Select Reviewer list on the Add Reviewer
  panel (noticed while running item 3; **context**).

So the refusal in 2a is specific to the target being a site administrator, not a
broken Disable flow.

### 2c — Merge is not offered against a site administrator · **claim**

**App:** OJS · **Role:** manager `u53top.jm.ojs` · **Screen:** same list

`getByRole('menuitem', {name: 'Merge user'})` on the `Sb U53top` row → **count
0**. On the `Rv U53top` row, in the same menu-open pass, `Merge user` **is**
present (positive control, §1b matrix). `Login As` is withheld from the manager
for a site administrator in exactly the same way.

The manager therefore never gets as far as a merge dialog for an administrator;
the surface is simply not offered.

### 2d — Remove User against a site administrator is NOT refused · **claim**

**App:** OJS · **Role:** manager `u53top.jm.ojs` · **Screen:** same list, row of
`Sb U53top` → `Remove User`

Offered, and the modal is a real one:

```
Remove

Remove this user from this journal? This action will unenroll the user from all
roles within this journal.

OK
Cancel
```

**The offer was NOT taken** (this probe had no need to unenrol the account and
the fixture was still in use). What is recorded is that the same manager, on the
same administrator's row, is refused for Disable but reaches a live confirm for
Remove — three actions with three different answers on one row.

---

## 3. Item 3 — "Editorial Notes never appear on one's own record"

**Verdict: CORRECTS.** On the Select Reviewer panel, an administrator viewing
their **own** record sees the Editorial Notes block with its content in full.
The "never on one's own record" rule holds on the workflow's Reviewers panel and
is absent from the Users & Roles Edit screen entirely, but it does **not** hold
on the screen where the notes are actually read.

### 3a — Editorial Notes are not on the Users & Roles Edit screen at all · **claim**

**App:** OJS · **Role:** site administrator `u53top.sa.ojs` · **Screen:**
Users & Roles → row actions → `Edit`

`Edit` does not open a user-details modal; it navigates to
`Users & Roles / Invite user to take a role` (STEP 1 — "Enter details and invite
for roles"). Taking `View more details` expands Homepage URL, Phone, Working
Languages and **Reviewing interests**. There is **no Editorial Notes field**, on
one's own record or on anyone else's:

| Actor | Record edited | "Editorial Notes" present | "Reviewing interests" present |
|---|---|---|---|
| `u53top.sa.ojs` | own (`Sa`) | no | yes |
| `u53top.sa.ojs` | `Sb` (other admin) | no | yes |

So this screen cannot be the one the claim is about.

### 3b — On the workflow Reviewers panel, the action is offered per reviewer · **claim**

**App:** OJS · **Role:** site administrator + manager `u53top.sb.ojs` ·
**Screen:** Editor Dashboard → Active submissions → submission 1 → Reviewers
panel → row `More Actions` (`getByRole('button', {name: 'More Actions'})`)

| Row | Menu entries |
|---|---|
| `Sa U53top` (a site administrator, reviewer on this submission) | `Review Details`, `Email Reviewer`, `Edit`, `Cancel Reviewer`, `History`, **`Editorial Notes`** |
| `Rv U53top` (reviewer only) | `Review Details`, `Email Reviewer`, `Edit`, `Cancel Reviewer`, `History`, `Login As`, **`Editorial Notes`** |

Took `Editorial Notes` on each and wrote through the screen's rich-text editor
(the modal's confirm button is labelled **`OK`**, id `submitFormButton-…`):
`u53top notes for SA ojs` and `u53top notes for RV ojs`. Both saved.

**Own-row case on this panel is unreachable, and that is itself the observation
(context):** signed in as `u53top.sa.ojs`, who is a manager *and* a reviewer on
submission 1, the dashboard row shows in place of the workflow link:

> "You cannot access this submission as a Journal Manager since you are the
> reviewer. To view it, go to "Review Assignments""

So a manager who is a reviewer on a submission cannot open that submission's
Reviewers panel at all, and can never meet their own row there. **Undetermined**
whether that panel would withhold the action on one's own row — the screen never
lets the situation arise.

### 3c — On the Select Reviewer panel, one's OWN Editorial Notes are shown · **claim**

**Apps:** OJS and OMP · **Role:** site administrator + manager
(`u53top.sa.<app>`), who is also enrolled as a reviewer in the same context ·
**Screen:** submission 2 (OJS) / 3 (OMP) → Reviewers panel → `Add Reviewer` →
"Locate a Reviewer" list → `Show more details about Sa U53top`

The expanded entry for **the signed-in administrator's own record** reads, in
order: Active reviews currently assigned · Reviews completed · Review requests
declined · Review requests cancelled · Days since last review assigned · Average
days to complete review · **`Editorial Notes`** · `u53top notes for SA ojs`.

| Fleet | Actor | Record expanded | "Editorial Notes" heading | Note content visible |
|---|---|---|---|---|
| OJS | `u53top.sa.ojs` | **own** (`Sa`) | **yes** | **yes** — `u53top notes for SA ojs` |
| OMP | `u53top.sa.omp` | **own** (`Sa`) | **yes** | **yes** — `u53top notes for SA omp` |
| OJS | `u53top.sb.ojs` | other (`Sa`) | yes | yes — `u53top notes for SA ojs` |

The third line is the **positive control**: the same block, on the same screen,
for someone else's record, taken the same way. Own and other are rendered
identically — the panel draws no distinction.

**Corroboration (code, not a screen observation):** the reviewer-grid action in
3b is gated by `Repo::user()->canCurrentUserGossip()`, which returns false for
`$currentUser->getId() === $userId`; the list panel in 3c is gated by
`Repo::user()->canSeeGossip()`, a viewer-only capability with no self-check.
Two gates, one field.

**OPS:** not applicable — OPS ships no reviewer user group at all, so no account
can hold the reviewer role and the Editorial Notes surface never appears.
Recorded as **context**, not as a passing case.

---

## 4. Item 4 — the re-authentication prompt

**Verdict: CONFIRMS, with the scope narrowed.** A password prompt does guard
Administration pages when `security.password_timeout` is set — and *only*
Administration pages, and *only* for site administrators. With the setting
absent (the fleets' normal state) it never fires at all, so nothing in the draft
about it is observable on a default install.

### 4.0 — The configuration window this probe held

| | |
|---|---|
| File | `/Users/jarda/git/pkp/pkp-main/ojs-main/config.test.inc.php` (OJS only; OMP and OPS untouched) |
| Change | inserted `password_timeout = 1` (minutes) plus a two-line comment, immediately after `[security]` |
| Opened | **2026-07-28 23:21:32 UTC** (2026-07-29 01:21:32 +0200) |
| Closed | **2026-07-28 23:24:08 UTC** (2026-07-29 01:24:08 +0200) |
| Duration | **2 min 36 s** |
| sha256 before | `5717905ebefad601456ed09ae7fd884d5ad17c2d220856a7e129b93b52486b81` |
| sha256 while patched | `468e9934346958f86f44950d6290c34701e506d508e79d9bf6ed1aa0b5e8ae4c` |
| sha256 after restore | `5717905ebefad601456ed09ae7fd884d5ad17c2d220856a7e129b93b52486b81` |
| Byte compare | `cmp` against a `cp -p` copy taken before the edit → **identical, byte for byte** |
| Residue check | `grep -c password_timeout config.test.inc.php` → **0** |
| Behaviour check | after restore, `u53top.admin.ojs` → `/index.php/index/en/admin` lands on `/admin` directly, no prompt |

This probe was the only agent on the fleets for the whole window. Per PRINCIPLES
design-record 9 this is exactly the manoeuvre a retained test may not make; it is
recorded here as a one-off probe measurement, not as a pattern for the suite.

### 4a — When the prompt appears · **claim**

**App:** OJS · **Role:** site administrator `u53top.admin.ojs` · **Screen:**
typed URL `http://127.0.0.1:8000/index.php/index/en/admin`

Signing in does **not** grant elevated access. The first visit to `/admin` after
signing in redirects to:

```
http://127.0.0.1:8000/index.php/index/en/admin/confirmAccess?source=%2Findex.php%2Findex%2Fen%2Fadmin
```

The page reads, verbatim:

```
Confirm Access

Signed in as Admin U53top. Please enter your password to continue.

Password
Cancel Submit
```

Fields: one `input[type="password"]` (`id="password-…"`, no `name`), one
`button[type="submit"]` reading **`Submit`**, and a **`Cancel`** control. The
requested page is carried in `?source=`.

### 4b — Correct password, wrong password, and the window · **claim**

| Step | Action | Result |
|---|---|---|
| Correct password → `Submit` | lands on `http://…/en/admin`, Administration renders in full (Site Management, System Information, Expire User Sessions, Delete Caches, Jobs) |
| Immediately open `/admin/contexts` | **no prompt**; Hosted Journals renders directly — the elevation persists across Administration pages |
| Wrong password → `Submit` | stays on `…/en/admin/confirmAccessSubmit`, the same Confirm Access page redisplays, with the notification: **"Invalid username/email or password. Please try again."** |
| Elevate, then idle 70 s (`password_timeout = 1` minute), then open `/admin/settings` | **prompted again** — redirected to `…/admin/confirmAccess?source=%2Findex.php%2Findex%2Fen%2Fadmin%2Fsettings`, same Confirm Access wording |

### 4c — Scope: journal-level screens are not guarded · **claim**

**App:** OJS · **Role:** the same site administrator, same session, *before*
elevating · **Screen:** `/index.php/u53toppojs/en/management/settings/access`

The journal's Users & Roles screen opened normally, with no redirect to
`confirmAccess`. The prompt guards the site-level Administration area only, not
journal management — even though Users & Roles is where the destructive user
actions live.

**Undetermined:** whether a non-administrator ever meets this prompt. Non-admins
cannot reach `/admin` at all, so the situation does not arise through the
screens.

---

## 5. Incidental observations · **context**

- **Untranslated locale key on the users list, all three fleets.** The row
  actions button on Users & Roles carries `aria-label="##userAccess.management.options##"`
  — the raw key, not a translation. It is the only handle a screen-reader user or
  a test has for that control.
- **`##common.help##`** renders unlocalised in the page header on every screen
  visited, all three fleets.
- **Scratch contexts do enrol `admin` as Journal manager**, as the skill's
  patterns lesson 13 says — the unfiltered `u53toppojs` list reads
  `Current Users (5)`: `admin admin` (Journal manager, no start date, no
  disabled icon) plus this probe's four. It is easy to miss because the list
  keeps its search phrase across visits, and a `u53top` search hides it.
  Recorded because a count assertion on a scratch context's user list is +1.
- **Disabled users vanish from the Select Reviewer list**, which is how
  `u53top.rv.ojs` stopped appearing there after item 2b.

---

## 6. Proof the seeded `admin` is untouched

Last action of the run, on each fleet: signed in as `admin` through the sign-in
screen, then navigated to `/index.php/index/en/admin`.

| Fleet | Sign-in | "account has been disabled" shown | Administration reached |
|---|---|---|---|
| OJS 8000 | succeeded → `/en/index` | no | yes, `<h1>` = "Administration" |
| OMP 8100 | succeeded → `/en/index` | no | yes, `<h1>` = "Administration" |
| OPS 8200 | succeeded → `/en/index` | no | yes, `<h1>` = "Administration" |

`admin` is enabled, unmerged, and still a site administrator on all three fleets.

---

## 7. Proposed content for the maintainer to write

This probe wrote nothing to `PROGRESS.md`, the atlas, `app-changes.md` or the
spec. The one row it would otherwise have appended:

### Proposed `docs/e2e/app-changes.md` row

| Date | App | Change | Why |
|---|---|---|---|
| 2026-07-29 | shared (`lib/pkp`) | `api/v1/_test/PKPTestApiController.php` — `users[].roles` accepts `siteAdmin`, resolved by `Role::ROLE_ID_SITE_ADMIN` (the site admin group has a null context id and no `nameLocaleKey`) and enrolled via the installer's own `Repo::userGroup()->assignUserToGroup()`; `classes/testing/scenario/schema/user.json` documents it | Test-harness only, inside `/api/v1/_test/*`. No product code touched. Needed because **no screen grants the site administrator role** (`UserForm::saveUserGroupAssignments()` intersects posted group ids with the context's own groups; the admin group is in no context), so the installer's read-only `admin` was the only administrator an install had and every administrator-behaviour question was undetermined |

Nothing else in this run changed app code. The `config.test.inc.php` edit of §4.0
is restored and byte-identical; it is a probe measurement, not a change.
