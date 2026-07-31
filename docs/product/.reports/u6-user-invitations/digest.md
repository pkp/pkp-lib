# U6 User invitations — digest (RUNBOOK step 3b)

One block per spec-affecting fact, from probe-1 … probe-4 (2026-07-31). Evidence
pointers name the report file and its item; the trail stays there. `Proposed:`
is a suggestion; step 4 decides.

### D1 — Only Managers and the Site Administrator get the Invitations panel and the "Invite to a role" button, identically in all three apps
Affects: Actors rows 1–4 · footnote a
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim (drop the pre-probe hedging); button label verbatim "Invite to a role" everywhere. The Users & Roles address itself denies every non-manager role tested.
Evidence: probe-1-gates-surfaces.md, items 1–2

### D2 — The wizard-address check behind A1 ran; its role-by-role outcome is held privately
Affects: register A1 · footnote f-a1
Status: undetermined (publicly)
Apps: OJS, OMP, OPS
Proposed: keep A1 ❓; update its basis to "live-checked 2026-07-31; outcome routed to the maintainer's private security file". Publicly statable: Author and Reviewer are denied at the wizard address; the section-editor and assistant-level cells are the routed ones. OPS has no seeded reviewer account (cell not testable).
Evidence: probe-1-gates-surfaces.md, item 1

### D3 — The invitation email template really is missing from the OPS Emails screen while OPS invitations still deliver
Affects: register OPS1 · scenario 9 · Settings bullet 5
Status: confirms
Apps: OPS (absence); OJS, OMP (presence controls)
Proposed: 🐞 user-visible — "User Invited to Role Notification" found by search on OJS/OMP with an Edit button, "No items found." on OPS (full-list scan also empty); the OPS invite email was sent and delivered in the same session (positive control).
Evidence: probe-1-gates-surfaces.md, item 3 · probe-2-send-accept.md, item 3

### D4 — The whole send flow runs as scenario 1 describes, in all three apps, with glossary substitution holding
Affects: scenario 1 · Rules 11, 15 · Fields table (send) · Side effects on-send · footnote p
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claims. Step rail, search-miss notice, role/date/masthead row, compose step with template picker, "Invitation Sent" dialog, "Invited {date}" row, and the email (roles with dates + masthead sentence, accept/decline links sharing one id+key pair) all verified verbatim. One footnote-p fix: the roles column is headed "INVITATIONS", not "Roles".
Evidence: probe-2-send-accept.md, item 3

### D5 — The "Invitation Sent" dialog's button does not return the sender to Users & Roles
Affects: Rule 15
Status: corrects
Apps: OJS (observed); dialog identical on OMP/OPS
Proposed: correct the rule — the single button reads "View All Users" and only re-anchors the wizard URL; the wizard stays on the compose step behind the dialog. The sender navigates back themselves.
Evidence: probe-2-send-accept.md, item 3

### D6 — Only the email address is required to invite a newcomer; the names are optional
Affects: Fields table (send), row 2
Status: corrects
Apps: OJS (deep); same form on OMP/OPS
Proposed: required = email only. Given and Family Name carry helper text saying the user can change them; a send with no given name completed.
Evidence: probe-4-edituser-expiry-disabled.md, item 11 · probe-2-send-accept.md, item 3

### D7 — Opening an existing user's accept link never signs them in; the link alone is the credential, and they stay signed out
Affects: Rule 6 (first half) · scenario 3 · footnote l · A4's contrast sentence
Status: corrects
Apps: OJS, OMP, OPS
Proposed: rewrite — a signed-out visitor holding the link gets the one-step review wizard directly (no credential prompt, no account fields), accepts, and still lands signed out on the sign-in page. There is no auto-login at any point. The roles do land on the account (verified in Current Users).
Evidence: probe-2-send-accept.md, item 5 · probe-3-decline-cancel-edit.md, item 6

