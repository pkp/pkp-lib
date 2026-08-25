# U6 — User invitations · probe report, **Group B — Send-wizard mechanics**

**Frame.** QA documentation of an application's own screens, on a local
disposable test install with seeded accounts. Signed in as each role and used
the screens the way that role would — including typing a URL directly to reach
one. Recorded what the screen offers, what happens when it is used, and where
the two disagree, so the product team can fix it. No request was constructed
that the screens themselves would not send: browser only, no hand-built API
calls, no altered payloads, no credentials carried between sessions.

**Scope**: probe-list items **B-6 … B-15** (10 of 10 executed). OJS only
(`http://127.0.0.1:8000` — the install's `allowed_hosts` accepts `127.0.0.1`,
not `localhost`; `localhost` answers `400 Server host not allowed`).

**Scratch fixtures created for this run** (scenario endpoints, `X-Test-Key:
playwright-test-key`):

| Journal | path | contextId | Notes |
|---|---|---|---|
| U6 Probe B Journal | `u6probeb` | 53 | all mutating work happened here |
| U6 Probe B Second Journal | `u6probeb2` | 61 | B-15 only |

Scratch users in `u6probeb` (password = username doubled): `u6b.mgr` (Journal
manager, the inviter, id 204) · `u6b.multi` (Author + Reviewer, 205) ·
`u6b.single` (Author, 206) · `u6b.dis` (Author, 207 — disabled during B-11) ·
`u6b.edit` (Author, 208 — disabled during B-13) · `u6b.held` (Author, 209).
`u6b2.mgr` (Journal manager, 222) in `u6probeb2`.
`publicknowledge` and the 18 seeded users were touched **read-only only** (one
search in B-8; no save, no send). Mailpit was queried scoped by recipient and
never cleared.

**Install facts framing this group**: ORCID is not configured; the scheduled
task runner is Off; the test DB is PostgreSQL.

**Marking**: **[claim]** = settles the item / promotable to an assertion.
**[context]** = incidental DOM or neighbouring-group territory, **not**
promotable.

Two role-name conventions matter below: the wizard's role dropdown labels are
the journal's own user-group names ("Journal manager", "Reviewer", …).

---

## B-6 — Invitations table anatomy (fn-n · Rule 3)

**Role**: Journal manager `u6b.mgr`.
**Screen**: `/index.php/u6probeb/management/settings/access` → Users tab
(default tab; the Invitations table sits above the Current Users table).
**Setup**: six invitations sent through the wizard (see B-7's method), then two
more during B-13; one cancelled at the end of this item.

### What appeared

**[claim] Heading** — `Invitations (6)`, the count in parentheses in the
heading itself. It tracked live: 6 → 7 after another send, 7 → 6 after a
cancel.

**[claim] Column headers** (locator: `table` #1 → `thead th`, verbatim
`textContent`): `Name`, `Email`, `Invitations`, `Status`, `Affiliation`, and a
sixth header whose only content is `<span class="sr-only">More Actions</span>`.
Rendered uppercase by CSS; the innerText reads `NAME EMAIL INVITATIONS STATUS
AFFILIATION / MORE ACTIONS`.

**[claim] Status cell wording** — every row read exactly `Invited 2026-07-28`
(the send date). Sampled 7 rows across both pages; no other status string
occurred.

**[claim] Name cell** — empty for an invitee with no account (Given/Family
Name left blank in the wizard); populated for an invitee who already has one
(`Hana Held`). Affiliation cell was empty on every row.

**[claim] Invitations cell** — carries the invited role name(s), e.g. `Author`,
`Reader`, `Reviewer`.

**[claim] Five rows per page with pagination.** Page 1 held exactly 5 rows,
page 2 the remaining 2. Pagination control: `nav.pkpPagination` with
`aria-label="View additional pages"`, visible text `Previous 1 2 Next`. Unlike
the Current Users table below it, the Invitations table shows **no**
"Showing 1 to N of M" summary line.

**[claim] Per-row menu.** Locator:
`button[aria-label="Invitation management options"]` (one per row; the button
has no visible text — icon only). Pressing it opens a `[role="menu"]` with
exactly **two** `[role="menuitem"]` entries, verbatim: `Edit` and
`Cancel Invite`.

**[claim] Pending-only.** Using the row menu → `Cancel Invite` on
`b9past21785253395@u6probeb.test` removed that row and dropped the heading from
`Invitations (7)` to `Invitations (6)`; the email no longer appeared anywhere in
the table.

**[claim] A second invitation to the same address does not add a second row.**
`b6.invitee4@u6probeb.test` was invited to `Author`, then invited again (fresh
wizard walk) to `Reader`. The count went 6 → 7 while **two** further
invitations had been sent (invitee4/Reader and u6b.held/Reviewer), and the table
listed invitee4 exactly once, showing the **newer** role (`Reader`); the
`Author` row was gone.

**[context]** `Cancel Invite`'s confirmation dialog read
`Cancel Invitation` / `Email: … · Role: Author, · Status: Invited 2026-07-28 ·
Affiliation:` / buttons `Cancel Invitation` and `Cancel`. **D-29 owns this
dialog** — recorded only because the cancel was needed for the pending-only
check. Note there was no warning sentence in it.

**Settles**: Rule 3's table anatomy — **confirmed** (headings, columns, status
wording, 5-per-page, two-entry row menu, pending-only). Adds one fact the draft
does not have: repeat invitations to one address collapse to a single row.

