# U6 — User invitations · probe report, **Group D** (Acceptance flow & lifecycle)

**Frame.** QA documentation of an application's own screens, on a local
disposable test install with seeded accounts. Sign in as each role and use the
screens the way that role would — including typing a URL directly to reach one.
Record what the screen offers, what happens when it is used, and where the two
disagree, so the product team can fix it. Never construct a request the screens
themselves would not send; a question that needs that goes on the deferred
queue instead.

**Agent**: Opus probe agent, RUNBOOK step 3. **Date**: 2026-07-28.
**Items**: D-23 … D-33 (11 of 11 executed; 0 blocked; 1 sub-claim left
undetermined — see D-26).
**App**: OJS only, fleet at `http://127.0.0.1:8000` (the fleet rejects the
`localhost` host name — `400 Server host not allowed`; `allowed_hosts` in
`config.test.inc.php` lists `127.0.0.1` only. Recorded as an env fact for
later probes, not a product finding).

## Scratch context (mine; nothing else was touched)

Created via `POST /api/v1/_test/scenarios/context` with
`X-Test-Key: playwright-test-key`:

- journal `u6probed` — "U6 Probe D Journal", acronym `U6D`, contextId 54, tag `u6probeD`
- `u6d.mgr` (Mona ProbeD, Journal manager) — the inviter throughout
- `u6d.author` (Aiden ProbeD, Author) — the "existing user" of D-26

Invitee addresses are throwaways I own (`u6d.new1…10@example.org`,
`u6d.exp1@example.org`). `publicknowledge` and its seeded users were read
only — `author.alex` was signed in twice (D-27) and named once as a taken
username (D-24c); no seeded user was modified. Mailpit was never cleared; every
check was `search?query=to:<recipient>`.

**Driver**: Chromium via Playwright, used as a browser only — `goto`, `fill`,
`click`, `check`, `selectOption`, `keyboard.press`. No hand-built application
requests; the only non-browser calls are the sanctioned scenario endpoint above
and Mailpit's read API (plus one run through Mailpit's own web UI, D-23e).

**Vocabulary used below**: **claim** = settles (or corrects) the item;
**context** = incidental DOM seen in passing, not promotable to an assertion.

---

## D-23 — Newcomer end to end (s1 · fn-y, fn-t, fn-v · Rules 1, 10)

### D-23a — Send, as Journal Manager `u6d.mgr`

Screen: `/index.php/u6probed/management/settings/access` → button
`getByRole('button', {name: 'Invite to a role'})` → lands on
`/index.php/u6probed/invitation/create/userRoleAssignment`.

Walked: search `u6d.new1@example.org` (`#-search-control` + button
`getByRole('button', {name:'Search User', exact:true})`) → details step
(`#userDetails-inviteeEmail-control`, `#userDetails-givenName-control-en` =
"Nora", `#userDetails-familyName-control-en` = "Newcomer",
`select[name=userGroupId]` = "Author", `input[name=dateStart]` = 2026-08-01,
`select[name=masthead]` = "Appear on the masthead") → `Save And Continue` →
composer step → `Invite user to the role`.

**claim** — the send confirmation dialog, verbatim:

> **Invitation Sent**
>
> u6d.new1@example.org has been invited to new role in OJS. You can be updated
> about the user's decision on the Users & Roles page, your OJS notifications
> and/or your email
>
> [ View All Users ]

**context** — page heading "Invite user to take a role"; intro line "You are
inviting a user to take a role in OJS along with appearing in the journal
masthead"; composer subject field `#userInvited-subject` arrives pre-filled
`"You are invited to new roles"`; body is TinyMCE `#userInvited-body-control_ifr`
pre-filled with the template, tokens shown unrendered in the composer
(`{$RECIPIENTNAME}`, `{$INVITERNAME}`, `{$EXISTINGROLES}`, `{$ROLESADDED}`).

### D-23b — The email (Mailpit, scoped `to:u6d.new1@example.org`)

**claim** — exactly one message; Subject **"You are invited to new roles"**;
From `u6d.mgr@example.org`. Body (text part) verbatim, trimmed:

