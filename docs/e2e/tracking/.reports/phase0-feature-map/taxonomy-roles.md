# Feature taxonomy draft — role-and-journey lens

Draft input to the Phase-0/1 FEATURE-MAP (this file is a working report, NOT the map).
Method: started from the actors (reader, author, reviewer, editor/section editor,
journal manager, subscription manager, site admin, assistants) and enumerated what each
comes to the application to accomplish; features are those jobs-to-be-done clustered by
user intent, then cross-checked against every atlas modality (routes, grids, vue, api,
notif, mail, jobs, settings, plugins, AFFW/AFFM/AFFR/AFFU). TEMPLATE rules 8–10 applied:
mechanics live in the mechanism's home feature; a workflow stage is ONE shared feature
covering every participant on the screen (author and assistants included) — the role
lens was used to FIND features and check completeness, never to split a shared screen.
Atom IDs listed are REPRESENTATIVE (5–15 spanning modalities), not a full crosswalk.
CLI atoms appear only as "(ref)" — per the 2026-07-27 ruling they never seed features.

Built blind from atlas + canon docs only (RUNBOOK "Rebuilding a feature from scratch");
no prior FEATURE-MAP or git history consulted.

**Tally**: 60 features — 14 H · 35 M · 11 L. Common-scenario budget at tier midpoints
≈ 445 per app + app specifics; inside the ≤700/app ceiling but without much slack —
the H list should be re-examined at map time (see disagreement register, item 8).

Actor → feature index (completeness check, not structure): every actor's jobs land in
at least one feature — Reader → 7–18; Author → 19, 20, then shared workflow features
22–43; Reviewer → 26 (+25 as subject, 28, 5); Editor/Section Editor → 21–43;
Journal Manager → 10, 13, 44–57; Subscription Manager → 17, 18; Site Admin → 57–60
(+50, 53); Copyeditor/Layout Editor/Proofreader assistants → participants of 30, 31,
36, 37 (no per-role features, per rule 9).

---

## A. Account & identity (every role)

### 1. Sign-in & sessions — {OJS OMP OPS} — M
- Intent: a user signs in and out, recovers a lost password, and stays securely in session.
- Atoms: ROUTE-016 · AFFU-001 · AFFU-003 · AFFU-007 · AFFU-036 · AFFU-040 · AFFU-044 · AFFU-049 · MAIL-030 · SET-052 · SET-057
- Note: also mechanism home for "Log in as / log out as" (ops on ROUTE-016; AFFW-467, AFFM-105 offering surfaces are deltas) and the confirm-password gate (AFFM-191 usage is a site-admin delta). See disagreement item 3.

### 2. Registration & account validation — {OJS OMP OPS} — M
- Intent: a visitor creates an account (optionally self-registering as reader/reviewer), validates it by email, and activates it.
- Atoms: ROUTE-030 · AFFU-009 · AFFU-017 · AFFU-019 · AFFU-021 · AFFU-029 · AFFU-035 · MAIL-058 · MAIL-059 · JOB-053 · SET-053 · SET-057
- Note: whether registration is open at all is Users & roles' Site-access form (AFFM-116) — cross-link, not a claim.

### 3. User profile — {OJS OMP OPS} — M
- Intent: a user maintains their identity, contact, roles, public profile, password, notification preferences and API key.
- Atoms: ROUTE-029 · GRID-065 · AFFU-053 · AFFU-061 · AFFU-069 · AFFU-078 · AFFU-084 · AFFU-089 · AFFU-093 · AFFU-096 · MAIL-003 · SET-027
- Note: notification-preference EFFECTS are verified where the notification fires (owning features); this feature owns the tab.

### 4. ORCID integration — {OJS OMP OPS} — M
- Intent: users and authors connect and verify ORCID iDs; the app requests verification and deposits works/reviews.
- Atoms: ROUTE-021 · API-029 · AFFU-099 · AFFU-104 · AFFU-106 · AFFU-108 · MAIL-027 · MAIL-029 · JOB-017 · JOB-019 · JOB-033 · PLUG-021 · AFFM-117 · AFFM-222
- Note: OPS carries ORCID as a bundled plugin where OJS/OMP have it in core (PLUG-021) — install-fact divergence, one feature.

### 5. Notifications center — {OJS OMP OPS} — M
- Intent: a signed-in user sees, triages, and unsubscribes from in-app and email notifications.
- Atoms: ROUTE-020 · GRID-039 · GRID-040 · GRID-064 · AFFU-111 · AFFU-113 · AFFU-118 · AFFU-121 · NOTIF-001 · NOTIF-003
- Note: individual NOTIF-* types are claimed by the features that trigger them; this feature owns the panel, unsubscribe flow and toast plumbing.

### 6. User invitations — {OJS OMP OPS} — M
- Intent: a manager invites someone to a role; the recipient accepts (creating/linking an account) or declines.
- Atoms: ROUTE-013 · ROUTE-014 · API-024 · VUE-001 · VUE-011 · VUE-052 · AFFU-122 · AFFU-126 · AFFU-130 · AFFM-099 · AFFM-118 · MAIL-055 · JOB-013 · SET-064
- Note: send-wizard (management) + accept flow (user) kept as ONE journey — QA tests them end-to-end. Reviewer one-click access invites are feature 25's delta.

## B. Reader & public site

