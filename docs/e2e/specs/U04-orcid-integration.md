---
name: orcid-integration
scope: Users and contributors connect verified ORCID iDs; the journal requests verification by email and deposits published works (and reviews) to ORCID records
apps: [ojs, omp, ops]
shared: pkp-lib
status: verified
atlas-claims: [AFFW-502, AFFM-117, AFFM-222, AFFU-065, AFFU-066, AFFU-099, AFFU-100, AFFU-101, AFFU-102, AFFU-103, AFFU-104, AFFU-105, AFFU-106, AFFU-107, AFFU-108, AFFU-109, AFFU-110, ROUTE-021, API-029, MAIL-027, MAIL-028, MAIL-029, JOB-017, JOB-018, JOB-019, JOB-020, JOB-033, JOB-034, PLUG-021]
---

# ORCID integration

> Conventions (markers, badges, footnotes): [Reading a spec](GLOSSARY.md#reading-a-spec).

## Purpose

An ORCID iD is a researcher's persistent public identifier. This feature lets a
journal collect iDs that are **verified** — connected by their owners through
ORCID's own sign-in — rather than typed in on trust. Users connect an iD to
their account from their profile or while registering; editors ask a
submission's contributors by email to verify theirs; and a journal that holds
ORCID **member** credentials writes back: when an article is published, its
record is added to each verified contributor's ORCID profile, and on a journal a
completed peer review can be added to the reviewer's profile as a review
contribution. Everything is switched on per journal (or once for the whole
site) with API credentials issued by ORCID.

## Actors & permissions

"Verified" below means the person completed ORCID's sign-in for this install, so
the iD carries an access token; an iD merely typed or imported is
**unauthenticated** and displays as such. Screens that host an ORCID control
(the profile, the workflow's Contributors list, the Reviewers table) keep their
own reachability rules in their own features — this table covers only the ORCID
capability on each.

| Action | Who may — and when |
|--------|--------------------|
| **Enable & configure ORCID for one journal** | • Site Administrator; Journal Manager — the "ORCID" tab on Settings → Users & Roles (Rule 1); locked read-only while the site-wide configuration is active (Rule 3) <sup>a</sup> |
| **Enable & configure ORCID site-wide** | • Site Administrator — the "ORCID" tab on Site Settings; the tab exists only while the install hosts more than one journal (Rule 2) <sup>b</sup> |
| **Connect / authorize own iD** | • Any signed-in user — profile, Identity tab (Rules 5–6)<br>• Any visitor — a journal's registration page (Rule 7); not offered on the site-level registration page <sup>c</sup> |
| **Remove own iD** | • The user themselves — "Delete" beside the connected iD on the Identity tab (Rule 6c) <sup>d</sup> |
| **Request a contributor's verification by email** | • Site Administrator; Journal Manager; Section Editor assigned to the submission — "Request verification" on the contributor's ORCID iD field (Rule 8)<br>• Author of the submission — the same button, from the submission wizard's Contributors step (the contributor list on the author's own dashboard is read-only)<br>• ⚠ [A5](#a5) an Assistant who can edit the contributor is offered the same button; the request is refused, yet the field reports it as sent <sup>e</sup> |
| **Remove a contributor's iD** | • The same roles as the row above — "Delete" on the contributor's ORCID iD field (Rule 8a) ⚠ [A5](#a5) <sup>e</sup> |
| **Verify via the emailed link** | • Whoever holds the emailed authorization link — works signed out; the link is single-use (Rule 9) <sup>f</sup> |
| **Send a review to ORCID** {OJS} | • Site Administrator; Journal Manager; Section Editor — "Send Review To ORCID" on the reviewer's row (Rule 12)<br>• ⚠ [A1](#a1) a Press Manager is offered the same action, though a press deposits nothing <sup>g</sup> |
| **Read the public ORCID pages** | • Anyone, signed in or out — the "What is ORCID?" page and the verification landing page, reached from links or by URL (Rules 9–10) <sup>f</sup> |

## Fields & validation

Journal ORCID settings ("ORCID" tab, Settings → Users & Roles):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Enable ORCID functionality" | no | Unchecking hides every ORCID surface for this journal (Rule 4). Checked and locked while the site-wide configuration is active (Rule 3) <sup>a</sup> |
| "ORCID API" | yes (when enabled) | Four choices: Public / Public Sandbox / Member / Member Sandbox. Member unlocks deposits (Rule 11); Sandbox points every ORCID link at ORCID's test service <sup>a</sup> |
| "Client ID" / "Client Secret" | yes (when enabled) | Credentials issued by ORCID; the secret is entered masked. Shown read-only (secret fully masked) while configured site-wide <sup>a</sup> |
| "City" | no | Sent as the review venue on review deposits; a review deposit silently requires both this and the journal's country to be set (Rule 12) <sup>g</sup> |
| "Send e-mail to request ORCID authorization from authors when an article is accepted ie. sent to copy editing" | no | Turns on the automatic author emails of Rule 13 ⚠ [A6](#a6) |
| "ORCID request log" | no | Error (default) or full logging of ORCID traffic, for the journal's technical staff <sup>a</sup> |

Site-wide ORCID settings carry only the first three rows (enable, API, Client
ID/Secret) — city, the email toggle and the log level remain per-journal. <sup>b</sup>

Contributor's ORCID iD field (add/edit contributor, Contributors list):

| State | What the field shows |
|-------|----------------------|
| No iD | "Request verification" button → confirmation "Would you like to send an email to this author requesting they verify their ORCID?" (for a contributor being added, the dialog adds "The email will be sent once the author has been created."); after sending, the button is disabled and reads "ORCID Verification has been requested!", with a "Resend Verification Email" link beside it (Rule 8) <sup>e</sup> |
| Unauthenticated iD | The iD as a link, the hollow ORCID icon, and the note "This ORCID has not been verified. Please remove this unverified ORCID and request verification from the user/author directly." plus "Delete" <sup>e</sup> |
| Verified iD | The iD as a link with the solid ORCID icon, plus "Delete" (confirmation before removal) <sup>e</sup> |

## Rules & state

1. **Per-journal switch.** ORCID is off until a Journal Manager enables it on
   the "ORCID" tab and saves credentials; every rule below assumes it is on for
   the journal at hand. The tab is always present, enabled or not. <sup>a</sup>
2. **Site-wide switch (multi-journal installs).** On an install hosting more
   than one journal, the Site Administrator's Site Settings carry an "ORCID"
   tab that enables ORCID **for every journal at once** with one set of
   credentials. On a single-journal install the tab is absent ⚠ [A9](#a9). <sup>b</sup>
3. Site-wide configuration overrides the journals: each journal's tab then
   shows "Enable ORCID functionality" checked and locked, the API type and
   Client ID read-only, the secret masked, and a note ending "Contact your
   site administrator to disable ORCID functionality or change these
   credentials." City, the email toggle and the log level stay editable per
   journal. <sup>b</sup>
4. **Disabled means invisible.** With ORCID off for a journal, the profile's
   Identity tab shows no ORCID field at all, the registration page shows no
   ORCID block, and the contributor form has no ORCID iD field. <sup>c</sup>
5. **Connecting from the profile.** On the Identity tab of an ORCID-enabled
   journal an ORCID block appears: with no iD, a "Create or Connect
   your ORCID iD" button; with an unauthenticated iD, the iD as a hollow-icon
   link suffixed "(unauthenticated)" and an "Authorize and Connect your ORCID
   iD" button; with a verified iD, only the iD as a solid-icon link and its
   "Delete" button — the connect button and the "What is ORCID?" link beside
   it are gone. The connect/authorize button opens ORCID's sign-in in a popup
   window; completing it stores the verified iD and reloads the
   tab. <sup>c</sup> <sup>h</sup>
6. Companions of the profile flow:
   6a. ⚠ [A4](#a4) the "What is ORCID?" link beside the button opens the same
   sign-in popup instead of the What-is-ORCID page it names. <sup>c</sup>
   6b. Denying access on ORCID's consent screen records the refusal; the
   stored iD and token, if any, are cleared. <sup>f</sup>
   6c. "Delete" beside a verified iD asks "Are you sure you want to remove this
   ORCID?"; confirming removes the iD at once — no separate save — and tells
   ORCID to cancel this install's access token. <sup>d</sup>
7. **Connecting while registering.** A journal's registration page offers the
   same "Create or Connect your ORCID iD" button at the top of the form;
   completing ORCID's sign-in fills the name, email, country and affiliation
   fields from the ORCID record and pins the iD to the form. Connecting is
   never required to register. ⚠ [A3](#a3) the account created afterwards
   holds the iD as unauthenticated — the new user is expected to press
   "Authorize and Connect" once more from their profile. <sup>i</sup>
8. **Contributor verification requests.** On the contributor form, "Request
   verification" emails that contributor an authorization link (Rule 14
   chooses the email). The button then reads "ORCID Verification has been
   requested!" with a "Resend Verification Email" link. For a contributor
   being added, the request is remembered and the email goes out when the
   contributor is saved.
   8a. "Delete" beside a contributor's iD (verified or not) removes it after
   confirmation and cancels its token at ORCID. <sup>e</sup>
9. **The emailed link and the verification landing.** The emailed
   authorization link leads to ORCID's sign-in; ORCID then returns the
   browser to the journal's "ORCID Authorization" page. Success shows the
   verified iD and "Your ORCID iD has been verified and successfully
   associated with the submission.", then returns the visitor to the journal
   front page after ten seconds. A used, stale or tampered link shows "Your
   ORCID iD could not be verified. The link is no longer valid."; an iD
   already attached to the submission answers "An ORCID iD was already stored
   for this submission."; ⚠ [A2](#a2) a
   visitor who pressed Deny at ORCID gets a raw placeholder where the
   explanation should be. Every failure closes with "Please contact the
   journal manager with your name, ORCID iD, and details of your
   submission." — ⚠ [A8](#a8) "journal manager" verbatim on presses and
   preprint servers too. <sup>f</sup>
10. **The What-is-ORCID page.** Every journal exposes a public "What is
    ORCID?" page (linked from the ORCID emails; also reachable by URL)
    explaining the iD and how the journal uses it; its "How and why" section
    differs between public-API and member-API journals. <sup>f</sup>
11. **Member API = deposits.** With a Member (or Member Sandbox) API
    configured, publishing an article adds it as a "work" to the ORCID record
    of every contributor holding a verified iD with a live deposit permission
    — "live" meaning not expired and not revoked; no screen shows liveness,
    the deposit simply skips the others — publication is the trigger; nothing
    is deposited before it. A
    contributor verified under the public API holds no deposit permission:
    the deposit pauses and the journal emails them a request to re-authorize
    with the wider permission (Rule 14); completing that link finishes the
    deposit. Works deposited once are updated in place on later publishes.
    [OMP1](#omp1) presses request and verify iDs identically but deposit
    nothing. <sup>j</sup>
12. **Review deposits {OJS}.** On a journal with the member API, a completed
    review can be added to the reviewer's ORCID record as a review
    contribution: the Reviewers table offers "Send Review To ORCID" on the
    row of a reviewer with a verified iD, behind a confirmation ("Send this
    review to the reviewer's ORCID?"). Confirming closes the dialog with no
    on-screen message either way — the deposit runs in the background.
    Publishing the article also deposits its completed reviews. A review deposit
    additionally requires the ORCID settings' City and the journal's country
    to be set — otherwise it is skipped without message. The row action's
    presence depends on the reviewer's verified iD alone — the member API gates
    only the deposit, so a public-API journal offers the action too, and its
    confirm deposits nothing. ⚠ [A1](#a1) the row action is offered before
    the review is complete, though nothing is deposited for an incomplete
    review. <sup>g</sup>
13. **Automatic author emails {OJS OMP}.** With the settings tab's email
    toggle on, recording the editorial decision Accept (or Skip Review) emails
    every contributor who does not yet hold a live verified iD the
    verification request of Rule 8 ⚠ [A6](#a6). ⚠ [OPS1](#ops1) a preprint
    server shows the toggle but has no accepting decision to trigger it. <sup>k</sup>
14. **Which email goes out.** Under a public API the request email is "Submission
    ORCID" (asking the contributor to connect their iD); under a member API it
    is "Requesting ORCID record access" (asking for deposit permission); the
    re-authorization mail of Rule 11 is "Requesting updated ORCID record
    access". All
    three carry the personal authorization link and the What-is-ORCID link,
    and are sent from the journal's principal contact. ⚠ [OPS2](#ops2) on a
    preprint server none of the three appears on the Emails settings screen;
    the re-authorization mail, once listed nowhere, gained its journal and
    press rows upstream ([A7](#a7), resolved 2026-08-25). <sup>l</sup>
15. Other screens that surface ORCID follow the rules above: the invitation
    wizard's "Verify ORCID iD" step (described in
    [User invitations](U06-user-invitations.md)) appears only while ORCID is
    enabled; the reviewer-suggestion form collects a plain, unverified
    "ORCID iD" text field; and reviewer lists distinguish a verified iD
    (solid icon) from an unauthenticated one (hollow icon,
    "(unauthenticated)" suffix — described with the Reviewers table in
    [Reviewer assignment & management](U27-reviewer-assignment-and-management.md)). <sup>m</sup>
16. A preprint server's install additionally ships the retired ORCID Profile
    plugin from the pre-built-in era [OPS3](#ops3); the built-in integration
    this spec describes is what runs everywhere.

## Side effects

- **On "Request verification" / the Rule 13 decision** — one email to the
  contributor (Rule 14 chooses which), carrying their single-use
  authorization link. <sup>l</sup>
- **On completing an emailed authorization for a published article** — the
  work is deposited at once and the landing page adds "The submission has
  been added to your ORCID record."; for an article not yet published it
  says the record will be added on publication. <sup>f</sup>
- **On publication** — work deposits to every eligible contributor (Rule 11)
  and, on a journal, review deposits for the article's completed reviews
  (Rule 12); all in the background, with no notice on the publishing
  screen. <sup>j</sup>
- **On removing an iD** (own profile or a contributor's) — the install asks
  ORCID to cancel the matching access token in the background. <sup>d</sup>
- **ORCID request log** — traffic with ORCID is written to the application
  log at the level the settings tab chooses. <sup>a</sup>

## Settings that modify behavior

- **Enable ORCID functionality** (per journal, or site-wide on multi-journal
  installs) — the master switch; Rules 1–4.
- **ORCID API** — Public collects verified iDs only; Member also deposits
  (Rules 11–12); the two Sandbox variants aim every link and deposit at
  ORCID's test service instead of the real one.
- **Send e-mail to request ORCID authorization…** — the Rule 13 automatic
  emails; off by default.
- **City** + the journal's country (from the journal's masthead settings) —
  both required for review deposits (Rule 12).
- **ORCID request log** — logging verbosity only; no user-facing change.

## Cross-feature interactions

- **Profile — Identity tab** hosts the connect/authorize/delete controls
  (Rules 5–6); the tab itself and its other fields belong to the *User
  profile* feature.
- **Contributors list** (workflow and submission wizard) hosts the
  contributor ORCID field (Rule 8); the list's own mechanics belong to
  *Contributors & affiliations*.
- **Reviewers table** {OJS} hosts "Send Review To ORCID" (Rule 12); the
  table, its other row actions and its confirm-dialog chrome belong to
  [Reviewer assignment & management](U27-reviewer-assignment-and-management.md).
- **User invitations** — the accept wizard's "Verify ORCID iD" step and the
  send wizard's ORCID note are described in
  [User invitations](U06-user-invitations.md); they obey this spec's enablement
  rules.
- **Emails management** — the stored ORCID email templates are edited on the
  Emails settings screen (feature spec to come — *Emails management*); this
  spec owns the ORCID-specific gaps ⚠ [OPS2](#ops2) ⚠ [A7](#a7).
- **Publishing** — publication is the deposit trigger (Rule 11); the publish
  action itself belongs to the publishing feature of each app.

## Canonical scenarios

Common to all three apps; substitute vocabulary per the
[application glossary](GLOSSARY.md). Actors are named by role. No
scenario step completes ORCID's own sign-in; where a step opens the sign-in
popup, the scenario says inline what an offline install shows. Preconditions
marked "(seeded)" cannot be staged from any screen on an offline install —
their seeding recipes and accounts live in the scenarios' footnote, the one
part of the Footnotes tail a scenario runner needs. <sup>s</sup>

1. **Turn ORCID on for a journal** — Journal Manager: open Settings → Users &
   Roles, tab "ORCID". Tick "Enable ORCID functionality" — the API fields
   appear; pick "Public Sandbox", enter any placeholder Client ID and Client
   Secret — both must be filled in, but the save does not check them against
   ORCID — and save.
   Open your profile's Identity tab in another tab: an ORCID block with a
   "Create or Connect your ORCID iD" button has appeared. Untick and save
   again (control): the ORCID block is gone — the tab shows no ORCID field
   at all. <sup>s</sup>
2. **The connect offer, and the About link that doesn't go there** — any
   signed-in user on an ORCID-enabled journal: on the Identity tab, press
   "Create or Connect your ORCID iD" — a small popup window opens on an
   ORCID sign-in address while the profile tab stays put (on an offline
   install the popup shows a connection error — that is expected). Close it.
   Press the "What is ORCID?" link beside the button: ⚠ [A4](#a4) the same
   popup opens instead of the What-is-ORCID page. <sup>s</sup>
3. **Remove a connected iD from the profile** — a user whose account holds a
   verified iD (seeded): the Identity tab shows the iD as a link with the
   solid ORCID icon and a "Delete" button. Press Delete — "Are you sure you
   want to remove this ORCID?"; confirm. The iD is gone and the
   "Create or Connect your ORCID iD" button is back. <sup>s</sup>
4. **Ask a contributor to verify** — Journal Manager: on a submission's
   Contributors list, edit a contributor who has no iD. The ORCID iD field
   shows "Request verification"; press it — "Would you like to send an email
   to this author requesting they verify their ORCID?"; confirm — for an
   already-saved contributor the email leaves at once; only a contributor
   still being added waits for the save (Rule 8) — then save. The
   field now reads "ORCID Verification has been requested!". The
   contributor's mailbox holds the request email with a personal
   authorization link (leading to ORCID's site) and the What-is-ORCID link. <sup>s</sup>
5. **Remove a contributor's iD** — Journal Manager: edit a contributor whose
   iD is unauthenticated (seeded). The field shows the hollow-icon iD link,
   the not-verified warning, and "Delete". Press Delete and confirm: the iD
   is gone; the field offers "Request verification" again. <sup>s</sup>
6. **The public ORCID pages by URL** — signed-out visitor: type the journal's
   `/orcid/about` address — the "What is ORCID?" page renders with the
   journal's chrome. Type `/orcid/verify` — the "ORCID Authorization" page
   answers "Your ORCID iD could not be verified. The link is no longer
   valid." and "Please contact the journal manager with your name, ORCID iD,
   and details of your submission." ⚠ [A8](#a8) — a stale or truncated
   emailed link gets an explanation, never an error page. <sup>s</sup>
7. **A journal with ORCID off shows none of it** — visitor and Journal
   Manager on a scratch journal with ORCID disabled: the registration page
   has no ORCID block; a contributor form has no ORCID iD field; the
   profile's Identity tab shows no ORCID field at all (positive
   control: the same screens on the enabled journal show the Rule 5 and
   Rule 8 controls). <sup>s</sup>

App-specific:

8. **{OJS OMP} Accepting a submission asks the authors** — Journal Manager:
   with the ORCID email toggle on, record Accept on a submission in review.
   Each contributor without a verified iD receives the Rule 14 request email
   (observe the mailbox); with the toggle off (control), accepting a second
   submission sends nothing. <sup>s</sup>
9. **{OJS} Send a review to ORCID** — Journal Manager, on a journal
   configured with the Member Sandbox API — the API type gates only the
   background deposit, never the row action's presence (Rule 12): on a
   submission whose reviewer holds a verified iD (seeded), open the Reviewers
   table row menu: "Send Review To ORCID" is offered ⚠ [A1](#a1) even before
   the review is complete. After the review is complete, press it — "Send
   this review to the reviewer's ORCID?" — and confirm: the dialog closes
   with no message of any kind; the deposit itself happens in the background
   (Rule 12). <sup>s</sup>

## Findings register

Verdicts are the author's judgment (claude, 2026-08-07), unreviewed unless an
entry notes otherwise; the team settles them on spec review. Sorted 🐞 → ❓ → ✅
in the summary; the entries below are the source. Each entry opens with the
user-observable symptom; mechanism and evidence live in the entry's footnote.
An entry whose ID starts with OMP or OPS concerns only the app it names; and
because a claim tagged {OJS} (or {OJS OMP}) exists only in the app(s) the tag
names, a marker inside it may still report what the *other* apps wrongly show.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|-----------------------------|------|--------|--------|
| [A1](#a1) | "Send Review To ORCID" is offered before the review is complete, and confirms in silence | 🐞 | user-visible | — |
| [A2](#a2) | The ORCID-denied landing shows a raw placeholder instead of its message | 🐞 | user-visible | — |
| [A4](#a4) | "What is ORCID?" beside the connect button opens the sign-in popup, not the page | 🐞 | user-visible | — |
| [A5](#a5) | An Assistant's contributor-ORCID controls are refused by the server yet report success | 🐞 | user-visible | — |
| [A8](#a8) | The verification-failure page says "journal manager" on presses and preprint servers | 🐞 | minor | — |
| [OPS2](#ops2) | The ORCID request emails have no rows on the preprint server's Emails screen | 🐞 | user-visible | — |
| [A3](#a3) | An iD connected while registering lands on the account unverified | ❓ | minor | — |
| [A6](#a6) | The author-email toggle's label misdescribes when it fires | ❓ | minor | — |
| [A7](#a7) | The re-authorization email template is not editable in any app — resolved upstream for journals and presses (pkp/pkp-lib#13050); the preprint-server gap is [OPS2](#ops2)'s | ❓ | latent | rebase check (claude) 2026-08-25 |
| [A9](#a9) | The site tab's absence on single-journal installs rests on its switch-on condition, not observation | ❓ | minor | — |
| [OPS1](#ops1) | The author-email toggle exists on a preprint server that can never trigger it | ❓ | latent | — |
| [OMP1](#omp1) | A press requests and verifies iDs but deposits no works | ✅ | user-visible | — |
| [OPS3](#ops3) | A preprint server additionally bundles the legacy ORCID Profile plugin | ✅ | invisible | — |

### All apps

<a id="a1"></a>
**A1 — Send Review To ORCID ignores review completion, and answers with silence** · 🐞 · user-visible.
The Reviewers table offers "Send Review To ORCID" on any row whose reviewer
holds a verified iD, whatever the state of the review — the completion
condition the action was given never evaluates to false. Nothing is deposited
for an incomplete review (the background deposit re-checks completion), and
confirming shows no message either way (Rule 12), so an editor who uses it
early gets no sign the deposit never happened. A Press Manager is offered the
same action, dialog and silent close, though a press deposits nothing
([OMP1](#omp1)); a public-API journal offers it the same way, though its
deposit runs only under the member API (Rule 12).
Basis: probe + code. <sup>[f-a1](#fn-a1)</sup>

<a id="a2"></a>
**A2 — Denied-access landing renders a raw placeholder** · 🐞 · user-visible.
A contributor who follows their emailed authorization link and presses "Deny"
on ORCID's consent screen lands on the "ORCID Authorization" page, where the
explanation line renders as a raw `##orcid.authDenied##` token — the
translation key the page asks for does not exist in any locale. The refusal
itself is recorded correctly.
Basis: code (the denial leg needs ORCID's live consent screen, unreachable
from the test install). <sup>[f-a2](#fn-a2)</sup>

<a id="a3"></a>
**A3 — Registration-connected iD arrives unverified** · ❓ · minor.
A visitor who connects their ORCID iD while registering — signing in at ORCID
from the registration page's popup — gets an account that holds the iD as
**unauthenticated**: the profile then shows the hollow icon, the
"(unauthenticated)" suffix and an "Authorize and Connect your ORCID iD"
button asking them to do it again. The sign-in they already completed
granted this install a token that is simply not kept.
Question: should the registration connect count as verification?
Lean: yes — asking the same person to authorize twice reads as a gap, not a
choice.
Basis: code (the OAuth leg is unreachable from the test install). <sup>[f-a3](#fn-a3)</sup>

<a id="a4"></a>
**A4 — The About link opens the sign-in popup** · 🐞 · user-visible.
On the profile Identity tab and the registration page, the "What is ORCID?"
link beside the connect button opens the ORCID sign-in popup — the same as
the button — instead of the What-is-ORCID page it is labeled with. The page
itself works when reached by URL (Rule 10).
Basis: code; live confirmation pending. <sup>[f-a4](#fn-a4)</sup>

<a id="a5"></a>
**A5 — Assistant's refused ORCID controls report success** · 🐞 · user-visible.
An Assistant editing a contributor sees the same ORCID iD field as an editor
— "Request verification", "Delete" — but the server refuses both: no email
goes out, the iD stays. The screen still reports success — the field switches
to "ORCID Verification has been requested!" or shows the iD gone — with no
error anywhere. The roles allowed to use these controls omit the Assistant,
though Assistants may edit every other contributor field.
Basis: probe + code. <sup>[f-a5](#fn-a5)</sup>

<a id="a6"></a>
**A6 — The email toggle's label misdescribes its trigger** · ❓ · minor.
The setting reads "Send e-mail to request ORCID authorization from authors
when an article is accepted ie. sent to copy editing", but the emails fire
when the decision Accept **or Skip Review** is recorded — Skip Review is not
mentioned — and the grammar ("ie.") is off. Elsewhere the same setting is
described as acting "on publication", which it does not.
Question: which wording is the intended contract?
Lean: keep the accept-time behavior, fix the label.
Basis: probe + code. <sup>[f-a6](#fn-a6)</sup>

<a id="a7"></a>
**A7 — Re-authorization email not editable anywhere** · ❓ · latent.
The "Requesting updated ORCID record access" email (sent when a deposit needs
a wider permission, Rule 11) is delivered from a stored template that no app
lists on its Emails settings screen, so no manager can review or customize
it. Its two sibling ORCID emails are listed (on journals and presses).
Question: intended, or an omission from the emails roster?
Lean: omission — it shares its purpose and audience with the two listed ones.
Basis: probe + code. <sup>[f-a7](#fn-a7)</sup>

> **Resolved upstream — rebase check (claude), 2026-08-25**: the lean was
> right. pkp/pkp-lib#13050 adds the mailable to the shared map, so the
> template's row now appears wherever its two siblings do (journals and
> presses). A preprint server still lists none of the three — that gap
> remains [OPS2](#ops2)'s. Code-anchored, not live-probed (suites paused).

<a id="a8"></a>
**A8 — Failure page says "journal manager" on presses and preprint servers** · 🐞 · minor.
Every failure on the "ORCID Authorization" page closes with "Please contact
the journal manager with your name, ORCID iD, and details of your
submission." — on a press or a preprint server too, where the reader's
contact is a Press Manager or Preprint Server Manager, not a "journal
manager".
Basis: probe. <sup>[f-a8](#fn-a8)</sup>

<a id="a9"></a>
**A9 — Site ORCID tab on a single-journal install** · ❓ · minor.
Rule 2 says a single-journal install carries no "ORCID" tab in Site
Settings. The tab's presence on multi-journal installs is confirmed; its
absence rests on the condition that switches the tab on, since every
install at hand hosts several journals.
Question: is the tab really absent when the install hosts a single journal?
Lean: yes — the tab renders only when the install counts more than one
journal.
Basis: code. <sup>[f-a9](#fn-a9)</sup>

### OMP

<a id="omp1"></a>
**OMP1 — A press verifies iDs but deposits nothing** · ✅ · user-visible.
A press collects and verifies contributor iDs exactly like a journal — same
settings, same emails, same landing pages — but publishing a monograph adds
nothing to anyone's ORCID record, member API or not. The deposit machinery
declares monograph deposits unsupported for now.
Rationale: an acknowledged not-yet-built capability, not decay — the code
marks it as pending future work.
Basis: code. <sup>[f-omp1](#fn-omp1)</sup>

### OPS

<a id="ops1"></a>
**OPS1 — A dead email toggle on preprint servers** · ❓ · latent.
A preprint server's ORCID settings show the same "Send e-mail to request
ORCID authorization from authors…" toggle as a journal, but the decision
that triggers those emails (Accept) does not exist on a preprint server, so
the toggle changes nothing. Editors can still request verification per
contributor by hand.
Question: hide the toggle on preprint servers, or give posting the same
trigger?
Lean: hide it — a control that can never act misleads.
Basis: probe + code. <sup>[f-ops1](#fn-ops1)</sup>

<a id="ops2"></a>
**OPS2 — ORCID request emails hidden from the OPS Emails screen** · 🐞 ·
user-visible.
On a preprint server the Emails settings screen lists no row for the ORCID
request emails, so a manager cannot review or customize them, though the
"Request verification" button still sends them using the seeded texts. On
journals and presses the two request emails are listed.
Basis: probe + code (the preprint server keeps its own email roster and
omits them). <sup>[f-ops2](#fn-ops2)</sup>

<a id="ops3"></a>
**OPS3 — Legacy ORCID Profile plugin ships alongside the built-in feature** · ✅ · invisible.
A preprint server's install additionally bundles the old "ORCID Profile"
generic plugin (the pre-3.5 implementation), which journals and presses no
longer carry. The built-in integration described in this spec is what runs;
the plugins screen lists the plugin as "ORCID Profile Plugin", disabled,
with its enable box offered — journals and presses list no ORCID plugin
row.
Basis: probe + code (bundled files + migration helper).
<sup>[f-ops3](#fn-ops3)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<a id="fn-a"></a>
**a** — Context form: `PKP\components\forms\context\OrcidSettingsForm`
(component `orcidSettings`), mounted as tab `orcidSettings` in
`templates/management/access.tpl` from `ManagementHandler::access()` — the tab
is unconditional (no enablement gate). Fields/constants:
`OrcidManager::ENABLED/API_TYPE/CLIENT_ID/CLIENT_SECRET/CITY/
SEND_MAIL_TO_AUTHORS_ON_PUBLICATION/LOG_LEVEL`; API options
`publicProduction/publicSandbox/memberProduction/memberSandbox`; API type,
Client ID and Client Secret are `isRequired` and shown only while the enable
box is ticked (`showWhen: orcidEnabled`). Tab label = locale
`orcid.displayName` ("ORCID"). Logging: `OrcidManager::logInfo()/logError()`
via Laravel `Log`, level from `LOG_LEVEL` (`ERROR` default / `INFO` = all).
Screen reachability: Users & Roles gating is the user-management feature's
(`CanAccessSettingsPolicy`). Live-probed 2026-08-07 (OJS, OMP, OPS): tab,
fields, labels and required-field errors as described, identical in all
three apps.

<a id="fn-b"></a>
**b** — Site form: `PKP\components\forms\site\OrcidSiteSettingsForm`
(component `orcidSiteSettings`), tab in `templates/admin/settings.tpl` gated
`componentAvailability['orcidSiteSettings']` =
`AdminHandler::settings()`'s `$isMultiContextSite = context count !== 1`.
Site fields: enable ("Enable ORCID functionality site-wide"), API type,
Client ID, Client Secret only. Override behavior:
`OrcidManager::isGloballyConfigured()` (site `orcidEnabled`) short-circuits
`isEnabled()` to true for every context and makes `getClientId()/
getClientSecret()/getApiType()` read site values; the context form then
renders the enable option `disabled`, API/ID as read-only rows, the secret
as `*************************`, and appends
`orcid.manager.settings.description.globallyconfigured` ("…Contact your site
administrator to disable ORCID functionality or change these credentials.").
City/email-toggle/log-level have no site fields (`getCity()` etc. always read
the context). Live-probed 2026-08-07: the site tab carries exactly the four
fields; enabling site-wide locks every journal's tab as described
("configured globally" text, masked secret); disabling it restores each
journal's own editable values. The single-journal absence case was not
exercised — every test install hosts several journals (finding A9).

<a id="fn-c"></a>
**c** — Profile: `PKP\user\form\IdentityForm::fetch()` assigns
`orcidEnabled` only when `$request->getContext() && OrcidManager::isEnabled()`
(code comment: ORCID needs a context, so the site-level profile never shows
it); template `lib/pkp/templates/user/identityForm.tpl` guards the whole
block `{if $orcidEnabled}`. Live-probed 2026-08-07 (OJS, OMP, OPS): with
ORCID disabled the Identity tab renders NO ORCID field at all — the
template's fallback plain `orcid` text field does not surface on screen;
enabled, all three connect states render with the strings and icons of
Rule 5, and in the verified state the connect button and About link are
gone. Enabled: the included
`templates/form/orcidProfile.tpl` hides `input[name=orcid]` via JS
(`targetOp == 'profile'`) and injects: authorise variant (hollow icon link +
`orcid.authorise` button) when `$orcid && !$orcidAuthenticated`, connect
variant (`orcid.connect` button) otherwise, or the solid-icon `#orcid-link`
when `$orcidAuthenticated` (`User::hasVerifiedOrcid()` =
`orcidIsVerified`). `openORCID()` pings ORCID logout then
`window.open($orcidOAuthUrl)` — URL from `OrcidManager::buildOAuthUrl(
'authorizeOrcid', ['targetOp' => 'profile'])`; scope `/authenticate` (public)
or `/activities/update` (member). Popup completion:
`AuthorizeUserData::execute()` case `profile` stores
`setVerifiedOrcidOAuthData()` (iD, verified flag, token, scope, refresh,
expiry) and reloads the profile tab. The About link:
`<a href="{orcid/about}" onclick="return openORCID();">` — finding A4.

<a id="fn-d"></a>
**d** — Delete own iD: `#deleteOrcidButton` (guard
`$orcid && $orcidAuthenticated`), confirm modal
`orcid.field.deleteOrcidModal.message`, submits with injected
`removeOrcidId=true`; `IdentityForm::execute()` then nulls
orcid/verified and calls `OrcidManager::removeOrcidAccessToken($user)` which
dispatches `PKP\jobs\orcid\RevokeOrcidToken` (JOB-018 — POST to ORCID's
`oauth/revoke`) and clears token fields. Live-probed 2026-08-07: confirming
the modal removes the iD at once — no separate form save.

<a id="fn-e"></a>
**e** — Contributor field: `PKP\components\forms\FieldOrcid` added to
`ContributorForm` only when `OrcidManager::isEnabled()`; Vue
`FieldOrcid.vue` — states per its guards: request button
(`orcid.field.verification.request` / dialog
`orcid.field.authorEmailModal.title/.message`) posting
`orcid/requestAuthorVerification/{authorId}`; requested state
(`orcid.field.verification.requested`, `orcid.field.verification.resendRequest`);
deferred mode `currentValue='shouldRequestVerification'` when `authorId` is 0
— honored by `PKPSubmissionController::addContributor()`, which sends after
creating the author; unverified note `orcid.field.unverified.shouldRequest`;
delete button posting `orcid/deleteForAuthor/{authorId}` →
`OrcidController::deleteForAuthor()` (nulls iD, revokes token). API gate
(API-029): middleware roles Site Admin, Manager, Sub-editor, Author +
`hasEditPermissions()` (managers; assigned sub-editors; the submission's
authors) — `ROLE_ID_ASSISTANT` absent → finding A5. Requests also set
`orcidVerificationRequested` (drives the requested state on reload).
Live-probed 2026-08-07 (OJS deep; OMP and OPS request round-trip): dialog,
requested state, resend link, both iD states and the delete confirmation as
described; the deferred-request dialog adds "The email will be sent once the
author has been created." and the mail goes out on save; an Author reaches
the button from the submission wizard's Contributors step (the dashboard
contributor list is read-only for them).

<a id="fn-f"></a>
**f** — Pages: `PKP\pages\orcid\OrcidHandler` (ROUTE-021), ops
`verify/authorizeOrcid/about/updateScope`, site-access policy all roles
(login required only for `authorizeOrcid` with `targetOp` profile/submit);
`about` renders `frontend/pages/orcidAbout.tpl` (`orcid.about.title` "What
is ORCID?"; member/public branch `orcid.about.howAndWhyMemberAPI` vs
`…PublicAPI`); `verify` matches the author by emailed token
(`orcidEmailToken` + publication `state`) then
`VerifyIdentityWithOrcid` exchanges the OAuth code, stores
verified iD + token, and for published articles deposits at once
(`sendSubmissionSuccess` → "The submission has been added to your ORCID
record."; unpublished → `submissionNotPublished`). Template
`frontend/pages/orcidVerify.tpl` branch keys: success `orcid.verify.success`
+ 10-second JS redirect (`orcid.verify.success.redirect`); failure
`orcid.verify.failure`, `orcid.verify.duplicateOrcid` (Rule 9's duplicate
sentence quotes this key's locale text — the branch itself needs live ORCID
and was not rendered), `orcid.invalidClient`,
`orcid.authFailure`, missing `orcid.authDenied` (finding A2); tail
`orcid.failure.contact`. `updateScope` re-runs verify for work/review
re-authorizations (`OrcidDepositType`). Deny handling:
`OrcidHandler::handleUserDeniedAccess()` stores `orcidAccessDenied`, clears
token fields. Config kill-switch: `[general] sandbox` makes
verify/authorizeOrcid/updateScope return blank (not set on the test
installs). Live-probed 2026-08-07: about and verify render by URL inside
the journal chrome with the stated headings and failure text (OJS; URL
control on OMP and OPS); a member-API journal switches the about page's
"How and why" section.

<a id="fn-g"></a>
**g** — {OJS} Row action: `useReviewerManagerConfig.js` pushes
`Actions.REVIEWER_SEND_TO_ORCID` (label
`dashboard.reviewAssignment.action.sendReviewToOrcid` = "Send Review To
ORCID") when `reviewAssignment.reviewerHasOrcid &&
pkp.const.REVIEW_ASSIGNMENT_STATUS_COMPLETE` — the second operand is a bare
constant, always truthy (finding A1); `reviewerHasOrcid` =
reviewer's `orcidIsVerified` and not anonymized
(`PKP\submission\maps\Schema`). The guard carries no API-type term;
live-probed 2026-08-07 on a Public Sandbox (public-API) journal: the
completed review's row menu offered "Send Review To ORCID", the dialog read
"Send this review to the reviewer's ORCID?" with OK/Cancel, and confirming
returned 200 with an empty body and closed the dialog with no message. The
deposits-nothing half of that clause stays code-anchored — the deposit
itself is unobservable in-env — per the `DepositOrcidReview::handle()`
member-API gate below. Dialog + POST
`reviews/{submissionId}/{reviewAssignmentId}/sendToOrcid`
(`PKPReviewController::sendToOrcid`, roles Site Admin/Manager/Sub-editor) →
`APP\orcid\actions\SendReviewToOrcid` (OJS: chains
`ReconcileOrcidReviewPutCode` (JOB-034) + `DepositOrcidReview` (JOB-033);
OMP/OPS: base no-op). `DepositOrcidReview::handle()` returns unless status ∈
`REVIEW_COMPLETE_STATUSES`, member API on, and
`OrcidManager::getCity() && getCountry()` — the silent City+country
requirement; public-scope token → dispatches `SendUpdateScopeMail`
(JOB-020). Publication-time review deposits:
`PKPSendSubmissionToOrcid::depositReviewsForSubmission()`. Live-probed
2026-08-07 (OJS + OMP): dialog title "Send Review To ORCID", message "Send
this review to the reviewer's ORCID?", OK/Cancel; confirming succeeded with
no on-screen feedback of any kind.

<a id="fn-h"></a>
**h** — Display value/icons: `Identity::getOrcidDisplayValue()` appends
`orcid.unauthenticated` ("(unauthenticated)") for an unverified iD; solid
(`orcid.svg`) vs hollow (`orcid_unauthenticated.svg`) icon on the same
guard. Live-probed 2026-08-07 (OJS, reviewer list instance of the same
display logic): unauthenticated case rendered the hollow icon + suffixed
link, verified case the solid icon + bare iD link.

<a id="fn-i"></a>
**i** — Registration: `RegistrationForm::fetch()` assigns the block only for
context registration (`$request->getContext() !== null` — code comment);
`orcidProfile.tpl` register variant renders hidden `orcid` field + connect
button. Popup completion: `AuthorizeUserData` case `register` fetches
name/email/country/employment from ORCID and fills the form fields via
JS, hides the connect button; no token is stored anywhere (the account does
not exist yet) and `RegistrationForm::execute()` saves only
`$user->setOrcid(...)` — never `orcidIsVerified` — finding A3. Live-probed
2026-08-07 (OJS): the block renders at the top of the journal registration
form and the form submits without it; the site-level registration page
shows none.

<a id="fn-j"></a>
**j** — Deposit pipeline: listener
`PKP\observers\listeners\SendSubmissionToOrcid` on `PublicationPublished`
(status published/scheduled) → `APP\orcid\actions\SendSubmissionToOrcid
::execute()`: returns unless enabled + member API + app
`canDepositSubmission()`; collects contributors with iD + unexpired token;
per author dispatches `PKP\jobs\orcid\DepositOrcidSubmission` (JOB-017) —
POST/PUT `…/work[/put-code]` with the app's work payload
(`APP\orcid\OrcidWork` over `PKPOrcidWork`; OJS adds issue data), storing
`orcidWorkPutCode` for updates, deleting expired/revoked tokens; a
public-scope token instead dispatches `SendUpdateScopeMail` (JOB-020) →
`updateScope` landing resumes the deposit. App split: OJS
`canDepositSubmission()` true (+ issue); OPS true (no reviews —
`depositReviewsForSubmission()` overridden empty); OMP false and
`getOrcidWork()` null, with `FIXME: OMP cannot deposit submissions
currently. Check can be removed once added` — finding OMP1. Author-verify
deposits: `VerifyIdentityWithOrcid::depositOrcidItem()`.

<a id="fn-k"></a>
**k** — Listener `PKP\observers\listeners\SendAuthorOrcidEmail` on
`DecisionAdded`: fires for `Decision::ACCEPT` and
`Decision::SKIP_EXTERNAL_REVIEW` when the context's
`orcidSendMailToAuthorsOnPublication` is set, emailing (via
`SendAuthorMail`, JOB-019) every contributor without a live token. Neither
decision exists in OPS — finding OPS1; label text —
finding A6. Listeners auto-discover from `lib/pkp/classes/observers/
listeners` in all three apps (`EventServiceProvider`). Live-probed
2026-08-07 (OJS deep, OMP spot): with the toggle on, "Accept Submission"
and "Accept and Skip Review" each delivered one "Submission ORCID" mail per
contributor alongside the decision mail; with it off, only the decision
mail arrived.

<a id="fn-l"></a>
**l** — Emails: `SendAuthorMail` picks
`OrcidRequestAuthorAuthorization` (member API; key
`ORCID_REQUEST_AUTHOR_AUTHORIZATION`, name "Requesting ORCID record
access") vs `OrcidCollectAuthorId` (public; key `ORCID_COLLECT_AUTHOR_ID`,
name "Submission ORCID"); `SendUpdateScopeMail` sends
`OrcidRequestUpdateScope` (key `ORCID_REQUEST_UPDATE_SCOPE`, name
"Requesting updated ORCID record access"). All from the
principal contact, to the contributor, with variables `authorOrcidUrl`
(single-use OAuth link; token `orcidEmailToken`) + `orcidAboutUrl`
(`OrcidVariables`). Template keys seeded in every app's
`registry/emailTemplates.xml`. Roster gaps: OPS
`classes/mail/Repository::map()` replaces the base list and includes no
ORCID mailable (finding OPS2; OJS map lists the two request mailables, OMP
merges OJS's); `OrcidRequestUpdateScope` is in no app's map (finding A7).
Live-probed 2026-08-07: the public-API request delivered "Submission
ORCID" and the member-API request "Requesting ORCID record access" (wider
permission in its link), from the principal contact, in all three apps. On
the Emails settings screen the two listed rows are titled by internal name
— "orcidCollectAuthorId", "orcidRequestAuthorAuthorization" — so a manager
finds them by searching "ORCID", not by the names above; the row naming
itself is the Emails-management feature's territory.

<a id="fn-m"></a>
**m** — Riders: invitation ORCID step gate
`OrcidManager::isEnabled($context)` in `AcceptInvitationStep` (owned by
*User invitations*); invitation send-wizard note `invitation.orcid.description`
(`UserDetailsForm`, shown only when enabled); reviewer suggestions:
`ReviewerSuggestionsForm` adds plain `FieldText('orcidId')` when enabled;
reviewer-list display: see note h.

<a id="fn-s"></a>
**s** — Scenario seeding: scratch journals/submissions via the scenario
endpoints; ORCID enabled with dummy sandbox credentials (the OAuth exchange
itself needs orcid.org — outbound HTTP fails fast at the test config's
dead-port `[proxy]`, and no real ORCID account backs the dummy
`APP-TESTCLIENTID` pair — scenario steps stop at the popup/emailed link,
and deposit outcomes rest on code, notes g and j). Verified/unauthenticated iDs are
seeded directly (`user_settings`/`author_settings`: `orcid`,
`orcidIsVerified` — recipe proven in the 2026-08-07 reviewer-list probe).
Emails observed in Mailpit with per-test throwaway recipients.

<a id="fn-a1"></a>
**f-a1** — `useReviewerManagerConfig.js` "ORCID reviewer deposit" block:
`if (reviewAssignment.reviewerHasOrcid && pkp.const.REVIEW_ASSIGNMENT_STATUS_COMPLETE)`
— compares nothing to `reviewAssignment.statusId`; the constant (numeric,
non-zero) makes the guard `reviewerHasOrcid`-only. Deposit-side re-check:
`DepositOrcidReview::handle()` early-returns for incomplete statuses
(note g), so the UI offer outruns the deposit. Live-probed 2026-08-07: on
an incomplete review's row the action is offered for a verified-iD
reviewer and absent for a no-iD reviewer (OJS); confirming on a completed
review succeeded with no feedback of any kind; a Press Manager gets the
same action, dialog and silent success (OMP — base `SendReviewToOrcid` is
a no-op there).

<a id="fn-a2"></a>
**f-a2** — `orcidVerify.tpl` denied branch: `{translate
key="orcid.authDenied"}`; no `msgid "orcid.authDenied"` exists in any
`locale/` tree (the defined key is `orcid.verify.denied`, which nothing
renders). Missing keys render as `##orcid.authDenied##`. Reaching the
branch requires ORCID's consent screen returning `error=access_denied` with
a valid email token — orcid.org is unreachable through the dead-port
`[proxy]` (and no real account backs the dummy credentials), so the basis
stays code.

<a id="fn-a3"></a>
**f-a3** — Note i: `AuthorizeUserData` case `register` only fills form
fields; `RegistrationForm::execute()` stores `orcid` without
`orcidIsVerified` or token data. Contrast case `profile`, which stores the
full verified set. The follow-up state (hollow icon + "Authorize and
Connect") is the note-c authorise variant.

<a id="fn-a4"></a>
**f-a4** — `orcidProfile.tpl`: the about link carries
`onclick="return openORCID();"` — `openORCID()` opens the OAuth popup and
returns false, cancelling navigation. Affects profile and register variants
(both render the same capture). Live-probed 2026-08-07 (OJS Identity tab):
the click opened the sign-in popup and never navigated; `/orcid/about`
typed directly renders the page.

<a id="fn-a5"></a>
**f-a5** — Note e: API-029's role middleware (site admin / manager /
sub-editor / author) + `hasEditPermissions()`; contributor editing
otherwise admits Assistants (the contributors feature's gate). Live-probed
2026-08-07 (OJS): both actions returned an authorization refusal
(HTTP 401) — no email delivered, stored iD unchanged — while the field
showed the requested state / the iD removed.

<a id="fn-a6"></a>
**f-a6** — Label `orcid.manager.settings.sendMailToAuthorsOnPublication`
(verbatim in Fields & validation); trigger note k (accept + skip-review,
not copyediting entry, not publication); the constant's own name says
"OnPublication". Docs-vs-behavior only; no code defect. Label
live-confirmed identical in all three apps and trigger live-confirmed
(note k), 2026-08-07.

<a id="fn-a7"></a>
**f-a7** — `OrcidRequestUpdateScope` appears in no `Repository::map()`
(lib/pkp, OJS, OMP or OPS — dispatched directly from `SendUpdateScopeMail`),
and the Emails screen lists templates through the mailable map. Template
seeded in all three registries. Live-probed 2026-08-07: an "ORCID" search
of the Emails screen returns no row for it in any app (the two request
templates are listed on OJS and OMP). Update 2026-08-25 (rebase check):
pkp/pkp-lib#13050 adds `OrcidRequestUpdateScope` (with two unrelated
mailables) to the lib/pkp `Repository::map()`, which OJS and OMP merge; OPS's
override still builds its own list without it, so the OPS absence stands.

<a id="fn-a8"></a>
**f-a8** — The closing line is the shared tail string of the verification
landing (`orcid.failure.contact`, note f) — no per-app recast. Live-probed
2026-08-07: the OMP and OPS `/orcid/verify` pages both close with the
sentence verbatim, "journal manager" unchanged.

<a id="fn-a9"></a>
**f-a9** — Note b's gate: the tab renders only while
`AdminHandler::settings()` counts more than one context
(`$isMultiContextSite`). The test installs all host several journals, so
the single-journal case was not exercised (2026-08-07); the multi-journal
presence is live-probed.

<a id="fn-omp1"></a>
**f-omp1** — `omp-main/classes/orcid/actions/SendSubmissionToOrcid`:
`canDepositSubmission()` returns false, `getOrcidWork()` returns null;
base `PKPSendSubmissionToOrcid::execute()` early-returns on the flag with
`FIXME … once functionality added to OMP`. Everything identity-side is the
shared lib/pkp path (empty-chain evidence: OMP adds no other ORCID
overrides).

<a id="fn-ops1"></a>
**f-ops1** — Note k: the trigger decisions do not exist in OPS's decision
roster; the settings form is the shared note-a component, so the toggle
renders. Live-probed 2026-08-07: the OPS ORCID tab shows the toggle with
the identical label; a submitted preprint's workflow offers only "Post the
preprint" and "Decline Submission" — no accepting decision.

<a id="fn-ops2"></a>
**f-ops2** — Note l: OPS `Repository::map()` override ("OPS uses distinct
mailables") lists no ORCID mailable while the OPS registry seeds all three
templates; same mechanism as the invitation-template gap recorded in
[User invitations](U06-user-invitations.md). Live-probed 2026-08-07: searching
"ORCID" on the OPS Emails screen returns "No items found.", while "Request
verification" on a preprint contributor delivered the "Submission ORCID"
email (positive control).

<a id="fn-ops3"></a>
**f-ops3** — `ops-main/plugins/generic/orcidProfile` (submodule, release
1.3.4.3 / 2023-02-17, lazy-load) — the pre-3.5 plugin generation; absent
from `ojs-main`/`omp-main` plugin trees. OPS also carries the full built-in
integration (`ops-main/classes/orcid/*` mirroring OJS minus reviews). An
`OrcidProfileEmailDataMigration` class inside the plugin suggests it is
kept for upgrade data migration. Live-probed 2026-08-07: OPS Website
Settings → Plugins lists one row among Generic Plugins — "ORCID Profile
Plugin", "Allows for the import of user profile information from ORCID.",
enable box present and unchecked (enabling not exercised), no error
rendered; OJS and OMP list no ORCID plugin row (absence controls).

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Journal ORCID settings tab | Settings → Users & Roles → "ORCID" | AFFM-117 |
| Site ORCID settings tab | Site Settings → "ORCID" (multi-journal installs) | AFFM-222 |
| Profile identity ORCID block | Profile → Identity tab | AFFU-065..066, 099..103 |
| Registration ORCID block | `{journal}/user/register` | AFFU-099..101, 103 |
| Contributor ORCID field | workflow → Contributors → add/edit | AFFU-106..110 |
| What-is-ORCID page | `{journal}/orcid/about` | AFFU-104 · ROUTE-021 |
| Verification landing | `{journal}/orcid/verify` (also `updateScope`, `authorizeOrcid`) | AFFU-105 · ROUTE-021 |
| Send Review To ORCID row action {OJS} | workflow → Review → Reviewers table row menu | AFFW-502 (dialog chrome AFFW-506 — owned by *Reviewer assignment & management*) |
| Verification-request API | `POST orcid/requestAuthorVerification/{authorId}` · `POST orcid/deleteForAuthor/{authorId}` | API-029 |
| Review deposit API {OJS} | `POST reviews/{submissionId}/{reviewAssignmentId}/sendToOrcid` | API-032 rider (owned by *Review activity & history*) |
| Request emails | keys `ORCID_COLLECT_AUTHOR_ID`, `ORCID_REQUEST_AUTHOR_AUTHORIZATION`, `ORCID_REQUEST_UPDATE_SCOPE` | MAIL-027..029 |
| Background jobs | work deposit, token revocation, author mail, scope mail | JOB-017..020 |
| Background jobs {OJS} | review deposit, put-code reconciliation | JOB-033..034 |
| Legacy plugin {OPS} | `plugins/generic/orcidProfile` (bundled, install fact) | PLUG-021 |

## Reference — code anchors

- `lib/pkp/classes/orcid/OrcidManager.php` — enablement, API/credential resolution, token revocation
- `lib/pkp/classes/orcid/actions/` — `PKPSendSubmissionToOrcid`, `PKPSendReviewToOrcid`, `VerifyIdentityWithOrcid`, `AuthorizeUserData`
- `lib/pkp/pages/orcid/OrcidHandler.php` — verify/about/updateScope/authorizeOrcid pages
- `lib/pkp/api/v1/orcid/OrcidController.php` — contributor verification-request/delete API
- `lib/pkp/classes/components/forms/context/OrcidSettingsForm.php` · `site/OrcidSiteSettingsForm.php` · `FieldOrcid.php`
- `lib/pkp/jobs/orcid/` — `DepositOrcidSubmission`, `RevokeOrcidToken`, `SendAuthorMail`, `SendUpdateScopeMail`
- `lib/pkp/classes/observers/listeners/SendSubmissionToOrcid.php` · `SendAuthorOrcidEmail.php`
- `lib/pkp/templates/form/orcidProfile.tpl` · `user/identityForm.tpl` · `frontend/pages/orcidVerify.tpl` · `orcidAbout.tpl`
- App layer: `{app}/classes/orcid/actions/SendSubmissionToOrcid.php` (OJS deposit+issue / OMP disabled / OPS deposit, no reviews); OJS `classes/orcid/OrcidWork.php`, `OrcidReview.php`, `jobs/orcid/DepositOrcidReview.php`, `ReconcileOrcidReviewPutCode.php`; OPS `plugins/generic/orcidProfile` (legacy bundle)
- UI library: `src/components/Form/fields/FieldOrcid.vue` · `src/managers/ReviewerManager/useReviewerManagerConfig.js`
