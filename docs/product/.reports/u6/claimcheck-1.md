# U6 — Claim check, CHUNK 1

**Frame.** QA documentation of an application's own screens, on a local disposable
test install with seeded accounts. Signed in as each role and used the screens the
way that role would — including typing a URL directly to reach one. Recorded what
the screen offers, what happens when it is used, and where the two disagree, so the
product team can fix it. No request the screens themselves would not send; nothing
here was driven by a hand-built API call or an altered form payload.

**Target text**: `docs/product/specs/user-invitations.md` —
(a) the whole **Actors & permissions** section (preamble + every table row, including
the marked divergences and the second older web address);
(b) the **Reference — entry points & surfaces** table.

**Date**: 2026-07-28 · **Checker**: Opus claim-check agent, chunk 1 of 3.

**Apps / fleets**: OJS `http://127.0.0.1:8000`, OMP `http://127.0.0.1:8100`,
OPS `http://127.0.0.1:8200`. Every permission and reachability claim below was run
on all three fleets unless the line says otherwise.

**Scratch fixtures** (all `u6cc1*`, created through the scenario endpoints; the
seeded roster and `publicknowledge` were used read-only; Mailpit never cleared):

| Context (per app) | What it holds |
|---|---|
| `u6cc1{ojs,omp,ops}` | `u6cc1mgr` (manager), `u6cc1sed` (sectionEditor), `u6cc1asst` (editorialBoardMember), `u6cc1rdr` (reader), `u6cc1exist` (author); 3 seeded invitations (newcomer pending, existing-user pending, expired) |
| `u6cc1b*` | manager + newcomer/existing/expired invitations — used for the recipient-side journeys |
| `u6cc1d*` | manager, sectionEditor, a two-role user — immediate-role-change tests |
| `u6cc1e*` | manager, sectionEditor, a three-role user — section-editor role-removal test |
| `u6cc1f*` (OJS/OMP) | a user in the *Journal editor* / *Press editor* group (manager permission level) — the "Permit changes to Settings" test |
| `u6cc1g*` | manager + two pending invitations — decline and wrong-user tests |
| `u6cc1h*` | manager, sectionEditor, assistant + two pending invitations — typed-address edit test |

**The site-admin masking trap was removed first.** Creating any scratch context
enrols the creating site administrator as that context's manager. In each
`u6cc1{app}` context I signed in as `admin`, self-enrolled as Reader on
*Profile → Roles* (so the manager role was not the last one), then opened
*Users & Roles → Users → row menu → Edit* on the `admin admin` row and pressed
**Remove Role** on the manager row, confirming the "Remove Role" dialog. Verified
afterwards that `publicknowledge` on all three fleets still shows `admin admin`
with only the manager role — no seeded fixture was touched.

Locator shorthand used throughout: **the refusal page** = the page at
`/{ctx}/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`
whose body reads "The current role does not have access to this operation."

---

## Summary

**27 claims checked** — 23 **holds**, 3 **wrong**, 1 **undetermined**, 0 unreachable.