### 7. Public site & journal pages — {OJS OMP OPS} — M
- Intent: a visitor reads the journal's public pages — home, about, masthead, history, contact, submissions info, privacy, information-for pages, custom/static pages.
- Atoms: ROUTE-001 · ROUTE-002 · ROUTE-038 · ROUTE-039 · AFFR-001 · AFFR-010 · AFFR-019 · AFFR-022 · AFFR-025 · AFFR-032 · AFFR-033 · AFFR-035 · AFFR-096 · AFFR-098 · PLUG-027
- Note: information pages (ROUTE-039/061, AFFR-041) are {OJS OMP}; OPS absence handled inside the spec. Sitemap (ROUTE-025) claimed here as a discovery surface.

### 8. Navigation menus — {OJS OMP OPS} — M
- Intent: a manager assembles the site's menus (including custom pages); every visitor navigates by them.
- Atoms: GRID-037 · GRID-038 · API-028 · ROUTE-019 · AFFM-028 · AFFM-031 · AFFM-035 · AFFM-218 · VUE-066 · AFFR-003 · AFFR-008 · SET-017 · SET-018 · SET-056
- Note: VUE-042 (unmounted NavigationMenuManager component) NOT claimed — see UNASSIGNED.

### 9. Website appearance, blocks & integrations — {OJS OMP OPS} — M
- Intent: a manager shapes how the site looks — theme, logos, sidebar blocks, footer, date formats, third-party tracking.
- Atoms: AFFM-018 · AFFM-019 · AFFM-041 · AFFM-043 · AFFM-223 · AFFM-224 · AFFR-011 · AFFR-012 · AFFR-091 · AFFR-093 · PLUG-002 · PLUG-010 · PLUG-015 · PLUG-049
- Note: content-bearing blocks live with their content feature (subscription block → 17, information block → 7, browse block → 45, language toggle → 47); this feature owns block placement mechanics.

### 10. Announcements — {OJS OMP OPS} — M
- Intent: a manager publishes news items; readers browse them and subscribers get notified.
- Atoms: ROUTE-004 · GRID-007 · API-008 · VUE-092 · AFFR-028 · AFFR-030 · AFFM-129 · AFFM-134 · AFFM-225 · MAIL-001 · JOB-014 · NOTIF-008 · PLUG-007 · SET-002
- Note: site-level announcements (AFFM-225..227) are a scope delta of the same feature.

### 11. Issues & the archive — {OJS} — H
- Intent: an editor/manager assembles, orders, publishes and unpublishes issues; readers browse the current issue and back archive.
- Atoms: ROUTE-040 · ROUTE-041 · GRID-069 · GRID-070 · GRID-085 · API-053 · AFFM-243 · AFFM-245 · AFFM-247 · AFFM-258 · AFFR-042 · AFFR-043 · MAIL-060 · JOB-031 · NOTIF-055 · SET-031
- Note: assigning a submission TO an issue during scheduling belongs to feature 33 (the screen is the publish modal); the TOC's view of the result is owned here. Issue open-access date + OpenAccessNotify (MAIL-061, JOB-058, NOTIF-066) claimed here; the subscription context is feature 17's.

### 12. Published article page & reading — {OJS OMP OPS} — H
- Intent: a reader lands on a published article/preprint, reads its metadata, versions and references, and views/downloads galleys.
- Atoms: ROUTE-033 · ROUTE-080 · ROUTE-081 · AFFR-046 · AFFR-047 · AFFR-048 · AFFR-053 · AFFR-057 · AFFR-061 · AFFR-066 · AFFR-081 · AFFR-082 · PLUG-008 · PLUG-017 · PLUG-022 · PLUG-025
- Note: OMP's analogue is the catalog book page — a CHARTER out-of-scope surface; proposed treatment: spec covers OJS+OPS, OMP absence paragraph pointing at out-of-scope catalog (disagreement item 6). Meta-tag injectors (PLUG-014/016) and PFL (PLUG-023) claimed here as page furniture.

### 13. Reader comments & moderation — {OJS OMP OPS} — M
- Intent: readers comment on published work; managers approve, hide and handle reports.
- Atoms: API-012 · VUE-010 · VUE-082 · VUE-083 · AFFR-058 · AFFM-052 · AFFM-141 · AFFM-144 · AFFM-146 · NOTIF-052 · NOTIF-053 · ROUTE-017
- Note: kept as one feature (post → moderate is one QA journey); the enable switch (AFFM-052) claimed here, not by Website settings.

### 14. Search — {OJS OMP OPS} — M
- Intent: a visitor searches published content; the index stays current as content changes.
- Atoms: ROUTE-024 · ROUTE-048 · AFFR-078 · AFFR-083 · AFFR-084 · AFFR-085 · JOB-027 · SET-054 · CLI-029 (ref)
- Note: search-engine-indexing settings (meta headers) belong to feature 49.

### 15. Web feeds — {OJS OMP OPS} — L
- Intent: a reader subscribes to RSS/Atom feeds of new content.
- Atoms: PLUG-030 · AFFR-094 · AFFR-095 · ROUTE-037 · ROUTE-059 · ROUTE-076
- Note: gateway page handlers claimed here for feed dispatch; LOCKSS/CLOCKSS ops of ROUTE-037 belong to feature 49.

### 16. OAI-PMH harvesting — {OJS OMP OPS} — L
- Intent: an aggregator harvests the journal's metadata over OAI in the configured formats.
- Atoms: ROUTE-044 · ROUTE-064 · ROUTE-079 · PLUG-036 · PLUG-037 · PLUG-038 · PLUG-039 · PLUG-040 · PLUG-013 · SET-055
- Note: API-only-style surface; scenarios are protocol probes, so L despite many format atoms.