> Invitation to New Role
>
> Dear u6d.new1@example.org,
>
> In light of your expertise, you have been invited by Mona ProbeD to take on
> new roles at U6 Probe D Journal
>
> At U6 Probe D Journal, we value your privacy. As such, we have taken steps to
> ensure that we are fully GDPR compliant. […]
>
> **Newly assigned roles**
> 1. **Author** — Starting from 2026-08-01
> Your name will appear in the U6 Probe D Journal's masthead as a Author.
>
> On accepting the invite, you will be redirected to U6 Probe D Journal.
>
> Feel free to contact me with any questions about the process.
>
> Accept Invitation ( …/u6probed/invitation/accept?id=256&key=5WQA2W )
> Decline Invitation ( …/u6probed/invitation/decline?id=256&key=5WQA2W )
>
> Kind regards, U6 Probe D Journal

**claim** — the body names inviter ("Mona ProbeD") and journal, lists the role
with its start date and masthead choice, and carries the two links "Accept
Invitation" and "Decline Invitation". **Confirms** the Side-effects
"Invitation email" bullet and Rule 1's "the email goes out on press".

**claim** — the salutation for a **newcomer** is the invitee's **email
address**, not the given/family name the inviter typed on the details step
("Dear u6d.new1@example.org" although the wizard carried "Nora"/"Newcomer").
Contrast D-26, where an existing user gets "Dear Aiden ProbeD". Not in the
draft.

**context** — "Your name will appear in the U6 Probe D Journal's masthead as a
Author" (article/agreement defect in the template); "Newly assigned roles" is
the only section for a newcomer.

### D-23c — The acceptance flow (signed-out visitor)

Screen: the accept URL typed into the address bar.

**claim** — the step list offered is exactly three steps:
`1 Create OJS account · 2 Enter details · 3 Review & create account`
(locator: `.pkpSteps ol`). **No "Verify ORCID iD" step appears** — recorded as
an **install fact**: ORCID is not configured on this install. Rule 10's
newcomer list is otherwise **confirmed**, and its ORCID conditional is
consistent with the install fact.

**claim** — step 1 "STEP 1 - Create OJS account", intro "To get started with
OJS and accept the new role, you will need to create an account with us. For
this purpose please enter a username and password."; Email row shows
`u6d.new1@example.org` read-only; fields `#-username-control`,
`#-password-control`, `input[name=privacyStatement]`; buttons "Cancel" and
"Save and continue".

**claim** — step 2 "STEP 2 - Enter details", intro "Enter your details like
email ID, affiliation etc. As per the GDPR compliance, this information can
only modified by you. […]". Fields: Given Name * (`#acceptUserDetails-givenName-control-en`),
Family Name, Affiliation, Country of affiliation * (`#acceptUserDetails-userCountry-control`).
An **ORCID iD row is present and reads "Not verified. You can verify your ORCID
iD from your profile section in OJS."** even though ORCID is not configured on
this install (install-fact contrast worth a line: the *step* is gated, the
*row* is not).

**claim** — step 3 "STEP 3 - Review & create account", intro "Review details to
start your new roles in OJS". Sections "Account Details" (Username), "User
Details" (with an "Edit" button; language "English"), "Roles" table with
columns ROLE / START DATE / END DATE / JOURNAL MASTHEAD showing
`Author | 2026-08-01 | --- | Appear on the masthead`. Buttons: "Cancel",
"Back", **"Accept And Continue to OJS"** (locator
`getByRole('button', {name:/^Accept And Continue/})`). **Confirms** Rule 10's
final-button label.

### D-23d — Success dialog and where it lands

**claim** — after pressing "Accept And Continue to OJS", verbatim:

> **You've been assigned a new role in OJS**
>
> Congratulations on your new role in OJS! You might now have access to new
> options. If you need any assistance in understanding the system, please click
> on the “Help” button throughout the system for guidance.
>
> [ View All Submissions ]

