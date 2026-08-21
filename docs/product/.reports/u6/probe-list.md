# U6 — User invitations · probe list (RUNBOOK step 2 deliverable)

> **Frame** (binding for every probe agent): QA documentation of an application's
> own screens, on a local disposable test install with seeded accounts. Sign in as
> each role and use the screens the way that role would — including typing a URL
> directly to reach one. Record what the screen offers, what happens when it is
> used, and where the two disagree, so the product team can fix it. Never
> construct a request the screens themselves would not send; a question that
> needs that goes on the deferred queue instead.

**Sources folded in**: spec draft `docs/product/specs/user-invitations.md`
(every *(unconfirmed)* claim, footnote probe note, and ❓ register entry) and
code inventory `.reports/u6/code-inventory.md` §8 (P1–P14 — all 14 absorbed
below; the P-number is cited on each item).

**Environment (all items)**: fleets OJS :8000 · OMP :8100 · OPS :8200; Mailpit
up, scope by recipient (and per-app tag when fleets run concurrently), never
`clearAll()`. `publicknowledge` and seeded users are READ-ONLY; anything
mutating happens in a scratch context created via the scenario endpoints.
Install facts that frame several items: ORCID is **not configured** on the test
install (no credentials — it is off by default), and the scheduled task runner
is **Off**. Items involving ORCID or expiry are phrased to record the install
fact, not to prove an impossibility.

**Reporting (every item)**: record the exact on-screen wording, the locator
used, and mark claim-vs-context. Reports go to `.reports/u6/` per RUNBOOK
step 3. An observation nobody can judge against the "Settles" line is wasted
budget — write what you saw, next to what the draft claimed.

**Groups**: four, no overlap; each can go to its own probe agent. Groups A and
C are the **cross-app** groups (multi-app rule 4): every item in them is owned
by ONE agent driving all three fleets — never split by app mid-item. Groups B
and D run on OJS alone (the load-bearing code paths have empty subclass chains
in all three apps — inventory §2; the cross-app controls those single-app items
still need are already itemized inside Groups A and C).

---

## Group A — Access doors & role gates (CROSS-APP: OJS + OMP + OPS, one agent)

*Settles: who can actually reach the Invitations table and the wizard — the
draft's Actors table conditions and register entries A1, A2, A4.*

