# U6 probe 4 — Edit User entry point (10), Expiry (11), Disabled-user guard (12)

Probe run 2026-07-31, RUNBOOK step 3, facts only. All flows driven through the
screens as a signed-in user (Playwright chromium, headless; scripts in the
session scratchpad, none in any repo).

Scratch contexts (created via `_test` context scenario, one per fleet, contextId 5 each):
`u6rojs` (OJS :8000), `u6romp` (OMP :8100), `u6rops` (OPS :8200). Seeded members:
`u6rmgr` (Journal manager — the acting manager everywhere below), `u6rduo`
(two roles: Section editor + Author; OMP: Series editor + Author; OPS: Moderator + Author),
`u6rdis` (Author; the disable target). Scratch contexts auto-enroll `admin` as
Journal manager (+1 in user counts). Mailpit lookups scoped to
`u6rduo@mail.test`, `u6rdis@mail.test`, `u6p11-ojs@mail.test` only.

---

## Item 10 — Edit User entry point

### OJS (deep) — base `http://127.0.0.1:8000`, signed in as `u6rmgr`

**Users list.** `/index.php/u6rojs/management/settings/access` → heading
"Users & Roles", tabs `Users | Roles | Site Access Options | ORCID`. The Users
tab stacks two panels: "Invitations (n)" (with top-right button "Invite to a
role") and "Current Users (n)" (search box "Enter a user's name, role (e.g
Journal editor), or affiliation"). The u6rduo row read
`u6rduo | u6rduo@mail.test | Section editor Author | 2026-07-31`.
Row action menu (opened from the per-row "…" button): `Edit, Email, Login As,
Remove User, Disable User, Merge user`.
- Missing locale keys on this screen: the row-menu button's aria-label renders
  raw as `##userAccess.management.options##`; the top bar shows `##common.help##`.

