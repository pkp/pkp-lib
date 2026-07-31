---
name: users-management
scope: Managers and administrators find existing user accounts and act on them — edit, email, enable or disable, end their roles, merge duplicates
apps: [ojs, omp, ops]
shared: pkp-lib
status: draft
atlas-claims: [AFFM-102, AFFM-103, AFFM-104, AFFM-106, AFFM-107, AFFM-108, AFFM-203, AFFM-204, AFFM-205, AFFM-206, AFFM-207, AFFM-208, AFFM-210, GRID-003, GRID-050, VUE-013, VUE-051, API-047, MAIL-054, MAIL-056, MAIL-057]
---

# Users management {OJS OMP OPS}

## How to read this file

An unmarked claim asserts the behavior is identical in OJS, OMP and OPS.
`⚠ [A1](#a1)` marks an as-built deviation with a Findings-register entry;
an intended divergence carries the same link without the ⚠. A `{OJS OMP}`
badge on a sentence limits it to those apps. `<sup>[a](#fn-a)</sup>` marks
link to evidence footnotes at the document tail — a reader can skip them
and lose no behavior. Some claims below could only be read from the
applications' internals, not yet watched on a running install; each such
claim's footnote says so, and its wording stays cautious until it is
watched. Vocabulary is OJS's, per `APP-GLOSSARY.md`: for OMP read *press*
for journal and *Press Manager* for Journal Manager; for OPS read
*preprint server* and *Preprint Server Manager*. Every reference a reader
might follow is a link. The metadata block at the top and the Reference
tables at the end are machine bookkeeping — a reader needs nothing from
either.

## Purpose

Users management is how a journal's team looks after the accounts it
already has. A Journal Manager or Site Administrator finds a person in
the journal's user list and acts on the account: opens their record,
sends them an email, disables a problem account and re-enables it later,
ends their roles in this journal, or merges an accidental duplicate into
the account that should survive. Growing the team is the neighbouring
feature's job — see *User invitations* (its spec is being rebuilt; the
name stands until it returns) — and this
spec owns the list those invitations eventually land in, plus the
account-level operations everything else drives.

<a id="actors"></a>
## Actors & permissions

The feature has **two surfaces** running on the same operations:

