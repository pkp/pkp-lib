# U6 — User invitations · probe report, **Group C** (OPS email catalogue, role lists & vocabulary)

**Frame.** QA documentation of an application's own screens, on a local disposable
test install with seeded accounts. Signed in as each role and used the screens the
way that role would — including typing a URL directly to reach one. Recorded what
the screen offers, what happens when it is used, and where the two disagree, so the
product team can fix it. No request was constructed that the screens themselves
would not send: every observation below comes from a Chromium browser driving the
application's own pages, plus Mailpit's own web API for reading what was delivered.

**Agent**: Group C probe (RUNBOOK step 3), 2026-07-28. Items C-16 … C-22, all seven
executed. Group C is a cross-app item set (multi-app rule 4) — one agent drove all
three fleets; no item was split by app.

---

## Environment and setup

- Fleets: OJS `http://127.0.0.1:8000` · OMP `:8100` · OPS `:8200`. Mailpit `:8025`.
  (Note for later runs: the PHP dev servers reject the `Host: localhost` form with
  `400 Server host not allowed`; `127.0.0.1` is the host that works in a browser.
  The `/api/v1/_test/*` scenario endpoints accept either.)
- **Scratch contexts** created via `POST /index.php/index/api/v1/_test/scenarios/context`
  (`X-Test-Key: playwright-test-key`), one per app, distinctively named so concurrent
  probe agents' contexts are untouched:

  | App | tag / urlPath | Name | Manager account (seeded by the scenario) |
  |---|---|---|---|
  | OJS | `u6pcojs` (contextId 55) | Probe C Journal | `u6pcmgrojs` / `u6pcmgrojsu6pcmgrojs` |
  | OMP | `u6pcomp` (contextId 31) | Probe C Press | `u6pcmgromp` / `u6pcmgrompu6pcmgromp` |
  | OPS | `u6pcops` (contextId 22) | Probe C Server | `u6pcmgrops` / `u6pcmgropsu6pcmgrops` |

- Every mutating action happened inside those three contexts. `publicknowledge` and
  the seeded roster were not touched. Mailpit was never cleared; every mail read was
  scoped by recipient (`to:<address>` search), and every invitee address carries the
  `u6pc.` prefix so it cannot collide with another agent's.
- **Install facts framing this group** (recorded as facts, not proved impossibilities):
  ORCID is not configured on any of the three installs — no "Verify ORCID iD" step
  appeared in any acceptance flow I walked, and the accept flow's details step instead
  shows a read-only line "ORCID iD — Not verified. You can verify your ORCID iD from
  your profile section in OPS." The scheduled task runner is Off.
- Invitee addresses used: `u6pc.ojs.newcomer1@`, `u6pc.omp.cancel1@`,
  `u6pc.omp.newcomer2@`, `u6pc.ops.newcomer1@`, `u6pc.ops.cancel1@`,
  `u6pc.ojs.probe2@`, `u6pc.omp.probe2@`, `u6pc.ops.probe2@` (all `example.org`).

**Reading the marks**: **[claim]** = the observation settles (or bears directly on)
the item. **[context]** = incidental DOM/behaviour I happened to see while doing the
item; not promotable to an assertion without its own probe.

---

## C-16 — Is the invitation email in each app's Emails catalogue?

*(P1 · register OPS1)*

**Role**: the scratch-context Manager in each app (`u6pcmgr*`).
**Screen**: Settings → Workflow → Emails → "Add and edit templates" →
`/index.php/{ctx}/management/settings/manageEmails` ("Manage Emails", heading
"Emails"). The Workflow-settings Emails tab itself carries only journal-level
notification switches; the template catalogue lives one click further, behind the
link labelled **"Add and edit templates"** (`href` → `…/settings/manageEmails`).

**What I did**: opened the screen in each app; counted the listed templates; typed
`invit` into the search field and pressed Enter; also applied the group filter
"Other" (the group the invitation mailable belongs to) with no search term.

**Locators**
- screen: `page.goto('/index.php/{ctx}/management/settings/manageEmails')`
- list rows: `li.listPanel__item`, title `li.listPanel__item .listPanel__itemTitle`
- search: `page.getByPlaceholder('Search by name or description')`
- empty state: `page.getByText('No items found.')`
- group filter: `page.getByRole('button', {name: 'Other', exact: true})`
- row action: `page.getByRole('button', {name: 'Edit User Invited to Role Notification'})`
  (the visible glyph reads "Edit"; the accessible name comes from a
  `<span class="-screenReader">Edit User Invited to Role Notification</span>`)

