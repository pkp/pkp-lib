# U6 — claim check, chunk 2

**Target:** `docs/product/specs/user-invitations.md` — the whole **Fields & validation**
section, and **Rules & state** from the Lifecycle block through **Rule 8**.
**App:** OJS (`http://127.0.0.1:8000`). No claim in this chunk is marked as
differing per app, so no cross-app run was made; verdicts below are OJS-observed.
**Date:** 2026-07-28. **Driven through the screens only** (browser, signed in as
the stated role; scratch journals and throwaway users created through the
scenario endpoints; Mailpit read scoped by recipient, never cleared).

Scratch fixtures used (all `u6cc2*`): journals `u6cc2a`…`u6cc2f`, managers
`u6cc2mgr`, `u6cc2mgrb`…`u6cc2mgrf`, throwaway invitees/users `u6cc2holder`,
`u6cc2dis`, `u6cc2two`, `u6cc2reg1`, and invitation addresses `u6cc2*@example.org`.
Seeded roster and `publicknowledge` were read-only (only `reader.rosa@example.org`
was typed into a search box).

**Verdict tally — 59 claims checked: 52 holds · 3 wrong · 4 undetermined ·
0 unreachable**, plus one observed defect that is not a spec sentence (C37).
Every surface the chunk describes was reachable. Nothing blocked the run.

---

## Fields & validation — send-invitation wizard

Screen: Users & Roles → Users → "Invite to a role"
(`/index.php/{journal}/invitation/create/userRoleAssignment`), as Journal Manager
(`u6cc2mgr` in `u6cc2a`).

### C1 — search field is required and empty search is refused · **holds**
> "Empty search is refused with 'Provide at least one search criteria.'"

Pressed **Search User** (`getByRole('button', {name: 'Search User', exact: true})`)
with the search box (`input#-search-control`) empty. The step stayed on
"STEP 1 - Search User" and printed, above the field description, exactly
`Provide at least one search criteria.`

### C2 — only an email-shaped term is carried into the invitee's email · **holds**
> "When the search finds no role-holder in this journal (Rule 4a), only a term
> shaped like an email address is carried into the invitee's email field."

Searched `zzznobodyzzz` → newcomer branch, `input#userDetails-inviteeEmail-control`
value empty. Searched `u6cc2fresh1@example.org` → newcomer branch, same control
pre-filled with `u6cc2fresh1@example.org`.

### C3 — Email is required and pre-filled from the search · **holds**
> "Email address (new invitee, details step) | Yes | Pre-filled from the search
> when the search term was an email."

`input#userDetails-inviteeEmail-control` carries `required`, is labelled `Email *`
with a "Required" hint, and pre-fills as in C2.

### C4 — "At acceptance time the address must not already belong to an account" · **holds (in effect), but the screen says nothing** 
The constraint is real; the refusal is invisible.

Two cases, both driven end to end:

- *Account existed before the invitation was sent.* In `u6cc2e`, searched
  `u6cc2mgrb@example.org` (an account with no role in that journal) → newcomer
  branch → sent. Opening the emailed accept link gave the one-step
  "STEP 1 - Review & create account" page, and **"Accept And Continue to OJS"
  succeeded**: the success dialog "You've been assigned a new role in OJS"
  appeared, the row left the Invitations table, and the user gained the role.
  So the wizard happily invites an existing account through the newcomer form
  and acceptance completes.
- *Account created after the invitation was sent.* In `u6cc2d`, sent an
  invitation to `u6cc2reg1@example.org` (no account), then registered an account
  with that address on the journal's own registration form, then opened the
  accept link. The page again shows "STEP 1 - Review & create account" — and
  pressing **"Accept And Continue to OJS"** (`getByRole('button', {name:
  'Accept And Continue to OJS', exact: true})`) does **nothing at all**: no
  success dialog, no error dialog, no message, no navigation. The invitation
  stays pending, the row stays in the manager's table and the link stays live.

