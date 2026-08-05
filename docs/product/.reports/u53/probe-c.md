# U53 — Users management · probe report C (groups G5 + G7)

**Frame.** QA documentation of an application's own screens, on a local disposable
test install with seeded accounts. Signed in as each role and used the screens the
way that role would — including typing a URL directly to reach one. Recorded what
the screen offers, what happens when it is used, and where the two disagree.
Nothing here constructed a request the screens themselves would not send.

**Agent:** probe C (RUNBOOK step 3). **Items:** probe-list G5 (15, 16) and G7 (26)
— 3 of 3 executed. **Date:** 2026-07-28.
**Fleets:** OJS `127.0.0.1:8000` · OMP `127.0.0.1:8100` · OPS `127.0.0.1:8200`.

**G5's boundary, as briefed.** G5 is the *offered* half. Where an item's question
needs the server's answer to a request the screen would only send on a confirmed
destructive action, this report records the offer, marks the rest undetermined,
and names the one observation that would settle it. Those executions belong to G6
(items 23–24) and were not performed.

## Staging (all created by this probe; nothing seeded was mutated)

Namespace prefix `u53pc`. Scratch contexts minted through
`POST /api/v1/_test/scenarios/context`:

| App | Context path | Accounts created (all throwaway) |
|---|---|---|
| OJS | `u53pcaojs` (U53PC Scratch A ojs) | `u53pcmgrojs` "Mira Manager" `u53-15-mgr-ojs@mail.test` (Journal manager) · `u53pcdupeojs` "Dana Dupe" `u53-16-dupe-ojs@mail.test` (Author) · `u53pcsurvojs` "Sven Survivor" `u53-16-surv-ojs@mail.test` (Reader) |
| OMP | `u53pcaomp` | same three, `-omp` suffix, roles Press manager / Author / Reader |
| OJS | `u53pcbojs` (Scratch B) | `u53pcm1ojs` "Nora Nonadmin" `u53-15-m1-ojs@mail.test` (Journal manager) · `u53pcm2ojs` "Otto Othermgr" `u53-15-m2-ojs@mail.test` (Journal manager) |
| OMP | `u53pcbomp` | same two, `-omp` suffix |

**Two staging facts a reader needs.**

1. *(context)* Item 15 requires the site administrator to hold a role in the
   scratch context. It was given one by naming `admin` in the scenario spec's
   `users[]`; the endpoint reuses an existing account by username and only adds a
   **context-scoped** enrolment, so nothing about the seeded `admin` account
   outside these scratch contexts changed, and `publicknowledge` was not touched.
