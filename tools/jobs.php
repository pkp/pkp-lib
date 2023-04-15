<?php

declare(strict_types=1);

/**
 * @file tools/jobs.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class commandJobs
 * @ingroup tools
 *
 * @brief CLI tool to list, iterate and purge queued jobs on database
 */

namespace PKP\tools;

use APP\core\Application;
use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use PKP\cliTool\CommandLineTool;
use PKP\config\Config;
use PKP\job\models\Job as PKPJobModel;
use PKP\jobs\testJobs\TestJobFailure;
use PKP\jobs\testJobs\TestJobSuccess;
use PKP\queue\WorkerConfiguration;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidArgumentException as CommandInvalidArgumentException;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Throwable;

define('APP_ROOT', dirname(__FILE__, 4));
require_once APP_ROOT . '/tools/bootstrap.php';

class commandInterface
{
    use InteractsWithIO;

    public function __construct()
    {
        $output = new OutputStyle(
            new StringInput(''),
            new StreamOutput(fopen('php://stdout', 'w'))
        );

        $this->setOutput($output);
    }

    public function errorBlock(array $messages = [], ?string $title = null): void
    {
        $this->getOutput()->block(
            $messages,
            $title,
            'fg=white;bg=red',
            ' ',
            true
        );
    }
}

class commandJobs extends CommandLineTool
{
    protected const AVAILABLE_OPTIONS = [
        'list' => 'admin.cli.tool.jobs.available.options.list.description',
        'purge' => 'admin.cli.tool.jobs.available.options.purge.description',
        'test' => 'admin.cli.tool.jobs.available.options.test.description',
        'total' => 'admin.cli.tool.jobs.available.options.total.description',
        'help' => 'admin.cli.tool.jobs.available.options.help.description',
        'run' => 'admin.cli.tool.jobs.available.options.run.description',
        'work' => 'admin.cli.tool.jobs.available.options.work.description',
        'failed' => 'admin.cli.tool.jobs.available.options.failed.description',
        'usage' => 'admin.cli.tool.jobs.available.options.usage.description',
    ];

    protected const CURRENT_PAGE = 'current';
    protected const PREVIOUS_PAGE = 'previous';
    protected const NEXT_PAGE = 'next';

    /**
     * @var null|string Which option will be call?
     */
    protected $option = null;

    /**
     * @var null|array Parameters and arguments from CLI
     */
    protected $parameterList = null;

    /**
     * CLI interface, this object should extends InteractsWithIO
     */
    protected $commandInterface = null;

    /**
     * Constructor
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        array_shift($argv);

        $this->setParameterList($argv);

        if (!isset($this->getParameterList()[0])) {
            throw new CommandNotFoundException(
                __('admin.cli.tool.jobs.empty.option'),
                array_keys(self::AVAILABLE_OPTIONS)
            );
        }

        $this->option = $this->getParameterList()[0];

        $this->setCommandInterface(new commandInterface());
    }

    public function setCommandInterface(commandInterface $commandInterface): self
    {
        $this->commandInterface = $commandInterface;

        return $this;
    }

    public function getCommandInterface(): commandInterface
    {
        return $this->commandInterface;
    }

    /**
     * Save the parameter list passed on CLI
     *
     * @param array $items Array with parameters and arguments passed on CLI
     *
     */
    public function setParameterList(array $items): self
    {
        $parameters = [];

        foreach ($items as $param) {
            if (strpos($param, '=')) {
                [$key, $value] = explode('=', ltrim($param, '-'));
                $parameters[$key] = $value;

                continue;
            }

            $parameters[] = $param;
        }

        $this->parameterList = $parameters;

        return $this;
    }

    /**
     * Get the parameter list passed on CLI
     *
     */
    public function getParameterList(): ?array
    {
        return $this->parameterList;
    }

    /**
     * Get the value of a specific parameter
     *
     * @param mixed $default
     *
     */
    protected function getParameterValue(string $parameter, mixed $default = null): mixed
    {
        if (!isset($this->getParameterList()[$parameter])) {
            return $default;
        }

        return $this->getParameterList()[$parameter];
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        $this->getCommandInterface()->line('<comment>' . __('admin.cli.tool.usage.title') . '</comment>');
        $this->getCommandInterface()->line(__('admin.cli.tool.usage.parameters') . PHP_EOL);
        $this->getCommandInterface()->line('<comment>' . __('admin.cli.tool.available.commands', ['namespace' => 'jobs']) . '</comment>');

        $this->printUsage(self::AVAILABLE_OPTIONS);
    }

