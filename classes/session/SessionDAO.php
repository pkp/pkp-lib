<?php

/**
 * @file classes/session/SessionDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SessionDAO
 *
 * @ingroup session
 *
 * @see Session
 *
 * @brief Operations for retrieving and modifying Session objects.
 */

namespace PKP\session;

use Illuminate\Support\Facades\DB;
use PKP\db\DAO;

class SessionDAO extends DAO
{
    /**
     * Instantiate and return a new data object.
     */
    public function newDataObject()
    {
        return new Session();
    }

    /**
     * Retrieve a session by ID.
     *
     * @param string $sessionId
     *
     * @return Session
     */
    public function getSession($sessionId)
    {
        $result = $this->retrieve('SELECT * FROM sessions WHERE session_id = ?', [$sessionId]);

        if ($row = (array) $result->current()) {
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
            return $session;
        }

        return null;
    }

    /**
     * Insert a new session.
     *
     * @param Session $session
     */
    public function insertObject($session)
    {
        $this->update(
            'INSERT INTO sessions
				(session_id, ip_address, user_agent, created, last_used, remember, data, domain)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $session->getId(),
                $session->getIpAddress(),
                substr($session->getUserAgent(), 0, 255),
                (int) $session->getSecondsCreated(),
                (int) $session->getSecondsLastUsed(),
                $session->getRemember() ? 1 : 0,
                $session->getSessionData(),
                $session->getDomain()
            ]
        );
    }

    /**
     * Update an existing session.
     *
     * @param Session $session
     *
     * @return int Number of affected rows
     */
    public function updateObject($session)
    {
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
            [
                $session->getUserId() == '' ? null : (int) $session->getUserId(),
                $session->getIpAddress(),
                substr($session->getUserAgent(), 0, 255),
                (int) $session->getSecondsCreated(),
                (int) $session->getSecondsLastUsed(),
                $session->getRemember() ? 1 : 0,
                $session->getSessionData(),
                $session->getDomain(),
                $session->getId()
            ]
        );
    }

    /**
     * Delete a session.
     *
     * @param Session $session
     */
    public function deleteObject($session)
    {
        $this->deleteById($session->getId());
    }

    /**
     * Delete a session by ID.
     *
     * @param string $sessionId
     */
    public function deleteById($sessionId)
    {
        $this->update('DELETE FROM sessions WHERE session_id = ?', [$sessionId]);
    }

    /**
     * Delete sessions by user ID.
     *
     * @param string $userId
     */
    public function deleteByUserId($userId)
    {
        $this->update(
            'DELETE FROM sessions WHERE user_id = ?',
            [(int) $userId]
        );
    }

    /**
     * Delete all sessions older than the specified time.
     *
     * @param int $lastUsed cut-off time in seconds for not-remembered sessions
     * @param int $lastUsedRemember optional, cut-off time in seconds for remembered sessions
     */
    public function deleteByLastUsed($lastUsed, $lastUsedRemember = 0)
    {
        if ($lastUsedRemember == 0) {
            $this->update(
                'DELETE FROM sessions WHERE (last_used < ? AND remember = 0)',
                [(int) $lastUsed]
            );
        } else {
            $this->update(
                'DELETE FROM sessions WHERE (last_used < ? AND remember = 0) OR (last_used < ? AND remember = 1)',
                [(int) $lastUsed, (int) $lastUsedRemember]
            );
        }
    }

    /**
     * Delete all sessions.
     */
    public function deleteAllSessions()
    {
        $this->update('DELETE FROM sessions');
    }

    /**
     * Check if a session exists with the specified ID.
     *
     * @param string $sessionId
     *
     * @return bool
     */
    public function sessionExistsById($sessionId)
    {
        $result = $this->retrieve('SELECT COUNT(*) AS row_count FROM sessions WHERE session_id = ?', [$sessionId]);
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
    }

    /**
     * Delete given user's all sessions or except for the given session id
     *
     * @param int                   $userId     The target user id for whom to invalidate sessions
     *
     */
    public function deleteUserSessions(int $userId, string $excludableSessionId = null)
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->when($excludableSessionId, fn ($query) => $query->where('session_id', '<>', $excludableSessionId))
            ->delete();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\session\SessionDAO', '\SessionDAO');
}
