# U6 claim-check chunk B — Fields & validation, Rules & state (RUNBOOK step 8)

Date: 2026-07-31. Target: `lib/pkp/docs/product/specs/user-invitations.md` (Fields & validation, Rules & state).
Environment: scratch contexts `u6cb-ojs` + bilingual control `u6cb-ojs2` (OJS :8000), `u6cb-omp` (OMP :8100), `u6cb-ops` (OPS :8200); managers `u6cbmgr`, `u6cbmgr2`, `u6cbmgromp`, `u6cbmgrops`; recipients `u6cb*@mail.test`; seeded roster and `publicknowledge` untouched. Probes were headless-Chromium scripts driving the real screens only (no hand-built requests).

Verdict tally: **18 HOLDS · 5 WRONG · 0 UNRESOLVED** (+1 fact-only accessibility record).

---

## Send wizard — Fields & validation

### B1. Search field required to pass step 1 — HOLDS
Claim: «"Search for a user by email address, username, or ORCID iD" | yes (to pass step 1)».
Case: empty input, press "Search User". Observation: `u6cb-ojs/invitation/create/userRoleAssignment`, u6cbmgr — wizard stays on "STEP 1 - Search User"; new text appears: **"Provide at least one search criteria."** (screenshot `B0-empty-search.png`).
Empty input is rejected on step 1 exactly as claimed.

### B2. "a miss must be a valid email address, which becomes the invitee's address" — WRONG
Case: obviously malformed address `notanemail` typed into the search step. Observation (OJS, u6cbmgr): pressing "Search User" **advances to "STEP 2 - Enter details and invite for roles"** with the miss banner "The user does not have a role in this journal"; the invalid input is silently dropped — the details step's Email field is empty (`inputValue === ""`). Pressing "Save And Continue" there is what finally blocks, with **"This field is required when user id is not present."** on Email.
A malformed miss passes step 1; validity is only enforced one step later, and the typed text never becomes the invitee's address.

### B3. Personal fields: email only required — HOLDS
Claim: «Email / Given Name / Family Name / Affiliation | email only».
Case: Given Name, Family Name, Affiliation all left empty for `u6cbnew1@mail.test`. Observation: details step passes and the invitation sends ("Invitation Sent" dialog); the Invitations row renders with an empty Name cell. Helper text under both name fields reads "If you know the given/family name of the user, you can enter the information. However, this information can be changed by the user."

### B4. Start Date required — HOLDS
Case: role chosen ("Journal editor"), Start Date left empty, "Save And Continue". Observation: wizard stays on step 2 with inline **"This field is required."** under Start Date (screenshot `2-empty-date.png` context).

