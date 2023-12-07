<?php

/**
 * @file classes/user/UserPrivateNote.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserPrivateNote
 *
 * @ingroup user
 *
 * @see DAO
 *
 * @brief Basic class describing user private note existing in the system.
 */

namespace PKP\userPrivateNote;

use PKP\core\DataObject;

class UserPrivateNote extends DataObject
{
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