**Observations**

| App | templates listed (unfiltered) | search `invit` | "Other" group |
|---|---|---|---|
| OJS | 67 | **1 row** | 23 rows, includes the invitation row |
| OMP | 57 | **1 row** | 12 rows, includes the invitation row |
| OPS | **18** | **0 rows — "No items found."** | 6 rows, no invitation row |

**[claim]** OJS and OMP both list the row, with identical verbatim text:

> title: **"User Invited to Role Notification"**
> subtitle: **"This email is sent to users that are invited to obtain certain roles"**

Row markup as seen on OJS (identical shape on OMP):

```html
<li class="listPanel__item"><div class="listPanel__itemSummary"><div class="listPanel__itemIdentity">
<div class="listPanel__itemTitle">User Invited to Role Notification</div>
<div class="listPanel__itemSubtitle">This email is sent to users that are invited to obtain certain roles</div>
</div> <div class="listPanel__itemActions"><button …><span aria-hidden="true">Edit</span>
<span class="-screenReader">Edit User Invited to Role Notification</span></button></div></div></li>
```

**[claim]** On **OPS the row is absent**. Searching `invit`, `invited`, `role` and
`User Invited` each returns the verbatim empty state **"No items found."**. Searching
`notification` returns exactly one unrelated row ("Statistics Report Notification"),
so the search itself works — the absence is the catalogue, not the filter.

**[claim]** OPS's complete catalogue is 18 templates, none of them the invitation
email: Discussion (Production) · Moderator Assigned (Auto) · New Announcement · New
Version Created · New Version Posted · Notify Other Authors · Password Reset Confirm
· Posted Acknowledgement · Reinstate Submission Declined Without Review · Statistics
Report Notification · Submission Accepted · Submission Acknowledgement (No Moderation
Required) · Submission Acknowledgement (Pending Moderation) · Submission Confirmation
(Other Authors) · Submission Declined · User Created · Validate Email (Server
Registration) · Validate Email (Site).

**[claim]** Browsing by group rather than searching gives the same answer. OPS's
"Other" group lists 6 templates — New Announcement, Password Reset Confirm,
Statistics Report Notification, User Created, Validate Email (Server Registration),
Validate Email (Site) — while OJS's and OMP's "Other" groups both contain **"User
Invited to Role Notification"**.

**[context]** OPS's filter vocabulary on this screen is also reduced: the group
filters are Submission / Production / Other (no Review, no Copyediting), and the
"Sent From" / "Sent To" role filters read Moderator / Reader / System and Moderator
/ Author / Reader respectively — where OJS shows Editor / Reviewer / Assistant /
Reader / Subscription Manager / System. This is the Emails screen's own vocabulary,
not U6's.

**Settles**: **Confirms** OPS1's no-row consequence. A manager on OPS has no row for
the invitation email on the Emails management screen, by search or by group filter,
while the same manager on OJS and OMP has one. OJS and OMP are the controls and both
show the row.

---

## C-17 — What the invitation email's row offers when opened

*(P2)*

**Role**: scratch Manager. **Screen**: same `manageEmails` screen; pressed the row's
Edit control.

**Locators**: `page.getByRole('button', {name: 'Edit User Invited to Role Notification'})`;
resulting dialog `page.locator('[role="dialog"]')`; fields
`input#userInvited-…` / `input[id*="subject"]`; body in a TinyMCE `iframe`.

**Observations — OJS and OMP (identical)**

**[claim]** The Edit control opens a dialog whose entire content is the template edit
form, verbatim:

> **Edit Template** · **Name** — "Enter a brief name to help you find this template."
> · **Subject** · **Body** · [Insert Content] · [Save]

- Name field value: **"User Invited to Role Notification"**
- Subject field value: **"You are invited to new roles"**
- Body (iframe) verbatim: *"Invitation to New Role / Dear {$RECIPIENTNAME}, / In light
  of your expertise, you have been invited by {$INVITERNAME} to take on new roles at
  {$CONTEXTNAME} / At {$CONTEXTNAME}, we value your privacy. As such, we have taken
  steps to ensure that we are fully GDPR compliant. These steps include you being
  accountable to enter your own data and choosing who can see what information. For
  additional information on how we handled your data, please refer to our Privacy
  Policy. / {$EXISTINGROLES} {$ROLESADDED} / On accepting the invite, you will be
  redirected to {$CONTEXTNAME}. Feel free to contact me with any questions about the
  process. / Accept Invitation / Decline Invitation / Kind regards, {$CONTEXTNAME}"*

