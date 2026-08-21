# Crosswalk — AFFM + SET atoms → unified features (U1–U70)

- **Date**: 2026-07-27 · desk crosswalk for FEATURE-MAP Stage D.
- **Inputs**: `synthesis.md` §1/§4/§5 taxonomy as amended by `RULINGS.md` (all D-leans
  accepted; Q1a/Q2-amended/Q3a/Q4a/Q5a; new U68/U69/U70). Atlas: `atlas/affordances-management.md`
  (AFFM-001..271), `atlas/settings.md` (SET-001..065). 336 atoms, no gaps.
- **Rules applied**: D9 settings shape (each settings form/tab → the feature whose
  behavior it configures; only U58 and U29 own settings screens as features), D17
  (log-in-as mechanism → U1 wherever offered), D10 (license + reset-permissions → U40),
  D20 (users U53 / roles U54 / notify U55 split), Q5a (pure-infra config sections →
  OOS-infra), Q3a (series = sections machinery → U17), RULINGS U70 = AFFM-263..271.
- **Format**: `ID[..ID] → U## | UNASSIGNED: reason | OOS: cluster` · optional `? note`.

## AFFM — management/settings + site administration affordances

AFFM-001..002 → U7 (masthead + contact forms — journal identity)
AFFM-003..007 → U17 (sections grid, OJS/OPS)
AFFM-008..012 → U17 (series grid — Q3a: series is sections machinery relabeled)
AFFM-013..017 → U16 (categories manager)
AFFM-018..021 → U10 (website appearance: theme/setup/masthead-display/advanced forms)
AFFM-022 → U7 (Information form — information pages are U7's boundary)
AFFM-023..027 → U57 (context languages + submission-languages grids)
AFFM-028..034 → U8 (navigation menus + menu items grids and edit modal)
AFFM-035 → U9 (custom-NMI-page Preview button — custom pages per D14 S-shape)
AFFM-036 → U12 (announcements enable/settings form — configures U12 behavior)
AFFM-037..040 → U11 (highlights panel: order/add/edit/delete)
AFFM-041 → U10 (Lists form — "lists" named in U10)
AFFM-042 → U7 (privacy statement form — context policies)
AFFM-043 → U10 (date & time formats — named in U10)
AFFM-044..051 → U62 (installed-plugins + plugin-gallery grids, context scope)
AFFM-052 → U14 (public comments settings form — configures comments behavior)
AFFM-053..055 → U58 (disable-submissions, author guidance, metadata enablement)
AFFM-056..060 → U58 (genres/components grid — D9: genre config lives in U58)
AFFM-061..063 → U41 (contributor-roles manager — configures contributor roles)
AFFM-064 → U58 ? OPS author-screening tab; claim per synthesis §4 proposal, Phase-1 liveness flag (renders only when screening plugins register rules; no bundled plugin does)
AFFM-065..066 → U29 (review setup + reviewer guidance forms)
AFFM-067..077 → U29 (review forms grid + form-items grid)
AFFM-078..081 → U29 (reviewer recommendations manager — recommendation options)
AFFM-082..084 → U39 (publisher library grid)
AFFM-085 → U56 (email setup form: signature/bounce/options)
AFFM-086..089 → U37 (task & discussion templates — U37 owns template settings)
AFFM-090 → U40 (license form — D10 fold)
AFFM-091..092 → U45 (DOI setup + registration-agency forms)
AFFM-093 → U20 (search-engine indexing form — D9 dissolution)
AFFM-094 → U52 (payments method form — D9 dissolution)
AFFM-095 → U64 (context statistics-collection form — configures usage-stats collection)
AFFM-096 → U51 (Access form: open access / publishing mode — D9 dissolution)
AFFM-097..098 → U67 (PN + LOCKSS/CLOCKSS archiving forms)
AFFM-099..101 → U6 (invite-to-role button, edit/cancel pending invitation)
AFFM-102..104 → U53 (users table: search, edit user, send email)
AFFM-105 → U1 (Login As — D17: mechanism home is U1 on every offering surface)
AFFM-106..108 → U53 (remove-from-roles, enable/disable, merge user)
AFFM-109..113 → U54 (roles grid: create/edit/remove, stage-assignment toggles, filter)
AFFM-114..115 → U55 (Notify tab: bulk-email compose/send + queued-notice)
AFFM-116 → U54 (site access options form — self-registration is named in U54)
AFFM-117 → U4 (context ORCID settings form)
AFFM-118..120 → U6 (invite-to-role wizard steps)
AFFM-121..128 → U56 (emails management: search/filter/reset-all/mailable + template modals)
AFFM-129..136 → U12 (announcements panel + announcement types grid)
AFFM-137..140 → U66 (institutions panel)
AFFM-141..147 → U14 (user-comments moderation page)
AFFM-148..160 → U45 (DOIs management page: tabs/search/bulk/per-item actions)
AFFM-161 → U63 ? Tools tab bar — spans Import/Export (U63) and Permissions (U40's reset tool); homed with its dominant tab
AFFM-162 → U63 (import/export plugin link list)
AFFM-163 → U40 (reset-permissions tool — D10: license fold's settings delta)
AFFM-164..165 → U63 (native XML import/export submissions)
AFFM-166 → U63 (native XML export issues, OJS)
AFFM-167 → U63 (users XML import/export)
AFFM-168 → U63 (PubMed export plugin UI, OJS)
AFFM-169 → OOS: OMP-only exporters (synthesis §5 names AFFM-169 explicitly)
AFFM-170 → UNASSIGNED: OMP statisticsSettingsForm.tpl posts to an op no PHP handler declares — dead-code candidate (synthesis §4)
AFFM-171 → U65 ? statistics reports tool (report-plugin links) — U65 names "report plugins"; the reports are usage-shaped, U64 plausible
AFFM-172 → U51 ? payments-page tab bar — spans subscriptions (U51) and payment types/method (U52); homed with its dominant tabs
AFFM-173..180 → U51 (individual/institutional subscription grids + policies form)
AFFM-181..182 → U52 (payment types form + payments grid details)
AFFM-183 → U59 (Hosted Contexts link)
AFFM-184 → U60 (Site Settings link)
AFFM-185..190 → U61 (system info link, expire sessions, cache clears, task-log clear, jobs links)
AFFM-191 → U1 (confirm-password re-authentication gate — named in U1)
AFFM-192..196 → U59 (hosted contexts grid: create/edit/delete/wizard-link/reorder)
AFFM-197 → U59 (wizard Context form — context lifecycle/identity)
AFFM-198 → U10 (wizard Theme form — re-embed of the context theme form)
AFFM-199 → U57 (wizard Languages tab — re-embed of AFFM-023..027 controls)
AFFM-200 → U20 (wizard search-indexing form — same D9 dissolution as AFFM-093)
AFFM-201 → U55 (restrict-bulk-emails per-role form — D20: site-policy gates are U55's settings)
AFFM-202 → U62 (wizard Plugins tab — re-embed of AFFM-044..051 controls)
AFFM-203..208 → U53 (wizard Users grid: add/filter/email/edit/enable-disable/remove — user-management mechanism invariant of surface)
AFFM-209 → U1 (wizard Users grid Login As — D17)
AFFM-210 → U53 (wizard Users grid merge)
AFFM-211..213 → U60 (site setup, security, information forms — named in U60)
AFFM-214..217 → U57 (site languages grid: install/uninstall/reload/enable/primary — "admins install locales")
AFFM-218 → U8 (site-level navigation tab — re-embed of AFFM-028..035 controls)
AFFM-219 → U11 (site-level highlights tab — re-embed of AFFM-037..040)
AFFM-220 → U60 (site bulk-emails policy form — named in U60)
AFFM-221 → U64 (site statistics-collection form: geo/institution/SUSHI)
AFFM-222 → U4 (site-wide ORCID settings form)
AFFM-223..224 → U60 (site appearance theme + setup forms — "appearance" named in U60)
AFFM-225..227 → U12 (site announcements settings/panel/types — re-embeds at site scope)
AFFM-228..229 → U62 (site plugins grid + gallery)
AFFM-230..235 → U61 (jobs, failed jobs, failed-job details pages)
AFFM-236..238 → U61 (system information: version check, download links, phpinfo)
AFFM-239..242 → U51 (subscription add/edit modals + subscriber-select grid + type form)
AFFM-243..249 → U50 (issues tabs, create issue, edit-issue modal, issue data, TOC order/workflow-link/remove)
AFFM-250 → U50 ? per-article open-access toggle on TOC — access *rules* are U51's; the toggle is issue-TOC surface, homed with the screen
AFFM-251..254 → U50 (issue galleys tab — "issue galleys" named in U50)
AFFM-255 → U44 ? issue Identifiers tab (assign/clear pub-ids) — pub-id mechanism invariant of surface (rule-8); alt U50 as issue-modal tab
AFFM-256 → U50 ? issue Access tab (access status + open-access date) — implements U51's delayed-OA rules on the issue surface; homed with the screen
AFFM-257..262 → U50 (view/preview, publish, unpublish, set-current, delete, reorder issues)
AFFM-263..271 → U70 (catalog management — RULINGS assigns AFFM-263..271 to U70 explicitly)

## SET — entity schemas

SET-001 → U41 (affiliation schema)
SET-002 → U12 (announcement schema)
SET-003 → U41 (author schema)
SET-004 → U16 (category schema)
SET-005 → U42 (citation schema)
SET-006 → U59 ? context schema — a settings container spanning many features' props; homed with the context-entity lifecycle owner (create/edit/delete in U59); alt U7
SET-007 → U41 (contributorRole schema — pairs with AFFM-061..063)
SET-008 → U42 (dataCitation schema — D11: data citations ride U42)
SET-009 → U34 (editorial decision schema)
SET-010 → U45 (doi schema)
SET-011 → U38 (emailLog schema — logged emails live with the activity log, per API-018 precedent)
SET-012 → U56 (emailTemplate schema)
SET-013 → U38 (eventLog schema)
SET-014 → U43 (funder schema)
SET-015 → U11 (highlight schema)
SET-016 → U66 (institution schema)
SET-017..018 → U8 (navigationMenu + navigationMenuItem schemas)
SET-019 → U40 (publication schema — descriptive-metadata entity home)
SET-020 → U27 (reviewAssignment schema — assignment lifecycle is U27's CRUD)
SET-021 → U26 (reviewRound schema)
SET-022 → U41 (ror schema — ROR mechanics homed in U41 per majority)
SET-023 → U17 (section schema)
SET-024 → U60 (site schema — site-level settings entity)
SET-025 → U21 ? submission schema — entity spans wizard→workflow→dashboards; homed with creation (wizard); alt U24
SET-026 → U36 (submissionFile schema)
SET-027 → U3 (user schema — props are the profile's identity/contact/API-key surface)
SET-028 → U54 (userGroup schema — role groups)
SET-029 → U59 (OJS context overlay — follows SET-006 ruling)
SET-030 → U46 (OJS galley schema)
SET-031 → U50 (OJS issue schema)
SET-032 → U40 (OJS publication overlay — follows SET-019)
SET-033 → U17 (OJS section overlay)
SET-034 → U21 (OJS submission overlay — follows SET-025)
SET-035 → U59 (OMP context overlay — follows SET-006 ruling)
SET-036 → OOS: OMP publication formats & ONIX (synthesis §5 names SET-036 as format-file overlay)
SET-037 → U40 (OMP publication overlay — follows SET-019)
SET-038 → U17 (OMP series overlay — Q3a)
SET-039 → U21 (OMP submission overlay — follows SET-025)
SET-040 → OOS: OMP publication formats & ONIX (synthesis §5 names SET-040)
SET-041 → U59 (OPS context overlay — follows SET-006 ruling)
SET-042 → U46 (OPS galley schema)
SET-043 → U40 (OPS publication overlay — follows SET-019)
SET-044 → U17 (OPS section overlay)
SET-045 → U21 (OPS submission overlay — follows SET-025)

## SET — config.TEMPLATE.inc.php sections

SET-046..051 → OOS: OOS-infra (Q5a blanket: [general], [database], [cache], [i18n], [files], [finfo] — deployment-only)
SET-052 → U1 ? [security] — session/login keys dominate (force_login_ssl, session_check_ip, remember_me_lifetime, reset_seconds, password_timeout); allow_plugin_install/plugin_gallery_urls are cited by U62's guards, allowed_html near-infra
SET-053 → U56 ? [email] — SMTP transport keys are near-infra, but require_validation/validation_timeout gate U2's flow and envelope/DMARC options shape all outgoing mail; homed with email mechanics
SET-054 → U15 ([search] — driver, min_word_length, results_per_keyword configure search behavior)
SET-055 → U19 ([oai] — oai enable, repository_id, oai_max_records)
SET-056 → OOS: OOS-infra (Q5a: [interface] — named in the Q5 list)
SET-057 → U2 ? [captcha] — register/lost-password gates are U2's; captcha_on_login/altcha_on_login belong to U1; homed with registration (dominant surface)
SET-058..061 → OOS: OOS-infra (Q5a: [cli], [proxy], [debug], [logs] — named in the Q5 list)
SET-062..063 → U61 ([queues] + [schedule] — job-runner and task-runner behavior surfaced on U61's pages)
SET-064 → U6 ([invitations] expiration_days — configures invitation lifetime)
SET-065 → OOS: OOS-infra (Q5a: [features] empty placeholder — named in the Q5 list)

## Tally

- Total atoms: 336 (AFFM 271 + SET 65), no gaps.
- Feature-assigned: 320 (AFFM 269 · SET 51).
- OOS: 15 — AFFM-169 (OMP-only exporters) · SET-036/040 (OMP publication formats & ONIX) · SET-046..051, 056, 058..061, 065 (OOS-infra, 12 atoms).
- UNASSIGNED: 1 — AFFM-170 (dead-code candidate).
- `?` flags: 12 — AFFM-064, 161, 171, 172, 250, 255, 256 · SET-006, 025, 052, 053, 057.
