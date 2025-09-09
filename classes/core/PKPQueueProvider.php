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
    public function runJobInQueue(): void
    {
        $job = $this->getJobModelBuilder()->limit(1)->first();

        if ($job === null) {
            return;
        }

        $laravelContainer = PKPContainer::getInstance();

        $laravelContainer['queue.worker']->runNextJob(
            'database',
            $job->queue ?? Config::getVar('queues', 'default_queue', 'queue'),
            $this->getWorkerOptions()
        );
    }

    /**
     * Bootstrap any application services.
     *
     */
    public function boot()
    {
        if (Config::getVar('queues', 'job_runner', true)) {
            register_shutdown_function(function () {
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

                (new JobRunner($this))
                    ->withMaxExecutionTimeConstrain()
                    ->withMaxJobsConstrain()
                    ->withMaxMemoryConstrain()
                    ->withEstimatedTimeToProcessNextJobConstrain()
                    ->processJobs();
            });
        }

        Queue::failing(function (JobFailed $event) {
            error_log($event->exception->__toString());

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
        });

        Queue::createPayloadUsing(function(string $connection, string $queue, array $payload) {
            
            // If a job dispatch on CLI (e.g. TestJobSuccess/TestJobFailure),
            // there is simple no way to get the context(journal/press/server) information
            // and no need to set the context id information in job payload
            if (Application::isRunningOnCLI()) {
                return [];
            }

            return [
                'context_id' => Application::get()->getRequest()->getContext()->getId(),
            ];
        });

        Queue::before(function(JobProcessing $event){
            // Set the context for current CLI session if available right before job start processing
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (Application::isRunningOnCLI()) {
                Application::get()->setCliContext($event->job->payload()['context_id'] ?? null);
            }
        });

        Queue::after(function(JobProcessed $event){
            // Clear the context for current CLI session if available when job finish the processing
            // Not necessary when jobs are running via JobRunner as that runs at the end of request life cycle
            if (Application::isRunningOnCLI()) {
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
