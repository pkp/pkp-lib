---
name: user-invitations
scope: A manager invites someone into journal roles; the recipient accepts (creating or linking an account) or declines
apps: [ojs, omp, ops]
shared: pkp-lib
status: verified
atlas-claims: [AFFM-099, AFFM-100, AFFM-101, AFFM-118, AFFM-119, AFFM-120, AFFU-122, AFFU-123, AFFU-124, AFFU-125, AFFU-126, AFFU-127, AFFU-128, AFFU-129, AFFU-130, AFFU-131, AFFU-132, AFFU-133, AFFU-134, AFFU-135, AFFU-136, AFFU-137, AFFU-206, ROUTE-013, ROUTE-014, VUE-001, VUE-011, VUE-052, API-024, MAIL-055, SET-064, JOB-013, JOB-051]
---

# User invitations

> Conventions (markers, badges, footnotes): [Reading a spec](GLOSSARY.md#reading-a-spec).

## Purpose

Journal teams grow by invitation: a manager picks a person — someone already
registered or a complete outsider — names the roles they should hold (with start
date, optional end date, and whether they appear on the journal's public
masthead), and sends them an email invitation. The recipient follows the emailed
link to accept — creating an account on the spot if they are new, or linking the
roles to their existing account — or to decline. Until the recipient answers,
the manager can watch, edit, or cancel the pending invitation from the same
screen where users are managed. Invitations expire on their own after a few
days, so a stale link never grants a role.

## Actors & permissions

"The recipient" below means whoever holds the emailed invitation link; every
send-side capability lives on the **Users & Roles** screen (Users tab), whose
own reachability rules belong to the user-management feature (see
*Cross-feature interactions*).

| Action | Who may — and when |
|--------|--------------------|
| **See pending invitations** | • Site Administrator; Journal Manager — the "Invitations" table on Users & Roles <sup>a</sup> |
| **Invite to a role** (open the send wizard) | • Site Administrator; Journal Manager — the "Invite to a role" button <sup>a</sup><br>• ⚠ [A1](#a1) no other role is offered the button; an Author or Reviewer who types the wizard's own address (copy the URL a manager reaches via "Invite to a role") is turned away, and the address's full gate is an open question <sup>b</sup> |
| **Edit a pending invitation** | • Site Administrator; Journal Manager — "Edit Invitation" on the invitation's row (Rule 12) <sup>a</sup> |
| **Cancel a pending invitation** | • Site Administrator; Journal Manager — "Cancel Invite" on the invitation's row <sup>a</sup> |
| **Propose roles for an existing member** | • Site Administrator; Journal Manager — the user row's Edit action opens the same wizard (Rule 13) <sup>c</sup> |
| **Accept or decline** | • The recipient — via the emailed links, while the invitation is pending; worked signed out, no credentials asked (Rules 6–7) <sup>f</sup> |
| **Customize the invitation email for one send** | • Whoever is sending — the wizard's compose step (subject, body, template choice) <sup>g</sup> |
| **Edit the stored invitation email template** | • Journal Manager — on the Emails settings screen (owned by the emails-management feature); ⚠ [OPS1](#ops1) on a preprint server the template has no row there <sup>j</sup> |

## Fields & validation

On-screen labels follow the app: on a press the "Journal Masthead" column
reads "Press Masthead", on a preprint server "Server Masthead", and quoted
button labels containing "OJS" likewise substitute the app's own acronym — a
press shows "Create OMP account" and "Accept And Continue to OMP", a preprint
server "Accept And Continue to OPS".

Send wizard (fields for a **new** invitee; for an existing user the personal
fields show read-only):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Search for a user by email address, username, or ORCID iD" | yes (to pass step 1) | Exact match on email, then username, then ORCID iD. A miss — even text that is no valid email address — advances to "Enter details" with "The user does not have a role in this journal"; the typed text is discarded (the Email field arrives empty) and the address is validated there instead <sup>h</sup> |
| Email / Given Name / Family Name / Affiliation | email only | Names are optional — helper text notes the invitee can change them; a name is entered in the journal's primary language. If the address gains an account before the invitation is accepted, what the recipient then sees was not verified live <sup>o</sup> |
| Role (per row, "Select a new role") | at least one row | Roles the person already holds — or already chosen in another row — are not offered; from the second row on, a row's fields lose their screen-reader names ⚠ [A8](#a8) <sup>i</sup> |
| Start Date (per role row) | yes | A date in the past takes effect as "today" at acceptance (Rule 8) ⚠ [A8](#a8) |
| End Date (per role row) | no | Cannot be entered when inviting — an added role row's END DATE cell shows "---" and holds no input; the column only displays dates on an existing member's current roles (Rule 13) <sup>i</sup> |
| Journal Masthead (per role row) | yes | The select starts blank ("Appear on the masthead" / "Does not appear on the masthead"); leaving it empty blocks the step with "This field is required." Choosing Reviewer replaces the select with the fixed text "Appear on the masthead" — no choice to make ⚠ [A8](#a8) <sup>i</sup> |
| Email subject & body (compose step) | prefilled | Freely editable for this send; a different stored template can be selected <sup>g</sup> |

Accept wizard (new invitee):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| Username | yes | Must not be taken <sup>o</sup> |
| Password | yes | At least six characters — the minimum the field states on screen <sup>o</sup> |
| Privacy consent ("Yes, I agree to have my data collected…") | yes | Unchecked blocks the step with "Please confirm that you have read and agree privacy statement"; the label links to the journal's Privacy Statement <sup>o</sup> |
| Given Name / Family Name / Country / Affiliation | given name, country | Collected on the "Enter details" step; editable again from the review step via its Edit button <sup>k</sup> |

## Rules & state

<a id="invitation-states"></a>
1. An invitation is either **being composed** (started in the wizard, not yet
   sent), **pending** (sent, awaiting the recipient's answer), or settled as
   **accepted**, **declined**, or **cancelled**. There is no separate "expired"
   state: a pending invitation past its deadline simply stops working (Rule 4)
   and is later purged (see *Side effects*). <sup>d</sup>
2. Sending sets the acceptance deadline: the recipient has a fixed number of
   days (3 unless the site is configured otherwise — see *Settings*) to answer. <sup>e</sup>
3. One live invitation per person per journal: sending a new role invitation
   to the same person (or email address) silently replaces any earlier pending
   one. The second send's wizard gives no hint that a pending invitation
   exists, and the Invitations table afterwards holds only the newer row; the
   older email's links stop working ⚠ [A3](#a3). <sup>d</sup>
4. <a id="invitation-landing"></a> **The invitation-link landing.** Every
   invitation email carries personal accept/decline links. A link whose
   invitation is still pending opens its flow (for role invitations, Rules 5–7;
   other invitation kinds land in their own features' flows — see
   *Cross-feature interactions*). A correct link whose invitation was already
   answered (accepted or declined), cancelled, or expired shows the "Invitation Unavailable" page — "This
   invitation is no longer available. It may have already been accepted,
   declined, or expired…" — with "Login" and "Register" buttons. A tampered or
   truncated link shows a not-found error. ⚠ [A3](#a3) links of a replaced
   invitation — edited, or superseded by a new send to the same person — also
   show the bare not-found error. <sup>f</sup>
5. The accept wizard shapes itself to the recipient. A **new** invitee walks up
   to four steps: "Verify ORCID iD" → "Create OJS account" → "Enter details" →
   "Review & create account". An **existing** user gets at most "Verify ORCID
   iD" plus the review step. The ORCID step appears only when ORCID is enabled
   for the journal and the recipient has no verified ORCID iD. The review step
   is always there: opening the link alone never accepts the roles — the
   recipient always presses the accept button. <sup>k</sup>
6. The recipient works signed out. The emailed link is the only credential the
   accept wizard asks for — an existing user gets no password prompt and no
   account fields — but the link never signs anyone in either (Rule 9). If
   somebody else is already signed in on that browser, the page refuses:
   "Invitation not accepted. You're logged in as a different user." with a
   "Logout" action that genuinely ends that session. <sup>l</sup>
7. On the ORCID step the recipient either verifies their iD through the ORCID
   sign-in window ("Verify ORCID iD") or passes with "Skip ORCID verification";
   this step has no other Continue button. <sup>k</sup>
8. Accepting grants every listed role from its start date — past start dates
   take effect as the acceptance day — with the chosen masthead visibility, and
   marks the invitation accepted. The final button reads "Accept And Continue
   to OJS"; a closing dialog announces the new role, and its "View All
   Submissions" button leads out of the wizard (Rule 9). <sup>m</sup>
9. A new invitee's account exists only from the moment they accept; they choose
   username and password mid-wizard. ⚠ [A4](#a4) accepting signs nobody in —
   new invitees and existing users alike leave the closing dialog for the
   sign-in screen and must sign in themselves. <sup>m</sup>
10. Declining is deliberate: the emailed decline link opens a "Decline
    Invitation" confirmation page, and only pressing "Confirm Decline
    Invitation" declines — no roles are granted, and the browser moves to the
    sign-in page. Afterwards both emailed links show the "Invitation
    Unavailable" page (Rule 4). <sup>n</sup>
11. The "Invitations" table lists only invitations still awaiting an answer,
    each row reading "Invited {date}" — answered, cancelled, replaced, and
    expired invitations leave the list. <sup>p</sup>
12. **Editing = replacing.** "Edit Invitation" warns "If you edit the existing
    invitation or add a new role, the current invitation will be canceled and,
    a new one will be sent." — the wizard reopens prefilled, and sending
    composes a fresh invitation whose email supersedes the old one; the old
    links stop working ⚠ [A3](#a3). <sup>q</sup>
13. The user row's Edit action opens the same wizard for an existing member,
    with no search step. Inside its roles table, **removing** a current role or
    changing its masthead visibility takes effect immediately (each behind its
    own confirmation), and the member is emailed either way — see *Side
    effects* ⚠ [OMP1](#omp1); **adding** a role is only a proposal — it takes
    effect when the member accepts the resulting invitation. A member's last
    active role cannot be removed. In this wizard the send path stays inactive
    while no new role row exists; an empty row is enough to activate it, and
    missing role fields are rejected on the next step with inline errors. <sup>c</sup> <sup>i</sup>
14. A disabled user cannot be invited: reaching one through the search step
    shows "The user is currently disabled." with instructions to enable them
    first, and no role row can be added. ⚠ [A6](#a6) reaching the same person
    through the users list's Edit action shows an error message over an empty
    wizard instead of that warning. <sup>i</sup>
15. Wizard navigation (send side): "Back" returns one step, and returning to
    the search step clears everything entered; "Cancel" asks for confirmation
    only when something was changed; the final button reads "Invite user to
    the role", and a success dialog ("Invitation Sent") confirms. ⚠ [A5](#a5)
    that dialog promises updates about the recipient's decision. Its only
    button, "View All Users", returns the browser to Users & Roles. <sup>g</sup>
16. Cancelling a pending invitation (from its row, behind a confirmation
    listing the invitee's details) deactivates the emailed links immediately —
    the recipient thereafter sees the "Invitation Unavailable" page (Rule 4). <sup>q</sup>

## Side effects

- **On send** — one invitation email to the recipient, from the inviter,
  listing the roles offered (with dates and masthead visibility), the roles
  they already hold, and the accept/decline links. Subject and body are
  whatever the compose step showed at send time. ⚠ [A7](#a7) the email's fixed
  copy carries small wording slips — it greets a new invitee by their email
  address even when a name was entered. <sup>j</sup>
- **On acceptance** — the account is created (new invitee) or the roles are
  added to the existing account; masthead listings update per the chosen
  visibility.
- **On role removal or masthead change** (the user-row wizard, Rule 13) — the
  member is emailed at once ("You have been removed from a role" / "Your
  journal masthead visibility has been updated"), and the confirmation dialog
  says so up front. ⚠ [OMP1](#omp1) on a press or preprint server the masthead
  email fails with a raw error shown to the manager, though the visibility
  change itself sticks. <sup>r</sup>
- **No notice to the inviter** — nobody is emailed or notified when the
  recipient accepts or declines ⚠ [A5](#a5); the pending row simply
  disappears, so from the manager's screens an acceptance and a decline are
  told apart only by the new name an acceptance adds under Current Users. <sup>m</sup>
- **Daily cleanup** — a scheduled task permanently removes expired invitations
  once a day; ⚠ [A2](#a2) it also removes invitations still being composed,
  which never got a deadline. <sup>e</sup>

## Settings that modify behavior

- **Invitation lifetime** — how many days the recipient has to answer
  (default 3) is set in the installation's configuration file; there is no
  screen for it, so changing it is the system administrator's job. The value
  in force at the moment of sending applies. <sup>e</sup>
- **ORCID** — the "Verify ORCID iD" step exists only when ORCID is enabled for
  the journal (Rule 5). <sup>k</sup>
- **Site minimum password length** — the accept wizard's password field
  enforces and states it (six characters on a default install). <sup>o</sup>
- **Privacy Statement** — the consent checkbox links to the journal's Privacy
  Statement page. <sup>o</sup>
- **Invitation email template** — the stored template used to prefill the
  compose step is editable on the Emails settings screen; ⚠ [OPS1](#ops1) on a
  preprint server it has no row there, though sending still works. <sup>j</sup>

## Cross-feature interactions

- **Users & Roles screen** — the Invitations table and "Invite to a role"
  button live on the Users tab, above the users list; who reaches that screen,
  and everything else about managing existing users, belongs to the
  user-management feature (spec to come — *User management*).
- **Invitation-link landing** — Rule 4 is the shared front door for every
  emailed invitation link. The reviewer one-click review link (see the future
  *Review assignments* spec) and the registration email-validation and
  profile-email-change confirmations all land through it; each flow's own
  behavior belongs to its feature. <sup>f</sup>
- **Emails management** — the stored invitation email template is edited on
  the Emails settings screen (future *Emails management* spec); this spec owns
  only the invitation-specific defect ⚠ [OPS1](#ops1).
- **Roles settings** — which roles exist to be offered, their levels, and the
  masthead concept are the roles-configuration feature's; this wizard only
  consumes them.

## Canonical scenarios

Common to all three apps; substitute roles and vocabulary per the
[application glossary](GLOSSARY.md). Actors are named by role; seeded
accounts and recipes live in the footnotes. <sup>s</sup>

1. **Invite a newcomer to a role** — Journal Manager: on Users & Roles, press
   "Invite to a role"; on "Search User", enter an email address no account
   uses and continue — the wizard reports the user is not known to the journal
   and moves to "Enter details". Fill given name, add a role row — pick a role,
   set today's start date, and choose "Appear on the masthead" in the masthead
   select, which starts blank (choosing Reviewer instead shows that text fixed,
   with nothing to select) — continue to the compose
   step, adjust the subject, and press "Invite user to the role". An
   "Invitation Sent" dialog appears; press "View All Users" — the browser
   returns to Users & Roles, where the Invitations table shows the row as
   "Invited {date}", and the recipient's mailbox holds the invitation listing
   the offered role and two links. <sup>s</sup>
2. **Newcomer accepts and gets an account** — signed-out visitor: open the
   emailed accept link. Walk the wizard: skip ORCID verification (if the
   ORCID step is shown), choose a
   username and password and tick the privacy consent on "Create OJS account",
   fill name and country on "Enter details", check the summary on "Review &
   create account" (its Edit button reopens the details), and press "Accept
   And Continue to OJS". A dialog announces the new role; ⚠ [A4](#a4) its
   "View All Submissions" button lands on the sign-in screen — signing in with
   the new credentials succeeds, the account holds the offered role, and the
   invitation's row is gone from the manager's Invitations table.
3. **Existing user accepts an additional role** — Journal Manager: invite an
   existing registered user who does not yet hold the offered role, found by
   exact email on "Search User" (the wizard confirms the
   user exists and shows their details read-only); send with one new role.
   Recipient (signed out): open the accept link — the review step opens
   directly (at most an ORCID step precedes it), with no password prompt and
   no account fields; accept. ⚠ [A4](#a4) the browser lands on the sign-in
   screen still signed out; signing in the usual way shows the role on the
   account, and the manager sees the name under Current Users.
4. **Recipient declines** — recipient of a pending invitation: open the
   emailed decline link; a "Decline Invitation" page asks for confirmation;
   press "Confirm Decline Invitation". The browser lands on the sign-in page,
   no role was granted, the invitation's row is gone from the Invitations
   table, and both emailed links now show "Invitation Unavailable".
5. **Manager cancels a pending invitation** — Journal Manager: on the
   invitation's row menu, choose "Cancel Invite"; the confirmation dialog
   recaps the invitee (email, role, status, affiliation); confirm. The row
   disappears; the recipient's accept link now shows "Invitation Unavailable"
   with Login and Register buttons.
6. **Manager edits a pending invitation** — Journal Manager: on the
   invitation's row menu, choose Edit; a dialog warns the current invitation
   will be canceled and a new one sent; proceed. The wizard opens prefilled
   without the search step; change the role set and send. The recipient's
   mailbox holds a second invitation email whose links work; the first
   email's links no longer do ⚠ [A3](#a3).
7. **Wrong person signed in** — a signed-in user who is not the invitee opens
   an accept link addressed to an existing user: the page refuses with
   "Invitation not accepted. You're logged in as a different user." and offers
   to log out; after logging out and reopening the link, the real flow starts.
8. **Propose a role via the user row** — Journal Manager: open Edit on an
   existing member's row in the users list. The wizard opens on their details
   with no search step; the roles table shows current roles with Remove Role
   and masthead controls that act immediately and email the member (⚠
   [OMP1](#omp1) on a press or preprint server the masthead confirmation shows
   an error, though the change sticks), and the send button stays inactive
   until a new role row is added. Add one and send: the member
   receives an invitation email and the role appears on their account only
   after they accept it (scenario 3's flow).

App-specific:

9. **{OPS} The invitation template is missing from the Emails screen** —
   Preprint Server Manager: open the Emails settings screen and search for
   "User Invited to Role Notification". The list answers "No items found."
   ⚠ [OPS1](#ops1) — while scenario 1 run on the same server still delivers
   the invitation email (positive control). On a journal or press the same
   search finds the template with an Edit button.

## Findings register

Verdicts are the author's judgment (claude, 2026-07-31), unreviewed unless an
entry notes otherwise; the team settles them on spec review. Sorted 🐞 → ❓ → ✅
in the summary; the entries below are the source. Each entry opens with the
user-observable symptom; mechanism and evidence live in the entry's footnote.
An entry whose ID starts with OMP or OPS concerns only the app(s) it names.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|-----------------------------|------|--------|--------|
| [A3](#a3) | Links of a replaced invitation (edited or re-sent) die with a bare not-found error | 🐞 | minor | — |
| [A4](#a4) | Nobody is signed in after accepting — every recipient lands on the sign-in screen | 🐞 | user-visible | — |
| [A5](#a5) | "Invitation Sent" promises decision updates that are never delivered | 🐞 | user-visible | — |
| [A6](#a6) | Edit on a disabled member's row opens a broken wizard instead of the disabled-user warning | 🐞 | user-visible | — |
| [A7](#a7) | Small wording and untranslated-text defects across the invitation screens and emails | 🐞 | minor | — |
| [A8](#a8) | Added role rows carry no accessible field names — a screen reader hears row 1's labels | 🐞 | user-visible | — |
| [OMP1](#omp1) | Confirming a masthead change shows a raw email-template error on presses and preprint servers | 🐞 | user-visible | — |
| [OPS1](#ops1) | The invitation email template has no row on the preprint server's Emails screen | 🐞 | user-visible | — |
| [A1](#a1) | The send wizard's address is gated more widely than the screen that offers it | ❓ | latent | — |
| [A2](#a2) | Daily cleanup deletes invitations still being composed | ❓ | latent | — |

### All apps

<a id="a1"></a>
**A1 — Wizard address wider than its screen** · ❓ · latent.
The only screen offering "Invite to a role" is Users & Roles (Managers and the
Site Administrator), but in code the wizard's own address is gated more
widely. An Author or Reviewer who types the address is turned away; the live
outcome for the remaining roles was checked on 2026-07-31 and is recorded in
the maintainer's private security file, not here.
Question: should anyone beyond Managers and the Site Administrator reach the
send wizard at all? Lean: no — the screen's narrower gate looks like the
product decision.
Basis: code + probe. <sup>[f-a1](#fn-a1)</sup>

<a id="a2"></a>
**A2 — Cleanup eats unsent drafts** · ❓ · latent.
The daily cleanup that purges expired invitations also deletes every
invitation still being composed, because drafts never carry a deadline. A
manager who happens to be mid-wizard when the daily run fires loses the draft
and the wizard's next step fails. Such drafts pile up routinely: one pass
through the edit wizard left three behind, invisible anywhere in the UI.
Question: is same-day draft deletion intended housekeeping?
Lean: the reaping is intended, the mid-wizard window is an accepted-loss edge.
Basis: judgment (code); drafts observed live, the cleanup run itself not. <sup>[f-a2](#fn-a2)</sup>

<a id="a3"></a>
**A3 — Replaced invitation's links die ungracefully** · 🐞 · minor.
A cancelled, declined, or expired invitation's old links show the friendly
"Invitation Unavailable" page, but a replaced invitation — whether replaced
through Edit or superseded by a plain new send to the same person — is erased
outright, so its old email links render a bare "404 Not Found" with no
journal styling — the one stale-link case that skips the explanation. Same
user situation as cancellation; it should get the same page.
Basis: probe + claim check. <sup>[f-a3](#fn-a3)</sup>

<a id="a4"></a>
**A4 — Nobody is signed in after accepting** · 🐞 · user-visible.
A newcomer who has just chosen a username and password and pressed "Accept And
Continue to OJS" is not inside: the closing dialog's "View All Submissions"
button lands on the sign-in screen and they must type the credentials again.
An existing user — whose link opened the wizard with no password prompt —
ends on the same sign-in screen. The roles themselves are granted correctly.
Basis: probe, all three apps. <sup>[f-a4](#fn-a4)</sup>

<a id="a5"></a>
**A5 — Promised decision updates never arrive** · 🐞 · user-visible.
The "Invitation Sent" dialog tells the inviter they "can be updated about the
user's decision on the Users & Roles page, your OJS notifications and/or your
email" — but after both accept and decline every promised channel stays
silent: no notification, no email, and the pending row is removed outright.
An acceptance and a decline are indistinguishable from Users & Roles except
for the new name an acceptance adds under Current Users.
Basis: probe, with a mail-delivery positive control. <sup>[f-a5](#fn-a5)</sup>

<a id="a6"></a>
**A6 — Edit on a disabled member opens a broken wizard** · 🐞 · user-visible.
Pressing Edit on a disabled user's row pops "Error — The requested resource
was not found." over a wizard whose roles table is empty; the disabled-user
warning that the search path shows (Rule 14) never appears here. Observed on
a journal; the screen is shared.
Basis: probe. <sup>[f-a6](#fn-a6)</sup>

<a id="a7"></a>
**A7 — Small copy defects across these screens and emails** · 🐞 · minor.
The invitation email greets a new invitee by their email address even when a
name was entered, and offers roles "as a Author"; the search step reads
"Enter at least one details…" and "…invite to take a additional roles"; the
masthead confirmation says "journal masthead" on presses and preprint servers
too; the "Invitation Unavailable" page closes with "Please contact the
journal manager for further assistance." on presses and preprint servers as
well; and raw untranslated tokens ("##common.help##",
"##userAccess.management.options##") show on the management screens of all
three apps.
Basis: probe + claim check. <sup>[f-a7](#fn-a7)</sup>

<a id="a8"></a>
**A8 — Added role rows are invisible to a screen reader** · 🐞 · user-visible.
In the send wizard's roles table, every added row repeats the first row's
control identifiers, so each field label points back at row 1: a
screen-reader user editing the second or later row hears no name at all for
its role, start date, or masthead field and cannot tell which row they are
changing. Sighted use is unaffected.
Basis: probe (accessibility-tree check). <sup>[f-a8](#fn-a8)</sup>

### OMP and OPS

<a id="omp1"></a>
**OMP1 — Masthead change throws a raw email-template error** · 🐞 · user-visible.
On a press or preprint server, confirming a masthead visibility change answers
the manager with a raw error — "Email template USER_ROLE_MASTHEAD_UPDATE not
found. The migration script I11800_AddUserRoleMastheadUpdateEmail needs to be
run." — though the change itself sticks after a reload. The same error can
interrupt an existing-user invitation mid-send on a press (dismissable; the
invitation still delivers). On a journal the change applies cleanly and the
member's notice is delivered. Fresh presses and preprint servers ship without
the email template this notice needs, so any new install reproduces it.
Basis: probe + install-seed check. <sup>[f-omp1](#fn-omp1)</sup>

### OPS

<a id="ops1"></a>
**OPS1 — Invitation email template hidden on OPS** · 🐞 · user-visible.
On a preprint server the Emails settings screen lists no row for "User
Invited to Role Notification" — search and the full list both answer "No
items found." — so a manager cannot review or customize the stored template.
Invitations still send and deliver using it; on journals and presses the row
is present with an Edit button. Reads as collateral of the preprint server
keeping its own list of emails, not an intended trim — the template ships
seeded and in active use.
Basis: probe + code. <sup>[f-ops1](#fn-ops1)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<a id="fn-a"></a>
**a** — Mount: `templates/management/access.tpl` places
`<user-invitation-manager>` (UI library `UserInvitationManager.vue`, atom
VUE-052) above the users grid inside `ManagementHandler::access()` (ROUTE-017's
`access` op, owned by the journal-identity/settings dispatcher row); screen
access is gated by `CanAccessSettingsPolicy` (site admin, or manager-level
groups with `permitSettings`). Row actions and dialogs:
`UserInvitationManagerStore.js` (`editInvite`, cancel →
`PUT invitations/{id}/cancel`). Button: locale key `invitation.inviteToRole.btn`
→ page `invitation/create/userRoleAssignment`. Gate live-probed 2026-07-31 on
all three apps: only managers and the
Site Administrator reach the screen; button label "Invite to a role" verbatim
everywhere.

<a id="fn-b"></a>
**b** — `PKP\pages\invitation\InitializeInvitationUIHandler` (ROUTE-013)
assigns ops `create`/`edit` to `ROLE_ID_SITE_ADMIN`, `ROLE_ID_MANAGER`,
`ROLE_ID_SUB_EDITOR`, `ROLE_ID_ASSISTANT` (+ `ContextAccessPolicy`); the API
(`PKP\API\v1\invitations\InvitationController`, API-024) uses the same
four-role authorizer for add/populate/invite/cancel. The offering screen is
gated narrower (note a) — the mismatch is finding A1.

<a id="fn-c"></a>
**c** — Users-grid Edit → `ManagementHandler::editUser()` (ROUTE-017 rider) →
wizard in `editUser` mode: `UserRoleAssignmentInviteUIController::createHandle()`
with a user id — search step omitted (`SendInvitationStep::getSteps()` skips it
when a user or invitation is given), submit disabled until changes
(`isSubmitting` init in `UserInvitationPageStore.js`). Immediate actions from
`UserInvitationUserGroupsTable.vue`: `PUT users/{id}/endRole/{roleId}`
(“Remove Role”, blocked for the last active role —
`user.removeRole.roleRemainMessage`), `PUT users/{id}/masthead/{userUserGroupId}`.
Live-confirmed 2026-07-31 (edit-user probe): Edit on a member's row opened
the wizard with no search step — a two-step rail against the create flow's
three — showing read-only identity fields and the roles table with its
immediate Remove Role and masthead controls.

<a id="fn-d"></a>
**d** — Statuses: `PKP\invitation\core\enums\InvitationStatus`
(`INITIALIZED`, `PENDING`, `ACCEPTED`, `DECLINED`, `CANCELLED` — no EXPIRED
value; expiry is a timestamp check at read time). Single-live-invitation:
`Invitation::initialize()` deletes competing `INITIALIZED` rows of the same
type/target/journal; `Invitation::invite()` deletes competing `PENDING` rows
(`byNotId`). Replacement live-checked 2026-07-31 (claim check — Invitations table
before/after a second send to
the same address, both emailed links checked; OJS deep, OMP spot-check).

<a id="fn-e"></a>
**e** — Deadline: `Invitation::invite()` →
`setExpiryDate(now + getExpiryDays())`;
`getExpiryDays()` = config `[invitations] expiration_days`
(SET-064; `config.TEMPLATE.inc.php` default 3, same in all three apps) with
code fallback `Invitation::DEFAULT_EXPIRY_DAYS = 3`. Cleanup:
`PKP\task\RemoveExpiredInvitations` (JOB-051, registered daily in
`PKPScheduler`, inherited by all three app schedulers) dispatches
`PKP\jobs\invitations\RemoveExpiredInvitationsJob` (JOB-013) →
`InvitationModel::expired()->delete()` — hard delete; `scopeExpired()` is
`expiry_date < now() OR expiry_date IS NULL`, and `INITIALIZED` drafts always
have a NULL expiry date (finding A2). Lifetime live-checked 2026-07-31 with
`expiration_days = 0`: a just-sent link already rendered the unavailable page
and its row never listed, while a pending control row still did.

<a id="fn-f"></a>
**f** — Generic landing: `PKP\pages\invitation\InvitationHandler`
(ROUTE-014, AFFU-206), ops `accept`/`decline`/`confirmDecline`; link shape
`{journal}/invitation/accept?id={id}&key={key}` (built by
`InvitationHandler::getActionUrl()`; key is single-use plaintext in the email,
stored bcrypt-hashed). Lookup `Repo::invitation()->getByIdAndKey()` scopes
status PENDING + not expired, then verifies the key; a correct key on a
non-actionable row renders `invitation/invitationUnavailable.tpl`
(`invitation.unavailable.title`, buttons `user.login`,
`user.login.registerNewAccount`); anything else is a 404. Type dispatch:
`Invitation::getInvitationActionRedirectController()` — the reviewer one-click,
registration-validation and change-email invitation types route to their own
controllers through this same landing. Landing live-probed 2026-07-31: the
unavailable page renders after cancel, decline and expiry; a replaced
invitation's links 404 instead (finding A3).

<a id="fn-g"></a>
**g** — Send wizard: `pages/userInvitation/UserInvitationPage.vue` +
`UserInvitationPageStore.js` (VUE-011), steps from
`PKP\invitation\stepTypes\SendInvitationStep` — `searchUser` (skips API
update), `userDetails`, `sendMail` (Composer: editable subject/body, template
picker via `emailTemplatesApiUrl`, CC/BCC; prefill from the mailable in note
j). API sequence: first advance past details →
`POST invitations/add/userRoleAssignment` + `PUT …/populate`; final submit →
populate + `PUT …/invite`. Back to step 1 resets the payload; Cancel dialog
only when `detectChanges`. Success dialog keys `userInvitation.modal.*`
(message text — finding A5); its "View All Users" button navigates to
`management/settings/access` (Users & Roles) — live-confirmed 2026-07-31 on
all three apps by claim check (an earlier live probe the same day had read
the pre-click wizard anchor, not the post-click page).

<a id="fn-h"></a>
**h** — `UserInvitationSearchFormStep.vue`: `GET users?searchPhrase=…&status=all`,
match order exact email → exact username → ORCID (orcid.org URI prefix
stripped); found → `userInvitation.search.userFound`, miss →
`userInvitation.search.userNotFound`; empty input → "Provide at least one
search criteria." (`invitation.searchForm.emptyError`). Live-checked
2026-07-31 (claim check): a malformed miss (`notanemail`)
advances to "Enter details" with the typed text discarded — the Email field
arrives empty and errors "This field is required when user id is not
present." on continue.

<a id="fn-i"></a>
**i** — `UserInvitationUserGroupsTable.vue` +
`UserInvitationDetailsFormStep.vue`: role select disables held/selected
groups; masthead select (`invitation.masthead.show`/`.hidden`) starts with no
value — inline "This field is required." until picked (claim check
2026-07-31: OJS and OMP) — and is the fixed text "Appear on
the masthead" for reviewer groups, with no select rendered (claim check
2026-07-31; on-screen text only — the public masthead page was not probed);
an added
row's END DATE cell renders "---" with no input (claim check 2026-07-31);
disabled-user warning `userInvitation.user.disable*` and
`isSubmitting` also stuck while `userGroupsToAdd` is empty. Payload contract:
`userGroupsToAdd[]` = `{userGroupId, masthead (required bool), dateStart
(required date), dateEnd (optional)}` (`UserRoleAssignmentInvitePayload` +
rules `AllowedKeysRule`, `UserGroupExistsRule`, `AddUserGroupRule`,
`NoUserGroupChangesRule`). Live probe 2026-07-31: "Add
Another Role" alone enables "Save And Continue" — missing role fields error
inline on continue; disabled-user banner full text "The user is currently
disabled. The user was disabled. You cannot assign them a role while they are
disabled. Please enable the user first to invite them to a role.", with both
buttons disabled (enabled-user control passed).

<a id="fn-j"></a>
**j** — Mailable `PKP\mail\mailables\UserRoleAssignmentInvitationNotify`
(MAIL-055), template key `USER_ROLE_ASSIGNMENT_INVITATION`, variables
`recipientName`, `inviterName`, `inviterRole`, `rolesAdded` (with dates +
masthead visibility text), `existingRoles`, `acceptUrl`, `declineUrl`; sender
is the inviter. Sent only from `Invitation::invite()`. OPS divergence:
`ops-main/classes/mail/Repository::map()` overrides the base map without
merging and omits this mailable (comment: "OPS uses distinct mailables"),
while OJS/OMP `Repository::map()` merge the base list; the template key is
seeded in the OPS registry all the same, so sending works — finding OPS1.
Chain check: no app subclasses any invitation class; the API shim
`api/v1/invitations/index.php` is byte-identical in all three apps; no
app-name branches exist in shared invitation code.

<a id="fn-k"></a>
**k** — Accept wizard: `pages/acceptInvitation/AcceptInvitationPage.vue` +
store (VUE-001), mounted by `acceptInvitation.tpl` (AFFU-122) from
`UserRoleAssignmentInviteRedirectController::acceptHandle()`; steps from
`PKP\invitation\stepTypes\AcceptInvitationStep::getSteps()` — ORCID step only
`if (!$user->hasVerifiedOrcid() && OrcidManager::isEnabled($context))`
(shared core `OrcidManager`, identical in all three apps); new-user chain
`verifyOrcid → userCreate → userDetails → userCreateReview`, existing-user
chain `verifyOrcid → userCreateReview`; the review step is present for every
recipient, so the store's empty-step auto-finalize branch is unreachable in
practice — live checks 2026-07-31 found no auto-accept with
ORCID off (its shipped state in the test contexts) or on. Step advance →
`PUT invitations/{id}/key/{key}/refine`;
final → `…/finalize`. Review-step Edit button renders only for new users
(`AcceptInvitationReview.vue`). ORCID buttons: `AcceptInvitationVerifyOrcid.vue`;
the wizard's primary button is hidden on that step.

<a id="fn-l"></a>
**l** — `UserRoleAssignmentReceiveController::authorize()` calls
`Validation::registerUserSession($user)` for a signed-out existing invitee,
which reads as auto-login — but live probing (2026-07-31, two independent
runs) found the recipient signed out throughout the wizard and
after finalize (finding A4); the wizard simply never asks for credentials.
Signed-in non-invitee → refusal (store dialog keys
`acceptInvitation.authorization.shouldBeAnonymous` / `.message`, action
`user.logOut` — live-confirmed, the Logout button ends the session); new-user
invitations require an anonymous session. Key-based API ops
(`GET/PUT invitations/{id}/key/{key}/…`) are public routes with per-type
authorization.

<a id="fn-m"></a>
**m** — `UserRoleAssignmentReceiveController::finalize()`: creates the user
(username, names, email, country, affiliation, verified-ORCID data, password)
or backfills ORCID for an existing one; assigns each `userGroupsToAdd` row via
`Repo::userGroup()->assignUserToGroup(...)` with past `dateStart` clamped to
today; marks ACCEPTED. No recipient holds a session after finalize (finding
A4 — live-confirmed on all three apps) and no mail or notification goes to
the inviter (finding A5 — live-confirmed 2026-07-31 with a positive
control); the store's closing dialog
(`acceptInvitation.modal.*`, button "View All Submissions") redirects to the
`submissions` page, which greets the signed-out recipient with the sign-in
form.

<a id="fn-n"></a>
**n** — Decline: `declineInvitation.tpl` (AFFU-123) — POST + CSRF to
`invitation/confirmDecline`; base
`InvitationActionRedirectController::declineHandle()` throws
`GoneHttpException` unless PENDING;
`UserRoleAssignmentInviteRedirectController::confirmDecline()` marks DECLINED
and redirects to `login`. Live probe 2026-07-31: flow verbatim as
specified; after the decline both links render the unavailable page (note f),
not a bare gone response.

<a id="fn-o"></a>
**o** — Validation (`UserRoleAssignmentInvite` rules): `UsernameExistsRule`;
password `Password::min($site->getMinPasswordLength())` — the field's helper
text states the six-character default minimum (live-checked 2026-07-31; one
observation from this check is recorded in the maintainer's private security
file, not here); `EmailMustNotExistRule` at finalize —
`changeInvitationUserIdUsingUserEmail()` first converts an email invitation
into an existing-user invitation when the address has since registered;
privacy consent enforced client-side
(`acceptInvitation.privacyStatement.validation`), auto-satisfied for existing
users; `givenName` (primary locale) + `userCountry` required at
finalize/refine; existing-user invitations prohibit personal-detail overrides
(`ProhibitedIncludingNull`).

<a id="fn-p"></a>
**p** — Listing: `GET invitations/userRoleAssignment`
(`InvitationController::getMany()`) scoped
`byType → byContextId → stillActive()` (= not expired AND status PENDING —
Eloquent groups the scope's OR internally); 5 rows per page; status cell is
always `userInvitation.status.invited` ("Invited {date}"). Columns: Name
(+ORCID icon), Email, Invitations (the offered roles), Status, Affiliation —
live-confirmed 2026-07-31.

<a id="fn-q"></a>
**q** — Cancel: `PUT invitations/{id}/cancel` → status CANCELLED (allowed only
while PENDING); the row is kept, so old links reach the unavailable page (note
f). Edit: dialog `userInvitation.edit.title`/`.message` → page
`invitation/edit/{id}` (`editHandle`, mode `edit`) — but the wizard store
starts with no invitation id even in edit mode, so the first advance
`POST`s a NEW invitation and `invite()` then deletes the old PENDING row
(note d) — hence the replaced row's hard-404 links (finding A3). Live
2026-07-31 (live probe): cancel and replacement flows verbatim; the
cancel confirmation's confirm button reads "Cancel Invitation" beside the
dismiss button "Cancel". Each pass through the edit wizard mints a fresh
draft (see f-a2).

<a id="fn-r"></a>
**r** — Removal email "You have been removed from a role"; masthead email
"Your journal masthead visibility has been updated"; dialog copy "The user
will be notified of this change." (live probe 2026-07-31; both delivered on
OJS).
Masthead template key `USER_ROLE_MASTHEAD_UPDATE` — OMP/OPS seeding gap in
f-omp1.

<a id="fn-s"></a>
**s** — Scenario seeding: run on the seeded test journal/press/server
(`publicknowledge`) with the seeded Journal Manager / Press Manager / Preprint
Server Manager account; recipients use unique throwaway mailbox addresses per
app and test; mail observed in the test mail catcher. Exact usernames per the
e2e harness roster (test-authoring time). Scenario 3's "seeded user" is any
roster account not yet holding the offered role.

<a id="fn-a1"></a>
**f-a1** — Role assignment vs screen gate: note b vs note a. The atlas route
row records the same mismatch. Live check 2026-07-31, all three apps:
Author and Reviewer
denied at the wizard address; the section-editor and assistant-level outcomes
are recorded in the maintainer's private security file. OPS has no seeded
reviewer account, so that one cell was untestable.

<a id="fn-a2"></a>
**f-a2** — `scopeExpired()` includes `orWhereNull('expiry_date')` (note e);
`setExpiryDate()` only runs inside `invite()`, so drafts (`INITIALIZED`) always
have NULL expiry and match the delete. Drafts are invisible in the UI (listing
scope, note p), so the loss surfaces only as a failed wizard step. Live: one
edit-mode session created three draft invitations, only the last sent
(live probe 2026-07-31); the cleanup run itself was not exercised.

<a id="fn-a3"></a>
**f-a3** — Replacement path in note q: the old PENDING row is deleted by
`Invitation::invite()`'s `byNotId` cleanup, and `getByIdAndKey()`/the
unavailable-page fallback both need the row to exist (note f) → hard 404.
Cancellation keeps the row → friendly page. Live-confirmed 2026-07-31
(OJS and OPS): raw "404 Not Found", no journal styling.
Claim check 2026-07-31 (OJS deep,
OMP spot-check): a plain re-send to the same address kills the old accept
link the same way — same `byNotId` cleanup, not edit-specific.

<a id="fn-a4"></a>
**f-a4** — `finalize()` registers no session (note m) while the store then
redirects to `submissions`; the code's apparent existing-user auto-login
never materializes either (note l). Live-confirmed 2026-07-31 on OJS, OMP and
OPS, on both the accept and decline flows.

<a id="fn-a5"></a>
**f-a5** — Success-dialog copy: app locale key `userInvitation.modal.message`.
No accept/decline code path produces a notification or email to the inviter
(notes m, n). Live-confirmed 2026-07-31 (live probe, accept and decline
cases): bell/Tasks panel "No Items", inviter mailbox empty after both accept
and decline; positive control — an invitation sent afterwards delivered
normally.

<a id="fn-a6"></a>
**f-a6** — Users-grid Edit on a disabled member (live probe 2026-07-31): error
toast "The requested resource was not found.", empty roles table, no
disabled-user banner; the banner renders only on the search path
(`userInvitation.user.disable*`, note i). Enabled-user control passed.

<a id="fn-a7"></a>
**f-a7** — Copy items, all observed on live probes 2026-07-31: email
greeting by address and "as a Author" (invitation email); "journal masthead"
wording on OMP/OPS
masthead dialogs; search-step grammar "Enter at least
one details…" / "…invite to take a additional roles"; raw locale keys
`##common.help##` and `##userAccess.management.options##` on the management
screens of all three apps; and (claim check 2026-07-31) the
unavailable-page tail "Please contact the journal manager for further
assistance." unsubstituted on OMP and OPS.

<a id="fn-a8"></a>
**f-a8** — Every added row in `UserInvitationUserGroupsTable.vue` renders
`#-userGroupId-control`, `#-dateStart-control`, `#-masthead-control` — the
same ids as row 1 (empty `formId` segment in `FieldBase.compileId()`) — so
every `label[for]` resolves to the first row's control; no
`aria-label`/`aria-labelledby` anywhere in the table, and the aria snapshot
shows row 2's combobox/textbox without an accessible name. Observed
2026-07-31 (claim check —
duplicate-id scan + aria snapshot on OJS; shared component, all three apps).

<a id="fn-omp1"></a>
**f-omp1** — Error observed on OMP and OPS (live probes 2026-07-31, two
independent sessions); OJS control clean, member's email delivered (note r).
Install-seed check 2026-07-31: only OJS's `registry/emailTemplates.xml` seeds
`USER_ROLE_MASTHEAD_UPDATE`; the OMP and OPS registries carry no masthead
template, and the `I11800_AddUserRoleMastheadUpdateEmail` migration adds it
on upgraded installs only — omp_test/ops_test hold 0 such default rows vs 2
in ojs_test, so fresh installs reproduce the error.

<a id="fn-ops1"></a>
**f-ops1** — Evidence in note j (OPS map override vs seeded template).
Live-confirmed 2026-07-31 (live probes, all three apps): "User Invited
to Role Notification" listed with an Edit button on OJS and OMP; OPS search
and full list answer "No items found."; the OPS invitation email sent and
delivered in the same session.

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Users & Roles · Invitations table + "Invite to a role" | Settings → Users & Roles → Users tab | AFFM-099..101 · VUE-052 |
| Send wizard (create) | `{journal}/invitation/create/userRoleAssignment` | ROUTE-013 · VUE-011 · AFFM-118..120 |
| Send wizard (edit pending) | `{journal}/invitation/edit/{invitationId}` | ROUTE-013 · AFFM-100 |
| Send wizard (existing member) | users grid Edit → `{journal}/management/settings/user/{userId}` | ROUTE-017 rider (`editUser` — owned elsewhere) |
| Emailed accept link | `{journal}/invitation/accept?id=…&key=…` | ROUTE-014 · AFFU-206 · AFFU-122, 126..137 · VUE-001 |
| Emailed decline link | `{journal}/invitation/decline?id=…&key=…` → POST `confirmDecline` | ROUTE-014 · AFFU-123 |
| Unavailable-invitation landing | rendered in place of accept/decline | AFFU-124..125 |
| Invitations API | `api/v1/invitations/…` | API-024 |
| Invitation email | mailable key `USER_ROLE_ASSIGNMENT_INVITATION` | MAIL-055 |
| Site config | `[invitations] expiration_days` | SET-064 |
| Cleanup | daily scheduled task + queued job | JOB-051 · JOB-013 |

## Reference — code anchors

- `lib/pkp/classes/invitation/core/Invitation.php` — lifecycle, key, expiry
- `lib/pkp/classes/invitation/invitations/userRoleAssignment/UserRoleAssignmentInvite.php` (+ `payload/`, `rules/`, `handlers/`)
- `lib/pkp/pages/invitation/InvitationHandler.php` · `InitializeInvitationUIHandler.php`
- `lib/pkp/api/v1/invitations/InvitationController.php`
- `lib/pkp/classes/invitation/models/InvitationModel.php` · `repositories/Repository.php`
- `lib/pkp/classes/invitation/stepTypes/SendInvitationStep.php` · `AcceptInvitationStep.php`
- `lib/pkp/classes/mail/mailables/UserRoleAssignmentInvitationNotify.php`
- `lib/pkp/jobs/invitations/RemoveExpiredInvitationsJob.php` · `lib/pkp/classes/task/RemoveExpiredInvitations.php`
- UI library: `src/managers/UserInvitationManager/` · `src/pages/userInvitation/` · `src/pages/acceptInvitation/`
- App divergence: `ops-main/classes/mail/Repository.php` (map override)