**Edit → wizard WITHOUT a search step (claim confirmed).** "Edit" navigates to
`/index.php/u6rojs/management/settings/user/25` — page title path
"Users & Roles / Invite user to take a role", step rail exactly
`1 Enter details · 2 Review & invite for roles` (two steps). The new-invite
flow reached from "Invite to a role" (`/invitation/create/userRoleAssignment`)
has three: `1 Search User · 2 Enter details · 3 Review & invite for roles`.
Step 1 (edit mode) shows read-only identity (Email `u6rduo@mail.test`, ORCID
iD, Given Name, Family Name, Affiliation, "View more details" toggle) and a
roles table `ROLE | START DATE | END DATE | JOURNAL MASTHEAD` with, per row, a
masthead `<select>` ("Appear on the masthead" / "Does not appear on the
masthead"; both seeded roles started at "Does not appear") and a "Remove Role"
button; below: "Add Another Role"; footer: `Cancel | Save And Continue`.

**Remove Role applies immediately (claim confirmed).** "Remove Role" on the
Section editor row → dialog:
> Remove Role — Are you sure you want to remove this role? The user will lose
> access and permissions associated with it. [Remove Role] [Cancel]

Confirming fired `POST /api/v1/users/25/endRole/77` → 200, no invitation, no
send step. In the wizard the row stayed listed but flipped to end-dated
(END DATE 2026-07-31) with the static label "User Removed From Role" replacing
the button. Fresh load of Users & Roles: the u6rduo row now shows only
`Author` — removal is live before anything is "sent". The member was emailed
immediately: subject "You have been removed from a role", body "Removed from a
Role / Dear u6rduo, Thank you very much for your participation in the role of
Section editor at Scratch context u6rojs. This is a notice to let you know
that you have been removed from th…". Public masthead screen
`/about/editorialMasthead` was empty before and after (see masthead note below).

**Masthead change applies immediately (claim confirmed, with a context limit).**
Changing a row's masthead select opens a dialog:
> Confirm masthead visibility change — This will update whether this user
> appears on the journal masthead for the selected role. The user will be
> notified of this change. [Confirm] [Cancel]

Confirm → `POST /api/v1/users/25/masthead/32` → 200. The new value persists on
a full reload of the wizard, "Save And Continue" stays disabled throughout
(the change is NOT part of any invitation), and the member is emailed at once:
subject "Your journal masthead visibility has been updated", body "…Your
journal masthead visibility for the role Author (since July 31, 2026) in
Scratch context u6rojs has been updated. New setting: Appear on the masthead".
- Claim-vs-context: `/about/editorialMasthead` showed no change — but the only
  editable role left on the user was Author, and the Author group is not a
  masthead group (`registry/userGroups.xml` has no `masthead="true"` on
  author), so the public page cannot display that per-user flag regardless of
  timing. The immediacy evidence is the API 200 + persisted value + instant
  email. On OMP/OPS the same control 500s — see the spot-checks.

**Removing the LAST role is blocked (exact wording).** With only Author left,
"Remove Role" → dialog:
> Remove Role — You cannot remove the role. At least one role must be assigned
> to the user. [Close]

Only "Close" is offered; the role stays; no network mutation observed.

**Send-button gating.** Observed sequence on step 1 (edit mode):
- At entry, with no changes: "Save And Continue" disabled.
- After a masthead-only change: still disabled (that change applied on its own).
- After clicking "Add Another Role": ENABLED immediately — before any role is
  picked. Validation is deferred to the continue click: with a role selected
  but Start Date empty, continue fired `POST /api/v1/invitations/add/userRoleAssignment`
  → 200 then `POST /api/v1/invitations/19/populate` → **422**, and the wizard
  stayed on step 1 showing inline "Start Date * Required — This field is
  required." So the button is inactive until a role row exists, but an empty
  role row is enough to activate it.
- Step 2's send button ("Invite user to the role") was enabled on arrival.

**Completed add-role flow and the resulting email.** Added role Copyeditor,
Start Date 2026-07-31, masthead "Does not appear" → "Save And Continue" →
STEP 2 — "Modify email shared with the user" ("Send the user an email to let
them know about the invitation, next steps, journal GDPR polices and ORCID
verification"): Email Templates "Find Template" picker preloaded with "User
Invited to Role Notification / Invitation to New Role", To fixed to
`u6rduo@mail.test`, "Add CC/BCC", Subject, Message, "Insert Content"; buttons
`Cancel | Back | Invite user to the role`. Send fired
`invitations/add` → `invitations/20/populate` → `invitations/20/invite`, all
200, then an on-page confirmation:
> Invitation Sent — u6rduo@mail.test has been invited to new role in OJS. You
> can be updated about the user's decision on the Users & Roles page, your OJS
> notifications and/or your email — [View All Users]

Mailpit (to `u6rduo@mail.test`): From `u6rmgr <u6rmgr@mail.test>`, subject
"You are invited to new roles". Body: "Invitation to New Role / Dear u6rduo,
In light of your expertise, you have been invited by u6rmgr to take on new
roles at Scratch context u6rojs", a GDPR/privacy-policy paragraph, then
"Already assigned roles — 1. Author … Your name will appear in the Scratch
context u6rojs's masthead as a Author." and "Newly assigned roles —
1. Copyeditor … Your name will not appear in Scratch context u6rojs's masthead
as a Copyeditor.", "On accepting the invite, you will be redirected to Scratch
context u6rojs.", Accept Invitation / Decline Invitation buttons →
`/index.php/u6rojs/invitation/accept?id=20&key=…` and `…/decline?id=20&key=…`.
Copy nit (exact): "as a Author".

After send, Users & Roles: Invitations (1) row
`u6rduo | u6rduo@mail.test | Copyeditor | Invited 2026-07-31`; Current Users
row for u6rduo unchanged (`Author`) — the added role is pending acceptance.

**Other observations (OJS edit flow).**
- Every pass through "Save And Continue" and the final send each POSTed
  `invitations/add/userRoleAssignment` afresh: one edit session minted
  invitation ids 16, 19, 20; only 20 was invited. The stale drafts do not
  appear in the Invitations table (it showed exactly 1 row).
- Transient, seen once, not reproduced: the first fresh load of
  Users & Roles immediately after sending rendered "Current Users (0) / No
  Items / Showing 0 to 0 of 0" (screenshot on file) while Invitations (1) was
  correct; the next fresh load showed Current Users (4) (admin, u6rmgr,
  u6rduo, u6rdis).

### OMP spot-check — `http://127.0.0.1:8100/index.php/u6romp`, as `u6rmgr`

- Users list row menu identical (`Edit, Email, Login As, Remove User, Disable
  User, Merge user`); Edit → `/management/settings/user/25`; same two-step
  wizard, NO search step; column header `PRESS MASTHEAD`, field label "Press
  Masthead"; intro "You can invite them to take up a role in OMP".
- Masthead change: same confirm dialog, but its wording says "journal
  masthead" on OMP (app-agnostic copy leak; the surrounding screen says
  Press Masthead). Confirm → `POST /api/v1/users/25/masthead/31` → **500**
  with body `{"error":"Email template USER_ROLE_MASTHEAD_UPDATE not found. The
  migration script I11800_AddUserRoleMastheadUpdateEmail needs to be run."}`,
  surfaced verbatim to the manager in an on-screen Error dialog. Despite the
  500 the setting PERSISTED (select reads "Appear on the masthead" after a
  full reload) — the flag is applied, only the notification path fails; no
  rollback. `/about/editorialMasthead` listed no members before or after
  (recorded as-is; not probed further).

### OPS spot-check — `http://127.0.0.1:8200/index.php/u6rops`, as `u6rmgr`

- Same row menu; Edit → `/management/settings/user/14`; same two-step wizard,
  NO search step; column `SERVER MASTHEAD`; intro "…a role in OPS".
- Masthead change: identical behavior to OMP — same dialog (again "journal
  masthead" wording), `POST /api/v1/users/14/masthead/20` → **500**, same
  missing-template error shown on screen, and the flag persisted to "Appear on
  the masthead" after reload.

---

## Item 11 — Expiry (OJS only, run last)

**Config key.** `config.TEMPLATE.inc.php:724-727`:
`[invitations] expiration_days = 3` — "The number of days a user has to accept
an invitation before it expires." (Code side, for context:
`PKP\invitation\core\Invitation::DEFAULT_EXPIRY_DAYS = 3`; the expiry stamp is
set at invite time as now + N days and enforced at lookup by an
`expiry_date >= now` scope, with a dedicated friendly page when the key
matches an expired/used invitation.)

**Harness route.** `scenarios.md` offers no way to seed or age an invitation,
so the config path was used. The OJS `config.test.inc.php` had no
`[invitations]` section; a backup was taken (md5
`9366a16324d8f569559dc837525e2921`), then `expiration_days = 0` was appended.
Days-granularity did not block the probe: 0 is accepted, making the expiry
stamp equal to the send time, so the invitation lapses seconds after sending —
no forcing outside config+screens.

**Flow.** As `u6rmgr`, new-invite wizard
(`/index.php/u6rojs/invitation/create/userRoleAssignment`): Step 1 searched
`u6p11-ojs@mail.test` (no such user) → Step 2 headed "The user does not have a
role in this journal"; Email is the only required identity field (Given/Family
Name optional, with helper text "If you know the given name of the user, you
can enter the information. However, this information can be changed by the
user."); added role Reader, Start Date 2026-07-31 → Step 3 composer → "Invite
user to the role" → "Invitation Sent" confirmation. Email arrived (subject
"You are invited to new roles") with accept link
`/index.php/u6rojs/invitation/accept?id=32&key=jmTDR8`.

