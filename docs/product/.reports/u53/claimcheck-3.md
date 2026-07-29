# U53 Users management — claim check, chunk 3

**Frame.** QA documentation of the applications' own screens, on a local disposable
test install with seeded accounts. Every observation below was made by signing in
through the sign-in screen as a named role and working the screens (including typing
an address straight into the address bar). No hand-built API calls against the
feature, no altered form payloads, no credentials carried between sessions, no client
but a browser. Scratch journals/presses/servers and throwaway accounts were seeded
through the scenario endpoints; that is fixture setup, not a probe of the feature.

**Scope of this chunk.** The spec `docs/product/specs/users-management.md` —
Rules & state from the removal rules (Rule 6) to the end of the section (Rules 7, 8,
9); the whole **Side effects** section; the whole **Settings that modify behavior**
section; the whole **Cross-feature interactions** section. Rules 1–5 and the Actors /
Fields tables belong to chunks 1 and 2; two observations that fall in their territory
but that this chunk produced hard evidence for are recorded at the end under
*Adjacent observations*, unclaimed by this chunk's verdict counts.

**Where run.** OJS `http://127.0.0.1:8000` throughout, plus OMP `:8100` and OPS
`:8200` for every claim the spec marks as differing per app (the masthead change and
its error, the role-ended notice, and the email-templates screen).

**Accounts.** Everything acted on was created for this run under the prefix
`u53cc3`. Scratch contexts on OJS: `u53cc3a` (Journal A, contact address set),
`u53cc3b` (Journal B, **no** contact address), `u53cc3c`…`u53cc3f` (D, E, F used);
on OMP `u53cc3omp`, `u53cc3omp2`, `u53cc3omp3`; on OPS `u53cc3ops`, `u53cc3ops2`.
Site administrators were seeded with the new `users[].roles: ['siteAdmin']` fixture
(`u53cc3.adm`, `u53cc3.adm.omp`, `u53cc3.adm.ops`); the install's own `admin` was
never used and never acted on.

**Mail.** Scoped per test by a unique throwaway recipient address
(`u53cc3-<who>-<app>@mail.test`). Mailpit was never cleared. Every silence claim is
paired with a positive control taken on the same screen (the row's "Email" action),
recorded inline.

**Verdict tally.** 51 claims checked — **44 holds** (one of them, C11, holds only for
the part that could be watched), **3 wrong**, **0 unreachable**, **4 undetermined**.

---

## Rules & state — Rule 6, "Remove User"

Screen: OJS, Journal A, `Settings → Users & Roles`, Users tab, "Current Users" table,
as Journal Manager `u53cc3.mgr`. Row located by the user's email in the second
`table` on the page; the row's only `button` carries the accessible name
`##userAccess.management.options##` and opens the row menu.
Target: `u53cc3.rem` — Author + Reader in Journal A, **and** Author in Journal B.

### C1 — the confirmation's wording · **holds**
> "after one confirmation: *Remove this user from this journal? This action will
> unenroll the user from all roles within this journal.*"

Row menu → "Remove User". A dialog titled **"Remove"** opened reading exactly
*"Remove this user from this journal? This action will unenroll the user from all
roles within this journal."* with buttons **OK** and **Cancel**. Word for word as
written.

### C2 — it ends every role here, in one stroke · **holds**
Before: Roles cell "Author / Reader", Start Date "2026-07-29 / 2026-07-29". One
confirmation (OK). After: both roles gone together; no second prompt, no per-role
step.

### C3 — the account survives, still signs in, keeps its roles elsewhere · **holds**
Signed in afresh at `/index.php/index/en/login` as `u53cc3.rem` — sign-in succeeded
and landed on the site index. Journal B's Users & Roles (opened as
`u53cc3.adm`) still shows the row with **Author · 2026-07-29**.

### C4 — the ended roles keep their history · **holds**
Two independent surfaces show it. The row's "Edit" page lists **Author 2026-07-29 →
2026-07-29** and **Reader 2026-07-29 → 2026-07-29**, each with "User Removed From
Role" where the action button was. The Disable dialog on the same row still lists
*"Current Roles : Author, Reader"* after the removal.

