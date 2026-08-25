# U26 probe report — Group III (items 8–10): author-side surfaces

Agent: probe, RUNBOOK step 3. Date: 2026-07-27. Fleets driven live at OJS
`http://127.0.0.1:8000` and OMP `http://127.0.0.1:8100` (as Group I found, the
`localhost` host name 400s; the servers answer on `127.0.0.1` only).

Every affordance claim below was observed on the live screen by driving
Chromium (Playwright `chromium` from `ojs-main/node_modules`, real UI login per
user, one browser context per role). Marker convention: **CLAIM** = observation
the item asked for, promotable to the spec; **context** = incidental
DOM/behaviour noted in passing, not promotable.

## State seeded (scratch contexts only; no seeded `publicknowledge` data touched)

All contexts and submissions created through `POST /api/v1/_test/scenarios/*`
with unique tags. Review *completion* is not in the step-2 scenario schema, so
each completed review was driven through the reviewer's own UI wizard.

| App | Context (urlPath, id) | `defaultReviewMode` | Submission | Tag | Seeded state | Driven afterwards |
|---|---|---|---|---|---|---|
| OJS | `u26g3open` (11) | 3 = open | 20 | `u26g3-new` | `sendExternalReview`; `reviewer.julia` accepted | julia completed the review in the reviewer UI (attachment + both comment boxes + recommendation "Revisions Required"); editor never confirmed it |
| OJS | `u26g3open` (11) | 3 = open | 21 | `u26g3-overdue` | `sendExternalReview`; `reviewer.paul` accepted | editor.diana moved both due dates into the past via the reviewer row's **Edit** modal |
| OJS | `u26g3anon` (12) | 1 = anonymous | 22 | `u26g3-anon` | `sendExternalReview`; `reviewer.julia` accepted | julia completed the review in the reviewer UI |
| OJS | `u26g3anon` (12) | 1 = anonymous | 23 | `u26g3-notif` | `sendExternalReview`; no reviewers | editor.diana recorded **Request Revisions** (stay-in-round) with the Notify Authors email |
| OMP | `u26g3open` (5) | 3 = open | 19 | `u26g3-new` | `skipInternalReview`; `reviewer.julia` accepted | julia completed the review in the reviewer UI (attachment + both comment boxes) |
| OMP | `u26g3open` (5) | 3 = open | 20 | `u26g3-overdue` | `skipInternalReview`; `reviewer.julia` accepted | editor.diana moved both due dates into the past |
| OMP | `u26g3anon` (6) | 1 = anonymous | 21 | `u26g3-anon` | `skipInternalReview`; `reviewer.julia` accepted | julia completed the review in the reviewer UI |
| OMP | `u26g3anon` (6) | 1 = anonymous | 22 | `u26g3-notif` | `skipInternalReview`; no reviewers | editor.diana recorded **Request Revisions** with the Notify Authors email |

Accounts: `author.alex` (author / submitter), `editor.diana` (Journal editor /
Press editor), `reviewer.julia`, `reviewer.paul`.

**Harness context (not a product finding):** the context scenario's `users[]`
role key for a reviewer is **`externalReviewer`**, not `reviewer`, in both OJS
and OMP default user groups — `reviewer` 400s with
`No default user group for role 'reviewer'`. Worth a line in `users.md` /
`scenarios.md` when the harness docs are next touched.

**Harness context:** the scratch scratchpad directory is shared with the other
probe agents of this wave; scripts must be namespaced (this probe's live under
`.../scratchpad/u26g3probe/`).

## Common locators

- author workflow URL: `/{context}/en/dashboard/mySubmissions?workflowSubmissionId={id}`
- editorial workflow URL: `/{context}/en/dashboard/editorial?workflowSubmissionId={id}`
- primary column of the round: `[data-cy="workflow-primary-items"]`
- round status block: `[data-cy="workflow-primary-items"] div.border.border-light.p-4`,
  heading `h3` = "Round N Status", text `p.text-sm-normal`