### 17. Subscriptions — {OJS} — H
- Intent: a subscription manager sells and manages individual/institutional subscriptions; subscribers get access to restricted content and expiry notices.
- Atoms: ROUTE-046 · ROUTE-052 · ROUTE-032 · GRID-080 · GRID-081 · GRID-084 · GRID-087 · AFFM-172 · AFFM-176 · AFFM-239 · AFFM-241 · AFFR-090 · AFFR-099 · AFFR-101 · MAIL-063 · MAIL-067 · JOB-059 · PLUG-006 · PLUG-048
- Note: owns access ENFORCEMENT on restricted galleys (AFFR-047 restricted state; AFFM-250/256 issue-access surfaces stay with feature 11, cross-linked). Subscription Manager role's whole job lives here + 18.

### 18. Publication fees & payment methods — {OJS} — M
- Intent: a manager configures fees and a payment method; authors/readers pay APCs and one-off fees.
- Atoms: ROUTE-045 · API-005 · API-051 · AFFM-094 · AFFM-181 · AFFM-182 · AFFW-231 · AFFW-232 · MAIL-062 · MAIL-075 · NOTIF-036 · NOTIF-047 · PLUG-041 · PLUG-042 · AFFR-104
- Note: paymethod plugins are shared with OMP only for its (out-of-scope) direct sales — badge stays {OJS}, plugin sharing noted in spec.

## C. Author & submission intake

### 19. Making a submission — {OJS OMP OPS} — H
- Intent: an author starts, fills, saves, reconfigures, cancels and finally submits a manuscript through the wizard.
- Atoms: ROUTE-027 · VUE-023 · VUE-029 · VUE-081 · VUE-100 · AFFW-065 · AFFW-069 · AFFW-077 · AFFW-095 · AFFW-097 · AFFW-104 · AFFW-113 · AFFW-133 · MAIL-049 · MAIL-053 · NOTIF-012 · SET-025
- Note: wizard file/contributor panels are instantiations of features 36/39 (gates tested here, mechanics there). OMP chapters step (AFFW-123/124) → out of scope; OPS galleys step (AFFW-125/126) is an OPS divergence claimed here.

### 20. My Submissions (author dashboard) — {OJS OMP OPS} — M
- Intent: an author tracks all their submissions, finishes incomplete ones, and opens the shared workflow view.
- Atoms: ROUTE-005 · ROUTE-008 · VUE-003 · AFFW-027 · AFFW-034 · AFFW-037 · AFFW-040 · AFFW-048 · AFFW-701 · AFFW-702 · AFFW-706
- Note: per rule 9 this owns only the list and the entry route; everything behind "View" belongs to workflow features.

### 21. Submissions dashboard (editorial) — {OJS OMP OPS} — H
- Intent: editors, managers and assistants triage submission lists — views, filters, search, activity indicators, bulk cleanup.
- Atoms: ROUTE-007 · ROUTE-008 · VUE-003 · VUE-075 · AFFW-001 · AFFW-015 · AFFW-020 · AFFW-028 · AFFW-036 · AFFW-041 · AFFW-045 · AFFW-051 · AFFW-062 · AFFW-063 · API-006
- Note: the reviewer "Review Assignments" view of the same page went to feature 26 (disagreement item 4).

## D. The shared workflow screen (each stage = one feature, all participants)

### 22. Workflow page & stage navigation — {OJS OMP OPS} — M
- Intent: any participant (author included) opens a submission's workflow, moves between stages/versions, and sees what their role may access.
- Atoms: ROUTE-031 · ROUTE-053 · VUE-012 · AFFW-226 · AFFW-229 · AFFW-233 · AFFW-235 · AFFW-244 · AFFW-251 · AFFW-254 · AFFW-280 · AFFW-283 · AFFW-285 · AFFW-707
- Note: mechanism home for stage-access gating (`accessibleStages`) and the header/menu shell; per-stage panel rosters belong to stage features.

### 23. Submission stage — {OJS OMP OPS} — M
- Intent: on a new submission, the team reviews intake files, assigns people, and takes the first decision (send to review / skip / decline).
- Atoms: AFFW-286 · AFFW-288 · AFFW-290 · AFFW-291 · AFFW-293 · AFFW-295 · AFFW-302 · GRID-032 · GRID-033 · MAIL-009 · MAIL-016 · NOTIF-014 · NOTIF-039
- Note: OMP's decision routing through skip-internal (AFFW-296..301) stays a register divergence — parameterization, not a new feature. The Internal Review STAGE itself is out of scope (CHARTER).

### 24. Review stage — rounds, revisions & decisions — {OJS OMP} — H
- Intent: editors run review rounds; authors follow progress, upload revisions and read open reviews; decisions move the submission on.
- Atoms: AFFW-323 · AFFW-325 · AFFW-332 · AFFW-334 · AFFW-340 · AFFW-342 · AFFW-350 · AFFW-352 · AFFW-355 · GRID-024 · GRID-025 · GRID-027 · GRID-053 · MAIL-008 · MAIL-013 · NOTIF-023 · SET-021 · VUE-086
- Note: ONE feature for editor + author sides of the same screen (rule 9). Recommend-only controls (AFFW-340..344, MAIL-032) owned here. OPS lacks the stage entirely (absence paragraph).