    /**
     * Alias for usage command
     */
    public function help(): void
    {
        $this->usage();
    }

    /**
     * Retrieve the columnWidth based on the commands text size
     */
    protected function getColumnWidth(array $commands): int
    {
        $widths = [];

        foreach ($commands as $command) {
            $widths[] = Helper::width($command);
        }

        return $widths ? max($widths) + 2 : 0;
    }

    /**
     * Failed jobs list/redispatch/remove
     */
    protected function failed(): void
    {
        $parameterList = $this->getParameterList();

        if (in_array('--redispatch', $parameterList) || ($jobIds = $this->getParameterValue('redispatch'))) {
            $jobsCount = Repo::failedJob()->redispatchToQueue(
                $this->getParameterValue('--queue'),
                collect(explode(',', $jobIds ?? ''))
                    ->filter()
                    ->map(fn ($item) => (int)$item)
                    ->toArray()
            );
            $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.failed.redispatch.successful', ['jobsCount' => $jobsCount]));
            return;
        }

        if (in_array('--clear', $parameterList) || ($jobIds = $this->getParameterValue('clear'))) {
            $jobsCount = Repo::failedJob()->deleteJobs(
                $this->getParameterValue('--queue'),
                collect(explode(',', $jobIds ?? ''))
                    ->filter()
                    ->map(fn ($item) => (int)$item)
                    ->toArray()
            );
            $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.failed.clear.successful', ['jobsCount' => $jobsCount]));
            return;
        }

        array_push($this->parameterList, '--failed');

        $this->list();
    }

    /**
     * List all queued jobs
     */
    protected function list(): void
    {
        $perPage = $this->getParameterValue('perPage', '10');
        $page = $this->getParameterValue('page', '1');

        $parameterList = $this->getparameterList();

        $repository = in_array('--failed', $parameterList) ? Repo::failedJob() : Repo::job();

        $data = $repository
            ->setOutputFormat($repository::OUTPUT_CLI)
            ->perPage((int) $perPage)
            ->setPage((int) $page)
            ->showJobs();

        $this->total();

        $this->getCommandInterface()->table($this->getListTableFromat(), $data->all());

        $pagination = [
            'pagination' => [
                self::CURRENT_PAGE => $data->currentPage(),
                self::PREVIOUS_PAGE => ($data->currentPage() - 1) > 0 ? $data->currentPage() - 1 : 1,
                self::NEXT_PAGE => $data->currentPage(),
            ],
        ];

        if ($data->hasMorePages()) {
            $pagination['pagination'][self::NEXT_PAGE] = $data->currentPage() + 1;
        }

        $this->getCommandInterface()
            ->table(
                [
                    [
                        new TableCell(
                            __('admin.cli.tool.jobs.pagination'),
                            [
                                'colspan' => 3,
                                'style' => new TableCellStyle(['align' => 'center'])
                            ]
                        )
                    ],
                    [
                        __('admin.cli.tool.jobs.pagination.current'),
                        __('admin.cli.tool.jobs.pagination.previous'),
                        __('admin.cli.tool.jobs.pagination.next'),
                    ]
                ],
                $pagination
            );
    }

