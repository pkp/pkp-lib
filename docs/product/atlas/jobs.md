# Atlas — jobs (queued jobs + scheduled tasks)

- **Modality**: jobs — Laravel queued jobs (`jobs/` trees) and scheduled tasks
  (`classes/task*` trees + scheduler registrations).
- **Sweep date**: 2026-07-26
- **Trees swept**:
  - `ojs-main/lib/pkp/jobs/`, `ojs-main/lib/pkp/classes/task/`,
    `ojs-main/lib/pkp/classes/scheduledTask/` — lib/pkp submodule checked out at
    `24cb2d923c` (identical checkout in all three apps; note: OJS gitlink records
    `9a91dc2e` — working tree ahead of the recorded pointer)
  - `ojs-main/jobs/`, `ojs-main/classes/tasks/`, `ojs-main/classes/scheduler/`
  - `omp-main/jobs/`, `omp-main/classes/tasks/`, `omp-main/classes/scheduler/`
  - `ops-main/jobs/`, `ops-main/classes/tasks/`, `ops-main/classes/scheduler/`
  - Plugin scheduled tasks: `plugins/` in all three apps + `lib/pkp/plugins/`
- **Method** (exact commands):
  - `find <tree>/jobs lib/pkp/jobs -name "*.php"` (per app)
  - `find lib/pkp/classes/task <app>/classes/tasks <app>/classes/scheduler -name "*.php"`
  - `grep -rn "extends ScheduledTask\|extends FileLoader\|extends PKPUsageStatsLoader" <plugins trees>`
  - `grep -rln "HasTaskScheduler" <plugins trees>` (plugin scheduler registration)
  - Frequencies read from `PKPScheduler::registerSchedules()` and each app
    `APP\scheduler\Scheduler::registerSchedules()`, plus plugin `registerSchedules()`.
- **Notes**:
  - `registry/scheduledTasks.xml` does NOT exist in any of the three apps at this
    version — scheduling is registered in code via `PKPScheduler`/`Scheduler`
    (Laravel `Illuminate\Console\Scheduling\Schedule`). Plugin tasks are
    registered via the `HasTaskScheduler` interface.
  - App job/task classes with the same `APP\...` class name across apps are one
    atom, with the apps column showing which apps carry the file.
  - Shared (lib/pkp) classes exist in all three apps via the submodule; where a
    shared scheduled task is registered by only some app Schedulers, the
    registration facts are stated in the one-liner (no liveness judgment).
  - Name collision to watch: `PKP\task\ReviewReminder` (scheduled) vs
    `PKP\jobs\email\ReviewReminder` (queued); likewise `EditorialReminders`
    (task) vs `EditorialReminder` (job).

## Atoms

### Shared queued jobs — lib/pkp/jobs/

