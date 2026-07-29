# U53 Users management — claim check, chunk 1

**Frame.** QA documentation of an application's own screens, on a local disposable
test install with seeded accounts. Signed in as each role and used the screens the
way that role would — including typing a URL directly to reach one. Recorded what
the screen offers, what happens when it is used, and where the two disagree, so the
product team can fix it. Nothing here was produced by a request the screens
themselves would not send: browser only, no hand-built API calls, no altered form
payloads, no credentials carried between sessions. (The scratch fixtures were seeded
through the campaign's scenario endpoints, as the runbook prescribes.)

**Scope of this chunk.** The whole **Actors & permissions** section of
`docs/product/specs/users-management.md` (both surface bullets, the
administration-levels paragraph, all nine table rows) and the whole
**Reference — entry points & surfaces** table. Plus the assigned tension: whether
the hosted-contexts table "lists the administrator whether they are enrolled there
or not".

**Apps.** All three fleets: OJS `http://127.0.0.1:8000`, OMP `http://127.0.0.1:8100`,
OPS `http://127.0.0.1:8200`. Every verdict below is per-app unless it says otherwise.

**Fixtures (all created by me, prefix `u53cc1`).** Per app, two scratch contexts
`u53cc1-<app>-a` and `u53cc1-<app>-b`, seeded with:

| Account | What it is |
|---|---|
| `u53cc1.admin.<app>` | **site administrator holding no role in any context** (the new `users[].roles: ['siteAdmin']` fixture) |
| `u53cc1.mgr.<app>` | manager of context **a** only |
| `u53cc1.plain.<app>` | author in context **a** *and* in context **b** (the "belongs somewhere else" target) |
| `u53cc1.solo.<app>` | author in context **a** only (the fully-administered target) |
| `u53cc1.mgr2.<app>` | manager of context **b** only |

`publicknowledge`, its seeded roster and the install's `admin` were used **read-only**
(sign-in, list views, opening a row's menu / a dialog — never confirming an action on
them). Destructive steps ran only on `u53cc1.solo.<app>`. Mailpit untouched.

Locators are given for every control asserted about.

---

## Part 1 — the assigned tension (hosted list vs the administrator)

### CC1-01 — "every journal's Hosted Journals table lists the administrator whether they are enrolled there or not" (Rule 1, and the same statement inside A8)

**Verdict: WRONG** (all three apps).

**What I did.** Signed in as `u53cc1.admin.<app>` — a site administrator holding no
role in any context — and opened Administration → Hosted Journals/Presses/Servers
(`/index.php/index/admin/contexts`), expanded the scratch context's row
(`tr` containing the context path → `a.show_extras`), chose **Settings wizard** from
the revealed control row, and opened the **Users** tab (`button:has-text("Users")`).
The grid is `div[id^="component-grid-settings-user-usergrid"]`.

**What appeared.** The scratch context's Current Users table listed exactly four
people on every app — the install `admin`, and my three context-enrolled throwaways.
The administrator I was signed in as **did not appear at all**, on any app:

- OJS: `admin`, `u53cc1.mgr.ojs` (Journal manager), `u53cc1.plain.ojs` (Author), `u53cc1.solo.ojs` (Author)
- OMP: same shape, "Press manager"
- OPS: same shape, "Preprint Server manager"

It appears only when the filter is deliberately widened: opening the header's
**Search** link (`a:has-text("Search")` inside the grid) and searching
`u53cc1.admin` returns **No Items**; ticking **"Include users with no roles in this
journal."** (`input[name=includeNoRole]`) and searching again returns the row. That
checkbox is the screen's own way of asking for people who hold nothing here — the
default table does not carry them.

**Why the install's `admin` is there.** It holds a real manager role in each scratch
context, and both screens say so: the Users & Roles list shows it as "Journal
manager" / "Press manager" / "Preprint Server manager", and the hosted list's own
role filter (`select[name=userGroup]`, option "Journal manager" / "Press manager" /
"Preprint Server manager") returns the `admin` row on all three apps. The manager's
disable dialog for that row also reads "Current Roles : Journal manager" (etc.).
So the administrator that "appears everywhere" is an *enrolled* administrator; the
only thing peculiar about its hosted-list row is the **empty Roles cell** (its
enrolment carries no start date — the Users & Roles list shows the same role with a
blank Start Date).

**In plain product terms.** A journal's user table lists the people who hold a role
in that journal. A site administrator with no role there is in neither list —
neither the Users & Roles list nor the Hosted Journals one — unless the hosted
list's "Include users with no roles in this journal." box is ticked. The install's
`admin` shows up everywhere because every context creation enrols it as a manager,
not because administrators are listed unconditionally. What *is* peculiar is that
the hosted list leaves that administrator's Roles cell blank while the Users & Roles
list names the role (the surviving half of A8).

### CC1-02 — same question on the Users & Roles list

**Verdict: holds** (the spec does not claim otherwise; recorded because it settles
the tension symmetrically). On all three apps the scratch context's Users & Roles
list showed "Current Users (4)" without the signed-in role-less administrator;
searching its own name in the list's search field returned the three enrolled
throwaways and no administrator row.

---

## Part 2 — Actors & permissions: preamble and the two surface bullets

### CC1-03 — "The feature has **two surfaces** running on the same operations"

**Verdict: holds.** Both surfaces were driven on all three apps; the same accounts,
the same remove/disable/merge operations, reached from either. (Detail throughout
this report.)

### CC1-04 — "Settings → Users & Roles, 'Users' tab, the table headed 'Current Users' (it sits below the Invitations table…)"

**Verdict: holds** (all apps). As `u53cc1.mgr.<app>` the page reads, in order:
"Users / Roles / Site Access Options / ORCID" tabs, then "Invitations (0)" with
"Invite to a role", then "Current Users (4)" with the search field, then the table
(NAME, EMAIL, ROLES, START DATE, AFFILIATION, MORE ACTIONS).

### CC1-05 — "The screen answers at `management/settings/access` … and at the older second address `management/access` — the two addresses admit different people"

**Verdict: holds**, and the shape is sharper than the sentence suggests — worth
recording because chunk 1's Row 1 leans on it:

| Who | `management/settings/access` | `management/access` |
|---|---|---|
| Manager of the context (all apps) | opens the list | **refused** — "The current role does not have access to this operation." |
| Site administrator with **no** role in the context, OJS | opens the list | opens the list |
| Site administrator with **no** role in the context, OMP and OPS | **refused** — "The current role does not have access to this operation." | opens the list |

(Typed addresses, e.g. `http://127.0.0.1:8100/index.php/u53cc1-omp-a/en/management/settings/access`;
the refusal lands on `…/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`.)

### CC1-06 — the Hosted Journals bullet: collapsed expander, three actions, "Settings wizard" opens the settings pages, then the "Users" tab with its own filter and "Add User"

**Verdict: holds** (all three apps). The context row's only control is a link reading
**"Settings"** (`a.show_extras`); clicking it reveals a control row beneath it
reading exactly **"Edit  Remove  Settings wizard"**; "Settings wizard" leads to
`/index.php/index/en/admin/wizard/{contextId}`. Its **Users** tab holds a grid headed
"Current Users" with a **Search** link and an **Add User** link in the header, and
columns Given Name / Family Name / Username / Roles / Email. Revealed, the filter
holds `input[name=search]`, `select[name=userGroup]` (default "All Roles"),
`input[name=includeNoRole]` ("Include users with no roles in this journal.") and a
"Search" submit; it collapses again after each search.

### CC1-07 — "a stock install leaves the [password confirmation] window unset, and the prompt then never appears"

**Verdict: holds.** Across every administrator session in this run — three apps,
Hosted Journals, the settings wizard, the users grid, Add User, Edit User, disable
and enable — no "Confirm Access" page was ever interposed.

### CC1-08 — "These pages answer only at the site's own web address … typed after a journal's address instead, the page is refused — the journal's reader-facing site opens showing only 'Access denied.'"

**Verdict: holds** (all three apps). Typing
`/index.php/u53cc1-<app>-a/en/admin/wizard/{contextId}` (and the same under
`/publicknowledge/`) as the site administrator lands on
`…/user/authorizationDenied?message=user.authorization.accessDenied`: the context's
reader-facing site with "Home / Access denied." as its content.

### CC1-09 — "nobody administers a Site Administrator's account — not even another Site Administrator"

**Verdict: holds** (all three apps), and it is the *second administrator* half that
is new evidence — it needed the throwaway-administrator fixture.

- On the Hosted Journals users grid, as `u53cc1.admin.<app>`, choosing **Edit User**
  on the install `admin`'s row (`tr.row_controls:visible a:has-text("Edit User")`)
  opens a modal titled "Edit User" whose entire content is: "You do not have
  sufficient permissions to administer this user. In order to administer a user, you
  must either be site administrator, or administer all contexts that this user is
  enrolled in."
- On the Users & Roles list, as `u53cc1.admin.<app>`, the same account's row offers
  the full six-item menu, and **Disable User** opens the dialog "Disable admin admin
  / Current Roles : Journal manager" with that same refusal standing where the reason
  box and confirm button belong.
- The same refusal appears for a Journal Manager acting on that row (CC1-16).

### CC1-10 — "a Site Administrator fully administers everyone else"

**Verdict: holds** (all three apps). As `u53cc1.admin.<app>`, the row menu on
`u53cc1.plain.<app>` (a user with roles in two contexts, which blocks a manager)
offered all six actions: Edit, Email, Login As, Remove User, Disable User, Merge
user. On the legacy grid the same administrator could disable and re-enable a user
end-to-end (CC1-18).

### CC1-11 — "a Journal Manager fully administers a user only while that user's every role, site-wide, is in journals the manager runs; a user who also belongs to somewhere else on the site can only be partly administered — and the operations below that need *full* administration are refused for them"

**Verdict: holds** (all three apps). As `u53cc1.mgr.<app>`:

- `u53cc1.solo.<app>` (roles only here) → menu **["Edit","Email","Login As","Remove User","Disable User","Merge user"]**
- `u53cc1.plain.<app>` (also enrolled in context b) → menu **["Edit","Email","Remove User","Disable User"]**, and Disable User opens "Disable Plain U53cc1 / Current Roles : Author" carrying the refusal text quoted in CC1-09.

### CC1-12 — "Everyone always fully administers their own account"

**Verdict: undetermined.** Nothing on either surface exposes the level for one's own
account: the viewer's own row offers only Edit and Email on the Users & Roles list
(CC1-17), so the statement has no observable consequence there. The only own-row
control that could show it is the Hosted Journals list's own-row **Disable User**
(CC1-19), which I deliberately did not confirm — the only account I could have used
is the read-only install `admin`.

---

## Part 3 — Actors & permissions: the table, row by row

### CC1-13 — Row 1, first bullet: "**See and search the Users & Roles list** • Journal Manager — while their role keeps the 'Permit changes to Settings' option"

**Verdict: holds** for the positive half — as `u53cc1.mgr.<app>` the journal's
Settings menu carries "Users & Roles" and the list opens (all apps). The
*conditional* half (what happens when the option is removed) was **not** exercised:
it is a *Roles configuration* screen the spec itself delegates. Undetermined as a
conditional.

### CC1-14 — Row 1, second bullet: "Site Administrator — from the journal's menu, in every app, with no error along the way — **an administrator holding no role in any journal included**"

**Verdict: WRONG** (all three apps, in two separate ways).

**What I did.** Signed in as `u53cc1.admin.<app>` (no role anywhere) and tried to
reach the journal's Users & Roles the way the sentence describes — from the
journal's own menu — then by typed address.

**What appeared.**

1. **There is no menu.** `/index.php/u53cc1-<app>-a/en/dashboard` and
   `…/en/submissions` both redirect to the context's public site
   (`…/u53cc1-<app>-a/index`), and no page of that context offers a link to
   `management/settings/access` (`a[href*="management/settings/access"]` → 0
   matches, all apps). Even the Users & Roles page itself, once reached by typed
   address, renders with no editorial side menu — the OJS backend menu a manager sees
   ("Settings → Users & Roles") is absent for this administrator.
2. **On OMP and OPS the address itself is refused.** `management/settings/access`
   answers "The current role does not have access to this operation." for this
   administrator, in the scratch context *and* in `publicknowledge`. Only the older
   address `management/access` lets them in. On OJS both addresses open.

So on OJS the screen is reachable only by typing the address; on OMP and OPS the
address the sentence names refuses them outright, and only the older address works.
(The install `admin` sees none of this, because it holds a manager role in every
context — which is why the claim looked true before.)

### CC1-15 — Row 2: "**See and filter the Hosted Journals list** • Site Administrator only — no other role reaches Site Administration"

**Verdict: holds** (all three apps). As `u53cc1.mgr.<app>`, typing
`/index.php/index/admin/contexts` and `/index.php/index/admin/wizard/1` both land on
"The current role does not have access to this operation.", and no Administration
link exists anywhere in the manager's pages (`a[href*="/index/admin"]` → 0).

### CC1-16 — Row 3: "**Add a user directly** ('Add User') • Site Administrator — on the Hosted Journals list only. The Users & Roles list offers no way to create an account"

**Verdict: holds** (all three apps). The hosted list's users grid carries **Add User**
in its header, and it opens "Add User — Step #1: Fill in User Details". The Users &
Roles list, viewed as a manager, offers no "Add User" control at all (0 matches for a
button/link whose text is exactly "Add User"); the only creation-shaped affordance is
"Invite to a role", which belongs to *User invitations*.

### CC1-17 — Row 4, first bullet: "**Edit a user** • Journal Manager and Site Administrator — the Users & Roles row's 'Edit' leaves for the user's own edit page …; offered on every row, the actor's own included"

**Verdict: wrong in part** (the manager half holds everywhere; the administrator half
dead-ends on OMP and OPS).

- As `u53cc1.mgr.<app>`, "Edit" on a row leads to
  `/{ctx}/management/settings/user/{userId}` — the page headed "Users & Roles /
  Invite user to take a role", with the ROLE / START DATE / END DATE / JOURNAL
  MASTHEAD table, "Remove Role" and "Add Another Role". All three apps.
- The manager's **own** row offers exactly ["Edit","Email"] on all three apps, so
  "the actor's own included" holds for a manager.
- As `u53cc1.admin.<app>` (no role in the context), the row menu offers Edit on all
  three apps, but on **OMP and OPS** choosing it lands on "The current role does not
  have access to this operation." — the edit page refuses the very administrator the
  list just offered it to. On OJS it opens normally.
- "Offered on every row, the actor's own included" is also empty for that
  administrator in another sense: they have no own row, because a role-less
  administrator is not listed (CC1-01/02).

### CC1-18 — Row 4, second bullet: "Site Administrator — the Hosted Journals row's 'Edit User' opens the full account form"

**Verdict: holds** (all three apps). "Edit User" on `u53cc1.plain.<app>`'s row opens
the modal "Edit User — User Details" with Given Name*, Family Name, Preferred Public
Name, **Username shown as static text** (`u53cc1.plain.<app>`, no input field),
Contact Email*, and a Password block reading "Leave the pass…".

### CC1-19 — Row 5: "**Email a user** ('Email') • Journal Manager and Site Administrator — the one action with no target-based restriction: any row, their own included"

**Verdict: holds as an offer** (all three apps). Email is present in every row menu I
opened on both surfaces, including the manager's own row, the administrator's own row
(install `admin` viewing itself: ["Edit","Email"]), a cross-context user's row and an
administrator's row. Delivery was not exercised in this chunk.

### CC1-20 — Row 6, first bullet: "**Disable / Enable an account** • whoever fully administers the target — a Journal Manager choosing it on a Site Administrator, or on a user who belongs anywhere else on the site, is refused inside the dialog, before anything is confirmed"

**Verdict: holds** (all three apps). Evidence in CC1-09 and CC1-11: the dialog opens
titled "Disable {name}" with "Current Roles : …", and the refusal text replaces the
reason box and confirm button; "Close" is the only button.

### CC1-21 — Row 6, second bullet, first half: "offered on the Users & Roles list only — **the Hosted Journals list carries no per-row Disable for anyone**"

**Verdict: WRONG** (all three apps). The control is there, and it works.

**What I did.** As `u53cc1.admin.<app>`, on the Hosted Journals → Settings wizard →
Users grid, expanded a user row (`tr … a.show_extras`) and read the revealed control
row (`tr.row_controls:visible`).

**What appeared.** Every row's control row reads
**"Email  Edit User  Disable User  Remove  Login As  Merge User"** (Login As and
Merge User drop off rows the viewer cannot fully administer). Driving it end to end
on my throwaway `u53cc1.solo.<app>`, on each app:

- **Disable User** opens a modal "Disable User" with a "Reason for disabling user"
  box and the note "Please note that once a user is disabled, you won't be able to
  add them to any roles until the user is enabled again.", with Cancel / OK.
- Confirming with OK disables the account: the row's action flips to **"Enable"**
  ("Email Edit User Enable Login As Merge User").
- **Enable** opens "Enable — Reason for enabling user / Once the user is enabled,
  they will regain access to the site, and you'll be able to invite them to roles as
  needed." Confirming restores the row to "Disable User".

So disabling and enabling are **not** the Users & Roles list's alone: the Hosted
Journals surface offers the same pair of operations on every row, with its own dialog
wording (and, unlike the Users & Roles dialog, without a "Current Roles" listing).

### CC1-22 — Row 6, second bullet, second half: "and never on the viewer's own row, a Journal Manager's and a Site Administrator's alike"

**Verdict: holds on the Users & Roles list; WRONG on the Hosted Journals list.**

- Users & Roles: the manager's own row offers ["Edit","Email"] on all three apps; the
  install administrator viewing its own row in the same list also gets exactly
  ["Edit","Email"] on all three apps.
- Hosted Journals: signed in as the install `admin` and expanding **its own row** in
  the scratch context's users grid, the control row reads
  **"Email  Edit User  Disable User  Remove"** — Login As and Merge User are withheld
  on the own row, but **Disable User is offered on it**, on all three apps. I did not
  confirm it (the only own-row candidate available is the read-only install account),
  so what the dialog would do on self is not recorded here — only that the screen
  offers the action.

### CC1-23 — Row 7: "**Remove a user's roles here** • whoever at least partly administers the target — offered only while the target holds an active role in this journal"

**Verdict: holds** (all three apps, both surfaces).

As `u53cc1.mgr.<app>` on `u53cc1.solo.<app>`: menu before =
["Edit","Email","Login As","Remove User","Disable User","Merge user"]; "Remove User"
confirms with "Remove this user from this journal/press/server? This action will
unenroll the user from all roles within this journal/press/server." (OK / Cancel).
After confirming, the row remains (Roles and Start Date cells empty), "Current Users
(4)" is unchanged, and the menu is now
["Edit","Email","Login As","Disable User","Merge user"] — Remove User gone. On the
Hosted Journals grid the same row afterwards reads
"Email Edit User Disable User Login As Merge User" — no Remove. The
partly-administered half holds too: the manager keeps Remove User on
`u53cc1.plain.<app>`, whose second context blocks the full-administration actions
(CC1-11).

### CC1-24 — Row 8: "**Merge a user into another** • whoever fully administers the account being merged away … the Users & Roles list withholds the action on rows the viewer cannot fully administer … never offered on one's own row as the account to merge away; the target list, though, does offer the viewer's own account as the survivor"

**Verdict: holds** (all three apps).

- Withheld where full administration is missing: "Merge user" is absent from the
  manager's menu on `u53cc1.plain.<app>` and on the administrator's row, present on
  `u53cc1.solo.<app>` (CC1-11, CC1-16).
- Never on one's own row: the manager's own row and the administrator's own row both
  offer only ["Edit","Email"].
- The target list: choosing "Merge user" on `u53cc1.solo.<app>` opens a modal
  "Merge user — Merge into this User" listing users; expanding the **viewer's own**
  row there (`u53cc1.mgr.<app>`) reveals the single action **"Merge into this User"**.
  All three apps. I did not confirm any merge.

### CC1-25 — Row 9: "**End a single role / change masthead display** • driven from the user's edit page … The server also accepts them from a Section Editor, a role no screen lets in"

**Verdict: first half holds; second half not checkable from the screens.** The edit
page reached from a row's "Edit" carries the per-role table (ROLE / START DATE /
END DATE / JOURNAL MASTHEAD) with "Remove Role", "Add Another Role" and the masthead
choice ("Appear on the masthead" / "Does not appear on the masthead") — all three
apps. The Section Editor sentence describes what the server accepts where no screen
offers it; nothing on any screen can confirm or deny it, and this chunk did not
attempt it. (It is already recorded in *User invitations*' register, which the
sentence links.)

