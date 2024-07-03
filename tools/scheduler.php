<?php

/**
 * @file tools/schedular.php
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
use Illuminate\Console\Scheduling\ScheduleListCommand;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use PKP\config\Config;
use PKP\cliTool\traits\HasCommandInterface;
use PKP\cliTool\traits\HasParameterList;
use PKP\core\PKPContainer;
use PKP\cliTool\CommandLineTool;
use PKP\core\PKPConsoleCommandServiceProvider;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidArgumentException as CommandInvalidArgumentException;
use Throwable;

define('APP_ROOT', dirname(__FILE__, 4));
require_once APP_ROOT . '/tools/bootstrap.php';

class CommandScheduler extends CommandLineTool
{
    use HasParameterList;
    use HasCommandInterface;
    
    protected const AVAILABLE_OPTIONS = [
        'run'       => 'admin.cli.tool.schedular.options.run.description',
        'list'      => 'admin.cli.tool.schedular.options.list.description',
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
        if (Application::isUnderMaintenance()) {
            $this->getCommandInterface()->getOutput()->error(__('admin.cli.tool.schedular.maintenance.message'));
            return;
        }

        // Application is set to sandbox mode and will not run any schedule tasks
        if (Config::getVar('general', 'sandbox', false)) {
            $this->getCommandInterface()->getOutput()->error(__('admin.cli.tool.schedule.sandbox.message'));
            error_log(__('admin.cli.tool.schedule.sandbox.message'));
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
