# U6 User invitations — Claim-check Chunk A (RUNBOOK step 8)

Target text: `lib/pkp/docs/product/specs/user-invitations.md` — **Actors & permissions**
table plus the three assigned hunts (Rule 3, the "Invitation Sent" dialog's
"View All Users" button, and URL reachability).

Method: live adversarial probing on the three fleets (OJS 8000 / OMP 8100 /
OPS 8200, `127.0.0.1`). Mutations run in fresh scratch contexts
`u6ca-ojs` / `u6ca-omp` / `u6ca-ops` seeded via `/api/v1/_test/scenarios/context`
with throwaway users (`u6ca.manager`, `u6ca.dualmr` = manager+reviewer,
`u6ca.dualar` = author+reviewer, `u6ca.reader`); `publicknowledge` and the
seeded roster used read-only. Recipients are `u6ca*@mail.test` throwaways.
Date of run: 2026-07-31 (claude).

Each verdict is the author's judgment, unreviewed.

---

## Claim 1 — "Invite to a role" reachable by the Site Administrator (all three apps)

**Claim (quoted):** *"**Invite to a role** (open the send wizard) — Site
Administrator; Journal Manager — the 'Invite to a role' button"* (Actors table);
footnote a: *"the Site Administrator row holds on all three apps."*

**Adversarial case:** type the wizard's own create address as `admin` on each of
OJS, OMP, OPS — the app where a site-admin row is most likely to differ.

**Observation:** `GET {app}/index.php/publicknowledge/en/invitation/create/userRoleAssignment`
as `admin`:
- OJS → 200, wizard renders ("Invite user to take a role … STEP 1 - Search User").
- OMP → 200, wizard renders ("…a role in OMP … appearing in the press masthead").
- OPS → 200, wizard renders ("…a role in OPS … appearing in the server masthead").

**HOLDS** — the Site Administrator reaches the send wizard on all three apps.

---

## Claim 2 — "Invite to a role" for the Journal Manager is scoped to their own journal

**Claim (quoted):** *"**Invite to a role** … Site Administrator; Journal
Manager"* — an unmarked all-apps claim that the *Journal Manager* is the qualifier
(i.e. a manager of one journal, acting within it).

**Adversarial case:** a scratch Manager of journal A typing journal B's wizard
address — does a Manager reach a journal they do not manage?
`u6ca.manager` manages only `u6ca-{ojs,omp,ops}`; probe both their own context
(control) and `publicknowledge` (not managed), on all three apps.

**Observation:** as `u6ca.manager`:
- own context (`.../u6ca-ojs/…/create/userRoleAssignment`, and OMP/OPS equivalents)
  → 200, wizard renders (control passes on all three).
- `.../publicknowledge/en/invitation/create/userRoleAssignment` →
  302→`/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`
  ("The current role does not have access to this operation") on OJS, OMP and OPS.

**HOLDS** — the Manager gate is context-scoped; a manager of one journal is
turned away from another journal's wizard on all three apps (no cross-journal
over-reach).

---

## Claim 3 — A1: no role beyond Manager/Admin is offered the button; Author/Reviewer are turned away, including when a user holds two roles

**Claim (quoted):** *"⚠ A1 no other role is offered the button; an Author or
Reviewer who types the wizard's own address is turned away"* and the assigned
sub-hunt: *"do the denied-role rows still hold when the user holds TWO roles,
one allowed one denied?"*

**Adversarial case (OJS):**
- `u6ca.dualar` = **author + reviewer** (two *denied* roles) → wizard address.
- `u6ca.dualmr` = **manager + reviewer** (one allowed, one denied) → wizard address.

**Observation:** `GET .../u6ca-ojs/invitation/create/userRoleAssignment`:
- `u6ca.dualar` (author+reviewer) →
  `/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`.
- `u6ca.dualmr` (manager+reviewer) → 200, wizard renders (nav shows both "My
  Assignments as Reviewer" and the manager menus — access granted via the manager
  role).

