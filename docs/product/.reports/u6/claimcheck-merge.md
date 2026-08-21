# U6 — claim-check merge (change list)

**Frame.** QA documentation of an application's own screens, on a local disposable
test install with seeded accounts. Signed in as each role and used the screens the
way that role would — including typing a URL directly to reach one. Recorded what
the screen offers, what happens when it is used, and where the two disagree, so the
product team can fix it. Nothing here was driven by a request the screens themselves
would not send.

**Target text:** `docs/product/specs/user-invitations.md`.
**Inputs:** `.reports/u6/claimcheck-1.md` (27 claims), `claimcheck-2.md` (59),
`claimcheck-3.md` (42) — 128 claims checked. **Date:** 2026-07-28.

This list carries only what needs changing: every claim found wrong, every claim
left undetermined (with the one observation that would settle it), and observed
defects that are not spec sentences. Claims that hold carry no block.
`Proposed:` is a suggestion; the finalizer decides what enters the spec and at
what weight.

**29 blocks — 14 corrects · 10 new · 1 confirms · 4 undetermined.**

---

### D1 — The "Permit changes to Settings" condition cannot be exercised for the Journal Manager role the Actors table names: the default manager role's own settings cannot be opened from the Roles screen in any app, and OPS ships no other manager-level role at all
Affects: Actors row 1 (See the Invitations table) · Settings "Roles configuration" · footnote a
Status: corrects
Apps: all three — the option is editable only on a secondary manager-level role (Journal editor / Press editor / Production editor on OJS and OMP); on OPS the default manager role is the only manager-level role and offers no such control. Clearing it does take the whole Users & Roles screen away, watched on OJS and OMP.
Proposed: rewrite the Actors condition so it names the roles it can actually apply to; carry the OPS difference as a marked divergence rather than leaving the condition unmarked. Chunk 3 left this undetermined (it could not open a role's form); **chunk 1 settled it**.
Evidence: claimcheck-1.md C6 — settles claimcheck-3.md C35 and C37

### D2 — Clearing that option takes the Users & Roles screen away but leaves the send-invitation wizard open: the same person still reaches the wizard by typing its address and is offered the full invitation flow
Affects: Actors row 1 · new register entry
Status: new
Apps: OJS and OMP (the condition cannot arise on OPS — see D1)
Proposed: 🐞 or a clause on the Actors row — the gate covers one of the two inviter-side surfaces
Evidence: claimcheck-1.md C6

### D3 — Editing a pending invitation is not limited to the actors who see the table: a Section Editor or an Assistant who types the invitation's own address reopens it, changes it and sends a fresh invitation email that supersedes the first; a Reader is refused
Affects: Actors row "Edit a pending invitation" · A1 · A8
Status: corrects
Apps: all three
Proposed: correct the Actors row; extend A8's register entry (or a new one) to the edit address. A1's "an Assistant is stopped at the search step" is true of the create wizard only — the edit wizard has no search step.
Evidence: claimcheck-1.md C10

### D4 — A Section Editor who types the send wizard's address can end another user's role for real: the role is gone from the manager's users table afterwards, and leaving the wizard does not undo it
Affects: Actors row "Change an existing user's current roles" · A3 · A8
Status: corrects
Apps: all three
Proposed: correct the Actors row; the destructive change through a role the screens never offer the wizard to is a heavier case than A8's send
Evidence: claimcheck-1.md C12

### D5 — The wizard reached by address offers a Section Editor the journal's manager-level roles as well: nothing narrows the role list for a role that arrived by typing the address
Affects: A1 · A8 · new
Status: new
Apps: all three
Proposed: a clause on A8 (or its own entry) — the invitation that can be sent this way includes manager-level roles
Evidence: claimcheck-1.md C5

### D6 — On OMP and OPS, a Site Administrator who is not the journal's manager reaches the Invitations table at the older address underneath an error dialog reading "The current role does not have access to this operation."; dismissing it leaves a fully working screen with "Invite to a role"
Affects: A2 · A4 · Actors row 1
Status: new
Apps: OMP and OPS (OJS shows no such dialog)
Proposed: a footnote or register clause — a QA reader meets this dialog first and reads it as the refusal itself
Evidence: claimcheck-1.md C8

### D7 — The acceptance flow's country field is labelled "Country of affiliation", and the review step lists it under that name
Affects: Fields & validation, acceptance-flow row "Country"
Status: corrects
Apps: OJS observed; the same label seen on OJS and OPS in the acceptance walkthrough
Proposed: plain claim — rename the field in the table
Evidence: claimcheck-2.md C23; corroborated by claimcheck-3.md C4

### D8 — The untouched "Journal Masthead" select renders empty, like the two untouched controls beside it — it does not display "Appear on the masthead" — and still fails "Save And Continue" with "This field is required."
Affects: Fields & validation, masthead row · A16
Status: corrects
Apps: OJS
Proposed: drop the "looks filled" premise and A16 with it, keeping the required-field refusal (which holds, and arrives with an error-count banner the spec does not mention)
Evidence: claimcheck-2.md C13, C14

### D9 — An address that already belongs to an account can be invited through the newcomer form, and its accept link opens the existing-user review step and completes normally
Affects: Fields & validation, "Email address" row
Status: corrects
Apps: OJS
Proposed: qualify the "must not already belong to an account" sentence — it bites only when the account appears after the invitation was sent (see D13)
Evidence: claimcheck-2.md C4

### D10 — On the account step with every field blank, the privacy-consent message is the only refusal shown; the empty username and password draw none until the box is ticked
Affects: Fields & validation, Privacy Consent / Username / Password rows · new
Status: new
Apps: OJS
Proposed: a clause on the Privacy Consent row
Evidence: claimcheck-2.md C18

### D11 — The wizard's role table has a fourth column, "End Date": display-only, showing a dash for a new row and for a current role, and the date once a role has been ended
Affects: Rule 5 · Fields & validation
Status: corrects
Apps: OJS
Proposed: plain claim in Rule 5
Evidence: claimcheck-2.md C49

### D12 — "Remove Role" is not only beside added rows: the first row starts without one and gains it as soon as "Save And Continue" fails
Affects: Rule 5
Status: corrects
Apps: OJS
Proposed: plain claim — adjust the sentence
Evidence: claimcheck-2.md C50

### D13 — Acceptance-flow refusals appear under the offending field, not as a banner above the step; and a refusal on the review step shows nothing at all — the final button appears to do nothing, while the invitation stays pending and its link stays live
Affects: Rule 10 (last bullet) · Rule 14 · A5 · footnote w · Fields "Email address" row
Status: corrects
Apps: OJS
Proposed: correct both statements of the banner/modal split; A5's banner half has no observed surface, while its step-list half holds. The silent review step is the visible face of the address-already-taken rule (D9) and deserves its own weight.
Evidence: claimcheck-3.md C12 and C19; claimcheck-2.md C4 (the same silent click)

### D14 — A third untranslated text sits on the acceptance flow: the existing user's single review step shows the literal "{$current}/{$total} steps" beside "1/1 steps"
Affects: Rule 14 ("two display defects") · A5
Status: corrects
Apps: all three; on the single-step existing-user page only — the newcomer's three-step page does not show it
Proposed: raise the count, or write the rule without a count
Evidence: claimcheck-1.md C15 (all three apps); claimcheck-3.md C21

### D15 — The reopened edit-invitation page is headed with the invitee's email address when the invitation carries no typed name
Affects: Rule 6 · A17
Status: corrects
Apps: OJS
Proposed: plain claim — "the invitee's own name" is the email address for a newcomer
Evidence: claimcheck-2.md C57

### D16 — Once one pending invitation's address has gained an account, the Invitations table renders its rows without Name and Email cells, sliding every later value one column left; the same rows' cancel dialog shows an empty email
Affects: Rule 3 · new register entry
Status: new
Apps: OJS; rows in journals without such an invitation render all six cells
Proposed: 🐞 — a QA reader following scenario 1 can land on it
Evidence: claimcheck-2.md C37; corroborated by claimcheck-3.md (incidental)

### D17 — The "logged in as a different user" refusal offers no close control: "Logout" is its only button, and pressing Escape is the only other way out; the refusal is drawn over a fully rendered account form
Affects: Rule 12
Status: new
Apps: OJS (chunk 1 saw the same refusal over the rendered form in all three)
Proposed: a clause on Rule 12 — the spec tells a reader what dismissing does without saying dismissal is Escape-only
Evidence: claimcheck-3.md C14; claimcheck-1.md C16

### D18 — The acceptance success dialog writes the app's own name, exactly as the step names do
Affects: Rule 10 (accepting bullet)
Status: corrects
Apps: all three
Proposed: extend the app-name caveat to the dialog's wording
Evidence: claimcheck-3.md C9

### D19 — "Invitation Unavailable" offers Login and Register as links styled as buttons
Affects: Rule 13
Status: corrects
Apps: OJS and OPS
Proposed: wording only — "buttons" is how they read on screen; the finalizer may leave it
Evidence: claimcheck-3.md C15

### D20 — An invitation link that names an invitation but carries no key renders the same page with nothing on it as a link that names no invitation
Affects: Rule 13 · A15
Status: new
Apps: all three
Proposed: widen A15's symptom sentence
Evidence: claimcheck-1.md C2

### D21 — The immediate changes made on the details step each mail the affected user — one message when a role is ended, another when the masthead choice changes — so the window before any invitation is sent is not silent
Affects: Rule 9 · Side effects (Immediate role changes; No mail on the other exits)
Status: new
Apps: OJS
Proposed: one clause, with the mechanics linked to *Users management*
Evidence: claimcheck-3.md C1, C2

### D22 — Invitations expire three days after they are sent, on every fleet
Affects: Rule 2 · Settings "Invitation validity"
Status: confirms
Apps: all three
Proposed: keep as written. **Cross-chunk note:** chunk 2 marked this undetermined because no screen in the flow states the validity period; chunks 1 and 3 exercised it and read the three-day window off the invitations the apps themselves stamped. The claim stands on chunks 1 and 3; what chunk 2 records is that a reader is never told the period on screen.
Evidence: claimcheck-1.md C26; claimcheck-3.md C32; claimcheck-2.md C28

### D23 — Nothing that happens at an invitation's expiry could be observed on this install: neither the nightly deletion nor the absence of mail at expiry
Affects: Rule 2 · Side effects "Nightly cleanup" · Side effects "No mail on the other exits"
Status: undetermined
Apps: all three
Settling observation: an install whose scheduled tasks run, where the expired invitation is gone the following day and no message reaches invitee or inviter. Chunk 1 notes this is not visible on any screen, so it sits outside what this campaign's screens can show. Cancelling and declining were watched and send no mail.
Evidence: claimcheck-1.md C27; claimcheck-2.md C30; claimcheck-3.md C30, C31

### D24 — Everything the spec says happens while ORCID is enabled is unobserved: the wizard's ORCID field, the acceptance flow's "Verify ORCID iD" step and what it offers, the table's ORCID icon, and what a verified-ORCID existing user sees
Affects: Fields "ORCID iD" row · Rule 3 (ORCID icon) · Rule 10 (ORCID bullets) · Settings "ORCID enabled" · A13 · Cross-feature *ORCID integration*
Status: undetermined
Apps: all three — the negative half (nothing ORCID-related renders while it is off, except the "not verified" line, which holds) is confirmed
Settling observation: an install with ORCID configured and an account carrying a verified iD.
Evidence: claimcheck-2.md C7, C36; claimcheck-3.md C5, C8, C33, C42

### D25 — On the Users & Roles ORCID tab, ticking "Enable ORCID functionality" and saving does not stick: the box is clear again and the ORCID fields gone on a fresh load of the tab
Affects: new — *ORCID integration*'s screen, not this spec's
Status: new
Apps: OJS
Proposed: pass to that feature by link; recorded here because it is why D24 stays undetermined
Evidence: claimcheck-2.md C7

### D26 — Whether a refused send answers the inviter with an unexplained modal could not be exercised: three sends aimed at failing all succeeded
Affects: Rule 14 · A6
Status: undetermined
Apps: OJS
Settling observation: one send the wizard actually refuses, and what the inviter is shown when it does. **This is "we could not make it happen", not "it does not happen"** — A6 rests on the earlier probe and nothing observed contradicts it.
Evidence: claimcheck-3.md C20

### D27 — The reviewer one-click access link's landing behaviour was not watched
Affects: Cross-feature *Reviewer's review*
Status: undetermined
Apps: OJS
Settling observation: a submission in review on a journal with one-click reviewer access enabled, opening the reviewer's emailed link.
Evidence: claimcheck-3.md C41

### D28 — On the Roles screen, the per-row "Settings" control opens nothing — no dialog and no navigation; a role's own form is reached instead by expanding its row and using "Edit"
Affects: new — *Roles configuration*'s screen, not this spec's
Status: new
Apps: OJS (chunk 1 used the expander-and-Edit route successfully in all three)
Proposed: pass to that feature by link. Recorded because the inert control is what left chunk 3 unable to check D1, and because the spec's Settings bullet sends a reader to that screen.
Evidence: claimcheck-3.md C35; route confirmed by claimcheck-1.md C6

### D29 — The code-anchor list points at a path where the expired-invitation cleanup job does not sit
Affects: Reference — code anchors
Status: corrects
Apps: n/a
Proposed: correct the path (the file sits directly under the shared library's `jobs/invitations/`, not under `classes/`)
Evidence: claimcheck-1.md (closing notes)
