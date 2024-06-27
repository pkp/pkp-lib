<?php

/**
 * @file tools/schedular.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class commandSchedular
 *
 * @ingroup tools
 *
 * @brief 
 */

namespace PKP\tools;

use APP\core\Application;

use Symfony\Component\Console\Output\BufferedOutput;

use Symfony\Component\Console\Input\ArrayInput;

use PKP\core\PKPContainer;

use Illuminate\Console\Scheduling\ScheduleRunCommand;

use Carbon\Carbon;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleListCommand;
use Illuminate\Console\Scheduling\ScheduleWorkCommand;
use PKP\cliTool\CommandLineTool;
use PKP\config\Config;
use PKP\core\PKPConsoleCommandServiceProvider;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidArgumentException as CommandInvalidArgumentException;
use Symfony\Component\Console\Helper\Helper;
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

class CommandScheduler extends CommandLineTool
{
    protected const AVAILABLE_OPTIONS = [
        'run'       => 'admin.cli.tool.schedular.options.run.description',
        'list'      => 'admin.cli.tool.schedular.options.list.description',
        // 'work'      => 'admin.cli.tool.schedular.options.work.description',
        'usage'     => 'admin.cli.tool.schedular.options.usage.description',
    ];

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
     * Dispatch jobs into the queue
     */
    protected function run(): void
    {
        if (Application::isUnderMaintenance()) {
            $this->getCommandInterface()->getOutput()->error(__('admin.cli.tool.schedular.maintenance.message'));
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
     * Dispatch jobs into the queue
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

    // protected function work(): void
    // {
    //     [$input, $output] = PKPConsoleCommandServiceProvider::getConsoleIOInstances();
        
    //     $scheduleWorkCommand = new ScheduleWorkCommand;
    //     $scheduleWorkCommand->setLaravel(PKPContainer::getInstance());
    //     $scheduleWorkCommand->setInput($input);
    //     $scheduleWorkCommand->setOutput(PKPConsoleCommandServiceProvider::getConsoleOutputStyle());

    //     $scheduleWorkCommand->run($input, $output);
    // }

    // protected function test(): void
    // {

    // }

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
    $tool = new CommandScheduler($argv ?? []);
    $tool->execute();
} catch (Throwable $e) {
    $output = new commandInterface();

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
