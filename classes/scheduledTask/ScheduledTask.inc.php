<?php

/**
 * @file classes/scheduledTask/ScheduledTask.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTask
 * @ingroup scheduledTask
 * @see ScheduledTaskDAO
 *
 * @brief Base class for executing scheduled tasks.
 * All scheduled task classes must extend this class and implement execute().
 */

import('lib.pkp.classes.scheduledTask.ScheduledTaskHelper');

class ScheduledTask {

	/** @var array task arguments */
	var $_args;

	/** @var string? This process id. */
	var $_processId = null;

	/** @var array Messages log about the execution process */
	var $_executionLog;

	/** @var ScheduledTaskHelper */
	var $_helper;


	/**
	 * Constructor.
	 * @param $args array
	 */
	function ScheduledTask($args = array()) {
		$this->_args = $args;
		$this->_processId = uniqid();
		$this->_executionLog = array();

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_ADMIN, LOCALE_COMPONENT_PKP_COMMON);
	}


	//
	// Protected methods.
	//
	/**
	 * Get this process id.
	 * @return int
	 */
	function getProcessId() {
		return $this->_processId;
	}

	/**
	 * Get scheduled task helper object.
	 * @return ScheduledTaskHelper
	 */
	function &getHelper() {
		if (!$this->_helper) $this->_helper =& new ScheduledTaskHelper();
		return $this->_helper;
	}

	/**
	 * Get the scheduled task name. Override to
	 * define a custom task name.
	 * @return string
	 */
	function getName() {
		return __('admin.scheduledTask');
	}

	/**
	 * Add an entry into the execution log.
	 * @param $message string A translated message.
	 * @param $type string (optional) One of the ScheduledTaskHelper
	 * SCHEDULED_TASK_MESSAGE_TYPE... constants.
	 */
	function addExecutionLogEntry($message, $type = null) {
		$log = $this->_executionLog;

		if (!$message) return;

		if ($type) {
			$log[] = '[' . Core::getCurrentDate() . '] ' . '[' . __($type) . '] ' . $message;
		} else {
			$log[] = $message;
		}

		$this->_executionLog = $log;
	}


	//
	// Protected abstract methods.
	//
	/**
	 * Implement this method to execute the task actions.
	 */
	function executeActions() {
		// In case task does not implement it.
		fatalError("ScheduledTask does not implement executeActions()!\n");
	}


	//
	// Public methods.
	//
	/**
	 * Make sure the execution process follow the required steps.
	 * This is not the method one should extend to implement the
	 * task actions, for this see ScheduledTask::executeActions().
	 * @param boolean $notifyAdmin optional Whether or not the task
	 * will notify the site administrator about errors, warnings or
	 * completed process.
	 * @return boolean Whether or not the task was succesfully
	 * executed.
	 */
	function execute() {
		$this->addExecutionLogEntry(Config::getVar('general', 'base_url'));
		$this->addExecutionLogEntry(__('admin.scheduledTask.startTime'), SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

		$result = $this->executeActions();

		$this->addExecutionLogEntry(__('admin.scheduledTask.stopTime'), SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

		$helper =& $this->getHelper();
		$helper->notifyExecutionResult($this->_processId, $this->getName(), $result, $this->_getLogMessage());

		return $result;
	}


	//
	// Private helper methods.
	//
	/**
	 * Get the execution log as string.
	 * @return string
	 */
	function _getLogMessage() {
		$log = $this->_executionLog;
		$logString = implode(PHP_EOL . PHP_EOL, $log);

		return $logString;
	}
}

?>
