<?php

declare(strict_types=1);

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
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Builder;
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

class PKPQueueProvider extends IlluminateQueueServiceProvider
{
    /**
     * Specific queue to target to run the associated jobs
     */
    protected ?string $queue = null;

    /**
     * Set a specific queue to target to run the associated jobs
     */
    public function forQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Get a job model builder instance to query the jobs table
     */
    public function getJobModelBuilder(): Builder
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
        $worker = PKPContainer::getInstance()['queue.worker']; /** @var \Illuminate\Queue\Worker $worker */

        $worker
            ->setCache(app()->get('cache.store'))
            ->daemon(
                $connection,
                $queue,
                $this->getWorkerOptions($workerOptions)
            );
    }

    /**
     * Run the queue worker to process queue the jobs
     */
    public function runJobInQueue(): bool
    {
        $job = $this->getJobModelBuilder()->limit(1)->first();

        if ($job === null) {
            return false; // this will signal that there are no jobs to run
        }

        $laravelContainer = PKPContainer::getInstance();

        $laravelContainer['queue.worker']->runNextJob(
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

                // Not to run in unit test mode
                if (app()->runningUnitTests()) {
                    return;
                }

                (new JobRunner($this))
                    ->withMaxExecutionTimeConstrain()
                    ->withMaxJobsConstrain()
                    ->withMaxMemoryConstrain()
                    ->withEstimatedTimeToProcessNextJobConstrain()
                    ->processJobs();
            });
        }

        Queue::failing(function (JobFailed $event) {
            $contextId = $event->job->payload()['context_id'] ?? 'unknown';
            error_log("Job failed for context_id {$contextId}: {$event->exception->__toString()}");

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
                // if running in unit tests, no change to payload and immediate return
                if (app()->runningUnitTests()) {
                    return $payload;
                }

                $contextId = null;
                
                if (!array_key_exists('context_id', $payload)) {
                    // This try/catch block is to facilitate the case when the application
                    // running in CLI mode and a job get dispatched in CLI where it trying to
                    // determine the context form the request() . But as the request delegate
                    // to router and in CLI mode, perhaps the router is not available which throws
                    // as \AssertionError. In that case it will fallback to CLI context to get
                    // directly from there if exists a context and set it.
                    try {
                        $contextId = Application::get()->getRequest()->getContext()?->getId();
                    } catch (\Throwable $e) {
                        // error_log('Failed to retrieve context ID from request on queue payload creator with exception: ' . $e->__toString());
                        $contextId = Application::get()->getCliContext();
                    }

                    if ($contextId) {
                        $payload['context_id'] = $contextId;
                    }
                }

                return $payload;
            });
        }
        
        Queue::before(function(JobProcessing $event) {
            // Set the context for current CLI session if available right before job start processing
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (app()->runningInConsole() && !Application::get()->isUnderMaintenance()) {
                // FIXME: should validate the context and fail the job is invalid ?
                Application::get()->setCliContext($event->job->payload()['context_id'] ?? null);
            }
        });

        Queue::after(function(JobProcessed $event) {
            // Clear the context for current CLI session if available when job finish the processing
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (app()->runningInConsole() && !Application::get()->isUnderMaintenance()) {
                Application::get()->clearCliContext();
            }
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
