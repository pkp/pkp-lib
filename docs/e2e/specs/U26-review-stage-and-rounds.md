---
name: review-stage-and-rounds
scope: Editors run numbered review rounds on a submission (round status, review files, revisions, round decisions); the author follows and responds on the same screen
apps: [ojs, omp]
shared: pkp-lib
status: draft
atlas-claims: [AFFW-323, AFFW-324, AFFW-325, AFFW-327, AFFW-328, AFFW-329, AFFW-330, AFFW-332, AFFW-333, AFFW-334, AFFW-335, AFFW-336, AFFW-337, AFFW-338, AFFW-339, AFFW-349, AFFW-350, AFFW-351, AFFW-352, AFFW-353, AFFW-355, AFFW-487, AFFW-666, AFFW-667, AFFW-701, AFFW-702, AFFW-703, AFFW-704, GRID-010, GRID-011, GRID-024, GRID-025, GRID-027, GRID-029, GRID-053, MAIL-047, NOTIF-029, NOTIF-031, SET-021]
---

# Review stage & rounds {OJS OMP}

> Conventions (markers, badges, footnotes): [Reading a spec](GLOSSARY.md#reading-a-spec).

## Purpose

Peer review happens in numbered rounds. When an editor sends a submission to
review, Round 1 opens: the editor chooses which files reviewers will see,
invites reviewers, watches a status line that tracks where the round stands,
and closes the round with a decision — accept, decline, ask for revisions, or
open another round. The author follows the same submission on the same workflow
screen in a reduced view: the letters the editor sent them, the reviews they
are allowed to read, and a place to upload revised files when revisions were
asked for. This spec covers the review stage screen and the round machinery —
what each role sees on it per round and state. Inviting and managing the
reviewers themselves, the decision-recording wizard, and the author's formal
response to reviews are neighboring features (see *Cross-feature
interactions*).

OPS does not install a review stage: a preprint server's workflow goes straight
from submission to Production, so no Review entry ever appears in a preprint's
workflow menu and none of the screens in this file exist there. <sup>p</sup>

On a press, everything in this file describes the External Review stage. A
press also offers a separate, earlier Internal Review stage [OMP1](#omp1); its
screens are documented separately. <sup>q</sup>

## Actors & permissions

"Assigned to the stage" means listed on the Participants panel for the review
stage. A **deciding editor** is a Journal Manager or Editor, or an assigned
Section Editor whose participation is not limited to recommendations; a
**recommending editor** is one whose participation is limited to
recommendations (the limitation is set when participants are managed — see
*Stage participants*). The Author reaches this screen only for their own
submission, through My Submissions. Typing the workflow address of a
submission outside these grants gets only an access error, with nothing of
that submission shown. <sup>a</sup>

| Action | Who may — and when |
|--------|--------------------|
| **Open the review stage** (rounds, status, panels) | • Site Administrator; Journal Manager; Editor — every submission in the journal<br>• Section Editor, Guest Editor — when assigned to the stage<br>• Funding Coordinator — the one assistant-level group the stage's assignment dialog offers for assignment here<br>• Production assistant roles (Copyeditor, Layout Editor, Proofreader) — no path in: the assignment dialog does not offer them, and one assigned on another stage is told they do not have access to this stage ⚠ [A5](#a5)<br>• Author — their own submission, in the author view (Rule 14) <sup>a</sup> |
| **See the round status line** | • Everyone who opens the stage — the same status box for every role (Rules 4–5) ⚠ [A2](#a2) |
| **Choose the files under review** | • Site Administrator; Journal Manager; Editor; assigned Section Editor and Guest Editor — the Files for Review panel's file selection (Rule 8) <sup>h</sup> |
| **Upload revised files** | • Author — the "Upload revisions" button, only while the round asks for or already holds revisions (Rule 9) ⚠ [A1](#a1)<br>• Editorial roles — through the Revisions Uploaded panel's own controls (to file revisions on the author's behalf) <sup>i</sup> |
| **Record a decision on the round** (buttons at the top of the screen) | • Deciding editors — on the current round only (Rules 10–11)<br>• Recommending editors — never the decision buttons; they get recommendation controls instead, and only while a deciding editor is also assigned to the stage (Rule 13; the recording flow is *Editorial decision recording*'s) <sup>e</sup> |
| **See the "Recommendation" box** | • Deciding editors — once a recommending editor has recorded a recommendation for the round (Rule 13) <sup>e</sup> |
| **Read a completed review** (author view) | • Author — only reviews conducted openly, and only once completed (Rule 15) <sup>j</sup> |
| **Open the editor's letters** ("Notifications" list) | • Author — the emails editors sent them about this submission (Rule 16) <sup>k</sup> |
| **Delete the submission** | • Journal Manager; Site Administrator — only while the submission stands declined (Rule 11) <sup>e</sup> |

## Fields & validation

N/A — the stage's own surfaces are panels, buttons and a status line. The
forms they open belong to their own features: the decision wizard to
*Editorial decision recording*, the file-upload wizard to *Submission files*,
reviewer forms to *Reviewer assignment & management*.

## Rules & state

<a id="rounds"></a>
1. Review happens in **rounds numbered from 1**. Every round the submission
   has been through stays visible; the highest-numbered round is the
   **current** round. Only the current round's status and decisions still
   move — but a past round's panels remain editable (Rule 10
   ⚠ [A8](#a8)). <sup>b</sup>
2. Round 1 is created the moment a submission enters the review stage (the
   "Send for Review" decision on the Submission stage — see *Submission
   stage*). Every later round is created only by the "Create New Review Round"
   button (Rule 12). Rounds are never renumbered. <sup>b</sup>
3. In the workflow menu, the stage entry "Review" expands into one entry per
   round, "Review Round 1", "Review Round 2", …. Opening a submission that is
   in review lands on the current round; the page title reads "Review (Round
   {N})". <sup>a</sup>
4. <a id="round-status"></a> **The round status box.** At the top of the
   stage, a box headed "Round {N} Status" carries one sentence describing
   where the current round stands (Rule 5). Selecting a **past** round — or
   viewing the stage after the submission has moved past it — the box is
   headed plain "Status" instead. A past round reads "The submission has been
   advanced to the next round of review". Once the submission has moved past
   review, the box reads "The submission is currently in the {stage} stage."
   (or, on a past round, "The submission advanced to the next review round,
   was accepted, and is currently in the {stage} stage."). Before the stage
   has started it reads "The {stage} stage has not yet been initiated." Both
   the editorial and the author view show this same box ⚠ [A2](#a2).
   <sup>c</sup> <sup>d</sup>
5. **The status sentences.** The status is recomputed from the round's actual
   state every time the screen loads:

   | The box reads | When |
   |---|---|
   | "Waiting for reviewers to be assigned." | No reviewer has been added to the round ⚠ [A4](#a4) |
   | "Awaiting responses from reviewers." | Invitations are out or reviews are underway; nothing overdue, nothing submitted-and-unread |
   | "A review is overdue." | A reviewer missed a response or review deadline |
   | "New reviews have been submitted." | A review arrived that no editor has confirmed yet |
   | "All reviews are confirmed and a decision is needed." | Every review is in and confirmed ⚠ [A4](#a4) |
   | "Revisions have been requested." | After a Request Revisions decision (same round), while no revised file has arrived |
   | "Revisions have been submitted and a decision is needed." | The author uploaded a revised file after Request Revisions |
   | "Revisions requested from the author to be taken to a new review round." | After a Request Revisions decision that requires a new round, while no revised file has arrived |
   | "Revisions submitted. A new review round needs to be created." | The author uploaded a revised file after that decision |
   | "Awaiting recommendations from editors." | Recommending editors are assigned and none has recorded a recommendation for the round |
   | "New editorial recommendations have been submitted." | At least one recommending editor has recorded one, others still pending |
   | "All recommendations are in and a decision is needed." | Every recommending editor has recorded one |
   | "Submission accepted." | After an Accept Submission decision on the round |
   | "Submission declined." | After a Decline Submission decision on the round |
   | "Returned back to review." | The submission was sent back from the Copyediting stage **and** every review of the round is completed and confirmed — until then the reviewer-derived sentences show instead (Rule 6) |

   <sup>c</sup> <sup>d</sup>
6. **Precedence.** When several sentences could apply, the round reports the
   most urgent reviewer fact first: an overdue review wins over an unread one,
   an unread one over still-running ones; reviewers who declined or were
   removed are not counted. The revision sentences (requested/submitted) and
   the closing sentences (accepted/declined) override the reviewer-derived
   ones outright. "Returned back to review." sits **below** the
   reviewer-derived sentences: after a submission is moved back from
   Copyediting, a round with no reviewers reads "Waiting for reviewers to be
   assigned." and one with reviews underway "Awaiting responses from
   reviewers."; the returned-back sentence can surface only once every review
   is completed and confirmed. "Awaiting recommendations from editors." appears only once
   the round has at least one reviewer record — a round with none reads
   "Waiting for reviewers to be assigned." even while recommendations are
   awaited (a declined reviewer is record enough). On a round whose only
   reviewer records are declines, the recommendation sentences take over from
   the confirmed-reviews fallback of ⚠ [A4](#a4): with a recommendation still
   pending such a round reads "Awaiting recommendations from editors."
   <sup>c</sup> <sup>e</sup>
7. The two "requested" sentences flip to their "submitted" partner as soon as
   a revised file newer than the decision exists, and flip back if that file
   is deleted — though the author's revisions task does not come back
   ⚠ [A9](#a9) (see *Side effects*). <sup>c</sup> <sup>m</sup>
8. <a id="review-files"></a> **Files for Review.** Each round carries its own
   set of files under review — what reviewers of that round are given — in
   the panel headed "Files for Review". The
   panel's selection dialog ("Current Review Files For Round {N}") lists the
   submission's workflow files with checkboxes: ticking a file adds it to the
   round, new files can be uploaded from the same dialog, and no file is ever
   deleted here. Confirming reports "Review files updated." Unticking a file,
   though, changes nothing the editor can see — the panel lists it exactly as
   before — and the dialog's checkboxes do not reliably mirror the panel
   ⚠ [A7](#a7). <sup>h</sup>
9. <a id="revisions"></a> **Revisions Uploaded.** Each round also carries the
   revised files uploaded in answer to a revision request, in the panel headed
   "Revisions Uploaded". The panel is present from the moment the round
   opens, and its description — "These files have been submitted by the
   author after revisions were requested" — shows even on a round where no
   revisions were ever requested ⚠ [A10](#a10). The author's bottom-of-screen "Upload
   revisions" button appears only while the round's status is "Revisions have
   been requested.", "Revisions requested from the author to be taken to a new
   review round.", or "Revisions have been submitted and a decision is
   needed." ⚠ [A1](#a1) — after the author's first upload in the
   new-round variant the round leaves this list and that button disappears,
   though the Revisions Uploaded panel's own "Upload" control still opens the
   same wizard. <sup>i</sup>
10. **Past rounds lose the decision buttons.** The decision buttons render
    only when the current round is selected; a past round shows the panels of
    that round (its reviewers, its files), and the panels keep their own
    controls (upload, add a reviewer, assign a participant). Those controls
    still act: a reviewer added or a file uploaded on a past round lands in
    that past round ⚠ [A8](#a8). <sup>a</sup>
    <sup>e</sup>
11. <a id="decisions"></a> **The decision buttons** (deciding editors, current
    round): "Request Revisions", "Accept Submission" (the highlighted one),
    "Create New Review Round", "Cancel Review Round", "Decline Submission".
    Three qualifications:
    - "Request Revisions" opens with the choice "Require New Review Round" —
      "Revisions will not be subject to a new round of peer reviews."
      (preselected) or "Revisions will be subject to a new round of peer
      reviews." — which selects between the two revision paths of Rule 5.
      The new-round choice continues under the wizard name "Resubmit for
      Review"; the wizard itself is *Editorial decision
      recording*'s. <sup>e</sup>
    - "Cancel Review Round" is offered only while no reviewer of the round
      has responded to the invitation — a decline forecloses cancelling just
      as an acceptance does — or completed a review; past that point the
      button is simply absent, with no explanation in its
      place. <sup>f</sup>
    - While the submission stands **declined**, all of the above are replaced
      by "Revert Decline", and a Journal Manager or Site Administrator
      additionally gets "Delete". <sup>e</sup>
12. **Creating and cancelling rounds.**
    - "Create New Review Round" opens round N+1: the wizard offers the
      previous round's revised files — already ticked — to carry over as the
      new round's review files; reviewers are *not* carried over, so the new
      round starts as "Waiting for reviewers to be assigned." and the
      previous round becomes a past round (Rule 10). <sup>g</sup>
    - "Cancel Review Round" removes the current round entirely — the round,
      its reviewer invitations and its file links disappear, and a withdrawn
      invitation vanishes from the reviewer's own assignment lists without a
      trace. The submission lands on the previous round — whose status box no
      longer recalls a revision request recorded on it ⚠ [A6](#a6) — or back
      on the Submission stage if Round 1 was cancelled. The files themselves
      stay in the submission's records. <sup>f</sup>
13. <a id="recommendations"></a> **Recommendations.** A recommending editor
    sees three recommendation buttons in place of the decision buttons —
    "Recommend Revisions", "Recommend Accept", "Recommend Decline" (the flow is
    *Editorial decision recording*'s; the limitation itself is set in *Stage
    participants*). The buttons require a deciding editor to also be assigned
    to the stage: a recommending editor who is the only editorial participant
    gets no buttons at all — the "Recommendation" box instead reads "You can
    not make a recommendation until an editor is assigned with permission to
    record a decision." On the deciding editor's screen, once a recommendation for
    the selected round exists, a box headed "Recommendation" lists the
    recommendation(s) recorded — e.g. "Accept Submission" — with no further
    detail. The round status walks the three recommendation sentences of
    Rule 5. <sup>e</sup>
14. <a id="author-view"></a> **The author's view** of the same screen, per
    round: the Revisions Uploaded panel with the upload button (Rule 9) and
    the stage's discussions, always; a "Notifications" list once an editor
    has sent the author a letter (Rule 16); and the list of reviews they may
    read, once such a review exists (Rule 15). A submission fresh in
    review therefore shows only the two ever-present panels — the other two
    appear with their preconditions. When the editor has asked for a formal response to
    the reviews, a response panel appears as well — that flow is *Author
    response to reviews*', and on a press the panel currently never appears
    even when a response was invited; the finding is recorded with that
    feature. <sup>a</sup>
15. <a id="author-read-review"></a> **Reading reviews as the author.** Only
    reviews conducted **openly** are ever listed for the author, and only once
    completed; until such a review exists the author's screen shows no
    reviewers list at all — not an empty one — and anonymous reviews never
    appear, whatever their state. Each listed review offers "Read Review",
    which opens a window with the reviewer's name, completion date and
    recommendation — on a press no recommendation exists to show
    ⚠ [OMP2](#omp2) —, the review text the author is meant to see (for form-based
    reviews, only the parts marked for authors; for free-text reviews, only
    remarks shared with the author) — though on a journal the window currently
    renders no review text at all ⚠ [OJS1](#ojs1) — and a section for files
    the reviewer attached ⚠ [A3](#a3). <sup>j</sup>
16. <a id="author-emails"></a> **The "Notifications" list** on the author's
    review stage holds the emails editors sent the author about this
    submission — each row a subject line and date; clicking one opens the
    full letter read-only in a side panel. This is where the author re-reads a
    decision letter. Until the first such letter the list is absent
    entirely — not shown empty. <sup>k</sup>
17. **Old addresses.** A bookmarked author link of the form used by earlier
    versions (`{journal}/authorDashboard/submission/{id}`) lands on My
    Submissions with the submission's workflow open; the old per-round tab
    address from those versions
    (`{journal}/authorDashboard/reviewRoundInfo/{id}`) answers with a bare
    "404 Not Found" page, with or without an id. <sup>o</sup>

## Side effects

- **Author uploads a revised file** → the round status flips per Rule 7, and
  an automatic email (subject "Revised Version Uploaded"), sent under the
  author's own name and address, goes to the Journal Managers, Editors and
  Section Editors assigned to the stage. All of this takes effect the moment
  the file is attached in the upload window's first step — closing the wizard
  without finishing does not undo the upload or recall the email. To avoid a
  flood, the same editor is not emailed again for further uploads within a
  day unless they have signed in since the last notice. <sup>l</sup>
- **Request Revisions decision** → each author assigned to the stage gets a
  task in their task list — the "Tasks" panel opened from the site header —
  naming the submission — on a journal it reads
  "Revision required.", on a press "Revisions to consider in External
  Review." ⚠ [OMP3](#omp3) — and their row on My Submissions reads "Revision
  requested" with a "Submit revisions" button in both apps (the list itself
  belongs to *My Submissions*). The task disappears when a revised file is
  uploaded, but does not return if the only revised file is deleted — the
  status and the My Submissions row revert to their requested state (Rule 7)
  while the task list stays silent ⚠ [A9](#a9). The new-round variant's task
  reads "Resubmit for review." in both apps and stays in the author's list
  even after they upload ⚠ [A1](#a1). The decision's own notifications and
  emails are *Editorial decision recording*'s. <sup>m</sup>
- **Round created** → an internal round-status notice record is created; it
  currently surfaces nowhere (footnote only). <sup>n</sup>
- **Round cancelled** → the round's reviewer invitations are withdrawn, and
  the authors — and the reviewers, when the round has any (the wizard offers
  the reviewer-notice step only then) — can be notified by email as part of
  the decision (the email steps are *Editorial decision
  recording*'s). <sup>f</sup>

## Settings that modify behavior

- **Minimum Confirmed Reviews Required** (the journal's review setup — see
  *Review setup & review forms*): when set, the round status box shows
  "Minimum number of confirmed reviews required: {N}." while reviews are being
  gathered. While confirmed reviews remain below that minimum, the line
  replaces the submitted- and confirmed-review sentences ("New reviews have
  been submitted.", "All reviews are confirmed and a decision is needed.") —
  but "Awaiting responses from reviewers." still appears beneath it while
  reviews are underway. <sup>r</sup>
- **Reviewer suggestions enabled** (publication settings): adds a reviewer
  suggestions panel to the stage — the panel is *Reviewer suggestions*'. <sup>a</sup>
- **Review type per assignment** (open / anonymous, chosen when the reviewer
  is assigned — *Reviewer assignment & management*): gates the author's
  access to each review (Rule 15).

## Cross-feature interactions

- **Reviewer assignment & management** — the Reviewers panel on this stage:
  adding, reminding, thanking, unassigning reviewers and the editor's reading
  of reviews. This spec owns only the author-side reading (Rule 15).
- **Editorial decision recording** — every button of Rule 11 and the
  recommendation controls open that feature's wizard; this spec owns the
  buttons' presence and what each decision does to the round.
- **Stage participants** — who is assigned to the stage, and the
  limited-to-recommendations setting behind Rule 13.
- **Author response to reviews** — the response request and the author's
  response panel that mount on this stage.
- **Reviewer suggestions** — the suggestions panel (mounted here when
  enabled).
- **Submission files** — the file manager and upload wizard mechanics behind
  the Files for Review and Revisions Uploaded panels.
- **Tasks & discussions** — the Discussions panel present on this stage in
  both views.
- **Submission stage** — the "Send for Review" decision that opens Round 1.
- **Copyediting stage** — sending a submission back from copyediting returns
  it to its review round, whose status box usually resumes the
  reviewer-derived sentences; "Returned back to review." shows only in the
  all-confirmed state (Rules 5–6).
- **Reviewer's review** — a reviewer revisits their own past rounds on their
  reviewer page, including a round-history window; none of that renders on
  this screen.

## Canonical scenarios

Common to OJS and OMP; substitute roles and vocabulary per the
[application glossary](GLOSSARY.md) (on a press, the stage is External
Review). Actors are named by role; seeded accounts and recipes live in the
footnotes. Where a scenario checks a recipient's mailbox, outgoing mail on the
test install is observed in the install's mail catcher. <sup>s</sup>

1. **Round 1 opens with the submission** — Editor: on a new submission's
   workflow, record the decision that sends it to review ("Send for Review").
   The workflow menu now shows "Review" with the entry "Review Round 1"
   selected, the page title reads "Review (Round 1)", and the status box says
   "Round 1 Status — Waiting for reviewers to be assigned." The Files for
   Review panel lists the files chosen when sending to review.
2. **The status line follows the reviewers** — Editor: add a reviewer to the
   round (Reviewers panel); the status becomes "Awaiting responses from
   reviewers." After the reviewer submits their review, it reads "New reviews
   have been submitted."; after the editor opens the review through the
   Reviewers panel's "Read Review" action and presses "Confirm" in the review
   window, "All reviews are confirmed and a decision is needed."
3. **Request revisions within the round** — Editor: press "Request
   Revisions", keep "Revisions will not be subject to a new round of peer
   reviews.", complete the wizard. The status box reads "Revisions have been
   requested." The Author signs in: their task list holds a revisions task
   for this submission, and the submission's review stage shows the "Upload
   revisions" button.
4. **Author uploads a revision** — Author: on the review stage, press "Upload
   revisions" and complete the upload. The Revisions Uploaded panel lists the file,
   the status box (editor view) now reads "Revisions have been submitted and
   a decision is needed.", the author's task is gone, and the assigned
   editors' mailboxes hold the revised-version notice.
5. **Request revisions toward a new round** — Editor: press "Request
   Revisions", choose "Revisions will be subject to a new round of peer
   reviews.", complete the wizard — status "Revisions requested from the
   author to be taken to a new review round." Author: upload one revised
   file — status "Revisions submitted. A new review round needs to be
   created." ⚠ [A1](#a1) after this upload the bottom "Upload revisions"
   button is gone and the task list still reads "Resubmit for review."; a
   further file can be added only through the Revisions Uploaded panel's own
   "Upload" control.
6. **A new round** — Editor: press "Create New Review Round"; in the
   wizard's file step the revised file is already ticked — keep it and record
   the decision. The menu gains "Review Round 2"
   (selected), status "Waiting for reviewers to be assigned.", and the Files
   for Review panel of Round 2 lists the carried file. Selecting "Review Round 1"
   shows its reviewers and files, no decision buttons, and the note "The
   submission has been advanced to the next round of review".
7. **Cancel a round** — Editor: on a Round 2 whose only reviewer has not yet
   responded to the invitation, press "Cancel Review Round" and confirm through
   the wizard. "Review Round 2" disappears from the menu, the submission
   stands on Round 1, and the invited reviewer no longer finds the assignment
   in any of their assignment lists.
   Additionally verify: on a submission whose Round 1 is cancelled, the
   submission returns to the Submission stage.
8. **Cancelling is blocked once a review is in** — Editor: on a round with a
   completed review, verify "Cancel Review Round" is not offered among the
   buttons.
9. **Accept out of review** — Editor: press "Accept Submission" and complete
   the wizard. The submission moves to Copyediting; selecting the review
   stage still shows the rounds, with the status box reporting the submission
   is now in the Copyediting stage.
10. **Decline, revert, delete** — Editor: press "Decline Submission" and
    complete the wizard — the buttons are replaced by "Revert Decline", and a
    Journal Manager additionally sees "Delete" while a Section Editor does
    not. Press "Revert Decline" and complete it: the submission is back in
    review and the status line again reflects the round's reviewer state.
11. **Recommend-only round** — on a round with at least one reviewer added
    (one who declined is enough — Rule 6), a Section Editor limited to
    recommendations — assigned alongside a deciding Editor (Rule 13) — opens
    the current round: no decision buttons — the buttons
    read "Recommend Revisions", "Recommend Accept", "Recommend Decline"
    instead; the status box reads "Awaiting recommendations from editors."
    After they record "Accept Submission" as a recommendation (wizard per
    *Editorial decision recording*), the deciding Editor's screen shows the
    "Recommendation" box listing "Accept Submission", and the status box
    reads "All recommendations are in and a decision is needed." (with a
    second recommending editor still pending it would read "New editorial
    recommendations have been submitted.").
12. **Author reads an open review** — with a completed **open** review on the
    round, the Author opens the review stage: the reviewers list names the
    reviewer and offers "Read Review", which opens the review with the
    reviewer's name, date and recommendation (no recommendation line on a
    press ⚠ [OMP2](#omp2)). On a press the remarks the
    reviewer shared with the author follow; on a journal expect no review
    text in the window, even when the reviewer shared remarks ⚠
    [OJS1](#ojs1). The window's attachments section is not part of this
    scenario's pass/fail and must not be asserted by tests ⚠ [A3](#a3). With
    an **anonymous** completed review on another submission as control, the
    author's review stage shows no reviewers list at all — not an empty one.
    The author also finds the decision letters under "Notifications" and can
    open each read-only (Rule 16).

App-specific:

13. **{OMP} Straight to External Review** — Press Editor: on a new
    monograph's Submission stage, choose "Send to External Review" (skipping
    Internal Review). External Review "Review Round 1" opens exactly as in
    scenario 1, and scenarios 2–12 run identically on the press [OMP1](#omp1).
    The workflow menu also carries the separate "Internal Review" stage entry
    (documented separately).
14. **{OPS} No review stage on a preprint server** — Preprint Server
    Manager: open any preprint's workflow. The menu offers no Review entry —
    the stages go straight to Production — and no round, reviewer or review
    file surface exists anywhere on the screen. Positive control: the same
    screen offers the Production stage's own controls (e.g. posting or
    declining the preprint), so the workflow itself is working. <sup>p</sup>

## Findings register

Verdicts are the author's judgment (claude, 2026-07-31), unreviewed unless an
entry notes otherwise; the team settles them on spec review. Sorted 🐞 → ❓ →
✅ in the summary (🐞 defect, author's call · ❓ needs a product ruling · ✅
intended divergence); the entries below are the source. Each entry opens with
the user-observable symptom; mechanism and evidence live in the entry's
footnote.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|-----------------------------|------|--------|--------|
| [A1](#a1) | After the first upload on the new-round revision path, the author's "Upload revisions" button vanishes and nothing says they are done — though the panel's own "Upload" still works | 🐞 | user-visible | — |
| [OJS1](#ojs1) | On a journal, the author's "Read Review" window shows no review text — remarks shared with the author are missing (a press shows them) | 🐞 | user-visible | — |
| [A9](#a9) | Deleting the only revised file flips the status back but never returns the author's revisions task | 🐞 | minor | — |
| [A10](#a10) | The Revisions Uploaded panel says files were "submitted by the author after revisions were requested" on rounds where no revisions were requested | 🐞 | minor | — |
| [A2](#a2) | Author sees the editor's status wording; the author-tailored wording exists but is never shown | ❓ | user-visible | — |
| [A3](#a3) | What the read-review window's attachments section lists — observation recorded privately with the maintainer pending a fix | ❓ | latent | — |
| [A4](#a4) | A round whose only reviewers declined reports "All reviews are confirmed and a decision is needed." | ❓ | user-visible | — |
| [A5](#a5) | No screen path assigns a production assistant to the review stage; one assigned elsewhere is refused entry | ❓ | minor | — |
| [A6](#a6) | After a round is cancelled, the restored round's status box no longer mentions its unresolved revision request | ❓ | user-visible | — |
| [A7](#a7) | Unticking a file in the review-files dialog changes nothing the editor can see, and the checkboxes do not mirror the panel | ❓ | minor | — |
| [A8](#a8) | A past round's panels still act — a reviewer added or a file uploaded there lands in the closed round | ❓ | user-visible | — |
| [OMP2](#omp2) | A press collects no reviewer recommendation (intended — confirmed upstream 2026-08-25), and the decision letter still prints "Recommendation:" with nothing after it | ❓ | user-visible | rebase check (claude), 2026-08-25 — form-field half intended |
| [OMP3](#omp3) | On a press the fresh revisions task reads "Revisions to consider in External Review.", not the journal's "Revision required." | ❓ | minor | — |
| [OMP1](#omp1) | Presses run an additional Internal Review stage before External Review | ✅ | — | — |

### All apps

<a id="a1"></a>
**A1 — Inconsistent upload affordances after the first resubmit upload** · 🐞 · user-visible.
When revisions were requested to be taken to a new review round, the author's
bottom-of-screen "Upload revisions" button is offered while the request
stands — but as soon as the author uploads one file, the button vanishes,
while the Revisions Uploaded panel's own "Upload" control still opens the
same wizard. In the same situation without a new round required, the bottom
button correctly stays and the revisions task clears on upload. The author is
therefore not locked out; the defect is the contradiction — one upload path
withdrawn, the other still offered — and that nothing tells the author they
are done: the "Resubmit for review." task stays in their list after the
upload, and the status sentence still awaits the editor's new round.
Basis: probe (2026-07-31); the state missing from the bottom button's list is
code-confirmed. <sup>[f-a1](#fn-a1)</sup>

<a id="a2"></a>
**A2 — Author-tailored status wording never shown** · ❓ · user-visible.
The product carries author-specific wordings for two round statuses — e.g.
"New reviews have been submitted and are being considered by the editor." and
a reassuring no-action-needed text for an overdue review — but the status box
the author sees uses the editor wording ("A review is overdue."), which
invites the author to worry or chase. The tailored texts were shown by earlier
versions.
Question: should the author's status box use the author wordings again?
Lean: yes — the texts exist, are maintained, and read as deliberate design.
Basis: probe (2026-07-31 — the author's box is character-identical to the
editor's at every state checked); the tailored texts themselves are code
reading. <sup>[f-a2](#fn-a2)</sup>

<a id="a3"></a>
**A3 — Read-review attachment listing recorded privately** · ❓ · latent.
What the author's "Read Review" window lists in its attachments section was
observed live on both the journal and the press; the observation is recorded
privately with the maintainer, pending a fix. Until that resolves, this spec
makes no claim about the window's attachment listing, and tests must not
assert anything about what that section lists (scenario 12 excludes it
explicitly).
Basis: probe (2026-07-31, both apps — routed to the maintainer's private
file). <sup>[f-a3](#fn-a3)</sup>

<a id="a4"></a>
**A4 — All-declined round claims confirmed reviews** · ❓ · user-visible.
A round where reviewers were invited but every one of them declined reports
"All reviews are confirmed and a decision is needed." — no review exists, none
was confirmed. The sentence "Waiting for reviewers to be assigned." appears
only while the round has no reviewer records at all.
Question: what should a round with only declined reviewers report? Lean: the
"decision is needed" half is right, the "all reviews are confirmed" half is
wrong — misleading wording rather than misbehavior.
Basis: probe (2026-07-31 — a round whose only invited reviewer declined shows
the confirmed-reviews sentence); reproduces identically on a press
(2026-07-31). <sup>[f-a4](#fn-a4)</sup>

<a id="a5"></a>
**A5 — No path assigns a production assistant to the review stage** · ❓ · minor.
The review stage's participant-assignment dialog offers no production
assistant groups (Copyeditor, Layout Editor, Proofreader), and one assigned
on another stage who selects the review stage sees only "You don't currently
have access to that stage of the workflow." — so no screen path gives those
roles any part in review, although the file panels' underlying permissions
would admit an assistant. The Funding Coordinator — also an assistant-level
group — is offered by the same dialog on both apps, by design.
Question: are the production assistant roles meant to be assignable to the
review stage? Lean: the dialog's roster is the intended gate, and the
file-panel permissions naming assistants are leftovers — but whether an
assistant placed on the stage by other means would gain access was not
observed.
Basis: probe (OJS, 2026-07-31); the dialog's role roster read on both apps
(2026-07-31). <sup>[f-a5](#fn-a5)</sup>

<a id="a6"></a>
**A6 — Cancelling a round forgets the previous round's revision request** · ❓ · user-visible.
After "Cancel Review Round" removes Round 2, the restored Round 1 reports
"Awaiting responses from reviewers." — even though a request for revisions
toward a new round had been recorded on it and the author's revised file
still sits in its Revisions Uploaded panel. The status box no longer says
revisions were requested or submitted, so nothing on the screen recalls the
round's open business.
Question: should the restored round's status still reflect its unresolved
revision request? Lean: yes — the box falls back to reviewer bookkeeping and
hides what the round was actually waiting on.
Basis: probe (2026-07-31). <sup>[f-a6](#fn-a6)</sup>

<a id="a7"></a>
**A7 — Unticking a review file changes nothing the editor can see** · ❓ · minor.
In the "Current Review Files For Round {N}" dialog, unticking a listed file
and confirming reports "Review files updated." — but the Files for Review
panel still lists the file exactly as before; only reopening the dialog shows
the cleared checkbox. A file the round received through a decision arrives in
the dialog unticked even while the panel lists it — as does a file uploaded
from the dialog itself — so the checkboxes do not mirror the panel to begin
with.
Question: what is the checkbox meant to control — and does a reviewer of the
round still get an unticked file? Lean: the checkbox drives a visibility flag
the editor's own panel ignores; whether the reviewer's file list honors it is
the observation that would settle intent.
Basis: probe (OJS, 2026-07-31); reproduces on OMP (2026-07-31), including for
a file uploaded from the dialog itself. <sup>[f-a7](#fn-a7)</sup>

<a id="a8"></a>
**A8 — Past rounds still accept reviewers and files** · ❓ · user-visible.
A past round is presented as closed — its decision buttons are gone and its
status reads "The submission has been advanced to the next round of review" —
yet its panels still work: adding a reviewer there sends a live invitation
recorded on the past round, and completing an upload lands the file in the
past round's panel. A round's record can therefore change after review has
moved on, with nothing marking the panels read-only.
Question: are a past round's reviewer and file panels meant to stay editable?
Lean: the withdrawn decision buttons say the round is closed, and a reviewer
invited into a superseded round reads as an accident waiting to happen — but
the acting roles are entitled to manage those panels, so it may be deliberate
flexibility.
Basis: probe (2026-07-31 — both mutations driven on OJS; the retained
controls render identically on OMP). <sup>[f-a8](#fn-a8)</sup>

<a id="a9"></a>
**A9 — Deleting the only revised file leaves no revisions task behind** · 🐞 · minor.
When the only revised file on a round is deleted — by the editor or by the
author — the round's status and the author's My Submissions row correctly
return to their revisions-requested state, but the revisions task never
returns to the author's task list: the list stays silent where the decision
had put "Revision required." The author still has the My Submissions cue and
the upload button, so the miss is the prompt, not the path. A file later
added on the review stage does bring a task back, under different wording.
Basis: probe (2026-07-31, OJS and OMP, both deleter
roles). <sup>[f-a9](#fn-a9)</sup>

<a id="a10"></a>
**A10 — Revisions panel claims revisions were requested when none were** · 🐞 · minor.
The "Revisions Uploaded" panel opens with the description "These files have
been submitted by the author after revisions were requested" on every round —
including a round on which no revision request was ever recorded, where the
sentence describes an event that never happened. The panel is empty at that
point, so the miss is the explanation, not the files — but a reader is told
revisions were requested when they were not.
Since: 2026-08-02 · Basis: probe. <sup>[f-a10](#fn-a10)</sup>

### OJS

<a id="ojs1"></a>
**OJS1 — Author's read-review window shows no review text** · 🐞 · user-visible.
On a journal, the "Read Review" window an author opens on a completed open
review gives the reviewer's name, completion date and recommendation — and
then no review text at all: even a remark the reviewer explicitly shared with
the author is missing. The decision letter's reviewer appendix stops at the
recommendation the same way (that letter is *Editorial decision recording*'s
surface). On a press the same window shows the shared remarks; editor-only
remarks are correctly absent in both apps. The author is the reader this
window exists for, and on a journal they get none of the text.
Basis: probe (2026-07-31, OJS with OMP contrast). <sup>[f-ojs1](#fn-ojs1)</sup>

### OMP

<a id="omp1"></a>
**OMP1 — Internal Review stage** · ✅ · intended divergence.
A press runs an additional Internal Review stage between Submission and
External Review, with the same round machinery, its own decision labels
("Send to External Review" closes an internal round), and its own revision
file spaces. A monograph may also skip it entirely (scenario 13). The External
Review stage this file documents is unchanged by its presence. The Internal
Review stage's own screens are documented separately.
Basis: code (the stage roster and decision list are press-specific by
design); parity and the Internal Review menu entry observed live
(2026-07-31). <sup>[f-omp1](#fn-omp1)</sup>

<a id="omp2"></a>
**OMP2 — No reviewer recommendation on a press** · ❓ · user-visible.
On a press, the reviewer's review form offers no recommendation field, so the
author's "Read Review" window shows no recommendation line — and the decision
letter's per-reviewer section still prints the label "Recommendation:" with
nothing after it (the letter itself is *Editorial decision recording*'s
surface). On a journal the recommendation runs through all three places.
Question: is a recommendation-free review the press's intended design? Lean:
the missing form field reads as deliberate, but a letter printing an empty
"Recommendation:" label is a defect either way.
Basis: probe (2026-07-31, OMP with OJS contrast). <sup>[f-omp2](#fn-omp2)</sup>

> **Upstream answer — rebase check (claude), 2026-08-25**: the form-field
> half is intended. Upstream OMP now flips
> `Application::hasCustomizableReviewerRecommendation()` to `false` outright
> (commit titled "Disable customizable reviewer recommendations"), so the
> suppression is an app-level design flag, not a template accident. The
> letter's empty "Recommendation:" label remains the open defect half.

<a id="omp3"></a>
**OMP3 — The press's fresh revisions task is worded differently** · ❓ · minor.
After a Request Revisions decision on a press, the author's task-list entry
reads "Revisions to consider in External Review." where a journal's reads
"Revision required." Everything around it is identical in both apps — the
"Revision requested" row on My Submissions, the "Submit revisions" button,
the task clearing on upload, and the new-round variant's "Resubmit for
review." task.
Question: is the press's different task wording intended? Lean: unintended —
the press's internal-review bookkeeping deletes the task the decision just
created and a second mechanism refiles it under its own wording; the effect
is wording-level only.
Basis: probe (2026-07-31, the same flow driven on both
apps). <sup>[f-omp3](#fn-omp3)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<a id="fn-a"></a>
**a** — The workflow page composes per stage from
`useWorkflowConfig/workflowConfigEditorialOJS.js` (editorial) and
`workflowConfigAuthorOJS.js` (author view) in lib/ui-library. External-review
editorial primary panels (in order): `FileManager` `WORKFLOW_REVIEW_REVISIONS`
(atom AFFW-323), `FileManager` `EDITOR_REVIEW_FILES` (AFFW-324),
`ReviewerManager` (AFFW-325), `AuthorResponseRequestManager` (owned by the
author-response feature), `DiscussionManager` (AFFW-327); secondary:
`WorkflowRecommendOnlyListingRecommendations` (guard `selectedReviewRound &&
selectedStage?.isCurrentUserDecidingEditor`, AFFW-328), `ParticipantManager`
(AFFW-329), `ReviewerSuggestionManager` (guard `selectedReviewRound &&
publicationSettings.isReviewerSuggestionEnabled`, AFFW-330). Author-view
primary: `WorkflowListingEmails` (AFFW-350), redacted `ReviewerManager` (guard
`getOpenAndCompletedReviewAssignmentsForRound(...).length`, AFFW-352),
`FileManager` `WORKFLOW_REVIEW_REVISIONS` + `DiscussionManager` (AFFW-353),
`AuthorResponseManager` (guard: response requested or round status ∈
{ACCEPTED, REVISIONS_REQUESTED}; owned by the author-response feature). Stage
access gate: `permissions.accessibleStages` (common config guard) — an
inaccessible stage renders only "user.authorization.accessibleWorkflowStage"
text. Round menu: `useWorkflowNavigationConfigOJS.js::getReviewItems()` →
label `workflow.reviewRoundN` "Review Round {$number}", initial selection =
current round; page title `submission.stage.externalReviewWithRound` "Review
(Round {$round})". OMP: `workflowConfigEditorialOMP.js` /
`workflowConfigAuthorOMP.js` define no external-review key, so the OJS
external-review block applies verbatim through the config deep-merge
(`useWorkflowConfigOMP.js`) — shared-behavior evidence for every panel/button
claim. Server side, both dashboards are served by
`PKPDashboardHandler` (ops `editorial` / `mySubmissions`); per-submission
access is enforced by `SubmissionAccessPolicy` on `GET
/api/v1/submissions/{id}` (authors need authorship or a stage assignment;
reviewers only via a review assignment). Refused-visitor probe 2026-07-31:
an unconnected author and an assigned reviewer typing
another submission's editorial-dashboard workflow address were turned away at
the dashboard's role gate; a foreign submission id opened through My
Submissions produced only an "Error / The current role does not have access
to this operation." dialog — no submission data in any case. Panel roster, order and on-screen headings
live-probed 2026-07-31 — the file panels are headed "Files for Review" and
"Revisions Uploaded" on screen, verbatim incl. subtexts on OJS and OMP.

<a id="fn-b"></a>
**b** — `PKP\submission\reviewRound\ReviewRound` / `ReviewRoundDAO`
(lib/pkp); DB unique key on (submission, stage, round). No OJS or OMP subclass
of either class exists (empty chains — shared-behavior evidence). Round 1:
`DecisionType::runAdditionalActions()` creates round 1 with status
`REVIEW_ROUND_STATUS_PENDING_REVIEWERS` when a decision promotes into a review
stage with no round; increments only in
`NewExternalReviewRound::runAdditionalActions()` (last round + 1). "Current
round" = highest (stage, round) — `ReviewRoundDAO::getLastReviewRoundBySubmissionId()`.
Data reaches the UI embedded in the submission payload
(`Schema::getPropertyReviewRounds()`: id, round, stageId, statusId, status,
publicationId, isAuthorResponseRequested, authorResponse — the reviewRound
schema, atom SET-021).

<a id="fn-c"></a>
**c** — Status machine: `ReviewRound::determineStatus()` — precedence:
stored revision statuses (1/2) flip to their submitted partners (11/15) via
`Repo::decision()::getActivePendingRevisionsDecision()` +
`revisionsUploadedSinceDecision()` (compares file update time to decision
date, review-revision file stage); stored 3/4/5 (sent-external / accepted /
declined) are final; recommendation scan (recommendOnly stage assignments,
manager+sub-editor roles) yields 14/13; reviewer scan (declined/cancelled
ignored) yields, in order: no assignments → 6, any overdue → 10, any unread →
8, any incomplete → 7, pending recommendations → 12; stored 16
(returned-to-review) survives; fallback → 9 (basis of finding A4).
`ReviewRoundDAO::updateStatus()` recalculates on reviewer edits, revision
uploads and decisions. Status 16 is set only by
`BackFromCopyediting::runAdditionalActions()`.

<a id="fn-d"></a>
**d** — Status texts: `ReviewRound::getStatusKey()` →
`editor.submission.roundStatus.*` keys (lib/pkp locale, quoted verbatim in
Rule 5; author variants `author.submission.roundStatus.reviewsReady` /
`.reviewOverdue` — finding A2). Box: `WorkflowSubmissionStatus.vue` — heading
`notification.type.roundStatusTitle` "Round {$round} Status", body =
`currentReviewRound.status` (always the current round; past-round and
future-stage texts `workflow.submissionInNextReviewRound`,
`workflow.submissionInFutureStage`,
`workflow.submissionNextReviewRoundInFutureStage`, `workflow.stageNotStarted`
per Rule 4). Status texts and box headings live-probed 2026-07-31 — OJS full
chain: waiting → awaiting responses → new reviews → all confirmed, both
revision-request paths and their submitted partners, declined, past-round and
post-accept texts; OMP: waiting / new reviews / all confirmed /
revisions requested. On a past round or past stage the
box heading renders plain "Status", not "Round {$round} Status" (Rule 4), and
the past-round sentence carries no closing period on screen. "Returned back
to review." has not been observed on screen: the claim check (2026-07-31)
drove Move to Review into a reviewerless round and an
in-progress round and saw "Waiting for reviewers to be assigned." /
"Awaiting responses from reviewers." instead — the stored returned-back
status survives the reviewer scan only in the all-completed-and-confirmed
state (note c), and reaching that state needs a UI-submitted,
editor-confirmed review the scenario seeder cannot produce, so the
sentence's positive appearance stays code-confirmed only. One dead status: `REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL` ("Sent
for external review.") is set by no decision type in OJS or OMP (OMP's
`SendExternalReview` sets the internal round to ACCEPTED instead), so its
sentence never renders — invisible, recorded here only.

<a id="fn-e"></a>
**e** — Buttons: `workflowConfigEditorialOJS.js` external-review
`getActionItems` — early return on `!selectedReviewRound` or
`selectedReviewRound.round < currentReviewRound.round` (AFFW-339); recommend-
only branch on `selectedStage.currentUserCanRecommendOnly` renders
`WorkflowRecommendOnlyControls` (owned by the decision-recording feature);
else `WorkflowActionButton`s per Rule 11 guarded by
`isDecisionAvailable(...)` over `submission.availableEditorialDecisions`
(AFFW-332..338). Server assembly: `APP\submission\maps\Schema::
getAvailableEditorialDecisions()` (OJS and OMP external branches identical:
RequestRevisions, Resubmit, Accept, NewExternalReviewRound, + CancelReviewRound
when retractable, + Decline while queued; replaced by [RevertDecline] while
declined) gated by `checkDecisionPermissions()` (manager/sub-editor stage
assignments; recommendOnly ⇒ recommendation-only; assistants get none —
hence no buttons for assistant-level roles). Delete guard additionally
requires a manager/site-admin role
(`hasCurrentUserAtLeastOneAssignedRoleInAnyStage`). Recommendation display:
`WorkflowRecommendOnlyListingRecommendations.vue` (AFFW-349) — heading
`editor.submission.recommendation` "Recommendation", body =
`selectedStage.recommendations[].label` joined by commas; recommendations are
serialized only for the deciding editor. Button roster, the Request
Revisions entry modal (radio labels, "not subject" preselected) and the two
wizard headings ("Request Revisions" / "Resubmit for Review") live-probed
2026-07-31. Past-round view probed the same
day: all five decision buttons absent, while the panels'
own buttons (Upload, Upload/Select Files, Add Reviewer, Assign) still
render; the claim check (2026-07-31) then exercised them and they act on
the past round — basis of finding A8 (note f-a8).
Declined-state roster probed 2026-07-31: while declined,
a Section Editor saw only "Revert Decline", a Journal Manager also "Delete";
Revert Decline restored the full button set and the recomputed status.
Recommend-only probed the same day: the three buttons
render as quoted in Rule 13, the "Recommendation" box lists the bare decision
label, and on a reviewerless round the box read "Waiting for reviewers to be
assigned." rather than the recommendation sentence — the Rule 6 precedence
line; consistent with the `determineStatus()` scan order in note c (the
no-assignments check precedes the pending-recommendations one). The
declined-only overlap of Rule 6 was driven the same day on a second
recommend-only round
(its one reviewer had declined): the box read "Awaiting recommendations from
editors." while the recommendation was pending and "All recommendations are
in and a decision is needed." once recorded — the recommendation branches
supersede the fallback sentence of finding A4. Unobserved edge: a round with
reviews underway, unread or overdue while recommendations are pending was
not driven, so which sentence wins there stays code-predicted only (note c's
scan order puts the reviewer flags before the pending-recommendations
branch); the body records the observed states.
Rule 13's deciding-editor precondition: claim check 2026-07-31
— with a recommend-only Section Editor as the
only editorial stage participant, no recommendation buttons rendered and the
"Recommendation" box carried the guard text quoted in Rule 13; with a
deciding Journal Editor assigned alongside, the three buttons rendered and
the recorded recommendation listed as in Rule 13.

<a id="fn-f"></a>
**f** — `PKP\decision\types\CancelReviewRound` — `canRetract()` false when
any assignment is confirmed or completed; "confirmed" means a response of
either kind: `ReviewerAction::confirmReview()` sets `dateConfirmed` on
decline exactly as on accept, and the retractability check counts every
confirmed-and-not-cancelled assignment (`DecisionType::
REVIEW_ASSIGNMENT_CONFIRMED`) — basis of Rule 11's responded-not-accepted
wording; claim check 2026-07-31: Cancel absent
on a round with one declined and one unanswered invitation, present on a
reviewerless round. The locale carries a restriction
text (`editor.submission.decision.cancelReviewRound.restriction`) but no
current screen renders it — when retraction is unavailable the button is
simply not offered (probed 2026-07-31 on OJS with positive
controls; OMP observed the same vanish the same day, no text in its
place). `runAdditionalActions()` deletes the round's review assignments and
the round row (round-file links go with it via cascade; notification rows for
the round are deleted too); new stage = external review while >1 round, else
Submission stage (OMP's internal variant: internal review / Submission).
Author email mailable `DecisionCancelReviewRoundNotifyAuthor`; reviewer
withdrawal mail `ReviewerUnassign` — both sent from the decision wizard
(decision-recording feature); the reviewer step renders only when the round
has reviewers (live probe 2026-07-31: a reviewerless round's wizard has the
author step only). Cancel flow live-probed
2026-07-31: Round-2 cancel restores Round 1 (recomputed status — finding A6),
Round-1 cancel lands on the Submission stage, and the withdrawn unanswered
invitation disappeared from every reviewer-side list including "All
assignments".

<a id="fn-g"></a>
**g** — `PKP\decision\types\NewExternalReviewRound` — label
`editor.submission.createNewRound` "Create New Review Round"; wizard step
promotes selected files (previous round's review-revision files offered) into
the new round's review-file stage; new round built with status
PENDING_REVIEWERS. OMP: `NewInternalReviewRound extends NewExternalReviewRound`
(parameterization only). Wizard live-probed 2026-07-31:
the revised file's checkbox in the "Select Files" step arrives already
checked, and the carried file lands in the new round as a copy under a new
file id — the author's original stays in the old round's Revisions Uploaded
panel.

<a id="fn-h"></a>
**h** — Editor review files: `EditorReviewFilesGridHandler` (GRID-025; roles
manager, site admin, sub-editor, assistant; ops fetchGrid/fetchRow/selectFiles)
→ selection modal `ManageReviewFilesGridHandler` (GRID-027, op
`updateReviewFiles`, title `editor.submission.review.currentFiles` "Current
Review Files For Round {$round}"). `ManageSubmissionFilesForm::execute()`:
selected & not yet in stage → copied in and linked to the round
(`assignRevisionToReviewRound` → `review_round_files`); already in stage →
`viewable` flag set to the checkbox; deselection only clears `viewable`,
never deletes. Success notice `notification.updatedReviewFiles` "Review files
updated." No app subclasses this pair (empty chains). On-screen panel
heading "Files for Review" live-probed 2026-07-31 (note a); the dialog's
checkbox behavior probed the same day (finding A7).

<a id="fn-i"></a>
**i** — Author upload button: `workflowConfigAuthorOJS.js` external-review
`getActionItems` — guard `[REVIEW_ROUND_STATUS_REVISIONS_REQUESTED,
REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW, REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED,
REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED].includes(selectedReviewRound.statusId)`
— the literal duplicate where `REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED`
(15) belongs is finding A1's basis. Button label `workflow.uploadRevisions`
"Upload revisions", upload into the review-revision file stage of the round
(file-upload wizard — submission-files feature). The panel-header "Upload"
action is the `FileManager`'s own and is not gated by the round status —
observed surviving after the resubmit-path upload, 2026-07-31 (finding A1).
Editor-side revisions panel
is the same `FileManager` namespace (AFFW-323); its editor actions observed
2026-07-31 on OJS and OMP, identical (claim check): a
header "Upload" opens the same three-step wizard, filing a revision on the
author's behalf, ungated by round status; each file row's "More Actions"
menu offers exactly "Update File Details", "More Information", "Delete"
(delete confirms, then removes the file); no select/checkbox control. The
author's per-file menu on the same panel is narrower — no "More
Information". Legacy revision grids (`WorkflowReviewRevisionsGridHandler`
GRID-029, `AuthorReviewRevisionsGridHandler` GRID-024) are wired to no
current screen — see note o.

<a id="fn-j"></a>
**j** — Author reading: redacted `ReviewerManager`
(`useReviewerManagerConfig.js`, `redactedForAuthors: true`) — rows =
open-method assignments with a completion date
(`getOpenAndCompletedReviewAssignmentsForRound`), columns reviewer / type /
actions (no status column, no add button); row action `editor.review.readReview`
"Read Review" (AFFW-487) for statuses complete/thanked/received/viewed →
legacy modal `AuthorReviewerGridHandler::readReview` (GRID-053; author role
authorized only for open-method assignments) rendering `authorReadReview.tpl`
(AFFW-666): reviewer full name, completion date, recommendation, then either
review-form responses filtered to elements marked "included in author view"
or reviewer comments filtered to viewable-by-author (AFFW-667), plus the
attachments grid `AuthorOpenReviewAttachmentsGridHandler` (GRID-010) — its
listing is finding A3's subject (sibling `AuthorReviewAttachmentsGridHandler`,
GRID-011, is unreachable — note o). Author window live-probed 2026-07-31 on
OJS and OMP (findings OJS1, A3); the
anonymous-only control the same day — no reviewers
panel mounts in the author view, matching the mount guard above.
API-side control: review assignments serialized to
authors/reviewers are anonymized unless the review method is open. The modal
form posts nothing (no submit button); the grid's second operation
(`reviewRead`) is never sent by any screen. No app subclasses
`AuthorReviewerGridHandler` (empty chains).

<a id="fn-k"></a>
**k** — `WorkflowListingEmails.vue` (AFFW-350/351): fetches
`emails/authorEmails?submissionId=…&eventType=EDITOR_NOTIFY_AUTHOR` (only on
the review stage), heading `notification.notifications` "Notifications", rows
= subject + date; click opens `authorDashboard/readSubmissionEmail` in a side
modal (`submissionEmail.tpl`, AFFW-702) — the one surviving operation of the
legacy author dashboard (note o); the server re-checks the email belongs to
this submission, this event type and this user. Live-probed 2026-07-31 on OJS
and OMP: subject + date rows, and
the opened letter carries zero editable fields and no reply controls.
Conditional mounting (Rules 14, 16): claim check 2026-07-31
— with no editor letter the author view rendered no
Notifications list at all; after a decision sent the author a letter, the
list appeared with that one subject + date row.

<a id="fn-l"></a>
**l** — `PKP\mail\mailables\RevisedVersionNotify` (MAIL-047, template key
`REVISED_VERSION_NOTIFY`): dispatched only from
`PKP\submissionFile\Repository::notifyEditorsRevisionsUploaded()`, itself
called from `Repo::submissionFile()->add()` when a review-revision file's
uploader is a stage-assigned author — i.e. at the wizard's step-1 file
transfer, before the Review Details and Confirm steps (observed live: the
email and status flip landed during an abandoned wizard run). Recipients:
users with manager or sub-editor stage assignments on the round's stage.
Throttle: an editor is skipped when a prior notice exists, they have not
logged in since it, and it is under 24 h old — confirmed live: a second
completed upload on the same round sent nothing. Observed From: the author's
own name and address; subject "Revised Version Uploaded". Probed 2026-07-31
(live probe). Both remaining branches closed by the claim check
2026-07-31: an Editor not assigned to the
stage received nothing for an upload — the assigned-to-the-stage qualifier is
load-bearing — and after the assigned editor signed in again, a further
upload sent a fresh notice (the sign-in branch re-arms the daily throttle).

<a id="fn-m"></a>
**m** — `PendingRevisionsNotificationManager`
(`NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS`, NOTIF-031; OMP also
`…_INTERNAL_…`): created at task level for each stage-assigned author when an
active Request Revisions decision exists and no newer revision file does;
content = "Upload Revised File" link action into the round. The fresh OJS
task is the decision-owned
`NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS` row ("Revision
required."); on OMP the internal-review delegate's removal branch deletes
that shared row and the external delegate refiles the task as the
pendingRevisions type, worded "Revisions to consider in {$stage}." —
finding OMP3 (note f-omp3). Deleting the last revision file does NOT
re-create the task: the delete-time notification pass runs while the file
row still exists (`Repo::submissionFile()->delete()`), so
`revisionsUploadedSinceDecision()` still counts it and the removal branch
holds; nothing re-evaluates after the row is gone — finding A9, claim check
2026-07-31 (both apps, editor- and author-side
deletion). A later file added to the review stage while the decision stands
does re-create a task, as the pendingRevisions type in both apps
(`PKP\submissionFile\Repository::add()` pass; observed live on OMP,
code-traced on OJS) — so "Revision required." is the fresh-decision wording
on a journal, not a task-list invariant. Observed 2026-07-31 (live probe +
claim check): the OJS Tasks panel entry reads
"Revision required." plus the submission title, the My Submissions row shows
"Revision requested" with a "Submit revisions" button in both apps, and the
task cleared on the in-round upload. The new-round variant (Resubmit)
produces a different, decision-owned task ("Resubmit for review.", verbatim
in both apps) — side-effect line only here.

<a id="fn-n"></a>
**n** — `DecisionType::createReviewRound()` creates one
`NOTIFICATION_TYPE_REVIEW_ROUND_STATUS` record per round (NOTIF-029, normal
level, no user). Its only render site was the legacy author-dashboard round
tab (note o), so it currently surfaces nowhere; the round-status box reads
the round's status from the submission payload instead. The record is deleted
with the round. Recorded here as mechanism; no user impact.

<a id="fn-o"></a>
**o** — Legacy author-dashboard surfaces, and the atom waivers this draft
declares. Routing still names three operations; only `readSubmissionEmail`
has a handler op and renders (note k). `authorDashboard/submission` is a pure
redirect to My Submissions with the workflow open (Rule 17).
`reviewRoundInfo` has no handler method or role assignment — the template
`reviewRoundInfo.tpl` (AFFW-703/704) and, through it, `submissionEmails.tpl`
(AFFW-701), `AuthorReviewAttachmentsGridHandler` (GRID-011) and
`AuthorReviewRevisionsGridHandler` (GRID-024) render nowhere;
`WorkflowReviewRevisionsGridHandler` (GRID-029) likewise has no referencing
screen. These six atoms are **waived as unreachable legacy surfaces**
(evidence: no render/reference site in OJS, OMP or lib code; typed-URL probe
2026-07-31: `authorDashboard/submission` redirected to
My Submissions with the workflow open, and `reviewRoundInfo` answered
HTTP 404 both with and without a submission id — an error, not a screen; the
waivers stand).

<a id="fn-p"></a>
**p** — OPS absence, install facts: the preprint server's stage roster is
Production only (`APP\core\Application::getApplicationStages()` in ops-main —
"Only one stage in OPS"), it inherits an empty review-stage list, registers
no review-stage decision types (`APP\decision\Repository::getDecisionTypes()`:
decline/revert/post cluster only), and serializes no review stage or
decisions (`APP\submission\maps\Schema` short-circuits to production/done).
The shared round classes and tables ship in the codebase but are never
populated on a default install. Live absence probe
2026-07-31: a preprint's workflow menu held exactly one entry, Production; no
round, reviewer or review-file surface anywhere on the screen; the Production
stage's own controls rendered as the positive control (scenario 14).

<a id="fn-q"></a>
**q** — OMP chain check (multi-app evidence): no OMP subclass of
`ReviewRound`, `ReviewRoundDAO`, any file/reviewer grid in this spec, or
`PendingRevisionsNotificationManager` (empty chains). OMP's divergence lives
in: `APP\decision\types\*` internal-review decision types (all thin
subclasses of the external ones), `Application::getApplicationStages()`
(internal stage added), `Schema::getAvailableEditorialDecisions()` (external
branch identical to OJS's), and `Repository::getReviewNotificationTypes()`
(internal + external pending-revisions types). The Vue configs add only
internal-stage blocks (note a). One forwarded OMP observation, confirmed live
2026-07-31 (cross-app control probe on OMP): no Author Response panel
renders in either the editorial or the author view — a silent absence, console
clean (`WorkflowPageOMP.vue` does not register the author-response components
the merged external-review config emits) — while the author still receives
the letter inviting a response. The finding belongs to *Author response to
reviews* (Rule 14 carries the pointer).

<a id="fn-r"></a>
**r** — Context setting `numReviewsPerSubmission` (review setup form, label
"Minimum Confirmed Reviews Required") → dashboard passes
`contextMinReviewsPerSubmission`; `WorkflowSubmissionStatus.vue` adds
`dashboard.minimumConfirmedReviewsRequired` "Minimum number of confirmed
reviews required: {$number}." and applies minimum-aware overrides for the
submitted/confirmed reviewer statuses. Probed
2026-07-31: with a minimum of 2 and one confirmed review, the box carried only
the minimum line. Claim check 2026-07-31: with
two accepted and none confirmed, the box carried the minimum line **plus**
"Awaiting responses from reviewers." — identical on OJS and OMP — so the
override suppresses only the submitted/confirmed sentences, not the
in-progress one; the body's earlier "no status sentence at all" claim was
corrected on that observation. The wording once the minimum is met remains
unobserved live: crossing the threshold needs a UI-submitted,
editor-confirmed review the scenario seeder cannot produce, so it was
deferred by cost, not oversight (claim check 2026-07-31).

<a id="fn-s"></a>
**s** — Scenario seeding: run on the seeded test journal/press
(`publicknowledge`) with seeded editor/section-editor/author/reviewer roster
accounts; mutating flows use scratch submissions created through the test
scenario endpoints; mail observed in the test mail catcher with per-test
throwaway recipient addresses. Recommend-only setup (scenario 11): assign a
Section Editor as participant with the recommendation-only limitation (stage-
participants feature), alongside a deciding-editor participant — without one
the recommendation buttons do not render (Rule 13). Exact usernames per the e2e harness roster at
test-authoring time. Seeding caveat observed 2026-07-31 while building probe
fixtures, twice independently (a probe run on the seeded journal's own state
provided the contrast): on scratch
journals, submitting assigns no editor even with a matching section editor
seeded, so every recipe must include an explicit participant-assignment step
before the workflow steps — the auto-assignment behavior itself belongs to
the *Submission stage* feature, not this register.

<a id="fn-a1"></a>
**f-a1** — Guard list in note i: the four-entry status list contains
`REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED` twice and lacks
`REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED` (15) — the state a
resubmit-path round enters on the author's first upload (note c). The
in-round path (statuses 1→11) keeps the button through both states. Probed
2026-07-31 (contrast, same probe: the in-round path keeps
the button and its task clears on upload); the persisting "Resubmit for
review." task is the decision-owned resubmit task (note m).

<a id="fn-a2"></a>
**f-a2** — `Schema::getPropertyReviewRounds()` calls
`ReviewRound::getStatusKey()` without the author flag, so the payload —
and the status box in both dashboards — always carries the editor wording.
The author variants exist for statuses 8 and 10
(`author.submission.roundStatus.reviewsReady` / `.reviewOverdue`) and were
rendered by the legacy author dashboard's round-status notification path
(`PKPNotificationManager::getNotificationMessage()`, which does pass the
author flag but has no remaining surface — notes n, o). Probed 2026-07-31 —
author and editor boxes character-identical at four states, viewed side by
side.

<a id="fn-a3"></a>
**f-a3** — Both apps' observations (live probes 2026-07-31, OJS and OMP)
are routed to the maintainer's private
security file per the campaign's routing rule; nothing beyond that routing
declaration is recorded anywhere else. The grid handlers involved are indexed in note j and
the Reference tables; their behavior is not described here until the
maintainer's fix ships, and no test asserts it.

<a id="fn-a4"></a>
**f-a4** — `determineStatus()` reviewer scan ignores declined/cancelled
assignments entirely; "no assignments" (status 6) requires zero records, so
an all-declined round falls through every flag to the fallback status 9
(note c). Probed 2026-07-31 (one invited reviewer,
declined). Reproduced on OMP by the claim check the same day:
a press round whose only invited reviewer
declined reads the identical sentence.

<a id="fn-a5"></a>
**f-a5** — Probed 2026-07-31 on OJS: the review stage's
Assign dialog (stage-participants feature) listed no production assistant
user groups, and a Copyeditor assigned on the Copyediting stage got only the
`user.authorization.accessibleWorkflowStage` text ("You don't currently have
access to that stage of the workflow.") on the review stage. Claim check
2026-07-31 read the dialog's full role roster on
both apps — OJS: Journal editor, Section editor, Guest editor, Funding
coordinator, Translator, Author; OMP: Press editor, Series editor, Funding
coordinator, Author, Volume editor, Translator — Funding coordinator is a
`ROLE_ID_ASSISTANT` group with review-stage access by design, the one
assistant-level group with a screen path onto the stage. The code-side
admission with no screen path: `EditorReviewFilesGridHandler` includes
`ROLE_ID_ASSISTANT` in its role list (note h), and the stage-access gate is
`permissions.accessibleStages` (note a) — whether a stage assignment created
by other means would populate it for a production assistant was not observed.

<a id="fn-a6"></a>
**f-a6** — Probed 2026-07-31: Round 1 carried a
recorded resubmit decision and an uploaded revision (status before Round 2
existed: "Revisions requested from the author to be taken to a new review
round."); after Round 2 was cancelled, the restored Round 1's box read
"Awaiting responses from reviewers." The post-cancel recompute (note c)
resolves the round against its reviewer records and the resubmit decision no
longer surfaces; whether it should is the entry's question.

<a id="fn-a7"></a>
**f-a7** — Probed 2026-07-31: unticking a listed file
and confirming produced no visible change in the Files for Review panel, and
the decision-carried file showed unticked in the dialog while the panel
listed it. Reproduced on OMP by the claim check the same
day: dialog heading, in-dialog upload link and the
"Review files updated." notice verbatim on the press, and a file uploaded
from the dialog itself arrived with its checkbox unchecked while the panel
listed it; untick-and-confirm changed nothing visible there either.
Mechanism per note h: deselection clears the `viewable` flag on
the round's file link and never deletes; the panel renders the file
regardless of that flag. Whether `viewable` gates the reviewer-side file
list was not observed — the entry's open half.

<a id="fn-a8"></a>
**f-a8** — Claim check 2026-07-31, two independent drives: on a two-round
scratch submission, past Round 1's Add Reviewer form
completed and that round's Reviewers panel listed the new reviewer as
"Request Sent"; and separately, past Round 1's Revisions
Uploaded "Upload" wizard completed with the file landing in Round 1's panel
while Round 2's stayed empty. The past round's status sentence was unchanged by
either mutation. Panel configs per note a (shared through the config
deep-merge); no current-round gate on these panel operations was observed.

<a id="fn-a9"></a>
**f-a9** — Mechanism and evidence in note m (the delete-time notification
pass runs while the file row still counts). Claim check
2026-07-31: flip-back plus silent task list observed on OJS
and OMP, for editor-side and author-side deletion of the only revised file;
the later file add that re-creates a task observed live on the press and
code-traced on the journal. The spec's prior revival claim was corrected by
the same check.

<a id="fn-a10"></a>
**f-a10** — Probed 2026-08-02, editorial view (Journal/Press Manager), on
rounds whose status box read "Awaiting responses from reviewers." and where
no Request Revisions decision had ever been recorded: the heading "Revisions
Uploaded" and the paragraph "These files have been submitted by the author
after revisions were requested" rendered verbatim on an OJS Review round 1
and an OMP External Review round 1; identical on an OMP Internal Review
round 1 the same day. Panel mechanism in notes a and i (the editor-side
revisions `FileManager`, ungated by round status).

<a id="fn-ojs1"></a>
**f-ojs1** — Probed 2026-07-31: on OJS, a free-text review
with a comment shared for author and editor and an editor-only comment — the
author's window rendered neither, and the decision letter's reviewer
appendix listed the recommendation only; on OMP, the
shared comment rendered (the editor-only comment absent in both apps). The
window is `authorReadReview.tpl` via `AuthorReviewerGridHandler::readReview`
(note j); the OJS-side mechanism was not traced.

<a id="fn-omp1"></a>
**f-omp1** — OMP stage roster and decision registry in note q; internal
stage entry in the workflow menu labeled `workflow.review.internalReview`
"Internal Review". The skip path (scenario 13) is the submission-stage
decision `SKIP_INTERNAL_REVIEW`, labeled with the press's send-to-external
wording. Internal-stage screens, decisions and divergences are outside this
file's scope (campaign scope ruling). Live parity probe
2026-07-31: the External Review round matched the OJS record panel-for-panel
and button-for-button, and the "Internal Review" menu entry rendered.

<a id="fn-omp2"></a>
**f-omp2** — Probed 2026-07-31: the OMP reviewer
form carried no recommendation control, the author's read-review window
listed no recommendation line, and the decision letter's reviewer appendix
printed "Recommendation:" followed by an empty value. The OJS contrast run
showed the recommendation in all three places.

<a id="fn-omp3"></a>
**f-omp3** — Claim check 2026-07-31,
UI-driven on both apps with DB verification: OJS holds one
`NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS` task row ("Revision
required."); OMP holds a `NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS` row
and no decision-type row. Mechanism: both apps create the decision task
(`PKP\decision\Repository::updateNotifications()`), but OMP's
`APP\decision\Repository::getReviewNotificationTypes()` also runs the
internal-revisions delegate, whose removal branch — finding no
internal-stage pending decision — deletes both its own type and the shared
decision row (`PendingRevisionsNotificationManager`); the external delegate
then refiles the task under `notification.type.pendingRevisions`
("Revisions to consider in {$stage}."). Wording chain and the re-created
task per note m.

## Reference — entry points & surfaces

| Entry | Path | Atom |
|-------|------|------|
| Review stage, editorial view | workflow menu → Review → Review Round {N} (editorial dashboard) | AFFW-323..325, 327..330, 332..339, 349 |
| Review stage, author view | My Submissions → submission → Review Round {N} | AFFW-350..353, 355 |
| Author read-review window | author reviewers list → "Read Review" | AFFW-487, 666..667 · GRID-010, 053 |
| Author email window | "Notifications" list → subject link | AFFW-701 (legacy list — waived), AFFW-702 |
| Editor review files selection | Files for Review panel → edit | GRID-025, 027 |
| Revisions panels | Revisions Uploaded panel (both views) | GRID-024 (waived), GRID-029 (waived) |
| Legacy author round tab | `{journal}/authorDashboard/reviewRoundInfo` (dead route) | AFFW-703..704 (waived) · GRID-011 (waived) |
| Revised-version email | template key `REVISED_VERSION_NOTIFY` | MAIL-047 |
| Round-status notice record | per-round, no surface | NOTIF-029 |
| Author revisions task | task list entry with upload action | NOTIF-031 |
| Round data shape | `reviewRounds` on the submission payload | SET-021 |
| Round history (reviewer's page) | reviews API `history` operation — surface owned by *Reviewer's review*; endpoint owned by *Author response to reviews* | API-032 (rider) |

## Reference — code anchors

- `lib/pkp/classes/submission/reviewRound/ReviewRound.php` · `ReviewRoundDAO.php` — round model, status machine
- `lib/pkp/classes/decision/DecisionType.php` + `lib/pkp/classes/decision/types/` (`RequestRevisions`, `Resubmit`, `Accept`, `Decline`, `RevertDecline`, `NewExternalReviewRound`, `CancelReviewRound`) — round transitions
- `lib/pkp/classes/submission/maps/Schema.php` — round serialization, decision availability, permission checks
- `ojs-main/classes/submission/maps/Schema.php` · `omp-main/classes/submission/maps/Schema.php` — per-app decision rosters
- `omp-main/classes/decision/types/` — internal-review decision family
- lib/ui-library `src/pages/workflow/composables/useWorkflowConfig/workflowConfigEditorialOJS.js` · `workflowConfigAuthorOJS.js` (+ OMP/OPS variants) — stage composition and guards
- lib/ui-library `src/pages/workflow/components/primary/WorkflowSubmissionStatus.vue` · `WorkflowListingEmails.vue` · `secondary/WorkflowRecommendOnlyListingRecommendations.vue`
- `lib/pkp/controllers/grid/files/review/` (`EditorReviewFilesGridHandler`, `ManageReviewFilesGridHandler`) · `lib/pkp/controllers/grid/users/reviewer/AuthorReviewerGridHandler.php` · `lib/pkp/controllers/grid/files/attachment/AuthorOpenReviewAttachmentsGridHandler.php`
- `lib/pkp/classes/submissionFile/Repository.php` — revision upload side effects
- `lib/pkp/classes/notification/managerDelegate/PendingRevisionsNotificationManager.php`
- `lib/pkp/classes/mail/mailables/RevisedVersionNotify.php`