---

## B-7 — Wizard chrome, three steps (fn-o · Rule 4 · P7 send half · register A5)

**Role**: Journal manager `u6b.mgr`.
**Screen**: Users & Roles → `Invite to a role` button (locator
`getByRole('button', {name: 'Invite to a role'})`), which navigates to
`/index.php/u6probeb/invitation/create/userRoleAssignment`.

### What appeared

**[claim] Page heading** — `h1` = `Invite user to take a role`. Breadcrumb
above it: `Users & Roles / Invite user to take a role`. Intro paragraph,
verbatim: `You are inviting a user to take a role in OJS along with appearing
in the journal masthead`.

**[claim] Step list** — `ol.pkpSteps__buttons`, three `li.pkpSteps__step`:

1. `1 Search User`
2. `2 Enter details`
3. `3 Review & invite for roles`

**[claim] The step list's accessible name is a raw untranslated key.**
`ol.pkpSteps__buttons` carries
`aria-label="##invitation.wizard.completeSteps##"` — verbatim, including the
`##` sentinels. Present on every step of the wizard.

**[claim] Panel headings do not match the tab labels on step 3.** The three
panels announce themselves as:

| Step tab | Panel `h2` (verbatim) |
|---|---|
| `1 Search User` | `STEP 1 - Search User` |
| `2 Enter details` | `STEP 2 - Enter details and invite for roles` |
| `3 Review & invite for roles` | `STEP 3 - Modify email shared with the user` |

**[claim] Continue-button labels**, per step: step 1 `Search User`; step 2
`Save And Continue`; step 3 `Invite user to the role`.

**[claim] "Back" first appears on step 2** and stays on step 3. Step 1's button
row is `Cancel | Search User` only.

**[claim] "Cancel" is present on all three steps.**

**[claim] Step-tab clickability.** An unvisited step renders as
`<span class="pkpSteps__step__label">` — not focusable, not a button. A visited
step renders as `<button class="pkpSteps__step__label--current">` (or
`--completed` once passed, with a checkmark `<svg>` replacing the number).
Verified per step: on step 1 only tab 1 is a button; on step 2 tabs 1–2 are;
on step 3 all three are. Clicking the completed tab 1 from step 2 navigated
back (`h2` became `STEP 1 - Search User`, URL hash `#searchUser`).

**[claim] URL hashes** track the step: `#searchUser`, `#userDetails`,
`#userInvited`.

**[claim] Step 3 is an email composer, not a review screen.** Its body reads
`Send the user an email to let them know about the invitation, next steps,
journal GDPR polices and ORCID verification` (typo "polices" is on screen), and
it offers `Email Templates` / `Find Template`, a template card
`User Invited to Role Notification — Invitation to New Role Dear
{$recipientName}, In...`, a `To` field pre-filled with the invitee address, an
`Add CC/BCC` control, a `Subject` field pre-filled with
`You are invited to new roles` (locator `input#userInvited-subject`), and a
TinyMCE `Message` body (locator `#userInvited-body-control_ifr`) pre-filled
with the rendered template.

**[claim] Success dialog** after `Invite user to the role`: a `[role="dialog"]`
reading, verbatim —

> **Invitation Sent**
>
> `{email}` has been invited to new role in OJS. You can be updated about the
> user's decision on the Users & Roles page, your OJS notifications and/or your
> email
>
> `View All Users`

**[claim] "Cancel" on the send wizard** opens a `[role="dialog"]`:
`Cancel Invitation` / `Are you sure want to cancel this invitation?` (grammar
slip is on screen — "sure want to") / buttons `Cancel Invite` and `Go Back`.