- author reviewers panel: heading `h3` with text `Reviewers`, row action
  `getByRole('button', {name: 'Read Review'})`
- notifications panel: heading `h3.lg-bold.m-3.text-heading` with text
  `Notifications`; rows `ul > li`, subject `li a.text.cursor-pointer`, date
  `li span.ms-4`

---

## Item 8 — Round status wording seen by the Author (Rule 8, register A2) — SETTLED

### What was driven

OJS as **Author** (`author.alex`) on submissions 20 (a submitted, unconfirmed
open review) and 21 (an accepted reviewer past the due date); the same two
states re-driven on OMP (submissions 19 and 20) as the spot-check. The
deciding editor's view of the same rounds was read for comparison.

### Observations

**8.1 CLAIM — the author's round-status line is the editor phrasing, verbatim.**
Locator: `[data-cy="workflow-primary-items"] div.border.border-light.p-4`
(`h3` + `p.text-sm-normal`).

| App | Submission | Round state | Author's screen (`h3` / `p`) | Editor's screen (same round) |
|---|---|---|---|---|
| OJS | 20 | one open review submitted, editor has not confirmed it | `Round 1 Status` / **"New reviews have been submitted."** | `Round 1 Status` / "New reviews have been submitted." |
| OJS | 21 | one accepted reviewer, review due date in the past | `Round 1 Status` / **"A review is overdue."** | `Round 1 Status` / "A review is overdue." |
| OMP | 19 | one open review submitted, unconfirmed | `Round 1 Status` / **"New reviews have been submitted."** | — (not read) |
| OMP | 20 | accepted reviewer, due date in the past | `Round 1 Status` / **"A review is overdue."** | — (not read) |

The author's line is byte-identical to the editor's in both OJS states, and the
same two strings appear on OMP. **A2's symptom is confirmed live in both
apps**: the author-phrased variants never reach the workflow screen. For the
spec's benefit, the unused strings (locale `lib/pkp/locale/en/submission.po`)
read:

- `author.submission.roundStatus.reviewsReady` — "New reviews have been
  submitted and are being considered by the editor."
- `author.submission.roundStatus.reviewOverdue` — "One or more reviewers missed
  their deadline. The editorial team is aware and will ensure the reviews are
  completed. No action is needed from you right now. You'll be notified once a
  decision is made."

**8.2 CLAIM — the heading is per-round and identical for both roles**:
"Round 1 Status" on OJS and on OMP's External Review round (OMP's page heading
above it reads `WORKFLOW: EXTERNAL REVIEW (ROUND 1)`, OJS's
`WORKFLOW: REVIEW (ROUND 1)`).

**8.3 context — how the overdue state was produced.** The reviewer row's
**More actions → Edit** modal ("Edit Review", fields "Response Due Date" and
"Review Due Date", note "Review due date must be greater or equal to response
due date.") accepted dates in the past (`2026-07-10` / `2026-07-20`) and saved
them without any validation error. Useful for the U27/U29 specs and for future
overdue seeding; not a Group III claim.

**8.4 context — the author sees an "Upload" control in the "Revisions
Uploaded" panel at both of these statuses** ("New reviews have been submitted.",
"A review is overdue."), i.e. outside Rule 12's window. Item 6 (Group II) owns
that question (footnote c); recorded here only because it was on screen.

---

## Item 9 — Notifications panel (Rule 14, scenario 7) — SETTLED

### What was driven

OJS submission 23 and OMP submission 22 (no reviewers, no author-facing
decision yet) as **Author**; then **Request Revisions** recorded as
`editor.diana` through the real decision wizard (revision-type modal → "Next" →
Composer step "Notify Authors" with the "Revisions Requested" template →
"Record Decision"); then the author's view again.

### Observations

