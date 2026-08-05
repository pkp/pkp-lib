# U26 probe report — Group II (items 3–7)

Agent: probe, RUNBOOK step 3. Date: 2026-07-27. Fleets driven live at OJS
`http://127.0.0.1:8000` and OMP `http://127.0.0.1:8100` (the `localhost` host
name 400s "Server host not allowed" on both — the running servers answer on
`127.0.0.1` only; context, matches Group I).

All state was seeded through `POST /index.php/index/api/v1/_test/scenarios/{context,submission}`
with unique tags; no seeded account and no pre-existing seeded submission was
modified. Two scratch journals/presses were created for the reviewer-suggestion
setting check (that check needs a journal-level setting toggled, which
`publicknowledge` must not have). Every affordance claim below was observed on
the live screen by driving Chromium (Playwright `chromium` from
`ojs-main/node_modules`, real UI login per user, no storage-state cache).

Marker convention: **CLAIM** = observation the item asked for, promotable to the
spec; **context** = incidental observation noted in passing.

## Common locators

- workflow modal: `[data-cy="active-modal"]` (its wrapper reports
  `visibility: hidden`; wait on inner text length, not `toBeVisible`)
- selected-stage heading: `[data-cy="active-modal"] h2` (first)
- round status + panels: `[data-cy="workflow-primary-items"]` (headings `h3`)
- decision / recommendation buttons: `[data-cy="workflow-action-items"]`
- side column: `[data-cy="workflow-secondary-items"]` (headings `h2`/`h3`)
- editorial workflow URL: `/{context}/en/dashboard/editorial?workflowSubmissionId={id}`
- author workflow URL: `/{context}/en/dashboard/mySubmissions?workflowSubmissionId={id}`
- round selection: `getByRole('link', {name: /Review Round N/})` inside the modal

**Text casing note (applies to every title below).** Several headings are
CSS-uppercased (`text-transform`). `innerText` therefore reads
`WORKFLOW: REVIEW (ROUND 1)`, `PARTICIPANTS`, `RECOMMENDATION`, `REVIEWERS
SUGGESTED BY AUTHOR`. All titles quoted in this report are the DOM
`textContent` (title case) — that is what the spec should quote.

## Seeded state

OJS, context `publicknowledge` (submitter `author.alex`, section `ART`,
deciding editor recorded as `sectioneditor.ana`):

| Key | Sub | Decisions / reviewers |
|---|---|---|
| A | 19 | `sendExternalReview` — fresh round, no reviewers |
| B | 29 | `sendExternalReview`; round 1: `reviewer.julia` invited, `reviewer.paul` accepted |
| C | 30 | `sendExternalReview`, `decline` |
| D1 | 31 | `sendExternalReview`; round 1: `reviewer.julia` declined; + `sectioneditor.ravi` assigned recommend-only through the UI |
| D2 | 32 | `sendExternalReview`; round 1: `reviewer.paul` invited, later made overdue through the UI; + `sectioneditor.ravi` recommend-only |
| E1 | 33 | `sendExternalReview`, `requestRevisions` (stay-in-round) |
| E2 | 34 | `sendExternalReview`, `resubmit` |
| F | 35 | `sendExternalReview`, `resubmit`, `newExternalReviewRound`; round 1 `reviewer.julia` accepted, round 2 `reviewer.paul` invited |

OMP, context `publicknowledge` (series `monographs`, entry decision
`skipInternalReview`): same seven keys — A 24, B 25, C 26, D1 27, D2 28, E1 29,
E2 30, F 31.

Scratch contexts for the reviewer-suggestion gate: OJS journal
`u26g2ojs0iu` (submissions 48, 52), OMP press `u26g2ompwby` (submission 43),
each with a throwaway section/series editor and author.

---

## Item 3 — Panel roster, editor view (Rules 3, 11) — SETTLED

### What was driven

