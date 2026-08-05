# U53 — Users management · probe report A (groups G1 + G2)

**Frame.** QA documentation of an application's own screens, on a local disposable
test install with seeded accounts. Signed in as each role and used the screens the
way that role would — including typing a URL directly to reach one. Recorded what
the screen offers, what happens when it is used, and where the two disagree.
Nothing here constructs a request the screens themselves would not send.

**Agent**: probe A (Opus). **Date**: 2026-07-28.
**Items owned**: probe-list G1 (1–4) and G2 (5–9) — 9 items, all executed.
**Apps driven**: OJS `127.0.0.1:8000` · OMP `127.0.0.1:8100` · OPS `127.0.0.1:8200`
(all three fleets driven by this one agent; no item split by app).
**Driver**: Chromium via Playwright, real UI sign-in each time
(`/index.php/index/en/login`, `input#username` / `input#password` / `form#login button`).
No API client, no hand-built request, no carried credentials.

Every observation is tagged **claim** (settles part of an item; promotable to an
assertion) or **context** (incidental DOM/state; NOT promotable).

## Fixtures this probe created (namespace `u53pa`)

Created through the scenario endpoints only; nothing seeded or `publicknowledge`
was mutated. Mailpit never cleared.

| App | Scratch context | Users |
|---|---|---|
| OJS | `u53paojs` — "U53 Probe Alpha OJS" (contextId 3) | `u53pa.mgr` (manager, Mandy Manager, `u53pa-mgr-ojs@mail.test`, affiliation "Probe Alpha Lab") + 28 throwaways `u53pa.u00`…`u53pa.u27` (family names `Probealpha00`…`Probealpha27`; roles cycling sectionEditor/author/reader/copyeditor/externalReviewer; affiliations cycling "Zeppelin Institute"/"Marlowe College"/"Zeppelin Institute"/"Kowalski University") |
| OJS | `u53paojs2` — "U53 Probe Alpha OJS Two" (contextId 9) | `u53pa.mgr2` (manager) + `u53pa.two` (Tara Tworole, **two** roles: Author + Reader) — staged only for item 5's ended-role look |
| OMP | `u53paomp` (contextId 3) | `u53pa.mgr` + 6 throwaways |
| OPS | `u53paops` (contextId 3) | `u53pa.mgr` + 6 throwaways |

The only mutation this probe performed on any account: **item 5** ended the Author
role of `u53pa.two` (own throwaway, own scratch journal) from the user's edit page,
to observe how the Roles column renders an ended role. Nothing was disabled,
removed or merged.

---

# G1 — Doors & typed addresses

## Item 1 — Does the left menu show Settings → Users & Roles for a site administrator?

**Apps**: OJS + OMP + OPS. **Role**: seeded site administrator (`admin`).
**Screen**: the context backend, `/index.php/publicknowledge/submissions`
(lands on `…/en/dashboard/editorial?currentViewId=assigned-to-me`).

**Locators**: `nav[aria-label="Site Navigation"]`; the Settings group is a
collapsed `getByRole('button', {name: 'Settings'})`; the target is
`getByRole('link', {name: 'Users & Roles', exact: true})`.

**claim — the menu entry exists in all three apps.** In the collapsed state the
"Users & Roles" link is present in the DOM but not visible (`count: 1`,
`isVisible(): false`); after clicking the "Settings" nav button it becomes
visible in OJS, OMP and OPS alike. The Settings group renders, verbatim and in
this order:

- OJS: `Settings` → `Journal`, `Website`, `Workflow`, `Distribution`, **`Users & Roles`**
- OMP: `Settings` → `Press`, `Website`, `Workflow`, `Distribution`, **`Users & Roles`**
- OPS: `Settings` → `Server`, `Website`, `Workflow`, `Distribution`, **`Users & Roles`**

The link's `href` is `…/index.php/publicknowledge/en/management/settings/access`
in all three.

