# U53 — Users management · probe report D (Group G6, items 17–25)

RUNBOOK step 3. **Group G6 — destructive operations.** Facts only, for the digest
agent and for later archaeology. Each observation is marked **claim** (a
deliberate, repeated observation of the thing the item asks about) or
**context** (incidental, not promotable to an assertion).

Frame: QA documentation of the applications' own screens on a local disposable
test install. Every action below was driven as a signed-in user through the
application's own screens (Playwright-driven Chromium, real clicks, real form
fills, typed URLs where an item calls for one). No hand-built API calls against
product endpoints; the only non-browser traffic was the sanctioned
`/api/v1/_test/scenarios/*` seeding used to create this probe's own throwaway
accounts and scratch contexts, and read-only Mailpit search.

Date of run: 2026-07-28 → 2026-07-29 (the run crossed local midnight; both dates
appear in start-date observations and that is why).

---

## 0. Safety statement, staging and namespacing

**No seeded account was disabled, removed, merged or otherwise mutated. No
action of any kind was taken on `publicknowledge` or on any of its seeded
users.** The seeded `admin` account was used only to *sign in and act on this
probe's own throwaways* (item 22 requires a site administrator as the actor);
`admin`'s own row was never chosen for Disable, Remove or Merge.

Every account acted on was created by this probe, in scratch contexts created by
this probe, all under the reserved `u53pd` prefix.

### Scratch contexts created (all via `POST /api/v1/_test/scenarios/context`)

| App | urlPath / tag | contextId | Name |
|---|---|---|---|
| OJS | `u53pdojsa` | 5 | U53 Probe D Journal A |
| OJS | `u53pdojsb` | 6 | U53 Probe D Journal B |
| OJS | `u53pdojsc` | 10 | U53 Probe D Journal C |
| OMP | `u53pdompa` | 5 | U53 Probe D Press A |
| OMP | `u53pdompb` | 6 | U53 Probe D Press B |
| OPS | `u53pdopsa` | 4 | U53 Probe D Server A |

### Throwaway accounts created (username → role(s) → mail address)

OJS journal A: `u53pdmgra` (Journal manager), `u53pd17o` (Section editor),
`u53pd18o` (Reader → later role-less → later Reviewer), `u53pd19o` (Section
editor), `u53pd20o` (Section editor + Reader), `u53pd21o` (Section editor),
`u53pd22od` (Section editor), `u53pd22os` (Copyeditor), `u53pd23d` (Section
editor in A **and** Copyeditor in B), `u53pd23t` (Copyeditor), `u53pd24o`
(Section editor in A **and** Copyeditor in B), `u53pdctlo` (Reader — the
positive-control mail recipient).
OJS journal B: `u53pdmgrb` (Journal manager).
OJS journal C: `u53pdmgrc` (Journal manager), `u53pd22cd` (Section editor),
`u53pd22cs` (Copyeditor).

OMP press A: `u53pdmmgra` (Press manager), `u53pdm17`, `u53pdm19`, `u53pdm21`,
`u53pdm22d` (all Series editor), `u53pdm22s` (Copyeditor), `u53pdm24` (Series
editor in A **and** Copyeditor in B), `u53pdmctl` (Reader — positive control).
OMP press B: `u53pdmmgrb` (Press manager).

OPS server A: `u53pdpmgra` (Server manager), `u53pdp19` (Moderator), `u53pdp20`
(Moderator + Reader), `u53pdp21` (Moderator), `u53pdp22d` (Moderator),
`u53pdp22s` (Author), `u53pdpctl` (Reader — positive control).

Every address is `<username>@mail.test`, unique across the three fleets, so
Mailpit scoping by recipient is unambiguous. **Mailpit was never cleared.**

### Accounts permanently deleted by this probe (merge, item 22)