| ID | Claim | Verdict |
|----|-------|---------|
| C1 | Inviter-side surfaces are Users & Roles (Settings → Users & Roles, "Users" tab) + the wizard it opens | holds |
| C2 | Recipient-side needs no account or sign-in; reached only through the emailed link; the link is the credential | holds |
| C3 | A pending invitation is one sent and not yet accepted / declined / cancelled / expired | holds |
| C4 | Section Editor and Assistant are offered no way in by any screen | holds |
| C5 | Typing the wizard's address gives a Section Editor a working wizard that really sends; every exit then refuses them | holds |
| C6 | Journal Manager sees the table while their role keeps "Permit changes to Settings"; clearing it takes the whole screen away | **wrong** |
| C7 | Site Administrator — on OJS; OMP and OPS refuse one who is not also the journal's own manager | holds |
| C8 | A second, older web address admits only Site Administrators and refuses every manager | holds |
| C9 | "Invite to a role" — the same actors as the table; the button sits above it | holds |
| C10 | Edit a pending invitation — the same actors, via the row's "Invitation management options" menu, "Edit" | **wrong** |
| C11 | Cancel a pending invitation — the same actors, same menu, "Cancel Invite" | holds |
| C12 | Change an existing user's current roles from inside the wizard — the same actors | **wrong** |
| C13 | …takes effect immediately, before any invitation is sent | holds |
| C14 | Open the acceptance page — whoever holds a pending link, no sign-in; spent/expired lands on "Invitation Unavailable" | holds |
| C15 | Accept — never signs anyone in; newcomer creates an account, existing signed-out user gets a single review step; both end at the sign-in page | holds |
| C16 | If someone else is signed in, acceptance is refused | holds |
| C17 | Decline — the link holder, via the email's decline link and a confirmation page; no sign-in | holds |
| C18 | Reference: Invitations table @ Settings → Users & Roles → Users tab | holds |
| C19 | Reference: send wizard @ the button, `/{ctx}/invitation/create/userRoleAssignment`, `/{ctx}/invitation/edit/{id}` | holds |
| C20 | Reference: send wizard, edit-user mode @ users row "Edit" → `/{ctx}/management/settings/user/{userId}` | holds |
| C21 | Reference: acceptance flow @ email "Accept Invitation" → `/{ctx}/invitation/accept?id&key` | holds |
| C22 | Reference: decline page @ email "Decline Invitation" → `/{ctx}/invitation/decline?id&key` | holds |
| C23 | Reference: "Invitation Unavailable" @ any spent/expired invitation link | holds |
| C24 | Reference: invitations lifecycle API @ `/{ctx}/api/v1/invitations/…` | holds |
| C25 | Reference: invitation email sent on "Invite user to the role" | holds |
| C26 | Reference: validity window @ `[invitations] expiration_days` | holds |
| C27 | Reference: expired-invitation cleanup @ daily scheduled job | **undetermined** |

The three disproved claims are C6, C10 and C12 — all in the Actors table, all
about **who may do what**.

---

## Actors & permissions — preamble

### C1 — the inviter-side surfaces

> "**inviter-side** actions live on the Users & Roles screen (Settings → Users &
> Roles, "Users" tab) and the send-invitation wizard it opens"

**What I did.** Signed in as `u6cc1mgr` in each app, landed on
`/{ctx}/dashboard/editorial`, clicked the left-menu **Settings** entry
(`page.getByRole('link', {name: 'Settings', exact: true})` — an `<a href="#">` that
expands a submenu), then the revealed **Users & Roles** entry
(`page.getByRole('link', {name: 'Users & Roles', exact: true})`).

**What appeared.** In all three apps the submenu link's `href` is
`…/{ctx}/management/settings/access`; the landed page's `h1` is "Users & Roles";
the tab strip (`button.pkpTabs__button`) reads
`["Users","Roles","Site Access Options","ORCID"]` with **Users** already selected;
the heading "Invitations (2)" and the "Invite to a role" button are on that tab.
Clicking the button navigates to `/{ctx}/invitation/create/userRoleAssignment`
(`h1` = "Invite user to take a role").

**Verdict: holds** (OJS, OMP, OPS).

---

### C2 — recipient-side needs no account, and the link is the credential

> "**recipient-side** actions need no account or sign-in — they are reached only
> through the personal link in the invitation email, and the link is the
> credential."

**What I did.** Opened each recipient link in a **fresh browser context with no
cookies** (signed out) in each app: the pending-newcomer accept link, the
existing-user accept link, the expired accept link, the expired decline link and a
pending decline link. Then, as an ordinary address-bar exercise, opened the accept
address of a live invitation with (a) the `id` but no `key`, (b) the `id` and a
wrong `key`, (c) no parameters at all.

**What appeared.** Every valid link rendered its page with no sign-in prompt and no
user menu: the newcomer wizard ("STEP 1 - Create OJS/OMP/OPS account"), the
existing-user review step, "Invitation Unavailable", "Decline Invitation". Wrong
key → a bare **404 Not Found** page in all three apps. `id` without `key`, and no
parameters at all → a completely blank page (HTTP 500) in all three apps.

**Verdict: holds.** Possession of the link is the whole gate; the key is required
and a wrong or missing key yields nothing.

