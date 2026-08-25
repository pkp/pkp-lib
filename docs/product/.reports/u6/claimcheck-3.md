# U6 — claim check, chunk 3

**Frame.** QA documentation of an application's own screens, on a local disposable test
install with seeded accounts. Signed in as each role and used the screens the way that role
would — including typing a URL directly to reach one. Recorded what the screen offers, what
happens when it is used, and where the two disagree, so the product team can fix it. No
request was constructed that the screens themselves would not send.

**Target:** `docs/product/specs/user-invitations.md` — **Rules & state, Rule 9 → end of the
section**; the whole **Side effects**, **Settings that modify behavior** and **Cross-feature
interactions** sections.

**Run:** OJS fleet `http://127.0.0.1:8000` (2026-07-28), OPS fleet `http://127.0.0.1:8200`
for the app-marked claims. Driven headless through Playwright as an ordinary browser: real
sign-ins on the login form, real clicks, emailed links opened from Mailpit.

**Scratch material (mine, disposable):**

| Thing | What |
|---|---|
| OJS journal `u6cc3ojs` ("U6 CC3 Journal") | manager `u6cc3mgr`, existing users `u6cc3ex1` / `u6cc3oth`, 8 seeded invitations (1 seeded `expired`) |
| OJS journal `u6cc3ojs2` ("U6 CC3 Bilingual Journal") | locales en + fr_CA, manager `u6cc3mgr2`, `u6cc3two` (2 roles), `u6cc3one` (1 role) |
| OPS server `u6cc3ops` | manager `u6cc3opsmgr`, existing user `u6cc3opsex`, 2 seeded invitations |
| Invitations sent live through the wizard | `u6cc3.mail@`, `u6cc3.past@`, `u6cc3.canc@`, `u6cc3.edit@`, `u6cc3.race@`, `u6cc3.empty@`, `u6cc3.otto2@` (all `@example.org`) |

`publicknowledge` was opened **read-only** on OPS (`manager.maya`) for the email-templates
control. Mailpit was never cleared; every assertion is scoped by recipient.

**Install facts this report leans on (established, not discoveries):** ORCID integration is
not configured on any fleet, so no account carries a verified iD; the scheduled task runner
is off, so nightly cleanup never fires. Also known: the wizard's role table reuses one set
of control identifiers across rows (A18) — every role-row control below is addressed
positionally for that reason.

**Verdict tally:** 42 claims checked (C1–C42) — **30 holds · 3 wrong · 7 undetermined ·
2 part-holds/part-undetermined (C35, C37) · 0 unreachable**. Every surface the chunk names
was reachable; C31 holds for cancelling and declining with its expiry clause unobservable
here.

---

## Rules & state — Rule 9

### C1 — Remove Role acts immediately

> "Changes made on the details step to an existing user's **current** roles act immediately —
> 'Remove Role' ends the role after a confirmation … in both cases before any invitation is
> sent"

**Did:** signed in as `u6cc3mgr2` on `u6cc3ojs2`; Users & Roles → Current Users → row *Tia
Tworoles* (Reader + Author) → row menu → "Edit" (landed on
`/u6cc3ojs2/en/management/settings/user/347`); on the "Enter details" step clicked the Author
row's `button` named "Remove Role" (row located by `tr` containing "Author" + "Remove Role"),
then confirmed; then navigated away without sending anything.

**Appeared:** dialog headed "Remove Role" with body "Are you sure you want to remove this
role? The user will lose access and permissions associated with it." and buttons "Remove
Role" / "Cancel". After confirming, the row showed END DATE `2026-07-28` and the text "User
Removed From Role". Back on Users & Roles the user's ROLES cell read "Reader" only, and the
Invitations table read "Invitations (0)" — nothing was sent. An email
"You have been removed from a role" reached `u6cc3two@example.org`.

**Verdict: holds.**

### C2 — Masthead choice acts immediately

> "and the masthead choice updates after a confirmation, in both cases before any invitation
> is sent"

**Did:** same wizard; on the remaining Reader row changed the "Journal Masthead" select
(`select#-masthead-control`, first visible row) from "Does not appear" to "Appear on the
masthead"; then reloaded the page fresh.

**Appeared:** dialog "Confirm masthead visibility change — This will update whether this user
appears on the journal masthead for the selected role. The user will be notified of this
change." with buttons "Confirm" / "Cancel". After Confirm and a fresh reload the select held
the new value; Invitations still read (0). A mail "Your journal masthead visibility has been
updated" reached the user.

