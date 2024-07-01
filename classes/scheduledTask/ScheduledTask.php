<?php

/**
 * @file classes/scheduledTask/ScheduledTask.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTask
 *
 * @ingroup scheduledTask
 *
 * @see
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
     * Task arguments
     */
    private array $_args;

    /**
     * This process id
     */
    private ?string $_processId = null;

    /**
     * File path in which execution log messages will be written.
     */
    private string $_executionLogFile;

    /** 
     * The schedule task helper
     */
    private ?ScheduledTaskHelper $_helper = null;

    /**
     * Constructor.
     */
    public function __construct(array $args = [])
    {
        $this->_args = $args;
        $this->_processId = uniqid();

        // Check the scheduled task execution log folder.
        $fileMgr = new PrivateFileManager();

        $scheduledTaskFilesPath = realpath($fileMgr->getBasePath()) . '/' . ScheduledTaskHelper::SCHEDULED_TASK_EXECUTION_LOG_DIR;
        $classNameParts = explode('\\', $this::class); // Separate namespace info from class name
        $this->_executionLogFile = "{$scheduledTaskFilesPath}/" . end($classNameParts) .
            '-' . $this->getProcessId() . '-' . date('Ymd') . '.log';
        if (!$fileMgr->fileExists($scheduledTaskFilesPath, 'dir')) {
            $success = $fileMgr->mkdirtree($scheduledTaskFilesPath);
            if (!$success) {
                // files directory wrong configuration?
                assert(false);
                $this->_executionLogFile = null;
            }
        }
    }

    /**
     * Get this process id.
     */
    public function getProcessId(): ?string
    {
        return $this->_processId;
    }

    /**
     * Get scheduled task helper object.
     */
    public function getHelper(): ScheduledTaskHelper
    {
        if (!$this->_helper) {
            $this->_helper = new ScheduledTaskHelper;
        }
        
        return $this->_helper;
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
     * @param string        $message    A translated message.
     * @param string|null   $type       One of the ScheduledTaskHelper SCHEDULED_TASK_MESSAGE_TYPE... constants
     */
    public function addExecutionLogEntry(string $message, ?string $type = null): void
    {
        $logFile = $this->_executionLogFile;

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
     * @return bool true if succeed
     */
    abstract protected function executeActions(): bool;

    /**
     * Make sure the execution process follow the required steps.
     * This is not the method one should extend to implement the
     * task actions, for this see ScheduledTask::executeActions().
     *
     * @return bool Whether or not the task was succesfully executed.
     */
    public function execute(): bool
    {
        $this->addExecutionLogEntry(Config::getVar('general', 'base_url'));
        $this->addExecutionLogEntry(__('admin.scheduledTask.startTime'), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        $result = $this->executeActions();

        $this->addExecutionLogEntry(__('admin.scheduledTask.stopTime'), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        $helper = $this->getHelper();
        $helper->notifyExecutionResult($this->_processId, $this->getName(), $result, $this->_executionLogFile);

        return $result;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\scheduledTask\ScheduledTask', '\ScheduledTask');
}
