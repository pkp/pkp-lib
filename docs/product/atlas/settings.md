# Atlas — settings modality

- **Modality**: settings (site/context entity schemas + `config.inc.php` template sections)
- **Sweep date**: 2026-07-26
- **Trees swept**:
  - `lib/pkp/schemas/*.json` — lib/pkp submodule checked out at `ad4606f93e` in all three apps (identical; task brief cited `9db481cf4d` — `git diff 9db481cf4d ad4606f93e -- schemas/` is empty, so the schemas tree is the same at either SHA). Swept once; default `apps: ojs omp ops`.
  - `/Users/jarda/git/pkp/pkp-main/ojs-main/schemas/*.json`, `/Users/jarda/git/pkp/pkp-main/omp-main/schemas/*.json`, `/Users/jarda/git/pkp/pkp-main/ops-main/schemas/*.json` (app overlays)
  - `config.TEMPLATE.inc.php` in each of the three app roots
- **Globs/greps used**:
  - `ls lib/pkp/schemas/*.json` and `ls {ojs,omp,ops}-main/schemas/*.json` (file enumeration)
  - JSON parse of each schema for `title`, `description`, `len(properties)`
  - `grep -nE '^\['` on each `config.TEMPLATE.inc.php` (section enumeration)
  - Per-section key extraction regex `^;?\s*([A-Za-z_][A-Za-z0-9_\[\]"'.]*)\s*=` (includes commented-out default keys, e.g. `;allowed_hosts = ...`)
  - `diff` of the three `config.TEMPLATE.inc.php` files (per-app divergence)
- **Not swept here**: plugin settings forms (owned by the plugins sweep).
- **Maintainer ruling (2026-07-27, Q5)**: pure-infrastructure config sections —
  SET-046..051, SET-056, SET-058..061, SET-065 — are **out of scope with this
  blanket reason**: deployment/server plumbing with no user-facing product
  behavior to specify (mirrors the cli ruling). They satisfy the atom-claim
  invariant as marked here; no spec claims them. Config sections whose keys DO
  change product behavior remain claimable by the features they affect.
- Prop counts are mechanical (`len(properties)`); overlay props merge into the shared schema at runtime.
- Config atoms: the three templates are line-for-line identical apart from app branding/terminology in comments and example values (OJS/OMP/OPS names, journal/press/server wording, default db credentials, `session_cookie_name`), except where a divergence is noted on the atom.

## Atoms — shared entity schemas (lib/pkp/schemas)

| ID | apps | stable pointer | one line |
|---|---|---|---|
| SET-001 | ojs omp ops | lib/pkp/schemas/affiliation.json | Affiliation — institution an author is associated with (5 props). |
| SET-002 | ojs omp ops | lib/pkp/schemas/announcement.json | Announcement — announcement/news item (12 props). |
| SET-003 | ojs omp ops | lib/pkp/schemas/author.json | Author — an author of a publication (32 props). |
| SET-004 | ojs omp ops | lib/pkp/schemas/category.json | Category — a category of content (12 props). |
| SET-005 | ojs omp ops | lib/pkp/schemas/citation.json | Citation — reference in a publication to another publication (27 props). |
| SET-006 | ojs omp ops | lib/pkp/schemas/context.json | Context — journal/press/server settings entity (133 props). Overlaid by all three apps (SET-029, SET-035, SET-041). |
| SET-007 | ojs omp ops | lib/pkp/schemas/contributorRole.json | ContributorRole — contributor role for contributors (5 props). |
| SET-008 | ojs omp ops | lib/pkp/schemas/dataCitation.json | DataCitation — a data citation in a publication (11 props). |
| SET-009 | ojs omp ops | lib/pkp/schemas/decision.json | Editorial Decision — accept/decline/request-revisions etc. (12 props). |
| SET-010 | ojs omp ops | lib/pkp/schemas/doi.json | DOI — persistent identifier assigned to published items (6 props). |
| SET-011 | ojs omp ops | lib/pkp/schemas/emailLog.json | Email Log — logged email regarding a submission (11 props). |
| SET-012 | ojs omp ops | lib/pkp/schemas/emailTemplate.json | Email Template — saved email message sent by the application (10 props). |
| SET-013 | ojs omp ops | lib/pkp/schemas/eventLog.json | Event Log — logged action taken regarding the submission (43 props). Overlaid by OMP (SET-036). |
| SET-014 | ojs omp ops | lib/pkp/schemas/funder.json | Funder — a funder in a publication (7 props). |
| SET-015 | ojs omp ops | lib/pkp/schemas/highlight.json | Highlight — text+image+URL highlight at context or site level (9 props). |
| SET-016 | ojs omp ops | lib/pkp/schemas/institution.json | Institution — used for usage stats, subscriptions, etc. (7 props). |
| SET-017 | ojs omp ops | lib/pkp/schemas/navigationMenu.json | Navigation Menu — assignable to a theme navigation area (5 props). |
| SET-018 | ojs omp ops | lib/pkp/schemas/navigationMenuItem.json | Navigation Menu Item — item assignable to navigation menus (12 props). |
| SET-019 | ojs omp ops | lib/pkp/schemas/publication.json | Publication — published version of a submission (52 props). Overlaid by all three apps (SET-032, SET-037, SET-043). |
| SET-020 | ojs omp ops | lib/pkp/schemas/reviewAssignment.json | Review Assignment — reviewer's assignment to review a submission (43 props). Claimed by: reviewer-assignment-and-management. |
| SET-021 | ojs omp ops | lib/pkp/schemas/reviewRound.json | Review Round — round of review assignments in the review stage (7 props). Claimed by: review-stage-and-rounds. |
| SET-022 | ojs omp ops | lib/pkp/schemas/ror.json | Ror — cached institution record from the ror.org data dump (7 props). |
| SET-023 | ojs omp ops | lib/pkp/schemas/section.json | Section — journal/server section or press series (6 props). Overlaid by all three apps (SET-033, SET-038, SET-044). |
| SET-024 | ojs omp ops | lib/pkp/schemas/site.json | Site — overall site hosting one or more contexts; site-level settings entity (37 props). |
| SET-025 | ojs omp ops | lib/pkp/schemas/submission.json | Submission — a submission to the journal/press/server (32 props). Overlaid by all three apps (SET-034, SET-039, SET-045). |
| SET-026 | ojs omp ops | lib/pkp/schemas/submissionFile.json | Submission File — submission file incl. metadata (43 props). Overlaid by OMP (SET-040). |
| SET-027 | ojs omp ops | lib/pkp/schemas/user.json | User — a registered user (49 props). |
| SET-028 | ojs omp ops | lib/pkp/schemas/userGroup.json | UserGroup — user group assigned to one of the allowed roles (14 props). |

