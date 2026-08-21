# Glossary

The vocabulary the specs use, defined so a QA or product person can read any
spec without a developer. Written in OJS voice; where OMP/OPS use a different
NAME for the same thing, the entry says "cross-app names: APP-GLOSSARY"
rather than repeating the mapping (`APP-GLOSSARY.md` owns cross-app
substitution; this file owns meanings). Specs follow TEMPLATE rule 10:
on-screen names win, first use of a coined term carries a gloss or pointer
here, and authors add missing terms as part of writing. **Settled usage**
entries record which of two competing words the specs use going forward;
shipped specs are aligned opportunistically (self-healing).

## The world

- **Site vs journal (context)** — one install hosts many *journals* plus
  site-level surfaces (site login, Administration, Site Settings, Hosted
  Journals). "Context" is the generic word for the journal-like unit.
  Cross-app names: APP-GLOSSARY (press, preprint server).
- **Section** — a journal's content grouping (e.g. Articles, Reviews); a
  section can set its own policies (abstract required, word limits, a default
  review form). Cross-app names: APP-GLOSSARY (series; OPS has one
  "Preprints" section).
- **Issue** — a journal's published collection (volume/number/year); OJS
  only. Cross-app names: APP-GLOSSARY (absence is not a synonym — OMP/OPS
  have no issues; OMP's counterpart concept is the catalog).
- **Submission** — the manuscript-plus-metadata object moving through the
  workflow; OJS voice also says "article". Cross-app names: APP-GLOSSARY
  (monograph, preprint).
- **Publication (object)** — the publishable face of a submission: title &
  abstract, contributors, galleys, scheduling. A submission carries one or
  more publications (versions); publishing acts on a publication. Its status
  can be **published** or **scheduled** (assigned to a future issue —
  scheduled counts as published for automatic follow-ups such as ORCID
  deposits).
- **Galley** — a publication's reader-facing file rendition (e.g. the PDF).
  Cross-app names: APP-GLOSSARY (publication format).
- **Contributor** — a person on the submission's author list (the
  Contributors list). Distinct from the **Author role** — the account that
  submitted and follows the submission; a contributor need not have an
  account at all.

## Roles and access

- **Role** — a named group membership in a journal (Journal Manager, Section
  Editor, Reviewer, Author, Reader…). The app's Roles settings screen is the
  source of names; specs always use the concrete on-screen name, never
  umbrellas like "editorial staff". Role LEVELS (manager / editor /
  assistant / reader) group roles by capability; "assistant-level" covers
  Copyeditor, Layout Editor, Proofreader, Funding Coordinator — panels but
  never decision buttons.
- **Site Administrator** — the install-wide admin: Administration area, site
  settings, impersonation. Takes part in a journal's workflow only through a
  journal role.
- **Guest Editor** — an editorial role assignable per submission, alongside
  Section Editor; can be deciding or recommending like any assigned editor.
- **Assigned (to the stage)** — listed on that stage's Participants panel;
  the precondition for Section Editors, Guest Editors and assistants to act
  on a submission.
- **Deciding editor** — a Journal Manager or Editor, or an assigned Section
  or Guest Editor whose participation is NOT limited to recommendations;
  the set that gets real decision buttons. **Settled usage**: specs say
  "deciding editor" / "recommending editor"; the collective "review
  managers" is retired.
- **Recommending editor (recommend-only)** — an assigned editor whose
  participation IS limited to recommendations (a per-assignment flag set in
  Stage participants): records a recommendation instead of a decision, and
  only while a deciding editor is also assigned.
- **Impersonation (Login As)** — continuing the browser session AS another
  user; total while it lasts (every action attributed to the target), exited
  via "Logout as {username}". **Settled usage**: "impersonation" is the
  concept, "Login As" the on-screen control.