**[claim]** **No role or user-group restriction control is offered** on this template,
in either app. Counted directly in the dialog: `getByText('Mark as unrestricted')` = 0,
`getByText('Limit access to specific roles')` = 0, `getByRole('button', {name:'Add Template'})` = 0.

**[claim] — positive control that the restriction control exists on this screen.**
Opening a *composable* mailable instead — `Edit Discussion (Review)` on OJS — opens a
different dialog shape: a per-mailable panel headed with the mailable's name and
description, then

> "Add and edit templates that you would like to make available to the user when they
> are sending this email. The default will be loaded automatically, and the user will
> be able to quickly load any other templates you add here."
> **Templates** · [Add Template] · row "Discussion (Review)" badge "Default" [Edit] ·
> row "Assign Editor" [Edit] [Remove]

and its **[Add Template]** form carries exactly the restriction control that the
invitation template lacks, verbatim:

> **Mark as unrestricted** — "Unrestricted templates will be accessible to all roles."
> · checkbox "Mark as unrestricted" · **"Limit access to specific roles"**

"Review Request" behaves the same way (Templates list + Add Template). "Editorial
Reminder" — like the invitation email — opens straight into the bare Edit Template
form. So the screen has two dialog shapes, and the invitation email is in the
name/subject/body-only shape.

**Settles**: On OJS and OMP the invitation email's editing surface is name, subject
and body only — no role/user-group restriction control, and no alternate-template
list. OPS has no row at all to open (C-16), so the OPS half of this item is answered
by that absence.

---

## C-18 — Can an alternate/custom template be added for the invitation email on OPS?

*(P3)*

**Role**: scratch Manager on OPS (`u6pcmgrops`). **Screen**:
`/index.php/u6pcops/management/settings/manageEmails`.

**What I did**: scanned the panel for any panel-level "add" affordance; searched
`invited`, `role`, `User Invited`, `notification`; applied the "Other" group filter.
Then recorded the OJS path as the control.

**[claim] The screen offers no path on OPS.** There is no panel-level add/new button
at all (`page.locator('.pkpListPanel').getByRole('button')` inside the list panel
returned zero labelled buttons besides the per-row/filter controls), the invitation
email has no row to open, and every search term that would find it returns
**"No items found."** Since the only "Add Template" affordance in this UI lives
*inside* a mailable's own dialog (see C-17's control), and there is no invitation
mailable dialog to open on OPS, there is nowhere on the screen from which an
alternate invitation template could be started.

**[claim] OJS control — and it is a narrower control than expected.** On OJS the
invitation email's row *does* exist, but pressing Edit lands on the bare Edit Template
form; there is **no "Add Template" button** for this email on OJS either (C-17). So
"add an alternate template for the invitation email" has no screen path in **any** of
the three apps; on OJS/OMP the reason is the dialog shape, on OPS the reason is that
the row does not exist. The alternate-template affordance is real on this screen but
only for composable mailables (Discussion (Review), Review Request).

**Settles**: **Corrects the shape of** OPS1's alternate-template consequence. The
consequence is true on OPS ("no path"), but it is not an OPS-only consequence — no
app's Emails screen offers an alternate-template path for the invitation email. The
OPS-specific loss is the *editing* row itself (C-16/C-17).

---

## C-19 — Full invite walk on OPS, with OJS/OMP composer + delivery as controls

*(P4 · P6 OPS half · scenario s8)*

**Role**: scratch Manager. **Screens**: `/index.php/{ctx}/management/settings/access`
(Users & Roles) → "Invite to a role" → `/index.php/{ctx}/invitation/create/userRoleAssignment`.

**Locators used throughout the send wizard**
- entry: `page.getByRole('button', {name: 'Invite to a role'})` on Users & Roles
- step 1 field: `page.locator('#-search-control')`; continue `page.getByRole('button', {name: 'Search User'})`
- step 2: `page.locator('#-userGroupId-control')` (role select),
  `page.locator('#-dateStart-control')` (`input[type=date]`),
  `page.locator('#-masthead-control')` (masthead select),
  `page.getByRole('button', {name: 'Add Another Role'})`,
  `page.getByRole('button', {name: 'Save And Continue'})`