**Settles**: Rule 4's chrome claims — **confirmed** for step names, button
labels, Back/Cancel placement and tab clickability; **corrects** any claim that
step 3 is a review step (it is the email composer, and the tab label
disagrees with the panel heading). Register **A5 (send half): confirmed** — a
raw key does surface, as the step list's accessible name
`##invitation.wizard.completeSteps##`.

---

## B-8 — Search step outcomes, email carry-over, ORCID (fn-e · Rule 4a)

**Role**: Journal manager `u6b.mgr` (plus one read-only control as
`manager.maya` in `publicknowledge`).
**Screen**: wizard step 1. Search field locator `input#-search-control`
(`name="search"`; note the id genuinely begins with a hyphen). Field label,
verbatim: `Search for a user by email address, username, or ORCID iD. Enter
only one to get started!`; placeholder `e.g. aeinstein@example.com or aeinstein
or 0000-0002-1825-0097`. Step description, verbatim: `Search for the user using
their email address, username or ORCID ID. Enter at least one details to get
started. If the user does not exist, you can invite them to take up roles and
be a part of your journal. If the user already exist in the system, you can
view user information and invite to take a additional roles.`

### (a) Continue with the field empty

**[claim]** Pressing `Search User` with an empty field stays on step 1 and
renders, directly under the `STEP 1 - Search User` heading, a red paragraph
`p.font-bold.text-negative` reading verbatim:

> `Provide at least one search criteria.`

No field-level error, no dialog, no navigation.

### (b) Email-shaped term matching nobody

**[claim]** Term `nobody.newcomer@u6probeb.test` → the wizard **advances to
step 2**. Two paragraphs at the top of the panel, verbatim:

> `The user does not have a role in this journal`
> `You can invite them to take up a role in OJS`

**[claim] Email carry-over is conditional on the term looking like an email.**
`input#userDetails-inviteeEmail-control` was pre-filled with
`nobody.newcomer@u6probeb.test`. Terms `author.alex` (a username),
`0000-0002-1825-0097` (ORCID-shaped) and `notanemail` all advanced to the same
step-2 newcomer branch with the email field **empty**.

**[claim] Newcomer identity fields are editable inputs**:
`#userDetails-inviteeEmail-control` (labelled `Email *`, `Required`),
`#userDetails-givenName-control-en` (`Given Name`, helper text `If you know the
given name of the user, you can enter the information. However, this
information can be changed by the user.`), `#userDetails-familyName-control-en`
(`Family Name`, same helper text with "family"). No Affiliation field, no
ORCID iD field.

### (c) Existing user's email

**[claim]** Term `u6b.multi@u6probeb.test` (holds Author + Reviewer in this
journal) → step 2, top paragraph verbatim:

> `The user already exists in the journal`
> `You can invite them to take up a role in OJS`

**[claim] Identity is read-only — rendered as static text, not disabled
inputs.** No `input` exists for email/given/family name in this branch (the
form-control inventory contains only the role-row selects and the date input).
The displayed labels are `Email`, `ORCID iD`, `Given Name`, `Family Name`,
`Affiliation` with the values beside them.

**[claim] Current roles are listed** in a table headed
`ROLE | START DATE | END DATE | JOURNAL MASTHEAD |` — for `u6b.multi`:
`Author | 2026-07-28 | --- | «masthead select» | Remove Role` and
`Reviewer | 2026-07-28 | --- | Appear on the masthead | Remove Role`.

**[claim] The "already exists" branch keys on journal membership, not on
having an account.** Searching `author.alex@example.org` — a seeded account
that exists site-wide but holds no role in the scratch journal — produced the
**newcomer** branch (`The user does not have a role in this journal`, editable
Email/Given/Family Name inputs, no ORCID/Affiliation display). The read-only
control run as `manager.maya` in `publicknowledge`, where `author.alex` does
hold a role, produced the **existing** branch for the same address
(`The user already exists in the journal`, values `Alex` / `Author` /
`Public Knowledge Project`, role row `Author | 2026-07-27 | --- | …`).
So the wizard offers to create identity details for a person who already has an
account, whenever that account is not enrolled in the journal being invited
into.

### (d) Username search

**[claim]** `u6b.multi` (username of an in-journal user) matched — same
`The user already exists in the journal` branch as the email search.
`author.alex` (username of a site-wide user with no role in this journal) did
not match; newcomer branch.

### ORCID (install fact)