- **Access-denied page** — what a signed-in user gets on a screen their role
  does not allow ("The current role does not have access to this
  operation."); signed out, the same address shows Login instead.

## Workflow

- **Stage** — one of the workflow's fixed stops: Submission → Review →
  Copyediting → Production (OJS). Fixed per app, not configurable.
  Cross-app: APP-GLOSSARY (OMP adds Internal Review; OPS has Production
  only). The **active stage** is where the submission currently sits;
  decision buttons render only there.
- **Workflow screen** — the per-submission editorial screen with its stage
  navigation; ONE shared surface for every role including the Author (role
  determines what is offered, never a separate screen). The author's reduced
  presentation of it is called the **author view** in specs.
- **Dashboard / My Submissions** — the editorial submission lists vs the
  author's own list. **Settled usage**: "Dashboard" = the editorial landing;
  authors land on "My Submissions"; "reviewer dashboard" names the
  reviewer's assignment list.
- **Decision** — a recorded editorial move that changes stage or status,
  taken through the decision wizard: Send for Review, Accept and Skip
  Review, Decline Submission, Revert Decline (Submission stage); Request
  Revisions, Accept Submission, Create New Review Round, Cancel Review
  Round, Decline (Review); Send To Production; Post the preprint (OPS).
  Specs quote the exact button label. **Queued** = the submission status
  "still awaiting a decision at this stage".
- **Declined (submission)** — the status set by Decline Submission: the
  submission stays on its stage, the label reads "Declined", buttons flip to
  **Revert Decline** (+ Delete for managers), and it lists only under the
  dashboard's Declined view.
- **Desk review** — informal name for pre-review screening on the Submission
  stage (the panel heading reads "Desk Review Tasks & Discussions"). What QA
  folk call "desk reject" is, on screen, Decline Submission while queued.

## Review

- **Review round** — the numbered container (from 1) for one pass of peer
  review: its reviewers, its files under review, its revisions, its status.
  Round 1 opens on Send for Review; later rounds only via Create New Review
  Round; rounds are never renumbered. The **current round** is the
  highest-numbered one — only it moves; past rounds stay visible.
- **Round status** — the recomputed one-sentence state box at the top of the
  review stage ("Round {N} Status"); the review-stage spec owns the sentence
  roster and precedence.
- **Review assignment** — one reviewer's engagement on one round: a row in
  the **Reviewers panel** with its own review type, dates, files, status and
  history. **Settled usage**: "Reviewers panel" (not "Reviewers table").
- **Reviewer statuses** — the row vocabulary: Request Sent, Request
  Accepted, Overdue, Request Declined, Request Resent, Review Submitted,
  Review Viewed, Complete, Reviewer Thanked, Request Cancelled.
- **Review type** — per assignment: Anonymous Reviewer/Anonymous Author,
  Anonymous Reviewer/Disclosed Author, or Open. Gates the author's access to
  the finished review — only open, completed reviews are ever listed for the
  author.
- **Review form / Free Form Review** — a structured questionnaire an
  assignment may carry (default "Free Form Review" = none); parts are marked
  for authors vs editors; changeable only until the review is submitted.
- **Files for Review / revisions** — the per-round file sets: what reviewers
  of that round are given, and what the author uploads in answer to Request
  Revisions ("Revisions Uploaded" panel; the wizard's new-round path is
  named "Resubmit for Review").
- **Reviewer pool** — the users holding a reviewer role for the stage,
  searchable in Add Reviewer (per-stage on OMP: Internal vs External).
- **Recommendation** — two senses, same screen, kept distinct in specs: the
  **reviewer's recommendation** (part of a completed review on a journal;
  shown in the row and Read Review) and an **editor's recommendation** (what
  a recommending editor records instead of a decision; shown to deciding
  editors in the Recommendation box).
- **Confirming a review** — the editor's Confirm press in Read Review: row →
  Complete, reviewer's task cleared. **Thank Reviewer** acknowledges it
  (row → Reviewer Thanked); **Revert Decision** ("Unconsider this Review")
  steps a Complete/Thanked row back.
- **Unassign vs Cancel (reviewer)** — before any response: Unassign
  Reviewer, row deleted. After a response: Cancel Reviewer, row kept as
  Request Cancelled and **Reinstate Reviewer** can restore it. **Resend
  Review Request** asks a declined reviewer to reconsider with fresh dates.
- **Log Response** — recording a reviewer's accept/decline on their behalf
  when they answered by email.
