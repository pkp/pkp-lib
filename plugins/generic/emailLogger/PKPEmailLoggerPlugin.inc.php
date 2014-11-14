<?php

/**
 * @file plugins/generic/emailLogger/PKPEmailLoggerPlugin.inc.php
 *
 * Copyright (c) 2013 Simon Fraser University Library
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailLoggerPlugin
 * @ingroup plugins_generic_emailLogger
 *
 * @brief Plugin that will log all emails sent by the system.
 * The main purpose is to be used by tests to verify whether emails were sent or not.
 * When enabled, will prevent any email from going out.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class PKPEmailLoggerPlugin extends GenericPlugin {

	// @var $_notification PKPNotification
	private $_notification;

	// @var $emailLogEntryDao EmailLogDAO
	protected $emailLogEntryDao;

	//
	// Implement methods from PKPPlugin.
	//
	/**
	 * @copydoc LazyLoadPlugin::register()
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if ($success) {
			HookRegistry::register('Mail::send', array($this, 'mailSendCallback'));
			HookRegistry::register('PKPNotificationOperationManager::sendNotificationEmail', array($this, 'recordNotificationDetails'));
		}

		return $success;
	}

	/**
	 * @copydoc LazyLoadPlugin::getName()
	 */
	function getName() {
		return 'emailloggerplugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.emailLogger.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */ 
	function getDescription() {
		return __('plugins.generic.emailLogger.description');
	}

	/**
	 * @copydoc Plugin::isSitePlugin()
	 */
	function isSitePlugin() {
		return true;
	}

	/**
	 * @copydoc Plugin::getPluginPath()
	 */
	function getPluginPath() {
		return 'lib' . DIRECTORY_SEPARATOR . 'pkp' . DIRECTORY_SEPARATOR . parent::getPluginPath();
	}
	
	/**
	 * PKPNotificationOperationManager::sendNotificationMail() callback
	 * to store notification details to be later used in log entry.
	 * @param $hookName string
	 * @args array
	 * @return boolean 
	 */
	function recordNotificationDetails($hookName, $args) {
		$notification = current($args);
		$this->_notification = $notification;

		return false;
	}
	
	/**
	 * Mail send callback that logs all emails sent by the system.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean 
	 */
	function mailSendCallback($hookName, $args) {
		$mail = current($args);
		$request = Application::getRequest();

		// Don't send the email, avoid log entry duplication.
		$returner = true;
		
		if ($mail instanceOf SubmissionMailTemplate) {
			$mail->log($request);
			return $returner;
		}

		// This is an email that is not logged
		// by default. We will use the plugin
		// settings to record this log, so we make sure we
		// don't mess up with the system's log and also that 
		// we can keep this plugin simple.
		$log = $this->getSetting(CONTEXT_ID_NONE, 'emailLog');
		if (!is_array($log)) $log = array();

		$notificationType = null;
		if ($this->_notification) {
			$notificationType = $this->_notification->getType();
			$this->_notification = null;
		}
		
		$log[] = array(
			'dateSent' => time(),
			'notification_type' => $notificationType, 
			'from' => $mail->getFrom(),
			'recipients' => $mail->getRecipients(),
			'subject' => $mail->getSubject(),
			'body' => $mail->getBody()
		);

		$this->updateSetting(CONTEXT_ID_NONE, 'emailLog', $log, 'object'); 	
		return $returner;
	}

	//
	// Public email log interface.
	//
	/**
	 * Check if a log entry exists.
	 * @param $notificationType int The type of the notification
	 * that's the origin of the email.
	 * @param $recipientEmail string
	 * @param $body string|null Part of the body string, if
	 * null, body will not be checked.
	 * @return boolean
	 */
	function exists($notificationType = null, $recipientEmail = null, $body = null) {
		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		// Force the cache object and file refresh. 
		$pluginSettingsDao->getPluginSettings(CONTEXT_ID_NONE, $this->getName());
		$log = $this->getSetting(CONTEXT_ID_NONE, 'emailLog');

		if (!$notificationType && !$recipientEmail && !$body && $log) return true;

		foreach ($log as $entry) {
			if ($notificationType) {
				if ($entry['notification_type'] == $notificationType) {
					if (!$recipientEmail && !$body) return true;
				} else {
					continue;
				}
			}
			if ($recipientEmail) {
				$recipientEmails = $this->_getRecipientEmails($entry['recipients']);
				if (in_array($recipientEmail, $recipientEmails)) {
					if (!$body) return true;
				} else {
					continue;
				}
			}
			if ($body) {
                                if (strpos($entry['body'], $body) !== false) {
                                        return true;
                                } else {
                                        continue;
                                }
                        }
		}

		return false;
	}

	/**
         * Check the presence of an email log by assoc.
         * @param $assocType int 
         * @param $assocId int
         * @param $recipientEmail string
	 * @param $eventType int
         * @param $body string|null Part of the body string, if
         * null, body will not be checked.
	 * @return boolean
         */
        function existsByAssoc($assocType, $assocId, $recipientEmail = null, $eventType = null, $body = null) {
		if ($assocType == ASSOC_TYPE_SUBMISSION) {
			$this->emailLogEntryDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
		}

		$entries = $this->getLogByAssoc($assocType, $assocId);
		if (!$recipientEmail && !$eventType && !$body && $entries) return true;

		foreach ($entries as $entry) {
			if ($recipientEmail) {
				$recipientEmails = $this->_getRecipientEmails($entry->getRecipients());
				if (in_array($recipientEmail, $recipientEmails)) {
					if (!$eventType && !$body) return true;
				} else {
					continue;
				}
			}

			if ($eventType) {
				if ($entry->getEventType() == $eventType) {
					if (!$body) return true;
				} else {
					continue;
				}
			}

			if ($body) {
				if (strpos($entry->getBody(), $body) !== false) {
					return true;
				} else {
					continue;
				}
			}
		}

		return false;
	}


	//
	// Protected methods.
	//
	/**
	 * Get email log entries by assoc, using the current log entry DAO.
	 * If none is set, return empty array.
	 * @param $assocType int 
	 * @param $assocId int
	 * @return array Filled with EmailLogEntry objects or empty.
	 */
	protected function getLogByAssoc($assocType, $assocId) {
		$entries = array();
		if ($this->emailLogEntryDao) {
			$entryFactory = $this->emailLogEntryDao->getByAssoc($assocType, $assocId);
			$entries = $entryFactory->toArray();
		}

		return $entries;	
	}


	//
	// Private helper methods.
	//
	/**
	 * Get all recipient emails from the
	 * passed recipient data.
	 * @param $recipients array
	 * @return array
	 */
	private function _getRecipientEmails($recipients) {
		if (is_string($recipients)) {
			// Email and user name are mixed in string.
			import('lib.pkp.classes.mail.MailTemplate');
			$mail = new Mail();
			$recipientsList = array();
			$recipients = array($recipients);
			$recipients = $mail->processAddresses($recipientsList, $recipients);
		}

		$recipientEmails = array();
		
		foreach ($recipients as $recipient) {
			$recipientEmails[] = $recipient['email'];
		}
		
		return $recipientEmails;
	}
}