- step 3: subject `input#userInvited-subject`, body TinyMCE iframe (control
  `#userInvited-body-control`), send `page.getByRole('button', {name: 'Invite user to the role'})`

### C-19a — OPS: the wizard opens and the role list is the 5 seeded groups

**[claim]** Users & Roles renders for the OPS scratch manager at
`/index.php/u6pcops/management/settings/access`, title "Users & Roles | Probe C
Server", with tabs "Users / Roles / Site Access Options / ORCID", the heading
**"Invitations (0)"**, the **"Invite to a role"** button, table headers
`NAME · EMAIL · INVITATIONS · STATUS · AFFILIATION · MORE ACTIONS`, and "No Items".

**[claim]** Wizard page heading and description, verbatim on OPS:

> "Invite user to take a role" — **"You are inviting a user to take a role in OPS
> along with appearing in the server masthead"**

Steps: **"1 Search User · 2 Enter details · 3 Review & invite for roles"**.

**[claim]** The "Select a new role" dropdown on OPS lists **exactly five options, in
this order**: `["Preprint Server manager", "Moderator", "Author", "Reader",
"Editorial Board Member"]`. **No Reviewer option of any kind.** This confirms the
draft's expectation (5 seeded groups, no Reviewer).

**[claim]** Masthead cell on OPS: **every** role row renders the selector, not fixed
text. Cell-by-cell for a two-row table (roles Moderator + Editorial Board Member),
`tbody tr` → `td`:

| cell | row 0 | row 1 |
|---|---|---|
| 0 | role select (`selects=1`) | role select (`selects=1`) |
| 1 | "Start Date * Required" | same |
| 2 | "---" (End Date) | same |
| 3 | **"Server Masthead * Required / Appear on the masthead / Does not appear on the masthead"**, `selects=1` | same, `selects=1` |
| 4 | "Remove Role" | same |

Column header row on OPS: **`ROLE · START DATE · END DATE · SERVER MASTHEAD`**.

**[context]** The "Select a new role" select has no blank/placeholder option; the
masthead select likewise starts unset — pressing "Save And Continue" with the role
and date filled but masthead untouched produced **"1 error detected! Please correct
the error below before proceeding."** with **"This field is required."** under the
masthead cell. (Validation wording belongs to Group B's B-9; noted here only because
it is what a walker hits.)

### C-19b — OPS: the composer step is filled, and the send succeeds

**[claim]** Step 3 on OPS renders fully. Heading and description verbatim:

> **"STEP 3 - Modify email shared with the user"** — "Send the user an email to let
> them know about the invitation, next steps, server GDPR polices and ORCID
> verification"

(the misspelling "polices" is on screen as written).

**[claim] The template picker on OPS *does* list the invitation template**, even
though the Emails management screen does not (C-16). The composer shows:

> "Email Templates" · [Find Template] · **"User Invited to Role Notification —
> Invitation to New Role Dear {$recipientName}, In…"**

**[claim]** The composer arrives **pre-filled** on OPS:
- To: `u6pc.ops.newcomer1@example.org` (plus "Add CC/BCC")
- **Subject: "You are invited to new roles"**
- Body (iframe), with the context name already substituted: *"Invitation to New Role
  / Dear {$RECIPIENTNAME}, / … to take on new roles at **Probe C Server** / At Probe C
  Server, we value your privacy … / {$EXISTINGROLES} {$ROLESADDED} / On accepting the
  invite, you will be redirected to Probe C Server. … / Accept Invitation / Decline
  Invitation / Kind regards, Probe C Server"*

**[claim]** Pressing **"Invite user to the role"** succeeds on OPS. A dialog appears,
verbatim:

> **"Invitation Sent"** — "u6pc.ops.newcomer1@example.org has been invited to new role
> in OPS. You can be updated about the user's decision on the Users & Roles page, your
> OPS notifications and/or your email" · [View All Users]

### C-19c — OPS: the email arrives, with both buttons

**[claim]** Mailpit, scoped `to:u6pc.ops.newcomer1@example.org`: **exactly 1 message**,
subject **"You are invited to new roles"**, From `Probe Manager
<u6pcmgrops@example.org>`. Body text verbatim (extract):

