<?php

/**
 * @file classes/user/PrivateNote.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PrivateNote
 *
 * @ingroup user
 *
 * @see PrivateNotesDAO
 *
 * @brief Basic class describing user private note existing in the system.
 */

namespace PKP\user;

use PKP\core\DataObject;

class PrivateNote extends DataObject
{
	/**
	 * Get private note ID.
     *
	 * @return int
	 */
    public function getId(): int
    {
		return $this->getData('id');
	}

	/**
	 * Set private note ID.
     *
	 * @param $id int
	 */
    public function setId($id): void
    {
		$this->setData('id', $id);
	}

	/**
	 * Get private note context ID.
     *
	 * @return int
	 */
    public function getContextId(): int
    {
		return $this->getData('contextId');
	}

	/**
	 * Set private note context ID.
     *
	 * @param $contextId int
	 */
    public function setContextId(int $contextId): void
    {
		$this->setData('contextId', $contextId);
	}

	/**
	 * Get private note user ID.
     *
	 * @return int
	 */
    public function getUserId(): int
    {
		return $this->getData('userId');
	}

	/**
	 * Set private note user ID.
     *
	 * @param $userId int
	 */
    public function setUserId(int $userId): void
    {
		$this->setData('userId', $userId);
	}


	/**
	 * Get private note value.
     *
	 * @return string
	 */
    public function getNote(): string
    {
		return $this->getData('note');
	}

	/**
	 * Set private note value.
     *
	 * @param $note string
	 */
    public function setNote(string $note): void
    {
		$this->setData('note', $note);
	}
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\PrivateNote', '\PrivateNote');
}