### 25. Finding & managing reviewers — {OJS OMP} — H
- Intent: an editor finds, invites, edits, reminds, thanks, unassigns and reinstates reviewers on a round.
- Atoms: GRID-086 · VUE-016 · VUE-046 · VUE-068 · AFFW-213 · AFFW-221 · AFFW-486 · AFFW-488 · AFFW-496 · AFFW-624 · AFFW-626 · AFFW-634 · AFFW-645 · MAIL-044 · MAIL-042 · JOB-012 · JOB-054 · AFFU-205 · NOTIF-019
- Note: one-click reviewer access (AFFU-205..209) claimed here as the invite delta of feature 6's mechanics.

### 26. Doing a review (reviewer's journey) — {OJS OMP} — H
- Intent: a reviewer responds to a request, works through the 4-step wizard (or declines), submits a review, and revisits past rounds.
- Atoms: ROUTE-022 · ROUTE-047 · VUE-009 · VUE-037 · VUE-076 · AFFU-138 · AFFU-143 · AFFU-149 · AFFU-153 · AFFU-159 · AFFU-167 · AFFU-176 · AFFU-192 · AFFU-197 · AFFU-203 · MAIL-035 · MAIL-036
- Note: includes the reviewer's dashboard list (AFFU-197..204) — see disagreement item 4. Reviewer discussions (AFFU-181..191) are feature 37's instantiation, gates tested here.

### 27. Review setup & review forms — {OJS OMP} — M
- Intent: a manager configures how review works — mode, deadlines, guidance, review forms and reviewer recommendation options.
- Atoms: AFFM-065 · AFFM-066 · AFFM-067 · AFFM-071 · AFFM-074 · AFFM-078 · AFFM-079 · GRID-046 · GRID-047 · GRID-059 · API-054 · VUE-047 · VUE-069 · AFFW-670 · AFFU-170
- Note: form ELEMENT definitions live here; their rendering inside the wizard is feature 26's instantiation.

### 28. Reviewer suggestions — {OJS OMP} — L
- Intent: an author suggests reviewers at submission; an editor sees and acts on the suggestions during review.
- Atoms: API-031 · VUE-048 · VUE-099 · AFFW-085 · AFFW-121 · AFFW-157 · AFFW-289 · AFFW-330 · AFFW-575 · AFFW-576
- Note: spans wizard and workflow, but it is one mechanism with one owner (rule 8); enable-setting scenario included.

### 29. Author response to reviews — {OJS} — M
- Intent: an editor requests a formal author response to a review round; the author submits it and the editor manages it.
- Atoms: ROUTE-023 · VUE-008 · VUE-049 · VUE-070 · API-032 · MAIL-033 · AFFW-326 · AFFW-354 · AFFW-577 · AFFW-579 · AFFW-584
- Note: response DOIs belong to feature 52 (PUT authorResponses in API-001).

### 30. Copyediting stage — {OJS OMP} — M
- Intent: editor, copyeditor and author take a submission through draft → copyedited files and on to production.
- Atoms: AFFW-356 · AFFW-357 · AFFW-359 · AFFW-361 · AFFW-362 · AFFW-363 · AFFW-610 · AFFW-611 · GRID-014 · GRID-015 · GRID-019 · GRID-020 · MAIL-005 · MAIL-076 · NOTIF-032 · NOTIF-042 · NOTIF-043
- Note: the Copyeditor assistant's whole journey lives on this shared screen (rule 9 — no separate assistant feature).

### 31. Production stage & galleys — {OJS OMP OPS} (galleys {OJS OPS}) — M
- Intent: the production team prepares production-ready files and publishes galleys; the author follows via discussions.
- Atoms: AFFW-364 · AFFW-365 · AFFW-368 · AFFW-370 · AFFW-375 · AFFW-419 · AFFW-524 · AFFW-528 · AFFW-737 · AFFW-753 · GRID-016 · GRID-022 · GRID-023 · GRID-068 · GRID-101 · MAIL-077 · MAIL-082 · NOTIF-044 · NOTIF-045 · SET-030
- Note: OMP has the stage but represents output as publication formats — out-of-scope surface; spec covers OMP stage panels, formats get an absence/out-of-scope pointer. Layout Editor / Proofreader journeys live here.

### 32. JATS & body text — {OJS} — M
- Intent: an editor maintains a JATS XML representation and edits the article body text in the built-in editor.
- Atoms: API-009 · API-025 · AFFW-269 · AFFW-270 · AFFW-403 · AFFW-407 · AFFW-408 · AFFW-411 · AFFW-412 · AFFW-415 · AFFW-463 · PLUG-019
- Note: oaiJats (PLUG-040) stays with feature 16; JATS public download guard owned here.

### 33. Publishing, scheduling & versioning — {OJS OMP OPS} — H
- Intent: someone with publish rights schedules, publishes, unschedules, unpublishes and versions a publication — and in OPS the author posts the preprint.
- Atoms: GRID-062 · GRID-107 · VUE-084 · API-042 · AFFW-377 · AFFW-384 · AFFW-388 · AFFW-389 · AFFW-392 · AFFW-393 · AFFW-435 · AFFW-441 · AFFW-446 · AFFW-449 · AFFW-709 · MAIL-002 · MAIL-031 · MAIL-072 · NOTIF-035 · NOTIF-054 · JOB-050 · AFFR-052 · CLI-022 (ref)
- Note: owns issue-assignment-at-scheduling (OJS delta AFFW-445..448, API-057) — the screen is the publish modal, though issues own the resulting TOC. OPS author-can-post (API-065 role widening, MAIL-072..074) is a register divergence, not its own feature.