> "Invitation to New Role / Dear u6pc.ops.newcomer1@example.org, / In light of your
> expertise, you have been invited by **Probe Manager** to take on new roles at **Probe
> C Server** / … / **Newly assigned roles** / 1. **Moderator** / Starting from
> 2026-08-04 / Your name will appear in the Probe C Server's masthead as a Moderator.
> / On accepting the invite, you will be redirected to Probe C Server. … / **Accept
> Invitation** / **Decline Invitation** / Kind regards, Probe C Server"

**[claim]** Both buttons carry live links:
`http://127.0.0.1:8200/index.php/u6pcops/invitation/accept?id=133&key=TkuqaE` and
`…/invitation/decline?id=133&key=TkuqaE`.

**[context]** The greeting is "Dear u6pc.ops.newcomer1@example.org" — the recipient
variable resolves to the email address for a newcomer with no name entered.

### C-19d — OPS: acceptance completes and the role attaches

**[claim]** Opening the accept link signed out shows the acceptance flow with **three
steps and no ORCID step** (ORCID not configured — install fact):
**"1 Create OPS account · 2 Enter details · 3 Review & create account"**.

Step 1 verbatim: *"STEP 1 - Create OPS account / To get started with OPS and accept
the new role, you will need to create an account with us. For this purpose please
enter a username and password."* · Email (read-only) · Username * Required · Password
* Required ("It should be at least 6 characters long…") · Privacy Consent * Required
("Yes, I agree to have my data collected and stored according to the Privacy
Statement") · [Cancel] [Save and continue].

Step 3 verbatim: *"STEP 3 - Review & create account / Review details to start your new
roles in OPS"*, Roles table headed `ROLE · START DATE · END DATE · SERVER MASTHEAD`
with row "Moderator · 2026-08-04 · --- · Appear on the masthead"; final button
**"Accept And Continue to OPS"**.

**[claim]** After pressing it, a dialog appears verbatim:

> **"You've been assigned a new role in OPS"** — "Congratulations on your new role in
> OPS! You might now have access to new options. If you need any assistance in
> understanding the system, please click on the "Help" button throughout the system
> for guidance." · [View All Submissions]

**[claim]** Pressing **"View All Submissions"** lands on the **sign-in page**:
`/index.php/u6pcops/login?source=%2Findex.php%2Fu6pcops%2Fsubmissions`, page title
"Login | Probe C Server". The just-created account is not signed in at the end of the
acceptance flow. (Same on OMP — see C-21. The OJS control for this landing belongs to
D-23; I did not run it.)

**[claim]** Back as the OPS manager on Users & Roles: **Invitations (0) / "No Items"**
— the row is gone — and **Current Users (3)** now lists `Probe Newcomer ·
u6pc.ops.newcomer1@example.org · Moderator · 2026-08-04`.

### C-19e — OJS and OMP controls (composer + delivery)

**[claim] OJS** (`u6pcojs`, invitee `u6pc.ojs.newcomer1@example.org`, role Author):
step 3 description "…next steps, **journal** GDPR polices and ORCID verification";
template picker lists "User Invited to Role Notification"; subject pre-filled **"You
are invited to new roles"**; body pre-filled with "Probe C Journal" substituted.
Send dialog: **"Invitation Sent — u6pc.ojs.newcomer1@example.org has been invited to
new role in OJS…"**. Mailpit `to:u6pc.ojs.newcomer1@example.org`: 1 message, subject
"You are invited to new roles", links
`…:8000/index.php/u6pcojs/invitation/accept?id=289&key=k6R9Ze` + matching `decline`.

**[claim] OMP** (`u6pcomp`, invitees `u6pc.omp.cancel1@` role Author and
`u6pc.omp.newcomer2@` role Series editor): step 3 description "…next steps, **press**
GDPR polices and ORCID verification"; same template picker row; subject **"You are
invited to new roles"**; body with "Probe C Press" substituted. Send dialogs:
**"Invitation Sent — … has been invited to new role in OMP…"**. Mailpit: 1 message
each, both with accept + decline links (`…:8100/index.php/u6pcomp/invitation/accept?id=145&key=ik3Qvh`
and `?id=147&key=6C5zvN`).

**Settles**: **Corrects** the uncertainty half of OPS1. OPS invitations compose, send,
deliver and are accepted exactly as on OJS and OMP — the catalogue omission costs OPS
only the Emails-screen row (C-16/C-17/C-18), not the flow. **Confirms** the OPS role
list (5 groups, no Reviewer) and, on the masthead question, **confirms** that OPS's
fixed-text branch never triggers: every OPS row shows the selector.

---

## C-20 — Reviewer vs non-reviewer masthead cell (OJS + OMP)

*(P6 controls · fn-i)*

**Role**: scratch Manager on OJS and on OMP. **Screen**: send wizard, step 2, with a
newcomer email (`u6pc.ojs.probe2@example.org` / `u6pc.omp.probe2@example.org` — nothing
was sent for these two; the wizard was abandoned after reading the table).

**Locators**: role selects `select[id*="userGroupId"]` (`#-userGroupId-control` for
row 0); row cells `tbody tr` → `td`; masthead select `select[id*="masthead"]`
(`#-masthead-control`); second row added with
`page.getByRole('button', {name: 'Add Another Role'})`.

**[claim] OJS** — row 0 role **"Reviewer"**, row 1 role **"Author"**:

| cell 3 (masthead column) | reviewer row | non-reviewer row |
|---|---|---|
| text | **"Appear on the masthead"** | **"Journal Masthead * Required / Appear on the masthead / Does not appear on the masthead"** |
| `select` count | **0** | **1** |

The reviewer row's masthead cell is fixed text with no control; the Author row's cell
is a select whose two options are exactly `["Appear on the masthead", "Does not appear
on the masthead"]`. Column header: **`JOURNAL MASTHEAD`**.

**[claim] OMP** — same result, twice. With row 0 = **"External Reviewer"** and row 1 =
**"Author"**: reviewer cell is fixed text "Appear on the masthead", `select` count 0;
Author cell is **"Press Masthead * Required"** + the same two options, `select` count 1.
Repeating with row 0 = **"Internal Reviewer"** and row 1 = "Series editor" gives the
identical split — **both** OMP reviewer groups get the fixed text. Column header:
**`PRESS MASTHEAD`**.

**[claim] Full role dropdown option lists** (order as rendered):
- **OJS (18)**: Journal manager · Journal editor · Production editor · Section editor ·
  Guest editor · Copyeditor · Designer · Funding coordinator · Indexer · Layout Editor ·
  Marketing and sales coordinator · Proofreader · Author · Translator · **Reviewer** ·
  Reader · Subscription Manager · Editorial Board Member.
- **OMP (19)**: Press manager · Press editor · Production editor · Series editor ·
  Copyeditor · Designer · Funding coordinator · Indexer · Layout Editor · Marketing and
  sales coordinator · Proofreader · Author · Volume editor · Chapter Author · Translator ·
  **Internal Reviewer** · **External Reviewer** · Reader · Editorial Board Member.
- **OPS (5)**: Preprint Server manager · Moderator · Author · Reader · Editorial Board Member.

Note the OJS reviewer group's on-screen label is plain **"Reviewer"**, not "External
Reviewer" as the registry key suggests.

**Settles**: **Confirms** the Fields table's *(unconfirmed)* reviewer-masthead claim,
in both OJS and OMP, and extends it: on OMP it holds for Internal Reviewer as well as
External Reviewer. Together with C-19's OPS reading, the branch is confirmed to be
role-driven and to have no seeded trigger on OPS.

---

## C-21 — App-flavoured wording at every seam (OMP + OPS)

*(locale seam, inventory §3b)*

**Role**: scratch Manager (send side) and anonymous invitee (accept side).
Everything below is verbatim from the screen.

### Send wizard

| Seam | OMP | OPS |
|---|---|---|
| Wizard page description | "You are inviting a user to take a role in **OMP** along with appearing in the **press** masthead" | "You are inviting a user to take a role in **OPS** along with appearing in the **server** masthead" |
| Step 1 description tail | "…you can invite them to take up roles and be a part of your **press**." | "…be a part of your **server**." |
| Search outcome — **not found** | "The user does not have a role in this **press**" + "You can invite them to take up a role in **OMP**" | "The user does not have a role in this **server**" + "You can invite them to take up a role in **OPS**" |
| Search outcome — **found** | "The user already exists in the **press**" + "You can invite them to take up a role in **OMP**" | "The user already exists in the **server**" + "You can invite them to take up a role in **OPS**" |
| Masthead column header | **`PRESS MASTHEAD`** | **`SERVER MASTHEAD`** |
| Masthead field label | "**Press Masthead** * Required" | "**Server Masthead** * Required" |
| Step 3 description | "…next steps, **press** GDPR polices and ORCID verification" | "…next steps, **server** GDPR polices and ORCID verification" |
| Send-success dialog | "Invitation Sent — … has been invited to new role in **OMP**. You can be updated about the user's decision on the Users & Roles page, your **OMP** notifications and/or your email" · [View All Users] | same with **OPS** |

All three step names are app-neutral in both apps: "1 Search User · 2 Enter details ·
3 Review & invite for roles". Continue buttons: "Search User" (step 1), "Save And
Continue" (step 2), "Invite user to the role" (step 3); "Cancel" and "Back" present.

### Acceptance flow

| Seam | OMP | OPS |
|---|---|---|
| Step list | "1 **Create OMP account** · 2 Enter details · 3 Review & create account" | "1 **Create OPS account** · 2 Enter details · 3 Review & create account" |
| Step 1 description | "STEP 1 - Create **OMP** account / To get started with **OMP** and accept the new role, you will need to create an account with us. For this purpose please enter a username and password." | same with **OPS** |
| Step 2 ORCID line | "ORCID iD — Not verified. You can verify your ORCID iD from your profile section in **OMP**." | "…in **OPS**." |
| Step 3 description | "STEP 3 - Review & create account / Review details to start your new roles in **OMP**" | "…in **OPS**" |
| Roles table header on step 3 | `ROLE · START DATE · END DATE · **PRESS MASTHEAD**` | `… · **SERVER MASTHEAD**` |
| Final button | **"Accept And Continue to OMP"** | **"Accept And Continue to OPS"** |
| Success dialog | "**You've been assigned a new role in OMP** — Congratulations on your new role in **OMP**! You might now have access to new options. If you need any assistance in understanding the system, please click on the "Help" button throughout the system for guidance." · [View All Submissions] | same with **OPS** |

Step 2's own description is app-neutral in both: "STEP 2 - Enter details / Enter your
details like email ID, affiliation etc. As per the GDPR compliance, this information
can only modified by you. You can also choose if you want this information to be
visible on your profile to the editor." (the "can only modified" grammar is on screen
as written).