**Lapsed link (opened ~2 minutes after send, anonymous browser context).**
HTTP 200, page text exactly:
> Invitation Unavailable
> This invitation is no longer available. It may have already been accepted,
> declined, or expired. Please contact the journal manager for further
> assistance.

with Login and Register links in the page chrome.

**Invitations table.** Fresh load of Users & Roles as `u6rmgr`: the
`u6p11-ojs@mail.test` row does NOT show. The table listed only the earlier,
still-pending invitation (`u6rduo | Copyeditor | Invited 2026-07-31`, created
under the default 3-day lifetime) — the positive control proving pending rows
do display. So an expired invitation is absent from the Invitations table
(with a 0-day lifetime it was never observed there at all; "row disappears on
expiry" vs "never listed" cannot be split at 0 days — a >0-day aged row was
not reachable through config+screens in one session).

**Restoration.** `config.test.inc.php` was restored from the backup
immediately after the checks; post-restore md5 matches the pre-edit value
(`9366a16324d8f569559dc837525e2921`). No config drift remains.

---

## Item 12 — Disabled-user guard (OJS only)

**Disabling from the users list.** Row menu "Disable User" on `u6rdis` →
legacy modal titled "Disable u6rdis": text "Current Roles : Author", textarea
"Reason for disabling user", note (exact) "Please note that once a user is
disabled, you won't be able to add them to any roles until the user is enabled
again.", buttons `Cancel | OK`. OK →
`POST $$$call$$$/grid/settings/user/user-grid/disable-user` → 200. Afterwards
the Current Users row text is unchanged (no visible "disabled" badge in the
row); the only list-level signal is the row menu now offering "Enable User".