**claim — clicking it lands on the working screen in all three apps, with no
error dialog.** Final URL `…/management/settings/access`; `<h1>` = `Users & Roles`;
`<title>` = `Users & Roles | Journal of Public Knowledge` (OMP:
`… | Public Knowledge Press`; OPS: `… | Public Knowledge Preprint Server`).
The page renders the tab strip `Users`  `Roles`  `Site Access Options`  `ORCID`,
the Invitations table, and the table headed **`Current Users (18)`** (OPS: `(9)`).
`page.locator('[role=dialog]')` count = **0** in every app, and no JS dialog
fired (`page.on('dialog')` never triggered).

**claim — the same holds in a scratch context.** Repeated in `u53paojs`,
`u53paomp`, `u53paops`: the menu entry is present and clicking it lands on
`…/{scratch}/management/settings/access` showing `Current Users (30)` / `(8)` /
`(8)`. No dialog in any app.

**Settles**: Actors row 1's site-admin clause and fn-a's menu-visibility question.
**Verdict: CORRECTS the draft.** The draft's Actors table says the Site
Administrator reaches the list "on OJS from the menu; on OMP and OPS only through
the older address, under an error dialog they must dismiss". Observed: on OMP and
OPS the menu entry is present, the link works, and **no error dialog appears**.