- **One-click access** — a journal setting adding a sign-in-free keyed
  review link to request/reminder emails; each reminder mints a fresh link.
- **Editorial Notes** — notes editors keep about a reviewer as a person (one
  shared text across all submissions, shown in reviewer search; never shown
  to the reviewer).

## Invitations, accounts, ORCID

- **Invitation (role invitation)** — a manager's emailed offer of one or
  more journal roles (start date, optional end, masthead visibility);
  accepted (creating/linking an account) or declined via personal links.
  States: draft (being composed) → pending → accepted / declined /
  cancelled. There is no "expired" STATE — a pending invitation past its
  deadline simply stops working (the landing page's word "expired" is a
  deadline check, not a status) and is purged. **Distinct from a review
  request**, which specs also call an "invitation" in email subjects — the
  Add Reviewer flow, not "Invite to a role".
- **Invitation Unavailable page** — the shared front door for every emailed
  personal link that no longer works ("This invitation is no longer
  available…"): answered/cancelled/expired role invitations, stale reviewer
  one-click links, used registration-validation links.
- **Masthead** — the journal's public listing of team members per role; each
  invited role row carries "Appear on the masthead" / "Does not appear";
  reviewers always appear.
- **Disabled account** — refused at sign-in ("Your account has been
  disabled…", optionally with a recorded reason); a disabled user also
  cannot be invited to a role.
- **Principal contact** — the journal's configured contact identity that
  system emails (password resets, ORCID requests, automatic reminders) are
  sent from.
- **ORCID iD: verified vs unauthenticated** — *verified* means the person
  completed ORCID's own sign-in for this install, so the iD carries an
  access token (solid icon); an iD merely typed or imported is
  *unauthenticated* (hollow icon, "(unauthenticated)" suffix). **Settled
  usage**: verified/unauthenticated (not "authenticated").
- **ORCID deposit** — the background write of a published article to each
  verified contributor's ORCID record (member API only), or of a completed
  review to the reviewer's record ("Send Review To ORCID", OJS).

## Screen furniture

- **Participants panel** — the per-stage list of assigned users; assignment
  and the recommend-only flag live in its Stage-participants forms.
- **Tasks (header panel)** — the per-user to-do list opened from the site
  header ("Review pending.", "Revision required."…), cleared by the
  corresponding action. Not to be confused with the Submission-stage
  discussions panel headed "Desk Review Tasks & Discussions".
- **Notifications (author's list)** — on the author's review stage, the list
  of emails editors sent about this submission (decision emails re-read
  here). Distinct from toast notices and from the header Tasks panel; specs
  name the surface. **Settled usage**: decision communications are "decision
  emails" (not "letters").
- **Activity log** — the submission's permanent record of editorial events
  (assignments, decisions, reminders); "logged" in specs means a row lands
  here.
- **Submission wizard** — the author's multi-step submission flow; its
  Contributors step hosts the author-side ORCID request.

## The test install (used in Canonical scenarios)

- **Seeded** — fixture data present on the test install before any test
  runs: the base journal, the account roster, sections, issues.
- **Roster** — the seeded accounts (one per permission archetype, usernames
  like `editor.diana`); shared and never mutated by tests.
- **Scratch** — a throwaway journal/submission/user created for one test and
  owned by it.
- **Mail catcher** — the test install's outgoing-mail trap; "the email
  arrives" in a scenario means it lands there.

## Reading a spec (format terms)

- **Badge** — `{OJS OMP}` on a title or scenario: which apps have the
  surface; an unmarked claim asserts "verified identical in every app that
  has it".
- **Finding / Findings register** — the spec's single home for as-built
  deviations: 🐞 defect (author's call) · ❓ needs a product ruling · ✅
  intended divergence; ⚠ in the body marks "as-built deviation here" and
  links to the entry. **Impact** is one plain word (user-visible / minor /
  invisible / latent).
- **Footnote marks** (`<sup>a</sup>`) — provenance: code anchors, probe
  dates, seeded accounts. The body never depends on them; a reader can skip
  the footnote tail and lose no behavior.
