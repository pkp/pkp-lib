<?php

/**
 * @file classes/core/PKPQueueProvider.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPQueueProvider
 *
 * @brief Registers Events Service Provider and boots data on events and their listeners
 */

namespace PKP\core;

use APP\core\Application;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\QueueServiceProvider as IlluminateQueueServiceProvider;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Queue;
use PKP\config\Config;
use PKP\context\ContextDAO;
use PKP\job\models\Job as PKPJobModel;
use PKP\plugins\PluginRegistry;
use PKP\queue\JobRunner;
use PKP\queue\PKPQueueDatabaseConnector;
use PKP\queue\WorkerConfiguration;
use PKP\services\PKPSchemaService;

class PKPQueueProvider extends IlluminateQueueServiceProvider
{
    /**
     * Specific queue to target to run the associated jobs
     */
    protected ?string $queue = null;

    /**
     * Context whose plugins/hooks/schema this worker loaded in Queue::before. `null` (site level) is a
     * first-class value, so pair it with $contextCommitted; the Looping listener compares the next
     * job's context against this to decide when to quit and relaunch for a clean per-context process.
     */
    protected ?int $committedContextId = null;

    /**
     * Whether this worker has committed to a context yet (processed its first job and loaded plugins).
     * Distinguishes "no job processed yet" from "committed to the null/site context".
     */
    protected bool $contextCommitted = false;

    /**
     * Whether the daemon should relaunch itself after a context-change quit. Set true ONLY by the
     * Looping listener on a context change — never for other stop reasons (signals, queue:restart,
     * --max-jobs/--max-time, memory, --stop-when-empty), which exit gracefully for the supervisor.
     */
    protected static bool $relaunchOnContextChange = false;

    /**
     * Set a specific queue to target to run the associated jobs
     */
    public function forQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Apply context-aware filtering to the job query.
     */
    public function applyJobContextAwareFilter(
        EloquentBuilder|QueryBuilder $jobQuery,
        ?int $contextId = null
    ): EloquentBuilder|QueryBuilder {
        if (DB::connection() instanceof PostgresConnection) {
            // Failed to cast payload to jsonb because it contains PHP-serialized objects
            // with null bytes (\u0000) which PostgreSQL's jsonb parser rejects.
            // Use text-based regex to extract the top-level context_id instead using SUBSTRING
            // instead of REGEXP_REPLACE .
            return $jobQuery->where(
                fn ($query) => $query
                    ->whereRaw(
                        "SUBSTRING(payload FROM '\"context_id\":\\s*([0-9]+)') = ?",
                        [(string) $contextId]
                    )
                    ->orWhereRaw(
                        "payload !~ '\"context_id\":\\s*[0-9]+'"
                    )
            );
        }

        return $jobQuery->where(
            fn ($query) => $query
                ->where('payload->context_id', $contextId)
                ->orWhereNull('payload->context_id')
        );
    }

    /**
     * Get a job model builder instance to query the jobs table
     */
    public function getJobModelBuilder(): EloquentBuilder
    {
        return PKPJobModel::isAvailable()
            ->nonEmptyQueue()
            ->when($this->queue, fn ($query) => $query->onQueue($this->queue))
            ->when(is_null($this->queue), fn ($query) => $query->notQueue(PKPJobModel::TESTING_QUEUE))
            ->notExceededAttempts();
    }

    /**
     * Get the worker options object
     */
    public function getWorkerOptions(array $options = []): WorkerOptions
    {
        return new WorkerOptions(
            ...array_values(WorkerConfiguration::withOptions($options)->getWorkerOptions())
        );
    }

