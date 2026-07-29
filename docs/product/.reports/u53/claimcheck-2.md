# U53 Users management — claim check, CHUNK 2

**Frame.** QA documentation of an application's own screens, on a local disposable test
install with seeded accounts. Signed in as each role and used the screens the way that
role would — including typing a URL directly to reach one. Recorded what the screen
offers, what happens when it is used, and where the two disagree, so the product team
can fix it. No request was constructed that the screens themselves would not send.

**Scope of this chunk** — `docs/product/specs/users-management.md`:
the whole **Fields & validation** section, and **Rules & state from the start of the
section through the disable/enable rules** (Rules 1, 1a, 1b, 1c, 2, 3, 4, 5).

**Run on** OJS `http://127.0.0.1:8000`, plus OMP `:8100` and OPS `:8200` for the two
claims carrying a `{OJS OMP}` badge. Date 2026-07-29. Driven with a real Chromium
browser through the sign-in form; every observation is a screen observation.

**Scratch material** (all created for this run, prefix `u53cc2`):

| Context | urlPath | id | purpose |
|---|---|---|---|
| Journal A | `u53cc2a` | 87 | manager/peer-manager/reviewer/author/cross-journal user |
| Journal B | `u53cc2b` | 89 | second home for the cross-journal user |
| Journal C | `u53cc2c` | 93 | 30 throwaway users — paging |
| Journal D | `u53cc2d` | 94 | a site administrator holding a context role (row target) |
| Journal E | `u53cc2e` | 95 | throwaway administrator's own row; account form work |
| Journal F | `u53cc2f` | 96 | journal with two supported locales |
| Journal G | `u53cc2g` | 97 | one account that is admin + manager + reviewer (own-record case) |
| Journal H | `u53cc2h` | 100 | disable/enable; a user with one ended role |
| Press (OMP) | `u53cc2omp` | 80 | `{OJS OMP}` reviewer-masthead check |
| Server (OPS) | `u53cc2ops` | 58 | `{OJS OMP}` absence check |

Throwaway accounts used: `u53cc2.admin`, `u53cc2.adm2`, `u53cc2.mgr`, `u53cc2.mgr2`,
`u53cc2.rev`, `u53cc2.rev2`, `u53cc2.auth`, `u53cc2.dis`, `u53cc2.shared`,
`u53cc2.emgr`, `u53cc2.erev`, `u53cc2.enorole`, `u53cc2.fmgr`, `u53cc2.frev`,
`u53cc2.gself`, `u53cc2.grev`, `u53cc2.hmgr`, `u53cc2.hdual`, `u53cc2.hdis`,
`u53cc2.p01`–`p30`, `u53cc2new1`, `u53cc2.ompadm`, `u53cc2.omprev`, `u53cc2.opsadm`,
`u53cc2.opsu`.

**Safety.** `publicknowledge`, its seeded roster and the seeded `admin` were treated as
read-only. **No seeded account was disabled, removed or merged.** The seeded `admin`
was signed in as (twice) only to read a form that a throwaway administrator cannot
render; nothing was saved on those visits. Mailpit was never cleared and nothing in
this chunk required a Mailpit assertion (no claim in scope is about mail).

---

## Summary

| Verdict | Count |
|---|---|
| holds | 58 |
| wrong | 11 |
| undetermined | 2 |
| unreachable | 1 |
| **total claims checked** | **72** |

Fields & validation: 36 claims — 30 hold, 5 wrong (FV-3, FV-12, FV-19, FV-22, FV-23),
1 undetermined (FV-20).
Rules 1–5: 35 claims — 28 hold, 6 wrong (R1-d, R1b-c, R1c-b, R2-c, R4-c, R5-a),
1 undetermined (R1a-e). Plus one unreachable item (U-1).