*Incidental (not a chunk-1 verdict, relevant to Rule 13 / A15):* the "blank page"
the spec attributes to a link that names no invitation is served as an HTTP 500,
and the same blank page appears for a link that names an invitation but carries no
key.

---

### C3 — what "pending" means

> "A **pending invitation** is one that has been sent and not yet accepted,
> declined, cancelled, or expired."

**What I did.** Each `u6cc1{app}` context was seeded with three invitations —
newcomer pending, existing-user pending, and one whose expiry date is in the past.
Read the manager's Invitations table. Separately, declined the `u6cc1g-dec-*`
invitation through the emailed decline page and re-read that context's table.

**What appeared.** The table heading read **"Invitations (2)"** in all three apps —
the expired one is absent from the list and from the count. After the decline, the
`u6cc1g*` table went from "Invitations (2)" to "Invitations (1)" and the declined
invitee's row was gone, in all three apps. Every listed row's status cell read
"Invited {date}".

**Verdict: holds.**

---

### C4 — Section Editor and Assistant get no way in from any screen

> "Two more roles (Section Editor and Assistant) are offered no way in by any
> screen"

**What I did.** Signed in as `u6cc1sed` (Section editor / Series editor /
Moderator) and `u6cc1asst` (Editorial Board Member) in each app and went to
`/{ctx}/dashboard/editorial`. Enumerated every `nav a, nav button`; counted
`getByRole('link', {name:'Settings', exact:true})`,
`getByRole('link', {name:'Users & Roles', exact:true})` and
`getByRole('button', {name:'Invite to a role'})`.

**What appeared.** Zero of each, for both roles, in all three apps. The Section
Editor's nav carries a Statistics submenu (including a "Users" *report* link, which
is not Users & Roles); the Assistant's nav stops at "Start A New Submission".
Both roles are turned away at `/{ctx}/management/settings/access` and at
`/{ctx}/management/access` — the refusal page, in all three apps.

**Verdict: holds.**

---

### C5 — the Section Editor's typed-in wizard sends for real, and every exit refuses

> "yet typing the wizard's address gives a Section Editor a working wizard that
> really sends — and every exit then refuses them ⚠ A8"

**What I did.** As `u6cc1sed`, typed
`/{ctx}/invitation/create/userRoleAssignment` in each app, searched an unmatched
email address, filled the waiting role row (role select, start date, masthead
select — addressed structurally as the role table's first `tbody tr`, per the
shared-identifier defect), pressed "Save And Continue", then "Invite user to the
role". Then took three different exits: the success dialog's button; the wizard's
"Cancel" button; and the breadcrumb "Users & Roles" link.

**What appeared.** All three apps: step 2 and step 3 render normally, the composer
shows a filled template, and pressing "Invite user to the role" produces the dialog
**"Invitation Sent — {email} has been invited to new role in OJS/OMP/OPS …"** with
a single **"View All Users"** button. A real email arrived in Mailpit for each app
(subject "You are invited to new roles", addressed to
`u6cc1-sed-send-{app}@example.org`), carrying working "Accept Invitation" and
"Decline Invitation" links.

Exits, all three apps:
- success dialog "View All Users" → the refusal page;
- "Cancel" (pressed at step 1) → the refusal page, with no confirmation dialog;
- breadcrumb "Users & Roles" (`href` = `…/{ctx}/management/settings/access`) → the
  refusal page.

**Verdict: holds** — and the "every exit" wording survives a third exit the spec
does not name (the breadcrumb).

*Incidental:* the role list the Section Editor is offered in that wizard includes
the journal's manager-level roles — the invitation I sent was to "Journal editor".
Nothing in the wizard narrows the offer for the role that reached it by address.

---

## Actors & permissions — the table

### C6 — Journal Manager and "Permit changes to Settings" · **WRONG**

> "**Journal Manager** — while their role keeps the "Permit changes to Settings"
> option on the Roles screen; clearing it takes the whole Users & Roles screen
> away"

**What I did.** Two parts.

*(i) Can the option be cleared for the role a Journal Manager actually holds?*
Signed in as `u6cc1emgr{app}` (the default *Journal manager* / *Press manager* /
*Preprint Server manager* group) in each app, opened Users & Roles → **Roles** tab
and enumerated the grid rows (`tr[id^=component-grid-settings-roles-usergroupgrid-row-]`)
with their permission levels, plus which rows carry an Edit control
(`tr[id$=control-row] a[id*=editUserGroup]`).

