# U6 User Invitations — Probe 5 (top-up): Inviter feedback after the recipient answers

Probe agent (RUNBOOK step 3, top-up). Facts from driving the running OJS
install 2026-07-31. ONE question, OJS only: **what does the INVITER see and
receive after the recipient accepts or declines an invitation?**

## Environment used

- OJS `http://127.0.0.1:8000` only. Mailpit `:8025` (never cleared).
- Fresh scratch context created via `POST /api/v1/_test/scenarios/context`
  (`X-Test-Key: playwright-test-key`): tag `u6sojs`, **path `u6s-ojs`**
  (context id 7), name "U6S Inviter Feedback". Inviter = the scratch manager
  **`u6smgr`** ("Signe Manager", `u6smgr@mail.test`). Scratch contexts
  auto-enroll `admin` as a second manager (parity lesson 13).
- Throwaway recipients (all created by this probe; no seeded roster user
  touched): `u6p15a-ojs@mail.test` (accept case), `u6p15d-ojs@mail.test`
  (decline case), `u6p15c-ojs@mail.test` (post-decline mail-flow positive
  control). `publicknowledge` untouched.
- Both invitations: role **Author**, start 2026-07-31, "Appear on the
  masthead", sent from `/{ctx}/invitation/create/userRoleAssignment` as
  `u6smgr`. Recipient actions ran in fresh anonymous browser contexts
  (explicit empty storage state).
- Screenshots in the probe scratchpad (`shots/inviter-*.png`, `shots/accept-final-dialog-*.png`,
  `shots/decline-landing-*.png`); not committed (public repo).
- claim-vs-context: **[claim]** = promotable behavior; **[context]** =
  incidental nav/DOM note.

## What the product itself promises (the claim under test)

The send-flow's closing **"Invitation Sent"** dialog (recorded verbatim in
probe-2) tells the inviter: *"You can be updated about the user's decision on
the Users & Roles page, your OJS notifications and/or your email."* This probe
checked all three channels after each kind of answer.

## Baseline (invitation pending, before any answer)

Signed in as `u6smgr`, URL `/index.php/u6s-ojs/management/settings/access`:

- Invitations panel heading **"Invitations (1)"**; row
  `Ada Accepter · u6p15a-ojs@mail.test · Author · Invited 2026-07-31`
  (STATUS column text "Invited 2026-07-31"). **[claim]**
- Current Users (2): admin + Signe Manager. **[context]**
- Tasks panel (see below) "No Items"; inviter mailbox (Mailpit
  `to:u6smgr@mail.test`) empty. **[context]**

## Where "OJS notifications" live for the inviter

- The editorial backend's only notification affordance is the **bell icon in
  the dark top bar**, which is the button accessibly named **"Tasks"**
  (header inventory as `u6smgr` on `/index.php/u6s-ojs/dashboard/editorial`:
  skip links · context title · a help link whose visible text is the raw
  placeholder `##common.help##` · button "Tasks" (bell glyph) · user menu
  "SM u6smgr"). There is **no separate notifications bell** — Tasks IS the
  bell. **[context]**
- Clicking it opens the **Tasks** panel (legacy
  `TaskNotificationsGridHandler` grid, `controllers/page/tasks.tpl`), with
  bulk buttons Mark New / Mark Read / Delete. **[context]**
- The legacy notifications route `/index.php/u6s-ojs/notification` returns a
  bare **"404 Not Found"**. **[context]**

## Case A — recipient ACCEPTS

Recipient flow: accept link from the invite email
(`/index.php/u6s-ojs/invitation/accept?id=38&key=…`) opened signed out; the
3-step new-user wizard completed (username `u6p15a`; final dialog "You've been
assigned a new role in OJS"). Then, as the inviter (`u6smgr`, fresh page
loads):