| ID | Apps | Pointer | Description |
|---|---|---|---|
| JOB-001 | ojs omp ops | `PKP\jobs\BaseJob` | Queued — abstract base class all PKP jobs extend (queue/connection defaults). |
| JOB-002 | ojs omp ops | `PKP\jobs\bulk\BulkEmailSender` | Queued — sends bulk emails to a batch of users. |
| JOB-003 | ojs omp ops | `PKP\jobs\citation\CrossrefJob` | Queued — retrieves structured citation metadata from Crossref. |
| JOB-004 | ojs omp ops | `PKP\jobs\citation\ExtractPidsJob` | Queued — extracts PIDs (DOI etc.) from raw citations. |
| JOB-005 | ojs omp ops | `PKP\jobs\citation\IsProcessedJob` | Queued — checks/marks citation-processing completion for a publication. |
| JOB-006 | ojs omp ops | `PKP\jobs\citation\OpenAlexJob` | Queued — retrieves structured citation metadata from OpenAlex. |
| JOB-007 | ojs omp ops | `PKP\jobs\citation\OrcidJob` | Queued — retrieves citation author metadata from ORCID. |
| JOB-008 | ojs omp ops | `PKP\jobs\doi\DepositContext` | Queued — deposits all DOIs + metadata for a context to its registration agency. |
| JOB-009 | ojs omp ops | `PKP\jobs\doi\DepositPeerReview` | Queued — deposits a peer-review DOI + metadata to the registration agency. |
| JOB-010 | ojs omp ops | `PKP\jobs\doi\DepositSubmission` | Queued — deposits a submission DOI + metadata to the registration agency. |
| JOB-011 | ojs omp ops | `PKP\jobs\email\EditorialReminder` | Queued — sends an editorial outstanding-tasks reminder email to one editor. |
| JOB-012 | ojs omp ops | `PKP\jobs\email\ReviewReminder` | Queued — sends a review reminder email for one review assignment. Claimed by: reviewer-assignment-and-management. |
| JOB-013 | ojs omp ops | `PKP\jobs\invitations\RemoveExpiredInvitationsJob` | Queued — removes all expired invitations. Claimed by: user-invitations. |
| JOB-014 | ojs omp ops | `PKP\jobs\notifications\NewAnnouncementNotifyUsers` | Queued — system notifications (+opt email) to users on new announcement. |
| JOB-015 | ojs omp ops | `PKP\jobs\notifications\StatisticsReportMail` | Queued — emails editors the monthly editorial statistics report. |
| JOB-016 | ojs omp ops | `PKP\jobs\notifications\StatisticsReportNotify` | Queued — creates system notifications for editors about the monthly report. |
| JOB-017 | ojs omp ops | `PKP\jobs\orcid\DepositOrcidSubmission` | Queued — deposits ORCID work entry to an authorized user's ORCID profile. Claimed by: orcid-integration. |
| JOB-018 | ojs omp ops | `PKP\jobs\orcid\RevokeOrcidToken` | Queued — revokes a user's ORCID access token. Claimed by: orcid-integration. |
| JOB-019 | ojs omp ops | `PKP\jobs\orcid\SendAuthorMail` | Queued — emails an author requesting ORCID verification. Claimed by: orcid-integration. |
| JOB-020 | ojs omp ops | `PKP\jobs\orcid\SendUpdateScopeMail` | Queued — emails a user to update their ORCID OAuth scope. Claimed by: orcid-integration. |
| JOB-021 | ojs omp ops | `PKP\jobs\statistics\ArchiveUsageStatsLogFile` | Queued — archives a processed usage-stats log file. |
| JOB-022 | ojs omp ops | `PKP\jobs\statistics\CompileContextMetrics` | Queued — compiles context-level usage metrics for a day. |
| JOB-023 | ojs omp ops | `PKP\jobs\statistics\CompileMonthlyMetrics` | Queued — compiles/stores monthly usage stats from daily records. |
| JOB-024 | ojs omp ops | `PKP\jobs\statistics\CompileSubmissionMetrics` | Queued — compiles submission-level usage metrics for a day. |
| JOB-025 | ojs omp ops | `PKP\jobs\statistics\PKPProcessUsageStatsLogFile` | Queued — abstract base for processing a usage-stats log file into temp records. |
| JOB-026 | ojs omp ops | `PKP\jobs\statistics\RemoveDoubleClicks` | Queued — removes double-clicks from temp usage records per COUNTER rules. |
| JOB-027 | ojs omp ops | `PKP\jobs\submissions\UpdateSubmissionSearchJob` | Queued — (re)indexes a submission in the search index. |
| JOB-028 | ojs omp ops | `PKP\jobs\testJobs\TestJobFailure` | Queued — test fixture job that always fails (queue smoke-testing). |
| JOB-029 | ojs omp ops | `PKP\jobs\testJobs\TestJobSuccess` | Queued — test fixture job that always succeeds (queue smoke-testing). |

### App queued jobs — <app>/jobs/

| ID | Apps | Pointer | Description |
|---|---|---|---|
| JOB-030 | ojs | `APP\jobs\doi\DepositIssue` | Queued — deposits an issue DOI + metadata to the registration agency. |
| JOB-031 | ojs | `APP\jobs\notifications\IssuePublishedNotifyUsers` | Queued — emails users when a new issue is published. |
| JOB-032 | ojs | `APP\jobs\notifications\OpenAccessMailUsers` | Queued — sends issue open-access notification emails to users. |
| JOB-033 | ojs | `APP\jobs\orcid\DepositOrcidReview` | Queued — deposits a peer-review contribution to reviewer's ORCID profile. Claimed by: orcid-integration. |
| JOB-034 | ojs | `APP\jobs\orcid\ReconcileOrcidReviewPutCode` | Queued — retrieves previously deposited ORCID reviews and stores put-codes. Claimed by: orcid-integration. |
| JOB-035 | ojs omp ops | `APP\jobs\statistics\CompileCounterSubmissionDailyMetrics` | Queued — compiles COUNTER submission daily metrics. |
| JOB-036 | ojs omp ops | `APP\jobs\statistics\CompileCounterSubmissionInstitutionDailyMetrics` | Queued — compiles COUNTER submission institution daily metrics. |
| JOB-037 | ojs | `APP\jobs\statistics\CompileIssueMetrics` | Queued — compiles issue-level usage metrics (OJS issues). |
| JOB-038 | omp | `APP\jobs\statistics\CompileSeriesMetrics` | Queued — compiles series-level usage metrics (OMP series). |
| JOB-039 | ojs omp ops | `APP\jobs\statistics\CompileSubmissionGeoDailyMetrics` | Queued — compiles submission Geo daily metrics. |
| JOB-040 | ojs omp ops | `APP\jobs\statistics\CompileUniqueInvestigations` | Queued — compiles/removes unique investigations per COUNTER rules. |
| JOB-041 | ojs omp ops | `APP\jobs\statistics\CompileUniqueRequests` | Queued — compiles unique requests per COUNTER rules. |
| JOB-042 | ojs omp ops | `APP\jobs\statistics\CompileUsageStatsFromTemporaryRecords` | Queued — compiles temp usage-stats records into the metrics tables. |
| JOB-043 | ojs omp ops | `APP\jobs\statistics\DeleteUsageStatsTemporaryRecords` | Queued — deletes temporary usage-stats records for a load id. |
| JOB-044 | ojs omp ops | `APP\jobs\statistics\ProcessUsageStatsLogFile` | Queued — app subclass of PKPProcessUsageStatsLogFile; parses a usage log file. |

