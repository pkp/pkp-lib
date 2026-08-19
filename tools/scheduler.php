<?php

/**
 * @file tools/scheduler.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class commandScheduler
 *
 * @ingroup tools
 *
 * @brief CLI tool to list and run schedule tasks
 */

namespace PKP\tools;

use APP\core\Application;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Cron\CronExpression;
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
use PKP\core\ConsoleCommandServiceProvider;
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
        'run'       => 'admin.cli.tool.scheduler.options.run.description',
        'list'      => 'admin.cli.tool.scheduler.options.list.description',
        'work'      => 'admin.cli.tool.scheduler.options.work.description',
        'test'      => 'admin.cli.tool.scheduler.options.test.description',
        'usage'     => 'admin.cli.tool.scheduler.options.usage.description',
    ];

    /**
     * Which option will be call?
     */
    protected ?string $option;

    /**
     * Constructor
     */
    public function __construct(array $argv = [])
    {
        parent::__construct($argv);

        array_shift($argv);

        $this->setParameterList($argv);

        $this->option = $this->getParameterList()[0];

        if (!$this->option) {
            throw new CommandNotFoundException(
                __('admin.cli.tool.jobs.empty.option'),
                array_keys(static::AVAILABLE_OPTIONS)
            );
        }

        $this->setCommandInterface();
    }

    /**
     * Parse and execute the command
     */
    public function execute()
    {
        if (!isset(static::AVAILABLE_OPTIONS[$this->option])) {
            throw new CommandNotFoundException(
                __('admin.cli.tool.jobs.option.doesnt.exists', ['option' => $this->option]),
                array_keys(static::AVAILABLE_OPTIONS)
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

        $this->printCommandList(static::AVAILABLE_OPTIONS);
    }

    /**
     * Run all the schedule tasks that are ready/due to run
     */
    protected function run(): void
    {
        if (!$this->shouldProcessTasks()) {
            return;
        }

        [$input, $output] = ConsoleCommandServiceProvider::getConsoleIOInstances();

        $scheduleRunCommand = new ScheduleRunCommand();
        $scheduleRunCommand->setLaravel(PKPContainer::getInstance());
        $scheduleRunCommand->setInput($input);
        $scheduleRunCommand->setOutput(ConsoleCommandServiceProvider::getConsoleOutputStyle());

        $scheduleRunCommand->run($input, $output);
    }

    /**
     * List all the schedule tasks in the system
     *
     * Rendered here rather than delegating to Laravel's ScheduleListCommand so that the next run
     * can be shown as an absolute date. The relative form on its own is ambiguous: Carbon reports
     * a single unit and floors it, so a monthly task 12 days out reads as "1 week from now".
     */
    protected function list(): void
    {
        $outputStyle = ConsoleCommandServiceProvider::getConsoleOutputStyle();

        $schedule = app()->get(Schedule::class); /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
        $events = $schedule->events();

        if (empty($events)) {
            /** @var \Illuminate\Console\View\Components\Factory $components */
            $components = app()->get(\Illuminate\Console\View\Components\Factory::class);
            $components->warning(__('admin.cli.tool.scheduler.tasks.empty'));

            return;
        }

        $appTimezone = Config::getVar('general', 'timezone', 'UTC');
        $now = Carbon::now($appTimezone);
        $terminalWidth = ScheduleListCommand::getTerminalWidth();

        // Pad every cron field to the widest value in the list so the expressions line up
        $spacing = [];
        foreach ($events as $event) {
            foreach (preg_split('/\s+/', $event->getExpression()) as $position => $field) {
                $spacing[$position] = max($spacing[$position] ?? 0, mb_strlen($field));
            }
        }

        // The timezone applies to every task, so state it once here rather than on each row.
        // Deliberately not naming the config key: the scheduler reads [general] timezone, which is
        // absent from config.TEMPLATE.inc.php, while the documented and installer written key is
        // [general] time_zone. Pointing admins at either one would mislead until they are reconciled.
        $outputStyle->writeln('');
        $outputStyle->writeln("  <fg=#6C7280>Timezone:</> {$appTimezone}");
        $outputStyle->writeln('');

        foreach ($events as $event) {
            $fields = preg_split('/\s+/', $event->getExpression());
            $expression = implode(' ', array_map(
                fn ($field, $position) => str_pad($field, $spacing[$position]),
                $fields,
                array_keys($fields)
            ));

            $taskName = $event->command ?? $event->getSummaryForDisplay();
            $taskName = mb_strlen($taskName) > 1 ? "{$taskName} " : '';

            // Resolve the next run in the event's own timezone before presenting it in the
            // application's, matching how ScheduleListCommand::getNextDueDateForEvent() does it
            $eventTimezone = $event->timezone ? (string) $event->timezone : $appTimezone;
            $nextRun = Carbon::instance(
                (new CronExpression($event->getExpression()))->getNextRunDate(Carbon::now($eventTimezone))
            )->setTimezone($appTimezone);

            // The reference time must be passed explicitly, otherwise the difference is measured
            // against the real clock rather than the moment this listing was generated
            $nextRunLabel = 'Next Run:';
            $nextRunValue = $nextRun->format('M j, Y H:i')
                . ' (in ' . $nextRun->diffForHumans($now, CarbonInterface::DIFF_ABSOLUTE) . ')';

            // Only worth stating when a task has opted out of the application wide timezone
            $timezoneNote = $eventTimezone === $appTimezone ? '' : " [{$eventTimezone}]";

            $hasMutex = $event->mutex->exists($event) ? 'Has Mutex › ' : '';

            $dots = str_repeat('.', max(
                $terminalWidth - mb_strwidth(
                    $expression . $taskName . $hasMutex . $nextRunLabel . $nextRunValue . $timezoneNote
                ) - 8,
                0
            ));

            $outputStyle->writeln(sprintf(
                '  <fg=yellow>%s</>  %s<fg=#6C7280>%s %s%s %s%s</>',
                $expression,
                $taskName,
                $dots,
                $hasMutex,
                $nextRunLabel,
                $nextRunValue,
                $timezoneNote
            ));
        }

        $outputStyle->writeln('');
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

        $outputStyle = ConsoleCommandServiceProvider::getConsoleOutputStyle();

        /** @var \Illuminate\Console\View\Components\Factory $components */
        $components = app()->get(\Illuminate\Console\View\Components\Factory::class);

        $components->info(
            __('admin.cli.tool.scheduler.options.work.running.info'),
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
                !Carbon::now()->startOfMinute()->equalTo($lastExecutionStartedAt)) {
                $executions[] = $execution = Process::fromShellCommandline($command);

                $execution->start();

                $lastExecutionStartedAt = Carbon::now()->startOfMinute();
            }

            foreach ($executions as $key => $execution) {
                $output = $execution->getIncrementalOutput()
                    . $execution->getIncrementalErrorOutput();

                $outputStyle->write(ltrim($output, "\n"));

                if (!$execution->isRunning()) {
                    unset($executions[$key]);
                }
            }
        }
    }

    /**
     * Run a specific scheduled task
     * 
     * Useful to test scheduled tasks under development.
     */
    protected function test(): void
    {
        $outputStyle = ConsoleCommandServiceProvider::getConsoleOutputStyle();

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
            $components->warning(__('admin.cli.tool.scheduler.tasks.empty'));
            return;
        }

        if (!empty($name = $this->getParameterValue('name', ''))) {
            $commandBinary = $phpBinary . ' ' . $_SERVER['SCRIPT_NAME'];

            $matches = array_filter(
                $commandNames,
                fn ($commandName) => trim(str_replace($commandBinary, '', $commandName)) === $name
            );

            if (count($matches) !== 1) {
                $components->error(__('admin.cli.tool.scheduler.tasks.notFound'));
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

        if (!$event instanceof CallbackEvent) {
            $components->bulletList([$event->getSummaryForDisplay()]);
        }

        $outputStyle->newLine(1);
    }

    /**
     * Get the selected command name by index.
     *
     * @param array $commandNames   The name of schedule task to retrieve
     * @param bool  $noScroll       Present the tasks list with no scrolling
     * 
     * @return int
     */
    protected function getSelectedCommandByIndex(array $commandNames, bool $noScroll = false): int
    {
        if (count($commandNames) !== count(array_unique($commandNames))) {
            // Some commands (likely closures) have the same name, append unique indexes to each one...
            $uniqueCommandNames = array_map(
                fn ($index, $value) =>"$value [$index]",
                array_keys($commandNames), $commandNames
            );

            $selectedCommand = select(
                __('admin.cli.tool.scheduler.run.prompt'),
                $uniqueCommandNames,
                null,
                $noScroll ? count($commandNames) : 10
            );

            preg_match('/\[(\d+)\]/', $selectedCommand, $choice);

            return (int) $choice[1];
        } else {
            return array_search(
                select(
                    __('admin.cli.tool.scheduler.run.prompt'),
                    $commandNames,
                    null,
                    $noScroll ? count($commandNames) : 10
                ),
                $commandNames
            );
        }
    }

    /**
     * Determine if the process can run scheduled tasks
     */
    protected function shouldProcessTasks(): bool
    {
        /** @var \Illuminate\Console\View\Components\Factory $components */
        $components = app()->get(\Illuminate\Console\View\Components\Factory::class);

        if (Application::isUnderMaintenance()) {
            $components->error(__('admin.cli.tool.scheduler.maintenance.message'));
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

        $message = __('admin.cli.tool.scheduler.mean.those') . PHP_EOL . implode(PHP_EOL, $alternatives);

        $output->errorBlock([$e->getMessage(), $message]);

        return;
    }

    throw $e;
}
