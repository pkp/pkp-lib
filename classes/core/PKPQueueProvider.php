<?php

declare(strict_types=1);

/**
 * @file classes/core/PKPQueueProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPQueueProvider
 * @ingroup core
 *
 * @brief Registers Events Service Provider and boots data on events and their listeners
 */

namespace PKP\core;

use APP\core\Application;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\QueueServiceProvider as IlluminateQueueServiceProvider;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Queue;
use PKP\config\Config;
use PKP\Domains\Jobs\Interfaces\JobRepositoryInterface;
use PKP\Domains\Jobs\Job as PKPJobModel;
use PKP\Domains\Jobs\JobRunner;
use PKP\Domains\Jobs\Repositories\Job as JobRepository;
use PKP\Domains\Jobs\WorkerConfiguration;

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
        $laravelContainer = PKPContainer::getInstance();

        $laravelContainer['queue.worker']->daemon(
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
            $job->queue,
            $this->getWorkerOptions()
        );
    }

    /**
     * Bootstrap any application services.
     *
     */
    public function boot()
    {
        Queue::failing(function (JobFailed $event) {
            error_log($event->exception->__toString());

            app('queue.failer')->log(
                $event->connectionName,
                $event->job->getQueue(),
                $event->job->getRawBody(),
                $event->exception
            );
        });
    }

    /**
     * Register the service provider.
     *
     */
    public function register()
    {
        parent::register();

        $this->registerDatabaseConnector(app()->get(\Illuminate\Queue\QueueManager::class));

        $this->app->bind(
            JobRepositoryInterface::class,
            JobRepository::class
        );

        if (!Application::get()->isUnderMaintenance() && Config::getVar('queues', 'job_runner', false)) {
            register_shutdown_function(function () {
                (new JobRunner())
                    ->withMaxJobsConstrain()
                    ->setMaxJobsToProcess(1)
                    ->processJobs();
            });
        }
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