*(ii) Does clearing it take the screen away?* In `u6cc1f{ojs,omp}` I seeded a user
in the *Journal editor* / *Press editor* group (permission level Journal/Press
Manager). Confirmed they reach Users & Roles. Then, as `admin`, expanded that
role's grid row, clicked its **Edit** link, unticked **"Permit changes to
Settings"** (`input#permitSettings`, initially checked) in the Edit Role modal and
pressed **OK**. Re-checked the role holder.

**What appeared.**

*(i)* In **all three apps** the grid's **row 0 — the default manager group** —
carries **no Settings expander and no Edit or Remove link at all**. Its "Permit
changes to Settings" option cannot be reached from the Roles screen in OJS, OMP or
OPS. Every other role row has one.

Manager-level groups per app:
- OJS — "Journal manager" (no Edit), "Journal editor", "Production editor";
- OMP — "Press manager" (no Edit), "Press editor", "Production editor";
- **OPS — "Preprint Server manager" (no Edit) and nothing else.** OPS's next role
  is "Moderator" at Moderator level. There is **no** manager-level role on OPS
  whose option can be cleared.

*(ii)* On OJS and OMP, after unticking the option, the holder of that role goes
from "Users & Roles" (`h1`, "Invite to a role" button present) to **the refusal
page** at `/{ctx}/management/settings/access`. That half of the sentence is real.

**Verdict: wrong**, as an unmarked claim asserting the same behaviour in all three
apps. What the screens actually do:

- The consequence the sentence describes — clearing the option takes the whole
  Users & Roles screen away — is real, and I watched it on OJS and OMP.
- But it can only be exercised on a *secondary* manager-level role (Journal editor
  / Press editor / Production editor). For the plain **Journal Manager** role the
  sentence names, the option is not editable on any of the three apps — the default
  manager group's row offers no Edit control at all.
- **On OPS the condition cannot arise**, because the default manager group is the
  only manager-level role the app ships and it has no Edit control.

**Second, separate observation on the same test:** on OJS and OMP, clearing the
option takes away the Users & Roles *screen* but **not the send-invitation
wizard** — the same user, immediately afterwards, still opens
`/{ctx}/invitation/create/userRoleAssignment` and gets "Invite user to take a role"
with no refusal. The gate the Actors row describes covers one of the two
inviter-side surfaces.

---

### C7 — Site Administrator, per app

> "**Site Administrator** — on OJS; OMP and OPS refuse one who is not also the
> journal's own manager ⚠ A4"

**What I did.** With the creating-admin manager enrolment stripped (see above),
signed in as `admin` and typed `/{ctx}/management/settings/access` in each app.
Control: the same address before the strip.

**What appeared.**

| App | Before the strip (admin also manager) | After the strip (admin only) |
|---|---|---|
| OJS | Users & Roles, "Invitations (2)", "Invite to a role" ×1 | **Users & Roles**, "Invitations (2)", "Invite to a role" ×1 |
| OMP | Users & Roles, "Invitations (2)", button present | **the refusal page** |
| OPS | Users & Roles, "Invitations (2)", button present | **the refusal page** |

The same split appears at `/{ctx}/management/settings/user/1` (the edit-user mode):
admitted on OJS, refusal page on OMP and OPS.

**Verdict: holds.**

---

### C8 — the second, older web address

> "the same screen also answers at a second, older web address that admits only
> Site Administrators and refuses every manager ⚠ A2"

**What I did.** Typed `/{ctx}/management/access` in each app as five different
signed-in users: `admin` while still a manager, `admin` after the strip,
`u6cc1mgr` (manager), `u6cc1sed` (section editor), `u6cc1asst` (assistant) and
`u6cc1rdr` (reader).

**What appeared.**