### 34. Recording an editorial decision — {OJS OMP OPS} — M
- Intent: an editor walks the decision wizard — notification email, file promotion — for any decision the stage offers.
- Atoms: ROUTE-009 · VUE-017 · AFFW-161 · AFFW-165 · AFFW-167 · AFFW-168 · AFFW-172 · AFFW-177 · AFFW-179 · AFFW-181 · AFFW-187 · AFFW-193 · AFFW-197 · AFFW-205 · MAIL-011 · SET-009
- Note: mechanism home for the wizard AND the email composer + file attacher (AFFW-181..212) used by discussions/notify too — disagreement item 2. WHICH decisions exist per stage belongs to stage features.

### 35. Participants & editor assignment — {OJS OMP OPS} — M
- Intent: an editor/manager assigns people to a submission's stages, notifies them, and controls their permissions (recommend-only, metadata).
- Atoms: GRID-054 · GRID-055 · VUE-043 · AFFW-466 · AFFW-468 · AFFW-471 · AFFW-671 · AFFW-673 · AFFW-674 · AFFW-677 · AFFW-679 · MAIL-024 · MAIL-052 · MAIL-078 · NOTIF-014 · NOTIF-046 · API-042
- Note: per-stage editor-assignment NOTIF types (NOTIF-014..018) claimed here as one family; the login-as row action defers to feature 1's mechanics.

### 36. Workflow files & uploads — {OJS OMP OPS} — H
- Intent: any participant uploads, revises, labels, inspects, downloads and deletes submission files anywhere in the workflow.
- Atoms: GRID-001 · GRID-002 · GRID-066 · API-043 · API-045 · VUE-038 · VUE-053 · AFFW-474 · AFFW-478 · AFFW-480 · AFFW-482 · AFFW-586 · AFFW-591 · AFFW-592 · AFFW-596 · AFFW-599 · AFFW-613 · SET-026
- Note: mechanism home for the FileManager + legacy upload wizard + file metadata/identifiers tabset; per-stage file panels are instantiations. Genre EFFECTS (component selector) tested here; genre CONFIG is feature 46's.

### 37. Tasks & discussions — {OJS OMP OPS} — H
- Intent: participants (reviewer included) open discussions and tasks on a submission, message each other with attachments, track status/history, and get reminded.
- Atoms: API-017 · API-044 · VUE-036 · VUE-050 · VUE-058 · VUE-060 · AFFW-507 · AFFW-510 · AFFW-515 · AFFW-517 · AFFW-519 · AFFM-086 · AFFM-087 · AFFU-182 · AFFU-186 · NOTIF-040 · MAIL-021 · MAIL-025 · JOB-011 · NOTIF-051
- Note: owns task templates settings (AFFM-086..089, VUE-050/071) and the editorial-reminder machinery; per-stage discussion mails (MAIL-020..023) claimed here.

### 38. Submission activity log & notes — {OJS OMP OPS} — L
- Intent: an editor inspects a submission's full event history, logged emails, and keeps internal notes.
- Atoms: GRID-008 · GRID-009 · GRID-056 · GRID-058 · API-018 · AFFW-235 · AFFW-687 · AFFW-690 · AFFW-691 · AFFW-694 · AFFW-697 · SET-011 · SET-013

### 39. Contributors — {OJS OMP OPS} — M
- Intent: authors and editors maintain the contributor list — order, roles, affiliations, principal contact — wherever the publication is edited.
- Atoms: GRID-051 · API-014 · VUE-033 · VUE-034 · VUE-056 · VUE-093 · VUE-094 · AFFW-084 · AFFW-146 · AFFW-151 · AFFW-396 · AFFW-681 · AFFW-683 · SET-003 · SET-007 · AFFR-053
- Note: contributor-role (CRediT-style) settings claimed here as the mechanism's config; ROR affiliation lookup mechanics live in feature 55.

### 40. Citations & data citations — {OJS OMP OPS} — M
- Intent: authors and editors capture reference lists and data citations; the system structures them via external lookups; readers see them.
- Atoms: API-011 · API-015 · VUE-032 · VUE-035 · VUE-055 · VUE-057 · AFFW-086 · AFFW-398 · AFFW-399 · AFFW-533 · AFFW-535 · AFFW-539 · AFFW-544 · JOB-003 · JOB-006 · AFFR-057 · SET-005 · CLI-016 (ref)

### 41. Funding — {OJS OMP OPS} — L
- Intent: authors and editors record funders and grants; readers see funding data on the article page.
- Atoms: API-020 · VUE-039 · VUE-061 · AFFW-087 · AFFW-401 · AFFW-552 · AFFW-554 · AFFW-557 · AFFR-063 · SET-014

### 42. Publication metadata & identifiers — {OJS OMP OPS} — M
- Intent: editors and permitted authors maintain title/abstract, descriptive metadata, license/permissions and non-DOI identifiers per publication.
- Atoms: API-042 · AFFW-395 · AFFW-397 · AFFW-402 · AFFW-421 · AFFW-430 · AFFW-599 · AFFW-601 · GRID-063 · VUE-089 · VUE-090 · PLUG-043 · SET-019 · AFFR-055 · AFFR-064
- Note: which metadata fields are ON is feature 46's form; DOIs are feature 52; the view-metadata modal (GRID-063) claimed here as the read-only surface.