    /**
     * Get table format for list view
     */
    protected function getListTableFromat(): array
    {
        $listforFailedJobs = in_array('--failed', $this->getparameterList());

        return [
            [
                new TableCell(
                    $listforFailedJobs
                        ? __('admin.cli.tool.jobs.queued.jobs.failed.title')
                        : __('admin.cli.tool.jobs.queued.jobs.title'),
                    [
                        'colspan' => $listforFailedJobs ? 6 : 7,
                        'style' => new TableCellStyle(['align' => 'center'])
                    ]
                )
            ],
            array_merge([
                __('admin.cli.tool.jobs.queued.jobs.fields.id'),
                __('admin.cli.tool.jobs.queued.jobs.fields.queue'),
                __('admin.cli.tool.jobs.queued.jobs.fields.job.display.name'),
            ], $listforFailedJobs ? [
                __('admin.cli.tool.jobs.queued.jobs.fields.connection'),
                __('admin.cli.tool.jobs.queued.jobs.fields.failed.at'),
                __('admin.cli.tool.jobs.queued.jobs.fields.exception'),
            ] : [
                __('admin.cli.tool.jobs.queued.jobs.fields.attempts'),
                __('admin.cli.tool.jobs.queued.jobs.fields.reserved.at'),
                __('admin.cli.tool.jobs.queued.jobs.fields.available.at'),
                __('admin.cli.tool.jobs.queued.jobs.fields.created.at')
            ])
        ];
    }

    /**
     * Run daemon worker process to continue handle jobs
     */
    protected function work(): void
    {
        $parameterList = $this->getParameterList();

        if (in_array('--help', $parameterList)) {
            $this->workerOptionsHelp();
            return;
        }

        if (Application::isUnderMaintenance()) {
            $this->getCommandInterface()->getOutput()->error(__('admin.cli.tool.jobs.maintenance.message'));
            return;
        }

        $connection = $parameterList['connection'] ?? Config::getVar('queues', 'default_connection', 'database');
        $queue = $parameterList['queue'] ?? Config::getVar('queues', 'default_queue', 'queue');

        if (in_array('--test', $parameterList)) {
            $queue = PKPJobModel::TESTING_QUEUE;
        }

        $this->listenForEvents();

        app('pkpJobQueue')->runJobsViaDaemon(
            $connection,
            $queue,
            $this->gatherWorkerOptions($parameterList)
        );
    }

    /**
     * Dispatch jobs into the queue
     */
    protected function run(): void
    {
        if (Application::isUnderMaintenance()) {
            $this->getCommandInterface()->getOutput()->error(__('admin.cli.tool.jobs.maintenance.message'));
            return;
        }

        $parameterList = $this->getParameterList();

        $queue = $parameterList['queue'] ?? Config::getVar('queues', 'default_queue', 'queue');

        if (in_array('--test', $parameterList)) {
            $queue = PKPJobModel::TESTING_QUEUE;
        }

        $jobQueue = app('pkpJobQueue');

        if ($queue && is_string($queue)) {
            $jobQueue = $jobQueue->forQueue($queue);
        }

        $jobBuilder = $jobQueue->getJobModelBuilder();

        if (($jobCount = $jobBuilder->count()) <= 0) {
            $this->getCommandInterface()->getOutput()->info(
                __(
                    'admin.cli.tool.jobs.available.options.run.empty.description',
                    ['queueName' => $queue,]
                )
            );

            return;
        }

        $this->listenForEvents();

        while ($jobBuilder->count()) {
            $jobQueue->runJobInQueue();

            if (in_array('--once', $parameterList)) {
                $jobCount = 1;
                break;
            }
        }

        $this->getCommandInterface()->getOutput()->success(
            __(
                'admin.cli.tool.jobs.available.options.run.completed.description',
                ['jobCount' => $jobCount, 'queueName' => $queue,]
            )
        );
    }

    /**
     * Purge queued jobs
     */
    protected function purge(): void
    {
        if (!isset($this->getParameterList()['queue']) && !isset($this->getParameterList()[1])) {
            throw new CommandInvalidArgumentException(__('admin.cli.tool.jobs.purge.without.id'));
        }

        $parameterList = $this->getParameterList();

        if (in_array('--all', $parameterList) || ($queue = $this->getParameterValue('queue'))) {
            if (!Repo::job()->deleteJobs($queue ?? null)) {
                $this->getCommandInterface()->getOutput()->warning(__('admin.cli.tool.jobs.purge.impossible.to.purge.empty'));
                return;
            }

            $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.purge.successful.all'));
            return;
        }

        $deleted = Repo::job()->delete((int) $this->getParameterList()[1]);

        if (!$deleted) {
            throw new CommandInvalidArgumentException(__('admin.cli.tool.jobs.purge.invalid.id'));
        }

        $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.purge.successful'));
    }

