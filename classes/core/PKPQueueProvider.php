<?php

/**
 * @file classes/core/PKPQueueProvider.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPQueueProvider
 *
 * @ingroup core
 *
 * @brief Registers Events Service Provider and boots data on events and their listeners
 */

namespace PKP\core;

use APP\core\Application;
use Illuminate\Queue\Events\Looping;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\QueueServiceProvider as IlluminateQueueServiceProvider;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Queue;
use PKP\config\Config;
use PKP\job\models\Job as PKPJobModel;
use PKP\queue\JobRunner;
use PKP\queue\WorkerConfiguration;
use PKP\queue\PKPQueueDatabaseConnector;
use Throwable;

class PKPQueueProvider extends IlluminateQueueServiceProvider
{
    /**
     * Specific queue to target to run the associated jobs
     */
    protected ?string $queue = null;

    /**
     * Whether the site is a multi-context site
     */
    protected bool $isMultiContextSite = false;

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
    ): EloquentBuilder|QueryBuilder
    {
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
     * Run the queue worker via an infinite loop daemon
     */
    public function runJobsViaDaemon(string $connection, string $queue, array $workerOptions = []): void
    {
        $worker = $this->app->get('queue.worker'); /** @var \Illuminate\Queue\Worker $worker */

        $worker
            ->setCache($this->app->get('cache.store'))
            ->daemon(
                $connection,
                $queue,
                $this->getWorkerOptions($workerOptions)
            );
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
        if (!Application::isUnderMaintenance()) {
            try {
                $this->isMultiContextSite = DB::table(Application::getContextDAO()->tableName)
                    ->where('enabled', 1)
                    ->count() > 1;
            } catch (Throwable $e) {
                $this->isMultiContextSite = false;
            }
        }

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

                error_log('Shutdown function started at: ' . microtime(true));

                $jobRunner = app('jobRunner'); /** @var \PKP\queue\JobRunner $jobRunner */
                $jobRunner
                    ->setCurrentContextId(Application::get()->getRequest()->getContext()?->getId())
                    ->withMaxExecutionTimeConstrain()
                    ->withMaxJobsConstrain()
                    ->withMaxMemoryConstrain()
                    ->withEstimatedTimeToProcessNextJobConstrain()
                    ->processJobs();

                error_log('Shutdown function ended at: ' . microtime(true));
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
            Queue::createPayloadUsing(function(string $connection, string $queue, array $payload) {
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

        Queue::before(function(JobProcessing $event) {
            // Set the context for current CLI session if available right before job start processing
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (!app()->runningInConsole() || Application::get()->isUnderMaintenance()) {
                return;
            }

            $contextId = $event->job->payload()['context_id'] ?? null;

            // Validate if the context exists
            if ($contextId) {
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($contextId);
                
                if (!$context) {
                    $jobName = $event->job->payload()['displayName'] ?? 'Unknown';

                    // Fail the job immediately with a meaningful exception
                    throw new \RuntimeException(
                        "Job execution failed: Invalid context_id {$contextId}. The context does not exist in the database for job: {$jobName}."
                    );
                }

                Application::get()->setCliContext($context);
            }

            if (!app()->runningUnitTests()) {
                // Initialize the locale and load generic plugins for context or no context
                // But will not load when running unit tests as part of the application lifecycle 
                // to avoid any unintended side effect on tests that are not related to queue jobs
                \PKP\plugins\PluginRegistry::loadCategory('generic', false, $contextId);
            }
        });

        Queue::after(function(JobProcessed $event) {
            // Clear the context for current CLI session if available when job finish the processing
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (app()->runningInConsole() && !Application::get()->isUnderMaintenance()) {
                Application::get()->clearCliContext();
            }
        });

        // This listener will check the next job in the queue before processing the current job, 
        // if the next job belongs to a different context, it will signal the worker to quit after
        // finishing the current job, so that the worker can be restarted with the correct context.
        $this->app['events']->listen(Looping::class, function (Looping $event) {

            if (!app()->runningInConsole() || app()->runningUnitTests()) {
                return true;
            }

            if (!$this->isMultiContextSite) {
                return true;
            }

            static $lockedContextId = null;
        
            // Peek at next job WITHOUT popping (no lock, no reserve, no attempt increment)
            $nextJob = DB::table('jobs')
                ->where('queue', $event->queue)
                ->where(fn(QueryBuilder $query) => $query->whereNull('reserved_at')
                    ->orWhere('reserved_at', '<=', now()->subSeconds(90)))
                ->orderBy('id', 'asc')
                ->first();
        
            if (!$nextJob) {
                return true; // No jobs available, continue looping
            }
        
            $payload = json_decode($nextJob->payload, true);
            $nextContextId = $payload['context_id'] ?? null;
        
            // Non-context-aware jobs don't affect locking
            if ($nextContextId === null) {
                return true;
            }
        
            // First context-aware job sets the locked context
            if ($lockedContextId === null) {
                $lockedContextId = $nextContextId;
                return true;
            }
        
            // Different context detected - quit BEFORE popping
            if ($nextContextId && $nextContextId !== $lockedContextId) {
                app('queue.worker')->shouldQuit = true;
                return false; // Skip this iteration, pauseWorker() will check shouldQuit and exit
            }
        
            return true;
        });
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
     *
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
