---
name: reviewer-assignment-and-management
scope: An editor finds, invites, edits, reminds, thanks, unassigns, reinstates reviewers and reads their reviews on a review round's Reviewers panel
apps: [ojs, omp]
shared: pkp-lib
status: verified
atlas-claims: [AFFW-213, AFFW-214, AFFW-215, AFFW-217, AFFW-218, AFFW-219, AFFW-220, AFFW-221, AFFW-222, AFFW-223, AFFW-224, AFFW-486, AFFW-488, AFFW-489, AFFW-490, AFFW-491, AFFW-492, AFFW-493, AFFW-494, AFFW-495, AFFW-496, AFFW-497, AFFW-498, AFFW-500, AFFW-501, AFFW-503, AFFW-504, AFFW-505, AFFW-506, AFFW-621, AFFW-622, AFFW-623, AFFW-624, AFFW-625, AFFW-626, AFFW-627, AFFW-628, AFFW-629, AFFW-630, AFFW-631, AFFW-632, AFFW-633, AFFW-634, AFFW-635, AFFW-636, AFFW-637, AFFW-638, AFFW-639, AFFW-640, AFFW-641, AFFW-642, AFFW-643, AFFW-644, AFFW-645, AFFW-646, AFFW-647, AFFW-648, AFFW-649, AFFW-650, AFFW-651, AFFW-652, AFFW-653, AFFW-654, AFFW-655, AFFW-656, AFFW-657, AFFW-658, AFFW-659, AFFW-660, AFFW-661, AFFW-662, AFFW-663, AFFW-664, AFFW-665, AFFW-668, AFFW-708, GRID-012, GRID-026, GRID-086, GRID-098, VUE-016, VUE-046, VUE-067, VUE-068, API-048, MAIL-026, MAIL-034, MAIL-038, MAIL-039, MAIL-040, MAIL-041, MAIL-042, MAIL-043, MAIL-044, MAIL-045, MAIL-046, NOTIF-019, NOTIF-048, SET-020, JOB-012]
---

# Reviewer assignment & management {OJS OMP}