**Row Edit action on the disabled user** (`/management/settings/user/26`):
`GET /api/v1/users/26` → **404**; the wizard opens with an Error dialog
"Error — The requested resource was not found. [OK]", the roles table renders
"No Items", and NO disabled-user banner appears. Button states: "Add Another
Role" ENABLED (not exercised further), "Save And Continue" disabled.
Claim-vs-observed: the UI has a designed disabled-user banner for exactly this
state — it renders on the search path below — but the Edit entry point
surfaces a raw not-found error instead.

**Invite wizard search step** ("Invite to a role" →
`/invitation/create/userRoleAssignment`, searching `u6rdis@mail.test`): the
wizard advances to STEP 2 headed "The user already exists in the journal" with
the warning banner, exactly:
> The user is currently disabled.
> The user was disabled. You cannot assign them a role while they are
> disabled. Please enable the user first to invite them to a role.

Button states: "Add Another Role" DISABLED, "Save And Continue" DISABLED — the
send step is unreachable, so the send button state is "never reachable"
(blocked at step 2). Note: the existing Author row's "Remove Role" button
remains enabled on this screen (not exercised).

**Control.** Same search flow for the enabled `u6rduo`: found → step 2, no
banner, "Add Another Role" and "Save And Continue" enabled.

**Search-step copy nits (exact, STEP 1):** "Enter at least one details to get
started.", "If the user already exist in the system, you can view user
information and invite to take a additional roles."

---

## Cross-item screen facts (for the digest)

- Missing locale keys visible on every backend screen probed, all three apps:
  `##common.help##` (top bar); OJS/OMP/OPS users list: `##userAccess.management.options##`
  (row-menu aria-label).
- The masthead confirm dialog says "journal masthead" on OMP and OPS.
- OMP + OPS: masthead visibility change 500s (missing USER_ROLE_MASTHEAD_UPDATE
  email template; error names a migration, `I11800_AddUserRoleMastheadUpdateEmail`,
  verbatim in the manager-facing dialog) while still persisting the flag.
  OJS applies it cleanly (200 + email).
- Edit-mode wizard mints a fresh invitation draft on every Save And Continue /
  send pass (ids 16/19/20 observed for one session); only the final one is
  invited and listed.