---

## Part 4 — Reference — entry points & surfaces

Every row was visited on all three apps. Nothing in the table is dead.

| # | Entry (spec row) | Verdict | What I saw |
|---|---|---|---|
| CC1-26 | Users & Roles screen (container) · `/{ctx}/management/settings/access` · `/{ctx}/management/access` · VUE-013 | **holds** | Both addresses render the screen; who each admits differs by app and role — see CC1-05. |
| CC1-27 | Users table (Vue) · Users & Roles → Users tab · VUE-051 | **holds** | "Current Users (n)" table with NAME / EMAIL / ROLES / START DATE / AFFILIATION / MORE ACTIONS. |
| CC1-28 | Search users (Vue) · table header · AFFM-102 | **holds** | Single field above the table, hint "Enter a user's name, role (e.g Journal editor), or affiliation" — identical on all three apps. |
| CC1-29 | Row Edit / Email / Remove / Enable-Disable / Merge (Vue) · row options menu · AFFM-103,104,106,107,108 | **holds** | Row options button `button[aria-haspopup=menu]` (its accessible name is the raw key `##userAccess.management.options##`); a fully administered row offers Edit, Email, Login As, Remove User, Disable User, Merge user on all three apps. |
| CC1-30 | Site wizard Users tab (legacy grid) · `/index/admin/wizard/{contextId}` · GRID-050 | **holds** | Reached from Hosted Journals → row → "Settings wizard"; the Users tab holds the grid. |
| CC1-31 | Add User / filter / row actions (legacy) · grid + rows · AFFM-203..208, 210 | **holds — nothing dead**; the *body text* is what is stale | AFFM-203 Add User → account form; AFFM-204 filter (search / role select / include-no-roles / Search) + paging line "1 - 4 of 4 items"; AFFM-205 Email; AFFM-206 Edit User → full account form; **AFFM-207 Enable/Disable renders on every row and works end to end** (CC1-21); AFFM-208 Remove; AFFM-210 Merge User → "Merge into this User" list. AFFM-209 (Login As) also renders and is deliberately outside the range, as on the Vue row. |
| CC1-32 | Users API (list, get, endRole, masthead; reviewers/report riders) · `/{ctx}/api/v1/users/...` · API-047 | **holds** | Typed `/{ctx}/api/v1/users` in the browser as an administrator returns the users JSON (`{"itemsMax":4,"items":[…]}`) on all three apps. |
| CC1-33 | Username suggestion (headless) · component call from the account form · GRID-003 | **holds** | The account form's "Suggest" button fills the Username field (Given "Zeta" + Family "Quux" → `zquux`) on all three apps. Nothing was saved. |
| CC1-34 | Welcome / role-ended / masthead emails · mail · MAIL-054, 056, 057 | **undetermined** | Not a reachable surface — bookkeeping rows; this chunk did not send or inspect mail. Worth a merge-agent glance: the atlas scopes MAIL-056/057 to `ojs omp`, while the spec body asserts the role-ended mail is still sent on OPS. |