**[claim] No ORCID iD field is offered on the details step for a newcomer.**
Page text contains no case-insensitive occurrence of "orcid" anywhere on the
newcomer details step. ORCID is **not configured on this install** (no
credentials; off by default) — recorded as an install fact, not an
impossibility.

**[claim] An `ORCID iD` display row IS shown for an existing user** — the label
appears in the read-only identity block (value empty for the scratch users,
empty for `author.alex` too) even though ORCID is not configured.

**[context]** The wizard's step-1 description and the search placeholder both
advertise ORCID iD as a search key; only the "no match" outcome was reachable
for an ORCID-shaped term on this install (no seeded user carries an ORCID iD).

**Settles**: Rule 4a's two search outcomes — **confirmed**, with the correction
that the discriminator is *role in this journal*, not existence in the system.
Empty-search refusal — **confirmed**, wording recorded. Email carry-over —
**confirmed but narrower** than a general "the search term is carried over":
only email-shaped terms carry. ORCID-field gating — **confirmed as an install
fact** for the newcomer branch; the existing-user branch shows the label
regardless.

---

## B-9 — Details-step validation and already-held roles (fn-h · Rule 5)

**Role**: Journal manager `u6b.mgr`. **Screen**: wizard step 2.

### (a) Save with no role chosen

**[claim]** With a valid email pre-filled and the role row untouched, pressing
`Save And Continue` stayed on step 2 and rendered a summary banner, verbatim:

> `3 errors detected! Please correct the errors below before proceeding.`

and three field-level messages, each verbatim `This field is required.`, under
`Select a new role *`, `Start Date *` and `Journal Masthead *`. (Each of those
three labels also carries the static word `Required` beneath it before any
submit.)

### (b) Today's date as Start Date

**[claim] Accepted — no refusal.** With role `Author`, Start Date `2026-07-28`
(today) and a masthead choice, `Save And Continue` advanced to step 3
(`#userInvited`).

### (c) A past date

**[claim] Accepted — no refusal, and the invitation sends.** Start Date
`2020-01-01` advanced to step 3, and pressing `Invite user to the role` produced
the `Invitation Sent` dialog. Repeated twice with two different addresses.
The `input#-dateStart-control` is `type="date"` with **empty `min` and `max`
attributes** — the browser imposes no bound either.

### Inviting a role the user already actively holds

**[claim] The screen never offers this case.** The `Select a new role` dropdown
(`select#-userGroupId-control`) **omits every role the user currently holds**:

- `u6b.held` (holds Author) → 17 options, `Author` absent.
- `u6b.multi` (held Author + Reviewer at the time) → 16 options, both absent.

**[claim] A role whose assignment has been end-dated returns to the dropdown.**
After Reviewer was removed from `u6b.multi` (B-12a), `Reviewer` reappeared in
that user's dropdown while `Author` stayed absent.

**[claim] Duplicate rows within one invitation are blocked by disabling the
option, not by a message.** Selecting `Author` in row 1 and pressing
`Add Another Role` yields a second `select#-userGroupId-control` whose `Author`
`<option>` carries `disabled` (`option.disabled === true`); Playwright's
`selectOption` fails with "option being selected is not enabled". No refusal
text is ever shown.

Because of both behaviours, **no refusal wording for "already actively holds
this role" is reachable through the screens.** This is the observation, not a
gap: the wizard prevents the case up front.

### Add / Remove role controls

**[claim]** `Add Another Role` (button) sits below the role table on both the
create and the edit route. With a **single** new-role row the row's action cell
is **empty** — no `Remove Role`. After `Add Another Role`, **both** rows gain a
`Remove Role` button. On the existing-user branch every *current* role row has
`Remove Role` from the start (see B-12).

**Settles**: the Fields table's *required* rules — **confirmed** (role, start
date and masthead are all required, with the exact messages above). The
*after-today* start-date rule — **corrected**: today and arbitrary past dates
are accepted at the step and at send, with no client-side bound. Rule 5's
already-held refusal — **corrected/undetermined-by-construction**: the refusal
is unreachable from the screens because held roles are omitted from the
dropdown and in-wizard duplicates are disabled options. The one observation
that would settle whether a refusal message exists at all would require sending
a request the screens do not offer — **out of scope**; noted here and not
investigated.

---

## B-10 — "Edit" from the users table (P14 · fn-p · Rule 4b)

**Role**: Journal manager `u6b.mgr`.
**Screen**: Users & Roles → **Current Users** table (the second table) → row
action menu → `Edit`. Target: `u6b.edit` (id 208).

### What appeared

