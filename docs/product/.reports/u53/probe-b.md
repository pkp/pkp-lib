# U53 — Users management · probe report B (groups G3 + G4)

**Frame.** QA documentation of the applications' own screens, on a local
disposable test install with seeded accounts. Every observation below was made
by signing in through the login form as the named role and driving the screens
in a real browser (Playwright/Chromium against the live fleets) — clicking what
is offered, filling what is presented, and typing addresses directly. No
hand-built API call, no altered form payload, no client but a browser. The only
non-browser use of the test API was scratch-context seeding, which the probe
list mandates for anything mutating.

**Agent**: probe B (Opus). **Items**: G3 = 10, 11, 12, 13; G4 = 14. All five
executed. Item 25 (self-disable execution) was left to the destructive group's
agent, as briefed.

**Date**: 2026-07-28/29. **Fleets**: OJS `127.0.0.1:8000`, OMP
`127.0.0.1:8100`, OPS `127.0.0.1:8200`. Mailpit `127.0.0.1:8025` (never
cleared; every read scoped by a throwaway recipient address that carries the app
name, plus a unique subject marker where one was needed).

## Fixtures this probe created (`u53pb` namespace)

Scratch contexts via `POST /index.php/index/api/v1/_test/scenarios/context`:

| App | tag | urlPath | contextId | seeded users |
|---|---|---|---|---|
| OJS | `u53pb-ojs` | `u53pbojs` | 2 | `u53pb-mgr-ojs` (manager), `u53pb-t14-ojs` (author, mail `u53pb-i14-ojs@mail.test`) |
| OMP | `u53pb-omp` | `u53pbomp` | 2 | `u53pb-mgr-omp`, `u53pb-t14-omp` |
| OPS | `u53pb-ops` | `u53pbops` | 2 | `u53pb-mgr-ops`, `u53pb-t14-ops` |
| OJS | `u53pb-ojs-c` | `u53pbojsc` | 7 | `u53pb-cmgr-ojs` (manager) — context contact set to `U53PB Contact Person <u53pb-contact-ojs@mail.test>` |
| OMP | `u53pb-omp-c` | `u53pbompc` | 7 | `u53pb-cmgr-omp` — same contact shape |
| OPS | `u53pb-ops-c` | `u53pbopsc` | 5 | `u53pb-cmgr-ops` — same contact shape |

Accounts minted **through the Add User screen** during item 12:
`u53pb-i12-ojs` (in `u53pbojs`, saved with no Country, no roles),
`u53pb-i12c-{ojs,omp,ops}` (in the `*c` contexts, Author role, later also
Reviewer on OJS/OMP), plus two refused attempts (`u53pb.dot.ojs`,
`u53pb.dot2.ojs`) that never became accounts. Nothing seeded was mutated;
`publicknowledge` and the seeded roster were read-only throughout (the only
`publicknowledge` interactions were look-and-cancel, item-10/11 controls).

Scratch material for later archaeology (screenshots, page text, page HTML per
step) is in the probe's own scratch dir, not committed.

---

## Item 10 — The Hosted Journals Users tab: dressing and filter

*Apps: OJS primary, OMP + OPS control (I ran all three).* **Role**: seeded
**site administrator** (`admin`).
**Screen**: `http://127.0.0.1:<port>/index.php/index/en/admin/wizard/<contextId>`
→ tab "Users" (`getByRole('tab', {name: 'Users'})`). Read-only except where the
filter was used.

### What appeared

**claim** — The tab holds one grid, `#userGridContainer`. Its heading
(`#userGridContainer .header h4`) reads **"Current Users"** in all three apps.

**claim** — Column headers (`#userGridContainer table thead th`), identical in
OJS, OMP and OPS:

> `Given Name` · `Family Name` · `Username` · `Roles` · `Email`

**claim** — Above the table sits a two-item action list
(`#userGridContainer .header ul.actions a`): **`Search`** and **`Add User`** —
in that order, all three apps.