### C5 — "No email tells the user" · **holds** (with positive control)
Mailpit for `u53cc3-rem-ojs@mail.test`: empty before the removal, **still empty**
after it. Control on the same screen: the row menu's "Email" action, subject
*"u53cc3 control after remove"*, delivered from `u53cc3-mgr-ojs@mail.test` within
seconds — so the channel was live and the removal genuinely sent nothing. Repeated
on a second account (`u53cc3.rol`, control *"u53cc3 control after remove-all"*).

### C6 — the row stays, its cells empty, the count unchanged · **holds**
Heading read **"Current Users (7)"** before and **"Current Users (7)"** after. The
row remained, with Roles and Start Date now blank.

### C7 — "re-granting a role later starts it fresh, dated that day" · **holds**
Re-granted Reader from `Administration → Hosted Journals → (journal) Settings wizard
→ Users → Edit User` (role checkbox list), as `u53cc3.adm`. The edit page then showed
**three** rows: Author (ended), Reader (ended), and a **new** Reader with no end date,
started today. A fresh assignment, not a revived one. Caveat worth stating: the
install is one day old, so every date on screen reads 2026-07-29 — the *freshness* is
evidenced by the extra row, not by the date differing.

### C8 — "neither list offers Remove User on that row at all" · **holds**
With no active role: the Users & Roles row menu offered exactly
`["Edit","Email","Disable User"]` — no "Remove User". On the Hosted Journals list the
same account's revealed control row read `Email · Edit User · Disable User · Login As
· Merge User` — no "Remove", while a role-holding row on the same table read
`Email · Edit User · Disable User · Remove · Login As · Merge User`. Both lists agree.

---

## Rules & state — Rule 7, "Merge user"

Two merges were run, both as Site Administrator `u53cc3.adm`, both on accounts
created for this run. **Accounts destroyed: `u53cc3.dup` (merged into `u53cc3.srv`)
and `u53cc3.dup2` (merged into `u53cc3.srv2`).** In each case the confirmation was
read on screen and both usernames checked for the `u53cc3.` prefix before OK was
pressed.

### C9 — two steps, the second list's title, the confirmation's wording · **holds**
Row menu → "Merge user" opens a second user list headed **"Merge into this User"**
(inside a modal; it is the older grid, each row an expander revealing a single action
"Merge into this User"). Picking the survivor produced, verbatim on screen:

> Are you sure you wish to merge the account with the username "u53cc3.dup" into the
> account with the username "u53cc3.srv"? The account with the username "u53cc3.dup"
> will not exist afterwards. This action is not reversible.

Second merge, same wording with `u53cc3.dup2` / `u53cc3.srv2`. Dialog title
"Confirm"; buttons OK / Cancel.

### C10 — the duplicate is listed there, with only its own action withheld · **holds** (with a wording caveat)
The duplicate's row **is** present in the "Merge into this User" list. It is the only
row in that table with **no control at all** — the other rows each carry the
expander (screen-reader name "Settings") that reveals "Merge into this User"; the
duplicate's row carries no expander, so the action cannot be reached. The effect the
spec describes is right; on screen it is the whole expander that is missing, not a
greyed-out action.

### C11 — everything the old account did is re-credited · **holds in part**
Watched: a submission the duplicate had authored in Journal A. After the merge, the
survivor `u53cc3.srv` signing in and opening `Dashboard → My Submissions` sees
**"My Submissions as Author — Active submissions (1)"** with that submission listed.
The submissions half of the claim is confirmed on screen. Reviews, decisions, files,
notes, notifications and email history were **not** individually watched — no screen
was driven that would show them for a merged-away account. The sentence is not
disproved; it is verified for one of its seven nouns.

### C12 — the old account's roles are added to the survivor, keeping dates and masthead choices · **holds**
First merge: survivor's Roles cell went from "Reader" to **"Reader / Author"**, both
dated 2026-07-29. Second merge tested the masthead half deliberately: before merging,
`u53cc3.dup2`'s Series/Section editor role was set to **"Appear on the masthead"** on
the edit page. After the merge, the survivor's edit page showed two roles — Reader
with the masthead control reading *Does not appear*, and Section editor reading
**Appear on the masthead**. The duplicate's masthead choice travelled with its role.

### C13 — {OJS} subscriptions and completed payments move; {OMP} payments; OPS neither · **undetermined**
Not checked. The scenario endpoint rejects a `subscriptions` key on this install
("Unknown key 'subscriptions' in the scenario spec"), and reaching a subscription
through the screens needs subscription publishing mode plus a subscription type plus a
subscription — three settings forms outside this feature. Nothing observed contradicts
the clause; nothing observed supports it either.

### C14 — the old account is deleted permanently; no screen can undo · **holds**
After each merge the source row was gone from both lists (the Hosted Journals table
went from "1 - 7 of 7 items" to "1 - 6 of 6 items"), and no screen visited offered any
undo. Signing in with the old username is refused (C16).

### C15 — the row is gone at once and the count drops by one · **holds**
Heading **"Current Users (7)" → "Current Users (6)"** on the first merge, and
**(4) → (3)** on the second, on the same page load path (list reloaded after the
confirmation).

### C16 — the duplicate's open window is thrown back to sign-in; the username is thereafter refused · **holds**
A second browser session was signed in as `u53cc3.dup` and left open at
`/u53cc3a/dashboard/mySubmissions` before the merge. Reloading it after the merge
landed on `/u53cc3a/login?source=…` — the journal's sign-in page. A fresh sign-in
attempt with `u53cc3.dup` and its password was refused with **"Invalid username/email
or password. Please try again."** The spec's "refused at sign-in as unknown" is
accurate as behaviour; the screen does not say "unknown", it gives the ordinary
wrong-credentials message.

### C17 — no email leaves on a merge · **holds** (with positive control)
Mailpit for both the source and the survivor addresses was empty of anything merge-
related before and after (the survivor of merge 2 had **no** mail at all). Control on
the same screen afterwards: the survivor's row menu → "Email", subject *"u53cc3
control after merge"*, delivered.

---

## Rules & state — Rule 8, single-role operations

Screen: the user's edit page reached from the row's "Edit"
(`/{journal}/management/settings/user/{id}`), as Journal Manager. The roles table
carries a **Remove Role** button per row and a **Journal/Press/Server Masthead**
`select` per row.

### C18 — "both act immediately, before any invitation business completes" · **holds**
Neither operation waits for the page's own **"Save And Continue"**. Ending a role and
changing a masthead value each took effect on the spot: after a plain reload — with
"Save And Continue" never pressed — the ended role showed its end date, and the
masthead select came back holding the new value.

### C19 — ending one role ends just that role, and always emails the user · **holds** (OJS · OMP · OPS)
OJS, `u53cc3.rol` (Author + Reader): "Remove Role" on the Author row → dialog
*"Remove Role — Are you sure you want to remove this role? The user will lose access
and permissions associated with it."* (buttons "Remove Role" / "Cancel"). After
confirming, only the Author row carried an end date; Reader was untouched. Mail
arrived at `u53cc3-rol-ojs@mail.test`, subject **"You have been removed from a role"**,
body naming the role and the journal.
Repeated on OMP (`u53cc3.rol.omp`, Journal/Press F) and OPS (`u53cc3.rol.ops`) — same
subject arrived in both. The role-ended notice is sent on all three apps, OPS
included.

### C20 — ending the user's last role is refused on the edit page itself · **holds** (OJS · OPS)
With one role left, "Remove Role" opened a dialog reading **"Remove Role — You cannot
remove the role. At least one role must be assigned to the user."** whose only button
is **Close**. Same on OPS. Nothing was sent and nothing changed.

### C21 — the masthead confirmation promises a notification and says "journal masthead" in every app · **holds** (OJS · OMP · OPS)
Changing the select fires a dialog reading, identically in all three apps:

> **Confirm masthead visibility change**
> This will update whether this user appears on the **journal** masthead for the
> selected role. The user will be notified of this change.

— on OMP where the same page's column header reads "PRESS MASTHEAD" and the field
label "Press Masthead", and on OPS where they read "SERVER MASTHEAD" / "Server
Masthead". Both halves of ⚠ A9 confirmed on screen.

### C22 — on OJS the change emails the user; re-choosing the value already set does nothing · **holds** (with a note)
OJS, `u53cc3.mst`: Section editor masthead from *Does not appear* → *Appear*,
confirmed. The page showed **no** error, the value persisted across a reload, and mail
arrived at `u53cc3-mst-ojs@mail.test` subject **"Your journal masthead visibility has
been updated"**. Selecting the value the role already had produced **no dialog at all**
and no mail — worth noting because the screen gives the manager no way to "re-choose"
a set value: picking the current option in the select is a no-op before any
confirmation is offered.

### C23 — on OMP and OPS the change saves, the manager is shown an internal error, and the user is never told · **holds**
OMP (`u53cc3.mst.omp`) and OPS (`u53cc3.mst.ops`): after confirming, an **Error**
dialog appeared reading

> Email template USER_ROLE_MASTHEAD_UPDATE not found. The migration script
> I11800_AddUserRoleMastheadUpdateEmail needs to be run.

with a single **OK**. The new value survived a reload in both apps (checked in both
directions on OPS), and no mail reached the user's address in either. ⚠ A5 confirmed.

### C24 — a Reviewer role's masthead is not a setting: the row shows the fixed words "Appear on the masthead" {OJS OMP} · **holds**
OJS (`u53cc3.mst`, Section editor + Reviewer): the Reviewer row's masthead cell is the
plain text **"Appear on the masthead"** — the page carries exactly one `select`, on the
Section editor row. OMP (`u53cc3.rev.omp`, External Reviewer + Author): the External
Reviewer row likewise reads **"Appear on the masthead"** as text while the Author row
carries the control.

---

## Rules & state — Rule 9, the users spreadsheet

### C25 — the address answers for the roles the lists admit; an Author or Reader gets a bare error and no file · **holds**
Typed `…/u53cc3a/api/v1/users/report` into the address bar in a signed-in browser.
As Journal Manager `u53cc3.mgr` and as Site Administrator `u53cc3.adm` the browser
downloaded **`user-report-2026-07-29.csv`**, one Yes/No column per role
(`"Journal manager","Journal editor",…,Reader,"Subscription Manager"`). As Reader
`u53cc3.srv` and as Author `u53cc3.dup` the same address rendered a bare
`{"error":"user.authorization.roleBasedAccessDenied","errorMessage":"The current role
does not have access to this operation."}` and no file.

### C26 — "a ready-made spreadsheet of **every user**" · **wrong** (narrowly)
The file lists only the accounts that currently hold at least one role in the journal.
At the moment of the download, Journal A's list showed **"Current Users (7)"** — and
the CSV held five rows: the two accounts whose roles had been ended (`u53cc3.rem`,
`u53cc3.rol`) are on the screen's list, with empty Roles cells, but are **absent from
the file**. What the screen calls a current user and what the download calls one are
not the same set.

### C27 — "No screen in any app mentions the address" · **holds for what was checked**
Nothing visited offers or links the download: not the Users & Roles screen, not the
Hosted Journals users tab, not `Settings → Tools`, not `Statistics → Reports` (which
lists Articles / Subscriptions / Review / COUNTER reports only). One thing complicates
the picture rather than disproving it: `Statistics → Users` carries an **"Export"**
button next to "Registered users", and clicking it produces **nothing at all** — no
download, no request, no message (its button element carries `href="false"`). So the
users download still has no working door, but a screen does show a control a reader
would expect to be one.

---

## Side effects

### C28 — welcome email sent when "Notify User" is ticked · **holds**
`Administration → Hosted Journals → Journal A → Settings wizard → Users → Add User`,
as `u53cc3.adm`. Filled Given Name, Username, Email, Password/Repeat, ticked
**"Notify User — Send user a welcome email."**, submitted (**OK**). Mail arrived at
the new address, subject **"Journal Registration"**.

### C29 — always sent when "Generate Password" is ticked · **holds**
Ticking **"Generate Password — Generate random password for this user."** immediately
ticks **Notify User** and **disables** it — an attempt to untick it on screen times out
against a disabled control. The account created that way (Journal B) received the
welcome mail although Notify was never ticked by hand.

### C30 — it leaves as soon as the details are saved, before any roles are chosen · **holds**
The mail was already in Mailpit while the screen stood on **"Step #2: Add User Roles
to New u53cc3new1"**, roles untouched and never saved.

### C31 — it carries the password in clear text, typed or generated · **holds**
Typed case: the body reads `Username: u53cc3new2` / `Password: Zq7!tuvwx9` — the exact
string typed into the form. Generated case: `Username: u53cc3new3` / `Password: RrxE78`.

### C32 — never sent when editing · **holds** (with positive control)
`Hosted Journals → Users → Edit User` on `u53cc3.mst`, changed the family name, saved:
the grid showed the new name and **no** mail arrived (the address' only message
remained the earlier masthead notice). The edit form carries neither a Notify User nor
a Generate Password control. Control from the same screen: the row's "Email" action,
subject *"u53cc3 control after legacy edit"*, delivered.

### C33 — replies go to the journal's contact address; with none set, no reply-to · **holds**
Journal A (contact address set): the welcome mail's Reply-To is
**"C3A Contact <u53cc3-contact-ojs@mail.test>"**. Journal B (no contact address in its
settings): the same mail carried **no Reply-To at all**. Both mails were sent from the
acting administrator's own address.

### C34 — role-ended email: sent every time, announcing "You have been removed from a role"; removing all roles at once sends nothing · **holds**
Both halves watched back to back on the same account and address, in the same session:
ending one role of two produced the mail titled exactly **"You have been removed from
a role"**; choosing "Remove User" on the same person immediately afterwards — which
ended their remaining role — added **nothing** to the mailbox; the control message sent
from that same screen a minute later arrived normally. ⚠ A1 reproduces exactly as
written.

### C35 — masthead-changed email {OJS}; on OMP and OPS the change saves, the manager sees an error, no email leaves · **holds**
See C22 and C23. On OJS the mail's subject is **"Your journal masthead visibility has
been updated"**.

### C36 — disabling and merging both end the affected account's open sessions at once · **holds**
Disable: a session signed in as `u53cc3.dis` and left open was thrown to
`/u53cc3e/login?source=…` on its next page load, immediately after a Journal Manager
disabled the account. Merge: the duplicate's open session behaved identically (C16).

### C37 — "No email otherwise — disabling, enabling, removing and merging tell the affected user nothing by mail" · **holds** (with positive controls)
Disable and enable: the target's mailbox stayed empty across both operations; the
control message *"u53cc3 control after disable-enable"*, sent from the same row menu
straight afterwards, arrived. Removing: C5. Merging: C17. Four operations, three
controls, no notification anywhere.

---

## Settings that modify behavior

### C38 — "Permit changes to Settings" decides whether a manager reaches the Users & Roles screen at all · **holds**
Checked on OJS Journal F with `u53cc3.jed`, a user holding the **Journal editor** role
(a manager-level group). With the option ticked, the user's menu lists "Users & Roles"
and the address opens the screen. Unticked — via
`Users & Roles → Roles → (Journal editor row expander) → Edit`, checkbox labelled
exactly **" Permit changes to Settings"** under "Role Options" — the same user's menu
no longer lists "Users & Roles" and typing the address lands on **"The current role
does not have access to this operation."** Re-ticking restored both. Worth recording
for the reader: the default **Journal manager** role row offers **no** actions at all in
the Roles grid (no expander, no Edit/Remove), so the option cannot be unticked on that
role — the claim is only exercisable on the other manager-level roles.

### C39 — more than one language adds the "Working Languages" field to the account form · **holds**
The site has two languages (Administration → Site Settings → Languages lists
English `en` and French `fr_CA`). The Add User form shows **Working Languages** with
two checkboxes, **English** and **French**.

### C40 — "…and renders name fields in each language" · **wrong**
On the same form, Given Name, Family Name, Preferred Public Name and Affiliation are
rendered **once**, in the site's primary language only. No second-language variant is
present, visible or hidden — the form's inputs are `givenName[en]`, `familyName[en]`,
`preferredPublicName[en]`, `affiliation[en]` and nothing else, and no locale switcher
sits beside them. Checked on a scratch journal **and** on `publicknowledge` (whose own
settings forms elsewhere do carry a French/English switcher), so this is not an
artefact of the scratch context's languages.

### C41 — the password confirmation window: the prompt, its persistence, its wording, its lapse · **holds** (every clause)
Run on OPS with `password_timeout = 1` set in the application's configuration file for
the duration of the check and restored immediately afterwards, as the seeded site
administrator `u53cc3.adm.ops`:
- the **first** site-administration address visited after signing in
  (`/index/admin/contexts`) redirected to `/index/admin/confirmAccess?source=…`
  showing **"Confirm Access — Signed in as Adm U53cc3. Please enter your password to
  continue."**, a Password field, Cancel and Submit;
- a **second** administration address (`/index/admin/settings`) landed on the same
  prompt, unanswered;
- a **wrong** password was refused with **"Invalid username/email or password. Please
  try again."**;
- the **correct** password opened Administration → Hosted Servers, and a further
  administration address then opened with no prompt;
- after the window lapsed (waited ~75 s on a 1-minute window), the next administration
  address landed on "Confirm Access" again.

### C42 — a stock install leaves the setting unset and the prompt never appears · **holds**
With the line commented out (its shipped state — `;password_timeout = 0`), every
administration address visited in this run opened directly, on all three fleets. The
setting was restored to that state and verified after the check.

### C43 — "Only Site Administration is guarded this way: the journal-level Users & Roles screen never asks" · **holds**
With the window configured and unanswered, the journal-level Users & Roles address on
OPS did **not** show the Confirm Access prompt. (It refused that particular
administrator for an unrelated reason — see *Adjacent observations* B — but the guard
under test never fired at journal level.)

### C44 — "No other configuration-file setting alters this feature's behavior on screen" · **undetermined**
Not checkable through the screens: it is a claim about everything that is *not* there.
Nothing observed contradicted it.

---

## Cross-feature interactions

### C45 — *User invitations* owns the area above the users table, and the edit page the row "Edit" opens drives Rule 8 · **holds**
The Users tab shows, above "Current Users": the **Invitations (0)** table and the
**Invite to a role** button. Clicking a row's **Edit** navigates to
`/{journal}/management/settings/user/{id}` — the page headed "Users & Roles / Invite
user to take a role", whose roles table carries the **Remove Role** buttons and the
**Journal Masthead** selects this spec's Rule 8 describes. The hand-off is real in
both directions.

### C46 — *Roles configuration* shares the Users & Roles screen (its "Roles" tab) · **holds**
The screen's tabs read **Users · Roles · Site Access Options · ORCID** in all three
apps; the Roles tab lists the journal's roles with permission levels and per-role
Edit/Remove.

### C47 — *Notify users* — "the 'Notify' tab beside 'Users'" · **wrong**
There is no **Notify** tab on the Users & Roles screen in OJS, OMP or OPS as installed
— checked on scratch contexts in all three apps and on `publicknowledge`; the tabs are
Users · Roles · Site Access Options · ORCID. The tab exists only after a **site
administrator** allows that journal to send bulk email
(`Administration → Site Settings → Bulk Emails` — "Select the hosted journals that
should be allowed to send bulk emails."). Ticking Journal A there made the tab appear
for its manager immediately: **Users · Roles · Notify · Site Access Options · ORCID** —
i.e. after "Roles", not beside "Users". Unticking removed it again (the setting was
restored). Two things are wrong as written: the tab is absent by default, and its
place is misdescribed.

### C48 — *Emails management* — where the welcome, role-ended and masthead templates are edited; OPS lists two of them nowhere · **holds**
`Settings → Workflow → Emails → "Add and edit templates"`
(`/management/settings/manageEmails`), as a manager in each app:
- **OJS** lists **"User Role Ended Notification"** ("The email notification sent to a
  user when they are removed from a role.") and **"User Role Masthead Visibility Update
  Notification"** ("…sent to a user when their masthead visibility is updated for a
  role."), plus the welcome template ("This email is sent to a newly created user when
  the editorial manager has created the user through the user settings…").
- **OMP** lists all three, in the same words.
- **OPS** lists **neither** the role-ended nor the masthead notice; the welcome
  template is there. ⚠ OPS1 confirmed on screen.

### C49 — *Login & sessions* — "Login As" on both lists · **holds**
Users & Roles row menu on a fully-administered row:
`["Edit","Email","Login As","Remove User","Disable User","Merge user"]`. Hosted
Journals revealed control row: `Email · Edit User · Disable User · Remove · Login As ·
Merge User`. Present on both, as the spec says.

### C50 — *Statistics — editorial* cites the users spreadsheet from its side · **undetermined**
Not a claim about this feature's screens, and the spec it points at does not exist yet,
so there is nothing on screen to agree or disagree with. What the neighbouring screens
do show is recorded at C27: `Statistics → Reports` does not offer the users download,
and `Statistics → Users` offers an **Export** button that does nothing.

### C51 — *Reviewer assignment* — its reviewer-picking screens read the same user records · **undetermined**
Not checked. Reaching a reviewer-picking screen needs a submission in the review stage
with an assigned editor, which is outside this chunk's scratch state; the claim is
about another feature's screens and makes no assertion this chunk could falsify
cheaply.

---

## Adjacent observations (outside this chunk's claims — evidence recorded here so it is not lost)

Neither of these is counted in this chunk's tally; both fall in the Actors table /
Rules 1–5 region owned by chunks 1 and 2.

### A — the Hosted Journals list **does** carry a per-row "Disable User", and it works
The spec says twice that it does not: Rule 1b ("it carries no per-row Disable:
disabling and enabling accounts is the Users & Roles list's alone") and the Actors
table ("offered on the Users & Roles list only — the Hosted Journals list carries no
per-row Disable for anyone"). Footnote **fn-h** records a top-up probe that "found no
per-row Disable anywhere on the Hosted Journals surface, for any row".

On this install it is there. As `u53cc3.adm` (seeded site administrator) at
`Administration → Hosted Journals → Journal A → Settings wizard → Users`, expanding
any user row (the `a.show_extras` link whose screen-reader name is "Settings") reveals
a control row that reads **Email · Edit User · Disable User · [Remove] · Login As ·
Merge User** — on every row watched, with or without an active role. Clicking
**Disable User** opens a dialog titled **"Disable User"** carrying a "Reason for
disabling user" box and the extra sentence *"Please note that once a user is disabled,
you won't be able to add them to any roles until the user is enabled again."*, with
Cancel / OK.

It was carried through on a throwaway (`u53cc3.rem`): after OK, the Users & Roles list
showed that row with the disabled mark and its menu flipped to **"Enable User"**, and
the Vue enable dialog opened with the reason typed on the legacy screen already in the
box. The account was re-enabled from the Vue list immediately afterwards.

### B — a site administrator with no role in the context is refused the journal-level Users & Roles screen on OMP and OPS
The Actors table's first row says a Site Administrator reaches the screen "from the
journal's menu, in every app, with no error along the way — an administrator holding no
role in any journal included", and footnote **fn-a** records that case as watched clean
— on OJS only.

Signed in as the seeded, context-less administrators and typing each app's journal-level
address:
- OJS `u53cc3.adm` → `/u53cc3a/management/settings/access` opens the screen normally;
- OMP `u53cc3.adm.omp` → `/u53cc3omp/management/settings/access` redirects to
  `/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied` showing
  **"The current role does not have access to this operation."**;
- OPS `u53cc3.adm.ops` → same denial.

Same fixture, same steps, three apps, two of them refuse.

---

## Housekeeping

- **Accounts destroyed (merged away, permanently):** `u53cc3.dup` → survivor
  `u53cc3.srv`; `u53cc3.dup2` → survivor `u53cc3.srv2`. Both were created by this run.
- **No seeded account was disabled, removed or merged.** `publicknowledge`, its
  roster and the install's `admin` were used read-only (viewing tabs and forms) and
  never acted on. Every disable, enable, role-end, removal, merge and edit landed on a
  `u53cc3`-prefixed throwaway in a `u53cc3` scratch context.
- **Settings touched and restored:** site Bulk Emails (Journal A ticked, then
  unticked) for C47; the Journal editor role's "Permit changes to Settings" on Journal
  F (unticked, then re-ticked) for C38; OPS's `password_timeout` (set to 1, then
  restored to its shipped commented state) for C41–C43. All three were verified back
  at their original values at the end of the run. Mailpit was never cleared.
- **Working files:** `scratchpad/u53cc3/` (driver scripts, screenshots, raw output).