`u53pd22od`, `u53pd22cd` (OJS), `u53pdm22d` (OMP), `u53pdp22d` (OPS). All four
were created by this probe minutes earlier. Every merge confirmation was
re-read before confirming and both usernames verified against this probe's own
list by an automated guard that aborts unless *both* names in the dialog match
`^u53pd` (guard output recorded per item below).

### Environment notes (context)

- Mailpit messages carry **no tags** in this install (`Tags: []` on every
  message read). Per-app scoping therefore rests entirely on the recipient
  address, which is why every throwaway got a distinct one.
- The scenario endpoints reuse an existing username rather than failing, which
  is how the cross-journal throwaways (`u53pd23d`, `u53pd24o`, `u53pdm24`) got
  roles in two contexts.
- Creating a scratch context enrols the creating site administrator (`admin`)
  as a manager of that context; `admin` therefore appears in every scratch
  context's user list. No action was taken on that row.

### Locators used (shared across items)

- Users & Roles list: `http://127.0.0.1:<port>/index.php/<contextPath>/en/management/settings/access`
- Row action menu button: `tr:has-text("<email>") >> button[aria-haspopup="menu"]`
  (its `aria-label` is the raw key `##userAccess.management.options##` — G2 item 8's territory, noted here only as context)
- Menu items: `getByRole('menuitem', {name: /…/})`
- Modals: `[role=dialog]`
- Hosted-Journals wizard: `/index.php/index/en/admin/wizard/<contextId>`, Users tab button `#users-button`
- Legacy grid row expander: `a.show_extras`; its actions live in the **following
  sibling `<tr>`** (`tr:has-text("<email>") >> xpath=following-sibling::tr[1]`)
- User edit / roles screen: reached by the row menu's **Edit**, which *navigates*
  (not a modal) to `/index.php/<contextPath>/management/settings/user/<userId>`

---

## Item 17 — Disable / Enable  ·  OJS + OMP

**Apps/roles:** OJS as `u53pdmgra` (Journal manager of `u53pdojsa`); OMP as
`u53pdmmgra` (Press manager of `u53pdompa`).
**Screen:** Users & Roles → Users list.
**Throwaways acted on:** `u53pd17o` (OJS), `u53pdm17` (OMP). A second browser
context was signed in as the throwaway before the disable, and had reached
`/<context>/dashboard/editorial?currentViewId=assigned-to-me`.

**Row menu before (both apps)** — *claim*:
`Edit / Email / Login As / Remove User / Disable User / Merge user`

**Disable dialog** — *claim*, verbatim (OJS; OMP identical except the role name):

> Disable U53pd17o U53pd17o
>
> Current Roles : Section editor
>
> Reason for disabling user
>
> Please note that once a user is disabled, you won't be able to add them to any roles until the user is enabled again.
>
>  Cancel OK

OMP title line: `Disable U53pdm17 U53pdm17`; roles line `Current Roles : Series editor`.
The dialog carries exactly one `textarea` (the reason box). Reason typed:
`u53pd probe item 17 reason text`. Confirmed with **OK**.

**Row after disabling** — *claim*: the row's Name cell gains a red inline icon.
Markup (OJS, name cell): `<span class="text-base-normal">U53pd17o U53pd17o</span> … <span class="inline-block align-middle rtl:scale-x-[-1] pkpIcon--inline h-4 w-4 text-negative"><svg …>` — a crossed-out-person glyph in the negative colour. Identical on OMP.
*Context*: the icon's wrapping `<span>` carries no `aria-label`, `title` or
`aria-hidden`; whether the inner `<svg>` provides a text alternative was not
established, so no accessibility claim is made here.

**Row menu after disabling** — *claim*, both apps:
`Edit / Email / Login As / Remove User / Enable User / Merge user`
(only the Disable entry flipped to **Enable User**).

**The throwaway's already-open session** — *claim*: navigating in the second
browser context immediately after the disable lands on the context's login page
(`/index.php/u53pdojsa/login?source=%2Findex.php%2Fu53pdojsa%2Fsubmissions`;
OMP the same with its own path). The session is dropped at the moment of the
disable.

**Fresh sign-in attempt** — *claim*: refused, at
`/index.php/index/en/login/signIn`, with the reason echoed back to the person
being refused:

> Your account has been disabled for the following reason: u53pd probe item 17 reason text

Identical on OMP.

**Enable dialog** — *claim*, verbatim:

> Reason for enabling user
>
> Once the user is enabled, they will regain access to the site, and you'll be able to invite them to roles as needed.
>
>  Cancel OK

The reason textarea is **pre-filled with the earlier disable reason**
(`"u53pd probe item 17 reason text"`), read back with `inputValue()`. Both apps.

**After enabling** — *claim*: the red mark is gone from the row, the menu entry
reads `Disable User` again, and the throwaway signs in successfully (lands on
`/index.php/index/en/index`). Both apps.

**Mail** — *context*: `u53pd17o@mail.test` and `u53pdm17@mail.test` were at zero
before and remained at zero after both the disable and the enable. Item 17 did
not ask for a silence demonstration, so this is recorded without a positive
control and is **not** offered as a silence claim.

**Settles:** Rules 4–5, the sessions-dropped side effect and scenario 3 —
**confirmed** in both apps.

---

## Item 18 — What the Disable dialog lists for a role-less user  ·  OJS

**Staging:** `u53pd18o` (created with Reader). Its Reader role was ended first by
choosing **Remove User** on the Vue list as `u53pdmgra`; the list then showed the
row with empty Roles and empty Start Date cells.

**Screen A — the wizard's Users tab** (site administrator `admin`, typed URL
`/index.php/index/en/admin/wizard/5`, Users tab):

