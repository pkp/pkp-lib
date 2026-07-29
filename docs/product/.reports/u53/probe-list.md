# U53 — Users management · probe list

RUNBOOK step 2 deliverable, second half. Executed under step 3 by Opus probe
agents. Every item is a screen action by a signed-in seeded role plus a
recorded observation; nothing here constructs a request the screens would not
send. Typed URLs are in scope (a bookmark is ordinary user behaviour).

**Sources**: spec draft `docs/product/specs/users-management.md`;
inventory `docs/product/.reports/u53/code-inventory.md` (its §10 items P1–P10
are folded in below — the P# reference on an item says where).

## Standing setup and etiquette (binding for every item)

- **Fleets**: OJS `127.0.0.1:8000` · OMP `127.0.0.1:8100` · OPS `127.0.0.1:8200`.
  Never `localhost` — page requests via `localhost` fail with a bare 400.
- **Scratch contexts via the scenario endpoints** for anything mutating.
  `publicknowledge` (and each app's seeded context) and every seeded account
  are **read-only**: sign in as them, look, never change them.
- **Throwaway accounts**: every account this list disables, removes roles
  from, merges, or otherwise mutates is created by the probe itself — via the
  scenario endpoints, or through the Add User flow inside a scratch context
  (item 12's flow doubles as the screen-driven way to mint one). Give each a
  unique recipient address (`u53-<item>-<n>@mail.test` pattern) so Mailpit
  scoping is unambiguous.
- **Mailpit discipline** (rule for every email/no-email claim): never clear
  Mailpit. Scope every query by the throwaway **recipient address** AND the
  **per-app tag** (fleets run concurrently). To establish **silence**: record
  the recipient's mailbox (zero messages) before the action, perform the
  action, then run a positive control — an action known to mail (e.g. the
  row "Email" send, or a single-role end) to the **same** Mailpit — and only
  after the control message has arrived re-check the target recipient and
  record it still at zero. Silence is a recorded observation with a
  positive control, never an assumption.
- **Cross-app items are owned end-to-end by the one agent running that
  group**, driving all fleets named in the item — never split by app
  mid-item (multi-app rule 4).
- Probe reports record the locator used and mark claim-vs-context, per
  RUNBOOK step 3. Report per group → `.reports/u53/probe-<group>.md`.

## Group map

| Group | Items | Settles | Apps |
|---|---|---|---|
| G1 Doors & typed addresses | 1–4 | who reaches each surface at each address, and the orphaned report URL | cross-app (all 3) |
| G2 Users & Roles list — read-only looks | 5–9 | what the Vue list shows, how search behaves, the own-row offer, the two string/a11y bugs | cross-app (all 3) |
| G3 Hosted Journals surface & account form | 10–13 | the legacy list's dressing/filter, its own-row offers, Add User + the account form's fields | site admin; OJS primary, controls flagged per item |
| G4 Email action | 14 | the per-user Email form and its delivery | cross-app (all 3) |
| G5 Offered-vs-server, read-only half | 15–16 | which rows offer Merge/Login As/Disable that the server would refuse — the offer side only, nothing confirmed | OJS + OMP |
| G6 **Destructive operations** | 17–25 | disable/enable, remove, merge, masthead — the irreversible half of the feature, on throwaway accounts only | mixed, cross-app where flagged |
| G7 Email-templates roster | 26 | OPS1 — which of this feature's emails the Emails screen lists | cross-app (all 3) |

G6 is the destructive group: **no item in it touches a seeded account or
`publicknowledge`; each item states what it creates before it destroys
anything.** Groups G1, G2, G4, G6, G7 run in all apps named on their items;
G3 and G5 name theirs per item.

---

## G1 — Doors & typed addresses (read-only; cross-app)

Settles: which role reaches which surface at which address in each app, and
whether the users spreadsheet really has no door.

**1.** [P1] As the seeded **site administrator**, in each app, open the
journal's (press's/server's) backend and record whether the left menu shows
Settings → Users & Roles at all; on OJS, click it and record where it lands.
*Apps: OJS+OMP+OPS, cross-app.* Settles: Actors row 1's site-admin clause and
fn-a's menu-visibility question (the menu gate reads `permitSettings` without
filtering by role).

**2.** As **site administrator**, in each app, navigate Administration →
Hosted Journals (Presses/Servers) → open the seeded context's settings pages →
Users tab. Record: whether a password re-confirmation prompt appears on the
way in and its exact wording (fn-b — unwatched), and that the tab shows a
table headed "Current Users". Read-only — look, don't act.
*Apps: OJS+OMP+OPS, cross-app.* Settles: the re-auth claim in the Actors
section and Rule 1b's surface existence.

**3.** As **site administrator**, in each app, type the wizard address
**after the journal's own address** (e.g.
`127.0.0.1:8000/index.php/publicknowledge/admin/wizard/1`) and record exactly
what appears (the spec claims the page is refused; the refusal's shape is
unwatched, fn-b). Then type the site-form address
(`.../index/admin/wizard/1`) and record that it answers.
*Apps: OJS+OMP+OPS, cross-app.* Settles: "answer only at the site's own web
address" (Actors section).

**4.** [P9/A7] As seeded **Journal Manager**, type
`{journal address}/api/v1/users/report` into the address bar; record whether
a file downloads and its filename. Then as a seeded role the lists do not
admit (seeded **author** or reader account, read-only sign-in), type the same
address and record what appears. While Groups 2–3 sweep their screens, each
records whether ANY control links to this address — the "no door" half.
*Apps: OJS+OMP+OPS, cross-app.* Settles: Rule 9 and register A7.

## G2 — Users & Roles list: read-only looks (cross-app)

Settles: Rule 1a/1c/2's description of the Vue list, register A4 and A5, and
the own-row offer (P10).

**5.** As **Journal Manager** in a scratch journal seeded with a handful of
users (scenario endpoints): record the "Current Users" heading with its live
count, the column set (Name / Email / Roles and Start Date / Affiliation /
row menu), that ended roles do not appear in the Roles column, and the
pagination control. If the scratch context can be seeded past 25 users,
record the 25-per-page split; otherwise record the shown count and flag the
page-size claim as still unwatched. (The red disabled mark is observed in
item 17; the ORCID mark cannot be staged — see deferred queue.)
*Apps: OJS primary; OMP+OPS one-look control of heading + columns.
Cross-app.* Settles: Rule 1a, fn-c's column claims.

**6.** Same screen, same role: type a partial user name into the search box
and record whether the list narrows **as you type** (no button); clear it,
search by a role name, then by an affiliation string, recording matches; if
paginated past page 1, navigate to page 2, type a search term, and record
the list returning to its first page.
*Apps: OJS; OMP control for the as-you-type behavior. Cross-app.* Settles:
Rule 2's search claims (fn-c — code-read, unwatched).

**7.** [A5] Same screen: record the search field's hint/placeholder text
**verbatim** in all three apps.
*Apps: OJS+OMP+OPS, cross-app.* Settles: register A5 (the "Journal editor"
example on presses and preprint servers, and the "e.g" period).

**8.** [A4] Same screen: record the accessible name the row-options
(ellipsis) menu button exposes to assistive technology — i.e. what a screen
reader would be given for that button (accessibility-tree/aria-label
observation of the rendered page, all three apps). Expected per code: the raw
key `userAccess.management.options`.
*Apps: OJS+OMP+OPS, cross-app.* Settles: register A4.

**9.** [P10] As **Journal Manager**, find the manager's **own row** in the
list and open its menu: record the exact action set offered. The draft
claims Edit and Email appear (unguarded) while Remove, Enable/Disable, Merge
and Login As are withheld.
*Apps: OJS+OMP+OPS, cross-app.* Settles: Rule 1c's Vue-side withholding and
the Actors rows for Edit/Email ("offered on every row, the actor's own
included").

## G3 — Hosted Journals surface & the account form (site admin)

Settles: Rule 1b's dressing, the legacy filter, A2's offer-half, and the
Fields table.

**10.** As **site administrator**, on the wizard's Users tab (via item 2's
path), record: the five columns (given name, family name, username, roles,
email), the filter row (search box; role dropdown defaulting to "All Roles";
"Include users with no roles in this journal." checkbox; "Search" button),
that the filter applies **on pressing Search** (not as-you-type), and that
selecting a single role, and separately the no-roles checkbox, narrow the
list accordingly. Read-only.
*Apps: OJS primary; OMP quick control of the filter row. * Settles: Rule 1b,
Rule 2's second half (fn-d).

**11.** [A2, offer half] Same screen: find the administrator's **own row**
and record the exact action set offered. The draft claims Email, Edit User,
Enable/Disable and Remove appear while Login As and Merge are withheld.
**Record only — do not choose Disable on the seeded administrator's row.**
The execution half is item 25.
*Apps: OJS+OMP+OPS, cross-app (exclusivity claim).* Settles: Rule 1c's
legacy-side withholding, register A2's on-screen half.

**12.** As **site administrator**, in a **scratch journal**: press "Add
User". Record the form against the Fields table: required marks (Given
Name, Username, Email, Password, Country), the username rules and the
"Suggest" button (type names, press it, record what it fills), the
password block with Generate Password ("Generate random password for this
user.") and Change Password checkboxes, the Notify User checkbox, whether
Working Languages appears (record how many languages the install has).
Attempt a save without Country and record the validation. Then create a
**throwaway** user with Notify User ticked; record the "Step #2: Add User
Roles to {name}" screen with its two checkbox lists; assign a role and save.
Mailpit (scoped by the throwaway's address + app tag): record the welcome
email's arrival and its reply-to (draft: the journal's contact address).
Confirm the new user appears in the scratch journal's Users & Roles list.
*Apps: OJS primary; OMP+OPS: repeat the create + welcome-mail check with one
throwaway each (the mail claim is unmarked-all-apps). Cross-app.* Settles:
scenario 6, Fields table (create shape), fn-l, fn-n.

**13.** Same role, "Edit User" on item 12's throwaway: record that the
username is shown but not editable; that Notify User is absent when editing;
the Roles and Masthead checkbox lists; on OJS and OMP, that a Reviewer row in
the Masthead list is rendered un-tickable (grant the throwaway the reviewer
role first via the Roles list); that Editorial Notes appears when the edited
user is a Reviewer here and the viewer a manager/administrator — and is
absent on the administrator's own record; save with the password fields
blank, then as the throwaway sign in with the original password and record
it still works; Mailpit: establish silence for the edit-save (no mail on
edit — per the standing Mailpit discipline, positive control included).
On OPS, record instead that no reviewer role exists to assign, so neither
the reviewer masthead lock nor Editorial Notes can appear.
*Apps: OJS+OMP, OPS absence control. Cross-app.* Settles: Fields table
(edit shape), Editorial Notes row, the {OJS OMP} reviewer-masthead lock.

## G4 — The Email action (cross-app)

Settles: the email form's shape and that it actually delivers — scenario 2.

**14.** As **Journal Manager** in a scratch journal, on a throwaway user's
row choose "Email". Record the form: Subject (required), To (fixed, shows
full name and address, cannot be edited), rich-text Body, "Send Email"
button. Send; Mailpit scoped by the throwaway's address + app tag: record
receipt. Then choose "Email" on the manager's **own row** (offer recorded in
item 9), send to self, and record it delivers.
*Apps: OJS primary; OMP+OPS one send each. Cross-app.* Settles: scenario 2,
the Email fields table, Actors row "Email a user" (no target restriction,
own row included).