**claim — corrects the draft.** Rule 10's last bullet and scenario 1 say "View
All Submissions" lands on the submissions dashboard. It does **not**. Pressed
on a second, identical newcomer run (`u6d.new8@example.org`, account
`eight.lander`), it lands on
`/index.php/u6probed/login?source=%2Findex.php%2Fu6probed%2Fsubmissions` —
**the journal's Login page**, title "Login | U6 Probe D Journal". The
acceptance flow does **not** sign the new user in: no user menu is present
anywhere in the flow or on the landing page (only "Register Login" in the site
header).

**claim (adjacent, follow-up control)** — signing in manually afterwards as
`nora.newcomer` succeeds, but `/index.php/u6probed/submissions` then redirects
to `/index.php/u6probed/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`
showing "The current role does not have access to this operation." The invited
role's Start Date is 2026-08-01 and the wizard refuses any date that is not
after today, so a newly accepted user cannot use the role on the day they
accept. Positive control: seeded `u6d.author` (role start 2026-07-28, i.e. not
in the future) reaches `/submissions` and is redirected to
`/dashboard/mySubmissions?currentViewId=active`. Not in the draft.

### D-23e — Reading the mail in Mailpit's own web UI

**claim** — opened `http://127.0.0.1:8025/search?q=to:u6d.new2@example.org`,
clicked the message "You are invited to new roles", and in the rendered HTML
part the two controls are anchors labelled exactly "Accept Invitation" and
"Decline Invitation" pointing at
`…/invitation/accept?id=258&key=54m9RG` and `…/invitation/decline?id=258&key=54m9RG`.
Clicking "Accept Invitation" from the mail opens the acceptance wizard on
"STEP 1 - Create OJS account" with the email pre-filled. The recipient's
journey works as described from the mail client outward.

### D-23f — Manager side, after acceptance

**claim** — before: `Invitations (7)`, row "Nora Newcomer · u6d.new1@example.org ·
Author · Invited 2026-07-28"; `Current Users (3)`. After: **`Invitations (6)`,
the row is gone**, and `Current Users (4)` now lists
"Nora Newcomer | u6d.new1@example.org | Author | 2026-08-01 | Probe D Institute".
**Confirms** Rule 1 (acceptance removes the row) and scenario 1's manager-side
step, except for the "View All Submissions" landing corrected in D-23d.

**context** — the Invitations table shows 5 rows per page with a
`Previous · 1 · 2 · Next` pager; the heading is `Invitations (N)`; columns
NAME / EMAIL / INVITATIONS / STATUS / AFFILIATION / (menu); every status cell
read "Invited 2026-07-28".

---

## D-24 — Password / username / step-list name (fn-f, fn-j · P7 accept half · register A5)

Invitations used: `u6d.new2@example.org` (id 258) for (a) and (c),
`u6d.new7@example.org` (id 271) for (b).

### D-24a — Password shorter than the stated minimum

Screen: accept step 1. Entered username `shortpw.user`, password `abc`, ticked
consent, pressed "Save and continue".

**claim** — the flow stays on "STEP 1 - Create OJS account". Two things appear:

- an inline message under the Password field, verbatim:
  **"The password must be at least 6 characters."**
  (locator: bare `<span>` inside the password form field, no class of its own;
  addressable as `page.getByText('The password must be at least 6 characters.')`)
- a warning banner, verbatim: **`##invitation.wizard.errors##`**
  (locator `.pkpNotification--warning`; full markup
  `<div class="pkpNotification pkpNotification--warning">##invitation.wizard.errors## <!----></div>`)

**claim** — the field's own help text reads "It should be at least 6 characters
long and could be a combination of uppercase letters, lowercase letters,
numbers and symbols". **Confirms** the Fields table's "Site minimum length,
stated under the field".

**claim — register A5.** The untranslated banner key IS reachable, and it is
reachable on the **account step**, not only (or even) on the review step. It
appears again on the details step: leaving Given Name blank on step 2 and
pressing "Save and continue" holds the flow on "STEP 2 - Enter details" with
per-field "This field is required." plus the same
`##invitation.wizard.errors##` banner. A5's substance is **confirmed**; its
placement ("review-step banner") is **corrected** — see D-24d.

### D-24b — A widely-breached password of legal length

Entered username `breached.user`, password `password1234`, ticked consent,
pressed "Save and continue".

