# Probe P6 — U26 Review stage & rounds (items 13–14, typed-URL / legacy routes)

Executed live against the OJS fleet at `http://127.0.0.1:8000`, 2026-07-31.

## Fixture (scratch, via scenario endpoints)

- Scratch context: `POST /api/v1/_test/scenarios/context` → contextId 67, urlPath
  `p6u26w03edcf2f7` (single-locale `en`). Users created: `…auth` (author,
  submitter, id 137), `…rev` (externalReviewer, id 138), `…other` (author,
  unconnected, id 139), `…se` (sectionEditor, id 140). Passwords = username×2.
- Scratch submission: `POST /api/v1/_test/scenarios/submission` with
  `decisions:["sendExternalReview"]` and one reviewer `…rev` at status
  `accepted` → submissionId **18**, stageId 3 (External Review), review round
  id 19, review assignment id 14 (accepted).

URLs under probe (context path == tag; scratch journal is single-locale so bare
un-prefixed paths serve directly):
- Editorial workflow form: `…/p6u26w03edcf2f7/dashboard/editorial?workflowSubmissionId=18`
- My Submissions form: `…/p6u26w03edcf2f7/dashboard/mySubmissions?workflowSubmissionId=18`
- Legacy author-dashboard submission: `…/p6u26w03edcf2f7/authorDashboard/submission/18`
- Legacy round tab: `…/p6u26w03edcf2f7/authorDashboard/reviewRoundInfo/18` and `…/authorDashboard/reviewRoundInfo`

---

## Item 13 — typing another submission's workflow addresses

### 13(a) Unconnected author → editorial-dashboard workflow form

Requested: `…/dashboard/editorial?workflowSubmissionId=18` (signed in as
`…other`, an author with no connection to submission 18).

- HTTP 200, but the request **redirected** to
  `…/p6u26w03edcf2f7/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`.
- On-screen body text (exact): **"The current role does not have access to this
  operation."** (page renders the public journal chrome — "Current / Archives /
  About / Search" — with `Home / The current role does not have access to this
  operation."`). No workflow dialog. No submission data (no title, abstract,
  reviewer, or round detail) present in the DOM.
- (Claim vs context: the address is role-gated for author-only accounts — the
  editorial dashboard's role assignment is Site admin / Manager / Sub-editor /
  Assistant; an author is stopped at the page authorization layer.)

### 13(b) Unconnected author → My Submissions workflow form

Requested: `…/dashboard/mySubmissions?workflowSubmissionId=18` (same
`…other` author).

- HTTP 200. Final URL `…/dashboard/mySubmissions?workflowSubmissionId=18&currentViewId=active`.
- The page renders the actor's **own** author dashboard: heading "Active
  submissions (0)"; the counts read "0 Active submissions / 0 Revisions
  requested / 0 Revisions submitted / 0 Incomplete submissions / 0 Scheduled for
  publication / 0 Published / 0 Declined"; list body "No Items / Showing 0 to 0
  of 0". (This author owns no submissions; submission 18 is not listed.)
- The `workflowSubmissionId=18` triggers a workflow dialog frame to open, but its
  content is an **Error** dialog (exact text): heading "Error", body **"The
  current role does not have access to this operation."**, button "OK". The
  dialog frame shows only the id echo "18" and "Close"; no title, abstract,
  reviewer identity, round status, or any other submission-18 data is rendered.
- (Claim vs context: My Submissions is reachable by any author for their own
  queue; opening a foreign submission's workflow returns an access error with no
  data, i.e. the per-submission entitlement holds at the dialog/API layer.)

### 13(c) Assigned reviewer → editorial-dashboard workflow form

Requested: `…/dashboard/editorial?workflowSubmissionId=18` (signed in as
`…rev`, the reviewer assigned to submission 18, status accepted).

- HTTP 200, **redirected** to
  `…/p6u26w03edcf2f7/user/authorizationDenied?message=user.authorization.roleBasedAccessDenied`.
- On-screen body text (exact): **"The current role does not have access to this
  operation."** No workflow dialog, no submission data.
- (Claim vs context: a reviewer reaches their own review via the reviewer
  submission page, not the editorial dashboard; the editorial-dashboard address
  is role-gated away from the reviewer role even for a submission they are
  assigned to.)

Security routing: nothing observed in 13(a)/(b)/(c) showed a role seeing more
than its entitlement — each denial produced an access error with no
submission-18 data. **Nothing was routed to the private security file.**

---

## Item 14 — the submission's own author typing legacy addresses

Signed in as `…auth` (the submitter/author of submission 18).

### 14(a) Legacy `authorDashboard/submission/{id}`

Requested: `…/p6u26w03edcf2f7/authorDashboard/submission/18`.

- HTTP 200, **redirected** to
  `…/p6u26w03edcf2f7/dashboard/mySubmissions?workflowSubmissionId=18&currentViewId=active&workflowMenuKey=workflow_3_19`.
- Landing = **My Submissions** with the submission's workflow dialog **open**.
  Visible headings: "Active submissions (1)", "Author", "WORKFLOW: REVIEW (ROUND
  1)", "Round 1 Status", "Revisions Uploaded", "Review Tasks & Discussions". The
  open dialog shows the stage rail (Submission / Review / Review Round 1 /
  Copyediting / Production / Publication), the Publication tabs, and Round 1
  status text "Awaiting responses from reviewers."
- Confirms draft **Rule 17** ("a bookmarked authorDashboard link lands on My
  Submissions with the submission's workflow open"). Matches handler:
  `PKPAuthorDashboardHandler::submission()` issues a `redirectUrl` to
  `dashboard/mySubmissions` with `workflowSubmissionId`.

### 14(b) Legacy `reviewRoundInfo` tab

Requested (two forms):
- `…/p6u26w03edcf2f7/authorDashboard/reviewRoundInfo/18` → **HTTP 404**, page
  body exactly "404 Not Found" (heading "404 Not Found").
- `…/p6u26w03edcf2f7/authorDashboard/reviewRoundInfo` (no id) → **HTTP 404**,
  page body exactly "404 Not Found".

- Confirms the draft's footnote **o** and the "dead route" entry in the
  Reference table: `reviewRoundInfo` has no handler method (only `submission`
  and `readSubmissionEmail` exist on `PKPAuthorDashboardHandler`), so the route
  answers with an error, not a screen — closing the "typed-URL probe pending to
  confirm the dead route answers with an error, not a screen" note. This
  supports the six legacy-surface waivers (AFFW-701, AFFW-703/704, GRID-011,
  GRID-024, GRID-029), whose only render site was this route.

---

## Proposed content for downstream docs (not written per instructions)

- **Spec / Findings register:** No contradiction found. Rule 17 verified;
  footnote-o's "typed-URL probe pending" is now satisfiable — the `reviewRoundInfo`
  route returns **HTTP 404 "404 Not Found"** (both with and without an id
  argument) on OJS.
- **app-changes.md:** nothing proposed (observed behavior matches the draft).

## Blockers

None.