What a QA reader should be told: the address-already-taken rule surfaces as a
dead button, not as a refusal. (This also contradicts Rule 14's "a review-step
failure shows a modal error instead" — no modal appeared. Rule 14 belongs to
another chunk; noted here only because it is the same click.)

### C5 — Given / Family name are optional · **holds**
`input[name=givenName-en]` / `input[name=familyName-en]` carry no `required`,
their labels show no asterisk, and a send with both blank completed.

### C6 — name fields are offered in each of the journal's form languages · **holds**
> "Offered in each of the journal's form languages."

In `u6cc2c` (supported locales en + fr_CA) the wizard first offered only
`givenName-en` / `familyName-en`. On Settings → Website → Setup → Languages I
ticked the **Forms** box for French (`input[id*="fr_CA-formLocale"]`); the wizard
then offered `givenName-en`, `givenName-fr_CA`, `familyName-en`,
`familyName-fr_CA`, with a "French English" language switcher and the counter
"0/2 languages completed" under each field. The Email field stays single-language.

### C7 — ORCID iD field shown only while ORCID is enabled · **undetermined (positive half)**
> "ORCID iD (new invitee) | No | Shown only while ORCID is enabled on the journal."

With ORCID off, no ORCID field appears on the details step — that half holds
(`u6cc2a`, `u6cc2e`). I could not observe the enabled case: on Users & Roles →
**ORCID** tab, ticking "Enable ORCID functionality"
(`input[name=orcidEnabled]`) and pressing **Save** reveals the API fields in the
same session, but on a fresh load of the tab the checkbox is unticked again and
the API fields are gone — the setting does not stick. With the checkbox ticked
in-session the invite wizard still offered no ORCID field. The ORCID screen is
another feature's territory; recording it only as the reason this half is
undetermined.

### C8 — role select is required; options are this journal's roles minus held ones · **holds**
> "Options are the journal's own roles minus any the invitee already holds
> (Rule 5)".

`select[name=userGroupId]` carries `required`. For a newcomer in `u6cc2a` it
offered exactly the journal's 18 default roles, once each (option values
2198–2215 — the journal's own groups; no duplicate set from any other journal).
For `u6cc2holder` (Author) the list was the same minus "Author"; for `u6cc2two`
(Author + Copyeditor) it was the same minus both.

### C9 — a role picked on another row is greyed out · **holds**
Picked "Copyeditor" in row 1, pressed **Add Another Role**; in row 2's select the
"Copyeditor" option carries `disabled`, and no message explains why.

### C10 — Start Date is required · **holds**
`input[name=dateStart]` (type `date`) carries `required`; leaving it blank fails
the row with "This field is required."

### C11 — today and long-past dates are accepted; nothing bounds the field · **holds**
Re-confirmed, not relitigated: `input[name=dateStart]` has no `min` and no `max`
attribute. Entered `2020-01-15` and `2026-07-28` (today); both passed
"Save And Continue" and the invitation sent.

### C12 — Journal Masthead is required and offers those two wordings · **holds**
`select[name=masthead]` carries `required` and its two options read exactly
`Appear on the masthead` and `Does not appear on the masthead`.

### C13 — "the untouched select already displays 'Appear on the masthead' while holding no value, so a row that looks complete still fails" · **WRONG**
> "Beware: the untouched select already displays 'Appear on the masthead' while
> holding no value, so a row that looks complete still fails 'Save And Continue'
> with 'This field is required.' until the choice is made deliberately ⚠ [A16]."

On a fresh "Enter details" step (`u6cc2a`, newcomer branch) the untouched
`select[name=masthead]` has `value=""` and `selectedIndex = -1`, and it renders
**blank** — a screenshot of the row shows an empty role select, the browser's
`dd.mm.yyyy` date placeholder and an empty masthead select. The row does not look
complete; it looks empty, exactly like the two neighbouring untouched controls.
(By contrast, a row that *does* carry a masthead value — e.g. the preloaded
edit-invitation wizard — displays that value, so the control shows what it holds.)