### 43. Media files — {OJS OMP OPS} — L
- Intent: an editor manages a publication's media/image files and links them into galley text.
- Atoms: API-041 · VUE-041 · VUE-062 · VUE-063 · VUE-064 · VUE-065 · AFFW-420 · AFFW-558 · AFFW-559 · AFFW-563 · AFFW-566 · AFFW-569

## E. Journal management & site administration

### 44. Journal setup — masthead, contact & sections — {OJS OMP OPS} — M
- Intent: a manager defines the journal's identity and its content structure (sections; series in OMP).
- Atoms: ROUTE-017 · VUE-022 · AFFM-001 · AFFM-002 · AFFM-003 · AFFM-005 · AFFM-006 · AFFM-007 · GRID-079 · GRID-106 · API-034 · SET-023 · SET-033 · AFFR-080 · AFFW-076
- Note: OMP series grid (AFFM-008..012, GRID-096) treated as the sections divergence per APP-GLOSSARY (section↔series), not a separate feature — disagreement item 7.

### 45. Categories — {OJS OMP OPS} — L
- Intent: a manager builds a category tree; submissions get categorized; readers browse by category.
- Atoms: API-010 · VUE-030 · VUE-054 · AFFM-013 · AFFM-015 · AFFM-017 · AFFR-024 · AFFR-072 · AFFR-086 · ROUTE-006 · SET-004

### 46. Submission configuration — {OJS OMP OPS} — M
- Intent: a manager controls submission intake — open/closed, author guidance, metadata enablement, file components (genres), screening.
- Atoms: AFFM-053 · AFFM-054 · AFFM-055 · AFFM-056 · AFFM-057 · AFFM-059 · AFFM-060 · AFFM-064 · GRID-042 · SET-006 · AFFR-035 · AFFW-070
- Note: contributor-role settings moved to 39 (mechanism home); metadata-enablement EFFECTS surface in features 19/42 (gates).

### 47. Languages & locales — {OJS OMP OPS} — M
- Intent: an admin/manager installs and enables locales at site and journal level; users switch languages; submissions declare theirs.
- Atoms: GRID-005 · GRID-036 · GRID-043 · GRID-044 · AFFM-023 · AFFM-024 · AFFM-026 · AFFM-199 · AFFM-214 · AFFM-215 · AFFM-217 · PLUG-004 · AFFR-089 · SET-049
- Note: per-submission language CHANGE modal (AFFW-457..459) belongs to feature 22's screen; this feature owns which locales exist.

### 48. Publisher & submission libraries — {OJS OMP OPS} — L
- Intent: a manager keeps a shared document library; participants attach/consult submission-library documents in the workflow.
- Atoms: ROUTE-015 · API-004 · GRID-021 · GRID-034 · GRID-045 · GRID-061 · AFFM-082 · AFFM-083 · AFFM-084 · AFFW-615 · AFFW-616 · AFFW-617 · AFFW-236

### 49. Distribution & licensing settings — {OJS OMP OPS} — M
- Intent: a manager sets license defaults, open-access/publishing mode, search-engine indexing and archiving (LOCKSS/PN).
- Atoms: AFFM-090 · AFFM-093 · AFFM-096 · AFFM-097 · AFFM-098 · ROUTE-037 · AFFR-065 · SET-029
- Note: publishing-mode effects are verified in features 12/17 (access), licensing display in 12 — this feature owns the switches. Borderline M/L.

### 50. Users & roles — {OJS OMP OPS} — H
- Intent: a manager administers user accounts and role groups — search, edit, enable/disable, merge, remove, stage assignment, masthead display, bulk email.
- Atoms: GRID-048 · GRID-050 · GRID-003 · VUE-013 · VUE-051 · AFFM-102 · AFFM-103 · AFFM-106 · AFFM-107 · AFFM-108 · AFFM-109 · AFFM-112 · AFFM-114 · AFFM-116 · API-046 · API-047 · API-002 · MAIL-054 · MAIL-056 · MAIL-057 · JOB-002 · CLI-028 (ref)
- Note: bulk-email plumbing (site enablement AFFM-220, per-context restriction AFFM-201) claimed here.

### 51. Emails management — {OJS OMP OPS} — M
- Intent: a manager reviews every mailable, customizes/adds/resets its templates, and disables optional ones.
- Atoms: API-019 · API-027 · VUE-021 · VUE-072 · VUE-073 · AFFM-121 · AFFM-122 · AFFM-123 · AFFM-125 · AFFM-127 · AFFM-128 · SET-012 · SET-053 · CLI-010 (ref)
- Note: template mechanics home; each mailable's TRIGGER is owned by its feature.

### 52. DOIs — {OJS OMP OPS} — H
- Intent: a manager enables DOIs, assigns them across object types, and registers/deposits them with an agency, tracking statuses and errors.
- Atoms: ROUTE-010 · API-016 · API-001 · API-052 · VUE-018 · VUE-095 · VUE-096 · AFFM-091 · AFFM-092 · AFFM-148 · AFFM-152 · AFFM-154 · AFFU-221 · AFFU-233 · AFFU-236 · AFFU-244 · JOB-008 · JOB-010 · JOB-030 · JOB-045 · PLUG-009 · PLUG-011 · SET-010 · CLI-030 (ref)
- Note: OMP chapter/format DOI rows (API-058, AFFU-245) sit on out-of-scope objects — noted in-spec, not force-fitted.

