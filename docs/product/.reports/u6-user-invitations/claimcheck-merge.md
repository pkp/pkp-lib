# U6 User invitations — claim-check merge (RUNBOOK step 8)

Change list from claimcheck-A.md + claimcheck-B.md, in the step-3b digest
block schema. Verdicts are the merge agent's judgment (claude, 2026-07-31);
the fold agent decides under step 4's rules. The two chunks disagreed on
nothing; there are no ❓ blocks.

Tally: 10 blocks — 8 corrects · 2 new · 0 confirms.

---

### CC1 — "View All Users" on the "Invitation Sent" dialog navigates the sender to Users & Roles — it does not leave them on the wizard
`Affects:` Rule 15 | footnote g | register A5 | scenario 1
`Status:` corrects
`Apps:` all three — live on OJS (single-locale and bilingual scratch, two consecutive runs), OMP, OPS
`Proposed:` 🐞→✅ for the navigation half only. Rewrite Rule 15's tail: the
success dialog's "View All Users" button returns the browser to Users & Roles
(`management/settings/access`); delete "leaves the browser on the wizard — the
sender returns to Users & Roles on their own". Keep the ⚠ A5 marker solely on
the promised-decision-updates sentence — A5's register entry (channels stay
silent) survives intact; its dialog-navigation half dies. Footnote g: replace
"only re-anchors the wizard URL — the store's redirect to
`management/settings/access` never fires (probe-2, item 3)" with the
live-confirmed navigation, dated 2026-07-31 (claim check; the earlier probe
read the pre-click `#userInvited` anchor, not the post-click page). Scenario 1:
"press View All Users — the browser returns to Users & Roles" replaces
"return to Users & Roles".
`Evidence:` claimcheck-A Claim 7; claimcheck-B B20

### CC2 — A malformed search miss is not blocked on step 1: the wizard advances with the typed text discarded, and only the details step's Email field enforces validity
`Affects:` Fields & validation, send-wizard row 1 ("Search for a user…") | footnote h
`Status:` corrects
`Apps:` probed on OJS; shared component, no app branch — all three
`Proposed:` rule-text replacement for the row's Rules cell: a miss — even a
malformed address like `notanemail` — advances to "Enter details" with "The
user does not have a role in this journal"; the typed text is silently dropped
(the Email field arrives empty) and validity is enforced there: "This field is
required when user id is not present." Delete "a miss must be a valid email
address, which becomes the invitee's address". Footnote h: drop "+ the input
must be a valid email to proceed"; the observed empty-input message is
"Provide at least one search criteria." (B1).
`Evidence:` claimcheck-B B2 (empty-input text: B1)

### CC3 — The send wizard offers no End Date input: an added role row's END DATE cell renders the literal "---"
`Affects:` Fields & validation, send-wizard "End Date" row
`Status:` corrects
`Apps:` probed on OJS (aria snapshot); shared component — all three
`Proposed:` rule text: End Date cannot be entered when inviting — every added
role row's END DATE cell shows "---" and contains no input; the column only
displays values on an existing member's current-role rows (Rule 13's table).
Either rewrite the row to say so or drop it from the "fields" table with a
note.
`Evidence:` claimcheck-B B6

### CC4 — Journal Masthead is required but NOT preset: the select starts blank and "Save And Continue" blocks with "This field is required." until a value is picked
`Affects:` Fields & validation, send-wizard "Journal Masthead" row
`Status:` corrects
`Apps:` OJS and OMP identically; shared component — all three
`Proposed:` change "yes (preset)" to "yes"; rule text notes the select renders
blank (options "Appear on the masthead" / "Does not appear on the masthead",
none selected) and the inline error "This field is required." appears until
the sender picks one.
`Evidence:` claimcheck-B B7

### CC5 — A Reviewer role row fixes the masthead choice to "Appear on the masthead" — the opposite of the spec's "never appear"
`Affects:` Fields & validation, send-wizard "Journal Masthead" row | footnote i
`Status:` corrects
`Apps:` probed on OJS; shared component — all three
`Proposed:` rule-text replacement: choosing Reviewer swaps the masthead cell
to the fixed text "Appear on the masthead" (no select), and the step continues
without a masthead input. Keep the claim to the wizard's on-screen text —
whether reviewers actually render on the public masthead page was not probed;
if the writer wants the public-page fact asserted, it needs its own probe.
`Evidence:` claimcheck-B B8