> Conventions (markers, badges, footnotes): [Reading a spec](GLOSSARY.md#reading-a-spec).

## Purpose

Once a submission is in a review round, someone has to find the reviewers,
invite them, keep them on schedule, and read what they send back. That work
happens in the "Reviewers" panel on the review stage: an editor searches the
journal's reviewer pool (or creates a brand-new reviewer account, or enrolls
an existing user), sets the deadlines, the review type and the files the
reviewer may see, then follows each invitation through its life — reminding an
overdue reviewer, recording a response the reviewer gave by email, reading and
confirming the finished review, thanking the reviewer, or unassigning and
later reinstating them. This spec covers that panel and every window it opens.
The round machinery around it — round numbers, the status box, the decision
buttons — is the neighboring feature
[→ round machinery](U26-review-stage-and-rounds.md#rounds); what the reviewer
themself sees and does is the *Reviewer's review* feature; how review
defaults and forms are configured is *Review setup & review forms*.

OPS does not install a review stage or any reviewer role: a preprint server's
workflow goes straight from submission to Production, no "Reviewers" panel
exists on any screen, and its role settings offer no reviewer group. The
absence of the stage itself is documented with
[→ the review stage](U26-review-stage-and-rounds.md#rounds). <sup>p</sup>

On a press, everything in this file runs twice: the Internal Review stage and
the External Review stage each carry their own Reviewers panel with the same
controls. The reviewer pool differs by stage — searching the Add Reviewer
window offers the press's Internal Reviewers on the internal stage and its
External Reviewers on the external stage [OMP1](#omp1), though the window's
opening, unsearched list does not yet apply that split ⚠ [OMP2](#omp2).
<sup>o</sup>

## Actors & permissions

The Reviewers panel renders on the editorial view of the review stage; who
can open that stage at all is
[→ stage access](U26-review-stage-and-rounds.md#rounds) (the *Review stage &
rounds* actors table). Within the panel, "review managers" below means:
Journal Manager, Editor, and an assigned Section Editor or Guest Editor. A
Site Administrator takes part through whatever journal role grants them
stage access — holding no role in the journal, they are refused at the
workflow screen itself ([A3](#a3), retired) <sup>a</sup>. An "assistant-level participant" is a member of an assistant
group assigned to the stage (on the seeded installs the one such group the
stage's assignment dialog offers is Funding Coordinator). The Author reaches
the same workflow screen for their own submission but, while reviews are
underway, gets no Reviewers panel at all — no table, no reviewer identities;
whether and when a reduced read-only list of completed reviews appears is
owned by
[→ reading reviews as the author](U26-review-stage-and-rounds.md#author-read-review).

| Action | Who may — and when |
|--------|--------------------|
| **See the Reviewers panel and its rows** | • Review managers and assistant-level participants — every round of the stage<br>• Author — no panel while reviews are underway; any reduced list of completed reviews is the neighboring feature's (see preamble) <sup>a</sup> |
| **See declined and cancelled rows** | • Review managers only — an assistant-level participant's table silently omits those rows ⚠ [A6](#a6) <sup>a</sup> |
| **Add a reviewer** (search & select, "Add Reviewer") | • Review managers and assistant-level participants — any round, including past rounds (the past-round oddity is recorded with the [→ round machinery](U26-review-stage-and-rounds.md#rounds)) <sup>a</sup> |
| **"Create New Reviewer" / "Enroll Existing User"** | • Journal Manager, Editor, assigned Section Editor and Guest Editor — the two links inside the Add Reviewer window (a Site Administrator's access runs through such a journal role — see the preamble)<br>• assistant-level participants — the two links never appear <sup>a</sup> |
| **Manage an assignment** (row actions: "Review Details", "Email Reviewer", "Edit", "Send Reminder", "Thank Reviewer", "Revert Decision", "Read Review", "Resend Review Request", "Unassign Reviewer"/"Cancel Reviewer", "Reinstate Reviewer", "Log Response", "History") | • Review managers and assistant-level participants — per assignment state (Rule 3 for when each appears; the operations in Rules 12–21; the Email Reviewer window is described under Fields) <sup>a</sup> |
| **"Editorial Notes"** | • Site Administrator, Journal Manager, Editor, Section Editor, Guest Editor — about a user holding a Reviewer role, never about themselves<br>• assistant-level participants — the entry is absent <sup>l</sup> |
| **"Login As" the reviewer** | • whoever may impersonate that reviewer — the row entry appears only then; the rule is [→ who may impersonate whom](U01-login-and-sessions.md#who-may-impersonate) <sup>l</sup> |
| **Author on the workflow screen** | • none of the above — an assigned Author gets none of these entries, even when they also hold an editorial role on the submission (the panel itself is absent, see the preamble); the server-side refusals behind that, including the read operations for anonymous review types, are recorded in the footnote <sup>a</sup> |

## Fields & validation

**Add Reviewer window** (title "Add Reviewer"; opened by the panel's "Add
Reviewer" button). Its upper half is the reviewer search (Rules 5–8); once a
reviewer is chosen, the name and email address are shown with a "Change" link
back to the search,
and the lower half holds the request form shared by all three add modes:

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| Reviewer selection | yes | The request form below (and its submit button) stays hidden until a reviewer is chosen — the window offers no way to submit without one <sup>d</sup> |
| "Choose a predefined message to use, or fill out the form below." | no | Email template chooser; renders on every add, even with no alternate request templates to offer — a one-option select ("Review Request") ⚠ [A19](#a19). When alternates do exist, all of them are listed ([A5](#a5), retired — the access check it questioned was reverted upstream) <sup>d</sup> |
| "Email to be sent to reviewer" | no | Rich-text request letter, prefilled from the chosen template; placeholders for name, deadlines and the review link are filled at send time. Effectively required: submitting with the letter emptied fails with no on-screen feedback of any kind, yet still creates the assignment and never sends the request email ⚠ [A18](#a18) <sup>d</sup> |
| "Do not send email to Reviewer." | no | Checkbox; skips the request email (the assignment is still created) <sup>d</sup> |
| "Response Due Date" / "Review Due Date" (under "Important Dates") | yes | Date pickers, prefilled per the journal's review setup (Rule 9). The permanent guidance "Review due date must be greater or equal to response due date." states the rule (the Edit window shows it too); submitting with the dates inverted is refused with no visible feedback — the window stays open and nothing is added ⚠ [A8](#a8). The pickers (shared with the Edit and Resend windows) take calendar picks or a date typed in the YYYY-MM-DD format (e.g. 2026-08-02); input in any other format looks accepted on screen while the old value is silently submitted ⚠ [A16](#a16) — and they accept dates already past without any warning ⚠ [A17](#a17) <sup>f</sup> |
| "Files To Be Reviewed" | no | Collapsed file list with one checkbox per file of the round; all ticked by default. The inline warning "No Files Selected" appears here only when the round has no files at all — unticking every box triggers no warning in this window (Rule 11) <sup>d</sup> |
| "Review Type" | yes | Radio group "Anonymous Reviewer/Anonymous Author", "Anonymous Reviewer/Disclosed Author", "Open"; preselected per the journal's review setup <sup>d</sup> |
| "Public Visibility" | no | Checkbox "Publicly Show Reviewer Comments"; preselected per the journal's public-visibility default <sup>d</sup> |
| "Review Form" | no | Select, shown only when the journal has active review forms; default "None / Free Form Review"; a section may designate a default form (Rule 10) <sup>d</sup> |

**Create New Reviewer mode** (link "Create New Reviewer" inside the Add
Reviewer window) adds account fields above the shared form:

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| Given name | yes | Required in the site's primary language <sup>e</sup> |
| Family name | no | A family name in a language requires a given name in that language <sup>e</sup> |
| Affiliation | no | Free text <sup>e</sup> |
| "Reviewing Interests" | no | Tag field over the site-wide interests vocabulary; suggestions appear while typing <sup>e</sup> |
| Username | yes | Hint: "The username must contain only lowercase letters, numbers, and hyphens/underscores."; a duplicate is refused with the toast "The selected username is already in use by another user."; "Suggest" fills a lowercase proposal from the given name <sup>e</sup> |
| Email | yes | Must be a valid address; a duplicate is refused with the toast "The selected email address is already in use by another user." <sup>e</sup> |
| Reviewer role select | yes | Shown only when more than one reviewer group serves the stage; otherwise the single group is used silently <sup>e</sup> |
| "Appear on the masthead" checkbox | — | Shown ticked and disabled — a new reviewer always appears on the masthead list of that group; informational only <sup>e</sup> |

**Enroll Existing User mode** (link "Enroll Existing User"; the form is
headed "Enroll an Existing User as Reviewer"):

| Field (UI label) | Required? | Rules |
|------------------|-----------|-------|
| "Search By Name" (user autocomplete) | yes | Searches users already enrolled in the journal who hold no reviewer role of any kind — on a press, membership in either stage's reviewer group excludes the user on both stages. Submitting with the field empty shows the generic "This field is required." above it; picking a user clears the message and the submit succeeds ([A14](#a14), retired) <sup>e</sup> |
| Reviewer role select | yes | Always shown — even a single reviewer group renders as a one-option select (unlike Create New Reviewer, which then hides it) <sup>e</sup> |
| "Appear on the masthead" checkbox | — | Shown ticked and disabled, as in Create New Reviewer <sup>e</sup> |

**Edit Review window** (row action "Edit"; title "Edit Review"): the two due
dates, "Review Type" radios, "Public Visibility" checkbox, the "Files To Be
Reviewed" file list, and — only while the review has not been submitted — the
"Review Form" select. Here — and only here — the "No Files Selected" warning
reacts to the checkboxes, appearing and disappearing as boxes are (un)ticked
(Rule 11). Saving with review due date before response due date is
refused under the same date rule as at add time ⚠ [A8](#a8). <sup>g</sup>

**Send Review Reminder window** (row action "Send Reminder"; window title
"Review Reminder"): reviewer name and address (read-only), a template chooser
when alternates exist, the editable message, and a read-only "Review
Schedule" block of three dates — "Editor's Request", "Response Due Date" and
"Review Due Date" while the reviewer has not responded; once they have,
"Response Due Date" gives way to "Review Acceptance Date" (the review due
date shows in both variants). Submit button "Send Reminder". <sup>h</sup>

**Thank Reviewer window** (row action "Thank Reviewer"): reviewer name
(read-only), prefilled thank-you message, "Do not send email to Reviewer."
checkbox; submit "Thank Reviewer". <sup>j</sup>

**Unassign/Cancel window** (row action "Unassign Reviewer" before a response,
"Cancel Reviewer" after one): prefilled notice message, "Do not send email to
Reviewer." checkbox; the submit button reads "Unassign Reviewer" or "Cancel
Reviewer" to match (Rule 17). **Reinstate Reviewer window**: same shape,
submit "Reinstate Reviewer". **Resend Review Request window** (on a declined
row): message, skip-email checkbox, plus fresh "Response Due Date" and
"Review Due Date" pickers — each preset from its own configured interval,
exactly as at add time ([A9](#a9), retired) (Rule 19).
<sup>k</sup>

**Email Reviewer window** (row action "Email Reviewer"): "To" (read-only,
showing the reviewer's name), Subject and Body — both marked required,
but only the subject is enforced ⚠ [A13](#a13); with both empty the one
error shown is "This field is required." under Subject. Submit button
"Send Email". <sup>l</sup>

**Editorial Notes window** (row action "Editorial Notes"): one rich-text
field holding the notes kept about this reviewer, under the guidance "Record
notes about this reviewer that you would like to make visible to other
administrators, managers and all editors. Notes will be visible for future
review assignments." ⚠ [A4](#a4). **Log Response window** (row
action "Log Response"): one required radio — "Reviewer has accepted the
invitation to review" / "Reviewer has declined the invitation to review" —
under the prompt "Record the response on behalf of the reviewer"; submit
"Log Response". <sup>k</sup> <sup>l</sup>

## Rules & state

1. **One table per round.** The "Reviewers" panel lists the selected round's
   review assignments — each row one reviewer of that round. Five columns:
   "Reviewer" (full name), "Reviewer status", "Type" (review-type icons),
   "Actions" (the state's primary button or buttons, Rule 3) and "More
   Actions" (the ellipsis menu holding the remaining row actions). The same
   reviewer may sit on several rounds; each round shows only its own row.
   <sup>a</sup>
2. <a id="statuses"></a> **The status column.** Each row's status is
   recomputed from the assignment's real state every time the screen loads:

   | Status reads | When | Second line |
   |---|---|---|
   | "Request Sent" | Invitation out, no response, response not yet due | none — the "Response due: {date}" line is missing here, though the date is set ⚠ [A7](#a7) |
   | "Request Accepted" | Reviewer accepted, review not yet due | "Review due: {date}" |
   | "Overdue" (red) | No response and the response date passed | "Response due: {date}" |
   | "Overdue" (red) | Accepted (or not) and the review date passed | "Review due: {date}" |
   | "Request Declined" | Reviewer declined (hover: "The reviewer declined this review request.") | — |
   | "Request Resent" | Request re-sent after a decline, no response yet | "Response due: {date}" — but the date shown is the review deadline ⚠ [A2](#a2) |
   | "Review Submitted" | Review in, not yet confirmed — merely opening it changes nothing ⚠ [A10](#a10); also after "Revert Decision" on a "Complete" row (Rule 16) | reviewer's recommendation {OJS} |
   | "Review Viewed" | Only after "Revert Decision" on a "Reviewer Thanked" row (Rule 16) — never produced by viewing ⚠ [A10](#a10) | reviewer's recommendation {OJS} |
   | "Complete" | An editor confirmed the review (Rule 14) | reviewer's recommendation {OJS} |
   | "Reviewer Thanked" | Thank-you sent or recorded (Rule 16) | reviewer's recommendation {OJS} |
   | "Request Cancelled" | Assignment cancelled (hover: "The editor cancelled this review request.") | — |

   A "Competing Interests" badge is appended when the reviewer declared any —
   possible only on a journal with a competing-interests policy, without
   which the reviewer wizard never asks and the badge cannot occur.
   On a press, no recommendation line ever shows — a press's review collects
   none [OMP1](#omp1). <sup>b</sup>
3. **What each status admits.** The row's offered actions follow the status:
   "Send Reminder" is the "Actions" button only in the two "Overdue" states;
   "Read Review" in "Review Submitted"/"Review Viewed"; "Thank Reviewer" and
   "Revert Decision" in "Complete"; "Revert Decision" alone in "Reviewer
   Thanked"; the other states get no "Actions" button. The "More Actions"
   menu always offers "Email Reviewer" and "History"; "Review Details", "Edit" and
   the unassign entry on every non-cancelled row; "Resend Review Request"
   only on "Request Declined"; "Reinstate Reviewer" only on "Request
   Cancelled"; "Log Response" only while the reviewer has not responded;
   "Editorial Notes" and "Login As" per the Actors table. Of these, only
   the "Review Details" window is not separately specified in this file —
   an explicit waiver, not an oversight. <sup>a</sup>
4. **The round's status line follows this table** — adding reviewers,
   receiving and confirming reviews move the round status box described in
   [→ the round status](U26-review-stage-and-rounds.md#round-status); this spec
   never restates those sentences.
5. <a id="search"></a> **Finding a reviewer.** The Add Reviewer window opens
   on "Locate a Reviewer": a searchable list of every user holding a
   reviewer role for the stage (on a press the opening, unsearched list does
   not yet honor the stage split ⚠ [OMP2](#omp2)), paged past 30 entries
   behind a "View additional pages" bar. Each entry shows name, affiliation
   and ORCID iD (a link showing the iD URL; an unauthenticated iD carries
   the suffix "(unauthenticated)" and an outline ORCID logo, an
   authenticated one the bare URL and the solid logo), headline counts ("{N}
   active", "Reviewer rating: {N}" stars), days since last assignment ("{N}
   days ago" / "Yesterday" / "Never assigned"), reviewing interests, and
   expands to full statistics: active reviews, "Reviews completed", "Review
   requests declined", "Review requests cancelled", "Days since last review
   assigned", "Average days to complete review" — always — plus
   "Reviewing Interests", "Editorial Notes" (when the viewer may read them)
   and "Biography" only when there is data: an empty section is omitted, not
   shown blank. <sup>c</sup>
6. **Search aids.** A name search box; a "Filters" sidebar with five slider
   controls — "Rated at least" (stars), "Reviews completed", "Days since
   last review assigned" (range), "Active reviews currently assigned"
   (range), "Average days to complete review" — each sitting disabled until
   its per-filter enable button is pressed; and, above the list, the
   submission's author names with affiliations (the first four bold, the
   rest collapsed behind a "Show All {N} Authors" / "Show Less" toggle) so
   the editor can spot conflicts. A reviewer whose affiliation matches an
   author's (case-insensitively) is badged "Same institution as author".
   <sup>c</sup>
7. **Locks and notices in the list.**
   - A reviewer already on this round is dimmed with "This reviewer has
     already been assigned to this review round." and cannot be selected
     again.
   - A user who can see the author's identity — anyone with any assignment on
     the submission, and every Journal Manager and Site Administrator — is
     locked with "This reviewer is locked because they have been assigned a
     role which allows them to view the author's identity. Anonymous peer
     review can not be guaranteed. Would you like to unlock this reviewer
     anyway?" plus an "Unlock" link; unlocking frees the Select button for
     that entry. <sup>c</sup>
8. **Later rounds.** From Round 2 on, reviewers who completed a review in the
   previous round are hoisted to the top of the list, flagged "This reviewer
   completed a review in the last round.", and their button reads "Reassign"
   instead of "Select Reviewer". The request email for any assignment on a
   later round defaults to the subsequent-round request template, sent as
   "Request to review a revised submission" (Side effects). <sup>c</sup>
   <sup>d</sup>
9. <a id="due-dates"></a> **Deadlines.** Every request carries a response due
   date (the date to accept or decline by) and a review due date. Defaults
   come from the journal's review setup (weeks from today for each; the
   install falls back to 3 weeks for the response and 4 for the review when
   unset — the numbers are *Review setup & review forms*'). The review due
   date may not precede the response due date ⚠ [A8](#a8), and nothing warns
   against a date already past ⚠ [A17](#a17) (Fields). <sup>f</sup>
10. **Review type, visibility, form.** Each assignment carries its own review
    type (the three-way anonymity choice), a public-visibility flag, and
    optionally one of the journal's review forms; defaults per the review
    setup and — for the form — per the submission's section. The type gates
    the author's access to the finished review
    ([→ reading as the author](U26-review-stage-and-rounds.md#author-read-review))
    and what the reviewer wizard shows is the *Reviewer's review* feature.
    The form can be changed (Edit window) only until the review is
    submitted; afterwards the selector disappears. <sup>d</sup> <sup>g</sup>
11. **File access.** The reviewer will see exactly the round files ticked in
    the "Files To Be Reviewed" list — at add time and at any later "Edit";
    editing replaces the whole selection with the current checkboxes. Which
    files populate that list is the round's Files for Review set
    ([→ Files for Review](U26-review-stage-and-rounds.md#review-files)). The
    "No Files Selected" warning appears in the Add window only when the
    round's list is empty; only the Edit window shows and hides it reactively
    as boxes are (un)ticked. Either way nothing blocks saving — an assignment
    can be created or saved with no files granted at all.
    <sup>d</sup> <sup>g</sup>
12. **Editing notifies the reviewer.** Saving the Edit window with a changed
    due date or review type puts a "Review assignment updated." task in the
    reviewer's own list and emails them ([A11](#a11), retired) ⚠ [A12](#a12)
    (Side effects). An edit that changes only files or visibility sends
    nothing. <sup>g</sup>
13. <a id="reminders"></a> **Reminders.** "Send Reminder" exists only while a
    row shows "Overdue" — an editor cannot send the reminder form to an
    on-schedule reviewer (the free-form "Email Reviewer" window has no such
    gate). Sending stamps a dated "Reminder" milestone into the row's
    History — a line the reviewer's subsequent response erases
    ⚠ [A15](#a15). Separately,
    the install sends automatic reminder emails around each deadline; their
    day-offsets are configured in *Review setup & review forms*, and each
    automatic send also lands in History. <sup>h</sup>
14. <a id="read-review"></a> **Reading and confirming.** "Read Review" opens
    the review: the reviewer's name and completion date, declared competing
    interests, the review form answers or the reviewer's comments — "For
    author and editor" and, separately, the editor-only comments, headed
    "For editor" {OJS} / "For editor only" {OMP} —, the
    "Reviewer Files" the reviewer attached, a "Reviewer rating" star row
    (optional, 1–5 or "No rating"), and {OJS} the reviewer's recommendation
    in a dropdown the editor may set or change on the reviewer's behalf (the
    change is recorded in the submission's activity log). The window's
    instruction reads "Once this review has been read, press \"Confirm\"…":
    pressing "Confirm" marks the review confirmed — the row turns "Complete"
    — and saves the rating; closing the window without confirming leaves
    the row unchanged at "Review Submitted" ⚠ [A10](#a10). On a press the
    window has no recommendation control [OMP1](#omp1). <sup>i</sup>
15. **Downloading a review.** The same window's "Download Review Form" menu
    offers four exports: "Author-Only Sections Displayed" and "Editor Form
    Shows All Review Sections", each as PDF or XML. The author-only variants
    omit the editor-only comments and also hide the reviewer's identity —
    the reviewer is presented anonymized. The file downloads through the
    browser. <sup>i</sup>
16. **Thanking, and taking it back.** "Thank Reviewer" sends (or, with the
    skip box, only records) the acknowledgement — the row turns "Reviewer
    Thanked" and the on-screen notice reads "Thank you email sent to
    reviewer." or "Review marked as acknowledged. Email not sent.". "Revert
    Decision" asks "Unconsider this Review" — confirming returns a
    "Complete" row to "Review Submitted" and a "Reviewer Thanked" row to
    "Review Viewed", with no notice shown either way, so the review can be
    re-examined; the review content is untouched and the revert is logged.
    <sup>j</sup>
17. <a id="unassign"></a> **Unassign vs Cancel.** Before the reviewer has
    responded, the entry reads "Unassign Reviewer" and removing them deletes
    the row outright — the notice reads "Reviewer removed." and nothing of
    the invitation remains on the round. After any response (accept or
    decline), the entry reads "Cancel Reviewer": the row stays, as "Request
    Cancelled", and only review managers keep seeing it ⚠ [A6](#a6). Both
    windows offer the notice email with a skip box. <sup>k</sup>
18. **Reinstate.** "Reinstate Reviewer" on a cancelled row restores the
    assignment to the state its dates imply (accepted, overdue, submitted…),
    with an optional notice email; notice "Reviewer reinstated." <sup>k</sup>
19. **Resend after a decline.** "Resend Review Request" on a declined row
    asks the reviewer to reconsider: fresh response and review due dates
    (Fields), optional email, notice "Request to
    reconsider the review assignment was sent." The row becomes "Request
    Resent" until the reviewer responds again ⚠ [A2](#a2), and its menu
    again offers "Unassign Reviewer" and "Log Response" — the request counts
    as unanswered once more. <sup>k</sup>
20. **Logging a response.** When a reviewer answers by email instead of
    clicking, "Log Response" records the acceptance or decline on their
    behalf; the row moves to "Request Accepted" or "Request Declined"
    exactly as if the reviewer had clicked: the reviewer receives no email,
    and the assigned editors get the same response notification a real
    reviewer click sends — its From header set to the reviewer's own
    address. The entry exists only while the invitation is unanswered.
    <sup>k</sup>
21. **History.** "History" opens a side modal titled "History" listing the
    assignment's dated milestones — "Assigned", "Notified", "Reminder",
    "Confirm" or "Declined", "Completed", "Acknowledged" — each with its
    date and time; blank milestones are omitted, and the "Reminder"
    milestone is erased by the reviewer's response ⚠ [A15](#a15).
    <sup>l</sup>
22. **Editorial Notes.** "Editorial Notes" opens the notes editors keep
    about a reviewer, naming its audience — "administrators, managers and
    all editors" — in its guidance text (Fields). The notes belong to the
    person, not the assignment: the same
    text appears (and is overwritten) wherever that reviewer is opened, on
    any submission ⚠ [A4](#a4). The reviewer never sees them; they also
    surface read-only in the reviewer search (Rule 5) for those allowed.
    <sup>l</sup>
23. **ORCID deposit.** A row whose reviewer has an authenticated ORCID iD
    offers "Send Review To ORCID" with the confirm "Send this review to the
    reviewer's ORCID?" — intended for completed reviews, but offered in
    every state ⚠ [A1](#a1). Confirming a completed review's deposit sends
    the review to the reviewer's ORCID record; the ORCID plumbing is the
    *ORCID integration* feature. <sup>i</sup>

## Side effects

- **Adding a reviewer** → the row appears ("{name} was assigned to review
  this submission and sent an email notification." — or "…was not sent an
  email notification." with the skip box); the reviewer gets a "Review
  pending." task in their own task list and — unless skipped — the request
  email (subject "Invitation to review" {OJS} / "Manuscript Review Request"
  {OMP}, or "Request to review a revised submission" on later rounds), sent
  under the acting editor's name. When the journal has
  reviewer one-click access enabled, the email carries a sign-in-free review
  link (the landing is the *Reviewer's review* feature; the invitation
  record is [→ user invitations](U06-user-invitations.md#invitation-states)).
  The assignment is logged in the submission's activity log. <sup>m</sup>
- **Creating a new reviewer** → a user account is created with a generated
  password and — unless the skip box is ticked — a welcome email
  ("Registration as Reviewer…") carrying the username and that password;
  the account must change its password at first sign-in
  ([→ forced password change](U01-login-and-sessions.md)). The new account
  joins the chosen reviewer group and its masthead list. <sup>e</sup>
- **Enrolling an existing user** → the user gains the chosen reviewer role
  permanently; no separate email beyond the request itself. <sup>e</sup>
- **Editing an assignment** (date or type changed) → reviewer task "Review
  assignment updated." plus the change-notice email — reporting the
  just-saved deadlines (it long reported the pre-edit ones — [A11](#a11),
  retired: fixed upstream), and whose unsubscribe link opens a page that
  does not offer this email type ⚠ [A12](#a12). <sup>g</sup>
- **Manual reminder** → the reminder email (subject "A reminder to please
  complete your review"), a "Notification sent." notice to the editor, a
  "Reminder" date in History (erased once the reviewer responds
  ⚠ [A15](#a15)), and an activity-log entry. <sup>h</sup>
- **Automatic reminders** → the overdue-response and overdue-review reminder
  emails go out from the journal's principal contact when the configured
  day-offsets are reached (clocks in *Review setup & review forms*); each
  send stamps the History "Reminder" date and the activity log. A reviewer
  response resets the reminder bookkeeping — the same reset that erases the
  History "Reminder" milestone ⚠ [A15](#a15). <sup>h</sup>
- **Confirming a review** (Rule 14) → the reviewer's "Review pending." task
  is cleared, the confirmation is logged, and a completed review is
  deposited to the reviewer's ORCID record when one is authenticated (the
  deposit consent flow is the *ORCID integration* feature). {OJS} A
  recommendation set in the window is logged as recorded by the editor on
  the reviewer's behalf. <sup>i</sup>
- **Thanking** → the acknowledgement email (unless skipped) and the
  acknowledged date in History. <sup>j</sup>
- **Unassigning/cancelling** → the notice email (unless skipped; subject
  "Request for Review Cancelled" — either way, unassign included) and the
  reviewer's "Review pending." task is removed. Logged. Nothing else is
  cleaned up — the reviewer's other participations on the submission are
  untouched. <sup>k</sup>
- **Reinstating / resending** → the respective email (unless skipped; the
  reinstate notice asks "Can you still review something for {journal}?")
  and a log entry. <sup>k</sup>
- **Logging a response** → the same emails and bookkeeping as the reviewer's
  own accept/decline (owned by the *Reviewer's review* feature). <sup>k</sup>
- **Adding a suggested reviewer** → when the added or enrolled reviewer
  matches one of the author's reviewer suggestions, the suggestion is marked
  approved and disappears from the suggestions panel (the panel is the
  *Reviewer suggestions* feature). <sup>m</sup>

## Settings that modify behavior

All of these live on other features' screens; listed here for their effect on
this panel. The configuration surfaces belong to *Review setup & review
forms* unless said otherwise. <sup>n</sup>

- **Default review type** — preselects the "Review Type" radios.
- **Weeks to respond / weeks to complete** — the due-date defaults (Rule 9).
- **Automatic reminder day-offsets** — arm the automatic reminder emails
  (Rule 13).
- **Reviewer one-click access** — adds the sign-in-free keyed link to
  request and reminder emails; with it on, the editor-facing preview shows
  the link as a placeholder rather than the live address, and each sent
  reminder mints a fresh keyed link of its own. <sup>h</sup>
- **Public visibility default** — preselects the "Public Visibility" box.
- **Review forms** — populate the "Review Form" selects; a section's default
  form preselects it (section configuration is *Sections* territory).
- **Reviewer suggestions enabled** (workflow settings) — the Add Reviewer
  window can be opened from a suggestion, arriving with that person
  preselected or prefilled; the suggestions panel itself is the *Reviewer
  suggestions* feature. <sup>d</sup>

## Cross-feature interactions

- **Review stage & rounds** — the stage screen, round machinery, round
  status box, Files for Review, and the author's read-only reviewers list;
  this spec owns everything inside the Reviewers panel and its windows.
- **Reviewer's review** — the reviewer's own experience: the request landing,
  accept/decline, the wizard, one-click access. Log Response (Rule 20)
  drives that feature's accept/decline path from the editor's side.
- **Review setup & review forms** — every default named in Settings, the
  reminder clocks, and review-form authoring.
- **Reviewer suggestions** — the suggestions panel and its "add" entry into
  this feature's window.
- **User invitations** — the invitation record behind one-click review
  links.
- **Sign-in & sessions** — "Login As" on a reviewer row, and the forced
  password change a created reviewer account goes through.
- **Users management** — the reviewer role memberships this feature creates
  (Create/Enroll) are otherwise managed there.
- **Editorial decision recording** — decisions close rounds; nothing in this
  panel records a decision.
- **ORCID integration** — the review deposit behind Rule 23.
- **Tasks & discussions** — the reviewer's "Review pending." task cleanup on
  unassignment; no discussion cleanup happens (Side effects).

## Canonical scenarios

Common to OJS and OMP; substitute roles and vocabulary per the
[application glossary](GLOSSARY.md) (on a press, run on External
Review; scenario 13 covers the internal twin). Actors are named by role;
seeded accounts and recipes live in the footnotes. <sup>s</sup> Outgoing
mail is observed in the test install's mail catcher.

1. **Invite a reviewer** — Editor: on a round with review files, press "Add
   Reviewer", search the list for a seeded reviewer by name, press "Select
   Reviewer", check the prefilled request letter and the two due dates
   (defaults per the journal's review setup), press "Add Reviewer". The
   panel lists the reviewer as "Request Sent" (the row's response-deadline
   line is missing ⚠ [A7](#a7)), and the reviewer's mailbox holds the
   request email. Verify it arrived; a silent failure mode exists
   (⚠ [A18](#a18)): submitting the form with the letter emptied gives no
   feedback at all while still creating the row, and no request email goes
   out. <sup>s</sup>
2. **The list warns before anonymity breaks** — Editor: in "Locate a
   Reviewer", find a reviewer who is also a Journal Manager: the entry is
   locked with the author-identity warning and no Select button; press
   "Unlock" — the "Select Reviewer" button appears. Also verify a reviewer
   already on the round shows "This reviewer has already been assigned to
   this review round." with no way to select them again.
3. **Create a brand-new reviewer** — Journal Manager: in the Add Reviewer
   window choose "Create New Reviewer", fill given name and email, press
   "Suggest" beside Username (a username appears), press "Add Reviewer".
   The row appears as "Request Sent"; the new address's mailbox holds the
   registration email with a password and the review request; signing in
   with that password lands on the "Change Password" form
   ([→ sign-in flows](U01-login-and-sessions.md)). <sup>s</sup>
4. **Enroll an existing user** — Journal Manager: choose "Enroll Existing
   User", type a seeded author's name into the autocomplete, pick them,
   press "Add Reviewer". The row appears; opening the journal's users list
   shows the user now also holds the Reviewer role. Control: typing the name
   of an existing reviewer into the same autocomplete finds nothing.
5. **Deadlines are validated** — Editor: in the Add Reviewer form set the
   review due date before the response due date and submit; no assignment
   is created — the window stays open, and the only statement of the rule
   is the guidance sentence "Review due date must be greater or equal to
   response due date." (no error message appears ⚠ [A8](#a8)). Correct the
   dates; the submission succeeds and the row appears.
6. **Edit an assignment, reviewer is told** — Editor: on a "Request Sent"
   row open "Edit", move the review due date a week later, save. The
   reviewer's account (sign in as them) shows the "Review assignment
   updated." task, and their mailbox holds the change notice. Control: an
   edit changing only the file ticks sends nothing new.
7. **Remind an overdue reviewer** — Editor: on a row showing "Overdue"
   (e.g. via "Edit", set the response due date to yesterday — the pickers
   accept past dates), the row's button reads "Send
   Reminder"; open it, verify the schedule readout, press "Send Reminder".
   The notice "Notification sent." appears, the reviewer's mailbox holds
   the reminder, and the row's "History" now lists a "Reminder" date —
   check it before the reviewer responds: their response erases the line
   ⚠ [A15](#a15). Control: a row that is not overdue
   offers no "Send Reminder" button. <sup>s</sup>
8. **Log a response on the reviewer's behalf** — Editor: on an unanswered
   row open "Log Response", choose "Reviewer has accepted the invitation to
   review", press "Log Response". The row reads "Request Accepted" with the
   review due date. Control: the "Log Response" entry is gone from that
   row's menu afterwards.
9. **Read, rate, confirm, thank** — with a submitted review on the round
   (seeded), Editor: the row reads "Review Submitted"; open "Read Review",
   see the reviewer's comments split "For author and editor" / "For editor"
   ({OMP}: "For editor only"), pick a star rating, press "Confirm" — the
   row turns "Complete".
   Press "Thank Reviewer", send — notice "Thank you email sent to
   reviewer.", row "Reviewer Thanked", thank-you in the reviewer's mailbox.
   Then "Revert Decision" → confirm "Unconsider this Review" — the row
   returns to "Review Viewed". <sup>s</sup>
10. **Download the review** — Editor: in the "Read Review" window open
    "Download Review Form" and fetch "Author-Only Sections Displayed (PDF)"
    and "Editor Form Shows All Review Sections (PDF)"; both download, and
    the author-only file omits the editor-only remarks and shows the
    reviewer anonymized, while the full one carries both comment blocks and
    the reviewer's name.
11. **Unassign before, cancel after** — Editor: unassign an unanswered
    reviewer ("Unassign Reviewer" → submit) — notice "Reviewer removed.",
    the row is gone. On a second, accepted reviewer the same menu entry
    reads "Cancel Reviewer"; cancelling leaves the row as "Request
    Cancelled". Press "Reinstate Reviewer" on it — the row returns to its
    dated state ("Request Accepted" or "Overdue"), and the reviewer's
    mailbox holds the cancel and reinstate notices.
12. **Decline, then ask again** — with a declined row (reviewer declined, or
    scenario 8 run with the decline option), Editor: the row reads "Request
    Declined"; open "Resend Review Request", keep the fresh dates, send.
    The row reads "Request Resent" ⚠ [A2](#a2) and the reviewer's mailbox
    holds the reconsider request.

App-specific:

13. **{OMP} Two review stages, two reviewer pools** — Press Editor: on a
    monograph in Internal Review, press "Add Reviewer" and search an
    Internal Reviewer by name — found; search an External Reviewer by name —
    "No items found."; send the monograph to External Review, press "Add
    Reviewer" there and repeat — now the External Reviewer is found and the
    Internal one is not [OMP1](#omp1). Assert through the search: the
    window's opening, unsearched list does not apply the split
    ⚠ [OMP2](#omp2). Every scenario above runs the same on both stages.
    <sup>o</sup> <sup>s</sup>
14. **{OJS} The recommendation runs through the table** — Editor: after
    scenario 9's confirm, the "Complete" row's status cell shows the
    reviewer's recommendation under the status; in "Read Review" the
    recommendation dropdown can be changed by the editor. {OMP} Control: on
    a press neither the status cell nor the window shows any recommendation
    control [OMP1](#omp1).
15. **{OPS} No reviewer surfaces on a preprint server** — Preprint Server
    Manager: open any preprint's workflow — no "Reviewers" panel exists on
    any screen, and Users & Roles offers no reviewer group to assign.
    Positive control: the same workflow screen offers the Production
    stage's own controls, so the screen itself is working. <sup>p</sup>

## Findings register

Verdicts are the author's judgment (claude, 2026-08-02), unreviewed unless an
entry notes otherwise; the team settles them on spec review. A claim-check
pass (claude, 2026-08-02, both apps live) re-drove every entry and the spec's
claims; where it overturned an entry, the entry is retired in place — badge
✅, wording states the resolution — so IDs stay dense, and the ruling sits in
the entry's Reviewed line and the summary's Review column. Sorted 🐞 → ❓ →
✅ in the summary (🐞 defect, author's call · ❓ needs a product ruling · ✅
intended divergence — or a retired finding); the entries below are the
source. Each entry opens with the user-observable symptom; mechanism and
evidence live in the entry's footnote. Impact key: user-visible = real effect
in ordinary use · minor = cosmetic only, however often seen · latent = only
in an unusual situation or configuration.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|-----------------------------|------|--------|--------|
| [A1](#a1) | "Send Review To ORCID" is offered on rows in every state, not only completed reviews | 🐞 | latent | — |
| [A2](#a2) | A "Request Resent" row's "Response due:" line shows the review deadline, not the response deadline | 🐞 | minor | — |
| [A7](#a7) | A "Request Sent" row never shows its "Response due:" line, though the date is set | 🐞 | minor | — |
| [A8](#a8) | Submitting inverted due dates is refused with no message — the form just stays open | 🐞 | user-visible | — |
| [A10](#a10) | "Review Viewed" is never produced by viewing a review — only by reverting a thanked one | 🐞 | user-visible | — |
| [A12](#a12) | The assignment-changed email's opt-out is offered nowhere — its own unsubscribe page omits the type | 🐞 | minor | — |
| [A13](#a13) | Email Reviewer sends with an empty body despite the body being marked required | 🐞 | minor | — |
| [A15](#a15) | The reviewer's response erases the dated "Reminder" milestone from the assignment's History | 🐞 | minor | claim check (claude), 2026-08-02 — settled |
| [A16](#a16) | A due date typed in the wrong format looks accepted on screen, but the old value is silently submitted | 🐞 | user-visible | claim check (claude), 2026-08-02 — rescoped |
| [A18](#a18) | Emptying the request letter makes the add fail silently — yet the assignment is created and the request email never goes out | 🐞 | user-visible | — |
| [A19](#a19) | The template chooser renders on every add — a one-option select even with zero alternate templates | 🐞 | minor | — |
| [OMP2](#omp2) | {OMP} The Add Reviewer window's opening list ignores the internal/external stage split — only searching filters by stage | 🐞 | user-visible | — |
| [A4](#a4) | Editorial Notes are one shared note per reviewer — editing them on one submission silently rewrites them everywhere | ❓ | user-visible | — |
| [A6](#a6) | Declined and cancelled rows are silently hidden from assistant-level participants — the same table shows different reviewers per role | ❓ | minor | — |
| [A17](#a17) | The due-date pickers accept dates already past without any warning | ❓ | minor | — |
| [OMP1](#omp1) | A press's review runs without reviewer recommendations, and with a per-stage reviewer pool (Internal vs External Reviewers) | ✅ | — | — |
| [A11](#a11) | Retired: the change notice reported the pre-change deadlines — fixed upstream (pkp/pkp-lib#13162) | ✅ | retired | rebase check (claude), 2026-08-25 — fixed upstream |
| [A5](#a5) | Retired: the alternate-template access check it questioned was reverted wholesale upstream (pkp/pkp-lib#10403 revert) — all alternates now listed unconditionally | ✅ | retired | rebase check (claude), 2026-08-25 — moot |
| [A3](#a3) | Retired: a role-less Site Administrator is refused at the workflow screen — the earlier full-surface observation was of the seeded admin's silent Journal Manager enrollment | ✅ | retired | claim check (claude), 2026-08-02 — overturned |
| [A9](#a9) | Retired: the Resend window presets each date from its own interval — the earlier collapse was a same-interval coincidence | ✅ | retired | claim check (claude), 2026-08-02 — overturned |
| [A14](#a14) | Retired: the enroll "required" message appears only on an empty submit and clears on pick — ordinary validation | ✅ | retired | claim check (claude), 2026-08-02 — overturned |

### All apps

<a id="a1"></a>
**A1 — ORCID deposit offered regardless of review state** · 🐞 · latent.
A reviewer row whose reviewer has an authenticated ORCID iD shows "Send
Review To ORCID" in its menu in every state — including before the reviewer
has even responded — although the action only makes sense for a completed
review. Latent because it needs ORCID configured and an authenticated
reviewer iD, which default test installs lack.
Basis: code reading. A live probe (2026-08-02) could only confirm the flip
side — without an ORCID on the reviewer, the entry is absent in every state
checked — because attaching an iD needs the external OAuth flow no screen
here provides. Settled by: a reviewer with a verified iD, any non-complete
row, open the menu. <sup>[f-a1](#fn-a1)</sup>

<a id="a2"></a>
**A2 — "Request Resent" shows the wrong deadline** · 🐞 · minor.
After a request is re-sent to a reviewer who declined, the row's second line
reads "Response due: {date}" but prints the review deadline, not the response
deadline. The reviewer's actual response date is the one the editor just set
in the resend window.
Basis: live probe + code reading (the resent-state cell is fed the review
date under the response-due label). <sup>[f-a2](#fn-a2)</sup>

<a id="a3"></a>
**A3 — Retired: the "role-less site admin" was a Journal Manager** · ✅ ·
retired.
Does not reproduce: a Site Administrator holding no journal role cannot
reach the workflow screen at all — the attempt is refused with the Error
dialog "The current role does not have access to this operation." (positive
control: the same account renders the full Administration area). The
complete add surface observed earlier belonged to the seeded `admin`
account, which silently holds a Journal Manager role in every journal of
the install — a manager was observed, not a role-less site admin. The
Actors section now records the corrected fact; the live behavior is more
restrictive than was documented and no open question remains.
Reviewed: claim check (claude), 2026-08-02 — overturned (was an open
question). <sup>[f-a3](#fn-a3)</sup>

<a id="a4"></a>
**A4 — Editorial Notes are one note per reviewer, everywhere** · ❓ ·
user-visible.
The "Editorial Notes" window is opened from one submission's reviewer row,
but the note it edits belongs to the reviewer as a person: the same text
shows for that reviewer on every other submission, and saving here
overwrites what a colleague wrote there. The window does name its audience
and future use — "Record notes about this reviewer that you would like to
make visible to other administrators, managers and all editors. Notes will
be visible for future review assignments." — but never says the note is one
shared text across submissions.
Question: is a single cross-submission note the intended design? Lean: the
storage is clearly deliberate (the search list shows the same note, and the
guidance promises future visibility), but the silent cross-submission
overwrite deserves a word in the window.
Basis: live probe (a note written on one submission read back verbatim from
another) + code reading (the note is stored on the user account, not the
assignment). <sup>[f-a4](#fn-a4)</sup>

<a id="a5"></a>
**A5 — Alternate templates listed under the wrong access check** · ✅ ·
retired.
The Add Reviewer window's template chooser builds its list of alternates to
the subsequent-round request template by checking, for each alternate,
whether the *subsequent-round template itself* is accessible to the editor —
not the alternate. A journal restricting individual alternate templates to
certain roles would find them offered (or hidden) wholesale.
Question: is per-alternate access meant to be enforced here? Lean: yes — the
sibling code path for the first-round template checks each alternate
individually; the mechanism is in the footnote.
Basis: code reading. <sup>[f-a5](#fn-a5)</sup>

> **Retired — rebase check (claude), 2026-08-25**: moot upstream. The whole
> per-user-group email-template access feature was reverted (pkp/pkp-lib#10403
> revert), removing both loops this entry compares — every alternate is now
> listed unconditionally, and no per-role template restriction remains to
> enforce. [A19](#a19)'s unconditional subsequent-round append is unchanged.

<a id="a6"></a>
**A6 — Assistant-level participants see a shorter reviewers table** · ❓ ·
minor.
For a review manager the table keeps declined and cancelled rows visible
(with their tooltips and the resend/reinstate entries). An assistant-level
participant assigned to the stage gets the same table with those rows
silently omitted — nothing indicates rows are missing, so two people looking
at "the same" round see different reviewer counts.
Question: is hiding declined/cancelled history from assistants intended?
Lean: intended as written — the visibility rule names exactly the manager
roles — but the silence is worth a product look.
Basis: live probe (a manager control saw all three row kinds where the
assistant's table showed one; the assistant's row menu also lacked "Login
As" and "Editorial Notes") + code reading.
<sup>[f-a6](#fn-a6)</sup>

<a id="a7"></a>
**A7 — "Request Sent" rows hide their response deadline** · 🐞 · minor.
A freshly invited reviewer's row reads "Request Sent" with no second line:
the "Response due: {date}" line every comparable state shows never renders
here, though the date is set and the Edit window shows it. The editor loses
the at-a-glance deadline until the row turns "Overdue".
Basis: live probe. <sup>[f-a7](#fn-a7)</sup>

<a id="a8"></a>
**A8 — Inverted due dates are refused without a word** · 🐞 · user-visible.
Submitting the Add Reviewer form with the review due date set before the
response due date does nothing visible: no error, no notice — the window
simply stays open and no assignment is created. Only the permanent guidance
sentence states the rule; the server's own error message never reaches the
screen.
Basis: live probe. <sup>[f-a8](#fn-a8)</sup>

<a id="a9"></a>
**A9 — Retired: the Resend window presets dates correctly** · ✅ · retired.
Does not reproduce: on a journal whose response and review intervals differ,
the Resend window's fresh pickers arrive preset exactly as a new request's —
each date today plus its own configured interval. The earlier "both preset
to the response interval" observation was made where the two intervals
coincide, so the apparent collapse was a coincidence, not a behavior.
Reviewed: claim check (claude), 2026-08-02 — overturned (was a defect).
<sup>[f-a9](#fn-a9)</sup>

<a id="a10"></a>
**A10 — "Review Viewed" does not mean viewed** · 🐞 · user-visible.
Opening "Read Review" and closing it without confirming leaves the row at
"Review Submitted" — viewing never produces "Review Viewed". The only route
to that status is "Revert Decision" on a "Reviewer Thanked" row, so the
label misstates what happened in both directions.
Basis: live probe. <sup>[f-a10](#fn-a10)</sup>

<a id="a11"></a>
**A11 — The change notice tells the reviewer the old deadlines** · ✅ ·
retired.
When an editor saves new due dates in the Edit window, the email telling
the reviewer their assignment changed reports the dates as they were before
the edit. The screen shows the new dates; the reviewer is told the wrong
ones.
Basis: live probe (both apps; on OJS twice independently, and with both
date fields). <sup>[f-a11](#fn-a11)</sup>

> **Retired — rebase check (claude), 2026-08-25**: fixed upstream by
> pkp/pkp-lib#13162 — the notice is now composed from the assignment
> re-fetched after the edit is saved, so it reports the just-saved dates.
> Code-anchored, not re-probed. [A12](#a12) (the unreachable opt-out) is
> untouched and stands.

<a id="a12"></a>
**A12 — The change notice's opt-out is unreachable** · 🐞 · minor.
The assignment-changed email is suppressed for reviewers who opted out of
it — but no screen offers that opt-out: the email type is missing from the
profile's notification settings, and the unsubscribe page the email's own
footer links to does not list it either. The link dead-ends for its own
email type.
Basis: live probe. <sup>[f-a12](#fn-a12)</sup>

<a id="a13"></a>
**A13 — Email Reviewer enforces only the subject** · 🐞 · minor.
The window marks both Subject and Body required, but filling only the
Subject sends the email with an empty body. With both empty, the only error
shown is "This field is required." under Subject — the body never shows
one.
Basis: live probe (both apps). <sup>[f-a13](#fn-a13)</sup>

<a id="a14"></a>
**A14 — Retired: the enroll "required" error is ordinary validation** · ✅ ·
retired.
Does not reproduce as stated: driving the sequence cleanly (open the enroll
form, type, pick a user) shows no "This field is required." message at any
point. The message appears only after submitting with the field empty, and
picking a user then clears it — submitting afterwards succeeds. The earlier
observation most likely read that post-submit state as the post-pick state.
The empty-submit error is also the generic "This field is required.", not
an "existing user must be selected" wording.
Reviewed: claim check (claude), 2026-08-02 — overturned (was a defect).
<sup>[f-a14](#fn-a14)</sup>

<a id="a15"></a>
**A15 — The reviewer's response erases the History "Reminder" line** · 🐞 ·
minor.
Sending a reminder stamps a dated "Reminder" milestone into the assignment's
History — but once the reviewer responds, the line is gone. The response
resets the reminder bookkeeping — the same reset that re-arms the automatic
reminders (Side effects) — and erases the dated History milestone with it.
The mechanism is deliberate; the user-facing cost is the lost History line.
Basis: live probe (the settling recipe run exactly: reminder → History shows
"Reminder"; an acceptance logged → the milestone is gone).
Reviewed: claim check (claude), 2026-08-02 — settled from an open question
to a defect; both prior conflicting observations are explained (one read
History before the response, one after). <sup>[f-a15](#fn-a15)</sup>

<a id="a16"></a>
**A16 — A wrong-format typed due date is silently thrown away** · 🐞 ·
user-visible.
The due-date pickers (the Add, Edit and Resend windows share the widget)
accept a date typed in the YYYY-MM-DD format (e.g. 2026-08-02) — it saves
and flows downstream. But input in any other format only looks accepted: the visible
field keeps the keystrokes while the old value is silently submitted — the
editor's correction doesn't happen and nothing says so.
Basis: live probe.
Reviewed: claim check (claude), 2026-08-02 — rescoped (a correctly
formatted typed date is accepted end-to-end; only wrong-format input is
discarded). <sup>[f-a16](#fn-a16)</sup>

<a id="a17"></a>
**A17 — Past due dates pass without a warning** · ❓ · minor.
The same pickers accept dates already in the past with no warning or
confirmation; a saved past date immediately renders the row "Overdue".
Question: should the screen warn? Lean: arguably intentional — this is the
only screen route to backdating a deadline — but a warning would cost
nothing.
Basis: live probe. <sup>[f-a17](#fn-a17)</sup>

<a id="a18"></a>
**A18 — Emptying the request letter half-completes the add, silently** · 🐞
· user-visible.
Submitting the Add Reviewer form with the request letter emptied shows
nothing at all — no error, no toast, no error page; the window simply stays
open. But the assignment IS created: after a reload the reviewer sits at
"Request Sent" with a full row menu, while the request email never goes out
and reopening Add Reviewer shows the reviewer dimmed as already assigned.
The editor is told nothing and the reviewer is never actually invited. In
ordinary use the field can be empty at submit without the editor ever
clearing it — the prefill can still be loading when the editor submits.
Basis: live probe (both apps, driven once per app; positive control: a
normal add delivers the request mail). <sup>[f-a18](#fn-a18)</sup>

<a id="a19"></a>
**A19 — The template chooser renders with nothing to choose** · 🐞 · minor.
The Add Reviewer window's template chooser ("Choose a predefined message to
use, or fill out the form below.") is meant to appear only when alternate
request templates exist, but it renders on every add: on a baseline journal
with zero alternates it shows a select with exactly one option, "Review
Request" — a chooser with nothing to choose. Which alternates the chooser
lists is a separate question — see [A5](#a5).
Basis: live probe (both apps, baseline contexts checked to hold no
alternates; two acting roles on OJS) + code reading.
<sup>[f-a19](#fn-a19)</sup>

### OMP

<a id="omp1"></a>
**OMP1 — Per-stage reviewer pools, no recommendations** · ✅ · intended
divergence.
A press runs this feature on both of its review stages, each with its own
reviewer pool: searching the Add Reviewer list on Internal Review finds the
users holding the Internal Reviewer role, on External Review those with
External Reviewer — the window's opening, unsearched list does not yet apply
that split; that defect is OMP2. And a press's review collects no reviewer
recommendation, so the status cell's recommendation line and the
read-review window's recommendation dropdown simply do not exist there —
the author-facing side of that same absence is recorded with the review
stage (its finding "No reviewer recommendation on a press").
Basis: live probe (the searched pool split, with positive and negative
controls on both stages; the read-review window verified without a
recommendation control) + code (the press disables reviewer recommendations
by design — since the 2026-08-25 rebase explicitly, via
`Application::hasCustomizableReviewerRecommendation()` returning `false` —
and scopes reviewer groups per stage); the author-side
counterpart was live-probed 2026-07-31 under the review-stage feature.
Re-driven in the claim check (2026-08-02): the search split held with
positive and negative controls in both directions on both stages, and the
read-review window again rendered no recommendation control.
<sup>[f-omp1](#fn-omp1)</sup>

<a id="omp2"></a>
**OMP2 — The Add Reviewer window opens with both stages' pools** · 🐞 ·
user-visible.
On a press, the Add Reviewer window's opening list shows the reviewers of
both stages — Internal and External Reviewers alike, on either stage. Only
searching applies OMP1's per-stage split: a name from the other stage's
group returns "No items found." while the stage's own reviewers are found.
The server-rendered opening list omits the stage filter that its own request
parameters carry.
Basis: live probe (positive and negative controls on both stages).
<sup>[f-omp2](#fn-omp2)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<a id="fn-a"></a>
**a** — Panel: lib/ui-library `managers/ReviewerManager/ReviewerManager.vue`
(VUE-046) mounted by the external-review block of
`workflowConfigEditorialOJS.js` (both apps — OMP defines no external-review
override; its internal-review block mounts the same component, atoms
AFFW-486, 488..501). Columns/top button:
`useReviewerManagerConfig.js::getColumns/getTopItems` (redacted author
variant drops status/actions columns and the Add button — the author view is
owned by review-stage-and-rounds, GRID-053/AFFW-487). Server ops:
`PKPReviewerGridHandler` (lib/pkp classes/controllers/grid/users/reviewer/) —
role map: manager, site admin, sub-editor = all ops; assistant = all minus
`createReviewer`, `enrollReviewer`, `gossip`; stage gate
`WorkflowStageAccessPolicy` + `ReviewRoundRequiredPolicy` /
`ReviewAssignmentRequiredPolicy`. Author denial:
`authorize()` blocks `_getAuthorDeniedOps()` (all mutating ops) for any user
with an author assignment on the submission — even one who also holds an
editorial role — and `_getAuthorDeniedAnonymousOps()` (readReview,
reviewHistory, reviewRead, sendEmail, gossip) when the review method is
anonymous/double-anonymous. App chains: OJS
`ReviewerGridHandler` overrides only `reviewRead` (recommendation-by-proxy,
note i; GRID-086); OMP's subclass is EMPTY (GRID-098) — shared-behavior
evidence. Declined/cancelled row visibility:
`PKP\submission\maps\Schema::getPropertyReviewAssignments()` skips
declined/cancelled rows unless `canSeeAllReviewAssignments()` — manager,
sub-editor or site admin among the user's roles at that stage (finding A6).
Assignment data shape: `reviewAssignment.json` schema (SET-020), serialized
per row incl. `canLoginAs`, `canGossip`, `reviewerHasOrcid`,
`competingInterests`. Live-probed 2026-08-02 (OJS + OMP, all round types):
the table's five column headers read "Reviewer" · "Reviewer status" ·
"Type" · "Actions" · "More Actions". Also live-probed 2026-08-02: the
submitting author's workflow view of a round with an active assignment
rendered no Reviewers panel at all — no table, no "Add Reviewer", no
reviewer identities (the reduced completed-reviews list is asserted with
review-stage-and-rounds); and the assistant-level participant's shortened
table is finding A6's live half (note f-a6). Claim check 2026-08-02, acting
as a Section Editor (Series Editor on the press): panel and rows, per-state
row menus, a completed add, both create/enroll links offered and the
Editorial Notes guidance verbatim — all as written; no "Login As" entry
appeared in any of that editor's row menus, consistent with the
impersonation rule. Site-admin gate: a genuinely role-less Site
Administrator is stopped by the stage-access check before the screen
renders (note f-a3); the seeded `admin` is enrolled as Journal Manager in
every journal of the test install, so any earlier full-surface "site admin"
observation was a manager observation. Still undriven: a Guest Editor
actor, a Section Editor completing create/enroll (the links are offered;
completion was probed as a manager), and a cancelled row viewed as a
Section Editor (code lean: the same visibility branch admits sub-editors).

<a id="fn-b"></a>
**b** — Status machine: `ReviewAssignment::getStatus()` (order: declined →
cancelled → request-resent → due-date arithmetic → thanked/complete/viewed/
received). Cell rendering:
`useReviewerManagerConfig.js::getCellStatusItems` — titles quoted in Rule 2
from `editor.review.requestSent` "Request Sent", `.requestAccepted`,
`common.overdue`, `editor.review.requestDeclined`(+`.tooltip`),
`.requestCancelled`(+`.tooltip`), `.reviewSubmitted`, `.reviewViewed`,
`common.complete`, `editor.review.reviewerThanked`,
`editor.review.ReviewerResendRequest` "Request Resent"; sub-lines
`editor.review.responseDue` "Response due: {$date}" / `.reviewDue` "Review
due: {$date}" (the Request Sent cell renders no sub-line live — finding
A7); badge `reviewer.competingInterests`. Overdue math: response
overdue once the response date-time passes; review overdue from the day
before the review date ends (both dates fall back to end-of-day when no time
is stored). Recommendation line: `getRecommendationString()` resolves
against the journal's recommendation roster passed only by the OJS dashboard
(`DashboardHandler::setupIndex`, `pageInitConfig['recommendations']`); OMP
passes none (note f-omp1). Live-probed 2026-08-02: all eleven statuses
driven on OJS with titles, second lines, red "Overdue" styling and both
hover tooltips as quoted (a five-state OMP spot-check matched; the two
deviations are findings A7 and A2); the "Competing Interests" badge fired
only on a journal with a competing-interests policy — without one the
reviewer wizard never asks, so no declaration can exist (baseline control).

<a id="fn-c"></a>
**c** — Search surface: legacy form template `advancedSearchReviewerForm.tpl`
mounts `AdvancedSearchReviewerContainer.vue` (VUE-016; AFFW-621, 213–214) →
`SelectReviewerListPanel.vue` (AFFW-215, 217..220) and
`SelectReviewerListItem.vue` (AFFW-221..224). Data:
`AdvancedSearchReviewerForm::fetch()` builds
`PKPSelectReviewerListPanel` — apiUrl `users/reviewers` (the endpoint is the
users API's reviewers listing, owned by *Users management*; API-047 rider),
filters exactly as Rule 6 (`reviewer.list.filterRating` "Rated at least",
`.completedReviews`, `.daysSinceLastAssignmentDescription`,
`.activeReviewsDescription`, `.averageCompletion`), item labels
`reviewer.list.*` quoted in Rules 5–8 (`currentlyAssigned`, `warnOnAssign`,
`warnOnAssignUnlock` "Unlock", `reviewerSameInstitution`,
`assignedToLastRound`, `reassign` "Reassign", `neverAssigned`, `biography`,
`empty` "No reviewers found", filter
`showOnlyReviewersFromPreviousRound` "Assigned to Earlier Round"). Lock
roster (`warnOnAssignment`): all user ids with any stage assignment on the
submission + every manager/site-admin (comment: cannot guarantee anonymous
review); unlock is client-side per item (`isWarningBypassed`). Same-
institution badge: client-side affiliation string match against the
publication's author affiliations. Last-round list: assignments of round
N-1 with status complete/thanked. Author list aid: `submissionAuthorList`
labels, collapse guard `authorCount > 4` (AFFW-213/214). Interests roster
for the tag field and display: the site-wide interests vocabulary, served by
the interests lookup (API-048; mounted ojs+omp — OPS mounts only the generic
vocab controller, an install fact consistent with the absence paragraph).
Live-probed 2026-08-02 (OJS + OMP): the five filters and their strings as
quoted, each slider disabled until its per-filter enable button is pressed;
pagination past 30 entries (30 per page, "View additional pages"); expanded
entries omit the interests/notes/biography sections entirely when empty;
the author list bolds the first four names behind "Show All {N} Authors" /
"Show Less"; the same-institution badge fired on a live case-insensitive
affiliation match; round 2 hoisted the last-round reviewer to the top with
the notice and "Reassign" button. The locale string "Assigned to Earlier
Round" (`reviewer.list.showOnlyReviewersFromPreviousRound`) is wired to no
control — neither the Vue panel nor the PHP filter config references it, and
the live Filters sidebar shows exactly the five sliders; an orphaned string,
recorded here rather than in the register. ORCID display top-up probe
2026-08-07 (OJS; scratch journal, scratch reviewer with a DB-seeded `orcid`
user setting — no seeded reviewer carries an iD): with `orcidIsVerified`
unset, the entry rendered the iD as a new-tab link reading
"https://orcid.org/0000-0002-1825-0097 (unauthenticated)" — the
`orcid.unauthenticated` suffix appended by
`Identity::getOrcidDisplayValue()` — beside the outline (hollow) ORCID
logo (`OrcidUnauthenticated` icon); with `orcidIsVerified` set, the same
entry showed the bare iD URL, no suffix, beside the solid green logo
(`Orcid` icon). Both icons are decorative SVGs with no accessible name, so
the "(unauthenticated)" suffix is the only textual marker
(`SelectReviewerListItem.vue`: `item.orcid` → link, `item.orcidIsVerified`
→ icon, `item.orcidDisplayValue` → visible text).

<a id="fn-d"></a>
**d** — Add form shell: `ReviewerForm` (defaults: review method from context
`defaultReviewMode`, else double-anonymous; visibility from
`getDefaultReviewPublicVisibility()`; section review form via
`section->getReviewFormId()`); footer template `reviewerFormFooter.tpl`
(AFFW-634..641; labels quoted in Fields:
`stageParticipants.notify.chooseMessage`,
`editor.review.personalMessageToReviewer`, `editor.review.skipEmail`,
`editor.review.importantDates`(+`.notice`), `submission.task.responseDueDate`,
`editor.review.reviewDueDate`, `editor.submissionReview.restrictFiles`
"Files To Be Reviewed" / `.hide`, `.reviewType`, `.publicVisibility`,
`manager.setup.reviewOptions.publicReviewerComments.show`,
`submission.reviewForm`, `editor.submission.noReviewerFilesSelected`
"No Files Selected"); file list = `LimitReviewFilesGridHandler` (GRID-026)
over the round's review-file stage. Selection validation
`editor.review.mustSelect` "You must select a reviewer"
(AdvancedSearchReviewerForm) is a server-side backstop only — live-probed
2026-08-02: the request form and its submit button stay hidden until a
reviewer is chosen, so the screen cannot trip the message. Selected-reviewer
sub-form `advancedSearchReviewerAssignmentForm.tpl` (AFFW-623/624; "Change"
link = `manager.reviewerSearch.change`; since the 2026-08-25 rebase it also
renders `#selectedReviewerEmail` beside the name — dev-team#178). Execute path:
`EditorAction::addReviewer()` — duplicate-in-round and has-reviewer-role
checks (`_isValidReviewer`), assignment stamped notified/new, ticked files
granted per assignment (`ReviewFilesDAO::grant`), trivial notice
`notification.addedReviewer`/`.addedReviewerNoEmail`. Template roster:
round 1 default REVIEW_REQUEST, later rounds default
REVIEW_REQUEST_SUBSEQUENT, both plus all their alternates (unconditionally
since the 2026-08-25 rebase — finding A5, retired); the `hasCustomTemplates = count > 1` chooser gate
is meant to hide the chooser with only one template, but the unconditional
subsequent-template append always satisfies it, so the chooser always
renders (finding A19, note f-a19). The empty-letter failure mode of the
same form is finding A18 (note f-a18). Files-warning wiring: the
change-driven show/hide of `noFilesWarning` exists only in the Edit
window's handler (`EditReviewFormHandler.js`); the Add window's handler
(`AdvancedReviewerSearchHandler.js`) has no reference to it — live-probed
2026-08-02 (claim check): untick-all in Add showed no warning and saved
(row created, zero grants), while the warning rendered in Add over a
zero-file round, and the Edit window showed/hid it reactively. Suggestion
entry: `reviewerSuggestionId` prefills
selection (search), or name/email/affiliation (create), or user (enroll) —
`getReviewerForm()`; approval side effect note m.

<a id="fn-e"></a>
**e** — `CreateReviewerForm`: validators quoted in Fields (username unique +
alphanumeric via `FormValidatorUsername`, email valid + unused, given name
required in site primary locale, family-name-needs-given-name custom check,
user group required); template `createReviewerForm.tpl` (AFFW-626..630;
"Suggest" button → username suggestion op; user-group select rendered only
with >1 reviewer group for the stage — `getUserGroupsByStage`; masthead
checkbox disabled+checked). Account creation: generated password
(`Validation::generatePassword()`), `mustChangePassword` set (the forced
change itself is *Sign-in & sessions*'), interests stored via the user-
interests repository, group joined with masthead flag on. Welcome mail
`ReviewerRegister` (MAIL-038, key REVIEWER_REGISTER) carrying the password;
reply-to = journal principal contact; skipped by `skipEmail`.
`EnrollExistingReviewerForm`: autocomplete op
`getUsersNotAssignedAsReviewers` (excludes holders of any reviewer group in
the context); validation `isValidUserAndGroup` (user exists, has no
reviewer role, group is a reviewer group of this context); template
`enrollExistingReviewerForm.tpl` (AFFW-631..633). Live-probed 2026-08-02
(OJS + OMP, plus a two-group OJS scratch journal): the create-mode group
select is hidden with one reviewer group and shown with two; the enroll
form is headed "Enroll an Existing User as Reviewer" and always renders the
group select; the "Appear on the masthead" checkbox is present, ticked and
disabled in both modes; duplicate username/email refusals surface as toasts
(strings quoted in Fields); "Suggest" filled "petra" from given name Petra;
the enroll autocomplete lists only users already enrolled in the context
and excludes holders of ANY of its reviewer groups — on an OMP internal
round, External Reviewers were excluded too; the empty-submit validation
message is the generic "This field is required." and clears on pick
(retired finding A14, note f-a14) — claim check 2026-08-02: an enroll
driven end-to-end on a scratch journal granted the role and produced a
"Request Sent" row. Same probe: the welcome mail carried
the username and generated password and first sign-in landed on the "Change
Password" screen; an enrolled user held the reviewer role permanently
afterwards (visible in the journal's users list).

<a id="fn-f"></a>
**f** — Due dates: `HasReviewDueDate` trait — context
`numWeeksPerResponse`/`numWeeksPerReview`, fallbacks 3/4 weeks, both
end-of-day today-based. Validators: both dates required
(`editor.review.errorAddingReviewer`), review ≥ response
(`FormValidatorDateCompare`, message
`editor.review.errorAddingReviewer.dateValidationFailed` — "…must be equal
or greater than responde due date", typo as shipped; live-probed 2026-08-02
on OJS + OMP the message never reaches the screen — the refusal is silent,
finding A8; control: valid dates on the same path succeed). The same pair
guards the Edit and Resend windows. Defaults live-probed 2026-08-02: each
date arrived as today + its own configured week count, independently (2/6
weeks on a scratch journal, 4/8 cross-check on the baseline; the 3/4-week
unset fallback was not exercised). The pickers' wrong-format-input discard
and missing past-date guard are findings A16/A17 (notes f-a16, f-a17).

<a id="fn-g"></a>
**g** — `EditReviewForm` (template `editReviewForm.tpl`, AFFW-642..644):
review-form select rendered only while `!getDateCompleted()`; execute
revokes all file grants then re-grants the ticked ones; a change to either
due date or the review method creates the reviewer task
NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED (NOTIF-048, task level; text
`notification.type.reviewAssignmentUpdated` "Review assignment updated.")
and sends `EditReviewNotify` (MAIL-026, key REVIEW_EDIT) with
`allowUnsubscribe`, suppressed when the reviewer blocked that notification
type's emails; changing only files/visibility/form skips both. Live-probed
2026-08-02 (OJS, two independent runs; also on OMP via a calendar-driven
due-date edit, claim check): the sent change notice carried the
pre-change dates — the mail was composed before the edit was applied
(finding A11, retired: since the 2026-08-25 rebase the mailable is built
from a post-edit re-fetch, pkp/pkp-lib#13162); the email type is offered
neither on the profile's notification
settings nor on the unsubscribe page its footer links to (finding A12).

<a id="fn-h"></a>
**h** — Manual reminder: Vue guard statuses RESPONSE_OVERDUE/REVIEW_OVERDUE
(AFFW-488); form `ReviewReminderForm` (template `reviewReminderForm.tpl`,
AFFW-646..648; schedule readout branches on `getDateConfirmed()`), mailable
`ReviewRemind` (MAIL-042, key REVIEW_REMIND), editor sender; stamps
`dateReminded`; success notice `notification.sentNotification`; event log
`submission.event.reviewer.reviewerReminded`. The template-body preview op
masks the one-click URL variable so editors never see a live reviewer link
(`fetchReviewReminderTemplateBody`). Automatic reminders: scheduled task
`PKP\task\ReviewReminder` (JOB-054, owned by *Review setup & review forms* —
clock thresholds `numDaysBefore/AfterReviewResponseReminderDue`,
`numDaysBefore/AfterReviewSubmitReminderDue`) dispatches one queued job per
due assignment — `PKP\jobs\email\ReviewReminder` (JOB-012) sending
`ReviewResponseRemindAuto` (MAIL-046, key REVIEW_RESPONSE_OVERDUE_AUTO) or
`ReviewRemindAuto` (MAIL-043, key REVIEW_REMIND_AUTO) from the journal's
principal contact, with one-click link when enabled; stamps
`dateReminded` + `reminderWasAutomatic`, email-logged and event-logged
(`reviewerRemindedAuto`). A reviewer response resets both reminder fields
(`ReviewerAction::confirmReview`), re-arming the automatic clock for the
next phase — the reset that also erases the History "Reminder" line,
observed live (finding A15, note f-a15). The automatic clocks themselves
cannot run in this environment (`task_runner` off); their behavior stands
on this code basis. Live-probed 2026-08-02 (OJS + OMP; claim check re-drove
the manual path as a Section Editor — the overdue row's Actions button
reads "Send Reminder" and a "Request Sent" row's menu offers no reminder
entry): window title
"Review Reminder"; the "Review Schedule" readouts as in Fields ("Editor's
Request" · "Response Due Date" · "Review Due Date", the response field
giving way to "Review Acceptance Date" once confirmed); toast "Notification
sent."; reminder subject "A reminder to please complete your review".
One-click access (scratch journal): the request email carried a keyed
invitation URL that landed a fresh logged-out browser authenticated on the
reviewer request page; the sent reminder resolved the preview's placeholder
to a fresh keyed URL of its own (new id and key); edit-change notices
carried plain links even with one-click on.

<a id="fn-i"></a>
**i** — Read window: `PKPReviewerGridHandler::readReview` → `readReview.tpl`
(AFFW-655..663; instruction `editor.review.readConfirmation`, comments split
`submission.comments.canShareWithAuthor` "For author and editor" /
`.cannotShareWithAuthor` "For editor only", competing-interests block,
attachments grid `EditorReviewAttachmentsGridHandler` (GRID-012) headed
`reviewer.submission.reviewerFiles` "Reviewer Files", rating radios
`editor.review.rateReviewer` with `editor.review.reviewerRating.none` "No
rating"). The viewed-marking step never fires — it compares strictly
against a state a new assignment never holds, so opening a fresh review
leaves it submitted (finding A10). Confirm posts `reviewRead`:
saves/clears the rating, sets considered (or reconsidered), stamps the
consideration date — and, for a not-yet-completed review, also stamps
completion — clears the reviewer's "Review pending." task (NOTIF-019), logs
`log.review.reviewConfirmed`, and triggers the ORCID deposit for the
assignment (`SendReviewToOrcid`; consent/config is the ORCID feature —
AFFW-506's deposit confirm rider). {OJS} recommendation dropdown: app
template override captures `reviewerRecommendations.tpl`
(`reviewer.article.recommendation`, description
`…selectRecommendation.byEditor`; AFFW-664); OJS `ReviewerGridHandler::
reviewRead` records a changed value with log
`log.review.reviewRecommendationSetByProxy` (GRID-086). {OMP} override
captures an empty block (AFFW-665); since the 2026-08-25 rebase the
authoritative gate is the app flag
`hasCustomizableReviewerRecommendation() === false` (the empty override
remains). Vue modal head
`ReviewerManagerReadReviewModal.vue` (VUE-068; AFFW-504/657): "Download"
menu labels `editor.review.download`, `editor.review.authorOnly` (PDF/XML),
`editor.review.allSections` (PDF/XML) → reviews API `export-pdf`/
`export-xml` with `authorFriendly`, then the temporary-file download op (the
`reviews` endpoints are owned by *Author response to reviews*; API-032
rider); export rendering `reviewDownload.tpl` (AFFW-668) drops the
private-comments block in author-friendly mode. Live-probed 2026-08-02: the
menu button reads "Download Review Form"; the editor-only comments heading
is "For editor" on OJS and "For editor only" on OMP (the exports mirror
each app's heading); the author-friendly variants anonymize the reviewer —
"Reviewer: C" in the PDF, `<anonymous/>` in the JATS XML (all four variants
fetched on OJS, one on OMP); every variant saves under the same filename.
Claim check 2026-08-02: the "Download Review Form" menu renders for
free-form reviews too — it is not gated on a review form.
Same probe: the window's contents appeared in the drafted order, "Confirm"
turned the row "Complete" with the recommendation under the status {OJS},
the OMP window rendered no recommendation control, and an editor-changed
recommendation was logged verbatim ("…has been set by the editor, …, on
behalf of the reviewer, …"). ORCID row action guard bug: finding A1 (note
f-a1).

<a id="fn-j"></a>
**j** — `ThankReviewerForm` (template `thankReviewerForm.tpl`, AFFW-645):
mailable `ReviewAcknowledgement` (MAIL-034, key REVIEW_ACK), stamps
`dateAcknowledged` (status → Thanked), notices
`notification.reviewerThankedEmail` / `notification.reviewAcknowledged`
(skip variant). Revert: confirm dialog (AFFW-490/506; title
`editor.review.unconsiderReview` "Unconsider this Review", body
`…unconsiderReviewText`) → op `unconsiderReview` sets considered =
UNCONSIDERED (status falls back to Received, or Viewed when previously
thanked — `getStatus()` branch), logs `log.review.reviewUnconsidered`;
review content and note history untouched (handler comment). Live-probed
2026-08-02 (OJS): revert returned a "Complete" row to "Review Submitted"
and a "Reviewer Thanked" row to "Review Viewed", with no toast either way;
both thank notices verbatim, and the thank-you-mail / suppressed-mail
controls both passed. Claim check 2026-08-02: the same journey re-driven as
a Section Editor on OJS and on the OMP twin — both revert directions
identical; the OMP thank mail "Thank you for your review" arrived under the
acting editor's name.

<a id="fn-k"></a>
**k** — Unassign/cancel: `UnassignReviewerForm` over
`ReviewerNotifyActionForm` (template `unassignReviewerForm.tpl`,
AFFW-649/650; submit label switches on `dateConfirmed`). Execute: no
response → assignment DELETED; responded → flagged cancelled with date;
either way the reviewer's task row (NOTIF-019) is deleted, the mail
`ReviewerUnassign` (MAIL-041, key REVIEW_CANCEL) goes unless skipped,
notices `notification.removedReviewer`/`.cancelledReviewer`, log
`log.review.reviewCleared`. The unassign path touches nothing else — no
participant, task-assignment or discussion cleanup exists on this build
(the clear-review hooks have zero listeners; the once-claimed drop from
open editorial tasks and discussions was a stale carry-over from the
retired `queries` implementation). Reinstate: `ReinstateReviewerForm`
(AFFW-651) clears the cancelled flags, mail `ReviewerReinstate` (MAIL-039,
key REVIEW_REINSTATE), notice `notification.reinstatedReviewer`, log
`log.review.reviewReinstated`. Resend: `ResendRequestReviewerForm`
(AFFW-652; live-probed 2026-08-02 on a distinct-interval journal: each
picker arrives preset from its own interval, as at add time — the
once-recorded collapse is retired finding A9, note f-a9) clears declined,
sets
`requestResent`, nulls `dateConfirmed`, sets both dates; mail
`ReviewerResendRequest` (MAIL-040, key REVIEW_RESEND_REQUEST), notice
`notification.reviewerResendRequest`; status REQUEST_RESEND until the next
response. Log Response: side modal `WorkflowLogResponseModal.vue` (VUE-067,
AFFW-501/505) posting the log-response form (radio labels quoted in Fields
from `editor.review.logResponse.form.*`) to the reviews API `confirmReview`
op (API-032 rider) → `ReviewerAction::confirmReview` — acts only while
`dateConfirmed` is null; stamps the response, resets reminder bookkeeping,
sends the editor-side response acknowledgement mails owned by the
*Reviewer's review* feature, declines also void the one-click invitation.
Vue action guard `!reviewAssignment.dateConfirmed` (AFFW-501). Live-probed
2026-08-02 (OJS, OMP where noted): notices as quoted; cancel mail subject
"Request for Review Cancelled" — its body ships the typo "reivew" ("…cancel
the request for you to reivew the submission…", both apps) — and the
unassign of an unanswered reviewer sent the same notice; reinstate subject
"Can you still review something for {journal}?"; after a resend the row's
menu again offered "Unassign Reviewer" and "Log Response"; the unassigned
reviewer's own dashboard lost the assignment (before/after, both apps). Log
Response (both apps): accept → "Request Accepted", decline → "Request
Declined", no mail to the reviewer, and the assigned editors received the
same response mails a real reviewer click sends, From set to the reviewer's
address.

<a id="fn-l"></a>
**l** — Email: `EmailReviewerForm` (template `emailReviewerForm.tpl`,
AFFW-653; subject/body validators `email.subjectRequired`/
`email.bodyRequired`, but live-probed 2026-08-02 on OJS and OMP only the
subject's error renders — as "This field is required." — and a subject-only
submit sends with an empty body, finding A13; "To" shows the reviewer's
name — the earlier username reading came from throwaway accounts whose name
equals their username (claim check 2026-08-02, seeded "Paul Reviewer");
submit button "Send Email"), plain mailable from/reply-to the acting
editor, email-logged. History: op `reviewHistory` → `workflow/reviewHistory.tpl`
(AFFW-498/708) listing the dated milestones quoted in Rule 21 (label keys
`common.assigned/notified/reminder/confirm|declined/completed/acknowledged`),
sorted by date, blanks skipped. Gossip: op guarded by
`Repo::user()->canCurrentUserGossip()` — viewer must hold manager, site
admin or sub-editor in the context, target must hold a reviewer role, never
self; assistants additionally lack the op in the role map (note a); form
`ReviewerGossipForm` (template `reviewerGossipForm.tpl`, AFFW-654) reads and
writes the user-level gossip field (finding A4). Login As: row entry on
`canLoginAs` (AFFW row action; the flag and flow are
[→ who may impersonate whom](U01-login-and-sessions.md#who-may-impersonate));
confirm text `grid.user.confirmLogInAs`. Live-probed 2026-08-02 (OJS +
OMP): History opens as a side modal titled "History"; milestones observed
verbatim "Assigned · Notified · Confirm · Completed · Acknowledged", dated
"YYYY-MM-DD hh:mm AM/PM", blanks omitted (the bare-verb "Confirm" is as
shipped). The row-menu entry is labeled "Editorial Notes" — the internal
name "gossip" never appears on screen; the window's guidance text is quoted
in Fields, and a note written on one submission was read back verbatim from
another (finding A4).

<a id="fn-m"></a>
**m** — Add-reviewer side effects: task NOTIFICATION_TYPE_REVIEW_ASSIGNMENT
(NOTIF-019, task level, text `notification.type.reviewAssignment` "Review
pending.", linking to the reviewer's submission page); request mail
round 1 `ReviewRequest` (MAIL-044, key REVIEW_REQUEST), later rounds
`ReviewRequestSubsequent` (MAIL-045, key REVIEW_REQUEST_SUBSEQUENT), sender
= acting editor, body = the edited personal message; with
`reviewerAccessKeysEnabled` a `ReviewerAccessInvite` invitation is created
and its one-click URL substituted into the mail (invitation lifecycle
[→ user invitations](U06-user-invitations.md#invitation-states); the landing is
the *Reviewer's review* feature). Event log `log.review.reviewerAssigned`;
email log records the request (the request/subsequent event-type switch
compares the round number against an unrelated status constant that happens
to equal 1 — behaviorally correct today, footnote-only). Suggestion
approval: after any successful add, a pending suggestion matching the
reviewer's email (or the one the window was opened from) is marked approved
with reviewer attached — the panel is the *Reviewer suggestions* feature.
Live-probed 2026-08-02 (OJS + OMP): success notices verbatim, skip-email
suppressed the mail (positive and negative controls), sender = the acting
editor; request subjects "Invitation to review" (OJS) / "Manuscript Review
Request" (OMP), and "Request to review a revised submission" with the
reconsider wording for the subsequent-round template. Claim check
2026-08-02: a UI-added reviewer's own Tasks list showed "Review pending."
with the submission title (OMP).

<a id="fn-n"></a>
**n** — Settings inventory (config surfaces owned elsewhere): context data
`defaultReviewMode`, `numWeeksPerResponse`, `numWeeksPerReview`,
`numDaysBefore/AfterReviewResponseReminderDue`,
`numDaysBefore/AfterReviewSubmitReminderDue`, `reviewerAccessKeysEnabled`,
default public visibility, review forms + section default
(`section.reviewFormId`), `reviewerSuggestionEnabled`.

<a id="fn-o"></a>
**o** — OMP parameterization: `APP\controllers\grid\users\reviewer\
ReviewerGridHandler` (omp-main) is an empty subclass (GRID-098);
`workflowConfigEditorialOMP.js` mounts ReviewerManager on the internal stage
(no `recommendations` prop) and inherits the OJS external-review block
through the config deep-merge. Stage split: reviewer groups are fetched per
stage (`getUserGroupsByStage`), and the seeded press assigns the Internal
Reviewer group to Internal Review and External Reviewer to External Review;
file grants use the internal-review file stage on the internal stage. The
recommendation absence: OMP's dashboard passes no recommendation roster and
its read-review template blanks the recommendation control (AFFW-665);
`hasCustomizableReviewerRecommendation()` returns true in both apps, so the
divergence lives in the template/roster, not the capability flag.
Live-probed 2026-08-02: the stage split held for the searched roster —
positive and negative name searches on both stages, and the underlying
reviewers listing filtered per stage — while the window's opening list
showed both groups on either stage (finding OMP2, note f-omp2); the OMP
read-review window rendered no recommendation control.

<a id="fn-p"></a>
**p** — OPS absence, install facts: the preprint server's stage roster is
Production only and it seeds no reviewer user groups (glossary roles table:
no reviewer keys in the OPS group roster); no workflow config block mounts a
ReviewerManager; the OPS `vocabs` API entry point mounts only the generic
vocabulary controller, not the reviewer-interests one (API-048). The
review-stage absence itself is owned by *Review stage & rounds* (its
scenario 14); scenario 15 here probes only the reviewer-specific surfaces.
Live-probed 2026-08-02: no Reviewers panel anywhere on a preprint's
workflow (stage tree: Production only), no Reviewer role in the Roles list,
a "Reviewer" role filter returning zero users, with Production's own
controls rendering as the positive control. One install nuance: the generic
"Create New Role" permission-level list still offers "Reviewer" — an
application-level enum; no reviewer group is seeded and none is reachable.

<a id="fn-s"></a>
**s** — Scenario seeding: seeded journal/press (`publicknowledge`), roster
accounts per the e2e harness (OJS reviewers `reviewer.julia/paul/amara/adam`;
OMP splits julia/paul as External and amara/adam as Internal Reviewers);
mutating flows run on scratch submissions created through the test scenario
endpoints (`reviewRounds[].reviewers[]` seeding exists, incl. accepted /
declined / submitted states and per-reviewer review forms); mail observed in
the mail catcher with per-test throwaway recipients. Overdue rows (scenario
7) are seeded with passed due dates rather than waited for. The baseline
journal seeds double-anonymous review with deadlines and reminder
thresholds; scratch contexts are used for anything needing different review
setup. Claim check 2026-08-02: scenario 1 driven end-to-end as a Section
Editor (previously covered by other roles); scenarios 8–14 re-driven as a
Section Editor on OJS with OMP twins for 9, 13 and 14; scenario 15 stands
on the earlier OPS probe of the same date.

<a id="fn-a1"></a>
**f-a1** — `useReviewerManagerConfig.js::getItemActions`: the guard reads
`reviewAssignment.reviewerHasOrcid && pkp.const.
REVIEW_ASSIGNMENT_STATUS_COMPLETE` — the second operand is the constant
itself (truthy, value 8), not a comparison with `statusId`, so only the
ORCID half gates. `reviewerHasOrcid` requires `orcidIsVerified` on the
reviewer account (note a). The deposit endpoint itself
(`reviews/…/sendToOrcid`) was not exercised. Live check 2026-08-02 (OJS,
three states including "Complete"): with no reviewer holding an ORCID, the
entry appeared in no menu — the with-ORCID display stays code-based. Claim
check 2026-08-02: the guard re-read verbatim at the same site
(`useReviewerManagerConfig.js:385–388`, constant compare, always truthy)
and the deposit call re-confirmed in the confirm path
(`PKPReviewerGridHandler::reviewConfirmed` → `SendReviewToOrcid`,
PKPReviewerGridHandler.php:818); attaching an iD still needs the external
OAuth flow no screen here provides. Settling observation unchanged.

<a id="fn-a2"></a>
**f-a2** — `getCellStatusItems`, case REQUEST_RESEND: `message:
t('editor.review.responseDue', {date: formatShortDate(reviewAssignment.
dateDue)})` — the label key is response-due, the value passed is `dateDue`
(the review deadline); every other state pairs the label with its own date.
Live-confirmed 2026-08-02 (OJS): with response due 2026-08-20 and review
due 2026-09-10 set in the Resend window, the resent row's cell read
"Response due: 2026-09-10". Re-driven in the claim check (2026-08-02, as a
Section Editor) with a second distinct pair — response +10 days / review
+20 days — and the cell again printed the review date under the
response-due label.

<a id="fn-a3"></a>
**f-a3** — Disproof live-probed 2026-08-02 (claim check, OJS): a throwaway
account holding ONLY the site-level administrator group opened the workflow
URL and was refused with the Error dialog "The current role does not have
access to this operation." — no Workflow heading rendered; positive
control: the same account rendered the full Administration page.
Methodology note, worth keeping: the seeded `admin` account is enrolled as
Journal Manager in every journal of this install (its user-group rows
include each journal's manager group), so the earlier "site admin was
offered both links and completed an add" observations — made with `admin` —
were observations of a Journal Manager. The handler's role map does admit
ROLE_ID_SITE_ADMIN to `createReviewer`/`enrollReviewer`, but the
stage-access policy stops a role-less site admin before any panel op is
reachable.

<a id="fn-a4"></a>
**f-a4** — `ReviewerGossipForm::execute()` writes `User::setGossip()` — a
field of the user record, no submission or assignment key. The reviewer
search list serves the same field per user (`gossipLabel`, note c), which is
also why the search shows it identically everywhere. Live-probed 2026-08-02
(OJS): a note saved on one submission's row was read back verbatim from
another submission's row for the same reviewer, and read-only in the search
expander; the window's guidance text is quoted in the entry and was
re-observed verbatim as a Section Editor in the claim check (2026-08-02;
the cross-submission overwrite was not re-driven).

<a id="fn-a5"></a>
**f-a5** — `AdvancedSearchReviewerForm::getEmailTemplates()`: the alternate
loop tests `isTemplateAccessibleToUser($user, $subsequentTemplate, …)` for
every `$alternateTemplate`; the base-class loop for the first-round template
(`ReviewerForm::getEmailTemplates()`) correctly tests each alternate.
Re-verified in the claim check (2026-08-02): both loops re-read, the wrong
variable still in place. The chooser's always-rendering is the separate
finding A19 (note f-a19). Update 2026-08-25 (rebase check): the pkp/pkp-lib
#10403 revert deletes `isTemplateAccessibleToUser()` and both loops' access
filtering entirely — the alternates are now appended unconditionally in
`ReviewerForm::getEmailTemplates()` and `AdvancedSearchReviewerForm`; A5
retired as moot.

<a id="fn-a6"></a>
**f-a6** — `Schema::getPropertyReviewAssignments()` skips rows with
declined/cancelled set unless `canSeeAllReviewAssignments()` grants
manager/sub-editor/site-admin at the assignment's stage (note a). The
legacy grid ops still authorize assistants for `reinstateReviewer` etc., but
the Vue table is the only current surface and it never receives the rows.
Live-probed 2026-08-02 (OJS + OMP): the manager control saw active,
declined and cancelled rows; the assistant-level participant's table showed
only the active row, and its row menu lacked "Login As" and "Editorial
Notes".

<a id="fn-a7"></a>
**f-a7** — Live-probed 2026-08-02 (OJS + OMP): every fresh invitation's
status cell rendered the title alone; the response due date was set and
visible in the Edit window. An earlier code reading placed the
`editor.review.responseDue` sub-line here (note b); the live cell never
shows it.

<a id="fn-a8"></a>
**f-a8** — Live-probed 2026-08-02 (OJS + OMP): review date set before the
response date, submit — no toast, no inline error, the window stayed open
and no row appeared. Control: correcting the dates on the same form
succeeded. The undisplayed server message string is quoted in note f.

<a id="fn-a9"></a>
**f-a9** — Disproof live-probed 2026-08-02 (claim check; OJS scratch
journal with intervals set to 2/6 weeks): the Resend pickers arrived preset
response +2 weeks and review +6 weeks — each from its own interval, exactly
as a new request's (note f). Code agrees: `ResendRequestReviewerForm` takes
its defaults from the same due-dates trait as the Add form. Recorded so the
entry doesn't come back: the original observation ran where the two
intervals were equal, making "both preset to the response interval"
indistinguishable from correct behavior.

<a id="fn-a10"></a>
**f-a10** — Live-probed 2026-08-02 (OJS + OMP; shared handler; the claim
check re-drove both apps, OJS as a Section Editor): open "Read
Review", close without confirming → row still "Review Submitted" on both
apps; "Review Viewed" only ever appeared after revert-from-Thanked, both
apps. Mechanism: the viewed-marking branch
compares strictly against a state new assignments never hold, so it never
fires (note i).

<a id="fn-a11"></a>
**f-a11** — Live-probed 2026-08-02 (shared mailable): on OJS twice
independently, then re-driven in the claim check with both date fields —
a review-due edit (2026-09-27 → 2026-10-11) reported "Submit Review By:
2026-09-27" and a response-due edit (2026-08-30 → 2026-08-01) reported
"Accept or Decline By: 2026-08-30" — and on OMP via a calendar-driven
due-date edit, whose notice also carried the pre-change date. The edit
saves the new dates on screen while the change-notice
email carries the old ones — the message is composed before the edit is
applied (note g). Update 2026-08-25 (rebase check): fixed upstream —
`EditReviewForm::execute()` now sends `EditReviewNotify` after
`Repo::reviewAssignment()->edit()`, built from a re-fetched assignment
(pkp/pkp-lib#13162); A11 retired. The `isset($notification)` guard is
unchanged, so a files/visibility-only edit still sends nothing.

<a id="fn-a12"></a>
**f-a12** — Live-probed 2026-08-02 (OJS; shared forms): the
assignment-changed email type appears neither in the profile's notification
settings roster nor on the unsubscribe page the email's footer links to.
Re-driven in the claim check (2026-08-02) by fetching the actual
unsubscribe link out of a live change notice: the page lists eleven email
types (issues, submissions, discussions, announcements, tasks, statistics)
— the changed-assignment type absent. The suppression guard itself: note g.

<a id="fn-a13"></a>
**f-a13** — Live-probed 2026-08-02 (OJS + OMP; the OMP subject-only send
was the claim check's, delivered with an empty body): a subject-only submit
delivered a
mail with an empty body on both apps; a both-empty submit showed "This
field is
required." under Subject only — the body's error node never renders
(note l).

<a id="fn-a14"></a>
**f-a14** — Disproof live-probed 2026-08-02 (claim check; OJS, the sequence
driven in both orders): zero "This field is required." messages before or
after picking a user; submitting with the field empty produced the generic
message, picking a user then cleared it, and the subsequent submit
succeeded (row "Request Sent"). The earlier observation's screen state
matches the empty-submit-then-pick order — the post-submit message was read
as a post-pick one.

<a id="fn-a15"></a>
**f-a15** — Settled 2026-08-02 (claim check, OJS; the entry's own recipe
run exactly): reminder sent → History read "Assigned / Notified / Reminder"
(the stamp verbatim "2026-08-02 01:53 PM Reminder" on the same-day probe);
an acceptance logged on the same row → History read "Assigned / Notified /
Confirm" — the Reminder milestone gone and the reminder stamp cleared.
The two earlier conflicting observations are both explained: one read
History before the response, one after. Mechanism: the response path resets
both reminder fields (note h), erasing the line.

<a id="fn-a16"></a>
**f-a16** — Rescoped by the claim check, live-probed 2026-08-02 (OJS + OMP;
the Add, Edit and Resend windows share the picker widget): a date typed in
the field's own Y-m-d format synced to the hidden field while typing,
survived save and reopen, and appeared in downstream mails (typed
2026-10-11, end-to-end); input in another format — typed 10/20/2026 — left
the visible field showing "10202026" while the hidden field silently kept
the prior value, which was what the form submitted. The earlier
"calendar picks only" reading was over-broad; the wrong-format half of the
symptom stands.

<a id="fn-a17"></a>
**f-a17** — Live-probed 2026-08-02 (OJS + OMP): past response and review
due dates saved through the Edit window with no warning — the probes'
overdue rows were produced exactly this way; only the review-after-response
comparison guards the pair (note f).

<a id="fn-a18"></a>
**f-a18** — Live-probed 2026-08-02 (claim check; OJS + OMP, once per app on
scratch submissions, the letter cleared through the screen): the form's
POST returned HTTP 500 with an empty body and nothing surfaced — no error,
no toast, no error page; after reload the panel showed the reviewer at
"Request Sent" with a fully functional row menu, and the mail catcher held
zero request mails for the probe's tag (positive control: a normal add
delivered "Invitation to review"). Mechanism lean: the assignment row is
written before the mailable is composed; the empty body throws during mail
composition, after the DB writes, outside any transaction. The
ordinary-use route: the rich-text letter prefill races the form, so the
field can be empty at submit without the editor ever clearing it.

<a id="fn-a19"></a>
**f-a19** — Live-probed 2026-08-02 (claim check; OJS with two acting roles
and OMP, baseline contexts DB-checked to hold zero alternates of the
request template): the chooser section and its one-option select ("Review
Request") rendered on every baseline add. Mechanism:
`AdvancedSearchReviewerForm::getEmailTemplates()` appends the
subsequent-round request template unconditionally on every round, so the
count-based chooser gate (note d) always passes; the subsequent entry then
drops out of the rendered select, leaving a single visible option. Adjacent
to finding A5, retired 2026-08-25 — no alternate access check remains
(note f-a5); the unconditional append this note describes is unchanged.

<a id="fn-omp1"></a>
**f-omp1** — Mechanism in notes b, i, o: recommendation roster passed only
by `ojs-main DashboardHandler`; OMP `readReview.tpl` override comments "Not
implemented in OMP"; reviewer groups resolved per stage via
`getUserGroupsByStage($contextId, $stageId, ROLE_ID_REVIEWER)`. The
author-side absence and the empty "Recommendation:" letter label are
recorded in review-stage-and-rounds (its own register), probed live
2026-07-31.

<a id="fn-omp2"></a>
**f-omp2** — Live-probed 2026-08-02 (OMP, both stages; re-driven in the
claim check the same day): the opening roster
listed Internal and External Reviewers alike on an internal and an external
round; name searches filtered correctly per stage (positive and negative
controls), as did the underlying reviewers listing queried per stage.
Mechanism: the server-rendered panel builds its initial list without the
review-stage filter that its own request parameters carry
(`PKPSelectReviewerListPanel`); the search's refetch goes through the
reviewers listing, which applies it.

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Reviewers panel | workflow → Review → Review Round {N} → "Reviewers" | VUE-046 · AFFW-486, 488..501, 503 |
| Add Reviewer window | Reviewers panel → "Add Reviewer" | AFFW-621..624 · VUE-016 · AFFW-213..215, 217..224 |
| Create New Reviewer | Add Reviewer window → link | AFFW-626..630 · MAIL-038 |
| Enroll Existing User | Add Reviewer window → link | AFFW-631..633 |
| Shared request form footer | all three add modes | AFFW-634..641 · GRID-026 |
| Default (fallback) add form | add modes without a search surface | AFFW-625 |
| Edit Review window | row menu → "Edit" | AFFW-642..644 · GRID-026 · MAIL-026 · NOTIF-048 |
| Send Reminder window | row button (overdue only) | AFFW-646..648 · MAIL-042 |
| Automatic reminder emails | scheduled, per due assignment | JOB-012 · MAIL-043, 046 |
| Read Review window | row button → "Read Review" | AFFW-655..663 · VUE-068 · AFFW-504, 657 · GRID-012 |
| Review export (download) | Read Review → "Download Review Form" | AFFW-668 |
| {OJS} recommendation control | Read Review window | AFFW-664 · GRID-086 |
| {OMP} recommendation absence | Read Review window | AFFW-665 · GRID-098 |
| Thank Reviewer window | row button | AFFW-645 · MAIL-034 |
| Revert / ORCID confirm dialogs | row actions | AFFW-490, 506 |
| Unassign / Cancel window | row menu | AFFW-649..650 · MAIL-041 |
| Reinstate window | row menu (cancelled rows) | AFFW-651 · MAIL-039 |
| Resend Request window | row menu (declined rows) | AFFW-652 · MAIL-040 |
| Email Reviewer window | row menu | AFFW-653 |
| Editorial Notes window | row menu → "Editorial Notes" | AFFW-654 |
| Log Response side modal | row menu | AFFW-501, 505 · VUE-067 |
| History side modal | row menu → "History" | AFFW-498, 708 |
| Reviewer task ("Review pending.") | reviewer's task list | NOTIF-019 |
| Reviewer interests lookup | create form + search list | API-048 |
| Assignment data shape | review assignments on the submission payload | SET-020 |
| Request emails | keys REVIEW_REQUEST / REVIEW_REQUEST_SUBSEQUENT | MAIL-044, 045 |

## Reference — code anchors

- `lib/pkp/classes/controllers/grid/users/reviewer/PKPReviewerGridHandler.php` — every modal op, role map, author denial
- `ojs-main/controllers/grid/users/reviewer/ReviewerGridHandler.php` (recommendation by proxy) · `omp-main/…/ReviewerGridHandler.php` (empty)
- `lib/pkp/controllers/grid/users/reviewer/form/` — `ReviewerForm`, `AdvancedSearchReviewerForm`, `CreateReviewerForm`, `EnrollExistingReviewerForm`, `EditReviewForm`, `ReviewReminderForm`, `ThankReviewerForm`, `UnassignReviewerForm`, `ReinstateReviewerForm`, `ResendRequestReviewerForm`, `EmailReviewerForm`, `ReviewerGossipForm`, `traits/HasReviewDueDate`
- `lib/pkp/classes/submission/action/EditorAction.php` — assignment creation + request mail
- `lib/pkp/classes/submission/reviewAssignment/ReviewAssignment.php` — status machine; `lib/pkp/schemas/reviewAssignment.json`
- `lib/pkp/classes/submission/maps/Schema.php::getPropertyReviewAssignments()` — row serialization and visibility
- lib/ui-library `src/managers/ReviewerManager/` — table, cells, actions, read-review and log-response modals
- lib/ui-library `src/components/ListPanel/users/SelectReviewerListPanel.vue` · `SelectReviewerListItem.vue` + `lib/pkp/classes/components/listPanels/PKPSelectReviewerListPanel.php` — reviewer search
- `lib/pkp/classes/submission/reviewer/ReviewerAction.php` — response recording (log response)
- `lib/pkp/jobs/email/ReviewReminder.php` (send) · `lib/pkp/classes/task/ReviewReminder.php` (clock, owned by review setup)
- `lib/pkp/classes/mail/mailables/` — `ReviewRequest`, `ReviewRequestSubsequent`, `ReviewerRegister`, `EditReviewNotify`, `ReviewRemind`, `ReviewRemindAuto`, `ReviewResponseRemindAuto`, `ReviewAcknowledgement`, `ReviewerUnassign`, `ReviewerReinstate`, `ReviewerResendRequest`
- `lib/pkp/api/v1/vocabs/PKPInterestController.php` — interests lookup