- *claim* — the role-less row is listed by default, with a blank Roles column:
  `Settings | U53pd18o | U53pd18o | u53pd18o | (blank) | u53pd18o@mail.test`
- *claim* — its row actions are `Email · Edit User · Disable User · Login As · Merge User`.
  **"Remove" is absent.** A role-holding row in the *same* grid
  (`u53pd21o`, Section editor) shows `Email · Edit User · Disable User · Remove · Login As · Merge User`.
  Confirmed on OMP too (`u53pdm19` role-less → no Remove; `u53pdm21` Series editor → Remove present).
- *claim* — the Disable dialog opened from this grid reads:

  > Disable User
  >
  > Reason for disabling user
  >
  > Please note that once a user is disabled, you won't be able to add them to any roles until the user is enabled again.
  >
  >  Cancel OK

  **It has no name in the heading and no "Current Roles" line at all.** This is
  a property of the *legacy grid's* dialog, not of role-lessness: the same
  dialog opened from this grid on a role-**holding** user (`u53pd21o`, Section
  editor) is character-for-character the same, heading `Disable User` included.
  **Cancelled** — the dialog was closed without confirming; `u53pd18o` was left
  enabled (verified afterwards: no red mark on its list row).

**Screen B — the Vue Users & Roles list** (Journal manager `u53pdmgra`), on the
same role-less user:

- *claim* — the row menu offers `Edit / Email / Login As / Disable User / Merge user`.
  **Remove User is withheld on a role-less row** (compare the six-entry menu on
  a role-holding row in item 17).
- *claim* — the Disable dialog **still lists the ended role**:

  > Disable U53pd18o U53pd18o
  >
  > Current Roles : Reader
  >
  > Reason for disabling user
  >
  > Please note that once a user is disabled, you won't be able to add them to any roles until the user is enabled again.

  i.e. "Current Roles" shows a role the list's own Roles column no longer shows,
  because the role was end-dated rather than deleted. **Cancelled** — not confirmed.

**Settles:** fn-h's "what the dialog lists for a user with no roles" —
**answered, and it corrects the premise**: the two surfaces answer differently.
The legacy grid's dialog never lists roles for anybody; the Vue dialog lists the
user's *ended* roles as "Current Roles".

---

## Item 19 — Remove User  ·  OJS + OMP + OPS