**Verdict: holds.** (Both immediate changes send their own mail to the affected user — that
belongs to *Users management*, but it is worth knowing the "before any invitation is sent"
window is not silent.)

### C3 — Last remaining role is refused

> "Removing a user's last remaining role is refused with an explanatory dialog."

**Did:** same journal, `management/settings/user/348` (*Ola Onerole*, Reader only); clicked
the row's "Remove Role", then the dialog's "Remove Role".

**Appeared:** a second dialog headed "Remove Role" reading "You cannot remove the role. At
least one role must be assigned to the user." with a single "Close" button. The role stayed.

**Verdict: holds.**

---

## Rules & state — Rule 10 (accepting)

### C4 — Newcomer step list and app-name substitution

> "**Newcomer** (no account): … then 'Create OJS account' (username, password, Privacy
> Consent), then 'Enter details' (name, affiliation, country), then 'Review & create account'.
> The last step's button reads 'Accept And Continue to OJS' — here and in the step names, OMP
> and OPS write their own app's name."

**Did:** opened the seeded newcomer accept link (`/u6cc3ojs/invitation/accept?id=425&key=…`)
signed out; walked all three steps. Repeated on OPS with `/u6cc3ops/invitation/accept?id=242…`.

**Appeared (OJS):** step tabs "1 Create OJS account / 2 Enter details / 3 Review & create
account"; step 1 offers Username, Password and a "Privacy Consent" checkbox; step 2 offers
Given Name / Family Name / Affiliation / "Country of affiliation"; step 3 is headed "STEP 3 -
Review & create account" with the primary button "Accept And Continue to OJS".
**On OPS:** "1 Create OPS account", "STEP 1 - Create OPS account", "Review details to start
your new roles in OPS", button "Accept And Continue to OPS", and the roles table column reads
"SERVER MASTHEAD".

**Verdict: holds.**

### C5 — The ORCID step appears only while ORCID is enabled

> "'Verify ORCID iD' (only while ORCID is enabled on the journal)"

**Did:** all acceptance flows above, on an install where ORCID is not configured.

**Appeared:** no ORCID step in any flow (newcomer or existing user), on either fleet.

**Verdict: undetermined** — the negative half is confirmed; the positive half (that enabling
ORCID adds the step) cannot be arranged on this install.

### C6 — Existing user gets a single review step, no sign-in

> "**Existing user**: … then a single review step showing the invited role; accepting attaches
> it to the existing account, without signing the visitor in."

**Did:** opened the seeded existing-user accept link (`…accept?id=426&key=…`, invitee
`u6cc3ex1`) in a clean signed-out context; also the OPS equivalent (`id=243`), which I
accepted through.

**Appeared:** a one-step flow — tab "1 Review & create account", heading "STEP 1 - Review &
create account", an identity block and a Roles table listing "Section editor / 2026-07-28 /
--- / Appear on the masthead"; buttons "Cancel" and "Accept And Continue to OJS" only. No
account or details step. On OPS the accepted role was attached and the visitor was left at
`/u6cc3ops/login?source=/index.php/u6cc3ops/submissions` — never signed in.

**Verdict: holds.**

### C7 — The ORCID line shows on the details step and the existing user's review

> "With ORCID not enabled there is no ORCID step and no ORCID field in the send wizard, but
> the acceptance flow's details step — and the existing user's review — still show an 'ORCID
> iD' line reading not verified."

**Did:** read the acceptance details step (OJS + OPS), the existing-user review step, and the
send wizard's newcomer details step (`/u6cc3ojs/invitation/create/userRoleAssignment`, whose
visible controls were `#userDetails-inviteeEmail-control`,
`#userDetails-givenName-control-en`, `#userDetails-familyName-control-en` and the role row —
no ORCID input).

**Appeared:** both acceptance surfaces render "ORCID iD — Not verified. You can verify your
ORCID iD from your profile section in OJS." (OPS: "…in OPS."). The send wizard offers no
ORCID field.

**Verdict: holds.**

### C8 — What the ORCID step offers

> "The ORCID step offers 'Verify ORCID iD' (opens ORCID in a new window) and 'Skip ORCID
> verification'; skipping loses nothing but the verification."

