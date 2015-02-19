<?php

/**
 * @file classes/session/SessionDAO.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SessionDAO
 * @ingroup session
 * @see Session
 *
 * @brief Operations for retrieving and modifying Session objects.
 */


import('lib.pkp.classes.session.Session');

class SessionDAO extends DAO {
	/**
	 * Constructor
	 */
	function SessionDAO() {
		parent::DAO();
	}

	/**
	 * Instantiate and return a new data object.
	 */
	function newDataObject() {
		return new Session();
	}

	/**
	 * Retrieve a session by ID.
	 * @param $sessionId string
	 * @return Session
	 */
	function &getSession($sessionId) {
		$result =& $this->retrieve(
			'SELECT * FROM sessions WHERE session_id = ?',
			array($sessionId)
		);

		$session = null;
		if ($result->RecordCount() != 0) {
			$row =& $result->GetRowAssoc(false);

			$session = $this->newDataObject();
			$session->setId($row['session_id']);
			$session->setUserId($row['user_id']);
			$session->setIpAddress($row['ip_address']);
			$session->setUserAgent($row['user_agent']);
			$session->setSecondsCreated($row['created']);
			$session->setSecondsLastUsed($row['last_used']);
			$session->setRemember($row['remember']);
			$session->setSessionData($row['data']);
			$session->setDomain($row['domain']);
		}

		$result->Close();
		unset($result);

		return $session;
	}

	/**
	 * Insert a new session.
	 * @param $session Session
	 */
	function insertSession(&$session) {
		return $this->update(
			'INSERT INTO sessions
				(session_id, ip_address, user_agent, created, last_used, remember, data, domain)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?)',
			array(
				$session->getId(),
				$session->getIpAddress(),
				substr($session->getUserAgent(), 0, 255),
				(int) $session->getSecondsCreated(),
				(int) $session->getSecondsLastUsed(),
				$session->getRemember() ? 1 : 0,
				$session->getSessionData(),
				$session->getDomain()
			)
		);
	}

	/**
	 * Update an existing session.
	 * @param $session Session
	 */
	function updateObject(&$session) {
		return $this->update(
			'UPDATE sessions
				SET
					user_id = ?,
					ip_address = ?,
					user_agent = ?,
					created = ?,
					last_used = ?,
					remember = ?,
					data = ?,
					domain = ?
				WHERE session_id = ?',
			array(
				$session->getUserId()==''?null:(int) $session->getUserId(),
				$session->getIpAddress(),
				substr($session->getUserAgent(), 0, 255),
				(int) $session->getSecondsCreated(),
				(int) $session->getSecondsLastUsed(),
				$session->getRemember() ? 1 : 0,
				$session->getSessionData(),
				$session->getDomain(),
				$session->getId()
			)
		);
	}

	function updateSession(&$session) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->updateObject($session);
	}

	/**
	 * Delete a session.
	 * @param $session Session
	 */
	function deleteObject(&$session) {
		return $this->deleteSessionById($session->getId());
	}

	function deleteSession(&$session) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteObject($session);
	}

	/**
	 * Delete a session by ID.
	 * @param $sessionId string
	 */
	function deleteSessionById($sessionId) {
		return $this->update(
			'DELETE FROM sessions WHERE session_id = ?',
			array($sessionId)
		);
	}

	/**
	 * Delete sessions by user ID.
	 * @param $userId string
	 */
	function deleteSessionsByUserId($userId) {
		return $this->update(
			'DELETE FROM sessions WHERE user_id = ?',
			array((int) $userId)
		);
	}

	/**
	 * Delete all sessions older than the specified time.
	 * @param $lastUsed int cut-off time in seconds for not-remembered sessions
	 * @param $lastUsedRemember int optional, cut-off time in seconds for remembered sessions
	 */
	function deleteSessionByLastUsed($lastUsed, $lastUsedRemember = 0) {
		if ($lastUsedRemember == 0) {
			return $this->update(
				'DELETE FROM sessions WHERE (last_used < ? AND remember = 0)',
				array((int) $lastUsed)
			);
		} else {
			return $this->update(
				'DELETE FROM sessions WHERE (last_used < ? AND remember = 0) OR (last_used < ? AND remember = 1)',
				array((int) $lastUsed, (int) $lastUsedRemember)
			);
		}
	}

	/**
	 * Delete all sessions.
	 */
	function deleteAllSessions() {
		return $this->update('DELETE FROM sessions');
	}

	/**
	 * Check if a session exists with the specified ID.
	 * @param $sessionId string
	 * @return boolean
	 */
	function sessionExistsById($sessionId) {
		$result =& $this->retrieve(
			'SELECT COUNT(*) FROM sessions WHERE session_id = ?',
			array($sessionId)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}
}

?>
