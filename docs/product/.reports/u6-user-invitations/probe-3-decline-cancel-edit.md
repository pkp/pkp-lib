# U6 User Invitations — Probe 3: Wrong-user refusal, Decline, Cancel, Edit/replace

Probe agent (RUNBOOK step 3). Facts from driving running installs 2026-07-31.
Items covered: 6 (wrong-user refusal), 7 (decline), 8 (cancel), 9 (edit/replace).
Written for the digest agent.

## Environment used

- Fleets (all live, via 127.0.0.1): OJS `:8000`, OMP `:8100`, OPS `:8200`.
  Mailpit `:8025`.
- Scratch contexts created via `POST /api/v1/_test/scenarios/context`
  (`X-Test-Key: playwright-test-key`); all mutations confined to these:
  - Primary set (items 6/7/8): OJS `u6qojs` (mgr `u6qmgrojs`), OMP `u6qomp`
    (mgr `u6qmgromp`), OPS `u6qops` (mgr `u6qmgrops`). The OJS/OMP contexts were
    seeded with two extra author accounts each — an existing target
    (`u6q6-<app>@mail.test`) and a bystander (`u6q6b<app>` /
    `u6q6b-<app>@mail.test`) — for item 6.
  - Clean set for item 9 (to avoid an accumulated-invitations pagination trap):
    OJS `u6r9ojs` (mgr `u6r9mgrojs`), OPS `u6r9ops` (mgr `u6r9mgrops`).
  - Scratch contexts auto-enroll `admin` as manager (parity lesson 13).
- Throwaway recipients: `u6q6-<app>@mail.test`, `u6q7<rnd>-<app>@mail.test`,
  `u6q8<rnd>-<app>@mail.test`, `u6q9<rnd>-<app>@mail.test` — all `@mail.test`
  addresses the probe created. No seeded roster user was invited or mutated;
  Mailpit was never cleared.
- Send entry point (all items): `/{ctx}/invitation/create/userRoleAssignment`
  reached as the scratch manager.
- Screenshots saved under the probe scratchpad (`shots/<app>-item<N>-*.png`);
  not committed (public repo).
- claim-vs-context: **[claim]** = promotable behavior; **[context]** =
  incidental nav/DOM note.

---

## Item 6 — Wrong-user refusal (OJS deep + OMP spot)

Existing-user invitation created in the scratch context (target
`u6q6-<app>@mail.test`, who already holds Author), then the emailed **accept**
link opened while signed in as a DIFFERENT user (`u6q6b<app>`).

### OJS (deep) — target `u6q6-ojs@mail.test`, invited to **Reviewer**; bystander signed in as `u6q6bojs`

- Accept link opened: `http://127.0.0.1:8000/index.php/u6qojs/invitation/accept?id=13&key=eiykNe`
- **The page renders the review wizard shell** ("STEP 1 - Review & create
  account", Account Details / User Details / Roles table all shown blank —
  "No Items" under Roles) with a **refusal dialog overlaid on top**. Both the
  wizard body and the dialog are present in the DOM.
- **Refusal dialog (verbatim):** **"Invitation not accepted. You're logged in as
  a different user. Please log out and sign in with the correct account to accept
  this invitation."** Its only action is a single button **"Logout"**. **[claim]**
- Page-level buttons present behind the dialog: Edit, Cancel, "Accept And
  Continue to OJS", Logout. **[context]**
- **Pressing "Logout":** navigates to `/u6qojs/login` (the journal sign-in
  screen, Home/Login). A follow-up profile probe confirmed the bystander session
  is now ended (`/user/profile` redirected to `/login?source=…`). So the dialog's
  Logout genuinely logs the wrong user out. **[claim]**
- **Reopening the link signed out** (fresh anonymous context): the flow starts —
  a single-step existing-user review wizard: step rail **"1 Review & create
  account"** ("1/1 steps"; an unresolved `{$current}/{$total} steps` token is
  also visible), heading "STEP 1 - Review & create account", **User Details**
  showing only an ORCID iD "Not verified…" row, **Roles** table listing the
  invited **Reviewer / 2026-07-31 / Appear on the masthead** row, and buttons
  Cancel + **"Accept And Continue to OJS"**. No credential prompt. **[claim]**
  (This matches probe-2 item 5's existing-user accept shape; identity rests on
  link-token possession.)

### OMP (spot) — target `u6q6-omp@mail.test`, invited to **External Reviewer**; bystander `u6q6bomp`

- Accept link: `http://127.0.0.1:8100/index.php/u6qomp/invitation/accept?id=6&key=P5X56x`
- **Identical refusal dialog (verbatim):** **"Invitation not accepted. You're
  logged in as a different user. Please log out and sign in with the correct
  account to accept this invitation."** — single **"Logout"** action. **[claim]**
- "Logout" → `/u6qomp/login`; bystander session ended (profile probe
  redirected to login). Signed-out reopen shows the same single-step review
  wizard ("1 Review & create account", Roles = External Reviewer). **[claim]**