## G5 — Offered vs server: the read-only half (OJS + OMP)

Settles: which rows dangle actions the server would refuse — **observation
only; nothing is confirmed in this group**. The refusal executions live in
G6 (items 23–24).

**15.** [P2] Stage: in a scratch journal, give the **site administrator** a
role there (screen-driven: as admin, via the wizard's Users tab — tick
"include users with no roles", Edit User, tick a role; or scenario endpoint
if it can). Then as **Journal Manager** of that scratch journal, find the
administrator's row in the Users & Roles list and record which actions its
menu offers — specifically whether **Merge user**, **Login As** and
**Disable User** appear on a row the server flatly bars (nobody administers
a site administrator). **Open the menu only; choose nothing.**
*Apps: OJS + OMP control. Cross-app.* Settles: register A3's
site-admin-target half (the schema map vs `getAdministrationLevel`
disagreement).

**16.** [P3, P5 offer-side] As **site administrator** in a scratch journal
with two throwaway accounts staged: choose "Merge user" on one. Record: the
second list's title ("Merge into this User"), that the duplicate's own row
is absent from it, that the administrator's own account is absent as a
target, and which rows offer "Merge Into User". **Close the modal without
confirming anything.**
*Apps: OJS.* Settles: Rule 7's two-step shape and self-exclusions; feeds
items 22–23.

