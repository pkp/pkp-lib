# U6 — User invitations · DIGEST (RUNBOOK step 3b)

Spec-affecting facts from probe reports A–D (33 items, three fleets), in the
spec's voice. `Proposed:` is a suggestion only. *OJS observed* = the item ran on
OJS alone (Groups B and D); its cross-app controls are in the A/C blocks.

---

### D1 — A manager reaches the Invitations table and the "Invite to a role" button from the menu, unless their role's settings permission is withdrawn
Affects: Actors rows 1–2 · Settings (Roles configuration bullet)
Status: confirms
Apps: all three. Clearing a manager-level role's "Permit changes to Settings" option takes its holders off the whole Users & Roles screen — table and button with it (OJS, OMP; OPS ships no editable manager-level role, and the manager-slot role itself offers no such control anywhere).
Proposed: drop *(unconfirmed)*; state the condition as the option a manager can see on the Roles screen.
Evidence: probe-a.md A-3, A-4

### D2 — A Site Administrator who is not also a manager of the journal reaches Users & Roles on OJS and is turned away on OMP and OPS
Affects: Actors row 1 · register A4
Status: confirms
Apps: OJS admits; OMP and OPS refuse with "The current role does not have access to this operation." The divergence hides on a stock install because creating a journal/press/server enrols the creator as its manager, and that hat admits them everywhere.
Proposed: keep ❓ A4, basis now observed; add the masking as why it looks fine.
Evidence: probe-a.md A-1

### D3 — The two addresses for Users & Roles admit opposite sets of people
Affects: register A2 · Actors row 1
Status: corrects (sharper than drafted)
Apps: all three. The older address admits a Site Administrator everywhere — including OMP and OPS, where the normal address refuses that same person — and refuses every manager everywhere. On OMP and OPS it is the only door a Site Administrator has to the Invitations table.
Proposed: ❓ A2 restated with the disjoint sets; the "should it share the guard" question stands.
Evidence: probe-a.md A-2

### D4 — A Section Editor who types the wizard's address gets a working wizard whose every exit refuses them
Affects: register A1 · Actors preamble
Status: confirms (both halves, worse than drafted)
Apps: all three. They search, enter details, compose and send a real invitation, and are offered the journal's full role list; "Cancel" and the success dialog's "View All Users" both land on the access-refused page. An Assistant gets the same wizard but is stopped at the search step by an Error dialog carrying the same sentence.
Proposed: 🐞 for the dead-end exits and the invitation a refused role can still send; keep the "are these roles meant to invite?" question open.
Evidence: probe-a.md A-5

