# U26 probe P7 — Review stage & rounds: cross-app controls (items 16–17)

Probe date: 2026-07-31. OMP fleet at `http://127.0.0.1:8100` (repo `../omp-main`), OPS fleet at `http://127.0.0.1:8200` (repo `../ops-main`). Playwright (chromium, headless), all screens driven as signed-in users. Workflow screens render inside `[data-cy="active-modal"]`; every observation below is scoped to that dialog unless noted. Text quotes are verbatim `innerText`; headings/buttons read via `getByRole('heading')` / `getByRole('button')`.

## Seeded state

**OMP** — scratch press `u26p7omp` (contextId 56) via `POST /api/v1/_test/scenarios/context` with throwaway users `u26p7editor` (roles `["editor"]` = Press editor), `u26p7author` (author), `u26p7rev` (externalReviewer). Submission 2 "Monograph u26p7sub external review probe" via `POST /api/v1/_test/scenarios/submission` with `decisions: ["sendExternalReview"]` → stage 3 (External Review), round 1, zero reviewers.

Everything after seeding was screen-driven as the named users:

1. `u26p7editor` assigned themselves as **Press editor** participant via the round's PARTICIPANTS "Assign" button (Assign Participant modal: role `<select>` offers `Press editor / Series editor / Funding coordinator / Author / Volume editor / Translator`; user grid radio; predefined message "Assign Editor"; Cancel/OK). All editor-side observations below are therefore as an *assigned* editor.
2. Editor added `u26p7rev` via "Add Reviewer" (step 1 list → button accessible name `Select u26p7rev`; step 2 form "Review Type" radios: `Anonymous Reviewer/Anonymous Author` / `Anonymous Reviewer/Disclosed Author` / `Open`, plus a "Public Visibility" checkbox `Publicly Show Reviewer Comments`) — **Open** selected, submitted with the second `Add Reviewer` button.
3. `u26p7rev` accepted and completed the review at `/index.php/u26p7omp/reviewer/submission/2` — steps `1. Request / 2. Guidelines / 3. Download & Review / 4. Completion`; step 3 filled "For author and editor" and "For editor only" (TinyMCE), uploaded TWO attachments `p7-attach-A.txt` and `p7-attach-B.txt` into the "Reviewer Files" grid ("Upload File" link → legacy upload wizard `Continue`/`Continue`/`Complete`), pressed `Submit Review`, confirmed ("Confirm — Are you sure you want to submit this review? OK/Cancel"). **Observation (context, reviewer's-review feature): OMP's step 3 has NO Recommendation dropdown** — the form is Review Files / two comment fields / Upload / discussions / `Go Back · Save for Later · Submit Review` only.
4. Editor opened the reviewer row's `Read Review`, pressed `Confirm` (editor read-review modal, context: title "Review: Monograph u26p7sub external review probe", reviewer name, `Download Review Form`, "Once this review has been read, press "Confirm" to indicate that the review process may proceed…", `Completed: 2026-07-31 03:13 PM`, "Reviewer Comments" with both comment fields, "Reviewer Files" grid listing both attachments with row extras `More Information / Edit / Delete`, "Reviewer rating … This rating is not shared with the reviewer.", `Cancel / Confirm`).
5. Editor recorded **Request Revisions**: entry modal "Request Revisions — Require New Review Round" with radios "Revisions will not be subject to a new round of peer reviews." (kept) / "Revisions will be subject to a new round of peer reviews." + `Next`; wizard at `/index.php/u26p7omp/decision/record/2?decision=4&reviewRoundId=2`, steps `1 Notify Authors / 2 Notify Reviewers`; in Notify Authors, `Attach Files` → panel offering `Upload File / Review Files ("Attach files that were uploaded by reviewers", button "Attach Review Files") / Submission Files / Library Files`; via "Attach Review Files" (list shows `u26p7rev — p7-attach-B.txt` and `u26p7rev — p7-attach-A.txt`, checkboxes, `Back / Attach Selected`) exactly ONE attachment (`p7-attach-A.txt`) was attached ("Attached Files: p7-attach-A.txt"); `Continue` → Notify Reviewers (template "Notify Reviewers of Decision") → `Record Decision` → confirmation "Revisions Requested — Revisions for the submission, Monograph u26p7sub external review probe, have been requested. All notifications have been sent, except any you chose to skip." + `View Submission Summary`.

**OPS** — scratch server `u26p7ops` (contextId 73) with `u26p7opsmgr` (manager), `u26p7opsau` (author); preprint 2 "Preprint u26p7pre workflow probe" via `POST /api/v1/_test/scenarios/submission` with `submitted: true` → response `stageId: 5` (Production), `reviewRounds: []`.

Mailpit untouched.

---

## Item 16 — OMP External Review stage: the four OJS probe shapes

### (a) Assigned editor opens the Review Round

As `u26p7editor` at `/index.php/u26p7omp/dashboard/editorial?workflowSubmissionId=2`. [claim]

Submission header: title, status badge `External Review (Round 1)`; header buttons `Activity Log`, `Library`, and a `Monograph` dropdown button (OMP-specific; OJS per probe-P1 has no such button). Stage header of the primary column: `WORKFLOW: EXTERNAL REVIEW (ROUND 1)`, then `Current Submission Language: English`.

**Primary panel headings, in order** (fresh round, no reviewers):

1. `Round 1 Status` — see (b)
2. `Revisions Uploaded` — subtext `These files have been submitted by the author after revisions were requested`; `Upload` button; empty table `No Items`, columns `NO / FILE NAME / DATE UPLOADED / TYPE / MORE ACTIONS`
3. `Files for Review` — subtext `These files will be sent to the reviewers to review`; `Upload/Select Files` button; empty table (the scenario-seeded monograph carries no round files — parity with the harness note that review files are grant-based)
4. `Reviewers` — `Add Reviewer` button; columns `REVIEWER / REVIEWER STATUS / TYPE / ACTIONS / MORE ACTIONS`
5. `Review Tasks & Discussions` — subtext `Use this space to start discussions, assign tasks to others, or create your personal task list to help you move this submission to the next stage.`; `Add` button; groups `Yet to begin / In progress / Closed`

**Parity/divergence vs the OJS record (probe-P1 item 1):** headings 1–4 and their subtexts match OJS verbatim (OJS stage header reads `WORKFLOW: REVIEW (ROUND 1)`; OMP says `EXTERNAL REVIEW`). **Divergence: OJS renders an `Author Response` panel between Reviewers and Review Tasks & Discussions ("Invite the author to respond to reviewer feedback before moving forward", `Request Response` button); OMP renders NO such panel** — see the addendum below.

**Action buttons** (right rail; exact labels, order): `Request Revisions`, `Accept Submission` (the filled/highlighted one), `Create New Review Round`, `Cancel Review Round`, `Decline Submission`. [claim] (Probe-P1's OJS list for a round that already had an accepted reviewer lacked `Cancel Review Round`; on this fresh OMP round it IS present, and it disappeared from the list after the review was completed — post-completion the rail read `Request Revisions / Accept Submission / Create New Review Round / Decline Submission` with no button and no explanatory text in its place. The button vanishes rather than rendering disabled with the restriction message.) [claim]

**Side panels**: one — `PARTICIPANTS` with `Assign` button; entries after step 1: `u26p7editor — Press editor`, `u26p7author — Author`. [claim]

**Stage menu** (left nav, top-level group `Workflow`): `Submission`, `Internal Review`, `External Review` (expanded: `Review Round 1`), `Copyediting`, `Production`; then OMP-specific group `Marketing` (`Audience / Representatives / Publication Dates`) and `Publication` group (`Unassigned version (2026-07-31) / Title & Abstract / Contributors / Chapters / Metadata / Publication Formats / Media / References / Catalog Entry / Permissions & Disclosure / Create New Version`). **The `Internal Review` entry exists** in both the editor's and the author's menus (existence recorded per the brief; the internal stage itself not probed). [claim: existence]

Reviewer-row status strings observed across the run (Reviewers panel): `Request Sent` (type `Open`) → after submission `Review Submitted` with action `Read Review` → after editor Confirm `Complete` with actions `Thank Reviewer / Revert Decision`. [context: reviewer-management feature, recorded for the status-line chain]

### (b) Status box, fresh round with no reviewers

Panel `Round 1 Status`, body (verbatim): `Waiting for reviewers to be assigned.` [claim — matches Rule 5 row 1 and the OJS wording]

Later states observed in the same box (editor view): after the review was submitted — `New reviews have been submitted.`; after the editor's Confirm in Read Review — `All reviews are confirmed and a decision is needed.`; after the Request Revisions decision — `Revisions have been requested.` (read from the author view; the box is the same panel). All verbatim matches to the spec's Rule 5 sentences. [claim]

### (c) Author presses "Read Review" (completed OPEN review, two attachments, one shared)

As `u26p7author` at `/index.php/u26p7omp/dashboard/mySubmissions?workflowSubmissionId=2`, Reviewers panel row `u26p7rev / Open / Read Review` (author-view columns: `REVIEWER / TYPE / ACTIONS` — no status column, no Add button). `Read Review` opens a window: [claim]

- Title: `Review: Monograph u26p7sub external review probe`
- Reviewer name `u26p7rev`, `Completed: 2026-07-31 03:13 PM`
- **No recommendation line renders anywhere in the window** (OMP's reviewer form has no recommendation field — see Seeded state step 3; the OJS window per the spec shows one). [claim]
- No `Download Review Form` button (the editor's window has one). [context]
- `Reviewer Comments` → `For author and editor` → the shared comment text only. The "For editor only" remark does NOT appear. [claim]
- A `Reviewer Files` grid renders (columns `Name / Date / Component`, rows downloadable). **Which attachments the grid lists — the point of this sub-item — is routed to the maintainer's private security file per the Frame; this report intentionally does not record the listing.** [claim — routed]

### (d) After the decision: author's "Notifications" list and the letter

Author view, same screen, panel order: `Round 1 Status` (`Revisions have been requested.`), `Notifications`, `Reviewers`, `Revisions Uploaded` (with `Upload` button), `Review Tasks & Discussions`; single action button bottom-right `Upload revisions`. (Divergence vs OJS author view is for the digest; OMP order puts `Notifications` directly under the status box.) [claim]

**Notifications list**: heading `Notifications`; one row = subject + timestamp on the next line:
- `Your submission has been reviewed and we encourage you to submit revisions`
- `2026-07-31 03:18 PM`

Clicking the row opens a read-only side panel: heading `Notifications`, then the subject line, the timestamp, and the full letter with no edit or reply controls. Letter body (abridged, verbatim passages): `Dear u26p7author,` … `Your submission "Monograph u26p7sub external review probe" has been reviewed and we would like to encourage you to submit revisions that address the reviewers' comments.` … `You can access the reviews and submit your response below` / `Submit Author Response` (renders as a line in the letter) … `Kind regards,` / `u26p7editor` — then an appendix: `The following comments were received from reviewers.` / `u26p7rev` / `Recommendation:` (label prints with an EMPTY value — OMP collected no recommendation) / the shared "for author and editor" comment text. The editor-only comment is not in the letter. The attached file itself is not rendered in the panel. [claim; the empty `Recommendation:` label is an observation for the digest]

### Addendum — author-response surfaces on OMP (observation forwarded to the *Author response to reviews* feature)

- **Editor view, review stage: NO author-response request panel renders anywhere** — neither on the fresh round nor after the completed review nor after the Request Revisions decision (heading list identical all three times: `Round 1 Status / Revisions Uploaded / Files for Review / Reviewers / Review Tasks & Discussions` + `PARTICIPANTS`). OJS's `Author Response` panel with `Request Response` (probe-P1 item 1) has no OMP counterpart on screen.
- **Author view: no response panel either**, although the decision letter the author receives says `You can access the reviews and submit your response below / Submit Author Response`.
- **No on-screen error renders in either view.** Browser console listeners (console `error`+`warning` and `pageerror`) captured **zero** entries on the editor and author workflow loads. The suspected component-registration gap produces a silent absence, not a visible error.

### Other on-screen observation (context, not this feature)

The top toolbar of every OMP and OPS page served from the scratch contexts shows a raw locale key `##common.help##` (the help control next to Tasks / user menu). Recorded as an install fact; not review-stage-specific.

---

## Item 17 — OPS: no Review entry on a preprint's workflow (install fact)

As `u26p7opsmgr` (server manager) at `/index.php/u26p7ops/dashboard/editorial?workflowSubmissionId=2`. What THIS install shows: [claim]

- Submission header: title, status badge `Production`, buttons `Preview`, `Activity Log`, `Library`.
- **Stage menu, group `Workflow`: exactly one entry — `Production`.** No `Review`, `Internal Review`, `External Review`, or `Submission` entry renders anywhere in the menu. The second group is `Preprint` (`Unassigned version (2026-07-31) / Title & Abstract / Contributors / Metadata / References / Galleys / Media / Permissions & Disclosure / Preprint entry / Create New Version`).
- No round, reviewer, or review-file surface appears anywhere on the screen (full-modal text dump contains none).
- **Positive control — the Production stage's own controls render**: stage header `WORKFLOW: PRODUCTION`, `Current Submission Language: English`, panel `Production Tasks & Discussions` (subtext and `Add` button as on OMP), action buttons `Post the preprint` (filled) and `Decline Submission`, side panel `PARTICIPANTS` with `Assign` and entry `u26p7opsau — Author`. Console: zero errors/warnings.

---

## Coverage

- Item 16 (OMP): DONE — (a) assigned-editor round view incl. exact panel/button/menu strings and the `Internal Review` entry; (b) fresh-round status wording plus three later transitions; (c) author Read Review window (attachment listing routed to the private security file per the Frame — routing hereby declared); (d) Notifications list + read-only letter; author-response addendum (no panel, no on-screen error, console clean).
- Item 17 (OPS): DONE — stage menu has no Review entry; Production controls render (positive control).

Probe scripts and screenshots live only in the session scratchpad (`p7-*.js`, `p7-*.png`); nothing was written into any repo tree except this report. Seeded scratch contexts `u26p7omp` / `u26p7ops` remain in the test DBs; `publicknowledge` and the seeded roster untouched on both fleets; Mailpit not cleared.