### D8 — A signed-in bystander opening someone else's accept link is refused and offered a working Logout
Affects: Rule 6 (second half) · scenario 7
Status: confirms
Apps: OJS, OMP
Proposed: plain claim; dialog verbatim "Invitation not accepted. You're logged in as a different user. Please log out and sign in with the correct account to accept this invitation.", sole action "Logout", which genuinely ends the session; the signed-out reopen starts the real flow. (The refusal overlays a blank wizard shell — cosmetic, writer's call.)
Evidence: probe-3-decline-cancel-edit.md, item 6

### D9 — No invitation ever accepts itself on page load; ORCID only adds or removes the leading Verify step
Affects: Rule 5 (last sentence) · Rule 7 · Settings bullet 2 · footnote k
Status: corrects
Apps: OJS (deep); step shapes identical on OMP/OPS
Proposed: drop the zero-step auto-accept clause — with ORCID off an existing user still gets the one-step review wizard and must press the accept button. With ORCID on, a "Verify ORCID iD" step is prepended (buttons "Verify ORCID iD" / "Skip ORCID verification", no other Continue) — Rule 7 confirmed. Note: ORCID ships disabled in both the seeded journal and scratch contexts.
Evidence: probe-2-send-accept.md, item 12

### D10 — The accept wizard's password field promises a six-character minimum on screen
Affects: Fields table (accept), password row · footnote o
Status: corrects
Apps: OJS (observed)
Proposed: state the on-screen minimum-length rule only, and hold the breached-password clause out of the spec for now — one observation from this check was routed to the maintainer's private security file (content omitted here by policy).
Evidence: probe-2-send-accept.md, item 4

### D11 — A brand-new invitee is dumped at the sign-in screen right after creating their account — on all three apps
Affects: register A4 · Rule 9 · scenario 2
Status: confirms
Apps: OJS, OMP, OPS
Proposed: 🐞 user-visible — closing dialog "You've been assigned a new role in {app}" → "View All Submissions" → the sign-in page, no session. The new credentials do work and the role is granted. A4's contrast sentence ("existing users get the opposite") must be rewritten per D7 — existing users are not signed in either, which makes the finding broader, not narrower. Scenario 2's privacy-consent rejection text also confirmed verbatim.
Evidence: probe-2-send-accept.md, items 4–5

### D12 — The inviter hears nothing when the recipient answers: no notification, no email, and the row just vanishes
Affects: register A5 · Side effects bullet 3 · Rule 11
Status: confirms
Apps: OJS (observed; the promising dialog is identical on OMP/OPS and the code is shared — no app override found in the draft's chain check)
Proposed: 🐞 user-visible — after BOTH accept and decline, all three promised channels stay silent: the Tasks/bell panel shows "No Items" (it is the backend's only notification surface), the inviter's mailbox receives nothing (positive control: an invitation sent after the decline delivered normally), and the pending row is removed outright with no Accepted/Declined state. The only accept-side trace is the new member appearing in Current Users; a decline leaves no trace at all — accepted and declined invitations are indistinguishable to the inviter. Dialog promise text confirmed verbatim at send time.
Evidence: probe-5-inviter-feedback.md, cases A + B · probe-2-send-accept.md, item 3

### D13 — A declined invitation's links die politely, not with a bare "gone"
Affects: Rule 10 (last sentence) · scenario 4
Status: corrects
Apps: OJS, OMP
Proposed: reopening either link after a decline shows the friendly "Invitation Unavailable" page with Login/Register — not a bare gone/404. The rest of Rule 10 and scenario 4 confirmed verbatim: "Decline Invitation" confirmation page, "Confirm Decline Invitation" button, landing on sign-in, no role granted, row gone.
Evidence: probe-3-decline-cancel-edit.md, item 7

### D14 — Cancelling works exactly as written, in all three apps
Affects: Rule 16 · scenario 5
Status: confirms
Apps: OJS, OMP, OPS
Proposed: plain claim. Row menu is exactly "Edit" + "Cancel Invite"; confirmation recaps email/role/status/affiliation; row gone immediately and after reload; the accept link then shows "Invitation Unavailable" with Login/Register. One nit for the writer: the confirm button is "Cancel Invitation" next to a dismiss button "Cancel" (footnote-worthy label collision).
Evidence: probe-3-decline-cancel-edit.md, item 8

### D15 — A replaced invitation's old links really do return a bare 404
Affects: register A3 · Rule 12 · scenario 6
Status: confirms
Apps: OJS, OPS
Proposed: 🐞 minor — after Edit + resend, the first email's accept link renders raw "404 Not Found" with no journal chrome, while cancel/decline produce the styled Unavailable page. Rule 12 otherwise confirmed: warning dialog verbatim (including the "canceled and, a new one" comma), prefilled two-step wizard at `invitation/edit/{id}`, second email with a fresh id+key that works.
Evidence: probe-3-decline-cancel-edit.md, item 9

### D16 — The user-row Edit wizard behaves as Rule 13 says: immediate removals and masthead changes, additions only by invitation
Affects: Rule 13 · scenario 8
Status: confirms
Apps: OJS (deep), OMP, OPS (spot: same two-step wizard, no search step)
Proposed: plain claims. Remove Role acts at once behind its confirmation; last-role removal blocked with "You cannot remove the role. At least one role must be assigned to the user."; masthead change applies on its own confirmation without enabling the send button; an added role shows in Invitations as pending and not in Current Users until accepted.
Evidence: probe-4-edituser-expiry-disabled.md, item 10

### D17 — The member is emailed immediately when a role is removed or their masthead visibility changes
Affects: Side effects · Rule 13
Status: new
Apps: OJS (delivered); OMP/OPS notification path fails — see D22
Proposed: add to Side effects — role removal sends "You have been removed from a role"; a masthead change sends "Your journal masthead visibility has been updated", and its confirmation dialog says so up front ("The user will be notified of this change.").
Evidence: probe-4-edituser-expiry-disabled.md, item 10

### D18 — An empty new-role row is enough to light the send path; validation waits for the continue click
Affects: Rule 14 (second half) · footnote c
Status: corrects
Apps: OJS
Proposed: refine — "Save And Continue" enables the moment "Add Another Role" is clicked, before any role is picked; missing fields (e.g. Start Date) are rejected only on continue, with an inline required-field error.
Evidence: probe-4-edituser-expiry-disabled.md, item 10

### D19 — Inviting a disabled user is blocked with the warning banner, via the search path
Affects: Rule 14 (first half)
Status: confirms
Apps: OJS
Proposed: plain claim, fuller verbatim text: "The user is currently disabled. The user was disabled. You cannot assign them a role while they are disabled. Please enable the user first to invite them to a role." — with both "Add Another Role" and "Save And Continue" disabled (send step unreachable). Enabled-user control passed.
Evidence: probe-4-edituser-expiry-disabled.md, item 12

### D20 — The Edit row action on a disabled member opens a broken wizard instead of the disabled-user warning
Affects: new (register candidate; touches Rule 13/14 seam)
Status: new
Apps: OJS (observed)
Proposed: 🐞 user-visible — Edit on a disabled user's row pops "Error — The requested resource was not found.", the roles table renders empty, and no disabled-user banner appears; the designed banner exists (it renders on the search path, D19) but this entry point never shows it.
Evidence: probe-4-edituser-expiry-disabled.md, item 12

### D21 — An expired invitation's link shows the Unavailable page and its row is absent from the table
Affects: Rules 2, 4, 11 · Settings bullet 1
Status: confirms
Apps: OJS
Proposed: plain claims. With `[invitations] expiration_days = 0`, the link lapsed right after sending and rendered "Invitation Unavailable" verbatim with Login/Register; the expired row was absent from Invitations while a pending row (positive control) still listed. Caveat for the writer: at a 0-day lifetime, "disappears on expiry" vs "never listed" cannot be split — the absence claim is what is proven.
Evidence: probe-4-edituser-expiry-disabled.md, item 11

### D22 — On OMP and OPS, confirming a masthead visibility change throws a raw missing-email-template error at the manager — the change still sticks
Affects: new (register candidate) · Rule 13 · D17's notification path
Status: new
Apps: OMP, OPS (fail); OJS (clean control: change applies + email delivered)
Proposed: 🐞 user-visible register entry — the manager sees verbatim "Error / Email template USER_ROLE_MASTHEAD_UPDATE not found. The migration script I11800_AddUserRoleMastheadUpdateEmail needs to be run.", yet the flag persists after reload; only the notification fails, no rollback. It also fires mid-send on OMP existing-user invitations (dismissable; the invite still delivers). Settled as a product defect on FRESH OMP/OPS installs, not a test-install artifact: only OJS's `registry/emailTemplates.xml` seeds `USER_ROLE_MASTHEAD_UPDATE` (OMP's and OPS's registries have no MASTHEAD template at all; omp_test/ops_test DBs hold 0 such default rows vs 2 in ojs_test), and the `I11800` migration adds it only on upgraded installs.
Evidence: probe-3-decline-cancel-edit.md, cross-cutting finding · probe-4-edituser-expiry-disabled.md, item 10 · orchestrator registry/DB check 2026-07-31

### D23 — Assorted copy and locale defects a reader will meet on these screens
Affects: new (one minor register entry or footnote, writer's call)
Status: new
Apps: per item; most all-app
Proposed: bundle at minor weight or drop to footnote — (a) the masthead confirmation dialog says "journal masthead" on OMP and OPS; (b) a new invitee's email greets them by their address even when a given name was entered on send; (c) email copy "as a Author"; (d) search-step grammar ("Enter at least one details…", "…invite to take a additional roles"); (e) raw locale keys `##common.help##` and `##userAccess.management.options##` on the backend screens of all three apps.
Evidence: probe-4-edituser-expiry-disabled.md, items 10–12 + cross-item facts · probe-2-send-accept.md, item 3 · probe-3-decline-cancel-edit.md, minor finding

### D24 — Each pass through the edit-mode wizard quietly mints another unsent draft invitation
Affects: register A2 · footnote f-a2
Status: new
Apps: OJS (observed)
Proposed: footnote addition to A2 — one edit session created three draft invitations, only the last was sent; drafts are invisible in the UI, so the daily cleanup (A2) has real, routinely produced rows to reap. A2 itself stays ❓ (cleanup run not probed live).
Evidence: probe-4-edituser-expiry-disabled.md, item 10