**Side finding surfaced while sending the OMP existing-user invitation (item 6
send side) — see item 6/8/9 "Masthead-template error" note below.**

---

## Item 7 — Decline (OJS deep + OMP spot for the "gone" rendering)

New-email invitation sent, then the emailed **decline** link opened signed out.

### OJS (deep) — recipient `u6q7-ojs@mail.test`, role **Author**

- Decline link: `http://127.0.0.1:8000/index.php/u6qojs/invitation/decline?id=11&key=ne4m6G`
  (accept + decline share the same `id`+`key`).
- **Confirmation page (verbatim):** heading **"Decline Invitation"**, body
  **"Are you sure you want to decline this invitation? Confirm the decline by
  clicking the button below."**, single button **"Confirm Decline Invitation"**.
  URL stays on the decline route; title is the context name. No other actions on
  the page. **[claim]**
- **Pressing "Confirm Decline Invitation"** (label verbatim): navigates to
  **`/u6qojs/login`** (the journal sign-in / Home-Login screen). No explicit
  "invitation declined" success banner was rendered on the destination. **[claim]**
- **No role granted:** Users & Roles → **Current Users** still shows exactly the
  4 pre-existing accounts (admin, u6qmgrojs, Existing Target, Bystander User);
  the recipient `u6q7-ojs@mail.test` is NOT in Current Users. **[claim]**
- **Invitations-table row gone:** the Invitations panel dropped from
  "Invitations (1)" to **"Invitations (0)" / "No Items"**; body no longer
  contains `u6q7-ojs`. **[claim]**
- **Reopening the same decline link** (verbatim): heading **"Invitation
  Unavailable"**, body **"This invitation is no longer available. It may have
  already been accepted, declined, or expired. Please contact the journal manager
  for further assistance."**, with **Login** and **Register** links. **[claim]**
- Bonus: the paired **accept** link after decline shows the same "Invitation
  Unavailable" friendly page. **[claim]**

### OMP (spot — "gone" rendering) — recipient `u6q7bfky-omp@mail.test`, role **Author**

- Confirmation page identical (verbatim): "Decline Invitation" / "Are you sure
  you want to decline this invitation? Confirm the decline by clicking the button
  below." / **"Confirm Decline Invitation"**. **[claim]**
- Confirm → `/u6qomp/login`. Invitations row gone (`to` absent after reload).
  Reopen shows **"Invitation Unavailable"** / same body / **Login Register**.
  **[claim]**

---

## Item 8 — Cancel (all three apps)

As the scratch manager, a pending new-email invitation was cancelled from the
Invitations table via the row's action menu.

### Common mechanics (all apps) **[claim]**