## G6 — DESTRUCTIVE OPERATIONS (throwaway accounts only)

Settles: Rules 4–8, registers A1, A2 (execution half), A3, A6; scenarios 3,
4, 5, 7, 8. **Extra care applies to every item here.** Disable locks an
account out; Remove ends roles irreversibly from this screen; merge
**permanently deletes an account**. Therefore, binding for the whole group:

- Every account acted on is a **throwaway this probe creates first**, in a
  **scratch context** minted via the scenario endpoints — the item states
  what it creates. Never a seeded account, never `publicknowledge`.
- Throwaways get unique mail addresses (standing setup) and, where sign-in
  must be checked, a password the probe knows.
- Before any merge confirm: re-read the two usernames on screen and verify
  **both** are this probe's own throwaways.

**17.** [scenario 3; Rules 4–5; fn-h] **Create first**: scratch journal +
throwaway user with a role in that journal only, known password. Open a
second browser session signed in as the throwaway. As **Journal Manager**:
choose "Disable User" — record the dialog title ("Disable {name}"), that it
lists the user's current roles, and the "Reason for disabling user" box;
type a reason; confirm. Record: the row's red disabled mark (closes item 5's
gap), the menu action now reading "Enable User", the throwaway's open
session ending at that moment (act in the second browser and record what it
shows), and a fresh sign-in attempt being refused (refusal wording is the
login feature's — record refusal only). Then "Enable User": record the
earlier reason **pre-filled**; confirm; record the throwaway signing in
again.
*Apps: OJS + OMP control of the dialog + label flip. Cross-app.* Settles:
Rules 4–5, the sessions-dropped side effect, scenario 3.

**18.** [fn-h open corner] **Create first**: throwaway user with **no roles**
in the scratch journal (create, then remove the role, or create role-less if
the endpoint allows). On the wizard's Users tab (role-less users are
reachable there via the no-roles filter), as **site administrator**, open
its Disable dialog and record what the description lists when the user has
no roles. Cancel or complete on the throwaway — either is safe; record which.
*Apps: OJS.* Settles: fn-h's "what the dialog lists for a user with no
roles".