- **The Users & Roles list** — the everyday surface. Settings → Users &
  Roles, "Users" tab, the table headed "Current Users" (it sits below the
  Invitations table, which belongs to *User invitations*). The screen
  answers at
  `management/settings/access` typed after the journal's own web address,
  and at the older second address `management/access` — the two addresses
  admit different people, a deviation that belongs to *User invitations*
  (which roles each door admits, and that administrator access differs
  per app). <sup>[a](#fn-a)</sup>
- **The Hosted Journals list** — the older surface, inside Site
  Administration. Administration → Hosted Journals — each journal's row
  is a collapsed expander (assistive technology reads it as
  "Settings"); expanding it reveals a control row beneath the journal's
  row with three actions, "Edit", "Remove" and "Settings wizard", and
  it is "Settings wizard" that opens the journal's settings pages —
  then the "Users" tab: a table also headed "Current Users", with its
  own filter and its own "Add User" button. The way in can first demand the administrator's
  password again: a "Confirm Access" page — "Signed in as {name}",
  "Please enter your password to continue." — stands before Site
  Administration's pages once the confirmation window is configured; a
  stock install leaves the window unset, and the prompt then never
  appears ([Settings](#settings), [A7](#a7)) <sup>[b](#fn-b)</sup>.
  These pages answer only at the site's own web address
  (`index/admin/wizard/` followed by the journal's number); typed after
  a journal's address instead, the page is refused — the journal's
  reader-facing site opens showing only "Access denied."
  <sup>[b](#fn-b)</sup>.

**Administration levels** decide who may act on whom, everywhere in this
feature. In this spec: a person **fully administers** a target account
when the server lets them do everything to it; they **partly administer**
it when they may only manage its roles in journals they run. The rules,
in order: nobody administers a Site Administrator's account — not even
another Site Administrator; a Site Administrator fully administers
everyone else; a Journal Manager fully administers a user only while that
user's every role, site-wide, is in journals the manager runs; a user who
also belongs to somewhere else on the site can only be partly
administered — and the operations below that need *full* administration
are refused for them. Everyone always fully administers their own
account — a rule no everyday screen demonstrates: the one control that
could show it, the Hosted Journals list's own-row "Disable User", has
never been confirmed on anyone's own account ⚠ [A12](#a12). These
consequences are the classes code-reading gets wrong;
where a row still shows an action the server will refuse, that is
recorded at the action below ⚠ [A2](#a2). <sup>[f](#fn-f)</sup>

| Action | Who may — and when |
|--------|--------------------|
| **See and search the Users & Roles list** | • Journal Manager — while their role keeps the "Permit changes to Settings" option (see [Settings](#settings))<br>• Site Administrator — from the journal's menu, in every app, while they are enrolled as the journal's manager (the install's own administrator always is — Rule 1). Holding no role in the journal, they find no menu entry for the screen anywhere: on OJS the screen still opens when either of its two addresses (see above) is typed; on OMP and OPS the typed `management/settings/access` address refuses them — "The current role does not have access to this operation." — and only the older second address admits them — a per-app deviation that belongs to *User invitations* <sup>[a](#fn-a)</sup> |
| **See and filter the Hosted Journals list** | • Site Administrator only — no other role reaches Site Administration <sup>[b](#fn-b)</sup> |
| **Add a user directly** ("Add User") | • Site Administrator — on the Hosted Journals list only. The Users & Roles list offers no way to create an account: new people are invited instead (see *User invitations*) <sup>[c](#fn-c)</sup> |
| **Edit a user** | • Journal Manager and Site Administrator — the Users & Roles row's "Edit" leaves for the user's own edit page, the same page *User invitations* documents (its Rule 4b); offered on every row, the actor's own included. On OMP and OPS the offer outruns the page for a Site Administrator holding no role in the journal: every user row on the list that administrator reaches still offers "Edit", and choosing it is refused — "The current role does not have access to this operation." — the same per-app deviation as reaching the list itself (row above); on OJS the page opens normally <sup>[e](#fn-e)</sup><br>• Site Administrator — the Hosted Journals row's "Edit User" opens the full account form ([Fields](#fields)) |
| **Email a user** ("Email") | • Journal Manager and Site Administrator — the one action with no target-based restriction: any row, their own included <sup>[g](#fn-g)</sup> |
| **Disable / Enable an account** | • whoever **fully administers** the target — a Journal Manager choosing it on a Site Administrator, or on a user who belongs anywhere else on the site, is refused inside the dialog, before anything is confirmed ⚠ [A2](#a2)<br>• offered on both lists. The Users & Roles list never offers it on the viewer's own row — a Journal Manager's and a Site Administrator's alike; the Hosted Journals list offers it on every row, the viewer's own included (Rule 1c) <sup>[h](#fn-h)</sup> |
| **Remove a user's roles here** ("Remove User"; the Hosted Journals list labels it "Remove") | • whoever at least **partly administers** the target — offered only while the target holds an active role in this journal <sup>[i](#fn-i)</sup> |
| **Merge a user into another** | • whoever **fully administers** the account being merged away — in practice a Site Administrator, since any role in another journal blocks a manager, and the Users & Roles list withholds the action on rows the viewer cannot fully administer (Rule 1c)<br>• never offered on one's own row as the account to merge away; the target list, though, does offer the viewer's own account as the survivor (Rule 7) <sup>[j](#fn-j)</sup> |
| **End a single role / change masthead display** | • driven from the user's edit page, which *User invitations* documents — the operations themselves are this feature's (Rule 8) <sup>[k](#fn-k)</sup> |

<a id="fields"></a>
## Fields & validation

**The account form** — "Add User", and "Edit User" on the Hosted
Journals list. One form; the password block changes shape between create
and edit. Its name and affiliation boxes (Given Name, Family Name,
Preferred Public Name, Affiliation) each render once, in the site's
primary language, with no language switcher beside them — even on a
site running two languages, a manager cannot enter a person's name in
the second one from this form. <sup>[c](#fn-c)</sup>

The rules in the table below are enforced, but the form breaks none of
them out loud: saving with a username outside its allowed characters, a
password under the minimum, a repeat that does not match, or an email
address already in use leaves the dialog open, the typed values in
place, and no message anywhere — no field error, no banner — and the
account is simply not created. Only an empty required field draws a
message ("This field is required.") ⚠ [A10](#a10).

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| Given Name | Yes | One box, in the site's primary language (see above). |
| Family Name | No | |
| Preferred Public Name | No | |
| Username | Yes | Creating only — when editing, the username is shown but cannot be changed. Lowercase letters, numbers and hyphens/underscores; a "Suggest" button fills it from the names typed above. <sup>[n](#fn-n)</sup> |
| Email address | Yes | |
| Password / Repeat password | Yes (creating) | The site's minimum length is stated only when editing, alongside the note that leaving the fields blank keeps the current password; the create form states no minimum anywhere — someone creating an account is told nothing about it. <sup>[c](#fn-c)</sup> |
| Generate Password | No | Creating only. "Generate random password for this user." — also forces the welcome email (see [Side effects](#side-effects)). |
| Change Password | No | "User must change password on next log in." Ticked by default when creating. |
| Country | No | |
| Notify User | No | Creating only: "Send user a welcome email." |
| Homepage URL / Phone / Reviewing interests / Affiliation / Bio Statement / Mailing Address / Signature | No | |
| Working Languages | No | Shown only when the site has more than one language. <sup>[c](#fn-c)</sup> |
| Editorial Notes | No | Shown only once the edited user holds a Reviewer role in this journal {OJS OMP} (OPS installs no reviewer role, so the field never appears there) — and only to a viewer who also holds a Journal Manager or Section Editor role in that journal. The form opens from Site Administration alone, so the viewer is always a Site Administrator, but administrator status by itself is not enough: an administrator whose only role in the journal is Author opens the same form and the block is simply not there, with nothing marking its absence, while an administrator who is also the journal's manager sees it. The viewer's own record is the one exception — a qualifying viewer sees the notes on every reviewer's record but their own. The block never appears on the page the Users & Roles row's "Edit" opens; beyond this form, the notes also surface on the reviewer-picking screens (see *Reviewer assignment*, spec pending), where the own-record exception did not hold when watched. <sup>[c](#fn-c)</sup> |
| Roles / Masthead checkboxes | No | Editing only — two checkbox lists of this journal's roles: which the user holds, and which show them on the masthead. The masthead list opens with every box ticked, whether or not the user holds the role — it is not a picture of where the person currently appears — and its Reviewer rows are the only locked ones: ticked and impossible to untick {OJS OMP} (OPS installs no reviewer role). What saving a ticked masthead box does for a role the person does not hold was not established in this pass; the masthead changes this spec records act on roles the person holds (Rule 8b). Creating instead continues to a second screen, "Step #2: Add User Roles to {name}", with the same two lists — where nothing is locked. <sup>[c](#fn-c)</sup> |

**The disable/enable dialog** — a form, not a yes/no confirmation;
each list dresses it differently (Rule 5):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Reason for disabling user" / "Reason for enabling user" | No | Free text, stored with the account and pre-filled the next time the dialog opens, from either list — enabling does not clear it, and the disabled person sees it quoted when their sign-in is refused (Rule 5). |

Beneath the box the dialog carries a fixed note: the disable dialog's
warns that a disabled user cannot be added to any role until enabled
again; the enable dialog's says access returns and the person can be
invited to roles as needed. Neither note mentions that the typed
reason is shown to the person (Rule 5). <sup>[h](#fn-h)</sup>

**The email form** ("Email" on either list):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| Subject | Yes | |
| To | — | Fixed: shows the recipient's full name and address; cannot be edited. |
| Body | Yes | Rich text. Sent with "Send Email". |

## Rules & state

**The two lists**

1. Both lists are headed "Current Users" and drive the same operations
   underneath; they differ in dressing and in what they offer
   (Rules 1a–1c). Nothing a manager does on one list is invisible on the
   other: they read the same accounts — but not always identically. A
   Site Administrator enrolled in the journal is described differently
   by each list (an empty Roles cell on the Hosted Journals list;
   "Journal manager" with a blank start date on Users & Roles)
   ⚠ [A8](#a8). A Site Administrator holding no role in the journal
   appears in neither of its lists; only the Hosted Journals filter's
   "Include users with no roles in this journal." box surfaces them.
   The install's own administrator appears in every journal's lists
   because creating a journal enrols its creator as a manager there.
   - 1a. **The Users & Roles list** shows 25 users per page under a
     heading whose count follows the current filter: "Name" (with an
     ORCID mark when the user has one, and a red disabled mark when the
     account is disabled), "Email", "Roles" and "Start Date" (each
     current role with the date it began — ended roles are not shown),
     "Affiliation", and a per-row options menu holding the row actions.
     The row-options button gives assistive technology a raw technical
     key in place of a name ⚠ [A3](#a3). Above the table, a single
     search field narrows the list; its hint text names "Journal editor"
     as its example in every app ⚠ [A4](#a4). <sup>[c](#fn-c)</sup>
   - 1b. **The Hosted Journals list** shows five columns — given name,
     family name, username, roles, email. Its filter sits behind the
     header's "Search" link — hidden until clicked, and collapsing again
     after each search; revealed, it holds a search box, a role dropdown
     (default "All Roles"), an "Include users with no roles in this
     journal." checkbox, and a "Search" button, and applies on pressing
     Search. Its row actions are Email, Edit User, Disable User (or
     Enable — Rule 4), Remove (so labelled — not "Remove User"), Login
     As and Merge User; Login As and Merge User drop off rows the
     viewer cannot fully administer — and off the viewer's own row
     too, a guard of its own, not a case of the administration rule
     (everyone fully administers their own account —
     [Actors](#actors); Rule 1c) — and Remove off rows with no
     active role here. Disabling and enabling complete from this list
     just as from the other (Rules 4–5). "Add User" sits above the
     table. <sup>[d](#fn-d)</sup>
   - 1c. What each list guards differently: on the Users & Roles list
     the viewer's own row offers only "Edit" and "Email" — Login As,
     Remove User, Disable User and Merge user are withheld, and not for
     administrators alone: a Journal Manager's own row is stripped
     identically. The Hosted Journals list withholds Login As and Merge
     User on the viewer's own row and still offers Email, Edit User,
     Disable User and Remove there — its own-row Disable opens the
     ordinary reason dialog with nothing standing in the way, and what
     confirming it would do to the account doing it has never been
     watched ⚠ [A12](#a12). Beyond
     the viewer's own row, the
     Users & Roles list also withholds "Merge user" and "Login As" on
     any row the viewing Journal Manager cannot fully administer — a
     Site Administrator's, or a user who also holds a role in another
     journal — while Edit, Email, Remove User and Disable User stay
     offered there; the one that dangles is Disable User, which the
     dialog itself then refuses ⚠ [A2](#a2). A fellow manager's row,
     fully administered, offers all six actions.

2. **Search** on the Users & Roles list runs only when Enter is pressed
   in the search field — typing alone changes nothing, and no search
   button is offered. It matches against names, usernames, email
   addresses, roles and affiliations and resets the list to its first
   page. A role word can also return a row whose Roles cell shows no
   such role — a result the searcher cannot account for from the list
   in front of them ⚠ [A11](#a11). The Hosted Journals filter
   applies on its "Search" button and can also select a single role, or
   users with no roles at all. <sup>[c](#fn-c)</sup>
   <sup>[d](#fn-d)</sup>

3. **Editing.** The Users & Roles row's "Edit" leaves the list for the
   user's own edit page — the roles-and-masthead page that *User
   invitations* documents (including
   that page's missing heading and dead Cancel) — except for the
   role-less administrator OMP and OPS refuse at that page (Actors —
   Edit a user). The Hosted Journals
   row's "Edit User" opens the full account form ([Fields](#fields))
   in place. <sup>[e](#fn-e)</sup>

**Disable and enable**

4. Disabling an account — from either list — keeps the account and
   everything it holds — roles, masthead choices, history — and only
   bars the person from signing in; any window they have open
   elsewhere is thrown back to sign-in at that moment. The Users &
   Roles list then marks the row with the red disabled icon and the
   row's action reads "Enable User" in place of "Disable User". The
   Hosted Journals row shows no mark at all: only its action's label —
   "Enable", in place of "Disable User" — says the person cannot sign
   in. What one list does the other shows: an account disabled from
   Hosted Journals carries the red mark on Users & Roles, and either
   list's enable action restores sign-in. <sup>[h](#fn-h)</sup>
5. Each list dresses the dialog its own way, one operation behind
   both. The Users & Roles dialog is titled "Disable {name}" (or
   "Enable {name}") and lists the user's roles under "Current Roles" —
   a listing that also includes roles that have already ended, which
   the list's own Roles column no longer shows. The Hosted Journals
   dialog names nobody and lists no roles: it is headed plainly
   "Disable User" (or "Enable"), and its body is the reason box, a
   fixed note ([Fields](#fields)), Cancel and OK. The operation does
   not touch roles. The reason box is stored with the account and
   shown again pre-filled on the next open — on either list, whichever
   one took it — and never cleared by enabling; enabling restores
   sign-in exactly as it was. The typed reason follows the person:
   while the account is disabled, their sign-in attempt is refused
   with "Your account has been disabled for the following reason: …"
   quoting it — nothing on either dialog tells the manager the reason
   will be shown to the user. <sup>[h](#fn-h)</sup>

**Remove**

6. "Remove User" ends **every** role the user holds in this journal, in
   one stroke, after one confirmation: "Remove this user from this
   journal? This action will unenroll the user from all roles within
   this journal." The account survives, still signs in, and keeps every
   role it holds in other journals; the ended roles keep their history
   (their start and end dates remain on record). No email tells the
   user ⚠ [A1](#a1). The row itself stays in the list, its Roles and
   Start Date cells now empty, and the "Current Users" count does not
   change. It cannot be undone from this screen — re-granting a role
   later starts it fresh, dated that day — and once no active role is
   left here, neither list offers "Remove User" on that row at all.
   <sup>[i](#fn-i)</sup>

**Merge**

7. "Merge user" is a two-step choice: the duplicate's row opens a second
   user list titled "Merge into this User"; picking the surviving
   account there asks for confirmation in these words: "Are you sure you
   wish to merge the account with the username \"{old}\" into the
   account with the username \"{new}\"? The account with the username
   \"{old}\" will not exist afterwards. This action is not reversible."
   It means it:
   - everything the old account ever did — submissions activity,
     reviews, decisions, files, notes, notifications, email history — is
     re-credited to the surviving account;
   - the old account's roles are added to the survivor wherever the
     survivor lacks them, keeping their dates and masthead choices;
   - {OJS} subscriptions and completed payments move to the survivor;
     {OMP} completed payments move; OPS has neither;
   - the old account is then **deleted permanently**. No screen can
     undo a merge.
   The moment the merge is confirmed, the duplicate's row is gone and
   the "Current Users" count drops by one; any window the duplicate had
   open is thrown back to sign-in, and their username is thereafter
   refused at sign-in as unknown. The survivor's record carries the
   duplicate's role from then on, with its original start date and
   masthead choice. One's own account is never offered as a source —
   neither list shows the action on the viewer's own row — but it can
   be the target: the "Merge into this User" list includes the viewer's
   own account and offers it, to a Journal Manager and a Site
   Administrator alike. The duplicate itself is listed there too, with
   only its "Merge into this User" action withheld.
   <sup>[j](#fn-j)</sup>

**Single-role operations** (mechanics home; driven from the user's edit
page — the page belongs to *User invitations*)

<a id="single-role"></a>
8. Two operations act on one role assignment at a time, and both act
   immediately, before any invitation business completes:
   - 8a. **Ending one role** ends just that role in this journal —
     unlike Remove (Rule 6), which ends them all — and **always emails
     the user** that the role has ended ⚠ [A1](#a1). On the edit page,
     each current role's row in the role table carries a "Remove Role"
     button; choosing it asks "Are you sure you want to remove this
     role? The user will lose access and permissions associated with
     it.", and confirming ends the role at once. Ending the user's
     last role is refused on the page itself, with "You cannot remove
     the role. At least one role must be assigned to the user." and
     "Close" its only way out (that guard is the edit page's, recorded
     by *User invitations*; so is the rest of the page).
   - 8b. **Changing masthead display** switches whether that one role
     shows the person on the journal's masthead. The confirmation shown
     before saving promises "The user will be notified of this change" —
     and says "journal masthead" in every app ⚠ [A9](#a9). On OJS,
     when the choice actually changes the user is emailed; re-choosing
     the value already set changes nothing and emails nobody. On OMP and
     OPS the change saves and the manager is then shown an error naming
     an internal migration script; the user is never told ⚠ [A5](#a5).
     A Reviewer role's masthead display is not offered as a setting at
     all: its row shows the fixed words "Appear on the masthead" where
     other roles show a control {OJS OMP}. <sup>[k](#fn-k)</sup>

**Odd corners**

9. A ready-made spreadsheet of the journal's users — one Yes/No column
   per role — answers at a typed address (`api/v1/users/report` after
   the journal's web address) for the same roles the lists admit; an
   Author or Reader typing it is refused — an error message (its
   wording not recorded here) and no file downloading; the missing
   download is the part to judge by. The file
   holds only the accounts with at least one active role in the
   journal: rows whose roles have all been ended stay on the "Current
   Users" list, with empty Roles cells, and are absent from the file —
   what the screen calls a current user and what the download holds
   are not the same set. No screen in any app mentions the address
   ⚠ [A6](#a6). <sup>[m](#fn-m)</sup>

<a id="side-effects"></a>
## Side effects

- **Welcome email** — sent when an account is created from "Add User"
  with "Notify User" ticked, and always when "Generate Password" is
  ticked. It leaves as soon as the details are saved, before any roles
  are chosen, and carries the password in clear text — whether typed
  or generated. Never sent when editing. Replies go to the journal's
  contact address; a journal with no contact address set sends it with
  no reply-to. <sup>[l](#fn-l)</sup>
- **Role-ended email** — sent to the user every time a single role is
  ended (Rule 8a), from any surface that drives the operation; it
  announces "You have been removed from a role". Removing all roles at
  once (Rule 6) sends nothing ⚠ [A1](#a1). <sup>[k](#fn-k)</sup>
- **Masthead-changed email** {OJS} — sent to the user when their
  masthead display actually changes (Rule 8b). On OMP and OPS its
  template is missing from a fresh install: the change saves, the
  manager sees an error, and no email leaves ⚠ [A5](#a5).
  <sup>[k](#fn-k)</sup>
- **Sessions dropped** — disabling an account (Rule 4) and merging one
  away (Rule 7) both end the affected account's open sessions at once.
  <sup>[h](#fn-h)</sup> <sup>[j](#fn-j)</sup>
- **No email otherwise** — disabling, enabling, removing and merging
  tell the affected user nothing by mail. <sup>[i](#fn-i)</sup>

<a id="settings"></a>
## Settings that modify behavior

- **"Permit changes to Settings"** on manager-level roles decides
  whether a Journal Manager reaches the Users & Roles screen at all; the
  option and its mechanics belong to *Roles configuration* (spec
  pending), and *User invitations* records how it
  behaves per app (including that OPS cannot exercise it). Unticking
  the option on a manager-level role removes the screen's menu entry
  at once and leaves its typed address refused — "The current role
  does not have access to this operation." — and re-ticking restores
  both. The default Journal Manager role itself cannot lose the
  option: its row on the Roles tab offers no actions at all, so only
  other manager-level roles can actually be cut off this way.
  <sup>[o](#fn-o)</sup>
- **The site's languages** — more than one language adds the "Working
  Languages" field to the account form; the name and affiliation boxes
  it does not touch — they stay single, in the site's primary language
  ([Fields](#fields)).
- **The password confirmation window (server configuration)** — how
  long a Site Administrator's password confirmation holds. Not a
  screen setting: it lives in the application's own configuration
  file — the file named "config inc php", written as the three words
  joined by dots, at the application's root — in the section headed
  `[security]`, as the setting named "password timeout" — written as
  the two words joined by an underscore, its value in minutes. While the
  setting is present, the first site-administration address visited
  after signing in lands on the "Confirm Access" page — and every
  site-administration address keeps landing there until the password is
  entered; once entered, Administration stops asking until the window
  lapses, when the prompt returns. A wrong password is refused with
  "Invalid username/email or password. Please try again." A stock
  install leaves the setting unset, and the prompt then never appears
  [A7](#a7). Only Site Administration is guarded this way: the
  journal-level Users & Roles screen never asks. No other
  configuration-file setting alters this feature's behavior on screen.
  <sup>[b](#fn-b)</sup>

## Cross-feature interactions

- ***User invitations*** (spec being rebuilt; the name stands until it
  returns) — owns everything above
  the users table on the Users tab, the invite wizard, and the user edit
  page the row "Edit" opens; that page drives this spec's single-role
  operations (Rule 8). Inviting is the only way to add a user from the
  Users & Roles surface.
- ***Roles configuration*** (spec pending) — defines the roles the lists
  display and the account form assigns, and the settings-access option
  the Actors table leans on; shares the Users & Roles screen (its
  "Roles" tab).
- ***Notify users*** (spec pending) — bulk email to whole role groups,
  on a "Notify" tab the Users & Roles screen does not carry as
  installed: the tab appears only once a site administrator allows the
  journal to send bulk email (Administration → Site Settings → Bulk
  Emails — tick the journal), and it then sits between "Roles" and
  "Site Access Options"; unticking removes it again. The per-user
  "Email" action here is this spec's. <sup>[p](#fn-p)</sup>
- ***Emails management*** (spec pending) — where the welcome,
  role-ended and masthead emails' templates are edited; OPS's screen
  lists two of them nowhere ⚠ [OPS1](#ops1).
- ***Login & sessions*** (spec pending) — "Login As" on both lists is
  that feature's, though it renders among this feature's row actions.
- ***Statistics — editorial*** (spec pending) — expected to cite the
  users spreadsheet (Rule 9) from its side once that spec exists;
  until then the cross-reference stands from this side alone. What its
  screens already show is recorded at [A6](#a6): Statistics → Users
  offers an "Export" control that is dead when clicked, and no
  statistics screen offers the users download.
- ***Reviewer assignment*** (spec pending) — its reviewer-picking
  screens show the same user records, Editorial Notes included
  ([Fields](#fields)); the screens and filters are its own to
  describe.

## Canonical scenarios

Common to all three apps — run each in the app's own vocabulary and
roles (per `APP-GLOSSARY.md`), in a scratch journal, on scratch accounts.

1. **Find a user and open their record** — Journal Manager: open
   Settings → Users & Roles; under "Current Users", type part of a
   user's name into the search field, pressing Enter. The list narrows
   to matching rows; the row shows the user's email, current roles with start
   dates, and affiliation. Open the row's options menu and choose
   "Edit": the user's own edit page opens on that user's record —
   their email address and roles are on it (the page, its missing
   heading included, is *User invitations*' to
   describe).
   <sup>[s1](#fn-s1)</sup>
2. **Email a user from the list** — Journal Manager: on a row's options
   menu choose "Email". A form opens with "Subject", a fixed "To"
   naming the user, and "Body"; fill both free fields and click "Send
   Email". The user receives the message as typed, from the manager's
   own address. <sup>[s2](#fn-s2)</sup>
3. **Disable an account, then re-enable it** — Journal Manager, on a
   scratch user with roles only in this journal: choose "Disable User"
   from the row menu. A dialog "Disable {name}" opens, listing the
   user's current roles, with a "Reason for disabling user" box; type a
   reason and confirm. The row's name gains the disabled mark and the
   menu now offers "Enable User"; as the user, signing in is refused,
   with the typed reason quoted back.
   Choose "Enable User" — the reason typed earlier is already in the
   box — and confirm; the user signs in again. <sup>[s3](#fn-s3)</sup>
4. **Remove a user from the journal** — Journal Manager, on a scratch
   user holding roles here: choose "Remove User"; the confirmation
   reads "Remove this user from this journal? This action will unenroll
   the user from all roles within this journal."; confirm. The user's
   roles in this journal are gone, no email reaches them, and their
   account still signs in. <sup>[s4](#fn-s4)</sup>
5. **Merge a duplicate account** — Site Administrator, with two scratch
   accounts, each holding a role in this journal (an account with no
   role here appears on neither list — Rule 1), and the duplicate
   holding one role the survivor lacks, so the transfer shows. Before
   merging, note down what the outcome is judged against: the
   duplicate's role and its start date, and the "Current Users" count.
   On the Users & Roles list, on the **duplicate's** row — the account
   whose row the merge starts from is the one destroyed — choose
   "Merge user". A second user list titled "Merge into this User"
   opens; on the surviving account's row, choose its "Merge into this
   User" action. The confirmation names both usernames and warns the
   old account "will not exist afterwards. This action is not
   reversible." Before confirming, read it: unless the account it
   names as the one that will not exist afterwards is the duplicate's,
   cancel — the merge was opened from the wrong row. Confirm. The
   duplicate's row is gone at once and the "Current Users" count drops
   by one from the noted figure; its username no longer signs in; and
   the survivor's row now also shows the duplicate's role, keeping the
   start date noted before the merge.
   <sup>[s5](#fn-s5)</sup>
6. **Add a user from Site Administration** — Site Administrator: from
   Administration → Hosted Journals, expand the journal's row and
   choose "Settings wizard" from the control row it reveals; on the
   settings pages open the "Users" tab, and click "Add User". Fill
   Given Name, Username, Email address, and Password with a matching
   Repeat password — the create form states no password minimum
   anywhere, so first read the minimum off any existing user's edit
   form, the one place it is stated ([Fields](#fields)), and type a
   password at least that long. Tick "Notify
   User" and save. If saving appears to do nothing — the dialog stays
   open, the values stay put, no message — a rule was broken silently
   ⚠ [A10](#a10): suspect a short password, a mismatched repeat, a
   username character outside the allowed set, or an email address
   already in use. On success, "Step #2: Add
   User Roles to {name}" appears — tick a role and save. A welcome
   email reaches the address — this document fixes no way of watching
   mail on a test install, so use whatever catches the install's
   outgoing messages; the email leaves as soon as the details are
   saved, before roles are chosen ([Side effects](#side-effects)), so
   there is nothing to wait for after Step 2. The new user appears in
   the journal's Users & Roles list with the role.
   <sup>[s6](#fn-s6)</sup>
7. **A manager meets a shared user** — Journal Manager, on a scratch
   user who also holds a role in *another* journal on the same site:
   the row offers neither "Merge user" nor "Login As", but still offers
   "Disable User" ⚠ [A2](#a2); choosing it opens the dialog with the
   user's name and roles, but where the reason box and confirmation
   button belong stands a message that the manager lacks permission to
   administer this user, with "Close" its only button — there is
   nothing to confirm. "Remove User" on
   the same row still works — it only needs this journal's say-so — and
   leaves the other journal's role untouched. <sup>[s7](#fn-s7)</sup>
8. **One role ended, then all of them** — Journal Manager, on a scratch
   user holding two roles here: choose "Edit" from the row's options
   menu; the user's edit page opens, its role table listing both
   current roles, "Remove Role" beside each. Choose one row's "Remove
   Role" and confirm the question that opens ("Are you sure you want
   to remove this role? …"); an email reaches the user saying the role
   ended. Back on the list, choose "Remove User" and confirm: the
   remaining role ends and **no** email is sent ⚠ [A1](#a1) — judge
   that silence against the role-ended email that arrived moments
   before, which proves mail is being watched at all; this document
   fixes no waiting period beyond seeing nothing arrive alongside
   it. (The edit
   page itself is *User invitations*' to
   describe; Rule 8a quotes what this scenario needs of it.)
   <sup>[s8](#fn-s8)</sup>

App-specific: none — the app differences in this feature are reductions
and register entries (OPS's missing reviewer machinery, the OMP/OPS
masthead email ⚠ [A5](#a5)), not flows of their own.

## Findings register

Verdicts are the author's judgment (claude, 2026-07-28), unreviewed
unless an entry notes otherwise; the team settles them on spec review.
Badges — the "Bug?" column: 🐞 a defect (the author's call) · ❓ needs
a product ruling · ✅ an intended divergence. This register was drafted
from code reading before live probing; every badge is provisional until
its flow is watched, and each entry names its basis: **probe** —
watched live on a running install; **claim check** — a later pass that
re-tested this spec's own written claims on the three running apps
(live evidence, same standing as a probe); **code reading** — read from
the applications' internals, not yet watched. Sorted 🐞 → ❓ → ✅.
Each entry opens with the user-observable
symptom; mechanism and evidence live in the entry's footnote. Impact:
**user-visible** — met on screen in ordinary use and consequential;
**minor** — met on screen, small in consequence; **latent** — surfaces
only in corners not ordinarily exercised, or not yet watched.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|------------------------------|------|--------|--------|
| [A3](#a3) | The row-options menu's accessible name exists in no language file | 🐞 | user-visible | — |
| [A5](#a5) | On OMP and OPS a masthead change saves, then shows the manager an internal error, and tells nobody | 🐞 | user-visible | — |
| [A10](#a10) | The account form refuses bad input with no message of any kind | 🐞 | user-visible | — |
| [A4](#a4) | The search hint says "Journal editor" on presses and preprint servers too | 🐞 | minor | — |
| [A9](#a9) | The masthead confirmation says "journal masthead" everywhere and promises an email OMP and OPS never send | 🐞 | minor | — |
| [A1](#a1) | Ending one role always emails the user; removing all their roles at once emails nobody | ❓ | user-visible | — |
| [A2](#a2) | "Disable User" stays offered on rows it can only refuse; Merge and Login As are withheld there | ❓ | minor | — |
| [A6](#a6) | A users spreadsheet answers at a typed address no screen offers | ❓ | latent | — |
| [A8](#a8) | The two lists read a Site Administrator differently | ❓ | minor | — |
| [A11](#a11) | A role word in the users search returns rows whose Roles cell shows no such role | ❓ | minor | — |
| [A12](#a12) | "Disable User" is offered on the viewer's own Hosted Journals row; what confirming it does is unwatched | ❓ | latent | — |
| [OPS1](#ops1) | The role-ended and masthead emails have no row on OPS's email-templates screen | ❓ | latent | — |
| [A7](#a7) | The password re-confirmation guards Site Administration only; a stock install never shows it | ✅ | latent | — |

### All apps

<a id="a1"></a>
**A1 — One role ended is announced; all roles ended is silent** · ❓ · user-visible.
Ending a single role from the user's edit page always emails the person
that the role ended (Rule 8a); "Remove User", which ends every role they
hold in the journal at once (Rule 6), sends nothing — the person finds
out by visiting. The larger removal is the quieter one. Question: is
Remove meant to notify the user, or is ending-one-role meant to be
configurable/silent? Lean: the asymmetry is accidental — the single-role
email was added with the new edit page and Remove was never revisited.
Basis: probe — both halves watched back to back: the single-role email
arrives every time, and removing the person's remaining roles right
afterwards adds nothing to their mailbox. <sup>[f-a1](#fn-a1)</sup>

<a id="a2"></a>
**A2 — "Disable User" dangles where its neighbours are withheld** · ❓ · minor.
On the Users & Roles list, a row the viewing Journal Manager cannot
fully administer — a Site Administrator's, or a user who also holds a
role in another journal — withholds "Merge user" and "Login As", yet
still offers "Disable User". Choosing it opens the dialog with the
refusal standing where the reason box and confirmation button belong:
"You do not have sufficient permissions to administer this user. In
order to administer a user, you must either be site administrator, or
administer all contexts that this user is enrolled in." — with "Close"
as the dialog's only button. Nothing is at risk — there is nothing to
confirm — but one action dangles where its two neighbours are hidden,
and the refusal says "contexts", a word no screen uses. Watched on both
kinds of row, the Site Administrator's included, in all three apps; a
positive control (the same manager disabling a plain reviewer) got the
ordinary "Reason for disabling user" dialog and completed. On the same
rows "Remove User" behaves differently: it opens its ordinary
confirmation — against a shared user it then works (Rule 6 needs only
this journal's say-so); against a Site Administrator the confirmation
was not taken further. Question: should "Disable User" be withheld on
those rows as Merge and Login As already are? Lean: yes — the same
row's other guards read as the intent. Basis: probe (the withholding
and the in-dialog refusal, both row kinds, all three apps).
<sup>[f-a2](#fn-a2)</sup>

<a id="a3"></a>
**A3 — The row menu's accessible name exists in no language** · 🐞 · user-visible.
The per-row options menu on the Users & Roles list gives assistive
technology a raw technical key, hash-marks and all, where a name like
"user management options" should be — in every app. Sighted use is
unaffected (the menu is an icon button), and the same table's "More
Actions" header is properly translated, so the gap is this one control's.
Expected: a real name in every shipped language. Basis: code reading;
probe. <sup>[f-a3](#fn-a3)</sup>

<a id="a4"></a>
**A4 — The search hint names a journal role everywhere** · 🐞 · minor.
The Users & Roles search field's hint reads "Enter a user's name, role
(e.g Journal editor), or affiliation" from a shared string no app
overrides — a press or preprint server manager is offered "Journal
editor" as the example (and the "e.g" wants a period). Expected: the
example follows the app's vocabulary. Basis: code reading; probe.
<sup>[f-a4](#fn-a4)</sup>

<a id="a5"></a>
**A5 — Masthead changes on OMP and OPS: saved, then an error, and nobody told** · 🐞 · user-visible.
The email announcing a masthead change is installed on OJS only: OMP and
OPS ship without its template, and neither install nor upgrade adds it.
On OMP and OPS a manager changing a user's masthead display sees the
change save and then an error naming an internal migration script —
developer text, on a manager's screen — and the user is never told,
despite the confirmation's promise ⚠ [A9](#a9); on OJS the same change
saves with no message and the user is emailed. The change sticks in
every case. Whether to carry the template on OMP and OPS or stop the
change depending on one is the team's call — the error shown to the
manager is a defect on any reading. Basis: probe (all three apps); code
reading (the missing-template cause). <sup>[f-a5](#fn-a5)</sup>

<a id="a6"></a>
**A6 — A users spreadsheet with no door** · ❓ · latent.
A spreadsheet of the journal's users (a CSV download, one Yes/No
column per role — holding only the accounts with at least one active
role here, Rule 9) answers at `api/v1/users/report` typed after the
journal's web address — to the roles the lists admit; others get a bare
error — but no screen in any app links to it. The nearest thing to a
door on any screen is dead: Statistics → Users offers an "Export"
control beside "Registered users", and clicking it produces no
download, no message and no visible response. That screen is
*Statistics — editorial*'s (spec pending) to describe; the control is
noted here because it is the one a reader would take for this
download's missing door. Question: is a screen
missing, or is the export retired and due for removal? Lean: orphaned —
nothing in any app's interface references it.
Basis: code reading; probe (the download and its refusal watched);
claim check (the file's contents and the dead control).
<sup>[f-a6](#fn-a6)</sup>

<a id="a7"></a>
**A7 — The password re-confirmation guards Site Administration only, and a stock install never shows it** · ✅ · latent.
The "Confirm Access" prompt is real: with the password-timeout window
configured ([Settings](#settings)), the first site-administration
address visited after signing in lands on it, every site-administration
address keeps landing there until the password is entered, and the
prompt returns once the window lapses; a wrong password is refused with
"Invalid username/email or password. Please try again." The
journal-level Users & Roles screen is never met by it. A stock install
leaves the window unset, so the prompt never appears there — which is
why earlier watching on fresh sign-ins found nothing. Read as intended:
the guard is a configuration choice, off by default, scoped to Site
Administration. Basis: probe (all three apps, window configured and
unset). <sup>[f-a7](#fn-a7)</sup>

<a id="a8"></a>
**A8 — The two lists read a Site Administrator differently** · ❓ · minor.
The lists share their accounts, and for ordinary users they agree; for
a Site Administrator enrolled in the journal they do not: the Hosted
Journals row shows an empty Roles cell where the Users & Roles row
shows "Journal manager" with a blank start date — and the Hosted
Journals manager-role filter still returns the row whose Roles cell it
leaves empty. An administrator with no role in the journal is not the
case: they appear in neither list (Rule 1). Question: which rendering
of the enrolled administrator is meant? Lean: drift, not design — the
lists read the same records but were never taught the same answer for
an administrator. Basis: probe; claim check.
<sup>[f-a8](#fn-a8)</sup>

<a id="a9"></a>
**A9 — The masthead confirmation misspeaks twice** · 🐞 · minor.
The confirmation shown before a masthead change (Rule 8b) says "journal
masthead" on presses and preprint servers too, and promises "The user
will be notified of this change" — a promise OMP and OPS never keep,
since the notice email cannot leave there ⚠ [A5](#a5). Expected: the
noun follows the app's vocabulary, and the promise is made only where
it is kept. Same class as the search hint ⚠ [A4](#a4). Basis: probe
(all three apps). <sup>[f-a9](#fn-a9)</sup>

<a id="a10"></a>
**A10 — The account form refuses bad input without a word** · 🐞 · user-visible.
Saving "Add User" with a username outside its allowed characters, a
password under the minimum, a repeat that does not match, or an email
address already in use gets no response at all: the dialog stays open,
the typed values stay put, and nothing anywhere says why — the account
is simply not created. Only an empty required field is answered ("This
field is required."). The administrator's only way forward is to guess
which rule was broken. Expected: each rule the form enforces refuses
out loud, on the field it concerns. Basis: claim check (watched on
OJS; the form is the shared one). <sup>[f-a10](#fn-a10)</sup>

<a id="a11"></a>
**A11 — A role search returns rows the list cannot explain** · ❓ · minor.
Typing a role word into the Users & Roles search can return a row
whose Roles cell shows no such role: searching "Author" returned a
Site Administrator's row, whose Roles cell reads "Journal manager" —
and the role dropdown on the same journal's Hosted Journals filter
does not return that row under Author. A manager searching by role
gets a result nothing in front of them explains. Question: should the
search match role records the list does not display? Lean: the search
reads assignments the Roles cell hides — kin to the two lists'
differing readings of an administrator ⚠ [A8](#a8) — so the answer
belongs with whichever rendering of those roles is ruled correct.
Basis: claim check (OJS, reproduced in a second journal).
<sup>[f-a11](#fn-a11)</sup>

<a id="a12"></a>
**A12 — Disabling one's own account is offered, never confirmed** · ❓ · latent.
The rulebook says everyone fully administers their own account, and
the one screen control that could show it is the Hosted Journals
list's own-row "Disable User" (Rule 1c): the ordinary reason dialog
opens on the viewer's own account, Cancel and OK in place, nothing
standing in the way. Nobody has confirmed it on their own account, so
what follows — a manager locked out by their own hand, or a refusal
the dialog never hinted at — is unrecorded; the everyday Users & Roles
list shows nothing either way, offering only "Edit" and "Email" on the
viewer's own row. Question: does confirming disable the actor's own
account, ending their session then and there, or is it refused? Lean:
it completes and locks the actor out — the dialog carries no guard,
and the account rules grant everyone full administration of their own
account. Confirming that dialog on a throwaway administrator's own
account and reading what the screen then does would settle it. Basis:
probe; claim check (the dialog opened on two throwaway
administrators' own rows and closed unconfirmed).
<sup>[f-a12](#fn-a12)</sup>

### OPS

<a id="ops1"></a>
**OPS1 — Two of this feature's emails are absent from OPS's Emails screen** · ❓ · latent.
OPS's email-templates screen lists neither the role-ended email nor the
masthead email; a manager on OJS or OMP finds both listed. The
role-ended email is still *sent* on OPS (Rule 8a) — its template is
seeded — so the only loss there is that a manager cannot customize it;
the masthead email is doubly absent ⚠ [A5](#a5). The screen itself is
*Emails management*'s (spec pending) to record; this entry holds the
cause. Question: deliberate trimming of OPS's mail list, or an override
that fell behind? Lean: fell behind — the roster override predates
these mailables. Basis: probe (the three screens and the OPS send);
code reading (the cause). <sup>[f-ops1](#fn-ops1)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

Draft note (2026-07-28; updated 2026-07-29): this spec was drafted from
code reading — the atom inventory `.reports/u53/code-inventory.md` plus
targeted reads — and from *User invitations*' live-probed register where
cited. It has since been checked against live probes A–D
(2026-07-28/29): footnotes name what each probe watched, and a claim
still resting on code reading alone says so. Evidence cited as a "fold
probe" (2026-07-29) was watched while the per-app test suites were
written and is folded in here. Evidence cited as a "claim check"
(2026-07-29) comes from the pass that re-tested this spec's own claims
on all three running apps — its item numbers (CC1-…, FV-…, R…-…, C…)
index `.reports/u53/`; where a claim check disproved an earlier
reading, the footnote says which reading was superseded. Empty
subclass chains
across all three apps cover the whole
execution path — grid handler, forms, API controller, mailables,
templates (inventory §2) — so shared-behavior claims rest on that; the
known seams are the per-app settings-handler role maps, the merge
extensions (OJS/OMP), and OPS's replacing mail roster.

<a id="fn-a"></a>
**a** — Doors: `SettingsHandler` op `settings` arg `access` →
`ManagementHandler::access()` (role map OJS: SITE_ADMIN+MANAGER; OMP/OPS:
MANAGER — inventory §5) plus `CanAccessSettingsPolicy` (site admin, or
manager group with `permitSettings`); older door op `access`, site admin
only, no settings policy. Live evidence: this feature's probe A
(2026-07-28): a site administrator reached the screen from the journal's
menu with no error dialog in all three apps — the accounts watched
there also hold a manager role in every journal. The pure
site-administrator case (the role-map seam) has since been isolated
(claim check CC1-14, CC1-05, 2026-07-29, all three apps, role-less
administrator fixture): no app's journal menu offers the screen to an
administrator holding no role there; OJS admits them at the typed
address (the role map lists SITE_ADMIN on the new door there), while
OMP and OPS refuse the new door — "The current role does not have
access to this operation." — and admit them only at the older
`management/access` door, site-admin-only by construction (their new
door's role maps carry MANAGER alone). An earlier fold
probe's cleaner reading of the OJS menu did not survive that check.
Menu visibility reduces
`permitSettings` over all the viewer's groups (`PKPTemplateManager`) —
inventory P1. No `Config::getVar` on the path affects
behavior (inventory §4).

<a id="fn-b"></a>
**b** — `AdminHandler::wizard($contextId)`, role SITE_ADMIN only;
`PKPSiteAccessPolicy` + `ReauthenticationRequiredPolicy` (the
password-confirm prompt is settings-gated and off on a stock install —
A7, f-a7);
`authorize()` returns false when a context is present in the request.
The typed-under-a-journal refusal watched live (probe A item 3,
2026-07-28, all three apps): the reader-facing journal site renders with
only "Access denied." as its content; the site-address door answers.
The journal row's collapsed expander (screen-reader name "Settings")
and the control row it reveals — "Edit", "Remove", "Settings wizard",
the last of these opening the settings pages — watched independently
in all three apps (fold probes, 2026-07-29); shared markup, no app
override.
The users grid inside the tab is fetched at the *edited* journal's path
and re-authorizes there (inventory §3). The Settings section's closing
sentence — no other configuration-file setting altering this feature's
on-screen behavior — rests on code reading alone (inventory §4: no
other configuration read on the feature's paths); it is a negative no
screen observation can establish, and none contradicted it (claim
check C44, 2026-07-29).

<a id="fn-c"></a>
**c** — Vue list: `UserAccessManager.vue` + `UserAccessManagerStore`
(25/page, live search via the users API, `status: 'all'`); cells
`UserAccessManagerCell*.vue` (Name renders ORCID icon and red
`DisableUser` icon; UserGroups/StartDate render only rows without an end
date). Account form: `UserDetailsForm` +
`templates/common/userDetails.tpl` (required flags read from the
template; username restriction `user.register.usernameRestriction`;
gossip gate `Repo::user()->canCurrentUserGossip()` — target must hold
ROLE_ID_REVIEWER in context, viewer manager/sub-editor/admin, never
self). Step 2: `UserRoleForm`, heading `grid.user.step2`. Reviewer
masthead checkboxes rendered `disabled` in `userDetailsForm.tpl`.
Heading, count-follows-filter, columns, hidden ended roles, 25/page and
the Enter-only search watched live (probe A items 5–6, 2026-07-28, all
three apps); the ORCID mark remains unwatched either way — no account
on this install carries an ORCID, and no screen in this feature puts
one on an account, so the route to the observation was closed, not the
claim disproved (claim check R1a-e, 2026-07-29, OJS). Lean: renders as
written; the Users & Roles row of an account that carries an ORCID
would settle it. The account form watched
live (probe B items 12–13, 2026-07-28 — the full form on OJS, the
create path in all three apps, edit on OJS and OMP): Country optional,
Change Password pre-ticked on create, the username shown but immutable
on edit, blank password fields keeping the current password, and every
Reviewer masthead row locked whether or not the account holds the
role, while the create screen's Step 2 locks nothing. Probe B's record
of a rejected username being refused in words did not survive the
claim check: the create form refuses it silently (FV-19 — f-a10). The
gossip gate's code reads as never-self, and on this form the claim
check agrees: a viewer holding site administrator, Journal manager and
Reviewer in the same journal saw no Editorial Notes block on their own
record while a peer reviewer's record in the same session showed it
(FV-23, 2026-07-29, OJS). The earlier contrary reading — an
administrator's own record showing the block with its content,
identical to a control record (top-up probe, 2026-07-29, OJS and OMP,
disposable-administrator fixture) — was taken on the reviewer-picking
screen, another feature's surface, where it stands. The gate's
administrator arm also did not show on this form: an administrator
whose only role in the journal is Author got the form without the
block, while an administrator who is also its manager saw it (FV-22,
OJS; the positive case also OMP) — on screen the block follows the
manager or sub-editor role, not administrator status. The block
appears nowhere on the Users & Roles "Edit" page (top-up probe,
2026-07-29). The one surface not
watched for the self case is the workflow's reviewer panel — and it
cannot be: a manager who is themselves a reviewer on the submission
cannot open that panel at all, so no user can meet the case there.
Claim check (2026-07-29, OJS): the name and affiliation boxes render
once, in the site's primary language, no locale switcher, on
single-locale and two-locale journals alike (FV-3; C40 — on
`publicknowledge` too, whose *other* settings forms do carry a
switcher — the shared form itself is what omits it); the password
minimum is stated on the edit form only — "Leave the password fields
blank to keep the current password. The password must be at least 6
characters." — while the create form names none (FV-12). The masthead
checkbox list opens fully ticked for held and unheld roles alike, the
reviewer rows the only locked ones (FV-26, FV-27, all three apps).
Working Languages is present on the two-language site as written
(FV-20; C39); whether it disappears with one language was not watched
— turning a language off is a site-wide change on this shared install,
so that route was closed, not disproved (FV-20). Lean: as written, the
shared form's own condition; the Add User and Edit User forms on an
install with a single enabled language would settle it.

<a id="fn-d"></a>
**d** — `UserGridHandler::initialize()` / `getFilterForm()` →
`userGridFilter.tpl`: fields `search`, `userGroup` (default
`grid.user.allRoles`), `includeNoRole`
(`user.noRoles.selectUsersWithoutRoles`), submit `common.search`;
`PagingFeature`. Watched (probe B item 10, 2026-07-28): the filter
renders collapsed behind the header's "Search" link and collapses again
after each submit; the row remove action is labelled "Remove". The full
row-action roster — Email · Edit User · Disable User · Remove · Login
As · Merge User, with Login As and Merge User dropping off rows the
viewer cannot fully administer and Remove off role-less rows — watched
across every row of three journals (claim check R1b-c, OJS; CC1-21,
all three apps, 2026-07-29). The
handler also reads a search-field/match selector that the template never
renders — dead plumbing, no on-screen trace (inventory §9.5), so nothing
to document body-side.

<a id="fn-e"></a>
**e** — Vue row Edit (AFFM-103) → `management/settings/user/{userId}`
(`ManagementHandler::editUser()`) — the *User invitations* edit-user
mode, live-watched during that feature's work (its footnote f-p). The
Edit and Email offers on the viewer's own row
are unguarded in `getItemActions` (inventory P10); watched (probe A
item 9, 2026-07-28): the viewer's own row offers exactly Edit and Email.
The role-less administrator's dangling Edit watched (claim check
CC1-17, 2026-07-29): on OMP and OPS the row offers it and the page
answers "The current role does not have access to this operation."
(the edit-user route rides the same per-app role map as the settings
door — fn-a); on OJS it opens.
Legacy row Edit (AFFM-206) → `UserDetailsForm` modal.
The form's partial-administration mode (identity read-only,
`userDetailsReadOnly.tpl`) appears unreachable in current UI: only site
administrators reach the legacy form, and they are FULL over anyone
they aren't flatly barred from — left out of the body for that reason.

<a id="fn-f"></a>
**f** — `Validation::getAdministrationLevel(target, actor, contextId?)`:
self → FULL; target site admin → PROHIBITED; actor site admin → FULL;
actor no manager group anywhere → PROHIBITED; target in any context the
actor doesn't manage → PARTIAL (with context) / PROHIBITED (without);
else FULL. Ops: edit/remove check *with* context (PARTIAL tolerated);
disable/merge/update-roles check *without* context and demand FULL
(inventory §5). Refusal string `grid.user.cannotAdminister` — watched
rendering inside the Vue disable dialog against a cross-journal target
and an administrator target (probe D item 24, 2026-07-28/29; top-up
probe 2026-07-29 — f-a2); the legacy surface's per-row Disable (fn-h)
has not been driven against a target its viewer cannot administer, so
whether it renders the same refusal is unwatched.

<a id="fn-g"></a>
**g** — `UserGridHandler::editEmail/sendEmail`: gate is a role check on
the *sender* (site admin at site level, or manager in context), no
administration-level check on the target; the Vue action is offered
unconditionally, own row included (inventory §6; the own-row offer
watched, probe A item 9, 2026-07-28). Form: `userEmailForm.tpl` (`email.subject`,
`email.to` disabled, `email.body`, submit `common.sendEmail`). Form
and delivery watched (probe B item 14, 2026-07-28, all three apps):
the message arrives as typed, from the sender's own address, own row
included.

<a id="fn-h"></a>
**h** — `UserDisableForm::execute()`: sets only the disabled flag and
reason; on disable, `SessionGuard::invalidateOtherSessions(target)`.
Labels: Vue `grid.user.enable`/`grid.user.disable` ("Enable User"/
"Disable User"); Vue dialog
`user.disabledModal.title`/`user.enabledModal.title`, description
`user.disabledModal.description` ("Current Roles : {roles}"). Watched
(probe D items 17–18, 2026-07-28/29, OJS and OMP): the red mark and
the "Enable User" flip, the open window thrown back to sign-in, the
refusal quoting the stored reason ("Your account has been disabled for
the following reason: …"), the reason pre-filled on enable, and the
Vue dialog naming the user and listing already-ended roles under
"Current Roles". The Hosted Journals surface: an earlier fold-probe
record of finding no per-row Disable there was wrong and is
superseded — the claim check drove disable → enable end to end from
that grid (CC1-21, 2026-07-29, all three apps) and read "Disable
User" on every user row of three journals (R1b-c, OJS). Its dialog:
headed "Disable User"/"Enable", body reason box + note + Cancel/OK,
no name, no roles (R5-a; CC1-21). The notes (both surfaces' dialogs —
R5-g; CC1-21): disable — "Please note that once a user is disabled,
you won't be able to add them to any roles until the user is enabled
again."; enable — "Once the user is enabled, they will regain access
to the site, and you'll be able to invite them to roles as needed."
Cross-list interplay watched (claim check, OJS): disabled from Hosted
Journals → red mark on Users & Roles, and the Users & Roles enable
dialog pre-filled with the reason typed on the other surface; the
disabled account's Hosted Journals row itself carries no mark (R4-c,
OJS) — its label pair is asymmetric, "Disable User" / "Enable". A
journal row's own revealed control row still offers exactly Edit,
Remove and Settings wizard — the per-row Disable lives on the user
rows inside the wizard's Users tab. Own-row offers: the Users & Roles
stripping watched for a Journal Manager and a Site Administrator
alike (top-up probe, 2026-07-29); the Hosted Journals own row offers
Disable User (CC1-22, all three apps; R1c-b, OJS — the dialog opened
on two throwaway administrators' own rows and closed unconfirmed, so
the operation's effect on its own actor remains unwatched —
[A12](#a12)).

<a id="fn-i"></a>
**i** — `UserGridHandler::removeUser()`: end-dates every active role
assignment in the context (history preserved); zero active roles →
`grid.user.userNoRoles`; CSRF-checked; no mailable anywhere on the path.
Confirm: `manager.people.confirmRemove` (journal/press/server wording
per app locale — verified in all three `.po` files). Re-adding creates a
new assignment (`assignUserToGroup`), it does not clear the end date.
Watched (probe D item 19, 2026-07-28/29, all three apps): one
confirmation, no mail — shown against a positive control — the row
remaining with empty Roles and Start Date cells, the count unchanged,
and a re-granted role dated that day. Neither list offers Remove on a
role-less row (probe D items 18–19), so the fallback answer
`grid.user.userNoRoles` ("This user does not have any roles.") is one
no screen can produce.

<a id="fn-j"></a>
**j** — `UserGridHandler::mergeUsers` two-pass (target grid titled
`grid.user.mergeUsers.mergeIntoUser`; confirm
`grid.user.mergeUsers.confirm` — the permanence sentence is in the
string, all three apps; rendered as read when watched, closing
inventory P5). Merges run live (probe D item 22, 2026-07-28/29, all
three apps): on confirming, the row vanishes at once, the count drops,
the duplicate's open session lands back at sign-in, the old username is
refused as unknown, and the survivor carries the duplicate's role with
its original start date and masthead choice — closing inventory P4. The
target-list offers watched on OJS (probe C item 16 · probe D item 22):
the duplicate listed with only its action withheld, the viewer's own
account listed and offered, to manager and administrator alike — the
"self always false" guard below covers the source map only. Of the
re-credited history, the submissions half is watched (claim check C11,
2026-07-29, OJS): after the merge the survivor's own "My Submissions"
lists the duplicate's submission. Reviews, decisions, files, notes,
notifications and email history still rest on the code below — no
screen was driven that shows them for an account that no longer
exists; a screen showing the survivor holding a review or a file the
duplicate produced would settle them (claim check C11). The
subscriptions and completed-payments clause likewise rests on the
code: the route to watching it was closed on this install, not
disproved — the scratch material could not be seeded with a
subscription, and reaching one through the screens takes subscription
publishing mode, a subscription type and a subscription, three
settings forms outside this feature (claim check C13); the survivor's
subscription record after merging away an account that holds one
would settle it. Merge body:
`PKP\user\Repository::mergeUsers()` — re-attributes files, notes,
decisions, reviews, logs, comments, notifications; invalidates the old
user's sessions; copies role assignments the target lacks (dates +
masthead kept); transfers stage assignments; deletes the old user. OJS
override adds subscriptions + payments; OMP adds payments; OPS binds the
base class (inventory §2 — an intended-looking divergence). Gates: Vue
`user.canMergeUsers` (context-scoped map, self always false); server
FULL without context.

<a id="fn-k"></a>
**k** — `PKPUserController::endRole()` (ends one assignment, sends
`UserRoleEndNotify` unconditionally; template `USER_ROLE_END` seeded in
all three registries) and `::masthead()` (400 for reviewer groups
`api.400.reviewerMastheadCannotBeChanged`; silent no-op when unchanged;
otherwise writes via `setUserUserGroupMasthead` *then* looks up
`USER_ROLE_MASTHEAD_UPDATE` and throws if absent — the template exists
only in OJS's `registry/emailTemplates.xml`; the upgrade migration skips
keys absent from the app registry, so OMP/OPS never gain it — inventory
§4, §7). Both routes admit SUB_EDITOR via group middleware, no
administration-level check (behind *User invitations*' register entry
A8, live-probed there). Sole front-end caller: *User invitations*'
roles table (inventory §6). Watched (probe D
items 20–21, 2026-07-28/29): the role-ended email arrives on OJS and
OPS — and the claim check has since watched it arrive in all three
apps, OMP included (C19, C22, 2026-07-29), settling what the earlier
pass could not exercise (CC1-34); the masthead change on OMP and OPS saves, then surfaces the raw
migration-naming error to the manager (f-a5), while OJS saves silently
and mails; re-choosing the set value does nothing and mails nobody in
any app. The reviewer row's fixed "Appear on the masthead" cell watched
on OJS (probe D item 21); the OMP half of that claim rests on the
shared page and the account form's matching reviewer lock (fn-c) — the
reviewer-group 400 refusal stays code-read, with no screen left that
could send it. The Section Editor acceptance likewise has no surface
in this feature: the claim check found no screen observation that
could show or contradict it (CC1-25), so it stays carried by
*User invitations*' register alone.

<a id="fn-l"></a>
**l** — `UserCreated` mailable, key `USER_REGISTER`, sent from
`UserDetailsForm::execute()` on create only, when `sendNotify` or
`generatePassword` (which forces send); `replyTo` = context contact.
Registry row present in all three apps. Send and content watched
(probe B item 12, 2026-07-28, all three apps): the mail leaves on
saving the details, before roles are chosen; the password rides in
clear text whether typed or generated; with no journal contact address
set, the mail carries no reply-to. Editing sends nothing by mail
(probe B item 13).

<a id="fn-m"></a>
**m** — `PKPUserController::getReport()` streams
`user-report-{date}.csv`; route group middleware admits site admin /
manager / sub-editor. No caller in `lib/ui-library/src`, any
`templates/`, or app code (inventory §9.9, P9). Browser-reachable as a
typed GET address — in scope as a typed URL. Contents (claim check
C26, 2026-07-29, OJS): the file lists only accounts holding at least
one active role in the journal; accounts whose roles were all ended —
still on the "Current Users" list with empty Roles cells — are absent.

<a id="fn-n"></a>
**n** — Suggest button (`common.suggest`) → headless
`UserApiHandler::suggestUsername`, gated only "any signed-in user"
(`PKPSiteAccessPolicy`, all roles) — the wide gate has no user-facing
surface of its own; recorded here as provenance.

<a id="fn-o"></a>
**o** — Claim check C38 (2026-07-29, OJS; the earlier pass had left
the conditional unexercised at CC1-13): driven on a Journal editor
role — option ticked, the manager's menu lists "Users & Roles" and the
screen opens; unticked, the menu entry is gone and the typed address
answers "The current role does not have access to this operation.";
re-ticked, both return. The default Journal manager row on the Roles
tab renders with no expander and no row actions, so the option cannot
be unticked on that role from any screen.

<a id="fn-p"></a>
**p** — Claim check C47 (2026-07-29, all three apps, scratch contexts
and `publicknowledge`): as installed the tabs read Users · Roles ·
Site Access Options · ORCID; ticking the journal under Administration
→ Site Settings → Bulk Emails made the Notify tab appear for its
manager at once — Users · Roles · Notify · Site Access Options ·
ORCID — and unticking removed it again.

<a id="fn-s1"></a>
**s1** — Scenario 1: seed a scratch journal + a known user via the
scenario endpoints; search behavior and row contents per fn-c, watched —
the search submits on Enter, not as you type. Edit landing page:
*User invitations*' footnote f-p.

<a id="fn-s2"></a>
**s2** — Scenario 2: Mailpit-scoped by recipient; sender and delivery
watched per fn-g. The own-row Email offer (inventory P10) can ride
this scenario as a one-look check.

<a id="fn-s3"></a>
**s3** — Scenario 3: needs a scratch user with roles in one journal
only (a FULL target for a manager, per fn-f). Sign-in refusal wording
belongs to the login feature; this scenario only asserts refusal and
the quoted reason. Disabled-icon and label flip per fn-h — watched.

<a id="fn-s4"></a>
**s4** — Scenario 4: confirm wording per fn-i (all three apps' locale
files); "no email" asserted via Mailpit silence for the recipient,
against a positive control (fn-i).

<a id="fn-s5"></a>
**s5** — Scenario 5: run as site admin to guarantee FULL (fn-f); stage
two scratch accounts, give the duplicate some history (e.g. a role +
an email log entry) to watch the re-crediting; post-merge display
watched per fn-j (P4 closed).

<a id="fn-s6"></a>
**s6** — Scenario 6: wizard entry per fn-b (on a stock install the
re-auth prompt does not fire — A7, f-a7); welcome mail per fn-l;
step-2 heading
`grid.user.step2`. Run end to end (probe B item 12, 2026-07-28, all
three apps).

<a id="fn-s7"></a>
**s7** — Scenario 7: stage the shared user via a second scratch journal
(the seeded `publicknowledge` context is read-only). Refusal per fn-f,
its in-dialog rendering watched (probe D item 24 — f-a2).
Remove-still-works: `removeUser` tolerates PARTIAL (fn-f); watched on
the same row.

<a id="fn-s8"></a>
**s8** — Scenario 8: the edit page's confirmations belong to *User
invitations* and were watched live during that feature's own evidence
pass — each single-role change mails as it happens; the Remove silence
is fn-i. This scenario is A1's demonstration. Both dialogs — the
"Remove Role" confirmation and the single-"Close" last-role refusal —
were seen on screen in that pass; the exact strings quoted in Rule 8a
and here were then taken from the language files rather than
transcribed off the screen: `user.removeRole.message` and
`user.removeRole.roleRemainMessage` in the shared locale file, no app
override (grep 2026-07-29).

<a id="fn-a1"></a>
**f-a1** — `endRole()` sends `UserRoleEndNotify` unconditionally;
`removeUser()`'s path (`UserGridHandler` → end-date loop) constructs no
mailable. *User invitations*' claim-check C1 watched the single-role
email live
(2026-07-28); the Remove silence watched (probe D item 19,
2026-07-28/29, all three apps, against a positive control). Both halves
watched back to back (probe D item 20, 2026-07-28/29, OJS and OPS): the
single-role email ("You have been removed from a role") arrived each
time, OPS included, and removing all remaining roles afterwards added
nothing to the mailbox. The claim check re-drove the single-role
notice in all three apps (C19, C22, C35, 2026-07-29), OMP included —
the all-apps reading the body carries is watched, not inferred.

<a id="fn-a2"></a>
**f-a2** — Vue disable action condition is `!== self` only; server
demands FULL without context (fn-f) → shared-context and administrator
targets refuse. Merge: `canMergeUsers` from `permissionMapForManager`
vs server FULL-without-context — inventory §5 "what the users table
shows" + P2/P3. Watched (probe C item 15 · probe D items 23–24,
2026-07-28/29, OJS and OMP; the administrator-target corner via the
disposable-administrator fixture, top-up probe 2026-07-29, all three
apps): Merge user and Login As absent on the administrator's row and on
a cross-journal user's row while Edit, Email, Remove User and Disable
User remain; a peer manager's row offers all six; Disable on either row
renders `grid.user.cannotAdminister` inside the dialog, in place of the
reason field and OK button, with "Close" the only button; Remove on the
cross-journal row succeeds, other journal's role untouched, and on the
administrator's row reaches its ordinary confirmation (not confirmed —
destructive against a live administrator). Positive control (top-up):
the same manager disabling a plain reviewer got the reason dialog with
"OK", the red disabled mark, and the reviewer's sign-in refused with
"Your account has been disabled…". The code-read suspicion that the
journal-scoped merge map could dangle Merge did not materialize on any
watched row. Still unwatched: the "Merge into this User" target list's
offer against an administrator as the surviving account.

<a id="fn-a3"></a>
**f-a3** — `UserAccessManagerCellActions.vue` labels its
`DropdownActions` with the key `userAccess.management.options`; the key
exists in no `.po` file in lib/pkp or any app (grep 2026-07-28). Watched
(probe A item 8, 2026-07-28): assistive technology is handed
`##userAccess.management.options##` verbatim, while the same table's
"More Actions" header is properly translated. Same class as
*User invitations*' register entry A5.

<a id="fn-a4"></a>
**f-a4** — `userAccess.search` in shared `lib/pkp/locale/en/userAccess.po`,
value "Enter a user's name, role (e.g Journal editor), or affiliation";
no override in any app locale (grep 2026-07-28, all three repos). On
screen as predicted in all three apps (probe A item 7, 2026-07-28).

<a id="fn-a5"></a>
**f-a5** — Registry: `USER_ROLE_MASTHEAD_UPDATE` in OJS's
`registry/emailTemplates.xml` only; `I11800_AddUserRoleMastheadUpdateEmail`
iterates the app's own registry and skips absent keys (inventory §4).
`masthead()` writes the change, then
`Repo::emailTemplate()->getByKey(...)` miss throws a raw exception
naming the migration. Locale strings for the mailable exist in shared
`lib/pkp/locale/en/emails.po` — a registry gap, not a translation gap.
Watched (probe D item 21, 2026-07-28/29, OMP and OPS with OJS as
control — inventory P6 closed): the save lands, the error names the
migration, no mail leaves; on OJS the same flow saves silently and
mails the user. Re-confirmed by claim check (C23, C48, 2026-07-29).

<a id="fn-a6"></a>
**f-a6** — `PKPUserController::getReport()`; no UI caller anywhere
(fn-m). Inventory P9. Probe A item 4 (2026-07-28): a Journal Manager's
typed request streams the CSV (one Yes/No column per role); an Author's
or Reader's gets a bare error and no file; no screen in any app mentions
the address. Claim check (2026-07-29, OJS): C26 — the file's
role-holders-only contents (fn-m); C27 — Statistics → Users' "Export"
clicked: no download, no message, no visible response.

<a id="fn-a7"></a>
**f-a7** — `ReauthenticationRequiredPolicy` on `AdminHandler` (fn-b);
`PKPSessionGuard::isElevatedSessionActive()` reads `[security]
password_timeout` (minutes) from the app-root `config.inc.php`; unset →
reauthentication is never required, which is why probe A item 2
(2026-07-28) went straight through on the stock installs. Top-up probe
(2026-07-29, disposable-administrator fixture, all three apps, window
configured): "Confirm Access / Signed in as … Please enter your
password to continue." met the first site-administration address after
sign-in and every further one until answered, re-appeared once the
window lapsed, and refused a wrong password with "Invalid
username/email or password. Please try again."; the journal-level
Users & Roles screen never prompted. Settles what was the Settings
section's one-possible-exception clause.

<a id="fn-a8"></a>
**f-a8** — Watched (probe A item 2; probe B item 10, 2026-07-28): the
same administrator account read from both lists in each app. The
administrator watched is the install's own, auto-enrolled into every
scratch journal — which bounds the entry without settling it: a
role-less administrator fixture appears in neither of a journal's
lists in any app, surfacing on the Hosted Journals list only via the
no-roles filter box (claim check CC1-01, CC1-02, 2026-07-29, all
three apps), and a second administrator enrolled in one journal only
appears in that journal's lists alone (claim check R1-d, OJS). The
differing descriptions are therefore of an administrator who is
enrolled there. Which list errs remains unsettled by either
observation.

<a id="fn-a9"></a>
**f-a9** — Watched on all three apps (probe D item 21, 2026-07-28/29):
the same confirmation text renders on OJS, OMP and OPS — no app
overrides the noun or the notification promise. The promise's failure
on OMP/OPS is f-a5's mechanism. Same class as f-a4.

<a id="fn-a10"></a>
**f-a10** — Claim check FV-19 (2026-07-29, OJS; with FV-8 and FV-12):
six faulty saves driven against the create form — a username outside
its allowed characters, a short password, a mismatched repeat and an
address already in use among them — each left the dialog open with the
typed values in place and produced no field error, banner or
notification of any kind; only the empty-required-field case produced
"This field is required." Supersedes probe B's earlier record of a
rejected username being refused in words (fn-c). The form is the
shared `UserDetailsForm` (fn-c); OMP and OPS not driven.

<a id="fn-a11"></a>
**f-a11** — Claim check R2-c (2026-07-29, OJS, reproduced in a second
journal): a username search and an email-address search each found
their person; searching "Author" returned the administrator's row
("Journal manager" in its Roles cell), which the Hosted Journals
filter's role dropdown does not return under Author. The search rides
the users API's search phrase (fn-c); which stored role records it
matches was not read.

<a id="fn-a12"></a>
**f-a12** — Own-row offers: the Vue list strips to Edit + Email for a
Journal Manager and a Site Administrator alike (claim check CC1-12;
probe A item 9 — fn-e); the legacy list's own row offers Disable User
in all three apps (CC1-22, the install administrator's row, dialog not
opened), and the dialog was opened and closed unconfirmed on two
throwaway administrators' own rows (R1c-b, 2026-07-29, OJS). The
lean's basis: `Validation::getAdministrationLevel()` returns FULL for
self (fn-f) and the legacy disable action carries no self guard
(inventory §5), so nothing read in code stands between OK and a
disabled own account; whether the actor's live session then drops as
another account's would (fn-h) is part of what confirming would show.

<a id="fn-ops1"></a>
**f-ops1** — OPS `APP\mail\Repository::map()` replaces (does not merge)
the shared mailable roster and omits `UserRoleEndNotify` and
`UserRoleMastheadUpdateNotify`; `USER_ROLE_END` remains seeded in OPS's
registry so sending still finds a template (inventory §2, §4). Listing
behavior watched (probe C item 26, 2026-07-28/29 — inventory P7
closed): OJS and OMP list both notices on the Emails screen, OPS lists
neither. OMP listing the masthead notice while lacking its template
(f-a5) shows the roster and the template registry are separate lists.
The screen is U56's surface.

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Users & Roles screen (container) | `/{ctx}/management/settings/access` · `/{ctx}/management/access` | VUE-013 |
| Users table (Vue) | Users & Roles → Users tab | VUE-051 |
| Search users (Vue) | table header | AFFM-102 |
| Row Edit / Email / Remove / Enable-Disable / Merge (Vue) | row options menu | AFFM-103, 104, 106, 107, 108 |
| Site wizard Users tab (legacy grid) | `/index/admin/wizard/{contextId}` | GRID-050 |
| Add User / filter / row actions (legacy) | grid + rows | AFFM-203..208, 210 |
| Users API (list, get, endRole, masthead; reviewers/report riders) | `/{ctx}/api/v1/users/...` | API-047 |
| Username suggestion (headless) | component call from the account form | GRID-003 |
| Welcome / role-ended / masthead emails | mail | MAIL-054, 056, 057 |

## Reference — code anchors

- `lib/pkp/controllers/grid/settings/user/UserGridHandler.php` (+ `UserGridRow`, the four forms)
- `lib/ui-library/src/managers/UserAccessManager/` (Vue list, store, actions, config)
- `lib/pkp/api/v1/users/PKPUserController.php` — list/get/endRole/masthead/reviewers/report
- `lib/pkp/classes/security/Validation.php` — `getAdministrationLevel()`
- `lib/pkp/classes/user/Repository.php` (+ OJS/OMP `APP\user\Repository`) — `mergeUsers()`
- `lib/pkp/classes/mail/mailables/` — `UserCreated`, `UserRoleEndNotify`, `UserRoleMastheadUpdateNotify`
- `lib/pkp/pages/management/ManagementHandler.php` · `lib/pkp/pages/admin/AdminHandler.php`
- `lib/pkp/templates/common/userDetails.tpl` and `controllers/grid/settings/user/**`
