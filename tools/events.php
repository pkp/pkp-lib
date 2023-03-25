<?php

declare(strict_types=1);

/**
 * @file tools/events.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class commandEvents
 * @ingroup tools
 *
 * @brief CLI tool to list all events registered on the system
 */

namespace PKP\tools\event;

use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Console\OutputStyle;
use PKP\cache\CacheManager;
use PKP\cliTool\CommandLineTool;

use PKP\core\PKPContainer;
use PKP\core\PKPEventServiceProvider;

use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

use Throwable;

define('APP_ROOT', dirname(__FILE__, 4));
require_once APP_ROOT . '/tools/bootstrap.php';

class commandEvents extends CommandLineTool
{
    use InteractsWithIO;

    protected const AVAILABLE_OPTIONS = [
        'cache' => 'Create an Events cached version',
        'clear' => 'Clear the Events cached version',
        'list' => 'List all events on the system',
        'usage' => 'Display the command usage'
    ];

    /**
     * @var null|string Which option will be call?
     */
    protected $option = null;

    /**
     * Constructor
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        array_shift($argv);

        $this->option = array_shift($argv);

        if (!$this->option) {
            throw new CommandNotFoundException(
                'Option could not be empty! Check the usage method.',
                array_keys(self::AVAILABLE_OPTIONS)
            );
        }

        $output = new OutputStyle(
            new StringInput(''),
            new StreamOutput(fopen('php://stdout', 'w'))
        );

        $this->setOutput($output);
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        $this->line('<comment>Usage:</comment>');
        $this->line('command [arguments]' . PHP_EOL);
        $this->line('<comment>Available commands for the "events" namespace:</comment>');

        $width = $this->getColumnWidth(array_keys(self::AVAILABLE_OPTIONS));

        foreach (self::AVAILABLE_OPTIONS as $commandName => $description) {
            $spacingWidth = $width - Helper::width($commandName);
            $this->line(
                sprintf(
                    '  <info>%s</info>%s%s',
                    $commandName,
                    str_repeat(' ', $spacingWidth),
                    $description
                )
            );
        }
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
     * List all events registered
     */
    protected function list(): void
    {
        $eventServiceProvider = app()
            ->makeWith(
                PKPEventServiceProvider::class,
                ['app' => PKPContainer::getInstance()]
            );

        $events = [];

        $rawEvents = $eventServiceProvider->getEvents();

        foreach ($rawEvents as $event => $listeners) {
            $events[] = [$event, implode(', ', $listeners)];
        }

        $this->table(['Event', 'Listeners'], $events);
    }

    /**
     * Clean the Event cached file
     */
    protected function clear(): void
    {
        $cacheManager = CacheManager::getManager();
        $cacheManager->flush('event');

        $this->getOutput()->success('Cache cleared!');
    }

    /**
     * Create an Event cached file
     */
    protected function cache(): void
    {
        // Cleaning old file
        $cacheManager = CacheManager::getManager();
        $cacheManager->flush('event');

        $eventServiceProvider = app()
            ->makeWith(
                PKPEventServiceProvider::class,
                ['app' => PKPContainer::getInstance()]
            );

        // Forcing the cache rebuild
        $eventServiceProvider->getEvents();

        $filePath = $cacheManager->getFileCachePath();
        $files = glob("{$filePath}/fc-event*.php");
        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->getOutput()->error('Cached file was not created');

                return;
            }
        }

        $this->getOutput()->success('Cache rebuilt!');
    }

    /**
     * Parse and execute list event command
     */
    public function execute()
    {
        if (!isset(self::AVAILABLE_OPTIONS[$this->option])) {
            throw new CommandNotFoundException(
                sprintf('Option "%s" does not exist.', $this->option),
                array_keys(self::AVAILABLE_OPTIONS)
            );
        }

        $this->{$this->option}();
    }
}

try {
    $tool = new commandEvents($argv ?? []);
    $tool->execute();
} catch (Throwable $e) {
    if ($e instanceof CommandNotFoundException) {
        $output = new OutputStyle(
            new StringInput(''),
            new StreamOutput(fopen('php://stdout', 'w'))
        );

        $alternatives = $e->getAlternatives();

        $message = 'Did you mean this?';
        if (count($alternatives) > 1) {
            $message = 'Did you mean one of those?';
        }

        $message = $message . PHP_EOL . implode(PHP_EOL, $alternatives);

        $output->block(
            [$e->getMessage(), $message],
            null,
            'fg=white;bg=red',
            ' ',
            true
        );

        return;
    }

    throw $e;
}