**9.1 CLAIM — the panel is absent before any author-facing decision.** Author's
round view of OJS 23 / OMP 22 listed, in order: "Round 1 Status" ("Waiting for
reviewers to be assigned."), "Revisions Uploaded", "Review Tasks & Discussions".
No `Notifications` heading (`h3` search over
`[data-cy="workflow-primary-items"]` returned none). Note both submissions
already carried a seeded stage-promoting decision (`sendExternalReview` /
`skipInternalReview`) — those decisions send the author nothing, and the panel
stayed absent, so the gate is the author-notify email, not "any decision".

**9.2 CLAIM — after the decision email the panel lists subject and date.**
Observed DOM (OJS 23; OMP 22 identical apart from the timestamp):

```html
<div class="border border-light">
  <h3 class="lg-bold m-3 text-heading">Notifications</h3>
  <ul><li class="flex items-center border-t border-light px-3 py-1">
    <span class="flex-1 truncate">
      <a class="text cursor-pointer text-base-normal hover:underline">Your submission has been reviewed and we encourage you to submit revisions</a>
    </span>
    <span class="ms-4 shrink-0 text-base-normal text-secondary">2026-07-27 06:17 PM</span>
  </li></ul>
</div>
```

Panel title verbatim: **"Notifications"**. Row = the email's subject line plus a
`YYYY-MM-DD hh:mm A` timestamp. The panel sits between the round-status block
and "Revisions Uploaded" in the author's primary column.

**9.3 CLAIM — clicking the subject opens the full message.** A side panel opens,
headed "Notifications", repeating the subject and the same date, followed by the
complete message body ("Dear Alex Author, Your submission "Notifications panel
[u26g3-notif]" has been reviewed …" through "Kind regards, Diana Editor" and a
trailing "The following comments were received from reviewers." section). Same
on OMP.

**9.4 context — the surfaces behind the panel.** Panel load fires
`GET /{context}/api/v1/emails/authorEmails?submissionId={id}&eventType=805306369`;
the subject click fires
`GET /{context}/authorDashboard/readSubmissionEmail?submissionId={id}&submissionEmailId={n}`
(the legacy read-message page of footnote y, still live), with
`lib/pkp/js/pages/authorDashboard/SubmissionEmailHandler.js` loaded alongside.
Both apps.

**9.5 CLAIM — the subject is not a link or a button in the accessibility tree.**
The clickable element is an `<a>` with **no `href`** and no role attribute;
`getByRole('link'|'button', {name: /…subject…/})` matches nothing. Only a CSS/
text locator reaches it (`[data-cy="workflow-primary-items"] li a`). Reported
as an accessibility/labelling gap for whoever owns the panel (Rule 14).

**9.6 CLAIM — only the Review stage shows the panel.** With the notification
present, the same author's other stages showed no Notifications panel:
"Submission" (status "The submission is currently in the Review stage.", plus
"Submission Files"), "Copyediting" ("The Copyediting stage has not yet been
initiated."), "Production" ("The Production stage has not yet been
initiated."). OJS; matches Rule 14's last sentence.

**9.7 CLAIM — legacy author-dashboard URLs (footnote y).** As the author, on
both apps:

| Legacy URL | Result |
|---|---|
| `/{context}/en/authorDashboard/submission/{id}` | 200 after redirect to `/{context}/dashboard/mySubmissions?workflowSubmissionId={id}&currentViewId=active&workflowMenuKey=workflow_3_{reviewRoundId}` — i.e. My Submissions **with that submission's workflow opened at its review round**, not a bare list |
| `/{context}/en/authorDashboard/reviewRoundInfo/{id}` | **404 Not Found** |
| `/{context}/en/authorDashboard` | **404 Not Found** |

Footnote y's claim that the retired round panel is unreachable is confirmed by
the 404; the redirect claim is confirmed with the refinement that it carries the
submission through.

---

## Item 10 — Author reads an open review; anonymity exclusivity (Rule 13, scenario 6) — SETTLED

### What was driven

Open review completed by `reviewer.julia` (OJS 20, OMP 19) with an
author-visible comment ("For author and editor" box), an editor-only comment
("For editor" box), and one attached file (`reviewer-attachment.txt`); read as
**Author**. Anonymous review completed the same way (OJS 22, OMP 21) and read
as Author.

### Observations

**10.1 CLAIM — the author's reviewers panel and its read action.** Panel title
**"Reviewers"**; columns **REVIEWER | TYPE | ACTIONS**; the row read
`Julia Reviewer` | `Open` | a button labelled **"Read Review"**. Identical on
OJS and OMP. Locator: `getByRole('button', {name: 'Read Review'})`.

**10.2 CLAIM — what the read sheet shows (OJS).** Clicking "Read Review" opens a
side sheet titled `Review: Open review round [u26g3-new]` containing, in order:

```
Julia Reviewer
Completed: 2026-07-27 05:58 PM
Recommendation: Revisions Required
Reviewer Comments
For author and editor
AUTHOR-VISIBLE COMMENT u26g3: the argument in section 2 needs a clearer control condition.
Reviewer Files
 Search
Name  Date  Component
41  reviewer-attachment.txt  July 27, 2026
```

So: reviewer's full name, a `Completed:` date-time, the recommendation, one
comments section labelled "For author and editor", and the attachments grid —
matching Rule 13's list.

**10.3 CLAIM — the editor-only comment does not render.** With the sheet open,
`page.content()` (whole document HTML) contained `AUTHOR-VISIBLE COMMENT` and
did **not** contain `EDITOR-ONLY COMMENT` anywhere — the private comment is
absent from the DOM, not merely hidden. Same check passed on OMP.

**10.4 CLAIM — OMP's sheet carries no recommendation line.** OMP 19's sheet
read: name, `Completed: 2026-07-27 06:06 PM`, `Reviewer Comments` /
`For author and editor` + text, `Reviewer Files` with the attachment — **no
`Recommendation:` line at all**. Cause observed upstream on the reviewer's own
screen: OMP's reviewer Completion step (step 3) offers **no recommendation
control** — the step ends at "Go Back / Save for Later / Submit Review", where
OJS's shows a "Recommendation" block with a "Choose One" select (Accept
Submission / Revisions Required / Resubmit for Review / Resubmit Elsewhere /
Decline Submission / See Comments). Every press in `omp_test` has **zero**
`reviewer_recommendations` rows, including the bootstrap press, while every OJS
journal has 6. Corroborating code: `ojs-main/classes/services/ContextService.php:73`
calls `Repo::reviewerRecommendation()->addDefaultRecommendations($context)` on
context creation; OMP's `ContextService` never calls it (the shared repository
method exists at `lib/pkp/classes/submission/reviewer/recommendation/Repository.php:72`).
So on a press created through the app's own path, reviewers cannot record a
recommendation and the author's sheet has none to show, unless a Press Manager
adds recommendations by hand. **Proposed for the spec**: Rule 13's
"recommendation" element is conditional on the context having reviewer
recommendations configured — which OMP does not seed. Suggested register entry
below.