**context (qualifier the digest should carry).** The `admin` account is not a
pure site administrator in these installs: on the Users & Roles list its own row
shows the Roles cell "Journal manager" (OMP "Press manager", OPS "Preprint Server
manager") — see item 5's admin-row observation and item 4's CSV, whose first data
row reads `1,admin,admin,admin@example.org,,,,"2026-07-28 20:55:59",,Yes,No,No,No,No`
(the `Yes` is the manager column). So this observation cannot separate "reaches
it because site admin" from "reaches it because also a manager". The scratch-context
repeat does not separate them either — `admin` is listed in the scratch contexts'
Current Users with the same manager label. **The one observation that would
separate them**: a site administrator holding no manager group anywhere, opening
a context backend on OMP/OPS. Staging that needs an administrator account the
scenario endpoints do not currently mint (same blocker as probe-list item 25).

## Item 2 — Administration → Hosted Journals → context settings → Users tab

**Apps**: OJS + OMP + OPS. **Role**: `admin`. Read-only throughout.

**Path driven (screen clicks only)**: nav `Administration` button →
`/index.php/index/en/admin` → link "Hosted Journals" (OMP "Hosted Presses",
OPS "Hosted Servers") → `/index.php/index/en/admin/contexts` → on the
`publicknowledge` row, the row-expander `a.show_extras` (screen-reader text
"Settings") → the revealed link `a[href$="/admin/wizard/1"]` (accessible text
"Settings wizard") → `/index.php/index/en/admin/wizard/1` → tab button
`button#users-button` ("Users").

**claim — no password re-confirmation prompt appears anywhere on that path**, in
any of the three apps. `input[type=password]` count = 0 on `/admin`, on
`/admin/contexts`, on `/admin/wizard/1` and on its Users tab; no dialog fired;
no interstitial page was rendered.

**context — why, and what would settle it.** The prompt is gated by
`security.password_timeout` in the server configuration; that key is absent
(commented out) from all three fleets' `config.test.inc.php`, so re-authentication
is never required in this install and the administrator's session is always
treated as elevated. The observation therefore records *this install's* behaviour,
not a defect. **The one observation that would settle fn-b's re-auth claim**: the
same path walked on an install with `security.password_timeout` set above zero
and the elevation window expired. Out of reach here — changing the shared fleets'
config would disturb three sibling agents.

Note for the digest: this also bears on the spec's Settings section, which says
"No server-configuration file setting alters this feature's behavior" — a
configuration key does gate the prompt on the way into this feature's second
surface.

**claim — the tab shows a table headed "Current Users".** On
`/index.php/index/en/admin/wizard/1#users` in all three apps, the tab content
opens with the heading **`Current Users`** followed by a control row rendering as
`Search` and `Add User`, then the column headers, verbatim and in order:

```
Given Name   Family Name   Username   Roles   Email
```

and a footer reading `Items per page:` with options `10 25 50 75 100` and
`1 - 18 of 18 items` (OJS/OMP; OPS shows its own smaller roster).

Top-level wizard tabs are `Journal Settings | Plugins | Users` (OJS),
`Setup | Plugins | Users` (OMP), `Server Settings | Plugins | Users` (OPS) —
`div.pkpTabs__buttons[role=tablist]`, buttons `#setup-button` / `#context-button`,
`#plugins-button`, `#users-button`.

**claim (cross-list divergence, cross-app) — the two lists disagree about the
administrator's roles.** For the same account in the same context:

| Screen | `admin`'s Roles cell |
|---|---|
| Hosted Journals Users tab (legacy grid) | *empty* — row text `admin \| admin \| admin \| \| admin@example.org` |
| Users & Roles list (Vue) | `Journal manager` (OMP `Press manager`, OPS `Preprint Server manager`) |

Verified in all three apps. The Vue row also shows an **empty Start Date** for
that role, and the item-4 CSV independently reports `admin` as holding the
manager role. Bears on Rule 1's "Nothing a manager does on one list is invisible
on the other: they read the same accounts".

**context** — the `Settings` link on a Hosted-Journals row is not reachable
without first clicking the row's expander: `a[href$="/admin/wizard/1"]` resolves
in the DOM but is `display: none` until `a.show_extras` on that row is clicked.
Relevant to whoever writes the test.

**context** — the wizard's Users tab rendered only `Search` and `Add User` above
the table in this pass; the role dropdown and "Include users with no roles"
checkbox were not visible without opening the filter. That surface is item 10's
(G3) — recorded here only so the digest does not read this item as contradicting it.

## Item 3 — The wizard address typed after a journal's address vs the site's

**Apps**: OJS + OMP + OPS. **Role**: `admin`. Both addresses typed into the
address bar (`page.goto`), nothing clicked.

**claim — typed after the journal's own address, the page is refused.**
Typed: `http://127.0.0.1:8000/index.php/publicknowledge/admin/wizard/1`
(and the 8100/8200 equivalents).

- HTTP status 200, but the browser ends at
  `http://127.0.0.1:8000/index.php/publicknowledge/en/user/authorizationDenied?message=user.authorization.accessDenied`
- `<title>` = `| Journal of Public Knowledge` (OMP `| Public Knowledge Press`,
  OPS `| Public Knowledge Preprint Server`) — note the empty title segment
  before the pipe.
- The page rendered is the **reader-facing journal site** (masthead, `Current
  Archives Announcements About`, Search), with the breadcrumb `Home /` and the
  body text, verbatim:

  > `Access denied.`

Identical in all three apps.

**claim — typed at the site's own address, it answers.**
Typed: `http://127.0.0.1:8000/index.php/index/admin/wizard/1` → redirects to
`…/index/en/admin/wizard/1`, `<title>` = `Settings Wizard | Open Journal Systems`
(OMP `… | Open Monograph Press`, OPS `… | Open Preprint Systems`), breadcrumb
`Administration / Hosted Journals / Settings Wizard`, heading `Settings Wizard`,
tab strip `Journal Settings | Plugins | Users`. Identical in OMP and OPS with
their own vocabulary.

**Settles**: the Actors section's "answer only at the site's own web address" —
**CONFIRMED**, and fn-b's unwatched refusal shape is now watched: it is a
redirect to a reader-facing "Access denied." page, not an in-place error.

## Item 4 — The users spreadsheet at a typed address

**Apps**: OJS + OMP + OPS. Address typed into the address bar:
`http://127.0.0.1:<port>/index.php/publicknowledge/api/v1/users/report`.

**claim — as the seeded Journal Manager (`manager.maya`), a file downloads.**
In all three apps the navigation is interrupted by a download
(`page.goto` reports "Download is starting"); the browser's suggested filename is,
verbatim:

```
user-report-2026-07-28.csv
```

The file is a UTF-8-BOM CSV whose header row is a fixed identity block followed by
one column per role of that app:

- OJS: `ID,"Given Name","Family Name",Email,Phone,Country,"Mailing Address","Date registered",Updated,"Journal manager","Journal editor","Production editor","Section editor","Guest editor",Copyeditor,Designer,"Funding coordinator",Indexer,"Layout Editor","Marketing and sales coordinator",Proofreader,Author,…`
- OMP: `…,"Press manager","Press editor","Production editor","Series editor",Copyeditor,Designer,"Funding coordinator",Indexer,"Layout Editor","Marketing and sales coordinator",Proofreader,Author,"Volume editor","Ch…`
- OPS: `ID,"Given Name","Family Name",Email,Phone,Country,"Mailing Address","Date registered",Updated,"Preprint Server manager",Moderator,Author,Reader,"Editorial Board Member"`

Data rows carry `Yes`/`No` per role column, e.g. OPS row 1:
`1,admin,admin,admin@example.org,,,,"2026-07-28 20:55:59",,Yes,No,No,No,No`.

**claim — as a seeded role the lists do not admit, it is refused.** Signed in as
`author.alex` and, separately, `reader.rosa`, the same typed address returns
**HTTP 401**, `content-type: application/json`, no download, and the browser
displays the raw JSON body, verbatim:

```
{"error":"user.authorization.roleBasedAccessDenied","errorMessage":"The current role does not have access to this operation."}
```

Identical in all three apps for both roles (6 combinations).

**claim — the "no door" half.** Sweeping the rendered pages this probe visited
for the string `users/report`:

| Screen | mentions `users/report` |
|---|---|
| Users & Roles list (`/{ctx}/management/settings/access`), OJS/OMP/OPS | **no** |
| Hosted Journals wizard Users tab (`/index/en/admin/wizard/1#users`), OJS/OMP/OPS | **no** |

(`await page.content()` full-HTML substring check, after the Vue list had loaded.)

**Settles**: Rule 9 and register A7 — **CONFIRMED** on the reachability half
(manager gets the CSV, author/reader are refused) and on the no-door half for the
two screens this probe covers. A7's "for the same roles the lists admit" is
confirmed for manager-yes / author-no / reader-no; a sub-editor was not tested.

---

# G2 — Users & Roles list: read-only looks

All G2 items were driven as **`u53pa.mgr`** (Journal/Press/Preprint Server Manager
of the probe's own scratch context), at
`/index.php/u53pa<app>/management/settings/access`.
The users table is `page.locator('table').nth(1)` on that page — table 0 is the
Invitations table (User invitations' surface), table 2 is the Roles tab's table.

## Item 5 — What the list shows

**Apps**: OJS primary, OMP + OPS one-look control.

**claim — heading carries a live count.** `<h3>` text, verbatim:
`Current Users (30)` on OJS's scratch journal (29 seeded throwaways + `admin`),
`Current Users (8)` on OMP's and OPS's. The count tracks the current filter:
after searching it read `Current Users (1)`, `Current Users (5)`,
`Current Users (14)`, `Current Users (0)` (item 6).

**claim — the column set, verbatim from the `<thead>`** (identical in OJS, OMP
and OPS; the all-caps rendering is CSS `uppercase`, the DOM text is title case):

```
Name | Email | Roles | Start Date | Affiliation | <sr-only>More Actions</sr-only>
```

The sixth header is `<span class="sr-only">More Actions</span>` — a translated
string, not a raw key (contrast item 8).

**claim — Roles and Start Date render one entry per role, aligned.** For a
two-role user the cells are, verbatim HTML:

```html
<td …><div class="flex flex-col">Author</div><div class="flex flex-col">Reader</div></td>
<td …><div class="flex flex-col">2026-07-28</div><div class="flex flex-col">2026-07-28</div></td>
```

**claim — ended roles are not shown in the Roles column.** Staged on the probe's
own throwaway `u53pa.two` (Author + Reader) in the probe's own scratch journal
`u53paojs2`: on the user's edit page the Author row's "Remove Role" was confirmed
(that page and its dialog are *User invitations*' surface; recorded here only for
its effect on this list). The edit page then showed
`Author | 2026-07-28 | 2026-07-28 | … | User Removed From Role` and
`Reader | 2026-07-28 | --- | … | Remove Role`. Back on the Users & Roles list the
same row reads:

```
Tara Tworole | u53pa-two-ojs@mail.test | Reader | 2026-07-28 | Dualrole Institute
```

**context (rendering artifact worth one line).** The ended role leaves an **empty
div in place** rather than disappearing from the markup — the cells after the
removal are:

```html
<td …><div class="flex flex-col"></div><div class="flex flex-col">Reader</div></td>
<td …><div class="flex flex-col"></div><div class="flex flex-col">2026-07-28</div></td>
```

i.e. a blank slot above the surviving role in both the Roles and Start Date cells.
Incidental DOM — not promotable as an assertion, but it is what a manager would
see as a blank first line in those two cells.

**claim — 25 users per page, with pagination.** With 30 users in the scratch
journal: page 1 renders **25** `tbody tr`, page 2 renders **5**
(first/last names on page 1: `admin admin` … `Wanda Probealpha22`; page 2:
`Xenia Probealpha23` … `Bianca Probealpha27`). The pagination control is
`nav.pkpPagination[role="navigation"][aria-label="View additional pages"]`,
containing buttons whose visible labels are `Previous`, `1`, `2`, `Next` and
whose `aria-label`s are `Go to Previous`, `Go to Page 1`, `Go to Page 2`; the
current page carries `aria-current="true"`; `Previous` is `disabled` on page 1.
With 8 users (OMP/OPS scratch) no pagination element is rendered at all.

**Settles**: Rule 1a and fn-c's column claims — **CONFIRMED** for the heading,
the column set, the 25-per-page split, the pagination control, and "ended roles
are not shown". The ORCID mark and the red disabled mark were not staged here
(deferred / item 17 respectively), so Rule 1a's Name-cell marks stay unwatched by
this probe.

**context — the administrator appears on the list of a context it was not
enrolled in by the probe.** In every scratch context created for this probe,
`admin` is listed with the Roles cell `Journal manager` / `Press manager` /
`Preprint Server manager` and an **empty Start Date**; see item 2's cross-list
divergence claim.

## Item 6 — How the search behaves

**Apps**: OJS primary; OMP and OPS both run as controls (the item asked for OMP
only — OPS was cheap and is included).

**Locator**: `input[placeholder^="Enter a user's name"]`, i.e.
`input#v-15[type=search].pkpSearch__input` (OPS: `#v-13`).

**claim — the list does NOT narrow as you type.** Typing `Bruno` character by
character (`pressSequentially`, 100–120 ms per key) and then waiting **5.5 s**
with no further interaction leaves the list unchanged: heading still
`Current Users (30)`, still 25 rows, pagination still `Previous 1 2 Next`.
No request to the users endpoint is issued while typing.

**claim — the search runs on Enter.** Pressing `Enter` in the field fires the
fetch and the list narrows: heading becomes `Current Users (1)`, one row,
`Bruno Probealpha01`, pagination gone. Same in OMP and OPS.

**claim — there is no Search button.** The field's container is
`div.pkpSearch > label`, holding a `-screenReader` span with the hint text, the
`input[type=search]`, and `span.pkpSearch__icons` whose only child is a
non-interactive `<span>` wrapping a magnifier `<svg>` — no `<button>`, no
`role=button`, nothing clickable. Verified identical in all three apps.

**claim — search matches names, role labels and affiliation.** On OJS's scratch
journal (30 users): `Bruno` → `Current Users (1)`; `Copyeditor` →
`Current Users (5)` (`Dmitri Probealpha03`, `Ivana Probealpha08`,
`Nils Probealpha13`, `Sonia Probealpha18`, `Xenia Probealpha23` — exactly the
throwaways enrolled as copyeditor); `Zeppelin` → `Current Users (14)` (exactly
the throwaways with affiliation "Zeppelin Institute"). Clearing the field and
pressing Enter restores `Current Users (30)`.

**claim — the same on the controls.** OMP: `Bruno` → `(1)`, `Copyeditor` → `(1)`,
`Zeppelin` → `(3)`. OPS: `Bruno` → `(1)`, `Zeppelin` → `(3)`, and
**`Copyeditor` → `Current Users (0)`** with the table body rendering a single row
reading, verbatim, `No Items` — consistent with OPS shipping no copyeditor group.

**claim — searching resets the list to its first page.** On OJS, clicked
`Go to Page 2` (list showed 5 rows, `aria-current` on "Go to Page 2"), then typed
`Probealpha` and pressed Enter: the list returned to **page 1** —
`Current Users (28)`, 25 rows, `aria-current="true"` back on `Go to Page 1`,
first row `Ada Probealpha00`.

**Settles**: Rule 2's search claims (fn-c). **Verdict: PARTLY CORRECTS the draft.**
"matches … against names, roles and affiliations" — **confirmed**. "resets the
list to its first page" — **confirmed**. "**matches as you type**" — **corrected**:
the list does not react to typing at all; the search runs only when Enter is
pressed, and no button is offered as an alternative.

## Item 7 — The search field's hint text, verbatim

**Apps**: OJS + OMP + OPS.

**claim.** In all three apps the field carries the same string twice — as the
`placeholder` attribute and as the visually hidden `<label>` text
(`span.-screenReader` inside the label, which is therefore the field's accessible
name). Verbatim, byte for byte identical across OJS, OMP and OPS:

```
Enter a user's name, role (e.g Journal editor), or affiliation
```

Note `e.g` without a trailing period, and `Journal editor` on the press and the
preprint server.

**Settles**: register A5 — **CONFIRMED**, both halves (the "Journal editor"
example on OMP/OPS, and the missing period after "e.g").

## Item 8 — The row-options menu's accessible name

**Apps**: OJS + OMP + OPS.

**Locator**: the last cell's button in a users-table row —
`table.nth(1) tbody tr td:last-child button`, a
`button[aria-haspopup="menu"][id^="headlessui-menu-button-"]` whose only visible
content is a three-dot `<svg>` marked `aria-hidden="true"`.

**claim.** The button's accessible name comes solely from its `aria-label`, and
that attribute reads, verbatim, in **every row and in all three apps**:

```
##userAccess.management.options##
```

Checked on all 25 buttons on OJS's page 1 and all 8 on OMP and OPS — every one
identical. The doubled-hash wrapper is this application's rendering for a locale
key that resolves to nothing, and it is what a screen reader is handed as the
button's name. (The same `##…##` form appears elsewhere on these pages — the
header's help control reads `##common.help##` — so the marker, not just the bare
key, is the shipped rendering.)

**Settles**: register A4 — **CONFIRMED**, and sharpened: the draft predicted "the
raw key `userAccess.management.options`"; what is actually exposed is the key
wrapped in `##…##`. Contrast the same table's sixth column header, which is a
properly translated `sr-only` "More Actions" — so the defect is specific to this
one control.

## Item 9 — What the manager's own row offers

**Apps**: OJS + OMP + OPS. **Role**: `u53pa.mgr`, viewing their own row
(`Mandy Manager`, `u53pa-mgr-<app>@mail.test`).

**Locator**: the row's `td:last-child button` (item 8's button); the opened panel
is `[role=menu]`, its entries `[role=menuitem]`.

**claim — the manager's own row offers exactly two actions**, in this order, in
all three apps:

```
Edit
Email
```

**claim — a different user's row offers six**, in this order, in all three apps
(control taken on `u53pa.u00`, a throwaway with one role in the same scratch
context):

```
Edit
Email
Login As
Remove User
Disable User
Merge user
```

Note the casing as rendered: `Remove User`, `Disable User`, but `Merge user`
(lower-case *u*) and `Login As`.

**Settles**: Rule 1c's Vue-side withholding and the Actors rows for Edit/Email —
**CONFIRMED exactly as drafted**: Edit and Email appear on the actor's own row;
Remove, Disable, Merge (and Login As) are withheld there and present on other
rows.

---

## Coverage summary

| Item | Apps driven | Outcome |
|---|---|---|
| 1 | OJS, OMP, OPS (+ scratch repeat in each) | settled — **corrects** the draft's OMP/OPS "error dialog" clause; carries one qualifier (admin also holds a manager role) |
| 2 | OJS, OMP, OPS | table/heading settled; **re-auth prompt half undetermined** in this install (config key absent) — plus one new cross-list divergence |
| 3 | OJS, OMP, OPS | settled — confirms the refusal, and watches its shape |
| 4 | OJS, OMP, OPS × 3 roles | settled — confirms Rule 9 / A7 |
| 5 | OJS (+ OMP, OPS control) | settled, including the ended-role look |
| 6 | OJS, OMP, OPS | settled — **corrects** "as you type" |
| 7 | OJS, OMP, OPS | settled — confirms A5 |
| 8 | OJS, OMP, OPS | settled — confirms A4, sharpened |
| 9 | OJS, OMP, OPS | settled — confirms Rule 1c |

**9 of 9 items executed. 1 half-item undetermined** (item 2's re-authentication
prompt). Nothing blocked.

## Undetermined — and the one observation that would settle each

1. **Item 2, the re-authentication prompt.** No prompt exists in these installs
   because `security.password_timeout` is unset in all three fleets'
   configuration. *Settling observation*: walk Administration → Hosted Journals →
   a context's Settings wizard on an install where that key is set above zero,
   after the elevation window has lapsed, and record the prompt's wording.
2. **Item 1, isolating the site-administrator clause.** The seeded `admin` also
   holds the manager role in every context observed, so the menu's visibility
   cannot be attributed to the site-admin role alone. *Settling observation*: the
   same menu look, on OMP and OPS, as an administrator account that holds no
   manager group in any context.

## Incidental (outside G1/G2 — one line each, not investigated)

- The Hosted Journals wizard's Users tab shows the account's Roles cell **empty**
  for `admin`, while the Users & Roles list shows a manager role for the same
  account — all three apps (recorded in full under item 2; the legacy list's own
  columns and filter are item 10's).
- The wizard Users tab's footer offers `Items per page: 10 25 50 75 100` and
  `1 - 18 of 18 items` — a per-page selector the Vue list does not have.
- The page reached by the refused typed wizard address has an empty `<title>`
  segment: `| Journal of Public Knowledge`.
- The backend header's help control exposes `##common.help##` on every backend
  page in all three apps — same missing-key class as A4 but a different feature's
  surface.
- On the Hosted Journals list, a row's "Settings wizard" link is `display:none`
  until the row's expander (`a.show_extras`, screen-reader text "Settings") is
  clicked — noted for whoever writes the test.
- Adding a role from the user's edit page does not add it directly: it advances
  to "STEP 2 - Modify email shared with the user" and ends in "Invite user to the
  role" (that page belongs to *User invitations*).

## Proposed content for files this probe must not edit

None. This probe proposes no PROGRESS row, atlas marker or `app-changes.md`
entry: nothing here was an app defect that blocked a test run, and no test was
written.