**claim** — **The filter row is not visible until "Search" is clicked.** On
arrival `#userSearchForm` is present in the page but hidden
(`isVisible() === false`; the form carries `style="display: none;"`). Clicking
the header's `Search` link (`a.pkp_linkaction_search`) reveals it. This
qualifies the draft's Rule 1b, which describes the filter as simply sitting
above the table.

**claim** — Revealed, the filter holds exactly (locators as given):

| Control | Locator | What it shows |
|---|---|---|
| Search field | `#userSearchForm input[name=search]` | label **"Search"**; **no placeholder** (`placeholder` attribute is `null`) |
| Role dropdown | `#userSearchForm select[name=userGroup]` | selected option **"All Roles"** |
| No-roles checkbox | `#userSearchForm #includeNoRole` | unchecked; label OJS **"Include users with no roles in this journal."**, OMP **"Include users with no roles in this press."**, OPS **"Include users with no roles in this server."** |
| Submit | `#userSearchForm button[type=submit]` | **"Search"** |

**claim** — **The filter applies on pressing Search, not as you type.** In OJS's
`u53pbojs` (3 rows), typing `Utarget` into the search field and waiting 2.5 s
left all three rows in place; pressing **Search** narrowed the list to the one
matching row. Identical in OMP and OPS.

**context** — After a Search submit the filter panel collapses again
(`isVisible() === false`) while keeping the criteria (the search box still held
`"Utarget"`); the "Search" link must be clicked again to adjust the filter.