**[claim] The users-table row action button's accessible name is a raw
untranslated key.** Locator: the first `button` in the row; its attribute is
`aria-label="##userAccess.management.options##"` — verbatim, with the `##`
sentinels. (Contrast the Invitations table's button, whose `aria-label` is the
translated `Invitation management options`.)

**[claim] The users-row menu has six entries**, verbatim: `Edit`, `Email`,
`Login As`, `Remove User`, `Disable User`, `Merge user`. (After disabling a
user, the fifth entry reads `Enable User`.)

**[claim] `Edit` navigates to a different route** —
`/index.php/u6probeb/management/settings/user/208` — not the
`/invitation/create/userRoleAssignment` route.

**[claim] The wizard there has TWO steps and no search step**:
`1 Enter details`, `2 Review & invite for roles`. Panel heading on arrival:
`STEP 1 - Enter details and invite for roles`. There is no `Back` button on
step 1.

**[claim] The primary Continue button is DISABLED on arrival.**
`getByRole('button', {name: 'Save And Continue'}).isDisabled() === true`.
Verified on a clean load of three different users.

**[claim] What first enables it.** Tested one action per fresh load:

| Action | `Save And Continue` after |
|---|---|
| nothing (baseline) | disabled |
| change an existing role's `Journal Masthead` selector | **still disabled** |
| press `View more details` | **still disabled** |
| press `Add Another Role` (row left completely empty) | **enabled** |

So the enabling trigger is the *presence of a new role row*, not any change to
the user's data — and it fires with the new row still blank.

**[claim] The page heading is missing on this route.** The `h1` element exists
but is empty (`allInnerTexts() === ['']`), and the intro paragraph
("You are inviting a user…") is absent. Only the breadcrumb
`Users & Roles / Invite user to take a role` names the page. On the
`/invitation/create/…` route the same `h1` reads `Invite user to take a role`.

**[claim] Extra control on this route**: a `View more details` button above the
role table; the identity block is the read-only existing-user block
(`Email`, `ORCID iD`, `Given Name`, `Family Name`, `Affiliation`).

**[claim] `Cancel` on this route does nothing.** One visible `Cancel` button
(bounding box present, enabled). Pressing it produced no dialog, no navigation
(URL unchanged after 5 s) and no visible change. Repeated on two users. The
same `Cancel` on the `/invitation/create/…` route *does* open the
`Cancel Invitation` dialog (B-7).

**Settles**: Rule 4b's *(unconfirmed)* disabled-until-changed claim —
**confirmed with a correction**: the button is disabled on arrival, but what
un-disables it is adding a role row, not "changing something"; editing the
existing rows leaves it disabled. Adds: the route's missing page heading and
the dead `Cancel`.

---

## B-11 — Wizard for a disabled user (fn-r · Rule 8)

**Role**: Journal manager `u6b.mgr`. **Target**: `u6b.dis` (id 207).

### Disabling

**[claim]** Users table row menu → `Disable User` opens a `[role="dialog"]`
reading, verbatim: `Disable Dana Disabled` / `Current Roles : Author` /
`Reason for disabling user` (a free-text field) / `Please note that once a user
is disabled, you won't be able to add them to any roles until the user is
enabled again.` / buttons `Cancel` and `OK`.

**[claim] The disabled state is signalled in the users table by an icon only.**
After confirming, the row still reads `Dana Disabled | u6b.dis@u6probeb.test |
Author | 2026-07-28` with no text marker; a red `svg` (span class contains
`text-negative`) is appended after the name, carrying no accessible text. The
row menu's fifth entry becomes `Enable User`.

### Opening the wizard for that user

**[claim] There is no "The user is currently disabled." banner.** Loading
`/index.php/u6probeb/management/settings/user/207` produced no banner and no
explanatory sentence anywhere on the page. The word "disabled" appears on
screen only as the fixture user's family name.

**[claim] What appears instead is an error dialog and an empty role table.**
The identity block renders normally (Email / ORCID iD / Given Name `Dana` /
Family Name `Disabled` / Affiliation), the role table renders `No Items`, and a
`[role="dialog"]` opens reading, verbatim:

> **Error**
>
> `The requested resource was not found.`
>
> `OK`

The underlying page request that failed is
`GET /index.php/u6probeb/api/v1/users/207` → **404** (observed in the browser's
own network activity; no request was constructed).

**Control**: the identical load for an **enabled** user (`206`) produced no
error dialog and listed the user's `Author` role. So the failure is specific to
the disabled user.

**[claim] The role-adding controls are present and enabled.**
`Add Another Role` is visible and not disabled; pressing it enables
`Save And Continue`; a role can be chosen and dated.