- The invitation-row action trigger is a button with
  `aria-label="Invitation management options"` (NOT accessibly named "More
  Actions"; the column header text is "MORE ACTIONS"). Its menu items are
  exactly **"Edit"** and **"Cancel Invite"** (`role="menuitem"`).
- **Cancel confirmation dialog (verbatim recap), OJS example:**
  title **"Cancel Invitation"**, body recap
  **"Email: u6q8okqu-ojs@mail.test  Role: Author,  Status: Invited 2026-07-31
  Affiliation:"** (affiliation blank), two buttons: **"Cancel Invitation"**
  (the confirm/destructive action) and **"Cancel"** (dismiss). The confirm
  button label collides with the dismiss verb — the primary button is
  "Cancel Invitation", the dismiss is "Cancel".
- After confirming: the row disappears from the Invitations panel **immediately**
  and **stays gone after a full reload** (recipient address absent both times).
- The recipient's **accept** link then shows the friendly
  **"Invitation Unavailable"** page ("This invitation is no longer available. It
  may have already been accepted, declined, or expired. Please contact the
  journal manager for further assistance.") with **Login** and **Register**
  links — matching the expectation of an unavailable page with Login/Register.

### Per-app confirmation

| App | Recipient / role | Confirm dialog | Row gone (immediate + reload) | Accept link after cancel |
|---|---|---|---|---|
| OJS | `u6q8okqu-ojs@mail.test` / Author | "Cancel Invitation" recap, buttons "Cancel Invitation" + "Cancel" | yes / yes | "Invitation Unavailable" + Login/Register |
| OMP | `u6q88kfi-omp@mail.test` / Author | same (recap shows OMP address, Role: Author) | yes / yes | "Invitation Unavailable" + Login/Register |
| OPS | `u6q8gpe7-ops@mail.test` / Moderator | same (recap shows OPS address, Role: Moderator) | yes / yes | "Invitation Unavailable" + Login/Register |

All **[claim]**.

---

## Item 9 — Edit/replace (OJS deep + OPS spot)

Row's **Edit** menu item exercised; role changed; re-sent. Key observation is
what the FIRST (superseded) email's accept link then shows.

### OJS (deep) — recipient `u6q9pvav-ojs@mail.test`, role Author → **Reviewer** (clean context `u6r9ojs`)

- **Edit warning dialog (verbatim):** title **"Edit Invitation"**, body **"If you
  edit the existing invitation or add a new role, the current invitation will be
  canceled and, a new one will be sent. Are you sure you want to proceed?"** (the
  comma placement "canceled and, a new one" is in the product text), buttons
  **"Edit Invitation"** (proceed) and **"Cancel"** (dismiss). **[claim]**
- **Prefilled wizard WITHOUT the search step:** proceeding navigates to
  **`/u6r9ojs/invitation/edit/34`** (the invitation id in the path). Header
  "Ed Editable" / sub-line "You are viewing Ed's user details". Step rail is now
  just **"1 Enter details" · "2 Review & invite for roles"** — the "Search User"
  step is absent (vs the 3-step create wizard). The details form is the same as
  create-step-2 (Email/Given/Family + the roles sub-table with role/date/
  masthead). **[claim]** (Field values are model-prefilled; the resend targeting
  the original address confirms the recipient was retained even though the input
  values are not visible in a text dump.)
- **Re-send:** changed the role to Reviewer, Save And Continue → compose →
  "Invite user to the role" → same **"Invitation Sent"** dialog. A **second**
  email arrived (2 total to the recipient); it carries a **new** invitation with
  a **different `id` and `key`** (`id=36&key=8BH5Vn` vs first `id=34&key=8rJWZ5`).
  **[claim]**
- **KEY OBSERVATION — the FIRST email's accept link after edit/resend returns a
  bare `404 Not Found`** (raw text "404 Not Found", empty title, no journal
  chrome, no links) — **NOT** the friendly "Invitation Unavailable" page that
  Cancel and Decline produce for a superseded link. **[claim]** This is an
  inconsistency: an invitation invalidated by Edit 404s, while one invalidated
  by Cancel/Decline shows the styled unavailable page with Login/Register.
- The **second** (new) email's accept link works: opens the normal 3-step
  new-user wizard ("1 Create OJS account · 2 Enter details · 3 Review & create
  account"). **[claim]**

### OPS (spot) — recipient `u6q9rb2o-ops@mail.test`, role Moderator → **Author** (clean context `u6r9ops`)

- Edit warning dialog identical verbatim ("Edit Invitation" / "If you edit the
  existing invitation or add a new role, the current invitation will be canceled
  and, a new one will be sent. Are you sure you want to proceed?" / buttons
  "Edit Invitation" + "Cancel"). **[claim]**
- Prefilled 2-step edit wizard at **`/u6r9ops/invitation/edit/8`** (no search
  step; masthead column "SERVER MASTHEAD"; role list = Preprint Server manager /
  Moderator / Author / Reader / Editorial Board Member). **[claim]**
- Re-send produced a second email with a new `id`+`key` (`id=10&key=L8X8pw` vs
  first `id=8&key=JaGVfL`). **[claim]**
- **KEY: first email's accept link → bare `404 Not Found`** (same as OJS); the
  second link opens the working 3-step "Create OPS account" wizard. **[claim]**

---

## Cross-cutting finding — OMP masthead-update email template missing (blocks existing-user invites on OMP)

Surfaced on OMP while sending the item-6 **existing-user** invitation (adding a
role to `u6q6-omp@mail.test`). Applies to any existing-user invite where a
masthead value is set on the added role (the field is **required**).

- Setting the added role's masthead select pops a confirm dialog
  **"Confirm masthead visibility change / This will update whether this user
  appears on the journal masthead for the selected role. The user will be
  notified of this change. / [Confirm] [Cancel]"** (this same dialog appears on
  OJS and there it completes successfully — OJS sends a "Your journal masthead
  visibility has been updated" email to the target, confirmed in Mailpit).
- On **OMP**, pressing **Confirm** produces a fatal **Error** dialog (verbatim):
  **"Error / Email template USER_ROLE_MASTHEAD_UPDATE not found. The migration
  script I11800_AddUserRoleMastheadUpdateEmail needs to be run. / OK"**. **[claim]**
- Dismissing the error with **OK** does let the underlying invitation proceed
  (the invite email was still delivered), but the masthead-visibility flow is
  broken and the user sees a raw migration-script error string. **[claim]**
- Not re-verified on OPS (OPS item 9 avoided the existing-user branch). Worth a
  digest check on whether the `I11800` migration ran on OMP/OPS but not just OJS.

## Minor finding — untranslated locale key on Current Users row actions

On the Users & Roles **Current Users** table (all apps), each row's action-menu
trigger carries `aria-label="##userAccess.management.options##"` (raw
locale-key placeholder), whereas the **Invitations** table rows use the
resolved `aria-label="Invitation management options"`. **[claim]** (context-level
DOM/accessibility note; observed incidentally.)

---

## Blockers

None to item completion. The OMP masthead-template error is a product defect,
not a probe blocker — the existing-user invite still completed after dismissing
the error.

## Security routing

Nothing routed to the private security file this probe. The item-6 wrong-user
guard behaves correctly (refusal dialog + forced logout); the signed-out
existing-user accept-by-link-possession behavior was already recorded by probe-2
(item 5) as the designed link-token auth, so no new security concern arose here.