What actually happens: leaving the masthead untouched fails the row, and the
refusal wording is right — after **Save And Continue** the step stayed put,
showed "1 error detected! Please correct the error below before proceeding."
at the top and " This field is required." under the masthead select. The
"looks filled" premise is what does not reproduce.

### C14 — the untouched masthead fails with "This field is required." · **holds**
See C13's second paragraph for the observed wording (plus the error-count banner,
which the spec does not mention).

### C15 — Reviewer rows show fixed "Appear on the masthead" instead of a choice · **holds**
Added a second row and picked "Reviewer": that row's masthead cell became the
static text `Appear on the masthead` and the number of
`select[name=masthead]` controls on the step dropped from 2 to 1.

---

## Fields & validation — acceptance flow

Screen: the emailed accept link, signed out
(`/index.php/{journal}/invitation/accept?id=…&key=…`), newcomer invitations
seeded in `u6cc2a` / `u6cc2d`.

### C16 — Username required; a taken username is refused on the account step · **holds**
> "Must not already be taken — a taken username is refused on the account step itself."

`input#-username-control` carries `required`. Entered `admin` with a valid
password and Privacy Consent ticked, pressed **Save and continue**: the step
stayed on "STEP 1 - Create OJS account" and printed under the field
`There is already an existing user with the username admin. Try another one.`

### C17 — Password: site minimum stated under the field; too-short refused on the step · **holds**
The description under `input#-password-control` reads "It should be at least 6
characters long and could be a combination of uppercase letters, lowercase
letters, numbers and symbols". Entering `abc` and continuing kept the step and
printed `The password must be at least 6 characters.` under the field.
(The breach-list clause was not re-tested — it is install-dependent and already
recorded.)

### C18 — Privacy Consent required, label links the privacy statement, exact refusal · **holds**
`input[name=privacyStatement]`; the label reads "Yes, I agree to have my data
collected and stored according to the Privacy Statement" and "Privacy Statement"
links to `…/u6cc2a/about/privacy`. Continuing with it unticked printed
`Please confirm that you have read and agree privacy statement`.
Worth knowing for a QA reader: with **everything** blank, that is the *only*
message shown — the blank username and password draw no refusal at all until the
box is ticked.

### C19 — Given Name is required · **holds**
`input#acceptUserDetails-givenName-control-en` carries `required`; cleared it and
continued — the step stayed and showed " This field is required." under it.

### C20 — Family Name is not required · **holds**
`input#acceptUserDetails-familyName-control-en` has no `required`; left blank, the
step advanced and the review step showed "Family Name" empty.

### C21 — Affiliation is not required · **holds**
`input#acceptUserDetails-affiliation-control-en` has no `required`; left blank,
the step advanced.

