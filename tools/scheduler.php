<?php

/**
 * @file tools/scheduler.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class commandSchedular
 *
 * @ingroup tools
 *
 * @brief CLI tool to list and run schedule tasks
 */

namespace PKP\tools;

use APP\core\Application;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ProcessUtils;
use Illuminate\Console\Scheduling\ScheduleListCommand;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use PKP\config\Config;
use PKP\cliTool\traits\HasCommandInterface;
use PKP\cliTool\traits\HasParameterList;
use PKP\core\PKPContainer;
use PKP\cliTool\CommandLineTool;
use PKP\core\PKPConsoleCommandServiceProvider;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidArgumentException as CommandInvalidArgumentException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;
use function Laravel\Prompts\select;

define('APP_ROOT', dirname(__FILE__, 4));
require_once APP_ROOT . '/tools/bootstrap.php';

class CommandScheduler extends CommandLineTool
{
    use HasParameterList;
    use HasCommandInterface;
    
    protected const AVAILABLE_OPTIONS = [
        'run'       => 'admin.cli.tool.schedular.options.run.description',
        'list'      => 'admin.cli.tool.schedular.options.list.description',
        'work'      => 'admin.cli.tool.schedular.options.work.description',
        'test'      => 'admin.cli.tool.schedular.options.test.description',
        'usage'     => 'admin.cli.tool.schedular.options.usage.description',
    ];

    /**
     * Which option will be call?
     */
    protected ?string $option;

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

        $this->setCommandInterface();
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

    /**
     * Print command usage information.
     */
    public function usage()
    {
        $this->getCommandInterface()->line('<comment>' . __('admin.cli.tool.usage.title') . '</comment>');
        $this->getCommandInterface()->line(__('admin.cli.tool.usage.parameters') . PHP_EOL);
        $this->getCommandInterface()->line('<comment>' . __('admin.cli.tool.available.commands', ['namespace' => 'jobs']) . '</comment>');

        $this->printCommandList(self::AVAILABLE_OPTIONS);
    }

    /**
     * Run all the schedule tasks that are ready/due to run
     */
    protected function run(): void
    {
        if (!$this->shouldProcessTasks()) {
            return;
        }

        [$input, $output] = PKPConsoleCommandServiceProvider::getConsoleIOInstances();

        $scheduleRunCommand = new ScheduleRunCommand();
        $scheduleRunCommand->setLaravel(PKPContainer::getInstance());
        $scheduleRunCommand->setInput($input);
        $scheduleRunCommand->setOutput(PKPConsoleCommandServiceProvider::getConsoleOutputStyle());

        $scheduleRunCommand->run($input, $output);
    }

    /**
     * List all the schedule tasks in the system
     */
    protected function list(): void
    {
        [$input, $output] = PKPConsoleCommandServiceProvider::getConsoleIOInstances();

        $scheduleListCommand = new ScheduleListCommand;
        $scheduleListCommand->setLaravel(PKPContainer::getInstance());
        $scheduleListCommand->setInput($input);
        $scheduleListCommand->setOutput(PKPConsoleCommandServiceProvider::getConsoleOutputStyle());

        $scheduleListCommand->run($input, $output);
    }

    /**
     * Run the task scheduling process as worker daemon
     * 
     * This is useful in local dev environment where developers have no need to set up
     * any crontab to run the schedule task periodically.
     */
    protected function work(): void
    {
        if (!$this->shouldProcessTasks()) {
            return;
        }

        $outputStyle = PKPConsoleCommandServiceProvider::getConsoleOutputStyle();

        /** @var \Illuminate\Console\View\Components\Factory $components */
        $components = app()->get(\Illuminate\Console\View\Components\Factory::class);

        $components->info(
            __('admin.cli.tool.schedular.options.work.running.info'),
            OutputInterface::VERBOSITY_NORMAL
        );

        [$lastExecutionStartedAt, $executions] = [Carbon::now()->subMinutes(10), []];

        $command = implode(' ', array_map(fn ($arg) => ProcessUtils::escapeArgument($arg), [
            PHP_BINARY,
            $_SERVER['SCRIPT_NAME'],
            'run',
        ]));

        while (true) {
            usleep(100 * 1000);

            if (Carbon::now()->second === 0 &&
                ! Carbon::now()->startOfMinute()->equalTo($lastExecutionStartedAt)) {
                $executions[] = $execution = Process::fromShellCommandline($command);

                $execution->start();

                $lastExecutionStartedAt = Carbon::now()->startOfMinute();
            }

            foreach ($executions as $key => $execution) {
                $output = $execution->getIncrementalOutput().
                    $execution->getIncrementalErrorOutput();

                $outputStyle->write(ltrim($output, "\n"));

                if (! $execution->isRunning()) {
                    unset($executions[$key]);
                }
            }
        }
    }