**claim — corrects the draft's *(unconfirmed)* claim.** The flow advanced
straight to "STEP 2 - Enter details". No refusal, no warning banner, no inline
message, nothing. On this install (egress firewalled) a breached password of
legal length is accepted **silently**.

### D-24c — A username that is already taken

Entered username `author.alex` (a seeded `publicknowledge` account), password
`ValidProbe2026!`, ticked consent, pressed "Save and continue".

**claim — corrects fn-f / the Fields table.** The refusal is **not** server-side
at the final step: the flow never leaves "STEP 1 - Create OJS account". Inline,
verbatim:

> **There is already an existing user with the username author.alex. Try another one.**

together with the `##invitation.wizard.errors##` banner. The review step is
never reached, so this particular error can never be the thing that lights up a
review-step banner.

### D-24d — Making the review step's banner appear

Since D-24c refuses at step 1, I drove the only remaining screens-only route to
a review-step failure: two browser contexts on the **same** invitation
(`u6d.new10@example.org`, id 309), both walked to "STEP 3 - Review & create
account"; context B pressed "Accept And Continue to OJS" and completed; then
context A pressed the same button.

**claim** — context A's refusal renders as a **modal dialog**, not the warning
banner. Verbatim:

> **Error**
>
> You are not authorized to access the requested resource.
>
> [ OK ]

`.pkpNotification--warning` count on that page: **0**. So the review-step
banner named in Rule 10's Cancel bullet and in A5 did not appear in the one
screens-only way I could make the final submit fail; the untranslated key
surfaces on the account and details steps instead (D-24a).

### D-24e — The accept wizard's step-list accessible name

**claim** — `document.querySelector('.pkpSteps ol').getAttribute('aria-label')`
is the raw key **`##invitation.wizard.completeSteps##`** on the acceptance
wizard (all variants: 3-step newcomer, 1-step existing user). **Confirms** the
A5 step-list half.

**context (Group B territory, one line)** — the **send** wizard's step list
carries the same raw `##invitation.wizard.completeSteps##`.

---

## D-25 — Privacy Consent left unticked (fn-k)

Screen: accept step 1 (invitation 258). Username `consent.probe`, password
`ValidProbe2026!`, consent checkbox left unchecked, pressed "Save and continue".

**claim** — the flow stays on "STEP 1 - Create OJS account". Inline message,
verbatim:

> **Please confirm that you have read and agree privacy statement**

plus the `##invitation.wizard.errors##` banner. **Confirms** the Fields table's
wording exactly.

**claim** — it blocks everything downstream: after the refusal the step list's
later entries are **not** clickable — step 1 renders as a `<button>`, steps 2
and 3 render as plain `<span class="pkpSteps__step__label">` with no button at
all (`.pkpSteps__step` scan: `1 Create OJS account btn=true | 2 Enter details
btn=false | 3 Review & create account btn=false`). **Confirms** the consent
row's blocking claim.

**claim** — the checkbox label is "Yes, I agree to have my data collected and
stored according to the **Privacy Statement**", where "Privacy Statement" is an
anchor to `/index.php/u6probed/about/privacy`. **Confirms** the Fields table's
"a checkbox whose label links to the journal's privacy statement".

---

## D-26 — Existing user, signed out (s2 · fn-d, fn-t · Rule 10 existing-user branch)

Setup per the item's note: a **scratch** user with an existing account
(`u6d.author`, Aiden ProbeD, already Author in the scratch journal) invited to a
role they lack (**Reviewer**, start 2026-08-05… entered 2026-08-01) — the
seeded `publicknowledge` users' state is untouched.

**context (send side)** — searching an existing member's email answers
**"The user already exists in the journal"** and the details step arrives with
Email/Given Name/Family Name/Affiliation rendered as read-only text, a current
roles table (`Author | 2026-07-28 | --- | <masthead selector> | Remove Role`)
and one **empty new-role row already present** (no "Add Another Role" click
needed). The "Select a new role" dropdown **omits "Author"** — the role they
already hold.

**claim (email)** — the existing user's mail is addressed **"Dear Aiden
ProbeD"** and carries an extra section:

> **Already assigned roles** — 1. Author — Starting from 2026-07-28 —
> "Your name will not appear in U6 Probe D Journal's masthead as a Author."
>
> **Newly assigned roles** — 1. Reviewer — Starting from 2026-08-01 —
> "Your name will appear in the U6 Probe D Journal's masthead as a Reviewer."

**claim — the auto-sign-in claim is *disproved*.** Opening the accept link
signed out does **not** sign the user in. The page renders with **no user
menu** — the only header affordance is the journal-name link
`U6 Probe D Journal -> /index.php/u6probed/index`. There is no "Tasks", no
avatar, no username.

**claim — corrects Rule 10's existing-user bullet.** The existing user is not
accepted immediately and does get a page: the flow is a **single step**,
`1 Review & create account` (heading "STEP 1 - Review & create account", intro
"Review details to start your new roles in OJS"), showing a "User Details"
block that contains only an ORCID iD row ("Not verified. You can verify your
ORCID iD from your profile section in OJS.") and a Roles table
`Reviewer | 2026-08-01 | --- | Appear on the masthead`. Buttons: "Cancel" and
"Accept And Continue to OJS". No account step, no details step — **confirms**
scenario 2's "no account or details step".

**undetermined** — the draft's narrower *(unconfirmed)* sub-claim is "no steps,
no page … for an existing user **whose ORCID is already verified**". This
install has ORCID unconfigured and the user has no ORCID, so I observed the
not-verified case only. **The one observation that would settle it**: the same
accept link opened by an existing user whose ORCID iD is already verified —
which needs ORCID credentials this install does not have.

**claim** — pressing "Accept And Continue to OJS" shows the same success dialog
as D-23d ("You've been assigned a new role in OJS" … "View All Submissions"),
and "View All Submissions" again lands on
`/index.php/u6probed/login?source=%2Findex.php%2Fu6probed%2Fsubmissions`.

**claim** — the role is attached: the manager's `Current Users` row for
"Aiden ProbeD | u6d.author@example.org" now lists **Author** *and* **Reviewer**
with start dates 2026-07-28 and 2026-08-01. **Confirms** scenario 2's outcome.

---

## D-27 — Accept link opened as the wrong user (s6 · fn-d · Rule 12)

Signed in as seeded `author.alex` (read-only visit), pasted invitation 258's
accept link into the address bar.

**claim** — a modal (`[role=dialog]`, red left border) appears over the accept
wizard, verbatim:

> **Invitation not accepted. You're logged in as a different user.**
>
> Please log out and sign in with the correct account to accept this invitation.
>
> [ Logout ]

The button label is **"Logout"** (one word) — the draft's Rule 12 and scenario
6 call it "Log Out". Only one button; there is no visible close/X.

**claim** — pressing "Logout" signs the session out and lands on
`/index.php/u6probed/login` (title "Login | U6 Probe D Journal"). Re-opening
the same accept link in that same browser then opens the acceptance flow
normally, on "STEP 1 - Create OJS account" with Email pre-filled
`u6d.new2@example.org`. **Confirms** Rule 12's "signs them out to start over"
and scenario 6.

**claim — corrects Rule 12's last clause.** Dismissing the dialog (Escape;
an outside click behaves the same) does **not** land on the submissions
dashboard. It navigates to
`/index.php/u6probed/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`,
a front-end page reading "The current role does not have access to this
operation."

**context** — behind the dialog, the accept wizard's step 1 renders with an
**empty** Email row (contrast the pre-filled row after logging out).

---

## D-28 — Decline (s3 · fn-x · Rule 11)

Invitation `u6d.new4@example.org` (id 264). Decline link typed into the address
bar, no sign-in.

**claim** — the page, verbatim (heading is an `<h1>`):

> **Decline Invitation**
>
> Are you sure you want to decline this invitation? Confirm the decline by
> clicking the button below.
>
> [ Confirm Decline Invitation ]

Only that one button; no sign-in required. **Confirms** Rule 11's wording and
button label.

**claim** — pressing "Confirm Decline Invitation" lands on
`/index.php/u6probed/login` — the journal's sign-in page, with no confirmation
message of any kind. **Confirms** Rule 11's "lands on the sign-in page".

