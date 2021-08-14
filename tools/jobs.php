<?php

declare(strict_types=1);

/**
 * @file tools/jobs.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class jobs
 * @ingroup tools
 *
 * @brief CLI tool to list, iterate and purge queued jobs on database
 */

namespace PKP\tools;

use APP\facades\Repo;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Console\OutputStyle;
use PKP\cliTool\CommandLineTool;
use PKP\Support\Jobs\Entities\TestJob;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidArgumentException as CommandInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\StringInput;

use Symfony\Component\Console\Output\StreamOutput;
use Throwable;

define('APP_ROOT', dirname(__FILE__, 4));
require_once APP_ROOT . '/tools/bootstrap.inc.php';

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

    protected function getParameterValue(string $parameter, string $default = null): ?string
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

        $width = $this->getColumnWidth(array_keys(self::AVAILABLE_OPTIONS));

        foreach (self::AVAILABLE_OPTIONS as $commandName => $description) {
            $spacingWidth = $width - Helper::width($commandName);
            $this->getCommandInterface()->line(
                sprintf(
                    '  <info>%s</info>%s%s',
                    $commandName,
                    str_repeat(' ', $spacingWidth),
                    __($description)
                )
            );
        }
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
     * List all queued jobs
     */
    protected function list(): void
    {
        $perPage = $this->getParameterValue('perPage', '10');
        $page = $this->getParameterValue('page', '1');

        $this->total();

        $data = Repo::job()
            ->setOutputFormat(Repo::job()::OUTPUT_CLI)
            ->perPage((int) $perPage)
            ->setPage((int) $page)
            ->showQueuedJobs();

        $this->getCommandInterface()
            ->table(
                [
                    [
                        new TableCell(
                            __('admin.cli.tool.jobs.queued.jobs.title'),
                            [
                                'colspan' => 7,
                                'style' => new TableCellStyle(['align' => 'center'])
                            ]
                        )
                    ],
                    [
                        __('admin.cli.tool.jobs.queued.jobs.fields.id'),
                        __('admin.cli.tool.jobs.queued.jobs.fields.queue'),
                        __('admin.cli.tool.jobs.queued.jobs.fields.job.display.name'),
                        __('admin.cli.tool.jobs.queued.jobs.fields.attempts'),
                        __('admin.cli.tool.jobs.queued.jobs.fields.reserved.at'),
                        __('admin.cli.tool.jobs.queued.jobs.fields.available.at'),
                        __('admin.cli.tool.jobs.queued.jobs.fields.created.at')
                    ]
                ],
                $data->all(),
            );

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
     * Purge queued jobs
     */
    protected function purge(): void
    {
        if (!isset($this->getParameterList()['queue']) && !isset($this->getParameterList()[1])) {
            throw new CommandInvalidArgumentException(__('admin.cli.tool.jobs.purge.without.id'));
        }

        if (($this->getParameterList()[1] ?? null) === '--all') {
            $this->purgeAllJobs();

            return;
        }

        $queue = $this->getParameterValue('queue');
        if ($queue) {
            $this->purgeAllJobsFromQueue($queue);

            return;
        }

        $deleted = Repo::job()->delete((int) $this->getParameterList()[1]);

        if (!$deleted) {
            throw new CommandInvalidArgumentException(__('admin.cli.tool.jobs.purge.invalid.id'));
        }

        $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.purge.successful'));
    }

    /**
     * Purged all queued jobs
     */
    protected function purgeAllJobs(): void
    {
        $deleted = Repo::job()->deleteAll();

        if (!$deleted) {
            throw new LogicException(__('admin.cli.tool.jobs.purge.impossible.to.purge.all'));
        }

        $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.purge.successful.all'));
    }

    /**
     * Purged all queued jobs from a queue
     */
    protected function purgeAllJobsFromQueue(string $queue): void
    {
        $deleted = Repo::job()->deleteFromQueue($queue);

        if (!$deleted) {
            throw new LogicException(__('admin.cli.tool.jobs.purge.impossible.to.purge.all'));
        }

        $this->getCommandInterface()->getOutput()->success(__('admin.cli.tool.jobs.purge.successful.all'));
    }

    /**
     * Create a test queued job
     */
    protected function test(): void
    {
        dispatch(new TestJob());

        $this->getCommandInterface()->getOutput()->success('Dispatched job!');
    }

    /**
     * Display the queued jobs quantity
     */
    protected function total(): void
    {
        $total = Repo::job()
            ->total();

        $this->getCommandInterface()
            ->getOutput()
            ->warning(__('admin.cli.tool.jobs.total.jobs', ['total' => $total]));
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
