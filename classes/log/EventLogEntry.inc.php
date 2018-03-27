<?php

/**
 * @file classes/log/EventLogEntry.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EventLogEntry
 * @ingroup log
 * @see EventLogDAO
 *
 * @brief Describes an entry in the event log.
 */

// Information Center events
define('SUBMISSION_LOG_NOTE_POSTED',			0x01000000);
define('SUBMISSION_LOG_MESSAGE_SENT',			0x01000001);

class EventLogEntry extends DataObject {

	//
	// Get/set methods
	//

	/**
	 * Get user ID of user that initiated the event.
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * Set user ID of user that initiated the event.
	 * @param $userId int
	 */
	function setUserId($userId) {
		$this->setData('userId', $userId);
	}

	/**
	 * Get date entry was logged.
	 * @return datestamp
	 */
	function getDateLogged() {
		return $this->getData('dateLogged');
	}

	/**
	 * Set date entry was logged.
	 * @param $dateLogged datestamp
	 */
	function setDateLogged($dateLogged) {
		$this->setData('dateLogged', $dateLogged);
	}

	/**
	 * Get IP address of user that initiated the event.
	 * @return string
	 */
	function getIPAddress() {
		return $this->getData('ipAddress');
	}

	/**
	 * Set IP address of user that initiated the event.
	 * @param $ipAddress string
	 */
	function setIPAddress($ipAddress) {
		$this->setData('ipAddress', $ipAddress);
	}

	/**
	 * Get event type.
	 * @return int
	 */
	function getEventType() {
		return $this->getData('eventType');
	}

	/**
	 * Set event type.
	 * @param $eventType int
	 */
	function setEventType($eventType) {
		$this->setData('eventType', $eventType);
	}

	/**
	 * Get associated type.
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set associated type.
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		$this->setData('assocType', $assocType);
	}

	/**
	 * Get associated ID.
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set associated ID.
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		$this->setData('assocId', $assocId);
	}

	/**
	 * Get custom log message (either locale key or literal string).
	 * @return string
	 */
	function getMessage() {
		return $this->getData('message');
	}

	/**
	 * Set custom log message (either locale key or literal string).
	 * @param $message string
	 */
	function setMessage($message) {
		$this->setData('message', $message);
	}

	/**
	 * Get flag indicating whether or not message is translated.
	 * @return boolean
	 */
	function getIsTranslated() {
		return $this->getData('isTranslated');
	}

	/**
	 * Set flag indicating whether or not message is translated.
	 * @param $isTranslated int
	 */
	function setIsTranslated($isTranslated) {
		$this->setData('isTranslated', $isTranslated);
	}

	/**
	 * Get translated message, translating it if necessary.
	 * @param $locale string optional
	 * @param $hideReviewerName boolean optional Don't reveal reviewer names in
	 *  log descriptions.
	 */
	function getTranslatedMessage($locale = null, $hideReviewerName = false) {
		$message = $this->getMessage();
		// If it's already translated, just return the message.
		if ($this->getIsTranslated()) return $message;

		// Otherwise, translate it and include parameters.
		if ($locale === null) $locale = AppLocale::getLocale();

		$params = array_merge($this->_data, $this->getParams());
		unset($params['params']); // Clean up for translate call

		if ($hideReviewerName) {
			$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
			// Reviewer activity log entries (assigning, accepting, declining)
			if (isset($params['reviewerName'])) {
				$blindAuthor = true;
				if (isset($params['reviewAssignmentId'])) {
					$reviewAssignment = $reviewAssignmentDao->getById($params['reviewAssignmentId']);
					if ($reviewAssignment && !in_array($reviewAssignment->getReviewMethod(), array(SUBMISSION_REVIEW_METHOD_BLIND, SUBMISSION_REVIEW_METHOD_DOUBLEBLIND))) {
						$blindAuthor = false;
					}
				}
				if ($blindAuthor) {
					$params['reviewerName'] = __('editor.review.anonymousReviewer');
				}
			}
			// Files submitted by reviewers
			if (isset($params['fileStage']) && $params['fileStage'] === SUBMISSION_FILE_REVIEW_ATTACHMENT) {
				assert(isset($params['fileId']) && isset($params['submissionId']));
				$blindAuthor = true;
				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
				$submissionFile = $submissionFileDao->getLatestRevision($params['fileId']);
				if ($submissionFile && $submissionFile->getAssocType() === ASSOC_TYPE_REVIEW_ASSIGNMENT) {
					$reviewAssignment = $reviewAssignmentDao->getById($submissionFile->getAssocId());
					if ($reviewAssignment && !in_array($reviewAssignment->getReviewMethod(), array(SUBMISSION_REVIEW_METHOD_BLIND, SUBMISSION_REVIEW_METHOD_DOUBLEBLIND))) {
						$blindAuthor = false;
					}
				}
				if (isset($params['username']) && $blindAuthor) {
					if (isset($params['username'])) {
						$params['username'] = __('editor.review.anonymousReviewer');
					}
					if (isset($params['originalFileName'])) {
						$params['originalFileName'] = '';
					}
				}
			}
		}


		return __($message, $params, $locale);
	}

	/**
	 * Get custom log message parameters.
	 * @return array
	 */
	function getParams() {
		return $this->getData('params');
	}

	/**
	 * Set custom log message parameters.
	 * @param $params array
	 */
	function setParams($params) {
		$this->setData('params', $params);
	}

	/**
	 * Return the full name of the user.
	 * @return string
	 */
	function getUserFullName() {
		$userFullName =& $this->getData('userFullName');
		if(!isset($userFullName)) {
			$userDao = DAORegistry::getDAO('UserDAO');
			$userFullName = $userDao->getUserFullName($this->getUserId(), true);
		}

		return ($userFullName ? $userFullName : '');
	}

	/**
	 * Return the email address of the user.
	 * @return string
	 */
	function getUserEmail() {
		$userEmail =& $this->getData('userEmail');

		if(!isset($userEmail)) {
			$userDao = DAORegistry::getDAO('UserDAO');
			$userEmail = $userDao->getUserEmail($this->getUserId(), true);
		}

		return ($userEmail ? $userEmail : '');
	}
}

?>
