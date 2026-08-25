---
name: login-and-sessions
scope: A user signs in and out, stays signed in, recovers or is forced to change a password, re-confirms their password for the Administration area, and an administrator or manager logs in as another user
apps: [ojs, omp, ops]
shared: pkp-lib
status: verified
atlas-claims: [AFFW-467, AFFW-470, AFFW-473, AFFW-499, AFFM-105, AFFM-191, AFFM-209, AFFU-001, AFFU-002, AFFU-003, AFFU-004, AFFU-005, AFFU-006, AFFU-007, AFFU-008, AFFU-036, AFFU-037, AFFU-038, AFFU-039, AFFU-040, AFFU-041, AFFU-042, AFFU-043, AFFU-044, AFFU-045, AFFU-046, AFFU-047, AFFU-048, AFFU-049, AFFU-050, AFFU-051, AFFU-052, ROUTE-016, MAIL-030, SET-052]
---

# Login & sessions

> Conventions (markers, badges, footnotes): [Reading a spec](GLOSSARY.md#reading-a-spec).

## Purpose

Everything here is about being someone on the site. A visitor signs in with a
username (or email address) and password and is taken where their roles point;
signing out hands the browser back to the public site. A forgotten password is
recovered through an emailed link; an account can be flagged so its next
sign-in forces a password change. Two supervised doors exist on top of the
ordinary one: administrators and journal managers can **log in as** another
user to see the site exactly as that user does and act on their behalf, and
the site can be configured so that the Administration area asks the Site
Administrator to **confirm their password** again before opening. This spec
owns those flows, the session behavior behind them (staying signed in,
expiry), and the screens they run on.

## Actors & permissions

"Impersonate" below means using **Login As** to continue the browser session
as another user. A user is "wholly within a manager's journals" when every
role they hold anywhere on the site sits in journals that manager manages.
The Users & Roles screen's own reachability belongs to the users-management
feature (see *Cross-feature interactions*).

| Action | Who may — and when |
|--------|--------------------|
| **Sign in / sign out** | • Anyone with an enabled account — any journal's Login page or the site-level one; a disabled account is refused with a message (Rule 2) <sup>a</sup> |
| **Request a password reset** | • Anyone, signed out — the "Forgot your password?" link on the Login page (Rules 7–8) <sup>e</sup> |
| **Set a new password from the emailed link** | • The holder of the emailed link — while the link is valid (Rules 8–10) <sup>f</sup> |
| **Complete a forced password change** | • The account holder — when their account is flagged to require it, at their next sign-in (Rule 11) <sup>g</sup> |
| **Impersonate a user (Login As)** | • Site Administrator — any account except their own or another Site Administrator's (Rule 14)<br>• Journal Manager — accounts wholly within the journals they manage (Rule 14)<br>• Nobody else — no other role is offered the action anywhere, and even its address, captured by hand in a session that does offer the action (Rule 14), answers them with the access-denied page: "The current role does not have access to this operation." (Rule 17) <sup>h</sup> |
| **Return to their own account** | • The impersonator — "Logout as {username}" in the user menu, or the same entry on the workflow Participants panel (Rule 15) <sup>j</sup> |
| **Pass the Confirm Access gate** | • Site Administrator — the gate exists only when the site's configuration requires re-authentication, and only the Administration area asks (Rule 16); every other role is turned away from Administration by its ordinary role gate, never by this one <sup>k</sup> |
| **See the access-denied page** | • Any signed-in user reaching a screen their role does not allow (Rule 17); a signed-out visitor gets the Login page instead (Rule 4) <sup>l</sup> |

## Fields & validation

**Login page** (title "Login"):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Username or Email" | yes | Either the account's username or its email address |
| "Password" | yes | The typing box stops accepting input at 32 characters, though passwords may be longer ⚠ [A1](#a1); a "Forgot your password?" link sits under it <sup>a</sup> |
| "Keep me logged in" | no | Keeps the sign-in alive for a fixed window from login, beyond the idle lifetime that otherwise ends it (Rule 5); the box arrives pre-ticked ⚠ [A2](#a2) |
| Spam check | when configured | Only when the installation's configuration turns a check on for the login form: reCAPTCHA shows its widget; the ALTCHA check is invisible — nothing extra appears and signing in works as usual, though a browser without JavaScript is refused with "You must complete the validation check used to prevent spam submissions." (see *Settings*) <sup>a</sup> |
| "Register" link | — | Shown beside the "Login" button while the journal accepts registrations; disabling registration removes it (see *Settings*) <sup>a</sup> |

**Lost-password page** (title "Reset Password"; reached via "Forgot your
password?"):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Registered user's email" | yes | Must be an email address; the page instructs "Enter your account email address below and an email will be sent with instructions on how to reset your password." <sup>e</sup> |
| Spam check | when configured | The Login page's invisible ALTCHA check, per configuration <sup>e</sup> |
| "Register" link | — | Same as the Login page's — gone when registration is disabled |

**Set-a-new-password form** (opened by the emailed link; the page is headed
"Reset Password", though the browser tab shows a raw internal code in place
of a title ⚠ [A3](#a3)):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "New password" | yes | At least the site minimum length (stated under the field: "The password must be at least {N} characters."); when the site's compromised-password check is on, a known-breached password is refused (see *Settings*) <sup>f</sup> |
| "Repeat new password" | yes | Must match |

**Change Password form** (forced at sign-in; title "Change Password"):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Login" (username) | yes | Prefilled with the username that just signed in <sup>g</sup> |
| "Current password" | yes | Wrong value errors "The current password you entered was incorrect." <sup>g</sup> |
| "New password" / "Repeat new password" | yes | Site minimum length; both must match <sup>g</sup> |

**Confirm Access form** (title "Confirm Access"):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Password" | yes | The signed-in Site Administrator's own password; the page says "Signed in as **{name}**. Please enter your password to continue."; a wrong password re-shows the form with the generic sign-in error (Rule 16) <sup>k</sup> |
| "Cancel" | — | Leaves for the page the administrator came from — never back into Administration (Rule 16) <sup>k</sup> |

## Rules & state

1. **One Login page.** Every journal has a Login page, and the site has one
   outside any journal — reached from the "Login" link at the top right of
   the site's own homepage, the page listing the journals; both carry the
   same form. A visitor who is already
   signed in and opens the Login, lost-password, or emailed reset-link
   address is sent to their home (Rule 3) instead of the form. <sup>a</sup>
2. **One generic failure.** A wrong password and a wrong or unknown username
   or email answer the same sentence on the same page: "Invalid
   username/email or password. Please try again." An empty required box
   never reaches the site: the browser itself refuses the submission and
   prompts to fill the field in. A disabled
   account with correct credentials answers differently: "Your account has
   been disabled. Please contact the administrator for more information." —
   or, when staff recorded a reason, "Your account has been disabled for the
   following reason: {reason}". <sup>a</sup>
3. **Landing after sign-in.** An interrupted destination wins (Rule 4).
   Otherwise a user holding a role beyond Reader **in the journal signed
   into** lands on that journal's Dashboard; a user with no such role there
   — Reader-only, role-less, or holding roles only in other journals —
   lands on the journal home page. Signing in at the site-level Login of a
   multi-journal site lands on the site home page whatever the user's
   roles: the Dashboard landing needs a journal to aim at. <sup>b</sup>
4. **An interrupted visit resumes.** A signed-out visitor opening a private
   screen's address — a bookmark, an emailed link — gets the plain Login
   page; nothing on it names the destination being held, though a few
   screens add an explanatory sentence above the form (a download that
   requires signing in, for example). Signing in continues to the address
   they originally asked for. One address misbehaves: the address that ends
   at the word "dashboard", with nothing after it, answers a blank
   server-error page instead of the Login page ⚠ [A7](#a7). <sup>b</sup>
5. **Staying signed in.** Closing the browser does not sign a user out:
   ticked or not, the sign-in survives browser restarts — unless the
   installation is configured to end sessions at browser close. Unticked,
   the session ends when its idle lifetime runs out (a config default of
   7 days without a visit). Ticking "Keep me logged in" extends the sign-in
   past that idle limit, for a fixed window from login (config default
   30 days). <sup>c</sup>
6. **Signing out.** The user menu (top-right initials) offers "Logout";
   signing out returns the browser to the Login page and ends only this
   browser's session — the same account signed in elsewhere stays signed in.
   The login form then arrives with the just-departed user's email address
   prefilled in "Username or Email" — the email even when the sign-in had
   used the username. While impersonating, the menu offers no
   plain Logout — its only exit is "Logout as {username}" (Rule 15). <sup>d</sup>
7. **Requesting a reset.** Submitting the lost-password form answers "A
   confirmation has been sent to your email address if a matching account was
   found. Please follow the instructions in the email to reset your
   password." with a "Login" link back; an email actually goes out when the
   address belongs to an account. <sup>e</sup>
8. **The reset email** carries one link. The link expires on a clock (2 hours
   on a default install — see *Settings*) and dies early if the account's
   password changes or the account signs in, so it is single-use in effect.
   <sup>e</sup> <sup>f</sup>
9. **Setting the new password.** A valid link opens the set-a-new-password
   form (Fields above). Saving answers "Password has been updated
   successfully. Please login with updated password." with a "Login" link —
   the user is **not** signed in by resetting, and every other signed-in
   session of that account ends at that moment. <sup>f</sup>
10. **A dead link explains itself.** An expired or altered link shows "Sorry,
    the link you clicked on has expired or is not valid. Please try resetting
    your password again." with a "Reset Password" link back to the
    lost-password page. A link whose username no longer exists lands on the
    lost-password page directly. <sup>f</sup>
11. **Forced password change.** An account flagged to require a password
    change signs in only through the "Change Password" form: correct
    credentials at the Login page divert there instead of landing anywhere.
    The page explains "You must choose a new password before you can log in
    to this site…". Completing the form signs the user in and lands them
    where an ordinary sign-in would (Rule 3 — a reviewer, for instance,
    lands on the Dashboard); their other sessions end. No users screen offers the
    flag itself ⚠ [A5](#a5): the one screen-driven path that sets it is the
    review stage's "Create New Reviewer" {OJS OMP}, which flags the new
    account automatically and emails it a generated password (the form
    belongs to the reviewer-assignment feature). <sup>g</sup>
12. **Impersonation is total while it lasts.** After Login As, the browser
    session **is** the target user: their dashboard, their submissions,
    their name on everything done. The action is offered behind a
    confirmation dialog reading "Log in as this user? All actions you
    perform will be attributed to this user." (OK / Cancel). <sup>h</sup> <sup>i</sup>
13. **While impersonating, the screen says so.** The top bar shows the
    impersonator's own initials, muted, with the target's initials overlaid
    in a warning color; the user menu adds "You are currently logged in as
    {username}" with a "Logout as {username}" link; on a submission's
    workflow screen, the Participants panel shows its own "Logout as
    {username}" entry at the top of the list. In every one of these,
    {username} is the **impersonated** account — the labels name the user
    being worn, not the one who will be restored. <sup>j</sup>
14. <a id="who-may-impersonate"></a> **Who may impersonate whom.** A Site
    Administrator may impersonate anyone except themselves and other Site
    Administrators. A Journal Manager may impersonate a user wholly within
    the journals they manage; a user who also holds roles in a journal the
    manager does not manage is out of reach. Rows never offer the action on
    the current user's own account. No screen offers a path to an
    out-of-reach user, but the action's address can be built by hand: use
    Login As on a row that does offer it, copy the address the browser
    visited from its history — it ends in a number identifying that user —
    return to your own account ("Logout as", Rule 15), then open that
    address with the number changed. Opened for an out-of-reach user, it
    shows an error page — "Sorry, you do not have administrative rights
    over this user…" — listing the possible causes, with a link back to the
    users list. In a session the site can no longer fully resolve — one
    that outlived a server-side reset, say — the same address answers a
    blank server error instead of impersonating or turning the visitor
    away ⚠ [A8](#a8). <sup>h</sup>
15. **Returning.** "Logout as {username}" (user menu or Participants panel)
    restores the original account without asking for credentials and lands
    home — or back on the same submission when used from a workflow screen.
    Typing the plain sign-out address instead — it must be captured before
    impersonating, by copying the link behind the user menu's "Logout"
    entry, since the menu no longer offers it after — ends everything: the
    browser is signed out of both identities and lands on the Login page,
    not back in the original account. No deeper nesting is supported, yet the
    Users & Roles list and the workflow Participants panel still offer
    Login As while an impersonation is already active (to see this,
    impersonate a user who can themselves open one of those screens — a
    Journal Manager, say), and using it starts a second one that replaces
    the first ⚠ [A4](#a4). <sup>i</sup> <sup>j</sup>
16. **The Confirm Access gate.** When the installation's configuration sets a
    re-authentication window (minutes), every Administration screen first
    shows the "Confirm Access" form (Fields above). The correct password
    opens Administration for the window's duration; working inside
    Administration keeps refreshing the window, so the prompt returns only
    after the administrator has been away from it longer than the window. A
    button press that needs Administration rights after the window lapsed —
    pressing Administration's "Delete Template Cache" button after idling
    past the window, for example — also lands on Confirm Access, and the
    pressed action is **not** replayed: after confirming, a notice says
    "Your last action was not completed. Please try again." Opening the
    Confirm Access address directly with nothing to continue to — the
    address trimmed of the interrupted page it carries in the address bar
    whenever the gate fires — never shows the form: the browser is sent
    straight home without being asked for a password (while a confirmed
    window is still active, it lands on Administration instead). Starting an
    impersonation ends the confirmed window. <sup>k</sup>
17. **Access denied, signed in.** A signed-in user reaching a screen their
    role does not allow is shown a message page stating the denial (the
    exact sentence varies with the screen; most commonly "The current role
    does not have access to this operation."); nothing of the refused screen
    renders. Signed out, the same address shows the Login page instead
    (Rule 4). <sup>l</sup>
18. **Sessions end from the outside too.** The installation can be
    configured to end a session whose network address changes mid-visit (on
    by default), and the Site Administrator can expire every session at once
    with the "Expire User Sessions" tool (see *System administration*); the
    user's next click then lands on the Login page. <sup>m</sup>

## Side effects

- **On a reset request** — one email, "Password Reset Confirmation", to the
  matching account, sent from the site's principal contact, containing the
  single reset link (Rule 8). No other flow in this spec sends mail. <sup>e</sup>
- **On every sign-in** — the account's last-login date is updated (it shows
  on users-management screens). Completing a forced change or an emailed
  reset also ends the account's other sessions (Rules 9, 11). <sup>b</sup> <sup>f</sup>
- **On impersonation** — everything done while impersonating is recorded as
  the impersonated user: submissions, decisions and emails all carry the
  target's name, per the confirmation dialog's warning (Rule 12). Submission
  activity-log entries additionally remember who was really acting: they
  display "{impersonator} (acting as {target})" — except to readers from
  whom a review's anonymity already hides the entry's reviewer, who see the
  target's name alone (the log screen itself belongs to the *Submission
  activity log & notes* feature).

## Settings that modify behavior

- **Site password policy** — the minimum password length and the
  compromised-password check ("uncompromised" validation on new passwords)
  are set on the Administration site settings screen (owned by the *Site
  settings* feature). <sup>n</sup>
- **Sign-in rate limiting** — the same screen can enable rate limiting with
  a maximum attempt count (default 5) and cool-down (default 300 seconds).
  When enabled, repeated failed sign-ins or reset requests are refused for
  the cool-down — deliberately showing the same generic answer as an
  ordinary failure, plus a delay, so the limiting itself is invisible.
  During the cool-down even the correct password answers "Invalid
  username/email or password. Please try again." — intended concealment
  [A6](#a6). Disabled by default. <sup>n</sup>
- **Configuration file, security section** — for the system administrator,
  no screen: the reset-link lifetime (`reset_seconds`, default 2 hours); the
  "Keep me logged in" window (`remember_me_lifetime`, default 30 days);
  end-session-on-browser-close (`session_expire_on_close`);
  end-session-on-address-change (`session_check_ip`, default on); forcing
  https for login or the whole site (`force_login_ssl`, `force_ssl`); the
  Confirm Access window (`password_timeout` minutes, default off); the salt
  behind reset links — a secret value that makes the links unforgeable. The
  same section's plugin-installation and allowed-HTML
  keys serve other features (see *Cross-feature interactions*). <sup>m</sup>
- **Idle session lifetime** — `session_lifetime` (default 7 days) in the
  configuration file's general section. <sup>m</sup>
- **Spam checks on login and lost-password** — the configuration file's
  captcha section decides whether the login form carries reCAPTCHA or the
  invisible ALTCHA check (`captcha_on_login` / `altcha_on_login`) and
  whether the lost-password form carries the ALTCHA check
  (`altcha_on_lost_password`); the captcha configuration's home is the
  *Registration & account validation* feature. <sup>a</sup>
- **User registration disabled** — the journal setting that closes
  registration also removes the Register links from the Login and
  lost-password pages; a directly-typed register address then answers "This
  journal is currently not accepting user registrations." The setting
  itself belongs to the *Roles configuration* feature. <sup>a</sup>

## Cross-feature interactions

- **Registration** — the Login page's "Register" link enters the
  registration flow, and the captcha configuration is homed there (see
  *Registration & account validation*).
- **User profile** — a signed-in user changes their own password on the
  profile's Password tab (see *User profile*); this spec owns only the
  sign-in-blocking flows (forced change, emailed reset).
- **Users management** — disabling accounts (with the reason Rule 2 shows)
  and the Users & Roles screen where Login As is most prominently offered
  belong to *Users management*; this spec owns the Login As action itself
  on every surface that offers it. No users screen currently exposes the
  flag behind the forced password change ⚠ [A5](#a5) (Rule 11).
- **Stage participants / Reviewer assignment** — the Participants panel and
  the Reviewers table belong to their own features; this spec owns only
  their "Login As" / "Logout as" entries. The "Create New Reviewer" form
  that flags its new account for a password change (Rule 11) is the
  reviewer-assignment feature's.
- **System administration** — the Administration area the Confirm Access
  gate protects, and the "Expire User Sessions" tool, are *System
  administration & jobs*'; the gate itself is this spec's.
- **Site settings** — the password-policy and rate-limiting form is *Site
  settings*'; this spec owns their effect on these screens.
- **Invitations & one-click links** — emailed invitation and reviewer
  one-click links open their flows signed out without touching these
  screens; they belong to [user invitations](U06-user-invitations.md) and the
  future *Reviewer's review* spec.
- **Plugins management** — the configuration file's security section also
  carries the plugin-installation policy keys consumed by *Plugins
  management*.

## Canonical scenarios

Common to all three apps; substitute roles and vocabulary per the
[application glossary](GLOSSARY.md). Actors are named by role; seeded
accounts and recipes live in the footnotes. <sup>s</sup>

1. **Sign in and land on the Dashboard** — Editor: open the journal's Login
   page; enter the right username with a wrong password — the page answers
   "Invalid username/email or password. Please try again." and keeps the
   username filled in. Enter the correct password and press "Login" — the
   browser lands on the Dashboard. <sup>s</sup>
2. **Sign out** — any signed-in user: open the top-right user menu (the
   initials button) and press "Logout". The browser returns to the Login
   page, signed out; the "Username or Email" box already holds the
   departed account's email address. Opening any dashboard address now
   shows the Login page, not the dashboard — except the address that ends
   at the word "dashboard", which answers a blank error page ⚠ [A7](#a7).
3. **A bookmarked private page waits for sign-in** — Editor, signed out:
   paste a submission workflow address into the browser. The Login page
   appears instead of the submission. Sign in — the browser continues
   straight to the submission that was asked for, not to the Dashboard.
4. **Recover a forgotten password** — Author, signed out: press "Forgot your
   password?" on the Login page, enter the account's email address, submit —
   the page answers that a confirmation has been sent if the address
   matched. Open the emailed "Password Reset Confirmation" message and follow its
   link; on "Reset Password", enter a new password twice and save. The page
   answers "Password has been updated successfully. Please login with
   updated password." — still signed out. Sign in with the new password
   (works); the old password now fails with the generic error. <sup>s</sup>
5. **A stale or altered reset link is refused** — the same Author, signed
   out again (signed in, the link just bounces home — Rule 1): open the
   emailed link again after the password was changed and the account signed
   in — the page answers "Sorry, the link you clicked on has expired or is
   not valid. Please try resetting your password again." with a link back
   to the lost-password form. A link with a mangled code answers the same.
6. **Forced password change at first sign-in** — Editor, on a submission in
   review: in the "Add Reviewer" window, choose "Create New Reviewer" and
   create a reviewer with a throwaway email address. The registration email
   delivers a username and a generated password. Sign in with them — instead
   of landing anywhere, the "Change Password" form appears. Enter the
   emailed password as the current one and a new password twice; pressing
   "OK" signs the reviewer in and lands them on the Dashboard — the landing
   their reviewer role earns (Rule 3). Signing in again with
   the new password is normal. {OJS OMP; a preprint server has no review
   stage, so no screen there sets the flag — no OPS analogue.} <sup>s</sup>
7. **Administrator impersonates a user and returns** — Site Administrator:
   on Users & Roles, open an Author's row menu and choose "Login As"; a
   dialog warns all actions will be attributed to that user; press OK. The
   browser is now that Author's session — their name, their My Submissions —
   and the top bar shows the administrator's initials with the Author's
   overlaid in a warning color; the user menu reads "You are currently
   logged in as {author}".
   Choose "Logout as {author}" — the administrator is back in their own
   session, no password asked. <sup>s</sup>
8. **Editor impersonates a participant from the Participants panel** —
   Editor, on a submission's workflow: on the Participants panel, open a
   Section Editor participant's row menu and choose "Login As"; confirm.
   The browser lands on the same submission as that participant, and the
   top of the Participants panel now offers "Logout as {that participant}"
   — press it to return to the editor's view of the same submission.
   Impersonating the submission's Author instead lands on the author's own
   My Submissions view, which shows no Participants panel — the way back is
   the user menu's "Logout as {author}" entry. On a journal or press the
   Reviewers table offers the same row action for reviewers {OJS OMP; a
   preprint server has no Reviewers table}. <sup>i</sup>
9. **Administration asks for the password again** — Site Administrator, on
   an install configured with a re-authentication window (see *Settings*):
   open Administration — the "Confirm Access" page appears, naming the
   administrator and asking for their password. A wrong password re-shows
   the form with the generic sign-in error; the right one opens
   Administration. Leaving Administration alone past the window and coming
   back shows the gate again; pressing Cancel on it leads back out, not
   into Administration. <sup>s</sup>

## Findings register

Verdicts are the author's judgment (claude, 2026-08-01), unreviewed unless an
entry notes otherwise; the team settles them on spec review. Sorted 🐞 → ❓ in
the summary; the entries below are the source. Each entry opens with the
user-observable symptom; mechanism and evidence live in the entry's footnote.
Impact values: user-visible = real effect in ordinary use · minor =
cosmetic only, however often seen · latent = only in an unusual situation
or configuration.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|-----------------------------|------|--------|--------|
| [A1](#a1) | The password boxes stop accepting input at 32 characters, so longer passwords cannot be typed | 🐞 | user-visible | Jarda 2026-08-25 |
| [A2](#a2) | "Keep me logged in" arrives pre-ticked on every visit | 🐞 | minor | Jarda 2026-08-25 |
| [A3](#a3) | The set-a-new-password page's browser tab shows a raw internal code instead of a title | 🐞 | minor | Jarda 2026-08-25 |
| [A4](#a4) | "Login As" is still offered mid-impersonation (Users & Roles and the Participants panel); a second use strands the operator — "Logout as" restores the intermediate user, not their own account | 🐞 | latent | Jarda 2026-08-25 |
| [A7](#a7) | Signed out, the address ending at the word "dashboard" answers a blank server error instead of the Login page | 🐞 | user-visible | Jarda 2026-08-25 |
| [A8](#a8) | Login As answers a blank server error when the browser's session can no longer be fully resolved (e.g. it outlived a server-side reset) | 🐞 | minor | Jarda 2026-08-25 |
| [A5](#a5) | No users screen offers the "must change password" flag, so a forced change cannot be required on an existing account | ❓ | user-visible | Jarda 2026-08-25 · to triage |
| [A6](#a6) | With rate limiting on, even the correct password is refused as "Invalid username/email or password" during the cool-down — intended concealment | ✅ | latent | Jarda 2026-08-25 |

### All apps

<a id="a1"></a>
**A1 — Password boxes cut off at 32 characters** · 🐞 · user-visible.
The password fields on the Login, Confirm Access, forced-change and reset
forms refuse to accept more than 32 typed characters, while nothing stops an
account from having a longer password (passwords are stored hashed, and other
entry paths allow longer ones). A user whose password runs past 32 characters
types it, the box silently keeps only the first 32, and sign-in fails with
the generic error — with no hint why.
Basis: code inspection + observed on a running site. <sup>[f-a1](#fn-a1)</sup>

> **Reviewed — Jarda Kotěšovec, 2026-08-25**: confirmed 🐞. Ruling: raise the
> maximum accepted password length to at least 64 characters (OWASP Password
> Storage guidance; bcrypt reads only a password's first 72 bytes, so 64 is a
> safe, meaningful step up from 32).

<a id="a2"></a>
**A2 — "Keep me logged in" pre-ticked** · 🐞 · minor.
The checkbox arrives already ticked on a fresh Login page, so every user gets
a persistent multi-week session unless they notice and untick it — the
opposite of the opt-in the label suggests.
Basis: code inspection (a malformed template attribute renders the box
ticked unconditionally) + observed on a running site. <sup>[f-a2](#fn-a2)</sup>

> **Reviewed — Jarda Kotěšovec, 2026-08-25**: confirmed 🐞 — unintended
> behaviour (the malformed attribute), and persistent sessions should be
> opt-in per OWASP session-management guidance, not opt-out. Fix: the box
> arrives unticked.

<a id="a3"></a>
**A3 — Raw code in the reset page's browser tab** · 🐞 · minor.
The set-a-new-password page (the emailed reset link's destination) is headed
"Reset Password", but the browser tab reads "user.login.resetPassword" — an
internal code shown where the page title belongs.
Basis: observed on a running site. <sup>[f-a3](#fn-a3)</sup>
Tracked as
[pkp/pkp-lib#13132](https://github.com/pkp/pkp-lib/issues/13132) (open as of
2026-08-25; also notes the same symptom on Administration → Jobs).

> **Reviewed — Jarda Kotěšovec, 2026-08-25**: confirmed 🐞. Fix: resolve the
> tab title to the translated "Reset Password" string. Already reported as
> pkp/pkp-lib#13132.

<a id="a4"></a>
**A4 — Second Login As offered mid-impersonation** · 🐞 · latent.
While an impersonation is already active — seeing this requires
impersonating a user who can themselves open Users & Roles or a workflow
screen, a Journal Manager or Editor, say — both the Users & Roles list and
the workflow Participants panel still offer "Login As" on other users'
rows, and choosing it starts a second impersonation. The chain does not
nest: one "Logout as" then restores the *intermediate* user — plainly
signed in, with no impersonation banner and no further "Logout as" offered
— so the operator's own account is unreachable from the screen; the only
way out is the plain sign-out address (copied from the user menu's "Logout"
entry before impersonating — Rule 15), which signs everything out. The end
state holds fewer rights than the operator started with, so nothing is
gained — the cost is a stranded, confusing session. The application's own
earlier screens establish the intended behavior: the previous generation of
these lists explicitly withheld "Login As" while an impersonation was
active, and the current screens lost that rule in their rebuild.
Basis: observed on a running site + code inspection. <sup>[f-a4](#fn-a4)</sup>

> **Reviewed — Jarda Kotěšovec, 2026-08-25**: confirmed 🐞 (upgraded from an
> open question). The legacy grids' guard is the intended behavior; the
> current screens must not offer "Login As" while an impersonation is
> active. Fix recommendation: enforce it server-side in the computed
> "can log in as" property so every screen inherits the rule at once.

<a id="a5"></a>
**A5 — No screen sets the "must change password" flag** · ❓ · user-visible.
The forced-change flow (Rule 11) is fully functional, but no current users
screen offers the flag that triggers it: a user row's "Edit" opens an
invite-style wizard with no such option. The only screen-driven path that
flags an account is the review stage's "Create New Reviewer" {OJS OMP},
which flags its newly created account automatically — so staff cannot
require a password change on an existing account. In OJS 3.4 the users
list's Edit User form offered exactly this checkbox on existing accounts;
the capability was dropped when that form was replaced by the invitation
wizard.
Question: bring the capability back (and on which screen), or retire it
deliberately? Lean: none recorded — the loss is verified fact, the
restoration is a product call.
Basis: observed on a running site + 3.4 code comparison.
<sup>[f-a5](#fn-a5)</sup>

> **Reviewed — Jarda Kotěšovec, 2026-08-25**: ❓ stands. Verified against
> 3.4: the Edit User form carried the "must change password" checkbox for
> existing accounts, so the capability loss is real — but whether to
> restore it is a product decision. Disposition: **to triage** with the
> team.

<a id="a6"></a>
**A6 — Lockout tells a correctly-authenticating user their password is wrong** · ✅ · latent.
With sign-in rate limiting enabled, exceeding the attempt limit changes
nothing on screen but a short delay, and inside the cool-down even the
correct password answers "Invalid username/email or password. Please try
again." This is deliberate design, not an oversight: the limiting stays
invisible so an attacker can never tell throttling from a wrong guess —
accepting that the account's real owner, mid-cool-down, briefly gets a
message that is untrue for them (the window self-clears within the
cool-down, 5 minutes by default).
Basis: observed on a running site + upstream design record.
<sup>[f-a6](#fn-a6)</sup>

> **Reviewed — Jarda Kotěšovec, 2026-08-25**: ✅ intended (was ❓). The
> concealment is the feature's documented design — see the introducing
> upstream issue (pkp/pkp-lib#12162, 2026-02); the brief mislead of the
> genuine owner is the accepted cost.

<a id="a7"></a>
**A7 — The address ending at the word "dashboard" answers a blank error page when signed out** · 🐞 · user-visible.
A signed-out visitor opening a dashboard address cut short at the word
"dashboard", with nothing after it —
a truncated bookmark, a hand-typed URL — gets an entirely blank page: a
server error with no content, where every other private address shows the
Login page and continues to the destination after sign-in (Rule 4). Every
deeper dashboard address behaves correctly. Nothing private is exposed —
the page is simply empty — but the visitor is left with no way forward.
Basis: observed on a running site. <sup>[f-a7](#fn-a7)</sup>

> **Reviewed — Jarda Kotěšovec, 2026-08-25**: confirmed 🐞. Ruling: signed
> out, the bare dashboard address must behave like every other private
> address — redirect to the Login page (destination preserved); choosing
> which dashboard variant to land on happens after the successful sign-in,
> as it already does for every other route.

<a id="a8"></a>
**A8 — Login As answers a blank server error in a half-resolved session** · 🐞 · minor.
When the browser carries a session the site can no longer fully resolve —
observed with a session that outlived a server-side database reset; the
pages themselves still render as signed-in — opening the Login As address
(from a row action or directly) answers an entirely blank server error: no
impersonation, no refusal, no way forward. A fresh sign-in makes the same
action work normally, and a signed-out visitor is properly redirected to
Login — only the half-resolved state crashes. Nothing private is exposed.
Since: 2026-08-25 · Basis: observed on a running site + code inspection.
<sup>[f-a8](#fn-a8)</sup>

> **Reviewed — Jarda Kotěšovec, 2026-08-25**: confirmed 🐞 (filed on
> review). Ruling: a session whose user cannot be resolved is treated like
> a signed-out visitor — redirect to Login, never a crash. Same family as
> the bare-dashboard error (A7): both are missing signed-out guards on
> older page routes.

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<a id="fn-a"></a>
**a** — Login page: `PKP\pages\login\LoginHandler` (`index`/`signIn` ops)
rendering `lib/pkp/templates/frontend/pages/userLogin.tpl`; ops `index`,
`signIn`, `signOut`, `lostPassword`, `requestResetPassword`, `resetPassword`,
`updateResetPassword`, `changePassword`, `savePassword`, `signInAsUser`,
`signOutAsUser`. **Chain check**: no `pages/login/` directory exists in
ojs, omp or ops — the handler and all its templates are fully shared
(positive evidence). Generic error `user.login.loginError`; disabled
messages `user.login.accountDisabled(WithReason)` — the reason is whatever
staff typed when disabling. Signed-in visitors: `Validation::isLoggedIn()` →
`sendHome()`. Live-probed 2026-07-31 (OJS deep; OMP/OPS spot): failure
message, sign-in and landings as stated; an empty required box is stopped by
the browser's own fill-this-field prompt before anything is sent. Captcha:
reCAPTCHA on login when `[captcha] recaptcha` + `captcha_on_login` (not
live-driven); ALTCHA per `altcha` + `altcha_on_login` /
`altcha_on_lost_password` (`FormValidatorReCaptcha` / `FormValidatorAltcha`)
— live-probed 2026-08-01 (OJS): the ALTCHA widget stays invisible and solves
itself at submit, so sign-in simply works; only a JavaScript-less submission
is refused, with the error quoted in Fields. Register link:
`{if !$disableUserReg}` in `userLogin.tpl` / `userLostPassword.tpl` —
live-probed 2026-07-31 on scratch contexts (OJS + OPS) and 2026-08-01
(OMP scratch press): disabling registration removes the link from both
pages, and the typed register address answers "This journal is currently
not accepting user registrations." (app-localized journal/press/server
wording) — all three apps observed.

<a id="fn-b"></a>
**b** — Landing: `LoginHandler::_redirectAfterLogin()` — with a context and
no `source`, any of admin/manager/sub-editor/author/reviewer/assistant roles
**held in that context** → `dashboard`; else `PKPPageRouter::getHomeUrl()`
(no context → site index; reader-only or no groups in the context → journal
index). Landing corrections live-probed 2026-08-01 (OJS, two-journal site):
a user whose roles all sit in another journal lands on the signed-into
journal's home page, and site-level sign-in lands on the site index even
for role-holders. The signed-in bounce (Rule 1) also covers the emailed
reset-link address — live-probed 2026-08-01 (all three apps): a signed-in
user opening it is sent home, never shown the reset form or the dead-link
page. Interrupted visit:
`Validation::redirectLogin()` appends `source` = the requested address, and
`signIn` redirects to any `source` that is a relative path; `loginMessage`
renders above the form. Last-login: `Validation::registerUserSession()` sets
`dateLastLogin`. Live-probed 2026-07-31 (all three apps): role-based
landings, the signed-in bounce off Login and lost-password, and the
interrupted visit — a held workflow address shows the plain Login page (no
visible mention of the pending destination) and continues to that
submission after sign-in.

<a id="fn-c"></a>
**c** — Remember: `Validation::login(..., $remember)` → Laravel
`Auth::attempt($credentials, $remember)`; recaller-cookie lifetime
`[security] remember_me_lifetime` (days, default 30, absolute from login —
`PKPContainer` session config); idle lifetime `[general] session_lifetime`
(days, default 7); `[security] session_expire_on_close` empties the cookie
lifetime. Checkbox markup: `userLogin.tpl` `input#remember` (finding A2).
Live-probed 2026-08-01 (OJS): signed in with the box unticked, the sign-in
cookie carries a dated expiry equal to the install's idle lifetime (30 days
under the test configuration's `session_lifetime`), not a browser-session
expiry — so it survives browser close; ticked, a separate `remember_web_*`
cookie (30 days) appears alongside the same session cookie. With
`session_expire_on_close` unset (the default), nothing ends at browser close
in either case — the earlier gloss "extends the session past closing the
browser" was wrong and is corrected as of this probe.

<a id="fn-d"></a>
**d** — `LoginHandler::signOut()` → `Validation::logout()`: invalidates the
session, then stores the departed user's username and email in the fresh
anonymous session; `LoginHandler::index()` prefills the form from those
values — live-probed 2026-07-31 (OJS, OMP) and 2026-08-01 (OPS): the box
shows the account's email address, even when sign-in had used the
username. The ends-only-this-browser clause driven live 2026-08-01 (OJS,
two browser contexts): signing out in one left the other's session of the
same account signed in. Redirect: back to the `login` page. Menu wiring:
`TopNavActions.vue` + `useUserAuth.getLogoutUrl()` — impersonating, the menu
link becomes `login/signOutAsUser` labeled `user.logOutAs` (Rule 15); there
is no second, plain-logout entry while impersonating.

<a id="fn-e"></a>
**e** — `LoginHandler::lostPassword()` / `requestResetPassword()`:
confirmation `user.login.lostPassword.confirmationSent` on the generic
message page with back-link "Login"; mail sent via
`PKP\mail\mailables\PasswordResetRequested` (template key
`PASSWORD_RESET_CONFIRM`, from the site's contact, variable
`passwordResetUrl`; observed subject "Password Reset Confirmation").
Live-probed 2026-07-31: the confirmation sentence is identical whether or
not the address matches an account; delivery confirmed in all three apps. **Chain check**: the OPS `classes/mail/Repository::map()`
override (which omits some base mailables) *includes*
`PasswordResetRequested`, and all three apps seed the
`PASSWORD_RESET_CONFIRM` template in `registry/emailTemplates.xml` — no
app-side divergence. Rate limiting: `PKP\security\RateLimitingService`,
keyed IP+identifier, enabled by the site setting (note n), generic-response
by design.

<a id="fn-f"></a>
**f** — Link: `login/resetPassword/{username}?confirm={hash:expiry}` built
by the `PasswordResetUrl` mail trait;
`Validation::generatePasswordResetHash()` — HMAC over username + password
hash + last-login + expiry with `[security] salt`, expiry = now +
`reset_seconds` (default 7200). Any of password change or a new sign-in
changes the inputs, killing outstanding links (Rule 8). Form:
`PKP\user\form\ResetPasswordForm` (`user/userPasswordReset.tpl`, fields
"New password"/"Repeat new password", `FormValidatorPassword` = min length +
match + Laravel `uncompromised()` when the site setting is on). Invalid hash
→ `displayInvalidHashErrorMessage()` (`user.login.lostPassword.invalidHash`);
unknown username → redirect to `lostPassword`. Success message
`user.login.resetPassword.passwordUpdated`; `Auth::logoutOtherDevices()`
ends the account's other sessions; no session is created for the resetter.
Live-probed 2026-07-31: tampered, expired and already-used links all answer
the invalid-link page; an unknown-username link lands on the lost-password
form; saving leaves the user signed out and the new password works
(default-install hint under the field: "The password must be at least 6
characters."). Browser-tab title defect on this form: finding A3.

<a id="fn-g"></a>
**g** — Flag: `user.mustChangePassword`. No current users screen exposes it
(finding A5); the review stage's Create New Reviewer form
(`CreateReviewerForm`) sets it on the account it creates and emails a
generated password. `LoginHandler::signIn()` — a flagged user's successful
credential check immediately logs the fresh session out again and redirects
to `changePassword/{username}`; `PKP\user\form\LoginChangePasswordForm`
(`user/loginChangePassword.tpl`, instructions
`user.login.changePasswordInstructions`; checks: current password via
`Validation::checkCredentials()`, min length, match); `savePassword` clears
the flag, calls `Auth::logoutOtherDevices()`, signs the user in
(`Validation::login`) and `sendHome()`s them. Live-probed 2026-07-31 (OJS;
OMP byte-identical): the divert, the wrong-current-password error verbatim,
sign-in on completion (a reviewer lands on their reviewer dashboard) and a
normal next sign-in; the submit button is labeled "OK".

<a id="fn-h"></a>
**h** — Ops `signInAsUser/{userId}` and `signOutAsUser`
(`LoginHandler::authorize()`: `RoleBasedHandlerOperationPolicy` — Manager or
Site Administrator — for `signInAsUser`). Reach test:
`Validation::getAdministrationLevel(target, current)` must be
`ADMINISTRATION_FULL` — target is a site admin → PROHIBITED for everyone;
current is site admin → FULL; else current needs the Manager role and the
target's every group must sit in the manager's managed contexts (a
cross-context holding → PARTIAL/PROHIBITED). Denial page: error template
with `manager.people.noAdministrativeRights` (bullet list of causes),
back-link to `management/settings/access`. Self-impersonation is refused in
both the guards and the handler. Session switch:
`PKPSessionGuard::signInAs()` — stores the original user id as
`signedInAs`, migrates the session to the target, and stops any elevated
(Confirm Access) window; `signOutAs()` restores. Live-probed 2026-07-31
(row menus identical in all three apps): Login As present on an author's
row for the administrator and the manager, absent on one's own row and —
for the manager — on a Site Administrator's row; the cross-journal case
hides the row action, and the typed address answers the denial page, whose
back link is labeled "All Enrolled Users".

<a id="fn-i"></a>
**i** — Offering surfaces and their guards, all confirming with
`grid.user.confirmLogInAs` / title `grid.action.logInAs` ("Login As"):
(1) Users & Roles rows — `UserAccessManager` (`useUserAccessManagerConfig`:
offered when not the current user and the row's server-computed permission
allows; the server computes it per note h; no impersonation-active check —
finding A4); redirect `login/signInAsUser/{id}` with no return address, so
it lands per Rule 3.
(2) Workflow Participants panel rows — `ParticipantManager`
(`Actions.PARTICIPANT_LOGIN_AS`, guard `participant.canLoginAs`); redirects
into the same submission as the target (authors → the My Submissions
workflow view; editorial roles → the editorial one).
(3) Reviewers table rows {OJS OMP} — `ReviewerManager`
(`Actions.REVIEWER_LOGIN_AS`, guard `reviewAssignment.canLoginAs`); plain
redirect, landing per Rule 3. OPS ships no reviewer surface (no review
stage), so this offering simply does not exist there.
(4) Administration → Hosted Journals → journal settings → Users tab —
legacy `UserGridRow` LinkAction `logInAs`, guarded by the note-h reach test
**and** `!Validation::loggedInAs()`; mid-impersonation the grid itself is
unreachable (the impersonated non-administrator fails Administration's role
gate — live-probed 2026-07-31), so that extra guard is not observable on
screen (finding A4).
Live-probed 2026-07-31 across surfaces: the confirmation dialog is
verbatim-identical everywhere in all three apps; every Participants-panel
row but the viewer's own offers the action; the Reviewers table carries it
on OJS and OMP and does not exist on OPS (the Participants panel present
there as the positive control); the hosted-journals grid offers it on all
three. Impersonating the author lands on the author's own view, which
renders no Participants panel — that exit is the user menu (scenario 8).

<a id="fn-j"></a>
**j** — Indicators: `TopNavActions.vue` — the impersonator's own initials
as the muted base avatar with the target's `InitialsAvatar` overlaid
(`is-warnable`; DOM-verified 2026-08-01 against the component's bindings),
menu text
`manager.people.signedInAs` ("You are currently logged in as {$username}")
+ `user.logOutAs` ("Logout as {$username}"). Participants panel:
`ParticipantManager.vue` renders a warnable "Logout as" list entry when
`isUserLoggedInAs`; `useUserAuth.getLogoutAsUrl()` returns to the same
submission when a workflow page is open, else plain `signOutAsUser` →
home. Live-probed 2026-07-31: the menu shows the impersonated account's
username ("You are currently logged in as author.alex" / "Logout as
author.alex" while the administrator impersonated author.alex); the
Participants-panel entry shows the impersonated user's full name ("Logout
as Ravi Section Editor"); no plain Logout entry exists alongside. Typing
the plain sign-out address mid-impersonation ends the whole session — the
browser lands signed out on the Login page (Rule 15).

<a id="fn-k"></a>
**k** — Gate: `[security] password_timeout` (minutes; commented out/0 =
off) → `Validation::isReauthenticationRequired()`;
`ReauthenticationRequiredPolicy` is attached to **every** Administration op
except the confirm pair, and redirects to `admin/confirmAccess` with the
interrupted address as `source` (POST-ish requests flagged
`isActionRequest`). Window: `PKPSessionGuard::isElevatedSessionActive()` —
a session timestamp younger than the window, refreshed on each
Administration request while inside it; only site admins can hold it.
Form: `PKP\user\form\ConfirmPasswordForm` (`user/confirmPassword.tpl`,
heading `user.confirmAccess` "Confirm Access", description
`user.confirmAccess.description`); wrong password re-renders with
`user.login.loginError`. Submit: `AdminHandler::confirmAccessSubmit()` —
starts the window, redirects to `source` (relative paths only; foreign
hosts → home), and on `isActionRequest` raises the
`user.lastAction.incomplete` notice. No `source` → `redirectHome` before
the form is ever rendered; opening the bare address live-checked
2026-08-01 (window active — gate off, all three apps): a straight
redirect into Administration, no form shown. Cancel:
referer, but never an `/admin` address (loop guard) — falls back to the
site index. Impersonation: `signInAs()` calls `stopElevatedSession()`.
Live-probed 2026-08-01 (OJS deep; OMP gate + entry): the gate on entry,
Cancel leading out, the wrong-password generic error, entry on the correct
password, the re-prompt after idling past the window, and the interrupted
action — verified not executed — with the last-action notice on arrival;
the signed-in line shows the administrator's full name; a Journal Manager
typing Administration addresses gets the role denial, never the gate.
**Chain check**: no app has a `pages/admin/` directory —
`PKP\pages\admin\AdminHandler` is fully shared.

<a id="fn-l"></a>
**l** — `PKPUserHandler::authorizationDenied()` (redirect target of
`PKPPageRouter`'s denial path): signed out → `Validation::redirectLogin()`;
signed in → generic message page rendering the denial sentence (locale key
passed as `message`, sanitized to key characters). **Chain check**: each
app subclasses `PKP\pages\user\PKPUserHandler` as `APP\pages\user\UserHandler`
— OJS adds subscription/payment ops (owned by *Subscriptions & open access
control*), OMP adds nothing, OPS overrides only an incomplete-setup check;
none touches `authorizationDenied` or the ops in this spec. Live-probed
2026-07-31 (all three apps): the signed-in denial reads "The current role
does not have access to this operation."; signed out, the same address
shows the plain Login page and continues to the requested screen after
sign-in.

<a id="fn-m"></a>
**m** — `config.inc.php` `[security]`: `force_ssl`, `force_login_ssl`
(login pages redirect to https when set; `signIn` bounces back to http
after, when only login is forced), `session_check_ip` (default On —
`PKPAuthenticateSession` middleware compares the session's login IP each
request and logs out on mismatch), `encryption` (legacy hash migration —
old md5/sha1 hashes are transparently re-hashed on successful login),
`session_expire_on_close`, `remember_me_lifetime`, `salt`,
`api_key_secret` (API keys — *User profile*), `reset_seconds`,
`allowed_html`/`allowed_title_html` (near-infrastructure sanitizer lists),
`allow_plugin_install`/`plugin_gallery_urls` (consumed by *Plugins
management*), `password_timeout`. `[general] session_lifetime` = idle days.
Sessions live server-side in the database; the expire-sessions tool is
`AdminHandler::expireSessions()` (*System administration & jobs*).

<a id="fn-n"></a>
**n** — Administration → Site Settings security form
(`PKPSiteSecurityForm`): `minPasswordLength`,
`passwordUncompromisedEnabled` (Laravel `Password::uncompromised()` —
queries the external haveibeenpwned.com API; outbound HTTP fails fast at
the test config's dead-port `[proxy]`, so the check cannot pass in the
e2e env),
`rateLimitEnabled` + `rateLimitMaxAttempts` (default 5) +
`rateLimitDecaySeconds` (default 300) — read by `RateLimitingService`
(note e). The form itself is the *Site settings* feature's. On-screen
labels (probed 2026-08-01): group "Rate Limiting", checkbox "Enable rate
limiting", fields "Maximum attempts" (pre-filled 5) and "Lockout duration
(seconds)" (pre-filled 300). Correct-password refusal inside the
cool-down: finding A6.

<a id="fn-s"></a>
**s** — Scenario seeding: the seeded test journal/press/server
(`publicknowledge`) and roster accounts (passwords = username doubled):
scenario 1 `editor.diana`; 2–3 any roster account / `editor.diana`; 4–5
`author.alex` with mail observed in the test mail catcher (throwaway
recipient addresses when creating scratch users); 6 a scratch reviewer
created via Create New Reviewer on a scratch submission in review — the
generated password is read from the test mail catcher (never flag a shared
roster account — cached sign-ins of other tests would break); 7 `admin`
impersonating `author.alex`; 8 `editor.diana` on a scratch submission with
a roster section editor assigned as participant via the workflow Assign
modal and `author.alex` as author, reviewer variant with `reviewer.julia`;
9 requires `password_timeout` set in `config.test.inc.php` for the run.
Scenario 4's account caveat: resetting a roster password must be undone or
done on a scratch user for the same reason as 6.

<a id="fn-a1"></a>
**f-a1** — `maxlength="32"` hardcoded on the password inputs of
`userLogin.tpl`, `confirmPassword.tpl`, `loginChangePassword.tpl` (username
and password), `userPasswordReset.tpl`; no matching cap exists at
registration/reset time beyond it (the reset form itself caps typing at 32,
so an over-long password can only arise from other paths — e.g. seeded or
imported accounts, or pre-cap registrations). Live-probed: typing 34
characters leaves 32 in the box and sign-in fails with the generic error
(OJS and OMP, 2026-07-31); the same cap observed on the reset form
(2026-07-31) and the Confirm Access box (2026-08-01).

<a id="fn-a2"></a>
**f-a2** — `userLogin.tpl`: `<input type="checkbox" name="remember" ...
checked="$remember">` — the attribute value is literal text, not a template
substitution, so the `checked` attribute is always present and the browser
renders the box ticked regardless of any prior choice. Live-confirmed
2026-07-31: pre-ticked on a fresh Login page in OJS, OMP and OPS.

<a id="fn-a3"></a>
**f-a3** — Live-probed 2026-07-31 (OJS) and 2026-08-01 (OPS; the form's
template is shared — now observed on both, not just inferred): the
browser tab on the set-a-new-password form shows the raw locale key
`user.login.resetPassword` while the page heading renders "Reset Password"
— the page-title string reaches the tab untranslated
(`user/userPasswordReset.tpl`).

<a id="fn-a4"></a>
**f-a4** — The Vue Users & Roles config (`useUserAccessManagerConfig`)
offers the action when `getCurrentUserId() !== user.id && user.canLoginAs`;
the server-computed `canLoginAs`
(`user/maps/Schema::getPropertyCanLoginAs`) checks only the note-h reach
test and never consults the impersonation state. The legacy grids all
guard it — `UserGridRow`, `StageParticipantGridRow.php:115` and
`ReviewerGridRow.php:180` require `!Validation::loggedInAs()` — but none
of them is the live surface anymore. Live-probed 2026-07-31: the
offering appears mid-impersonation in all three apps; the second
impersonation was driven on OJS. Non-nesting driven 2026-08-01 (OMP;
`PKPSessionGuard::signInAs()` is shared): the second call overwrites the
stored `signedInAs` id with the intermediate user's, so `signOutAsUser`
restores the intermediate user and no impersonation state remains.
Re-probed 2026-08-25 (OJS): the Vue Participants panel offers "Login As"
on another participant's row mid-impersonation too (its action config,
`useParticipantManagerConfig`, gates only on `participant.canLoginAs`).
Fix per review: return false from `getPropertyCanLoginAs` when
`Validation::loggedInAs()` is active, so every Vue consumer inherits the
legacy rule.

<a id="fn-a5"></a>
**f-a5** — Flag `user.mustChangePassword`. Live-probed 2026-07-31 (OJS,
OMP): the users list row's "Edit" opens the invite-style wizard, which
carries no password-related control; no other users-screen path offers
one. The review stage's Create New Reviewer form (`CreateReviewerForm`)
sets the flag on the account it creates and mails a generated password
(driven live — scenario 6's seeding path). The legacy user-details form
(`UserDetailsForm` + `userDetails.tpl`) still carries the checkbox, but no
current screen links to it. 3.4 comparison (2026-08-25, review): in OJS
3.4.0 the users grid's Edit User (`UserGridHandler::editUser` →
`UserDetailsForm` → `common/userDetails.tpl`) rendered the checkbox for
EXISTING accounts — `readUserVars` includes `mustChangePassword`
unconditionally and `execute` writes it for any user; the true-by-default
initData applies to new accounts only — establishing the regression.

<a id="fn-a6"></a>
**f-a6** — Live-probed 2026-08-01 (OJS, scratch user, site setting
temporarily enabled at 3 attempts / 300 s): attempts beyond the limit —
including one with the correct password — answer `user.login. Introduced
2026-02 by the site-security rate-limiting feature (upstream issue
pkp/pkp-lib#12162); defaults 5 attempts / 300 s, keyed per username+IP
(IPv6 /64), configurable in Site Settings → Security.loginError`
verbatim, with only a 2–5-second artificial delay
(`RateLimitingService::applyRateLimitDelay()`) distinguishing them; the
limit is keyed per username+address.

<a id="fn-a7"></a>
**f-a7** — Live-probed 2026-08-01 (OJS, OMP, OPS — identical): signed out,
`{journal}/dashboard` with no operation after it answers HTTP 500 with an
empty body; every `/dashboard/{op}` address redirects to Login with the
destination held as `source` and resumes correctly after sign-in. Cause
pinned 2026-08-25 (review): the bare address resolves "which dashboard is
home" via `PKPPageRouter::getHomeUrl()`, which starts from
`Auth::user()->getId()` with no signed-out guard — the only
anonymous-reachable caller; every other caller runs just after sign-in.
Fix per ruling: guard `getHomeUrl()` (no user → the login redirect), so
variant resolution stays post-login.

<a id="fn-a8"></a>
**f-a8** — Live-probed 2026-08-25 (OJS): with an aged storage-state session
(cookies from before a test-database reset; authenticated pages still
render), `GET login/signInAsUser/{id}` answers HTTP 500 —
`LoginHandler::signInAsUser` passes `$sessionGuard->getUserId()` (null in
that state) into the int-typed second parameter of
`Validation::getAdministrationLevel()`. Controls the same day: anonymous
GET → 302 to Login; freshly signed-in session → impersonation proceeds
(200 → dashboard). Fix per ruling: treat an unresolvable session user as
signed out (redirect to Login) before the administration-level check.

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Login page + form | `{journal}/login` (form posts to `login/signIn`) | ROUTE-016 · AFFU-001..008 |
| Sign out | user menu → `login/signOut` | ROUTE-016 |
| Lost password | `login/lostPassword` → POST `login/requestResetPassword` | AFFU-036..039 |
| Emailed reset link | `login/resetPassword/{username}?confirm={hash}` → form POSTs `login/updateResetPassword` | AFFU-040..043 · MAIL-030 |
| Forced password change | `login/changePassword[/{username}]` → POST `login/savePassword` | AFFU-044..048 |
| Login As / return | `login/signInAsUser/{id}` · `login/signOutAsUser` | ROUTE-016 |
| Login As — Users & Roles row | Settings → Users & Roles → user row menu | AFFM-105 |
| Login As — Participants panel | workflow → Participants row action (+ "Logout as" entry) | AFFW-470, 473, 467 |
| Login As — Reviewers table {OJS OMP} | workflow Review stage → reviewer row action | AFFW-499 |
| Login As — hosted-journal users grid | Administration → Hosted Journals → journal → Users tab | AFFM-209 |
| Confirm Access gate | `admin/confirmAccess` → POST `admin/confirmAccessSubmit` (ops cited; the admin handler is owned by *System administration & jobs*) | AFFM-191 · AFFU-049..052 |
| Access denied page | `user/authorizationDenied?message=…` (op cited; the user-page handler is owned by *User profile*) | — |
| Config, security section | `config.inc.php` `[security]` | SET-052 |

## Reference — code anchors

- `lib/pkp/pages/login/LoginHandler.php` — every op in this spec
- `lib/pkp/classes/security/Validation.php` — login/logout, reset hashes, administration levels, `canUserLoginAs()`
- `lib/pkp/classes/core/PKPSessionGuard.php` — sessions, sign-in-as, elevated (Confirm Access) window
- `lib/pkp/classes/security/RateLimitingService.php` · `classes/middleware/PKPAuthenticateSession.php`
- `lib/pkp/classes/user/form/ResetPasswordForm.php` · `LoginChangePasswordForm.php` · `ConfirmPasswordForm.php`
- `lib/pkp/classes/security/authorization/ReauthenticationRequiredPolicy.php` · `pages/admin/AdminHandler.php` (confirm ops)
- `lib/pkp/classes/mail/mailables/PasswordResetRequested.php` (+ `mail/traits/PasswordResetUrl.php`)
- Templates: `lib/pkp/templates/frontend/pages/userLogin.tpl`, `userLostPassword.tpl`; `lib/pkp/templates/user/userPasswordReset.tpl`, `loginChangePassword.tpl`, `confirmPassword.tpl`
- UI library: `src/components/TopNavActions/TopNavActions.vue` · `src/composables/useUserAuth.js` · `src/managers/{UserAccessManager,ParticipantManager,ReviewerManager}/`
- Legacy grid: `lib/pkp/controllers/grid/settings/user/UserGridRow.php`
- App divergence points checked: none in `pages/login` or `pages/admin` (no app subclasses); `pages/user/UserHandler.php` in each app (no login-related overrides)
