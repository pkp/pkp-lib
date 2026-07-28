# U6 — User invitations · probe report, GROUP A (Access doors & role gates)

**Frame.** QA documentation of an application's own screens, on a local
disposable test install with seeded accounts. Signed in as each role and used
the screens the way that role would — including typing a URL directly to reach
one. Records what the screen offers, what happens when it is used, and where the
two disagree. No request was constructed that the screens themselves would not
send: browser only, no hand-built API calls, no altered form payloads, no
credentials carried between sessions.

**Date**: 2026-07-28 · **Agent**: Opus probe (RUNBOOK step 3) · **Scope**: probe
list items A-1 … A-5 only (cross-app: OJS :8000 · OMP :8100 · OPS :8200, all
driven by this one agent). Anything noticed outside Group A is under
"Incidental" at the end and is **not** an item result.

**Coverage**: 5 of 5 items covered. 0 undetermined. Nothing blocked.

---

## Environment as observed

- Fleets answer on `http://127.0.0.1:8000` (OJS), `:8100` (OMP), `:8200` (OPS).
  `http://localhost:...` returns HTTP 400 on all three; `127.0.0.1` works. All
  three serve a context at url path `publicknowledge`.
- Seeded roster is shared; per-app role labels differ (users table, as `admin`,
  Settings → Users & Roles → Users, read-only):

  | user | OJS role shown | OMP role shown | OPS role shown |
  |---|---|---|---|
  | `sectioneditor.ana` | Section editor | Series editor | Moderator |
  | `assistant.rita` | Funding coordinator | Funding coordinator | **Editorial Board Member** |
  | `reader.rosa` | Reader | Reader | Reader |

  **Install fact (claim)**: OPS *does* seed `assistant.rita` — in the
  **Editorial Board Member** group, whose Permission level reads **Assistant**
  (Roles tab, see A-4). The probe list's parenthetical ("OPS seeds NO assistant
  group — skip that half there") is **corrected**: the assistant half ran on OPS
  too and behaved exactly as on OJS/OMP.
- `publicknowledge` shows 18 users on OJS and OMP, 9 on OPS.
- **Scratch contexts created for this probe** (scenario endpoint
  `POST /api/v1/_test/scenarios/context`, `X-Test-Key: playwright-test-key`),
  same url paths in all three apps unless noted:
  - `u6ascratch` (tag `u6a`) — users `u6a.mgr` (manager), `u6a.sed` (sectionEditor). Used for A-4's Roles-tab reads.
  - `u6gadm` (tag `u6ga-adm`) — user `ga.mgr` (manager). Used for the first A-1 isolation attempt.
  - `u6gadm2` (tag `u6ga-adm2`) — users `admin` (**reader**), `ga2.mgr` (manager). The A-1/A-2 site-admin isolation context.
  - `u6gwalk` (tag `u6ga-walk`) — `gw.sed` (sectionEditor), `gw.mgr` (manager), plus `gw.asst` (funding) on OJS/OMP only. Used for A-5's completed walks.
  - `u6grole` (tag `u6ga-role`, OJS + OMP only) — `gr.ed` (`editor` → the *Journal editor* / *Press editor* group, permission level Journal/Press Manager), `gr.mgr` (manager). Used for A-4's permission withdrawal.
  - Throwaway passwords follow the harness rule (username doubled).
- **Invitations actually sent** (scratch contexts only, so other agents can scope
  Mailpit around them): to `ga-sed2-ojs@u6groupa.test` (OJS), `ga-sed-omp@…`
  (OMP), `ga-sed-ops@…` (OPS), and one earlier OJS send to
  `ga-sed-ojs@u6groupa.test`. Mailpit was never cleared.

**The one refusal wording seen everywhere.** Every hard refusal in Group A is the
same page, in all three apps: the browser lands on
`/{ctx}/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`,
rendered in the *reader-facing* theme (site header, "Home /" breadcrumb, no
`<h1>` text), with the single sentence:

> **The current role does not have access to this operation.**