**[claim]** The step list's **accessible name is an untranslated raw key**: the step
list element is `<ol class="pkpSteps__buttons"
aria-label="##invitation.wizard.completeSteps##">`, observed on the OMP acceptance
flow, the OPS acceptance flow, **and** the OPS send wizard. Locator:
`page.locator('[aria-label*="invitation.wizard"]')` → exactly 1 match per page; read
via `getAttribute('aria-label')`. (Register entry A5 is owned by B-7/D-24; this is
the same defect observed independently on OMP and OPS, i.e. it is not OJS-only, and
it is present on both wizards, not only the acceptance one.)

**[claim]** On **both** OMP and OPS, pressing the success dialog's **"View All
Submissions"** lands on the sign-in page —
`/index.php/{ctx}/login?source=%2Findex.php%2F{ctx}%2Fsubmissions`, title "Login |
Probe C Press" / "Login | Probe C Server". The newly created account is not signed in.

**Settles**: **Confirms** the draft's unmarked glossary-substitution claims for OMP
and OPS at every seam listed in the item — press/server, OMP/OPS product name,
"Press Masthead"/"Server Masthead", "Create OMP/OPS account", "Accept And Continue to
OMP/OPS", and the app-named success dialog. **New**, beyond what the item asked: the
step-list accessible name is the raw key on OMP and OPS too, and "View All
Submissions" lands on the sign-in page rather than a dashboard on both.

