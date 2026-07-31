# U6 User Invitations — Probe 2: Send flow & Accept flow

Probe agent (RUNBOOK step 3). Facts from driving running installs 2026-07-31.
Items covered: 3 (send/new email), 4 (accept as new user), 5 (accept as
existing user), 12 (ORCID gating / zero-step accept).

## Environment used

- Fleets: OJS `http://127.0.0.1:8000`, OMP `:8100`, OPS `:8200`. Mailpit `:8025`.
- Scratch contexts created via `POST /api/v1/_test/scenarios/context`
  (`X-Test-Key: playwright-test-key`), each with one manager user:
  - OJS: context path `u6pojs` (id 3), manager `u6mgrojs` (pw `u6mgrojsu6mgrojs`)
  - OMP: `u6pomp` (id 3), manager `u6mgromp`
  - OPS: `u6pops` (id 3), manager `u6mgrops`
  - Scratch contexts auto-enroll `admin` as manager (parity lesson 13), so each
    Users list shows 2 users before invites (admin + the scratch manager).
- Throwaway recipients: `u6p3-<app>@mail.test` (items 3/4/5),
  `u6p12b-ojs@mail.test` (item 12 enabled-ORCID run). All are `@mail.test`
  addresses the probe created; no seeded roster user was invited or mutated.
- Entry point for the send flow: Settings → **Users & Roles** →
  `management/settings/access` (Users tab) → **"Invite to a role"** button,
  which navigates to `/{ctx}/invitation/create/userRoleAssignment`.

---

## Item 3 — Send flow, new email

### OJS (deep) — signed in as `u6mgrojs` @ `u6pojs`