### 53. Statistics & reporting — {OJS OMP OPS} — H
- Intent: editors and managers examine usage, editorial-activity and user stats; download reports; serve COUNTER R5/SUSHI; the pipeline compiles usage data.
- Atoms: ROUTE-026 · API-038 · API-039 · API-036 · API-056 · VUE-002 · VUE-024 · VUE-025 · VUE-027 · AFFU-249 · AFFU-259 · AFFU-261 · AFFU-269 · AFFU-275 · AFFU-283 · AFFU-286 · JOB-023 · JOB-044 · JOB-060 · MAIL-048 · NOTIF-049 · PLUG-029 · PLUG-044 · PLUG-047 · AFFM-095 · AFFM-221 · AFFM-171 · CLI-020 (ref)
- Note: the one feature clearly at risk of overflowing H — candidate split usage-stats / editorial-stats+reports / COUNTER (disagreement item 5).

### 54. Import & export — {OJS OMP OPS} — M
- Intent: a manager moves content and users in/out — native XML, users XML, PubMed export, pub-id-aware export lists.
- Atoms: ROUTE-018 · VUE-019 · AFFM-161 · AFFM-162 · AFFM-164 · AFFM-165 · AFFM-166 · AFFM-167 · AFFM-168 · GRID-052 · GRID-071 · GRID-076 · GRID-078 · PLUG-032 · PLUG-034 · PLUG-035 · CLI-026 (ref)
- Note: OMP CSV/ONIX exporters ride on out-of-scope objects — listed in out-of-scope, not claimed.

### 55. Institutions & ROR registry — {OJS OMP OPS} — L
- Intent: a manager maintains the institution list (for stats and subscriptions); users pick ROR-backed institutions anywhere affiliations appear.
- Atoms: API-023 · API-033 · VUE-098 · AFFM-137 · AFFM-138 · AFFM-140 · SET-016 · SET-022 · JOB-057

### 56. Highlights — {OJS OMP OPS} — L
- Intent: a manager curates homepage highlight slides at journal or site level; visitors see the carousel.
- Atoms: API-022 · VUE-097 · AFFM-037 · AFFM-038 · AFFM-040 · AFFM-219 · AFFR-017 · SET-015

### 57. Plugins management — {OJS OMP OPS} — M
- Intent: an admin/manager enables, configures, uploads, upgrades and deletes plugins, and installs from the gallery.
- Atoms: GRID-006 · GRID-041 · GRID-077 · AFFM-044 · AFFM-045 · AFFM-046 · AFFM-048 · AFFM-049 · AFFM-051 · AFFM-202 · AFFM-228 · NOTIF-009 · NOTIF-010 · SET-052 · CLI-017 (ref)
- Note: each plugin's OWN settings/behavior is claimed by its owning feature; this owns the management surface.

### 58. Hosted contexts (multi-journal site) — {OJS OMP OPS} — M
- Intent: a site admin creates, orders, edits and removes hosted journals and walks the new-context settings wizard.
- Atoms: GRID-004 · VUE-014 · API-013 · AFFM-192 · AFFM-193 · AFFM-194 · AFFM-195 · AFFM-196 · AFFM-197 · AFFM-201 · AFFM-203 · AFFM-208 · ROUTE-003 · SET-006
- Note: the wizard's embedded users/plugins/languages tabs are scope deltas of features 50/57/47.

### 59. Site settings & administration — {OJS OMP OPS} — M
- Intent: a site admin configures the site (title, security, info, appearance, bulk-email policy) and operates it (caches, sessions, system info).
- Atoms: ROUTE-003 · VUE-015 · AFFM-184 · AFFM-186 · AFFM-187 · AFFM-188 · AFFM-211 · AFFM-212 · AFFM-213 · AFFM-220 · AFFM-236 · AFFM-238 · SET-024 · SET-046 · SET-052
- Note: infrastructure-only config sections (SET-047..051, SET-058..061, SET-059) claimed here at reference level — they set environment, not user-observable rules; flagged for the map to confirm.

### 60. Background jobs & scheduled tasks — {OJS OMP OPS} — L
- Intent: a site admin watches the job queue, retries or discards failed jobs, and relies on the scheduler running tasks.
- Atoms: API-026 · VUE-005 · VUE-006 · VUE-007 · AFFM-230 · AFFM-231 · AFFM-232 · AFFM-234 · JOB-049 · JOB-052 · JOB-063 · JOB-067 · SET-062 · SET-063 · CLI-012 (ref) · JOB-028 · JOB-029

---

## (a) Candidate-UNASSIGNED (fit no feature without force-fitting)

- **ROUTE-043** — OJS legacy `manager` page dispatcher whose switch returns no handler for any op: dead routing stub; nothing reachable to specify.
- **API-062** — OMP `publicationPeerReviews` entry point instantiates a controller class that does not exist at the swept SHA: dangling mount, dead code candidate.
- **API-030 + AFFR-211 trio (PkpCite / PkpOpenReview / PkpOrcidDisplay)** — open-peer-review data API is mounted (OJS) but the Vue components that would consume it grep to no template/PHP mount: feature-in-progress with no reachable UI; parking rather than inventing a "public review" feature.
- **NOTIF-056..065** — OJS books-for-review notification constants; the books-for-review plugin is not bundled in any swept tree: dead-code candidates.
- **NOTIF-011** — `NOTIFICATION_TYPE_PLUGIN_BASE`: an offset constant with zero referencing files; infrastructure value, no behavior to specify.
- **VUE-004** — ui-library ExamplePage; documentation artifact, no app mount.
- **VUE-042** — NavigationMenuManager component has no mount (only its form modal VUE-066 is wired); the modal is claimed by feature 8, the manager component parks here.
- **AFFM-170** — OMP statistics-settings form template whose save op greps to no PHP handler: dead-code candidate (also rides an OMP-only page).
- **ROUTE-012 + CLI-027 / CLI-031** — installer/upgrader: a real surface but not a role journey on a running install, and untestable inside the shared-fixture e2e model; parked for an explicit maintainer scope ruling rather than force-fitted into site administration.