**claim** — Selecting a single role narrows the list. OJS `u53pbojs`, role
**"Author"** → 1 row (`Utarget Fourteen · u53pb-t14-ojs · Author`); role
**"Journal manager"** → 2 rows; roles "Section editor" / "Reader" / "Reviewer"
→ 0 rows. Same shape on OMP ("Press manager") and OPS ("Preprint Server
manager").

**claim** — The no-roles checkbox widens the list to the whole site roster.
OJS: unticked → `1 - 3 of 3 items`; ticked → `1 - 25 of 65 items 1 2 3 > >>`,
listing every seeded `publicknowledge` account and other agents' throwaways.
OMP: 3 → 30 items. OPS: 3 → 18 items. Unticking and pressing Search restored
the 3-row list.

**context** — Paging control text: `1 - 3 of 3 items`, with an "Items per page:"
select beside it; default 25 per page.

### An unclaimed behaviour this item ran into (all three apps)

**claim** — **The site administrator's row is listed in every journal's Current
Users table whether or not they hold a role there, with an empty Roles cell, and
the "manager" role filter returns it.** Watched in the pristine seeded journal
(`admin/wizard/1`, `publicknowledge`, read-only):

- default filter (no-roles **unticked**): `1 - 18 of 18 items`, first row
  `admin · admin · admin · (Roles cell empty) · admin@example.org`;
- role filter **"Journal manager"** → 2 rows: the `admin` row plus
  `manager.maya`;
- role filters "Section editor" (3), "Reader" (1), "Author" (2), "Reviewer" (4)
  → the `admin` row is absent from each.

Same row presence, same empty Roles cell, on OMP and OPS's `publicknowledge`.

**claim** — **The two lists disagree about the administrator's roles.** For the
same account and the same journal (`publicknowledge`, OJS):

- Hosted Journals list, `admin` row → Roles cell **empty**;
- Hosted Journals list → "Edit User" on that row → the "User Roles" checkbox
  list shows **"Journal manager" ticked**;
- Users & Roles list (Vue), seen as `manager.maya` at
  `/index.php/publicknowledge/en/management/settings/access` → row
  `admin admin · admin@example.org · **Journal manager** · (Start Date cell
  blank)`.

Identical on OMP ("Press manager") and OPS ("Preprint Server manager"). This
touches Rule 1's unmarked claim that the two lists "read the same accounts".

### Settles

Rule 1b's dressing: **confirmed** as to heading, the five columns, the four
filter controls with their exact strings, the "Add User" placement, and
per-app checkbox wording — **corrected** in one respect: the filter is collapsed
behind a "Search" link until clicked. Rule 2's second half ("The Hosted Journals
filter applies on 'Search' and can also select a single role, or users with no
roles at all"): **confirmed**, in all three apps.

---

## Item 11 — The administrator's own row on the Hosted Journals list

*Apps: OJS + OMP + OPS (cross-app exclusivity claim).* **Role**: seeded **site
administrator** (`admin`), viewing their **own** row. Nothing was chosen —
offers recorded only.
**Screen**: `.../index/en/admin/wizard/<id>` → "Users" tab; row expanded with
the first-column disclosure link (`a.show_extras`, screen-reader text
"Settings"); actions read from
`#component-grid-settings-user-usergrid-row-1-control-row a.pkp_controllers_linkAction`.

### What appeared

**claim** — On the administrator's own row, in **all three apps**, exactly four
actions, in this order and with these labels:

> **Email** · **Edit User** · **Disable User** · **Remove**

**Login As** and **Merge User** are absent from the own row.

**claim** — Control, same screen, same session, a throwaway's row
(`u53pb-t14-<app>`), all three apps — six actions:

> **Email** · **Edit User** · **Disable User** · **Remove** · **Login As** · **Merge User**

**claim** — The same four-action own-row set appears in the seeded
`publicknowledge` journal (wizard 1) in all three apps, so it is not a property
of the scratch contexts.

**context** — The remove action's visible label on this list is **"Remove"**,
not "Remove User" (the Users & Roles list's label — see item 14, where the Vue
row menu reads "Remove User"). Rule 1b currently writes the legacy label as
"Remove User".

### Settles

Register A2's on-screen half and Rule 1c's legacy-side withholding:
**confirmed, and confirmed identical in all three apps** — the Hosted Journals
list withholds only Login As and Merge on the viewer's own row, and does offer
Disable User (and Email, Edit User, Remove) there. The execution half (does a
self-disable actually lock the administrator out) was not touched here; it is
item 25's.

---

## Item 12 — "Add User" and the account form

*Apps: OJS primary for the form; OMP + OPS repeat the create + welcome-mail
check.* **Role**: seeded **site administrator** (`admin`).
**Screen**: Hosted Journals → wizard → "Users" tab → **Add User**
(`#userGridContainer .header ul.actions a.pkp_linkaction_addUser`), which opens
a side modal headed **"Add User"** containing `#userDetailsFormContainer`.

### The form as presented (OJS)

**claim** — Step-1 heading (`#userDetailsFormContainer h3`):
**"Step #1: Fill in User Details"**. Footer note: **"Required fields are marked
with an asterisk: *"**. Buttons: **Cancel** · **OK** (not "Save").

**claim** — Required marks, read from the rendered labels and the inputs'
`required` attributes:

| Field | Label as shown | Required marked? |
|---|---|---|
| Given Name | `Given Name*` | yes (`required` present) |
| Family Name | `Family Name` | no |
| Preferred Public Name | `Preferred Public Name` | no |
| Username | `Username*` | yes |
| Email | `Email*` | yes |
| Password | `Password*` | yes |
| Repeat password | `Repeat password*` | yes |
| **Country** | **`Country`** | **no — no asterisk, no `required` attribute, and the select's first option is blank** |

**claim** — **Country is not required, and a save without it succeeds.** With
Given Name, Username, Email and both password fields filled and the Country
select left at its blank option, pressing **OK** produced **no validation
message of any kind** and went straight to Step #2; the account
(`u53pb-i12-ojs`) was created. Re-opening that account's Edit User form later
showed the Country select still empty (`inputValue() === ""`). This **corrects**
the draft's Fields table, which marks Country `Required? Yes`.

**claim** — Username rules. The hint above the field
(`#userDetailsFormContainer label.description`) reads verbatim:

> "The username must contain only lowercase letters, numbers, and hyphens/underscores."

Typing `u53pb.dot2.ojs` (a dot — the shape every seeded username uses) and
pressing OK is refused: the form stays on Step #1 with the typed values intact,
and a page-level warning appears at
`.app__notifications .pkpNotification--warning`, reading verbatim:

> "The username can contain only lower-case alphanumeric characters, underscores, and hyphens, and must begin and end with an alphanumeric character."

(The refusal's wording is not the hint's wording.)

**claim** — The **Suggest** button (`#suggestUsernameButton`, label "Suggest")
fills the username from the name fields, lowercased and with spaces removed:

| Given Name typed | Family Name typed | Username filled |
|---|---|---|
| `Utwelve` | `Probe` | `uprobe` |
| `Anna` | *(empty)* | `anna` |
| `Anna` | `Smith Jones` | `asmithjones` |
| *(empty)* | `Onlyfamily` | `onlyfamily` |

**claim** — The password block (`<legend>Password</legend>`) holds Password,
Repeat password and two checkboxes:

- `#generatePassword`, group label **"Generate Password"**, checkbox text
  **"Generate random password for this user."** — **unchecked** by default;
- `#mustChangePassword`, group label **"Change Password"**, checkbox text
  **"User must change password on next log in."** — **checked by default** on
  the create form. (The draft's Fields table does not state a default; this one
  is on.)

**claim** — `#sendNotify`, group label **"Notify User"**, checkbox text
**"Send user a welcome email."** — unchecked by default.

**claim** — **Working Languages appears**, inside a collapsed "More User
Details" section (`a.toggleExtras`), as two checkboxes
(`input[name="locales[]"]`): **English**, **French**. The install has two
languages (`en`, `fr_CA`), so the "more than one language" condition holds and
the field is present — consistent with the draft; the single-language case is
not stageable here.

**context** — The rest of "More User Details": Homepage URL, Phone, Working
Languages, Reviewing interests, Affiliation, Bio Statement (e.g., department and
rank), Mailing Address, Signature. Country carries the description-free plain
label; the country select offers 249 options (a blank first option + 248
countries).

### Step #2 and the welcome mail (all three apps)

Run in the `*c` contexts, whose contact address is set, so the reply-to claim is
testable.

**claim** — Pressing OK on a valid Step #1 opens `#userRoleForm` headed
verbatim **"Step #2: Add User Roles to Utwi12c Probei12c"** — i.e. "Step #2: Add
User Roles to {given} {family}". Same heading text in all three apps.

**claim** — Step #2 holds **two checkbox lists**
(`#userRoleForm ul.checkbox_and_radiobutton > label`): **"User Roles"** and
**"Appear on Masthead"**, each listing every role the context ships (OJS 18,
OMP 19, OPS 5). On this screen **every "User Roles" box starts unticked and
every "Appear on Masthead" box starts ticked**, and **none is disabled — the
Reviewer rows included** (contrast the edit form, item 13). Buttons: **Cancel**
· **Save**.

**claim** — Ticking "Author" under "User Roles" and pressing Save closes the
modal and returns to the grid, where the new row appears with Roles = `Author`:

> `Utwi12c Probei12c · u53pb-i12c-ojs · Author · u53pb-i12c-ojs@mail.test`

**claim** — **The welcome email arrives**, one message per created account, sent
at Step 1 (it was already in the box before Step #2 was saved). Scoped by
recipient:

| App | Recipient | Subject | From | Reply-To |
|---|---|---|---|---|
| OJS | `u53pb-i12c-ojs@mail.test` | **"Journal Registration"** | `admin admin <admin@example.org>` | `U53PB Contact Person <u53pb-contact-ojs@mail.test>` |
| OMP | `u53pb-i12c-omp@mail.test` | **"Press Registration"** | same | `... <u53pb-contact-omp@mail.test>` |
| OPS | `u53pb-i12c-ops@mail.test` | **"Server Registration"** | same | `... <u53pb-contact-ops@mail.test>` |

OJS body, verbatim:

> Utwi12c Probei12c
>
> You have now been registered as a user with U53PB Contact ojs. We have included your username and password in this email, which are needed for all work with this journal through its website. At any point, you can ask to be removed from the journal's list of users by contacting me.
>
> Username: u53pb-i12c-ojs
> Password: u53pbI12Pass1
>
> Thank you,
>
> admin admin

OMP and OPS differ only in vocabulary ("this press" / "this server"; OMP's
sentence reads "removed from the list of users", OPS's "removed from the
server's list of users").

**claim** — The password travels in the mail **in clear text even when it was
typed by the administrator** — "Generate Password" was not used in any of these
three sends. The draft mentions the travelling password only for the Generate
Password case.

**claim** — **Reply-to is the journal's contact address**, confirming the draft.
Counter-observation worth keeping: in a scratch context with **no** contact
address set (`u53pbojs`), the same mail arrived with **no Reply-To header at
all** (`From` was the acting administrator's own address).

**claim** — The new account appears in that context's **Users & Roles** list.
Signed in as the scratch manager (`u53pb-cmgr-<app>`) at
`/index.php/u53pb<app>c/en/management/settings/access`:

> OJS: `Current Users (3) … Utwi12c Probei12c · u53pb-i12c-ojs@mail.test · Author · 2026-07-28`

Same in OMP and OPS.

### Settles

Scenario 6: **confirmed end to end** in all three apps (Add User → Step #2 →
welcome mail → the user in the Users & Roles list). Fields table (create shape):
**confirmed** except **Country, which is corrected from required to optional**,
plus two additions the table does not carry (the "Change Password" default-on,
and the create-form refusal wording for a bad username). fn-l (welcome mail,
reply-to = context contact): **confirmed**, all three apps. fn-n (Suggest):
**confirmed** on the button's behaviour; the breadth question the probe list
deferred was not touched.

---

## Item 13 — "Edit User" on the account created in item 12

*Apps: OJS + OMP, OPS absence control.* **Role**: seeded **site administrator**
(`admin`). **Screen**: Hosted Journals → wizard → "Users" tab → row's **Edit
User** (`a.pkp_linkaction_edit`), which opens the same
`#userDetailsFormContainer`, headed **"User Details"** (no "Step #1" prefix).

### What appeared — the edit shape (all three apps)

**claim** — The **username is shown but is not a field**: there is no
`input[name=username]` at all; the form text reads `Username u53pb-i12c-ojs`
followed by the Contact section. It cannot be edited.

**claim** — **Notify User is absent when editing** (`#sendNotify` count 0), as
is **Generate Password** (`#generatePassword` count 0). **Change Password**
(`#mustChangePassword`) is still present.

**claim** — The password block carries the note, verbatim:

> "Leave the password fields blank to keep the current password."

**claim** — The edit form carries the same **two checkbox lists** as Step #2 —
labels **"User Roles"** and **"Appear on Masthead"** — with the account's
current roles ticked in the first list.

**claim** — **The Reviewer rows in the "Appear on Masthead" list are rendered
un-tickable.** OJS: `Reviewer [checked] [DISABLED]`. OMP: **both**
`Internal Reviewer [checked] [DISABLED]` and
`External Reviewer [checked] [DISABLED]`. Every other row in the masthead list
is checked and enabled. The disabling is present **even before** the account
holds any reviewer role.

**claim** — **OPS has no reviewer role to assign.** Its "User Roles" list holds
exactly `Preprint Server manager`, `Moderator`, `Author`, `Reader`,
`Editorial Board Member`; its "Appear on Masthead" list holds the same five,
**all checked and none disabled**. So neither the reviewer masthead lock nor
Editorial Notes can appear there.

**claim** — **Editorial Notes appears only once the edited user is a Reviewer
here.** OJS, same account, same viewer, same screen:

- before: role list shows `Author [checked]`, `Reviewer` unticked → no
  "Editorial Notes" text in the form, no `[name=gossip]` control;
- tick "Reviewer" in the "User Roles" list, press OK (grid row now reads
  `Author, Reviewer`), re-open Edit User → **"Editorial Notes"** is present and
  a `[name=gossip]` control exists.

OMP identical (ticking "Internal Reviewer"). OPS: absent throughout, as above.

**claim** — **Editorial Notes is absent on the administrator's own record.**
Opening Edit User on the `admin` row (scratch context and, read-only,
`publicknowledge`) shows no Editorial Notes in any of the three apps.

> **undetermined** — this does *not* isolate the draft's "never on one's own
> record" clause: the administrator holds no Reviewer role, so the reviewer
> condition alone explains the absence. **The one observation that would settle
> it**: a viewer who both reaches this form and holds the Reviewer role in the
> same context, opening their own record. Only site administrators reach this
> form, and the seeded administrator is read-only, so settling it needs the
> throwaway site administrator item 25 is conditional on.

### Save with blank passwords, and mail silence (all three apps)

**claim** — Saving the edit form with **both password fields empty** (verified
`inputValue() === ""` at the moment of pressing OK) succeeds, and the account's
**original password still signs in**: signing in as `u53pb-i12c-<app>` with
`u53pbI12Pass1` landed on `/index.php/index/en/index` with the username shown in
the page header, in OJS, OMP and OPS.

**claim** — **No mail is sent when an account is edited**, established with a
positive control per the standing discipline:

1. target box before the edit — OJS `u53pb-i12c-ojs@mail.test`: **1** message
   (`"Journal Registration"`, the item-12 welcome);
2. edit-save performed (affiliation changed, passwords blank);
3. target box immediately after: still **1**;
4. **positive control** — the "Email" row action on `u53pb-cmgr-ojs`'s row, same
   screen, same session, subject `U53PBCTRLojs1785275653253`; that message
   **arrived** at `u53pb-cmgr-ojs@mail.test`;
5. target box re-read after the control had landed: still **1**, and still only
   `"Journal Registration"` (created 23:46:27, i.e. before the edit).

OMP (`"Press Registration"`, control `U53PBCTRLomp1785275773418`) and OPS
(`"Server Registration"`, control `U53PBCTRLops1785275889860`) gave the same
result.

### Settles

Fields table (edit shape): **confirmed** — username shown but not editable,
Notify User absent, roles-and-masthead lists present, blank passwords keep the
current one. The `{OJS OMP}` reviewer-masthead lock: **confirmed**, and extended
— it is *both* OMP reviewer groups, and the lock is applied whether or not the
user holds the role. The Editorial Notes row: **confirmed** for the "only when
the edited user is a Reviewer here" clause; the "never on one's own record"
clause is **undetermined** (see above). The OPS absence: **confirmed** as an
install fact — no reviewer role exists to assign. "Never sent when editing"
under Side effects: **confirmed** with a positive control, all three apps.

---

## Item 14 — The Email action

*Apps: OJS primary, OMP + OPS one send each (I ran the full item in all three).*
**Role**: **Journal Manager** of a scratch journal (`u53pb-mgr-<app>`, seeded
into `u53pb<app>` with the `manager` role).
**Screen**: `http://127.0.0.1:<port>/index.php/u53pb<app>/en/management/settings/access`
→ "Current Users" table → the row's options menu
(`table tbody tr:has-text("<address>") button[aria-haspopup=menu]`) → **Email**.

### What appeared

**claim** — On a throwaway user's row the menu offers, in this order, in all
three apps:

> **Edit** · **Email** · **Login As** · **Remove User** · **Disable User** · **Merge user**

**claim** — On the **manager's own row** the menu offers only:

> **Edit** · **Email**

(all three apps — Remove User, Disable User, Merge user and Login As are all
withheld there). This is item 9's territory; recorded because item 14's own-row
send required opening that menu.

**claim** — Choosing **Email** opens a dialog headed **"Email"**
(`getByRole('dialog') h1`) containing `form#sendEmailForm` with exactly three
fields (`#sendEmailForm label` → `["Subject*", "To", "Body*"]`):

| Field | Locator | What it shows |
|---|---|---|
| Subject | `#sendEmailForm input[name=subject]` | label **"Subject\*"**, `required` attribute present |
| To | `#sendEmailForm input[name=user]` | label **"To"**, value **`Utarget Fourteen <u53pb-i14-ojs@mail.test>`** — full name and address; `disabled`, `isEditable() === false` |
| Body | `#sendEmailForm textarea[name=message]` + a TinyMCE frame `iframe[id^="message-"]` | label **"Body\*"**, `required` attribute present; rich-text toolbar (bold/italic/underline/link/…) |

Footer: **"Required fields are marked with an asterisk: *"**. Buttons
(`#sendEmailForm .form_buttons`): **Cancel** · **Send Email**.

**claim** — **The message is delivered.** After filling Subject and Body and
pressing **Send Email**, the dialog closes (no dialog left in the page, no
notification banner shown), and Mailpit holds exactly one matching message per
send:

| App | Sent from row | Recipient | Subject marker | From | Body received |
|---|---|---|---|---|---|
| OJS | throwaway | `u53pb-i14-ojs@mail.test` | `U53PBI14Tojs1785275970437` | `Umgr Probe <u53pb-mgr-ojs@mail.test>` | "U53PB probe body for U53PBI14Tojs1785275970437 — item 14." |
| OJS | **own row** | `u53pb-mgr-ojs@mail.test` | `U53PBI14Sojs1785276015317` | `Umgr Probe <u53pb-mgr-ojs@mail.test>` | same shape |
| OMP | throwaway | `u53pb-i14-omp@mail.test` | `U53PBI14Tomp1785276070296` | `Umgr Probe <u53pb-mgr-omp@mail.test>` | same shape |
| OMP | **own row** | `u53pb-mgr-omp@mail.test` | `U53PBI14Somp1785276115196` | same | same shape |
| OPS | throwaway | `u53pb-i14-ops@mail.test` | `U53PBI14Tops1785276168007` | `Umgr Probe <u53pb-mgr-ops@mail.test>` | same shape |
| OPS | **own row** | `u53pb-mgr-ops@mail.test` | `U53PBI14Sops1785276212871` | same | same shape |

Both boxes were verified at **0** messages before the sends. The subject and
body arrive exactly as typed; the message carries **no Reply-To** header (the
`From` is the sending manager's own name and address, so a reply reaches them).

**claim** — The self-send works: a Journal Manager can email their own account
from their own row, and the message arrives.

### Settles

Scenario 2: **confirmed** — the form's shape, the fixed To, and delivery, in
all three apps. The Email fields table: **confirmed** verbatim (Subject
required, To fixed showing full name and address and not editable, rich-text
Body, "Send Email"). Actors row "Email a user" (no target restriction, own row
included): **confirmed** on the offer *and* on delivery, all three apps.

---

## Incidental (outside G3/G4, one line each — for whoever owns them)

- **Item 8 / register A4**: the Vue list's row-options button carries
  `aria-label="##userAccess.management.options##"` — the raw key, rendered with
  the `##…##` missing-string markers, in all three apps (seen in the page HTML
  while working item 14).
- **Item 7 / register A5**: the Users & Roles search hint reads "Enter a user's
  name, role (e.g Journal editor), or affiliation" **on OMP and OPS too** (seen
  on every Vue list screenshot this probe took).
- **Item 5 / Rule 1a**: on the Vue list, the site administrator's row shows a
  role ("Journal manager" / "Press manager" / "Preprint Server manager") with a
  **blank Start Date** cell, in `publicknowledge` and in scratch contexts.
- **Item 9 / Rule 1c**: the Vue own-row menu offers exactly **Edit · Email**
  (recorded above under item 14).
- The legacy list's remove action is labelled **"Remove"**; the Vue list's is
  **"Remove User"**.

## Nothing blocked this probe

No item was left unrun and no scope line was reached; the single undetermined
point (item 13's own-record clause for Editorial Notes) is recorded above with
the observation that would settle it.