| Who | OJS | OMP | OPS |
|---|---|---|---|
| `admin` (also manager) | Users & Roles | Users & Roles | Users & Roles |
| `admin` (not a manager) | Users & Roles, "Invitations (2)", "Invite to a role" | Users & Roles + Error modal (below) | Users & Roles + Error modal (below) |
| `u6cc1mgr` (manager) | **refusal page** | **refusal page** | **refusal page** |
| `u6cc1sed` | refusal page | refusal page | refusal page |
| `u6cc1asst` | refusal page | refusal page | refusal page |
| `u6cc1rdr` | refusal page | refusal page | refusal page |

**Verdict: holds** — the older address admits site administrators only and refuses
every manager, in all three apps, and on OMP/OPS it is indeed the only door a
non-manager site administrator has to the Invitations table.

**One thing the sentence does not prepare a reader for.** On **OMP and OPS**, a
non-manager site administrator arriving at the older address gets the Users & Roles
screen **underneath a modal reading "Error — The current role does not have access
to this operation." with a single "OK" button**. It is not the screen's own guard:
the page's own data loads fine, and the modal is raised by a background editorial
count the app requests on every page for that user (I watched the failing response
in the browser's own network activity — the only 4xx on the page — and OJS does not
raise it). Pressing "OK" leaves a fully working screen: `Invite to a role` is
present and clicking it opens the wizard at
`/{ctx}/invitation/create/userRoleAssignment`. Worth knowing because the modal
covers the button and would read to a QA person as the refusal itself. The same
modal greets that user at the wizard address on OMP/OPS.

---

### C9 — "Invite to a role"

> "**Invite to a role** (open the send-invitation wizard and send) — the same
> actors as the table — the "Invite to a role" button sits above it"

**What I did.** For each actor that reaches the table (manager everywhere; site
administrator on OJS via the menu address, on OMP/OPS via the older address),
counted `getByRole('button', {name: 'Invite to a role'})`, compared its document
position against the first `<table>`, and clicked it.

**What appeared.** Exactly one button in every admitted case, in all three apps;
`compareDocumentPosition` reports **button-before-table** in all three; clicking it
lands on `/{ctx}/invitation/create/userRoleAssignment` with `h1` = "Invite user to
take a role". No non-admitted role sees the button anywhere.

**Verdict: holds.**

---

### C10 — Edit a pending invitation · **WRONG**

> "**Edit a pending invitation** — the same actors — via the row's "Invitation
> management options" menu, "Edit" (Rule 6)"

**What I did.** Two parts.

*(i) The named path.* As `u6cc1mgr` in each app, on a pending row clicked the
button whose accessible name is exactly **`Invitation management options`**
(`button[aria-label="Invitation management options"]`) and read the menu items.

*(ii) The falsifier.* The Reference table lists `/{ctx}/invitation/edit/{id}` as a
public address of this wizard. So I typed it. In `u6cc1h{app}` (two pending
invitations each) I signed in as `u6cc1hsed{app}` (Section editor / Series editor /
Moderator) and `u6cc1hast{app}` (Editorial Board Member) and typed
`/{ctx}/invitation/edit/{id}` for one invitation each, then pressed "Save And
Continue" and "Invite user to the role". Control: `u6cc1rdr` (reader) at the same
address.

**What appeared.**

*(i)* Two row menus per table, and the menu items are exactly
`["Edit", "Cancel Invite"]` — identical in OJS, OMP and OPS.

*(ii)* In **all three apps**, both the Section Editor and the Assistant:
- reached the edit-invitation wizard (`h1` = the invitee's own name, step
  "STEP 1 - Enter details and invite for roles" — the search step is omitted);
- had an **enabled** "Save And Continue" and reached "STEP 2 - Modify email shared
  with the user";
- pressed "Invite user to the role" and got **"Invitation Sent — … has been invited
  to new role in OJS/OMP/OPS"**;
- a real email arrived in Mailpit for each of the six sends
  (`u6cc1h-p1-*` and `u6cc1h-p2-*`, subject "You are invited to new roles");
- the manager's Invitations table afterwards shows both rows re-dated to today.

The reader was refused (the refusal page), so this is not "any signed-in user".

**Verdict: wrong.** In product terms: **editing and resending a pending invitation
is not restricted to the actors who see the table.** A Section Editor or an
Assistant who has the invitation's address — which is an ordinary web address, the
same one the manager's "Edit" menu item navigates to — can reopen any pending
invitation of their journal, change it and send a fresh invitation email that
supersedes the first. The Assistant reaches this even though the *create* wizard
stops them at its search step: the edit wizard has no search step to stop them at.

