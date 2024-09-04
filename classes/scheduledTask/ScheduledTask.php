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

use PKP\config\Config;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\core\Core;
use PKP\file\PrivateFileManager;

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
     * Constructor.
     * 
     * @param array $args The task arguments
     */
    public function __construct(private array $args = [])
    {
        $this->processId = uniqid();

        // Check the scheduled task execution log folder.
        $fileMgr = new PrivateFileManager();

        $scheduledTaskFilesPath = realpath($fileMgr->getBasePath()) . '/' . ScheduledTaskHelper::SCHEDULED_TASK_EXECUTION_LOG_DIR;
        $classNameParts = explode('\\', $this::class); // Separate namespace info from class name
        
        $this->executionLogFile = "{$scheduledTaskFilesPath}/"
            . end($classNameParts)
            . '-'
            . $this->getProcessId()
            . '-' 
            . date('Ymd')
            . '.log';

        if (!$fileMgr->fileExists($scheduledTaskFilesPath, 'dir')) {
            $success = $fileMgr->mkdirtree($scheduledTaskFilesPath);
            if (!$success) {
                // files directory wrong configuration?
                assert(false);
                $this->executionLogFile = null;
            }
        }
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
            $this->helper = new ScheduledTaskHelper;
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
     * Add an entry into the execution log.
     *
     * @param string $message A translated message.
     * @param ?string $type One of the ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE... constants
     */
    public function addExecutionLogEntry(string $message, ?string $type = null): void
    {
        $logFile = $this->executionLogFile;

        if (!$message) {
            return;
        }
        $date = '[' . Core::getCurrentDate() . '] ';

        if ($type) {
            $log = $date . '[' . __($type) . '] ' . $message;
        } else {
            $log = $date . $message;
        }

        $fp = fopen($logFile, 'ab');
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $log . PHP_EOL);
            flock($fp, LOCK_UN);
        } else {
            throw new \Exception("Couldn't lock the file.");
        }
        fclose($fp);
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

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\scheduledTask\ScheduledTask', '\ScheduledTask');
}