    /**
     * Run the queue worker via an infinite loop daemon.
     *
     * Plugins load once per process for the first job's context; the worker quits on a context change
     * (see the Looping listener; null/site is first-class) so a fresh process re-bootstraps for the new
     * context. With $selfRestart and pcntl it re-execs ITSELF in place; otherwise the supervisor and/or
     * the scheduled ProcessQueueJobs task handle recovery, as before.
     */
    public function runJobsViaDaemon(string $connection, string $queue, array $workerOptions = [], bool $selfRestart = true): void
    {
        // Clear any stale value so only a context-change during THIS daemon run can trigger a relaunch.
        static::$relaunchOnContextChange = false;

        // Capture relaunch info now, before any job runs: absolutise the script path (argv[0]) and
        // remember the launch CWD, because a job may chdir() and pcntl_exec() keeps the current CWD.
        // (PHP interpreter flags like php -d/-c are not in $_SERVER['argv'], so they are not preserved
        // across re-exec — configure them via php.ini/env when relying on self-restart.)
        $launchCwd = getcwd();
        $relaunchArgv = $_SERVER['argv'] ?? [];
        if (isset($relaunchArgv[0])) {
            $relaunchArgv[0] = realpath($relaunchArgv[0]) ?: $relaunchArgv[0];
        }

        $worker = $this->app->get('queue.worker'); /** @var \Illuminate\Queue\Worker $worker */

        $worker
            ->setCache($this->app->get('cache.store'))
            ->daemon(
                $connection,
                $queue,
                $this->getWorkerOptions($workerOptions)
            );

        // Daemon loop exited. If it quit because the next job changed context, re-exec a fresh,
        // identical process (pcntl_exec keeps the same PID and never returns on success) so
        // plugins/hooks/schema re-bootstrap for the new context. If pcntl is missing or the exec
        // fails, just exit 0 (unchanged) and let the supervisor / ProcessQueueJobs task recover.
        if ($selfRestart && static::$relaunchOnContextChange && function_exists('pcntl_exec') && $relaunchArgv) {
            static::$relaunchOnContextChange = false;

            // Restore the launch directory so a relative script path in argv[0] still resolves.
            if ($launchCwd !== false) {
                @chdir($launchCwd);
            }
            // Re-run the original invocation (php <jobs.php> work <original options>) with an
            // absolute script path. Environment is inherited from the current process.
            pcntl_exec(PHP_BINARY, $relaunchArgv);

            // Reaching here means pcntl_exec failed; fall through to a normal return (exit 0).
        }
    }

    /**
     * Run the queue worker to process queue the jobs
     */
    public function runJobInQueue(?EloquentBuilder $jobBuilder = null): bool
    {
        $job = $jobBuilder
            ? $jobBuilder->limit(1)->first()
            : $this->getJobModelBuilder()->limit(1)->first();

        if ($job === null) {
            return false; // this will signal that there are no jobs to run
        }

        $queueWorker = app()->get('queue.worker'); /** @var \Illuminate\Queue\Worker $queueWorker */

        $queueWorker->runNextJob(
            Config::getVar('queues', 'default_connection', 'database'),
            $job->queue ?? Config::getVar('queues', 'default_queue', 'queue'),
            $this->getWorkerOptions()
        );

        return true;
    }