Locator used throughout: `page.locator('body').innerText()` on the landed page,
plus `page.url()`. Called "**the refusal page**" below.

---

## A-1 — Site Administrator at `/{ctx}/management/settings/access`

*(P5 · register A4 · fn-a)* · **CROSS-APP** · Apps: OJS, OMP, OPS.

### A-1a — as seeded `admin` in `publicknowledge` (read-only visit)

Typed `/index.php/publicknowledge/management/settings/access` into the address
bar. **All three apps**: HTTP 200, final URL `…/en/management/settings/access`,
page title `Users & Roles | <context name>`.

- `page.locator('h1')` → `["Users & Roles"]` (all three).
- `page.locator('button.pkpTabs__button')` → `["Users","Roles","Site Access Options","ORCID"]` (all three). (`aria-selected` was read in A-3, not here.)
- Invitations heading present: `page.getByText('Invitations', {exact:true})` → 1; on-screen text reads **"Invitations (0)"**.
- `page.getByRole('button', {name:'Invite to a role'})` → 1, visible.

**Claim**: the seeded site administrator reaches the Users & Roles screen with
the Invitations table and the "Invite to a role" button in **all three apps** —
no refusal, no redirect.

**Confound found, and removed in A-1b (claim)**: the users table on that same
screen lists `admin admin / admin@example.org` with role **Journal manager**
(OJS) / **Press manager** (OMP) / **Preprint Server manager** (OPS). The seeded
site admin is *also* a manager of the context, so A-1a alone cannot tell which
hat admits them. The same is true of any scratch context: creating one enrols
the creating site admin as its manager (observed on `u6ascratch`, `u6gadm`,
`u6gadm2`, `u6gwalk`, `u6grole` — `admin admin` appears in every one's Current
Users list with the manager role).

### A-1b — isolation: a site administrator with **no** manager role in the context

Setup, entirely through the screens: in scratch context `u6gadm2` the site admin
holds two roles (manager, from creating the context; Reader, seeded). Signed in
as `admin`, opened the users table row for `admin admin` → row menu → **Edit**
(the users-table row menu button carries `aria-label="##userAccess.management.options##"`;
its menu items are `Edit` and `Email`). The edit wizard opens at
`/{ctx}/management/settings/user/1`. Pressed **Remove Role** on the manager row;
confirmation dialog (locator `[role=dialog]`) read verbatim:

> **Remove Role**
> Are you sure you want to remove this role? The user will lose access and permissions associated with it.
> [Remove Role] [Cancel]