### Shared scheduled tasks — lib/pkp/classes/task/

| ID | Apps | Pointer | Description |
|---|---|---|---|
| JOB-045 | ojs omp ops | `PKP\task\DepositDois` | Scheduled — dispatches automatic DOI deposit jobs for all configured contexts; registered daily (OJS Scheduler only). |
| JOB-046 | ojs omp ops | `PKP\task\EditorialReminders` | Scheduled — dispatches editorial-reminder email jobs; registered monthlyOn(1) (OJS + OMP Schedulers). |
| JOB-047 | ojs omp ops | `PKP\task\FileLoader` | Scheduled — abstract base task for staged file processing (stage/processing/archive/reject dirs). |
| JOB-048 | ojs omp ops | `PKP\task\PKPUsageStatsLoader` | Scheduled — abstract base ETL task for usage-stats log files (extends FileLoader). |
| JOB-049 | ojs omp ops | `PKP\task\ProcessQueueJobs` | Scheduled — processes queued jobs from the scheduler; registered everyMinute (PKPScheduler, all apps). |
| JOB-050 | ojs omp ops | `PKP\task\PublishSubmissions` | Scheduled — publishes submissions scheduled for publication; registered daily (OMP Scheduler only). |
| JOB-051 | ojs omp ops | `PKP\task\RemoveExpiredInvitations` | Scheduled — dispatches expired-invitation cleanup; registered daily (PKPScheduler, all apps). Claimed by: user-invitations. |
| JOB-052 | ojs omp ops | `PKP\task\RemoveFailedJobs` | Scheduled — prunes old failed jobs from the failed-jobs list; registered daily (PKPScheduler, all apps). |
| JOB-053 | ojs omp ops | `PKP\task\RemoveUnvalidatedExpiredUsers` | Scheduled — removes unvalidated users past validation timeout; registered monthlyOn(1) (PKPScheduler, all apps). |
| JOB-054 | ojs omp ops | `PKP\task\ReviewReminder` | Scheduled — dispatches automated reviewer-reminder jobs; registered daily (OJS + OMP Schedulers). |
| JOB-055 | ojs omp ops | `PKP\task\StatisticsReport` | Scheduled — dispatches monthly editorial statistics report notify/mail jobs; registered monthlyOn(1) (PKPScheduler, all apps). |
| JOB-056 | ojs omp ops | `PKP\task\UpdateIPGeoDB` | Scheduled — updates the DB-IP city lite database for Geo stats; registered monthlyOn(10) (PKPScheduler, all apps). |
| JOB-057 | ojs omp ops | `PKP\task\UpdateRorRegistryDataset` | Scheduled — updates the ROR registry tables; registered monthly (PKPScheduler, all apps). |

### App scheduled tasks — <app>/classes/tasks/

| ID | Apps | Pointer | Description |
|---|---|---|---|
| JOB-058 | ojs | `APP\tasks\OpenAccessNotification` | Scheduled — emails users when an issue becomes open access; registered daily (OJS Scheduler). |
| JOB-059 | ojs | `APP\tasks\SubscriptionExpiryReminder` | Scheduled — automated subscription expiry reminder emails; registered monthlyOn(1) (OJS Scheduler). |
| JOB-060 | ojs omp ops | `APP\tasks\UsageStatsLoader` | Scheduled — app ETL of usage-stats logs (extends PKPUsageStatsLoader); registered daily in each app Scheduler. |

### Plugin scheduled tasks (overlap with plugins sweep)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| JOB-061 | ojs | `APP\plugins\generic\doaj\DOAJInfoSender` | Scheduled (plugin) — sends deposits to DOAJ; registered daily via `DOAJExportPlugin::registerSchedules()` (HasTaskScheduler). |
| JOB-062 | ojs | `APP\plugins\generic\crossref\CrossrefCitationDoiCheckTask` | Scheduled (plugin) — fetches/stores matched citation DOIs from Crossref; registered hourly via `CrossrefPlugin::registerSchedules()`. |

### Scheduler / task infrastructure

| ID | Apps | Pointer | Description |
|---|---|---|---|
| JOB-063 | ojs omp ops | `PKP\scheduledTask\PKPScheduler` | Infra — abstract scheduler; registers core scheduled tasks + plugin schedules (CLI) + web-based runner entry. |
| JOB-064 | ojs omp ops | `APP\scheduler\Scheduler` | Infra — per-app PKPScheduler subclass registering app-specific scheduled tasks (one class per app). |
| JOB-065 | ojs omp ops | `PKP\scheduledTask\ScheduledTask` | Infra — abstract base class for all scheduled tasks (execution + logging contract). |
| JOB-066 | ojs omp ops | `PKP\scheduledTask\ScheduledTaskHelper` | Infra — helper for scheduled-task execution logging and admin error notification emails. |
| JOB-067 | ojs omp ops | `PKP\scheduledTask\ScheduleTaskRunner` | Infra — web-based (non-cron) schedule task runner. |
