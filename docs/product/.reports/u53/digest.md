# U53 — Users management · digest (RUNBOOK step 3b)

Spec-affecting facts from probe reports A–D (26 items, three fleets, 2026-07-28/29),
in product voice. `Proposed:` is a suggestion; step 4 decides. Detail and locators
stay in `.reports/u53/probe-{a,b,c,d}.md`.

Where one report left a question open and another settled it, the block says so
(D27, D28). Two questions stay open because this install cannot create a second
site administrator (D15, D29, D30).

---

## Doors

### D1 — A Site Administrator reaches Settings → Users & Roles from the journal's own menu, and the screen opens with no error dialog.
Affects: Actors row 1
Status: corrects
Apps: OJS, OMP, OPS
Proposed: plain claim · drop the OMP/OPS "older address, under an error dialog" clause. The administrators observed also hold a manager role everywhere, so the pure site-administrator case is not isolated.
Evidence: probe-a.md item 1

### D2 — Nobody is asked to confirm their password on the way into Administration and a journal's settings pages.
Affects: Actors section (Hosted Journals bullet) · Settings section
Status: undetermined
Apps: OJS, OMP, OPS
Proposed: the re-confirmation claim stays cautious. Settling observation: the same path on an install where the site's password-timeout setting is on and the window has lapsed — which also bears on "no server-configuration setting alters this feature".
Evidence: probe-a.md item 2

### D3 — Typed after a journal's address, the settings-wizard page is refused: the reader-facing journal site opens showing only "Access denied." At the site's own address it answers.
Affects: Actors section
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim · the refusal's shape need no longer be marked unwatched.
Evidence: probe-a.md item 3

### D4 — A Journal Manager typing the users-report address gets a spreadsheet of every user with a Yes/No column per role; an author or reader gets a bare error and no file. No screen mentions the address.
Affects: Rule 9 · register A7
Status: confirms
Apps: OJS, OMP, OPS
Proposed: keep A7; now watched rather than code-read.
Evidence: probe-a.md item 4

## The Users & Roles list

### D5 — The list is headed "Current Users" with a count that follows the filter, shows Name / Email / Roles / Start Date / Affiliation plus a row menu, hides ended roles, and pages at 25.
Affects: Rule 1a · scenario 1
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim · only the ORCID mark stays unwatched (the red disabled mark is watched in D17).
Evidence: probe-a.md item 5

### D6 — The search field does nothing as you type: it runs only when Enter is pressed, and no button is offered instead. What it matches — names, roles, affiliations — and the return to page one are as drafted.
Affects: Rule 2 · scenario 1
Status: corrects
Apps: OJS, OMP, OPS
Proposed: plain claim · replace "matches as you type"; scenario 1 needs the same fix.
Evidence: probe-a.md item 6

### D7 — Two shipped strings on this list are wrong as drafted: the search hint offers "Journal editor" as its example on presses and preprint servers (and drops the period after "e.g"), and the row-options button gives assistive technology a raw key, `##userAccess.management.options##`, in place of a name.
Affects: register A5 · register A4
Status: confirms
Apps: OJS, OMP, OPS
Proposed: keep both as 🐞; the menu key ships wrapped in `##…##`, and the same table's "More Actions" header is properly translated.
Evidence: probe-a.md items 7, 8

### D8 — The viewer's own row offers exactly "Edit" and "Email"; every other row offers Edit, Email, Login As, Remove User, Disable User, Merge user.
Affects: Rule 1c · Actors rows "Edit a user" / "Email a user"
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim, as drafted.
Evidence: probe-a.md item 9 · probe-b.md item 14

## The Hosted Journals list

### D9 — The filter is hidden until the header's "Search" link is clicked, and collapses again after each search; revealed, it holds the search box, "All Roles" dropdown, no-roles checkbox and Search button, and applies on pressing Search.
Affects: Rule 1b · Rule 2 (second half)
Status: corrects
Apps: OJS, OMP, OPS
Proposed: plain claim · say the filter sits behind a "Search" link; this list's remove action is labelled "Remove", not "Remove User".
Evidence: probe-b.md item 10

### D10 — A Site Administrator's own row here offers Email, Edit User, Disable User and Remove; only Login As and Merge User are withheld.
Affects: register A2 · Rule 1c
Status: confirms
Apps: OJS, OMP, OPS
Proposed: keep A2's on-screen half; its execution half stays open (D30).
Evidence: probe-b.md item 11

### D11 — The two lists describe the same administrator differently: Hosted Journals shows an empty Roles cell, Users & Roles shows "Journal manager" with a blank start date. The administrator is listed in every journal's Hosted Journals table whether enrolled there or not, and the manager role filter returns them.
Affects: Rule 1 ("they read the same accounts") · new
Status: new
Apps: OJS, OMP, OPS
Proposed: register entry (❓) · Rule 1's unmarked sentence overstates the agreement.
Evidence: probe-b.md item 10 · probe-a.md item 2