### C22 — Country: required, a dropdown · **holds**
`select#acceptUserDetails-userCountry-control`, 249 options, carries `required`.
On a fresh invitation (`u6cc2d` #452) it starts unselected; continuing without
choosing kept the step and showed the pink " This field is required." callout
under the select plus a footer line "Please correct one error. Jump to next error".

### C23 — the field's label is "Country" · **WRONG**
The spec's Fields table names this field **Country**. On screen the label reads
exactly `Country of affiliation`, with the description "This is a country in
which the institute you are affiliated with is situated"; the review step lists
it as "Country of affiliation" too.

---

## Rules & state — Lifecycle (Rules 1–3)

### C24 — an invitation being composed is not yet listed anywhere · **holds**
Composed an invitation in `u6cc2f` as far as the email composer (step 3) without
sending; in a second session the manager's Users & Roles screen showed
"Invitations (0)" and "No Items".

### C25 — it becomes pending the moment "Invite user to the role" is pressed, and that is when the email goes out · **holds**
Pressing the button produced the "Invitation Sent" dialog
("u6cc2sent1@example.org has been invited to new role in OJS…", button
"View All Users"), the row appeared in the Invitations table, and a message
"You are invited to new roles" was in Mailpit for that recipient.

### C26 — pending is the only state the table shows · **holds**
An `expired` invitation seeded in `u6cc2a` never appears in the table or its
count; accepted, declined and cancelled invitations leave it (C27).

### C27 — accepting, declining or cancelling removes the row; each is final · **holds**
In `u6cc2d`: accepting (full newcomer flow) took the count 1 → 0 and put the new
user in the users table; declining took 3 → 2; cancelling took 2 → 1. Re-opening
each of those accept links afterwards gave "Invitation Unavailable".

### C28 — validity is 3 days by default · **undetermined**
No screen in the flow states the validity period, so this cannot be checked
through the screens. (The expiry *behaviour* is C29.)

### C29 — an expired invitation drops out of the table and its link stops working · **holds**
The seeded expired invitation (`u6cc2a`) is absent from the table; both its accept
and its decline link land on "Invitation Unavailable" — "This invitation is no
longer available. It may have already been accepted, declined, or expired.
Please contact the journal manager for further assistance." with Login and
Register buttons.

### C30 — a nightly background cleanup deletes expired invitations · **undetermined**
Not observable on this install through any screen (the scheduler does not run).

### C31 — table heading "Invitations" with a count · **holds**
Heading rendered `Invitations (1)`, `Invitations (7)`, `Invitations (0)` matching
the rows present.

### C32 — the table's columns · **holds**
> "Columns: 'Name', 'Email', 'Invitations', 'Status', 'Affiliation', and a
> per-row 'Invitation management options' menu."

Header cells render (in capitals) `NAME`, `EMAIL`, `INVITATIONS`, `STATUS`,
`AFFILIATION`, `MORE ACTIONS`; the per-row menu button's accessible name is
`Invitation management options` and it opens "Edit" / "Cancel Invite". The spec
does not mention the sixth header's own wording ("More Actions") — minor, noted
for the merge agent, not a disagreement.

### C33 — the status cell always reads "Invited {date}" · **holds**
Every row observed, across four journals, read `Invited 2026-07-28`.

### C34 — five rows per page, with pagination below · **holds**
In `u6cc2b` (7 pending invitations) the table showed 5 rows and a
"Previous 1 2 Next" control below it; page 2 held the remaining 2.

### C35 — one row per invitee; re-inviting replaces the row with the newest roles · **holds**
Invited `u6cc2sent1@example.org` as Section editor, then again as Proofreader:
the count stayed at 2 and that row's role cell changed from "Section editor" to
"Proofreader". (The Name cell also emptied, because the second invitation carried
no typed name.)

### C36 — an ORCID icon accompanies the name when the invitee has one · **undetermined**
No invitee with an ORCID iD could be created through the screens: the wizard
offers no ORCID field while ORCID is off, and the ORCID setting could not be
turned on (C7).

### C37 — the table's Name and Email cells · **observed defect, attached to Rule 3**
Not a spec sentence, but it falsifies the columns a reader would expect. In
`u6cc2d`, after the C4 second case (an invitation whose newcomer address acquired
an account before acceptance) both remaining rows render **without their Name and
Email cells at all**, so every later value slides one column left: the role
("Reader") appears under **NAME**, the status ("Invited 2026-07-28") under
**EMAIL**, the row menu under **INVITATIONS**, and STATUS and AFFILIATION are
blank. The same rows' "Cancel Invitation" dialog shows `Email:` with nothing after
it. Rows in journals without such an invitation (`u6cc2b`, `u6cc2e`) render all
six cells normally, so this is state-dependent, not universal.

---

## Rules & state — Sending (Rules 4–8)

### C38 — wizard heading and the three step names · **holds**
`h1` reads `Invite user to take a role` over "You are inviting a user to take a
role in OJS along with appearing in the journal masthead". The step list reads
"1 Search User", "2 Enter details", "3 Review & invite for roles".

### C39 — the third step's tab says one thing and its panel another · **holds**
Tab `Review & invite for roles`; panel heading `STEP 3 - Modify email shared with
the user`, with the description "Send the user an email to let them know about
the invitation, next steps, journal GDPR polices and ORCID verification".

### C40 — in the two-step edit modes the same composer is headed "STEP 2 - Modify email shared with the user" · **holds**
Edited a pending invitation in `u6cc2f` (`/invitation/edit/480`): steps are
"1 Enter details", "2 Review & invite for roles" and the composer's panel heading
is `STEP 2 - Modify email shared with the user`.

### C41 — the composer offers a template picker, recipient fields and a pre-filled subject and body · **holds**
"Email Templates" with a "Find Template" search and the entry "User Invited to
Role Notification"; a "To" field carrying the invitee's address and "Add CC/BCC";
`input#userInvited-subject` pre-filled `You are invited to new roles`; and the
message body pre-filled with the template text ("Invitation to New Role / Dear
{$RECIPIENTNAME}, …" — the placeholders resolve in the delivered mail).

### C42 — the second step's tab and panel heading disagree the same way · **holds**
Tab `Enter details`; panel heading `STEP 2 - Enter details and invite for roles`.

### C43 — each step's Continue button carries its own label · **holds**
`Search User`, `Save And Continue`, `Invite user to the role` — in that order.

### C44 — "Back" appears from the second step on; "Cancel" always · **holds**
Step 1 footer: Cancel, Search User. Step 2: Cancel, Back, Save And Continue.
Step 3: Cancel, Back, Invite user to the role.

### C45 — visited step tabs can be clicked, later ones cannot · **holds**
On step 1 the step labels are `<button>` for step 1 and `<span>` for steps 2 and
3 (`.pkpSteps__step__label`); on step 3 all three are buttons.

### C46 — the search's discriminator and the two outcome sentences · **holds**
> "A match with a role here answers 'The user already exists in the journal' …
> Anyone else — including someone with an account elsewhere on the site — gets
> 'The user does not have a role in this journal' and the blank newcomer form".

In `u6cc2a`: `u6cc2holder@example.org` and the username `u6cc2holder` (Author in
that journal) → `The user already exists in the journal`, with Email, ORCID iD,
Given Name, Family Name and Affiliation shown as read-only text and the current
role listed in the table. `reader.rosa@example.org` (an account on the site with
no role in that journal) and `u6cc2mgrb@example.org` (a manager of another
journal) → `The user does not have a role in this journal` and the blank
newcomer form, offering to create identity details for an account that exists.

### C47 — the edit modes omit the search step and open on "Enter details" · **holds**
Both entries confirmed: the pending row's "Edit" (`/invitation/edit/{id}`) and
the users-table "Edit" (`/management/settings/user/{userId}`) open a two-step
wizard headed "STEP 1 - Enter details and invite for roles".

### C48 — from the users table: Continue starts disabled, "Add Another Role" enables it, editing existing rows does not; no heading; Cancel does nothing · **holds**
On `/management/settings/user/364` (`u6cc2f`): **Save And Continue** was disabled
on open; changing an existing row's `select[name=masthead]` left it disabled;
pressing **Add Another Role** enabled it while the new row was still blank. The
page's `h1` is empty (only the breadcrumb "Users & Roles / Invite user to take a
role" names it), and pressing **Cancel** produced no dialog and no navigation
(URL unchanged, zero dialogs).

### C49 — the role table holds "role, 'Start Date', 'Journal Masthead'" per row · **WRONG (incomplete)**
The role table has **four** value columns, not three. Header cells read
`ROLE`, `START DATE`, `END DATE`, `JOURNAL MASTHEAD` (plus the action cell).
"End Date" is display-only: it shows `---` for a new row and for a current role,
and shows a date once a role has been ended (C54). The Fields & validation table
omits it too.

### C50 — "Add Another Role" adds a further row, "Remove Role" beside each added row · **holds, with a wrinkle**
Pressing **Add Another Role** added a second complete row and both rows then
carried a "Remove Role" button. The wrinkle: the *initial* row starts with no
"Remove Role", but gains one as soon as a "Save And Continue" fails validation —
so "beside each added row" is not quite how it behaves.

### C51 — validation refuses "Save And Continue" while any row is blank · **holds**
With row 1 complete and row 2 blank, the step stayed put and reported
"3 errors detected! Please correct the errors below before proceeding." with
" This field is required." under row 2's role, start date and masthead.

### C52 — every row reuses the first row's control identifiers · **holds**
Already filed; confirmed in passing — with two rows present, both role selects
answer to `select[name=userGroupId]` and both masthead selects to
`select[name=masthead]`, with `id="-userGroupId-control"` repeated.

### C53 — the role list offers this journal's own roles only · **holds**
See C8: in each scratch journal the list held exactly that journal's 18 default
roles, once each, with that journal's own option values; a second journal on the
same site added nothing (no duplicated role names).

### C54 — an end-dated role returns to the list · **holds**
In `u6cc2f`, `u6cc2two` held Author + Copyeditor and the list omitted both. Used
"Remove Role" on the Author row and confirmed in the "Remove Role" dialog ("Are
you sure you want to remove this role? The user will lose access and permissions
associated with it."). The Author row then showed an End Date of 2026-07-28 and
**"Author" was back in the "Select a new role" list** — still there after
reloading the wizard from scratch.

### C55 — a role already added on another row is greyed with no message · **holds**
See C9.

### C56 — the "Edit" warning wording, and confirming reopens the wizard preloaded · **holds**
The dialog is headed "Edit Invitation" and reads exactly: "If you edit the
existing invitation or add a new role, the current invitation will be canceled
and, a new one will be sent. Are you sure you want to proceed?" with buttons
"Edit Invitation" and "Cancel". Confirming opened `/invitation/edit/{id}` with the
email, the role ("Proofreader"), the start date (28.07.2026) and the masthead
choice ("Appear on the masthead") all preloaded.

### C57 — the reopened page is headed with the invitee's own name, over "You are viewing {name}'s user details" · **holds, with a nuance**
Observed heading `u6cc2sent1@example.org` over "You are viewing
u6cc2sent1@example.org's user details" — i.e. for an invitee with no typed name
(a newcomer) the heading is the **email address**, not a name. The shape of the
claim holds; "the invitee's own name" is not always what shows.

### C58 — the superseded email's links land on a bare not-found page · **holds**
In `u6cc2f`: sent an invitation, kept the first email's accept link, then used
Edit → changed the role → sent again. Opening the first link now renders a page
whose entire content is `404 Not Found` — no journal name, no explanation, no
navigation — not "Invitation Unavailable".

### C59 — the "Cancel Invite" dialog lists email, roles, status and affiliation and nothing more · **holds**
The dialog is headed "Cancel Invitation" and its body is exactly four lines:
`Email: u6cc2sent1@example.org`, `Role: Proofreader,`, `Status: Invited
2026-07-28`, `Affiliation:` — no sentence about the emailed link. Buttons are
"Cancel Invitation" and "Cancel"; confirming removed the row, and the accept link
then gave "Invitation Unavailable". (Field labels are singular — "Role:" — where
the spec says "roles".)

### C60 — nothing tells an inviter that a user is disabled; the wizard opens and fails twice · **holds**
Disabled `u6cc2dis` from the users table ("Disable User" → "Disable Dina Disabled"
dialog → OK), then opened `/management/settings/user/329`. The wizard page opened
with an **"Error — The requested resource was not found."** dialog over a role
table reading **"No Items"**, and nothing on the page says the user is disabled.
Dismissing the dialog leaves **"Add Another Role" enabled** (`isDisabled()` false);
pressing it adds a fully working role row with the complete role list. Continuing
to the email composer raised a second dialog: **"The route
u6cc2a/api/v1/invitations/null/populate could not be found."**

---

## Incidental (outside this chunk, recorded once so it is not lost)

- The users table's per-row menu button carries the untranslated accessible name
  `##userAccess.management.options##` (the Invitations table's equivalent button
  is fine). Users management's screen, not this spec's.
- The acceptance flow's step list renders the literal text `{$current}/{$total}
  steps` next to "1/1 steps" on the existing-user review page.
