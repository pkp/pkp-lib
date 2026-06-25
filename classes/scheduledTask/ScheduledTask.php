<?php

/**
 * @file classes/scheduledTask/ScheduledTask.php
 *
 * Copyright (c) 2013-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTask
 *
 * @brief Base class for executing scheduled tasks.
 * All scheduled task classes must extend this class and implement execute().
 */

namespace PKP\scheduledTask;

use Illuminate\Support\Facades\Log;
use PKP\config\Config;
use PKP\core\PKPContainer;
use Psr\Log\LogLevel;
use Illuminate\Log\Logger;

abstract class ScheduledTask
{
    /**
     * This process id
     */
    private string $processId;

    /**
     * File path in which execution log messages will be written.
     */
    private string $executionLogFile;

    /**
     * The schedule task helper
     */
    private ScheduledTaskHelper $helper;

    /**
     * On-demand Laravel logger writing to this task's per-process execution log file.
     */
    private ?Logger $logger = null;

    /**
     * Constructor.
     *
     * @param array $args The task arguments
     */
    public function __construct(private array $args = [])
    {
        $this->processId = uniqid();

        // The parent directory is auto-created by Monolog's StreamHandler on first write.
        $this->executionLogFile = PKPContainer::getInstance()->logFilePath(
            $this->getExecutionLogFileName(),
            ScheduledTaskHelper::SCHEDULED_TASK_EXECUTION_LOG_DIR
        );
    }

    /**
     * Get this process id.
     */
    public function getProcessId(): ?string
    {
        return $this->processId;
    }

    /**
     * Get scheduled task helper object.
     */
    public function getHelper(): ScheduledTaskHelper
    {
        if (!isset($this->helper)) {
            $this->helper = new ScheduledTaskHelper();
        }

        return $this->helper;
    }

    /**
     * Get the scheduled task name. Override to define a custom task name.
     */
    public function getName(): string
    {
        return __('admin.scheduledTask');
    }

    /**
     * The task's short (un-namespaced) class name, used for both the log file
     * name and the on-demand channel name.
     */
    private function getShortClassName(): string
    {
        $parts = explode('\\', $this::class);
        return end($parts);
    }

    /**
     * File name for this task's per-process execution log,
     * e.g. "EditorialReminders-66ab12cd9f3e1-20260603.log".
     */
    protected function getExecutionLogFileName(): string
    {
        return $this->getShortClassName() . '-' . $this->getProcessId() . '-' . date('Ymd') . '.log';
    }

    /**
     * Get the on-demand logger that writes to this task's per-process execution
     * log file. Built once per task instance and memoized.
     */
    private function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = Log::build([
                'driver' => 'single',
                'path' => $this->executionLogFile,
                'level' => 'debug',
                'locking' => true,
            ]);
        }

        return $this->logger;
    }

    /**
     * Add an entry into the execution log.
     *
     * @param string $message A translated message.
     * @param ?string $type One of the ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE... constants
     */
    public function addExecutionLogEntry(string $message, ?string $type = null): void
    {
        if (!$message || !$this->executionLogFile) {
            return;
        }

        $level = match ($type) {
            ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR => LogLevel::ERROR,
            ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_WARNING => LogLevel::WARNING,
            ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE => LogLevel::NOTICE,
            default => LogLevel::INFO,
        };

        $this->getLogger()->log($level, $message);
    }

    /**
     * Implement this method to execute the task actions.
     *
     * @return bool true if successful
     */
    abstract protected function executeActions(): bool;

    /**
     * Make sure the execution process follow the required steps.
     * This is not the method one should extend to implement the
     * task actions, for this see ScheduledTask::executeActions().
     *
     * @return bool Whether the task was successfully executed.
     */
    public function execute(): bool
    {
        $this->addExecutionLogEntry(Config::getVar('general', 'base_url'));
        $this->addExecutionLogEntry(__('admin.scheduledTask.startTime'), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        $result = $this->executeActions();

        $this->addExecutionLogEntry(__('admin.scheduledTask.stopTime'), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        $helper = $this->getHelper();
        $helper->notifyExecutionResult($this->processId, $this->getName(), $result, $this->executionLogFile);

        return $result;
    }
}