**Users & Roles Users tab** (`/u6pojs/management/settings/access`): two panels.
Top panel heading **"Invitations (0)"** with **"Invite to a role"** button
top-right; its table columns: `NAME · EMAIL · INVITATIONS · STATUS ·
AFFILIATION` (+ hidden More Actions). Empty state text: **"No Items"**. Lower
panel **"Current Users (2)"** with a search box (placeholder "Enter a user's
name, role (e.g Journal...") and columns `NAME · EMAIL · ROLES · START DATE ·
AFFILIATION`. Footer "Showing 1 to 2 of 2".

**Wizard header** (all steps): H1 "Invite user to take a role", sub-line
"You are inviting a user to take a role in OJS along with appearing in the
journal masthead". Step rail: **`1 Search User` · `2 Enter details` ·
`3 Review & invite for roles`**.

**STEP 1 — Search User.** URL `/u6pojs/invitation/create/userRoleAssignment`.
Heading "STEP 1 - Search User". Body text: "Search for the user using their
email address, username or ORCID ID. Enter at least one details to get started.
If the user does not exist, you can invite them to take up roles and be a part
of your journal. If the user already exist in the system, you can view user
information and invite to take a additional roles." Field label "Search for a
user by email address, username, or ORCID iD. Enter only one to get started!"
with placeholder "e.g. aeinstein@example.com or aeinstein or
0000-0002-1825-0097". Input `name="search"`. Buttons: **Cancel**, **Search User**.

**Search-miss message** (searched `u6p3-ojs@mail.test`, no such user): advances
to STEP 2 with a pink notice **"The user does not have a role in this journal"**
and sub-text "You can invite them to take up a role in OJS".

**STEP 2 — Enter details.** Heading "STEP 2 - Enter details and invite for
roles". Fields: **Email \*** (prefilled with the searched address, required),
**Given Name** (optional; help "If you know the given name of the user, you can
enter the information. However, this information can be changed by the user."),
**Family Name** (same help). Roles sub-table columns: **`ROLE · START DATE ·
END DATE · JOURNAL MASTHEAD`**. Per-row controls: **"Select a new role \*"**
(`select[name="userGroupId"]`), **"Start Date \*"** (`input[type=date]`,
placeholder `dd.mm.yyyy`), END DATE shows `---`, **"Journal Masthead \*"**
(`select[name="masthead"]`) options **"Appear on the masthead" /
"Does not appear on the masthead"**. Buttons **"Add Another Role"**, Cancel,
Back, **"Save And Continue"**.

Role dropdown options (OJS scratch default groups, in order): Journal manager,
Journal editor, Production editor, Section editor, Guest editor, Copyeditor,
Designer, Funding coordinator, Indexer, Layout Editor, Marketing and sales
coordinator, Proofreader, Author, Translator, Reviewer, Reader, Subscription
Manager, Editorial Board Member.

Filled: Given=Uma, Family=Probe, role **Section editor**, start 2026-07-31,
**Appear on the masthead**.

**STEP 3 — Compose.** Heading "STEP 3 - Modify email shared with the user",
sub-text "Send the user an email to let them know about the invitation, next
steps, journal GDPR polices and ORCID verification". Left column **"Email
Templates"** with a **"Find Template"** search box and one template card
**"User Invited to Role Notification"** (preview "Invitation to New Role Dear
{$recipientName}, In..."). Right column compose: **To** = `u6p3-ojs@mail.test`
(prefilled), **"Add CC/BCC"** link, **Subject** prefilled **"You are invited
to new roles"**, a rich-text toolbar (B, I, superscript, subscript, link,
**"+ Insert Content"**), and a TinyMCE body prefilled with the template.
Prefill body (raw, variables unresolved in composer): greeting `Dear
{$RECIPIENTNAME},`, "In light of your expertise, you have been invited by
{$INVITERNAME} to take on new roles at U6 Probe ojs", a GDPR paragraph
referencing "Privacy Policy", then `{$EXISTINGROLES}` / `{$ROLESADDED}` tokens,
"On accepting the invite, you will be redirected to U6 Probe ojs.", and green
**Accept Invitation** / red **Decline Invitation** buttons, "Kind regards,
U6 Probe ojs". Buttons bottom: Cancel, Back, **"Invite user to the role"**.

**"Invitation Sent" dialog** (verbatim): title **"Invitation Sent"**, body
**"u6p3-ojs@mail.test has been invited to new role in OJS. You can be updated
about the user's decision on the Users & Roles page, your OJS notifications
and/or your email"**, single button **"View All Users"**. (The button navigates
to `#userInvited` on the same wizard URL rather than to the Users list; the
wizard remains showing STEP 3 behind the dialog.)

**Invitations-table row after send** (Users & Roles → Invitations panel, now
"Invitations (1)"): row `Uma Probe · u6p3-ojs@mail.test · Section editor ·
Invited 2026-07-31 · (blank affiliation)`. Columns confirmed NAME / EMAIL /
INVITATIONS (=role) / STATUS (="Invited 2026-07-31") / AFFILIATION. Row More
Actions menu items: **"Edit"**, **"Cancel Invite"**.

**Notification / activity trace after send:** the inviter's **Tasks** panel
shows "No Items" (0 - 0 of 0 items) — no on-screen notification or activity-log
row visible to the inviter beyond the Invitations-table row itself. Claim-vs-
context: no submission/activity log applies (this is a context-level user
invite, not a submission action).

**Delivered email** (Mailpit, To `u6p3-ojs@mail.test`, one message; positive
result). Subject **"You are invited to new roles"**, From `u6mgrojs
<u6mgrojs@mail.test>`. Rendered body resolves the variables: `Dear
u6p3-ojs@mail.test,` (recipient name falls back to the email address — no given
name substituted in greeting), inviter shown as `u6mgrojs`, journal "U6 Probe
ojs", GDPR paragraph. Roles block header **"Newly assigned roles"**, item:
**"Section editor / Starting from 2026-07-31 / Your name will appear in the
U6 Probe ojs's masthead as a Section editor."** Then "On accepting the invite,
you will be redirected to U6 Probe ojs." and Accept/Decline buttons.
- **Accept link:** `http://127.0.0.1:8000/index.php/u6pojs/invitation/accept?id=3&key=wYWaRS`
- **Decline link:** `http://127.0.0.1:8000/index.php/u6pojs/invitation/decline?id=3&key=wYWaRS`
- Links carry `id` + `key`; the accept/decline pair share the key.

### OMP (through delivery) — `u6mgromp` @ `u6pomp`

Identical wizard structure. Header sub-line reads "…appearing in the **press**
masthead"; STEP 2 miss notice "The user does not have a role in this **press**";
masthead column header **"PRESS MASTHEAD"**. Role dropdown options: Press
manager, Press editor, Production editor, Series editor, Copyeditor, Designer,
Funding coordinator, Indexer, Layout Editor, Marketing and sales coordinator,
Proofreader, Author, Volume editor, Chapter Author, Translator, Internal
Reviewer, External Reviewer, Reader, Editorial Board Member. Invited role
**Series editor**. Compose prefill identical (says "U6 Probe omp"). Dialog:
"u6p3-omp@mail.test has been invited to new role in **OMP**. …your OMP
notifications…". Delivered email To `u6p3-omp@mail.test`, subject "You are
invited to new roles", roles block "Series editor / Starting from 2026-07-31 /
…masthead as a Series editor."
- Accept: `http://127.0.0.1:8100/index.php/u6pomp/invitation/accept?id=2&key=4wYw9J`

### OPS (through delivery — positive control) — `u6mgrops` @ `u6pops`

Header "…appearing in the **server** masthead"; miss notice "…role in this
**server**"; column **"SERVER MASTHEAD"**. Role dropdown (short OPS set):
**Preprint Server manager, Moderator, Author, Reader, Editorial Board Member**.
Invited role **Moderator**. Dialog "…invited to new role in **OPS**. …your OPS
notifications…". 
- **OPS delivered email recipient (positive control for the OPS
  emails-screen-absence claim): `u6p3-ops@mail.test`.** Subject "You are invited
  to new roles", From `u6mgrops@mail.test`, roles block "Moderator / Starting
  from 2026-07-31 / …masthead as a Moderator."
- Accept: `http://127.0.0.1:8200/index.php/u6pops/invitation/accept?id=2&key=5jKUr2`

Note: the invitation email itself is delivered and present in Mailpit on all
three apps including OPS — the OPS "emails-screen absence" claim (a Settings UI
question) is a separate probe's scope; this item confirms the OPS invite email
does get sent.

---

## Item 4 — Accept as new user (signed out)

Driven in a fresh anonymous browser context (no storage state).

### OJS (deep) — accept link from item 3 (`id=3`)

**Landing** = STEP 1 of `AcceptInvitationPage` (URL unchanged
`/u6pojs/invitation/accept?id=3&key=wYWaRS`). Step rail (ORCID DISABLED in this
context): **`1 Create OJS account` · `2 Enter details` · `3 Review & create
account`** — three steps, **no ORCID Verify/Skip step** (see item 12 for the
enabled variant). No tabs; a single-column wizard.

**STEP 1 — Create OJS account.** Body "To get started with OJS and accept the
new role, you will need to create an account with us. For this purpose please
enter a username and password." Fields: **Email** (read-only,
`u6p3-ojs@mail.test`), **Username \*** (help "It could be a combination of
uppercase letters, lowercase letters or numbers"), **Password \*** (help "It
should be at least 6 characters long and could be a combination of uppercase
letters, lowercase letters, numbers and symbols"), **Privacy Consent \***
checkbox "Yes, I agree to have my data collected and stored according to the
Privacy Statement" ("Privacy Statement" is a link). Buttons: Cancel,
**"Save and continue"**.

**Rejection A — privacy checkbox unchecked** (with an otherwise-valid password):
inline error **"Please confirm that you have read and agree privacy statement"**
(verbatim; appears under the Privacy Consent field). Stays on STEP 1.

**Rejection B — password `password123`** (privacy checked): **NO rejection
occurred — the wizard advanced to STEP 2 and the account was creatable.**
This contradicts the item's expectation of a rejection. The observation is a
plausible security-weakness (breached/common password accepted; only the
documented 6-char minimum is enforced) and has been **routed to the private
security file (`../e2e_ng/security.md`)**; kept generic here per policy.
Claim-vs-context: the on-screen rule only promises a 6-char minimum, which
`password123` satisfies.

**STEP 2 — Enter details.** Heading "STEP 2 - Enter details", body about GDPR
("this information can only modified by you… choose if you want this
information to be visible… to the editor"). **Email** (read-only). **ORCID iD**
row: **"Not verified. You can verify your ORCID iD from your profile section in
OJS."** (shown even with ORCID disabled — informational, no button). Fields:
**Given Name \*** (prefilled "Uma" from the invite), **Family Name** (prefilled
"Probe"), **Affiliation** (`affiliation-en`, empty — the invite's given/family
carried over but affiliation did not), **Country of affiliation \***
(`select[name="userCountry"]`, full country list). Buttons Cancel, Back,
"Save and continue".

**STEP 3 — Review & create account.** Heading "STEP 3 - Review & create
account", body "Review details to start your new roles in OJS". Sections:
**Account Details** (Username), **User Details** with an **"Edit"** button and
per-locale (English) Given/Family/Affiliation/Country, and **Roles** table
(`ROLE · START DATE · END DATE · JOURNAL MASTHEAD`) row "Section editor /
2026-07-31 / --- / Appear on the masthead". Buttons: Cancel, Back, **final
button "Accept And Continue to OJS"**.

**Closing dialog** (verbatim): title **"You've been assigned a new role in
OJS"**, body **"Congratulations on your new role in OJS! You might now have
access to new options. If you need any assistance in understanding the system,
please click on the "Help" button throughout the system for guidance."** Single
button **"View All Submissions"**.

**Where the browser lands after the dialog:** clicking "View All Submissions"
navigates to
`/u6pojs/login?source=%2Findex.php%2Fu6pojs%2Fsubmissions` — i.e. the **sign-in
screen** (Home / Login). **The just-accepted user is NOT auto-signed-in**;
they must log in manually.

**Signing in with the new credentials & roles:** the account created with
`password123` (privacy accepted, valid path) signed in successfully — post-login
URL `/u6pojs/dashboard/editorial?currentViewId=assigned-to-me`. Profile → Roles
tab lists, under the scratch context and also under the user's other contexts:
**Reader, Author, Reviewer** as the base roles PLUS the invited **Section
editor** (visible as the row in the Users list after accept:
`Uma Probe · u6p3-ojs@mail.test · Section editor · 2026-07-31`). Note the base
Reader/Author/Reviewer are auto-added on account creation across contexts; the
invited role (Section editor) is the one from the invitation.
(Attempting login with the earlier password value failed / correct one worked —
the account persists the password set at STEP 1.)

Users & Roles after accept: Invitations panel returns to "No Items"; the
invited user now appears in **Current Users** with role Section editor and
affiliation "U6 Probe Institute".

### OMP (post-accept landing confirmed) — accept `id=2`

Same 3-step wizard (STEP 1 Create OMP account / 2 Enter details / 3 Review).
Masthead column "PRESS MASTHEAD"; review row "Series editor". Final button
**"Accept And Continue to OMP"**. Closing dialog title "You've been assigned a
new role in **OMP**" (same body), button "View All Submissions". **Lands on the
sign-in screen** `/u6pomp/login?source=…%2Fsubmissions` — not auto-signed-in.

### OPS (post-accept landing confirmed) — accept `id=2`

Same wizard; masthead "SERVER MASTHEAD"; review row "Moderator"; final button
**"Accept And Continue to OPS"**. Closing dialog "You've been assigned a new
role in **OPS**", button "View All Submissions". **Lands on the sign-in
screen** `/u6pops/login?source=…%2Fsubmissions` — not auto-signed-in.

Consistent across all three: closing dialog → "View All Submissions" →
locale-less `/{ctx}/login?source=/index.php/{ctx}/submissions` sign-in page,
no automatic session for the newly created account.

---

## Item 5 — Accept as existing user (affordance-class claim, all 3 apps)

For each app the invited address was the account **the probe created in item 4**
(`u6p3-<app>@mail.test`, now an existing user in that scratch context), invited
by the scratch manager to a SECOND role.

### Send side (existing-user branch of the wizard)

STEP 2 for an existing user differs from the new-user branch:
- Notice (OJS) **"The user already exists in the journal"** (OMP: "…in the
  press"; OPS: "…in the server").
- Email/Given/Family/Affiliation shown **read-only** (prefilled from the
  account), plus an **ORCID iD** display row.
- The roles table shows the user's **current role as an existing read-only row**
  (e.g. OJS "Section editor / 2026-07-31" with a **"Remove Role"** control and
  its own masthead select) ABOVE the "Select a new role" entry row. The role
  dropdown for the new row **omits roles the user already holds** (e.g. OJS
  no longer lists "Section editor").
- New roles invited: OJS **Copyeditor**, OMP **Copyeditor**, OPS **Editorial
  Board Member**.

Delivered emails now carry **two role blocks**: **"Already assigned roles"**
(the existing role) and **"Newly assigned roles"** (the invited one), and the
greeting resolves to the real name **"Dear Uma Probe,"** (vs the bare email for
the brand-new user in item 3). Example OJS: Already="Section editor", Newly=
"Copyeditor". Accept links:
- OJS `…/u6pojs/invitation/accept?id=5&key=QVgf2Z`
- OMP `…/u6pomp/invitation/accept?id=4&key=yC8ivk`
- OPS `…/u6pops/invitation/accept?id=4&key=HpVQX6`

### Accept side — opened SIGNED OUT (fresh anonymous context)

**Key finding (all 3 apps): the accept link does NOT auto-sign-in the existing
user and does NOT prompt for credentials.** Opening the link signed out lands
directly on a **single-step review wizard**:
- Step rail collapses to **"1 Review & create account"** with "1/1 steps"
  (a `{$current}/{$total} steps` token is also visible unrendered next to it).
- Heading "STEP 1 - Review & create account", body "Review details to start
  your new roles in OJS/OMP/OPS".
- **User Details** section shows only an **ORCID iD** row ("Not verified. You
  can verify your ORCID iD from your profile section in OJS/OMP/OPS.") — no
  account-creation fields, no password, no privacy checkbox (the account
  already exists).
- **Roles** table shows ONLY the newly invited role (OJS "Copyeditor", OMP
  "Copyeditor", OPS "Editorial Board Member" / 2026-07-31 / Appear on the
  masthead).
- Buttons: Cancel, **"Accept And Continue to OJS/OMP/OPS"**. No sign-in step.

So an anonymous visitor holding the emailed link can accept a role addition to
an existing account without proving they are that user (identity rests on
possession of the `id`+`key` link). Recorded here as an affordance/behavior
fact; not separately routed (link-token possession is the designed auth for
accept) — flagging for the digest to judge against the spec's stated intent.

**After accepting:** same closing dialog "You've been assigned a new role in
OJS/OMP/OPS" → "View All Submissions" → lands on the **sign-in screen**
(`/{ctx}/login?source=…submissions`), still not signed in.

**Roles after accepting** (verified in Users & Roles → Current Users as the
manager): the user now holds **both** roles:
- OJS `Uma Probe`: **Section editor + Copyeditor** (both start 2026-07-31).
- OMP `Uma Probe`: **Series editor + Copyeditor**.
- OPS `Uma Probe`: **Moderator + Editorial Board Member**.

---

## Item 12 — ORCID gating / zero-step accept (OJS only)

### ORCID Settings observation

Users & Roles has a fourth tab **"ORCID"** (tabs: Users, Roles, Site Access
Options, ORCID). Content: heading "ORCID", a checkbox **"Enable ORCID
functionality"** (default **unchecked / `orcidEnabled=false`** in BOTH the
scratch context `u6pojs` AND the seeded `publicknowledge` journal — verified as
`u6mgrojs` and as `manager.maya` respectively), help text about configuring the
ORCID API, and a **Save** button. When enabled, additional required fields
appear: **ORCID API \*** (`select`, options "Public / Public Sandbox / Member /
Member Sandbox"), **Client ID \***, **Client Secret \***, City, an e-mail
setting checkbox ("Send e-mail to request ORCID authorization from authors when
an article is accepted…"), and **ORCID request log** level (Errors / All).

So both test/scratch contexts ship with ORCID **disabled** by default.

### With ORCID DISABLED — existing-user invitation, accept link opened

(Existing user `u6p3-ojs@mail.test`, invited to role Reader; accept link
`…/invitation/accept?id=7&key=4xftjn`, opened signed out.)
- **A wizard DOES show** — it is NOT a zero-step immediate acceptance. The
  existing-user single-step review appears (same as item 5): "1 Review & create
  account", User Details (ORCID iD "Not verified…" row), Roles table (Reader),
  and an **"Accept And Continue to OJS"** button. Acceptance still requires
  clicking that button; it does not happen automatically on page load.
- Result screen after opening: the review wizard (no separate ORCID step, since
  ORCID is off). The ORCID "Not verified" line is shown as static text only.

### With ORCID ENABLED (scratch context, dummy Public credentials) — new-user accept

After enabling ORCID (API=Public Sandbox, dummy Client ID/Secret, Saved), a
NEW-user invitation's accept link (`u6p12b-ojs@mail.test`, `id=9`) opens with an
**extra leading step**: step rail becomes **`1 Verify ORCID iD` · `2 Create OJS
account` · `3 Enter details` · `4 Review & create account`** (four steps).
- **STEP 1 — Verify ORCID iD.** Body "You can choose to verify your ORCID iD or
  skip it. If you chose to skip it now, you can verify your ORCID iD from your
  profile section in OJS later". Buttons: **"Verify ORCID iD"**, **"Skip ORCID
  verification"**, Cancel.
- Clicking **"Skip ORCID verification"** advances to STEP 2 (Create OJS
  account) — the same account-creation form as the ORCID-disabled STEP 1. So
  ORCID gating only inserts/removes the leading Verify step; the rest of the
  wizard is unchanged.

Summary for item 12: neither the scratch nor seeded contexts enable ORCID by
default; with ORCID disabled a wizard still shows (existing-user = 1-step
review, new-user = 3-step) and acceptance is button-driven, never a zero-step
auto-accept. Enabling ORCID prepends a "Verify ORCID iD" step (Verify / Skip).
(ORCID was disabled again after the probe to restore the scratch context.)

---

## Blockers

None. All four items driven to completion on the apps required.

## Security routing

One finding routed to the private security file (`../e2e_ng/security.md`):
the item-4 `password123` weak-password acceptance during accept-invitation
account creation. Content kept out of this report per policy; the item-4
"password123" row above is generic by design.
