# U26 probe P1 — Review stage & rounds (items 1–3)

Probe date: 2026-07-31. OJS fleet at `http://127.0.0.1:8000`, Playwright (chromium, headless), all screens driven as signed-in users.

## Seeded state

Scratch journal `u26p1jx` ("U26 P1 Probe Journal", contextId 62) via `POST /api/v1/_test/scenarios/context`, with throwaway users `se1u26` (sectionEditor, "Sole SectionEditor"), `ce1u26` (copyeditor, "Cora Copyeditor"), `au1u26` (author, "Avery Author"), `rev1u26`/`rev2u26`/`rev3u26` (externalReviewer: "Rex ReviewerOne" / "Rita ReviewerTwo" / "Remy ReviewerThree").

Three submissions via `POST /api/v1/_test/scenarios/submission`, all `decisions: ["sendExternalReview"]`, stage 3, round 1:

- sub 3 "Panels probe u26p1suba" — round 1 reviewers seeded: rev1u26 `accepted`, rev2u26 `invited` (item 1)
- sub 4 "Statusbox probe u26p1subb" — round 1, zero reviewers (item 2)
- sub 5 "Decline probe u26p1subc" — round 1, zero reviewers (item 3)

Participant assignments were made through the screens (as `admin`, who is auto-enrolled Journal manager on scratch contexts): `se1u26` assigned as Section editor on subs 3/4/5 via the review-stage Participants "Assign" button; `ce1u26` assigned as Copyeditor on sub 3 — the review-stage Assign Participant modal's user-group dropdown (`select[name="filterUserGroupId"]`) offers only `["Journal editor","Section editor","Guest editor","Funding coordinator","Translator","Author"]` (no Copyeditor), so the copyeditor was assigned from the Copyediting stage panel, whose dropdown offers `["Journal editor","Production editor","Section editor","Guest editor","Copyeditor","Marketing and sales coordinator","Author","Translator"]` (context: participant assignment is stage-scoped; the group list follows the stage).

Workflow screens opened as `/index.php/u26p1jx/dashboard/editorial?workflowSubmissionId={id}` (editor roles) and `/index.php/u26p1jx/dashboard/mySubmissions?workflowSubmissionId={id}` (author). The workflow renders inside `[data-cy="active-modal"]`; observations below are scoped to it.

---

## Item 1 — Review Round screen: Section Editor vs Copyeditor (sub 3)

### As Section Editor `se1u26` (assigned participant), Review Round 1

Stage header (top of primary column): `WORKFLOW: REVIEW (ROUND 1)`, then `Current Submission Language: English`.

**Primary panel headings, in order** (headings inside `[data-cy="workflow-primary-items"]`, via `getByRole('heading')`):

1. `Round 1 Status` — body: `Awaiting responses from reviewers.`
2. `Revisions Uploaded` — subtext `These files have been submitted by the author after revisions were requested`; `Upload` button; empty table (`No Items`), columns `NO / FILE NAME / DATE UPLOADED / TYPE / MORE ACTIONS`
3. `Files for Review` — subtext `These files will be sent to the reviewers to review`; `Upload/Select Files` button; empty table
4. `Reviewers` (`[data-cy="reviewer-manager"]`) — `Add Reviewer` button; table columns `REVIEWER / REVIEWER STATUS / TYPE / ACTIONS / MORE ACTIONS`; rows: `Rex ReviewerOne` — `Request Accepted` + `Review due: 2026-09-25`, type `Anonymous Reviewer/Disclosed Author`; `Rita ReviewerTwo` — `Request Sent`, type `Anonymous Reviewer/Disclosed Author`
5. `Author Response` — subtext `Invite the author to respond to reviewer feedback before moving forward`; `Request Response` button; table `AUTHOR / RESPONSE STATUS` with row `Avery Author — Awaiting reviews`
6. `Review Tasks & Discussions` (`[data-cy="discussion-manager"]`) — subtext `Use this space to start discussions, assign tasks to others, or create your personal task list to help you move this submission to the next stage.`; `Add` button; groups `Yet to begin` / `In progress` / `Closed`, all `No Items`

**Action buttons** (`[data-cy="workflow-action-items"]`, exact labels in order): `Request Revisions`, `Accept Submission`, `Create New Review Round`, `Decline Submission`.

