<?php

/**
 * @file classes/user/PrivateNotesDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PrivateNotesDAO
 *
 * @ingroup user
 *
 * @see PrivateNote
 *
 * @brief Operations for retrieving and modifying user private notes.
 */

namespace PKP\user;

use PKP\db\DAO;
use PKP\db\DAOResultFactory;

class PrivateNotesDAO extends DAO
{
    /**
     * Construct a new PrivateNote object.
     *
     * @return PrivateNote
     */
    public function newDataObject(): PrivateNote
    {
        return new PrivateNote();
    }

	/**
	 * Retrieve a user private note value.
     *
	 * @param int $journalId
	 * @param int $userId
     *
	 * @return PrivateNote|null
	 */
    public function getPrivateNote(int $journalId, int $userId): PrivateNote|null
    {
		$params = [
            $journalId,
            $userId
        ];
		$result = $this->retrieve(
            'SELECT * FROM user_private_notes WHERE context_id = ? AND user_id = ?',
            $params
        );
		$factory = new DAOResultFactory($result, $this, '_fromRow');
		return $factory->toIterator()->current();
	}

	/**
	 * Set a user private note value.
	 *
	 * @param int $journalId
	 * @param int $userId
	 * @param string $note
	 */
    public function setPrivateNote(int $journalId, int $userId, string $note): void
    {
		$params = [
            $note,
            $journalId,
            $userId
        ];
		$dbPrivateNote = $this->getPrivateNote($journalId, $userId);
		if ($dbPrivateNote) {
			$this->update(
                'UPDATE user_private_notes SET note = ? WHERE context_id = ? AND user_id = ?',
                $params
            );
		} else {
			$this->update(
                'INSERT INTO user_private_notes (note, context_id, user_id) VALUES (?, ?, ?)',
                $params
            );
		}
	}

    /**
     * Internal function to return a PrivateNote object from a row.
     *
     * @param array $row
     *
     * @return PrivateNote
     */
    function _fromRow(array $row): PrivateNote
    {
        $privateNote = $this->newDataObject();

        $privateNote->setId($row['private_note_id']);
        $privateNote->setContextId($row['context_id']);
        $privateNote->setUserId($row['user_id']);
        $privateNote->setNote($row['note']);

        return $privateNote;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\PrivateNotesDAO', '\PrivateNotesDAO');
}