## Atoms — app schema overlays (and app-only schemas)

| ID | apps | stable pointer | one line |
|---|---|---|---|
| SET-029 | ojs | ojs-main/schemas/context.json | Journal — OJS overlay of shared context.json (55 props: e.g. subscriptions, publishing, DOI/OAI journal settings). |
| SET-030 | ojs | ojs-main/schemas/galley.json | Galley — OJS app-only schema, no shared counterpart in lib/pkp/schemas (14 props). |
| SET-031 | ojs | ojs-main/schemas/issue.json | Issue — OJS app-only schema, no shared counterpart in lib/pkp/schemas (31 props). |
| SET-032 | ojs | ojs-main/schemas/publication.json | OJS overlay of shared publication.json (8 props, no title key in file). |
| SET-033 | ojs | ojs-main/schemas/section.json | OJS overlay of shared section.json (10 props). |
| SET-034 | ojs | ojs-main/schemas/submission.json | OJS overlay of shared submission.json (4 props). |
| SET-035 | omp | omp-main/schemas/context.json | Press — OMP overlay of shared context.json (24 props). |
| SET-036 | omp | omp-main/schemas/eventLog.json | OMP overlay of shared eventLog.json (1 prop). |
| SET-037 | omp | omp-main/schemas/publication.json | OMP overlay of shared publication.json (6 props). |
| SET-038 | omp | omp-main/schemas/section.json | Series — OMP overlay of shared section.json (11 props). |
| SET-039 | omp | omp-main/schemas/submission.json | OMP overlay of shared submission.json (9 props). |
| SET-040 | omp | omp-main/schemas/submissionFile.json | OMP overlay of shared submissionFile.json (6 props). |
| SET-041 | ops | ops-main/schemas/context.json | Server — OPS overlay of shared context.json (10 props). |
| SET-042 | ops | ops-main/schemas/galley.json | Galley — OPS app-only schema, no shared counterpart in lib/pkp/schemas (14 props). |
| SET-043 | ops | ops-main/schemas/publication.json | OPS overlay of shared publication.json (7 props, no title key in file). |
| SET-044 | ops | ops-main/schemas/section.json | OPS overlay of shared section.json (12 props). |
| SET-045 | ops | ops-main/schemas/submission.json | OPS overlay of shared submission.json (2 props). |

## Atoms — config.TEMPLATE.inc.php sections

Templates are structurally identical across the three apps (same 20 sections, same order); divergences noted per atom. Keys listed include commented-out defaults present in the template.