2. *(context)* Independently of that, the app itself enrols the creating user as
   a manager of a newly created context — the scenario endpoint builds the
   context acting as the site administrator, exactly as the admin's own
   create-journal flow does. So in every scratch context above, `admin`'s row
   reads "Journal manager" (OMP: "Press manager"). This is the ordinary state of
   a journal an administrator just created, not an artefact of the harness.
   Scratch B exists precisely so the confound this creates can be controlled for
   (see item 15's control).

Mailpit was not queried or cleared by this probe (no item in G5/G7 makes an
email-delivery claim). `publicknowledge` and the seeded roster were read-only:
`admin` and `manager.maya` were signed in as and looked at, never edited.

---

# G5 — Offered vs server: the read-only half

## Item 15 [P2] — what a manager is offered on a site administrator's row

**Apps:** OJS + OMP (cross-app, both driven by this agent).
**Role signed in as:** the scratch context's own throwaway Journal/Press manager
(`u53pcmgrojs` / `u53pcmgromp`; scratch-B control as `u53pcm1ojs` /
`u53pcm1omp`).
**Screen:** Users & Roles →`Current Users` table.
URL typed directly: `http://127.0.0.1:8000/index.php/u53pcaojs/en/management/settings/access`
(OMP: `:8100/index.php/u53pcaomp/…`; scratch B: `…/u53pcbojs/…`, `…/u53pcbomp/…`).

**What I did.** Signed in through `form#login`. Opened the Users & Roles URL.
Located the Vue users table as
`page.locator('table').filter({has: page.locator('th', {hasText: 'Roles'})})`
(the page also carries an Invitations table whose header set overlaps —
filtering on `Affiliation` alone selects the wrong table). Opened each row's
options menu with
`row.locator('td').last().locator('button').first()`
and read the menu with `page.locator('[role=menuitem], [role=menu] button, [role=menu] a')`.
**Menus were opened only; nothing was chosen.**

### CLAIM — the site administrator's row (OJS and OMP, identical)

Scratch A, viewed by the throwaway manager. Menu on `admin` (`admin@example.org`),
verbatim, in screen order:

> Edit · Email · Remove User · Disable User

**Merge user and Login As are not present.** Remove User and Disable User are.

### CLAIM — ordinary rows in the same list, same viewer (control)

Menu on Dana Dupe and on Sven Survivor, verbatim, in screen order — identical on
both rows and both apps:

> Edit · Email · Login As · Remove User · Disable User · Merge user

### CLAIM — the isolating control (scratch B): a plain manager's row

The scratch-A administrator holds a manager role, so "manager peer" and "site
administrator" are confounded there. Scratch B removes the confound: three
Journal/Press managers in one context, one of them the site administrator.
Signed in as Nora Nonadmin (plain manager), the three menus read:

| Row | Roles cell | Menu offered, verbatim |
|---|---|---|
| `admin` (site administrator) | Journal manager | Edit · Email · Remove User · Disable User |
| Otto Othermgr (plain manager) | Journal manager | Edit · Email · Login As · Remove User · Disable User · Merge user |
| Nora Nonadmin (the viewer's own row) | Journal manager | Edit · Email |

Identical on OMP (Roles cell reads "Press manager").

So the withholding of Login As and Merge user tracks **site-administrator status**,
not the manager role: a peer manager's row offers both.

### Settles

- **Register A3, site-admin-target half — the Merge/Login As part: CORRECTED
  relative to the draft's suspicion.** A manager is *not* offered Merge user or
  Login As on a site administrator's row, on either app. There is no dangling
  offer for those two actions here.
- **The Disable half: the offer is confirmed, the refusal is UNDETERMINED.**
  "Disable User" (and "Remove User") *are* offered to a manager on a site
  administrator's row, in both apps. Whether the server accepts or refuses them
  is not established here, because settling it means choosing a destructive
  action on a row this group is instructed not to act on.
  **The one observation that would settle it:** as a manager, choose "Disable
  User" on a *throwaway site administrator's* row and record what the screen
  renders — which is G6's territory (items 24–25) and depends on item 25's
  precondition that a throwaway site administrator can be minted at all.

### Locators asserted about

- users table: `table` filtered by `th` with text `Roles`
- row options button: `tr td:last-child button` (first)
- menu items: `[role=menuitem]`

---

## Item 16 [P3, P5 offer-side] — the merge target grid

**App:** OJS (per the item). **Roles signed in as:** the seeded site
administrator `admin`, and — as an in-scope offer-side extension — the scratch
context's throwaway Journal manager `u53pcmgrojs`.
**Screen:** Users & Roles list → row menu → "Merge user" → the legacy modal.
Reached from `http://127.0.0.1:8000/index.php/u53pcaojs/en/management/settings/access`.

**What I did.** Opened the row menu on **Dana Dupe** (a throwaway) and chose
`page.getByRole('menuitem', {name: 'Merge user'})`. Read the modal
(`page.locator('[role=dialog]').last()`), enumerated every row of the grid inside
it and every row action on those rows, screenshotted, then **closed the modal with
its Close button without choosing anything**. Verified afterwards that
`page.locator('[role=dialog]').count()` was 0 and the list page was unchanged.
No merge was initiated.

### CLAIM — the modal's shape

- Modal title (`h1`), verbatim: **"Merge user"**
- The grid inside it carries a header (`h4`), verbatim: **"Merge into this User"**
- The grid header row also offers two grid-level actions, verbatim:
  **"Search"** and **"Add User"**
  (`a#component-grid-settings-user-usergrid-search-button-…`,
  `a#component-grid-settings-user-usergrid-addUser-button-…`)
- Grid columns, verbatim in order: **Given Name · Family Name · Username ·
  Roles · Email**
- Footer count line, verbatim: **"1 - 4 of 4 items"**
- The per-row action's label, verbatim: **"Merge into this User"**
  (the draft writes it "Merge Into User" — the on-screen string is
  "Merge into this User")

### CLAIM — which rows the grid offers the action on

Signed in as **`admin`** (site administrator), merging *from* Dana Dupe:

| Grid row (`tr` id) | Who | "Merge into this User" offered? |
|---|---|---|
| `…-usergrid-row-1` | admin — the site administrator, **and the signed-in user** | **yes** |
| `…-usergrid-row-50` | Mira Manager (Journal manager) | yes |
| `…-usergrid-row-51` | **Dana Dupe — the duplicate itself** | **no** |
| `…-usergrid-row-52` | Sven Survivor (Reader) | yes |

Signed in as **`u53pcmgrojs`** (plain Journal manager), merging *from* the same
Dana Dupe — the grid is identical, row for row:

| Grid row | Who | "Merge into this User" offered? |
|---|---|---|
| `…-row-1` | **admin — a site administrator** | **yes** |
| `…-row-50` | Mira Manager — **the signed-in user's own account** | **yes** |
| `…-row-51` | Dana Dupe (the duplicate) | no |
| `…-row-52` | Sven Survivor | yes |

Three "Merge into this User" links in each case
(`a.pkp_linkaction_mergeUser`, count 3).

### CLAIM — how the duplicate's own row is excluded

The duplicate is **listed** in the target grid, with all five of its cells
(Dana · Dupe · `u53pcdupeojs` · Author · `u53-16-dupe-ojs@mail.test`). What it
lacks is the row action: its `<tr>` carries class `gridRow` without `has_extras`,
and no `-control-row` sibling is emitted for it, so no "Merge into this User"
appears. Every other row carries `gridRow has_extras` plus a `…-control-row`
holding the action.

### Settles

- **"the second list's title (Merge into this User)": CONFIRMED**, as the grid
  header inside a modal whose own title is "Merge user".
- **"the duplicate's own row is absent from it": CORRECTED.** The duplicate's row
  is present and fully rendered; only its row action is withheld. A reader of the
  screen sees the duplicate listed among the candidates.
- **"the administrator's own account is absent as a target": CORRECTED, twice
  over.** (a) The signed-in administrator's own account is listed *and* offers
  "Merge into this User". (b) The signed-in **manager's** own account is likewise
  listed and offers it. There is no self-exclusion on the target side at all —
  the only excluded row is the merge's source.
- **Register A3 / P3, the merge offer half: CONFIRMED as an offer.** A Journal
  manager is offered "Merge into this User" on a **site administrator's** row
  inside this grid — the same administrator whose row menu, one screen earlier,
  withholds "Merge user" from that same manager (item 15). The two surfaces
  disagree with each other about the same pair of accounts.
- **Whether the server accepts any of those offers: UNDETERMINED here** — settling
  it means confirming a merge, which permanently deletes an account and is G6's
  item 22/23. **The one observation that would settle the sharp case:** as a
  Journal manager, choose "Merge into this User" on a throwaway *site
  administrator* target with a throwaway duplicate as the source, and record what
  the screen renders after the confirmation.
- **P5 (does any confirmation say the old account ceases to exist): not reached
  by this item** — no confirmation is shown before "Merge into this User" is
  chosen, and choosing it is item 22's step.

### Locators asserted about

- modal: `[role=dialog]` (last)
- modal title: `[role=dialog] h1`
- grid header: `[role=dialog] .pkp_controllers_grid > .header` (and its `h4`)
- row action: `a.pkp_linkaction_mergeUser` — note these anchors carry **no
  `href`**, so `getByRole('link', {name: 'Merge into this User'})` matches
  **nothing**; a test must locate them by class or by text on `a`.
- close: `[role=dialog]` → `getByRole('button', {name: /close/i})`

---

# G7 — Email-templates roster

## Item 26 [P7, register OPS1] — which of this feature's emails the Emails screen lists

**Apps:** OJS + OMP + OPS (cross-app, all three driven by this agent).
**Role signed in as:** `manager.maya` in each app (read-only browsing).
**Screen:** Settings → Workflow → **Emails** tab → "Add and edit templates" →
the **Manage Emails** page.

**The door (recorded in all three apps).** From
`/index.php/publicknowledge/en/management/settings/workflow`, the tab strip
contains a tab named **Emails** (`getByRole('tab', {name: 'Emails', exact: true})`).
Its panel holds exactly one link, text verbatim **"Add and edit templates"**,
href `…/index.php/publicknowledge/en/management/settings/manageEmails`. Same tab,
same link text, same target in OJS, OMP and OPS. The Manage Emails page's own
`h1`s read **"Manage Emails"** and **"Emails"**; browser title e.g.
"Manage Emails | Journal of Public Knowledge".

**What I did.** Opened the Manage Emails page in each app, enumerated every listed
row (`.listPanel__item`), then typed search terms into the page's own search box
(`input[type=search]`, placeholder verbatim **"Search by name or description"**)
and re-enumerated. Two rows were the targets:

- **"User Role Ended Notification"** (the role-ended notice)
- **"User Role Masthead Visibility Update Notification"** (the masthead-change notice)

### CLAIM — presence per app

| App | Rows listed, unfiltered | "User Role Ended Notification" | "User Role Masthead Visibility Update Notification" |
|---|---|---|---|
| OJS | 67 | **present** | **present** |
| OMP | 57 | **present** | **present** |
| OPS | 18 | **absent** | **absent** |

Searching **"Role"** returns, verbatim, the same three rows on OJS and on OMP:
"User Invited to Role Notification", "User Role Ended Notification", "User Role
Masthead Visibility Update Notification". Searching **"Masthead"** returns exactly
one row on each: "User Role Masthead Visibility Update Notification".

On both OJS and OMP each of those rows carries an action whose accessible text is
"Edit " + the row name (e.g. "Edit User Role Masthead Visibility Update
Notification"), and the row's description line reads, verbatim:
"The email notification sent to a user when they are removed from a role." /
"The email notification sent to a user when their masthead visibility is updated
for a role." *(context — the row's own behaviour belongs to the Emails feature,
not this one.)*

### CLAIM — the OPS absence, with its positive control

Absence on OPS is recorded as an install fact, with the screen proved working
first. The unfiltered OPS list holds **18** rows, verbatim:

> Discussion (Production) · Moderator Assigned (Auto) · New Announcement · New
> Version Created · New Version Posted · Notify Other Authors · Password Reset
> Confirm · Posted Acknowledgement · Reinstate Submission Declined Without Review
> · Statistics Report Notification · Submission Accepted · Submission
> Acknowledgement (No Moderation Required) · Submission Acknowledgement (Pending
> Moderation) · Submission Confirmation (Other Authors) · Submission Declined ·
> User Created · Validate Email (Server Registration) · Validate Email (Site)

Positive controls on the **same** search box in the **same** session: searching
"Notification" returns 1 row ("Statistics Report Notification"); searching "User"
returns 4 rows ("Password Reset Confirm", "User Created", "Validate Email (Server
Registration)", "Validate Email (Site)"). Searching "Role" returns 0 rows and
"Masthead" returns 0 rows. The screen and its search work; the two templates are
simply not part of what an OPS install offers here. (No row named "User Invited
to Role Notification" is offered on OPS either.)

### Settles

- **Register OPS1, listing half — the OPS clause: CONFIRMED.** A manager on an OPS
  install finds neither the role-ended notice nor the masthead-change notice on
  the Emails screen.
- **The OJS clause: CONFIRMED.** Both are listed.
- **The OMP clause: CORRECTED.** The draft expects OMP to list the role-end one
  and *not* the masthead one ("its masthead template is absent from the install
  per A6"). On the running OMP install a manager finds **both** rows, and the
  masthead row is reachable by searching "Masthead" like any other. Whatever A6
  concludes about the masthead *notice being sent* on OMP, the **row on the
  Emails screen is there**.
- *(context, repo file rather than a screen observation, offered only as a
  pointer for whoever writes the register entry)*: `registry/emailTemplates.xml`
  in the OJS repo declares both `USER_ROLE_END` and `USER_ROLE_MASTHEAD_UPDATE`;
  the OMP and OPS repos' copies declare only `USER_ROLE_END`. Neither of those
  facts predicts what the screen showed above — OMP lists a row its registry does
  not declare, OPS lists no row for one its registry does. The screen is the
  observation of record; this line exists so nobody re-derives the wrong answer
  from the XML.

### Locators asserted about

- tab: `getByRole('tab', {name: 'Emails', exact: true})` on
  `…/management/settings/workflow`
- link out of the tab: the tab panel's only `a`, text "Add and edit templates"
- list rows on Manage Emails: `.listPanel__item`
- search box: the **visible** `input[type=search]` (the page also carries a hidden
  `input[type=search]` with placeholder "Search submissions" from the surrounding
  chrome — matching `input[type=search]` unqualified selects the wrong one and
  times out on `fill`)

---

# Incidental (outside G5/G7 — recorded in one line each, not investigated)

- **Item 8 / register A4 (G2's):** the row-options button on the Users & Roles
  table exposes `aria-label="##userAccess.management.options##"` — the raw
  untranslated key wrapped in `##` — on both OJS and OMP, on every row inspected.
- **Item 9 / P10 (G2's):** the signed-in manager's **own** row menu offers exactly
  "Edit" and "Email" and nothing else, on OJS and OMP.
- **Item 5 (G2's):** the Users & Roles heading renders with a live count, verbatim
  "Current Users (4)"; the page also shows "Invitations (0)" above it. Columns
  observed: Name · Email · Roles · Start Date · Affiliation · More Actions.
- **Item 5, Start Date column:** in scratch B the site administrator's Start Date
  cell is **empty** while every throwaway's shows `2026-07-28`. The administrator
  there was enrolled by the app itself as the context's creator.
- **Item 7 / register A5 (G2's):** the Users & Roles search box placeholder reads,
  verbatim on OJS and OMP, "Enter a user's name, role (e.g Journal editor), or
  affiliation" — on OMP too, i.e. it names "Journal editor" on a press.

# Blockers

None. All three items executed on every app they name.