### CC6 — Re-sending to a still-pending address also kills the old links with the bare 404: A3's fate is not edit-only
`Affects:` register A3 | Rule 4 (A3 sentence) | Rule 12 | footnote f-a3 | Rule 3 (cross-ref)
`Status:` corrects
`Apps:` OJS (both chunks, deep) + OMP spot-check; same shared replacement path — all three
`Proposed:` widen A3's scope from "an invitation replaced through Edit" to
"a replaced invitation — whether through Edit or a plain new send to the same
person/address"; Rule 4's ⚠ A3 sentence reads "links of a replaced invitation
(edited, or superseded by a new send)"; f-a3 gains the claim-check evidence
(old accept link → bare "404 Not Found", no journal styling, after a plain
re-send). Rule 3 gets the ⚠ A3 marker so the re-send path carries the warning
where a reader meets it.
`Evidence:` claimcheck-B B15; claimcheck-A Claim 6

### CC7 — The "Invitation Unavailable" page's tail says "contact the journal manager" on presses and preprint servers too
`Affects:` register A7 | footnote f-a7
`Status:` corrects
`Apps:` OMP and OPS (defect); OJS correct by vocabulary
`Proposed:` add to A7's copy list: the unavailable page's closing line
"Please contact the journal manager for further assistance." is not
substituted to press/server vocabulary on OMP and OPS; f-a7 gains the pointer.
Stays inside the existing A7 bundle — no new register entry.
`Evidence:` claimcheck-B B16

### CC8 — The second send gives no hint a pending invitation exists: replacement is silent
`Affects:` Rule 3 | footnote d
`Status:` corrects
`Apps:` OJS (deep) + OMP spot-check; shared code — all three
`Proposed:` sharpen Rule 3's wording to observed behavior: "…silently replaces
any earlier pending one — the second send's wizard gives no warning that a
pending invitation exists, and the Invitations table afterwards holds only the
newer row (the newer send's name and role)." Footnote d — previously code-only
— gains the live-probe date 2026-07-31 (table before/after, both emailed
links checked).
`Evidence:` claimcheck-A Claim 6; claimcheck-B B15

### CC9 — Every added role row repeats the first row's form-control ids, so second-row controls have no accessible name
`Affects:` new (register — proposed A8, structural accessibility, distinct from the A7 copy bundle)
`Status:` new
`Apps:` all three — shared component `UserInvitationUserGroupsTable.vue`; live locator evidence on OJS
`Proposed:` 🐞 register entry, impact user-visible (assistive-technology
users): in the send wizard's role table, every added row renders
`#-userGroupId-control`, `#-dateStart-control`, `#-masthead-control` — the
same ids as row 1 (empty `formId` segment in `FieldBase.compileId()`), so all
`label[for]` associations resolve to the first row and the aria snapshot shows
row 2's combobox/textbox with no accessible name; no `aria-label` /
`aria-labelledby` anywhere in the table. A screen-reader user cannot tell
which row's role, start date, or masthead field they are editing.
`Evidence:` claimcheck-B "Accessibility fact record" (test-author observation,
2026-07-31; re-verified there with duplicate-id scan and aria snapshot)

### CC10 — PHP undefined-property warnings on reopening an edited invitation (UserRoleAssignmentInviteResource.php:61-62) — no screen-visible symptom
`Affects:` new (candidate only — proposed for .reports, not the register)
`Status:` new
`Apps:` observed on OJS server logs (test authoring); shared resource class
`Proposed:` drop to .reports only. "The screen is the instrument": neither
claim-check chunk records any screen-visible symptom on this install — the
edit wizard reopens and functions (claimcheck-A Claim 7 environs, claimcheck-B
B15/B23 exercised the edit/replace paths without incident). The warnings live
in server logs; this record is their home unless a display-errors install
surfaces them on screen.
`Evidence:` test-author observation, 2026-07-31; absence of screen symptom —
claimcheck-A and claimcheck-B throughout (no matching observation in either)