## The account form

### D12 — Country is not required: it carries no asterisk and saving without it creates the account without complaint. "User must change password on next log in." is ticked by default when creating.
Affects: Fields table (account form)
Status: corrects
Apps: OJS; create path also exercised on OMP and OPS
Proposed: plain claim · Country → No, plus the Change Password default. A rejected username is refused in different words from the rule above the field — footnote at most.
Evidence: probe-b.md item 12

### D13 — Adding a user works end to end: details → "Step #2: Add User Roles to {name}" → the account in that journal's Users & Roles list, and a welcome email with the journal's contact as reply-to. The password travels in the mail in clear text whether generated or typed; with no contact address set, the mail carries no reply-to.
Affects: scenario 6 · Side effects ("Welcome email")
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim · widen the password clause beyond Generate Password; the mail leaves as soon as the details are saved, before roles are chosen.
Evidence: probe-b.md item 12

### D14 — Editing shows the username but offers no way to change it, drops Notify User and Generate Password, keeps the roles and masthead lists, keeps the password when the fields are left blank, and tells the user nothing by mail. Every Reviewer row in "Appear on Masthead" is ticked and un-tickable — on OMP both reviewer rows — whether or not the account holds the role; the create screen locks nothing.
Affects: Fields table (edit shape, Roles/Masthead row) · Side effects ("Never sent when editing")
Status: confirms
Apps: OJS, OMP; OPS installs no reviewer role, so the lock cannot appear there
Proposed: plain claim · note the lock does not depend on holding the role and is absent when creating.
Evidence: probe-b.md item 13

### D15 — "Editorial Notes" appears only once the edited account holds a Reviewer role in this journal; whether it is also withheld on one's own record is undetermined.
Affects: Fields table (Editorial Notes row)
Status: undetermined
Apps: OJS, OMP
Proposed: the "never on one's own record" clause stays cautious. Settling observation: someone who both reaches this form and holds a Reviewer role opening their own record — which needs a throwaway site administrator, and probe D established none can be created here, so probe B's open point stays open.
Evidence: probe-b.md item 13 · probe-d.md item 25

## Email, disable, remove, merge

### D16 — "Email" opens a form with a required Subject, a fixed "To" naming the recipient that cannot be edited, and a rich-text Body sent with "Send Email"; the message arrives as typed, from the sender's own address, own row included.
Affects: scenario 2 · Email fields table · Actors row "Email a user"
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim, as drafted.
Evidence: probe-b.md item 14

### D17 — Disabling behaves as drafted, and the manager's typed reason is quoted back to the person at sign-in: "Your account has been disabled for the following reason: …". The row gains a red mark, the menu reads "Enable User", any window they had open is thrown back to sign-in, and enabling re-opens the dialog with that reason already filled in.
Affects: Rules 4–5 · scenario 3 · Fields table (reason box)
Status: confirms
Apps: OJS, OMP
Proposed: plain claim · add that the reason reaches the disabled person — the form does not say so.
Evidence: probe-d.md item 17

### D18 — The two lists' disable dialogs differ: the Users & Roles one names the user and lists "Current Roles", including roles that have already ended and that its own Roles column no longer shows; the Hosted Journals one is headed only "Disable User" and never lists roles for anybody.
Affects: Rule 5 · Rule 1c
Status: corrects
Apps: OJS, OMP
Proposed: plain claim · Rule 5 describes the Users & Roles dialog only; the ended-roles listing is a footnote or small ❓.
Evidence: probe-d.md item 18

### D19 — "Remove User" ends every role here after one confirmation, mails nobody, and leaves the account signing in. The row stays, with empty Roles and Start Date cells, and the "Current Users" count does not change; re-granting a role starts it fresh, dated that day.
Affects: Rule 6 · scenario 4 · register A1 (silent half)
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim · Rule 6 can now say what the list shows afterwards; the silence was shown against a positive control in each app.
Evidence: probe-d.md item 19

### D20 — Neither list offers "Remove User" on a row with no roles left, so the answer "This user does not have any roles." is unreachable.
Affects: Rule 6 (last clause)
Status: corrects
Apps: OJS, OMP
Proposed: plain claim · drop that sentence or restate it as an answer no screen can produce.
Evidence: probe-d.md items 18, 19

### D21 — Ending a single role always emails the person ("You have been removed from a role"), on OPS too; removing all their roles afterwards adds nothing to their mailbox.
Affects: register A1 · Rule 8a · scenario 8 · register OPS1 (sending half)
Status: confirms
Apps: OJS, OPS
Proposed: keep A1 — the asymmetry is now demonstrated, not predicted.
Evidence: probe-d.md item 20