---

### C11 — Cancel a pending invitation

> "**Cancel a pending invitation** — the same actors — via the same menu, "Cancel
> Invite" (Rule 7)"

**What I did.** Opened the "Invitation management options" menu on a pending row as
`u6cc1mgr` in each app and read the items; checked that no other route to
cancellation is offered on any screen a Section Editor, Assistant or Reader can
reach (the Reference table lists no cancel address, and neither of those roles
reaches a table with row menus).

**What appeared.** The menu's second item is **"Cancel Invite"** in all three apps.
No cancel affordance appears on any screen reachable by the roles that are not
listed.

**Verdict: holds.**

---

### C12 — Changing an existing user's current roles from inside the wizard · **WRONG**

> "**Change an existing user's current roles** (remove a role, change masthead
> display) from inside the wizard — the same actors"

**What I did.** In `u6cc1e{app}` I seeded `u6cc1etri{app}` holding three roles
(Author, Reader, Editorial Board Member) and `u6cc1esed{app}` (Section editor /
Series editor / Moderator). As the **Section Editor**, typed
`/{ctx}/invitation/create/userRoleAssignment`, searched that user's email address,
landed on "STEP 2 - Enter details and invite for roles", and pressed **Remove
Role** on the row whose first cell reads "Author" (rows addressed structurally, not
by accessible name). Confirmed the dialog. Then signed in as the context's manager
(`u6cc1emgr{app}`) and read the users table.

**What appeared.** In **all three apps**:
- the search answered "The user already exists in the journal / press / server" and
  the details step listed the user's current roles with a **Remove Role** button
  beside each;
- pressing it produced the dialog "**Remove Role** — Are you sure you want to
  remove this role? The user will lose access and permissions associated with it."
  with [Remove Role] [Cancel];
- confirming ended the role: the row gained today's end date and its action became
  the non-button text "User Removed From Role";
- **the manager then saw the user with only "Reader" and "Editorial Board Member"** —
  the role really is gone.

**Verdict: wrong.** In product terms: **a Section Editor who types the wizard's
address can end another user's role for real**, in OJS, OMP and OPS. The Actors
table lists this action as belonging to the table's actors only. This is the same
door as C5/A8, but the consequence is heavier than sending an invitation: it is a
destructive change to someone else's access, and it is not undone by leaving the
wizard.

---

### C13 — the change takes effect before any invitation is sent

> "takes effect immediately, before any invitation is sent ⚠ A3"

**What I did.** As `u6cc1dmgr{app}` in each app, opened the users table row menu
(`button[aria-label="##userAccess.management.options##"]`) for the two-role user
`u6cc1dtwo{app}`, chose **Edit** → the wizard at
`/{ctx}/management/settings/user/{userId}`, pressed **Remove Role** on the Author
row, confirmed, then **navigated away without sending anything**, and re-read the
users table.

**What appeared.** In all three apps the confirmation dialog is the same "Remove
Role" text; the row immediately shows an end date of today and "User Removed From
Role"; and after abandoning the wizard the users table lists the user with
**"Reader"** only. Nothing was sent.

**Verdict: holds.**

---

### C14 — opening the acceptance page

> "**Open the acceptance page** — whoever holds a pending invitation's emailed link
> — no sign-in required; a spent or expired link lands on "Invitation Unavailable"
> instead (Rule 13)"

**What I did.** In a signed-out browser context, per app: opened the pending
newcomer accept link, the pending existing-user accept link, the **expired** accept
link and the **expired** decline link; and, after declining an invitation, re-opened
its accept link.

**What appeared.** All three apps: pending links render their flow with no sign-in
prompt. The expired accept link **and** the expired decline link both land on
`h1` = "Invitation Unavailable" with the text "This invitation is no longer
available. It may have already been accepted, declined, or expired. Please contact
the journal manager for further assistance." and Login / Register buttons. A
declined invitation's accept link lands on the same page.

**Verdict: holds.**

---

### C15 — accepting never signs anyone in