**Verdict: undetermined** — the step never renders on this install (ORCID unconfigured).

### C9 — Success dialog, and where "View All Submissions" lands

> "Accepting assigns the invited roles and shows a success dialog — 'You've been assigned a
> new role in OJS' — but signs nobody in: its 'View All Submissions' button lands on the
> journal's sign-in page, not the submissions dashboard"

**Did:** completed the newcomer acceptance for `u6cc3.new1@example.org` (account `u6cc3nadia`)
and clicked the dialog's button; repeated the existing-user acceptance on OPS.

**Appeared:** `[role=dialog]` reading "You've been assigned a new role in OJS /
Congratulations on your new role in OJS! … / View All Submissions"; clicking it landed on
`/u6cc3ojs/login?source=%2Findex.php%2Fu6cc3ojs%2Fsubmissions` with no user menu anywhere.
The role was really assigned: `Nadia Newcomer / u6cc3.new1@example.org / Section editor` now
appears in the manager's users table, and signing in as `u6cc3nadia` reaches
`/u6cc3ojs/dashboard/editorial`. OPS shows the same dialog with "OPS" substituted.

**Verdict: holds.** One wording nit for the merge agent: the dialog text substitutes the app
name exactly like the step names do ("…a new role in OPS"), but Rule 10 quotes the OJS form
without extending its app-name caveat to it.

### C10 — A passed start date becomes today

> "A start date that has meanwhile passed becomes today."

**Did:** as `u6cc3mgr` sent an invitation to `u6cc3.past@example.org` with "Start Date"
`2026-07-01` (accepted by the field and by the send); opened the emailed accept link and
completed the flow; then read the manager's users table.

**Appeared:** the acceptance review step still displayed the invited date `2026-07-01`; after
accepting, the users table row reads `Pat / u6cc3.past@example.org / Section editor /
2026-07-28` — today.

**Verdict: holds.**

### C11 — The Cancel dialog and abandoning the flow

> "'Cancel' asks 'Cancel Role Invitation Process?' — 'Cancel Invitation Process' abandons the
> flow (the invitation stays pending and the link reusable) and also lands on the sign-in
> page … ; 'Go Back' returns with entered data intact."

**Did:** on a pending newcomer flow (`…accept?id=431`) filled Username `u6cc3keep` and ticked
Privacy Consent, pressed "Cancel", pressed "Go Back", re-read the fields; then pressed
"Cancel" again and "Cancel Invitation Process"; then re-opened the same link.

**Appeared:** dialog headed "Cancel Role Invitation Process?" with body "Are you sure you want
to cancel? Canceling now will stop the role acceptance process, and you'll need to restart
from the invitation email to accept the role again. If you're already a user, you'll be taken
back to the dashboard. If not, you'll need to access the invitation email to start the
process again." and buttons "Cancel Invitation Process" / "Go Back". Go Back restored
Username `u6cc3keep` and the ticked checkbox. Cancel Invitation Process landed on
`/u6cc3ojs/login?source=%2Findex.php%2Fu6cc3ojs%2Fsubmissions`, and re-opening the link
resumed the flow at step 1.

**Verdict: holds.**

### C12 — Where a refusal is shown during acceptance  ❗

> "A warning banner listing what the server refused tops the account and details steps; a
> review-step failure shows a modal error instead (Rule 14)." *(Rule 14 repeats it: "the
> banner tops refusals on the account and details steps, while a review-step failure shows a
> modal error instead".)*

**Did:** three refusals, all through the screens.
1. Account step, taken username: entered `u6cc3oth` + a legal password + Privacy Consent →
   "Save and continue".
2. Account step, short password: entered a fresh username + `abc` → "Save and continue".
3. Review step: opened a newcomer flow (`…accept?id=431`) and walked to "Review & create
   account"; meanwhile, in a separate browser session, that same person registered themselves
   on the journal's own Register page with the invited address (gaining the invited role that
   way); then pressed "Accept And Continue to OJS".

**Appeared:**
1. An inline message under the Username field: "There is already an existing user with the
   username u6cc3oth. Try another one." **No banner anywhere on the step** (the page text
   contains no `invitation.wizard.errors` and no warning region).
2. An inline message under the Password field: "The password must be at least 6 characters."
   Again no banner.
3. **Nothing at all.** No modal, no banner, no inline note, no navigation — the button
   appears to do nothing. (The browser's own console recorded the refusal;
   the screen said nothing.)