**Roles/screens:** the context's own Journal/Press/Server manager
(`u53pdmgra` / `u53pdmmgra` / `u53pdpmgra`) on Users & Roles → Users.
**Throwaways acted on:** `u53pd19o`, `u53pdm19`, `u53pdp19`.
**Positive-control recipients:** `u53pdctlo`, `u53pdmctl`, `u53pdpctl`.

**Pre-state** — *claim*: each target mailbox was read and held **0** messages.

**Confirmation, verbatim** — *claim*. Dialog title `Remove`; buttons `OK`,
`Cancel`:

| App | Body |
|---|---|
| OJS | `Remove this user from this journal? This action will unenroll the user from all roles within this journal.` |
| OMP | `Remove this user from this press? This action will unenroll the user from all roles within this press.` |
| OPS | `Remove this user from this server? This action will unenroll the user from all roles within this server.` |

The OJS wording matches the draft exactly.

**What the list shows afterwards (P8)** — *claim*, all three apps: **the row does
not disappear.** It remains, with the Roles cell and Start Date cell both empty:
`"U53pd19o U53pd19o\tu53pd19o@mail.test"` (OMP/OPS analogous). The
"Current Users (N)" heading is **unchanged** by the removal (OJS stayed at 13,
OMP at 9, OPS at 8).