**claim** — the manager's table drops from `Invitations (6)` to
`Invitations (5)` and no longer lists `u6d.new4@example.org`.

**claim** — re-opening the **accept** link for the declined invitation now
renders:

> **Invitation Unavailable**
>
> This invitation is no longer available. It may have already been accepted,
> declined, or expired. Please contact the journal manager for further
> assistance.
>
> Login  Register

Both are **anchors, not buttons**: `Login -> /index.php/u6probed/login`,
`Register -> /index.php/u6probed/user/register` (locators
`getByRole('link', {name:'Login'})` / `{name:'Register'}`). Re-opening the
**decline** link renders the same page. **Confirms** Rule 13's page anatomy on
OJS (the OJS control C-22 asks for); note the description does say "journal
manager".

**claim** — **no new mail**. Scoped Mailpit before and after by recipient:
`to:u6d.new4@example.org` = 1 message both times (the original invitation);
`to:u6d.mgr@example.org` = 0 both times. Scope limitation stated plainly: I
checked the invitee and the inviter, the only two addresses a decline notice
could plausibly reach; I did not and cannot assert "no message to *anyone*"
without clearing or counting a shared inbox, which the etiquette forbids.

---

## D-29 — Cancel Invite (s4 · fn-q · Rule 7)

Invitation `u6d.new5@example.org` (id 266), as `u6d.mgr` on the Users & Roles
screen.

**claim** — the per-row control is a single icon button whose accessible name
is **"Invitation management options"** (locator
`row.getByRole('button', {name:'Invitation management options'})`). It opens a
`[role=menu]` with exactly two `[role=menuitem]`s: **"Edit"** and
**"Cancel Invite"**.

**claim** — "Cancel Invite" opens a dialog, verbatim:

> **Cancel Invitation**
>
> - **Email:** u6d.new5@example.org
> - **Role:** Author,
> - **Status:** Invited 2026-07-28
> - **Affiliation:**
>
> [ Cancel Invitation ]  [ Cancel ]

**claim — corrects Rule 7.** The draft says the dialog "warns that cancelling
'will deactivate acceptance link sent via email'". **There is no warning
sentence.** The dialog's description element is an empty paragraph immediately
followed by the detail list:
`<p id="reka-dialog-description-…"><p></p> <div class="px-8"><ul role="list" …>`.
The four details are as the draft says (email, roles, status, affiliation);
"Role: Author," carries a trailing comma and Affiliation is blank.

**claim** — button effects. **"Cancel"** closes the dialog and leaves the table
unchanged (`Invitations (5)`, row still present). **"Cancel Invitation"**
closes the dialog and the count drops to `Invitations (4)` with the row gone.
Neither navigates away from `/management/settings/access`.

**claim** — the already-delivered accept link for id 266 now renders
"Invitation Unavailable" with the same sentence and the same Login/Register
anchors as D-28. **Confirms** scenario 4.

**claim** — **no new mail** (same scoping caveat as D-28):
`to:u6d.new5@example.org` = 1 (the original), `to:u6d.mgr@example.org` = 0,
before and after.

---

## D-30 — Edit a pending invitation (s5 · fn-q · Rule 6)

Invitation `u6d.new6@example.org` (id 269).

**claim** — "Invitation management options" → "Edit" opens a dialog, verbatim:

> **Edit Invitation**
>
> If you edit the existing invitation or add a new role, the current invitation
> will be canceled and, a new one will be sent. Are you sure you want to
> proceed?
>
> [ Edit Invitation ]  [ Cancel ]

**Confirms** Rule 6's quoted wording exactly, including the stray comma.