**10.5 CLAIM — an anonymous completed review never reaches the author's screen.**
OJS 22 and OMP 21 (review method anonymous, review completed, status line "New
reviews have been submitted."): the author's round showed **no "Reviewers"
panel at all** — primary column went "Round 1 Status" → "Revisions Uploaded" →
"Review Tasks & Discussions" — and therefore no read action anywhere.

**10.6 CLAIM — the server agrees with the screen for the anonymous case.**
Replaying the exact request the open-review button makes
(`GET /{context}/$$$call$$$/grid/users/reviewer/author-reviewer-grid/read-review?submissionId={id}&reviewAssignmentId={n}&stageId=3`)
from the author's own authenticated session against the anonymous assignment
returned `{"status":false, …}` on both apps — no review content:

- OJS: `"You do not have permission to access this review assignment."`
- OMP: `"You have been denied access because you do not seem to be a valid reviewer for this monograph."`

Screen and server agree (no over-permissive path). **Minor finding**: OMP's
denial text tells the author they are "not a valid reviewer for this
monograph", which describes the wrong role and the wrong reason for the
requester; OJS's wording is the accurate one.

**10.7 CLAIM — the panel does not appear while an open review is only under
way.** OJS 21 and OMP 20 each have an *open* review assignment that the reviewer
has accepted but not submitted; on the author's round view neither app rendered
a "Reviewers" panel (the only "Reviewer" text on the page came from the
dashboard list behind the modal, "Reviewers assigned:"). Rule 13 currently reads
"appears once the round has an open review **under way or completed**"; live,
the panel appears only once at least one open review is **completed**.
Suggested spec correction below.

**10.8 context — reviewer-side label delta.** The private comment box is
labelled "For editor" on OJS and "For editor only" on OMP (same helper text
"These comments are private between you and the editor."). U28's territory.

**10.9 context — the read action's request pair.** "Read Review" fires
`…/author-reviewer-grid/read-review?submissionId={id}&reviewAssignmentId={n}&stageId=3`,
and the sheet then fetches
`…/grid/files/attachment/author-open-review-attachments-grid/fetch-grid?submissionId={id}&reviewId={n}&stageId=3&reviewRoundId={r}` —
matching footnote g's AFFW-666..667 / GRID-010 mapping.

---

## Proposed content for the orchestrator (not written to PROGRESS/atlas/app-changes by this agent)

**Rule 8 (status line identical for every role) — keep as written, upgrade A2's
basis.** A2 line can move from "Basis: code" to probe-confirmed: authors read
"New reviews have been submitted." and "A review is overdue." verbatim on both
OJS and OMP (item 8.1).

**Rule 13 — two edits.**

1. Replace "appears once the round has an open review under way or completed"
   with "appears once the round has a **completed** open review"; an open review
   that is merely under way shows the author nothing (item 10.7).
2. Qualify the recommendation element: the sheet shows the recommendation
   "when the context has reviewer recommendations configured — OJS seeds six per
   journal, OMP seeds none, so an OMP review carries no recommendation to show"
   (item 10.4).

**Rule 14 — keep; add that the panel's date format is `YYYY-MM-DD hh:mm A` and
that the row's subject is a bare `<a>` with no `href`/role (item 9.5), and that
the gate is an author-facing decision email, not any decision (item 9.1).**

**Footnote y — confirmed with a refinement**: `/authorDashboard/submission/{id}`
redirects to My Submissions *with the submission's workflow open at its review
round*; `/authorDashboard/reviewRoundInfo/{id}` and `/authorDashboard` both 404
(item 9.7).

**Suggested new register entries** (IDs for the spec author to assign):

- *OMP presses seed no reviewer recommendations* · 🐞 · user-visible · OMP only.
  Reviewers on a press get no recommendation control on the Completion step and
  the author's read-review sheet shows no recommendation; OJS seeds six defaults
  per journal. Evidence: item 10.4, live on both screens plus zero
  `reviewer_recommendations` rows for every press.
- *Author's reviewers panel waits for a completed review* · ❓ · minor · both
  apps. Rule 13's "under way" case does not exist on screen. Evidence: item
  10.7. Question for the team: should an accepted-but-unfinished open review be
  visible to the author?
- *OMP's anonymous read-review denial names the wrong role* · ❓ · minor · OMP.
  The author is told they "do not seem to be a valid reviewer for this
  monograph". Evidence: item 10.6.
- *Notifications subject row is not a link or button* · 🐞 · user-visible · both
  apps. Keyboard/AT users cannot reach the message. Evidence: item 9.5.

**Harness follow-ups**: `users.md`/`scenarios.md` should record the
`externalReviewer` role key; `scenarios.md` should note that completed reviews,
review method per assignment, and overdue due dates are still UI-only (this
probe drove all three by hand).