**Mail silence (A1's silent half)** — *claim*, all three apps, by the standing
discipline: target mailbox recorded at 0 → Remove performed → **positive
control** run on the same Mailpit (the row **Email** action sent to the control
throwaway, subject `u53pd item19 positive control <app>`) → control message
confirmed delivered → target mailbox re-read: still **0**. No email is sent by
Remove User in any of the three apps.

**The throwaway still signs in** — *claim*: all three signed in normally
afterwards, landing on `/index.php/index/en/index`.

**Re-adding a role gives a fresh start date** — *claim* (OJS): `u53pd19o`'s
Section editor role was re-granted through the site administrator's **Edit User**
form on the wizard's Users tab (tick "Section editor" → OK). The Users & Roles
list then showed `Section editor` with Start Date **2026-07-29**, where the
original enrolment had read **2026-07-28**. The dates differ because the run
crossed midnight, which makes this observation unambiguous.
*Context*: the manager's own **Edit** route to the same user is the U6
"Invite user to take a role" wizard, whose "Add Another Role" path goes through
an invitation rather than an immediate enrolment; the direct re-grant above was
therefore done on the administrator's legacy Edit User form.

**Removing a role-less user** — *claim*, and it **corrects the item's
expectation**: the "This user does not have any roles." answer is not reachable
from the legacy list, because the legacy list **does not offer Remove at all**
on a role-less row (evidence under item 18, OJS and OMP). The Vue list likewise
withholds `Remove User` on a role-less row. Nothing on either screen offers the
action that would produce that message.

*Context* — what the ended role looks like on the user's own roles screen
(`/management/settings/user/56`): the role stays in the table with
`START DATE 2026-07-28 · END DATE 2026-07-28`, its Remove-Role button replaced by
the badge `User Removed From Role`, and its masthead dropdown still rendered and
still interactive.

**Settles:** Rule 6 end-to-end, scenario 4, A1's silent half, P8 — **confirmed**;
the role-less-Remove message is **corrected to "not offered"**.

---

## Item 20 — One role ended (mails) vs Remove (adds nothing)  ·  OJS + OPS

**Roles/screens:** `u53pdmgra` (OJS) / `u53pdpmgra` (OPS) — Users & Roles list,
then the row menu's **Edit** → the roles table at `/management/settings/user/<id>`.
**Throwaways acted on:** `u53pd20o` (Section editor + Reader), `u53pdp20`
(Moderator + Reader). Mailboxes read at **0** first.

**Ending one role** — the roles table row's `Remove Role` button opens a
confirmation (*context*, U6's screen):

> Remove Role
>
> Are you sure you want to remove this role? The user will lose access and permissions associated with it.
>
> Remove Role   Cancel

After confirming, the ended row reads `Section editor | 2026-07-28 | 2026-07-29`
with the `User Removed From Role` badge (OPS: `Moderator | 2026-07-28 | 2026-07-29`).

**Mail after the single role end** — *claim*, both apps: exactly one message
arrives at the throwaway, subject **`You have been removed from a role`**, From
the acting manager's own address. Body (OJS; OPS identical with its own role and
context name):

> Removed from a Role
>
> Dear U53pd20o U53pd20o,
>
> Thank you very much for your participation in the role of Section editor at U53 Probe D Journal A.
>
> This is a notice to let you know that you have been removed from the following role at U53 Probe D Journal A: *Section editor*.
>
> Your account with U53 Probe D Journal A is still active and any other roles you previously held are still active.
>
> Feel free to contact me with any questions about the process.

**OPS sends this mail too** — *claim*. Subject and body identical, naming
`Moderator` at `U53 Probe D Server A`. This confirms the draft's OPS1 exception.

**Then Remove User on the same throwaway** — *claim*, both apps: the row menu
still offered the full set (`Edit / Email / Login As / Remove User / Disable User / Merge user`);
Remove was confirmed; the row remained with an empty Roles cell. Afterwards, with
a **positive control** delivered to the control throwaway on the same Mailpit
(subject `u53pd item20 positive control <app>`), the target mailbox still held
**exactly one** message — the role-ended notice. The Remove added nothing.

**Settles:** register A1's demonstration, Rule 8a's always-emails claim and
scenario 8 — **confirmed** on OJS and OPS.

---

## Item 21 — Masthead change  ·  OMP + OPS primary, OJS control

**Screen:** the user's roles table at `/index.php/<contextPath>/management/settings/user/<id>`
(reached by the Users & Roles row menu's **Edit**), column
"Journal Masthead" / "Press Masthead" / "Server Masthead".
**Roles:** the context's own manager in each app.
**Throwaways acted on:** `u53pd21o` (Section editor), `u53pdm21` (Series editor),
`u53pdp21` (Moderator) — all non-reviewer roles. Every mailbox read at **0** first.

**How the control behaves** — *claim*: the masthead cell is a `select`
(`select[name=masthead]`) with options `Appear on the masthead` (value `true`) and
`Does not appear on the masthead` (value `false`). Changing it does **not** wait
for "Save And Continue" (that button stays disabled); the change fires a
confirmation immediately:

> Confirm masthead visibility change
>
> This will update whether this user appears on the journal masthead for the selected role. The user will be notified of this change.
>
> Confirm   Cancel

*claim* — **that dialog says "journal masthead" in all three apps**, verbatim and
unchanged, including on OMP (press) and OPS (server), where the table header two
lines above it correctly reads "PRESS MASTHEAD" / "SERVER MASTHEAD". It also
promises "The user will be notified of this change", which is untrue on OMP and
OPS (below).

**OJS** — *claim*: after Confirm, the page simply re-renders. **No success
message, no error, no toast.** Reloading the page shows the select at the new
value (`false` → `true`): the change **persisted**. One email arrives, subject
**`Your journal masthead visibility has been updated`**:

> Updated role masthead visibility
>
> Dear U53pd21o U53pd21o,
>
> Your journal masthead visibility for the role Section editor (since July 28, 2026) in U53 Probe D Journal A has been updated.
>
> New setting: Appear on the masthead
>
> If you have questions about this change, please contact the journal manager.

**OMP and OPS** — *claim*: after Confirm, an error dialog appears, identical on
both apps, verbatim:

> Error
>
> Email template USER_ROLE_MASTHEAD_UPDATE not found. The migration script I11800_AddUserRoleMastheadUpdateEmail needs to be run.
>
> OK

and **the change still persisted**: reloading each user's roles page showed the
select at the new value (`true`). **No mail arrived.** Silence established
properly: target mailbox 0 before → change made → row **Email** positive control
delivered to `u53pdmctl` / `u53pdpctl` on the same Mailpit → target mailbox
re-read: still 0. The error text names an internal migration script to the
manager who made the change.

*Context*: the same error + persistence + silence reproduced on a second toggle
in the reverse direction (`true` → `false`) on both apps.

**Re-saving the same choice (OJS)** — *claim*: with the select already at
`Appear on the masthead`, re-selecting the same option produces **no dialog at
all** (`[role=dialog]` count 0 after 4 s), the value is unchanged, and no second
mail arrives — the mailbox still held exactly the one masthead notice after a
positive control had been delivered to `u53pdctlo`.

**A reviewer role's masthead cell** — *claim* (OJS): staged by granting
`u53pd18o` the **Reviewer** role through the site administrator's legacy Edit
User form. On that user's roles table the Reviewer row's masthead cell is **not a
control at all** — it is the plain text `Appear on the masthead`
(`select[name=masthead]` count = 0 on that row, = 1 on the neighbouring Reader
row in the same table). So the toggle is not offered for a reviewer role, and no
server refusal is reachable from this screen. This **corrects** the draft's
"the server refuses" framing for the edit path.

**Settles:** register A6, Rule 8b, the masthead side effect and OPS1's sending
half — **answered**, with the OMP/OPS behaviour being "saves, then errors, and
never mails", plus the new "journal masthead" wording leak in all three apps.

---

## Item 22 — Merge  ·  OJS + OMP + OPS (one full merge per app)

**Role/screen:** signed in as the seeded site administrator `admin`, on each
scratch context's Users & Roles list. **Nothing seeded was targeted.**

| App | Context | Duplicate (deleted) | Survivor |
|---|---|---|---|
| OJS | `u53pdojsa` | `u53pd22od` (Section editor) | `u53pd22os` (Copyeditor) |
| OJS | `u53pdojsc` | `u53pd22cd` (Section editor) | `u53pd22cs` (Copyeditor) |
| OMP | `u53pdompa` | `u53pdm22d` (Series editor) | `u53pdm22s` (Copyeditor) |
| OPS | `u53pdopsa` | `u53pdp22d` (Moderator) | `u53pdp22s` (Author) |

(The second OJS pair in journal C exists because the first run's post-merge log
was truncated; journal C was staged solely to re-observe the open-session drop.)

**History staged on each duplicate** — *context*: one email sent to it from its
own row's **Email** action (subject `u53pd item22 history marker <app>` on OJS,
`u53pd control hist22 <app>` on OMP/OPS), which leaves both a Mailpit message and
an email-log trace; plus its role's masthead set to `Appear on the masthead`
(value `true`) so the survivor's inherited masthead choice would be
distinguishable from the survivor's own (`false`).

**The second list** — *claim*: choosing "Merge user" replaces the panel with a
heading **`Merge into this User`** over the legacy user grid, and each eligible
row's expander offers the action **`Merge into this User`**.
*Context, offered-side observations that belong to G5 item 16 but were on screen*:
the duplicate's own row **is present** in that list (rendered without the
`Settings` expander, so no action is offered on it), and the acting
administrator's own `admin` row **is present with** its expander.

