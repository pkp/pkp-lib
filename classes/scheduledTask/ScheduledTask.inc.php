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

define('SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED', 'common.completed');
define('SCHEDULED_TASK_MESSAGE_TYPE_ERROR', 'common.error');
define('SCHEDULED_TASK_MESSAGE_TYPE_WARNING', 'common.warning');

class ScheduledTask {

	/** @var array task arguments */
	var $args;

	/** @var string Site admin email. */
	var $_adminEmail;

	/** @var string Site admin name. */
	var $_adminName;

	/** @var string? This process id. */
	var $_processId = null;

	/**
	 * Constructor.
	 * @param $args array
	 */
	function ScheduledTask($args = array()) {
		$this->args = $args;
		$this->_newProcessId();

		$siteDao =& DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
		$site =& $siteDao->getSite(); /* @var $site Site */
		$this->_adminEmail = $site->getLocalizedContactEmail();
		$this->_adminName = $site->getLocalizedContactName();

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_ADMIN, LOCALE_COMPONENT_PKP_COMMON);
	}


	//
	// Getters and setters.
	//
	/**
	* Get this process id.
	* @return int
	*/
	function getProcessId() {
		return $this->_processId;
	}


	//
	// Public template methods.
	//
	/**
	 * Fallback method in case task does not implement execute method.
	 */
	function execute() {
		fatalError("ScheduledTask does not implement execute()!\n");
	}


	//
	// Protected methods.
	//
	/**
	 * Get the scheduled task name. Override to
	 * define a custom task name.
	 * @return string
	 */
	function getName() {
		return __('admin.scheduledTask');
	}

	/**
	 * Notify the site administrator via email about
	 * the task process.
	 * @param $type string One of the
	 * SCHEDULED_TASK_MESSAGE_TYPE... constants
	 * @param $message string
	 * @param $subject string (optional)
	 */
	function notify($type, $message, $subject = '') {
		// Check type.
		$taskMessageTypes = $this->_getAllMessageTypes();
		if (!in_array($type, $taskMessageTypes)) {
			assert(false);
		}

		// Instantiate the email to the admin.
		import('lib.pkp.classes.mail.Mail');
		$mail = new Mail();

		// Recipient
		$mail->addRecipient($this->_adminEmail, $this->_adminName);

		// The message
		if ($subject == '') {
			$subject = $this->getName() . ' - ' . $this->getProcessId() . ' - ' . __($type);
		}

		$mail->setSubject($subject);
		$mail->setBody($message);

		return $mail->send();
	}


	//
	// Private helper methods.
	//
	/**
	* Set a new process id.
	*/
	function _newProcessId() {
		$this->_processId = uniqid();
	}

	/**
	 * Get all schedule task message types.
	 * @return array
	 */
	function _getAllMessageTypes() {
		return array(
			SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED,
			SCHEDULED_TASK_MESSAGE_TYPE_ERROR,
			SCHEDULED_TASK_MESSAGE_TYPE_WARNING
		);
	}
}

?>