Confirmed. The role row then showed an end date of today and its action changed
to the non-button text **"User Removed From Role"**. The users table afterwards
lists `admin admin … Reader`. (First attempt, in `u6gadm`, where the site admin
held *only* the manager role, was refused — see Incidental #1.)

Then typed `/index.php/u6gadm2/management/settings/access`:

| App | Result | Locators / wording |
|---|---|---|
| **OJS** | **Admitted.** `h1` = `["Users & Roles"]`; tabs as above; "Invitations (0)"; `getByRole('button',{name:'Invite to a role'})` → 1 (visible; see Incidental #2 for why an initial count read 0). Pressing it opens the wizard at `/{ctx}/invitation/create/userRoleAssignment`. | as listed |
| **OMP** | **Refused.** Landed on the refusal page. `getByText('Invitations',{exact:true})` → 0; invite button → 0. | "The current role does not have access to this operation." |
| **OPS** | **Refused.** Identical to OMP. | same sentence |

**Settles A4 — CONFIRMED, with the mechanism made visible.** The draft's claim
("on OMP and OPS the code reads as refusing them") holds *for a site
administrator who is not a manager of that context*: OJS admits them to
`/{ctx}/management/settings/access`, OMP and OPS turn them away with the refusal
page. The reason it is easy to miss on a stock install is that the site admin is
enrolled as manager in every context they create, which masks the divergence
(A-1a).

---

## A-2 — the older address `/{ctx}/management/access`

*(P12 · register A2 · f-a2)* · **CROSS-APP** · Apps: OJS, OMP, OPS.

Typed `/index.php/publicknowledge/management/access` (and the scratch-context
equivalents) into the address bar.

| Role signed in as | App | Result at `/management/access` |
|---|---|---|
| `admin` (site admin, also manager of the context) | OJS / OMP / OPS | **Admitted** — `h1` `["Users & Roles"]`, tabs `["Users","Roles","Site Access Options","ORCID"]`, "Invitations (0)", `getByRole('button',{name:'Invite to a role'})` → 1 |
| `admin` **with no manager role in the context** (`u6gadm2`, after A-1b) | OJS / OMP / OPS | **Admitted in all three** — same page, same Invitations heading, same button; pressing it opens the wizard at `/{ctx}/invitation/create/userRoleAssignment` (verified on OJS, OMP and OPS) |
| `manager.maya` (seeded manager, `publicknowledge`) | OJS / OMP / OPS | **Refused** — the refusal page |
| `u6a.mgr` (scratch manager, `u6ascratch`) | OJS / OMP / OPS | **Refused** — the refusal page |

**Claim (settles A2 — CONFIRMED, and sharper than the draft).** The two
addresses open the same screen but admit **disjoint** sets on this install:

- `/{ctx}/management/settings/access` admits managers in all three apps, and a
  site administrator on OJS only (A-1b).
- `/{ctx}/management/access` admits a site administrator in **all three** apps —
  including OMP and OPS, where the normal address refuses that same person — and
  **refuses every manager**, in all three apps.

So on OMP and OPS the older address is the *only* door a site administrator can
use to reach the Invitations table, and on all three apps it is a door a journal
/press/server manager cannot use at all.

**Context (not promotable)**: on `/management/access` the left-hand Settings menu
renders without its sub-items (the body text goes `… Settings / Content /
Statistics / Tools …`), whereas on `/management/settings/access` it renders
`Settings / Journal (Press, Server) / Website / Workflow / Distribution / Users
& Roles`. Noticed in `body.innerText()`; not separately verified.

---

## A-3 — the Manager's baseline: menu → Users & Roles → Users

*(fn-a, fn-b)* · **CROSS-APP** · Apps: OJS, OMP, OPS · role: seeded
`manager.maya`, context `publicknowledge`, read-only.

Path driven from the dashboard, no typed URL: landed on
`/{ctx}/dashboard/editorial`, clicked the left-menu item **Settings**
(an `<a href="#">` that expands a submenu — locator: first visible `a` whose
trimmed text is exactly `Settings`), then the revealed **Users & Roles** link.

- The submenu link's `href` is `…/index.php/publicknowledge/en/management/settings/access` in all three apps.
- Landed there; `h1` → `["Users & Roles"]`.
- Tabs (`button.pkpTabs__button`) → `["Users","Roles","Site Access Options","ORCID"]`; **Users** is `aria-selected="true"` on arrival — no click needed.
- Invitations heading: an `<h3>` whose text is **"Invitations (0)"**.
- `getByRole('button', {name:'Invite to a role'})` → 1, visible, `<button>`.
- **Layout, measured**: the button's top and the "Invitations (0)" heading's top are the same (both 228px in a 1280×720 viewport); the table starts at 280px. So the button sits on the **same band as the heading, at its right**, above the table — not on a line of its own above the heading. Screenshot: `a3c-<app>.png` (scratch dir).
- Invitations table headers (`th` of the first table): `["NAME","EMAIL","INVITATIONS","STATUS","AFFILIATION","MORE ACTIONS"]`; body reads "No Items".
- Pressing the button navigates to `/{ctx}/invitation/create/userRoleAssignment` and shows the wizard: `h1` "Invite user to take a role", intro line **"You are inviting a user to take a role in OJS along with appearing in the journal masthead"** (OMP: "…in OMP along with appearing in the press masthead"; OPS: "…in OPS along with appearing in the server masthead"), step list "1 Search User / 2 Enter details / 3 Review & invite for roles", step heading "STEP 1 - Search User".

**Claim (settles the Actors table's baseline row)**: in all three apps a
Journal/Press/Preprint-Server Manager reaches Users & Roles from the menu, lands
on the Users tab, sees the "Invitations (…)" table, and the "Invite to a role"
button is present and opens the wizard.

---

## A-4 — withdrawing a role's settings permission on the Roles screen

*(P10 · fn-a)* · **CROSS-APP** · Apps: OJS, OMP, OPS.

### A-4a — what the Roles screen offers for the manager-slot role

As `admin`, Users & Roles → **Roles** tab, scratch context `u6ascratch`. The
grid is headed **"Current Roles"** with columns `Role Name · Permission level ·
Submission · Review · Copyediting · Production`, and a "Search" and "Create New
Role" button. Each row has a disclosure control (`Settings`) that reveals
`Edit` / `Remove`.

**Claim**: the row for the app's manager-slot role has **no disclosure control
and no action row at all** — in every app:

| App | Role Name | Permission level | row offers Settings/Edit/Remove? |
|---|---|---|---|
| OJS | **Journal manager** | Journal Manager | **no** (`tr.gridRow` without `has_extras`, no `…-control-row`, zero `<a>` in the row) |
| OMP | **Press manager** | Press Manager | **no** |
| OPS | **Preprint Server manager** | Manager | **no** |

Every other role row in every app does have it. Full Roles inventory as
displayed (claim — read off the grid):

- **OJS** (18): Journal manager · Journal editor · Production editor (all *Journal Manager*); Section editor · Guest editor (*Section Editor*); Copyeditor · Designer · Funding coordinator · Indexer · Layout Editor · Marketing and sales coordinator · Proofreader · Editorial Board Member (*Assistant*); Author · Translator (*Author*); Reviewer (*Reviewer*); Reader (*Reader*); Subscription Manager (*Subscription Manager*).
- **OMP** (19): Press manager · Press editor · Production editor (*Press Manager*); Series editor (*Series Editor*); Copyeditor · Designer · Funding coordinator · Indexer · Layout Editor · Marketing and sales coordinator · Proofreader · Editorial Board Member (*Assistant*); Author · Volume editor · Chapter Author · Translator (*Author*); Internal Reviewer · External Reviewer (*Reviewer*); Reader (*Reader*).
- **OPS** (5): Preprint Server manager (*Manager*); Moderator (*Moderator*); Author (*Author*); Reader (*Reader*); Editorial Board Member (*Assistant*).

Screenshot of the OJS grid: `a4-roles.png` (the "Journal manager" row is the only
one without the ▶ triangle).

**Per the item's stop clause, that is the answer for the literal manager-slot
role in all three apps: the Roles screen offers no way to withdraw its settings
permission.** For **OPS** this is the end of the item — *Preprint Server
manager* is the only role at Manager permission level, so there is no editable
manager-level role to test with.

### A-4b — the same withdrawal on another manager-level role (OJS, OMP)

OJS and OMP each ship a second role at manager permission level that *is*
editable. Ran the item's intent there, in scratch context `u6grole`:

Role under test: **Journal editor** (OJS) / **Press editor** (OMP), permission
level Journal Manager / Press Manager; test user `gr.ed` holds only that role.

1. **Before** (positive control): signed in as `gr.ed`, typed
   `/index.php/u6grole/management/settings/access` → **admitted**; `h1`
   `["Users & Roles"]`, "Invitations (0)", `getByRole('button',{name:'Invite to a role'})` → 1.
2. As `admin`: Roles tab → the role's `Settings` disclosure → **Edit**. A modal
   titled **"Edit"** opens. Its content, verbatim:

   > Role details
   > Permission level* — [Journal Manager | Section Editor | Assistant | Author | Reviewer | Reader | Subscription Manager]  *(OMP: Press Manager | Series Editor | Assistant | Author | Reviewer | Reader)*
   > Role Name*
   > Abbreviation*
   > Role Options
   >  ☐ Allow user self-registration
   >  ☐ This role is only allowed to recommend a review decision and will require an authorised editor to record a final decision.
   >  ☐ Permit submission metadata edit.
   >  ☐ Consider role in masthead list
   >  ☑ **Permit changes to Settings**
   > Required fields are marked with an asterisk: *
   > [Cancel] [OK]

   Locator for the control: `input#permitSettings` — present (count 1) and
   **checked** on arrival for both roles. Unchecked it; saved with the modal's
   **OK** button.
3. **After**: signed in as `gr.ed`, typed the same address → **refused**, the
   refusal page, in both apps. Invitations heading → 0, invite button → 0.
4. **Control after the change**: `gr.mgr` (plain Journal/Press manager, untouched
   role) → still **admitted**, "Invitations (0)" and the invite button present.

**Claim (settles the Actors table's condition "when their role is allowed to
access settings")**: withdrawing **"Permit changes to Settings"** from a
manager-level role takes that role's holders off the Users & Roles screen
entirely — they get the refusal page, so they lose the Invitations table and the
"Invite to a role" button with it. Verified on OJS and OMP; on OPS the screen
offers no editable manager-level role to try it on.

**Context (read-only, not an item result)**: on OPS the *Moderator* role's Edit
modal does offer the same `input#permitSettings` checkbox, **unchecked**, and its
Permission level list is `[Manager | Moderator | Assistant | Author | Reviewer |
Reader]`. Modal opened and cancelled; nothing saved.

---

## A-5 — Section Editor and Assistant at the wizard and the table

*(P9 · register A1 · f-a1)* · **CROSS-APP** · Apps: OJS, OMP, OPS.

### A-5a — typing the two addresses, seeded roles, `publicknowledge` (read-only)

| Role | App | `/{ctx}/invitation/create/userRoleAssignment` | `/{ctx}/management/settings/access` |
|---|---|---|---|
| `sectioneditor.ana` (Section editor / Series editor / Moderator) | OJS, OMP, OPS | **Wizard opens.** `h1` = "Invite user to take a role"; step list "1 Search User / 2 Enter details / 3 Review & invite for roles"; "STEP 1 - Search User" | **Refused** — the refusal page |
| `assistant.rita` (Funding coordinator / Funding coordinator / Editorial Board Member) | OJS, OMP, OPS | **Wizard opens** — identical chrome and step list | **Refused** — the refusal page |

Identical in all three apps. Locators: `page.locator('h1')`,
`page.getByRole('button',{name:'Invite to a role'})` (→ 0 on the refusal page),
`page.url()`.

### A-5b — using the wizard, seeded roles, `publicknowledge` (search step only, read-only)

Filled the step-1 field (`page.locator('input')` first visible) with an address
matching no user and pressed **Search User**
(`getByRole('button',{name:'Search User',exact:true})`):

- **Section Editor** — advances: URL gains `#userDetails`, heading becomes
  **"STEP 2 - Enter details and invite for roles"**, two `<select>`s appear.
  Same on OJS, OMP and OPS. No dialog.
- **Assistant** — does **not** advance. Stays on "STEP 1 - Search User" and an
  error dialog appears (`[role=dialog]`), verbatim:

  > **Error**
  > The current role does not have access to this operation.
  > [OK]

  Same on OJS, OMP and OPS.

**Claim**: the wizard opens for both roles in all three apps, but only the
Section Editor gets past its first step; the Assistant is stopped at the search
step by an Error dialog carrying the same sentence as the refusal page.

### A-5c — walking the wizard to completion as a Section Editor (scratch context `u6gwalk`)

As `gw.sed` (holds only the section-editor-slot role), in each app:

1. Opened `/index.php/u6gwalk/invitation/create/userRoleAssignment`. Buttons on
   arrival: `["1 Search User", "Cancel", "Search User"]` (step tabs 2 and 3 are
   text, not buttons, at this point).
2. **Cancel first, as a separate run**: pressing **Cancel**
   (`getByRole('button',{name:'Cancel',exact:true})`) on step 1 navigates
   straight to **the refusal page** — no confirmation dialog
   (`[role=dialog]` → `[]`). **Verified on OJS, OMP and OPS, and for the
   Assistant role too** (`gw.asst`, OJS + OMP).
3. Fresh run, walked it: entered an unused address, **Search User** → step 2
   ("STEP 2 - Enter details and invite for roles"; on OJS the line under it read
   **"The user does not have a role in this journal"** — the OMP/OPS wording of
   that one line was not captured, and the glossary seam is Group C's C-21).
   The Email field
   (`input#userDetails-inviteeEmail-control`) arrives **pre-filled** with the
   searched address. Chose a role, filled Start Date
   (`input#-dateStart-control`, `type=date`) and the masthead selector, pressed
   **Save And Continue** → step 3 ("STEP 3 - Modify email shared with the
   user"), buttons `Cancel · Back · Invite user to the role`.
4. Pressed **"Invite user to the role"**. A dialog appears, verbatim (OJS):

   > **Invitation Sent**
   > ga-sed2-ojs@u6groupa.test has been invited to new role in OJS. You can be updated about the user's decision on the Users & Roles page, your OJS notifications and/or your email
   > [View All Users]

   OMP and OPS: identical wording with "in OMP" / "your OMP notifications" and
   "in OPS" / "your OPS notifications".
5. Pressed **"View All Users"** → **the refusal page**, in all three apps.

**Claim (settles A1 — CONFIRMED, both halves)**: a Section Editor who types the
wizard's address gets a fully working wizard — search, details, email composer,
send — and *both* of its exits, **Cancel** and the success dialog's **View All
Users**, land on "The current role does not have access to this operation."
The invitation is nonetheless created and sent. An Assistant gets the same
wizard chrome but cannot get past its search step, and their **Cancel** lands on
the same refusal page.

**Claim (role list offered to a Section Editor)**: on step 2 the "Select a new
role" dropdown offered `gw.sed` the *full* list for the context, the manager-slot
role included — OJS: `Journal manager, Journal editor, Production editor,
Section editor, Guest editor, Copyeditor, Designer, Funding coordinator, Indexer,
Layout Editor, Marketing and sales coordinator, Proofreader, Author, Translator,
Reviewer, Reader, Subscription Manager, Editorial Board Member`. (Recorded
because it is what *this role* is offered; the general shape of that dropdown is
Group B/C's B-15 and C-19.)

---

## Incidental (outside Group A — one line each, not item results)

1. **Last-role removal is refused, verbatim** (seen while setting up A-1b, in the
   users-table Edit wizard for a user holding a single role): dialog "Remove
   Role — *You cannot remove the role. At least one role must be assigned to the
   user.*" with a single **Close** button. Belongs to B-12(c).
2. **An Error modal covers the Users & Roles page for a signed-in user with no
   editorial role in the context**: on `u6gadm2` (site admin holding only
   Reader), the page renders fully but an `[role=dialog]` "Error — The current
   role does not have access to this operation. [OK]" sits over it on load, and
   while it is open every control behind it is inside an `aria-hidden="true"`
   ancestor (so `getByRole` finds nothing). Dismissing **OK** leaves the page and
   the "Invite to a role" button fully usable. The failing background call is the
   header's task-count fetch (`…/_submissions/viewsCount`), not an invitations
   call — i.e. it belongs to the dashboard/task-badge feature, not U6.
3. **Raw locale key on screen**: the users-table row menu button's accessible
   name is `##userAccess.management.options##` (its menu holds `Edit` and
   `Email`). Also seen page-wide: a `##common.help##` item in the header.
   Belongs to B-6.
4. **Scenario endpoint, OPS role vocabulary**: seeding a user with role key
   `funding` on OPS is rejected with "No default user group for role 'funding'
   in context … Available roles: manager, sectionEditor, author, reader,
   editorialBoardMember." Harness-side detail; the screen-side equivalent is the
   OPS Roles inventory in A-4a.
5. On step 1 of the send wizard only the current step is a `<button>`; the later
   step names are inert text until reached. Belongs to B-7.

## Out of scope, not investigated

Nothing in Group A required a request the screens do not send, so no item was
dropped and nothing was added to the deferred queue.