### D22 — Changing a masthead setting on OMP and OPS saves and then shows the manager an error naming an internal migration script, and the user is never told. On OJS it saves with no message and mails the user; re-choosing the value already set does nothing and mails nobody. The confirmation beforehand says "journal masthead" on presses and preprint servers too, and promises "The user will be notified of this change" — untrue on those two apps.
Affects: register A6 · Rule 8b · Side effects ("Masthead-changed email") · new
Status: confirms
Apps: OMP, OPS (saves, errors, silent); OJS (saves, silent screen, mails); wording in all three
Proposed: keep A6, upgraded from predicted to watched — developer text shown to a manager, change sticks regardless. The "journal masthead" wording and the broken promise are worth a second 🐞 (minor), same class as A5.
Evidence: probe-d.md item 21

### D23 — A Reviewer role's masthead setting is not a control: the cell shows the plain words "Appear on the masthead" where other roles show a dropdown.
Affects: Rule 8b
Status: corrects
Apps: OJS
Proposed: plain claim · "the server refuses it" → "the setting is not offered".
Evidence: probe-d.md item 21

### D24 — A manager on OMP finds both the role-ended and masthead-change notices on the Emails screen; on OPS neither is listed; on OJS both are.
Affects: register OPS1
Status: corrects
Apps: OMP corrected; OJS, OPS confirmed
Proposed: keep OPS1 for OPS; drop its claim that OMP lacks the masthead row.
Evidence: probe-c.md item 26

### D25 — A merge does what its confirmation says: it names both usernames and warns the old one "will not exist afterwards. This action is not reversible."; on confirming, the duplicate's row is gone at once and the count drops, their open window is thrown back to sign-in, their username is thereafter refused as unknown, and the survivor's record carries the duplicate's role with its original start date and masthead choice.
Affects: Rule 7 · scenario 5
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim · Rule 7's two "not watched" sentences can be stated plainly.
Evidence: probe-d.md item 22

### D26 — In the second list, the duplicate is listed among the candidates with only its "Merge into this User" action withheld, and the viewer's own account is listed and offered as a target — to a manager and an administrator alike.
Affects: Rule 7 (self-exclusions)
Status: corrects
Apps: OJS
Proposed: plain claim · "one's own account can never be the target" is wrong; only the source is excluded, and even it stays visible.
Evidence: probe-c.md item 16 · probe-d.md item 22

## Offered versus refused

### D27 — On the Users & Roles list, "Merge user" and "Login As" are withheld on any row the manager cannot fully administer — a site administrator's, and a user holding a role in another journal — while Edit, Email, Remove User and Disable User stay offered. A peer manager's row offers all six, so the withholding tracks the target.
Affects: register A3 (merge half) · Rule 1c
Status: corrects
Apps: OJS, OMP
Proposed: A3's merge complaint does not hold on this surface — the list hides Merge rather than dangling it. Probe C left the acceptance half open; probe D settled it by finding no control to accept.
Evidence: probe-c.md item 15 · probe-d.md item 23

### D28 — A manager choosing "Disable User" on a user enrolled in another journal is refused inside the dialog before confirming anything: the panel shows the name and roles, then, in place of the reason box and the OK button, "You do not have sufficient permissions to administer this user. In order to administer a user, you must either be site administrator, or administer all contexts that this user is enrolled in." "Remove User" on the same row is accepted and leaves the other journal's role untouched.
Affects: register A3 (disable half) · scenario 7 · Rule 6
Status: confirms
Apps: OJS, OMP
Proposed: keep A3's disable complaint; scenario 7 should say the refusal arrives before the manager confirms. This settles the server half probe C left open.
Evidence: probe-d.md item 24 · probe-c.md item 15

### D29 — Whether a manager's offers against a site administrator are accepted or refused is undetermined: "Disable User" on that row, and "Merge into this User" on it inside the target list, were not exercised.
Affects: register A3
Status: undetermined
Apps: OJS, OMP
Proposed: A3 keeps this corner unwatched. Settling observation: a manager choosing each action against a throwaway site administrator and recording what the screen renders (see D30).
Evidence: probe-c.md items 15, 16 · probe-d.md item 25

### D30 — Whether a Site Administrator can lock themselves out with the "Disable User" their own row offers is undetermined: this install cannot create a second, throwaway site administrator — no screen in Administration grants that role, and every role-granting screen is per-journal.
Affects: register A2 (execution half)
Status: undetermined
Apps: OJS
Proposed: A2 keeps its offer half watched (D10) and its execution half unwatched — for want of a disposable account to try it on, not because the behaviour was disproved. Settling observation: a throwaway site administrator choosing Disable User on their own row, recording whether the session dies mid-flight or the action is refused.
Evidence: probe-d.md item 25