**claim** — confirming with "Edit Invitation" navigates to
`/index.php/u6probed/invitation/edit/269`. The page is headed with the
invitee's name — "Nn6 Newcomer6" — over the line "You are viewing Nn6's user
details", and the step list is **two** steps: `1 Enter details · 2 Review &
invite for roles`. **No search step. Confirms** Rule 6 and Rule 4b's
no-search-step claim for this entry point.

**claim** — the form arrives preloaded. Visible control values:
`inviteeEmail="u6d.new6@example.org"`, `givenName-en="Nn6"`,
`familyName-en="Newcomer6"`, `userGroupId="968"` (Author),
`dateStart="2026-08-01"`, `masthead="true"`. A "Remove Role" button sits beside
the loaded row and "Add Another Role" below it.

Added a Reviewer row (start 2026-08-05), saved, and sent.

**claim** — the table then shows **one** row for `u6d.new6@example.org` with
**"Author / Reviewer"** in the INVITATIONS cell and status "Invited
2026-07-28"; the count did not grow (still `Invitations (4)`). A second email
arrived (Mailpit `to:u6d.new6@example.org` = 2), listing both roles with their
respective start dates, and carrying new links `id=301&key=Wp83aU`.

**claim — corrects scenario 5 and Rule 6's tail.** The **first** email's links
do **not** land on "Invitation Unavailable". Both of them —
`…/invitation/accept?id=269&key=ej9xVM` and `…/invitation/decline?id=269&key=ej9xVM`
— return **HTTP 404** and render a bare, unstyled page whose entire body is
`<h1>404 Not Found</h1>`, with an empty `<title>` and no site chrome. The
**second** email's links work: accept opens "STEP 1 - Create OJS account" with
the email pre-filled; decline opens the "Decline Invitation" page.

So the three ways an invitation stops being usable are **not** uniform from the
recipient's side: decline (D-28), manager cancel (D-29) and expiry (D-31) all
show "Invitation Unavailable", while **edit-and-resend shows a bare 404** — the
same page a garbled link gets (D-33).

---

## D-31 — An aged-out invitation (s7 · fn-m, fn-s7 · Rule 2)

**Setup used — recorded as the item asks.** No scenario endpoint seeds
invitations (the live schemas `context.json` / `submission.json` have no
invitation key), so I used the item's fallback: `[invitations]
expiration_days = 0` appended to `config.test.inc.php`. Because
`Invitation::invite()` stamps `expiryDate = now + expiryDays` at send time, the
config only needs to be in force for the send itself, so the window was kept to
**4 seconds** (16:04:14.5 → 16:04:18.5 UTC): the wizard was walked to the
composer step under the normal config, the file was patched, "Invite user to the
role" was pressed, and the original file was written back and byte-compared
(identical). Invitee `u6d.exp1@example.org`, invitation id 307. No wait was
needed — the invitation was already past its expiry the moment it existed.
(Two other probe agents were on this fleet concurrently; the narrow window is
why the patch is described this precisely.)

**claim** — the manager's Invitations table **never lists it**: the count stayed
`Invitations (4)` before and after the send, and no `u6d.exp1@example.org` row
appears on either page of the table. **Confirms** Rule 2's "an expired
invitation drops out of the table".

**claim** — the emailed **accept** link (`id=307&key=Nu7rYL`) lands on:

> **Invitation Unavailable**
>
> This invitation is no longer available. It may have already been accepted,
> declined, or expired. Please contact the journal manager for further
> assistance.
>
> Login  Register

with `Login -> /index.php/u6probed/login` and
`Register -> /index.php/u6probed/user/register`. The emailed **decline** link
renders the same page. **Confirms** scenario 7 in full, including that the two
controls lead to the sign-in and registration pages (they are anchors, not
buttons).

**install fact, as instructed** — `[schedule] task_runner = Off` in
`config.test.inc.php`; the nightly deleter does not run here and deletion was
not probed. The listing and the link are the assertions.

---

## D-32 — Cancel mid-acceptance (fn-w · Rule 10 cancel bullet)

Invitation `u6d.new3@example.org` (id 261). Walked to "STEP 2 - Enter details",
then pressed "Cancel".

**claim** — the dialog, verbatim:

> **Cancel Role Invitation Process?**
>
> Are you sure you want to cancel? Canceling now will stop the role acceptance
> process, and you’ll need to restart from the invitation email to accept the
> role again. If you’re already a user, you’ll be taken back to the dashboard.
> If not, you’ll need to access the invitation email to start the process
> again.
>
> [ Cancel Invitation Process ]  [ Go Back ]

**Confirms** Rule 10's dialog title and both button labels.

**claim** — **"Go Back"** closes the dialog and returns to exactly where the
user was: URL `…/invitation/accept?id=261&key=9T37bh#userDetails`, heading
still "STEP 2 - Enter details", entered data intact.