> "**Accept the invitation** — the link holder — the flow never signs anyone in: a
> newcomer creates an account, an existing signed-out user gets a single review
> step attaching the role to their account (Rule 10), and both end at the sign-in
> page ⚠ A12"

**What I did.** Signed-out browser contexts, per app.
*Existing user*: opened the existing-user accept link, read the step list, pressed
"Accept And Continue to OJS/OMP/OPS", then the success dialog's button.
*Newcomer*: opened the newcomer accept link, completed "STEP 1 - Create {APP}
account" (username `-username-control`, password `-password-control`, the
`privacyStatement` checkbox), "STEP 2 - Enter details" (given/family name,
country), then "STEP 3 - Review & create account" → "Accept And Continue to
{APP}", then the dialog's button.

**What appeared.** Identical in all three apps.

- Existing user: exactly **one** step, "STEP 1 - Review & create account", listing
  the invited role; no account step, no details step. Accepting shows "You've been
  assigned a new role in OJS/OMP/OPS …" with a single **"View All Submissions"**
  button, which lands on
  `/{ctx}/login?source=%2Findex.php%2F{ctx}%2Fsubmissions` — `h1` = "Login".
- Newcomer: the three steps as described; the same success dialog; the same landing
  on the sign-in page.
- In neither case does the landed page carry a user menu, an "Editor Dashboard" or
  a "Logout" — nobody is signed in.

**Verdict: holds.**

*Incidental (Rule 10's territory):* the existing-user review step's progress
indicator renders the literal text `{$current}/{$total} steps` beneath
"1/1 steps", in all three apps.

---

### C16 — acceptance while someone else is signed in

> "if someone else is signed in, acceptance is refused (Rule 12)"

**What I did.** Signed in as `u6cc1gmgr{app}` (a manager, not the invitee) and
pasted the pending accept link of a different person's invitation into the address
bar, in each app.

**What appeared.** All three apps: a modal reading "**Invitation not accepted.
You're logged in as a different user.** Please log out and sign in with the correct
account to accept this invitation." with a single **"Logout"** button.

**Verdict: holds.**

*Incidental:* the modal sits over a fully rendered "STEP 1 - Create {APP} account"
form with an empty Email field — the refused page still draws the wizard behind the
refusal.

---

### C17 — declining

> "**Decline the invitation** — the link holder — via the email's decline link and
> a confirmation page (Rule 11); no sign-in required"

**What I did.** In a signed-out context, per app: opened the decline link, read the
page, pressed **"Confirm Decline Invitation"**; then re-opened the same
invitation's accept link; then read the manager's Invitations table.

**What appeared.** All three apps: `h1` = "Decline Invitation", body "Are you sure
you want to decline this invitation? Confirm the decline by clicking the button
below.", one button. After confirming, the browser lands on `/{ctx}/login`
(`h1` = "Login") with nobody signed in. The accept link then shows "Invitation
Unavailable", and the manager's table count drops by one with the row gone.

**Verdict: holds.**

---

## Reference — entry points & surfaces

Every row was opened as described. Nothing in this table is dead.

| # | Row | What I did / what appeared | Verdict |
|---|---|---|---|
| C18 | Invitations table @ Settings → Users & Roles → Users tab | Menu-walked it as the manager in all three apps (C1). Tabs `["Users","Roles","Site Access Options","ORCID"]`, Users pre-selected, "Invitations (n)" heading, row menus `Invitation management options` with Edit / Cancel Invite. | holds |
| C19 | Send wizard @ the button, `/{ctx}/invitation/create/userRoleAssignment`, `/{ctx}/invitation/edit/{id}` | Button → the create address (all apps). Create address typed directly → `h1` "Invite user to take a role", three steps (all apps). `/{ctx}/invitation/edit/{id}` typed directly as the manager → 200, `h1` = the invitee's name, "STEP 1 - Enter details and invite for roles" (all apps). | holds |
| C20 | Send wizard, edit-user mode @ users row "Edit" → `/{ctx}/management/settings/user/{userId}` | Users-table row menu (`aria-label="##userAccess.management.options##"`) items `["Edit","Email","Login As","Remove User","Disable User","Merge user"]`; "Edit" navigates to `/{ctx}/management/settings/user/{id}` in all three apps. The page's `h1` is empty (A9). | holds |
| C21 | Acceptance flow @ email "Accept Invitation" → `/{ctx}/invitation/accept?id&key` | Read the delivered invitation email in Mailpit for each app: an anchor whose visible text is exactly "Accept Invitation" pointing at `http://127.0.0.1:{port}/index.php/{ctx}/invitation/accept?id=…&key=…`. Followed it signed-out → the acceptance flow. | holds |
| C22 | Decline page @ email "Decline Invitation" → `/{ctx}/invitation/decline?id&key` | Same emails: an anchor "Decline Invitation" → `/{ctx}/invitation/decline?id=…&key=…`. Followed signed-out → the "Decline Invitation" page. | holds |
| C23 | "Invitation Unavailable" @ any spent/expired invitation link | Reached from an expired accept link, an expired decline link and a declined invitation's accept link, in all three apps. | holds |
| C24 | Invitations lifecycle API @ `/{ctx}/api/v1/invitations/…` | Watched the browser's own network activity while the manager's Users & Roles screen loaded: the screen issues `GET /index.php/{ctx}/api/v1/invitations/userRoleAssignment?offset=0&count=5&page=1&perPage=5` in all three apps. No hand-built request was made. | holds |
| C25 | Invitation email @ sent on "Invite user to the role" | Nine sends across the session (three by a Section Editor in the create wizard, six by a Section Editor / Assistant in the edit wizard); every one produced exactly one Mailpit message to the invitee, subject "You are invited to new roles". | holds |
| C26 | Validity window @ `[invitations] expiration_days` | The section exists with `expiration_days = 3` in all three apps' `config.TEMPLATE.inc.php`, and OMP's and OPS's `config.test.inc.php` carry it explicitly (OJS's test config inherits the default). Observed effect on all three fleets: a seeded invitation stamped `invitedAt 2026-07-28` carries `expiryDate 2026-07-31` — three days. | holds |
| C27 | Expired-invitation cleanup @ daily scheduled job | The scheduled task is registered (`PKPScheduler` schedules `PKP\task\RemoveExpiredInvitations`, which dispatches `RemoveExpiredInvitationsJob`), but all three fleets run with `[schedule] task_runner = Off`, so nothing fires. The expired invitation's absence from the manager's table is explained by the listing scope alone (C3), not by deletion — so this install cannot show the row's claim either way. | **undetermined** |