1. **Tasks panel / notifications bell** — dashboard
   `/index.php/u6s-ojs/dashboard/editorial`, bell ("Tasks") clicked: panel
   shows **"No Items" · "0 - 0 of 0 items"**. No badge/count on the bell.
   **The inviter receives NO on-screen notification of the acceptance.**
   **[claim]** (keyed on the Tasks grid's "No Items" empty-state text;
   screenshot `inviter-tasks-open-after-accept.png`)
2. **Inviter mailbox** — Mailpit search `to:u6smgr@mail.test`: **zero
   messages**, before and after the acceptance (checked at every phase; final
   sweep also empty). **The inviter receives NO email about the acceptance.**
   Positive control: mail delivery from this context demonstrably works —
   the invite emails to `u6p15a-…`/`u6p15d-…`/`u6p15c-…` all arrived,
   including one sent AFTER these events. **[claim]**
3. **Invitations table** — `/index.php/u6s-ojs/management/settings/access`
   reloaded: panel now **"Invitations (0)" / "No Items"** — the row is
   **removed outright**, with no "Accepted" state shown anywhere. The trace
   of the acceptance is indirect: **Current Users grew to (3)** with the new
   row `Ada Accepter · u6p15a-ojs@mail.test · Author · 2026-07-31`. **[claim]**
   (screenshot `inviter-users-roles-after-accept.png`)

## Case B — recipient DECLINES

Recipient flow: decline link (`…/invitation/decline?id=…&key=…`) opened signed
out; "Decline Invitation" confirmation page → "Confirm Decline Invitation"
pressed (lands on `/u6s-ojs/login`, matching probe-3). Then, as the inviter:

1. **Tasks panel / notifications bell** — identical: **"No Items" ·
   "0 - 0 of 0 items"**, no bell badge. **No on-screen notification of the
   decline.** **[claim]** (screenshot `inviter-tasks-open-after-decline.png`)
2. **Inviter mailbox** — still **zero messages** to `u6smgr@mail.test`.
   **No email about the decline.** Positive control: a third invitation sent
   AFTER the decline (to `u6p15c-ojs@mail.test`) was delivered normally
   (subject "You are invited to new roles"), so mail flow was live when the
   silence was observed. **[claim]**
3. **Invitations table** — **"Invitations (0)" / "No Items"**: the declined
   row is **removed outright**, exactly like the accepted one. The declined
   recipient (`Dana Decliner / u6p15d-ojs@mail.test`) does **not** appear in
   Current Users (still 3: admin, Signe Manager, Ada Accepter). **[claim]**
   (Row-present-before-answer for the decline case is per the identical send
   flow + delivered email; probe-3 item 7 independently recorded the
   (1)→(0) drop on decline.)

## The finding (claim vs reality)

**Accepted and declined invitations are indistinguishable to the inviter, and
two of the three promised update channels never fire.** The "Invitation Sent"
dialog promises updates via "the Users & Roles page, your OJS notifications
and/or your email"; in reality:

- **OJS notifications**: nothing — Tasks/bell stays empty in both outcomes.
- **Email**: nothing — the inviter's mailbox receives no message in either
  outcome (with a live positive control).
- **Users & Roles page**: the pending row silently vanishes in BOTH outcomes.
  The only way to tell accept from decline is to notice whether the person
  materialized in Current Users; a decline leaves no trace at all (no
  history, no "Declined" status, no filter for answered invitations was
  found on the screen).

Product-quality finding, not a security weakness (no data or action crosses a
role boundary) — so it belongs in the public Findings register, not the
security file.

## Minor incidental notes **[context]**

- Backend top-bar help link renders the raw locale placeholder
  `##common.help##` on the scratch context (seen as `u6smgr`; same class of
  defect as probe-3's `##userAccess.management.options##` note).
- `/index.php/u6s-ojs/notification` → bare "404 Not Found".

## Blockers

None. Both cases driven to completion. (Leftover state: scratch context
`u6s-ojs` retains one pending control invitation to `u6p15c-ojs@mail.test`;
harmless, disposable context.)

## Security routing

Nothing routed to the private security file this probe.