---

## C-22 — "Invitation Unavailable" after cancelling, in all three apps

*(register A7 · Rule 13 page anatomy)*

**What I did**, per app: as the scratch Manager, on Users & Roles opened the pending
row's actions menu and chose "Cancel Invite", confirmed, then opened that
invitation's already-delivered **accept** link in a signed-out browser.

**Locators**
- row menu: `page.locator('tr', {hasText: <invitee email>}).getByRole('button', {name: 'Invitation management options'})`
  (icon-only button; the accessible name is its `aria-label`)
- menu items: `page.getByRole('menuitem')` → exactly two, **"Edit"** and **"Cancel Invite"**
- confirm dialog buttons: `[role="dialog"]` → `getByRole('button', {name: 'Cancel Invitation'})`
  (destructive) and `getByRole('button', {name: 'Cancel', exact: true})`
- unavailable page links: `page.getByRole('link', {name: 'Login'})` / `{name: 'Register'}`

**[claim]** The cancel confirmation dialog is identical in all three apps; verbatim
(OMP instance):

> **"Cancel Invitation"** / "Email: u6pc.omp.cancel1@example.org" / "Role: Author,
> Status: Invited 2026-07-28" / "Affiliation:" · [Cancel Invitation] [Cancel]

**[context]** No warning sentence appeared in that dialog in any of the three apps,
and the "Affiliation:" line was empty for these newcomers. D-29 owns the dialog's
anatomy on OJS; recorded here only because I had to pass through it.

