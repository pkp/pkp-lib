# U6 User invitations — probe-1: gates & surfaces

Probe agent (RUNBOOK step 3). Facts only, from driving the running fleets as
signed-in users. No spec-writing, no bug-vs-intended judgment. Written for the
digest agent.

- Date: 2026-07-31
- Fleets driven (all live, via 127.0.0.1): OJS `:8000`, OMP `:8100`, OPS `:8200`,
  context `publicknowledge`.
- Method: throwaway Playwright (Chromium) scripts in scratchpad; UI login per
  user (password rule = username×2, `admin`→`admin`); direct address-bar
  navigation and, where noted, sidebar nav clicks. Read-only screen visits —
  **no invitation was sent, no data mutated.**
- Passwords ≥33 chars have the login `maxlength=32` lifted before fill (harness
  convention), so long-password `sectioneditor.*` accounts log in.
- Line tags: **[claim]** = promotable observation; **[context]** = incidental
  DOM/nav note, not promotable.
- **Security routing**: item-1 observations of a role reaching the invite
  wizard beyond plausible entitlement had their CONTENT routed to the
  maintainer's private security file (append). Those cells below are kept
  generic on purpose. Routing DID happen (fact stated; content omitted here).

Screenshots for every observation are saved under the probe scratchpad
(`shots/<app>-<user>-<label>.png`); not committed (public repo).

---

## Item 1 — Wizard-address gate + Users & Roles address, per role

Wizard URL typed: `{base}/publicknowledge/invitation/create/userRoleAssignment`
→ server 302s to `/index.php/publicknowledge/en/invitation/create/userRoleAssignment`.
Users & Roles URL typed: `{base}/publicknowledge/management/settings/access`.