**[claim] Pressing `Save And Continue` for a disabled user advances the UI and
then errors.** The panel became `STEP 2 - Modify email shared with the user`
(the composer, fully populated with the invitee address) and a second
`[role="dialog"]` opened, verbatim:

> **Error**
>
> `The route u6probeb/api/v1/invitations/null/populate could not be found.`
>
> `OK`

The literal token `null` is on screen. The enabled-user control (`206`) took
the same path with no dialog.

**Settles**: Rule 8 — **corrected**. The draft's disabled-user banner and its
explanation do not exist on this install; the wizard opens for a disabled user,
offers the full role-adding UI, and fails with two generic error dialogs, one
of which prints an internal route with a `null` id. The dialog wording gives
the manager no indication that the user being disabled is the reason.

---

## B-12 — Removing and re-mastheading current roles (fn-s · Rule 9 · register A3)

**Role**: Journal manager `u6b.mgr`. **Target**: `u6b.multi` (id 205, held
Author + Reviewer), plus `u6b.held` (id 209, one role) for (c).

### (a) `Remove Role` on a current role

**[claim] Confirmation dialog**, verbatim:

> **Remove Role**
>
> `Are you sure you want to remove this role? The user will lose access and
> permissions associated with it.`
>
> `Remove Role`  `Cancel`

