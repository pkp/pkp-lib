---
name: user-invitations
scope: A manager invites a person into journal roles; the recipient accepts the emailed invitation (creating or linking an account) or declines it
apps: [ojs, omp, ops]
shared: pkp-lib
status: draft
atlas-claims: [AFFM-099, AFFM-100, AFFM-101, AFFM-118, AFFM-119, AFFM-120, AFFU-122, AFFU-123, AFFU-124, AFFU-125, AFFU-126, AFFU-127, AFFU-128, AFFU-129, AFFU-130, AFFU-131, AFFU-132, AFFU-133, AFFU-134, AFFU-135, AFFU-136, AFFU-137, AFFU-206, ROUTE-013, ROUTE-014, VUE-001, VUE-011, VUE-052, API-024, MAIL-055, SET-064, JOB-013, JOB-051]
---

# User invitations {OJS OMP OPS}

## How to read this file

An unmarked claim asserts the behavior is identical in OJS, OMP and OPS.
`⚠ [A1](#a1)` marks an as-built deviation with a Findings-register
entry; an intended divergence carries the same link without the ⚠
(e.g. [OPS2](#ops2)). A `{OJS OMP}` badge on a sentence limits it to
those apps.
`<sup>[a](#fn-a)</sup>` marks link to evidence footnotes at the document tail
— a reader can skip them and lose no behavior. Vocabulary is OJS's, per
`APP-GLOSSARY.md`: for OMP read *press* for journal and *Press Manager* for
Journal Manager; for OPS read *preprint server* and *Preprint Server
Manager*. Every reference a reader might follow is a link. The metadata
block at the very top of the file is machine bookkeeping, and the
Reference tables at the very end are a developer's index — a reader needs
nothing from either.

## Purpose

User invitations are how a journal's team grows without asking people to
register themselves. A Journal Manager picks a person — an existing user or
someone with nothing but an email address — chooses the role or roles they
should hold, and sends them an invitation email. The recipient clicks the
email's link and either accepts (creating a new account, or attaching the
roles to their existing one) or declines. Invitations wait in a table on the
Users & Roles screen until they are answered, edited, cancelled, or expire.

<a id="actors"></a>
## Actors & permissions

Terms used below: **inviter-side** actions live on the Users & Roles screen
(Settings → Users & Roles, "Users" tab) and the send-invitation wizard it
opens; **recipient-side** actions need no account or sign-in — they are
reached only through the personal link in the invitation email, and the link
is the credential. Each emailed link opens an `invitation/accept` or
`invitation/decline` page under the journal's own web address and carries
two values: an **id** naming the invitation, and a **key** — a long
secret code, the invitation's only credential. Opening a link does not
spend it: an abandoned acceptance leaves it usable (Rule 10), and Rule 13
defines when a link stops working — and covers links missing either
value. A **pending
invitation** is one that has been sent and not
yet accepted, declined, cancelled, or expired.

Several checks below turn on typing an address directly; each of these is
typed after the journal's own web address. The send-invitation wizard
answers at `invitation/create/userRoleAssignment`; a pending invitation's
edit wizard at `invitation/edit/` followed by the invitation's number —
the same address a pending row's "Edit" action lands on, so a manager can
read it from the browser's address bar. The Users & Roles screen answers
at `management/settings/access` (the address the menu opens) and at the
older second address `management/access` (the table's first row).

The inviter-side roles below are what the screens offer. Two more roles
(Section Editor and Assistant) are offered no way in by any screen, yet
typing the wizard's address gives a Section Editor a working wizard that
really sends, and typing a pending invitation's edit address lets either
role change and resend it — every exit then refuses them ⚠ [A8](#a8);
whether these roles are meant to invite at all is an open question
⚠ [A1](#a1).

| Action | Who may — and when |
|--------|--------------------|
| **See the Invitations table** (Users & Roles → Users) | • Journal Manager — while their role keeps the "Permit changes to Settings" option; the option can be changed only on a manager-level role other than the default one, and on OPS the condition cannot arise at all (see [Settings](#settings), [OPS2](#ops2)). Clearing it takes the whole Users & Roles screen away — but not the send wizard's own address ⚠ [A18](#a18) <sup>[a](#fn-a)</sup><br>• Site Administrator — on OJS; OMP and OPS refuse one who is not also the journal's own manager ⚠ [A4](#a4)<br>• the same screen also answers at a second, older web address that admits only Site Administrators — on OMP and OPS underneath an error dialog they must dismiss first — and refuses every manager ⚠ [A2](#a2) |
| **Invite to a role** (open the send-invitation wizard and send) | • the same actors as the table — the "Invite to a role" button sits above it <sup>[b](#fn-b)</sup> |
| **Edit a pending invitation** | • the same actors — via the row's "Invitation management options" menu, "Edit" (Rule 6)<br>• a Section Editor or an Assistant who types the invitation's edit address — offered by no screen, yet it reopens, changes and resends for real ⚠ [A8](#a8); a Reader is refused |
| **Cancel a pending invitation** | • the same actors — via the same menu, "Cancel Invite" (Rule 7) |
| **Change an existing user's current roles** (remove a role, change masthead display) from inside the wizard | • the same actors — takes effect immediately, before any invitation is sent ⚠ [A3](#a3); mechanics belong to *Users management* (spec pending)<br>• a Section Editor who types the send wizard's address — the role really ends, and leaving the wizard does not undo it ⚠ [A8](#a8) |
| **Open the acceptance page** | • whoever holds a pending invitation's emailed link — no sign-in required; a spent or expired link lands on "Invitation Unavailable" instead (Rule 13) <sup>[c](#fn-c)</sup> |
| **Accept the invitation** | • the link holder — the flow never signs anyone in: a newcomer creates an account, an existing signed-out user gets a single review step attaching the role to their account (Rule 10), and both end at the sign-in page ⚠ [A12](#a12) <sup>[d](#fn-d)</sup><br>• if someone else is signed in, acceptance is refused (Rule 12) |
| **Decline the invitation** | • the link holder — via the email's decline link and a confirmation page (Rule 11); no sign-in required |

## Fields & validation

**Send-invitation wizard** (inviter side):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Search for a user by email address, username, or ORCID iD. Enter only one to get started!" | Yes | Empty search is refused with "Provide at least one search criteria." When the search finds no role-holder in this journal (Rule 4a), only a term shaped like an email address is carried into the invitee's email field. <sup>[e](#fn-e)</sup> |
| Email address (new invitee, details step) | Yes | Pre-filled from the search when the search term was an email. An address that already belongs to an account can still be invited through this newcomer form: its accept link opens the existing user's single review step (Rule 10) and completes normally. The taken-address check bites only when the account appears after the invitation was sent — and when it does, the refusal is invisible: the review step's final button appears to do nothing ⚠ [A19](#a19). <sup>[f](#fn-f)</sup> |
| Given name / Family name (new invitee) | No | Offered in each of the journal's form languages. |
| ORCID iD (new invitee) | No | Shown only while ORCID is enabled on the journal (see [Settings](#settings)) ⚠ [A22](#a22). |
| "Select a new role" (role table, per row) | Yes | Options are the journal's own roles minus any the invitee already holds (Rule 5); a role picked on another row of the same invitation is greyed out. <sup>[g](#fn-g)</sup> |
| "Start Date" (role table, per row) | Yes | Today and dates long past are accepted, at the step and at send; nothing bounds the field. A role accepted before a future start date stays unusable until the date arrives (see [Side effects](#side-effects)). <sup>[h](#fn-h)</sup> |
| "Journal Masthead" (role table, per row) | Yes | Choice of "Appear on the masthead" / "Does not appear on the masthead". The untouched select renders empty, and "Save And Continue" refuses the row with "This field is required." under a banner counting the step's errors, until the choice is made. For Reviewer roles the row shows the fixed value "Appear on the masthead" instead of a choice. <sup>[i](#fn-i)</sup> |

**Acceptance flow** (recipient side, new users only unless noted):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| Username | Yes | Must not already be taken — a taken username is refused on the account step itself. <sup>[f](#fn-f)</sup> |
| Password | Yes | Site minimum length, stated under the field; a too-short password is refused on the step. A further check against public breach lists needs the server to reach an outside service, so it depends on the install: where the server cannot, a widely-breached password of legal length is accepted without comment. <sup>[j](#fn-j)</sup> |
| Privacy Consent | Yes | A checkbox whose label links to the journal's privacy statement. Leaving it unticked blocks every later step with "Please confirm that you have read and agree privacy statement" — and with the whole account step blank this is the only message shown: the empty username and password draw no refusal of their own until the box is ticked. <sup>[k](#fn-k)</sup> |
| Given Name | Yes | Existing users skip this form entirely (Rule 10). |
| Family Name | No | |
| Affiliation | No | |
| Country of affiliation | Yes | A dropdown; the review step lists it under this same name. |

## Rules & state

<a id="lifecycle"></a>
**Lifecycle**

1. An invitation being composed in the wizard is not yet listed anywhere.
   It becomes a **pending** invitation the moment the inviter presses
   "Invite user to the role" — that is when the email goes out — and pending
   is the only state the Invitations table shows. Accepting, declining or
   cancelling removes it from the table; each is final. <sup>[l](#fn-l)</sup>
2. Sending starts the validity clock: 3 days by default (see
   [Settings](#settings)). An expired invitation drops out of the table and
   its link stops working; a nightly background cleanup then deletes expired
   invitations for good — though what happens at the moment of expiry has
   not been watched ⚠ [A21](#a21). <sup>[m](#fn-m)</sup>
3. The Invitations table sits at the top of the Users tab, headed
   "Invitations" with a count. Columns: "Name", "Email", "Invitations",
   "Status", "Affiliation", and a per-row "Invitation management options"
   menu. The status cell always reads "Invited {date}" — the table has no
   other status wording. Five rows per page, with pagination below.
   The table keeps one row per invitee: inviting an address that already
   has a pending row replaces that row with the newest invitation's roles.
   An ORCID icon accompanies the name when the invitee has one
   ⚠ [A22](#a22).
   Once one pending invitation's address has gained an account, the table
   renders rows without their Name and Email cells — every later value
   slides one column left — and the affected rows' "Cancel Invitation"
   dialog shows an empty email; whether every row degrades or only that
   invitee's own rows was not recorded ⚠ [A20](#a20).
   <sup>[n](#fn-n)</sup>

**Sending**

4. The send-invitation wizard is headed "Invite user to take a role" and
   walks three steps: "Search User", "Enter details", and a third step
   whose tab reads "Review & invite for roles" but which is an email
   composer, not a review screen — headed "STEP 3 - Modify email shared
   with the user" here, though the panel headings' numbers follow the
   wizard's own step count, not a fixed position: in the two-step edit
   modes that omit the search step (Rules 4b and 6) the same composer is
   headed "STEP 2 - Modify email shared with the user". It offers a
   template picker, recipient fields and a pre-filled subject and body.
   The second step's tab and its panel heading also disagree with each
   other; that heading's exact text is not recorded here. Each step's
   Continue button carries its own
   label — "Search User", "Save And Continue", and finally "Invite user to
   the role". "Back" appears from the second step on; "Cancel" always;
   step tabs already visited can be clicked, later ones cannot.
   <sup>[o](#fn-o)</sup>
   - 4a. The search sorts people by whether they hold a role in *this*
     journal, not by whether they have an account. A match with a role
     here answers "The user already exists in the journal" and pre-fills
     the details step with their identity (read-only) and current roles.
     Anyone else — including someone with an account elsewhere on the
     site — gets "The user does not have a role in this journal" and the
     blank newcomer form, so the wizard offers to create identity details
     for an account that already exists. <sup>[e](#fn-e)</sup>
   - 4b. When the invitation is opened for an already-known user (from a
     pending row's "Edit", or from the users table), the "Search User" step
     is omitted and the wizard opens on "Enter details". Opened from the
     users table, the Continue button starts disabled and is enabled by
     adding a role row — "Add Another Role" enables it even while the new
     row is still blank, while editing the user's existing rows leaves it
     disabled. In this mode the page shows no heading (only the breadcrumb
     names it) and "Cancel" does nothing ⚠ [A9](#a9). <sup>[p](#fn-p)</sup>
5. The "Enter details" step's role table holds one role per row — role,
   "Start Date", "Journal Masthead", and a display-only "End Date" column
   that shows a dash for a new row and for a current role, and the date
   once a role has been ended. Composing a new invitation, the
   table opens with one blank row already in place: fill that row first.
   "Add Another Role" below adds a further row, with "Remove Role"
   beside each added row — the first row starts without one and gains it
   as soon as "Save And Continue" fails — and the step's own validation
   refuses "Save And Continue" while any row is blank — clicking "Add Another Role"
   before filling the waiting row only adds a second blank row that must
   be filled too. From the second row on the controls also lose their
   accessible names: every row reuses the first row's control
   identifiers, so a screen reader announces nothing for the later rows'
   controls, the first row's controls accumulate one more copy of each
   label per row added, and clicking a label lands in the wrong row's
   control ⚠ [A17](#a17). The role list offers this
   journal's own roles only — another journal on the same site never
   contributes options — minus the roles the invitee already holds, so an
   already-held role can never be picked and no refusal for it ever
   appears. An end-dated role returns to the list. A role already added
   on another row of the same invitation is greyed out in the list, with
   no message saying why. <sup>[g](#fn-g)</sup>
6. "Edit" on a pending row first warns: "If you edit the existing invitation
   or add a new role, the current invitation will be canceled and, a new one
   will be sent. Are you sure you want to proceed?" — confirming with "Edit
   Invitation" reopens the wizard preloaded with the invitation's content.
   The reopened page is headed with the invitee's own name — the email
   address, when the invitation carries no typed name — over "You are
   viewing {name}'s user details" — a heading about the person, not about
   inviting ⚠ [A16](#a16).
   Sending the edited invitation supersedes the first email — but its links
   land on a bare, unstyled not-found page with no journal name and no way
   back, not on "Invitation Unavailable" like every other dead link
   ⚠ [A14](#a14). <sup>[q](#fn-q)</sup>
7. "Cancel Invite" on a pending row opens a "Cancel Invitation" dialog that
   lists the invitation's email, roles, status and affiliation — nothing
   more; no sentence warns that the emailed link will stop working, though
   it does (Rule 13). Confirming with "Cancel Invitation" removes the row;
   "Cancel" backs out. <sup>[q](#fn-q)</sup>
8. Nothing tells an inviter that a user is disabled. The wizard opens for
   a disabled user and then fails twice without explanation: an error
   dialog sits over an empty role table while the role-adding controls
   stay enabled, and continuing to the composer errors again
   ⚠ [A10](#a10). <sup>[r](#fn-r)</sup>
9. Changes made on the details step to an existing user's **current** roles
   act immediately — "Remove Role" ends the role after a confirmation, and
   the masthead choice updates after a confirmation, in both cases before
   any invitation is sent ⚠ [A3](#a3). Removing a user's last remaining role
   is refused with an explanatory dialog. Role mechanics are owned by
   *Users management* (spec pending); this wizard is one more surface that
   drives them. <sup>[s](#fn-s)</sup>

**Accepting**

10. The emailed "Accept Invitation" link opens the acceptance flow. Its
    steps depend on who the invitee is:
    - **Newcomer** (no account): "Verify ORCID iD" (only while ORCID is
      enabled on the journal), then "Create OJS account" (username,
      password, Privacy Consent), then "Enter details" (name, affiliation,
      country), then "Review & create account". The last step's button
      reads "Accept And Continue to OJS" — here and in the step names,
      OMP and OPS write their own app's name. <sup>[t](#fn-t)</sup>
    - **Existing user**: "Verify ORCID iD" (only if their ORCID is not yet
      verified and ORCID is enabled), then a single review step showing the
      invited role; accepting attaches it to the existing account, without
      signing the visitor in. What an existing user whose ORCID iD is
      already verified sees is an open question ⚠ [A13](#a13).
      <sup>[t](#fn-t)</sup>
    - The ORCID step offers "Verify ORCID iD" (opens ORCID in a new window)
      and "Skip ORCID verification"; skipping loses nothing but the
      verification ⚠ [A22](#a22). With ORCID not enabled there is no ORCID step and no
      ORCID field in the send wizard, but the acceptance flow's details
      step — and the existing user's review — still show an "ORCID iD"
      line reading not verified. <sup>[u](#fn-u)</sup> <sup>[z](#fn-z)</sup>
    - Accepting assigns the invited roles and shows a success dialog —
      "You've been assigned a new role in OJS", where OMP and OPS write
      their own app's name, as the step names do — but signs nobody in: its
      "View All Submissions" button lands on the journal's sign-in page,
      not the submissions dashboard ⚠ [A12](#a12). A start date that has
      meanwhile passed becomes today. <sup>[v](#fn-v)</sup>
    - "Cancel" asks "Cancel Role Invitation Process?" — "Cancel Invitation
      Process" abandons the flow (the invitation stays pending and the
      link reusable) and also lands on the sign-in page, though the dialog
      promises a dashboard ⚠ [A12](#a12); "Go Back" returns with entered
      data intact. A refusal on the account or details step appears under
      the offending field; a refusal on the review step shows nothing at
      all — the final button appears to do nothing, while the invitation
      stays pending and its link stays live ⚠ [A19](#a19).
      <sup>[w](#fn-w)</sup>
11. The emailed "Decline Invitation" link opens a "Decline Invitation" page:
    "Are you sure you want to decline this invitation? Confirm the decline
    by clicking the button below." The "Confirm Decline Invitation" button
    marks the invitation declined and lands on the sign-in page. Declining
    needs no account and no sign-in. <sup>[x](#fn-x)</sup>
12. If someone is signed in as a **different** user than the invitation
    names, opening the accept link is refused with "Invitation not accepted.
    You're logged in as a different user.", drawn over the fully rendered
    account form. The dialog offers no close control: "Logout" — which
    signs them out to start over — is its only button, and pressing Escape
    is the only other way out. Dismissing it that way lands on the
    access-refused page ("The current role does not have access to this
    operation."), not the submissions dashboard. <sup>[d](#fn-d)</sup>
13. A link whose invitation is already accepted, declined, cancelled or
    expired lands on "Invitation Unavailable": "This invitation is no longer
    available. It may have already been accepted, declined, or expired.
    Please contact the journal manager for further assistance." — with
    "Login" and "Register", links styled as buttons, into the journal's
    own sign-in and registration pages. The wording says "journal manager"
    in OMP and OPS too ⚠ [A7](#a7). A link whose id matches no invitation
    gets the site's unstyled not-found page, with no journal name and no
    way back; a link carrying no id at all — or an id but no key — renders
    a blank page (id and key are the emailed link's two values, per
    [Actors & permissions](#actors) — strip them from a real link to stage
    these) ⚠ [A15](#a15).
    <sup>[c](#fn-c)</sup>
14. Display defects are expected on these screens until fixed: both
    wizards' step list carries an untranslated raw key as its
    screen-reader name, and the existing user's single review step shows
    the literal "{$current}/{$total} steps" beside "1/1 steps" — the
    newcomer's three-step page does not ⚠ [A5](#a5); and a refused send
    answers the inviter with a modal error that names no user, no role
    and no reason ⚠ [A6](#a6).

<a id="side-effects"></a>
## Side effects

- **Invitation email** — sent when the inviter presses "Invite user to the
  role". Subject "You are invited to new roles"; the body names the inviter
  and the journal, lists the invited roles — each with its start date and a
  sentence stating whether the role appears on the masthead (the sentence's
  exact wording is not quoted here) — and carries "Accept Invitation" and
  "Decline Invitation" buttons. An existing user is greeted by name and gets an
  "Already assigned roles" section listing their current roles; a newcomer
  is greeted by their email address, even when the inviter typed a name
  ⚠ [A11](#a11). The inviter
  can adjust the text on the wizard's last step before sending. The
  journal-wide template is edited under *Emails management* (spec pending)
  {OJS OMP}: a name, subject and body form, with no path to an alternate
  template for this email in any app. OPS's own email-templates screen
  lists no row for this email — neither search nor the group filter finds
  one — so on OPS the template cannot be edited at all, though sending,
  delivery and acceptance work there as everywhere ⚠ [OPS1](#ops1).
  <sup>[y](#fn-y)</sup>
- **Account creation** — accepting as a newcomer creates the account with
  the username, password and details entered in the flow.
- **Role assignment** — accepting attaches every invited role, with its
  start date and masthead choice (a passed start date becomes today). A
  role whose start date is still ahead is attached but not yet active:
  its holder is refused that role's screens until the date arrives.
  <sup>[h](#fn-h)</sup>
- **Immediate role changes** — the wizard's details step can end an existing
  user's role or change their masthead display before anything is sent
  (Rule 9) ⚠ [A3](#a3). Each change mails the affected user as it happens —
  one message when a role is ended, another when the masthead choice
  changes — so the window before any invitation is sent is not mail-silent.
  The confirmations, the messages and their mechanics belong
  to *Users management* (spec pending). <sup>[s](#fn-s)</sup>
- **Nightly cleanup** — a daily background job permanently deletes expired
  invitations (Rule 2) ⚠ [A21](#a21). <sup>[m](#fn-m)</sup>
- **No mail on the other exits** — cancelling and declining send no email
  to the invitee or the inviter; expiry is expected to be silent too, but
  its moment has not been watched ⚠ [A21](#a21). <sup>[y](#fn-y)</sup>

<a id="settings"></a>
## Settings that modify behavior

- **Invitation validity (server configuration)** — how many days an
  invitation stays valid; 3 unless the server's operator changes it. Not a
  screen setting: it lives in the application's own configuration file —
  the file named "config inc php", written as the three words joined by
  dots, sitting at the application's root directory — in the section
  headed `[invitations]`, as the setting named "expiration days" —
  written as the two words joined by an underscore. The shipped example
  beside it, named the same with "TEMPLATE" inserted after "config",
  already carries the section with its default; a test install may be run
  against a differently-named copy of the configuration file.
  <sup>[m](#fn-m)</sup>
- **ORCID enabled on the journal** — adds the optional ORCID field to the
  invite wizard's details step and the "Verify ORCID iD" step to the
  acceptance flow (Rules 4–5, 10); owned by *ORCID integration* (spec
  pending). None of that enabled-side behavior has been watched — ORCID
  cannot be switched on on the test install ⚠ [A22](#a22).
- **The journal's form languages** — decide which languages the name fields
  and the email composer offer.
- **Roles configuration** — the roles on offer in the wizard are the
  journal's configured roles (see *Roles configuration*, spec pending),
  including the "Permit changes to Settings" option on manager-level roles
  that decides whether their holders reach Users & Roles at all (the Actors
  table's condition). On the Roles screen — the "Roles" tab beside "Users"
  on the same Users & Roles screen — a role's own form opens by
  expanding its row and choosing "Edit" — the row's "Settings" control
  there opens nothing, an inert control that is *Roles configuration*'s
  to record. The default Journal Manager role's own form cannot be
  opened from the Roles screen in any app, so the option is changed in
  practice only on the other manager-level roles (Journal editor or
  Production editor on OJS; Press editor or Production editor on OMP). OPS
  ships no manager-level role besides the default one, so nothing there
  offers the option and the condition cannot arise [OPS2](#ops2).
  <sup>[a](#fn-a)</sup>

## Cross-feature interactions

- ***Users management*** (spec pending) — owns the users table that shares
  the Users tab, its "Edit" action that opens this wizard preloaded
  (Rule 4b), and the end-role / masthead mechanics the details step drives
  (Rule 9).
- ***Roles configuration*** (spec pending) — defines the roles the wizard
  can offer and the settings-access permission the Actors table leans on.
- ***Emails management*** (spec pending) — owns the journal-wide editing of
  the invitation email template ⚠ [OPS1](#ops1).
- ***Registration & account validation*** (spec pending) — self-registration
  is the other door into a journal; the "Register" button on "Invitation
  Unavailable" leads there. Invited newcomers skip registration entirely.
- ***User profile*** (spec pending) — confirming a changed profile email
  rides the same emailed-link landing pages, with its own flow; only the
  landing surface is shared (this spec's Rule 13 page belongs to whichever
  invitation the link names).
- ***Reviewer's review*** (spec pending) — the reviewer one-click access
  link rides the same landing machinery; what its emailed link lands on
  has not been watched here, and its behavior is that spec's.
- ***ORCID integration*** (spec pending) — the verification window the
  "Verify ORCID iD" buttons open, and the settings tab where ORCID is
  switched on; on the test install that tab's save does not stick, which
  keeps every ORCID-enabled surface here unwatched ⚠ [A22](#a22) — the
  inoperative save is that feature's defect to record.

## Canonical scenarios

Common to all three apps — run each in the app's own vocabulary and roles
(per `APP-GLOSSARY.md`), in a scratch journal with a manager-role account.

1. **Invite a newcomer, watch them join** — Journal Manager, then the
   invitee: from Users & Roles → Users, click "Invite to a role"; search for
   an email address that matches nobody ("The user does not have a role in
   this journal" appears); fill the email, then complete the role row
   already waiting in the table — pick a role, a start date of today (so
   the role is usable the moment it is accepted), and a
   "Journal Masthead" choice (the selector starts empty and the step is
   refused until it is set) — and "Save And Continue"; on "Review &
   invite for roles" click "Invite user to the role". The row appears in
   "Invitations" as "Invited {date}".
   As the invitee, open the email's "Accept Invitation" link, complete the
   account step (username, password, tick Privacy Consent), the details
   step, and click "Accept And Continue to OJS" on the review step. The
   success dialog appears; "View All Submissions" lands on the journal's
   sign-in page ⚠ [A12](#a12) — sign in with the credentials just created —
   and the manager now finds the newcomer in the users table with the
   invited role. <sup>[s1](#fn-s1)</sup>
2. **Invite an existing user to an additional role** — Journal Manager,
   then the invitee: search for the email of a user who already holds a
   role in this journal ("The user already exists in the journal" appears,
   details read-only, current roles listed); fill the waiting blank role
   row — a role they do not hold, a start date and a masthead choice —
   and send. As that user (signed out), open the
   accept link: a single review step appears, with no account or details
   step and no sign-in on their behalf. Accept; the flow ends at the
   sign-in page ⚠ [A12](#a12), and the role is attached to the existing
   account. <sup>[s2](#fn-s2)</sup>
3. **Decline an invitation** — invitee: open the email's "Decline
   Invitation" link; the "Decline Invitation" page appears; click "Confirm
   Decline Invitation". The sign-in page loads; the manager's Invitations
   table no longer lists the row; re-opening the accept link now lands on
   "Invitation Unavailable". <sup>[s3](#fn-s3)</sup>
4. **Cancel a pending invitation** — Journal Manager: on a pending row open
   "Invitation management options" → "Cancel Invite"; the dialog lists the
   invitation's details; confirm with "Cancel Invitation". The row
   disappears, and the already-delivered email's accept link lands on
   "Invitation Unavailable". <sup>[s4](#fn-s4)</sup>
5. **Edit a pending invitation** — Journal Manager: on a pending row choose
   "Edit"; the warning about cancel-and-resend appears; confirm with "Edit
   Invitation". The wizard opens on "Enter details" (no search step) with
   the invitation's content preloaded; change the role set and send. The
   table shows the new invitation; the second email's links work, while
   the first email's links now land on a bare not-found page [A14](#a14).
   <sup>[s5](#fn-s5)</sup>
6. **Open an accept link as the wrong user** — any signed-in user who is
   not the invitee: paste the accept link into the address bar. The page
   answers "Invitation not accepted. You're logged in as a different user."
   with a "Logout" button; logging out and re-opening the link resumes
   the normal acceptance flow. <sup>[s6](#fn-s6)</sup>
7. **An expired invitation** — invitee: open the accept link of an
   invitation older than the validity window. To stage one without the
   full three-day wait, lower the validity setting in the configuration
   file named under [Settings](#settings), send a fresh invitation, and
   let the shortened window lapse. "Invitation Unavailable"
   appears, with "Login" and "Register" buttons that lead to the sign-in
   and registration pages; the manager's Invitations table no longer lists
   the row. <sup>[s7](#fn-s7)</sup>

App-specific:

8. **{OPS} Invite on a preprint server, end to end** — Preprint Server
   Manager, then the invitee: run scenario 1 on a preprint server. The
   wizard offers exactly five roles — Preprint Server Manager, Moderator,
   Author, Reader and Editorial Board Member — and every row shows the
   masthead selector; the email step shows a filled subject and body; the
   email arrives with both buttons, and acceptance completes. Then open the
   screen where email templates are edited (*Emails management*, spec
   pending): neither search nor the group filter finds this email's row —
   the one OPS-specific gap [OPS1](#ops1). <sup>[s8](#fn-s8)</sup>

## Findings register

Verdicts are the author's judgment (claude, 2026-07-28), unreviewed unless an
entry notes otherwise; the team settles them on spec review. Each entry
states its own basis — code reading, probe, or judgment — and a badge stays
provisional until its flow is watched live. Badges: 🐞 a defect (the
author's call) · ❓ needs a product ruling · ✅ an intended divergence.
Impact is one word for how the deviation shows itself: **user-visible** —
met on screen in ordinary use, and consequential for the person using it;
**minor** — met on screen too, but small in consequence (consequence, not
visibility, divides these two); **latent** — surfaces only in corners not
ordinarily exercised, or not yet watched. Sorted 🐞 → ❓ → ✅;
each entry opens with the user-observable symptom, and mechanism and
evidence live in the entry's footnote.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|------------------------------|------|--------|--------|
| [A5](#a5) | The wizards' step-list screen-reader name is a raw key, and the existing user's review step shows a literal "{$current}/{$total} steps" | 🐞 | user-visible | — |
| [A6](#a6) | A refused send answers with a modal error naming no user, no role and no reason | 🐞 | minor | — |
| [A7](#a7) | "Invitation Unavailable" tells press and preprint-server visitors to "contact the journal manager" | 🐞 | minor | — |
| [A8](#a8) | A Section Editor can send an invitation from the typed-in wizard, whose every exit then lands on an access-refused page | 🐞 | user-visible | — |
| [A9](#a9) | Opened from the users table, the wizard page has no heading and its "Cancel" does nothing | 🐞 | minor | — |
| [A10](#a10) | Inviting a disabled user opens the wizard, then fails twice with unexplained errors | 🐞 | user-visible | — |
| [A11](#a11) | The invitation email greets a newcomer by their email address, not the name the inviter typed | 🐞 | minor | — |
| [A12](#a12) | Accepting an invitation ends at a sign-in page; the invitee is never signed in | 🐞 | user-visible | — |
| [A14](#a14) | A superseded invitation email's links land on a bare not-found page, not "Invitation Unavailable" | 🐞 | user-visible | — |
| [A15](#a15) | An invitation link missing its invitation or its key renders a blank page | 🐞 | minor | — |
| [A16](#a16) | The edit-invitation page is headed with the invitee's own name, not anything about inviting | 🐞 | minor | — |
| [A17](#a17) | The role table's rows share one set of control names — later rows are nameless to a screen reader | 🐞 | user-visible | — |
| [A18](#a18) | Clearing a role's settings permission removes Users & Roles but not the typed-in send wizard | 🐞 | user-visible | — |
| [A19](#a19) | A refused acceptance on the review step shows nothing — the final button appears to do nothing | 🐞 | user-visible | — |
| [A20](#a20) | Invitations-table rows lose their Name and Email cells once a pending address has gained an account | 🐞 | user-visible | — |
| [A1](#a1) | Section Editors and Assistants are admitted by the invitation machinery but offered no way in by any screen | ❓ | latent | — |
| [A2](#a2) | The older address to Users & Roles admits only Site Administrators and refuses every manager — the opposite of the menu's address | ❓ | user-visible | — |
| [A3](#a3) | The invite wizard removes an existing user's role immediately, before any invitation is sent | ❓ | user-visible | — |
| [A4](#a4) | A Site Administrator who is not also the journal's manager is refused Users & Roles on OMP and OPS while OJS admits them | ❓ | user-visible | — |
| [A13](#a13) | Whether an existing user with a verified ORCID iD sees any acceptance page at all is unwatched | ❓ | latent | — |
| [A21](#a21) | What happens at an invitation's expiry — the nightly deletion and its silence — is unwatched | ❓ | latent | — |
| [A22](#a22) | Everything the spec says of an ORCID-enabled journal is unwatched — ORCID cannot be switched on here | ❓ | latent | — |
| [OPS1](#ops1) | The invitation email has no row on OPS's email-templates screen, so its template cannot be edited there | ❓ | user-visible | — |
| [OPS2](#ops2) | OPS's only manager-level role offers no "Permit changes to Settings" option — the settings gate cannot be exercised | ✅ | latent | — |

### All apps

<a id="a1"></a>
**A1 — Two roles are let in by the machinery but not by any screen** · ❓ · latent.
The invitation wizard and its whole sending machinery admit four roles —
Site Administrator, Journal Manager, Section Editor, Assistant — but the
only screen offering the "Invite to a role" button is gated to the first
two. A Section Editor who types the wizard's address gets the full wizard,
the journal's full role list included, and can send; an Assistant is
stopped at the create wizard's search step — but the edit page, which has
no search step, admits both roles fully. What they hit on the way out is
its own defect ⚠ [A8](#a8). Question: are Section Editors and Assistants
meant to send role invitations? Lean: the screen gate is the intent and
the machinery's wider list is a leftover.
Basis: probe. <sup>[f-a1](#fn-a1)</sup>

<a id="a2"></a>
**A2 — The two doors to Users & Roles admit opposite sets of people** · ❓ · user-visible.
The Users & Roles screen answers at two addresses (both named under
[Actors & permissions](#actors)): the settings one the
menu links to, and an older direct one. The older address admits a Site
Administrator everywhere — including OMP and OPS, where the normal address
refuses that same person ⚠ [A4](#a4) — and refuses every manager
everywhere. On OMP and OPS it is the only door a Site Administrator has to
the Invitations table — and there it opens underneath an error dialog
reading "The current role does not have access to this operation.":
dismissing the dialog leaves a fully working screen, "Invite to a role"
included, while OJS shows no dialog at all — a reader meeting the dialog
first can take it for the refusal itself. Question: should the older
address share the normal guard, or go? Lean: one screen should have one
guard.
Basis: probe. <sup>[f-a2](#fn-a2)</sup>

<a id="a3"></a>
**A3 — Role removal acts before the invitation exists** · ❓ · user-visible.
On the wizard's "Enter details" step, "Remove Role" beside an existing
user's current role — and the masthead choice — take effect the moment
their confirmation is accepted, while every surrounding word still reads as
composing an invitation that has not been sent — abandoning the wizard even
asks "Are you sure want to cancel this invitation?". An inviter who backs
out of the wizard has still removed the role: both changes survive the
wizard being abandoned unsent, and nothing on the step says they act at
once. Question: should current-role edits made inside an unsent invitation
act immediately? Lean: as-built is defensible (the confirmations are
explicit) but the wizard framing invites the mistake; the team should rule.
Basis: probe (the behavior); the ruling is judgment.
<sup>[f-a3](#fn-a3)</sup>

<a id="a4"></a>
**A4 — Site Administrator access to the screen differs per app** · ❓ · user-visible.
On OJS a Site Administrator opens Users & Roles from the menu; on OMP and
OPS the same person is refused with "The current role does not have access
to this operation." The divergence hides on a stock install because
creating a journal enrols its creator as the journal's manager, and that
hat admits them everywhere. Question: is the OMP/OPS refusal intended?
Lean: unintended drift — the three apps assign the same operations
inconsistently. Basis: probe. <sup>[f-a4](#fn-a4)</sup>

<a id="a5"></a>
**A5 — Wizard texts exist in no language** · 🐞 · user-visible.
The step list at the top of both wizards carries an accessible name whose
text is defined in no language file anywhere — a screen-reader user hears
a raw technical key where the step list's name should be, in all three
apps — and the existing user's single review step shows the literal
"{$current}/{$total} steps" beside its "1/1 steps" count, in all three
apps; the newcomer's three-step page does not. No error banner appears on
the acceptance flow: refusals there surface under the offending field,
and a review-step refusal shows nothing at all ⚠ [A19](#a19). Expected:
real text in every shipped language.
Basis: probe. <sup>[f-a5](#fn-a5)</sup>

<a id="a6"></a>
**A6 — A refused send answers with an unexplained modal error** · 🐞 · minor.
When a send is refused, the inviter gets a modal error that names no
user, no role and no reason — it prints an internal address containing
"null" — and no warning banner and no inline note appear anywhere on the
step. A later check could not stage the refusal again — three sends aimed
at failing all went through — so the modal stands on its one earlier
sighting; not managing to repeat it is not evidence it is gone. Expected:
the refusal says what was refused and why, on the step
where it happened. Basis: probe. <sup>[f-a6](#fn-a6)</sup>

<a id="a7"></a>
**A7 — "Contact the journal manager", says the press** · 🐞 · minor.
The "Invitation Unavailable" page's explanation ends "Please contact the
journal manager for further assistance." in every app: a press or preprint
server shows the same sentence, naming a role and a context those readers
do not have. Expected: the wording follows the app's vocabulary like the
rest of the flow does. Basis: probe. <sup>[f-a7](#fn-a7)</sup>

<a id="a8"></a>
**A8 — A refused role's wizard sends for real, then every exit turns them away** · 🐞 · user-visible.
A Section Editor who reaches the wizard by its address can search, enter
details, compose and send a real invitation — offered the journal's full
role list, manager-level roles included — and then has nowhere to stand:
"Cancel" and the success dialog's "View All Users" both land on "The
current role does not have access to this operation." An Assistant is
stopped at the create wizard's search step by an Error dialog carrying
the same sentence — but a pending invitation's edit address, whose wizard
has no search step, admits both roles fully: either can change it and
send a fresh invitation email that supersedes the first. The details
step's "Remove Role" works for them too: a Section Editor can end another
user's existing role for real, and leaving the wizard does not undo it
(Rule 9's immediate action ⚠ [A3](#a3), driven by a role no screen lets
in). A
Reader is refused everywhere. Expected: a role the screens refuse is
refused at the wizard's door — not after sending, and not with dead-end
exits. Basis: probe. <sup>[f-a8](#fn-a8)</sup>

<a id="a9"></a>
**A9 — The edit-user wizard has no heading and a dead Cancel** · 🐞 · minor.
Opened from the users table, the wizard page carries no heading — only
the breadcrumb says where the inviter is — and pressing "Cancel" produces
no dialog and no navigation: the button does nothing. Expected: a visible
page title and a working Cancel, as the invite-mode wizard has.
Basis: probe. <sup>[f-a9](#fn-a9)</sup>

<a id="a10"></a>
**A10 — Inviting a disabled user fails twice, unexplained** · 🐞 · user-visible.
Nothing tells the inviter the user is disabled. The wizard opens with an
"Error — The requested resource was not found." dialog over an empty role
table while the role-adding controls stay enabled; continuing reaches the
email composer and errors again, printing an internal address containing
"null". Expected: the wizard says the user is disabled and switches the
controls off. Basis: probe. <sup>[f-a10](#fn-a10)</sup>

<a id="a11"></a>
**A11 — The invitation email greets a newcomer by their email address** · 🐞 · minor.
A newcomer's invitation email opens by greeting them with their email
address, even when the inviter typed a given and family name into the
wizard; an existing user is greeted by name. Expected: the typed name is
used when there is one. Basis: probe. <sup>[f-a11](#fn-a11)</sup>

<a id="a12"></a>
**A12 — Acceptance ends at a sign-in page, not in the app** · 🐞 · user-visible.
Accepting completes but never signs the invitee in: the success dialog's
"View All Submissions" button lands on the journal's sign-in page — a
newcomer is left at a login form with an account they just created.
Abandoning mid-flow with "Cancel Invitation Process" lands on the same
page, though that dialog promises a dashboard. Expected: the buttons land
where they say — signed in, on the submissions dashboard. Basis: probe.
<sup>[f-a12](#fn-a12)</sup>

<a id="a13"></a>
**A13 — What a verified-ORCID existing user sees is unwatched** · ❓ · latent.
The acceptance flow reads as accepting immediately — no steps, no page —
for an existing user whose ORCID iD is already verified, but the case
could not be arranged: ORCID integration is not configured on a stock
install, so no account there carries a verified iD. Question: does
such a user get the review step, or no page at all? Lean: the zero-step
acceptance is real — the flow submits as soon as it has no steps to show.
Basis: code reading — the settling observation needs an install with
ORCID configured. <sup>[f-a13](#fn-a13)</sup>

<a id="a14"></a>
**A14 — A superseded email's links land on a bare not-found page** · 🐞 · user-visible.
After an inviter edits and resends an invitation, the first email's links
render a bare, unstyled not-found page — no journal name, no explanation,
no way back — while decline, cancellation and expiry all give the recipient
"Invitation Unavailable" with Login and Register. Expected: a superseded
link lands on "Invitation Unavailable" like every other dead link.
Basis: probe. <sup>[f-a14](#fn-a14)</sup>

<a id="a15"></a>
**A15 — A link missing its invitation or its key renders a blank page** · 🐞 · minor.
An invitation link that matches no invitation gets the site's unstyled
not-found page, with no journal name and no navigation; one that names no
invitation at all — or names an invitation but carries no key — renders a
page with nothing on it. Expected: all of these say
what happened and offer a way onward, as "Invitation Unavailable" does.
Basis: probe. <sup>[f-a15](#fn-a15)</sup>

<a id="a16"></a>
**A16 — The edit-invitation page is headed with the invitee's name** · 🐞 · minor.
Opened at its own address, the edit-invitation wizard is headed with the
invitee's own name — the email address, when the invitation carries no
typed name — over "You are viewing {name}'s user details" — a heading
about viewing a person, on a page whose work is editing an invitation. This is not the edit-user mode's missing heading ⚠ [A9](#a9):
that page shows no heading at all, this one shows the wrong one.
Expected: a heading that names the inviting work, as the create-mode
wizard's does. Basis: probe. <sup>[f-a16](#fn-a16)</sup>

<a id="a17"></a>
**A17 — Role-table rows share one set of control names** · 🐞 · user-visible.
Every row of the wizard's role table reuses the first row's control
identifiers: from the second row on, the role, start-date and masthead
controls carry no accessible name — a screen reader announces nothing
for them — while the first row's controls accumulate one more copy of
each label per row added, and clicking any label lands in the wrong
row's control. Adding a second role is therefore close to unworkable
without sight of the screen. Expected: each row's controls carry their
own names and labels. Basis: probe. <sup>[f-a17](#fn-a17)</sup>

<a id="a18"></a>
**A18 — Clearing settings access closes the screen but not the wizard** · 🐞 · user-visible.
Clearing a manager-level role's "Permit changes to Settings" takes the
whole Users & Roles screen away from its holders — yet the send-invitation
wizard still answers at its own address: the person who can no longer
open the Invitations table is offered the full invitation flow by typing
the wizard's address {OJS OMP}. On OPS the condition cannot arise, because
no role offers the option [OPS2](#ops2). Expected: the option gates both
inviter-side surfaces, the wizard along with the table.
Basis: probe. <sup>[f-a18](#fn-a18)</sup>

<a id="a19"></a>
**A19 — A refused acceptance on the review step shows nothing** · 🐞 · user-visible.
When the server refuses an acceptance at the review step — as it does
when the invited address has meanwhile gained an account — the final
button appears to do nothing: no message, no navigation, no change on
screen. The invitation stays pending and its link stays live, so the
invitee has no way to tell the acceptance failed or why. Refusals on the
account and details steps, by contrast, appear under the offending
field. Watched on OJS. Expected: the review step says what was refused,
as the earlier steps do. Basis: probe. <sup>[f-a19](#fn-a19)</sup>

<a id="a20"></a>
**A20 — Invitations-table rows lose their Name and Email cells** · 🐞 · user-visible.
Once one pending invitation's address has gained an account, the
Invitations table renders its rows without the Name and Email cells —
every later value slides one column left, under the wrong headings — and
the same rows' "Cancel Invitation" dialog shows an empty email. Watched
on OJS; the table in a journal without such an invitation renders all
six cells. Whether every row in the affected journal's table degrades,
or only the invitee's own rows, was not recorded. The state arises when
a pending invitation's address gains an account before the invitation is
answered — the same state behind the review step's silent refusal
⚠ [A19](#a19). Expected: every row renders every cell,
whatever state its invitee's address is in. Basis: probe. <sup>[f-a20](#fn-a20)</sup>

<a id="a21"></a>
**A21 — What happens at an invitation's expiry is unwatched** · ❓ · latent.
The nightly deletion of expired invitations, and the absence of any email
when an invitation expires, could not be watched: the test install does
not run scheduled background tasks, so the moment of expiry never plays
out there — an install fact, not a refusal by the apps. What surrounds it
was watched and holds: the expired row leaves the table, its link stops
working, and cancelling and declining send no mail. Question: on an
install whose scheduled tasks run, is the expired invitation gone the
following day, with no message to invitee or inviter? Lean: yes to both —
as read, the cleanup deletes and composes no message, and nothing watched
contradicts it. Basis: code reading — the settling observation needs an
install whose scheduled tasks run. <sup>[f-a21](#fn-a21)</sup>

<a id="a22"></a>
**A22 — Everything behind "ORCID enabled" is unwatched** · ❓ · latent.
The wizard's ORCID field, the acceptance flow's "Verify ORCID iD" step and
what it offers, the Invitations table's ORCID icon, and what a
verified-ORCID existing user sees (⚠ [A13](#a13)) all describe a journal
with ORCID enabled — and none of it could be watched, because ORCID cannot
be switched on on the test install: the integration is unconfigured, and
on the Users & Roles ORCID tab, ticking "Enable ORCID functionality" and
saving does not stick — the box is clear again on a fresh load of the tab.
That inoperative save is *ORCID integration*'s defect to record (spec
pending); here it is an install fact, not an impossibility. The disabled
half holds as watched: with ORCID off, nothing ORCID-related renders
anywhere in the flow except the "Not verified" line. Question: do the
ORCID-enabled surfaces behave as described on an install with ORCID
configured? Lean: yes — the descriptions read straight off the screens'
own definitions, and nothing watched contradicts them. Basis: code
reading — the settling observation needs an install with ORCID configured
and an account carrying a verified iD. <sup>[f-a22](#fn-a22)</sup>

### OPS

<a id="ops1"></a>
**OPS1 — The invitation email has no row on OPS's email-templates screen** · ❓ · user-visible.
On OPS, the screen where a manager edits email templates lists no row for
the invitation email — search and the group filter both come up empty — so
a Preprint Server Manager cannot edit the template that OJS and OMP
managers can. Everything else works: the wizard's composer offers the
template with subject and body filled, the email sends and arrives, and
acceptance completes. Question: is OPS meant to run invitations on a
template its managers cannot edit, or is the missing row an oversight?
Lean: oversight — every other screen of the flow is present on OPS.
Basis: probe. <sup>[f-ops1](#fn-ops1)</sup>

<a id="ops2"></a>
**OPS2 — The settings-permission condition cannot arise on OPS** · ✅ · latent.
The default Preprint Server Manager role is OPS's only manager-level
role, and no screen offers its "Permit changes to Settings" option — so
the Actors table's condition (a manager losing Users & Roles when the
option is cleared) can never be exercised on OPS. Intended: OPS
deliberately ships a slimmer role set (scenario 8's five roles), and in
every app the default manager role's own form cannot be opened from the
Roles screen — the option is only ever changed on the secondary
manager-level roles OPS does not have.
Basis: probe. <sup>[f-ops2](#fn-ops2)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

Draft note (2026-07-28): claims originate in code reading — the atom
inventory `.reports/u6/code-inventory.md` plus targeted reads. The
2026-07-28 probe fleets have since confirmed or corrected much of the
file; where a footnote records a live watch, that is the claim's basis,
and remaining probe pointers flag what is still unwatched. Empty subclass
chains across all three apps cover the entire
invitation machinery (inventory §2), so shared-behavior claims rest on that;
the exceptions are the two host-class overrides behind A4 and OPS1.

<a id="fn-a"></a>
**a** — Screen: `ManagementHandler::access()` (`SettingsHandler` op
`settings`, arg `access` → Users & Roles, tab "Users", heading
`navigation.access`). Guard: role assignment for the `settings` op (OJS:
`SITE_ADMIN` + `MANAGER`; OMP/OPS: `MANAGER` only — see f-a4) **plus**
`CanAccessSettingsPolicy` (site admin, or manager whose user group has
`permitSettings`). Live-watched 2026-07-28 (`.reports/u6/probe-a.md` A-3,
A-4): clearing a manager-level role's "Permit changes to Settings" removes
the screen on OJS and OMP; OPS ships no manager-level role whose option can
be edited, and its manager-slot role offers no such control anywhere.
The default manager role's own form cannot be opened from the Roles screen
in any app — the option was cleared on the secondary manager-level roles
(`.reports/u6/claimcheck-1.md` C6, settling `.reports/u6/claimcheck-3.md`
C35 and C37).

<a id="fn-b"></a>
**b** — Button: `UserInvitationManager.vue` `#top-controls`, label
`invitation.inviteToRole.btn`, rendered unconditionally to whoever reaches
the screen; click →
`invitation/create/userRoleAssignment`
(`InitializeInvitationUIHandler::create`, roles
`SITE_ADMIN/MANAGER/SUB_EDITOR/ASSISTANT` — the wider set behind A1).

<a id="fn-c"></a>
**c** — `InvitationHandler` ops `accept`/`decline`: no role assignment, no
`authorize()` override — gate is possession of a valid `id`+`key`
(`Repo::invitation()->getByIdAndKey()`: pending + unexpired +
`password_verify`). Spent/expired but key-valid →
`invitationUnavailable.tpl` (static Login/Register links, keys `user.login`
/ `user.login.registerNewAccount`); unknown id or wrong key →
`NotFoundHttpException`. `acceptInvitation.tpl` and `userInvitation.tpl`
both open with a stray literal `<?php` line before the Smarty comment —
whether anything renders on screen is unverified.

<a id="fn-d"></a>
**d** — `UserRoleAssignmentReceiveController::authorize()`: no matching
user → anonymous permitted; matching user + nobody signed in →
`Validation::registerUserSession($user)` in code, but live-watched
2026-07-28 (OJS, `.reports/u6/probe-d.md` D-26) no signed-in session
appears anywhere in the flow — no user menu, and the flow ends at the
sign-in page (f-a12); different user signed in → deny → dialog strings
`acceptInvitation.authorization.shouldBeAnonymous` / `.message`, actions →
`login/signOut` / `submissions`. The deny dialog live-watched 2026-07-28
(OJS, `.reports/u6/probe-d.md` D-27): the button renders as "Logout", and
dismissing the dialog landed on the access-refused page, not the
submissions dashboard the code targets. No close control and Escape-only
dismissal, over the fully rendered account form:
`.reports/u6/claimcheck-3.md` C14; the rendered-form backdrop seen in all
three apps, `.reports/u6/claimcheck-1.md` C16.

<a id="fn-e"></a>
**e** — `UserInvitationSearchFormStep.vue`: field label
`userInvitation.searchField`; search runs on Continue via `GET
users?searchPhrase=&status=all`; outcome strings
`userInvitation.search.userFound` / `.userNotFound` (app locale files,
journal/press/server substituted); empty search →
`invitation.searchForm.emptyError`, request skipped; an email-shaped
unmatched term is carried into `inviteeEmail`. Live-watched on OJS
(`.reports/u6/probe-b.md` B-8): the search matches role-holders in the
current journal only — an account with no role here gets the newcomer
branch.

<a id="fn-f"></a>
**f** — `UserDetailsForm`: `inviteeEmail` required. At acceptance:
`EmailMustNotExistRule`; username uniqueness enforced server-side at
finalize (`UserRoleAssignmentReceiveController::finalize()`). Live-watched
2026-07-28 (OJS, `.reports/u6/probe-d.md` D-24b): the taken-username
refusal surfaces on the account step itself, not at the end of the flow.
An address that already had an account was invited through the newcomer
form and accepted through the existing-user review step — the
`EmailMustNotExistRule` can bite only for an account created between send
and acceptance (OJS, `.reports/u6/claimcheck-2.md` C4).

<a id="fn-g"></a>
**g** — Menu source: `SendInvitationStep::getAllUserGroups()` =
`UserGroup::withContextIds([$context->getId()])` — current context only.
Live-watched on a two-journal OJS install (`.reports/u6/probe-b.md` B-15,
B-9; the held-role omission also seen on OMP and OPS): the second journal
contributes no options, held roles are omitted from the list, an
end-dated role returns, and a same-invitation duplicate is greyed with no
message.

<a id="fn-h"></a>
**h** — Start date: the rule key
`invitation.userRoleAssignment.userGroup.startDate.mustBeAfterToday`
exists in code, but live-watched behavior (OJS, `.reports/u6/probe-b.md`
B-9) accepts today and past dates at the step and at send, with no bound
on the field. At acceptance, `assignUserToGroup` clamps a meanwhile-passed
start date to today; a future-dated assignment stays inactive until its
date — the holder is refused the role's screens on acceptance day
(`.reports/u6/probe-d.md` D-23d).

<a id="fn-i"></a>
**i** — `UserInvitationUserGroupsTable.vue`: rows whose role id is
Reviewer (`pkp.const.ROLE_ID_REVIEWER`) show the static text
`invitation.masthead.show` and force masthead on; other rows get the
select (`invitation.masthead.show` / `.hidden`). Live-watched 2026-07-28
(`.reports/u6/probe-c.md` C-20, C-19a): fixed text on OJS and on both OMP
reviewer roles; OPS seeds no reviewer role — its wizard offers exactly
five roles and every row shows the selector. The untouched select renders
empty and the row fails as required under an error-count banner,
live-watched 2026-07-28 (OJS, `.reports/u6/claimcheck-2.md` C13, C14) —
correcting an earlier suite-run note that it displayed the masthead-on
option text.

<a id="fn-j"></a>
**j** — `Password::min($site->getMinPasswordLength())->uncompromised()`
(receive-controller rules). `uncompromised()` consults an external breach
service. Live-watched 2026-07-28 (OJS, `.reports/u6/probe-d.md` D-24a,
D-24c): the short-password refusal appears inline on the step; with the
test env's egress firewalled, a well-known breached password of legal
length passed silently. Field description uses
`pkp.const.MIN_PASSWORD_LENGTH`.

<a id="fn-k"></a>
**k** — `AcceptInvitationPageStore`: the flow's only client-side rule —
privacy unticked → error `acceptInvitation.privacyStatement.validation`,
server request skipped. Source FIXME: the check also blocks the ORCID
step's save. Checkbox label links `about/privacy`. Live-watched 2026-07-28
(OJS, `.reports/u6/claimcheck-2.md` C18): with the whole account step
blank, the consent message is the only refusal — the unticked box skips
the server request that would report the empty username and password.

<a id="fn-l"></a>
**l** — `InvitationStatus`: `INITIALIZED` (composing — created by the
wizard's first save) → `PENDING` (on `Invitation::invite()`, which sends
the mail and stamps expiry) → `ACCEPTED`/`DECLINED`/`CANCELLED`. Table
fetch: `GET invitations/userRoleAssignment` scoped `stillActive()` =
pending **and** unexpired — hence pending-only listing. Send chain: `POST
invitations/add/userRoleAssignment` → `PUT …/populate` → `PUT …/invite`;
cancel: `PUT …/cancel` (pending only).

<a id="fn-m"></a>
**m** — `[invitations] expiration_days` in the app-root `config.inc.php`
(shipped example `config.TEMPLATE.inc.php`, default 3); sole consumer
`Invitation::getExpiryDays()` → `setExpiryDate()` at invite time. Cleanup:
`RemoveExpiredInvitationsJob` (`InvitationModel::expired()->delete()`),
dispatched daily by `PKP\task\RemoveExpiredInvitations`
(`PKPScheduler::registerSchedules()`; app schedulers call parent —
additive overrides only). Test env runs with `task_runner=Off`, so expiry
probes should rely on the listing scope, not the deletion.

<a id="fn-n"></a>
**n** — `UserInvitationManager.vue`: heading `invitation.header`
("Invitations") + item count; columns `invitation.tableHeader.name`,
`about.contact.email`, `invitation.header`, `common.status`,
`user.affiliation`, sr-only `common.moreActions`; status cell
unconditionally `userInvitation.status.invited`; `countPerPage = 5`;
pagination wrapper `v-if itemCount > 0`; ORCID icon `v-if
invitation.existingUser?.orcid || invitation.newUser?.orcid`. Table
anatomy and the replace-on-reinvite behavior live-watched on OJS
(`.reports/u6/probe-b.md` B-6, B-13).

<a id="fn-o"></a>
**o** — `SendInvitationStep::getSteps()`: `searchUser` (only when neither
an invitation nor a user is preloaded), `userDetails`, `userInvited`. Step
names/labels/buttons: `userInvitation.searchUser.*`, `.enterDetails.*`,
`.sendMail.*` (nextButtonLabels "Search User" / "Save And Continue" /
"Invite user to the role"). Page title `invitation.wizard.pageTitle`. Step
tabs: `Steps.vue` renders a click-through button only for
`startedSteps`-listed steps. Cancel/Back: `UserInvitationPage.vue`
(`common.cancel` warnable; `common.back` `v-if !isOnFirstStep`). Composer
step and tab/heading mismatch live-watched in all three apps
(`.reports/u6/probe-b.md` B-7; `.reports/u6/probe-c.md` C-19b).

<a id="fn-p"></a>
**p** — Edit-user entry: users-table row "Edit" →
`management/settings/user/{userId}` (`ManagementHandler::editUser()`; the
row action is *Users management*'s atom AFFM-103). In this mode
`UserInvitationPageStore.isSubmitting` initialises `true` (button
disabled) and a deep watcher re-evaluates on payload change — inventory
P14. Live-watched 2026-07-28 (OJS, `.reports/u6/probe-b.md` B-10):
editing existing rows leaves Continue disabled, "Add Another Role"
enables it even while blank; only the breadcrumb names the page, and
Cancel produced no dialog and no navigation (A9).

<a id="fn-q"></a>
**q** — `UserInvitationManagerStore`: edit dialog
`userInvitation.edit.title` / `.message`, primary action label =
`userInvitation.edit.title`, redirect `invitation/edit/{id}`; cancel
dialog `invitation.cancelInvite.title` / `.message` with body
`UserInvitationManagerCancelInvitationDialogBody.vue` (read-only
email/roles/status/affiliation), confirm → `PUT invitations/{id}/cancel` +
refetch.

<a id="fn-r"></a>
**r** — Code carries a disabled-user banner
(`userInvitation.user.disableTitle` / `.disableMessage` `v-if
invitationPayload.disabled`) and control gating (`template v-if
!disabled`, "Add Another Role" `:is-disabled="disabled"`), but the live
watch (OJS, 2026-07-28, `.reports/u6/probe-b.md` B-11) saw neither: the
observed behavior is A10's two errors with the controls enabled.

<a id="fn-s"></a>
**s** — Immediate calls from `UserInvitationUserGroupsTable.vue`: `PUT
users/{userId}/endRole/{roleId}` (confirm dialog; guard
`numberOfActiveRoles <= 1` → `user.removeRole.roleRemainMessage`, single
"Close") and `PUT users/{userId}/masthead/{userUserGroupId}` (confirm
`user.masthead.update.title`) — *Users management* machinery, driven from
this wizard before any invite is sent. Each PUT also mails the affected
user — live-watched 2026-07-28 (OJS, `.reports/u6/claimcheck-3.md` C1,
C2): one message on the role end, another on the masthead change.

<a id="fn-t"></a>
**t** — `AcceptInvitationStep::getSteps()`: new user → [`verifyOrcid` when
`OrcidManager::isEnabled($context)`] + `userCreate` + `userDetails` +
`userCreateReview`; existing user → [`verifyOrcid` when unverified &&
enabled] + review. Step names: `acceptInvitation.accountDetails.stepName`
(app-substituted "Create OJS/OMP/OPS account"),
`acceptInvitation.userDetails.stepName`, `.detailsReview.stepName`; final
button `.detailsReview.nextButtonLabel`.
Existing-user branch live-watched 2026-07-28 (OJS,
`.reports/u6/probe-d.md` D-26): a single review step showing the invited
role; accepting attaches it to the account. The zero-step path —
`AcceptInvitationPageStore.receiveInvitation()` submits immediately when
the step list is empty — remains unwatched for a verified-ORCID user
(f-a13).

<a id="fn-u"></a>
**u** — `AcceptInvitationVerifyOrcid.vue`: `acceptInvitation.verifyOrcid`
opens `orcidOAuthUrl` in a popup; `.skipVerifyOrcid` jumps to the next
step bypassing the save (`refine`) request.

<a id="fn-v"></a>
**v** — `UserRoleAssignmentReceiveController::finalize()`: creates the
user (username, names, email, country, affiliation, ORCID data, password),
assigns each invited group (past `dateStart` clamped to today), marks
accepted. Success dialog `acceptInvitation.modal.title` / `.message` /
`.button`; redirect `submissions` — live-watched in all three apps
(`.reports/u6/probe-d.md` D-23d, D-32; `.reports/u6/probe-c.md` C-19d,
C-21), the dialog appears as coded but the redirect lands on the
sign-in page: no session exists (A12, f-a12).

<a id="fn-w"></a>
**w** — Cancel dialog `acceptInvitation.cancelInvite.title`,
`acceptInvitation.cancel.message`; actions `.cancelInvite.button` →
`submissions`, `userInvitation.cancel.goBack` → close. Review banner:
`v-if step.type === 'review'` — `userCreateReview` **is** `type: 'review'`
(reachable), text key `invitation.wizard.errors` (missing — A5).
Live-watched (`.reports/u6/probe-d.md` D-24e, D-32; re-driven 2026-07-28,
`.reports/u6/claimcheck-3.md` C12, C19): refusals on the account and
details steps render under the offending field — no banner surfaced —
the review step's own refusal shows nothing at all (A19, f-a19), and the
cancel action landed on the sign-in page (A12).

<a id="fn-x"></a>
**x** — `declineInvitation.tpl`: headings
`invitation.decline.confirm.title` / `.description`, submit
`invitation.decline.confirm` POSTing to `confirmDecline` (POST-only +
CSRF + pending-only; then redirect `{ctx}/login`). Plain page, not Vue.

<a id="fn-y"></a>
**y** — Mailable `UserRoleAssignmentInvitationNotify`, template key
`USER_ROLE_ASSIGNMENT_INVITATION`, seeded by all three apps'
`registry/emailTemplates.xml`; subject/body strings live only in
`lib/pkp/locale/en/emails.po` (subject
`emails.userRoleAssignmentInvitationNotify.subject`); variables include
`recipientName`, `inviterName`, `existingRoles`, `rolesAdded`,
`acceptUrl`, `declineUrl`. Sent inside `Invitation::invite()`
(`Mail::send`). Delivery live-watched in all three apps
(`.reports/u6/probe-d.md` D-23b, D-26; `.reports/u6/probe-c.md` C-19c):
subject, naming, role list with start date and masthead sentence, and
both links as coded; the existing-user "Already assigned roles" section
and the newcomer email-address greeting (A11) observed there. No mailable
is constructed on the cancel, decline or expiry paths; live-watched on OJS
(`.reports/u6/probe-d.md` D-28, D-29, D-31): no message reached invitee or
inviter on decline or cancellation.

<a id="fn-z"></a>
**z** — ORCID unconfigured on the stock test installs, all three apps:
no `verifyOrcid` step appeared in any acceptance flow and the newcomer
details step of the send wizard offered no ORCID field, while the
acceptance details step and the existing-user identity block rendered the
"ORCID iD — Not verified" line regardless (`.reports/u6/probe-d.md`
D-23c; `.reports/u6/probe-c.md` C-19d; `.reports/u6/probe-b.md` B-8).

<a id="fn-s1"></a>
**s1** — Scratch context via the scenario endpoints; Mailpit scoped by
recipient (never `clearAll()`). Invitee address must match no seeded user.
Live-run 2026-07-28 in all three apps (`.reports/u6/probe-d.md` D-23;
`.reports/u6/probe-c.md` C-19); the sign-in landing is f-a12.

<a id="fn-s2"></a>
**s2** — Use a seeded non-manager user as invitee; pick a role they do not
hold. The single review step is footnote t's existing-user branch,
live-watched 2026-07-28 (`.reports/u6/probe-d.md` D-26); the
verified-ORCID zero-step question is A13 (f-a13).

<a id="fn-s3"></a>
**s3** — Decline needs the email's own link (id+key). Record the landing
page after confirming, and the accept link's behavior afterwards.

<a id="fn-s4"></a>
**s4** — Keep the invitation email open before cancelling; drive the
link only after the cancel confirms.

<a id="fn-s5"></a>
**s5** — Record both emails' links. Live-run 2026-07-28 (OJS,
`.reports/u6/probe-d.md` D-30): the warning, the preloaded two-step
wizard, the second email and the single row listing both roles are as
drafted; the superseded pair's landing is A14 (f-a14).

<a id="fn-s6"></a>
**s6** — Sign in as any seeded user other than the invitee first; paste
the accept URL. Live-run 2026-07-28 (OJS, `.reports/u6/probe-d.md` D-27);
the dismissal path lands on the access-refused page (Rule 12).

<a id="fn-s7"></a>
**s7** — Aging: set `[invitations] expiration_days` low on the test
install, or seed a backdated invitation via a scenario endpoint; the
nightly deleter does not run in the test env (`task_runner=Off`), which is
fine — the listing scope and the link check are the assertions.

<a id="fn-s8"></a>
**s8** — OPS seeds only 5 user groups (manager, Moderator [section-editor
slot], author, reader, editorial board member — `registry/userGroups.xml`).
Live run 2026-07-28 (`.reports/u6/probe-c.md` C-19a): the dropdown offers
exactly those 5 and every row shows the masthead selector. The email-step,
delivery and Emails-screen observations are C-16–C-19 (f-ops1).

<a id="fn-a1"></a>
**f-a1** — `InitializeInvitationUIHandler` and the create-side
`InvitationController` routes admit
`SITE_ADMIN/MANAGER/SUB_EDITOR/ASSISTANT`; the hosting screen admits
admin/manager-with-`permitSettings` only (footnote a);
`UserInvitationPageStore` redirects to `management/settings/access` on
both success and cancel — a page the two extra roles cannot open.
Live-watched 2026-07-28, all three apps (`.reports/u6/probe-a.md` A-5):
the Section Editor walk, the Assistant search-step stop, and both dead-end
exits (A8) all observed.

<a id="fn-a2"></a>
**f-a2** — Old door: `/{ctx}/management/access` (op `access`) — role
assignment `SITE_ADMIN` only, in all three apps, and **no**
`CanAccessSettingsPolicy` (that policy attaches to the `settings` op
only). Normal door: `/{ctx}/management/settings/access`. Same
`ManagementHandler::access()` body. Live-watched 2026-07-28, both doors,
both roles, all three apps (`.reports/u6/probe-a.md` A-2). The OMP/OPS
error dialog over the working screen: `.reports/u6/claimcheck-1.md` C8.

<a id="fn-a3"></a>
**f-a3** — Mechanism in footnote s. The wizard's surrounding promise
("the current invitation will be canceled and a new one sent") covers the
invitation, not these immediate PUTs. Live-watched 2026-07-28 (OJS,
`.reports/u6/probe-b.md` B-12): both confirmations appear, both changes
survive abandoning the wizard unsent, and the abandon dialog itself reads
"Are you sure want to cancel this invitation?". The ruling stays a
judgment call.

<a id="fn-a4"></a>
**f-a4** — OJS `SettingsHandler::__construct` assigns `SITE_ADMIN →
['access','settings']`; OMP and OPS assign `SITE_ADMIN → ['access']` only.
The role policies sit in a `PolicySet(COMBINING_PERMIT_OVERRIDES)` — with
no assignment for the `settings` op, an unassigned site admin is denied
regardless of `CanAccessSettingsPolicy`'s admin allowance. Live-watched
2026-07-28 in all three apps (`.reports/u6/probe-a.md` A-1): OJS admits,
OMP and OPS refuse a site admin holding no manager role.

<a id="fn-a5"></a>
**f-a5** — Keys `invitation.wizard.completeSteps` (the `Steps` component's
aria label, both wizards) and `invitation.wizard.errors` (review banner
title) have no `msgid` in `lib/pkp/locale/en/invitation.po` nor in any
app's locale tree. Missing keys normally render as `##key##`. Live-watched
2026-07-28 (`.reports/u6/probe-b.md` B-7; `.reports/u6/probe-c.md` C-21):
the raw key renders on both wizards' step lists in all three apps. The
banner itself never surfaced on the re-drive — acceptance refusals render
under the offending field (`.reports/u6/claimcheck-3.md` C12, C19; f-a19).
The "{$current}/{$total} steps" literal renders beside "1/1 steps" on the
existing user's single-step page in all three apps
(`.reports/u6/claimcheck-1.md` C15; `.reports/u6/claimcheck-3.md` C21);
the newcomer's three-step page does not show it.

<a id="fn-a6"></a>
**f-a6** — `UserInvitationPage.vue` renders the banner under `template
v-if="step.type === 'review'"`, but `SendInvitationStep` produces types
`emptySection`/`form`/`email` only — the branch is dead as configured.
`AcceptInvitationPage.vue`'s copy is live (footnote w). Live-watched
2026-07-28 (OJS, `.reports/u6/probe-b.md` B-13): a refused send surfaced
as a modal error printing "null" inside an internal address — no banner
and no inline note appeared on the step. Re-driven 2026-07-28 (OJS,
`.reports/u6/claimcheck-3.md` C20): three sends arranged to fail all
succeeded — the refusal could not be staged again; the entry rests on
the B-13 watch.

<a id="fn-a7"></a>
**f-a7** — `invitation.unavailable.description` exists only in
`lib/pkp/locale/en/invitation.po`; neither OMP nor OPS overrides it (their
`locale/en/invitation.po` files carry 18 other keys). Live-watched
2026-07-28 in all three apps (`.reports/u6/probe-c.md` C-22): heading,
explanation and the Login/Register links are word-for-word identical
everywhere, "journal manager" included.

<a id="fn-a8"></a>
**f-a8** — Mechanism in footnote f-a1: the wizard's success and cancel
paths both redirect to `management/settings/access`, which these roles
cannot open. Live-watched 2026-07-28, all three apps
(`.reports/u6/probe-a.md` A-5); the Assistant's stop is an Error dialog at
the search step carrying the same refusal sentence. Edit address, role
list and role removal live-watched 2026-07-28
(`.reports/u6/claimcheck-1.md` C10, C5, C12): both roles reopened,
changed and resent a pending invitation at `invitation/edit/{id}` — no
search step stops the Assistant there, a Reader is refused — the offered
role list included manager-level options, and "Remove Role" ended another
user's role, surviving the wizard's abandonment.

<a id="fn-a9"></a>
**f-a9** — Edit-user mode (users table row "Edit" →
`management/settings/user/{userId}`, footnote p). Live-watched 2026-07-28
(OJS, `.reports/u6/probe-b.md` B-10): the invite-mode page title
(`invitation.wizard.pageTitle`, footnote o) does not render here, and
pressing Cancel produced no dialog and no navigation.

<a id="fn-a10"></a>
**f-a10** — Live-watched 2026-07-28 (OJS, `.reports/u6/probe-b.md` B-11).
The code's disabled-user banner and control gating (footnote r) never
rendered; the composer-step error printed an internal address containing
"null".

<a id="fn-a11"></a>
**f-a11** — Greeting renders the `recipientName` variable (footnote y);
for a newcomer it carried the email address even with names typed in the
wizard. Live-watched in all three apps (`.reports/u6/probe-d.md` D-23b,
D-26; `.reports/u6/probe-c.md` C-19c).

<a id="fn-a12"></a>
**f-a12** — Live-watched 2026-07-28, all three apps
(`.reports/u6/probe-d.md` D-23d, D-32; `.reports/u6/probe-c.md` C-19d,
C-21): newcomer and existing-user acceptances, and the mid-flow cancel,
all land on the journal's sign-in page; "Go Back" returns with data
intact, and after a cancel the invitation stays pending and its link
reusable. Mechanism: the success and cancel redirects target
`submissions` (footnotes v, w) but the flow never establishes a session
(footnote d).

<a id="fn-a13"></a>
**f-a13** — `AcceptInvitationPageStore.receiveInvitation()` submits
immediately when the step list is empty (footnote t) — the code-readable
zero-step acceptance. Unwatchable on the stock test install: ORCID is
unconfigured, so no seeded account carries a verified iD
(`.reports/u6/probe-d.md` D-26). Settling observation: the accept link
opened by an existing verified-ORCID user on an install with ORCID
configured.

<a id="fn-a14"></a>
**f-a14** — Live-watched 2026-07-28 (OJS, `.reports/u6/probe-d.md` D-30):
after edit-and-resend, the superseded pair of links rendered the unstyled
"404 Not Found"; the other dead-link exits (decline D-28, cancel D-29,
expiry D-31) all rendered "Invitation Unavailable". Mechanism: the
superseded links no longer resolve to a known invitation, so they fall
into footnote c's `NotFoundHttpException` branch instead of the
unavailable page.

<a id="fn-a15"></a>
**f-a15** — Live-watched 2026-07-28 (`.reports/u6/probe-d.md` D-28, D-33;
`.reports/u6/probe-c.md` C-22): an unknown `id` renders the site's
unstyled "404 Not Found" with no chrome and no navigation; a link with no
`id` at all renders a blank page, and a link carrying an `id` but no
`key` renders the same blank page in all three apps
(`.reports/u6/claimcheck-1.md` C2). Mechanism: footnote c's
`NotFoundHttpException` branch.

<a id="fn-a16"></a>
**f-a16** — Observed 2026-07-28 during the e2e suite work (OPS): the
edit wizard opened at `invitation/edit/{id}` is headed with the
invitee's name over "You are viewing {name}'s user details". The pending
row's "Edit" action redirects to the same address (footnote q), so the
same page serves both entrances; the rendering mechanism is unread. A
newcomer invitation carrying no typed name is headed with the invitee's
email address (OJS, `.reports/u6/claimcheck-2.md` C57).
Distinct from A9 (edit-user mode via `management/settings/user/{userId}`,
no heading at all).

<a id="fn-a17"></a>
**f-a17** — Observed 2026-07-28 during the e2e suite work (OJS; the
table is shared code — footnote i — so all three apps are implicated):
every `UserInvitationUserGroupsTable.vue` row repeats the first row's
input ids, so each `label[for]` resolves to the first row's control —
rows two and up expose no accessible name, the first row's accessible
name accumulates one label copy per row, and label clicks focus the
first row. The suites had to address the rows positionally instead of
by accessible name. No probe-report citation: observed in the suite run.

<a id="fn-a18"></a>
**f-a18** — Live-watched 2026-07-28 (OJS and OMP,
`.reports/u6/claimcheck-1.md` C6): with the role's `permitSettings`
cleared, the settings address refuses while
`invitation/create/userRoleAssignment` serves the full wizard. Mechanism
in f-a1: the wizard routes check role IDs only — `CanAccessSettingsPolicy`
attaches to the host screen, not to the wizard.

<a id="fn-a19"></a>
**f-a19** — Live-watched 2026-07-28 (OJS, `.reports/u6/claimcheck-3.md`
C12, C19; the same silent click `.reports/u6/claimcheck-2.md` C4): the
observed trigger is the taken-address rule — `EmailMustNotExistRule`
fires at finalize (footnote f) when the invited address gained an account
after the send — and the review step rendered no response to the final
button; the invitation stayed pending and its link stayed live. Account-
and details-step refusals rendered under the offending field. The code's
review banner (footnote w) did not surface.

<a id="fn-a20"></a>
**f-a20** — Live-watched 2026-07-28 (OJS, `.reports/u6/claimcheck-2.md`
C37; corroborated incidentally by `.reports/u6/claimcheck-3.md`): with
one pending invitation's address holding an account (f-a19's trigger
state), the table's rows rendered without Name and Email cells,
every later value shifted one column left, and the affected rows' cancel
dialog (footnote q) showed an empty email. Journals without such an
invitation rendered all six cells. Rendering mechanism unread.

<a id="fn-a21"></a>
**f-a21** — Mechanism and configuration in footnote m; the test installs
run with `task_runner=Off`, so `RemoveExpiredInvitationsJob` never fires
there. The job's body deletes expired rows and constructs no mailable.
Undetermined across the 2026-07-28 checks (`.reports/u6/claimcheck-1.md`
C27; `.reports/u6/claimcheck-2.md` C30; `.reports/u6/claimcheck-3.md`
C30, C31); cancel- and decline-silence live-watched (footnote y).

<a id="fn-a22"></a>
**f-a22** — ORCID-off half live-watched in all three apps (footnote z).
The enable attempt: OJS Users & Roles ORCID tab, "Enable ORCID
functionality" ticked and saved, clear again on reload
(`.reports/u6/claimcheck-2.md` C7). Enabled-side claims rest on the
component definitions cited in footnotes i, n, t and u; left undetermined
across `.reports/u6/claimcheck-2.md` C36 and `.reports/u6/claimcheck-3.md`
C5, C8, C33, C42. A13 (f-a13) is the same gap's existing-user corner.

<a id="fn-ops1"></a>
**f-ops1** — `ops-main/classes/mail/Repository.php::map()` re-declares the
mailable list and omits `UserRoleAssignmentInvitationNotify` (comment:
"OPS uses distinct mailables from OJS and OMP").
`ManagementHandler::manageEmails()` builds the Emails screen from
`Repo::mailable()->getMany()` — hence no row on OPS. The send path
bypasses the catalogue — `UserRoleAssignmentInvite::getMailable()` fetches
the template by key directly, and OPS's `registry/emailTemplates.xml`
seeds `USER_ROLE_ASSIGNMENT_INVITATION`. Live-watched 2026-07-28
(`.reports/u6/probe-c.md` C-16–C-19, OPS with OJS and OMP as controls):
no row on OPS by search or group filter; the row exists on OJS and OMP
and opens a name/subject/body form with no role restriction and no
alternate-template path in any app; OPS's composer, send, delivery and
acceptance all work as coded.

<a id="fn-ops2"></a>
**f-ops2** — OPS's `registry/userGroups.xml` seeds a single
`ROLE_ID_MANAGER` group (footnote s8's five-group list). Live-watched
2026-07-28 (`.reports/u6/claimcheck-1.md` C6, settling
`.reports/u6/claimcheck-3.md` C35 and C37): on OJS and OMP the option was
cleared on a manager-level role other than the default one — the default
role's own form cannot be opened from the Roles screen — and the screen
disappeared; on OPS no role offers the control.

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Invitations table ("Invite to a role", row Edit / Cancel Invite) | Settings → Users & Roles → Users tab | AFFM-099..101, VUE-052 |
| Send-invitation wizard (3 steps) | "Invite to a role" button, or `/{ctx}/invitation/create/userRoleAssignment` · `/{ctx}/invitation/edit/{id}` | ROUTE-013, VUE-011, AFFM-118..120 |
| Send wizard, edit-user mode | users table row "Edit" → `/{ctx}/management/settings/user/{userId}` (row action owned by *Users management*) | — (ROUTE-017 `editUser` op; rider proposed) |
| Acceptance flow | email "Accept Invitation" → `/{ctx}/invitation/accept?id&key` | ROUTE-014, VUE-001, AFFU-122, 126..137, 206 |
| Decline confirmation page | email "Decline Invitation" → `/{ctx}/invitation/decline?id&key` | AFFU-123 |
| "Invitation Unavailable" page | any spent/expired invitation link | AFFU-124..125 |
| Invitations lifecycle API | `/{ctx}/api/v1/invitations/…` | API-024 |
| Invitation email | sent on "Invite user to the role" | MAIL-055 |
| Validity window configuration | `[invitations] expiration_days` | SET-064 |
| Expired-invitation cleanup | daily scheduled job | JOB-013, JOB-051 |

## Reference — code anchors

- `lib/pkp/classes/invitation/invitations/userRoleAssignment/UserRoleAssignmentInvite.php`
  (+ `payload/`, `rules/`, `handlers/` — the create/receive controllers)
- `lib/pkp/classes/invitation/core/Invitation.php`, `models/InvitationModel.php`,
  `repositories/Repository.php`, `stepTypes/SendInvitationStep.php` /
  `AcceptInvitationStep.php`
- `lib/pkp/pages/invitation/InvitationHandler.php`,
  `InitializeInvitationUIHandler.php`; `lib/pkp/api/v1/invitations/InvitationController.php`
- `lib/pkp/classes/components/forms/invitation/UserDetailsForm.php`,
  `AcceptUserDetailsForm.php`
- `lib/ui-library/src/managers/UserInvitationManager/`,
  `src/pages/userInvitation/`, `src/pages/acceptInvitation/`
- `lib/pkp/templates/invitation/` (accept, decline, unavailable, wizard),
  `templates/management/access.tpl`
- `lib/pkp/classes/mail/mailables/UserRoleAssignmentInvitationNotify.php`;
  each app's `registry/emailTemplates.xml`, `registry/userGroups.xml`
- `PKP\pages\management\ManagementHandler` + each app's
  `APP\pages\management\SettingsHandler` (the host-screen gates)
- `lib/pkp/jobs/invitations/RemoveExpiredInvitationsJob.php`,
  `classes/task/RemoveExpiredInvitations.php`