**Confirmation, verbatim** — *claim*, identical in all three apps (dialog title
`Confirm`, buttons `OK` / `Cancel`):

> Are you sure you wish to merge the account with the username "u53pd22od" into the account with the username "u53pd22os"? The account with the username "u53pd22od" will not exist afterwards. This action is not reversible.

P5's on-screen half is **confirmed**: the confirmation does state that the old
account will cease to exist, and says the action is not reversible.

**Guard before confirming** — both usernames were re-read out of the rendered
dialog and matched against this probe's throwaway prefix before `OK` was clicked.
Recorded guard output per run:
`["u53pd22od","u53pd22os"]`, `["u53pd22cd","u53pd22cs"]`,
`["u53pdm22d","u53pdm22s"]`, `["u53pdp22d","u53pdp22s"]`.

**The moment the merge completes (P4)** — *claim*, all three apps: the modal
closes by itself, the browser stays on
`/index.php/<contextPath>/management/settings/access` (no page navigation, no
reload), and **the duplicate's row is already gone** from the refreshed Users &
Roles list — zero occurrences of the duplicate's username anywhere on the page.
The "Current Users (N)" count drops by one immediately (OJS 13 → 12, OMP 9 → 8,
OPS 8 → 7).

**The duplicate's open session** — *claim*: a second browser context signed in as
the duplicate and sitting on the editorial dashboard is dropped; the next
navigation lands on the context's login page
(`/index.php/u53pdojsc/login?source=%2Findex.php%2Fu53pdojsc%2Fsubmissions`).