### D5 — Inviting the same address twice replaces its row rather than adding one
Affects: Rule 3
Status: new (Rule 3's anatomy otherwise confirmed)
Apps: OJS observed. One row per invitee, carrying the newest invitation's roles. Heading count, columns, the always-"Invited {date}" status, five rows per page, the two-entry row menu and the pending-only listing are as drafted; a composing invitation is listed nowhere.
Proposed: one added sentence; no existing text dropped.
Evidence: probe-b.md B-6, B-13

### D6 — The wizard's third step is an email composer, not a review screen
Affects: Rule 4
Status: corrects
Apps: all three. The tab reads "Review & invite for roles"; the panel is headed "STEP 3 - Modify email shared with the user" and offers a template picker, recipient fields and a pre-filled subject and body. Step 2's tab and panel disagree the same way.
Proposed: rewrite the step-3 description; note the tab/heading mismatch.
Evidence: probe-b.md B-7 · probe-c.md C-19b

### D7 — The search step sorts people by whether they hold a role in *this* journal, not by whether they have an account
Affects: Rule 4a · Fields table (search row)
Status: corrects
Apps: OJS observed. Someone with an account elsewhere on the site but no role here gets "The user does not have a role in this journal" and the blank newcomer form — so the wizard offers to create identity details for an existing account. Only an email-shaped search term is carried into the invitee's email field.
Proposed: correct the discriminator; narrow the carry-over sentence.
Evidence: probe-b.md B-8

### D8 — "Start Date" accepts today and dates long past, and a future start date leaves the accepted role unusable until it arrives
Affects: Fields table (Start Date) · Rule 5 · Side effects (role assignment)
Status: corrects · new
Apps: OJS observed. Past dates are accepted at the step and at send, with no bound on the field. Separately, a newcomer who accepts a role starting tomorrow is refused that role's screens on the day they accept.
Proposed: replace "must be after today" with what the screen enforces; add the not-yet-active consequence.
Evidence: probe-b.md B-9 · probe-d.md D-23d

### D9 — The role dropdown offers this journal's own roles minus the ones the invitee already holds, so the "already holds it" refusal never appears
Affects: Rule 5 · Fields table (role row)
Status: confirms (own roles) · corrects (the refusal)
Apps: OJS observed for the two-journal control; the omission also seen on OMP and OPS. A second journal never contributes options; an end-dated role returns to the list; a duplicate row inside one invitation is blocked by greying the option, with no message.
Proposed: drop *(unconfirmed)*; replace Rule 5's refusal sentence.
Evidence: probe-b.md B-15, B-9

### D10 — A Reviewer row's masthead cell is fixed text; every other row gets the two-option selector
Affects: Fields table (masthead row) · scenario 8
Status: confirms
Apps: OJS and OMP (both OMP reviewer roles). OPS seeds no reviewer role — its wizard offers exactly five roles and every row shows the selector.
Proposed: drop *(unconfirmed)*; keep the OPS role list in scenario 8.
Evidence: probe-c.md C-20, C-19a

### D11 — Opened from the users table, the wizard's Continue stays disabled until a role row is added, the page has no heading, and "Cancel" does nothing
Affects: Rule 4b
Status: confirms with correction · new
Apps: OJS observed. Editing the user's existing rows leaves Continue disabled; "Add Another Role" enables it even while blank. Only the breadcrumb names the page, and pressing "Cancel" produces no dialog and no navigation.
Proposed: drop *(unconfirmed)*, correct what enables the button; 🐞 minor for the dead Cancel and missing heading.
Evidence: probe-b.md B-10

### D12 — There is no "user is currently disabled" banner; the wizard opens for a disabled user and then fails with two unexplained errors
Affects: Rule 8
Status: corrects (draft claim disproved)
Apps: OJS observed. An "Error — The requested resource was not found." dialog sits over an empty role table while the role-adding controls stay enabled; continuing reaches the composer and errors again, printing an internal address containing "null". Nothing says the user is disabled.
Proposed: replace Rule 8; new 🐞, user-visible.
Evidence: probe-b.md B-11

### D13 — Removing a role or changing a masthead choice inside the wizard takes effect at once, and nothing on the step says so
Affects: Rule 9 · register A3 · Side effects
Status: confirms
Apps: OJS observed. Both confirmations exist and both survive abandoning the wizard unsent; removing a last role is refused. Every surrounding word is invitation-framed, down to "Are you sure want to cancel this invitation?".
Proposed: keep ❓ A3, basis now observed.
Evidence: probe-b.md B-12

### D14 — When a send is refused the manager gets a modal error naming an internal address, and no banner appears anywhere
Affects: register A6 · Rule 14
Status: confirms (and widens)
Apps: OJS observed. The message names no user, no role and no reason, and prints "null"; no warning banner and no inline note appeared on the step.
Proposed: keep 🐞 A6, symptom rewritten to what the manager sees.
Evidence: probe-b.md B-13

### D15 — The wizards' step list has an untranslated screen-reader name, and the untranslated error banner appears on the account and details steps, not the review step
Affects: register A5 · Rule 14
Status: confirms substance · corrects placement
Apps: all three — the step-list name on both wizards in OJS, OMP and OPS. Refusals on the acceptance flow's account and details steps are topped by a banner reading a raw key; the review step's own failure showed a modal error instead.
Proposed: keep 🐞 A5, correct the placement and note it is not OJS-only.
Evidence: probe-b.md B-7 · probe-d.md D-24a, D-24d, D-24e · probe-c.md C-21

### D16 — The invitation email is as drafted, but a newcomer is greeted by their email address and an existing user's mail also lists their current roles
Affects: Side effects (invitation email) · scenarios 1–2
Status: confirms with additions
Apps: all three (delivery verified in each). Subject, inviter and journal naming, the role list with start date and masthead sentence, and both links are as drafted. The newcomer greeting is the email address even when the inviter typed a name; an existing user is greeted by name and gets an "Already assigned roles" section.
Proposed: add both as plain claims; the newcomer greeting is worth 🐞 minor.
Evidence: probe-d.md D-23b, D-26 · probe-c.md C-19c

### D17 — Acceptance completes but never signs the new user in: "View All Submissions" lands on the journal's sign-in page
Affects: Rule 10 (last two bullets) · scenarios 1–2 · Actors row "Accept the invitation"
Status: corrects
Apps: all three. The steps, the "Accept And Continue to OJS" button and the success dialog are as drafted; only the destination is wrong. Abandoning mid-flow with "Cancel Invitation Process" lands on the same sign-in page, though that dialog promises a dashboard; "Go Back" returns with data intact and the invitation stays pending and reusable.
Proposed: correct both bullets and both scenarios; new 🐞, user-visible — the invitee is left at a login form with an account just created.
Evidence: probe-d.md D-23d, D-32 · probe-c.md C-19d, C-21

### D18 — An existing user who opens the accept link signed out is not signed in for them, and gets a single review step
Affects: Actors row "Accept the invitation" · Rule 10 (existing-user bullet) · scenario 2
Status: corrects (the auto-sign-in claim is disproved)
Apps: OJS observed. No user menu appears anywhere; the flow is one step showing the invited role, and accepting attaches it to the existing account.
Proposed: delete the auto-sign-in claim and its footnote; rewrite the bullet as the single review step.
Evidence: probe-d.md D-26

### D19 — Whether an existing user with a verified ORCID iD is accepted with no page at all is undetermined
Affects: Rule 10 (existing-user bullet)
Status: undetermined
Apps: OJS. Settling observation: the same accept link opened by an existing user whose ORCID iD is already verified, on an install where ORCID is configured.
Proposed: keep the sentence marked, or drop it.
Evidence: probe-d.md D-26

### D20 — The account step refuses a short password and a taken username inline, and accepts a widely-breached password without comment
Affects: Fields table (Username, Password)
Status: confirms (length) · corrects (breach list; where the username refusal lands)
Apps: OJS observed. A taken username is refused on the account step itself, not at the end of the flow. A well-known breached password of legal length is accepted silently on this install, whose outbound network access is closed.
Proposed: drop the *(unconfirmed)* breach-list claim or reduce it to an install-dependent note; move the username refusal to the account step.
Evidence: probe-d.md D-24a, D-24b, D-24c

### D21 — The wrong-user refusal is as drafted, but its button reads "Logout" and dismissing it lands on an access-refused page
Affects: Rule 12 · scenario 6
Status: confirms with correction
Apps: OJS observed. Logging out and re-opening the link resumes the normal flow; dismissing the dialog lands on "The current role does not have access to this operation.", not the submissions dashboard.
Proposed: correct the button label and the dismissal destination.
Evidence: probe-d.md D-27

### D22 — The "Cancel Invitation" dialog carries no warning sentence
Affects: Rule 7 · scenario 4
Status: corrects
Apps: all three. It lists the invitation's email, roles, status and affiliation and nothing else; the drafted sentence about deactivating the emailed link is not on screen. Both buttons behave as drafted.
Proposed: delete the quoted warning from Rule 7.
Evidence: probe-d.md D-29 · probe-c.md C-22

### D23 — After editing and resending, the first email's links lead to a bare "not found" page, not "Invitation Unavailable"
Affects: Rule 6 · Rule 13 · scenario 5
Status: corrects
Apps: OJS observed. The warning, the preloaded two-step wizard and the second email are as drafted, and the table keeps one row listing both roles. The superseded email's links render an unstyled "404 Not Found" — no journal name, no explanation, no way back — where decline, cancellation and expiry all give the recipient "Invitation Unavailable" with Login and Register.
Proposed: correct scenario 5 and Rule 6's tail; new 🐞, user-visible.
Evidence: probe-d.md D-30

### D24 — Decline, cancellation and expiry behave as drafted, and none of them sends mail
Affects: Rule 11 · Rule 2 · Side effects (no mail on the other exits) · scenarios 3, 4, 7
Status: confirms
Apps: OJS observed; the "Invitation Unavailable" landing re-checked on OMP and OPS. The decline page's wording and button, the sign-in landing, the row leaving the table and the spent link's landing are as drafted. An invitation past its validity window never appears in the table and its links land on "Invitation Unavailable". No message reached invitee or inviter on decline or cancellation.
Proposed: drop the code-absence hedge from the no-mail bullet, scoped to invitee and inviter.
Evidence: probe-d.md D-28, D-29, D-31

### D25 — "Invitation Unavailable" is word-for-word identical on presses and preprint servers, "journal manager" included; a link matching nothing gets a page with no way back
Affects: register A7 · Rule 13
Status: confirms
Apps: all three. Heading, explanation and the Login / Register links into the context's own sign-in and registration pages are identical everywhere, and a press or preprint-server visitor is told to contact the journal manager. A link matching no invitation renders the site's unstyled "404 Not Found" with no chrome and no navigation; a link naming no invitation at all renders a blank page.
Proposed: keep 🐞 A7 as drafted; add the stale-link landing to Rule 13, 🐞 minor for the blank page.
Evidence: probe-c.md C-22 · probe-d.md D-28, D-33

### D26 — OPS invitations send, arrive and are accepted normally; what OPS loses is the email's row on the Emails screen
Affects: register OPS1 · Side effects (invitation email) · scenario 8 · Cross-feature (*Emails management*)
Status: corrects (the "does it work?" half) · confirms (the missing row)
Apps: OPS, with OJS and OMP as controls. A manager on OPS finds no invitation-email row by search or group filter, so the template cannot be edited there; on OJS and OMP the row exists. The OPS wizard's composer still offers the template with subject and body filled, sends, delivers with both buttons, and acceptance completes. Where the row exists it opens a name/subject/body form only — no role restriction, and no "add an alternate template" path for this email in *any* app.
Proposed: split OPS1 — keep the missing row (OPS-only), retire the sending uncertainty, state the no-alternate-template fact as all-app.
Evidence: probe-c.md C-16, C-17, C-18, C-19

### D27 — Every app-flavoured word in both wizards follows the app
Affects: How to read this file (glossary substitution) · Rules 4, 4a, 10 · scenario 8
Status: confirms
Apps: OMP and OPS checked against OJS at every seam — the wizard's opening line, both search outcomes, the masthead column and field labels, the "Create OMP/OPS account" step, the final button and the success dialog. Step names and Continue labels are app-neutral everywhere.
Proposed: no text change; this is the evidence the unmarked claims rest on.
Evidence: probe-c.md C-21

### D28 — With ORCID unconfigured there is no "Verify ORCID iD" step and no ORCID field in the wizard, but the acceptance flow still shows an ORCID line
Affects: Settings (ORCID bullet) · Rule 10 · Fields table
Status: confirms as install fact · new
Apps: all three. No ORCID step appeared in any acceptance flow and the newcomer details step offers no ORCID field; the acceptance details step and the existing-user identity block still show an "ORCID iD — Not verified…" line regardless.
Proposed: keep the ORCID claims configuration-dependent; add the always-present row as a plain claim.
Evidence: probe-d.md D-23c · probe-c.md C-19d · probe-b.md B-8