---

## Summary

**Claims checked: 34.**

| Verdict | Count | IDs |
|---|---|---|
| holds | 26 | CC1-02, 03, 04, 05, 06, 07, 08, 09, 10, 11, 13, 15, 16, 18, 19, 20, 23, 24, 26, 27, 28, 29, 30, 31, 32, 33 |
| wrong | 5 | **CC1-01** (hosted list lists a non-enrolled administrator), **CC1-14** (site administrator reaches the list from the journal's menu, in every app, role-less included), **CC1-17** (partly — the row's Edit dead-ends for a role-less administrator on OMP/OPS), **CC1-21** (the Hosted Journals list carries no per-row Disable), **CC1-22** (partly — never on the viewer's own row) |
| unreachable | 0 | — |
| undetermined | 3 | CC1-12 (own-account administration level has no on-screen consequence), CC1-25 second half (a server-side acceptance no screen exercises), CC1-34 (mail rows are not surfaces) |

(CC1-13's conditional half is folded into "holds" for the part I could drive and
flagged as undetermined in its entry; it is not counted twice.)

**The three findings a merge agent should not miss**

1. **The hosted list does not list unenrolled administrators.** A site administrator
   with no role in a context appears in *neither* of that context's user lists; the
   install `admin` appears because context creation enrols it as a manager. The
   surviving oddity is the blank Roles cell (and blank start date) for that
   administrator's row.
2. **The Hosted Journals list does have a per-row Disable/Enable**, on every row
   including the viewer's own, in all three apps, and it completes: reason dialog →
   OK → the row's action flips to "Enable" → Enable restores it. Two places in the
   Actors table say the opposite.
3. **A site administrator holding no role in a context cannot get to Users & Roles
   the way the spec describes**: there is no menu path in any app, and on OMP and OPS
   the `management/settings/access` address refuses them outright (only the older
   `management/access` works) — and there the row's "Edit" is offered but dead-ends
   on a refusal.

**Blockers:** none. Everything in the chunk was checkable through the screens on all
three fleets.

**Leftover fixtures** (safe to keep or delete; all mine): contexts
`u53cc1-{ojs,omp,ops}-{a,b}` and accounts `u53cc1.*` on each fleet.
`u53cc1.solo.<app>` was left **enabled** with its context roles removed;
`publicknowledge`, the seeded roster and the install `admin` are unchanged.