OJS + OMP external review, round selected, four viewpoints: assigned deciding
Section Editor (`sectioneditor.ana`), unassigned Journal Manager
(`manager.maya`), on both a reviewer-less round (A) and a round with reviewers
(B), plus the declined round (C).

### 3.1 CLAIM — editorial panel roster and exact titles

Locator: `[data-cy="workflow-primary-items"] h3` (in DOM order) and
`[data-cy="workflow-secondary-items"] h3`.

**OJS** (identical for `sectioneditor.ana` and `manager.maya`, on A, B and C):

```
Round 1 Status
Revisions Uploaded
Files for Review
Reviewers
Author Response
Review Tasks & Discussions
```
side column: `Participants`

**OMP** (identical for both roles, on A, B and C):

```
Round 1 Status
Revisions Uploaded
Files for Review
Reviewers
Review Tasks & Discussions
```
side column: `Participants`

**The "Author Response" panel is OJS-only.** OMP's editorial round has no
author-response request panel at all. (Cross-check in source: only
`workflowConfigEditorialOJS.js` pushes `AuthorResponseRequestManager`; OMP's
editorial config has no such entry.) Spec Rule 3 currently lists the author
response request panel unmarked, i.e. as shared behavior.

Panel sub-labels observed (verbatim, useful for U36/U30 label accuracy):

- Revisions Uploaded — "These files have been submitted by the author after
  revisions were requested", button `Upload`
- Files for Review — "These files will be sent to the reviewers to review",
  button `Upload/Select Files`
- Reviewers — button `Add Reviewer`; column headers `Reviewer`, `Reviewer
  Status`, `Type`, `Actions`
- Author Response (OJS) — "Invite the author to respond to reviewer feedback
  before moving forward", button `Request Response`; columns `Author`,
  `Response Status`
- Review Tasks & Discussions — "Use this space to start discussions, assign
  tasks to others, or create your personal task list to help you move this
  submission to the next stage.", button `Add`; groups `Yet to begin`,
  `In progress`, `Closed`

### 3.2 CLAIM — page title

Locator: `[data-cy="active-modal"] h2`. OJS: `Workflow: Review (Round 1)`;
OMP: `Workflow: External Review (Round 1)`. The submission header above the
column additionally shows the bare form `Review (Round 1)` /
`External Review (Round 1)`, and the side menu entry reads `Review Round 1`.
Rule 2's "titles the page 'Review (Round N)'" is true of the header, not of the
column heading.

### 3.3 CLAIM — "Recommendation" box is absent when nothing is recorded

On A, B, C and on D1 *before* a recommendation existed, the side column
contained `Participants` only, for both the assigned Section Editor and the
unassigned Journal Manager, on both apps. (Presence once a recommendation
exists: item 5.)

### 3.4 CLAIM — Reviewer suggestions panel needs the setting **and** a suggestion

The setting's on-screen home (recorded for the spec's Settings bullet): journal
Settings → **Workflow** → tab **Review** → section **"Reviewer Suggestion at
Submission"**, checkbox **"Allow authors to suggest potential reviewers at
submission process"** (`/{context}/en/management/settings/workflow`, tab
`Review`; save PUTs `api/v1/contexts/{id}`, field `reviewerSuggestionEnabled`).

Three states driven on scratch journal `u26g2ojs0iu` (and repeated on scratch
press `u26g2ompwby`), each read from `[data-cy="workflow-secondary-items"]`:

| Setting | Submission has suggestions | Side column observed |
|---|---|---|
| off | no | `Participants` |
| **on** | **no** (submission 48 / 42) | `Participants` — **no suggestions panel** |
| on | yes (submission 52 / 43) | `Participants`, `Reviewers Suggested by Author` |
| off (toggled back) | yes (submission 52) | `Participants` |