**Side panels** (`[data-cy="workflow-secondary-items"]`): one panel, `PARTICIPANTS` (`[data-cy="participant-manager"]`) with an `Assign` button and entries `Sole SectionEditor — Section editor`, `Avery Author — Author`. (Context: the Copyeditor assigned on the Copyediting stage does NOT appear in the Review Round's participants panel; she does appear in the Copyediting stage's panel — participant display is stage-scoped.)

**Side navigation** (tree, `role="treeitem"` links): `Submission`, `Review` → `Review Round 1`, `Copyediting`, `Production`, plus a `Publication` group (`Unassigned version (2026-07-31)`, `Title & Abstract`, `Contributors`, `Metadata`, `References`, `JATS XML`, `Body Text`, `Galleys`, `Media`, `Permissions & Disclosure`, `Publication Settings`). Header buttons: `Activity Log`, `Library`. (Context: `Create New Version` appears for admin/manager but not for the section editor.)

### As assigned Copyeditor `ce1u26`, same submission

Opening `workflowSubmissionId=3` lands on the `Review (Round 1)` view (per `[data-cy="sidemodal-header"]`). The primary column shows only the stage header `WORKFLOW: REVIEW (ROUND 1)` and the text:

> `You don't currently have access to that stage of the workflow.`

**No review panels render** (`[data-cy="workflow-primary-items"]` contains no headings; `reviewer-manager`, `discussion-manager`, `workflow-action-items`, `workflow-secondary-items`, `participant-manager` are all absent from the DOM). No decision buttons appear. Clicking the `Review Round 1` tree item gives the same result. The side nav still lists all stages (`Submission`, `Review`, `Review Round 1`, `Copyediting`, `Production`) and the `Publication` group header, plus `Library` and `Workflow`/`Publication` top buttons.

Contrast (context): on the `Copyediting` view the copyeditor DOES get panels — `WORKFLOW: COPYEDITING`, a `Status` panel reading `The Copyediting stage has not yet been initiated.`, and a `PARTICIPANTS` panel (Sole SectionEditor, Cora Copyeditor, Avery Author) with no `Assign` button and no action buttons.

Claim-vs-context: the claim recorded here is "assigned Copyeditor gets the no-access message instead of rendered review panels" — this DISAGREES with the probe brief's context expectation ("panels render and no decision buttons appear"). The copyediting-stage rendering is context.

---

## Item 2 — Round status box lifecycle (sub 4), editor vs author wording

Status box = the `Round 1 Status` panel (first heading inside `[data-cy="workflow-primary-items"]`; wording is the paragraph under it). Editor read as `se1u26` on `/dashboard/editorial`; author read as `au1u26` on `/dashboard/mySubmissions`, same submission, immediately after each step.

| Step | Editor wording (verbatim) | Author wording (verbatim) |
|---|---|---|
| A. Fresh round, no reviewers | `Waiting for reviewers to be assigned.` | `Waiting for reviewers to be assigned.` |
| B. One reviewer added (Remy, status `Request Sent`) | `Awaiting responses from reviewers.` | `Awaiting responses from reviewers.` |
| C. Reviewer submitted review | `New reviews have been submitted.` | `New reviews have been submitted.` |
| D. Editor confirmed the review | `All reviews are confirmed and a decision is needed.` | `All reviews are confirmed and a decision is needed.` |

At every step the author-side wording was character-identical to the editor-side wording (relevant to draft register entry A2: no author-tailored wording surfaced at any of the four states).

Step details, facts only:

- **A.** Editor action buttons at this state: `Request Revisions`, `Accept Submission`, `Create New Review Round`, `Cancel Review Round`, `Decline Submission` — note `Cancel Review Round` is present on the reviewerless round (absent on sub 3, which has an accepted reviewer).
- **B.** Reviewer added via `Add Reviewer` (button in the Reviewers panel) → list step (locator: `getByRole('button', {name: /Select Remy ReviewerThree/})`; visible label `Select Reviewer` + SR text `Select {name}`) → form step (defaults kept: `Review Request` template, review type preselected `Anonymous Reviewer/Anonymous Author`, `Important Dates` prefilled) → submit `Add Reviewer`. Reviewer row then shows `Remy ReviewerThree — Request Sent — Anonymous Reviewer/Anonymous Author`. `Cancel Review Round` still present at this state (reviewer not yet accepted).
- **C.** As `rev3u26` at `/index.php/u26p1jx/reviewer/submission/4`: 4-step rail `1. Request / 2. Guidelines / 3. Download & Review / 4. Completion`. Accepting requires the checkbox `Yes, I agree to have my data collected and stored according to the privacy statement.` (skipping it shows `This field is required.`); buttons `Decline Review Request` (an `<a.cancelButton>`, not a button) and `Accept Review, Continue to Step #2`. Step 2: `Reviewer Guidelines` — `This publisher has not set any reviewer guidelines.` → `Continue to Step #3`. Step 3: two TinyMCE comment fields (`For author and editor`, `For editor`), `Recommendation` select with options `Choose One / Accept Submission / Revisions Required / Resubmit for Review / Resubmit Elsewhere / Decline Submission / See Comments` (helper text: `You must enter a review or upload a file before selecting a recommendation.`); `Submit Review` → confirm dialog `Are you sure you want to submit this review?` (OK/Cancel) → step 4 `Review Submitted` with thank-you text. Editor's reviewer row then reads `Review Submitted` + `Accept Submission`, row action `Read Review`; the Author Response panel row flips to `Ready To Invite Author — Editor can now request the author's response.`; editor action buttons lose `Cancel Review Round`.
- **D.** Editor `Read Review` modal (title `Review: Statusbox probe u26p1subb`): instruction text `Once this review has been read, press "Confirm" to indicate that the review process may proceed. …`; shows `Completed:` timestamp, `Recommendation: Accept Submission`, both comment blocks, `Reviewer Files`, an editable `Recommendation` select (`Set or adjust the reviewer recommendation.`), `Reviewer rating` (`Rate the quality of the review provided. This rating is not shared with the reviewer.`), buttons `Cancel` / `Confirm` (+ `Download Review Form`). After `Confirm`, the reviewer row reads `Complete` + `Accept Submission` with row actions `Thank Reviewer` and `Revert Decision`.

Context (author view generally): the author's workflow view contains, in order, `Round 1 Status`, `Revisions Uploaded` (with an `Upload` button), and `Review Tasks & Discussions` (with an `Add` button); no Reviewers, Files for Review, or Author Response panels; no `[data-cy="workflow-action-items"]`, so no decision buttons.

---

## Item 3 — Sole invited reviewer declines (sub 5)

1. As `se1u26`, invited `Rita ReviewerTwo` via the same Add Reviewer flow. Status box: `Awaiting responses from reviewers.`
2. As `rev2u26` at `/index.php/u26p1jx/reviewer/submission/5`: clicked `Decline Review Request` (locator: `getByText('Decline Review Request')` — element is `a.cancelButton`, NOT a `role=button`). A side modal opens titled `Decline Review Request` with text `You may provide the editor with any reasons why you are declining this review in the field below.`, a comment field, and a `Decline Review Request` button. After submitting, the browser lands on the journal's public reader homepage (no reviewer dashboard confirmation screen was shown).
3. As `se1u26`, sub 5 Round 1 Status (claim, verbatim):

   > `All reviews are confirmed and a decision is needed.`

   This matches the wording expected by draft register entry A4. Reviewer row: `Rita ReviewerTwo — Request Declined`. Author Response row: `Avery Author — Awaiting reviews`. Action buttons: `Request Revisions`, `Accept Submission`, `Create New Review Round`, `Decline Submission` (no `Cancel Review Round`).

4. Context: the author (`au1u26`) also sees `All reviews are confirmed and a decision is needed.` on sub 5 at this state (editor wording mirrored again, consistent with item 2).

---

## Incidental observations (context, not promotable)

- The workflow header renders a raw locale key `##common.help##` next to the user nav on every screen probed (editor, author, copyeditor, reviewer pages alike).
- Scratch-journal single-locale URL behavior confirmed: bare `/index.php/u26p1jx/...` URLs serve directly.
- The Add Reviewer list step also offers `Create New Reviewer` and `Enroll Existing User` at the bottom.
- Review-round seeding note for later specs: reviewer rows seeded `accepted` get `Request Accepted` + a `Review due:` date; UI-invited rows show `Request Sent` with no due date shown in the row.

No observation from this probe was routed to the private security file — nothing seen reached data or actions beyond the acting role's entitlement (the copyeditor was blocked from the review stage; the author box mirrors generic stage wording without reviewer identities).

Probe scripts (throwaway) live under the session scratchpad, not in any repo.