### B5. Start date in the past accepted at send — HOLDS
Claim (Rule 8 / field table): a past date is accepted; it "takes effect as 'today' at acceptance".
Case: Start Date `01.01.2020`. Observation: the date field takes the value with no complaint (no `min` attribute; the only remaining error on continue was the masthead field — screenshot `2-past-date.png`); after the masthead was set the step advanced. The send side accepts a past date. (The clamp-to-today at acceptance was not re-probed — footnote m's territory, outside this chunk.)

### B6. End Date "(per role row) | no | —" — WRONG
Case: inspect a new-role row for any end-date control. Observation (OJS aria snapshot of the role table): the END DATE cell of every *added* role row renders the literal text **"---"** and contains **no input at all** (row = role select · Start Date input · `---` · masthead select · Remove Role). An end date can never be entered when inviting; the column only displays values on an existing member's current-role rows. The field table lists End Date as an optional *field* of the send wizard — the wizard offers no such field.

### B7. Journal Masthead "yes (preset)" — WRONG
Case: fresh role row, masthead untouched. Observation (OJS + OMP identically): the masthead `<select>` renders **blank** (`value === ""`; options "Appear on the masthead" / "Does not appear on the masthead", no selected option), and "Save And Continue" blocks with inline **"This field is required."** under Journal Masthead until a value is picked (screenshot `2-past-date.png` shows the blank select with the error). Required — yes; preset — no.

### B8. "Reviewer roles never appear and the choice is fixed" — WRONG
Case: choose "Reviewer" in the role select (OJS `u6cb-ojs`). Observation: the masthead cell swaps to the fixed text **"Appear on the masthead"** (no select; table selects drop from 2 to 1), and the step continues to step 3 without any masthead input (screenshot `C-reviewer-row.png`). The choice is indeed fixed — but the screen fixes it to *appear on the masthead*, the opposite of the spec's "never appear". (Whether reviewers actually render on the public masthead page was not probed; on-screen, the wizard asserts they appear.)

## Accept wizard — Fields & validation

All on OJS invitation `u6cb-ojs/invitation/accept?id=140&key=…` for `u6cbnew1@mail.test` (anonymous browser), unless marked OMP/OPS.

### B9. Password "At least six characters — the minimum the field states on screen" — HOLDS
Boundary: 5 characters `abcde` → inline **"The password must be at least 6 characters."**; exactly 6 (`abcdef`) → step advances. Field helper text on screen: **"It should be at least 6 characters long and could be a combination of uppercase letters, lowercase letters, numbers and symbols"** — the minimum is stated. Same helper text verbatim on OMP (`u6cb-omp` accept wizard).

### B10. Username "Must not be taken" — HOLDS
Case: existing username `u6cbmgr`. Observation: inline **"There is already an existing user with the username u6cbmgr. Try another one."**; a free username passes. (Username helper text on screen: "It could be a combination of uppercase letters, lowercase letters or numbers".)

### B11. Privacy consent blocks with the quoted text; label links to the Privacy Statement — HOLDS
Case: valid username + 6-char password, checkbox unchecked. Observation: inline **"Please confirm that you have read and agree privacy statement"** — verbatim as quoted in the spec. Label: "Yes, I agree to have my data collected and stored according to the Privacy Statement", whose link points to `{context}/about/privacy` (checked on OJS and OMP). Ticking it lets the same 6-char password through.

### B12. "Enter details": given name + country required; family name/affiliation optional — HOLDS
Case: everything left empty on "STEP 2 - Enter details", continue. Observation: error summary **"Please correct 2 errors. Go to Given Name: This field is required. Go to Country of affiliation: This field is required."** — exactly two. Filling only Given Name + Country advances to review; the account was created with family name and affiliation empty. DOM confirms: `givenName-en` required, `familyName-en`/`affiliation-en` not, `userCountry` required.

### B13. Final accept button per app — HOLDS (all three)
Claim (Rule 8, OJS vocabulary): the final button reads "Accept And Continue to OJS".
Observed verbatim on the "Review & create account" step: OJS **"Accept And Continue to OJS"**, OMP **"Accept And Continue to OMP"**, OPS **"Accept And Continue to OPS"**. Step-1 pill likewise substitutes: "Create OJS account" / "Create OMP account" / "Create OPS account". Review step shows the Edit button (new user) on all three.

### B14. Closing dialog and "View All Submissions" land on sign-in (Rule 9 / A4) — HOLDS
Observation (OJS): dialog **"You've been assigned a new role in OJS — Congratulations on your new role in OJS! …"** with single button "View All Submissions"; clicking it lands on `u6cb-ojs/login?source=%2Findex.php%2Fu6cb-ojs%2Fsubmissions` — heading "Login", signed out, as A4 records.

## Rules & state

### B15. Rule 3 — one live invitation per person; a new send replaces the pending one — HOLDS (with an uncovered consequence)
Case: second fresh invitation (different role) sent to the still-pending `u6cbnew1@mail.test` through the normal create wizard. Observation: the Invitations table afterwards holds **one** row for that address (the new role, "Section editor"); the first email's accept link (`invitation/accept?id=136&key=n9q7wN`) then returns a bare **"404 Not Found"** (HTTP 404, no journal styling — screenshot `B5-link1-after-replacement.png`). Replacement happens exactly as claimed. Note for the register: Rule 4 + A3 attribute the bare-404 fate only to invitations "replaced through editing"; a plain re-send replacement produces the identical bare 404, which the Rule 4 enumeration doesn't cover.

### B16. Rule 4 — "Invitation Unavailable" page for answered/cancelled invitations — HOLDS (plus a copy fact)
Observed after **accept** (OJS), **decline** (OMP), **cancel** (OPS): heading **"Invitation Unavailable"**, body **"This invitation is no longer available. It may have already been accepted, declined, or expired. Please contact the journal manager for further assistance."**, buttons **"Login"** and **"Register"** — on all three apps. Copy fact behind the spec's "…" ellipsis: the tail says **"journal manager" verbatim on OMP and OPS too** (press/server vocabulary not substituted) — an A7-family item the register doesn't currently list.

### B17. Rule 6 — wrong-person refusal — HOLDS
Case: signed-in manager (u6cbmgr) opens the accept link for `u6cbwrong@mail.test`. Observation: dialog **"Invitation not accepted. You're logged in as a different user."** + "Please log out and sign in with the correct account to accept this invitation." with a **"Logout"** action (screenshot `B7-wrong-person.png`).

### B18. Rule 10 — decline is deliberate — HOLDS
Observed on OMP and OPS: decline link opens page headed **"Decline Invitation"** — "Are you sure you want to decline this invitation? Confirm the decline by clicking the button below." — sole button **"Confirm Decline Invitation"**; confirming lands on `{context}/login`; the accept link thereafter shows the Unavailable page (B16).

### B19. Rule 11 — rows read "Invited {date}" — HOLDS
Observation (OJS): row cell **"Invited 2026-07-31"**; same string in the OPS cancel recap. Table lists only pending rows (replaced row gone — B15).

### B20. Rule 15 — «Its only button, "View All Users", leaves the browser on the wizard — the sender returns to Users & Roles on their own» — WRONG
Case: click "View All Users" in the "Invitation Sent" dialog. Observation: the browser **navigates to Users & Roles** (`…/management/settings/access`, heading "Users & Roles") — reproduced on OJS single-locale scratch (`u6cb-ojs`), OJS **bilingual** scratch control (`u6cb-ojs2`, en+fr_CA, URL `…/u6cb-ojs2/en/management/settings/access`), OMP and OPS. Footnote g's "the store's redirect to `management/settings/access` never fires (probe-2, item 3)" is contradicted by all four runs; the pre-click URL only gains `#userInvited` momentarily. The dialog's promise text itself matches A5's quote verbatim ("…You can be updated about the user's decision on the Users & Roles page, your OJS notifications and/or your email", OMP/OPS substituting their names).

### B21. Rule 15 — final button "Invite user to the role"; success dialog "Invitation Sent" — HOLDS
Observed verbatim on OJS, OMP, OPS: step-3 button **"Invite user to the role"**; dialog title **"Invitation Sent"**. (Step buttons before it: step 1 "Search User", step 2 "Save And Continue".)

### B22. Footnote d / Rules 1+11 — status machinery as screens can show it — HOLDS
Case (finalizer's flag): does any invitation screen ever render a status or expiry beyond "pending"? Observation: the Invitations table's columns are NAME · EMAIL · INVITATIONS · STATUS · AFFILIATION (+ sr-only More Actions); the Status cell is the single string **"Invited {date}"** (`userInvitation.status.invited`, built from `createdAt`); the cancel confirmation's recap line likewise reads "Status: Invited 2026-07-31". No expiry date, no deadline, and no other status word is ever rendered — component source (`UserInvitationManager.vue` lines 67–75, `UserInvitationManagerCancelInvitationDialogBody.vue`) hard-codes that one key. Nothing a screen shows contradicts Rule 1's no-expired-state model; equally, nothing on screen ever warns of the Rule 2 deadline.

### B23. Scenario 5 / footnote q — cancel dialog recap and buttons — HOLDS
Observation (OJS + OPS): dialog **"Cancel Invitation"** listing "Email: … / Role: … / Status: Invited {date} / Affiliation:" with confirm button **"Cancel Invitation"** beside dismiss **"Cancel"**; row menu items are "Edit" and "Cancel Invite".

## Accessibility fact record (scope item 4 — facts only)

Send wizard role table, OJS `u6cb-ojs/invitation/create/userRoleAssignment`, after "Add Another Role" (two rows):

- Every added row renders its controls with the **same ids**: `select#-userGroupId-control`, `input#-dateStart-control`, `select#-masthead-control` (the `formId` segment of `FieldBase.compileId()` is empty, hence the leading hyphen). Document-wide duplicate-id scan with two rows: `-userGroupId-control`, `-dateStart-control`, `-masthead-control` (plus the unrelated pre-existing `announcer`).
- Labels associate via `label[for]`, so all labels resolve to the **first** row's controls. Playwright aria snapshot, row 1: `combobox "Select a new role * Required …"`, `textbox "Start Date * Required …"`; row 2: **bare `combobox` / `textbox` with no accessible name**. No `aria-label`/`aria-labelledby` on any of the six controls.
- Same component (`UserInvitationUserGroupsTable.vue`) serves OMP/OPS; the OJS rendering reproduces the duplicated-id observation made there. Screenshot `2-two-role-rows.png`; raw dumps in the probe log.

## Not probed here
Role options excluding already-held/already-chosen roles (footnote i's disable logic); Rule 8's past-date clamp at acceptance; ORCID step (off in scratch contexts); Rule 2 lifetime (already live-checked per footnote e).

## Security routing
Nothing from this chunk was routed to the private security file — no probe surfaced data or actions beyond the acting role's entitlement.

## Proposed register/spec deltas (for the maintainer, not applied)
- Field table: search-step miss behavior (B2), End Date row (B6), masthead preset (B7), reviewer masthead polarity (B8).
- Rule 15 / footnote g: "View All Users" does navigate to Users & Roles (B20).
- A3/Rule 4: extend the bare-404 case to re-send replacement (B15).
- A7: add the unavailable page's "journal manager" tail on OMP/OPS (B16).
- Candidate new register entry: duplicated ids / unnamed controls on added role rows (accessibility fact record above).