## (b) Out-of-scope candidates per CHARTER (OMP/OPS-only surfaces OJS never exposes)

- **OMP catalog**: ROUTE-055, ROUTE-056, ROUTE-062 · VUE-020, VUE-101 · AFFM-263..270 · AFFR-070, AFFR-071, AFFR-073..077 · GRID-099 · API-059 · PLUG-018 · AFFR-051.
- **OMP Internal Review stage** (`*_INTERNAL` decisions dropped by CHARTER): ROUTE-072 `internalReview` op · AFFW-248, AFFW-304..322, AFFW-345..348 · MAIL-071 · NOTIF-015, NOTIF-020, NOTIF-030 (internal-stage types). The OMP *submission-stage* routing that references skip-internal (AFFW-296..301) stays IN scope as a feature-23 divergence.
- **OMP monographs / chapters / publication formats**: GRID-089..094, GRID-097 · AFFW-111, AFFW-123, AFFW-124, AFFW-237..239, AFFW-275..277, AFFW-425..427, AFFW-431..434, AFFW-572..574, AFFW-754..777 · API-058, API-061 · VUE-031, VUE-044, VUE-045 · SET-040 · MAIL-080, MAIL-081 · PLUG-031, PLUG-033, PLUG-046 · JOB-038 (series-metrics compile — stats analogue but feeding OMP-only series reporting; borderline, leaning claimable by 53 as parameterization).
- **OPS preprint relations & journal relay**: AFFW-128, AFFW-385, AFFW-386 · API-065 `relate` endpoint · PLUG-024 (preprintToJournal).
- **OMP direct sales** (glossary: a different feature sharing only paymethod plugins): the approved-proof pricing forms AFFW-770, AFFW-771 and API-059 pricing flags.

## (c) Hardest grouping decisions (→ disagreement register)

1. **Issues as ONE feature (11) vs split reader-archive / issue-management.** Rejected the split: publishing an issue is only verifiable on the reader TOC, and the QA litmus says one test plan. Cost: an H feature touching two personas; the alternative left a reader-archive feature that couldn't fill 3 scenarios alone.
2. **Email composer + file attacher homed in Recording-a-decision (34), not a standalone feature and not Emails management.** Rejected standalone because composer scenarios are only executable inside a host flow (fails the QA litmus); rejected Emails management because that feature is template CONFIG, not sending. Risk: discussions/notify instantiations (37, 35) must dedupe against 34 by link.
3. **"Log in as" homed in Sign-in & sessions (1), not Users & roles.** It is a session mechanic surfaced from three different screens (user grid, participant manager, reviewer grid); homing it with sessions keeps one owner. Alternative (Users & roles) matched the most prominent offering surface but would force workflow features to cross-link into a settings feature for a session behavior.
4. **Reviewer's dashboard list folded into Doing-a-review (26) rather than a third dashboard feature.** TEMPLATE rule 9 names the editorial dashboard and My Submissions as separate list-owning features, which argues for symmetry; rejected because the Review Assignments view is thin (8 atoms), its statuses/actions are pure projections of wizard state, and it cannot sustain 3 scenarios that aren't wizard scenarios.
5. **Statistics kept as one H feature (53).** The alternative — usage / editorial+reports / COUNTER-SUSHI split — is well-motivated (three distinct reader intents and the atom count is the largest of any feature); kept single because the screens share the date-range/filter/download mechanics and the ETL pipeline, and a split would need a fourth "pipeline" home. Flagged as the most likely forced split at map review.
6. **Published landing page (12) badged all three apps despite CHARTER dropping the OMP catalog.** The OMP monograph page is simultaneously "the catalog" (out of scope) and "the landing page analogue" (in scope by the one-spec-three-apps rule). Chose: spec covers OJS+OPS fully; OMP gets an absence/out-of-scope paragraph, not scenarios. The alternative (cover OMP book page as analogue) contradicts CHARTER's explicit drop list.
7. **OMP series treated as the sections divergence inside Journal setup (44), not out-of-scope.** APP-GLOSSARY maps section↔series as vocabulary, which implies parameterization (RUNBOOK rule 7); but the series grid has extra machinery (cover images, ISSN fields) that leans toward the out-of-scope catalog cluster. Chose in-scope divergence; register the tension.
8. **Genre (file components) config in Submission configuration (46) with effects in Workflow files (36).** Variance-based split per rule 8; the alternative (all-in-files) would make the settings screen a foreign body inside a workflow feature. Same pattern applied to metadata enablement (46 vs 19/42) — if the map dislikes one, both should change together.
9. **Reader comments as one feature (13) spanning reader posting and manager moderation.** Alternative was reader-side + management-side features; rejected because approve/hide/report states are meaningless without both actors in one scenario.
10. **One-click reviewer access claimed by Finding-reviewers (25) though it is invitation machinery (6).** The invitation framework is 6's mechanics; the reviewer-access invite parameterizes it but lands the user in the review wizard. Kept the delta in 25 (the user intent is "get the reviewer into the review"), with mechanics links to 6.