**19.** [scenario 4; Rule 6; A1 silence; P8] **Create first**: throwaway
with one role in the scratch journal only. As **Journal Manager**: record
the throwaway's Mailpit box at zero; choose "Remove User"; record the
confirmation **verbatim** (draft: "Remove this user from this journal? This
action will unenroll the user from all roles within this journal.");
confirm. Record: what the list now shows for that user — row gone, or
present with an empty Roles cell (P8); Mailpit silence for the recipient
per the standing discipline (positive control: item 14's send or item 21's
role-end mail); the throwaway still signing in. Then re-add a role via the
user's edit page and record the Roles column showing a **fresh start date**
(today). Finally, on a **role-less throwaway** (item 18's, or this one
after a second Remove), attempt "Remove User" from the legacy list — where
role-less rows are reachable via its no-roles filter — and record the
"This user does not have any roles." answer.
*Apps: OJS+OMP+OPS (the confirm wording is per-app locale; the silence claim
is unmarked-all-apps). Cross-app.* Settles: Rule 6 end-to-end, scenario 4,
A1's silent half, P8.

**20.** [scenario 8; A1; Rule 8a] **Create first**: throwaway with **two
roles** in the scratch journal. As **Journal Manager**: open the row's Edit
page and end **one** role (the edit page's own confirmation applies — it is
U6's screen; record only this feature's outcome). Mailpit scoped to the
throwaway: record the role-ended email arriving. Back on the list: "Remove
User", confirm; record Mailpit **still showing only the one** role-ended
message afterwards (the remove added nothing), per the silence discipline.
*Apps: OJS + OPS control (OPS still sends the role-end mail per the draft,
despite OPS1). Cross-app.* Settles: register A1's demonstration, Rule 8a's
always-emails claim, scenario 8.

**21.** [P6; register A6; Rule 8b] **Create first**: throwaway with one
non-reviewer role in a scratch context, per app. As **manager** of that
context, open the user's edit page (the U6 roles table) and toggle the
role's masthead display. Record, in each app: what the screen says
(success, error, or nothing), whether the change **persisted** (reload the
page and record the toggle's state), and Mailpit for the masthead-change
notice (OJS: arrives; OMP/OPS: draft predicts an error after the change
sticks and **no** mail — establish the silence properly). On OJS
additionally: re-save the **same** choice and record that nothing changes
and no second mail arrives (silence discipline). On OJS or OMP, on a
reviewer-role row (stage via item 13's grant): record whether the masthead
toggle is offered at all, and if used, what the screen shows (draft: the
server refuses).
*Apps: OMP + OPS primary, OJS control — one agent, all three fleets.
Cross-app.* Settles: register A6 (the exact shape fn-a6 asks for), Rule 8b,
the masthead side effect, OPS1's sending half.

**22.** [scenario 5; Rule 7; P4; P5] **Create first**: TWO throwaway
accounts in a scratch journal — a duplicate (give it a role and some
observable history: item 14's email send to it leaves a trace; note what
history was staged) and a survivor with a different role, plus an open
browser session as the duplicate. As **site administrator**: "Merge user"
on the duplicate; pick the survivor; record the confirmation **verbatim**
and whether it states the old account will cease to exist (P5's on-screen
half); **verify both usernames are the probe's throwaways**; confirm.
Record: what the screen shows the moment the merge completes (P4 — do the
grids refresh, does the duplicate's row vanish); the duplicate's open
session ending (second browser); the duplicate's username refused at
sign-in; the survivor's row/edit page now holding the duplicate's role
**with its original start date and masthead choice**.
*Apps: OJS primary; OMP + OPS one full merge each on their own throwaway
pairs (the merge body diverges per app — the observable claims here are the
shared ones). Cross-app.* Settles: Rule 7, scenario 5, P4, P5, the
sessions-dropped side effect.

**23.** [A3, merge half; P3 execution] **Create first**: scratch journals
A and B; a throwaway duplicate holding roles in **both**; a throwaway
target in A. As **Journal Manager of A only**: choose "Merge user" on the
duplicate (record that the action is offered at all), pick the target, and
confirm — the server should refuse (the duplicate belongs to a context the
manager does not run). Record exactly what the screen does: an error
message (which words), a silent re-render of the target list, or anything
else. **Both accounts are throwaways; if the merge unexpectedly succeeds,
record that as the finding — nothing seeded is at risk.**
*Apps: OJS.* Settles: register A3's merge disagreement (fn-a3: context-
scoped offer vs context-free server check) and fn-j's "falls through"
prediction.

**24.** [scenario 7; A3, disable half] **Create first**: scratch journals
A and B; a throwaway user with a role in each. As **Journal Manager of A
only**: record that the user's row in A still offers "Disable User"; choose
it and confirm; record the refusal **as rendered** (draft expects wording
like "You do not have sufficient permissions to administer this user" —
unwatched). Then on the same row choose "Remove User" and confirm; record
that it succeeds (roles in A end; the throwaway still signs in and keeps
its role in B).
*Apps: OJS + OMP control. Cross-app.* Settles: register A3's disable half,
scenario 7, the PARTIAL-tolerated Remove contrast.

**25.** [A2, execution half — conditional] Runs **only if** the scenario
endpoints can mint a **throwaway site administrator**. If they can: as that
throwaway administrator, on the Hosted Journals Users tab, choose "Disable
User" on **their own row**, confirm with a reason, and record what happens —
locked out mid-session? refused? — then have the seeded administrator
re-enable the throwaway (cleanup). If no throwaway administrator can be
staged through sanctioned means, record that item 11's offer observation
stands alone and mark A2's execution half **unprobeable this round** in the
group report. **Under no circumstances is the seeded administrator's own
account disabled.**
*Apps: OJS.* Settles (conditionally): register A2's "can an administrator
really lock themselves out" question.

## G7 — Email-templates roster (cross-app)

**26.** [P7; register OPS1] As **manager** in each app: open Settings →
Workflow → Emails and search/browse for the role-ended notice and the
masthead-change notice templates. Record presence or absence of each row in
each app (draft: OPS lists neither; OJS lists both; OMP lists the role-end
one — its masthead template is absent from the install per A6). The screen
is U56's; record listing facts only, no judgments about that screen's other
behavior.
*Apps: OPS primary, OJS + OMP controls — one agent, all three fleets.
Cross-app.* Settles: register OPS1's listing half.

---

## Claims this list deliberately does not carry

- **ORCID mark on the Name cell** (Rule 1a): not stageable — no screen in a
  test install can attach an ORCID iD to an account (the same blocker U6
  queued for ORCID search). One line added to the deferred queue; the
  spec's wording stays cautious.
- **Username-suggestion gate breadth** (fn-n's "any signed-in user"): the
  Suggest button exists only on the administrator's account form; asking
  whether the endpoint answers for other roles means constructing a request
  no screen sends. Deferred queue; the spec makes no body claim on it.
- **`includePermissions` request parameter** (inventory §5, flagged "probe
  only if a claim depends on it"): no spec claim depends on it, and no
  screen varies it. Dropped, not deferred.
- **Sub-editor admission to the endRole/masthead operations** (fn-k): already
  live-probed under U6 (its A8); not re-probed here.
- **Subscription/payment transfer on merge** (Rule 7's {OJS}/{OMP} lines):
  staging a subscription and watching it move is a different feature's
  screens and budget; item 22 confirms the shared observable core, and the
  per-app transfer claims keep their code-reading footnote until that
  feature's probes reach them. Not deferred — screen-answerable later, just
  not in this feature's budget.