So the panel's title is **"Reviewers Suggested by Author"**, it sits *after*
Participants, and it appears only when the journal setting is enabled AND the
submission carries at least one author suggestion — enabling the setting alone
produces nothing. (Component-side confirmation: `ReviewerSuggestionManager.vue`
renders under `v-if="…reviewerSuggestionsList.length > 0"`.) Spec Rule 3 and
the Settings bullet currently say only "when the journal has reviewer
suggestions enabled".

The suggestion state was created the way the wizard does it: submission seeded
with `submitted: false`, then as the author `POST
/api/v1/submissions/{id}/reviewers/suggestions` + `PUT
/api/v1/submissions/{id}/submit`, then the send-to-review decision recorded
through the decision wizard. Panel content observed: initials avatar, `Sara
Suggested`, `Probe University`, `Domain expert`.

### 3.5 context — deciding-editor parity

The unassigned Journal Manager's screen is byte-identical to the assigned
Section Editor's for panels, status and decision buttons; the only difference
observed anywhere is an extra `Create New Version` entry in the Publication
side menu (U33's territory).

---

## Item 4 — Decision button matrix (Rule 10, scenarios 1–2, 10) — SETTLED

### 4.1 CLAIM — fresh round (no reviewers)

Locator: `[data-cy="workflow-action-items"]` button texts, in DOM order.
OJS submission 19 as `sectioneditor.ana`; OMP submission 24 as `manager.maya`
(and 24 as `sectioneditor.ana`): identical on both apps —

```
Request Revisions
Accept Submission
Create New Review Round
Cancel Review Round
Decline Submission
```

All five of Rule 10's active-round buttons appear, in that order.

### 4.2 CLAIM — declined round

OJS submission 30, OMP submission 26, status line "Submission declined.":

| Viewer | Buttons observed |
|---|---|
| `sectioneditor.ana` (assigned, deciding) | `Revert Decline` |
| `manager.maya` (Journal Manager, unassigned) | `Revert Decline`, `Delete` |

Identical on OJS and OMP. Exclusivity holds: the Section Editor is never
offered `Delete`; the button's label is exactly `Delete` (not "Delete
Submission"). The author's view of the declined round (submission 30 as
`author.alex`) shows no action buttons at all.

### 4.3 context — Cancel Review Round drops out once a reviewer confirms

On B (one reviewer accepted) and on D1 (the only reviewer *declined* the
invitation) the action list was `Request Revisions`, `Accept Submission`,
`Create New Review Round`, `Decline Submission` — `Cancel Review Round` absent.
On D2 (reviewer invited, never answered) it was present. So a *declined*
invitation already withdraws the cancel affordance, not only an accepted one.
Rule 10's parenthetical and footnote e say "accepted or completed". Item 11
(Group IV) owns Rule 15; recorded here as context because it changes what
Rule 10's Cancel bullet should say.

---

## Item 5 — Recommend-only view & display exclusivity (Rules 8, 11; scenario 8) — SETTLED

### What was driven

`sectioneditor.ravi` was assigned to the round through the UI as a
recommend-only participant: side column `Participants` → `Assign` → legacy
"Assign Participant" modal → user group `Section editor` (OMP: `Series
editor`) → search `Ravi` → radio → checkbox `input[name="recommendOnly"]`
(label "Recommend only") → `OK`. The Participants list then shows the row with
the sub-label **"Only allowed to recommend an editorial decision"** (CLAIM —
that is the participant-list wording for the flag).

Recommendation recorded by driving the real wizard: button `Recommend Accept`
navigates to `/{context}/en/decision/record/{id}?decision=9&…`, page title
`Recommend Accept`, single step `Notify Editors`, submit button `Record
Decision` (the template mask must clear first).

### 5.1 CLAIM — recommend-only participant sees recommendation controls, no decision buttons

`[data-cy="workflow-action-items"]` as `sectioneditor.ravi`, before recording,
on OJS 31 and OMP 27:

```
Recommend Revisions
Recommend Accept
Recommend Decline
```

No `Request Revisions` / `Accept Submission` / `Create New Review Round` /
`Cancel Review Round` / `Decline Submission`. Same three labels on both apps.

