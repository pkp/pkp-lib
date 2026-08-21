# U53 Users management — claim-check merge (change list)

Merged from `claimcheck-1.md` (Actors & permissions + Reference — 34 claims),
`claimcheck-2.md` (Fields & validation + Rules 1–5 — 72 claims) and
`claimcheck-3.md` (Rules 6–9, Side effects, Settings, Cross-feature — 51 claims).
157 claims checked, 128 hold and carry no block. Below: 16 wrong claims (merged to
13 blocks), 9 undetermined, 1 whose route is closed to the roles named, chunk 3's
two adjacent observations (folded into D2 and D4), and 5 observed behaviours that
are not spec sentences at all.

`Proposed:` is a suggestion only. 30 blocks.

---

### D1 — A journal's Hosted Journals user table lists a Site Administrator only where that administrator holds a role in the journal
`Affects:` Rule 1 · register [A8](../../specs/users-management.md#a8) · Actors (the same statement inside A8)
`Status:` corrects
`Apps:` OJS, OMP, OPS
`Proposed:` plain claim — a site administrator with no role in a journal appears in neither of that journal's user lists; the Hosted Journals filter's "Include users with no roles in this journal." box is the only way to surface them. The install's own administrator appears in every journal because journal creation enrols it as a manager. What survives of A8 is the differing description of an *enrolled* administrator: a blank Roles cell on the Hosted Journals list, "Journal manager" with a blank start date on Users & Roles.
`Evidence:` claimcheck-1.md CC1-01, CC1-02 (role-less administrator fixture, all three apps) · claimcheck-2.md R1-d (second administrator enrolled in one journal only, OJS)

### D2 — A Site Administrator holding no role in a journal cannot reach that journal's Users & Roles screen the way the Actors table describes
`Affects:` Actors row "See and search the Users & Roles list", second bullet · footnote fn-a
`Status:` corrects
`Apps:` OJS — no menu path anywhere in the journal, so the screen opens only by typing its address; OMP and OPS — the `management/settings/access` address refuses that administrator with "The current role does not have access to this operation.", and only the older `management/access` address admits them
`Proposed:` plain claim — the bullet's "from the journal's menu, in every app, with no error along the way" is not what a role-less administrator meets; fn-a's clean fold-probe reading was taken on OJS only. Candidate register entry (per-app administrator access already has one in *User invitations*, [A4](../../specs/user-invitations.md#a4) — link rather than duplicate).
`Evidence:` claimcheck-1.md CC1-14 (all three apps, both halves) · claimcheck-1.md CC1-05 (the two addresses, per role and app) · claimcheck-3.md adjacent observation B (same fixture, three apps — routed here)

### D3 — For that same administrator the Users & Roles row offers "Edit" and the edit page then refuses them
`Affects:` Actors row "Edit a user", first bullet · Rule 3
`Status:` corrects
`Apps:` OMP, OPS (on OJS the edit page opens normally)
`Proposed:` plain claim — the row offers Edit to a site administrator holding no role in the journal, and choosing it lands on "The current role does not have access to this operation."
`Evidence:` claimcheck-1.md CC1-17

### D4 — The Hosted Journals list carries a per-row "Disable User" on every row, and disabling and enabling complete from it
`Affects:` Rule 1b · Rule 4 · Rule 5 · Actors row "Disable / Enable an account", second bullet · footnote fn-h
`Status:` corrects
`Apps:` OJS, OMP, OPS
`Proposed:` plain claim — the revealed control row reads Email · Edit User · Disable User · Remove · Login As · Merge User (Login As and Merge User drop off rows the viewer cannot fully administer, Remove off rows with no active role). "Disable User" opens a reason form with Cancel / OK; confirming flips the row's action to "Enable", and "Enable" restores it. Disabling is therefore not the Users & Roles list's alone, and what one list does the other shows: a person disabled from Hosted Journals carries the red disabled mark on Users & Roles, and the Users & Roles enable dialog opens pre-filled with the reason typed on the other surface. fn-h's record of finding no per-row Disable anywhere does not stand.
`Evidence:` claimcheck-1.md CC1-21 (disable → enable driven end to end on all three apps) · claimcheck-2.md R1b-c (every row of three journals, OJS) · claimcheck-3.md adjacent observation A (carried through on a throwaway, cross-list interplay watched, OJS — routed here)

### D5 — On the Hosted Journals list the viewer's own row offers "Disable User" too
`Affects:` Rule 1c · Actors row "Disable / Enable an account", second bullet ("never on the viewer's own row")
`Status:` corrects
`Apps:` OJS, OMP, OPS
`Proposed:` plain claim — the own row withholds Login As and Merge User and offers Email, Edit User, **Disable User** and Remove; the dialog opens on one's own account with nothing standing in the way. Rule 1c's enumeration of what is left on the own row omits it. No one confirmed it on their own account, so what the operation does to the person performing it is not recorded (see D23).
`Evidence:` claimcheck-1.md CC1-22 (own row of the install administrator, all three apps, dialog not opened) · claimcheck-2.md R1c-b (own rows of two throwaway administrators, dialog opened and closed unconfirmed, OJS)

### D6 — A disabled account's Hosted Journals row looks exactly like an enabled one, and its action there reads "Enable"
`Affects:` Rule 4
`Status:` corrects
`Apps:` OJS, OMP, OPS (the unmarked row watched on OJS)
`Proposed:` plain claim — the red disabled mark and the "Enable User" label belong to the Users & Roles list; on the Hosted Journals list nothing on the row says the person cannot sign in, and the pair of labels is asymmetric ("Disable User" / "Enable").
`Evidence:` claimcheck-2.md R4-c · claimcheck-1.md CC1-21 (the label flip, all three apps)

### D7 — The Hosted Journals disable/enable dialog names nobody and lists no roles
`Affects:` Rule 5 · Fields (the disable/enable dialog)
`Status:` corrects
`Apps:` OJS, OMP, OPS
`Proposed:` plain claim — the titled, role-listing dialog ("Disable {name}", "Current Roles : …") is the Users & Roles one; the Hosted Journals dialog is headed "Disable User" (and "Enable"), and its body is the reason box, a note, Cancel and OK.
`Evidence:` claimcheck-2.md R5-a · claimcheck-1.md CC1-21 · claimcheck-3.md adjacent observation A

### D8 — Both disable and enable dialogs carry a note about roles
`Affects:` Fields (the disable/enable dialog)
`Status:` new
`Apps:` OJS, OMP, OPS
`Proposed:` footnote — disabling: "Please note that once a user is disabled, you won't be able to add them to any roles until the user is enabled again."; enabling: "Once the user is enabled, they will regain access to the site, and you'll be able to invite them to roles as needed." Neither note mentions that the typed reason is shown to the person.
`Evidence:` claimcheck-2.md R5-g · claimcheck-1.md CC1-21

### D9 — The account form offers one name box per person, in the site's primary language, even on a site with two languages
`Affects:` Fields (Given Name) · Settings ("The site's languages")
`Status:` corrects
`Apps:` OJS (watched); the form is the shared one
`Proposed:` plain claim — Given Name, Family Name, Preferred Public Name and Affiliation each render once, with no locale switcher beside them; a manager cannot enter a person's name in the site's second language from this form. Two claims say otherwise: the Given Name row's "Offered in each of the site's languages" and the Settings bullet's "renders name fields in each language". The "Working Languages" half of that Settings bullet holds.
`Evidence:` claimcheck-2.md FV-3 (single-locale and two-locale journals) · claimcheck-3.md C40 (also on `publicknowledge`, whose other settings forms do carry a locale switcher)

### D10 — The password minimum length is stated only when editing an account
`Affects:` Fields (Password / Repeat password)
`Status:` corrects
`Apps:` OJS (watched)
`Proposed:` plain claim — the edit form reads "Leave the password fields blank to keep the current password. The password must be at least 6 characters."; the create form states no minimum anywhere, so someone creating an account is told nothing about it.
`Evidence:` claimcheck-2.md FV-12

### D11 — The account form refuses a bad username, a short password, a mismatched repeat or an address already in use without saying anything
`Affects:` Fields (the account form) · new register entry
`Status:` new
`Apps:` OJS (watched)
`Proposed:` 🐞 — six faults, six silent refusals: the dialog stays open with the typed values in place and no field error, no banner, no notification of any kind; the account is simply not created. Only an empty required field produces a message ("This field is required."). The Fields table states the username and password rules as if the form enforced them visibly.
`Evidence:` claimcheck-2.md FV-19 (and FV-8, FV-12)

### D12 — Editorial Notes follows a manager or editor role in the journal, not Site Administrator status
`Affects:` Fields (Editorial Notes)
`Status:` corrects
`Apps:` OJS (the withholding watched on OJS; the role-holding viewer's positive case also on OMP)
`Proposed:` plain claim — a site administrator whose only role in the journal is Author opens the same form and the block is simply not there, with no indication anything is missing; an administrator who is also a manager of that journal sees it.
`Evidence:` claimcheck-2.md FV-22

### D13 — On the account form a qualifying viewer sees Editorial Notes on everyone except themselves
`Affects:` Fields (Editorial Notes) · footnote fn-c
`Status:` corrects
`Apps:` OJS (watched)
`Proposed:` plain claim — a viewer holding site administrator, Journal manager and Reviewer in the same journal sees no Editorial Notes block on their own record, while a peer reviewer's record in the same session shows it. The spec's "one's own record is not excepted" counter-example was taken on the reviewer-picking screens, which are another feature's surface; on this form the exception is real.
`Evidence:` claimcheck-2.md FV-23

### D14 — No Journal Manager or Section Editor can open the account form at all
`Affects:` Fields (Editorial Notes — the list of viewers)
`Status:` corrects
`Apps:` OJS, OMP, OPS
`Proposed:` plain claim — the account form lives behind Administration → Hosted Journals, which admits site administrators only, and the Users & Roles row's "Edit" opens a different page entirely; so the "Journal Manager, Section Editor or Site Administrator" viewers the rule names cannot all meet this form. The route is closed to those roles by design, not by anything about this install.
`Evidence:` claimcheck-2.md U-1 · claimcheck-1.md CC1-15 (no other role reaches Site Administration, all three apps)

### D15 — Whether "Working Languages" disappears on a one-language site is undetermined
`Affects:` Fields (Working Languages)
`Status:` undetermined
`Apps:` OJS
`Proposed:` ❓ — the field is present on a two-language site, as written; the "only when" half was not observed. The route was closed for install reasons, not disproved: turning a language off is a site-wide change on a shared install, outside the scratch material. Would settle it: the same Add User and Edit User forms on an install where only one language is enabled.
`Evidence:` claimcheck-2.md FV-20 · claimcheck-3.md C39 (the positive half)

### D16 — Whether the Name cell carries an ORCID mark is undetermined
`Affects:` Rule 1a
`Status:` undetermined
`Apps:` OJS
`Proposed:` ❓ — not observed either way. The route was closed for install reasons: no account on this install carries an ORCID, and no screen in this feature puts one on an account. Would settle it: the Users & Roles row of an account that carries an ORCID.
`Evidence:` claimcheck-2.md R1a-e

### D17 — The masthead checkbox list arrives fully ticked, whether or not the person holds the role
`Affects:` Fields (Roles / Masthead checkboxes)
`Status:` new
`Apps:` OJS, OMP, OPS
`Proposed:` footnote — the second list is not a picture of where the person currently appears: every masthead box renders ticked on opening the edit form, including for roles the person does not hold, and only the reviewer rows are locked.
`Evidence:` claimcheck-2.md FV-26, FV-27

### D18 — The Users & Roles search also matches usernames and email addresses, and a role word can return a row whose Roles cell shows no such role
`Affects:` Rule 2
`Status:` corrects
`Apps:` OJS (watched)
`Proposed:` plain claim — typing a username or an email address finds the person, which the rule does not mention; and searching "Author" returned the administrator's row, whose Roles cell reads "Journal manager" and which the same journal's own role filter does not return under Author. A manager searching by role gets a result they cannot account for from the list in front of them. Candidate register entry.
`Evidence:` claimcheck-2.md R2-c (reproduced in a second journal)

### D19 — The users spreadsheet holds only the people who currently hold a role, not every user the list shows
`Affects:` Rule 9 · register [A6](../../specs/users-management.md#a6)
`Status:` corrects
`Apps:` OJS (watched)
`Proposed:` plain claim — the file lists the accounts with at least one active role in the journal; the rows whose roles have been ended stay on the "Current Users" list, with empty Roles cells, and are absent from the file. What the screen calls a current user and what the download calls one are not the same set.
`Evidence:` claimcheck-3.md C26

### D20 — Statistics → Users offers an "Export" button that produces nothing at all
`Affects:` register [A6](../../specs/users-management.md#a6) · Cross-feature (*Statistics — editorial*, spec pending)
`Status:` new
`Apps:` OJS (watched)
`Proposed:` 🐞 — beside "Registered users" sits an "Export" control; clicking it produces no download, no message and no visible response. It is the one screen control a reader would take for the users download's missing door, and it is dead. The screen belongs to the neighbouring feature — a link from A6 rather than a body claim here.
`Evidence:` claimcheck-3.md C27

### D21 — Of everything a merge is said to re-credit to the survivor, only the submissions half was seen on screen
`Affects:` Rule 7 (first bullet)
`Status:` undetermined
`Apps:` OJS
`Proposed:` ❓ — after a merge the survivor's own "My Submissions" lists the duplicate's submission, so submissions activity is confirmed. Reviews, decisions, files, notes, notifications and email history were not observed: no screen was driven that shows them for an account that no longer exists. Not disproved. Would settle it: a screen on which the survivor is shown holding a review or a file the duplicate produced.
`Evidence:` claimcheck-3.md C11

### D22 — Whether subscriptions and completed payments move to the survivor is undetermined
`Affects:` Rule 7 (third bullet)
`Status:` undetermined
`Apps:` OJS, OMP
`Proposed:` ❓ — nothing observed contradicts the clause and nothing supports it. The route was closed for install reasons, not disproved: the scratch material could not be seeded with a subscription, and reaching one through the screens needs subscription publishing mode, a subscription type and a subscription — three settings forms outside this feature. Would settle it: the survivor's subscription record after merging away an account that holds one.
`Evidence:` claimcheck-3.md C13

### D23 — Whether a person may act on their own account is undetermined; nothing on the everyday list shows it
`Affects:` Actors & permissions, administration-levels paragraph ("Everyone always fully administers their own account")
`Status:` undetermined
`Apps:` OJS, OMP, OPS
`Proposed:` ❓ — on the Users & Roles list the viewer's own row offers only Edit and Email, so the sentence has no observable consequence there. The one own-row control that could show it is the Hosted Journals list's own-row "Disable User" (D5), which nobody confirmed. Would settle it: confirming that dialog on a throwaway administrator's own account and reading what the screen then does.
`Evidence:` claimcheck-1.md CC1-12 · claimcheck-2.md R1c-b (the dialog opened on one's own row and closed)

### D24 — The Section Editor half of the single-role sentence cannot be seen from any screen
`Affects:` Actors row "End a single role / change masthead display"
`Status:` undetermined
`Apps:` OJS, OMP, OPS
`Proposed:` ❓ or drop from this spec's body — the sentence describes something accepted where no screen offers it, so no screen observation can agree or disagree with it; it is already carried by *[User invitations](../../specs/user-invitations.md#a8)*' register, which this spec links. Would settle it: nothing available on screen.
`Evidence:` claimcheck-1.md CC1-25 (second half)

### D25 — There is no "Notify" tab on the Users & Roles screen until a site administrator allows the journal to send bulk email, and it then sits after "Roles"
`Affects:` Cross-feature (*Notify users*)
`Status:` corrects
`Apps:` OJS, OMP, OPS
`Proposed:` plain claim — as installed the tabs read Users · Roles · Site Access Options · ORCID. Ticking the journal under Administration → Site Settings → Bulk Emails makes the tab appear for its manager at once, as Users · Roles · **Notify** · Site Access Options · ORCID; unticking removes it again. Two things in the bullet are wrong: the tab is absent by default, and it is not beside "Users".
`Evidence:` claimcheck-3.md C47 (three apps, scratch contexts and `publicknowledge`)

### D26 — "Permit changes to Settings" does decide whether a manager reaches the screen — but it cannot be taken away from the default Journal manager role
`Affects:` Actors row "See and search the Users & Roles list", first bullet · Settings ("Permit changes to Settings")
`Status:` new
`Apps:` OJS (watched)
`Proposed:` footnote — with the option ticked on a Journal editor role the person's menu lists "Users & Roles" and the screen opens; unticked, the menu entry is gone and the address answers "The current role does not have access to this operation."; re-ticking restores both. The default **Journal manager** row on the Roles tab offers no actions at all — no expander, no Edit, no Remove — so the option cannot be unticked on the role the Actors bullet names.
`Evidence:` claimcheck-3.md C38 (chunk 1 left the conditional unexercised at CC1-13; chunk 3 drove it)

### D27 — "No other configuration-file setting alters this feature's behavior on screen" cannot be settled from the screens
`Affects:` Settings (the password confirmation window bullet, last sentence)
`Status:` undetermined
`Apps:` OJS, OMP, OPS
`Proposed:` ❓ or drop the sentence — it is a claim about what is *not* there; nothing observed contradicted it, and no screen observation can establish it.
`Evidence:` claimcheck-3.md C44

### D28 — The *Statistics — editorial* cross-reference has nothing on screen to agree with
`Affects:` Cross-feature (*Statistics — editorial*)
`Status:` undetermined
`Apps:` OJS
`Proposed:` ❓ — the spec it points at does not exist yet, so the bullet asserts nothing a screen can confirm. What the neighbouring screens do show is in D19 and D20: Statistics → Reports does not offer the users download, and Statistics → Users offers a dead "Export". Would settle it: that spec existing.
`Evidence:` claimcheck-3.md C50

### D29 — The *Reviewer assignment* cross-reference was not exercised
`Affects:` Cross-feature (*Reviewer assignment*)
`Status:` undetermined
`Apps:` OJS
`Proposed:` ❓ — the bullet is about another feature's screens and the spec does not exist yet. The route was closed for state reasons, not disproved: a reviewer-picking screen needs a submission in the review stage with an editor assigned. Would settle it: opening that screen on such a submission and reading the user records it shows.
`Evidence:` claimcheck-3.md C51

### D30 — The role-ended notice reaches the user on all three apps, and the masthead notice on OJS only
`Affects:` Side effects (role-ended email, masthead-changed email) · register [OPS1](../../specs/users-management.md#ops1) · Reference — entry points & surfaces (the mail rows)
`Status:` confirms
`Apps:` role-ended: OJS, OMP, OPS · masthead-changed: OJS (on OMP and OPS the change saves, the manager meets an error and the person is never told)
`Proposed:` no change — recorded because chunk 1 could not exercise the mail rows (mail is not a screen) and chunk 3 drove both notices in all three apps, OPS included, which is the reading the body already carries.
`Evidence:` claimcheck-1.md CC1-34 (raised) · claimcheck-3.md C19, C22, C23, C35, C48 (settled)