**HOLDS** — two denied roles stay denied; a user gets in only when they hold an
allowed role, and then via that role. (The separately-recorded A1 over-reach of
Sub-editor / Assistant roles was not re-opened here — outside this chunk's cells.)

---

## Claim 4 — "See pending invitations": Site Administrator; Journal Manager

**Claim (quoted):** *"**See pending invitations** — Site Administrator; Journal
Manager — the 'Invitations' table on Users & Roles."*

**Adversarial case:** confirm the Invitations table actually renders for a
Manager on the journal they manage (not merely that the wizard is reachable), and
that a plain Reader is refused the invitation surfaces.

**Observation:** as `u6ca.manager` at `.../u6ca-ojs/management/settings/access`
the Users tab renders an **"Invitations (N)"** panel listing pending rows
(NAME / EMAIL / INVITATIONS / STATUS / AFFILIATION; e.g. "Harry Three ·
u6ca-h3@mail.test · Journal manager · Invited 2026-07-31"). As `reader.rosa`
(OJS) and each scratch `u6ca.reader`, the invitation *create* and *edit*
addresses both return `authorizationDenied` (role-based access denied), on OJS,
OMP and OPS.

**HOLDS** — the pending-invitations surface is present for the Manager and
refused to a Reader. (Reachability of the Users & Roles screen itself is deferred
by the spec to the user-management feature; only the invitation-specific
behaviour is judged here.)

---

## Claim 5 — Accept/decline work signed out, no credentials asked

**Claim (quoted):** *"**Accept or decline** — The recipient — via the emailed
links, while the invitation is pending; worked signed out, no credentials asked
(Rules 6–7)."*

**Adversarial case:** open a genuine, still-pending accept link in a signed-out
browser context and confirm no login prompt precedes the wizard.

**Observation:** signed-out `GET
.../u6ca-ojs/invitation/accept?id=149&key=d3QsAg` (a live pending invitation from
the Rule-3 run) → 200, accept wizard renders directly ("STEP 1 - Create OJS
account …"), no login form interposed.

**HOLDS** — a pending accept link opens its wizard for a signed-out visitor with
no credential prompt.

---

## Claim 6 — Rule 3: one live invitation per person per journal (a second send replaces the first)

**Claim (quoted):** *"3. One live invitation per person per journal: sending a
new role invitation to the same person (or email address) replaces any earlier
pending one."*

**Adversarial case:** as scratch Manager, send invitation #1 to
`u6ca-dup@mail.test` (role Journal editor), then immediately send invitation #2
to the **same email** (role Reviewer). Record what the send screens offer for
the second send, the resulting Invitations table, and both emailed links.

**Observation (OJS deep):**
- The second send's **Search User** step gave no warning about the existing
  pending invitation — it advanced straight to STEP 2 as for an unknown address.
- Invitations table after #1: two rows total, one for the email
  ("Dupe One · u6ca-dup@mail.test · Journal editor").
- Invitations table after #2: still **one** row for the email, now
  "Dupe Two · u6ca-dup@mail.test · **Reviewer**" — the second invitation
  replaced the first (name and role are the second send's).
- Emailed links (Mailpit, to `u6ca-dup@mail.test`): first invitation
  `id=146` accept link → **404 Not Found**; second `id=149` accept link → 200
  live accept wizard. (The replaced link dies with a bare 404 — the same
  ungraceful landing already logged as finding A3, not a new defect.)

**Spot-check (OMP):** two sends to `u6ca-dup-omp@mail.test` → Invitations table
shows **"Invitations (1)"**, single row "OmpDupe Two" — replacement confirmed.

**HOLDS** — exactly one live invitation survives per email; the newer send
silently supersedes the earlier one, whose link stops working, on OJS and OMP.

---

## Claim 7 — Rule 15 / A5: the "Invitation Sent" dialog's "View All Users" button "leaves the browser on the wizard"

**Claim (quoted):** *"Its only button, 'View All Users', leaves the browser on
the wizard — the sender returns to Users & Roles on their own"* (Rule 15); A5 /
footnote g: *"its 'View All Users' button only re-anchors the wizard URL — the
store's redirect to management/settings/access never fires."*

**Adversarial case:** send an invitation as scratch Manager, then actually press
"View All Users" on the "Invitation Sent" dialog and record where the browser
ends up (not the URL while the dialog is open).

**Observation (OJS, `u6ca.manager`, context `u6ca-ojs`, run twice):**
- URL while the dialog is shown: `.../invitation/create/userRoleAssignment#userInvited`.
- After pressing **"View All Users"**: `.../u6ca-ojs/management/settings/access`,
  page title "Users & Roles | U6CA Scratch OJS", the **Users & Roles** screen
  rendered with the "Invitations (1)" panel showing the just-created row.
- Deterministic across two consecutive runs.

**WRONG** — the button navigates the browser to the Users & Roles page (as its
label says); it does **not** leave the sender on the wizard. The `#userInvited`
anchor the earlier probe cited is the URL *while the dialog is open*, before the
click; the click itself performs the `management/settings/access` redirect that
the spec says "never fires." (Reads as a fix landing after the spec's probe, or a
misread of the anchor state; either way live reality now matches the label.)
This does not touch the other half of A5 (no accept/decline notice to the
inviter), which was out of this chunk's scope.

---

## Claim 8 — Rule 4: URL reachability for signed-out visitors and plain Readers

**Claim (quoted):** *"A correct link whose invitation was already answered …,
cancelled, or expired shows the 'Invitation Unavailable' page … A tampered or
truncated link shows a not-found error."* (Rule 4), plus the Reference table's
named addresses (create, edit, accept, decline).

**Adversarial case:** type each named address as a **signed-out visitor** and as
a **plain Reader**, on all three apps, using a bogus `id`/`key` (a tampered
link).

**Observation:**
| Address | Signed-out | Plain Reader |
|---|---|---|
| `.../invitation/create/userRoleAssignment` | 302 → `/en/login?source=…` (login required) | `authorizationDenied` (role-based access denied) |
| `.../invitation/edit/1` | 302 → `/en/login?source=…` | `authorizationDenied` |
| `.../invitation/accept?id=1&key=deadbeef…` | **404 Not Found** | **404 Not Found** |
| `.../invitation/decline?id=1&key=deadbeef…` | **404 Not Found** | **404 Not Found** |

Identical outcomes on OJS, OMP and OPS (signed-out); OJS `reader.rosa` and each
scratch `u6ca.reader` for the Reader column.

**HOLDS** — send-side addresses demand login / a privileged role; a tampered
accept/decline link shows the not-found error Rule 4 predicts. No signed-out or
Reader request reached a surface beyond its entitlement.

---

## Summary

| # | Claim | Verdict |
|---|-------|---------|
| 1 | Site Admin reaches the send wizard (all three apps) | HOLDS |
| 2 | Journal Manager gate is context-scoped (no cross-journal reach) | HOLDS |
| 3 | A1 denied-role rows hold, incl. dual-role users | HOLDS |
| 4 | "See pending invitations" — Admin/Manager only | HOLDS |
| 5 | Accept/decline work signed out, no credentials | HOLDS |
| 6 | Rule 3 — one live invitation per person, second replaces first | HOLDS |
| 7 | Rule 15/A5 — "View All Users leaves the browser on the wizard" | **WRONG** |
| 8 | Rule 4 — reachability for signed-out & Reader | HOLDS |

**Tally: 8 claims checked — 7 HOLDS, 1 WRONG, 0 UNRESOLVED.**

Nothing surfaced by this chunk warranted the security routing: every denied
case (cross-journal Manager, dual denied roles, signed-out/Reader on every named
address) returned an authorization refusal, a login redirect, or a 404 — no role
reached beyond its entitlement.