The eleven wrong claims cluster in three places: the **Hosted Journals row-action list**
(it *does* carry a per-row Disable, on every row including the viewer's own), the
**account form's field rules** (name fields are single-language; the password minimum
is stated only when editing; Editorial Notes needs a context role, not administrator
status, and is withheld on one's own record), and **the disable dialog described as one
dialog** when the two surfaces render visibly different dialogs.

---

# Part 1 — Fields & validation

## The account form (intro sentence)

### FV-1 — "**The account form** — "Add User", and "Edit User" on the Hosted Journals list. One form; the password block changes shape between create and edit."
**Did:** As `u53cc2.admin` (throwaway site administrator) typed
`/index.php/index/admin/wizard/95`, opened the **Users** tab, clicked **Add User**
(`#userGridContainer a[id^="component-grid-settings-user-usergrid-addUser-button"]`),
then separately expanded a row (`tr.gridRow a.show_extras`) and clicked **Edit User**
(`tr.row_controls:visible a:has-text("Edit User")`).
**Appeared:** Both open the same `form#userDetailsForm` in a modal over the wizard.
Create shows heading "Step #1: Fill in User Details", a "Password" fieldset with
Password / Repeat password (both required), **Generate Password** and **Change
Password**. Edit shows the same fieldset without Generate Password, with the two
password fields not required and the note "Leave the password fields blank to keep the
current password. The password must be at least 6 characters."
**Verdict: holds.**

## Field table — the account form

### FV-2 — Given Name | **Required? Yes**
**Did:** Add User form, journal E; also Edit User form.
**Appeared:** `input[name="givenName[en]"]` carries `required`/`aria-required="true"`
and its label reads "Given Name*". Submitting with it empty leaves the form open with
an inline error **"This field is required."**
**Verdict: holds.**

### FV-3 — Given Name | "Offered in each of the site's languages."
**Did:** Administration → Site Settings → **Languages** as `u53cc2.admin`: read the
Enable column. Then opened Add User on journal E (single-locale) **and** on journal F
(`supportedLocales: en, fr_CA`), and Edit User on journal E.
**Appeared:** The site Languages screen lists **English/English (en)** and
**French/français (fr_CA)**, and *both* Enable checkboxes are ticked — the site has two
languages. On every one of those forms the Name section renders exactly one Given Name
input, `name="givenName[en]"`, with one sub-label "Given Name*". There is no second
input, no locale tab, no flag/locale switcher anywhere in the form (searched the form's
markup for `localization` / `pkp_form_locale` / `flag` — zero hits). The form carries a
hidden `sitePrimaryLocale=en`.
**Verdict: wrong.** In plain product terms: on a site with two languages the account
form offers **one** Given Name box, in the site's primary language only. The same is
true of Family Name and Preferred Public Name. A manager cannot enter a person's name
in the site's second language from this form.

### FV-4 — Family Name | **Required? No**
**Did/Appeared:** `input[name="familyName[en]"]`, no `required`, label "Family Name"
with no asterisk; a user was created with it filled and the field never blocked a save.
**Verdict: holds.**

### FV-5 — Preferred Public Name | **Required? No**
**Did/Appeared:** `input[name="preferredPublicName[en]"]`, not required, label
"Preferred Public Name", description "Please provide the full name as the author should
be identified on the published work. Example: Dr. Alan P. Mwandenga".
**Verdict: holds.**

### FV-6 — Username | **Required? Yes**
**Appeared:** `input[name="username"]` `required`, label "Username*".
**Verdict: holds.**

### FV-7 — Username | "Creating only — when editing, the username is shown but cannot be changed."
**Did:** Compared the Add User form and the Edit User form for `u53cc2.erev`.
**Appeared:** On create there is a text input. On edit there is **no input at all** —
the form renders a "Username" section whose body is the plain text `u53cc2.erev`.
**Verdict: holds.**

### FV-8 — Username | "Lowercase letters, numbers and hyphens/underscores"
**Did:** Read the rule text above the field; then on a fresh Add User dialog submitted
`u53cc2.w1` (a dot) and `U53CC2UP1` (uppercase), each with an otherwise valid form.
**Appeared:** The rule is stated above the field as "The username must contain only
lowercase letters, numbers, and hyphens/underscores." Both values are refused — the
account is not created and the dialog stays open.
**Verdict: holds.** (But see FV-19 on *how* the refusal is presented.)

### FV-9 — Username | "a "Suggest" button fills it from the names typed above"
**Did:** Add User, filled Given Name "Newton" / Family Name "Newuser", clicked
`button#suggestUsernameButton` (labelled "Suggest").
**Appeared:** The Username box filled itself with `nnewuser`.
**Verdict: holds.**

### FV-10 — Email address | **Required? Yes**
**Did/Appeared:** `input[name="email"]` `required`, label "Email*"; submitting empty
leaves the form open with an inline **"This field is required."** next to it.
**Verdict: holds.**

### FV-11 — Password / Repeat password | **Required? Yes (creating)**
**Appeared:** On create both `password` and `password2` carry `required` /
`aria-required="true"`, labels "Password*" and "Repeat password*". On edit neither is
required.
**Verdict: holds.**

### FV-12 — Password | "Site minimum length, stated on the form."
**Did:** Read the whole Add User form text (journals E and F). Searched it for
"characters" / "minimum" / "at least" — nothing. Compared with the Edit User form.
**Appeared:** The **create** form's Password fieldset shows only the legend "Password",
the two boxes, "Generate Password" and "Change Password". No minimum length is stated
anywhere on it. The **edit** form states it: "Leave the password fields blank to keep
the current password. **The password must be at least 6 characters.**"
**Verdict: wrong.** In plain product terms: the minimum length is stated only when
editing an existing account. Someone creating an account is told nothing about it —
and a too-short password is then refused with no message (FV-19).

### FV-13 — Password | "When editing, leaving it blank keeps the current password."
**Did:** As `u53cc2.admin`, Edit User on `u53cc2.hdis` (journal H): confirmed both
password boxes render empty, changed only Preferred Public Name, saved. Then in a fresh
browser context signed in as `u53cc2.hdis` with the *original* password.
**Appeared:** The edit saved (modal closed). The original password still signs in —
landed on `/index.php/index/en/index`, no login form.
**Verdict: holds.**

### FV-14 — Generate Password | "Creating only. "Generate random password for this user.""
**Did/Appeared:** `input#generatePassword` is present on the create form with the
adjacent text "Generate random password for this user." and the section label "Generate
Password"; unticked by default. It is **absent** from the edit form.
**Verdict: holds.** (The "also forces the welcome email" half is a Side-effects claim,
outside this chunk, and was not exercised.)

### FV-15 — Change Password | ""User must change password on next log in." Ticked by default when creating."
**Did/Appeared:** `input#mustChangePassword` with adjacent text "User must change
password on next log in." and section label "Change Password". On the create form it is
`checked` on first render and was still checked at the moment of save. On the edit form
for `u53cc2.erev` it renders unchecked.
**Verdict: holds.**

### FV-16 — Country | **Required? No**
**Did/Appeared:** `select#country`, label "Country", first option blank, no asterisk,
not required. A user was created with it left blank.
**Verdict: holds.**

### FV-17 — Notify User | "Creating only: "Send user a welcome email.""
**Did/Appeared:** `input#sendNotify`, section label "Notify User", adjacent text "Send
user a welcome email."; present on create, absent on edit.
**Verdict: holds.**

### FV-18 — Homepage URL / Phone / Reviewing interests / Affiliation / Bio Statement / Mailing Address / Signature | **Required? No**
**Did/Appeared:** All present under "More User Details", none required, none with an
asterisk: `userUrl` (Homepage URL), `phone` (Phone), the tag-it "Reviewing interests"
widget, `affiliation[en]`, `biography[en]` (labelled "Bio Statement (e.g., department
and rank)"), `mailingAddress`, `signature[en]`. A user was created with all of them
empty.
**Verdict: holds.**

### FV-19 — the field table's stated refusals, as the screen delivers them (cross-cutting)
**Did:** Five separate Add User dialogs, each opened fresh from the grid on journal E,
each filled with an otherwise valid form and one fault, then submitted with the form's
own **OK** button: (a) password `abc` (below the stated 6), (b) Password `u53cc2validpw`
/ Repeat `u53cc2different`, (c) email `u53cc2-erev@mail.test` (already in use),
(d) username `u53cc2new1` (already in use), (e) username `U53CC2UP1` (uppercase). Also
(f) username `u53cc2.w1` (a dot).
**Appeared:** In every one of the six cases the account is not created and the dialog
stays open **showing no message whatsoever** — no field-level error, no banner, no
notification (checked `#userDetailsFormContainer .pkp_form_error`, `.error`,
`#userDetailsFormNotification`, `.pkp_notification:visible`, `.ui-pnotify`: all empty).
The typed values remain in place. By contrast the two *empty required field* cases
(Given Name, Email) do produce the inline "This field is required."
**Verdict: wrong** — as a description of what the reader will meet. The Fields table
states refusals ("Lowercase letters, numbers and hyphens/underscores", "Site minimum
length") as if the form enforces them visibly; on screen every one of these refusals is
silent. Someone adding a user with a dot in the username, or a 3-character password,
clicks OK and nothing at all happens — same screen, same values, no explanation.

### FV-20 — Working Languages | "Shown only when the site has more than one language."
**Did:** Site Languages screen shows two enabled locales. Add User and Edit User forms
on journals E and F.
**Appeared:** A "Working Languages" section with two checkboxes, "English" and
"French" (`input[name="locales[]"]` `#locales-en`, `#locales-fr_CA`) — on both create
and edit, in both journals.
**Verdict: undetermined for the "only when" half.** The field is present on a
two-language site, as claimed. The negative half — that it disappears on a
one-language site — cannot be shown without disabling a locale for the whole site,
which is a site-wide mutation on a shared install, not a screen action confined to my
scratch material. Not attempted.

### FV-21 — Editorial Notes | "Shown only once the edited user holds a Reviewer role in this journal"
**Did:** As `admin` (the only viewer who renders the block at all — see FV-22), opened
Edit User on journal E for `u53cc2.erev` (holds Reviewer) and for `u53cc2.enorole`
(holds Reader only).
**Appeared:** For the reviewer, a section labelled **"Editorial Notes"** with the
description "Record notes about this reviewer that you would like to make visible to
other administrators, managers and all editors. Notes will be visible for future review
assignments." and a rich-text `textarea[name="gossip"]`. For the reader, the section is
absent and there is no `textarea[name="gossip"]`.
**Verdict: holds.**

### FV-22 — Editorial Notes | "to a viewing Journal Manager, Section Editor or Site Administrator"
**Did:** Same target (`u53cc2.erev`, a Reviewer in journal E), same screen, two
different viewers. (a) `u53cc2.admin` — a **site administrator** whose only role in
journal E is Author. (b) `admin` — a site administrator who is *also* auto-enrolled as
Journal manager in that journal. Also (c) `u53cc2.ompadm` on OMP (site admin **and**
Press manager) against `u53cc2.omprev`.
**Appeared:** (a) **no Editorial Notes block, no gossip textarea.** (b) the block, with
its description. (c) the block.
**Verdict: wrong.** In plain product terms: what decides whether the notes appear is a
manager/editor role **inside that journal**, not being a Site Administrator. A site
administrator with no role in the journal opens the very same form and the notes are
simply not there — with no indication that anything is missing.
Two further notes for the reader, both observed: no Journal Manager and no Section
Editor can reach this form at all (it lives behind Administration → Hosted Journals,
which admits site administrators only), so on **the account form** the listed
"Journal Manager" and "Section Editor" viewers are not reachable; and the same form on
OPS never shows the block (FV-24).

### FV-23 — Editorial Notes | "One's own record is not excepted: a viewer who qualifies sees the notes on their own record like on anyone else's."
**Did:** Seeded one account, `u53cc2.gself`, holding site administrator + **Journal
manager + Reviewer** in journal G — a viewer who qualifies on every count. Signed in as
them, Administration → Hosted Journals → journal G → Users tab → **Edit User on their
own row**. Positive control in the same session: Edit User on `u53cc2.grev`, another
Reviewer in the same journal.
**Appeared:** Own record — **no "Editorial Notes" section, no `textarea[name="gossip"]`**.
Control record — the section and the textarea are there.
**Verdict: wrong** for this form. In plain product terms: on the account form a
qualifying viewer sees the notes on everyone *except themselves*; their own record
never shows the block. (The spec's counter-example is on the reviewer-picking screens,
which are another feature's surface and were not touched here.)

### FV-24 — Editorial Notes | "{OJS OMP} (OPS installs no reviewer role, so the field never appears there)"
**Did:** OPS `:8200`, scratch server `u53cc2ops` (id 58). As `u53cc2.opsadm` (site
administrator + Preprint Server manager) opened Edit User on `u53cc2.opsu`.
**Appeared:** No Editorial Notes, no gossip textarea. The journal's role list holds
only Preprint Server manager, Moderator, Author, Reader, Editorial Board Member — no
reviewer group of any kind.
**Verdict: holds.**

### FV-25 — Editorial Notes | "The block never appears on the page the Users & Roles row's "Edit" opens."
**Did:** As `u53cc2.hmgr` (Journal manager of journal H), Users & Roles → row options →
**Edit** for `u53cc2.hdis`.
**Appeared:** Landed on `/index.php/u53cc2h/management/settings/user/302`; the page's
text contains no "Editorial Notes".
**Verdict: holds.**

### FV-26 — Roles / Masthead checkboxes | "Editing only — two checkbox lists of this journal's roles"
**Did:** Compared the Add User form and the Edit User form.
**Appeared:** The create form has neither list. The edit form has two: a section titled
**"User Roles"** (18 `input[name="userGroupIds[]"]`) and a section titled **"Appear on
Masthead"** (18 `input[name="mastheadUserGroupIds[]"]`), both labelled with journal E's
role names.
**Verdict: holds.** Worth one note for the reader: the second list is not a picture of
where the person currently appears — **every** masthead box renders ticked, including
for roles the person does not hold (checked for `u53cc2.erev`, `u53cc2.enorole`,
`u53cc2.frev`, `u53cc2.omprev`, `u53cc2.opsu`: 18/18, 18/18, 18/18, 19/19, 5/5 ticked).

### FV-27 — Roles / Masthead | "Every Reviewer row in the masthead list is ticked and cannot be unticked, whether or not the user holds the role {OJS OMP}"
**Did:** OJS journal E: Edit User for `u53cc2.erev` (holds Reviewer) and for
`u53cc2.enorole` (does **not** hold Reviewer). OMP press `u53cc2omp`: Edit User for
`u53cc2.omprev` (holds External Reviewer, not Internal Reviewer).
**Appeared:** OJS — in both cases the masthead row labelled **"Reviewer"** is `checked`
and `disabled`, and it is the only disabled box in the list. OMP — **both** "Internal
Reviewer" and "External Reviewer" masthead rows are `checked` and `disabled`, including
Internal Reviewer which the account does not hold.
**Verdict: holds** (in both apps, and for the "whether or not" case).

### FV-28 — Roles / Masthead | "(OPS installs no reviewer role)"
**Did:** OPS Edit User for `u53cc2.opsu`.
**Appeared:** The masthead list has five rows — Preprint Server manager, Moderator,
Author, Reader, Editorial Board Member — none disabled, none named Reviewer.
**Verdict: holds.**

### FV-29 — "Creating instead continues to a second screen, "Step #2: Add User Roles to {name}", with the same two lists — where nothing is locked."
**Did:** Completed an Add User on journal E: Given Name "Newton", Family Name
"Newuser", username `u53cc2new1`, email `u53cc2-new1@mail.test`, a valid password,
Notify User ticked, saved.
**Appeared:** The modal advanced to a screen headed exactly **"Step #2: Add User Roles
to Newton Newuser"**, carrying "User Roles" (18 boxes) and "Appear on Masthead"
(18 boxes) and buttons Cancel / Save. **All 36 boxes report `disabled: false`** —
including the Reviewer masthead row that is locked on the edit form.
**Verdict: holds.**

## The disable/enable dialog

### FV-30 — "**The disable/enable dialog** — a form, not a yes/no confirmation"
**Did:** As `u53cc2.hmgr`, Users & Roles on journal H, row options → **Disable User** on
`u53cc2.hdis`; and later → **Enable User**.
**Appeared:** A dialog containing `textarea[name="disableReason"]`, hidden `userId` and
`enable` fields, and buttons **Cancel** and **OK** — a form, not a yes/no prompt.
**Verdict: holds.**

### FV-31 — "Reason for disabling user" / "Reason for enabling user" | **Required? No**
**Appeared:** The label above the box reads exactly **"Reason for disabling user"** on
the disable dialog and **"Reason for enabling user"** on the enable dialog. The textarea
carries no `required` attribute, and the enable step was completed with no editing of
the box.
**Verdict: holds.**

### FV-32 — "Free text, stored with the account and pre-filled the next time the dialog opens — enabling does not clear it"
**Did:** Disabled `u53cc2.hdis` with the reason `u53cc2 reason alpha`. Re-opened the row
menu → **Enable User**. Enabled. Re-opened the row menu → **Disable User**.
**Appeared:** The Enable dialog opened with `disableReason` already holding
"u53cc2 reason alpha". After enabling, the Disable dialog opened again with the same
text still in the box. The same behaviour on the Hosted Journals dialog: after
disabling `u53cc2.enorole` with "u53cc2 legacy reason", the enable dialog came back
pre-filled with it.
**Verdict: holds.**

### FV-33 — "the disabled person sees it quoted when their sign-in is refused (Rule 5)"
**Did:** In a fresh browser context, signed in as `u53cc2.hdis` while disabled.
**Appeared:** Landed on `/index.php/index/en/login/signIn` showing
**"Your account has been disabled for the following reason: u53cc2 reason alpha"**.
**Verdict: holds.**

## The email form

### FV-34 — Subject | **Required? Yes**
**Did:** As `u53cc2.hmgr`, Users & Roles journal H, row options → **Email** on
`u53cc2.hdis`.
**Appeared:** `input[name="subject"]` with `required`, label "Subject*".
**Verdict: holds.**

### FV-35 — To | "Fixed: shows the recipient's full name and address; cannot be edited."
**Appeared:** `input[name="user"]`, label "To", value **`Hilda Hdis <u53cc2-hdis@mail.test>`**,
attribute `disabled: true`.
**Verdict: holds.**

### FV-36 — Body | "Required. Rich text. Sent with "Send Email"."
**Appeared:** `textarea[name="message"]` with `required`, label "Body*", wrapped in a
TinyMCE toolbar (a row of rich-text buttons above it). The submit button is
`button[type=submit][name=submitFormButton]` labelled **"Send Email"**; the other
button is "Cancel".
**Verdict: holds.** (Delivery of the message is a Side-effects claim, outside this
chunk; not exercised, so no Mailpit assertion was needed.)

---

# Part 2 — Rules & state (Rules 1 – 5)

## Rule 1 — the two lists

### R1-a — "Both lists are headed "Current Users""
**Did:** Users & Roles on journals A, C, D, E, H as several roles; Hosted Journals →
journal's Users tab on 87/93/94/95/96/97/100.
**Appeared:** The Vue list's heading reads "Current Users (n)"; the wizard's Users tab
is headed "Current Users".
**Verdict: holds.**

### R1-b — "Nothing a manager does on one list is invisible on the other"
**Did:** Disabled `u53cc2.enorole` from the **Hosted Journals** list, then opened
journal E's **Users & Roles** list.
**Appeared:** The Users & Roles row for "Nora Norole" carried the red disabled mark
(the name cell's `span.text-negative` icon).
**Verdict: holds.**

### R1-c — "A Site Administrator is described differently by each list (an empty Roles cell on the Hosted Journals list; "Journal manager" with a blank start date on Users & Roles)"
**Did:** Read the `admin` row on both lists for journals A, C, D and E.
**Appeared:** Users & Roles — `["admin admin", "admin@example.org", "Journal manager",
"", "", ""]`: Roles "Journal manager", Start Date blank. Hosted Journals — Roles cell
empty. The same journal's Hosted-Journals role filter set to "Journal manager"
*returns* the admin row.
**Verdict: holds.**

### R1-d — "every journal's Hosted Journals table lists the administrator whether they are enrolled there or not"
**Did:** Seeded a second site administrator, `u53cc2.adm2` (site administrator +
Author), enrolled **only** in journal D. Read the full Hosted Journals user table for
journal E (5 rows) and journal C (default view). Also read journal A's and journal E's
tables for `u53cc2.admin`, a site administrator enrolled in E but not in A/C/D.
**Appeared:** `u53cc2.adm2` appears in **no** table but journal D's. `u53cc2.admin`
appears in journal E's table (where they hold Author) and in **none** of A's, C's or
D's. The only administrator who shows up everywhere is the install's own `admin` — and
that account is enrolled in every scratch journal (the Journal-manager filter returns it
in both journal E and journal C).
**Verdict: wrong.** In plain product terms: a journal's Hosted Journals table lists a
site administrator only where that administrator actually holds a role in the journal.
The install's own administrator looks like an exception only because it is enrolled in
every journal; a second administrator is simply absent.

### R1a-a — "The Users & Roles list shows 25 users per page"
**Did:** Journal C (31 users) as `u53cc2.admin`.
**Appeared:** 25 rows on page 1, 6 on page 2. Pagination is a `[role="navigation"]`
holding Previous / 1 / 2 / Next buttons (`aria-label="Go to Page 1"` etc.).
**Verdict: holds.**

### R1a-b — "under a heading whose count follows the current filter"
**Did:** Journal C: read the heading, then searched `Pager03`, then `Page`.
**Appeared:** "Current Users (31)" → "Current Users (1)" → "Current Users (30)".
**Verdict: holds.**

### R1a-c — columns ""Name" …, "Email", "Roles" and "Start Date" …, "Affiliation", and a per-row options menu"
**Did:** Read `table thead th` of the Current Users table on journals A, C, D, E, H.
**Appeared:** `["Name","Email","Roles","Start Date","Affiliation","More Actions"]`. The
Affiliation cell renders content (`u53cc2.rev` shows "Cc2 Institute").
**Verdict: holds.** One wording note: the sixth column's visible header is **"More
Actions"**, which the rule does not name.

### R1a-d — "Start Date (each current role with the date it began — ended roles are not shown)"
**Did:** Seeded `u53cc2.hdual` with Author **and** Reader in journal H, then used the
Hosted Journals Edit User form to untick Reader (ending that role). Read the row.
**Appeared:** Roles "Author", Start Date "2026-07-29" — the ended Reader role is gone
from both cells.
**Verdict: holds.**

### R1a-e — "with an ORCID mark when the user has one"
**Did:** No throwaway account on this install carries an ORCID, and nothing in the
screens I own puts one there (the account form has no ORCID field; the seeding surface
has no user-level ORCID key).
**Verdict: undetermined.** Not observed either way.

### R1a-f — "a red disabled mark when the account is disabled"
**Did:** Disabled `u53cc2.hdis` and read the Name cell's markup; also `u53cc2.enorole`
after disabling it from the other list.
**Appeared:** The name cell gains `<span class="… text-negative"><svg …>` — a red
crossed-out-person icon beside the name.
**Verdict: holds.**

### R1a-g — "The row-options button gives assistive technology a raw technical key in place of a name"
**Did:** Read the `aria-label` of the last cell's button on every row of journals A and D.
**Appeared:** `aria-label="##userAccess.management.options##"` on all of them, while the
column header "More Actions" is properly worded.
**Verdict: holds.**

### R1a-h — "a single search field narrows the list; its hint text names "Journal editor" as its example"
**Did:** Read the placeholder on journals A, C, E, H.
**Appeared:** One `input` above the table with placeholder **"Enter a user's name, role
(e.g Journal editor), or affiliation"**. (A second search box exists on the page, but it
is the dashboard's "Search submissions" box, outside this table.)
**Verdict: holds** on OJS. (The cross-app half of ⚠ A4 belongs to another chunk.)

### R1b-a — "The Hosted Journals list shows five columns — given name, family name, username, roles, email."
**Did:** Read `thead th` of `#userGridContainer` on journals A and E.
**Appeared:** `["Given Name","Family Name","Username","Roles","Email"]`.
**Verdict: holds.**

### R1b-b — the filter: "behind the header's "Search" link — hidden until clicked, and collapsing again after each search; revealed, it holds a search box, a role dropdown (default "All Roles"), an "Include users with no roles in this journal." checkbox, and a "Search" button, and applies on pressing Search."
**Did:** Journal E. Checked `form#userSearchForm` visibility before clicking; clicked
`a[id^="component-grid-settings-user-usergrid-search-button"]` ("Search"); read the
controls; ran a role-scoped search; re-checked visibility; then ran one with the
checkbox ticked.
**Appeared:** Hidden before (`isVisible() === false`), visible after. Controls:
`input[type=search][name=search]`, `select#userGroup` whose selected option is **"All
Roles"**, `input#includeNoRole` labelled **"Include users with no roles in this
journal."**, and `button[type=submit]` labelled **"Search"**. Choosing "Reviewer" and
pressing Search narrowed the grid to the one reviewer; the form was hidden again
afterwards. With `includeNoRole` ticked the grid returned site-wide users with no role
in the journal.
**Verdict: holds** — every element, default and behaviour as written.

### R1b-c — "Its row actions are Email, Edit User, Remove (so labelled — not "Remove User"), Login As, Merge User — it carries no per-row Disable: disabling and enabling accounts is the Users & Roles list's alone"
**Did:** As `u53cc2.admin`, journal E's Users tab: expanded each of the five rows in turn
(`tr.gridRow a.show_extras`) and read the control row that is the row's immediate next
sibling (`el.nextElementSibling` with class `row_controls`), collapsing between rows.
Repeated on journal G as `u53cc2.gself`, and on journal H.
**Appeared, on every row of every journal checked:**

| row | actions in the control row |
|---|---|
| `admin` (site admin) | Email · Edit User · **Disable User** · Remove |
| `u53cc2.admin` (site admin, **the viewer**) | Email · Edit User · **Disable User** · Remove |
| `u53cc2.emgr` (Journal manager) | Email · Edit User · **Disable User** · Remove · Login As · Merge User |
| `u53cc2.erev` (Reviewer) | Email · Edit User · **Disable User** · Remove · Login As · Merge User |
| `u53cc2.enorole` (Reader) | Email · Edit User · **Disable User** · Remove · Login As · Merge User |

The Disable link is a real per-row control with its own id
(`component-grid-settings-user-usergrid-row-<userId>-disable-button-…`, class
`pkp_linkaction_disable`) and href `…/user-grid/edit-disable-user?…&rowId=<userId>&…`.
**"Remove" is indeed labelled "Remove"** — that half holds.
**Verdict: wrong.** In plain product terms: the Hosted Journals list carries **six** row
actions, not five, and disabling is **not** the Users & Roles list's alone. Every row
offers "Disable User", including a site administrator's row and **the viewer's own
row**. Clicking it opens a working disable form (R4-d, R5-a below) — I disabled and
re-enabled a throwaway through it end to end.

### R1c-a — "on the Users & Roles list the viewer's own row offers only "Edit" and "Email" … a Journal Manager's own row is stripped identically"
**Did:** Journal A and journal D as `u53cc2.mgr` (Journal manager).
**Appeared:** Own row menu = `["Edit","Email"]` in both.
**Verdict: holds** (this was already settled; recorded for completeness).

### R1c-b — "The Hosted Journals list withholds Login As and Merge User on the viewer's own row and still offers Email, Edit User and Remove there."
**Did:** Journal E as `u53cc2.admin` (own row present); journal G as `u53cc2.gself`
(own row present).
**Appeared:** Own row = Email · Edit User · **Disable User** · Remove. Login As and
Merge User are indeed withheld; Email, Edit User and Remove are indeed offered.
**Verdict: wrong** as written — the sentence is a complete enumeration of what is left
("still offers Email, Edit User and Remove") and it omits **Disable User**, which is
offered on the viewer's own row. Clicking it opens a disable form carrying the viewer's
own user id with an OK button and nothing standing in the way; I opened it and closed it
without confirming.

### R1c-c — "the Users & Roles list also withholds "Merge user" and "Login As" on any row the viewing Journal Manager cannot fully administer — a Site Administrator's, or a user who also holds a role in another journal — while Edit, Email, Remove User and Disable User stay offered there"
**Did:** As `u53cc2.mgr` (Journal manager of journals A and D). In journal D, the row of
`u53cc2.adm2` (a site administrator). In journal A, the row of `u53cc2.shared` (also
enrolled in journal B, which this manager does not run).
**Appeared:** Both rows: `["Edit","Email","Remove User","Disable User"]` — Merge user
and Login As absent, the other four present. The `admin` row in both journals behaves
the same way.
**Verdict: holds.**

### R1c-d — "A fellow manager's row, fully administered, offers all six actions."
**Did:** As `u53cc2.mgr`, the row of `u53cc2.mgr2` in journals A and D.
**Appeared:** `["Edit","Email","Login As","Remove User","Disable User","Merge user"]`.
**Verdict: holds.**

## Rule 2 — searching and filtering

### R2-a — "Search on the Users & Roles list runs only when Enter is pressed in the search field — typing alone changes nothing, and no search button is offered."
**Did:** Journal C: typed `Pager03` character by character, waited 2.5 s, read the list;
then pressed Enter. Also enumerated every button on the page.
**Appeared:** After typing and waiting: heading still "Current Users (31)", still 25
rows, first row still "admin admin". After Enter: "Current Users (1)", one row,
"Pager03 Page03". The page's buttons are Skip-links, Journals, Tasks, the user menu,
"Invite to a role", and the pagination — there is no search button anywhere near the
field.
**Verdict: holds.**

### R2-b — "and resets the list to its first page"
**Did:** Journal C: went to page 2 (rows Pager25–Pager30), then searched `Page`.
**Appeared:** 30 matches, 25 rows shown, first row "Pager01 Page01" — back on page 1.
**Verdict: holds.**

### R2-c — "It matches against names, roles and affiliations"
**Did:** Journal A: searched `Institute` (an affiliation only `u53cc2.rev` has),
`Peermanager` (a family name), `Journal manager` (a role), `u53cc2.shared` (a username),
`u53cc2-shared@mail.test` (an email address), `Author`. Journal D: searched `Reviewer`
against `u53cc2.rev2`, whose name contains no "reviewer". Cross-checked `Author` against
the Hosted Journals role filter for the same journal.
**Appeared:** `Institute` → Rex Reviewer (affiliation match ✓). `Peermanager` → Milo
(name ✓). `Journal manager` → the three managers (role ✓). `Reviewer` in journal D →
Rita Revtwo (role, not name ✓). **`u53cc2.shared` → Sasha Shared** and
**`u53cc2-shared@mail.test` → Sasha Shared** — the search also matches usernames and
email addresses, which the rule does not mention. **`Author` → four rows: Aria Author,
Dana Disable, Sasha Shared *and* `admin admin`** — whose Roles cell reads "Journal
manager" and whom the same journal's Hosted-Journals role filter for "Author" does
**not** return (it returns exactly the three Authors). Reproduced in journal C.
`Reader` and `Copyeditor` return "No Items", so this is not "the administrator always
matches".
**Verdict: wrong** as a description of the search's reach. In plain product terms: the
box also matches usernames and email addresses, and a role term can pull in a row whose
Roles cell shows no such role — so a manager searching by role gets a result they cannot
account for from the list in front of them.

### R2-d — "The Hosted Journals filter applies on its "Search" button and can also select a single role, or users with no roles at all."
**Did:** Journal E, covered under R1b-b: chose "Reviewer" + Search; then "All Roles" +
"Include users with no roles in this journal." + Search.
**Appeared:** Role-scoped search returned the single reviewer. The checkbox search
returned site-wide accounts holding no role in the journal (the whole `publicknowledge`
roster among them). Neither applies until Search is pressed.
**Verdict: holds.**

## Rule 3 — editing

### R3-a — "The Users & Roles row's "Edit" leaves the list for the user's own edit page"
**Did:** As `u53cc2.hmgr`, journal H, row options → **Edit** on `u53cc2.hdis`
(`page.getByRole('menuitem', {name: 'Edit'})`).
**Appeared:** Navigated away from `…/management/settings/access` to
`…/management/settings/user/302`, a page headed "STEP 1 - Enter details and invite for
roles" with Email / ORCID iD / Given Name / Family Name / Affiliation.
**Verdict: holds.**

### R3-b — "The Hosted Journals row's "Edit User" opens the full account form ([Fields](#fields)) in place."
**Did:** Journal E, row `u53cc2.erev`, control row → **Edit User**.
**Appeared:** The browser address never changed (`…/index/en/admin/wizard/95#users`); a
modal opened over the wizard carrying `form#userDetailsForm` — the full account form
described in Fields, with the Roles and Masthead lists.
**Verdict: holds.**

## Rule 4 — disabling

### R4-a — "Disabling an account keeps the account and everything it holds — roles, masthead choices, history — and only bars the person from signing in"
**Did:** Disabled `u53cc2.hdis` from Users & Roles and read the row before and after;
then enabled and signed in.
**Appeared:** Before: `["Hilda Hdis","u53cc2-hdis@mail.test","Author","2026-07-29","",""]`.
After: identical. The account is still listed and the count is unchanged; only sign-in
is refused (R4-c). After enabling, the same password signs in again.
**Verdict: holds** for what the screens show (roles and the row survive). Masthead
choices and history are not rendered on any screen in this chunk, so those two words are
carried on the roles observation, not independently watched.

### R4-b — "any window they have open elsewhere is thrown back to sign-in at that moment"
**Did:** Signed `u53cc2.hdis` in in a second browser context (landed on
`/index.php/index/en/index`), then disabled the account from the manager's window, then
navigated in the user's window to `/index.php/u53cc2h/submissions`.
**Appeared:** The user's window landed on
`/index.php/u53cc2h/login?source=%2Findex.php%2Fu53cc2h%2Fsubmissions` with a sign-in
form.
**Verdict: holds.**

### R4-c — "The list then marks the row with the red disabled icon and the row's action reads "Enable User" in place of "Disable User"."
**Did:** Users & Roles, journal H: read the Name cell markup and the row menu after
disabling. Hosted Journals, journal E: read the row markup and the control row after
disabling `u53cc2.enorole`.
**Appeared:** **Users & Roles** — red `text-negative` icon in the Name cell; menu
becomes `["Edit","Email","Login As","Remove User","Enable User","Merge user"]`, i.e.
"Enable User" exactly. **Hosted Journals** — the row's markup is unchanged, **no mark of
any kind**, and the control row's action becomes **"Enable"**, not "Enable User" (the
disable label there *is* "Disable User", so the pair is asymmetric).
**Verdict: wrong** as an unqualified statement about "the list". It is true of the Users
& Roles list. On the Hosted Journals list a disabled account's row looks exactly like an
enabled one — nothing on the row says the person cannot sign in — and the action reads
"Enable".

## Rule 5 — the dialog

### R5-a — "The dialog is titled "Disable {name}" (or "Enable {name}") and lists the user's roles under "Current Roles""
**Did:** Users & Roles, journal H: opened Disable on `u53cc2.hdual` and on
`u53cc2.hdis`, and Enable on `u53cc2.hdis`. Hosted Journals, journal E: opened Disable
on `u53cc2.enorole` and on the viewer's own row, and Enable on `u53cc2.enorole`.
**Appeared:** **Users & Roles** — "Disable Hugo Hdual" / "Disable Hilda Hdis" /
"Enable Hilda Hdis", each followed by `Current Roles : Author, Reader` (resp.
`Current Roles : Author`). **Hosted Journals** — the dialog is titled just
**"Disable User"** (and **"Enable"** for the reverse), with **no name and no roles
listed at all**; its body is the reason box, the note, Cancel and OK.
**Verdict: wrong** as an unqualified statement about "the dialog". The named,
role-listing dialog is the Users & Roles one; the Hosted Journals dialog names nobody.

### R5-b — "a listing that also includes roles that have already ended, which the list's own Roles column no longer shows"
**Did:** `u53cc2.hdual` was seeded with Author + Reader in journal H; the Reader role was
then ended through the Hosted Journals Edit User form (unticking Reader in "User
Roles"). Read the row, then opened the disable dialog.
**Appeared:** Row Roles cell = "Author" (Reader gone). Dialog =
**"Current Roles : Author, Reader"**.
**Verdict: holds.**

### R5-c — "The operation does not touch roles."
**Did:** Read `u53cc2.hdis`'s Roles and Start Date cells before disabling, after
disabling and after enabling.
**Appeared:** `Author` / `2026-07-29` throughout.
**Verdict: holds.**

### R5-d — "The reason box is stored, shown again pre-filled on the next open, and never cleared by enabling"
Covered by FV-32. **Verdict: holds.**

### R5-e — "enabling restores sign-in exactly as it was"
**Did:** After enabling `u53cc2.hdis`, signed in in a fresh browser context with the
original password.
**Appeared:** Landed on `/index.php/index/en/index`, no login form.
**Verdict: holds.**

### R5-f — "their sign-in attempt is refused with "Your account has been disabled for the following reason: …" quoting it"
Covered by FV-33. **Verdict: holds.**

### R5-g — "nothing on the dialog tells the manager the reason will be shown to the user"
**Did:** Read the full text of both dialogs on both surfaces.
**Appeared:** Disable dialog: title, "Current Roles : …" (Users & Roles only), "Reason
for disabling user", then the note **"Please note that once a user is disabled, you
won't be able to add them to any roles until the user is enabled again."**, then Cancel /
OK. Enable dialog: "Reason for enabling user" and **"Once the user is enabled, they will
regain access to the site, and you'll be able to invite them to roles as needed."**
Neither note, nor anything else on either dialog, mentions that the typed reason is
shown to the person.
**Verdict: holds.** (Both dialogs *do* carry a note, which the Fields table does not
mention; the note is about roles, not about the reason.)

---

# Part 3 — the one thing I could not reach

### U-1 — a Journal Manager or Section Editor on the account form
The Fields section describes the account form's Editorial Notes as visible "to a viewing
Journal Manager, Section Editor or Site Administrator". The account form is reachable
only from Administration → Hosted Journals, and Site Administration admits site
administrators only — a Journal Manager signing in has no route to it (checked: the
journal-level Users & Roles row "Edit" goes to a different page entirely, R3-a). So the
Journal-Manager and Section-Editor readings of that rule cannot be exercised on this
form by anyone.
**Verdict: unreachable.**

---

# Proposed digest blocks (step-3b schema) — for the merge agent

### D-cc2-1 — On the Hosted Journals list every user row offers "Disable User", the viewer's own row included
`Affects:` Rule 1b · Rule 1c · Rule 4 · Rule 5 · Actors row "Disable / Enable an account"
`Status:` corrects
`Apps:` OJS (watched); the surface is the shared legacy grid
`Proposed:` plain claim — the Hosted Journals row actions are Email, Edit User, Disable User, Remove, Login As, Merge User; disabling is offered on both lists, and on this one it is offered on the viewer's own row and on a site administrator's row
`Evidence:` claimcheck-2.md R1b-c, R1c-b

### D-cc2-2 — A disabled account looks normal on the Hosted Journals list, and its action there reads "Enable"
`Affects:` Rule 4
`Status:` corrects
`Apps:` OJS
`Proposed:` plain claim — the red disabled mark and the "Enable User" label belong to the Users & Roles list; the Hosted Journals row carries no mark and reads "Enable"
`Evidence:` claimcheck-2.md R4-c

### D-cc2-3 — The Hosted Journals disable dialog names nobody and lists no roles
`Affects:` Rule 5 · Fields (disable/enable dialog)
`Status:` corrects
`Apps:` OJS
`Proposed:` plain claim — the titled, role-listing dialog is the Users & Roles one; the Hosted Journals dialog is headed "Disable User" / "Enable"
`Evidence:` claimcheck-2.md R5-a

### D-cc2-4 — The account form refuses a bad username, a short password, a mismatched repeat or a duplicate email without saying anything
`Affects:` Fields (account form) · new
`Status:` new
`Apps:` OJS
`Proposed:` 🐞 — six faults, six silent refusals: the dialog stays open with the typed values and no message; only empty required fields produce "This field is required."
`Evidence:` claimcheck-2.md FV-19

### D-cc2-5 — The account form offers one Given Name box on a two-language site
`Affects:` Fields (Given Name)
`Status:` corrects
`Apps:` OJS
`Proposed:` plain claim — Name, Family Name and Preferred Public Name render in the site's primary language only; no locale switcher
`Evidence:` claimcheck-2.md FV-3

### D-cc2-6 — The password minimum is stated only when editing
`Affects:` Fields (Password)
`Status:` corrects
`Apps:` OJS
`Proposed:` plain claim — "The password must be at least 6 characters" appears on the edit form; the create form states no minimum
`Evidence:` claimcheck-2.md FV-12

### D-cc2-7 — Editorial Notes follows a role in the journal, not administrator status, and is withheld on one's own record
`Affects:` Fields (Editorial Notes)
`Status:` corrects
`Apps:` OJS, OMP (absent on OPS as written)
`Proposed:` plain claim — a site administrator with no manager/editor role in the journal does not see the block; a qualifying viewer does not see it on their own record (positive control on a peer reviewer in the same journal)
`Evidence:` claimcheck-2.md FV-22, FV-23

### D-cc2-8 — The users search also matches usernames and email addresses, and returns rows for a role they do not show
`Affects:` Rule 2
`Status:` corrects
`Apps:` OJS
`Proposed:` plain claim — searching a username or an email address finds the person; a search for "Author" returned the administrator's row, whose Roles cell reads "Journal manager" and which the journal's own role filter does not return under Author
`Evidence:` claimcheck-2.md R2-c

### D-cc2-9 — A site administrator appears in a journal's Hosted Journals table only where they hold a role
`Affects:` Rule 1 · register A8
`Status:` corrects
`Apps:` OJS
`Proposed:` plain claim — a second administrator enrolled in one journal is absent from every other journal's table; the install's own administrator appears everywhere because it is enrolled everywhere
`Evidence:` claimcheck-2.md R1-d

### D-cc2-10 — The masthead list renders every role ticked, held or not
`Affects:` Fields (Roles / Masthead checkboxes)
`Status:` new
`Apps:` OJS, OMP, OPS
`Proposed:` footnote — the second list is not a picture of where the person appears: all rows arrive ticked, and only the reviewer rows are locked
`Evidence:` claimcheck-2.md FV-26, FV-27

### D-cc2-11 — Both disable dialogs carry a note about roles
`Affects:` Fields (disable/enable dialog)
`Status:` new
`Apps:` OJS
`Proposed:` footnote — "Please note that once a user is disabled, you won't be able to add them to any roles until the user is enabled again." / "Once the user is enabled, they will regain access to the site, and you'll be able to invite them to roles as needed."
`Evidence:` claimcheck-2.md R5-g