    /**
     * Bootstrap any application services.
     *
     */
    public function boot()
    {
        if (Config::getVar('queues', 'job_runner', true)) {
            $currentWorkingDir = getcwd();
            register_shutdown_function(function () use ($currentWorkingDir) {

                // restore the current working directory
                // see: https://www.php.net/manual/en/function.register-shutdown-function.php#refsect1-function.register-shutdown-function-notes
                chdir($currentWorkingDir);

                // As this runs at the current request's end but the 'register_shutdown_function' registered
                // at the service provider's registration time at application initial bootstrapping,
                // need to check the maintenance status within the 'register_shutdown_function'
                if (Application::get()->isUnderMaintenance()) {
                    return;
                }

                if (Config::getVar('general', 'sandbox', false)) {
                    error_log(__('admin.cli.tool.jobs.sandbox.message'));
                    return;
                }

                // We only want to Job Runner for the web request life cycle
                // not in any CLI based request life cycle
                if (app()->runningInConsole()) {
                    return;
                }

                // Not to run in unit test mode as part of the application lifecycle
                if (app()->runningUnitTests()) {
                    return;
                }

                $jobRunner = app('jobRunner'); /** @var \PKP\queue\JobRunner $jobRunner */
                $jobRunner
                    ->setCurrentContextId(Application::get()->getRequest()->getContext()?->getId())
                    ->withMaxExecutionTimeConstrain()
                    ->withMaxJobsConstrain()
                    ->withMaxMemoryConstrain()
                    ->withEstimatedTimeToProcessNextJobConstrain()
                    ->processJobs();
            });
        }

        Queue::failing(function (JobFailed $event) {
            $contextId = $event->job->payload()['context_id'] ?? 'unknown';
            error_log("Job failed for context_id {$contextId}: {$event->exception}");

            app('queue.failer')->log(
                $event->connectionName,
                $event->job->getQueue(),
                $event->job->getRawBody(),
                json_encode([
                    'message' => $event->exception->getMessage(),
                    'code' => $event->exception->getCode(),
                    'file' => $event->exception->getFile(),
                    'line' => $event->exception->getLine(),
                    'trace' => $event->exception->getTrace(),
                ])
            );

            // Clear the context for current CLI session when job failed to process
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (app()->runningInConsole() && !Application::get()->isUnderMaintenance()) {
                Application::get()->clearCliContext();
            }
        });

        // We will only register the payload creator if the application is not under maintenance
        // to prevent any unintended DB access.
        if (!Application::get()->isUnderMaintenance()) {
            Queue::createPayloadUsing(function (string $connection, string $queue, array $payload) {
                // If a `context_id` already exists, will not try to set a new one.
                if (array_key_exists('context_id', $payload)) {
                    return [];
                }

                $jobInstance = $payload['data']['command']; /** @var \Illuminate\Contracts\Queue\ShouldQueue $jobInstance */

                if ($jobInstance instanceof \PKP\queue\ContextAwareJob) {
                    return ['context_id' => $jobInstance->getContextId()];
                }

                return [];
            });
        }

        Queue::before(function (JobProcessing $event) {
            // Set the context for current CLI session if available right before job start processing
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (!app()->runningInConsole() || Application::get()->isUnderMaintenance()) {
                return;
            }

            $contextId = $event->job->payload()['context_id'] ?? null;

            $contextDao = Application::getContextDAO();

            // Validate if the context exists
            if ($contextId) {
                $context = $contextDao->getById($contextId);

                if (!$context) {
                    $jobName = $event->job->payload()['displayName'] ?? 'Unknown';

                    // The job's context no longer exists (e.g. the journal was deleted after the job was
                    // enqueued) and won't reappear, so retrying is pointless. Fail it immediately.
                    $event->job->fail(new \RuntimeException(
                        "Job execution failed: Invalid context_id {$contextId}. The context does not exist in the database for job: {$jobName}."
                    ));

                    return;
                }

                // Set the CLI context before plugins load. loadCategory() already passes $contextId to
                // each plugin's register(), so this mainly serves the job runtime: code reading
                // $request->getContext() resolves via the getCliContext() fallback in PKPRouter. Re-set
                // per job — Queue::after clears it after each one, while the reconcile below runs once per worker.
                Application::get()->setCliContext($context);
            }

            // Load the context's enabled generic + pubIds plugins once per worker, mirroring
            // Dispatcher::dispatch(). The Looping listener keeps a worker single-context, so one load
            // per process suffices. Skipped under unit tests to avoid unintended side effects.
            if (!$this->contextCommitted && !app()->runningUnitTests()) {
                PluginRegistry::loadCategory('generic', true, $contextId);
                PluginRegistry::loadCategory('pubIds', true, $contextId);

                // Commit the context whose plugins just loaded (from the actual job, not the Looping
                // peek — avoids a first-job peek/pop race) so Looping can detect a later context change.
                $this->committedContextId = $contextId !== null ? (int) $contextId : null;
                $this->contextCommitted = true;

                // Plugins may extend the `context` schema via Schema::get::context (e.g. plagiarism's
                // iThenticate settings), but the schema + Context object above were built before they
                // registered. Mirror Dispatcher::dispatch(): reload them so the job sees the full set.
                if ($contextId) {
                    $this->reconcileCliContextAfterPluginLoad($contextDao, (int) $contextId);
                }
            }
        });

        Queue::after(function (JobProcessed $event) {
            // Clear the context for current CLI session if available when job finish the processing
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (app()->runningInConsole() && !Application::get()->isUnderMaintenance()) {
                Application::get()->clearCliContext();
            }
        });

        // Peek the next job before popping it: if its context differs from the one this worker
        // committed to in Queue::before, quit so a fresh process re-bootstraps (runJobsViaDaemon()
        // relaunches in place). `null`/site is first-class, so any change relaunches; not gated on
        // multi-context. Daemon-only — run/scheduler/web JobRunner never fire Looping.
        $this->app['events']->listen(Looping::class, function (Looping $event) {

            if (!app()->runningInConsole() || app()->runningUnitTests()) {
                return true;
            }

            if (!$this->nextJobChangesContext($event->connectionName, $event->queue)) {
                return true; // Same context (or nothing to compare yet) — keep processing.
            }

            // Context change — set the relaunch flag (only this branch does) and quit before popping;
            // runJobsViaDaemon() then re-execs a fresh worker for the new context.
            static::$relaunchOnContextChange = true;
            app('queue.worker')->shouldQuit = true;
            return false; // pauseWorker() will see shouldQuit and exit the loop.
        });
    }

