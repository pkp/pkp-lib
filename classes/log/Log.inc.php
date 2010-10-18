<?php

/**
 * @defgroup log
 */

/**
 * @file classes/log/LogManager.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Log
 * @ingroup log
 *
 * @brief Static class for adding / accessing log entries.
 */


class LogManager {
	/**
	 * Add an event log entry to this article.
	 * @param $articleId int
	 * @param $entry ArticleEventLogEntry
	 */
	function logEventEntry($articleId, &$entry) {
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$journalId = $articleDao->getArticleJournalId($articleId);

		if (!$journalId) {
			// Invalid article
			return false;
		}

		$settingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
		if (!$settingsDao->getSetting($journalId, 'articleEventLog')) {
			// Event logging is disabled
			return false;
		}

		// Add the entry
		$entry->setArticleId($articleId);

		if ($entry->getUserId() == null) {
			$user =& Request::getUser();
			$entry->setUserId($user == null ? 0 : $user->getId());
		}

		$logDao =& DAORegistry::getDAO('ArticleEventLogDAO');
		return $logDao->insertLogEntry($entry);
	}

	/**
	 * Add a new event log entry with the specified parameters, at the default log level
	 * @param $articleId int
	 * @param $eventType int
	 * @param $assocType int
	 * @param $assocId int
	 * @param $messageKey string
	 * @param $messageParams array
	 */
	function logEvent($articleId, $eventType, $assocType = 0, $assocId = 0, $messageKey = null, $messageParams = array()) {
		$entry = new ArticleEventLogEntry();
		$entry->setEventType($eventType);
		$entry->setAssocType($assocType);
		$entry->setAssocId($assocId);

		if (isset($messageKey)) {
			$entry->setLogMessage($messageKey, $messageParams);
		}

		return ArticleLog::logEventEntry($articleId, $entry);
	}

	/**
	 * Add an email log entry to this article.
	 * @param $articleId int
	 * @param $entry ArticleEmailLogEntry
	 */
	function logEmailEntry($articleId, &$entry) {
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$journalId = $articleDao->getArticleJournalId($articleId);

		if (!$journalId) {
			// Invalid article
			return false;
		}

		$settingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
		if (!$settingsDao->getSetting($journalId, 'articleEmailLog')) {
			// Email logging is disabled
			return false;
		}

		// Add the entry
		$entry->setArticleId($articleId);

		if ($entry->getSenderId() == null) {
			$user =& Request::getUser();
			$entry->setSenderId($user == null ? 0 : $user->getId());
		}

		$logDao =& DAORegistry::getDAO('ArticleEmailLogDAO');
		return $logDao->insertLogEntry($entry);
	}
}

?>