**What would settle C27:** an install with the task runner on, where an expired
invitation's row is gone from the database the day after — not observable through a
screen, so it is out of this campaign's line.

---

## Notes for the merge agent

Three Actors-table rows are wrong, and all three fail in the same direction: the
table's "the same actors" shorthand is narrower than what the screens actually let
happen.

- **C10 and C12 are the same door as A8 opening onto two more actions.** A8 already
  records that a Section Editor who types the create wizard's address can send. What
  this chunk adds is that (a) a Section Editor can also *end another user's role*
  from that wizard, and (b) both a Section Editor **and an Assistant** can *edit and
  resend a pending invitation* by typing `/{ctx}/invitation/edit/{id}`. The
  Assistant case also qualifies A1's "an Assistant … is stopped at the search step":
  true of the create wizard, false of the edit wizard, which has no search step.
- **C6 is a divergence the row does not mark.** The condition it states cannot be
  exercised on OPS at all, and cannot be exercised for the default manager role on
  any app. Its second half (clearing removes the screen) was watched live on OJS and
  OMP.
- **C8's OMP/OPS Error modal** is a presentation fact a QA reader will hit
  immediately and misread as the refusal. It is raised by an unrelated background
  request, not by the Users & Roles guard, and dismissing it leaves a working screen.

Nothing blocked this chunk. Two items I noticed while checking, both outside chunk
1's text and left for whoever owns them: the existing-user review step renders a
literal `{$current}/{$total} steps` token in all three apps; and the "Reference —
code anchors" list points at `lib/pkp/classes/jobs/invitations/RemoveExpiredInvitationsJob.php`,
while the file is at `lib/pkp/jobs/invitations/RemoveExpiredInvitationsJob.php`.