    /**
     * Peek (no pop/lock/reserve) at the next job for the connection/queue and report whether its
     * context differs from the one this worker committed to in Queue::before. `null`/site is
     * first-class, so any change counts; not gated on multi-context. Extracted from the Looping
     * listener for testability (daemon-only — run/scheduler/web JobRunner never fire Looping).
     *
     * @return bool true if the worker should quit to re-bootstrap for the new context; false when it
     *   hasn't committed yet, there is no next job, or the next job shares the committed context.
     */
    protected function nextJobChangesContext(string $connectionName, string $queue): bool
    {
        // Nothing processed yet → no committed context to compare against.
        if (!$this->contextCommitted) {
            return false;
        }

        // Mirror DatabaseQueue's availability predicate (available + reserved-but-expired) using the
        // connection's retry_after, so the peek matches the job the worker will actually pop.
        $retryAfter = (int) (config("queue.connections.{$connectionName}.retry_after") ?? 60);
        $now = now()->getTimestamp();
        $reservedExpiredBefore = $now - $retryAfter;

        $nextJob = DB::table('jobs')
            ->where('queue', $queue)
            ->where(fn (QueryBuilder $query) => $query
                ->where(fn (QueryBuilder $available) => $available
                    ->whereNull('reserved_at')
                    ->where('available_at', '<=', $now))
                ->orWhere('reserved_at', '<=', $reservedExpiredBefore))
            ->orderBy('id', 'asc')
            ->first();

        if (!$nextJob) {
            return false; // No next job to compare against; keep looping.
        }

        $payload = json_decode($nextJob->payload, true);
        $nextContextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;

        return $nextContextId !== $this->committedContextId;
    }

    /**
     * Reconcile the CLI context's schema and object after context-scoped plugins have loaded.
     *
     * Plugins can extend the `context` schema via Schema::get::context, but Queue::before caches the
     * schema and builds the Context object before they register. Mirror Dispatcher::dispatch():
     * force-reload the schema and rebuild the object so the job sees the complete set.
     */
    protected function reconcileCliContextAfterPluginLoad(ContextDAO $contextDao, int $contextId): void
    {
        // Re-fire the Schema::get::context hook now that the context-scoped plugin hooks exist.
        app()->get('schema')->get(PKPSchemaService::SCHEMA_CONTEXT, true);

        // Rebuild the Context object from the now-enriched schema. getById performs no object
        // caching, so this returns a fresh object that includes the plugin-added settings.
        Application::get()->setCliContext($contextDao->getById($contextId));
    }

    /**
     * Register the database queue connector.
     *
     * @param  \Illuminate\Queue\QueueManager  $manager
     */
    protected function registerDatabaseConnector($manager)
    {
        $manager->addConnector('database', function () {
            return new PKPQueueDatabaseConnector($this->app['db']);
        });
    }

    /**
     * Register the queue worker.
     */
    protected function registerWorker()
    {
        $this->app->singleton('queue.worker', function ($app) {
            $isDownForMaintenance = function () {
                return $this->app->isDownForMaintenance();
            };

            $resetScope = function () use ($app) {
                if (method_exists($app['db'], 'getConnections')) {
                    foreach ($app['db']->getConnections() as $connection) {
                        $connection->resetTotalQueryDuration();
                        $connection->allowQueryDurationHandlersToRunAgain();
                    }
                }

                $app->forgetScopedInstances();

                return Facade::clearResolvedInstances();
            };

            return new Worker(
                $app['queue'],
                $app['events'],
                $app[ExceptionHandler::class],
                $isDownForMaintenance,
                $resetScope
            );
        });
    }
}
