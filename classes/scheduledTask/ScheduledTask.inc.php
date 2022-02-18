<?php

/**
 * @file classes/scheduledTask/ScheduledTask.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTask
 * @ingroup scheduledTask
 *
 * @see ScheduledTaskDAO
 *
 * @brief Base class for executing scheduled tasks.
 * All scheduled task classes must extend this class and implement execute().
 */

namespace PKP\scheduledTask;

use PKP\config\Config;
use PKP\core\Core;

use PKP\file\PrivateFileManager;

abstract class ScheduledTask
{
    /** @var array task arguments */
    private $_args;

    /** @var string? This process id. */
    private $_processId = null;

    /** @var string File path in which execution log messages will be written. */
    private $_executionLogFile;

    /** @var ScheduledTaskHelper */
    private $_helper;


    /**
     * Constructor.
     *
     * @param array $args
     */
    public function __construct($args = [])
    {
        $this->_args = $args;
        $this->_processId = uniqid();

        // Check the scheduled task execution log folder.
        $fileMgr = new PrivateFileManager();

        $scheduledTaskFilesPath = realpath($fileMgr->getBasePath()) . '/' . ScheduledTaskHelper::SCHEDULED_TASK_EXECUTION_LOG_DIR;
        $this->_executionLogFile = "$scheduledTaskFilesPath/" . str_replace(' ', '', $this->getName()) .
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


    //
    // Protected methods.
    //
    /**
     * Get this process id.
     *
     * @return int
     */
    public function getProcessId()
    {
        return $this->_processId;
    }

    /**
     * Get scheduled task helper object.
     *
     * @return ScheduledTaskHelper
     */
    public function getHelper()
    {
        if (!$this->_helper) {
            $this->_helper = new ScheduledTaskHelper();
        }
        return $this->_helper;
    }

    /**
     * Get the scheduled task name. Override to
     * define a custom task name.
     *
     * @return string
     */
    public function getName()
    {
        return __('admin.scheduledTask');
    }

    /**
     * Add an entry into the execution log.
     *
     * @param string $message A translated message.
     * @param string $type (optional) One of the ScheduledTaskHelper
     * SCHEDULED_TASK_MESSAGE_TYPE... constants.
     */
    public function addExecutionLogEntry($message, $type = null)
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
            fatalError("Couldn't lock the file.");
        }
        fclose($fp);
    }


    //
    // Protected abstract methods.
    //
    /**
     * Implement this method to execute the task actions.
     *
     * @return bool true iff success
     */
    abstract protected function executeActions();


    //
    // Public methods.
    //
    /**
     * Make sure the execution process follow the required steps.
     * This is not the method one should extend to implement the
     * task actions, for this see ScheduledTask::executeActions().
     *
     * @return bool Whether or not the task was succesfully
     * executed.
     */
    public function execute()
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