**A-1.** *(P5 · register A4 · fn-a)* As the seeded **Site Administrator**, in
each app, type `/{ctx}/management/settings/access` into the address bar
(context: `publicknowledge` or the app's seeded context — read-only visit).
Record what appears: the Users & Roles page with the "Invitations" table and
the "Invite to a role" button, or a refusal/redirect, and its exact wording.
**Settles**: whether OMP and OPS really turn a site admin away while OJS admits
them (A4). Apps: all three · cross-app control item.

**A-2.** *(P12 · register A2 · f-a2)* In each app, type the older address
`/{ctx}/management/access` (no `settings/` segment) — first as **Site
Administrator**, then as the seeded **Manager**. Record for each role: what
renders, whether the Invitations table and "Invite to a role" button are
present, or the refusal shown. **Settles**: whether the two doors admit
different sets of people to the same screen (A2). Apps: all three · cross-app.

**A-3.** *(fn-a, fn-b)* As the seeded **Manager** (Journal/Press/Preprint
Server Manager) in each app, open Settings → Users & Roles, Users tab, from the
menu. Record: the "Invitations" table heading, and that the "Invite to a role"
button appears above it and opens the wizard when pressed. **Settles**: the
Actors table's baseline row (manager sees table + button) in all three apps.
Apps: all three · cross-app.

**A-4.** *(P10 · fn-a)* In a **scratch context** in each app: using the Roles
screen (Users & Roles → Roles), edit the manager-slot role to withdraw its
settings-access permission (if the Roles screen offers no such control, record
that as the observation and stop); sign in as a manager holding only that role;
type `/{ctx}/management/settings/access`. Record what appears. **Settles**: the
Actors table's condition "when their role is allowed to access settings".
Setup: scratch context per app; a scratch manager user in the modified group.
Apps: all three · cross-app.

**A-5.** *(P9 · register A1 · f-a1)* As the seeded **Section Editor**, and
again as the seeded **Assistant** (OPS: the Moderator fills the section-editor
slot and OPS seeds NO assistant group — record that as an install fact and skip
that half there), in each app: type
`/{ctx}/invitation/create/userRoleAssignment`, and separately
`/{ctx}/management/settings/access`. Record what each screen shows. If the
wizard opens, walk it to completion in a scratch context and record where
"Cancel" and the final button land. **Settles**: whether the two extra roles
the machinery admits get a working wizard that dumps them on a screen they
cannot open (A1). Setup: scratch context for any completed walk. Apps: all
three · cross-app.

---

## Group B — Send-wizard mechanics (OJS only; scratch journal)

*Settles: the draft's Fields & validation table, Rules 3–9, and register
entries A3, A5 (send half), A6. Shared code, empty subclass chains — single-app
evidence; the cross-app controls live in Groups A and C.*

Common setup: sign in as the seeded Journal Manager of a **scratch journal**
created via the scenario endpoints; invitee emails must match no seeded user.

**B-6.** *(fn-n · Rule 3)* Send 6 invitations in the scratch journal, then
open Users & Roles → Users. Record: table heading and count, the column
headers, the status cell wording for several rows (draft: always "Invited
{date}"), rows per page and the pagination control, the per-row
"Invitation management options" menu and its two entries. **Settles**: Rule 3's
table anatomy and the pending-only, five-per-page claims. Apps: OJS.

**B-7.** *(fn-o · Rule 4 · P7 send half · register A5)* Open the wizard via
"Invite to a role" and walk all three steps. Record: the page heading, each
step's name, each step's Continue-button label, when "Back" first appears,
that "Cancel" is always present, which step tabs are clickable before and
after visiting them — and the step list's **accessible name** (does a raw key
like `invitation.wizard.completeSteps` surface?). **Settles**: Rule 4's chrome
claims and the send half of A5. Apps: OJS.

**B-8.** *(fn-e · Rule 4a · draft's ORCID setting row)* On the wizard's search
step: (a) press Continue with the field empty — record the message; (b) search
an email-shaped term matching nobody — record the message and whether the
email is pre-filled on the details step; (c) search a seeded user's email —
record the message, that identity fields are read-only, and that current roles
are listed; (d) search a seeded username — record whether it matches. Also
record, on the details step for a newcomer: whether any ORCID iD field is
offered (**install fact** — ORCID is not configured on this install; record
its absence as such). **Settles**: Rule 4a's two search outcomes, the empty-
search refusal, the email carry-over, and the ORCID-field gating stated as an
install fact. Apps: OJS.

**B-9.** *(fn-h · Rule 5)* On the details step for a newcomer: add a role row
and try to save with (a) no role chosen, (b) today's date as Start Date,
(c) a past date — record each refusal's wording. Then, for an existing scratch
user, invite a role they already actively hold — record the refusal when the
step saves. Also record "Add Another Role" and per-row "Remove Role" controls.
**Settles**: the Fields table's required/after-today rules and Rule 5's
already-held refusal. Apps: OJS.

**B-10.** *(P14 · fn-p · Rule 4b)* From the **users table** (not the
Invitations table), use a scratch user's row "Edit" action. Record: the wizard
opens on "Enter details" with no search step; whether the primary Continue
button is disabled on arrival; and what action first makes it enabled.
**Settles**: Rule 4b's *(unconfirmed)* disabled-until-changed claim. Apps: OJS.

**B-11.** *(fn-r · Rule 8)* Disable a scratch user via the users table, then
open the wizard for them (row Edit). Record: the exact banner text ("The user
is currently disabled." + explanation) and the state of the role-adding
controls (present/absent/disabled). **Settles**: Rule 8. Apps: OJS.

**B-12.** *(fn-s · Rule 9 · register A3)* Open the wizard for a scratch user
holding **two** roles. (a) Press "Remove Role" beside a current role — record
the confirmation dialog's wording, confirm, then **Cancel the wizard without
sending**; check the users table: is the role gone? (b) Change a current
role's masthead choice — record the confirmation and whether it applies
immediately. (c) For a scratch user with **one** role, press "Remove Role" —
record the refusal dialog. **Settles**: Rule 9 and the on-screen wording
behind A3 (the draft says the surrounding warning covers only the invitation).
Apps: OJS.

**B-13.** *(P13 · register A6 · f-a6)* Bring the wizard to its final step,
then make the server refuse the send using only the screens — e.g. in a
second tab, cancel that same pending/composing invitee's earlier invitation
or accept the same email, then press "Invite user to the role". Record
exactly where the refusal appears: a warning banner above the buttons, inline
notes on the step, or nothing. **Settles**: A6's claim that the final-step
banner can never appear. Apps: OJS.

**B-14.** *(P8 · fn-c template oddity)* Load the send wizard, and separately
open a pending scratch invitation's emailed "Accept Invitation" link. On both
pages, record whether any literal `<?php` text is visible anywhere on screen.
**Settles**: whether the stray template line renders. Apps: OJS.

**B-15.** *(P11 · fn-g · Rule 5)* Create a **second scratch journal**. In the
first journal's wizard, open the "Select a new role" dropdown and record the
full option list. **Settles**: whether the dropdown ever lists another
journal's role (the draft's *(unconfirmed)* own-roles claim). Apps: OJS
(shared code; single-app read is the evidence the draft asks for).

---

## Group C — OPS email catalogue, role lists & vocabulary (CROSS-APP: one agent)

*Settles: register OPS1 (the draft's biggest open question), the OPS role/
masthead facts (P6, fn-i), the glossary-substitution claims, and A7.*

**C-16.** *(P1 · register OPS1)* As the manager in each app, open the Emails
management screen (Settings → Workflow → Emails) and search/scan for the
invitation email ("You are invited to new roles" /
USER_ROLE_ASSIGNMENT_INVITATION row). Record: present or absent, per app.
**Settles**: OPS1's no-row consequence, with OJS and OMP as controls. Apps:
all three · cross-app.

**C-17.** *(P2)* Where the row exists (OJS, OMP — and OPS if C-16 finds one),
open the invitation email template on that screen and record whether a
role/user-group restriction control is offered and what it shows. **Settles**:
the template's editability surface per app. Apps: all three · cross-app.

**C-18.** *(P3)* On OPS's Emails screen, attempt — through whatever the screen
itself offers — to add an alternate/custom template for the invitation email.
Record what the screen offers or says (if C-16 found no row, "the screen
offers no path" is the observation). **Settles**: OPS1's alternate-template
consequence. Apps: OPS (with the OJS path recorded once as control). Cross-app.

**C-19.** *(P4 · P6 OPS half · scenario s8 · fn-s8)* Full invite walk on an
**OPS scratch server** as its manager: open the wizard, record the "Select a
new role" dropdown's complete option list (draft expects the 5 seeded groups,
no Reviewer) and how each row's masthead cell renders (selector vs fixed
text); continue to the email step — record whether the composer shows a
filled subject and body; send — record what the screen says; check Mailpit
scoped to the recipient — did the email arrive, with both buttons?; open the
accept link and complete acceptance. Run the composer/delivery observations
once each in an OJS and an OMP scratch context as controls. **Settles**:
whether OPS invitations actually send and arrive despite the catalogue
omission (OPS1), plus the OPS role list and masthead claims. Setup: scratch
contexts in all three apps; Mailpit scoped by recipient. Apps: all three ·
cross-app — one agent end to end.

**C-20.** *(P6 controls · fn-i)* In the OJS and OMP scratch contexts' wizards,
add a **Reviewer** role row and a non-reviewer row. Record: the reviewer row's
masthead cell (draft: fixed text "Appear on the masthead", no selector) versus
the other row's selector and its two options. **Settles**: the Fields table's
*(unconfirmed)* reviewer-masthead claim. Apps: OJS + OMP · cross-app.

**C-21.** *(locale seam, inventory §3b)* On OMP and OPS, while walking C-19,
record the app-flavoured wording at each seam: the search outcome messages
("…role in this press/server"?), the masthead column label ("Journal
Masthead" equivalent), the acceptance flow's account step name ("Create
OMP/OPS account"?), the final button ("Accept And Continue to …"), and the
success dialog text. **Settles**: the draft's unmarked glossary-substitution
claims. Apps: OMP + OPS (OJS wording comes from Group D) · cross-app.

**C-22.** *(register A7 · f-a7 · fn-c)* On OMP and on OPS: cancel a scratch
invitation, then open its emailed accept link. Record the "Invitation
Unavailable" page's full description sentence (does it say "journal
manager"?) and where the "Login" and "Register" buttons lead. Repeat once on
OJS as control. **Settles**: A7 and Rule 13's page anatomy. Setup: scratch
invitation per app. Apps: all three · cross-app.

---

## Group D — Acceptance flow & lifecycle (OJS only; scratch journal + Mailpit)

*Settles: Rules 1–2 and 10–13, the acceptance Fields table, footnotes d, f, j,
k, t–x, and scenarios s1–s7's unconfirmed steps.*

Common setup: scratch journal, seeded Journal Manager as inviter; invitee
addresses match no seeded user; Mailpit scoped by recipient, never cleared.

**D-23.** *(s1 · fn-y, fn-t, fn-v · Rules 1, 10)* Newcomer end to end: invite
a fresh email; in Mailpit record the subject ("You are invited to new
roles"?), that the body names inviter and journal and lists roles, and the two
buttons. Open "Accept Invitation": record the step list actually offered
(**install fact**: ORCID is not configured, so record the absence of any
"Verify ORCID iD" step as such), complete account, details, and review steps,
record the final button's label and the success dialog's text and where "View
All Submissions" lands. Back as manager: the newcomer is in the users table
with the role; the Invitations row is gone. **Settles**: scenario 1 and the
email/side-effect claims wholesale. Apps: OJS.

**D-24.** *(fn-f, fn-j · P7 accept half · register A5)* On a newcomer accept
flow's account step: (a) enter a password shorter than the stated minimum —
record the message; (b) enter a widely-breached password of legal length
(e.g. "password1234") — record exactly what happens (the install's egress is
firewalled; the live behavior — refusal, error, or silent pass — is precisely
what the draft cannot predict); (c) enter a seeded username — record when and
where the taken-username refusal appears (draft: server-side at the final
step; watch the review step's warning banner and record its exact text — a
raw key like `invitation.wizard.errors` is the A5 observation). Also record
the accept wizard's step-list accessible name. **Settles**: the password/
username rows, the reachable review banner, and the accept half of A5.
Apps: OJS.

**D-25.** *(fn-k)* On the account step, leave Privacy Consent unticked and
press Continue — record the error text and which steps it blocks. **Settles**:
the consent row's blocking claim. Apps: OJS.

**D-26.** *(s2 · fn-d, fn-t · Rule 10 existing-user branch)* Invite a seeded
non-manager user (e.g. a seeded author) to a role they lack; **signed out**,
open the accept link. Record: whether the flow signs them in automatically
(is the user menu suddenly theirs?), which steps appear (review only? an
immediate acceptance with no steps at all?), and after accepting, that the
role is attached. **Settles**: the auto-sign-in *(unconfirmed)* claim and
which steps an existing user actually sees on this install. Note: inviting
attaches a role to a seeded user — do this with a scratch-journal role so the
seeded user's `publicknowledge` state is untouched, or use a scratch user
with an existing account instead. Apps: OJS.

**D-27.** *(s6 · fn-d · Rule 12)* Sign in as a different seeded user, paste a
pending invitation's accept link. Record: the refusal dialog's exact text,
what "Log Out" does (and that re-opening the link then works), and where
dismissing the dialog lands. **Settles**: Rule 12. Apps: OJS.

**D-28.** *(s3 · fn-x · Rule 11)* Open a pending invitation's "Decline
Invitation" link (no sign-in). Record: the page's headings and button label,
what pressing "Confirm Decline Invitation" lands on, that the manager's table
no longer lists the row, that the accept link now lands on "Invitation
Unavailable" — and that Mailpit shows **no new message** to anyone for the
decline. **Settles**: Rule 11 and the no-mail-on-decline claim. Apps: OJS.

**D-29.** *(s4 · fn-q · Rule 7)* On a pending row, open "Invitation management
options" → "Cancel Invite". Record: the dialog's listed details (email, roles,
status, affiliation) and warning sentence, both buttons' labels and effects,
that the row disappears, that the already-delivered accept link lands on
"Invitation Unavailable", and that no new mail is sent. **Settles**: Rule 7
and the no-mail-on-cancel claim. Apps: OJS.

**D-30.** *(s5 · fn-q · Rule 6)* On a pending row, choose "Edit". Record: the
warning dialog's exact text, that confirming reopens the wizard on "Enter
details" preloaded (no search step); change the role set and send. Record:
the table shows the new invitation, the FIRST email's accept and decline
links land on "Invitation Unavailable", the SECOND email's links work.
**Settles**: Rule 6 and scenario 5's cancel-and-resend promise. Apps: OJS.

**D-31.** *(s7 · fn-m, fn-s7 · Rule 2)* Age an invitation past its validity:
preferably seed a backdated pending invitation via a scenario endpoint; if
none exists, set `[invitations] expiration_days` low in the scratch install's
config and wait it out (record which setup was used). Then record: the
manager's Invitations table no longer lists the row, and the accept link
lands on "Invitation Unavailable" with "Login" and "Register" leading to the
sign-in and registration pages. The nightly deleter does not run here
(task runner Off — install fact); the listing and the link ARE the
assertions, deletion is NOT probed. **Settles**: Rule 2's visible half and
scenario 7. Apps: OJS.

**D-32.** *(fn-w · Rule 10 cancel bullet)* Mid-acceptance, press "Cancel".
Record: the dialog's title and message, that "Cancel Invitation Process"
abandons the flow and where it lands, that "Go Back" returns — and that the
invitation is still pending in the manager's table and the link still opens
the flow. **Settles**: the cancel-keeps-it-pending claim. Apps: OJS.

**D-33.** *(fn-c tail · Rule 13)* Type an accept URL whose `id` matches no
invitation (a stale/garbled link, e.g. a very large id) into the address bar.
Record what page appears (draft: the site's not-found page, not "Invitation
Unavailable"). **Settles**: Rule 13's last sentence. Apps: OJS.

---

## Coverage notes

- All 14 inventory §8 items are absorbed: P1→C-16, P2→C-17, P3→C-18,
  P4→C-19, P5→A-1, P6→C-19+C-20, P7→B-7+D-24, P8→B-14, P9→A-5, P10→A-4,
  P11→B-15, P12→A-2, P13→B-13, P14→B-10.
- Register coverage: A1→A-5 · A2→A-2 · A3→B-12 · A4→A-1 · A5→B-7+D-24 ·
  A6→B-13 · A7→C-22 · OPS1→C-16..C-19.
- **Not probed, deferred instead** (see `../e2e_ng/server-questions.md`):
  whether the daily cleanup job permanently DELETES expired invitations —
  no screen shows deletion and the test env's task runner is Off; D-31
  covers everything a screen can show. Two U6 lines already on the queue
  (receive-side field tampering; ORCID-iD search matching) are not
  re-listed here and no item touches them.
- fn-j's breached-password rule: only the firewalled-install behavior is
  recordable (D-24b records whatever the screen does); that IS the install
  fact the spec needs — no deferred line, nothing out of frame.