(Locator: the role row's `getByRole('button', {name: 'Remove Role'})`; the
dialog's confirm button carries the same label as the trigger.)

**[claim] Confirming applies immediately, before any invitation is sent.**
Confirming fired the page's own `POST …/api/v1/users/205/endRole/952` and the
row changed in place: END DATE filled with `2026-07-28` and the action cell
became the static text `User Removed From Role` (the button is gone).

**[claim] Cancelling the wizard without sending does NOT undo it.** Leaving the
wizard (navigating to Users & Roles in a second tab, nothing sent), the Current
Users row for `Milo Multi` read `Milo Multi | u6b.multi@u6probeb.test | Author
| 2026-07-28` — **Reviewer gone**.

### (b) Changing a current role's masthead choice

**[claim] Confirmation dialog**, verbatim:

> **Confirm masthead visibility change**
>
> `This will update whether this user appears on the journal masthead for the
> selected role. The user will be notified of this change.`
>
> `Confirm`  `Cancel`

(Locator: `select#-masthead-control` inside the current-role row; options
`Appear on the masthead` (value `true`) and `Does not appear on the masthead`
(value `false`). Selecting the value it already has raises no dialog.)

**[claim] Confirming applies immediately and persists.** The page's own
`POST …/api/v1/users/205/masthead/293` fired; the select read `true`
afterwards, and a **fresh page load in a new tab** still read `true` — with no
invitation sent and the wizard abandoned.

### (c) `Remove Role` when the user holds only one role

**[claim] Refusal dialog**, verbatim:

> **Remove Role**
>
> `You cannot remove the role. At least one role must be assigned to the user.`
>
> `Close`

Single button, `Close`. Nothing changes.

### Bearing on register A3

**[claim]** Both mutations above are irreversible from the wizard and take
effect regardless of whether the invitation is ever sent, yet nothing on the
screen says so. The role-removal dialog speaks only about the role
("The user will lose access…"); the masthead dialog says only that the user
will be notified. The wizard's surrounding text is entirely
invitation-framed — the step heading reads `Enter details and invite for
roles`, the intro `You can invite them to take up a role in OJS`, and pressing
`Cancel` on the send wizard asks `Are you sure want to cancel this
invitation?`. There is no sentence anywhere on the step warning that some
actions on it are applied at once rather than on send.

**Settles**: Rule 9 — **confirmed** (both confirmations exist, both apply
immediately, the one-role refusal exists), wording recorded verbatim. Register
**A3: confirmed** — the surrounding warning covers only the invitation; the
immediate-effect actions carry no such warning.

---

## B-13 — Where a refused send surfaces (P13 · register A6 · f-a6)

**Role**: Journal manager `u6b.mgr`. **Screen**: wizard final step, two tabs of
the same signed-in session.

### Attempt 1 — duplicate invitation (no refusal to observe)

**[claim] The server does not refuse a duplicate.** Composing a fresh
invitation for `b6.invitee4@u6probeb.test`, which already had a pending
invitation, produced no warning at any step and ended in the normal
`Invitation Sent` dialog. (Its table consequence is under B-6.)

### Attempt 2 — user disabled in a second tab while composing

Tab A: `/management/settings/user/208` → `Add Another Role` (Reviewer,
2027-06-01) → `Save And Continue` → step 2 (`STEP 2 - Modify email shared with
the user`). Tab B: Users & Roles → row `u6b.edit@u6probeb.test` → row menu →
`Disable User` → `OK` (menu then showed `Enable User`, confirming the change
landed). Tab A: pressed `Invite user to the role`.

**[claim] The refusal surfaced as a modal error dialog** — verbatim:

> **Error**
>
> `The route u6probeb/api/v1/invitations/null/invite could not be found.`
>
> `OK`

**[claim] No warning banner appeared above the buttons and no inline notes
appeared on the step.** The step-3 button row stayed `Cancel | Back | Invite
user to the role`, and a sweep of `[class*=negative]`, `[class*=warning]` and
`[role=alert]` on the page returned only the `Cancel` button's own styling and
the error dialog itself. The composer content was untouched.

**[claim] The final button re-runs the whole create sequence.** The page's own
requests on pressing `Invite user to the role` were
`POST …/invitations/add/userRoleAssignment` → `POST …/invitations/{id}/populate`
→ `POST …/invitations/{id}/invite`, i.e. a **new** invitation record, not the
one created when `Save And Continue` was pressed. On a normal (non-refused)
walk, id 298 was created at `Save And Continue` and id 299 at send. In the
refused case the first of those three calls failed (422) and the two that
followed carried the literal `null` in the path, which is what the dialog
prints.

**[claim] An invitation being composed is not listed in the Invitations
table.** After `Save And Continue` created invitation 297 for a fresh address,
the Invitations table still read `Invitations (6)` and did not contain that
address — so "cancel the composing invitation from the table in a second tab"
is not a route the screens offer.

**Settles**: register **A6: confirmed** — across every refusal reachable from
the screens, the final-step *warning banner* never appeared; refusals arrive as
a generic modal `Error` dialog whose text is an internal route string. Worth
the digest's attention: the error message names no user, no role and no reason,
and prints `null`.

---

## B-14 — Stray `<?php` template line (P8 · fn-c)

**Role**: Journal manager `u6b.mgr` for the wizard; **signed-out** browser
(fresh context, no storage state) for the accept link.

**[claim] Send wizard — no `<?php` anywhere.** Checked on all three steps
(`#searchUser`, `#userDetails`, `#userInvited`), for both the rendered text
(`body.innerText`) and the served HTML (`page.content()`, both `<?php` and
`&lt;?php`): all false. Also checked the TinyMCE message body's own HTML
(`#userInvited-body-control_ifr` → `body.innerHTML`): false.

**[claim] Emailed invitation — no `<?php`.** Mailpit message
`ZFDzhMLbbtwDBwphK5tXfJ`, scoped by recipient
`to:b6.invitee5@u6probeb.test` (subject `You are invited to new roles`): the
HTML part contains neither `<?php` nor `&lt;?php`.

**[claim] "Accept Invitation" page — no `<?php`.** Opened
`http://127.0.0.1:8000/index.php/u6probeb/invitation/accept?id=281&key=s49c55`
in a signed-out browser: body text and served HTML both free of the literal.

**Settles**: the stray template line **does not render** on any of these
surfaces — the claim is settled negative.

**[context]** While there, the accept page's step list carries the same
`aria-label="##invitation.wizard.completeSteps##"`, and the page also serves
`##userInvitation.accountDetails.stepDescription##`. Its steps read `1 Create
OJS account`, `2 Enter details`, `3 Review & create account`; step 1 offers
`Username *`, `Password *` (helper `It should be at least 6 characters long and
could be a combination of uppercase letters, lowercase letters, numbers and
symbols`) and `Privacy Consent *` with `Yes, I agree to have my data collected
and stored according to the Privacy Statement`, buttons `Cancel` and `Save and
continue`. **Group D owns this flow** — recorded only because the page had to
be loaded for this item; nothing here was completed.

---

## B-15 — Does the role dropdown ever list another journal's role? (P11 · fn-g)

**Setup**: a second scratch journal `u6probeb2` (contextId 61) was created
with its own manager `u6b2.mgr`, so two scratch journals plus `publicknowledge`
existed on the install at read time.

**[claim] Each journal's wizard lists exactly its own 18 role groups, and the
two sets are disjoint.** Locator: `select#-userGroupId-control` on the details
step, read as `(option.value, option.textContent)` pairs.

`u6probeb` (18 options, ids 938–955):
Journal manager (938), Journal editor (939), Production editor (940), Section
editor (941), Guest editor (942), Copyeditor (943), Designer (944), Funding
coordinator (945), Indexer (946), Layout Editor (947), Marketing and sales
coordinator (948), Proofreader (949), Author (950), Translator (951), Reviewer
(952), Reader (953), Subscription Manager (954), Editorial Board Member (955).

`u6probeb2` (18 options, ids 1082–1099): the same 18 labels, ids 1082…1099.

No option from the other journal's id range appeared in either list, no label
was duplicated, and the count did not grow when the second journal was added
(`u6probeb`'s list was 18 both before and after `u6probeb2` existed).

**[claim] The dropdown is filtered per user, not just per journal**: see B-9 —
roles the invitee already holds in *this* journal are omitted.

**Settles**: the draft's *(unconfirmed)* own-roles claim — **confirmed**. The
dropdown lists only the current journal's own user groups.

**Note on method**: renaming one of the second journal's roles (to make the
label itself distinctive) was attempted through Users & Roles → Roles, but the
Roles grid's per-row `Edit` link is inside a hover-revealed `show_extras`
control that never became visible in this run; the Roles screen belongs to
another group's territory, so the attempt was abandoned rather than pursued.
The disjoint id ranges settle the item on their own.

---

## Untranslated keys observed on this feature's screens (cross-item)

Collected from the served HTML of the screens this group drove; each is
verbatim including the `##` sentinels.

| Key | Where it surfaces | Visible to a sighted user? | Mark |
|---|---|---|---|
| `##invitation.wizard.completeSteps##` | `aria-label` of `ol.pkpSteps__buttons` on **both** the send wizard (all steps, both routes) and the accept flow | No — screen-reader only | **[claim]** |
| `##userAccess.management.options##` | `aria-label` of the row action button in the **Current Users** table | No — screen-reader only | **[claim]** |
| `##common.help##` | site header Help link, every backend page | **Yes** | **[context]** — not this feature's screen |
| `##default.groups.name.editorialBoardMember##`, `##default.groups.abbrev.editorialBoardMember##` | inside the `pkp.registry.init('app', …)` JSON payload in a `<script>` block | No — never rendered; the visible dropdown option reads `Editorial Board Member` | **[context]** |
| `##userInvitation.accountDetails.stepDescription##` | the acceptance flow's step 1 | seen in HTML; Group D owns the flow | **[context]** |

---

## Incidental — noticed while working, outside Group B

One line each, per the brief; **not** promotable, and owned by other groups.

- **C-20 (reviewer masthead)**: on both routes, a `Reviewer` role row's
  `JOURNAL MASTHEAD` cell renders as the fixed text `Appear on the masthead`
  with **no** `select#-masthead-control`, while every non-reviewer row renders
  the two-option selector.
- **D-29 (Cancel Invite)**: dialog wording recorded under B-6 above; it lists
  Email / Role / Status / Affiliation and contains **no** warning sentence.
- **D-24 / A5 (accept half)**: the accept flow serves
  `##userInvitation.accountDetails.stepDescription##`; step names recorded
  under B-14.
- **Users table**: `Remove User`'s dialog reads `Remove` / `Remove this user
  from this journal? This action will unenroll the user from all roles within
  this journal.` / `OK` `Cancel`.
- **Environment**: the install's `allowed_hosts` accepts `127.0.0.1` only —
  browsing via `localhost:8000` returns `400 Server host not allowed`.

---

## Proposed content for files this report may not write

- **PROGRESS.md** — none proposed by this probe.
- **atlas files** — none proposed by this probe.
- **docs/e2e/app-changes.md** — none proposed. Nothing here blocked a test run
  or required an app-code workaround; every item above is a product finding for
  the spec's Findings register, routed through the digest.

---

## Coverage

| Item | Status |
|---|---|
| B-6 | settled |
| B-7 | settled |
| B-8 | settled |
| B-9 | settled — with one sub-question declared out of scope (see below) |
| B-10 | settled |
| B-11 | settled |
| B-12 | settled (a, b, c) |
| B-13 | settled |
| B-14 | settled (negative) |
| B-15 | settled |

**10 of 10 items covered. 0 undetermined.**

One sub-question inside B-9 is **out of scope rather than undetermined**:
whether a server-side refusal message exists for inviting a role the user
already actively holds. The screens never offer that case (held roles are
omitted from the dropdown; in-wizard duplicates are disabled options), so
answering it would require constructing a request the screens do not send. It
was not investigated and nothing is concluded about it.

**Blockers**: none.