| ID | apps | stable pointer | one line |
|---|---|---|---|
| SET-046 | ojs omp ops | config.TEMPLATE.inc.php `[general]` | General settings — keys: app_key, installed, base_url, strict, sentry_dsn, session_cookie_name, session_cookie_path, session_lifetime, session_samesite, time_zone, date_format_short, date_format_long, datetime_format_short, datetime_format_long, time_format, allow_url_fopen, restful_urls, base_url[...] overrides, allowed_hosts, trust_x_forwarded_for, show_upgrade_warning, enable_minified, enable_beacon, sitewide_privacy_statement, user_validation_period, sandbox. Per-app: only branding defaults differ (base_url example, session_cookie_name OJSSID/OMPSID/OPSSID). |
| SET-047 | ojs omp ops | config.TEMPLATE.inc.php `[database]` | Database connection — keys: driver, host, username, password, name, port, unix_socket, collation, secure, capath, verify, debug. Per-app: only example credentials differ (ojs/omp/ops). |
| SET-048 | ojs omp ops | config.TEMPLATE.inc.php `[cache]` | Cache settings — keys: default, path, web_cache, web_cache_hours. |
| SET-049 | ojs omp ops | config.TEMPLATE.inc.php `[i18n]` | Locale settings — keys: locale, connection_charset. |
| SET-050 | ojs omp ops | config.TEMPLATE.inc.php `[files]` | File settings — keys: files_dir, public_files_dir, public_user_dir_size, umask. |
| SET-051 | ojs omp ops | config.TEMPLATE.inc.php `[finfo]` | Fileinfo/MIME settings — keys: mime_database_path. |
| SET-052 | ojs omp ops | config.TEMPLATE.inc.php `[security]` | Security settings — keys: cipher, cookie_encryption, force_ssl, force_login_ssl, session_check_ip, encryption, session_expire_on_close, remember_me_lifetime, salt, api_key_secret, reset_seconds, allowed_html, allowed_title_html, allow_plugin_install, plugin_gallery_urls, password_timeout. Claimed by: login-and-sessions. |
| SET-053 | ojs omp ops | config.TEMPLATE.inc.php `[email]` | Email settings — keys: default, sendmail_path, smtp, smtp_server, smtp_port, smtp_auth, smtp_username, smtp_password, smtp_suppress_cert_check, allow_envelope_sender, default_envelope_sender, force_default_envelope_sender, force_dmarc_compliant_from, dmarc_compliant_from_displayname, require_validation, validation_timeout. |
| SET-054 | ojs omp ops | config.TEMPLATE.inc.php `[search]` | Search settings — keys: driver, search_index_name, opensearch_hosts, opensearch_username, opensearch_password, opensearch_ssl_verification, min_word_length, results_per_keyword. |
| SET-055 | ojs omp ops | config.TEMPLATE.inc.php `[oai]` | OAI interface settings — keys: oai, repository_id, oai_max_records. |
| SET-056 | ojs omp ops | config.TEMPLATE.inc.php `[interface]` | UI settings — keys: items_per_page, page_links, navigation_menu_max_depth. |
| SET-057 | ojs omp ops | config.TEMPLATE.inc.php `[captcha]` | Captcha settings — keys: recaptcha, recaptcha_public_key, recaptcha_private_key, captcha_on_register, captcha_on_login, recaptcha_enforce_hostname, altcha, altcha_hmackey, altcha_on_register, altcha_on_login, altcha_on_lost_password, altcha_encrypt_number. |
| SET-058 | ojs omp ops | config.TEMPLATE.inc.php `[cli]` | External command-line tools — keys: tar, xslt_command; OMP only adds xslt_parameter_option (XSL parameter snippet for xslt_command) — the one real key divergence across the three templates. |
| SET-059 | ojs omp ops | config.TEMPLATE.inc.php `[proxy]` | Proxy settings — keys: http_proxy, https_proxy. |
| SET-060 | ojs omp ops | config.TEMPLATE.inc.php `[debug]` | Debug settings — keys: show_stacktrace, display_errors, deprecation_warnings, log_web_service_info. |
| SET-061 | ojs omp ops | config.TEMPLATE.inc.php `[logs]` | Logging settings — keys: log_channel, log_level, log_stacks, log_daily_days, log_formatter. |
| SET-062 | ojs omp ops | config.TEMPLATE.inc.php `[queues]` | Job queue settings — keys: default_connection, default_queue, job_runner, job_runner_max_jobs, job_runner_max_execution_time, job_runner_max_memory, job_runner_cross_request_lock, process_jobs_at_task_scheduler, delete_failed_jobs_after. |
| SET-063 | ojs omp ops | config.TEMPLATE.inc.php `[schedule]` | Scheduled task settings — keys: task_runner, task_runner_interval, scheduled_tasks_report_error_only. Per-app: comment-only diff (OJS/OMP link deploy docs; OPS has `<link-to-documentation>` placeholder). |
| SET-064 | ojs omp ops | config.TEMPLATE.inc.php `[invitations]` | Invitation settings — keys: expiration_days. Claimed by: user-invitations. |
| SET-065 | ojs omp ops | config.TEMPLATE.inc.php `[features]` | New-features flag section — present in all three templates with no keys (empty placeholder). |