**claim** — **"Cancel Invitation Process"** abandons the flow and lands on
`/index.php/u6probed/login?source=%2Findex.php%2Fu6probed%2Fsubmissions` — the
journal's Login page (the same destination as the success dialog's "View All
Submissions", D-23d). Note the dialog's own text promises "you'll be taken back
to the dashboard" for an existing user; a signed-out newcomer gets the login
screen.

**claim** — the invitation **stays pending**: the manager's table still lists
"Nn3 Newcomer3 · u6d.new3@example.org · Author · Invited 2026-07-28"
(`Invitations (4)`), and re-opening the accept link opens the flow again on
"STEP 1 - Create OJS account" with the email pre-filled. **Confirms** the
cancel-keeps-it-pending claim.

---

## D-33 — A link that matches no invitation (fn-c tail · Rule 13)

URLs typed into the address bar, signed out:

| URL | HTTP | Page |
|---|---|---|
| `/u6probed/invitation/accept?id=999999999&key=ABCDEF` | 404 | body is exactly `<h1>404 Not Found</h1>`, `<title>` empty |
| `/u6probed/invitation/accept?id=999999999&key=Wp83aU` (real key, wrong id) | 404 | same |
| `/u6probed/invitation/decline?id=999999999&key=ABCDEF` | 404 | same |
| `/u6probed/invitation/accept?id=301&key=WRONGKEY` (real id, wrong key) | 404 | same |
| `/u6probed/invitation/accept` (no query at all) | **500** | completely empty body |

Controls for "the site's not-found page": `/u6probed/nosuchpagehere` → 404 with
body `<h1>404 Not Found</h1>`; `/u6probed/article/view/99999999` → the same.

**claim** — **confirms** Rule 13's last sentence: a link matching no invitation
gets the site's not-found page, **not** "Invitation Unavailable". Worth
carrying into the spec's voice, though: on this install "the site's not-found
page" is a bare unstyled `404 Not Found` with no journal chrome and no
navigation back — so a recipient with a stale link sees nothing that tells them
what happened or where to go, in contrast with the "Invitation Unavailable"
page's Login/Register.

**claim** — a truncated accept URL (no `id`/`key`) answers **HTTP 500 with an
entirely blank page**. Reached by ordinary address-bar typing; recorded as
observed, no severity argued.

---

## Incidental (outside Group D — one line each, not pursued)

- **B-7**: the *send* wizard's step list also carries the raw accessible name
  `##invitation.wizard.completeSteps##`.
- **B-8**: on the send wizard, the searched email is pre-filled into
  `#userDetails-inviteeEmail-control` on the details step; the newcomer details
  step offers **no ORCID iD field** (ORCID not configured — install fact).
- **B-15 / Rule 5**: on the existing-user details step the "Select a new role"
  dropdown **omits the role the user already holds** (Author absent for
  `u6d.author`); the full scratch-journal list is 18 options, Journal manager →
  Editorial Board Member.
- **B-14**: no literal `<?php` text was visible on the send wizard, the accept
  wizard, or in the delivered email body.
- Site chrome on every backend screen renders the raw key `##common.help##`
  where the Help control's label belongs — not this feature's surface.
- The single-step (existing-user) accept page renders the literal string
  `{$current}/{$total} steps` beside "1/1 steps" in its progress area.

## Deferred queue

Nothing from Group D needed a request the screens do not send, so I added no
line to `../e2e_ng/server-questions.md`. The one undetermined sub-claim (D-26,
existing user with a **verified** ORCID) is not a deferred-queue item — it is
answerable through the screens on an install where ORCID is configured, which
this one is not.

## Artifacts

Screenshots and driver scripts for every item are in the session scratchpad at
`…/scratchpad/u6d/` (`d23_*`, `d24_*`, `d25*`, `d26_*`, `d27_*`, `d28_*`,
`d29_*`, `d30_*`, `d31_*`, `d32_*`, `t_after*`). They are throwaway; nothing in
this report depends on them.