### 5.2 CLAIM — after recording, the recommender's own block restates it

Action area as `sectioneditor.ravi`, after recording Recommend Accept (OJS 31,
OMP 27) — verbatim innerText:

```
RECOMMENDATION

Accept Submission

Change decision
```

i.e. a block headed `Recommendation` holding the recorded recommendation
(`Accept Submission`) and one button labelled `Change decision`. The three
Recommend buttons are replaced. Note for Rule 11: the word "Recommendation"
appears in *both* views — what is exclusive to the deciding editor is the
**side-column listing box**, not the word.

### 5.3 CLAIM — the side-column "Recommendation" listing is deciding-editor only

Side column (`[data-cy="workflow-secondary-items"]`) after the recommendation,
OJS 31:

| Viewer | Side column |
|---|---|
| `manager.maya` (JM, unassigned — deciding) | `Recommendation` (h2), `Participants` (h3) |
| `sectioneditor.ana` (assigned, deciding) | `Recommendation`, `Participants` |
| `sectioneditor.omar` (assigned, deciding) | `Recommendation`, `Participants` |
| `sectioneditor.ravi` (recommend-only) | `Participants` only |
| `author.alex` (Author) | no side column at all |

Box content: heading `Recommendation`, body `Accept Submission`. Identical on
OMP 27 (`manager.maya` sees the box, `sectioneditor.ravi` does not).

### 5.4 CLAIM — round-status walk with a recommend-only participant

Status line (`h3 "Round 1 Status"` + following text), OJS 31 / OMP 27, where
the round's only reviewer had *declined* the invitation:

1. recommend-only editor assigned, nothing recommended →
   **"Awaiting recommendations from editors."**
2. recommendation recorded (the only recommender) →
   **"All recommendations are in and a decision is needed."**