**Verdict: wrong.** What the screens actually do: server refusals on the account step appear
as inline messages under the offending field, not as a banner above the step; and the one
review-step refusal I could arrange produced no visible message of any kind — the invitee
presses the final button and the page simply does not move.

---

## Rules & state — Rules 11–14

### C13 — The decline flow

> "The emailed 'Decline Invitation' link opens a 'Decline Invitation' page: 'Are you sure you
> want to decline this invitation? Confirm the decline by clicking the button below.' The
> 'Confirm Decline Invitation' button marks the invitation declined and lands on the sign-in
> page. Declining needs no account and no sign-in."

**Did:** opened `/u6cc3ojs/invitation/decline?id=427&key=…` in a clean signed-out context and
pressed the button (`button#declineInvitationSubmit`, label "Confirm Decline Invitation");
repeated later on a wizard-sent invitation (`id=478`).

**Appeared:** heading "Decline Invitation" over exactly the quoted sentence, one button.
After confirming, the browser landed on `/u6cc3ojs/login`. The invitation's accept link then
answered "Invitation Unavailable", and the manager's table no longer listed the row.

**Verdict: holds.**

### C14 — Opening the accept link as a different signed-in user

> "If someone is signed in as a **different** user than the invitation names, opening the
> accept link is refused with 'Invitation not accepted. You're logged in as a different
> user.' — its 'Logout' button signs them out to start over; dismissing the dialog lands on
> the access-refused page ('The current role does not have access to this operation.'), not
> the submissions dashboard."

**Did:** signed in as `u6cc3oth`, typed `/u6cc3ojs/invitation/accept?id=429&key=…` into the
address bar. Ran it twice: once pressing "Logout", once dismissing the dialog with Escape
(the dialog is `[role=dialog]` marked dismissible and carries **no visible close button** —
Escape is the only dismissal a user has).

**Appeared:** a red dialog titled "Invitation not accepted. You're logged in as a different
user." with body "Please log out and sign in with the correct account to accept this
invitation." and a single button "Logout". Logout landed on `/u6cc3ojs/login`, and re-opening
the link then showed the normal step-1 acceptance flow. Escape landed on
`/u6cc3ojs/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`, whose
page reads "The current role does not have access to this operation."

**Verdict: holds** (worth recording that dismissal is Escape-only — no close control is
offered).

### C15 — Spent and expired links land on "Invitation Unavailable"

> "A link whose invitation is already accepted, declined, cancelled or expired lands on
> 'Invitation Unavailable': 'This invitation is no longer available. It may have already been
> accepted, declined, or expired. Please contact the journal manager for further assistance.'
> — with 'Login' and 'Register' buttons into the journal's own sign-in and registration
> pages."

**Did:** opened all four states — expired (seeded, `id=428`), declined (`id=427`), cancelled
(`id=461`, cancelled from the table), accepted (`id=425`) — on both the accept and decline
addresses; clicked both controls.

**Appeared:** every one rendered the page headed "Invitation Unavailable" with exactly the
quoted sentence. The two controls are **links styled as buttons** — `a` "Login" →
`/u6cc3ojs/login`, `a` "Register" → `/u6cc3ojs/user/register` — and both landed on the
journal's own sign-in and registration pages.

**Verdict: holds** (they are links, not buttons, if the wording is meant literally).

### C16 — "journal manager" in the other apps

> "The wording says 'journal manager' in OMP and OPS too ⚠ A7"

**Did:** opened a spent invitation link on the OPS fleet
(`http://127.0.0.1:8200/index.php/u6cc3ops/invitation/accept?id=243&key=…`).

**Appeared:** on a preprint server the page reads word-for-word "…Please contact the journal
manager for further assistance.", with Login/Register pointing into the server's own pages.
(OMP not checked here.)

**Verdict: holds** for OPS.

### C17 — Links that name no invitation

> "A link that matches no invitation gets the site's unstyled not-found page, with no journal
> name and no way back; a link that names no invitation at all renders a blank page ⚠ A15"

**Did:** typed `/u6cc3ojs/invitation/accept?id=999999&key=abcdef`, then
`/u6cc3ojs/invitation/accept?id=431&key=WRONGKEY`, then `/u6cc3ojs/invitation/accept`.