**The duplicate's username at sign-in** — *claim*, all three apps: refused with

> Invalid username/email or password. Please try again.

(the generic credential failure, not a disabled-account message — the account is
gone, not disabled).

**What the survivor inherited** — *claim*, all three apps: the survivor's list
row now shows both roles, and its roles table carries the duplicate's role with
**its original start date** and **its masthead choice**, alongside the survivor's
own unchanged:

| App | Survivor's own role | Inherited role |
|---|---|---|
| OJS | Copyeditor · start 2026-07-28 · masthead `false` | Section editor · start 2026-07-28 · masthead `true` |
| OMP | Copyeditor · start 2026-07-28 · masthead `false` | Series editor · start 2026-07-28 · masthead `true` |
| OPS | Author · start 2026-07-28 · masthead `false` | Moderator · start 2026-07-28 · masthead `true` |

**Settles:** Rule 7, scenario 5, P4, P5 and the sessions-dropped side effect —
**confirmed**, identically in all three apps.

---

## Item 23 — Cross-context merge attempted by a manager of A only  ·  OJS

**Staging:** journals A (`u53pdojsa`) and B (`u53pdojsb`); duplicate `u53pd23d`
holding Section editor in A **and** Copyeditor in B; target `u53pd23t`
(Copyeditor) in A. Actor: `u53pdmgra`, Journal manager of **A only** (B has its
own manager `u53pdmgrb`).

**Result** — *claim*: **the merge cannot be started.** The row menu on
`u53pd23d`, as manager of A only, offers only

`Edit / Email / Remove User / Disable User`

— **"Merge user" and "Login As" are both withheld.** The control is absent, so
no confirmation was reached and nothing was confirmed.

**Positive control for the same screen and the same actor** — *claim*: in the
same list, the same manager's menu on `u53pd17o` (roles in A only) offers all six
entries including `Merge user` and `Login As` (item 17). The difference is the
target's roles outside the manager's context.

**Settles:** register A3's merge half and fn-j's "falls through" prediction —
**corrected**. The Vue list does *not* dangle Merge on a user the server would
refuse to administer; it hides it. Nothing is offered to this role on any screen
that would carry the merge further, so there is no further observation to make
from this surface.

---

## Item 24 — Disable refused, Remove tolerated  ·  OJS + OMP

**Staging:** `u53pd24o` (Section editor in OJS journal A, Copyeditor in journal B)
and `u53pdm24` (Series editor in OMP press A, Copyeditor in press B).
**Actors:** `u53pdmgra` (manager of OJS journal A only), `u53pdmmgra` (manager of
OMP press A only).

**The row still offers Disable** — *claim*, both apps: the menu on the
cross-context user reads

`Edit / Email / Remove User / Disable User`

(Merge user and Login As withheld, as in item 23; Disable User and Remove User
both offered).

**Choosing Disable User** — *claim*, both apps: the dialog opens, renders the
user's name and roles, and then, **in place of the reason box and the
Cancel/OK buttons**, renders the refusal:

> Disable U53pd24o U53pd24o
>
> Current Roles : Section editor
>
> You do not have sufficient permissions to administer this user. In order to administer a user, you must either be site administrator, or administer all contexts that this user is enrolled in.