    /**
     * Run a specific schedule task
     * 
     * Useful in testing of the schedule tasks in development.
     */
    protected function test(): void
    {
        $outputStyle = PKPConsoleCommandServiceProvider::getConsoleOutputStyle();

        /** @var \Illuminate\Console\View\Components\Factory $components */
        $components = app()->get(\Illuminate\Console\View\Components\Factory::class);

        $phpBinary = ProcessUtils::escapeArgument((new PhpExecutableFinder)->find(false));
        $schedule = app()->get(Schedule::class); /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
        $commands = $schedule->events();

        $commandNames = [];

        foreach ($commands as $command) {
            $commandNames[] = $command->command ?? $command->getSummaryForDisplay();
        }

        if (empty($commandNames)) {
            $components->warning(__('admin.cli.tool.schedular.tasks.empty'));
            return;
        }

        if (! empty($name = $this->getParameterValue('name', ''))) {
            $commandBinary = $phpBinary . ' ' . $_SERVER['SCRIPT_NAME'];

            $matches = array_filter($commandNames, function ($commandName) use ($commandBinary, $name) {
                return trim(str_replace($commandBinary, '', $commandName)) === $name;
            });

            if (count($matches) !== 1) {
                $components->error(__('admin.cli.tool.schedular.tasks.notFound'));
                return;
            }

            $index = key($matches);
        } else {
            $index = $this->getSelectedCommandByIndex($commandNames, $this->hasFlagSet('--no-scroll'));
        }

        $event = $commands[$index];

        $summary = $event->getSummaryForDisplay();

        $command = $event instanceof CallbackEvent
            ? $summary
            : trim(str_replace($phpBinary, '', $event->command));

        $description = sprintf(
            'Running [%s]%s',
            $command,
            $event->runInBackground ? ' in background' : '',
        );

        $components->task($description, fn () => $event->run(PKPContainer::getInstance()));

        if (! $event instanceof CallbackEvent) {
            $components->bulletList([$event->getSummaryForDisplay()]);
        }

        $outputStyle->newLine(1);
    }

    /**
     * Get the selected command name by index.
     *
     * @param  array  $commandNames
     * @return int
     */
    protected function getSelectedCommandByIndex(array $commandNames, bool $noScroll = false): int
    {
        if (count($commandNames) !== count(array_unique($commandNames))) {
            // Some commands (likely closures) have the same name, append unique indexes to each one...
            $uniqueCommandNames = array_map(function ($index, $value) {
                return "$value [$index]";
            }, array_keys($commandNames), $commandNames);

            $selectedCommand = select(
                __('admin.cli.tool.schedular.run.prompt'),
                $uniqueCommandNames,
                null,
                $noScroll ? count($commandNames) : 10
            );

            preg_match('/\[(\d+)\]/', $selectedCommand, $choice);

            return (int) $choice[1];
        } else {
            return array_search(
                select(
                    __('admin.cli.tool.schedular.run.prompt'),
                    $commandNames,
                    null,
                    $noScroll ? count($commandNames) : 10
                ),
                $commandNames
            );
        }
    }

    /**
     * Determine should process schedule tasks
     */
    protected function shouldProcessTasks(): bool
    {
        /** @var \Illuminate\Console\View\Components\Factory $components */
        $components = app()->get(\Illuminate\Console\View\Components\Factory::class);

        if (Application::isUnderMaintenance()) {
            $components->error(__('admin.cli.tool.schedular.maintenance.message'));
            return false;
        }

        if (Config::getVar('general', 'sandbox', false)) {
            $components->error(__('admin.cli.tool.schedule.sandbox.message'));
            return false;
        }

        return true;
    }
}

try {
    $tool = new CommandScheduler($argv ?? []);
    $tool->execute();
} catch (Throwable $e) {
    $output = new \PKP\cliTool\CommandInterface;

    if ($e instanceof CommandInvalidArgumentException) {
        $output->errorBlock([$e->getMessage()]);

        return;
    }

    if ($e instanceof CommandNotFoundException) {
        $alternatives = $e->getAlternatives();

        $message = __('admin.cli.tool.schedular.mean.those') . PHP_EOL . implode(PHP_EOL, $alternatives);

        $output->errorBlock([$e->getMessage(), $message]);

        return;
    }

    throw $e;
}