**Appeared:** the first two render a page whose entire body is "404 Not Found" — no journal
name, no navigation, empty `<title>`. The third renders `<html><head></head><body></body>`
— a completely blank page.

**Verdict: holds.**

### C18 — The step list's raw key

> "the step list's screen-reader name … show[s] untranslated raw keys"

**Did:** read the accessible name of the step list on the acceptance flow
(`ol.pkpSteps__buttons`).

**Appeared:** `aria-label="##invitation.wizard.completeSteps##"`.

**Verdict: holds.**

### C19 — The error banner's raw key

> "the acceptance flow's error banner show[s] untranslated raw keys — the banner tops refusals
> on the account and details steps"

**Did:** see C12 — three real refusals driven through the screens.

**Verdict: wrong** as written. No banner was rendered by any refusal I could produce, so its
raw key never reached the screen; the account-step refusals are inline field messages. (The
banner may still exist unreachable — but as a QA reader's expectation, "expect a raw-key
banner on the account and details steps" is not what the screens do.)

### C20 — A refused send answers the inviter with an unexplained modal

> "and a refused send answers the inviter with a modal error that names no user, no role and
> no reason ⚠ A6"

**Did:** three attempts to make a send fail from the wizard: (a) sending to an address that
gained an account with the invited role between the details step and the send; (b) sending
with the composer's Subject field emptied; (c) a second send to an address that already had a
pending row.

**Appeared:** all three succeeded — "Invitation Sent — <address> has been invited to new role
in OJS…". I could not arrange a refused send through the screens in this session.

**Verdict: undetermined** (the claim rests on probe-B's observation; nothing here contradicts
it).

### C21 — "Two display defects are expected on these screens"  ❗

> "Two display defects are expected on these screens until fixed: the step list's
> screen-reader name and the acceptance flow's error banner show untranslated raw keys …"

**Did:** read the acceptance flow's existing-user (single-step) page on OJS and OPS.

**Appeared:** besides the step-list key, the step counter renders the **template placeholder
itself**: the visible text is "1/1 steps" followed by a control whose text is literally
`{$current}/{$total} steps`. It appears on the single-step (existing-user) page in both apps;
the three-step newcomer page does not show it.

**Verdict: wrong** on the count — a QA reader told to expect two untranslated texts finds a
third on the same screen.

---

## Side effects

### C22 — The invitation email itself

> "**Invitation email** — sent when the inviter presses 'Invite user to the role'. Subject
> 'You are invited to new roles'; the body names the inviter and the journal, lists the
> invited roles with their start date and masthead sentence, and carries 'Accept Invitation'
> and 'Decline Invitation' buttons."

**Did:** sent an invitation as `u6cc3mgr` from the wizard to `u6cc3.mail@example.org` (role
Section editor, start date 2026-08-05, "Appear on the masthead"); read the Mailpit message
scoped to that recipient.

**Appeared:** From "Mona Manager <u6cc3mgr@example.org>", Subject **"You are invited to new
roles"**; body: "Dear u6cc3.mail@example.org, In light of your expertise, you have been
invited by **Mona Manager** to take on new roles at **U6 CC3 Journal**…", a "Newly assigned
roles" list — "1. Section editor / Starting from 2026-08-05 / Your name will appear in the U6
CC3 Journal's masthead as a Section editor." — and two buttons linking to
`/u6cc3ojs/invitation/accept?id=…&key=…` and `…/decline?id=…&key=…`.

**Verdict: holds.**

### C23 — Who the email greets, and the "Already assigned roles" section

> "An existing user is greeted by name and gets an 'Already assigned roles' section listing
> their current roles; a newcomer is greeted by their email address, even when the inviter
> typed a name ⚠ A11."

**Did:** the newcomer send above was made **with** Given Name "Mia" and Family Name
"Mailtest" typed into the wizard. Then invited the existing user `u6cc3oth` (holds Reader) to
Author and read that email.

**Appeared:** newcomer email opens "Dear u6cc3.mail@example.org," despite the typed name (the
typed name did reach the acceptance flow — its details step was pre-filled "Mia"/"Mailtest").
Existing-user email opens "Dear Otto Other," and carries "**Already assigned roles** 1. Reader
/ Starting from 2026-07-28 / Your name will not appear in U6 CC3 Journal's masthead as a
Reader." above "**Newly assigned roles** 1. Author …".

**Verdict: holds.**

### C24 — The inviter can adjust the text before sending

> "The inviter can adjust the text on the wizard's last step before sending."

**Did:** on the composer step (`input#userInvited-subject` pre-filled "You are invited to new
roles"; body in a rich-text editor) replaced the subject with "U6CC3 edited subject" and typed
"U6CC3 EDITED BODY LINE." into the body, then sent.

**Appeared:** the delivered message carried the edited subject and contained the typed body
line.

**Verdict: holds.**

### C25 — Where the template is edited

> "The journal-wide template is edited under *Emails management* (spec pending) {OJS OMP}: a
> name, subject and body form, with no path to an alternate template for this email in any
> app."

**Did:** as `u6cc3mgr`: Settings → Workflow → Emails → link "Add and edit templates" →
`/u6cc3ojs/management/settings/manageEmails`; searched "invit"; opened the row's
"Edit User Invited to Role Notification" button.

**Appeared:** the list carries a row "User Invited to Role Notification — This email is sent
to users that are invited to obtain certain roles" with a single "Edit" action (search finds
it). The editor exposes `#editEmailTemplate-name-control-en` = "User Invited to Role
Notification", `#editEmailTemplate-subject-control-en` = "You are invited to new roles", and a
rich-text body. Nothing on the screen offers to add a template ("Add Template" appears
nowhere). OMP not checked (claim is app-marked {OJS OMP}).

**Verdict: holds** for OJS.

### C26 — The OPS gap

> "OPS's own email-templates screen lists no row for this email — neither search nor the group
> filter finds one — so on OPS the template cannot be edited at all, though sending, delivery
> and acceptance work there as everywhere ⚠ OPS1."

**Did:** on OPS (`manager.maya`, read-only) opened
`/publicknowledge/management/settings/manageEmails`, read the whole unfiltered list, then
typed "invit" into "Search by name or description". Separately, on my own OPS server
`u6cc3ops`, drove a seeded invitation's accept link to completion (C6).

**Appeared:** the unfiltered OPS list contains no row whose name or description mentions
"invit" or "Invitation"; the search returns "No items found." The same search on OJS returns
the row (positive control). On OPS the acceptance completed and assigned the role.

**Verdict: holds.**

### C27 — Account creation

> "**Account creation** — accepting as a newcomer creates the account with the username,
> password and details entered in the flow."

**Did:** accepted as `u6cc3nadia` (username, password, Given Name "Nadia", Family Name
"Newcomer", Country Canada); then signed in on the journal's login form with exactly those
credentials.

**Appeared:** sign-in succeeded and the users table lists "Nadia Newcomer /
u6cc3.new1@example.org".

**Verdict: holds.**

### C28 — Role assignment, and a future start date

> "**Role assignment** — accepting attaches every invited role, with its start date and
> masthead choice (a passed start date becomes today). A role whose start date is still ahead
> is attached but not yet active: its holder is refused that role's screens until the date
> arrives."

**Did:** accepted the invitation whose Section-editor start date was 2026-08-05 (account
`u6cc3mia`, today 2026-07-28); signed in as that user and typed
`/u6cc3ojs/dashboard/editorial`, `/u6cc3ojs/submissions` and
`/u6cc3ojs/management/settings/access`. Positive control: `u6cc3nadia`, same role, start date
today.

**Appeared:** the users table shows `Mia Mailtest / Section editor / 2026-08-05` — attached.
All three addresses redirected to `/u6cc3ojs/user/authorizationDenied…`: "The current role
does not have access to this operation." The control user reached the Editor Dashboard.

**Verdict: holds.**

### C29 — Immediate role changes

> "**Immediate role changes** — the wizard's details step can end an existing user's role or
> change their masthead display before anything is sent (Rule 9)"

**Verdict: holds** — same evidence as C1/C2.

### C30 — Nightly cleanup

> "**Nightly cleanup** — a daily background job permanently deletes expired invitations
> (Rule 2)."

**Did:** nothing could be driven — the scheduled task runner is off on this install (stated
install fact). Supporting observation: the seeded already-expired invitation is still
resolvable — its link renders "Invitation Unavailable" rather than the not-found page —
i.e. the record is still there, consistent with a cleanup that never ran.

**Verdict: undetermined.**

### C31 — No mail on the other exits

> "**No mail on the other exits** — cancelling, declining, and expiry send no email to the
> invitee or the inviter."

**Did:** with a proper control in each case — an invitation whose *invitation* email had
already arrived in Mailpit. (a) Cancelled `u6cc3.canc@example.org` from the Invitations table
("Invitation management options" → "Cancel Invite" → "Cancel Invitation"). (b) Declined
`u6cc3.edit@example.org` from its emailed decline link. Counted every `u6cc3*` message before
and after.

**Appeared:** the total was unchanged in both cases — no message to the invitee, none to
`u6cc3mgr@example.org`.

**Verdict: holds for cancelling and declining; undetermined for expiry** (nothing fires at
expiry on this install, so there is no event to observe).

---

## Settings that modify behavior

### C32 — Invitation validity

> "**Invitation validity (server configuration)** — how many days an invitation stays valid; 3
> unless the server's operator changes it. Not a screen setting: it lives in the server's
> configuration file."

**Did:** read the expiry the app itself stamped on freshly sent invitations; swept all five
journal settings screens (`.../settings/context`, `website`, `workflow`, `distribution`,
`access`) as the manager for any mention of "expir", "validity" or an invitation/day control.

**Appeared:** every invitation sent at 17:50 on 2026-07-28 carried expiry 2026-07-31 — exactly
3 days. No settings screen mentions expiry or validity anywhere. (The fleet's
`config.test.inc.php` carries no `[invitations]` section, so the shipped default of 3 applies.)

**Verdict: holds.**

### C33 — ORCID enabled on the journal

> "**ORCID enabled on the journal** — adds the optional ORCID field to the invite wizard's
> details step and the 'Verify ORCID iD' step to the acceptance flow"

**Verdict: undetermined** — with ORCID unconfigured, both surfaces are absent as the claim
implies (C5, C7), but the enabling half cannot be watched here.

### C34 — The journal's form languages

> "**The journal's form languages** — decide which languages the name fields and the email
> composer offer."

**Did:** on `u6cc3ojs2` (supported locales en + fr_CA, French **not** a form language at
first) opened the invite wizard's details step and the composer — only `-en` fields, no
language control. Then, as the manager, Settings → Website → Setup → Languages, ticked French
in the **Forms** column (`input[id^="select-cell-fr_CA-formLocale"]`, via its label), and
re-opened the wizard.

**Appeared:** afterwards the details step renders "French English" locale tabs, controls
`#userDetails-givenName-control-en` **and** `-fr_CA` (plus the same for Family Name) and a
"0/2 languages completed" note; the composer step gained a "Switch to French" control.

**Verdict: holds.**

### C35 — Roles configuration

> "**Roles configuration** — the roles on offer in the wizard are the journal's configured
> roles …, including the 'Permit changes to Settings' option on a manager-level role that
> decides whether its holders reach Users & Roles at all…. OPS ships no manager-level role
> offering that option."

**Did:** compared the wizard's "Select a new role" list with the Roles tab
(Users & Roles → Roles) on `u6cc3ojs`. Then tried to open a role's own form from that tab.

**Appeared:** both lists are the same 18 roles, in the same order (Journal manager, Journal
editor, Production editor, Section editor, Guest editor, Copyeditor, Designer, Funding
coordinator, Indexer, Layout Editor, Marketing and sales coordinator, Proofreader, Author,
Translator, Reviewer, Reader, Subscription Manager, Editorial Board Member) — "1 - 18 of 18
items". The per-row "Settings" control (`a` named "Settings", 18 of them) produced **no
dialog and no navigation** in three attempts, so I could not see the role form or its "Permit
changes to Settings" option from the screen.

**Verdict:** first half **holds**; the "Permit changes to Settings" clause and its OPS
counterpart are **undetermined here** (they are chunk 1's cross-app permission territory, and
footnote *a* already rests on probe A). The inert "Settings" link is an incidental
observation on *Roles configuration*'s own screen, not a claim of this spec.

---

## Cross-feature interactions — is it visible on the other feature's screen?

### C36 — *Users management*

> "owns the users table that shares the Users tab, its 'Edit' action that opens this wizard
> preloaded (Rule 4b), and the end-role / masthead mechanics the details step drives (Rule 9)."

**Did:** Users & Roles → Current Users → row menu (aria-label `##userAccess.management.options##`)
→ "Edit".

**Appeared:** the menu offers Edit / Email / Login As / Remove User / Disable User / Merge
user; "Edit" lands on `/u6cc3ojs2/en/management/settings/user/347` — the invitation wizard in
two-step form ("1 Enter details / 2 Review & invite for roles"), preloaded with the person's
identity and current roles, with the "Remove Role" and masthead controls of Rule 9. Visible
as claimed.

**Verdict: holds.**

### C37 — *Roles configuration*

> "defines the roles the wizard can offer and the settings-access permission the Actors table
> leans on."

**Verdict:** the roles half **holds** (C35); the settings-access permission is
**undetermined** here for the reason in C35.

### C38 — *Emails management*

> "owns the journal-wide editing of the invitation email template ⚠ OPS1"

**Verdict: holds** — visible on that screen on OJS, absent on OPS (C25, C26).

### C39 — *Registration & account validation*

> "self-registration is the other door into a journal; the 'Register' button on 'Invitation
> Unavailable' leads there. Invited newcomers skip registration entirely."

**Did:** clicked "Register" on the Invitation Unavailable page; separately compared the
acceptance flow with the registration form.

**Appeared:** "Register" lands on `/u6cc3ojs/user/register`, the journal's own registration
form (Given Name, Affiliation, Country, Email, Username, Password, Repeat password, privacy
options). An invited newcomer never sees that form — the acceptance flow creates the account
from its own three steps (C27).

**Verdict: holds.**

### C40 — *User profile*

> "confirming a changed profile email rides the same emailed-link landing pages, with its own
> flow; only the landing surface is shared (this spec's Rule 13 page belongs to whichever
> invitation the link names)."

**Did:** signed in as `u6cc3oth`, Profile → Contact tab, changed Email to
`u6cc3.otto2@example.org`, Saved; read the resulting mail; opened its confirm link signed out;
then opened its reject link.

**Appeared:** the screen answered "You have requested a change of your email to
'u6cc3.otto2@example.org'. We have already sent you an email with directions on how to
validate the changed email." The mail (subject "Confirm account contact email change
request", sent to the **old** address) carries links at exactly this feature's addresses —
`/u6cc3ojs/invitation/accept?id=483&key=…` and `…/invitation/decline?id=483&key=…`. Opening
the accept link confirmed the change and landed on
`/u6cc3ojs/login?source=%2Findex.php%2Fu6cc3ojs%2Fuser%2Fprofile%2Fcontact` — its own flow,
none of this spec's pages. Opening the (now spent) decline link rendered this spec's
"Invitation Unavailable". The change really took: the wizard later showed the user's email as
`u6cc3.otto2@example.org`.

**Verdict: holds.**

### C41 — *Reviewer's review*

> "the reviewer one-click access link rides the same landing machinery; its behavior is that
> spec's."

**Did:** could not arrange a reviewer one-click link through the screens within this chunk's
budget (it needs a submission in review, a reviewer invited through the UI, and the journal's
one-click access setting). Code anchor only: `reviewerAccess` is one of four invitation types
alongside `userRoleAssignment`, `changeProfileEmail` and `registrationAccess`
(`lib/pkp/classes/invitation/invitations/`), all served by the same accept/decline handler —
the same shape the profile-email case demonstrated live in C40.

**Verdict: undetermined** (not screen-verified; nothing observed contradicts it).

### C42 — *ORCID integration*

> "the verification window the 'Verify ORCID iD' buttons open."

**Verdict: undetermined** — ORCID is unconfigured, so no such button renders (C5, C8).

---

## Proposed content for files this report must not write

Nothing is proposed for `PROGRESS.md`, atlas files or `docs/e2e/app-changes.md`: this chunk
produced no build blocker and no app-code change. The four **wrong** verdicts (C12/C19 the
banner-vs-inline placement, C21 the "two display defects" count, plus the wording nits noted
in C9 and C15) are spec corrections for the merge agent, not entries anywhere else.

## Incidental observations (context, not claims of this chunk)

- The Invitations table shows **blank Name and Email cells** for a pending invitation whose
  invited address has since gained an account (three such rows in my journal). Rule 3 belongs
  to another chunk; recorded here because a QA reader following scenario 1 could hit it.
- The users-table row menu's accessible name is the raw key
  `##userAccess.management.options##` (Users management's screen), and the app header renders
  `##common.help##` on every signed-in screen — both outside this spec.
- Each pass through the wizard's composer step creates a fresh invitation record before
  sending (visible as repeated add/populate traffic); only the last one is sent.