    /**
     * Create a test queued job
     */
    protected function test(): void
    {
        $queue = PKPJobModel::TESTING_QUEUE;
        $runnableJob = $this->getParameterList()['only'] ?? null;

        if ($runnableJob && !in_array($runnableJob, ['failed', 'success'])) {
            throw new CommandInvalidArgumentException(__('admin.cli.tool.jobs.test.invalid.option'));
        }

        if (!$runnableJob || $runnableJob === 'failed') {
            dispatch(new TestJobFailure());

            $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.test.job.failed.dispatch.message', ['queueName' => $queue]));
        }

        if (!$runnableJob || $runnableJob === 'success') {
            dispatch(new TestJobSuccess());

            $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.test.job.success.dispatch.message', ['queueName' => $queue]));
        }
    }

    /**
     * Gather worker daemon options
     *
     */
    protected function gatherWorkerOptions(array $parameters = []): array
    {
        $workerConfig = new WorkerConfiguration();

        return [
            'name' => $this->getParameterValue('--name', $workerConfig->getName()),
            'backoff' => $this->getParameterValue('--backoff', $workerConfig->getBackoff()),
            'memory' => $this->getParameterValue('--memory', $workerConfig->getMemory()),
            'timeout' => $this->getParameterValue('--timeout', $workerConfig->getTimeout()),
            'sleep' => $this->getParameterValue('--sleep', $workerConfig->getSleep()),
            'maxTries' => $this->getParameterValue('--tries', $workerConfig->getMaxTries()),
            'force' => $this->getParameterValue('--force', in_array('--force', $parameters) ? true : $workerConfig->getForce()),
            'stopWhenEmpty' => $this->getParameterValue('--stop-when-empty', in_array('--stop-when-empty', $parameters) ? true : $workerConfig->getStopWhenEmpty()),
            'maxJobs' => $this->getParameterValue('--max-jobs', $workerConfig->getMaxJobs()),
            'maxTime' => $this->getParameterValue('--max-time', $workerConfig->getMaxTime()),
            'rest' => $this->getParameterValue('--rest', $workerConfig->getRest()),
        ];
    }

    /**
     * Listen for the queue events in order to update the console output.
     *
     */
    protected function listenForEvents(): void
    {
        $events = app()['events'];

        $events->listen(JobProcessing::class, function ($event) {
            $this->writeOutput($event->job, 'starting');
        });

        $events->listen(JobProcessed::class, function ($event) {
            $this->writeOutput($event->job, 'success');
        });

        $events->listen(JobFailed::class, function ($event) {
            $this->writeOutput($event->job, 'failed');
        });
    }

    /**
     * Write the status output for the queue worker.
     *
     * @param  string  $status
     *
     */
    protected function writeOutput(Job $job, $status): void
    {
        match ($status) {
            'starting' => $this->writeStatus($job, 'Processing', 'comment'),
            'success' => $this->writeStatus($job, 'Processed', 'info'),
            'failed' => $this->writeStatus($job, 'Failed', 'error'),
        };
    }

    /**
     * Format the status output for the queue worker.
     *
     * @param  string  $status
     * @param  string  $type
     */
    protected function writeStatus(Job $job, $status, $type): void
    {
        $this->getCommandInterface()->getOutput()->writeln(sprintf(
            "<{$type}>[%s][%s] %s</{$type}> %s",
            Carbon::now()->format('Y-m-d H:i:s'),
            $job->getJobId(),
            str_pad("{$status}:", 11),
            $job->resolveName()
        ));
    }

