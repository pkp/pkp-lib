# U26 adversarial verification — chunk 2: Rules & state, rules 1–9

Scope: `docs/product/specs/review-stage-and-rounds.md` **Rules 1–9** (rounds &
side-menu navigation, the round-status table + precedence, the minimum-reviews
interplay), their footnotes (`h`, `i`, `j`, `k`, `u`, `f-a2`, `f-a3`, `f-a4`) and
the cited register entries (A2, A3, A4).

Frame: QA verification of documented behavior on a local disposable test install.
All work on scratch contexts seeded through the scenario API; no seeded
`publicknowledge` / press data was touched, Mailpit not cleared.

Date: 2026-07-27. Fleets: OJS `127.0.0.1:8000`, OMP `127.0.0.1:8100`
(`localhost` is refused by `allowed_hosts` — use `127.0.0.1` for browser work).

## Environment & method

- **Scratch contexts** (scenario API `POST /index.php/index/api/v1/_test/scenarios/context`):
  - OJS `u26v2a` (id 22, `defaultReviewMode: 3`) — status-table + navigation cases.
  - OJS `u26v2b` (id 23, `defaultReviewMode: 3`) — minimum-reviews journal.
  - OMP `u26v2c` (id 9) — cross-app spot check.
  - Users are scratch throwaways (`v2ed` Journal editor = `ROLE_ID_MANAGER`,
    `v2rec1`/`v2rec2` section editors used recommend-only, `v2au` author,
    `v2rv1..3` external reviewers; password = username doubled).
- **Submission states** via `POST scenarios/submission`. The live schema is
  richer than `scenarios.md` records: `participants[].recommendOnly`,
  `reviewRounds[].reviewers[].status: completed|confirmed`, `method`,
  `decisions[].editor` are all live. OJS reviewer role key on a scratch journal
  is **`externalReviewer`** (not `reviewer`); OMP's entry decision from the
  Submission stage is **`skipInternalReview`** (`sendExternalReview` is the
  Internal-Review-stage door), and the round decision name is
  **`newExternalReviewRound`** (not `newExternalRound`).