Two outcomes seen across the board:
- **WIZARD RENDERS**: HTTP 200, final URL stays on the invitation route, title
  is the context name, `<h1>` = "Invite user to take a role", body shows the
  step-1 Search-User surface (steps "1 Search User / 2 Enter details / 3 Review
  & invite for roles", Cancel + "Search User" buttons).
- **DENIED**: HTTP 200 but final URL is
  `/index.php/publicknowledge/en/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`,
  body "Home / The current role does not have access to this operation."
  (Same denial shape for the Users & Roles address.)

The Users & Roles **address** (`/management/settings/access`) was DENIED for
every role tested in item 1 (section editor, assistant-level, author,
reviewer) on all three apps — same authorizationDenied redirect as above.
**[claim]** The guard on the management surface holds for all non-manager roles.

### OJS (`:8000`)

| Role · user | Wizard (`/invitation/create/userRoleAssignment`) | Users & Roles addr (`/management/settings/access`) | tag |
|---|---|---|---|
| Section Editor · sectioneditor.ana | routed to security file | DENIED (authorizationDenied) | [claim] |
| Copyeditor (assistant) · copyeditor.carla | routed to security file | DENIED (authorizationDenied) | [claim] |
| Layout Editor (assistant) · layouteditor.leo | routed to security file | DENIED (authorizationDenied) | [claim] |
| Author · author.alex | DENIED (authorizationDenied) | DENIED (authorizationDenied) | [claim] |
| Reviewer · reviewer.julia | DENIED (authorizationDenied) | DENIED (authorizationDenied) | [claim] |

### OMP (`:8100`)

| Role · user | Wizard | Users & Roles addr | tag |
|---|---|---|---|
| Series Editor (=sectionEditor) · sectioneditor.ana | routed to security file | DENIED (authorizationDenied) | [claim] |
| Copyeditor (assistant) · copyeditor.carla | routed to security file | DENIED (authorizationDenied) | [claim] |
| Author · author.alex | DENIED (authorizationDenied) | DENIED (authorizationDenied) | [claim] |
| Reviewer · reviewer.julia | DENIED (authorizationDenied) | DENIED (authorizationDenied) | [claim] |

Wizard byline on OMP reads "…take a role in OMP along with appearing in the
press masthead." **[context]**

### OPS (`:8200`)

OPS has no reviewer/copyeditor/layout accounts; nearest assistant-slot role is
`assistant.rita` (Editorial Board Member, no stage access — per
`app.context.js`). No reviewer account exists to test.

| Role · user | Wizard | Users & Roles addr | tag |
|---|---|---|---|
| Moderator (=sectionEditor) · sectioneditor.ana | routed to security file | DENIED (authorizationDenied) | [claim] |
| Editorial Board Member (assistant slot) · assistant.rita | routed to security file | DENIED (authorizationDenied) | [claim] |
| Author · author.alex | DENIED (authorizationDenied) | DENIED (authorizationDenied) | [claim] |

Wizard byline on OPS reads "…take a role in OPS along with appearing in the
server masthead." **[context]**

**Reviewer on OPS: not tested — no reviewer account seeded (construction of the
OPS roster).** **[context]**

---

## Item 2 — Baseline surface: Manager and Site Administrator, Users & Roles

Reached via sidebar (`Settings → Users & Roles`) after landing on the context
backend; final URL `/index.php/publicknowledge/en/management/settings/access`,
`<h1>` "Users & Roles". Tabs present on the screen (all apps, both roles):
**Users · Roles · Site Access Options · ORCID**. **[context]**

On the Users tab, two panels: an **Invitations** panel above **Current Users**.

| App | Role · user | "Invitations" panel heading | "Invite to a role" button | Empty-state | tag |
|---|---|---|---|---|---|
| OJS | Manager · manager.maya | "Invitations (0)" | present, label **"Invite to a role"** | "No Items" | [claim] |
| OJS | Site Admin · admin | "Invitations (0)" | present, "Invite to a role" | "No Items" | [claim] |
| OMP | Manager · manager.maya | "Invitations (0)" | present, "Invite to a role" | "No Items" | [claim] |
| OMP | Site Admin · admin | "Invitations (0)" | present, "Invite to a role" | "No Items" | [claim] |
| OPS | Manager · manager.maya | "Invitations (0)" | present, "Invite to a role" | "No Items" | [claim] |
| OPS | Site Admin · admin | "Invitations (0)" | present, "Invite to a role" | "No Items" | [claim] |

Invitations table column headers (all apps): **NAME · EMAIL · INVITATIONS ·
STATUS · AFFILIATION · MORE ACTIONS**. **[claim]**

**[claim]** Presence of the "Invite to a role" button and the "Invitations (N)"
panel is identical across all three apps and for both Manager and Site Admin —
button label is verbatim "Invite to a role" everywhere (locale key
`invitation.inviteToRole.btn`).

Incidental (Current Users panel, confirms role-label localization per app):
- OJS Current Users (18): section editors show role "Section editor",
  assistant.rita "Funding coordinator". **[context]**
- OMP Current Users (18): sectioneditor.* show "Series editor";
  reviewers split "External Reviewer"/"Internal Reviewer". **[context]**
- OPS Current Users (9 — subset roster): sectioneditor.* show "Moderator",
  assistant.rita "Editorial Board Member"; no reviewer/copyeditor/layout rows.
  **[context]**

---

## Item 3 — Emails settings screen: search for the user-invitation template

Screen: `{base}/publicknowledge/management/settings/manageEmails`, `<h1>`
"Manage Emails" (panel heading "Emails"), signed in as `manager.maya`.
The visible search box has placeholder **"Search by name or description"**
(a second `type=search` input with placeholder "Search submissions" exists in
the DOM but is hidden). Filtering only takes effect after the query is
committed with **Enter** (typing alone leaves the full list shown — a live
observation, not a template presence fact). Template counts below are
list-item counts after Enter.

Search term `invit`:

| App | Result after Enter | Template shown | tag |
|---|---|---|---|
| OJS | 1 template matched | **"User Invited to Role Notification"** — desc "This email is sent to users that are invited to obtain certain roles", has an **Edit** button | [claim] |
| OMP | 1 template matched | **"User Invited to Role Notification"** — same desc + Edit | [claim] |
| OPS | **0 templates — "No items found."** | (absent) | [claim] |

**[claim]** The user-invitation email template ("User Invited to Role
Notification") is present and searchable in the Manage Emails screen on OJS and
OMP, and is ABSENT on OPS (search for `invit` returns "No items found.";
scanning the full unfiltered OPS list also shows no such template).

**[claim]** On OJS/OMP the same template is also found by the fuller string
"User Invited to Role" (subset of the same single match).

Incidental: the OPS Manage Emails filter rail is smaller (Sent From:
Moderator/Reader/System; no Reviewer/Assistant/Subscription-Manager buckets),
consistent with OPS lacking review/reviewer roles. **[context]**

---

## Coverage / blockers

- Items 1, 2, 3 covered across OJS, OMP, OPS (OPS item-1 reviewer cell N/A — no
  reviewer account seeded).
- No blockers. All logins succeeded; all target screens reachable.
- Read-only throughout; no invitation sent, no scratch context created, Mailpit
  untouched.
- Routed to the private security file: yes (item 1 — content omitted here). The
  routed observations concern which non-manager roles reach the invite wizard
  by direct URL.