    /**
     * Print work command options information.
     */
    protected function workerOptionsHelp(): void
    {
        $this->getCommandInterface()->line('<comment>' . __('admin.cli.tool.jobs.work.options.title') . '</comment>');
        $this->getCommandInterface()->line(__('admin.cli.tool.jobs.work.options.usage') . PHP_EOL);
        $this->getCommandInterface()->line('<comment>' . __('admin.cli.tool.jobs.work.options.description') . '</comment>');

        $workerConfig = new WorkerConfiguration();

        $options = [
            '--connection[=CONNECTION]' => __('admin.cli.tool.jobs.work.option.connection.description', ['default' => Config::getVar('queue', 'default_connection', 'database')]),
            '--queue[=QUEUE]' => __('admin.cli.tool.jobs.work.option.queue.description', ['default' => Config::getVar('queue', 'default_queue', 'queue')]),
            '--name[=NAME]' => __('admin.cli.tool.jobs.work.option.name.description', ['default' => $workerConfig->getName()]),
            '--backoff[=BACKOFF]' => __('admin.cli.tool.jobs.work.option.backoff.description', ['default' => $workerConfig->getBackoff()]),
            '--memory[=MEMORY]' => __('admin.cli.tool.jobs.work.option.memory.description', ['default' => $workerConfig->getMemory()]),
            '--timeout[=TIMEOUT]' => __('admin.cli.tool.jobs.work.option.timeout.description', ['default' => $workerConfig->getTimeout()]),
            '--sleep[=SLEEP]' => __('admin.cli.tool.jobs.work.option.sleep.description', ['default' => $workerConfig->getSleep()]),
            '--tries[=TRIES]' => __('admin.cli.tool.jobs.work.option.tries.description', ['default' => $workerConfig->getMaxTries()]),
            '--force' => __('admin.cli.tool.jobs.work.option.force.description', ['default' => $workerConfig->getForce() ? 'true' : 'false']),
            '--stop-when-empty' => __('admin.cli.tool.jobs.work.option.stopWhenEmpty.description', ['default' => $workerConfig->getStopWhenEmpty() ? 'true' : 'false']),
            '--max-jobs[=MAX-JOBS]' => __('admin.cli.tool.jobs.work.option.maxJobs.description', ['default' => $workerConfig->getMaxJobs()]),
            '--max-time[=MAX-TIME]' => __('admin.cli.tool.jobs.work.option.maxTime.description', ['default' => $workerConfig->getMaxTime()]),
            '--rest[=REST]' => __('admin.cli.tool.jobs.work.option.rest.description', ['default' => $workerConfig->getRest()]),
            '--test' => __('admin.cli.tool.jobs.work.option.test.description'),
        ];

        $this->printUsage($options, false);
    }

    /**
     * Print given options in a pretty way.
     */
    protected function printUsage(array $options, bool $shouldTranslate = true): void
    {
        $width = $this->getColumnWidth(array_keys($options));

        foreach ($options as $commandName => $description) {
            $spacingWidth = $width - Helper::width($commandName);
            $this->getCommandInterface()->line(
                sprintf(
                    '  <info>%s</info>%s%s',
                    $commandName,
                    str_repeat(' ', $spacingWidth),
                    $shouldTranslate ? __($description) : $description
                )
            );
        }
    }

    /**
     * Display the queued/failed jobs quantity
     */
    protected function total(): void
    {
        $parameterList = $this->getParameterList();

        $total = in_array('--failed', $parameterList)
            ? Repo::failedJob()->total()
            : Repo::job()->total();

        $outputInterface = $this->getCommandInterface()->getOutput();

        if (in_array('--failed', $parameterList)) {
            $method = $total > 0 ? 'error' : 'success';
            $outputInterface->{$method}(__('admin.cli.tool.jobs.total.failed.jobs', ['total' => $total]));
            return;
        }

        $outputInterface->warning(__('admin.cli.tool.jobs.total.jobs', ['total' => $total]));
    }

    /**
     * Parse and execute the command
     */
    public function execute()
    {
        if (!isset(self::AVAILABLE_OPTIONS[$this->option])) {
            throw new CommandNotFoundException(
                __('admin.cli.tool.jobs.option.doesnt.exists', ['option' => $this->option]),
                array_keys(self::AVAILABLE_OPTIONS)
            );
        }

        $this->{$this->option}();
    }
}

try {
    $tool = new commandJobs($argv ?? []);
    $tool->execute();
} catch (Throwable $e) {
    $output = new commandInterface();

    if ($e instanceof CommandInvalidArgumentException) {
        $output->errorBlock([$e->getMessage()]);

        return;
    }

    if ($e instanceof CommandNotFoundException) {
        $alternatives = $e->getAlternatives();

        $message = __('admin.cli.tool.jobs.mean.those') . PHP_EOL . implode(PHP_EOL, $alternatives);

        $output->errorBlock([$e->getMessage(), $message]);

        return;
    }

    throw $e;
}