The dialog contains **0 `textarea` elements** and **no confirm button** — the only
button in the panel is its `Close`. So the refusal is delivered *before* any
confirmation, not after one; there is nothing to confirm. Wording is identical on
OMP (`Disable U53pdm24 U53pdm24` / `Current Roles : Series editor`). This is
close to, but not the same shape as, the draft's expectation of a post-confirm
refusal.

**Then Remove User** — *claim*, both apps: the ordinary confirmation appears
(`Remove this user from this journal? …` / `… press? …`), OK is accepted, and it
**succeeds**: the row stays with an empty Roles cell, the throwaway still signs
in normally, and its role in journal/press **B is untouched** — checked from B's
own Users & Roles list as B's manager:
`U53pd24o U53pd24o | u53pd24o@mail.test | Copyeditor | 2026-07-28` and
`U53pdm24 U53pdm24 | u53pdm24@mail.test | Copyeditor | 2026-07-28`.

*Context*: after the Remove, the same cross-context row's menu shrinks to
`Edit / Email / Disable User` (Remove User drops out with the roles; Merge and
Login As stay withheld).

**Settles:** register A3's disable half, scenario 7 and the PARTIAL-tolerated
Remove contrast — **confirmed**, with the refusal appearing up-front in the
dialog rather than after a confirmation.

---

## Item 25 — Can an administrator disable their own account?  ·  UNDETERMINED

**The item's condition was not met: no throwaway site administrator can be
staged through sanctioned means on this install.** Two routes were checked, both
by using the install as it is:

1. **The scenario endpoints.** `users[].roles` resolves against the *context's*
   default user groups. Asking for `siteAdmin`, `admin` and `administrator` each
   returns 400 listing the whole accepted set, which contains no site-admin key:
   `manager, editor, productionEditor, sectionEditor, guestEditor, copyeditor,
   designer, funding, indexer, layoutEditor, marketing, proofreader, author,
   translator, externalReviewer, reader, subscriptionManager, editorialBoardMember`.
   (The three refused calls rolled back cleanly — no stray contexts were left;
   verified against the site's journal list.)
2. **The screens.** Signed in as `admin`, `/index.php/index/en/admin` offers
   Site Management (Hosted Journals, Site Settings), System Information, Expire
   User Sessions, Delete Caches, Clear Scheduled Task Logs and Jobs — **no
   site-wide user list and no way to grant the site-administrator role**. The
   only role-granting screens in the install are per-context: the legacy Edit
   User form's role list (18 checkboxes, all context user groups, no site
   administrator) and the Users & Roles invite flow, which uses the same
   per-context vocabulary.

**Therefore:** item 11's offer observation stands alone, and **register A2's
execution half is unprobeable this round.** The seeded administrator's account
was never disabled, and its own row was never chosen for any action.

**What would settle it:** a sanctioned way to mint a second site administrator —
either a `siteAdmin` role key on the scenario endpoint's user spec, or a second
administrator seeded by the bootstrap roster. With one in hand the item is a
single run: sign in as that throwaway administrator, open its own row on the
Hosted Journals Users tab, choose Disable User, confirm with a reason, and record
whether the session dies mid-flight or the action is refused.

---

## Incidental (noticed while working G6; one line each, not investigated)

- The Users & Roles row-menu button's accessible name is the raw, untranslated
  key `##userAccess.management.options##` (G2 item 8's claim — seen in all three
  apps' markup here).
- `##common.help##` renders untranslated in the backend header and in every
  side-modal's chrome, in all three apps.
- Creating a scratch context enrols the creating site administrator as a manager
  of it, so `admin` appears in every scratch context's Current Users list — with
  a blank Start Date cell.
- On a user's roles table, a role that has been **ended** still renders a live,
  interactive masthead dropdown next to its `User Removed From Role` badge.
- The Users & Roles heading count ("Current Users (N)") counts rows, not
  enrolments: it is unchanged by a Remove User (the row stays) and drops by one
  on a merge.