Identical text on OJS and OMP, and identical for every role that opens the
round (checked for the deciding editor, the recommend-only editor and the
Author on OJS 31 — the Author sees the same editor phrasing; A2's symptom,
Group III's item 8 owns it).

### 5.5 CLAIM — A3's mask reproduces

OJS 32 / OMP 28: one reviewer (`reviewer.paul`) still un-responded, response due
date moved into the past through the UI (Reviewers row menu → `Edit` → "Edit
Review" modal → Response Due Date / Review Due Date), which flipped the status
to **"A review is overdue."** and the reviewer row to `Overdue` / `Response
due: 2026-07-01`.

- With the recommend-only editor assigned but not yet recommending, the status
  stayed **"A review is overdue."** (so `PENDING_RECOMMENDATIONS` does *not*
  outrank overdue — matches Rule 7's ordering).
- The moment the single recommendation was recorded, the status became
  **"All recommendations are in and a decision is needed."** while the reviewer
  row still reads `Overdue`.

So A3 is confirmed live on both apps: recommendation-completeness masks an
overdue review in the status line. The overdue fact remains visible in the
Reviewers panel row — that mitigation is worth adding to A3's entry.

---

## Item 6 — Author upload window & A1 (Rule 12, scenarios 3–4, footnote c) — SETTLED

### What was driven

Author (`author.alex`) on the author workflow URL. Upload driven through the
real wizard: action button `Upload revisions` → dialog `Upload Review File`
(steps `1. Upload File`, `2. Review Details`, `3. Confirm`) → component select
(OJS "Article Component*" → `Article Text`; OMP "Submission Component*" →
`Appendix`) → `input[type=file]` → `Continue`, `Continue`, `Complete`.

### 6.1 CLAIM — stay-in-round flow (scenario 3)

OJS 33 / OMP 29:

| Moment | Status line | Action area |
|---|---|---|
| after Request Revisions (stay-in-round) | `Revisions have been requested.` | `Upload revisions` |
| after the author's first upload | `Revisions have been submitted and a decision is needed.` | `Upload revisions` (still there) |

The uploaded file is listed in `Revisions Uploaded` (row `revision.pdf`,
today's date, type `Article Text` / `Appendix`). Identical on both apps.

### 6.2 CLAIM — resubmit flow and A1 (scenario 4)

OJS 34 / OMP 30:

| Moment | Status line | Action area |
|---|---|---|
| after the resubmit variant | `Revisions requested from the author to be taken to a new review round.` | `Upload revisions` |
| after the author's first upload | `Revisions submitted. A new review round needs to be created.` | **empty** |

A1 reproduces live and identically in OJS and OMP: the action-area
`Upload revisions` button disappears after the first upload of a resubmit
round.

### 6.3 CLAIM — footnote c: the "Revisions Uploaded" panel's own Upload button

The panel's `Upload` button (locator:
`[data-cy="workflow-primary-items"] >> getByRole('button', {name: 'Upload', exact: true})`)
is **rendered and enabled for the Author in every state driven** — including
states outside Rule 12's window and on a past round:

| Author state | panel `Upload` present/enabled | Clicking it |
|---|---|---|
| fresh round, "Waiting for reviewers to be assigned." (OJS 19 / OMP 24) | yes / yes | dialog `Upload Review File` opens showing only **"You are not allowed to add and edit these files."** — no file input, no wizard |
| past round 1 of a two-round submission (OJS 35) | yes / yes | same refusal text |
| resubmit round after the first upload, i.e. A1's state (OJS 34 / OMP 30) | yes / yes | full wizard opens (with an extra "If you are uploading a revision of an existing file, please indicate which file." selector); upload **completed successfully** — a second file now sits in `Revisions Uploaded` |

Two findings fall out of this:

- **The author's panel Upload button is offered at every status but works only
  inside the server's requested-revision window.** Screen offers → server
  refuses, with a raw API error string as the whole modal body. (Server side:
  `SubmissionFileAccessPolicy` author branch 3b,
  `SubmissionFileRequestedRevisionRequiredPolicy`; message
  `api.submissionFiles.403.unauthorizedFileStageIdWrite`.) Direction is
  screen-offers-more, i.e. ordinary campaign output.
- **Footnote c's question is answered: yes, the panel's Upload is a real second
  path**, and in A1's state it is the *only* one left. A1's user impact is
  therefore "the obvious button vanishes; an unlabelled panel button still
  works", not "a second file cannot be added".

### 6.4 CLAIM — statuses that show the action-area button

Observed presence of the action-area `Upload revisions` button for the Author,
both apps:

| Round status | `Upload revisions` |
|---|---|
| Waiting for reviewers to be assigned. | absent |
| Awaiting responses from reviewers. | absent |
| Revisions have been requested. | **present** |
| Revisions have been submitted and a decision is needed. | **present** |
| Revisions requested from the author to be taken to a new review round. | **present** |
| Revisions submitted. A new review round needs to be created. | absent (A1) |
| Submission declined. | absent |
| past round ("The submission has been advanced…") | absent |

### 6.5 context — author panel roster

The Author's round shows `Round N Status`, `Revisions Uploaded`, `Review Tasks
& Discussions` and no side column. An extra `Author Response` block (h4)
appeared on OJS 33 only (the stay-in-round revision request); OMP never shows
it. No `Notifications` panel appeared on any of these submissions (all decision
mail was seeded under `Mail::fake()`); Group III item 9 owns that panel.

---

## Item 7 — Past-round read-only (Rule 5, scenario 5) — SETTLED

### What was driven

OJS 35 / OMP 31, two rounds (round 1: `reviewer.julia` accepted; round 2:
`reviewer.paul` invited), viewed as the deciding editor (`sectioneditor.ana`)
and as the Author (`author.alex`); `Review Round 1` selected from the side menu.

### 7.1 CLAIM — round 2 is preselected

On opening, the heading reads `Workflow: Review (Round 2)` /
`Workflow: External Review (Round 2)` and the status block is `Round 2 Status`
— the latest round is preselected (Rule 2).

### 7.2 CLAIM — no decision buttons on the earlier round

With `Review Round 1` selected, `[data-cy="workflow-action-items"]` is empty
(`innerText` === "") for the deciding editor, on both apps. For the Author it
is empty in both rounds.

### 7.3 CLAIM — the past round's status heading and text

The heading is **`Status`**, not `Round 1 Status`, and the text is

```
The submission has been advanced to the next round of review
```

(no trailing period; verbatim). Same on OJS and OMP, same for editor and
Author. Rule 5 quotes the text correctly; Rule 6's "shown under the heading
'Round N Status'" does not hold for past rounds.

### 7.4 CLAIM — panels are scoped to the selected round

On round 1 the Reviewers panel lists `Julia Reviewer` / `Request Accepted` /
`Review due: 2026-08-24` / `Anonymous Reviewer/Anonymous Author` — round 2's
`Paul Reviewer` is absent; switching back shows the reverse. Panel roster is
otherwise unchanged from the current round (editor: Status, Revisions Uploaded,
Files for Review, Reviewers, [Author Response on OJS], Review Tasks &
Discussions, side column Participants; author: Status, Revisions Uploaded,
Review Tasks & Discussions).

### 7.5 CLAIM — "no action buttons at all" is true only of the decision area

The past round still offers every *panel-level* control: `Upload`
(Revisions Uploaded), `Upload/Select Files` (Files for Review), `Add Reviewer`,
`Request Response` (OJS), `Add` (Discussions), and the reviewer row menu. The
Author likewise still sees the panel `Upload` (see 6.3 — it is refused by the
server on a past round). Rule 5's "no action buttons appear at all" should be
narrowed to the round-decision area.

---

## Proposed spec / register changes (for the orchestrator — not written by me)

1. **Rule 3** — mark the author response request panel `{OJS}`; OMP's editorial
   round has no such panel (item 3.1).
2. **Rule 3 + Settings bullet** — the Reviewer suggestions panel is titled
   **"Reviewers Suggested by Author"** and needs the journal setting *and* at
   least one author suggestion on the submission; the setting lives at
   Settings → Workflow → Review → "Reviewer Suggestion at Submission" /
   "Allow authors to suggest potential reviewers at submission process"
   (item 3.4).
3. **Rule 2 / Rule 6** — the column heading is "Workflow: Review (Round N)"
   (OMP "Workflow: External Review (Round N)"); the bare "Review (Round N)"
   is the submission header. On a *past* round the status heading is plain
   "Status" (items 3.2, 7.3).
4. **Rule 5** — narrow "no action buttons appear at all" to the round-decision
   area; panel controls (Upload, Upload/Select Files, Add Reviewer, Request
   Response, Add) remain on past rounds (item 7.5).
5. **Rule 10 / footnote e** — "Cancel Review Round" also disappears once a
   reviewer has *declined* the invitation, not only accepted/completed
   (item 4.3; Group IV item 11 should confirm).
6. **Rule 11** — the exclusive surface is the side-column "Recommendation"
   *listing*; the recommend-only participant sees their own block also headed
   "Recommendation", plus a "Change decision" button (item 5.2).
7. **Rule 12 / footnote c / A1** — new finding candidate: the Author's
   "Revisions Uploaded" panel offers an enabled "Upload" button at every round
   status and on past rounds, but the server refuses it outside the
   requested-revision window, answering with the raw string "You are not
   allowed to add and edit these files." as the entire modal body. A1's impact
   line should be corrected: the panel path still works in the A1 state
   (item 6.3).
8. **A3** — confirmed live on both apps; add that the Reviewers panel still
   shows the reviewer as `Overdue` while the status line hides it (item 5.5).
9. **Rule 8** — the Author does read the editor phrasing (observed on OJS 31);
   corroborates A2 for Group III item 8.