- **Two states are not reachable through seeding** and were driven by direct
  `review_assignments` / `review_rounds` updates on scratch rows: *overdue*
  (`date_due` / `date_response_due` moved into the past), *cancelled reviewer*
  (`cancelled = 1`), the legacy stored status 3, and the
  `RETURNED_TO_REVIEW` stamp (16). Every other case came from real service
  paths. The minimum setting was written to `journal_settings.numReviewsPerSubmission`
  on the scratch journal (its screen is U29's).
- **Locator used everywhere**: Playwright, page
  `/index.php/<context>/en/dashboard/{editorial|mySubmissions}?workflowSubmissionId=<id>`;
  inside `[data-cy="active-modal"]`: workflow-column heading = first `h2`
  (`textContent`, since CSS renders it upper-case); status block = the
  `div` in `[data-cy="workflow-primary-items"]` whose direct `h3` matches
  `/Status/`, reading `h3` + every `p` in order; side menu = `nav button|a`;
  panels = `h2, h3` in the primary column; actions =
  `[data-cy="workflow-action-items"] button`; side column =
  `[data-cy="workflow-secondary-items"] h2, h3`. Round switching = clicking the
  side-menu entry by exact text.
- Note: `[data-cy="active-modal"]` is present but **never "visible"** to
  Playwright's visibility check; wait on `state: 'attached'` plus a text
  predicate. (Useful for the test authors; not a product finding.)

---

## Rule 1 — numbering (round 1 on entry; each new-round decision adds the next number; never renumbered or reused)

Cases driven (OJS `u26v2a`, OMP `u26v2c`):

| Case | Seed | Observed |
|---|---|---|
| entry creates round 1 | `sendExternalReview` only (subm. 247) | side menu gains one entry "Review Round 1"; `review_rounds` holds one row, round 1 |
| successive rounds | `sendExternalReview` + 2× `newExternalReviewRound` (subm. 269) | rounds 1, 2, 3 |
| **cancel then create** | `sendExternalReview`, `newExternalReviewRound`, `cancelReviewRound`, `newExternalReviewRound` (subm. 270) | side menu "Review Round 1", "Review Round 2"; DB: round ids 299 (round 1) and **301 (round 2)** — the cancelled round was id 300, also numbered 2 |
| same on OMP | subm. 180 | External Review menu "Review Round 1", "Review Round 2"; round ids 179, **181**, both stage 3 |

**Verdict: DISAGREE (partial).** Sentences 1–2 hold. "Rounds are never
renumbered or reused" does not: after a round is cancelled, the next
"Create New Review Round" issues **the same round number again** (a new round
record, but the number the cancelled round carried). Both apps.

Observed behavior, product voice: *A cancelled round's number returns to the
pool. Cancelling Review Round 2 and then creating a new round produces a second
"Review Round 2" — a different round with the same name, so the side menu and
the round headings give the reader no sign that an earlier Round 2 existed.*
(Mechanism, for the footnote: `NewExternalReviewRound` numbers from the
submission's current last round, and `CancelReviewRound` deletes the round row.)

Suggested wording: "Rounds are numbered in sequence and never renumbered while
they exist; a cancelled round releases its number, and the next round created
takes it again ⚠."

## Rule 2 — one side-menu entry per round; heading; scoping; latest preselected

Cases driven: single round (247, 248…), three rounds (269), post-cancel (270),
post-decline (262), submission moved on to Copyediting (261, 271), min-reviews
journal two rounds (281), OMP (178, 179, 180).

| Claim | Observed |
|---|---|
| entry label "Review Round N" | verbatim, OJS and OMP (OMP under the "External Review" stage entry) |
| workflow column heading | `"Workflow: Review (Round 1)"` … `"(Round 3)"` verbatim; OMP `"Workflow: External Review (Round 1)"`. Rendered upper-case by CSS; the DOM text is the spec's casing |
| submission header short form | `Review (Round 1)` in the modal header |
| latest round preselected | 269 opens on Round 3; 270 (after cancel) opens on the re-created Round 2; 281 opens on Round 2 |
| panels scoped to the round | 281: Round 1's "Reviewers" lists both confirmed reviewers, Round 2's lists none; each round's file panels list their own rows |

**Verdict: AGREE.**

Context (not a disagreement): "the latest round is preselected" is what happens
when the Review stage is what opens. When the submission has moved on
(Copyediting), the workflow opens on the **active stage** — heading
`"Workflow: Copyediting"` — and a round is shown only after the reader picks it
from the side menu (stage selection is U24's).

## Rule 3 — editorial panel roster per round

Cases driven: 247 (empty round), 251, 255, 258, 259, 269 (round 3), 281; OMP 178.

Observed primary column, in render order:
`Round N Status`, `Revisions Uploaded`, `Files for Review`, `Reviewers`,
`Author Response`, `Review Tasks & Discussions`.
Side column: `Participants`; plus `Recommendation` on 258/259/279 (a
recommendation recorded) and never before one exists.
OMP 178: identical minus `Author Response` (OMP5).

**Verdict: AGREE** (titles verbatim; the "Recommendation" condition matches
Rule 11's). "Reviewers Suggested by Author" was not re-driven this chunk (needs
the U31 setting plus a suggestion; footnote `i` carries the earlier evidence).

## Rule 4 — author panel roster per round

Cases driven as the submitting author on `dashboard/mySubmissions`: 249 (unread
review), 250 (overdue), 252 (declined-only), 277 (min-reviews journal).

Observed primary column, in render order:
`Round 1 Status`, `Reviewers` (only where the round has a completed open
review — absent on 252), `Revisions Uploaded`, `Review Tasks & Discussions`.
No side column, in every case.

**Verdict: AGREE on the roster and the no-side-column claim.**
Context: the rule lists "Revisions Uploaded" before "Reviewers"; on screen
**"Reviewers" renders above "Revisions Uploaded"**. If the roster sentence is
read as an order, it is wrong; if read as a set, it is right. Suggest not
implying order (or swapping the two names).

## Rule 5 — only the latest round accepts decisions; past rounds read "Status" / advanced-to-next-round

Cases driven: 269 rounds 1 and 2 (review stage still active), 270 round 1,
281 round 1 (min-reviews journal), 271 rounds 1 and 2 (submission accepted, now
in Copyediting), 261 round 1 (single round, accepted); OMP 179.

| Case | Heading | Text | Decision buttons |
|---|---|---|---|
| past round, review stage active (269 r1/r2, 270 r1, 281 r1) | `Status` | `The submission has been advanced to the next round of review` | none |
| **past round, submission in a later stage** (271 r1) | `Status` | `The submission advanced to the next review round, was accepted, and is currently in the Copyediting stage.` | none |
| **latest round, submission in a later stage** (271 r2, 261 r1, OMP 179 r1) | `Status` | `The submission is currently in the Copyediting stage.` | none |
| panel-level controls on a past round (281 r1) | — | Upload, Upload/Select Files, Add Reviewer, Request Response, Add all rendered | — |

**Verdict: DISAGREE (partial).** "No decision buttons on an earlier round",
"headed plain Status", and "panel-level controls remain offered" all hold. The
verbatim past-round text holds **only while the Review stage is the active
stage**. Two further lines exist, and the second of them appears on the *latest*
round — which the rule says is the round that accepts decisions.

Observed behavior, product voice: *Once the submission leaves the Review stage,
every round of that stage reports the stage it moved to instead of a round
status: the latest round reads "The submission is currently in the Copyediting
stage." and earlier rounds read "The submission advanced to the next review
round, was accepted, and is currently in the Copyediting stage." Neither round
offers decision buttons.* (The earlier-round sentence asserts "was accepted"
whichever way the submission left the stage — worth its own look; not driven
against a decline-then-move path here.)

## Rule 6 — the status table

Every line below is the verbatim on-screen paragraph under the round-status
heading (`Round N Status`), read as `textContent`. All OJS `u26v2a` unless
noted.

| Table row | Case driven | Observed line | Verdict |
|---|---|---|---|
| "Waiting for reviewers to be assigned." | 247 fresh round; 269 round 3 | verbatim | agree |
| "Awaiting responses from reviewers." | 248 one accepted reviewer | verbatim | agree |
| "New reviews have been submitted." | 249 review submitted, unconfirmed | verbatim | agree |
| "A review is overdue." | 250 one submitted-unread + one past `date_due` | verbatim | agree (outranks the two rows above) |
| **"All reviews are confirmed and a decision is needed."** | 251 one confirmed review | verbatim | agree — **live-observed, no longer code-derived** |
| same row, **declined-only round** | 252 (sole reviewer declined the invitation); OMP 178 | **verbatim, with zero confirmed reviews** | **When column DISAGREES** |
| same row, **cancelled-only round** | 253 (sole reviewer's assignment cancelled) | **verbatim, with zero confirmed reviews** | **When column DISAGREES** |
| "Awaiting recommendations from editors." | 255 two recommend-only participants, none recorded, one confirmed review | verbatim | agree |
| same row, **round with no reviewers** | 256 recommend-only participant, no reviewer ever assigned | **"Waiting for reviewers to be assigned."** | **When column DISAGREES** |
| **"New editorial recommendations have been submitted."** | 258 one of two recommenders recorded, one review still in flight | verbatim | agree — **live-observed, no longer code-derived** |
| "All recommendations are in and a decision is needed." | 259 sole recommender recorded, reviewer overdue | verbatim | agree (A3 as written) |
| "Revisions have been requested." | 260 `requestRevisions`, reviewer overdue | verbatim | agree |
| "Revisions requested from the author to be taken to a new review round." | 313 `resubmit`, review submitted-unread | verbatim | agree |
| "Submission declined." | 262 `decline` with a review in flight | verbatim | agree |
| **"Submission accepted."** | 261 `accept` (single round); 271 `accept` after round 2; OMP 179 | **never shown — the round reads "The submission is currently in the Copyediting stage."** | **DISAGREE** |
| "Returned back to review." | 306 stamp 16 + one confirmed review | verbatim | agree |
| same row, empty round | 307 stamp 16, no assignments | "Waiting for reviewers to be assigned." | agree (A4) |
| "Sent for external review." | 272 stored status 3 on a round with no reviewers | verbatim | agree (final; outranks the empty-round line) |
| "Minimum number of confirmed reviews required: N." | see Rule 9 | verbatim (`N` = 2) | agree |
| "Minimum required number of reviews have been confirmed. A decision is needed." | see Rule 9 | verbatim | agree |
| "Revisions have been submitted and a decision is needed." | **not driven** — needs an author revision upload, beyond the scenario API | — | not re-verified this chunk (footnote `j` carries the earlier live evidence) |
| "Revisions submitted. A new review round needs to be created." | **not driven**, same reason | — | same |

Disagreements in product voice:

1. **The all-confirmed line is also the round's fall-through.** *A round whose
   reviewers all declined the invitation — or whose only assignment was
   cancelled — reads "All reviews are confirmed and a decision is needed."
   though no review was ever submitted or confirmed. The line is what the round
   falls back to whenever it has assignments and none of them is outstanding.*
   Suggested When column: "the round has review assignments and none is
   outstanding — every active review confirmed, or every assignment declined or
   cancelled ⚠".
2. **Pending recommendations need a reviewer.** *A round with a recommend-only
   participant and no reviewer assigned reads "Waiting for reviewers to be
   assigned.", not "Awaiting recommendations from editors."; the
   awaiting-recommendations line appears only once the round has at least one
   review assignment and none is outstanding.* Suggested When column: "…
   recommend-only participants assigned, none has recommended, and the round's
   reviews are settled (a round with no reviewers shows the waiting line
   instead)".
3. **"Submission accepted." has no on-screen home.** *Accepting a submission
   moves it to Copyediting, and from that moment the round's status area reports
   the stage rather than the round: "The submission is currently in the
   Copyediting stage." The accepted round status is stored and serialized, but
   no workflow screen displays it.* This is the same shape as the "Sent for
   external review." row (stored, not produced by a current decision) but
   arrived at from the opposite side — the decision exists, the display does
   not. Suggested handling: move the row's note to "stamped by Accept
   Submission; the workflow screen shows the submission's new stage instead ⚠"
   and consider a 🐞/❓ register entry.

Also verified for Rule 6's opening sentence: the round-status heading is
`Round N Status` on the latest round and `Status` on past rounds (Rule 5), and
each round showed exactly one status line except under the minimum setting,
where two lines show (Rule 9) — consistent with the rule as written.

Context (mechanism worth a footnote): the "decision-set" / "computed" split is
about how a status is *derived*, not about how it is *stored* — the stored value
is overwritten by later activity. Seeding `accept` → `backFromCopyediting` on a
submission and then adding and confirming a reviewer left `review_rounds.status`
at 9, the returned-to-review stamp gone from storage
(`ReviewRoundDAO::updateStatus($round, null)` recomputes and persists on
reviewer, file and decision activity). A4 says the stamp is outranked at display
time; it can also be erased.

## Rule 7 — precedence

Collisions constructed (beyond the documented recommendations-mask):

| Collision | Case | Winner |
|---|---|---|
| overdue vs submitted-unread | 250 | "A review is overdue." |
| decision-set revisions vs overdue reviewer | 260 | "Revisions have been requested." |
| decision-set resubmit vs submitted-unread review | 313 | "Revisions requested from the author…" |
| declined submission vs review in flight | 262 | "Submission declined." |
| legacy sent-to-external vs empty round | 272 | "Sent for external review." |
| **partial recommendations vs review in flight** | 258 | **"New editorial recommendations have been submitted."** |
| all recommendations vs overdue review | 259 | "All recommendations are in and a decision is needed." (A3) |
| **pending recommendations vs empty round** | 256 | **"Waiting for reviewers to be assigned."** |
| returned-to-review stamp vs empty round | 307 | "Waiting for reviewers to be assigned." (A4) |
| returned-to-review stamp vs settled review | 306 | "Returned back to review." (A4) |

**Verdict: DISAGREE on the stated order.** Sentences 1–2 (finality of
accepted/declined/sent-on; the revisions pair flipping only between its two
forms) hold. The order sentence — "no reviewers → overdue → unread reviews →
reviews under way → recommendation states → all-confirmed" — is right for
*one* of the three recommendation states. Both the "some, not all" and the
"all in" recommendation lines are evaluated **before** any reviewer state, so
they outrank overdue, unread and in-flight reviews; only "Awaiting
recommendations from editors." sits where the rule puts it.

Observed behavior, product voice: *Once any recommend-only participant has
recorded a recommendation, the round reports the recommendation state and stops
reporting the reviewers — an overdue review, a review still out and an unread
review are all hidden behind it. Until the first recommendation arrives the
reviewer states win, and a round with no reviewers at all reports "Waiting for
reviewers to be assigned." whatever the recommenders are doing.*

Suggested order for the rule: "no reviewers → recommendations recorded (some or
all) → overdue → unread reviews → reviews under way → awaiting recommendations
→ returned-to-review stamp → all-confirmed ⚠ [A3]".

**A3's scope also needs widening** (claim-vs-context): the entry reads "Once
every recommend-only participant has recommended…" — case 258 shows the masking
starts with the **first** recommendation, while other recommenders are still
pending. The table already flags both rows with ⚠ A3, so only the entry's own
sentence is short.

## Rule 8 — the status line is identical for every role

Cases driven on the same submissions, three role vantage points:

| Submission | Deciding editor (v2ed) | Recommend-only participant | Author |
|---|---|---|---|
| 249 unread review | "New reviews have been submitted." | — | **identical** |
| 250 overdue | "A review is overdue." | — | **identical** |
| 252 declined-only | "All reviews are confirmed and a decision is needed." | — | **identical** |
| 258 partial recommendations | "New editorial recommendations have been submitted." | identical for both the recommender who recorded (v2rec1) and the one who has not (v2rec2) | — |
| 259 all recommendations | "All recommendations are in and a decision is needed." | identical (v2rec1) | — |
| 277 minimum met | both lines | — | **both lines identical** |

**Verdict: AGREE** (and A2's symptom re-observed verbatim: the author reads the
editor phrasing for the reviews-ready and overdue states). The
`editor.submission.roundStatus.recommendationMadeByYou` string, which would be a
per-role variant, is used only by the dashboard's editorial-activity list, not
by this screen.

## Rule 9 — minimum confirmed reviews

Journal `u26v2b` with `numReviewsPerSubmission = 2`, freshly created for this
chunk. Cases and the verbatim paragraph sequence:

| Case | Seed | Line 1 | Line 2 |
|---|---|---|---|
| below minimum, review in flight | 275 (1 accepted) | `Minimum number of confirmed reviews required: 2.` | `Awaiting responses from reviewers.` |
| below minimum, everything confirmed | 276 (1 confirmed) | same prompt | **(none)** |
| minimum met | 277 (2 confirmed) | same prompt | `Minimum required number of reviews have been confirmed. A decision is needed.` |
| minimum met + a third review overdue | 278 | same prompt | `Minimum required number of reviews have been confirmed. A decision is needed.` |
| **minimum met + all recommendations in** | 279 | same prompt | **`All recommendations are in and a decision is needed.`** |
| **minimum met + revisions requested** | 280 | same prompt | **`Revisions have been requested.`** |
| new round after a satisfied round | 281 round 2 | same prompt | `Waiting for reviewers to be assigned.` |
| past round under the setting | 281 round 1 | **(no prompt line)** | `The submission has been advanced to the next round of review` |
| author's view | 277 as the author | same prompt | same minimum-met line |

**Verdict: DISAGREE (partial).** The rewritten behavior is right in its main
line: the prompt is a first line **above** the status, the ordinary status
continues below it while the minimum is unmet, and the ordinary line is dropped
when every review is confirmed but under N. The final sentence — "Once N reviews
are confirmed the round reads 'Minimum required number of reviews have been
confirmed. A decision is needed.'" — is not unconditional.

Observed behavior, product voice: *The minimum-met line replaces the ordinary
status only while the round's status is one of the reviewer-activity ones
(waiting for reviewers, awaiting responses, new reviews, overdue, all
confirmed). Once the round is reporting a recommendation state or a
decision-set state — revisions requested, resubmit requested — that line is what
shows beneath the prompt, even with the minimum met.* One consequence worth
noting alongside A3: with the minimum met, an **overdue** review is also
replaced by the minimum-met line (case 278).

Two further scope facts (claim-vs-context, both consistent with the code but
unstated in the rule):

- The count is **per review round**, though the setting is named per submission:
  a new round starts the count again (281 round 2 shows the prompt with zero
  confirmed reviews, after round 1 had two). Suggest "per round" in Rule 9 or in
  the Settings bullet.
- **Past rounds show no prompt line at all** — a past round under the setting
  reads only the advanced-to-next-round line. Rule 9 says "the round adds a
  first line"; suggest "the current round".

## Cross-app

OMP (`u26v2c`, External Review) reproduced every claim tested there:
heading `Workflow: External Review (Round N)`, round entries labelled
`Review Round N`, the declined-only round reading "All reviews are confirmed and
a decision is needed.", the accepted submission's round reading "The submission
is currently in the Copyediting stage.", and the round-number reuse after a
cancel (round ids 179 / 181, both numbered 1 / 2). Panel roster identical minus
"Author Response" (OMP5). No OJS/OMP divergence found in rules 1–9.

## Proposed changes (for the merge agent — I did not edit the spec)

1. **Rule 1** — replace "Rounds are never renumbered or reused" with the
   cancel-releases-the-number behavior (⚠, both apps). New register candidate:
   🐞/❓ "A cancelled round's number is reissued to the next round".
2. **Rule 4** — do not imply panel order, or list "Reviewers" before "Revisions
   Uploaded".
3. **Rule 5** — qualify the past-round text with "while the Review stage is the
   active stage", and add the two later-stage lines (including the latest
   round's).
4. **Rule 6 table** — three When-column corrections (all-confirmed fall-through
   incl. declined/cancelled-only rounds; awaiting-recommendations needs an
   assignment; "Submission accepted." is stamped but never displayed, ⚠).
5. **Rule 7** — restate the order with the two recommendation states ahead of
   the reviewer states.
6. **A3** — widen from "every recommend-only participant" to "any
   recommendation recorded".
7. **Rule 9** — bound the minimum-met sentence to the reviewer-activity
   statuses; note per-round counting and the absence of the prompt on past
   rounds.
8. **A4 / footnote `j`** — optional: note that the stamped status is not only
   outranked at display time but overwritten in storage by later round activity.

Nothing in this chunk needs the private routing path.