**[claim]** After confirming, the manager's table reads **"Invitations (0) … No
Items"** in all three apps.

**[claim] The "Invitation Unavailable" page is byte-identical in all three apps**,
including the app-inappropriate role name. Verbatim, on OJS, OMP **and** OPS:

> **Invitation Unavailable**
> "This invitation is no longer available. It may have already been accepted,
> declined, or expired. **Please contact the journal manager for further assistance.**"
> [Login] [Register]

Page title follows the context ("Probe C Press" / "Probe C Server" / "Probe C
Journal"), but the sentence says **"journal manager"** on the press and on the
preprint server.

**[claim] Where the two buttons lead** — they are links, not buttons, and each stays
inside the context:

| App | "Login" href → landing | "Register" href → landing |
|---|---|---|
| OJS | `/index.php/u6pcojs/login` → "Login \| Probe C Journal" | `/index.php/u6pcojs/user/register` → "Register \| Probe C Journal" |
| OMP | `/index.php/u6pcomp/login` → "Login \| Probe C Press" | `/index.php/u6pcomp/user/register` → "Register \| Probe C Press" |
| OPS | `/index.php/u6pcops/login` → "Login \| Probe C Server" | `/index.php/u6pcops/user/register` → "Register \| Probe C Server" |

**Settles**: **Confirms** A7 exactly as drafted — the "journal manager" sentence is
shown unchanged to press and preprint-server visitors — and confirms Rule 13's page
anatomy (heading, the three-clause explanation, Login and Register leading to the
context's sign-in and registration pages) in all three apps.

---

## Cross-item summary of what each "Settles" line got

| Item | Verdict |
|---|---|
| C-16 | **Confirms** OPS1's no-row consequence (OJS/OMP controls both show the row) |
| C-17 | Settled: name/subject/body only, no role restriction control, on OJS and OMP; positive control located |
| C-18 | **Corrects** the framing — no path on OPS, but no app offers an alternate-template path for this email |
| C-19 | **Corrects** OPS1's "does it even send?" half — OPS sends, delivers and accepts normally; **confirms** OPS role list and masthead rendering |
| C-20 | **Confirms** the reviewer-masthead claim on OJS and OMP (and for both OMP reviewer groups) |
| C-21 | **Confirms** the glossary-substitution claims for OMP and OPS at every named seam |
| C-22 | **Confirms** A7 and Rule 13's page anatomy in all three apps |

**Undetermined: none.** All seven items produced a settling observation.

**Nothing blocked me.** One environment note worth carrying: the fleets refuse
`Host: localhost`; drive them at `127.0.0.1`.

---

## Incidental (outside Group C — one line each, not probed further)

- For an **existing** user, the wizard's "Select a new role" dropdown omits the roles
  that user already holds (OPS dropped "Preprint Server manager", OMP dropped "Press
  manager") — bears on B-9/B-15, not probed further.
- The send wizard's step-2 validation message for a missing masthead choice is
  "1 error detected! Please correct the error below before proceeding." + "This field
  is required." — B-9's territory.
- The step-list `aria-label` is the raw key `##invitation.wizard.completeSteps##` (A5)
  on both the accept flow and the send wizard, observed on OMP and OPS — B-7/D-24 own
  it on OJS.
- One pending OPS invitation is deliberately left un-actioned in the scratch server
  (`u6pc.ops.aria1@example.org`, id 139) — it was the locator-verification case.
- "View All Submissions" in the acceptance success dialog lands on the sign-in page on
  OMP and OPS; the OJS control belongs to D-23.
- OJS's Emails catalogue lists two rows named `orcidCollectAuthorId` and
  `orcidRequestAuthorAuthorization` as raw keys rather than human names (also on OMP);
  not a U6 surface.
